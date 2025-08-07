<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $cliente_id = $_POST['cliente_id'];
    $status = $_POST['status'];
    $mes = $_POST['mes'];

    // Pegar o primeiro e último dia do mês
    $primeiro_dia = date('Y-m-01', strtotime($mes));
    $ultimo_dia = date('Y-m-t', strtotime($mes));

    // Atualiza todos os pedidos do cliente no período
    $sql = "UPDATE pedidos p 
            JOIN clientes c ON p.fk_cliente_id = c.id_cliente 
            SET p.status_pagamento = ? 
            WHERE c.id_cliente = ? 
            AND DATE(p.data_pedido) BETWEEN ? AND ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $cliente_id, $primeiro_dia, $ultimo_dia]);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 