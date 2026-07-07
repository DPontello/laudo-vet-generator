'use strict';

/**
 * Frontend do gerador de laudos (vanilla JS).
 *
 * O formulario e DATA-DRIVEN: o objeto ORGAOS espelha docs/referencia/laudo.schema.json.
 * A engine renderiza os campos, aplica "Tudo Normal" (defaults do schema) e monta o
 * payload JSON para POST no endpoint (public/index.php), que devolve o PDF.
 *
 * IDs de campo sao prefixados pelo orgao (CLAUDE.md secao 5): ex. "bexiga_replecao",
 * "bexiga_parede_espessura_cm".
 */

/* ---------- Opcoes reutilizadas ---------- */
const GRAU = [['discreta', 'Discreta'], ['moderada', 'Moderada'], ['acentuada', 'Acentuada']];
const GRAU_OPC = [['', '—'], ['discreta', 'Discreta'], ['moderada', 'Moderada'], ['acentuada', 'Acentuada']];
const LADO = [['esquerdo', 'Esquerdo'], ['direito', 'Direito'], ['bilateral', 'Bilateral']];

/* ---------- Config dos orgaos (espelha o schema) ---------- */
const ORGAOS = [
    { orgao: 'bexiga', titulo: 'Bexiga', avaliavel: true, campos: [
        { t: 'enum', k: 'replecao', label: 'Repleção', opts: GRAU, def: 'moderada' },
        { t: 'group', k: 'parede', label: 'Parede', children: [
            { t: 'enum', k: 'aspecto', label: 'Aspecto', opts: [['normoespessa', 'Normoespessa'], ['espessada', 'Espessada']], def: 'normoespessa' },
            { t: 'num', k: 'espessura_cm', label: 'Espessura (cm)', max: 3 },
        ] },
        { t: 'group', k: 'conteudo', label: 'Conteúdo', children: [
            { t: 'bool', k: 'homogeneo', label: 'Homogêneo', def: true },
            { t: 'group', k: 'sedimento', label: 'Sedimento', children: [
                { t: 'bool', k: 'presente', label: 'Presente', def: false },
                { t: 'enum', k: 'quantidade', label: 'Quantidade', opts: GRAU, def: 'discreta' },
                { t: 'enum', k: 'tipo', label: 'Tipo', opts: [['sedimentos_urinarios', 'Sedimentos urinários'], ['microurolitos', 'Microurólitos']], def: 'sedimentos_urinarios' },
            ] },
        ] },
        { t: 'group', k: 'litiase', label: 'Litíase', children: [
            { t: 'bool', k: 'cistolito_presente', label: 'Cistólito presente', def: false },
            { t: 'num', k: 'cistolito_cm', label: 'Cistólito (cm)', max: 10 },
        ] },
        { t: 'text', k: 'observacoes', label: 'Observações' },
    ] },

    { orgao: 'rins', titulo: 'Rins', avaliavel: true, campos: [
        { t: 'enum', k: 'simetria', label: 'Simetria', opts: [['simetricos', 'Simétricos'], ['assimetricos', 'Assimétricos']], def: 'simetricos' },
        { t: 'num', k: 'medida_esquerdo_cm', label: 'Medida esquerdo (cm)', max: 15 },
        { t: 'num', k: 'medida_direito_cm', label: 'Medida direito (cm)', max: 15 },
        { t: 'enum', k: 'contornos', label: 'Contornos', opts: [['regulares', 'Regulares'], ['irregulares', 'Irregulares']], def: 'regulares' },
        { t: 'enum', k: 'ecogenicidade_cortical', label: 'Ecogenicidade cortical', opts: [['usual', 'Usual'], ['hiperecogenicidade_difusa', 'Hiperecogenicidade difusa']], def: 'usual' },
        { t: 'enum', k: 'ecogenicidade_grau', label: 'Grau da hiperecogenicidade', opts: GRAU_OPC, def: '', opc: true },
        { t: 'enum', k: 'ecotextura', label: 'Ecotextura', opts: [['homogenea', 'Homogênea'], ['heterogenea', 'Heterogênea']], def: 'homogenea' },
        { t: 'enum', k: 'relacao_corticomedular', label: 'Relação corticomedular', opts: [['mantida', 'Mantida'], ['perdida', 'Perdida']], def: 'mantida' },
        { t: 'achados', k: 'achados', label: 'Achados focais' },
        { t: 'bool', k: 'litiase_ausente', label: 'Ausência de litíase/dilatação', def: true },
        { t: 'text', k: 'observacoes', label: 'Observações' },
    ] },

    { orgao: 'adrenais', titulo: 'Adrenais', avaliavel: true, campos: [
        { t: 'group', k: 'esquerda', label: 'Adrenal esquerda', children: adrenalCampos() },
        { t: 'group', k: 'direita', label: 'Adrenal direita', children: adrenalCampos() },
        { t: 'text', k: 'observacoes', label: 'Observações' },
    ] },

    { orgao: 'figado', titulo: 'Fígado', avaliavel: true, campos: [
        { t: 'enum', k: 'tamanho', label: 'Tamanho', opts: [['usual', 'Usual'], ['hepatomegalia_discreta', 'Hepatomegalia discreta'], ['hepatomegalia_moderada', 'Hepatomegalia moderada'], ['hepatomegalia_acentuada', 'Hepatomegalia acentuada']], def: 'usual' },
        { t: 'enum', k: 'bordas', label: 'Bordas', opts: [['afiladas', 'Afiladas'], ['abauladas', 'Abauladas']], def: 'afiladas' },
        { t: 'enum', k: 'contornos', label: 'Contornos', opts: [['regulares', 'Regulares'], ['irregulares', 'Irregulares']], def: 'regulares' },
        { t: 'enum', k: 'ecogenicidade', label: 'Ecogenicidade', opts: [['usual', 'Usual'], ['hiperecogenicidade_difusa', 'Hiperecogenicidade difusa'], ['hipoecogenicidade_difusa', 'Hipoecogenicidade difusa']], def: 'usual' },
        { t: 'enum', k: 'ecogenicidade_grau', label: 'Grau da alteração', opts: GRAU_OPC, def: '', opc: true },
        { t: 'enum', k: 'ecotextura', label: 'Ecotextura', opts: [['homogenea', 'Homogênea'], ['heterogenea', 'Heterogênea'], ['grosseira', 'Grosseira']], def: 'homogenea' },
        { t: 'bool', k: 'sistema_porta_anatomico', label: 'Sistema porta anatômico', def: true },
        { t: 'text', k: 'observacoes', label: 'Observações (achados focais)' },
    ] },

    { orgao: 'vesicula_biliar', titulo: 'Vesícula Biliar', avaliavel: true, campos: [
        { t: 'bool', k: 'parede_normoespessa', label: 'Parede normoespessa', def: true },
        { t: 'num', k: 'espessura_cm', label: 'Espessura (cm)', max: 3 },
        { t: 'bool', k: 'conteudo_anecogenico', label: 'Conteúdo anecogênico', def: true },
        { t: 'group', k: 'lama_biliar', label: 'Lama biliar', children: [
            { t: 'bool', k: 'presente', label: 'Presente', def: false },
            { t: 'enum', k: 'quantidade', label: 'Quantidade', opts: GRAU, def: 'discreta' },
            { t: 'enum', k: 'disposicao', label: 'Disposição', opts: [['suspensao', 'Em suspensão'], ['depositado', 'Depositado']], def: 'suspensao' },
        ] },
        { t: 'bool', k: 'litiase_ausente', label: 'Ausência de litíase/obstrução', def: true },
        { t: 'text', k: 'observacoes', label: 'Observações' },
    ] },

    { orgao: 'baco', titulo: 'Baço', avaliavel: true, campos: [
        { t: 'enum', k: 'tamanho', label: 'Tamanho', opts: [['usual', 'Usual'], ['esplenomegalia_discreta', 'Esplenomegalia discreta'], ['esplenomegalia_moderada', 'Esplenomegalia moderada'], ['esplenomegalia_acentuada', 'Esplenomegalia acentuada']], def: 'usual' },
        { t: 'enum', k: 'bordas', label: 'Bordas', opts: [['afiladas', 'Afiladas'], ['abauladas', 'Abauladas']], def: 'afiladas' },
        { t: 'enum', k: 'contornos', label: 'Contornos', opts: [['regulares', 'Regulares'], ['irregulares', 'Irregulares']], def: 'regulares' },
        { t: 'enum', k: 'ecotextura', label: 'Ecotextura', opts: [['homogenea', 'Homogênea'], ['heterogenea', 'Heterogênea']], def: 'homogenea' },
        { t: 'bool', k: 'vascularizacao_anatomica', label: 'Vascularização anatômica', def: true },
        { t: 'text', k: 'observacoes', label: 'Observações (achados nodulares)' },
    ] },

    { orgao: 'estomago', titulo: 'Estômago', avaliavel: true, campos: [
        { t: 'enum', k: 'conteudo', label: 'Conteúdo', opts: [['gas_e_ingesta', 'Gás e ingesta'], ['gas', 'Gás'], ['vazio', 'Vazio']], def: 'gas_e_ingesta' },
        { t: 'enum', k: 'parede', label: 'Parede', opts: [['normoespessa', 'Normoespessa'], ['espessada', 'Espessada']], def: 'normoespessa' },
        { t: 'num', k: 'espessura_min_cm', label: 'Espessura mín. (cm)', max: 3 },
        { t: 'num', k: 'espessura_max_cm', label: 'Espessura máx. (cm)', max: 3 },
        { t: 'bool', k: 'estratificacao_mantida', label: 'Estratificação mantida', def: true },
        { t: 'text', k: 'observacoes', label: 'Observações' },
    ] },

    { orgao: 'intestinos', titulo: 'Intestinos', avaliavel: true, campos: [
        { t: 'bool', k: 'estratificacao_mantida', label: 'Estratificação mantida', def: true },
        { t: 'enum', k: 'parede', label: 'Parede', opts: [['normoespessa', 'Normoespessa'], ['espessada', 'Espessada']], def: 'normoespessa' },
        { t: 'group', k: 'medidas', label: 'Medidas por segmento (cm)', children: [
            { t: 'num', k: 'duodeno_min_cm', label: 'Duodeno mín.', max: 2 },
            { t: 'num', k: 'duodeno_max_cm', label: 'Duodeno máx.', max: 2 },
            { t: 'num', k: 'jejuno_min_cm', label: 'Jejuno mín.', max: 2 },
            { t: 'num', k: 'jejuno_max_cm', label: 'Jejuno máx.', max: 2 },
            { t: 'num', k: 'colon_min_cm', label: 'Cólon mín.', max: 2 },
            { t: 'num', k: 'colon_max_cm', label: 'Cólon máx.', max: 2 },
        ] },
        { t: 'bool', k: 'peristaltismo_preservado', label: 'Peristaltismo preservado', def: true },
        { t: 'bool', k: 'obstrucao_ausente', label: 'Ausência de obstrução', def: true },
        { t: 'text', k: 'observacoes', label: 'Observações' },
    ] },

    { orgao: 'pancreas', titulo: 'Pâncreas', avaliavel: true, campos: [
        { t: 'enum', k: 'visibilizacao', label: 'Visibilização', opts: [['nao_visibilizado', 'Não visibilizado'], ['parcialmente_visibilizado', 'Parcialmente visibilizado'], ['visibilizado', 'Visibilizado']], def: 'nao_visibilizado' },
        { t: 'enum', k: 'lobo', label: 'Lobo', opts: [['direito', 'Direito'], ['esquerdo', 'Esquerdo']], def: 'direito' },
        { t: 'num', k: 'espessura_cm', label: 'Espessura (cm)', max: 4 },
        { t: 'bool', k: 'ecogenicidade_usual', label: 'Ecogenicidade usual', def: true },
        { t: 'bool', k: 'reatividade_adjacente', label: 'Reatividade adjacente', def: false },
        { t: 'text', k: 'observacoes', label: 'Observações' },
    ] },

    { orgao: 'reprodutor', titulo: 'Sistema Reprodutor', blocos: [
        { k: 'utero_ovarios_ausentes', label: 'Útero e ovários ausentes (OSH)', children: [
            { t: 'num', k: 'coto_uterino_cm', label: 'Coto uterino (cm)', max: 5 },
        ] },
        { k: 'utero', label: 'Útero', children: [
            { t: 'enum', k: 'status', label: 'Status', opts: [['usual', 'Usual'], ['aumentado', 'Aumentado'], ['nao_visibilizado', 'Não visibilizado']], def: 'usual' },
            { t: 'num', k: 'corpo_cm', label: 'Corpo (cm)', max: 10 },
            { t: 'num', k: 'corno_esquerdo_cm', label: 'Corno esquerdo (cm)', max: 10 },
            { t: 'num', k: 'corno_direito_cm', label: 'Corno direito (cm)', max: 10 },
            { t: 'enum', k: 'conteudo', label: 'Conteúdo', opts: [['nenhum', 'Nenhum'], ['anecogenico', 'Anecogênico'], ['particulado', 'Particulado']], def: 'nenhum' },
        ] },
        { k: 'ovarios', label: 'Ovários', children: [
            { t: 'num', k: 'medida_esquerdo_cm', label: 'Esquerdo (cm)', max: 6 },
            { t: 'num', k: 'medida_direito_cm', label: 'Direito (cm)', max: 6 },
            { t: 'enum', k: 'ecotextura', label: 'Ecotextura', opts: [['homogenea', 'Homogênea'], ['heterogenea', 'Heterogênea']], def: 'homogenea' },
            { t: 'enum', k: 'estruturas_anecoicas', label: 'Estruturas anecoicas', opts: [['nenhuma', 'Nenhuma'], ['foliculos', 'Folículos'], ['cistos', 'Cistos']], def: 'nenhuma' },
        ] },
        { k: 'prostata', label: 'Próstata', children: [
            { t: 'enum', k: 'tamanho', label: 'Tamanho', opts: [['usual', 'Usual'], ['aumentada', 'Aumentada']], def: 'usual' },
            { t: 'num', k: 'medida_x_cm', label: 'X (cm)', max: 10 },
            { t: 'num', k: 'medida_y_cm', label: 'Y (cm)', max: 10 },
            { t: 'num', k: 'medida_z_cm', label: 'Z (cm)', max: 10 },
        ] },
        { k: 'testiculos', label: 'Testículos', children: [
            { t: 'enum', k: 'status', label: 'Status', opts: [['presentes', 'Presentes'], ['ausentes_orquiectomia', 'Ausentes (orquiectomia)']], def: 'presentes' },
            { t: 'num', k: 'esquerdo_x_cm', label: 'Esq. X (cm)', max: 8 },
            { t: 'num', k: 'esquerdo_y_cm', label: 'Esq. Y (cm)', max: 8 },
            { t: 'num', k: 'direito_x_cm', label: 'Dir. X (cm)', max: 8 },
            { t: 'num', k: 'direito_y_cm', label: 'Dir. Y (cm)', max: 8 },
        ] },
    ] },

    { orgao: 'cavidade_abdominal', titulo: 'Cavidade Abdominal', avaliavel: true, campos: [
        { t: 'bool', k: 'sem_alteracoes', label: 'Sem alterações (linfonodos/vasos/líquido/massas)', def: true },
        { t: 'group', k: 'linfonodos_aumentados', label: 'Linfonodos aumentados', children: [
            { t: 'bool', k: 'presente', label: 'Presente', def: false },
            { t: 'checks', k: 'grupos', label: 'Grupos', opts: [['abdominais', 'Abdominais'], ['jejunais', 'Jejunais'], ['iliacos_mediais', 'Ilíacos mediais']] },
        ] },
        { t: 'bool', k: 'mesenterio_reativo', label: 'Mesentério reativo', def: false },
        { t: 'group', k: 'liquido_livre', label: 'Líquido livre', children: [
            { t: 'bool', k: 'presente', label: 'Presente', def: false },
            { t: 'enum', k: 'quantidade', label: 'Quantidade', opts: [['irrisoria', 'Irrisória'], ['discreta', 'Discreta'], ['moderada', 'Moderada'], ['acentuada', 'Acentuada']], def: 'discreta' },
            { t: 'enum', k: 'aspecto', label: 'Aspecto', opts: [['anecogenico', 'Anecogênico'], ['particulado', 'Particulado'], ['ecogenico', 'Ecogênico']], def: 'anecogenico' },
            { t: 'enum', k: 'sitio', label: 'Sítio', opts: [['hepatodiafragmatico', 'Hepatodiafragmático'], ['esplenorrenal', 'Esplenorrenal'], ['cistocolico', 'Cistocólico'], ['hepatorrenal', 'Hepatorrenal'], ['disperso', 'Disperso']], def: 'disperso' },
        ] },
        { t: 'group', k: 'hernia', label: 'Hérnia', children: [
            { t: 'bool', k: 'presente', label: 'Presente', def: false },
            { t: 'num', k: 'descontinuidade_cm', label: 'Descontinuidade (cm)', max: 15 },
            { t: 'enum', k: 'regiao', label: 'Região', opts: [['umbilical', 'Umbilical'], ['inguinal_esquerda', 'Inguinal esquerda'], ['inguinal_direita', 'Inguinal direita']], def: 'umbilical' },
        ] },
        { t: 'text', k: 'observacoes', label: 'Observações' },
    ] },
];

