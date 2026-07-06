<?php

declare(strict_types=1);

/**
 * Compositor de frases do orgao Vesicula Biliar.
 *
 * Recebe o objeto `vesicula_biliar` do payload JSON
 * (docs/referencia/laudo.schema.json) e devolve o paragrafo final, iniciando por
 * "VESÍCULA BILIAR: ".
 *
 * Estrategia (decisao "Hibrido"): prosa canonica do modelo
 * (docs/referencia/modelo-laudo-aline.txt). Frases:
 *   1. parede + espessura;
 *   2. conteudo anecogenico, com bloco opcional de lama biliar;
 *   3. ausencia de litiase/obstrucao.
 *
 * Nota de fidelidade: a Clarinha descreve o sedimento como "hiperecogênico
 * aglomerado" e omite o rotulo "(lama biliar)"; usamos o canonico do modelo
 * ("sedimento ecogênico ... (lama biliar)"). O descritor especifico do sedimento
 * nao e um grau, entao fica fora do escopo aditivo Hibrido (iria por observacoes).
 */

require_once __DIR__ . '/_helpers.php';

/**
 * Compoe o paragrafo da Vesicula Biliar.
 *
 * @param array<string,mixed> $v Objeto `vesicula_biliar` do payload.
 * @return string Paragrafo pronto, iniciando por "VESÍCULA BILIAR: ".
 */
function composeVesiculaBiliar(array $v): string
{
    if (($v['avaliado'] ?? true) === false) {
        return 'VESÍCULA BILIAR: Não avaliada.';
    }

    $frases = [];

    // 1. Parede + espessura.
    $parede = (($v['parede_normoespessa'] ?? true) === true) ? 'normoespessas' : 'espessadas';
    $s1 = "Repleta, com paredes {$parede}";
    $esp = $v['espessura_cm'] ?? null;
    if ($esp !== null) {
        $s1 .= ', medindo aproximadamente ' . formatarCm((float) $esp) . ' cm';
    }
    $frases[] = $s1 . '.';

    // 2. Conteudo (+ bloco opcional de lama biliar).
    $anecogenico = (($v['conteudo_anecogenico'] ?? true) === true) ? 'anecogênico' : 'ecogênico';
    $lama = (array) ($v['lama_biliar'] ?? []);
    if (($lama['presente'] ?? false) === true) {
        $quantidade = grauAdjetivo($lama['quantidade'] ?? 'discreta', 'f', 's');
        $disposicao = (($lama['disposicao'] ?? 'suspensao') === 'depositado')
            ? 'depositado em porção dependente'
            : 'em suspensão';
        $frases[] = "Conteúdo {$anecogenico} acompanhado de {$quantidade} quantidade de sedimento ecogênico "
            . "{$disposicao} e não formador de sombreamento acústico posterior (lama biliar).";
    } else {
        $frases[] = "Conteúdo {$anecogenico} e homogêneo.";
    }

    // 3. Ausencia de litiase/obstrucao.
    if (($v['litiase_ausente'] ?? true) === true) {
        $frases[] = 'Ausência de imagens sugestivas de litíase ou processo obstrutivo de vias biliares.';
    }

    // 4. Observacoes livres.
    $obs = trim((string) ($v['observacoes'] ?? ''));
    if ($obs !== '') { $frases[] = $obs; }

    return 'VESÍCULA BILIAR: ' . implode(' ', $frases);
}
