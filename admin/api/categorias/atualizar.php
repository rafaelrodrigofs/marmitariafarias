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
$nome_categoria = trim($_POST['nome_categoria'] ?? '');

if (empty($id_categoria) || empty($nome_categoria)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID e nome da categoria são obrigatórios'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE categoria SET nome_categoria = ? WHERE id_categoria = ?");
    $stmt->execute([$nome_categoria, $id_categoria]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Categoria atualizada com sucesso'
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
        'message' => 'Erro ao atualizar categoria: ' . $e->getMessage()
    ]);
} 