function adrenalCampos() {
    return [
        { t: 'enum', k: 'visibilizacao', label: 'Visibilização', opts: [['visibilizada', 'Visibilizada'], ['parcialmente_visibilizada', 'Parcialmente'], ['nao_visibilizada', 'Não visibilizada']], def: 'visibilizada' },
        { t: 'bool', k: 'aumentada', label: 'Aumentada', def: false },
        { t: 'num', k: 'polo_caudal_cm', label: 'Polo caudal (cm)', max: 5 },
        { t: 'num', k: 'polo_cranial_cm', label: 'Polo cranial (cm)', max: 5 },
        { t: 'num', k: 'comprimento_cm', label: 'Comprimento (cm)', max: 5 },
    ];
}

/* ---------- DOM helpers ---------- */
function el(tag, cls, txt) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (txt != null) e.textContent = txt;
    return e;
}

/* ---------- Renderizacao ---------- */
function renderNode(node, prefix, container) {
    const id = prefix + '_' + node.k;
    switch (node.t) {
        case 'enum': return renderEnum(node, id, container);
        case 'bool': return renderBool(node, id, container);
        case 'num': return renderNum(node, id, container);
        case 'text': return renderText(node, id, container);
        case 'group': return renderGroup(node, id, container);
        case 'checks': return renderChecks(node, id, container);
        case 'achados': return renderAchados(node, id, container);
    }
}

