<?php

declare(strict_types=1);

/**
 * Teste do arquivamento local dos laudos gerados.
 *
 * Aponta LAUDO_ARQUIVO_DIR para uma pasta temporaria (nao toca os dados reais),
 * gera o laudo via handler e verifica que:
 *   - a resposta continua sendo o PDF (200, application/pdf);
 *   - uma copia foi gravada na pasta de arquivo, comecando por "%PDF";
 *   - o nome do arquivo leva o slug do paciente;
 *   - regerar o mesmo laudo cria uma NOVA versao (nao sobrescreve).
 *
 * Uso: php tests/laudo-arquivamento.php
 */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laudo-arquivo-teste-' . getmypid();
putenv('LAUDO_ARQUIVO_DIR=' . $tmp);

require __DIR__ . '/../src/api/handler.php';

$falhas = 0;
function checa(string $rotulo, bool $ok, string $detalhe = ''): void
{
    global $falhas;
    if ($ok) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}" . ($detalhe !== '' ? "  ({$detalhe})" : '') . "\n";
}

$payload = [
    'cabecalho' => [
        'paciente' => 'Clarinha', 'especie' => 'Canino', 'sexo' => 'F',
        'responsavel' => 'Bruna', 'data_exame' => '2026-06-30', 'data_laudo' => '2026-07-02',
    ],
    'orgaos' => [
        'bexiga' => ['avaliado' => true, 'replecao' => 'discreta'],
    ],
];
$corpo = json_encode($payload);

$resp = tratarRequisicaoLaudo('POST', $corpo);
checa('resposta 200', $resp['status'] === 200, 'status=' . $resp['status']);
checa('content-type PDF', ($resp['headers']['Content-Type'] ?? '') === 'application/pdf');
checa('header X-Laudo-Arquivado presente', isset($resp['headers']['X-Laudo-Arquivado']));

$arquivos = glob($tmp . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
checa('um arquivo arquivado', count($arquivos) === 1, 'n=' . count($arquivos));
if ($arquivos !== []) {
    $nome = basename($arquivos[0]);
    $bytes = (string) file_get_contents($arquivos[0]);
    checa('arquivo comeca com %PDF', substr($bytes, 0, 4) === '%PDF');
    checa('nome no padrao "LAUDO US - <paciente> - <data>"',
        $nome === 'LAUDO US - Clarinha - 02-07-2026.pdf', $nome);
}

// Regerar o mesmo laudo deve criar uma segunda versao (nunca sobrescrever).
tratarRequisicaoLaudo('POST', $corpo);
$arquivos2 = glob($tmp . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
checa('regerar mantem versoes', count($arquivos2) === 2, 'n=' . count($arquivos2));

// Limpeza.
foreach (glob($tmp . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
@rmdir($tmp);

if ($falhas === 0) { echo "\nOK: arquivamento verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} verificacao(oes).\n";
exit(1);
