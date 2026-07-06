<?php

declare(strict_types=1);

/**
 * Geracao do PDF do laudo.
 *
 * Biblioteca escolhida: setasign/fpdf (FPDF 1.8).
 *   - Leve: 1 pacote, ZERO dependencias, PHP puro.
 *   - Suporte nativo a anexar JPEG (Image()), sem renderer headless/HTML.
 *   - Compativel com o PHP 8.3-cli do projeto; instalar via `composer install`.
 * As fontes core do FPDF usam Windows-1252, entao o texto (UTF-8) e convertido com
 * mb_convert_encoding. As imagens JPEG recebidas sao EFEMERAS (CLAUDE.md secao 2):
 * gravadas em arquivo temporario apenas para o Image() e removidas ao final.
 *
 * API: gerarLaudoPdf(string $textoLaudo, array $imagensJpeg = []): string
 *   - $textoLaudo: saida de montarLaudo() (src/laudo.php).
 *   - $imagensJpeg: lista de strings com os BYTES de cada JPEG a anexar.
 *   - retorna os BYTES do PDF final.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

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
 * FPDF do laudo com rodape de numeracao de pagina.
 */
final class LaudoPdf extends FPDF
{
    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120);
        $this->Cell(0, 8, laudoUtf8ToCp1252('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->SetTextColor(0);
    }
}

/** Renderiza uma linha do laudo, destacando titulo e rotulos em caixa alta. */
function laudoRenderLinha(FPDF $pdf, string $linha): void
{
    if (trim($linha) === '') {
        $pdf->Ln(2.5);
        return;
    }

    // Titulo centralizado.
    if (strpos($linha, 'LAUDO DE ULTRASSONOGRAFIA ABDOMINAL') !== false) {
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, laudoUtf8ToCp1252('LAUDO DE ULTRASSONOGRAFIA ABDOMINAL'), 0, 1, 'C');
        $pdf->Ln(2);
        return;
    }

    // Linha "ROTULO: corpo" com rotulo em caixa alta -> rotulo em negrito, corpo normal.
    if (preg_match('/^([A-ZÁÂÃÀÉÊÍÓÔÕÚÇ0-9][A-ZÁÂÃÀÉÊÍÓÔÕÚÇ0-9 \/()\-]*:)\s?(.*)$/u', $linha, $m)) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Write(5, laudoUtf8ToCp1252($m[1] . ' '));
        if ($m[2] !== '') {
            $pdf->SetFont('Arial', '', 10);
            $pdf->Write(5, laudoUtf8ToCp1252($m[2]));
        }
        $pdf->Ln(5);
        return;
    }

    // Linha comum.
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 5, laudoUtf8ToCp1252($linha));
}

/** Coloca um JPEG centralizado, redimensionado para caber na area util da pagina. */
function laudoImagemNaPagina(FPDF $pdf, string $arquivo): void
{
    $info = getimagesize($arquivo);
    if ($info === false) {
        return;
    }
    [$w, $h] = $info;
    $maxLargura = $pdf->GetPageWidth() - 36;   // margens laterais (18 + 18)
    $maxAltura  = $pdf->GetPageHeight() - 45;   // topo + rodape
    $escala = min($maxLargura / $w, $maxAltura / $h, 1.0);
    $dw = $w * $escala;
    $dh = $h * $escala;
    $x = ($pdf->GetPageWidth() - $dw) / 2;
    $pdf->Image($arquivo, $x, $pdf->GetY() + 2, $dw, $dh, 'JPG');
}

/**
 * Gera o PDF do laudo, anexando as imagens JPEG (uma por pagina).
 *
 * @param string        $textoLaudo  Texto do laudo (saida de montarLaudo()).
 * @param array<string> $imagensJpeg Bytes de cada JPEG a anexar (efemeros).
 * @return string Bytes do PDF final.
 */
function gerarLaudoPdf(string $textoLaudo, array $imagensJpeg = []): string
{
    $pdf = new LaudoPdf('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(18, 16, 18);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->AddPage();

    foreach (explode("\n", $textoLaudo) as $linha) {
        laudoRenderLinha($pdf, $linha);
    }

    // Anexa as imagens JPEG (efemeras: gravadas so para o Image(), removidas ao final).
    if ($imagensJpeg) {
        $temporarios = [];
        try {
            $indice = 0;
            foreach ($imagensJpeg as $jpeg) {
                $indice++;
                $tmp = tempnam(sys_get_temp_dir(), 'laudo_img_');
                $arquivo = $tmp . '.jpg';
                file_put_contents($arquivo, $jpeg);
                @unlink($tmp);
                $temporarios[] = $arquivo;

                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->Cell(0, 8, laudoUtf8ToCp1252('Imagem ') . $indice, 0, 1, 'L');
                laudoImagemNaPagina($pdf, $arquivo);
            }
        } finally {
            foreach ($temporarios as $arquivo) {
                if (is_file($arquivo)) {
                    @unlink($arquivo);
                }
            }
        }
    }

    return (string) $pdf->Output('S');
}
