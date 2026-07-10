<?php

declare(strict_types=1);

/**
 * Handler da API do laudo (contrato JSON).
 *
 * A logica vive numa funcao PURA (tratarRequisicaoLaudo) que recebe o metodo HTTP
 * e o corpo bruto e devolve {status, headers, body} — sem tocar superglobais nem
 * ecoar nada. Assim o endpoint (public/index.php) fica fino e a logica fica
 * testavel sem servidor.
 *
 * Transporte das imagens: BASE64 dentro do proprio JSON (campo opcional `imagens`,
 * lista de strings JPEG em base64). Escolha por simplicidade — um unico corpo JSON,
 * sem multipart. As imagens sao efemeras: decodificadas em memoria e repassadas ao
 * gerador de PDF, que as grava em arquivo temporario apenas para anexar e remove ao
 * final (CLAUDE.md secao 2). Nada de imagem e persistido no servidor.
 *
 * Contrato de entrada (POST, application/json):
 *   { "cabecalho": {...}, "orgaos": {...}, "impressao_diagnostica": [...],
 *     "observacoes_finais": [...], "imagens": ["<jpeg-base64>", ...] }
 * Saida: 200 com o PDF (application/pdf) ou 4xx/5xx com { "error": "..." } (JSON).
 */

require_once __DIR__ . '/../laudo.php';
require_once __DIR__ . '/../pdf/gerarPdf.php';
require_once __DIR__ . '/../checklists.php';

