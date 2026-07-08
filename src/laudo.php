<?php

declare(strict_types=1);

/**
 * Agregador do laudo completo.
 *
 * montarLaudo(array $payload): string monta o texto integral do laudo a partir do
 * payload JSON (docs/referencia/laudo.schema.json):
 *   - cabecalho (dados do paciente) + titulo;
 *   - cada orgao presente em `orgaos`, na ordem da secao 3 do CLAUDE.md, chamando
 *     o compositor correspondente (orgaos ausentes sao pulados);
 *   - IMPRESSÃO DIAGNÓSTICA (lista `impressao_diagnostica`);
 *   - Observação (lista `observacoes_finais`);
 *   - rodape fixo da medica (secao 11 do CLAUDE.md): disclaimer, local e data,
 *     assinatura e identificacao.
 *
 * O texto de cada orgao vem inalterado dos compositores (fonte unica da prosa).
 */

require_once __DIR__ . '/composers/_helpers.php';
require_once __DIR__ . '/composers/bexiga.php';
require_once __DIR__ . '/composers/rins.php';
require_once __DIR__ . '/composers/adrenais.php';
require_once __DIR__ . '/composers/figado.php';
require_once __DIR__ . '/composers/vesicula-biliar.php';
require_once __DIR__ . '/composers/baco.php';
require_once __DIR__ . '/composers/estomago.php';
require_once __DIR__ . '/composers/intestinos.php';
require_once __DIR__ . '/composers/pancreas.php';
require_once __DIR__ . '/composers/reprodutor.php';
require_once __DIR__ . '/composers/cavidade-abdominal.php';

/** Disclaimer fixo da medica (CLAUDE.md secao 11). */
const LAUDO_DISCLAIMER = 'O exame ultrassonográfico não possui valor diagnóstico absoluto. As informações '
    . 'fornecidas devem ser confrontadas com dados clínicos, laboratoriais e com outros exames de imagem '
    . 'anteriores e/ou subsequentes. Somente o Médico Veterinário responsável pelo paciente é capaz de '
    . 'interpretar o conjunto de todas as informações.';

/** "2026-06-30" -> "30/06/2026" (ou a string original se nao for ISO). */
function laudoDataCurta(?string $iso): string
{
    if ($iso === null || $iso === '') { return ''; }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) { return $iso; }
    return "{$m[3]}/{$m[2]}/{$m[1]}";
}

/** "2026-07-02" -> "02 de julho de 2026" (ou a string original se nao for ISO). */
function laudoDataExtenso(?string $iso): string
{
    if ($iso === null || $iso === '') { return ''; }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) { return $iso; }
    $meses = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
        7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];
    $mes = $meses[(int) $m[2]] ?? '';
    return "{$m[3]} de {$mes} de {$m[1]}";
}

/** Titulo fixo do laudo. */
const LAUDO_TITULO = 'LAUDO DE ULTRASSONOGRAFIA ABDOMINAL';

/** Linha de local e data por extenso ("Pouso Alegre, 02 de julho de 2026."). */
function laudoLocalDataLinha(array $c): string
{
    $dataLaudo = $c['data_laudo'] ?? ($c['data_exame'] ?? null);
    $extenso = laudoDataExtenso($dataLaudo);
    return 'Pouso Alegre, ' . ($extenso !== '' ? $extenso : '____ de __________ de ______') . '.';
}

/** Normaliza uma lista de linhas: trim e descarta vazias. */
function laudoFiltrarLinhas(array $linhas): array
{
    $itens = [];
    foreach ($linhas as $linha) {
        $linha = trim((string) $linha);
        if ($linha !== '') { $itens[] = $linha; }
    }
    return $itens;
}

