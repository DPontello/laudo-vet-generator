<?php

declare(strict_types=1);

/**
 * Compositor de frases do orgao Rins.
 *
 * Recebe o objeto `rins` do payload JSON (docs/referencia/laudo.schema.json) e
 * devolve o paragrafo final do laudo, iniciando por "RINS: ".
 *
 * Estrategia (decisao "Hibrido"): a prosa reproduz a redacao canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt). Onde a Clarinha usa apenas um grau de
 * intensidade que o schema nao previa (ex.: "Discreta hiperecogenicidade",
 * "discretos focos"), foram adicionados os campos opcionais `ecogenicidade_grau`
 * e `grau` por achado (aditivos, default null = sem prefixo). Achados fora do
 * conjunto de opcoes do schema entram por `observacoes`.
 *
 * Notas de fidelidade (modelo x laudo real da Clarinha):
 * - O modelo escreve "relacao corticomedulares" (plural) no ramo "usual" e
 *   "corticomedular" (singular) na frase avulsa; normalizamos para o singular
 *   gramaticalmente correto, que e o que a Clarinha usa.
 * - Mantemos o rotulo diagnostico entre parenteses (ex.: "(mineralizacao
 *   diverticular / microcalculos)") e o "litiases ou" no fecho, conforme o modelo
 *   canonico; a Clarinha os omite pontualmente (diferenca documentada no teste).
 * - Achado "sinal_medular" dispara *Observacao condicional no modelo (duas
 *   variantes); a nota de rodape fica a cargo da secao de observacoes (Fase 2),
 *   nao do compositor de orgao.
 * - "infarto_fibrose" tem no modelo sub-opcoes (difuso vs. polo/face) que o schema
 *   nao modela; renderizamos a forma difusa canonica, o detalhamento vai por
 *   `observacoes`.
 */

require_once __DIR__ . '/_helpers.php';

/**
 * Slot de lateralidade renal ("em rim esquerdo / rim direito / <bilateral>").
 *
 * @param string $lado      esquerdo|direito|bilateral
 * @param string $bilateral palavra do caso bilateral (varia por achado no modelo:
 *                          "bilateralmente", "bilaterais", "bilateral", ...).
 */
function rinsLado(string $lado, string $bilateral): string
{
    return [
        'esquerdo'  => 'em rim esquerdo',
        'direito'   => 'em rim direito',
        'bilateral' => $bilateral,
    ][$lado] ?? 'em rim esquerdo';
}

/** Frase "medindo aproximadamente X cm" ou vazio se a medida for null. */
function rinsMedida($valor): string
{
    return $valor === null ? '' : 'medindo aproximadamente ' . formatarCm((float) $valor) . ' cm';
}

/**
 * Compoe a frase de um achado focal renal.
 *
 * @param array<string,mixed> $a Item do array `achados` (tipo, lado, grau, medida_cm).
 */
function rinsAchado(array $a): string
{
    $tipo   = $a['tipo'] ?? '';
    $lado   = $a['lado'] ?? 'esquerdo';
    $grau   = $a['grau'] ?? null;
    $medida = $a['medida_cm'] ?? null;

    switch ($tipo) {
        case 'nefrocalcinose':
            return 'Presença de diminutos pontos hiperecogênicos difusamente distribuídos '
                . rinsLado($lado, 'bilateralmente') . ' (nefrocalcinose).';

        case 'infarto_fibrose':
            $adj = $grau !== null ? grauAdjetivo($grau, 'f', 'p') : 'discretas';
            return "Presença de {$adj} áreas hiperecogênicas estendendo-se da região cortical à medular renal, "
                . 'provocando discreta depressão da cápsula renal, '
                . rinsLado($lado, 'bilateralmente') . ' (infarto / fibrose).';

        case 'cistos':
            $med  = rinsMedida($medida);
            $meio = $med === '' ? 'em região cortical' : "em região cortical, {$med}";
            return 'Presença de estrutura circular anecoica ' . $meio . ' '
                . rinsLado($lado, 'em ambos os rins') . ' (cistos).';

        case 'mineralizacao_diverticular':
            $adj = $grau !== null ? grauAdjetivo($grau, 'm', 'p') . ' ' : '';
            return "Presença de {$adj}focos hiperecogênicos em topografia de recessos renais "
                . rinsLado($lado, 'bilaterais') . ' (mineralização diverticular / microcálculos).';

        case 'sinal_medular':
            return 'Presença de halo hiperecogênico na região medular, paralelo à transição corticomedular '
                . rinsLado($lado, 'bilateral') . ' (sinal de medular).';

        case 'nefrolito':
            $med  = rinsMedida($medida);
            $core = 'Presença de estrutura de interface hiperecogênica de superfície curva e regular, '
                . 'formadora de sombreamento acústico posterior';
            $core .= $med === '' ? ' ' : ", {$med} ";
            return $core . rinsLado($lado, 'em ambos os rins') . ' (nefrólito).';
    }

    return '';
}

