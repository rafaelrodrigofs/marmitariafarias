<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit;
}

require_once '../../config/database.php';

try {
    $id_despesa = $_POST['id_despesa'] ?? null;

    if (!$id_despesa) {
        throw new Exception('ID da despesa não fornecido');
    }

    $sql = "DELETE FROM despesas WHERE id_despesa = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_despesa]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Despesa excluída com sucesso']);
    } else {
        throw new Exception('Despesa não encontrada');
    }

} catch (Exception $e) {
    error_log('Erro ao excluir despesa: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao excluir despesa: ' . $e->getMessage()
    ]);
} 