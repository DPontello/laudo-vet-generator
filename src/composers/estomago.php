<?php

declare(strict_types=1);

/**
 * Compositor de frases do orgao Estomago.
 *
 * Recebe o objeto `estomago` do payload JSON (docs/referencia/laudo.schema.json) e
 * devolve o paragrafo final, iniciando por "ESTÔMAGO: ".
 *
 * Estrategia (decisao "Hibrido"): prosa canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt). Frases:
 *   1. topografia + replecao (gas e ingesta / gas / vazio);
 *   2. paredes + faixa de espessura + estratificacao.
 *
 * A parede "espessada" usa a redacao real da Clarinha ("espessas em algumas
 * porções"), unico exemplo de parede alterada disponivel (o modelo so traz
 * "normoespessas"). O caso da Clarinha bate verbatim.
 */

require_once __DIR__ . '/_helpers.php';

/**
 * Compoe o paragrafo do Estomago.
 *
 * @param array<string,mixed> $e Objeto `estomago` do payload.
 * @return string Paragrafo pronto, iniciando por "ESTÔMAGO: ".
 */
function composeEstomago(array $e): string
{
    if (($e['avaliado'] ?? true) === false) {
        return 'ESTÔMAGO: Não avaliado.';
    }

    $frases = [];

    // 1. Topografia + replecao.
    $repl = [
        'gas_e_ingesta' => 'repleto por gás e conteúdo pastoso particulado sugestivo de ingesta',
        'gas'           => 'repleto por gás',
        'vazio'         => 'vazio',
    ][$e['conteudo'] ?? 'gas_e_ingesta'] ?? 'repleto por gás e conteúdo pastoso particulado sugestivo de ingesta';
    $frases[] = "Topografia usual, {$repl}.";

    // 2. Paredes + faixa de espessura + estratificacao.
    $parede = (($e['parede'] ?? 'normoespessa') === 'espessada') ? 'espessas em algumas porções' : 'normoespessas';
    $s2 = "Paredes {$parede}";
    $faixa = faixaCm($e['espessura_min_cm'] ?? null, $e['espessura_max_cm'] ?? null);
    if ($faixa !== '') {
        $s2 .= ', medindo aproximadamente ' . $faixa;
    }
    $estratificacao = (($e['estratificacao_mantida'] ?? true) === true) ? 'manutenção' : 'perda';
    $s2 .= ", com {$estratificacao} da estratificação parietal de camadas.";
    $frases[] = $s2;

    // 3. Observacoes livres.
    $obs = trim((string) ($e['observacoes'] ?? ''));
    if ($obs !== '') { $frases[] = $obs; }

    return 'ESTÔMAGO: ' . implode(' ', $frases);
}