/**
 * Compoe o paragrafo dos Rins.
 *
 * @param array<string,mixed> $r Objeto `rins` do payload.
 * @return string Paragrafo pronto, iniciando por "RINS: ".
 */
function composeRins(array $r): string
{
    if (($r['avaliado'] ?? true) === false) {
        return 'RINS: Não avaliados.';
    }

    $frases = [];

    // 1. Simetria.
    $frases[] = (($r['simetria'] ?? 'simetricos') === 'assimetricos') ? 'Assimétricos.' : 'Simétricos.';

    // 2. Topografia, dimensoes e contornos.
    $esq = $r['medida_esquerdo_cm'] ?? null;
    $dir = $r['medida_direito_cm'] ?? null;
    $medidas = [];
    if ($esq !== null) { $medidas[] = formatarCm((float) $esq) . ' cm o esquerdo'; }
    if ($dir !== null) { $medidas[] = formatarCm((float) $dir) . ' cm o direito'; }
    $contornos = (($r['contornos'] ?? 'regulares') === 'irregulares') ? 'irregulares' : 'regulares';
    $s2 = 'Topografia e dimensões usuais';
    if ($medidas) { $s2 .= ', medindo aproximadamente ' . implode(' e ', $medidas); }
    $s2 .= ", com contornos {$contornos}.";
    $frases[] = $s2;

    // 3. Ecogenicidade cortical + ecotextura + relacao corticomedular.
    $ecotextura = (($r['ecotextura'] ?? 'homogenea') === 'heterogenea') ? 'heterogênea' : 'homogênea';
    if (($r['ecogenicidade_cortical'] ?? 'usual') === 'hiperecogenicidade_difusa') {
        $grau = $r['ecogenicidade_grau'] ?? null;
        $prefixo = $grau !== null
            ? capitalizar(grauAdjetivo($grau, 'f', 's')) . ' hiperecogenicidade'
            : 'Hiperecogenicidade';
        $eco = "{$prefixo} difusa da região cortical e ecotextura {$ecotextura}";
    } else {
        $eco = "Ecogenicidade usual e ecotextura {$ecotextura}";
    }
    $corticomedular = (($r['relacao_corticomedular'] ?? 'mantida') === 'perdida')
        ? ', com perda de definição e relação corticomedular'
        : ', com manutenção de definição e relação corticomedular';
    $frases[] = $eco . $corticomedular . '.';

    // 4. Achados focais (na ordem do payload).
    foreach (($r['achados'] ?? []) as $a) {
        $frase = rinsAchado((array) $a);
        if ($frase !== '') { $frases[] = $frase; }
    }

    // 5. Fecho: ausencia de litiase/dilatacao (achado normal).
    if (($r['litiase_ausente'] ?? true) === true) {
        $frases[] = 'Ausência de imagens sugestivas de litíases ou dilatação de pelves e ureteres.';
    }

    // 6. Observacoes livres (excecoes fora das opcoes).
    $obs = trim((string) ($r['observacoes'] ?? ''));
    if ($obs !== '') { $frases[] = $obs; }

    return 'RINS: ' . implode(' ', $frases);
}
