<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['pedido_numero']) || !isset($data['novo_total'])) {
        throw new Exception('Dados incompletos');
    }
    
    $pedidoNumero = $data['pedido_numero'];
    $novoTotal = $data['novo_total'];
    
    $stmt = $pdo->prepare("UPDATE pedidos SET total = ? WHERE numero_pedido = ?");
    $result = $stmt->execute([$novoTotal, $pedidoNumero]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Erro ao atualizar o total');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 