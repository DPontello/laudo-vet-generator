<?php

declare(strict_types=1);

/**
 * Teste de integracao do endpoint (contrato JSON). Chama tratarRequisicaoLaudo()
 * simulando requisicoes e verifica:
 *   - POST valido (payload da Clarinha + imagem base64) -> 200 application/pdf;
 *   - health check GET -> 200 JSON;
 *   - metodo invalido -> 405; corpo vazio / JSON invalido -> 400;
 *   - payload invalido (sem cabecalho, sexo errado, avaliado nao-bool) -> 400;
 *   - imagem nao-JPEG -> 400.
 *
 * Uso: php tests/api-endpoint-round-trip.php
 */

require __DIR__ . '/../src/api/handler.php';

$falhas = 0;
function checa(string $rotulo, bool $ok, string $detalhe = ''): void
{
    global $falhas;
    if ($ok) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}" . ($detalhe !== '' ? "  ({$detalhe})" : '') . "\n";
}

/** JPEG de amostra (base64) lido das fixtures — nao depende da extensao gd. */
function jpegBase64(): string
{
    $bytes = file_get_contents(__DIR__ . '/fixtures/imagem-amostra-1.jpg');
    if ($bytes === false || $bytes === '') {
        fwrite(STDERR, "fixture ausente: tests/fixtures/imagem-amostra-1.jpg\n");
        exit(1);
    }
    return base64_encode($bytes);
}

$payloadClarinha = [
    'cabecalho' => [
        'paciente' => 'Clarinha', 'especie' => 'Canino', 'sexo' => 'F', 'idade' => '7 anos',
        'responsavel' => 'Bruna', 'veterinario_requisitante' => 'Dra. Jaqueline',
        'data_exame' => '2026-06-30', 'data_laudo' => '2026-07-02',
    ],
    'orgaos' => [
        'bexiga' => [
            'avaliado' => true, 'replecao' => 'discreta',
            'parede' => ['aspecto' => 'normoespessa', 'espessura_cm' => 0.11],
            'conteudo' => ['homogeneo' => false, 'sedimento' => ['presente' => true, 'quantidade' => 'discreta', 'tipo' => 'sedimentos_urinarios']],
            'litiase' => ['cistolito_presente' => false],
        ],
        'reprodutor' => ['utero_ovarios_ausentes' => ['avaliado' => true]],
    ],
    'impressao_diagnostica' => ['Discreta quantidade de sedimentos urinários.'],
    'observacoes_finais' => [],
    'imagens' => [jpegBase64()],
];

/* ---- 1. POST valido -> PDF ---- */
$r = tratarRequisicaoLaudo('POST', json_encode($payloadClarinha));
checa('POST valido: status 200', $r['status'] === 200, 'status=' . $r['status']);
checa('POST valido: content-type pdf', ($r['headers']['Content-Type'] ?? '') === 'application/pdf');
checa('POST valido: corpo %PDF', substr($r['body'], 0, 4) === '%PDF');
checa('POST valido: filename do paciente', str_contains($r['headers']['Content-Disposition'] ?? '', 'laudo-clarinha.pdf'),
    $r['headers']['Content-Disposition'] ?? '');

/* ---- 2. Health check GET ---- */
$r = tratarRequisicaoLaudo('GET', '');
checa('GET: status 200', $r['status'] === 200);
checa('GET: json de servico', str_contains($r['body'], 'laudo-vet-generator'));

/* ---- 3. Metodo invalido ---- */
$r = tratarRequisicaoLaudo('PUT', '{}');
checa('PUT: 405', $r['status'] === 405);

/* ---- 4. Corpo vazio ---- */
$r = tratarRequisicaoLaudo('POST', '');
checa('POST vazio: 400', $r['status'] === 400);

/* ---- 5. JSON invalido ---- */
$r = tratarRequisicaoLaudo('POST', '{isso nao e json');
checa('POST json invalido: 400', $r['status'] === 400);

