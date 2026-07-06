<?php

declare(strict_types=1);

/**
 * Round-trip do Baco. O caso da Clarinha reproduz a parte parametrizavel
 * (esplenomegalia moderada + ecogenicidade/ecotextura usuais + vascularizacao).
 * As "áreas circulares" da Clarinha sao achado nodular em texto livre, passadas
 * por `observacoes` (caso separado, anexadas ao final). A Clarinha real separa
 * "Topografia e morfologia usuais." e omite o rotulo "(esplenomegalia moderada)";
 * usamos o canonico do modelo.
 *
 * Uso: php tests/baco-round-trip.php
 */

require __DIR__ . '/../src/composers/baco.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Baco da Clarinha (parte parametrizavel) ---- */
$clarinha = [
    'avaliado'                 => true,
    'tamanho'                  => 'esplenomegalia_moderada',
    'bordas'                   => 'abauladas',
    'contornos'                => 'regulares',
    'ecogenicidade'            => 'usual',
    'ecotextura'               => 'homogenea',
    'vascularizacao_anatomica' => true,
];
checa('Clarinha (parametrizavel)',
    'BAÇO: Aumento moderado de tamanho, com bordas abauladas e contornos regulares (esplenomegalia moderada). '
    . 'Ecogenicidade usual e ecotextura homogênea. Vascularização apresentando calibre e distribuição anatômicos.',
    composeBaco($clarinha));

/* ---- Caso 2: Tudo Normal ---- */
$normal = [
    'avaliado'                 => true,
    'tamanho'                  => 'usual',
    'bordas'                   => 'afiladas',
    'contornos'                => 'regulares',
    'ecogenicidade'            => 'usual',
    'ecotextura'               => 'homogenea',
    'vascularizacao_anatomica' => true,
];
checa('Tudo Normal',
    'BAÇO: Topografia, tamanho e morfologia usuais, com bordas afiladas e contornos regulares. '
    . 'Ecogenicidade usual e ecotextura homogênea. Vascularização apresentando calibre e distribuição anatômicos.',
    composeBaco($normal));

/* ---- Caso 3: achado nodular via observacoes (anexado ao final) ---- */
$comObs = $clarinha;
$comObs['observacoes'] = 'Notam-se algumas áreas circulares, pouco definidas, hipoecogênicas, medindo '
    . 'aproximadamente 1,06 cm x 0,96 cm.';
checa('Achado nodular por observacoes',
    'BAÇO: Aumento moderado de tamanho, com bordas abauladas e contornos regulares (esplenomegalia moderada). '
    . 'Ecogenicidade usual e ecotextura homogênea. Vascularização apresentando calibre e distribuição anatômicos. '
    . 'Notam-se algumas áreas circulares, pouco definidas, hipoecogênicas, medindo aproximadamente 1,06 cm x 0,96 cm.',
    composeBaco($comObs));

/* ---- Caso 4: nao avaliado ---- */
checa('Nao avaliado', 'BAÇO: Não avaliado.', composeBaco(['avaliado' => false]));

echo "\n--- Amostra: Clarinha ---\n" . composeBaco($clarinha) . "\n";
echo "\n--- Amostra: Tudo Normal ---\n" . composeBaco($normal) . "\n";

if ($falhas === 0) { echo "\nOK: round-trip do Baco verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
