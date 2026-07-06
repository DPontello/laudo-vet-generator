<?php

declare(strict_types=1);

/**
 * Round-trip da Bexiga: prova que o compositor reconstroi, a partir do payload,
 * exatamente o texto do laudo real (exemplo-laudo-clarinha.txt).
 *
 * Uso: php tests/bexiga-round-trip.php
 */

require __DIR__ . '/../src/composers/bexiga.php';

/** Payload equivalente a Bexiga da Clarinha (ver docs/referencia/laudo.schema.json). */
$payloadClarinha = [
    'avaliado'  => true,
    'replecao'  => 'discreta',
    'parede'    => ['aspecto' => 'normoespessa', 'espessura_cm' => 0.11],
    'conteudo'  => [
        'homogeneo' => false,
        'sedimento' => ['presente' => true, 'quantidade' => 'discreta', 'tipo' => 'sedimentos_urinarios'],
    ],
    'litiase'   => ['cistolito_presente' => false, 'cistolito_cm' => null],
    'observacoes' => '',
];

$esperado = 'BEXIGA: Discretamente repleta, com topografia e formato usuais. '
    . 'Margem interna lisa e parede normoespessa, medindo aproximadamente 0,11 cm. '
    . 'Conteúdo anecogênico acompanhado de discreta quantidade de partículas ecogênicas em flutuação, '
    . 'não formadoras de artefato. Ausência de imagens sugestivas de litíases.';

$obtido = composeBexiga($payloadClarinha);

echo "--- Esperado (laudo real) ---\n{$esperado}\n\n";
echo "--- Obtido (compositor) ---\n{$obtido}\n\n";

// Caso extra: "Tudo Normal" (defaults do schema, sem sedimento nem medida).
$payloadNormal = [
    'avaliado'  => true,
    'replecao'  => 'moderada',
    'parede'    => ['aspecto' => 'normoespessa', 'espessura_cm' => null],
    'conteudo'  => ['homogeneo' => true, 'sedimento' => ['presente' => false]],
    'litiase'   => ['cistolito_presente' => false],
];
echo "--- Tudo Normal ---\n" . composeBexiga($payloadNormal) . "\n\n";

if ($obtido === $esperado) {
    echo "OK: round-trip da Bexiga bate com o laudo real.\n";
    exit(0);
}

echo "FALHA: texto divergente.\n";
exit(1);
