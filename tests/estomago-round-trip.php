<?php

declare(strict_types=1);

/**
 * Round-trip do Estomago. O caso da Clarinha bate VERBATIM com o laudo real
 * (repleto por gás + paredes espessas em algumas porções + faixa 0,39-0,62 cm).
 *
 * Uso: php tests/estomago-round-trip.php
 */

require __DIR__ . '/../src/composers/estomago.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Estomago da Clarinha (verbatim) ---- */
$clarinha = [
    'avaliado'               => true,
    'conteudo'               => 'gas',
    'parede'                 => 'espessada',
    'espessura_min_cm'       => 0.39,
    'espessura_max_cm'       => 0.62,
    'estratificacao_mantida' => true,
];
checa('Clarinha (verbatim)',
    'ESTÔMAGO: Topografia usual, repleto por gás. Paredes espessas em algumas porções, medindo aproximadamente '
    . '0,39 cm a 0,62 cm, com manutenção da estratificação parietal de camadas.',
    composeEstomago($clarinha));

/* ---- Caso 2: Tudo Normal ---- */
$normal = [
    'avaliado'               => true,
    'conteudo'               => 'gas_e_ingesta',
    'parede'                 => 'normoespessa',
    'espessura_min_cm'       => null,
    'espessura_max_cm'       => null,
    'estratificacao_mantida' => true,
];
checa('Tudo Normal',
    'ESTÔMAGO: Topografia usual, repleto por gás e conteúdo pastoso particulado sugestivo de ingesta. Paredes '
    . 'normoespessas, com manutenção da estratificação parietal de camadas.',
    composeEstomago($normal));

/* ---- Caso 3: nao avaliado ---- */
checa('Nao avaliado', 'ESTÔMAGO: Não avaliado.', composeEstomago(['avaliado' => false]));

echo "\n--- Amostra: Clarinha ---\n" . composeEstomago($clarinha) . "\n";
echo "\n--- Amostra: Tudo Normal ---\n" . composeEstomago($normal) . "\n";

if ($falhas === 0) { echo "\nOK: round-trip do Estomago verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
