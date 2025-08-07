<?php
header('Content-Type: application/json');

// Função vazia para manter compatibilidade com o código existente
function writeLog($message) {
    // Função mantida apenas para compatibilidade, sem implementação de logs
}

require_once '../../config/database.php';

try {
    writeLog("Iniciando processo de exclusão");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido: ' . $_SERVER['REQUEST_METHOD']);
    }

    $input = file_get_contents('php://input');
    writeLog("Input recebido: " . $input);
    
    $ids = json_decode($input, true);
    writeLog("IDs decodificados: " . print_r($ids, true));

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    if (empty($ids) || !is_array($ids)) {
        throw new Exception('Nenhum pedido selecionado ou formato inválido');
    }

    writeLog("Iniciando transação");
    $pdo->beginTransaction();

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    // Debug das queries
    writeLog("Placeholders gerados: " . $placeholders);
    
    // Excluir acompanhamentos
    $sql_delete_acomp = "DELETE FROM pedido_item_acomp WHERE fk_pedido_item_id IN 
                        (SELECT id_pedido_item FROM pedido_itens WHERE fk_pedido_id IN ($placeholders))";
    writeLog("Query acompanhamentos: " . $sql_delete_acomp);
    $stmt = $pdo->prepare($sql_delete_acomp);
    $stmt->execute($ids);
    writeLog("Acompanhamentos excluídos");
    
    // Excluir itens do pedido
    $sql_delete_itens = "DELETE FROM pedido_itens WHERE fk_pedido_id IN ($placeholders)";
    writeLog("Query itens: " . $sql_delete_itens);
    $stmt = $pdo->prepare($sql_delete_itens);
    $stmt->execute($ids);
    writeLog("Itens excluídos");
    
    // Finalmente excluir os pedidos
    $sql_delete_pedidos = "DELETE FROM pedidos WHERE id_pedido IN ($placeholders)";
    writeLog("Query pedidos: " . $sql_delete_pedidos);
    $stmt = $pdo->prepare($sql_delete_pedidos);
    $stmt->execute($ids);
    writeLog("Pedidos excluídos");
    
    $pdo->commit();
    writeLog("Transação commitada com sucesso");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Pedidos excluídos com sucesso',
        'count' => count($ids)
    ]);

} catch (Exception $e) {
    writeLog("ERRO: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        writeLog("Transação revertida");
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao excluir pedidos: ' . $e->getMessage(),
        'error' => true,
        'debug' => [
            'input' => $input ?? null,
            'ids' => $ids ?? null,
            'error_trace' => $e->getTraceAsString()
        ]
    ]);
}

writeLog("Processo finalizado\n-------------------\n");
?> 