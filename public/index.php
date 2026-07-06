<?php

declare(strict_types=1);

/**
 * Endpoint HTTP do gerador de laudos.
 *
 * Fino de proposito: le o metodo e o corpo bruto, delega para
 * tratarRequisicaoLaudo() (src/api/handler.php) e emite a resposta. Toda a logica
 * (validacao, montagem do laudo, geracao de PDF) mora no handler, que e testavel
 * sem servidor.
 *
 * Rodar local: php -S localhost:8080 -t public
 */

require __DIR__ . '/../src/api/handler.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$corpo = file_get_contents('php://input');
if ($corpo === false) {
    $corpo = '';
}

$resposta = tratarRequisicaoLaudo($metodo, $corpo);

http_response_code($resposta['status']);
foreach ($resposta['headers'] as $chave => $valor) {
    header("{$chave}: {$valor}");
}
echo $resposta['body'];