function renderEnum(node, id, container) {
    const f = el('div', 'field');
    f.appendChild(el('span', null, node.label));
    const radios = el('div', 'radios');
    node.opts.forEach(([val, lab]) => {
        const lbl = el('label');
        const inp = el('input');
        inp.type = 'radio'; inp.name = id; inp.value = val;
        if (node.def === val) inp.checked = true;
        lbl.appendChild(inp); lbl.appendChild(el('span', null, lab));
        radios.appendChild(lbl);
    });
    f.appendChild(radios);
    container.appendChild(f);
}

function renderBool(node, id, container) {
    const f = el('div', 'field');
    const lbl = el('label', 'check');
    const inp = el('input'); inp.type = 'checkbox'; inp.id = id; inp.checked = !!node.def;
    lbl.appendChild(inp); lbl.appendChild(el('span', null, node.label));
    f.appendChild(lbl);
    container.appendChild(f);
}

function renderNum(node, id, container) {
    const f = el('div', 'field field--sm');
    f.appendChild(el('span', null, node.label));
    const inp = el('input'); inp.type = 'number'; inp.id = id; inp.step = '0.01'; inp.min = '0';
    if (node.max != null) inp.max = String(node.max);
    inp.placeholder = 'não aferido';
    f.appendChild(inp);
    container.appendChild(f);
}

