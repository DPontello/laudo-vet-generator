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

/* ---------- Biblioteca de observacoes finais padrao ----------
 * Notas reutilizaveis do rodape do modelo (docs/referencia/modelo-laudo-aline.txt).
 * O texto ainda pode ser refinado na previa editavel antes de gerar o PDF. */
const OBSERVACOES_PADRAO = [
    'A repleção gastrointestinal por conteúdo gasoso e consequente formação de artefato de reverberação impedem sua avaliação completa e de seu conteúdo e a visibilização de possíveis corpos sólidos.',
    'Paciente extremamente agitado(a) e apresentando acentuada quantidade de gás difusamente distribuído por todo o trato gastrointestinal, dificultando a adequada avaliação das estruturas abdominais. Sugere-se repetição do exame com preparo com simeticona e jejum prévios.',
    'A avaliação ultrassonográfica de órgãos profundos em cães de grande porte pode ser prejudicada pela limitação de frequência do equipamento.',
    'A presença de líquido livre dificulta a adequada avaliação dos órgãos abdominais, devido à alteração de ecogenicidade provocada pelo fenômeno de reforço acústico, além da possível alteração de suas topografias.',
    'Devido à acentuada distensão uterina, não foi possível avaliar adequadamente todas as estruturas abdominais.',
    'A organomegalia e a presença de estruturas em topografia não usual podem comprometer a adequada avaliação das demais estruturas abdominais.',
    'Sugere-se acompanhamento ultrassonográfico.',
    'Sugere-se exame radiográfico.',
    'Sugere-se EcoDopplercardiograma.',
    'Sugere-se exame endoscópico.',
];

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
        // Padrao anatomico: define quais segmentos aparecem nas medidas (cão x gato).
        { t: 'enum', k: 'padrao', label: 'Padrão anatômico', opts: [['cao', 'Cão'], ['gato', 'Gato']], def: 'cao' },
        { t: 'group', k: 'medidas', label: 'Medidas por segmento (cm)', children: [
            // Duodeno e jejuno servem aos dois padroes; os demais aparecem conforme `padrao` (atributo `so`).
            { t: 'num', k: 'duodeno_min_cm', label: 'Duodeno mín.', max: 2 },
            { t: 'num', k: 'duodeno_max_cm', label: 'Duodeno máx.', max: 2 },
            { t: 'num', k: 'jejuno_min_cm', label: 'Jejuno mín.', max: 2 },
            { t: 'num', k: 'jejuno_max_cm', label: 'Jejuno máx.', max: 2 },
            { t: 'num', k: 'colon_min_cm', label: 'Cólon mín.', max: 2, so: 'cao' },
            { t: 'num', k: 'colon_max_cm', label: 'Cólon máx.', max: 2, so: 'cao' },
            { t: 'num', k: 'ileo_min_cm', label: 'Íleo mín.', max: 2, so: 'gato' },
            { t: 'num', k: 'ileo_max_cm', label: 'Íleo máx.', max: 2, so: 'gato' },
            { t: 'num', k: 'colon_ascendente_min_cm', label: 'Cólon ascendente mín.', max: 2, so: 'gato' },
            { t: 'num', k: 'colon_ascendente_max_cm', label: 'Cólon ascendente máx.', max: 2, so: 'gato' },
            { t: 'num', k: 'colon_transverso_min_cm', label: 'Cólon transverso mín.', max: 2, so: 'gato' },
            { t: 'num', k: 'colon_transverso_max_cm', label: 'Cólon transverso máx.', max: 2, so: 'gato' },
            { t: 'num', k: 'colon_descendente_min_cm', label: 'Cólon descendente mín.', max: 2, so: 'gato' },
            { t: 'num', k: 'colon_descendente_max_cm', label: 'Cólon descendente máx.', max: 2, so: 'gato' },
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
    // Campo condicional: aparece apenas para o padrao anatomico indicado em `so`.
    if (node.so) f.dataset.so = node.so;
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
    rm.title = 'Remover achado'; rm.setAttribute('aria-label', 'Remover achado');
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
        // Container das opcoes personalizadas (checklists criados pela medica).
        const custom = el('div', 'orgao__custom'); custom.id = 'custom_' + org.orgao;
        body.appendChild(custom);
        det.appendChild(body);
        cont.appendChild(det);
    });
}

