<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id_acomp, nome_acomp FROM acomp ORDER BY nome_acomp");
    $acompanhamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'acompanhamentos' => $acompanhamentos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 