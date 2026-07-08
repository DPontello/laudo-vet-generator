<?php

declare(strict_types=1);

/**
 * Checklists personalizados (opcoes de observacao criadas pela propria medica).
 *
 * A medica cria, por secao, itens marcaveis com a FRASE que cada um insere no
 * laudo. Como o texto e autoral dela, entra pelo canal de texto livre de cada
 * orgao (`observacoes`) — os compositores ja imprimem isso —, sem precisar mexer
 * na prosa parametrizada (CLAUDE.md secao 10).
 *
 * Persistencia: um unico arquivo JSON em data/checklists.json (dados do usuario,
 * fora do versionamento). No Docker esse diretorio e um volume (docker-compose),
 * para sobreviver a reconstrucoes da imagem.
 *
 * Formato:
 *   { "<secao>": [ { "id": "...", "label": "...", "texto": "..." }, ... ], ... }
 */

/**
 * Caminho do arquivo de checklists personalizados. Pode ser sobrescrito via
 * variavel de ambiente LAUDO_CHECKLISTS_FILE (usada pelos testes para nao tocar
 * os dados reais, e util para apontar o storage em deploy).
 */
function checklistsArquivo(): string
{
    $env = getenv('LAUDO_CHECKLISTS_FILE');
    return ($env !== false && $env !== '') ? $env : dirname(__DIR__) . '/data/checklists.json';
}

/** Secoes que aceitam checklists personalizados (orgaos + observacoes finais). */
const CHECKLISTS_SECOES = [
    'bexiga', 'rins', 'adrenais', 'figado', 'vesicula_biliar', 'baco',
    'estomago', 'intestinos', 'pancreas', 'reprodutor', 'cavidade_abdominal',
    'observacoes_finais',
];

/**
 * Carrega a configuracao salva. Devolve [] (objeto vazio) se ausente/invalida —
 * o app trata isso como "sem checklists personalizados".
 *
 * @return array<string,array<int,array{id:string,label:string,texto:string}>>
 */
function carregarChecklists(): array
{
    $arquivo = checklistsArquivo();
    if (!is_file($arquivo)) { return []; }
    $bruto = file_get_contents($arquivo);
    $dados = json_decode((string) $bruto, true);
    return is_array($dados) ? $dados : [];
}

/**
 * Valida a configuracao recebida do cliente.
 *
 * @param mixed $dados
 * @return string|null Mensagem de erro, ou null se valido.
 */
function validarChecklists($dados): ?string
{
    if (!is_array($dados)) {
        return 'Configuracao invalida (esperado objeto por secao).';
    }
    foreach ($dados as $secao => $itens) {
        if (!in_array($secao, CHECKLISTS_SECOES, true)) {
            return "Secao desconhecida: {$secao}.";
        }
        if (!is_array($itens)) {
            return "A secao {$secao} deve ser uma lista de itens.";
        }
        foreach ($itens as $item) {
            if (!is_array($item)) {
                return "Item invalido na secao {$secao}.";
            }
            if (!isset($item['texto']) || trim((string) $item['texto']) === '') {
                return "Ha um item sem texto na secao {$secao}.";
            }
        }
    }
    return null;
}

/**
 * Sanitiza e grava a configuracao. Descarta itens sem texto, normaliza o rotulo
 * (usa o inicio do texto quando vazio) e garante um id.
 *
 * @param array<string,mixed> $dados
 * @return array<string,array<int,array{id:string,label:string,texto:string}>> Config salva.
 */
function salvarChecklists(array $dados): array
{
    $saida = [];
    foreach (CHECKLISTS_SECOES as $secao) {
        $itens = $dados[$secao] ?? [];
        if (!is_array($itens)) { continue; }
        $lista = [];
        $seq = 0;
        foreach ($itens as $item) {
            if (!is_array($item)) { continue; }
            $texto = trim((string) ($item['texto'] ?? ''));
            if ($texto === '') { continue; }
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                $label = mb_strlen($texto) > 60 ? mb_substr($texto, 0, 57) . '…' : $texto;
            }
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') { $id = $secao . '-' . (++$seq) . '-' . substr(md5($texto), 0, 6); }
            $lista[] = ['id' => $id, 'label' => $label, 'texto' => $texto];
        }
        if ($lista) { $saida[$secao] = $lista; }
    }

    $arquivo = checklistsArquivo();
    $dir = dirname($arquivo);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $json = json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (file_put_contents($arquivo, (string) $json) === false) {
        throw new \RuntimeException('Nao foi possivel gravar ' . $arquivo);
    }
    return $saida;
}
