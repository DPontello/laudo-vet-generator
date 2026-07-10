<?php

declare(strict_types=1);

/**
 * Round-trip dos Intestinos. O caso da Clarinha bate VERBATIM (faixas por
 * segmento duodeno/jejuno/cólon + frases contextuais fixas).
 *
 * Uso: php tests/intestinos-round-trip.php
 */

require __DIR__ . '/../src/composers/intestinos.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Intestinos da Clarinha (verbatim) ---- */
$clarinha = [
    'avaliado'                 => true,
    'estratificacao_mantida'   => true,
    'parede'                   => 'normoespessa',
    'medidas'                  => [
        'duodeno_min_cm' => 0.45, 'duodeno_max_cm' => 0.48,
        'jejuno_min_cm'  => 0.31, 'jejuno_max_cm'  => 0.42,
        'colon_min_cm'   => 0.11, 'colon_max_cm'   => 0.18,
    ],
    'peristaltismo_preservado' => true,
    'obstrucao_ausente'        => true,
];
checa('Clarinha (verbatim)',
    'INTESTINOS: Paredes com manutenção da estrutura laminar de camadas e normoespessas, medindo aproximadamente '
    . '0,45 cm a 0,48 cm em duodeno, 0,31 cm a 0,42 cm em jejuno e 0,11 cm a 0,18 cm em cólon. Intestino delgado '
    . 'repleto por discreta quantidade de gás e conteúdo pastoso. Movimentos peristálticos preservados. Cólon '
    . 'repleto por gás e conteúdo ecogênico formador de sombreamento acústico posterior (fezes). Ausência de '
    . 'imagens sugestivas de processo obstrutivo ou corpo estranho intestinal.',
    composeIntestinos($clarinha));

/* ---- Caso 2: Tudo Normal (sem medidas) ---- */
$normal = [
    'avaliado'                 => true,
    'estratificacao_mantida'   => true,
    'parede'                   => 'normoespessa',
    'medidas'                  => [],
    'peristaltismo_preservado' => true,
    'obstrucao_ausente'        => true,
];
checa('Tudo Normal',
    'INTESTINOS: Paredes com manutenção da estrutura laminar de camadas e normoespessas. Intestino delgado '
    . 'repleto por discreta quantidade de gás e conteúdo pastoso. Movimentos peristálticos preservados. Cólon '
    . 'repleto por gás e conteúdo ecogênico formador de sombreamento acústico posterior (fezes). Ausência de '
    . 'imagens sugestivas de processo obstrutivo ou corpo estranho intestinal.',
    composeIntestinos($normal));

/* ---- Caso 3: Gato (íleo + tres porcoes do cólon) ---- */
$gato = [
    'avaliado'                 => true,
    'estratificacao_mantida'   => true,
    'parede'                   => 'normoespessa',
    'padrao'                   => 'gato',
    'medidas'                  => [
        'duodeno_min_cm' => 0.45, 'duodeno_max_cm' => 0.48,
        'jejuno_min_cm'  => 0.31, 'jejuno_max_cm'  => 0.42,
        'ileo_min_cm'    => 0.25, 'ileo_max_cm'    => 0.30,
        'colon_ascendente_min_cm' => 0.20, 'colon_ascendente_max_cm' => 0.22,
        'colon_transverso_min_cm' => 0.18, 'colon_transverso_max_cm' => 0.20,
        'colon_descendente_min_cm' => 0.15, 'colon_descendente_max_cm' => 0.17,
    ],
    'peristaltismo_preservado' => true,
    'obstrucao_ausente'        => true,
];
checa('Gato (6 segmentos)',
    'INTESTINOS: Paredes com manutenção da estrutura laminar de camadas e normoespessas, medindo aproximadamente '
    . '0,45 cm a 0,48 cm em duodeno, 0,31 cm a 0,42 cm em jejuno, 0,25 cm a 0,30 cm em íleo, '
    . '0,20 cm a 0,22 cm em cólon ascendente, 0,18 cm a 0,20 cm em cólon transverso e '
    . '0,15 cm a 0,17 cm em cólon descendente. Intestino delgado '
    . 'repleto por discreta quantidade de gás e conteúdo pastoso. Movimentos peristálticos preservados. Cólon '
    . 'repleto por gás e conteúdo ecogênico formador de sombreamento acústico posterior (fezes). Ausência de '
    . 'imagens sugestivas de processo obstrutivo ou corpo estranho intestinal.',
    composeIntestinos($gato));

/* ---- Caso 4: nao avaliado ---- */
checa('Nao avaliado', 'INTESTINOS: Não avaliados.', composeIntestinos(['avaliado' => false]));

echo "\n--- Amostra: Clarinha ---\n" . composeIntestinos($clarinha) . "\n";
echo "\n--- Amostra: Tudo Normal ---\n" . composeIntestinos($normal) . "\n";

if ($falhas === 0) { echo "\nOK: round-trip dos Intestinos verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