/** Monta uma resposta JSON padronizada. */
function respostaJson(int $status, array $dados): array
{
    return [
        'status'  => $status,
        'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
        'body'    => json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

/**
 * Validacao basica do payload (campos criticos presentes e com tipos corretos).
 *
 * @return string|null Mensagem de erro, ou null se valido.
 */
function validarPayloadLaudo(array $d): ?string
{
    if (!isset($d['cabecalho']) || !is_array($d['cabecalho'])) {
        return 'Campo "cabecalho" ausente ou invalido (esperado objeto).';
    }
    if (!isset($d['orgaos']) || !is_array($d['orgaos'])) {
        return 'Campo "orgaos" ausente ou invalido (esperado objeto).';
    }

    $sexo = $d['cabecalho']['sexo'] ?? null;
    if ($sexo !== null && !in_array($sexo, ['M', 'F'], true)) {
        return 'cabecalho.sexo deve ser "M" ou "F".';
    }

    foreach ($d['orgaos'] as $nome => $org) {
        if (!is_array($org)) {
            return "orgaos.{$nome} deve ser um objeto.";
        }
        // Todos os orgaos tem `avaliado` boolean, exceto reprodutor (blocos proprios).
        if ($nome !== 'reprodutor' && array_key_exists('avaliado', $org) && !is_bool($org['avaliado'])) {
            return "orgaos.{$nome}.avaliado deve ser booleano.";
        }
    }

    foreach (['impressao_diagnostica', 'observacoes_finais'] as $k) {
        if (array_key_exists($k, $d) && !is_array($d[$k])) {
            return "{$k} deve ser uma lista.";
        }
    }

    if (array_key_exists('imagens', $d) && !is_array($d['imagens'])) {
        return 'imagens deve ser uma lista de strings JPEG em base64.';
    }

    // Laudo ja editado (fluxo da previa editavel): objeto opcional com os blocos.
    if (array_key_exists('laudo', $d) && !is_array($d['laudo'])) {
        return 'laudo deve ser um objeto com os blocos ja compostos.';
    }

    return null;
}

/**
 * Decodifica e valida as imagens base64. Devolve os bytes de cada JPEG.
 *
 * @param array<mixed> $imagensBase64
 * @return array{0: array<string>, 1: ?string} [bytes, erro]
 */
function decodificarImagens(array $imagensBase64): array
{
    $bytesImagens = [];
    $indice = 0;
    foreach ($imagensBase64 as $b64) {
        $indice++;
        if (!is_string($b64)) {
            return [[], "imagens[{$indice}] deve ser uma string base64."];
        }
        // Tolera prefixo data URI (data:image/jpeg;base64,....).
        if (str_contains($b64, ',')) {
            $b64 = substr($b64, strpos($b64, ',') + 1);
        }
        $bytes = base64_decode($b64, true);
        if ($bytes === false || $bytes === '') {
            return [[], "imagens[{$indice}] nao e base64 valido."];
        }
        // Assinatura JPEG (SOI 0xFFD8).
        if (substr($bytes, 0, 2) !== "\xFF\xD8") {
            return [[], "imagens[{$indice}] nao e um JPEG valido."];
        }
        $bytesImagens[] = $bytes;
    }
    return [$bytesImagens, null];
}

/**
 * Nome do paciente legivel e seguro para nome de arquivo: mantem espacos e a
 * caixa original, apenas transliterando acentos (Windows) e removendo os
 * caracteres proibidos em nomes de arquivo (< > : " / \ | ? * e controle).
 */
function nomePacienteArquivo(array $cabecalho): string
{
    $paciente = trim((string) ($cabecalho['paciente'] ?? ''));
    if ($paciente === '') { return 'Paciente'; }
    $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT', $paciente) : $paciente;
    $ascii = is_string($ascii) && $ascii !== '' ? $ascii : $paciente;
    $limpo = preg_replace('#[<>:"/\\\\|?*\x00-\x1F]#', '', $ascii);
    $limpo = trim((string) preg_replace('/\s+/', ' ', (string) $limpo));
    return $limpo !== '' ? $limpo : 'Paciente';
}

/** Data do laudo formatada (DD-MM-AAAA) para o nome do arquivo. */
function dataLaudoArquivo(array $cabecalho): string
{
    $data = trim((string) ($cabecalho['data_laudo'] ?? ($cabecalho['data_exame'] ?? '')));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $data, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    if ($data !== '') {
        return str_replace(['/', '\\'], '-', $data);
    }
    return date('d-m-Y');
}

/**
 * Nome base do laudo (sem extensao), no padrao pedido pela medica:
 *   "LAUDO US - <paciente> - <data do laudo>".
 * Usado tanto no arquivo baixado quanto na copia arquivada.
 */
function nomeBaseLaudo(array $cabecalho): string
{
    return 'LAUDO US - ' . nomePacienteArquivo($cabecalho) . ' - ' . dataLaudoArquivo($cabecalho);
}

/** Nome de arquivo do PDF baixado. */
function nomeArquivoPdf(array $cabecalho): string
{
    return nomeBaseLaudo($cabecalho) . '.pdf';
}

/**
 * Diretorio onde os laudos gerados sao arquivados (copia local para consulta
 * posterior — o "armazenamento" da medica). Default: data/laudos na raiz do
 * projeto — no Windows fica na propria pasta do app; no Docker e o volume
 * persistente. Sobrescrevivel via LAUDO_ARQUIVO_DIR (os testes usam isso para
 * nao tocar os dados reais).
 */
function arquivoLaudosDir(): string
{
    $env = getenv('LAUDO_ARQUIVO_DIR');
    return ($env !== false && $env !== '') ? $env : dirname(__DIR__, 2) . '/data/laudos';
}

/**
 * Arquiva uma copia do PDF gerado numa pasta local, para servir de historico.
 * Guarda TODAS as versoes: o nome leva data e hora da geracao; se ja existir um
 * arquivo com o mesmo nome (regeracao no mesmo segundo), acrescenta um sufixo
 * incremental — nunca sobrescreve.
 *
 * Nao-fatal por design: se falhar (sem permissao, disco cheio), devolve null e
 * a medica ainda recebe o PDF normalmente — o arquivamento nunca bloqueia o laudo.
 *
 * @return string|null Caminho do arquivo salvo, ou null se nao foi possivel salvar.
 */
function arquivarLaudoPdf(string $pdf, array $cabecalho): ?string
{
    $dir = arquivoLaudosDir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }
    $base = nomeBaseLaudo($cabecalho);
    $dir = rtrim($dir, "/\\");
    $caminho = $dir . DIRECTORY_SEPARATOR . $base . '.pdf';
    $i = 2;
    while (file_exists($caminho)) {
        $caminho = $dir . DIRECTORY_SEPARATOR . $base . ' (' . $i . ').pdf';
        $i++;
    }
    return @file_put_contents($caminho, $pdf) !== false ? $caminho : null;
}

/**
 * Trata o recurso de checklists personalizados (config da propria medica).
 * GET devolve a config salva; POST/PUT valida e grava. Rota: ?checklists.
 *
 * @param string $metodo     Metodo HTTP.
 * @param string $corpoBruto  Corpo bruto (php://input) — usado no POST/PUT.
 * @return array{status:int, headers:array<string,string>, body:string}
 */
function tratarRequisicaoChecklists(string $metodo, string $corpoBruto): array
{
    $metodo = strtoupper($metodo);

    if ($metodo === 'GET') {
        return respostaJson(200, carregarChecklists());
    }
    if ($metodo !== 'POST' && $metodo !== 'PUT') {
        return respostaJson(405, ['error' => 'Use GET para ler ou POST para salvar os checklists.']);
    }
    if (trim($corpoBruto) === '') {
        return respostaJson(400, ['error' => 'Corpo da requisicao vazio.']);
    }
    $dados = json_decode($corpoBruto, true);
    if (!is_array($dados)) {
        return respostaJson(400, ['error' => 'Corpo da requisicao nao e um JSON valido.']);
    }
    $erro = validarChecklists($dados);
    if ($erro !== null) {
        return respostaJson(400, ['error' => $erro]);
    }
    try {
        $salvo = salvarChecklists($dados);
    } catch (\Throwable $e) {
        return respostaJson(500, ['error' => 'Falha ao salvar os checklists: ' . $e->getMessage()]);
    }
    return respostaJson(200, $salvo);
}

/**
 * Trata uma requisicao ao endpoint do laudo.
 *
 * @param string $metodo    Metodo HTTP (GET, POST, ...).
 * @param string $corpoBruto Corpo bruto da requisicao (php://input).
 * @return array{status:int, headers:array<string,string>, body:string}
 */
function tratarRequisicaoLaudo(string $metodo, string $corpoBruto): array
{
    $metodo = strtoupper($metodo);

    if ($metodo === 'GET') {
        return respostaJson(200, [
            'servico' => 'laudo-vet-generator',
            'uso'     => 'POST application/json com o payload do laudo (ver laudo.schema.json). '
                . 'Imagens opcionais em "imagens" (lista de JPEG base64). Retorna o PDF.',
        ]);
    }

    if ($metodo !== 'POST') {
        return respostaJson(405, ['error' => 'Metodo nao permitido. Use POST.']);
    }

    if (trim($corpoBruto) === '') {
        return respostaJson(400, ['error' => 'Corpo da requisicao vazio; envie o payload JSON.']);
    }

    $dados = json_decode($corpoBruto, true);
    if (!is_array($dados)) {
        return respostaJson(400, ['error' => 'Corpo da requisicao nao e um JSON valido.']);
    }

    $erro = validarPayloadLaudo($dados);
    if ($erro !== null) {
        return respostaJson(400, ['error' => $erro]);
    }

    // Modo "previa": devolve o laudo ja composto em blocos estruturados (JSON),
    // para a medica editar o texto no navegador antes de gerar o PDF. Nao gera
    // PDF nem precisa de imagens aqui.
    if (($dados['modo'] ?? '') === 'previa') {
        return respostaJson(200, montarLaudoEstruturado($dados));
    }

    // Extrai e valida as imagens (efemeras).
    $imagens = [];
    if (array_key_exists('imagens', $dados)) {
        [$imagens, $erroImg] = decodificarImagens($dados['imagens']);
        if ($erroImg !== null) {
            return respostaJson(400, ['error' => $erroImg]);
        }
        unset($dados['imagens']);
    }

    // Se o payload trouxe um laudo ja editado (vindo da previa), usa-o direto —
    // o texto ajustado vira PDF sem recompor as frases. Senao, compoe do payload.
    $laudo = isset($dados['laudo']) && is_array($dados['laudo'])
        ? laudoEditadoParaPdf($dados['laudo'])
        : montarLaudoEstruturado($dados);

    try {
        $pdf = gerarLaudoPdf($laudo, $imagens);
    } catch (\Throwable $e) {
        return respostaJson(500, ['error' => 'Falha ao gerar o laudo: ' . $e->getMessage()]);
    }

    $cabecalho = (array) ($dados['cabecalho'] ?? []);
    $nome = nomeArquivoPdf($cabecalho);

    // Arquiva uma copia local do laudo (historico da medica). Nao-fatal: se
    // falhar, ela ainda recebe o PDF; sinalizamos o resultado num header.
    $arquivado = arquivarLaudoPdf($pdf, $cabecalho);

    $headers = [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="' . $nome . '"',
        'Content-Length'      => (string) strlen($pdf),
    ];
    if ($arquivado !== null) {
        $headers['X-Laudo-Arquivado'] = basename($arquivado);
    }

    return [
        'status'  => 200,
        'headers' => $headers,
        'body'    => $pdf,
    ];
}
