<?php

declare(strict_types=1);

/**
 * Endpoint HTTP do gerador de laudos.
 *
 * Fino de proposito: no GET serve o formulario (app.html); no POST le o corpo
 * bruto e delega para tratarRequisicaoLaudo() (src/api/handler.php), emitindo a
 * resposta. Toda a logica (validacao, montagem do laudo, geracao de PDF) mora no
 * handler, que e testavel sem servidor.
 *
 * Rodar local: php -S localhost:8080 -t public
 */

require __DIR__ . '/../src/api/handler.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// GET serve o formulario; ?health devolve o JSON de servico; ?checklists e a config.
if ($metodo === 'GET' && !isset($_GET['health']) && !isset($_GET['checklists'])) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/app.html');
    return;
}

$corpo = file_get_contents('php://input');
if ($corpo === false) {
    $corpo = '';
}

// Recurso de checklists personalizados (GET le / POST grava).
if (isset($_GET['checklists'])) {
    $resposta = tratarRequisicaoChecklists($metodo, $corpo);
} else {
    $resposta = tratarRequisicaoLaudo($metodo, $corpo);
}

http_response_code($resposta['status']);
foreach ($resposta['headers'] as $chave => $valor) {
    header("{$chave}: {$valor}");
}
echo $resposta['body'];
