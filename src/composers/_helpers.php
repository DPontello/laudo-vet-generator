<?php

declare(strict_types=1);

/**
 * Helpers compartilhados entre os compositores de orgao.
 *
 * Fonte unica de formatacao de medidas e de concordancia de grau. Carregado via
 * require_once por cada compositor; as definicoes sao guardadas por function_exists
 * para tolerar qualquer ordem de inclusao (ex.: o agregador do laudo completo puxa
 * varios compositores no mesmo processo).
 */

if (!function_exists('formatarCm')) {
    /** Converte medida em cm para o formato pt-BR (0.11 -> "0,11"). */
    function formatarCm(float $valor): string
    {
        return number_format($valor, 2, ',', '');
    }
}

if (!function_exists('grauAdverbio')) {
    /** Grau (discreta|moderada|acentuada) -> adverbio de intensidade. */
    function grauAdverbio(string $grau): string
    {
        return [
            'discreta'  => 'Discretamente',
            'moderada'  => 'Moderadamente',
            'acentuada' => 'Acentuadamente',
        ][$grau] ?? 'Moderadamente';
    }
}

if (!function_exists('grauAdjetivo')) {
    /**
     * Grau como adjetivo, com concordancia de genero/numero.
     *
     * @param string $grau   discreta|moderada|acentuada
     * @param string $genero 'f' (feminino) ou 'm' (masculino)
     * @param string $numero 's' (singular) ou 'p' (plural)
     * @return string ex.: ('discreta','m','p') -> "discretos"; ('acentuada','f','s') -> "acentuada"
     */
    function grauAdjetivo(string $grau, string $genero = 'f', string $numero = 's'): string
    {
        $raiz = ['discreta' => 'discret', 'moderada' => 'moderad', 'acentuada' => 'acentuad'][$grau] ?? 'moderad';
        $sufixo = ($genero === 'm' ? 'o' : 'a') . ($numero === 'p' ? 's' : '');
        return $raiz . $sufixo;
    }
}

if (!function_exists('capitalizar')) {
    /** Primeira letra maiuscula (ASCII; suficiente para graus discreta/moderada/acentuada). */
    function capitalizar(string $texto): string
    {
        return ucfirst($texto);
    }
}

if (!function_exists('faixaCm')) {
    /**
     * Faixa de medida "X cm a Y cm" (pt-BR). Se so um lado for aferido, devolve
     * "X cm"; se ambos forem null, devolve string vazia (a faixa some da frase).
     * Usada por Estomago e Intestinos (min-max por segmento).
     */
    function faixaCm($min, $max): string
    {
        if ($min !== null && $max !== null) {
            return formatarCm((float) $min) . ' cm a ' . formatarCm((float) $max) . ' cm';
        }
        if ($min !== null) { return formatarCm((float) $min) . ' cm'; }
        if ($max !== null) { return formatarCm((float) $max) . ' cm'; }
        return '';
    }
}

if (!function_exists('listaPtBr')) {
    /**
     * Junta itens numa enumeracao pt-BR:
     *   [] -> ''; [a] -> "a"; [a,b] -> "a e b"; [a,b,c] -> "a, b e c".
     * O chamador deve filtrar itens vazios antes. Fonte unica desse join, antes
     * reimplementado em adrenais/cavidade-abdominal/intestinos/reprodutor.
     *
     * @param array<int,string> $itens
     */
    function listaPtBr(array $itens): string
    {
        if (!$itens) { return ''; }
        $ultimo = array_pop($itens);
        return $itens ? implode(', ', $itens) . ' e ' . $ultimo : $ultimo;
    }
}

if (!function_exists('ecotexturaTexto')) {
    /** Ecotextura (enum de 2 valores) -> pt-BR: 'heterogenea' -> "heterogênea"; senao "homogênea". */
    function ecotexturaTexto(string $valor): string
    {
        return $valor === 'heterogenea' ? 'heterogênea' : 'homogênea';
    }
}