function renderText(node, id, container) {
    const f = el('div', 'field field--wide');
    f.appendChild(el('span', null, node.label));
    const ta = el('textarea'); ta.id = id; ta.rows = 2;
    f.appendChild(ta);
    container.appendChild(f);
}

function renderGroup(node, id, container) {
    const box = el('div', 'subgroup');
    box.appendChild(el('p', 'subgroup__title', node.label));
    const grid = el('div', 'grid');
    node.children.forEach((c) => renderNode(c, id, grid));
    box.appendChild(grid);
    container.appendChild(box);
}

function renderChecks(node, id, container) {
    const f = el('div', 'field field--wide');
    f.appendChild(el('span', null, node.label));
    const radios = el('div', 'radios');
    node.opts.forEach(([val, lab]) => {
        const lbl = el('label');
        const inp = el('input'); inp.type = 'checkbox'; inp.name = id; inp.value = val;
        lbl.appendChild(inp); lbl.appendChild(el('span', null, lab));
        radios.appendChild(lbl);
    });
    f.appendChild(radios);
    container.appendChild(f);
}

const ACHADO_TIPOS = [['nefrocalcinose', 'Nefrocalcinose'], ['infarto_fibrose', 'Infarto / fibrose'], ['cistos', 'Cistos'], ['mineralizacao_diverticular', 'Mineralização diverticular'], ['sinal_medular', 'Sinal de medular'], ['nefrolito', 'Nefrólito']];

