<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['id_produto']) || empty($_POST['id_produto'])) {
        throw new Exception('ID do produto não fornecido');
    }

    $id_produto = filter_var($_POST['id_produto'], FILTER_VALIDATE_INT);
    if ($id_produto === false) {
        throw new Exception('ID do produto inválido');
    }

    // Verifica se o produto existe
    $stmt = $pdo->prepare("SELECT id_produto FROM produto WHERE id_produto = ?");
    $stmt->execute([$id_produto]);
    if (!$stmt->fetch()) {
        throw new Exception('Produto não encontrado');
    }

    // Verifica se o produto tem pedidos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM pedido_itens 
        WHERE fk_produto_id = ?
    ");
    $stmt->execute([$id_produto]);
    $result = $stmt->fetch();

    if ($result['total'] > 0) {
        throw new Exception('Não é possível excluir um produto que possui pedidos');
    }

    // Inicia a transação
    $pdo->beginTransaction();

    try {
        // Remove regras de acompanhamento
        // Primeiro, precisamos pegar os IDs dos acompanhamentos associados ao produto
        $stmt = $pdo->prepare("
            SELECT fk_acomp_id 
            FROM produto_acomp 
            WHERE fk_produto_id = ?
        ");
        $stmt->execute([$id_produto]);
        $acompIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Se houver acompanhamentos, remove as regras
        if (!empty($acompIds)) {
            $placeholders = str_repeat('?,', count($acompIds) - 1) . '?';
            $stmt = $pdo->prepare("
                DELETE FROM produto_acomp_regras 
                WHERE fk_acomp_id IN ($placeholders)
            ");
            $stmt->execute($acompIds);
        }

        // Remove associações com acompanhamentos
        $stmt = $pdo->prepare("DELETE FROM produto_acomp WHERE fk_produto_id = ?");
        $stmt->execute([$id_produto]);

        // Finalmente remove o produto
        $stmt = $pdo->prepare("DELETE FROM produto WHERE id_produto = ?");
        $stmt->execute([$id_produto]);

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Produto excluído com sucesso'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Erro ao excluir produto: ' . $e->getMessage());
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 