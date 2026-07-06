<?php

declare(strict_types=1);

/**
 * Compositor de frases do orgao Bexiga.
 *
 * Recebe o objeto `bexiga` do payload JSON (ver docs/referencia/laudo.schema.json)
 * e devolve o paragrafo final do laudo, ja com a redacao padrao da medica.
 *
 * Principio (CLAUDE.md secao 10): o frontend so envia selecoes + medidas; toda a
 * prosa mora aqui. Trocar a redacao do laudo = editar estes fragmentos, sem tocar
 * no schema nem no formulario.
 */

/** Converte medida em cm para o formato pt-BR (0.11 -> "0,11"). */
function formatarCm(float $valor): string
{
    return number_format($valor, 2, ',', '');
}

/** Grau (discreta|moderada|acentuada) -> adverbio de intensidade. */
function grauAdverbio(string $grau): string
{
    return [
        'discreta'  => 'Discretamente',
        'moderada'  => 'Moderadamente',
        'acentuada' => 'Acentuadamente',
    ][$grau] ?? 'Moderadamente';
}

/**
 * Compoe o paragrafo da Bexiga.
 *
 * @param array<string,mixed> $b Objeto `bexiga` do payload.
 * @return string Paragrafo pronto, iniciando por "BEXIGA: ".
 */
function composeBexiga(array $b): string
{
    // Orgao nao avaliado: sai cedo.
    if (($b['avaliado'] ?? true) === false) {
        return 'BEXIGA: Não avaliada.';
    }

    $frases = [];

    // 1. Replecao + topografia.
    $adverbio = grauAdverbio($b['replecao'] ?? 'moderada');
    $frases[] = "{$adverbio} repleta, com topografia e formato usuais.";

    // 2. Margem e parede (com medida opcional).
    $aspecto = ($b['parede']['aspecto'] ?? 'normoespessa');
    $parede = "Margem interna lisa e parede {$aspecto}";
    $espessura = $b['parede']['espessura_cm'] ?? null;
    if ($espessura !== null) {
        $parede .= ', medindo aproximadamente ' . formatarCm((float) $espessura) . ' cm';
    }
    $frases[] = $parede . '.';

    // 3. Conteudo (base anecogenico + bloco opcional de sedimento).
    $sedimento = $b['conteudo']['sedimento'] ?? [];
    if (($sedimento['presente'] ?? false) === true) {
        $quantidade = $sedimento['quantidade'] ?? 'discreta';
        $descricaoTipo = ($sedimento['tipo'] ?? 'sedimentos_urinarios') === 'microurolitos'
            ? 'hiperecogênicas aglomeradas, formadoras de sombreamento acústico posterior'
            : 'ecogênicas em flutuação, não formadoras de artefato';
        $frases[] = "Conteúdo anecogênico acompanhado de {$quantidade} quantidade de partículas {$descricaoTipo}.";
    } else {
        $sufixo = ($b['conteudo']['homogeneo'] ?? true) ? ' homogêneo' : '';
        $frases[] = "Conteúdo anecogênico{$sufixo}.";
    }

    // 4. Litiase: cistolito descrito OU ausencia (normal).
    if (($b['litiase']['cistolito_presente'] ?? false) === true) {
        $medida = $b['litiase']['cistolito_cm'] ?? null;
        $trecho = 'Presença de estrutura de interface hiperecogênica depositada em porção dependente, '
            . 'de superfície curva e regular, formadora de sombreamento acústico posterior';
        if ($medida !== null) {
            $trecho .= ', medindo aproximadamente ' . formatarCm((float) $medida) . ' cm';
        }
        $frases[] = $trecho . ' (cistólito).';
    } else {
        $frases[] = 'Ausência de imagens sugestivas de litíases.';
    }

    // 5. Observacoes livres (excecoes fora das opcoes).
    $obs = trim((string) ($b['observacoes'] ?? ''));
    if ($obs !== '') {
        $frases[] = $obs;
    }

    return 'BEXIGA: ' . implode(' ', $frases);
}