/**
 * Mostra/oculta os campos de medida dos Intestinos conforme o padrao anatomico
 * escolhido (cão x gato). Campos marcados com data-so aparecem so no seu padrao;
 * os sem marca (duodeno/jejuno) ficam sempre visiveis.
 */
function atualizarIntestinosPadrao() {
    const sel = document.querySelector('input[name="intestinos_padrao"]:checked');
    const padrao = sel ? sel.value : 'cao';
    document.querySelectorAll('[data-so]').forEach((f) => {
        f.hidden = (f.dataset.so !== padrao);
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

/* ---------- Observacoes finais (checklist da biblioteca) ---------- */
function renderObservacoesOpcoes() {
    const box = document.getElementById('observacoes-opcoes');
    if (!box) return;
    OBSERVACOES_PADRAO.forEach((texto) => {
        const lbl = el('label', 'obs-opcao');
        const inp = el('input'); inp.type = 'checkbox'; inp.className = 'obs-check'; inp.value = texto;
        lbl.appendChild(inp); lbl.appendChild(el('span', null, texto));
        box.appendChild(lbl);
    });
}

/** Observacoes finais coletadas: notas padrao + personalizadas marcadas + campo livre. */
function coletarObservacoesFinais() {
    const marcadas = Array.from(document.querySelectorAll('.obs-check:checked')).map((c) => c.value);
    const custom = customChecadas('observacoes_finais');
    const livres = document.getElementById('observacoes_finais').value
        .split('\n').map((s) => s.trim()).filter((s) => s !== '');
    return marcadas.concat(custom, livres);
}

/* ---------- Checklists personalizados (criados pela medica, salvos no servidor) ---------- */
const SECOES_LABEL = {
    bexiga: 'Bexiga', rins: 'Rins', adrenais: 'Adrenais', figado: 'Fígado',
    vesicula_biliar: 'Vesícula Biliar', baco: 'Baço', estomago: 'Estômago',
    intestinos: 'Intestinos', pancreas: 'Pâncreas', reprodutor: 'Sistema Reprodutor',
    cavidade_abdominal: 'Cavidade Abdominal',
    impressao_diagnostica: 'Impressão diagnóstica', observacoes_finais: 'Observações finais',
};

let checklistsCustom = {};   // { secao: [{id, label, texto}] } carregado do servidor

/** Frases marcadas de uma secao (checkboxes personalizados). */
function customChecadas(secao) {
    return Array.from(document.querySelectorAll('.custom-check[data-secao="' + secao + '"]:checked'))
        .map((c) => c.value);
}

/** Carrega os checklists personalizados do servidor e os injeta nas secoes. */
async function carregarChecklistsCustom() {
    try {
        const resp = await fetch('?checklists', { headers: { 'Accept': 'application/json' } });
        if (!resp.ok) return;
        const data = await resp.json();
        checklistsCustom = (data && !Array.isArray(data)) ? data : {};
    } catch (e) {
        checklistsCustom = {};
    }
    renderCustomTodasSecoes();
}

/** (Re)desenha as caixas personalizadas em todas as secoes. */
function renderCustomTodasSecoes() {
    Object.keys(SECOES_LABEL).forEach((secao) => {
        const box = document.getElementById('custom_' + secao);
        if (!box) return;
        box.innerHTML = '';
        (checklistsCustom[secao] || []).forEach((item) => {
            const lbl = el('label', 'obs-opcao');
            const inp = el('input'); inp.type = 'checkbox'; inp.className = 'custom-check';
            inp.value = item.texto; inp.dataset.secao = secao;
            lbl.appendChild(inp); lbl.appendChild(el('span', null, item.label || item.texto));
            lbl.title = item.texto;
            box.appendChild(lbl);
        });
    });
}

/* ----- Modal de gerenciamento dos checklists ----- */
let configTrabalho = {};   // copia de trabalho editada no modal

function abrirConfig() {
    configTrabalho = JSON.parse(JSON.stringify(checklistsCustom || {}));
    const sel = document.getElementById('config-secao');
    if (!sel.options.length) {
        Object.keys(SECOES_LABEL).forEach((secao) => {
            const o = el('option', null, SECOES_LABEL[secao]); o.value = secao; sel.appendChild(o);
        });
    }
    renderConfigItens(sel.value || Object.keys(SECOES_LABEL)[0]);
    document.getElementById('config-modal').hidden = false;
    document.body.classList.add('modal-aberto');
    sel.focus();   // move o foco para dentro do modal (acessibilidade)
}

function fecharConfig() {
    document.getElementById('config-modal').hidden = true;
    document.body.classList.remove('modal-aberto');
}

/** Renderiza as linhas editaveis dos itens da secao selecionada. */
function renderConfigItens(secao) {
    const cont = document.getElementById('config-itens');
    cont.innerHTML = '';
    const itens = configTrabalho[secao] || (configTrabalho[secao] = []);
    if (!itens.length) {
        cont.appendChild(el('p', 'hint', 'Nenhum item nesta seção ainda. Clique em "Adicionar item".'));
    }
    itens.forEach((item, i) => {
        const row = el('div', 'config-item');
        const rot = el('input'); rot.type = 'text'; rot.className = 'config-item__label';
        rot.placeholder = 'Rótulo (opcional)'; rot.value = item.label || '';
        rot.addEventListener('input', () => { item.label = rot.value; });
        const txt = el('textarea'); txt.className = 'config-item__texto'; txt.rows = 2;
        txt.placeholder = 'Texto que entra no laudo ao marcar esta opção';
        txt.value = item.texto || '';
        txt.addEventListener('input', () => { item.texto = txt.value; });
        const rm = el('button', 'btn btn--ghost config-item__rm', '×'); rm.type = 'button';
        rm.title = 'Remover item'; rm.setAttribute('aria-label', 'Remover item');
        rm.addEventListener('click', () => { itens.splice(i, 1); renderConfigItens(secao); });
        row.appendChild(rot); row.appendChild(txt); row.appendChild(rm);
        cont.appendChild(row);
    });
}

function configAdicionarItem() {
    const secao = document.getElementById('config-secao').value;
    (configTrabalho[secao] || (configTrabalho[secao] = [])).push({ id: '', label: '', texto: '' });
    renderConfigItens(secao);
}

async function salvarConfig() {
    const btn = document.getElementById('config-salvar');
    btn.disabled = true;
    mostrarStatus('Salvando checklists…');
    try {
        const resp = await fetch('?checklists', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(configTrabalho),
        });
        if (!resp.ok) {
            let msg = 'Falha (' + resp.status + ').';
            try { const j = await resp.json(); if (j && j.error) msg = j.error; } catch (e) { /* ignore */ }
            mostrarStatus(msg, 'err');
            return;
        }
        const salvo = await resp.json();
        checklistsCustom = (salvo && !Array.isArray(salvo)) ? salvo : {};
        renderCustomTodasSecoes();
        fecharConfig();
        mostrarStatus('Checklists salvos.', 'ok');
    } catch (e) {
        mostrarStatus('Erro: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
    }
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
    // Achados personalizados de orgao contradizem "normal"; as observacoes finais
    // (limitacoes do exame) ficam como estao.
    document.querySelectorAll('.custom-check').forEach((c) => {
        if (c.dataset.secao !== 'observacoes_finais') c.checked = false;
    });
    // aplicarDefault seta o radio direto (sem disparar change); resincroniza a visibilidade.
    atualizarIntestinosPadrao();
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
        // Frases dos checklists personalizados entram pelo texto livre do orgao.
        const extra = customChecadas(org.orgao);
        if (extra.length) {
            obj.observacoes = [obj.observacoes].concat(extra).filter((s) => s && String(s).trim() !== '').join(' ');
        }
        orgaos[org.orgao] = obj;
    });

    const linhas = (elId) => document.getElementById(elId).value.split('\n').map((s) => s.trim()).filter((s) => s !== '');

    return {
        cabecalho: cab,
        orgaos: orgaos,
        // Frases marcadas (checklist personalizado) primeiro, depois o texto livre.
        impressao_diagnostica: customChecadas('impressao_diagnostica').concat(linhas('impressao_diagnostica')),
        observacoes_finais: coletarObservacoesFinais(),
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
        rm.title = 'Remover ' + f.name; rm.setAttribute('aria-label', 'Remover imagem ' + f.name);
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
/** POST do corpo (payload cru OU com laudo ja editado) e baixa o PDF retornado. */
async function enviarPdf(body) {
    const resp = await fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/pdf' },
        body: JSON.stringify(body),
    });

    if (!resp.ok) {
        let msg = 'Falha (' + resp.status + ').';
        try { const j = await resp.json(); if (j && j.error) msg = j.error; } catch (e) { /* ignore */ }
        mostrarStatus(msg, 'err');
        return false;
    }

    const blob = await resp.blob();
    const disp = resp.headers.get('Content-Disposition') || '';
    const m = disp.match(/filename="([^"]+)"/);
    baixar(blob, m ? m[1] : 'laudo.pdf');
    mostrarStatus('PDF gerado.', 'ok');
    return true;
}

