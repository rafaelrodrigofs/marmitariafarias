<?php
session_start();

// Recebe os dados JSON
$dados = json_decode(file_get_contents('php://input'), true);

if (isset($dados['data'])) {
    $_SESSION['data_pedidos'] = $dados['data'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Data não fornecida']);
} 