<?php

declare(strict_types=1);

/**
 * Compositor de frases do orgao Baco.
 *
 * Recebe o objeto `baco` do payload JSON (docs/referencia/laudo.schema.json) e
 * devolve o paragrafo final, iniciando por "BAÇO: ".
 *
 * Estrategia (decisao "Hibrido"): prosa canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt). Frases:
 *   1. tamanho/bordas/contornos (ramo usual ou esplenomegalia com grau);
 *   2. ecogenicidade + ecotextura;
 *   3. vascularizacao.
 * Achados nodulares em texto livre (ex.: as "áreas circulares" da Clarinha) entram
 * por `observacoes`, anexadas ao final.
 */

require_once __DIR__ . '/_helpers.php';

/**
 * Compoe o paragrafo do Baco.
 *
 * @param array<string,mixed> $b Objeto `baco` do payload.
 * @return string Paragrafo pronto, iniciando por "BAÇO: ".
 */
function composeBaco(array $b): string
{
    if (($b['avaliado'] ?? true) === false) {
        return 'BAÇO: Não avaliado.';
    }

    $frases = [];

    // 1. Tamanho, bordas e contornos.
    $bordas    = (($b['bordas'] ?? 'afiladas') === 'abauladas') ? 'abauladas' : 'afiladas';
    $contornos = (($b['contornos'] ?? 'regulares') === 'irregulares') ? 'irregulares' : 'regulares';
    $tamanho   = $b['tamanho'] ?? 'usual';
    if ($tamanho === 'usual') {
        $frases[] = "Topografia, tamanho e morfologia usuais, com bordas {$bordas} e contornos {$contornos}.";
    } else {
        $grau    = substr($tamanho, strlen('esplenomegalia_')); // discreta|moderada|acentuada
        $aumento = grauAdjetivo($grau, 'm', 's');                // discreto|moderado|acentuado
        $rotulo  = grauAdjetivo($grau, 'f', 's');                // discreta|moderada|acentuada
        $frases[] = "Aumento {$aumento} de tamanho, com bordas {$bordas} e contornos {$contornos} "
            . "(esplenomegalia {$rotulo}).";
    }

    // 2. Ecogenicidade + ecotextura (baco: ecogenicidade sempre usual no modelo).
    $ecotextura = ecotexturaTexto($b['ecotextura'] ?? 'homogenea');
    $frases[] = "Ecogenicidade usual e ecotextura {$ecotextura}.";

    // 3. Vascularizacao (some quando alterada; detalhe vai por observacoes).
    if (($b['vascularizacao_anatomica'] ?? true) === true) {
        $frases[] = 'Vascularização apresentando calibre e distribuição anatômicos.';
    }

    // 4. Observacoes livres (achados nodulares fora das opcoes).
    $obs = trim((string) ($b['observacoes'] ?? ''));
    if ($obs !== '') { $frases[] = $obs; }

    return 'BAÇO: ' . implode(' ', $frases);
}
