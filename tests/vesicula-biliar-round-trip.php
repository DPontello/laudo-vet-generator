<?php

declare(strict_types=1);

/**
 * Round-trip da Vesicula Biliar. O caso da Clarinha usa a redacao canonica do
 * modelo ("sedimento ecogênico ... (lama biliar)"). A Clarinha real escreve
 * "hiperecogênico aglomerado" e omite "(lama biliar)" — diferenca consciente
 * pela decisao "Hibrido".
 *
 * Uso: php tests/vesicula-biliar-round-trip.php
 */

require __DIR__ . '/../src/composers/vesicula-biliar.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Vesicula da Clarinha (canonico) ---- */
$clarinha = [
    'avaliado'             => true,
    'parede_normoespessa'  => true,
    'espessura_cm'         => 0.15,
    'conteudo_anecogenico' => true,
    'lama_biliar'          => ['presente' => true, 'quantidade' => 'moderada', 'disposicao' => 'suspensao'],
    'litiase_ausente'      => true,
];
checa('Clarinha (canonico)',
    'VESÍCULA BILIAR: Repleta, com paredes normoespessas, medindo aproximadamente 0,15 cm. Conteúdo anecogênico '
    . 'acompanhado de moderada quantidade de sedimento ecogênico em suspensão e não formador de sombreamento '
    . 'acústico posterior (lama biliar). Ausência de imagens sugestivas de litíase ou processo obstrutivo de '
    . 'vias biliares.',
    composeVesiculaBiliar($clarinha));

/* ---- Caso 2: Tudo Normal (defaults do schema) ---- */
$normal = [
    'avaliado'             => true,
    'parede_normoespessa'  => true,
    'espessura_cm'         => null,
    'conteudo_anecogenico' => true,
    'lama_biliar'          => ['presente' => false],
    'litiase_ausente'      => true,
];
checa('Tudo Normal',
    'VESÍCULA BILIAR: Repleta, com paredes normoespessas. Conteúdo anecogênico e homogêneo. Ausência de imagens '
    . 'sugestivas de litíase ou processo obstrutivo de vias biliares.',
    composeVesiculaBiliar($normal));

/* ---- Caso 3: lama depositada ---- */
checa('Lama depositada',
    'VESÍCULA BILIAR: Repleta, com paredes normoespessas. Conteúdo anecogênico acompanhado de discreta '
    . 'quantidade de sedimento ecogênico depositado em porção dependente e não formador de sombreamento acústico '
    . 'posterior (lama biliar). Ausência de imagens sugestivas de litíase ou processo obstrutivo de vias biliares.',
    composeVesiculaBiliar([
        'avaliado'            => true,
        'parede_normoespessa' => true,
        'lama_biliar'         => ['presente' => true, 'quantidade' => 'discreta', 'disposicao' => 'depositado'],
        'litiase_ausente'     => true,
    ]));

/* ---- Caso 4: nao avaliado ---- */
checa('Nao avaliado', 'VESÍCULA BILIAR: Não avaliada.', composeVesiculaBiliar(['avaliado' => false]));

echo "\n--- Amostra: Clarinha ---\n" . composeVesiculaBiliar($clarinha) . "\n";
echo "\n--- Amostra: Tudo Normal ---\n" . composeVesiculaBiliar($normal) . "\n";

if ($falhas === 0) { echo "\nOK: round-trip da Vesicula Biliar verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
