<?php

declare(strict_types=1);

/**
 * Round-trip do Sistema Reprodutor. O caso da Clarinha (apenas OSH, sem coto)
 * bate VERBATIM. Demais casos travam a redacao canonica de cada bloco por
 * sexo/status.
 *
 * Uso: php tests/reprodutor-round-trip.php
 */

require __DIR__ . '/../src/composers/reprodutor.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Clarinha (OSH, sem coto) — verbatim ---- */
$clarinha = [
    'utero_ovarios_ausentes' => ['avaliado' => true],
];
checa('Clarinha OSH (verbatim)',
    'ÚTERO E OVÁRIOS: Não visibilizados (histórico de ovariohisterectomia).',
    composeReprodutor($clarinha));

/* ---- Caso 2: OSH com coto uterino ---- */
checa('OSH com coto',
    'ÚTERO E OVÁRIOS: Não visibilizados (histórico de ovariohisterectomia). Coto uterino medindo aproximadamente '
    . '0,50 cm de diâmetro, com paredes finas e regulares, sem acúmulo de conteúdo intraluminal.',
    composeReprodutor(['utero_ovarios_ausentes' => ['avaliado' => true, 'coto_uterino_cm' => 0.50]]));

/* ---- Caso 3: Utero + Ovarios (femea integra) — dois blocos ---- */
checa('Utero + Ovarios',
    "ÚTERO: Parcialmente visibilizado com diâmetro de aproximadamente 1,20 cm em corpo, 0,80 cm em corno esquerdo "
    . "e 0,85 cm em corno direito. Paredes finas e regulares, sem acúmulo de conteúdo intraluminal."
    . "\n\n"
    . "OVÁRIOS: Forma e contorno usuais, medindo aproximadamente 0,90 cm o esquerdo e 0,85 cm o direito. "
    . "Ecogenicidade usual e ecotextura homogênea.",
    composeReprodutor([
        'utero' => [
            'avaliado' => true, 'status' => 'usual',
            'corpo_cm' => 1.20, 'corno_esquerdo_cm' => 0.80, 'corno_direito_cm' => 0.85,
        ],
        'ovarios' => [
            'avaliado' => true, 'medida_esquerdo_cm' => 0.90, 'medida_direito_cm' => 0.85,
            'ecotextura' => 'homogenea', 'estruturas_anecoicas' => 'nenhuma',
        ],
    ]));

/* ---- Caso 4: Utero aumentado (conteudo anecogenico) ---- */
checa('Utero aumentado',
    'ÚTERO: Aumentado de volume, com diâmetro de aproximadamente 3,00 cm em corpo, com acúmulo de conteúdo '
    . 'anecogênico homogêneo. Paredes espessadas e irregulares, apresentando múltiplas estruturas circulares '
    . 'anecoicas (cistos).',
    composeReprodutor(['utero' => [
        'avaliado' => true, 'status' => 'aumentado', 'corpo_cm' => 3.00, 'conteudo' => 'anecogenico',
    ]]));

/* ---- Caso 5: Ovarios com cistos ---- */
checa('Ovarios com cistos',
    'OVÁRIOS: Forma e contorno usuais, medindo aproximadamente 1,10 cm o esquerdo e 1,05 cm o direito. '
    . 'Ecogenicidade usual e ecotextura heterogênea, pela presença de estruturas circulares anecoicas. '
    . 'Diag. diferenciais: cistos ovarianos.',
    composeReprodutor(['ovarios' => [
        'avaliado' => true, 'medida_esquerdo_cm' => 1.10, 'medida_direito_cm' => 1.05,
        'ecotextura' => 'heterogenea', 'estruturas_anecoicas' => 'cistos',
    ]]));

/* ---- Caso 6: Prostata usual (macho) ---- */
checa('Prostata usual',
    'PRÓSTATA: Topografia, forma e contorno usuais, medindo aproximadamente 2,50 cm x 2,00 cm x 1,80 cm. '
    . 'Ecogenicidade usual e ecotextura homogênea.',
    composeReprodutor(['prostata' => [
        'avaliado' => true, 'tamanho' => 'usual', 'medida_x_cm' => 2.50, 'medida_y_cm' => 2.00, 'medida_z_cm' => 1.80,
    ]]));

/* ---- Caso 7: Prostata aumentada ---- */
checa('Prostata aumentada',
    'PRÓSTATA: Aumentada de volume, medindo aproximadamente 3,00 cm x 2,50 cm x 2,20 cm. Ecogenicidade usual e '
    . 'ecotextura homogênea.',
    composeReprodutor(['prostata' => [
        'avaliado' => true, 'tamanho' => 'aumentada', 'medida_x_cm' => 3.00, 'medida_y_cm' => 2.50, 'medida_z_cm' => 2.20,
    ]]));

/* ---- Caso 8: Testiculos presentes ---- */
checa('Testiculos presentes',
    'TESTÍCULOS: Simétricos, medindo aproximadamente 1,50 cm x 1,00 cm o esquerdo e 1,40 cm x 0,90 cm o direito. '
    . 'Parênquima apresentando ecogenicidade e ecotextura usuais. Mediastino preservado e centralizado.',
    composeReprodutor(['testiculos' => [
        'avaliado' => true, 'status' => 'presentes',
        'esquerdo_x_cm' => 1.50, 'esquerdo_y_cm' => 1.00, 'direito_x_cm' => 1.40, 'direito_y_cm' => 0.90,
    ]]));

/* ---- Caso 9: Testiculos ausentes (orquiectomia) ---- */
checa('Testiculos ausentes',
    'TESTÍCULOS: Não visibilizados (histórico de orquiectomia).',
    composeReprodutor(['testiculos' => ['avaliado' => true, 'status' => 'ausentes_orquiectomia']]));

/* ---- Caso 10: nenhum bloco ativo -> vazio ---- */
checa('Nenhum bloco', '', composeReprodutor([
    'utero_ovarios_ausentes' => ['avaliado' => false],
    'utero'                  => ['avaliado' => false],
    'prostata'               => ['avaliado' => false],
]));

echo "\n--- Amostra: Clarinha (OSH) ---\n" . composeReprodutor($clarinha) . "\n";

if ($falhas === 0) { echo "\nOK: round-trip do Sistema Reprodutor verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
