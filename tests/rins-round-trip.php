<?php

declare(strict_types=1);

/**
 * Round-trip dos Rins: prova que o compositor reconstroi a redacao canonica do
 * modelo a partir do payload. O caso da Clarinha bate verbatim, EXCETO por duas
 * diferencas conscientes (decisao "Hibrido": base = texto canonico do modelo):
 *   - mantemos o rotulo "(mineralização diverticular / microcálculos)";
 *   - mantemos "litíases ou" no fecho.
 * A Clarinha real omite ambos; a diferenca esta documentada abaixo.
 *
 * Uso: php tests/rins-round-trip.php
 */

require __DIR__ . '/../src/composers/rins.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) {
        echo "OK  {$rotulo}\n";
        return;
    }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Rins da Clarinha (texto canonico dos achados dela) ---- */
$clarinha = [
    'avaliado'               => true,
    'simetria'               => 'simetricos',
    'medida_esquerdo_cm'     => 4.54,
    'medida_direito_cm'      => 4.34,
    'contornos'              => 'regulares',
    'ecogenicidade_cortical' => 'hiperecogenicidade_difusa',
    'ecogenicidade_grau'     => 'discreta',
    'ecotextura'             => 'homogenea',
    'relacao_corticomedular' => 'mantida',
    'achados'                => [
        ['tipo' => 'mineralizacao_diverticular', 'lado' => 'bilateral', 'grau' => 'discreta'],
    ],
    'litiase_ausente'        => true,
];
$esperadoClarinha = 'RINS: Simétricos. Topografia e dimensões usuais, medindo aproximadamente 4,54 cm o esquerdo '
    . 'e 4,34 cm o direito, com contornos regulares. Discreta hiperecogenicidade difusa da região cortical e '
    . 'ecotextura homogênea, com manutenção de definição e relação corticomedular. Presença de discretos focos '
    . 'hiperecogênicos em topografia de recessos renais bilaterais (mineralização diverticular / microcálculos). '
    . 'Ausência de imagens sugestivas de litíases ou dilatação de pelves e ureteres.';
checa('Clarinha (canonico)', $esperadoClarinha, composeRins($clarinha));

/* Verbatim da Clarinha real, para referencia (difere so nos 2 pontos documentados). */
$clarinhaReal = 'RINS: Simétricos. Topografia e dimensões usuais, medindo aproximadamente 4,54 cm o esquerdo '
    . 'e 4,34 cm o direito, com contornos regulares. Discreta hiperecogenicidade difusa da região cortical e '
    . 'ecotextura homogênea, com manutenção de definição e relação corticomedular. Presença de discretos focos '
    . 'hiperecogênicos em topografia de recessos renais bilaterais. '
    . 'Ausência de imagens sugestivas de dilatação de pelves e ureteres.';
// A saida canonica difere do verbatim real nos 2 pontos documentados (decisao
// Hibrido). Travamos isso: se um dia passar a bater verbatim, revisar a decisao.
checa('difere do verbatim real (decisao Hibrido)', 'diferente',
    $clarinhaReal !== composeRins($clarinha) ? 'diferente' : 'igual');

/* ---- Caso 2: Tudo Normal (defaults do schema) ---- */
$normal = [
    'avaliado'               => true,
    'simetria'               => 'simetricos',
    'medida_esquerdo_cm'     => null,
    'medida_direito_cm'      => null,
    'contornos'              => 'regulares',
    'ecogenicidade_cortical' => 'usual',
    'ecotextura'             => 'homogenea',
    'relacao_corticomedular' => 'mantida',
    'achados'                => [],
    'litiase_ausente'        => true,
];
$esperadoNormal = 'RINS: Simétricos. Topografia e dimensões usuais, com contornos regulares. Ecogenicidade usual '
    . 'e ecotextura homogênea, com manutenção de definição e relação corticomedular. Ausência de imagens '
    . 'sugestivas de litíases ou dilatação de pelves e ureteres.';
checa('Tudo Normal', $esperadoNormal, composeRins($normal));

/* ---- Caso 3: nao avaliado ---- */
checa('Nao avaliado', 'RINS: Não avaliados.', composeRins(['avaliado' => false]));

/* ---- Caso 4: cobertura de cada tipo de achado (trava a redacao) ---- */
checa('achado nefrocalcinose',
    'Presença de diminutos pontos hiperecogênicos difusamente distribuídos bilateralmente (nefrocalcinose).',
    rinsAchado(['tipo' => 'nefrocalcinose', 'lado' => 'bilateral']));

checa('achado infarto_fibrose',
    'Presença de discretas áreas hiperecogênicas estendendo-se da região cortical à medular renal, '
    . 'provocando discreta depressão da cápsula renal, em rim esquerdo (infarto / fibrose).',
    rinsAchado(['tipo' => 'infarto_fibrose', 'lado' => 'esquerdo']));

checa('achado cistos',
    'Presença de estrutura circular anecoica em região cortical, medindo aproximadamente 0,30 cm '
    . 'em rim direito (cistos).',
    rinsAchado(['tipo' => 'cistos', 'lado' => 'direito', 'medida_cm' => 0.30]));

checa('achado mineralizacao_diverticular',
    'Presença de discretos focos hiperecogênicos em topografia de recessos renais bilaterais '
    . '(mineralização diverticular / microcálculos).',
    rinsAchado(['tipo' => 'mineralizacao_diverticular', 'lado' => 'bilateral', 'grau' => 'discreta']));

checa('achado sinal_medular',
    'Presença de halo hiperecogênico na região medular, paralelo à transição corticomedular '
    . 'em rim esquerdo (sinal de medular).',
    rinsAchado(['tipo' => 'sinal_medular', 'lado' => 'esquerdo']));

checa('achado nefrolito',
    'Presença de estrutura de interface hiperecogênica de superfície curva e regular, formadora de '
    . 'sombreamento acústico posterior, medindo aproximadamente 0,45 cm em rim direito (nefrólito).',
    rinsAchado(['tipo' => 'nefrolito', 'lado' => 'direito', 'medida_cm' => 0.45]));

echo "\n--- Amostra: Clarinha ---\n" . composeRins($clarinha) . "\n";
echo "\n--- Amostra: Tudo Normal ---\n" . composeRins($normal) . "\n";

if ($falhas === 0) {
    echo "\nOK: round-trip dos Rins verde.\n";
    exit(0);
}
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
