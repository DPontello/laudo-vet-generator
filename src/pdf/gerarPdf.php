<?php

declare(strict_types=1);

/**
 * Geracao do PDF do laudo no papel timbrado da medica.
 *
 * Biblioteca: setasign/fpdf (FPDF 1.8) — leve, zero dependencias, JPEG nativo.
 * A identidade visual (topo com nome/logo, rodape com contatos e a assinatura)
 * foi extraida do PDF modelo original em 300 DPI e vive em src/pdf/assets/:
 *   - timbrado-topo-capa.png  (pagina 1: barras + nome + logo)
 *   - timbrado-topo.png       (paginas seguintes: nome + logo)
 *   - timbrado-rodape.png     (todas as paginas: barras + contatos)
 *   - assinatura.png          (assinatura manuscrita sobre a linha)
 *
 * Tipografia do corpo segue o modelo: serifada (Times), paragrafo justificado
 * com o nome do orgao em negrito, observacoes em italico, disclaimer em
 * negrito-italico. As fontes core do FPDF usam Windows-1252, entao o texto
 * (UTF-8) e convertido com mb_convert_encoding.
 *
 * As imagens JPEG recebidas sao EFEMERAS (CLAUDE.md secao 2): gravadas em
 * arquivo temporario apenas para o Image() e removidas ao final. No anexo, a
 * imagem nunca e ampliada alem de ~150 DPI — ampliar era o que deixava as
 * fotos borradas/pixeladas no PDF antigo.
 *
 * API: gerarLaudoPdf(array $laudo, array $imagensJpeg = []): string
 *   - $laudo: saida de montarLaudoEstruturado() (src/laudo.php).
 *   - $imagensJpeg: lista de strings com os BYTES de cada JPEG a anexar.
 *   - retorna os BYTES do PDF final.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../laudo.php';   // laudoDataCurta() e montarLaudoEstruturado()

/** Pasta dos assets do timbrado. */
const LAUDO_PDF_ASSETS = __DIR__ . '/assets';

/* Geometria (mm, A4 retrato). */
const PDF_LARGURA      = 210.0;
const PDF_ALTURA       = 297.0;
const PDF_MARGEM       = 19.0;                       // margens laterais (como no modelo)
const PDF_TOPO_CAPA_H  = 46.9;                       // arte do topo da pagina 1 (570/2550 * 210)
const PDF_TOPO_H       = 35.4;                       // arte do topo das demais paginas (430/2550 * 210)
const PDF_RODAPE_H     = 25.5;                       // arte do rodape (310/2550 * 210)
const PDF_CORPO_FUNDO  = 267.0;                      // limite inferior do texto (rodape + respiro)
const PDF_IMG_DPI_MIN  = 150;                        // nitidez minima do anexo de imagens

/** Converte UTF-8 -> Windows-1252 para as fontes core do FPDF. */
function laudoUtf8ToCp1252(string $texto): string
{
    if (function_exists('mb_convert_encoding')) {
        return (string) mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }
    if (function_exists('iconv')) {
        return (string) iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
    }
    return $texto;
}

/**
 * FPDF com o papel timbrado: arte no topo (versao "capa" na pagina 1) e
 * rodape com os contatos em todas as paginas. Sem numeracao (como o modelo).
 */
final class LaudoPdf extends FPDF
{
    public function Header(): void
    {
        $capa = $this->PageNo() === 1;
        $arte = LAUDO_PDF_ASSETS . ($capa ? '/timbrado-topo-capa.png' : '/timbrado-topo.png');
        if (is_file($arte)) {
            $this->Image($arte, 0, 0, PDF_LARGURA);
        }
        $this->SetY($capa ? PDF_TOPO_CAPA_H + 6.0 : PDF_TOPO_H + 6.5);
    }

    public function Footer(): void
    {
        $arte = LAUDO_PDF_ASSETS . '/timbrado-rodape.png';
        if (is_file($arte)) {
            $this->Image($arte, 0, PDF_ALTURA - PDF_RODAPE_H, PDF_LARGURA);
        }
    }
}

