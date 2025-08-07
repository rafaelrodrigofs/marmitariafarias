<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    error_log("=== INICIANDO EXCLUSÃO DE PEDIDO ===");
    
    $data = json_decode(file_get_contents('php://input'), true);
    error_log("Dados recebidos: " . print_r($data, true));
    
    if (!isset($data['numero_pedido']) || !isset($data['telefone_cliente']) || !isset($data['data_pedido'])) {
        throw new Exception('Dados incompletos para exclusão do pedido');
    }

    // Limpa o telefone
    $telefone = preg_replace('/[^0-9]/', '', $data['telefone_cliente']);
    error_log("Telefone limpo: " . $telefone);
    
    // Primeiro verifica se o pedido existe
    $stmtVerifica = $pdo->prepare("
        SELECT 
            p.id_pedido, 
            p.num_pedido, 
            p.data_pedido,
            p.hora_pedido,
            p.fk_cliente_id,
            c.telefone_cliente,
            c.id_cliente
        FROM pedidos p
        LEFT JOIN clientes c ON p.fk_cliente_id = c.id_cliente
        WHERE p.num_pedido = ? 
        AND DATE(p.data_pedido) = ?
    ");
    $stmtVerifica->execute([$data['numero_pedido'], $data['data_pedido']]);
    $pedido = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    
    error_log("Query de verificação: " . print_r([
        'num_pedido' => $data['numero_pedido'],
        'data_pedido' => $data['data_pedido'],
        'telefone_informado' => $telefone,
        'resultado' => $pedido
    ], true));
    
    // Se encontrou o pedido mas o telefone não bate, vamos logar isso
    if ($pedido) {
        error_log("Pedido encontrado mas pode haver divergência no telefone:");
        error_log("Telefone informado: " . $telefone);
        error_log("Telefone no banco: " . ($pedido['telefone_cliente'] ?? 'não encontrado'));
        error_log("Cliente ID no banco: " . ($pedido['fk_cliente_id'] ?? 'não encontrado'));
    }
    
    if (!$pedido) {
        throw new Exception("Pedido não encontrado com os dados: Número {$data['numero_pedido']}, Data {$data['data_pedido']}, Telefone {$telefone}");
    }
    
    // Inicia transação
    $pdo->beginTransaction();
    error_log("Iniciando transação para exclusão");

    // Primeiro, exclui os acompanhamentos
    $stmt = $pdo->prepare("
        DELETE FROM pedido_item_acomp 
        WHERE fk_pedido_item_id IN (
            SELECT id_pedido_item 
            FROM pedido_itens 
            WHERE fk_pedido_id = ?
        )
    ");
    $stmt->execute([$pedido['id_pedido']]);
    error_log("Acompanhamentos excluídos: " . $stmt->rowCount());

    // Depois, exclui os itens do pedido
    $stmt = $pdo->prepare("DELETE FROM pedido_itens WHERE fk_pedido_id = ?");
    $stmt->execute([$pedido['id_pedido']]);
    error_log("Itens excluídos: " . $stmt->rowCount());

    // Por fim, exclui o pedido
    $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id_pedido = ?");
    $stmt->execute([$pedido['id_pedido']]);
    $pedidosExcluidos = $stmt->rowCount();
    error_log("Pedido excluído: " . $pedidosExcluidos);

    if ($pedidosExcluidos > 0) {
        $pdo->commit();
        error_log("=== EXCLUSÃO CONCLUÍDA COM SUCESSO ===");
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Falha ao excluir o pedido');
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transação revertida devido a erro");
    }
    error_log("ERRO na exclusão do pedido: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 