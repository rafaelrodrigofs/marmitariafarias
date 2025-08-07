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

$id_categoria = $_POST['id_categoria'] ?? '';

if (empty($id_categoria)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID da categoria é obrigatório'
    ]);
    exit;
}

try {
    // Primeiro verifica se existem produtos usando esta categoria
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produto WHERE fk_categoria_id = ?");
    $stmt->execute([$id_categoria]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Não é possível excluir esta categoria pois existem produtos vinculados a ela'
        ]);
        exit;
    }

    // Se não houver produtos, pode excluir a categoria
    $stmt = $pdo->prepare("DELETE FROM categoria WHERE id_categoria = ?");
    $stmt->execute([$id_categoria]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Categoria excluída com sucesso'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Categoria não encontrada'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao excluir categoria: ' . $e->getMessage()
    ]);
} 