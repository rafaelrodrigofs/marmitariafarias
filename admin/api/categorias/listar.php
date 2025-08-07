<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria");
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $categorias
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao listar categorias: ' . $e->getMessage()
    ]);
} 