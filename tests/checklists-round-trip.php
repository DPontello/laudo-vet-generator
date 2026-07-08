<?php

declare(strict_types=1);

/**
 * Teste dos checklists personalizados (config da propria medica):
 *   - validacao (secao desconhecida / item sem texto);
 *   - salvar normaliza (rotulo derivado do texto, id atribuido) e recarrega igual;
 *   - handler ?checklists: GET le, POST grava, POST invalido -> 400;
 *   - integracao: frase personalizada do reprodutor aparece no laudo (compositor).
 *
 * Usa LAUDO_CHECKLISTS_FILE apontando para um arquivo temporario, para nao tocar
 * os dados reais (data/checklists.json).
 *
 * Uso: php tests/checklists-round-trip.php
 */

putenv('LAUDO_CHECKLISTS_FILE=' . sys_get_temp_dir() . '/checklists-teste-' . getmypid() . '.json');

require __DIR__ . '/../src/api/handler.php';   // ja carrega laudo.php + todos os compositores

$falhas = 0;
function checa(string $rotulo, bool $ok, string $detalhe = ''): void
{
    global $falhas;
    if ($ok) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}" . ($detalhe !== '' ? "  ({$detalhe})" : '') . "\n";
}

/* ---- 1. Validacao ---- */
checa('valida config vazia', validarChecklists([]) === null);
checa('rejeita secao desconhecida', validarChecklists(['inexistente' => []]) !== null);
checa('rejeita item sem texto', validarChecklists(['bexiga' => [['label' => 'x']]]) !== null);
checa('aceita item com texto', validarChecklists(['bexiga' => [['texto' => 'Frase.']]]) === null);

/* ---- 2. Salvar normaliza e recarrega ---- */
$salvo = salvarChecklists([
    'reprodutor' => [['texto' => 'Presença de líquido em topografia uterina.']],
    'bexiga'     => [['label' => 'Divertículo', 'texto' => 'Imagem sugestiva de divertículo vesical.']],
    'estomago'   => [],   // secao vazia deve sumir
]);
checa('salvar: rotulo derivado do texto', ($salvo['reprodutor'][0]['label'] ?? '') !== '');
checa('salvar: id atribuido', ($salvo['reprodutor'][0]['id'] ?? '') !== '');
checa('salvar: secao vazia removida', !array_key_exists('estomago', $salvo));
checa('recarrega igual ao salvo', carregarChecklists() == $salvo);

/* ---- 3. Handler ?checklists ---- */
$r = tratarRequisicaoChecklists('GET', '');
checa('GET: 200 json', $r['status'] === 200 && str_contains($r['headers']['Content-Type'] ?? '', 'application/json'));

$r = tratarRequisicaoChecklists('POST', json_encode(['rins' => [['texto' => 'Nota nos rins.']]]));
checa('POST valido: 200', $r['status'] === 200, 'status=' . $r['status']);
$posSalvo = json_decode($r['body'], true);
checa('POST valido: gravou a secao rins', isset($posSalvo['rins'][0]['texto']));

$r = tratarRequisicaoChecklists('POST', json_encode(['secao_x' => []]));
checa('POST invalido: 400', $r['status'] === 400);

/* ---- 4. Integracao: frase personalizada do reprodutor entra no laudo ---- */
$bloco = composeReprodutor([
    'utero_ovarios_ausentes' => ['avaliado' => true],
    'observacoes' => 'Achado personalizado no reprodutor.',
]);
checa('reprodutor anexa observacoes', str_contains($bloco, 'Achado personalizado no reprodutor.'));

@unlink(getenv('LAUDO_CHECKLISTS_FILE'));

if ($falhas === 0) { echo "\nOK: checklists personalizados verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} verificacao(oes).\n";
exit(1);
