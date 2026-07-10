<?php

declare(strict_types=1);

/**
 * Runner da suite de testes.
 *
 * Roda cada tests/*-round-trip.php (e os demais tests/*.php, exceto este) em um
 * processo PHP separado — assim um `exit()`/estado global de um teste nao afeta
 * os outros — e agrega os codigos de saida. Sai 0 se todos passarem, 1 se algum
 * falhar. Substitui o antigo one-liner de shell (que nao propagava a falha).
 *
 * Uso: php tests/all.php
 */

$dir = __DIR__;
$arquivos = glob($dir . '/*.php') ?: [];
sort($arquivos);

$php = PHP_BINARY;
$verdes = 0;
$vermelhos = [];

foreach ($arquivos as $arquivo) {
    if (basename($arquivo) === 'all.php') { continue; }

    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($arquivo) . ' 2>&1';
    exec($cmd, $saida, $codigo);

    if ($codigo === 0) {
        $verdes++;
        echo "OK    " . basename($arquivo) . "\n";
    } else {
        $vermelhos[] = basename($arquivo);
        echo "FALHA " . basename($arquivo) . "\n";
        echo '      ' . implode("\n      ", array_slice($saida, -4)) . "\n";
    }
    $saida = [];
}

echo "\n==== " . $verdes . ' verdes, ' . count($vermelhos) . " vermelhos ====\n";
if ($vermelhos) {
    echo 'Vermelhos: ' . implode(', ', $vermelhos) . "\n";
    exit(1);
}
echo "Suite verde.\n";
exit(0);
