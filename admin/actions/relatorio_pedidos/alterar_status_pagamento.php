<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['pedido_id']) || !isset($data['novo_status'])) {
        throw new Exception('Dados inválidos');
    }

    $sql = "UPDATE pedidos SET status_pagamento = ? WHERE id_pedido = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$data['novo_status'], $data['pedido_id']]);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Status atualizado com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar status');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 