function renderAchados(node, id, container) {
    const box = el('div', 'subgroup');
    box.appendChild(el('p', 'subgroup__title', node.label));
    const rows = el('div'); rows.id = id + '_rows';
    box.appendChild(rows);
    const add = el('button', 'btn btn--ghost', '+ Adicionar achado');
    add.type = 'button';
    add.addEventListener('click', () => rows.appendChild(achadoRow()));
    box.appendChild(add);
    container.appendChild(box);
}

function achadoRow() {
    const row = el('div', 'achado-row');
    row.appendChild(selectFrom(ACHADO_TIPOS, 'achado-tipo'));
    row.appendChild(selectFrom(LADO, 'achado-lado'));
    row.appendChild(selectFrom(GRAU_OPC, 'achado-grau'));
    const med = el('input'); med.type = 'number'; med.step = '0.01'; med.min = '0'; med.placeholder = 'cm'; med.className = 'achado-medida';
    row.appendChild(med);
    const rm = el('button', 'btn btn--ghost', '×'); rm.type = 'button';
    rm.addEventListener('click', () => row.remove());
    row.appendChild(rm);
    return row;
}

function selectFrom(opts, cls) {
    const s = el('select', cls);
    opts.forEach(([v, l]) => { const o = el('option', null, l); o.value = v; s.appendChild(o); });
    return s;
}

