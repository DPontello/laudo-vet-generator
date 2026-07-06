<?php

declare(strict_types=1);

/**
 * Compositor de frases do orgao Pancreas.
 *
 * Recebe o objeto `pancreas` do payload JSON (docs/referencia/laudo.schema.json) e
 * devolve o paragrafo final, iniciando por "PÂNCREAS: ".
 *
 * Estrategia (decisao "Hibrido"): prosa canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt). Dois ramos por visibilizacao:
 *   - nao_visibilizado -> "Não visibilizado. Ausência de reatividade tecidual em
 *     sua topografia." (normal padrao do pancreas);
 *   - parcialmente/totalmente visibilizado -> espessura + lobo + ecogenicidade +
 *     reatividade adjacente.
 * O caso da Clarinha bate verbatim.
 */

require_once __DIR__ . '/_helpers.php';

/**
 * Compoe o paragrafo do Pancreas.
 *
 * @param array<string,mixed> $p Objeto `pancreas` do payload.
 * @return string Paragrafo pronto, iniciando por "PÂNCREAS: ".
 */
function composePancreas(array $p): string
{
    if (($p['avaliado'] ?? true) === false) {
        return 'PÂNCREAS: Não avaliado.';
    }

    $frases = [];
    $vis = $p['visibilizacao'] ?? 'nao_visibilizado';

    if ($vis === 'nao_visibilizado') {
        $frases[] = 'Não visibilizado.';
        $frases[] = 'Ausência de reatividade tecidual em sua topografia.';
    } else {
        $palavra = ($vis === 'visibilizado') ? 'Visibilizado' : 'Parcialmente visibilizado';
        $lobo = (($p['lobo'] ?? 'direito') === 'esquerdo') ? 'esquerdo' : 'direito';
        $esp = $p['espessura_cm'] ?? null;
        if ($esp !== null) {
            $frases[] = "{$palavra}, com espessura de aproximadamente " . formatarCm((float) $esp)
                . " cm em porção de lobo {$lobo}.";
        } else {
            $frases[] = "{$palavra}, em porção de lobo {$lobo}.";
        }

        // Ecogenicidade (alteracoes vao por observacoes).
        if (($p['ecogenicidade_usual'] ?? true) === true) {
            $frases[] = 'Ecogenicidade usual e ecotextura homogênea, com contornos preservados.';
        }

        $frases[] = (($p['reatividade_adjacente'] ?? false) === true)
            ? 'Presença de reatividade tecidual adjacente.'
            : 'Ausência de reatividade tecidual adjacente.';
    }

    // Observacoes livres.
    $obs = trim((string) ($p['observacoes'] ?? ''));
    if ($obs !== '') { $frases[] = $obs; }

    return 'PÂNCREAS: ' . implode(' ', $frases);
}
