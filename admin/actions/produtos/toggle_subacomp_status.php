<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $id_subacomp = $_POST['id_subacomp'] ?? null;
    
    if (!$id_subacomp) {
        throw new Exception('ID do subacompanhamento não fornecido');
    }

    // Busca o status atual para inverter
    $stmt = $pdo->prepare("SELECT activated FROM sub_acomp WHERE id_subacomp = ?");
    $stmt->execute([$id_subacomp]);
    $atual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$atual) {
        throw new Exception('Subacompanhamento não encontrado');
    }

    // Inverte o status (1 -> 0 ou 0 -> 1)
    $novo_status = $atual['activated'] ? 0 : 1;

    // Atualiza o status
    $stmt = $pdo->prepare("UPDATE sub_acomp SET activated = ? WHERE id_subacomp = ?");
    $resultado = $stmt->execute([$novo_status, $id_subacomp]);

    if ($resultado) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Status atualizado com sucesso',
            'novo_status' => $novo_status
        ]);
    } else {
        throw new Exception('Erro ao atualizar status');
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 