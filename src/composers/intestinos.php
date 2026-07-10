<?php

declare(strict_types=1);

/**
 * Compositor de frases do orgao Intestinos.
 *
 * Recebe o objeto `intestinos` do payload JSON (docs/referencia/laudo.schema.json)
 * e devolve o paragrafo final, iniciando por "INTESTINOS: ".
 *
 * Estrategia (decisao "Hibrido"): prosa canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt), na forma colapsada usada no laudo real
 * (faixas por segmento inline). O campo `padrao` escolhe os segmentos avaliados:
 * 'cao' (duodeno/jejuno/cólon) ou 'gato' (duodeno/jejuno/íleo + cólon
 * ascendente/transverso/descendente). Frases:
 *   1. paredes (estratificacao + espessura) + faixas por segmento;
 *   2. replecao do delgado;
 *   3. peristaltismo;
 *   4. replecao do colon;
 *   5. ausencia de obstrucao.
 * O caso da Clarinha bate verbatim.
 */

require_once __DIR__ . '/_helpers.php';

/**
 * Compoe o paragrafo dos Intestinos.
 *
 * @param array<string,mixed> $i Objeto `intestinos` do payload.
 * @return string Paragrafo pronto, iniciando por "INTESTINOS: ".
 */
function composeIntestinos(array $i): string
{
    if (($i['avaliado'] ?? true) === false) {
        return 'INTESTINOS: Não avaliados.';
    }

    $frases = [];

    // 1. Paredes (estratificacao + espessura) + faixas por segmento.
    $estratificacao = (($i['estratificacao_mantida'] ?? true) === true) ? 'manutenção' : 'perda';
    $parede = (($i['parede'] ?? 'normoespessa') === 'espessada') ? 'espessas em algumas porções' : 'normoespessas';
    $s1 = "Paredes com {$estratificacao} da estrutura laminar de camadas e {$parede}";

    $m = (array) ($i['medidas'] ?? []);
    $segmentos = [];

    // Adiciona um segmento a lista se ao menos uma das medidas (min/max) foi aferida.
    $addSegmento = static function (string $rotulo, string $chaveMin, string $chaveMax) use (&$segmentos, $m): void {
        $faixa = faixaCm($m[$chaveMin] ?? null, $m[$chaveMax] ?? null);
        if ($faixa !== '') { $segmentos[] = "{$faixa} em {$rotulo}"; }
    };

    // Segmentos avaliados por padrao anatomico: o cao usa cólon unico; o gato
    // detalha íleo e as tres porcoes do cólon (modelo-laudo-aline.txt).
    if (($i['padrao'] ?? 'cao') === 'gato') {
        $addSegmento('duodeno', 'duodeno_min_cm', 'duodeno_max_cm');
        $addSegmento('jejuno', 'jejuno_min_cm', 'jejuno_max_cm');
        $addSegmento('íleo', 'ileo_min_cm', 'ileo_max_cm');
        $addSegmento('cólon ascendente', 'colon_ascendente_min_cm', 'colon_ascendente_max_cm');
        $addSegmento('cólon transverso', 'colon_transverso_min_cm', 'colon_transverso_max_cm');
        $addSegmento('cólon descendente', 'colon_descendente_min_cm', 'colon_descendente_max_cm');
    } else {
        $addSegmento('duodeno', 'duodeno_min_cm', 'duodeno_max_cm');
        $addSegmento('jejuno', 'jejuno_min_cm', 'jejuno_max_cm');
        $addSegmento('cólon', 'colon_min_cm', 'colon_max_cm');
    }

    if ($segmentos) {
        $s1 .= ', medindo aproximadamente ' . listaPtBr($segmentos);
    }
    $frases[] = $s1 . '.';

    // 2. Replecao do delgado.
    $frases[] = 'Intestino delgado repleto por discreta quantidade de gás e conteúdo pastoso.';

    // 3. Peristaltismo.
    $frases[] = (($i['peristaltismo_preservado'] ?? true) === true)
        ? 'Movimentos peristálticos preservados.'
        : 'Movimentos peristálticos reduzidos.';

    // 4. Replecao do colon.
    $frases[] = 'Cólon repleto por gás e conteúdo ecogênico formador de sombreamento acústico posterior (fezes).';

    // 5. Ausencia de obstrucao.
    if (($i['obstrucao_ausente'] ?? true) === true) {
        $frases[] = 'Ausência de imagens sugestivas de processo obstrutivo ou corpo estranho intestinal.';
    }

    // 6. Observacoes livres.
    $obs = trim((string) ($i['observacoes'] ?? ''));
    if ($obs !== '') { $frases[] = $obs; }

    return 'INTESTINOS: ' . implode(' ', $frases);
}