/**
 * Escreve um paragrafo com trechos de estilos mistos (negrito, italico...),
 * justificado como no modelo. FPDF nao mistura estilos num MultiCell, entao a
 * quebra de linha e a distribuicao dos espacos sao feitas palavra a palavra.
 *
 * @param array<array{0:string,1:string}> $runs Pares [texto, estilo FPDF ('', 'B', 'I', 'BI')].
 * @param string $alinhamento 'J' (justificado) ou 'L' (esquerda).
 */
function pdfParagrafoRico(LaudoPdf $pdf, array $runs, float $tamanho, float $lh, string $alinhamento = 'J'): void
{
    // Tokeniza em palavras, preservando o estilo de cada uma.
    $palavras = [];
    foreach ($runs as [$texto, $estilo]) {
        foreach (preg_split('/\s+/u', trim($texto)) ?: [] as $p) {
            if ($p !== '') { $palavras[] = [$p, $estilo]; }
        }
    }
    if (!$palavras) { return; }

    $larguraUtil = PDF_LARGURA - 2 * PDF_MARGEM;

    // Mede cada palavra (e o espaco) no estilo correspondente.
    $medidas = [];
    $espaco = [];
    foreach ($palavras as $i => [$p, $estilo]) {
        if (!isset($espaco[$estilo])) {
            $pdf->SetFont('Times', $estilo, $tamanho);
            $espaco[$estilo] = $pdf->GetStringWidth(' ');
        } else {
            $pdf->SetFont('Times', $estilo, $tamanho);
        }
        $medidas[$i] = $pdf->GetStringWidth(laudoUtf8ToCp1252($p));
    }

    // Quebra gulosa em linhas.
    $linhas = [];
    $linha = [];
    $somaPalavras = 0.0;
    $somaEspacos = 0.0;
    foreach ($palavras as $i => [$p, $estilo]) {
        $esp = $linha ? $espaco[$estilo] : 0.0;
        if ($linha && ($somaPalavras + $somaEspacos + $esp + $medidas[$i]) > $larguraUtil + 0.01) {
            $linhas[] = [$linha, $somaPalavras];
            $linha = [];
            $somaPalavras = 0.0;
            $somaEspacos = 0.0;
            $esp = 0.0;
        }
        $linha[] = [$p, $estilo, $medidas[$i]];
        $somaPalavras += $medidas[$i];
        $somaEspacos += $esp;
    }
    if ($linha) { $linhas[] = [$linha, $somaPalavras]; }

    // Desenha linha a linha, distribuindo o espaco excedente entre as palavras.
    $total = count($linhas);
    foreach ($linhas as $n => [$itens, $somaPal]) {
        if ($pdf->GetY() + $lh > PDF_CORPO_FUNDO) { $pdf->AddPage(); }

        $gaps = count($itens) - 1;
        $justifica = $alinhamento === 'J' && $n < $total - 1 && $gaps > 0;
        $gap = $justifica ? ($larguraUtil - $somaPal) / $gaps : null;

        $x = PDF_MARGEM;
        $y = $pdf->GetY();
        foreach ($itens as $j => [$p, $estilo, $w]) {
            $pdf->SetFont('Times', $estilo, $tamanho);
            $pdf->Text($x, $y + $lh * 0.75, laudoUtf8ToCp1252($p));
            if ($j < $gaps) {
                $x += $w + ($gap ?? $espaco[$itens[$j + 1][1]]);
            }
        }
        $pdf->SetY($y + $lh);
    }
}

/** Garante espaco vertical minimo antes de um bloco; senao abre nova pagina. */
function pdfGarantirEspaco(LaudoPdf $pdf, float $alturaMinima): void
{
    if ($pdf->GetY() + $alturaMinima > PDF_CORPO_FUNDO) {
        $pdf->AddPage();
    }
}

/**
 * Rotulo de secao numa linha so (ex.: "IMPRESSÃO DIAGNÓSTICA:"), desenhado com
 * Text() para alinhar exatamente na margem e manter o sublinhado continuo.
 */
