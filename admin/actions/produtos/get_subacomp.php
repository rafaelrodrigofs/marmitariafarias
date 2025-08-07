<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $acomp_id = $_POST['acomp_id'] ?? null;

    if (!$acomp_id) {
        throw new Exception('ID do acompanhamento não fornecido');
    }

    $stmt = $pdo->prepare("
        SELECT id_subacomp as id, nome_subacomp as nome, 
               preco_subacomp as preco, activated
        FROM sub_acomp 
        WHERE fk_acomp_id = ?
        ORDER BY nome_subacomp ASC
    ");
    
    $stmt->execute([$acomp_id]);
    $subacompanhamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'subacompanhamentos' => $subacompanhamentos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 