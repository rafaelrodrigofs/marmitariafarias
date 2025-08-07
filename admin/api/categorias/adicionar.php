<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido'
    ]);
    exit;
}

$nome_categoria = trim($_POST['nome_categoria'] ?? '');

if (empty($nome_categoria)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nome da categoria é obrigatório'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO categoria (nome_categoria) VALUES (?)");
    $stmt->execute([$nome_categoria]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Categoria adicionada com sucesso',
        'id' => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao adicionar categoria: ' . $e->getMessage()
    ]);
} 