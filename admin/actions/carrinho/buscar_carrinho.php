<?php
session_start();
include_once '../../config/database.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['pedido_atual'])) {
        echo json_encode([
            'status' => 'success',
            'items' => []
        ]);
        exit;
    }

    $sql = "SELECT pi.*, p.nome_produto, 
            GROUP_CONCAT(
                CONCAT(
                    sa.nome_subacomp, 
                    ' (', 
                    pia.quantidade,
                    ')'
                ) SEPARATOR ', '
            ) as acompanhamentos
            FROM pedido_itens pi
            LEFT JOIN produto p ON p.id_produto = pi.fk_produto_id
            LEFT JOIN pedido_item_acomp pia ON pia.fk_pedido_item_id = pi.id_pedido_item
            LEFT JOIN sub_acomp sa ON sa.id_subacomp = pia.fk_subacomp_id
            WHERE pi.fk_pedido_id = :pedido_id
            GROUP BY pi.id_pedido_item";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['pedido_id' => $_SESSION['pedido_atual']]);
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'items' => $items
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 