/** Cabecalho do paciente + titulo. */
function laudoCabecalho(array $c): string
{
    $linhas = [];

    $data = laudoDataCurta($c['data_exame'] ?? null);
    $l1 = 'Paciente: ' . ($c['paciente'] ?? '');
    if ($data !== '') { $l1 .= '     Data do exame: ' . $data; }
    $linhas[] = $l1;

    $l2 = 'Espécie: ' . ($c['especie'] ?? '');
    if (trim((string) ($c['raca'] ?? '')) !== '') { $l2 .= '     Raça: ' . $c['raca']; }
    $l2 .= '     Sexo: ' . ($c['sexo'] ?? '') . '     Idade: ' . ($c['idade'] ?? '');
    $linhas[] = $l2;

    $linhas[] = 'Responsável: ' . ($c['responsavel'] ?? '');
    $linhas[] = 'Médico(a) Veterinário(a) Requisitante: ' . ($c['veterinario_requisitante'] ?? '');
    $linhas[] = '';
    $linhas[] = '                         ' . LAUDO_TITULO;

    return implode("\n", $linhas);
}

/** Lista dos paragrafos dos orgaos presentes, na ordem do CLAUDE.md secao 3. */
function laudoOrgaosLista(array $orgaos): array
{
    $ordem = [
        'bexiga'             => 'composeBexiga',
        'rins'               => 'composeRins',
        'adrenais'           => 'composeAdrenais',
        'figado'             => 'composeFigado',
        'vesicula_biliar'    => 'composeVesiculaBiliar',
        'baco'               => 'composeBaco',
        'estomago'           => 'composeEstomago',
        'intestinos'         => 'composeIntestinos',
        'pancreas'           => 'composePancreas',
        'reprodutor'         => 'composeReprodutor',
        'cavidade_abdominal' => 'composeCavidadeAbdominal',
    ];

    $paragrafos = [];
    foreach ($ordem as $chave => $fn) {
        if (!array_key_exists($chave, $orgaos)) { continue; }
        $texto = $fn((array) $orgaos[$chave]);
        if (trim($texto) !== '') { $paragrafos[] = $texto; }
    }
    return $paragrafos;
}

/** Paragrafos dos orgaos presentes, como texto unico. */
function laudoOrgaos(array $orgaos): string
{
    return implode("\n\n", laudoOrgaosLista($orgaos));
}

/** Secao IMPRESSÃO DIAGNÓSTICA (ou '' se vazia). */
function laudoImpressao(array $linhas): string
{
    $itens = laudoFiltrarLinhas($linhas);
    if (!$itens) { return ''; }
    return implode("\n", array_merge(['IMPRESSÃO DIAGNÓSTICA:'], $itens));
}

/** Secao Observação (biblioteca de notas finais) (ou '' se vazia). */
function laudoObservacoes(array $linhas): string
{
    $itens = laudoFiltrarLinhas($linhas);
    if (!$itens) { return ''; }
    return implode("\n", array_merge(['Observação:'], $itens));
}

/** Rodape fixo da medica (disclaimer, local e data, assinatura). */
function laudoRodape(array $c): string
{
    $local = laudoLocalDataLinha($c);

    return implode("\n", [
        LAUDO_DISCLAIMER,
        '',
        $local,
        '',
        '',
        '                     ___________________________',
        '',
        '                        Aline Marques de Souza',
        '                            CRMV-MG 31116',
        '',
        'Aline Marques de Souza',
        'ULTRASSONOGRAFIA VETERINÁRIA',
        'Pouso Alegre/MG',
    ]);
}

/**
 * Monta o laudo completo.
 *
 * @param array<string,mixed> $payload Payload conforme laudo.schema.json.
 * @return string Texto integral do laudo.
 */
function montarLaudo(array $payload): string
{
    $cabecalho = (array) ($payload['cabecalho'] ?? []);

    $secoes = [];
    $secoes[] = laudoCabecalho($cabecalho);
    $secoes[] = laudoOrgaos((array) ($payload['orgaos'] ?? []));
    $secoes[] = laudoImpressao((array) ($payload['impressao_diagnostica'] ?? []));
    $secoes[] = laudoObservacoes((array) ($payload['observacoes_finais'] ?? []));
    $secoes[] = laudoRodape($cabecalho);

    $secoes = array_values(array_filter($secoes, static fn($s) => trim((string) $s) !== ''));
    return implode("\n\n", $secoes);
}