function pdfRotulo(LaudoPdf $pdf, string $texto, string $estilo, float $tamanho, float $lh): void
{
    if ($pdf->GetY() + $lh > PDF_CORPO_FUNDO) { $pdf->AddPage(); }
    $pdf->SetFont('Times', $estilo, $tamanho);
    $pdf->Text(PDF_MARGEM, $pdf->GetY() + $lh * 0.75, laudoUtf8ToCp1252($texto));
    $pdf->SetY($pdf->GetY() + $lh);
}

/**
 * Tabela de dados do paciente (pagina 1), com bordas e a mesma divisao de
 * colunas do modelo: Paciente|Data do exame; Especie|Raca|Sexo|Idade;
 * Responsavel; Medico(a) Veterinario(a) Requisitante.
 */
function pdfTabelaPaciente(LaudoPdf $pdf, array $c): void
{
    $h = 6.6;
    $larguraUtil = PDF_LARGURA - 2 * PDF_MARGEM;

    $pdf->SetDrawColor(40, 40, 40);
    $pdf->SetLineWidth(0.28);
    $pdf->SetTextColor(0);

    // Celula com "Rotulo: valor" (rotulo em negrito); valor encolhe se nao couber.
    $celula = static function (float $w, string $rotulo, string $valor) use ($pdf, $h): void {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->Rect($x, $y, $w, $h);

        $pdf->SetFont('Times', 'B', 10.5);
        $rotuloCp = laudoUtf8ToCp1252($rotulo);
        $pdf->SetXY($x + 1.8, $y);
        $pdf->Cell($pdf->GetStringWidth($rotuloCp) + 1.2, $h, $rotuloCp, 0, 0, 'L');

        if ($valor !== '') {
            $restante = $w - ($pdf->GetX() - $x) - 1.5;
            $valorCp = laudoUtf8ToCp1252($valor);
            $tamanho = 10.5;
            $pdf->SetFont('Times', '', $tamanho);
            while ($tamanho > 7.0 && $pdf->GetStringWidth($valorCp) > $restante) {
                $tamanho -= 0.5;
                $pdf->SetFont('Times', '', $tamanho);
            }
            $pdf->Cell($restante, $h, $valorCp, 0, 0, 'L');
        }
        $pdf->SetXY($x + $w, $y);
    };

    $divisor = 107.0;   // alinhado entre as linhas 1 e 2, como no modelo

    $pdf->SetX(PDF_MARGEM);
    $celula($divisor, 'Paciente:', trim((string) ($c['paciente'] ?? '')));
    $celula($larguraUtil - $divisor, 'Data do exame:', laudoDataCurta(isset($c['data_exame']) ? (string) $c['data_exame'] : null));
    $pdf->Ln($h);

    $pdf->SetX(PDF_MARGEM);
    $celula(42.0, 'Espécie:', trim((string) ($c['especie'] ?? '')));
    $celula(41.5, 'Raça:', trim((string) ($c['raca'] ?? '')));
    $celula(23.5, 'Sexo:', trim((string) ($c['sexo'] ?? '')));
    $celula($larguraUtil - $divisor, 'Idade:', trim((string) ($c['idade'] ?? '')));
    $pdf->Ln($h);

    $pdf->SetX(PDF_MARGEM);
    $celula($larguraUtil, 'Responsável:', trim((string) ($c['responsavel'] ?? '')));
    $pdf->Ln($h);

    $pdf->SetX(PDF_MARGEM);
    $celula($larguraUtil, 'Médico(a) Veterinário(a) Requisitante:', trim((string) ($c['veterinario_requisitante'] ?? '')));
    $pdf->Ln($h);
}

/**
 * Bloco de um orgao: "ROTULO:" em negrito seguido da prosa do compositor,
 * justificado. Linhas "*Observacao:" (notas condicionais) saem em italico menor.
 */
