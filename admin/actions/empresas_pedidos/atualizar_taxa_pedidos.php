<?php
header('Content-Type: application/json');
session_start();
include_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidos = $_POST['pedidos'] ?? [];
    $valor_taxa = floatval($_POST['valor_taxa'] ?? 0);

    if (empty($pedidos) || $valor_taxa <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Debug - vamos logar os valores recebidos
        error_log('Pedidos recebidos: ' . print_r($pedidos, true));
        error_log('Valor da taxa: ' . $valor_taxa);

        $sql = "UPDATE pedidos SET taxa_entrega = :taxa WHERE id_pedido = :id";
        $stmt = $pdo->prepare($sql);

        foreach ($pedidos as $pedido_id) {
            $pedido_id = intval($pedido_id);
            error_log('Atualizando pedido: ' . $pedido_id . ' com taxa: ' . $valor_taxa);
            
            $result = $stmt->execute([
                ':taxa' => $valor_taxa,
                ':id' => $pedido_id
            ]);

            if (!$result) {
                error_log('Erro ao atualizar pedido ' . $pedido_id . ': ' . print_r($stmt->errorInfo(), true));
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Taxa atualizada com sucesso']);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Erro ao atualizar taxa: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar taxa: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
exit; 