async function gerarPdf(ev) {
    if (ev) ev.preventDefault();
    const btn = document.getElementById('btn-gerar');
    btn.disabled = true;
    mostrarStatus('Gerando PDF…');
    try {
        const payload = coletarPayload();
        payload.imagens = await lerImagens();
        await enviarPdf(payload);
    } catch (e) {
        mostrarStatus('Erro: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
    }
}

/* ---------- Previa editavel ---------- */
let previaLaudo = null;   // laudo estruturado (servidor + edicoes da medica); persiste entre aberturas do modal
// Texto de cada seção como saiu da ultima composicao do formulario. Serve de
// referencia para detectar quais seções a medica alterou a mao (mesclarSecoes).
let previaBase = { orgaos: [], impressao: [], observacoes: [] };
const PREVIA_SECOES = ['orgaos', 'impressao', 'observacoes'];
const PREVIA_ROTULOS = { orgaos: 'Órgãos', impressao: 'Impressão diagnóstica', observacoes: 'Observações' };

/**
 * Abre a previa. Sempre recompoe do formulario (para refletir o que foi marcado
 * nos checklists), mas ao reabrir PRESERVA apenas as seções que a medica editou
 * a mao — assim uma edicao manual num trecho nao "congela" o resto (modo mesclar).
 */
async function abrirPrevia() {
    await recomporPrevia(previaLaudo ? 'mesclar' : 'novo');
}

/**
 * (Re)compõe o texto da previa a partir do formulario, via servidor.
 * modo 'novo'    — usa o texto do formulario tal como veio (descarta edicoes);
 * modo 'mesclar' — mantem as seções que a medica editou a mao e atualiza as demais.
 */
async function recomporPrevia(modo = 'novo') {
    const btn = document.getElementById('btn-previa');
    btn.disabled = true;
    mostrarStatus('Montando prévia…');
    try {
        const payload = coletarPayload();
        payload.modo = 'previa';
        const resp = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!resp.ok) {
            let msg = 'Falha (' + resp.status + ').';
            try { const j = await resp.json(); if (j && j.error) msg = j.error; } catch (e) { /* ignore */ }
            mostrarStatus(msg, 'err');
            return;
        }
        const fresco = await resp.json();

        let preservados = [];   // rótulos dos trechos preservados, para o aviso
        if (modo === 'mesclar' && previaLaudo) {
            // Órgãos: mescla por ÓRGÃO (parágrafo). Editar o Fígado, por ex., nao
            // trava a atualizacao dos checklists dos demais orgaos.
            const orgs = mesclarOrgaos(fresco);
            if (orgs.length) preservados.push('Órgãos (' + orgs.join(', ') + ')');
            // Impressão e Observações: listas de linhas, no nivel da secao inteira.
            ['impressao', 'observacoes'].forEach((s) => {
                const editada = JSON.stringify(previaLaudo[s] || []) !== JSON.stringify(previaBase[s] || []);
                if (editada) {
                    fresco[s] = (previaLaudo[s] || []).slice();
                    preservados.push(PREVIA_ROTULOS[s]);
                } else {
                    previaBase[s] = (fresco[s] || []).slice();   // segue acompanhando o form
                }
            });
        } else {
            // Modo 'novo': tudo vem do formulario; a referencia passa a ser ele.
            PREVIA_SECOES.forEach((s) => { previaBase[s] = (fresco[s] || []).slice(); });
        }

        preencherPrevia(fresco);
        avisarSecoesPreservadas(preservados);
        abrirModal();
        mostrarStatus(preservados.length
            ? 'Prévia atualizada — mantive suas edições manuais; o resto reflete os checklists.'
            : 'Prévia pronta. Ajuste o texto e gere o PDF.', 'ok');
    } catch (e) {
        mostrarStatus('Erro: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
    }
}

/**
 * Mescla os parágrafos dos órgãos preservando, um a um, apenas os que a médica
 * editou a mao. Cada parágrafo é identificado pelo rótulo inicial (texto antes
 * do primeiro ":"), estável entre composições (ex.: "FÍGADO", "INTESTINOS").
 * Muta `fresco.orgaos` (o que sera exibido) e `previaBase.orgaos` (nova referencia);
 * retorna os nomes dos órgãos preservados para o aviso.
 */
function mesclarOrgaos(fresco) {
    // Normaliza qualquer forma (array do servidor ou capturado do textarea) em
    // parágrafos — o reprodutor, por ex., emite varios blocos com linha em branco.
    const emParas = (arr) => (arr || []).join('\n\n').split(/\n\s*\n/).map((s) => s.trim()).filter(Boolean);
    // Chave = rótulo antes do ":" (nos primeiros 40 chars); senao, o inicio do
    // parágrafo. Em maiusculas para casar mesmo com variacao de caixa.
    const chave = (p) => {
        const i = p.indexOf(':');
        return ((i > 0 && i <= 40) ? p.slice(0, i) : p.slice(0, 40)).trim().toUpperCase();
    };

    const frescoParas = emParas(fresco.orgaos);
    const baseMap = {}; emParas(previaBase.orgaos).forEach((p) => { baseMap[chave(p)] = p; });
    const editMap = {}; emParas(previaLaudo.orgaos).forEach((p) => { editMap[chave(p)] = p; });
    const frescoKeys = new Set(frescoParas.map(chave));

    // Órgão "editado a mao": existia na referencia e mudou de texto.
    const editados = new Set();
    Object.keys(editMap).forEach((k) => {
        if (baseMap[k] !== undefined && editMap[k] !== baseMap[k]) editados.add(k);
    });

    // Resultado: ordem/conjunto do formulario; onde houve edicao manual, usa a versao dela.
    const resultado = frescoParas.map((p) => {
        const k = chave(p);
        return editados.has(k) ? editMap[k] : p;
    });
    // Salvaguarda: parágrafo digitado/reescrito que o formulario nao reproduz
    // (chave orfa) é anexado — nunca descartamos texto manual em silencio (laudo).
    Object.keys(editMap).forEach((k) => {
        if (baseMap[k] === undefined && !frescoKeys.has(k)) { resultado.push(editMap[k]); editados.add(k); }
    });

    // Nova referencia: preservados mantem o baseline antigo (seguem "editados" no
    // proximo reabrir); os demais passam a valer o texto novo do formulario.
    previaBase.orgaos = frescoParas.map((p) => {
        const k = chave(p);
        return (editados.has(k) && baseMap[k] !== undefined) ? baseMap[k] : p;
    });
    fresco.orgaos = resultado;

    return Array.from(editados).map(rotuloOrgao);
}

/** Rótulo amigavel de um órgão a partir da chave ("FÍGADO" -> "Fígado"). */
function rotuloOrgao(k) {
    const t = k.toLowerCase();
    return t.charAt(0).toUpperCase() + t.slice(1);
}

/**
 * Mostra/oculta o aviso dos trechos preservados (que deixam de acompanhar o
 * formulario ate um "Recompor" explicito). Recebe uma lista de rótulos prontos.
 */
function avisarSecoesPreservadas(nomes) {
    const aviso = document.getElementById('previa-aviso');
    if (!nomes || !nomes.length) { aviso.hidden = true; return; }
    aviso.innerHTML = '⚠ Mantive suas edições manuais em <strong>' + nomes.join('; ') +
        '</strong>. Esses trechos deixam de ser atualizados pelo formulário — clique em ' +
        '<strong>“↻ Recompor do formulário”</strong> para regerá-los do zero. O restante ' +
        'continua refletindo o que você marca nos checklists.';
    aviso.hidden = false;
}

/** Recompõe do formulario descartando TODAS as edicoes atuais (com confirmacao). */
function recomporPreviaConfirmando() {
    if (previaLaudo && !window.confirm('Recompor vai descartar as edições atuais da prévia e regerar o texto a partir do formulário. Continuar?')) {
        return;
    }
    recomporPrevia('novo');
}

/**
 * Le as tres caixas da previa de volta para previaLaudo, preservando as edicoes
 * (inclusive marcadores de estilo) ao fechar o modal — para reabrir depois.
 */
function capturarPrevia() {
    if (!previaLaudo) return;
    const porLinha = (id) => document.getElementById(id).value.split('\n').map((s) => s.trim()).filter((s) => s !== '');
    const porBloco = (id) => document.getElementById(id).value.split(/\n\s*\n/).map((s) => s.trim()).filter((s) => s !== '');
    previaLaudo.orgaos = porBloco('previa-orgaos');
    previaLaudo.impressao = porLinha('previa-impressao');
    previaLaudo.observacoes = porLinha('previa-observacoes');
}

function preencherPrevia(laudo) {
    previaLaudo = laudo || {};
    const c = previaLaudo.cabecalho || {};
    document.getElementById('previa-cabecalho').textContent =
        [c.paciente, c.especie, c.raca, c.sexo, c.idade].filter(Boolean).join('  ·  ');
    document.getElementById('previa-orgaos').value = (previaLaudo.orgaos || []).join('\n\n');
    document.getElementById('previa-impressao').value = (previaLaudo.impressao || []).join('\n');
    document.getElementById('previa-observacoes').value = (previaLaudo.observacoes || []).join('\n');
}

/** Gera o PDF a partir do texto editado na previa (envia o laudo ja composto). */
async function gerarPdfDaPrevia() {
    if (!previaLaudo) return;
    const btn = document.getElementById('previa-gerar');
    btn.disabled = true;
    mostrarStatus('Gerando PDF…');
    try {
        const porLinha = (id) => document.getElementById(id).value.split('\n').map((s) => s.trim()).filter((s) => s !== '');
        const porBloco = (id) => document.getElementById(id).value.split(/\n\s*\n/).map((s) => s.trim()).filter((s) => s !== '');

        const body = coletarPayload();   // mantem cabecalho/orgaos para a validacao do servidor
        body.laudo = {
            cabecalho: previaLaudo.cabecalho || {},
            titulo: previaLaudo.titulo,
            orgaos: porBloco('previa-orgaos'),
            impressao: porLinha('previa-impressao'),
            observacoes: porLinha('previa-observacoes'),
            disclaimer: previaLaudo.disclaimer,
            local_data: previaLaudo.local_data,
        };
        body.imagens = await lerImagens();
        if (await enviarPdf(body)) fecharModal();
    } catch (e) {
        mostrarStatus('Erro: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
    }
}

function abrirModal() {
    document.getElementById('previa-modal').hidden = false;
    document.body.classList.add('modal-aberto');
    document.getElementById('previa-orgaos').focus();
}

function fecharModal() {
    capturarPrevia();   // guarda as edicoes para reabrir depois (nao se perdem ao fechar)
    document.getElementById('previa-modal').hidden = true;
    document.body.classList.remove('modal-aberto');
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

/* ---------- Tema (claro / escuro) ---------- */
function aplicarTema(tema) {
    document.documentElement.dataset.theme = tema;
    try { localStorage.setItem('laudo_tema', tema); } catch (e) { /* ignore */ }
    const btn = document.getElementById('btn-tema');
    if (btn) btn.textContent = tema === 'dark' ? '☾ Escuro' : '☀ Claro';
}

function alternarTema() {
    const atual = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
    aplicarTema(atual === 'dark' ? 'light' : 'dark');
}

/* ---------- Marcacao de estilo (negrito / sublinhado) ----------
 * As caixas de Impressao diagnostica e Observacoes aceitam negrito e sublinhado
 * via marcadores leves no texto: **negrito** e __sublinhado__. O PHP do PDF
 * interpreta esses marcadores (ver pdfRunsMarcados em src/pdf/gerarPdf.php).
 * Aqui so envolvemos a selecao nos marcadores — o texto continua editavel. */

/** Envolve a selecao atual do textarea com os marcadores (ex.: '**'). */
function envolverSelecao(ta, marca) {
    const ini = ta.selectionStart != null ? ta.selectionStart : ta.value.length;
    const fim = ta.selectionEnd != null ? ta.selectionEnd : ini;
    const val = ta.value;
    const sel = val.slice(ini, fim);
    ta.value = val.slice(0, ini) + marca + sel + marca + val.slice(fim);
    // Mantem selecionado o mesmo trecho, agora entre os marcadores.
    const desl = ini + marca.length;
    ta.focus();
    ta.setSelectionRange(desl, desl + sel.length);
}

/** Insere uma barra com botoes B/U logo acima do textarea informado. */
function montarBarraEstilo(ta) {
    const bar = el('div', 'fmt-bar');

    const botao = (rotulo, marca, titulo, cls) => {
        const b = el('button', 'btn btn--ghost fmt-btn' + (cls ? ' ' + cls : ''), rotulo);
        b.type = 'button';
        b.title = titulo;
        // mousedown preventDefault preserva a selecao do textarea ao clicar no botao.
        b.addEventListener('mousedown', (e) => e.preventDefault());
        b.addEventListener('click', () => envolverSelecao(ta, marca));
        return b;
    };

    bar.appendChild(botao('N', '**', 'Negrito (Ctrl+B) — envolve a seleção em **', 'fmt-btn--b'));
    bar.appendChild(botao('S', '__', 'Sublinhado (Ctrl+U) — envolve a seleção em __', 'fmt-btn--u'));

    // Atalhos de teclado dentro da propria caixa.
    ta.addEventListener('keydown', (ev) => {
        if (!(ev.ctrlKey || ev.metaKey)) return;
        const k = ev.key.toLowerCase();
        if (k === 'b') { ev.preventDefault(); envolverSelecao(ta, '**'); }
        else if (k === 'u') { ev.preventDefault(); envolverSelecao(ta, '__'); }
    });

    const ancora = ta.closest('label.field') || ta;
    ancora.parentNode.insertBefore(bar, ancora);
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
    renderObservacoesOpcoes();
    aplicarTema(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light');
    carregarChecklistsCustom();

    // Intestinos: alterna os segmentos visiveis conforme o padrao anatomico (cão/gato).
    document.querySelectorAll('input[name="intestinos_padrao"]').forEach((r) => r.addEventListener('change', atualizarIntestinosPadrao));
    atualizarIntestinosPadrao();

    // Barras de estilo (negrito/sublinhado) nas caixas de impressao e observacoes.
    ['impressao_diagnostica', 'observacoes_finais', 'previa-impressao', 'previa-observacoes'].forEach((id) => {
        const ta = document.getElementById(id);
        if (ta) montarBarraEstilo(ta);
    });

    document.getElementById('btn-tema').addEventListener('click', alternarTema);
    document.getElementById('btn-config').addEventListener('click', abrirConfig);
    document.getElementById('btn-tudo-normal').addEventListener('click', tudoNormal);
    document.getElementById('btn-previa').addEventListener('click', abrirPrevia);
    document.getElementById('imagens').addEventListener('change', aoEscolherImagens);
    document.getElementById('form-laudo').addEventListener('submit', gerarPdf);
    document.getElementById('form-laudo').addEventListener('keydown', navTeclado);

    document.getElementById('previa-gerar').addEventListener('click', gerarPdfDaPrevia);
    document.getElementById('previa-recompor').addEventListener('click', recomporPreviaConfirmando);
    document.querySelectorAll('#previa-modal [data-fechar]').forEach((e) => e.addEventListener('click', fecharModal));

    document.getElementById('config-secao').addEventListener('change', (e) => renderConfigItens(e.target.value));
    document.getElementById('config-add').addEventListener('click', configAdicionarItem);
    document.getElementById('config-salvar').addEventListener('click', salvarConfig);
    document.querySelectorAll('#config-modal [data-fechar-config]').forEach((e) => e.addEventListener('click', fecharConfig));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !document.getElementById('config-modal').hidden) { fecharConfig(); return; }
        if (e.key === 'Escape' && !document.getElementById('previa-modal').hidden) { fecharModal(); return; }
        if (e.altKey && (e.key === 'n' || e.key === 'N')) { e.preventDefault(); tudoNormal(); }
        if (e.altKey && (e.key === 'g' || e.key === 'G')) { e.preventDefault(); gerarPdf(); }
        if (e.altKey && (e.key === 'p' || e.key === 'P')) { e.preventDefault(); abrirPrevia(); }
    });
});
