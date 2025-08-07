<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$log_file = __DIR__ . '/webhook_log.txt';

if (!file_exists($log_file)) {
    echo json_encode(['pedidos' => []]);
    exit;
}

$logs = file_get_contents($log_file);
$linhas = explode("\n", $logs);
$pedidos = [];

foreach ($linhas as $linha) {
    if (
        strpos($linha, 'Pedido recebido') !== false ||
        strpos($linha, 'Novo pedido recebido') !== false ||
        strpos($linha, 'Pedido confirmado') !== false ||
        strpos($linha, 'Pedido em preparação') !== false ||
        strpos($linha, 'Pedido pronto') !== false ||
        strpos($linha, 'Pedido entregue') !== false ||
        strpos($linha, 'Pedido cancelado') !== false ||
        strpos($linha, 'Status desconhecido recebido') !== false
    ) {
        try {
            $dados = json_decode(substr($linha, strpos($linha, 'Dados: ') + 7), true);
            if ($dados) {
                $pedidos[] = $dados;
            }
        } catch (Exception $e) {
            // Ignora linhas com erro
        }
    }
}

echo json_encode(['pedidos' => $pedidos]); 