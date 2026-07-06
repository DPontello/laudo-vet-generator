<?php

declare(strict_types=1);

/**
 * Round-trip da Cavidade Abdominal. O caso da Clarinha (sem alteracoes em
 * linfonodos/vasos/liquido/massas + hernia umbilical) bate verbatim, com a ordem
 * canonica do modelo (frase "sem alteracoes" antes da hernia; a Clarinha real
 * inverte a ordem — diferenca aceita pela decisao "Hibrido").
 *
 * Uso: php tests/cavidade-abdominal-round-trip.php
 */

require __DIR__ . '/../src/composers/cavidade-abdominal.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($esperado === $obtido) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* ---- Caso 1: Clarinha (sem alteracoes + hernia umbilical) ---- */
$clarinha = [
    'avaliado'       => true,
    'sem_alteracoes' => true,
    'hernia'         => ['presente' => true, 'descontinuidade_cm' => 0.41, 'regiao' => 'umbilical'],
];
checa('Clarinha (canonico)',
    'CAVIDADE ABDOMINAL: Sem evidências ultrassonográficas de alterações em linfonodos e vasos abdominais. '
    . 'Não se observou líquido livre ou massas abdominais. Nota-se descontinuidade de aproximadamente 0,41 cm na '
    . 'musculatura abdominal em região umbilical, com conteúdo hipoecogênico homogêneo sugestivo de tecido '
    . 'adiposo.',
    composeCavidadeAbdominal($clarinha));

/* ---- Caso 2: Tudo Normal ---- */
$normal = ['avaliado' => true, 'sem_alteracoes' => true];
checa('Tudo Normal',
    'CAVIDADE ABDOMINAL: Sem evidências ultrassonográficas de alterações em linfonodos e vasos abdominais. '
    . 'Não se observou líquido livre ou massas abdominais.',
    composeCavidadeAbdominal($normal));

/* ---- Caso 3: linfonodos + mesenterio + liquido livre ---- */
checa('Linfonodos + mesenterio + liquido',
    'CAVIDADE ABDOMINAL: Linfonodos jejunais aumentados de tamanho. Ecogenicidade e ecotextura usuais, com '
    . 'margens regulares. Mesentério difusamente espessado e hiperecogênico (mesentério reativo). Presença de '
    . 'moderada quantidade de líquido livre anecogênico homogêneo em sítios hepatodiafragmático.',
    composeCavidadeAbdominal([
        'avaliado'              => true,
        'sem_alteracoes'        => false,
        'linfonodos_aumentados' => ['presente' => true, 'grupos' => ['jejunais']],
        'mesenterio_reativo'    => true,
        'liquido_livre'         => ['presente' => true, 'quantidade' => 'moderada', 'aspecto' => 'anecogenico', 'sitio' => 'hepatodiafragmatico'],
    ]));

/* ---- Caso 4: hernia inguinal esquerda (leva "redutível (hérnia inguinal)") ---- */
checa('Hernia inguinal esquerda',
    'CAVIDADE ABDOMINAL: Nota-se descontinuidade de aproximadamente 1,20 cm na musculatura abdominal em região '
    . 'inguinal esquerda, com conteúdo hipoecogênico homogêneo sugestivo de tecido adiposo, redutível '
    . '(hérnia inguinal).',
    composeCavidadeAbdominal([
        'avaliado'       => true,
        'sem_alteracoes' => false,
        'hernia'         => ['presente' => true, 'descontinuidade_cm' => 1.20, 'regiao' => 'inguinal_esquerda'],
    ]));

/* ---- Caso 5: multiplos grupos de linfonodos ---- */
checa('Linfonodos multiplos grupos',
    'CAVIDADE ABDOMINAL: Linfonodos abdominais e ilíacos mediais aumentados de tamanho. Ecogenicidade e '
    . 'ecotextura usuais, com margens regulares.',
    composeCavidadeAbdominal([
        'avaliado'              => true,
        'sem_alteracoes'        => false,
        'linfonodos_aumentados' => ['presente' => true, 'grupos' => ['abdominais', 'iliacos_mediais']],
    ]));

/* ---- Caso 6: nao avaliado ---- */
checa('Nao avaliado', 'CAVIDADE ABDOMINAL: Não avaliada.', composeCavidadeAbdominal(['avaliado' => false]));

echo "\n--- Amostra: Clarinha ---\n" . composeCavidadeAbdominal($clarinha) . "\n";
echo "\n--- Amostra: Tudo Normal ---\n" . composeCavidadeAbdominal($normal) . "\n";

if ($falhas === 0) { echo "\nOK: round-trip da Cavidade Abdominal verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
