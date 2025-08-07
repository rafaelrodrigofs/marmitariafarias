<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $id_produto = $_POST['id_produto'] ?? null;
    
    if (!$id_produto) {
        throw new Exception('ID do produto não fornecido');
    }

    // Busca o status atual
    $stmt = $pdo->prepare("SELECT activated FROM produto WHERE id_produto = ?");
    $stmt->execute([$id_produto]);
    $status_atual = (int)$stmt->fetchColumn();
    
    // Inverte o status (1 vira 0, 0 vira 1)
    $novo_status = $status_atual ? 0 : 1;
    
    // Atualiza com o novo status
    $stmt = $pdo->prepare("UPDATE produto SET activated = ? WHERE id_produto = ?");
    $stmt->execute([$novo_status, $id_produto]);

    echo json_encode([
        'status' => 'success',
        'activated' => $novo_status
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 