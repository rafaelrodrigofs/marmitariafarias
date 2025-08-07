<?php
header('Content-Type: application/json');
require_once __DIR__ . '../../config/database.php';

if (!isset($_POST['id_pedido'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID do pedido não fornecido']);
    exit;
}

$id_pedido = $_POST['id_pedido'];

try {
    // Buscar informações do pedido
    $sql_pedido = "SELECT 
        p.*,
        c.nome_cliente,
        c.telefone_cliente,
        cb.nome_bairro,
        pg.metodo_pagamento
    FROM pedidos p
    LEFT JOIN clientes c ON p.fk_cliente_id = c.id_cliente
    LEFT JOIN cliente_entrega ce ON p.fk_entrega_id = ce.id_entrega
    LEFT JOIN cliente_bairro cb ON ce.fk_Bairro_id_bairro = cb.id_bairro
    LEFT JOIN pagamento pg ON p.fk_pagamento_id = pg.id_pagamento
    WHERE p.id_pedido = ?";

    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->execute([$id_pedido]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['status' => 'error', 'message' => 'Pedido não encontrado']);
        exit;
    }

    // Buscar itens do pedido
    $sql_itens = "SELECT 
        pi.*,
        p.nome_produto
    FROM pedido_itens pi
    LEFT JOIN produto p ON pi.fk_produto_id = p.id_produto
    WHERE pi.fk_pedido_id = ?";

    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([$id_pedido]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    // Buscar acompanhamentos para cada item
    foreach ($itens as &$item) {
        $sql_acomp = "SELECT 
            a.nome_acomp,
            sa.nome_subacomp,
            pia.preco_unitario
        FROM pedido_item_acomp pia
        LEFT JOIN acomp a ON pia.fk_acomp_id = a.id_acomp
        LEFT JOIN sub_acomp sa ON pia.fk_subacomp_id = sa.id_subacomp
        WHERE pia.fk_pedido_item_id = ?";

        $stmt_acomp = $pdo->prepare($sql_acomp);
        $stmt_acomp->execute([$item['id_pedido_item']]);
        $item['acompanhamentos'] = $stmt_acomp->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'status' => 'success',
        'pedido' => $pedido,
        'itens' => $itens
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar detalhes do pedido: ' . $e->getMessage()
    ]);
} 