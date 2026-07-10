<?php

declare(strict_types=1);

/**
 * Compositor de frases do Sistema Reprodutor.
 *
 * Recebe o objeto `reprodutor` do payload JSON (docs/referencia/laudo.schema.json)
 * e devolve os blocos aplicaveis, cada um com seu proprio cabecalho:
 *   - ÚTERO E OVÁRIOS (historico de OSH);
 *   - ÚTERO;
 *   - OVÁRIOS;
 *   - PRÓSTATA;
 *   - TESTÍCULOS.
 *
 * So entra no laudo o bloco com `avaliado: true` (escolha por sexo/status). Os
 * blocos ativos sao unidos por linha em branco. Se nenhum estiver ativo, devolve
 * string vazia (o agregador pula o Sistema Reprodutor).
 *
 * Estrategia (decisao "Hibrido"): prosa canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt). Trechos sem campo no schema (ex.:
 * "lacre / fio de sutura" no OSH; medida da "maior" estrutura anecoica dos
 * ovarios) ficam de fora e iriam por texto livre no futuro. O caso da Clarinha
 * (apenas OSH, sem coto) bate verbatim.
 */

require_once __DIR__ . '/_helpers.php';

/** Bloco "ÚTERO E OVÁRIOS" (historico de ovariohisterectomia). */
function reproUteroOvariosAusentes(array $x): string
{
    if (($x['avaliado'] ?? false) !== true) { return ''; }
    $s = 'ÚTERO E OVÁRIOS: Não visibilizados (histórico de ovariohisterectomia).';
    $coto = $x['coto_uterino_cm'] ?? null;
    if ($coto !== null) {
        $s .= ' Coto uterino medindo aproximadamente ' . formatarCm((float) $coto)
            . ' cm de diâmetro, com paredes finas e regulares, sem acúmulo de conteúdo intraluminal.';
    }
    return $s;
}

/** Clausula "X cm em corpo, Y cm em corno esquerdo e Z cm em corno direito" (ou ''). */
function uteroDiametro(array $x): string
{
    $partes = [];
    if (($x['corpo_cm'] ?? null) !== null)          { $partes[] = formatarCm((float) $x['corpo_cm']) . ' cm em corpo'; }
    if (($x['corno_esquerdo_cm'] ?? null) !== null) { $partes[] = formatarCm((float) $x['corno_esquerdo_cm']) . ' cm em corno esquerdo'; }
    if (($x['corno_direito_cm'] ?? null) !== null)  { $partes[] = formatarCm((float) $x['corno_direito_cm']) . ' cm em corno direito'; }
    return listaPtBr($partes);
}

/** Bloco "ÚTERO". */
function reproUtero(array $x): string
{
    if (($x['avaliado'] ?? false) !== true) { return ''; }
    $status = $x['status'] ?? 'usual';

    if ($status === 'nao_visibilizado') {
        return 'ÚTERO: Não visibilizado.';
    }

    $diam = uteroDiametro($x);

    if ($status === 'aumentado') {
        $s = 'ÚTERO: Aumentado de volume';
        if ($diam !== '') { $s .= ', com diâmetro de aproximadamente ' . $diam; }
        $conteudo = $x['conteudo'] ?? 'nenhum';
        if ($conteudo === 'anecogenico') {
            $s .= ', com acúmulo de conteúdo anecogênico homogêneo';
        } elseif ($conteudo === 'particulado') {
            $s .= ', com acúmulo de conteúdo particulado (alta celularidade)';
        }
        $s .= '. Paredes espessadas e irregulares, apresentando múltiplas estruturas circulares anecoicas (cistos).';
        return $s;
    }

    // status usual.
    $s = 'ÚTERO: Parcialmente visibilizado';
    if ($diam !== '') { $s .= ' com diâmetro de aproximadamente ' . $diam; }
    $s .= '. Paredes finas e regulares, sem acúmulo de conteúdo intraluminal.';
    return $s;
}

