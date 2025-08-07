<?php
include_once '../config/database.php';

if ($_GET['action'] == 'getClienteIdByPedido') {
    $pedido_id = filter_input(INPUT_GET, 'pedido_id', FILTER_VALIDATE_INT);
    
    if ($pedido_id) {
        $stmt = $pdo->prepare("
            SELECT fk_cliente_id as cliente_id 
            FROM pedidos 
            WHERE id_pedido = ?
        ");
        $stmt->execute([$pedido_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
?> 