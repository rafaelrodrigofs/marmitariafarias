<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $id_subacomp = $_POST['id_subacomp'] ?? null;
    
    if (!$id_subacomp) {
        throw new Exception('ID do subacompanhamento não fornecido');
    }

    // Primeiro, pegamos o acomp_id para atualizar a lista depois
    $stmt = $pdo->prepare("SELECT fk_acomp_id FROM sub_acomp WHERE id_subacomp = ?");
    $stmt->execute([$id_subacomp]);
    $acomp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$acomp) {
        throw new Exception('Subacompanhamento não encontrado');
    }

    // Deleta o subacompanhamento
    $stmt = $pdo->prepare("DELETE FROM sub_acomp WHERE id_subacomp = ?");
    $resultado = $stmt->execute([$id_subacomp]);

    if ($resultado) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Subacompanhamento excluído com sucesso',
            'acomp_id' => $acomp['fk_acomp_id']
        ]);
    } else {
        throw new Exception('Erro ao excluir subacompanhamento');
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 