/* ---------- Render dos orgaos ---------- */
function renderOrgaos() {
    const cont = document.getElementById('orgaos-container');
    ORGAOS.forEach((org) => {
        const det = el('details', 'orgao'); det.open = false;
        const sum = el('summary'); sum.appendChild(el('span', null, org.titulo));

        if (org.avaliavel) {
            const flag = el('label', 'orgao__flag check');
            const av = el('input'); av.type = 'checkbox'; av.id = org.orgao + '_avaliado'; av.checked = true;
            av.addEventListener('click', (e) => e.stopPropagation());
            flag.addEventListener('click', (e) => e.stopPropagation());
            flag.appendChild(av); flag.appendChild(el('span', null, 'Avaliado'));
            sum.appendChild(flag);
        }
        det.appendChild(sum);

        const body = el('div', 'orgao__body');
        if (org.campos) {
            const grid = el('div', 'grid');
            org.campos.forEach((c) => renderNode(c, org.orgao, grid));
            body.appendChild(grid);
        } else if (org.blocos) {
            org.blocos.forEach((b) => body.appendChild(renderBloco(org.orgao, b)));
        }
        det.appendChild(body);
        cont.appendChild(det);
    });
}

function renderBloco(orgao, bloco) {
    const id = orgao + '_' + bloco.k;
    const box = el('div', 'subgroup');
    const head = el('label', 'check');
    const av = el('input'); av.type = 'checkbox'; av.id = id + '_avaliado'; av.checked = false;
    head.appendChild(av); head.appendChild(el('strong', null, bloco.label));
    box.appendChild(head);
    const grid = el('div', 'grid');
    bloco.children.forEach((c) => renderNode(c, id, grid));
    box.appendChild(grid);
    return box;
}

/* ---------- Tudo Normal (defaults do schema) ---------- */
function aplicarDefault(node, prefix) {
    const id = prefix + '_' + node.k;
    switch (node.t) {
        case 'enum': {
            const alvo = document.querySelector('input[name="' + id + '"][value="' + (node.def ?? '') + '"]');
            if (alvo) alvo.checked = true;
            break;
        }
        case 'bool': { const e = document.getElementById(id); if (e) e.checked = !!node.def; break; }
        case 'num': { const e = document.getElementById(id); if (e) e.value = ''; break; }
        case 'text': { const e = document.getElementById(id); if (e) e.value = ''; break; }
        case 'group': node.children.forEach((c) => aplicarDefault(c, id)); break;
        case 'checks': document.querySelectorAll('input[name="' + id + '"]').forEach((c) => (c.checked = false)); break;
        case 'achados': { const r = document.getElementById(id + '_rows'); if (r) r.innerHTML = ''; break; }
    }
}

