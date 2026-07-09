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
 * (faixas por segmento duodeno/jejuno/cólon inline). Frases:
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
    $duodeno = faixaCm($m['duodeno_min_cm'] ?? null, $m['duodeno_max_cm'] ?? null);
    if ($duodeno !== '') { $segmentos[] = "{$duodeno} em duodeno"; }
    $jejuno = faixaCm($m['jejuno_min_cm'] ?? null, $m['jejuno_max_cm'] ?? null);
    if ($jejuno !== '') { $segmentos[] = "{$jejuno} em jejuno"; }
    $colon = faixaCm($m['colon_min_cm'] ?? null, $m['colon_max_cm'] ?? null);
    if ($colon !== '') { $segmentos[] = "{$colon} em cólon"; }

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
