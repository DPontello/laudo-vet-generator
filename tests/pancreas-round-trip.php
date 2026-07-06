<?php

declare(strict_types=1);

/**
 * Round-trip do Pancreas. O caso da Clarinha bate VERBATIM (parcialmente
 * visibilizado + espessura 0,76 cm + lobo direito). O Tudo Normal usa o default
 * do schema (nao_visibilizado), que e o achado normal do pancreas.
 *
 * Uso: php tests/pancreas-round-trip.php
 */

require __DIR__ . '/../src/composers/pancreas.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Pancreas da Clarinha (verbatim) ---- */
$clarinha = [
    'avaliado'              => true,
    'visibilizacao'         => 'parcialmente_visibilizado',
    'lobo'                  => 'direito',
    'espessura_cm'          => 0.76,
    'ecogenicidade_usual'   => true,
    'reatividade_adjacente' => false,
];
checa('Clarinha (verbatim)',
    'PÂNCREAS: Parcialmente visibilizado, com espessura de aproximadamente 0,76 cm em porção de lobo direito. '
    . 'Ecogenicidade usual e ecotextura homogênea, com contornos preservados. Ausência de reatividade tecidual '
    . 'adjacente.',
    composePancreas($clarinha));

/* ---- Caso 2: Tudo Normal (default nao_visibilizado) ---- */
$normal = [
    'avaliado'              => true,
    'visibilizacao'         => 'nao_visibilizado',
    'lobo'                  => 'direito',
    'ecogenicidade_usual'   => true,
    'reatividade_adjacente' => false,
];
checa('Tudo Normal',
    'PÂNCREAS: Não visibilizado. Ausência de reatividade tecidual em sua topografia.',
    composePancreas($normal));

/* ---- Caso 3: visibilizado no lobo esquerdo ---- */
checa('Visibilizado lobo esquerdo',
    'PÂNCREAS: Parcialmente visibilizado, com espessura de aproximadamente 0,90 cm em porção de lobo esquerdo. '
    . 'Ecogenicidade usual e ecotextura homogênea, com contornos preservados. Ausência de reatividade tecidual '
    . 'adjacente.',
    composePancreas([
        'avaliado'              => true,
        'visibilizacao'         => 'parcialmente_visibilizado',
        'lobo'                  => 'esquerdo',
        'espessura_cm'          => 0.90,
        'ecogenicidade_usual'   => true,
        'reatividade_adjacente' => false,
    ]));

/* ---- Caso 4: nao avaliado ---- */
checa('Nao avaliado', 'PÂNCREAS: Não avaliado.', composePancreas(['avaliado' => false]));

echo "\n--- Amostra: Clarinha ---\n" . composePancreas($clarinha) . "\n";
echo "\n--- Amostra: Tudo Normal ---\n" . composePancreas($normal) . "\n";

if ($falhas === 0) { echo "\nOK: round-trip do Pancreas verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