function pdfBlocoOrgao(LaudoPdf $pdf, string $bloco): void
{
    pdfGarantirEspaco($pdf, 2 * 5.3);

    foreach (explode("\n", $bloco) as $linha) {
        $linha = trim($linha);
        if ($linha === '') { continue; }

        if (mb_strpos($linha, '*Observação') === 0 || mb_strpos($linha, '*Observacao') === 0) {
            $pdf->Ln(1.5);
            pdfParagrafoRico($pdf, [[$linha, 'I']], 9.5, 4.6);
            continue;
        }

        if (preg_match('/^([A-ZÁÂÃÀÉÊÍÓÔÕÚÜÇ][A-ZÁÂÃÀÉÊÍÓÔÕÚÜÇ0-9 ()\/\-]*:)\s*(.*)$/u', $linha, $m)) {
            pdfParagrafoRico($pdf, [[$m[1], 'B'], [$m[2], '']], 11, 5.3);
        } else {
            pdfParagrafoRico($pdf, [[$linha, '']], 11, 5.3);
        }
    }
    $pdf->Ln(3.8);
}

/**
 * Anexa as imagens do exame em grade de 2 colunas, com moldura discreta.
 * A imagem nunca e ampliada alem de PDF_IMG_DPI_MIN (nitidez preservada);
 * imagem sozinha na linha sai centralizada.
 *
 * @param array<string> $imagensJpeg Bytes de cada JPEG (efemeros).
 */
function pdfAnexarImagens(LaudoPdf $pdf, array $imagensJpeg): void
{
    if (!$imagensJpeg) { return; }

    $temporarios = [];
    try {
        $itens = [];
        foreach ($imagensJpeg as $jpeg) {
            $tmp = tempnam(sys_get_temp_dir(), 'laudo_img_');
            $arquivo = $tmp . '.jpg';
            file_put_contents($arquivo, $jpeg);
            @unlink($tmp);
            $temporarios[] = $arquivo;

            $info = getimagesize($arquivo);
            if ($info === false || $info[0] < 1 || $info[1] < 1) { continue; }
            $itens[] = ['arquivo' => $arquivo, 'px_w' => (int) $info[0], 'px_h' => (int) $info[1]];
        }
        if (!$itens) { return; }

        $pdf->AddPage();

        $gap = 5.0;
        $larguraUtil = PDF_LARGURA - 2 * PDF_MARGEM;
        $colW = ($larguraUtil - $gap) / 2;
        $alturaMax = 105.0;

        foreach (array_chunk($itens, 2) as $par) {
            foreach ($par as $i => $item) {
                $wNitidez = $item['px_w'] / PDF_IMG_DPI_MIN * 25.4;
                $w = min($colW, $wNitidez);
                $h = $w * $item['px_h'] / $item['px_w'];
                if ($h > $alturaMax) {
                    $h = $alturaMax;
                    $w = $h * $item['px_w'] / $item['px_h'];
                }
                $par[$i]['w'] = $w;
                $par[$i]['h'] = $h;
            }
            $alturaLinha = max(array_column($par, 'h'));

            if ($pdf->GetY() + $alturaLinha > PDF_CORPO_FUNDO) { $pdf->AddPage(); }
            $y = $pdf->GetY();

            $sozinha = count($par) === 1;
            foreach (array_values($par) as $col => $item) {
                $x = $sozinha
                    ? PDF_MARGEM + ($larguraUtil - $item['w']) / 2
                    : PDF_MARGEM + $col * ($colW + $gap) + ($colW - $item['w']) / 2;
                $yImg = $y + ($alturaLinha - $item['h']) / 2;

                $pdf->Image($item['arquivo'], $x, $yImg, $item['w'], $item['h'], 'JPG');
                $pdf->SetDrawColor(185, 185, 185);
                $pdf->SetLineWidth(0.2);
                $pdf->Rect($x, $yImg, $item['w'], $item['h']);
            }
            $pdf->SetY($y + $alturaLinha + $gap);
        }
    } finally {
        foreach ($temporarios as $arquivo) {
            if (is_file($arquivo)) { @unlink($arquivo); }
        }
    }
}

/**
 * Gera o PDF do laudo no papel timbrado, anexando as imagens JPEG ao final.
 *
 * @param array<string,mixed> $laudo       Saida de montarLaudoEstruturado().
 * @param array<string>       $imagensJpeg Bytes de cada JPEG a anexar (efemeros).
 * @return string Bytes do PDF final.
 */
