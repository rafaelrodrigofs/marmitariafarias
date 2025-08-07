<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

// Função para registrar logs em arquivo
function registrarLog($mensagem, $tipo = 'info') {
    $data = date('Y-m-d H:i:s');
    $log = "[{$data}] [{$tipo}] {$mensagem}" . PHP_EOL;
    error_log($log, 3, '../../logs/status_pedidos_' . date('Y-m-d') . '.log');
}

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
    // Obter dados do pedido
    $pedido_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    registrarLog("Tentativa de atualizar pedido ID: {$pedido_id} para status: {$status}");
    
    if (!$pedido_id) {
        throw new Exception('ID do pedido não fornecido');
    }
    
    // Mapear status de texto para valor numérico
    $status_map = [
        'analise' => 0,
        'producao' => 1,
        'entrega' => 2,
        'finalizado' => 3,
        'cancelado' => 4
    ];
    
    if (!isset($status_map[$status])) {
        throw new Exception('Status inválido');
    }
    
    $status_valor = $status_map[$status];
    
    // Buscar status atual para o log
    $sql_atual = "SELECT status_pedido FROM pedidos WHERE id_pedido = :pedido_id";
    $stmt_atual = $pdo->prepare($sql_atual);
    $stmt_atual->execute([':pedido_id' => $pedido_id]);
    $status_atual = $stmt_atual->fetchColumn();
    
    // Registrar a alteração em arquivo de log
    registrarLog("Alterando status do pedido {$pedido_id} de {$status_atual} para {$status_valor} pelo usuário {$_SESSION['user_id']}");
    
    // Atualizar status do pedido
    $sql = "UPDATE pedidos SET status_pedido = :status WHERE id_pedido = :pedido_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $status_valor,
        ':pedido_id' => $pedido_id
    ]);
    
    // Verificar se a atualização foi bem-sucedida
    if ($stmt->rowCount() === 0) {
        throw new Exception('Pedido não encontrado ou status não alterado');
    }
    
    registrarLog("Status do pedido {$pedido_id} atualizado com sucesso para {$status_valor}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Status atualizado com sucesso',
        'pedido_id' => $pedido_id,
        'status' => $status,
        'status_valor' => $status_valor
    ]);
    
} catch (Exception $e) {
    registrarLog("ERRO ao atualizar status: " . $e->getMessage(), "erro");
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar status do pedido: ' . $e->getMessage()
    ]);
}
?> 