function tudoNormal() {
    ORGAOS.forEach((org) => {
        if (org.avaliavel) { const a = document.getElementById(org.orgao + '_avaliado'); if (a) a.checked = true; }
        if (org.campos) org.campos.forEach((c) => aplicarDefault(c, org.orgao));
        if (org.blocos) org.blocos.forEach((b) => {
            const id = org.orgao + '_' + b.k;
            const a = document.getElementById(id + '_avaliado'); if (a) a.checked = false;
            b.children.forEach((c) => aplicarDefault(c, id));
        });
    });
    mostrarStatus('Preenchido com os padrões de normalidade.', 'ok');
}

/* ---------- Coleta do payload ---------- */
function coletarNode(node, prefix, alvo) {
    const id = prefix + '_' + node.k;
    switch (node.t) {
        case 'enum': {
            const sel = document.querySelector('input[name="' + id + '"]:checked');
            const v = sel ? sel.value : (node.def ?? '');
            if (node.opc && v === '') return; // grau opcional vazio -> omite
            alvo[node.k] = v;
            break;
        }
        case 'bool': { const e = document.getElementById(id); alvo[node.k] = e ? e.checked : !!node.def; break; }
        case 'num': { const e = document.getElementById(id); const val = e && e.value.trim() !== '' ? Number(e.value) : null; if (val != null && !Number.isNaN(val)) alvo[node.k] = val; break; }
        case 'text': { const e = document.getElementById(id); const s = e ? e.value.trim() : ''; if (s !== '') alvo[node.k] = s; break; }
        case 'group': { const obj = {}; node.children.forEach((c) => coletarNode(c, id, obj)); alvo[node.k] = obj; break; }
        case 'checks': { const arr = []; document.querySelectorAll('input[name="' + id + '"]:checked').forEach((c) => arr.push(c.value)); alvo[node.k] = arr; break; }
        case 'achados': alvo[node.k] = coletarAchados(id); break;
    }
}

function coletarAchados(id) {
    const rows = document.getElementById(id + '_rows');
    const out = [];
    if (!rows) return out;
    rows.querySelectorAll('.achado-row').forEach((row) => {
        const tipo = row.querySelector('.achado-tipo').value;
        const lado = row.querySelector('.achado-lado').value;
        const grau = row.querySelector('.achado-grau').value;
        const med = row.querySelector('.achado-medida').value.trim();
        const a = { tipo, lado };
        if (grau !== '') a.grau = grau;
        if (med !== '' && !Number.isNaN(Number(med))) a.medida_cm = Number(med);
        out.push(a);
    });
    return out;
}

function coletarPayload() {
    const cab = {};
    ['paciente', 'especie', 'raca', 'sexo', 'idade', 'responsavel', 'veterinario_requisitante', 'data_exame', 'data_laudo'].forEach((k) => {
        const e = document.getElementById('cab_' + k);
        if (e && e.value.trim() !== '') cab[k] = e.value.trim();
    });

    const orgaos = {};
    ORGAOS.forEach((org) => {
        const obj = {};
        if (org.avaliavel) {
            const a = document.getElementById(org.orgao + '_avaliado');
            obj.avaliado = a ? a.checked : true;
        }
        if (org.campos) org.campos.forEach((c) => coletarNode(c, org.orgao, obj));
        if (org.blocos) org.blocos.forEach((b) => {
            const id = org.orgao + '_' + b.k;
            const bloco = {};
            const a = document.getElementById(id + '_avaliado');
            bloco.avaliado = a ? a.checked : false;
            b.children.forEach((c) => coletarNode(c, id, bloco));
            obj[b.k] = bloco;
        });
        orgaos[org.orgao] = obj;
    });

    const linhas = (elId) => document.getElementById(elId).value.split('\n').map((s) => s.trim()).filter((s) => s !== '');

    return {
        cabecalho: cab,
        orgaos: orgaos,
        impressao_diagnostica: linhas('impressao_diagnostica'),
        observacoes_finais: linhas('observacoes_finais'),
    };
}

/* ---------- Imagens (arquivos originais, sem recompressao) ---------- */
const imagensSelecionadas = [];   // File[] acumulados entre escolhas

