<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit();
}

include_once '../../config/database.php';

// Verifica se os dados necessários foram enviados
if (!isset($_POST['id_despesa']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Dados incompletos']);
    exit();
}

$id_despesa = intval($_POST['id_despesa']);
$status = intval($_POST['status']);

// Valida o status (só pode ser 0 ou 1)
if ($status !== 0 && $status !== 1) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Status inválido']);
    exit();
}

try {
    $sql = "UPDATE despesas SET status_pagamento = ? WHERE id_despesa = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $id_despesa]);

    if ($stmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Status atualizado com sucesso'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Despesa não encontrada ou nenhuma alteração necessária'
        ]);
    }
} catch (PDOException $e) {
    error_log('Erro ao atualizar status da despesa: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao atualizar status da despesa'
    ]);
} 