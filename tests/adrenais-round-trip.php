<?php

declare(strict_types=1);

/**
 * Round-trip das Adrenais. O caso da Clarinha usa a estrutura canonica do modelo
 * (frase-lider "Topografia e morfologia usuais" separada e ecogenicidade ao
 * final). A Clarinha real funde a frase-lider na adrenal esquerda e antecipa a
 * ecogenicidade — diferenca de posicao esperada pela decisao "Hibrido".
 *
 * Uso: php tests/adrenais-round-trip.php
 */

require __DIR__ . '/../src/composers/adrenais.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Adrenais da Clarinha (canonico) ---- */
$clarinha = [
    'avaliado' => true,
    'esquerda' => [
        'visibilizacao'   => 'visibilizada',
        'aumentada'       => true,
        'polo_caudal_cm'  => 0.74,
        'polo_cranial_cm' => 0.69,
        'comprimento_cm'  => 1.76,
    ],
    'direita' => ['visibilizacao' => 'nao_visibilizada'],
];
$esperadoClarinha = 'ADRENAIS: Topografia e morfologia usuais. Adrenal esquerda aumentada de tamanho, medindo '
    . 'aproximadamente 0,74 cm em polo caudal, 0,69 cm em polo cranial e 1,76 cm de comprimento. Adrenal direita '
    . 'não visibilizada. Ecogenicidade usual e ecotextura homogênea.';
checa('Clarinha (canonico)', $esperadoClarinha, composeAdrenais($clarinha));

/* ---- Caso 2: Tudo Normal (defaults do schema) ---- */
$normal = [
    'avaliado' => true,
    'esquerda' => ['visibilizacao' => 'visibilizada', 'aumentada' => false],
    'direita'  => ['visibilizacao' => 'visibilizada', 'aumentada' => false],
];
checa('Tudo Normal', 'ADRENAIS: Topografia e morfologia usuais. Ecogenicidade usual e ecotextura homogênea.',
    composeAdrenais($normal));

/* ---- Caso 3: ambas nao visibilizadas ---- */
checa('Ambas nao visibilizadas', 'ADRENAIS: Não visibilizadas.', composeAdrenais([
    'avaliado' => true,
    'esquerda' => ['visibilizacao' => 'nao_visibilizada'],
    'direita'  => ['visibilizacao' => 'nao_visibilizada'],
]));

/* ---- Caso 4: nao avaliado ---- */
checa('Nao avaliado', 'ADRENAIS: Não avaliadas.', composeAdrenais(['avaliado' => false]));

/* ---- Caso 5: parcialmente visibilizada + aumentada + medidas ---- */
checa('Parcial + aumentada + medidas',
    'ADRENAIS: Topografia e morfologia usuais. Adrenal esquerda parcialmente visibilizada, aumentada de tamanho, '
    . 'medindo aproximadamente 0,80 cm em polo caudal, 0,70 cm em polo cranial e 1,50 cm de comprimento. '
    . 'Ecogenicidade usual e ecotextura homogênea.',
    composeAdrenais([
        'avaliado' => true,
        'esquerda' => [
            'visibilizacao'   => 'parcialmente_visibilizada',
            'aumentada'       => true,
            'polo_caudal_cm'  => 0.80,
            'polo_cranial_cm' => 0.70,
            'comprimento_cm'  => 1.50,
        ],
        'direita' => ['visibilizacao' => 'visibilizada', 'aumentada' => false],
    ]));

echo "\n--- Amostra: Clarinha ---\n" . composeAdrenais($clarinha) . "\n";
echo "\n--- Amostra: Tudo Normal ---\n" . composeAdrenais($normal) . "\n";

if ($falhas === 0) { echo "\nOK: round-trip das Adrenais verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
