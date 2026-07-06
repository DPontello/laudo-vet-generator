<?php

declare(strict_types=1);

/**
 * Compositor de frases do orgao Adrenais.
 *
 * Recebe o objeto `adrenais` do payload JSON (docs/referencia/laudo.schema.json)
 * e devolve o paragrafo final, iniciando por "ADRENAIS: ". Cada adrenal (esquerda
 * e direita) e avaliada individualmente.
 *
 * Estrategia (decisao "Hibrido"): prosa canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt). Estrutura:
 *   - ambas nao visibilizadas -> "Não visibilizadas.";
 *   - caso contrario -> "Topografia e morfologia usuais." + frase por adrenal
 *     alterada + "Ecogenicidade usual e ecotextura homogênea." no fim.
 *
 * Nota de fidelidade: a Clarinha funde "topografia e morfologia usuais" dentro da
 * frase da adrenal esquerda e coloca a ecogenicidade antes da adrenal direita;
 * seguimos a ordem canonica do modelo (frase-lider separada, ecogenicidade ao
 * final). Diferenca de posicao documentada no teste.
 */

require_once __DIR__ . '/_helpers.php';

/**
 * Clausula de medidas de uma adrenal ("medindo aproximadamente ... de comprimento")
 * ou vazio se nenhuma medida foi aferida.
 *
 * @param array<string,mixed> $a Objeto de uma adrenal.
 */
function adrenalMedidas(array $a): string
{
    $partes = [];
    if (($a['polo_caudal_cm'] ?? null) !== null)  { $partes[] = formatarCm((float) $a['polo_caudal_cm']) . ' cm em polo caudal'; }
    if (($a['polo_cranial_cm'] ?? null) !== null) { $partes[] = formatarCm((float) $a['polo_cranial_cm']) . ' cm em polo cranial'; }
    if (($a['comprimento_cm'] ?? null) !== null)  { $partes[] = formatarCm((float) $a['comprimento_cm']) . ' cm de comprimento'; }

    if (!$partes) {
        return '';
    }
    $ultima = array_pop($partes);
    $texto = $partes ? implode(', ', $partes) . ' e ' . $ultima : $ultima;
    return 'medindo aproximadamente ' . $texto;
}

/**
 * Frase de uma adrenal, ou vazio quando visibilizada e sem alteracoes (coberta
 * pela frase-lider "Topografia e morfologia usuais").
 *
 * @param array<string,mixed> $a    Objeto de uma adrenal.
 * @param string              $nome "esquerda" ou "direita".
 */
function adrenalFrase(array $a, string $nome): string
{
    $vis = $a['visibilizacao'] ?? 'visibilizada';

    if ($vis === 'nao_visibilizada') {
        return "Adrenal {$nome} não visibilizada.";
    }

    $clausulas = [];
    if ($vis === 'parcialmente_visibilizada') {
        $clausulas[] = 'parcialmente visibilizada';
    }
    if (($a['aumentada'] ?? false) === true) {
        $clausulas[] = 'aumentada de tamanho';
    }
    $medidas = adrenalMedidas($a);
    if ($medidas !== '') {
        $clausulas[] = $medidas;
    }

    if (!$clausulas) {
        return '';
    }
    return "Adrenal {$nome} " . implode(', ', $clausulas) . '.';
}

/**
 * Compoe o paragrafo das Adrenais.
 *
 * @param array<string,mixed> $ad Objeto `adrenais` do payload.
 * @return string Paragrafo pronto, iniciando por "ADRENAIS: ".
 */
function composeAdrenais(array $ad): string
{
    if (($ad['avaliado'] ?? true) === false) {
        return 'ADRENAIS: Não avaliadas.';
    }

    $esq = (array) ($ad['esquerda'] ?? []);
    $dir = (array) ($ad['direita'] ?? []);
    $visEsq = $esq['visibilizacao'] ?? 'visibilizada';
    $visDir = $dir['visibilizacao'] ?? 'visibilizada';

    // Ambas nao visibilizadas: frase unica.
    if ($visEsq === 'nao_visibilizada' && $visDir === 'nao_visibilizada') {
        return 'ADRENAIS: Não visibilizadas.';
    }

    $frases = ['Topografia e morfologia usuais.'];

    $fEsq = adrenalFrase($esq, 'esquerda');
    if ($fEsq !== '') { $frases[] = $fEsq; }
    $fDir = adrenalFrase($dir, 'direita');
    if ($fDir !== '') { $frases[] = $fDir; }

    $frases[] = 'Ecogenicidade usual e ecotextura homogênea.';

    $obs = trim((string) ($ad['observacoes'] ?? ''));
    if ($obs !== '') { $frases[] = $obs; }

    return 'ADRENAIS: ' . implode(' ', $frases);
}
