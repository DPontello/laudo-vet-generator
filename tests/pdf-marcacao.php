<?php

declare(strict_types=1);

/**
 * Testa pdfRunsMarcados(): a marcacao leve (**negrito**, __sublinhado__) das
 * caixas de Impressao e Observacoes vira runs de estilo para o PDF, resolvida
 * por palavra (a pontuacao colada herda o destaque) e combinando com o estilo
 * base da secao.
 *
 * Uso: php tests/pdf-marcacao.php
 */

require __DIR__ . '/../src/pdf/gerarPdf.php';

$falhas = 0;
function checa(string $rotulo, array $esperado, array $obtido): void
{
    global $falhas;
    $e = json_encode($esperado, JSON_UNESCAPED_UNICODE);
    $o = json_encode($obtido, JSON_UNESCAPED_UNICODE);
    if ($e === $o) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}\n  esperado: {$e}\n  obtido:   {$o}\n";
}

// Texto simples: uma run por palavra, sem estilo.
checa('sem marcacao',
    [['Hérnia', ''], ['umbilical.', '']],
    pdfRunsMarcados('Hérnia umbilical.'));

// Negrito no meio da frase.
checa('negrito',
    [['Cistite', 'B'], ['crônica.', '']],
    pdfRunsMarcados('**Cistite** crônica.'));

// Sublinhado combinando com o estilo base 'I' (observacoes sao em italico).
checa('sublinhado sobre base italico',
    [['Nota', 'I'], ['importante', 'IU'], ['aqui.', 'I']],
    pdfRunsMarcados('Nota __importante__ aqui.', 'I'));

// Negrito + sublinhado sobrepostos numa palavra so.
checa('negrito e sublinhado juntos',
    [['destaque', 'BU']],
    pdfRunsMarcados('**__destaque__**'));

// Pontuacao colada dentro dos marcadores fica junto da palavra (sem espaco espurio).
checa('pontuacao dentro do destaque',
    [['Colelitíase.', 'B']],
    pdfRunsMarcados('**Colelitíase.**'));

// Texto vazio devolve uma run vazia (fallback), preservando o estilo base.
checa('texto vazio',
    [['', 'I']],
    pdfRunsMarcados('', 'I'));

if ($falhas === 0) { echo "\nOK: marcacao de estilo do PDF verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} caso(s) divergente(s).\n";
exit(1);
