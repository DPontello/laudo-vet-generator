<?php

declare(strict_types=1);

/**
 * Teste de geracao de PDF. Monta o laudo da Clarinha, gera o PDF (com imagens
 * JPEG de amostra) e verifica que:
 *   - os bytes retornados nao estao vazios e comecam por "%PDF";
 *   - o arquivo gravado em disco nao esta vazio;
 *   - os arquivos temporarios das imagens sao removidos ao final (efemeros).
 * Nao compara conteudo binario (conforme o plano).
 *
 * Uso: php tests/pdf-gerar.php
 */

require __DIR__ . '/../src/laudo.php';
require __DIR__ . '/../src/pdf/gerarPdf.php';

$falhas = 0;
function checa(string $rotulo, bool $ok, string $detalhe = ''): void
{
    global $falhas;
    if ($ok) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}" . ($detalhe !== '' ? "  ({$detalhe})" : '') . "\n";
}

/** JPEG de amostra (bytes) lido das fixtures — nao depende da extensao gd. */
function jpegDeAmostra(string $nome): string
{
    $caminho = __DIR__ . '/fixtures/' . $nome;
    $bytes = file_get_contents($caminho);
    if ($bytes === false || $bytes === '') {
        fwrite(STDERR, "fixture ausente: {$caminho}\n");
        exit(1);
    }
    return $bytes;
}

$payload = [
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
        'rins' => [
            'avaliado' => true, 'simetria' => 'simetricos', 'medida_esquerdo_cm' => 4.54, 'medida_direito_cm' => 4.34,
            'contornos' => 'regulares', 'ecogenicidade_cortical' => 'hiperecogenicidade_difusa', 'ecogenicidade_grau' => 'discreta',
            'ecotextura' => 'homogenea', 'relacao_corticomedular' => 'mantida',
            'achados' => [['tipo' => 'mineralizacao_diverticular', 'lado' => 'bilateral', 'grau' => 'discreta']],
            'litiase_ausente' => true,
        ],
        'reprodutor' => ['utero_ovarios_ausentes' => ['avaliado' => true]],
        'cavidade_abdominal' => [
            'avaliado' => true, 'sem_alteracoes' => true,
            'hernia' => ['presente' => true, 'descontinuidade_cm' => 0.41, 'regiao' => 'umbilical'],
        ],
    ],
    'impressao_diagnostica' => ['Hérnia umbilical.', 'Adrenomegalia esquerda.'],
    'observacoes_finais' => ['A repleção gastrointestinal por conteúdo gasoso impede a avaliação completa.'],
];

$laudo = montarLaudo($payload);

// Conta temporarios de imagem antes (para checar limpeza depois).
$padraoTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laudo_img_*';
$antes = count(glob($padraoTmp) ?: []);

$imagens = [jpegDeAmostra('imagem-amostra-1.jpg'), jpegDeAmostra('imagem-amostra-2.jpg')];
$pdf = gerarLaudoPdf($laudo, $imagens);

checa('PDF nao vazio', strlen($pdf) > 0, 'bytes=' . strlen($pdf));
checa('assinatura %PDF', substr($pdf, 0, 4) === '%PDF');

$destino = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laudo-clarinha-teste.pdf';
file_put_contents($destino, $pdf);
checa('arquivo gravado nao vazio', is_file($destino) && filesize($destino) > 0, $destino);

$depois = count(glob($padraoTmp) ?: []);
checa('imagens temporarias removidas', $depois === $antes, "antes={$antes} depois={$depois}");

// Sem imagens: ainda gera PDF valido.
$pdfSemImagens = gerarLaudoPdf($laudo);
checa('PDF sem imagens', strlen($pdfSemImagens) > 0 && substr($pdfSemImagens, 0, 4) === '%PDF');

echo "\nPDF de amostra: {$destino} (" . filesize($destino) . " bytes)\n";

if ($falhas === 0) { echo "\nOK: geracao de PDF verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} verificacao(oes).\n";
exit(1);