/**
 * Monta o laudo em SECOES ESTRUTURADAS, para renderizadores que precisam de
 * controle tipografico (o PDF timbrado). A prosa de cada orgao continua vindo
 * inalterada dos compositores (fonte unica da redacao) — aqui so muda a
 * embalagem: em vez de um texto unico, cada secao vem separada.
 *
 * @param array<string,mixed> $payload Payload conforme laudo.schema.json.
 * @return array{
 *   cabecalho: array<string,mixed>,
 *   titulo: string,
 *   orgaos: array<string>,
 *   impressao: array<string>,
 *   observacoes: array<string>,
 *   disclaimer: string,
 *   local_data: string
 * }
 */
function montarLaudoEstruturado(array $payload): array
{
    $cabecalho = (array) ($payload['cabecalho'] ?? []);

    // Compositores podem devolver mais de um bloco no mesmo texto (ex.: reprodutor,
    // um bloco por segmento avaliado); achata em uma lista de blocos.
    $blocos = [];
    foreach (laudoOrgaosLista((array) ($payload['orgaos'] ?? [])) as $texto) {
        foreach (explode("\n\n", $texto) as $bloco) {
            if (trim($bloco) !== '') { $blocos[] = $bloco; }
        }
    }

    return [
        'cabecalho'   => $cabecalho,
        'titulo'      => LAUDO_TITULO,
        'orgaos'      => $blocos,
        'impressao'   => laudoFiltrarLinhas((array) ($payload['impressao_diagnostica'] ?? [])),
        'observacoes' => laudoFiltrarLinhas((array) ($payload['observacoes_finais'] ?? [])),
        'disclaimer'  => LAUDO_DISCLAIMER,
        'local_data'  => laudoLocalDataLinha($cabecalho),
    ];
}

/**
 * Sanitiza um laudo estruturado EDITADO (vindo da previa editavel do navegador)
 * para o formato que gerarLaudoPdf() espera — o mesmo shape de
 * montarLaudoEstruturado(). Cada secao de prosa vira lista de linhas nao-vazias;
 * os textos fixos (titulo, disclaimer) caem no padrao se vierem vazios. Assim o
 * texto que a medica ajustou no navegador vira PDF SEM recompor as frases dos
 * compositores, mantendo o timbrado intacto.
 *
 * @param array<string,mixed> $laudo Laudo estruturado (possivelmente editado).
 * @return array{cabecalho:array<string,mixed>,titulo:string,orgaos:array<string>,impressao:array<string>,observacoes:array<string>,disclaimer:string,local_data:string}
 */
function laudoEditadoParaPdf(array $laudo): array
{
    $lista = static function ($valor): array {
        $itens = [];
        foreach ((is_array($valor) ? $valor : []) as $item) {
            $s = trim((string) $item);
            if ($s !== '') { $itens[] = $s; }
        }
        return $itens;
    };
    $texto = static fn($v, string $padrao): string => trim((string) ($v ?? '')) !== '' ? (string) $v : $padrao;

    return [
        'cabecalho'   => is_array($laudo['cabecalho'] ?? null) ? $laudo['cabecalho'] : [],
        'titulo'      => $texto($laudo['titulo'] ?? null, LAUDO_TITULO),
        'orgaos'      => $lista($laudo['orgaos'] ?? []),
        'impressao'   => $lista($laudo['impressao'] ?? []),
        'observacoes' => $lista($laudo['observacoes'] ?? []),
        'disclaimer'  => $texto($laudo['disclaimer'] ?? null, LAUDO_DISCLAIMER),
        'local_data'  => (string) ($laudo['local_data'] ?? ''),
    ];
}
