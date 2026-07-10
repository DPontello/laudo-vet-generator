<?php

declare(strict_types=1);

/**
 * Compositor de frases da Cavidade Abdominal.
 *
 * Recebe o objeto `cavidade_abdominal` do payload JSON
 * (docs/referencia/laudo.schema.json) e devolve o paragrafo final, iniciando por
 * "CAVIDADE ABDOMINAL: ".
 *
 * Estrategia (decisao "Hibrido"): prosa canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt), na ordem do modelo:
 *   1. "sem alteracoes" (linfonodos/vasos + liquido/massas), quando aplicavel;
 *   2. linfonodos aumentados (+ grupos);
 *   3. mesenterio reativo;
 *   4. liquido livre (quantidade/aspecto/sitio);
 *   5. hernia (medida/regiao).
 *
 * Nota: o modelo escreve "medindo aproximadamente." nos linfonodos, mas o schema
 * nao tem medida de linfonodo — a clausula e omitida. A hernia so leva ", redutível
 * (hérnia inguinal)" nas regioes inguinais (o modelo so traz essa variante); a
 * umbilical encerra em "tecido adiposo", como no laudo real da Clarinha.
 */

require_once __DIR__ . '/_helpers.php';

/**
 * Compoe o paragrafo da Cavidade Abdominal.
 *
 * @param array<string,mixed> $c Objeto `cavidade_abdominal` do payload.
 * @return string Paragrafo pronto, iniciando por "CAVIDADE ABDOMINAL: ".
 */
function composeCavidadeAbdominal(array $c): string
{
    if (($c['avaliado'] ?? true) === false) {
        return 'CAVIDADE ABDOMINAL: Não avaliada.';
    }

    $frases = [];

    // 1. Sem alteracoes (achado normal).
    if (($c['sem_alteracoes'] ?? true) === true) {
        $frases[] = 'Sem evidências ultrassonográficas de alterações em linfonodos e vasos abdominais.';
        $frases[] = 'Não se observou líquido livre ou massas abdominais.';
    }

    // 2. Linfonodos aumentados.
    $lf = (array) ($c['linfonodos_aumentados'] ?? []);
    if (($lf['presente'] ?? false) === true) {
        $mapa = ['abdominais' => 'abdominais', 'jejunais' => 'jejunais', 'iliacos_mediais' => 'ilíacos mediais'];
        $grupos = [];
        foreach ((array) ($lf['grupos'] ?? []) as $g) { $grupos[] = $mapa[$g] ?? $g; }
        $lista = listaPtBr($grupos);
        $frases[] = $lista !== ''
            ? "Linfonodos {$lista} aumentados de tamanho."
            : 'Linfonodos aumentados de tamanho.';
        $frases[] = 'Ecogenicidade e ecotextura usuais, com margens regulares.';
    }

    // 3. Mesenterio reativo.
    if (($c['mesenterio_reativo'] ?? false) === true) {
        $frases[] = 'Mesentério difusamente espessado e hiperecogênico (mesentério reativo).';
    }

    // 4. Liquido livre.
    $ll = (array) ($c['liquido_livre'] ?? []);
    if (($ll['presente'] ?? false) === true) {
        $quantidade = [
            'irrisoria' => 'irrisória', 'discreta' => 'discreta', 'moderada' => 'moderada', 'acentuada' => 'acentuada',
        ][$ll['quantidade'] ?? 'discreta'] ?? 'discreta';
        $aspecto = [
            'anecogenico' => 'anecogênico homogêneo',
            'particulado' => 'particulado (alta celularidade)',
            'ecogenico'   => 'ecogênico (alta celularidade)',
        ][$ll['aspecto'] ?? 'anecogenico'] ?? 'anecogênico homogêneo';
        $sitio = [
            'hepatodiafragmatico' => 'hepatodiafragmático', 'esplenorrenal' => 'esplenorrenal',
            'cistocolico' => 'cistocólico', 'hepatorrenal' => 'hepatorrenal', 'disperso' => 'disperso',
        ][$ll['sitio'] ?? 'disperso'] ?? 'disperso';
        $frases[] = "Presença de {$quantidade} quantidade de líquido livre {$aspecto} em sítios {$sitio}.";
    }

    // 5. Hernia.
    $h = (array) ($c['hernia'] ?? []);
    if (($h['presente'] ?? false) === true) {
        $regiaoKey = $h['regiao'] ?? 'umbilical';
        $regiao = [
            'inguinal_esquerda' => 'inguinal esquerda', 'inguinal_direita' => 'inguinal direita', 'umbilical' => 'umbilical',
        ][$regiaoKey] ?? 'umbilical';
        $desc = $h['descontinuidade_cm'] ?? null;
        $medida = $desc !== null ? 'de aproximadamente ' . formatarCm((float) $desc) . ' cm ' : '';
        $s = "Nota-se descontinuidade {$medida}na musculatura abdominal em região {$regiao}, com conteúdo "
            . 'hipoecogênico homogêneo sugestivo de tecido adiposo';
        if ($regiaoKey !== 'umbilical') {
            $s .= ', redutível (hérnia inguinal)';
        }
        $frases[] = $s . '.';
    }

    // 6. Observacoes livres.
    $obs = trim((string) ($c['observacoes'] ?? ''));
    if ($obs !== '') { $frases[] = $obs; }

    return 'CAVIDADE ABDOMINAL: ' . implode(' ', $frases);
}