function aoEscolherImagens(ev) {
    const arquivos = Array.from(ev.target.files || []);
    const aceitas = arquivos.filter((f) => f.type === 'image/jpeg');
    aceitas.forEach((f) => imagensSelecionadas.push(f));
    ev.target.value = '';   // permite escolher o mesmo arquivo de novo depois de remover
    renderPreviewImagens();
    const recusadas = arquivos.length - aceitas.length;
    if (recusadas > 0) mostrarStatus(recusadas + ' arquivo(s) ignorado(s): apenas JPEG é aceito.', 'err');
}

function renderPreviewImagens() {
    const box = document.getElementById('imagens-preview');
    box.querySelectorAll('img').forEach((img) => URL.revokeObjectURL(img.src));
    box.innerHTML = '';
    imagensSelecionadas.forEach((f, i) => {
        const fig = el('figure', 'thumb');
        const img = el('img');
        img.src = URL.createObjectURL(f);
        img.alt = f.name;
        const rm = el('button', 'thumb__rm', '×');
        rm.type = 'button';
        rm.title = 'Remover ' + f.name;
        rm.addEventListener('click', () => { imagensSelecionadas.splice(i, 1); renderPreviewImagens(); });
        fig.appendChild(img);
        fig.appendChild(rm);
        fig.appendChild(el('figcaption', 'thumb__nome', f.name));
        box.appendChild(fig);
    });
}

function lerImagens() {
    return Promise.all(imagensSelecionadas.map((f) => new Promise((resolve, reject) => {
        const r = new FileReader();
        r.onload = () => { const s = String(r.result); resolve(s.slice(s.indexOf(',') + 1)); };
        r.onerror = reject;
        r.readAsDataURL(f);
    })));
}

/* ---------- Submissao ---------- */
async function gerarPdf(ev) {
    if (ev) ev.preventDefault();
    const btn = document.getElementById('btn-gerar');
    btn.disabled = true;
    mostrarStatus('Gerando PDF…');
    try {
        const payload = coletarPayload();
        payload.imagens = await lerImagens();

        const resp = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/pdf' },
            body: JSON.stringify(payload),
        });

        if (!resp.ok) {
            let msg = 'Falha (' + resp.status + ').';
            try { const j = await resp.json(); if (j && j.error) msg = j.error; } catch (e) { /* ignore */ }
            mostrarStatus(msg, 'err');
            return;
        }

        const blob = await resp.blob();
        const disp = resp.headers.get('Content-Disposition') || '';
        const m = disp.match(/filename="([^"]+)"/);
        baixar(blob, m ? m[1] : 'laudo.pdf');
        mostrarStatus('PDF gerado.', 'ok');
    } catch (e) {
        mostrarStatus('Erro: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
    }
}

function baixar(blob, nome) {
    const url = URL.createObjectURL(blob);
    const a = el('a'); a.href = url; a.download = nome;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
}

/* ---------- Status toast ---------- */
let statusTimer = null;
function mostrarStatus(msg, tipo) {
    const s = document.getElementById('status');
    s.textContent = msg;
    s.className = 'status status--show' + (tipo ? ' status--' + tipo : '');
    clearTimeout(statusTimer);
    statusTimer = setTimeout(() => { s.className = 'status'; }, 3500);
}

/* ---------- Navegacao por teclado ---------- */
function navTeclado(ev) {
    if (ev.key !== 'Enter') return;
    const t = ev.target;
    if (t.tagName === 'TEXTAREA' || t.tagName === 'BUTTON') return;
    ev.preventDefault();
    const foco = Array.from(document.querySelectorAll('#form-laudo input, #form-laudo select, #form-laudo textarea'))
        .filter((e) => !e.disabled && e.type !== 'hidden' && e.offsetParent !== null);
    const i = foco.indexOf(t);
    if (i >= 0 && i + 1 < foco.length) foco[i + 1].focus();
}

/* ---------- Init ---------- */
document.addEventListener('DOMContentLoaded', () => {
    renderOrgaos();
    document.getElementById('btn-tudo-normal').addEventListener('click', tudoNormal);
    document.getElementById('imagens').addEventListener('change', aoEscolherImagens);
    document.getElementById('form-laudo').addEventListener('submit', gerarPdf);
    document.getElementById('form-laudo').addEventListener('keydown', navTeclado);
    document.addEventListener('keydown', (e) => {
        if (e.altKey && (e.key === 'n' || e.key === 'N')) { e.preventDefault(); tudoNormal(); }
        if (e.altKey && (e.key === 'g' || e.key === 'G')) { e.preventDefault(); gerarPdf(); }
    });
});
