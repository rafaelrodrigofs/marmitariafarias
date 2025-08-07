<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $pedido_id = $_POST['pedido_id'];
    $status = $_POST['status'];

    $sql = "UPDATE pedidos SET status_pagamento = ? WHERE id_pedido = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $pedido_id]);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 