function gerarLaudoPdf(array $laudo, array $imagensJpeg = []): string
{
    $pdf = new LaudoPdf('P', 'mm', 'A4');
    $pdf->SetTitle('Laudo de Ultrassonografia Abdominal', true);
    $pdf->SetAuthor('Aline Marques de Souza — CRMV-MG 31116', true);
    $pdf->SetCreator('laudo-vet-generator', true);
    $pdf->SetMargins(PDF_MARGEM, PDF_TOPO_H + 6.5, PDF_MARGEM);
    $pdf->SetAutoPageBreak(true, PDF_ALTURA - PDF_CORPO_FUNDO);
    $pdf->AddPage();
    $pdf->SetTextColor(0);

    // Tabela do paciente + titulo (pagina 1).
    pdfTabelaPaciente($pdf, (array) ($laudo['cabecalho'] ?? []));
    $pdf->Ln(3.5);
    $pdf->SetFont('Times', 'B', 12.5);
    $pdf->Cell(0, 8, laudoUtf8ToCp1252((string) ($laudo['titulo'] ?? 'LAUDO DE ULTRASSONOGRAFIA ABDOMINAL')), 0, 1, 'C');
    $pdf->Ln(2.5);

    // Orgaos (prosa dos compositores, inalterada).
    foreach ((array) ($laudo['orgaos'] ?? []) as $bloco) {
        pdfBlocoOrgao($pdf, (string) $bloco);
    }

    // IMPRESSAO DIAGNOSTICA.
    $impressao = (array) ($laudo['impressao'] ?? []);
    if ($impressao) {
        pdfGarantirEspaco($pdf, 14.0);
        $pdf->Ln(1.5);
        pdfRotulo($pdf, 'IMPRESSÃO DIAGNÓSTICA:', 'BIU', 11.5, 6.5);
        $pdf->Ln(0.8);
        foreach ($impressao as $item) {
            pdfParagrafoRico($pdf, [[(string) $item, '']], 11, 5.3);
        }
        $pdf->Ln(3.5);
    }

    // Observacoes finais (biblioteca de notas), em italico como no modelo.
    $observacoes = (array) ($laudo['observacoes'] ?? []);
    if ($observacoes) {
        pdfGarantirEspaco($pdf, 13.0);
        $pdf->Ln(1.5);
        pdfRotulo($pdf, count($observacoes) > 1 ? 'Observações:' : 'Observação:', 'BI', 11, 6.0);
        $pdf->Ln(0.8);
        foreach ($observacoes as $item) {
            pdfParagrafoRico($pdf, [[(string) $item, 'I']], 10.5, 5.0);
            $pdf->Ln(1.2);
        }
        $pdf->Ln(2.5);
    }

    // Disclaimer fixo, negrito-italico como no modelo.
    $disclaimer = trim((string) ($laudo['disclaimer'] ?? ''));
    if ($disclaimer !== '') {
        pdfGarantirEspaco($pdf, 20.0);
        $pdf->Ln(2.5);
        pdfParagrafoRico($pdf, [[$disclaimer, 'BI']], 10, 4.8);
    }

    // Local/data + assinatura (bloco mantido junto na mesma pagina).
    pdfGarantirEspaco($pdf, 48.0);
    $pdf->Ln(7.0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, laudoUtf8ToCp1252((string) ($laudo['local_data'] ?? '')), 0, 1, 'L');
    $pdf->Ln(4.0);

    $assinatura = LAUDO_PDF_ASSETS . '/assinatura.png';
    if (is_file($assinatura)) {
        $w = 52.0;
        $h = $w * 180 / 680;   // proporcao do asset extraido (680x180)
        $pdf->Image($assinatura, (PDF_LARGURA - $w) / 2, $pdf->GetY(), $w, $h);
        $pdf->Ln($h + 2.0);
    } else {
        $pdf->Ln(9.0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 5, '___________________________', 0, 1, 'C');
    }
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->Cell(0, 5.5, laudoUtf8ToCp1252('Aline Marques de Souza'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'CRMV-MG 31116', 0, 1, 'C');

    // Anexo de imagens do exame.
    pdfAnexarImagens($pdf, $imagensJpeg);

    return (string) $pdf->Output('S');
}