/** Bloco "OVÁRIOS". */
function reproOvarios(array $x): string
{
    if (($x['avaliado'] ?? false) !== true) { return ''; }

    $medidas = [];
    if (($x['medida_esquerdo_cm'] ?? null) !== null) { $medidas[] = formatarCm((float) $x['medida_esquerdo_cm']) . ' cm o esquerdo'; }
    if (($x['medida_direito_cm'] ?? null) !== null)  { $medidas[] = formatarCm((float) $x['medida_direito_cm']) . ' cm o direito'; }

    $s = 'OVÁRIOS: Forma e contorno usuais';
    if ($medidas) { $s .= ', medindo aproximadamente ' . listaPtBr($medidas); }
    $s .= '.';

    $estruturas = $x['estruturas_anecoicas'] ?? 'nenhuma';
    if ($estruturas !== 'nenhuma') {
        $s .= ' Ecogenicidade usual e ecotextura heterogênea, pela presença de estruturas circulares anecoicas.';
        $dx = ($estruturas === 'cistos') ? 'cistos ovarianos' : 'folículos ovarianos';
        $s .= ' Diag. diferenciais: ' . $dx . '.';
    } else {
        $ecotextura = ecotexturaTexto($x['ecotextura'] ?? 'homogenea');
        $s .= " Ecogenicidade usual e ecotextura {$ecotextura}.";
    }
    return $s;
}

/** Clausula "X cm x Y cm x Z cm" da prostata (ou ''). */
function prostataMedidas(array $x): string
{
    $vals = [];
    foreach (['medida_x_cm', 'medida_y_cm', 'medida_z_cm'] as $k) {
        if (($x[$k] ?? null) !== null) { $vals[] = formatarCm((float) $x[$k]) . ' cm'; }
    }
    return $vals ? implode(' x ', $vals) : '';
}

/** Bloco "PRÓSTATA". */
function reproProstata(array $x): string
{
    if (($x['avaliado'] ?? false) !== true) { return ''; }

    $s = (($x['tamanho'] ?? 'usual') === 'aumentada')
        ? 'PRÓSTATA: Aumentada de volume'
        : 'PRÓSTATA: Topografia, forma e contorno usuais';
    $medidas = prostataMedidas($x);
    if ($medidas !== '') { $s .= ', medindo aproximadamente ' . $medidas; }
    $s .= '. Ecogenicidade usual e ecotextura homogênea.';
    return $s;
}

/** Clausula "X cm x Y cm o esquerdo e W cm x V cm o direito" dos testiculos (ou ''). */
function testiculosMedidas(array $x): string
{
    $lados = [];
    if (($x['esquerdo_x_cm'] ?? null) !== null && ($x['esquerdo_y_cm'] ?? null) !== null) {
        $lados[] = formatarCm((float) $x['esquerdo_x_cm']) . ' cm x ' . formatarCm((float) $x['esquerdo_y_cm']) . ' cm o esquerdo';
    }
    if (($x['direito_x_cm'] ?? null) !== null && ($x['direito_y_cm'] ?? null) !== null) {
        $lados[] = formatarCm((float) $x['direito_x_cm']) . ' cm x ' . formatarCm((float) $x['direito_y_cm']) . ' cm o direito';
    }
    return $lados ? implode(' e ', $lados) : '';
}

/** Bloco "TESTÍCULOS". */
function reproTesticulos(array $x): string
{
    if (($x['avaliado'] ?? false) !== true) { return ''; }

    if (($x['status'] ?? 'presentes') === 'ausentes_orquiectomia') {
        return 'TESTÍCULOS: Não visibilizados (histórico de orquiectomia).';
    }

    $s = 'TESTÍCULOS: Simétricos';
    $medidas = testiculosMedidas($x);
    if ($medidas !== '') { $s .= ', medindo aproximadamente ' . $medidas; }
    $s .= '. Parênquima apresentando ecogenicidade e ecotextura usuais. Mediastino preservado e centralizado.';
    return $s;
}

/**
 * Compoe o(s) bloco(s) do Sistema Reprodutor aplicaveis.
 *
 * @param array<string,mixed> $r Objeto `reprodutor` do payload.
 * @return string Blocos ativos unidos por linha em branco; '' se nenhum ativo.
 */
function composeReprodutor(array $r): string
{
    $blocos = [];
    foreach ([
        reproUteroOvariosAusentes((array) ($r['utero_ovarios_ausentes'] ?? [])),
        reproUtero((array) ($r['utero'] ?? [])),
        reproOvarios((array) ($r['ovarios'] ?? [])),
        reproProstata((array) ($r['prostata'] ?? [])),
        reproTesticulos((array) ($r['testiculos'] ?? [])),
    ] as $bloco) {
        if ($bloco !== '') { $blocos[] = $bloco; }
    }

    // Observacoes livres (excecoes / checklists personalizados) entram como
    // paragrafo proprio ao final da secao, como nos demais orgaos (decisao Hibrido).
    $obs = trim((string) ($r['observacoes'] ?? ''));
    if ($obs !== '') { $blocos[] = $obs; }

    return implode("\n\n", $blocos);
}