/* ---- 6. Sem cabecalho ---- */
$r = tratarRequisicaoLaudo('POST', json_encode(['orgaos' => []]));
checa('POST sem cabecalho: 400', $r['status'] === 400 && str_contains($r['body'], 'cabecalho'));

/* ---- 7. Sexo invalido ---- */
$r = tratarRequisicaoLaudo('POST', json_encode(['cabecalho' => ['sexo' => 'X'], 'orgaos' => []]));
checa('POST sexo invalido: 400', $r['status'] === 400 && str_contains($r['body'], 'sexo'));

/* ---- 8. avaliado nao-boolean ---- */
$r = tratarRequisicaoLaudo('POST', json_encode(['cabecalho' => ['sexo' => 'F'], 'orgaos' => ['bexiga' => ['avaliado' => 'sim']]]));
checa('POST avaliado nao-bool: 400', $r['status'] === 400 && str_contains($r['body'], 'avaliado'));

/* ---- 9. Imagem nao-JPEG ---- */
$r = tratarRequisicaoLaudo('POST', json_encode([
    'cabecalho' => ['sexo' => 'F'], 'orgaos' => ['bexiga' => ['avaliado' => true]],
    'imagens' => [base64_encode('isto nao e jpeg')],
]));
checa('POST imagem nao-JPEG: 400', $r['status'] === 400 && str_contains($r['body'], 'JPEG'));

/* ---- 10. Modo previa -> laudo estruturado em JSON (sem PDF) ---- */
$previa = $payloadClarinha;
unset($previa['imagens']);
$previa['modo'] = 'previa';
$r = tratarRequisicaoLaudo('POST', json_encode($previa));
checa('POST previa: status 200', $r['status'] === 200, 'status=' . $r['status']);
checa('POST previa: content-type json', str_contains($r['headers']['Content-Type'] ?? '', 'application/json'));
$laudo = json_decode($r['body'], true);
checa('POST previa: tem blocos de orgaos', is_array($laudo['orgaos'] ?? null) && count($laudo['orgaos']) > 0);
checa('POST previa: tem disclaimer', is_string($laudo['disclaimer'] ?? null) && $laudo['disclaimer'] !== '');
checa('POST previa: nao e PDF', substr($r['body'], 0, 4) !== '%PDF');

/* ---- 11. Laudo ja editado -> PDF com o texto ajustado ---- */
$marcador = 'ORGAO EDITADO NA PREVIA';
$editado = [
    'cabecalho'  => $payloadClarinha['cabecalho'],
    'orgaos'     => ['bexiga' => ['avaliado' => true]],   // presente so para a validacao
    'laudo'      => [
        'cabecalho'   => $payloadClarinha['cabecalho'],
        'titulo'      => 'LAUDO DE ULTRASSONOGRAFIA ABDOMINAL',
        'orgaos'      => ["BEXIGA: {$marcador}."],
        'impressao'   => ['Sem alteracoes.'],
        'observacoes' => ['Observacao editada.'],
        'disclaimer'  => 'Disclaimer.',
        'local_data'  => 'Pouso Alegre, 02 de julho de 2026.',
    ],
];
$r = tratarRequisicaoLaudo('POST', json_encode($editado));
checa('POST laudo editado: status 200', $r['status'] === 200, 'status=' . $r['status']);
checa('POST laudo editado: corpo %PDF', substr($r['body'], 0, 4) === '%PDF');

/* ---- 12. Laudo com tipo invalido -> 400 ---- */
$r = tratarRequisicaoLaudo('POST', json_encode([
    'cabecalho' => ['sexo' => 'F'], 'orgaos' => ['bexiga' => ['avaliado' => true]], 'laudo' => 'texto',
]));
checa('POST laudo invalido: 400', $r['status'] === 400 && str_contains($r['body'], 'laudo'));

if ($falhas === 0) { echo "\nOK: endpoint da API verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} verificacao(oes).\n";
exit(1);
