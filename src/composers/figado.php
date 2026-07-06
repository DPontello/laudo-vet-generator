<?php

declare(strict_types=1);

/**
 * Compositor de frases do orgao Figado.
 *
 * Recebe o objeto `figado` do payload JSON (docs/referencia/laudo.schema.json) e
 * devolve o paragrafo final, iniciando por "FÍGADO: ".
 *
 * Estrategia (decisao "Hibrido"): prosa canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt). Sequencia de frases:
 *   1. tamanho/bordas/contornos (ramo "usual" ou "hepatomegalia");
 *   2. ecogenicidade + ecotextura;
 *   3. sistema porta.
 * Achados focais (ex.: a "área ovalada" da Clarinha) sao texto livre e entram por
 * `observacoes` (anexadas ao final, conforme a decisao Hibrido).
 *
 * Campo aditivo `ecogenicidade_grau` (reusa $defs/grau_opcional) cobre o
 * "Discreta hiperecogenicidade difusa" da Clarinha sem quebrar o Tudo Normal.
 */

require_once __DIR__ . '/_helpers.php';

/**
 * Compoe o paragrafo do Figado.
 *
 * @param array<string,mixed> $f Objeto `figado` do payload.
 * @return string Paragrafo pronto, iniciando por "FÍGADO: ".
 */
function composeFigado(array $f): string
{
    if (($f['avaliado'] ?? true) === false) {
        return 'FÍGADO: Não avaliado.';
    }

    $frases = [];

    // 1. Tamanho, bordas e contornos.
    $bordas    = (($f['bordas'] ?? 'afiladas') === 'abauladas') ? 'abauladas' : 'afiladas';
    $contornos = (($f['contornos'] ?? 'regulares') === 'irregulares') ? 'irregulares' : 'regulares';
    $tamanho   = $f['tamanho'] ?? 'usual';
    if ($tamanho === 'usual') {
        $frases[] = "Topografia, tamanho e morfologia usuais, com bordas {$bordas} e contornos {$contornos}.";
    } else {
        $grau = substr($tamanho, strlen('hepatomegalia_')); // discreta|moderada|acentuada
        $aumento = grauAdjetivo($grau, 'm', 's');            // discreto|moderado|acentuado
        $rotulo  = grauAdjetivo($grau, 'f', 's');            // discreta|moderada|acentuada
        $frases[] = "Aumento {$aumento} de tamanho, com bordas {$bordas} e contornos {$contornos} "
            . "(hepatomegalia {$rotulo}).";
    }

    // 2. Ecogenicidade + ecotextura.
    $ecotextura = [
        'homogenea'  => 'homogênea',
        'heterogenea' => 'heterogênea',
        'grosseira'  => 'grosseira',
    ][$f['ecotextura'] ?? 'homogenea'] ?? 'homogênea';
    $eco  = $f['ecogenicidade'] ?? 'usual';
    $grauEco = $f['ecogenicidade_grau'] ?? null;
    if ($eco === 'hiperecogenicidade_difusa' || $eco === 'hipoecogenicidade_difusa') {
        $nome = $eco === 'hiperecogenicidade_difusa' ? 'hiperecogenicidade' : 'hipoecogenicidade';
        $base = $grauEco !== null ? capitalizar(grauAdjetivo($grauEco, 'f', 's')) . ' ' . $nome : capitalizar($nome);
        $frases[] = "{$base} difusa e ecotextura {$ecotextura}.";
    } else {
        $frases[] = "Ecogenicidade usual e ecotextura {$ecotextura}.";
    }

    // 3. Sistema porta (some quando alterado; detalhe vai por observacoes).
    if (($f['sistema_porta_anatomico'] ?? true) === true) {
        $frases[] = 'Sistema porta e veias hepáticas com calibre e distribuição anatômicos.';
    }

    // 4. Observacoes livres (achados focais fora das opcoes).
    $obs = trim((string) ($f['observacoes'] ?? ''));
    if ($obs !== '') { $frases[] = $obs; }

    return 'FÍGADO: ' . implode(' ', $frases);
}
