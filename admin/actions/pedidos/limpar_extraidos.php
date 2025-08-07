<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
    exit;
}

try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Buscar pedidos finalizados para registro
    $sql_select = "SELECT id_pedido, num_pedido FROM pedidos WHERE status_pedido = 3";
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->execute();
    $pedidos_finalizados = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
    
    // Registrar pedidos que serão arquivados
    if (!empty($pedidos_finalizados)) {
        $ids_pedidos = array_column($pedidos_finalizados, 'id_pedido');
        $numeros_pedidos = array_column($pedidos_finalizados, 'num_pedido');
        
        // Inserir registro no log de arquivamento
        $log_sql = "INSERT INTO log_arquivamento (
                        fk_user_id, 
                        tipo, 
                        quantidade, 
                        ids_registros, 
                        descricao, 
                        data_arquivamento
                    ) VALUES (
                        :user_id,
                        'pedidos',
                        :quantidade,
                        :ids_registros,
                        :descricao,
                        NOW()
                    )";
        
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':quantidade' => count($ids_pedidos),
            ':ids_registros' => implode(',', $ids_pedidos),
            ':descricao' => 'Pedidos finalizados: ' . implode(', ', $numeros_pedidos)
        ]);
    }
    
    // Mover pedidos finalizados para tabela de arquivamento
    $sql_arquivar = "INSERT INTO pedidos_arquivados
                    SELECT p.*, NOW() as data_arquivamento, :user_id as arquivado_por
                    FROM pedidos p
                    WHERE p.status_pedido = 3";
    
    $stmt_arquivar = $pdo->prepare($sql_arquivar);
    $stmt_arquivar->execute([':user_id' => $_SESSION['user_id']]);
    
    // Remover pedidos finalizados da tabela principal
    $sql_delete = "DELETE FROM pedidos WHERE status_pedido = 3";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute();
    
    // Confirmar transação
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedidos extraídos limpos com sucesso',
        'quantidade' => count($pedidos_finalizados)
    ]);
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao limpar pedidos extraídos: ' . $e->getMessage()
    ]);
}
?> 