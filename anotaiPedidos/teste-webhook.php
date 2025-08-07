<?php
header('Content-Type: application/json');

// Log para debug
$log = date('Y-m-d H:i:s') . " - Teste de acesso ao webhook\n";
file_put_contents('debug.log', $log, FILE_APPEND);

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = file_get_contents('php://input');
    $log = date('Y-m-d H:i:s') . " - Dados recebidos: " . $data . "\n";
    file_put_contents('debug.log', $log, FILE_APPEND);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook funcionando',
        'received_data' => json_decode($data, true)
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido',
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
} 