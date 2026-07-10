<?php

declare(strict_types=1);

/**
 * Testes unitarios dos helpers compartilhados (src/composers/_helpers.php).
 *
 * Eles sao a base de praticamente todo o texto numerico e das enumeracoes dos
 * laudos; travar seu comportamento (incl. bordas) evita depender de rodar os 11
 * compositores para pegar uma regressao de formatacao.
 *
 * Uso: php tests/helpers-round-trip.php
 */

require __DIR__ . '/../src/composers/_helpers.php';

$falhas = 0;
function checa(string $rotulo, string $esperado, string $obtido): void
{
    global $falhas;
    if ($obtido === $esperado) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$esperado}\n  obtido:   {$obtido}\n";
}

/* formatarCm — decimal pt-BR com 2 casas, sem separador de milhar. */
checa('formatarCm inteiro',     '2,00',  formatarCm(2.0));
checa('formatarCm arredonda',   '0,11',  formatarCm(0.114));
checa('formatarCm arredonda2',  '0,12',  formatarCm(0.115));
checa('formatarCm zero',        '0,00',  formatarCm(0.0));
checa('formatarCm milhar',      '1234,50', formatarCm(1234.5));

/* faixaCm — "X cm a Y cm"; um lado; nenhum. */
checa('faixaCm ambos', '0,20 cm a 0,50 cm', faixaCm(0.2, 0.5));
checa('faixaCm so min', '0,20 cm',          faixaCm(0.2, null));
checa('faixaCm so max', '0,50 cm',          faixaCm(null, 0.5));
checa('faixaCm nenhum', '',                 faixaCm(null, null));
checa('faixaCm zero',   '0,00 cm a 0,30 cm', faixaCm(0.0, 0.3));

/* grauAdverbio — com fallback para valor desconhecido. */
checa('grauAdverbio discreta',  'Discretamente',  grauAdverbio('discreta'));
checa('grauAdverbio acentuada', 'Acentuadamente', grauAdverbio('acentuada'));
checa('grauAdverbio fallback',  'Moderadamente',  grauAdverbio('inexistente'));

/* grauAdjetivo — concordancia de genero/numero. */
checa('grauAdjetivo f s', 'discreta',   grauAdjetivo('discreta', 'f', 's'));
checa('grauAdjetivo m s', 'discreto',   grauAdjetivo('discreta', 'm', 's'));
checa('grauAdjetivo m p', 'discretos',  grauAdjetivo('discreta', 'm', 'p'));
checa('grauAdjetivo f p', 'acentuadas', grauAdjetivo('acentuada', 'f', 'p'));
checa('grauAdjetivo fallback', 'moderada', grauAdjetivo('xxx', 'f', 's'));

/* capitalizar. */
checa('capitalizar', 'Discreta', capitalizar('discreta'));

/* listaPtBr — enumeracao pt-BR. */
checa('listaPtBr vazia',  '',            listaPtBr([]));
checa('listaPtBr um',     'a',           listaPtBr(['a']));
checa('listaPtBr dois',   'a e b',       listaPtBr(['a', 'b']));
checa('listaPtBr tres',   'a, b e c',    listaPtBr(['a', 'b', 'c']));
checa('listaPtBr quatro', 'a, b, c e d', listaPtBr(['a', 'b', 'c', 'd']));

/* ecotexturaTexto — enum de 2 valores -> pt-BR. */
checa('ecotexturaTexto hetero', 'heterogênea', ecotexturaTexto('heterogenea'));
checa('ecotexturaTexto homo',   'homogênea',   ecotexturaTexto('homogenea'));
checa('ecotexturaTexto default', 'homogênea',  ecotexturaTexto('qualquer'));

if ($falhas === 0) { echo "\nOK: helpers verdes.\n"; exit(0); }
echo "\nFALHA: {$falhas} verificacao(oes).\n";
exit(1);
