<?php

declare(strict_types=1);

/**
 * Round-trip do Figado. O caso da Clarinha reproduz a parte parametrizavel
 * (bordas/contornos usuais + "Discreta hiperecogenicidade difusa" + sistema
 * porta). A "área ovalada" da Clarinha e achado focal em texto livre e seria
 * passada por `observacoes` (validado em caso separado, anexada ao final).
 *
 * Uso: php tests/figado-round-trip.php
 */

require __DIR__ . '/../src/composers/figado.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Figado da Clarinha (parte parametrizavel) ---- */
$clarinha = [
    'avaliado'                => true,
    'tamanho'                 => 'usual',
    'bordas'                  => 'afiladas',
    'contornos'               => 'regulares',
    'ecogenicidade'           => 'hiperecogenicidade_difusa',
    'ecogenicidade_grau'      => 'discreta',
    'ecotextura'              => 'homogenea',
    'sistema_porta_anatomico' => true,
];
checa('Clarinha (parametrizavel)',
    'FÍGADO: Topografia, tamanho e morfologia usuais, com bordas afiladas e contornos regulares. '
    . 'Discreta hiperecogenicidade difusa e ecotextura homogênea. '
    . 'Sistema porta e veias hepáticas com calibre e distribuição anatômicos.',
    composeFigado($clarinha));

/* ---- Caso 2: Tudo Normal ---- */
$normal = [
    'avaliado'                => true,
    'tamanho'                 => 'usual',
    'bordas'                  => 'afiladas',
    'contornos'               => 'regulares',
    'ecogenicidade'           => 'usual',
    'ecotextura'              => 'homogenea',
    'sistema_porta_anatomico' => true,
];
checa('Tudo Normal',
    'FÍGADO: Topografia, tamanho e morfologia usuais, com bordas afiladas e contornos regulares. '
    . 'Ecogenicidade usual e ecotextura homogênea. '
    . 'Sistema porta e veias hepáticas com calibre e distribuição anatômicos.',
    composeFigado($normal));

/* ---- Caso 3: hepatomegalia moderada ---- */
checa('Hepatomegalia moderada',
    'FÍGADO: Aumento moderado de tamanho, com bordas abauladas e contornos regulares (hepatomegalia moderada). '
    . 'Ecogenicidade usual e ecotextura homogênea. '
    . 'Sistema porta e veias hepáticas com calibre e distribuição anatômicos.',
    composeFigado([
        'avaliado'                => true,
        'tamanho'                 => 'hepatomegalia_moderada',
        'bordas'                  => 'abauladas',
        'contornos'               => 'regulares',
        'ecogenicidade'           => 'usual',
        'ecotextura'              => 'homogenea',
        'sistema_porta_anatomico' => true,
    ]));

/* ---- Caso 4: achado focal via observacoes (anexado ao final) ---- */
$comObs = $clarinha;
$comObs['observacoes'] = 'Nota-se área ovalada, parcialmente definida, hiperecogênica e grosseira, medindo '
    . 'aproximadamente 1,27 cm x 0,74 cm, em topografia de lobos esquerdos.';
checa('Achado focal por observacoes',
    'FÍGADO: Topografia, tamanho e morfologia usuais, com bordas afiladas e contornos regulares. '
    . 'Discreta hiperecogenicidade difusa e ecotextura homogênea. '
    . 'Sistema porta e veias hepáticas com calibre e distribuição anatômicos. '
    . 'Nota-se área ovalada, parcialmente definida, hiperecogênica e grosseira, medindo '
    . 'aproximadamente 1,27 cm x 0,74 cm, em topografia de lobos esquerdos.',
    composeFigado($comObs));

/* ---- Caso 5: nao avaliado ---- */
checa('Nao avaliado', 'FÍGADO: Não avaliado.', composeFigado(['avaliado' => false]));

echo "\n--- Amostra: Clarinha ---\n" . composeFigado($clarinha) . "\n";
echo "\n--- Amostra: Tudo Normal ---\n" . composeFigado($normal) . "\n";

if ($falhas === 0) { echo "\nOK: round-trip do Figado verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
