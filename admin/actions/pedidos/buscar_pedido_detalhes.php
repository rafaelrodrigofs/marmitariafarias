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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID do pedido não fornecido'
    ]);
    exit;
}

$pedido_id = intval($_GET['id']);

try {
    // Buscar informações básicas do pedido
    $sql = "SELECT 
                p.id_pedido, 
                p.num_pedido, 
                p.data_pedido, 
                p.hora_pedido,
                p.status_pedido,
                p.status_pagamento,
                p.taxa_entrega,
                p.sub_total,
                c.id_cliente,
                c.nome_cliente,
                c.telefone_cliente,
                ce.nome_entrega,
                ce.numero_entrega,
                cb.nome_bairro,
                pag.metodo_pagamento
            FROM pedidos p
            LEFT JOIN clientes c ON p.fk_cliente_id = c.id_cliente
            LEFT JOIN cliente_entrega ce ON p.fk_entrega_id = ce.id_entrega
            LEFT JOIN cliente_bairro cb ON ce.fk_Bairro_id_bairro = cb.id_bairro
            LEFT JOIN pagamento pag ON p.fk_pagamento_id = pag.id_pagamento
            WHERE p.id_pedido = :pedido_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pedido_id' => $pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        echo json_encode([
            'success' => false,
            'message' => 'Pedido não encontrado'
        ]);
        exit;
    }
    
    // Buscar itens do pedido
    $sql_itens = "SELECT 
                    pi.id_pedido_item,
                    pi.quantidade,
                    pi.preco_unitario,
                    p.id_produto,
                    p.nome_produto
                  FROM pedido_itens pi
                  JOIN produto p ON pi.fk_produto_id = p.id_produto
                  WHERE pi.fk_pedido_id = :pedido_id";
    
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([':pedido_id' => $pedido_id]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar acompanhamentos de cada item
    foreach ($itens as &$item) {
        $sql_acomp = "SELECT 
                        pia.quantidade,
                        a.id_acomp,
                        a.nome_acomp,
                        sa.id_subacomp,
                        sa.nome_subacomp,
                        sa.preco_subacomp
                      FROM pedido_item_acomp pia
                      JOIN acomp a ON pia.fk_acomp_id = a.id_acomp
                      JOIN sub_acomp sa ON pia.fk_subacomp_id = sa.id_subacomp
                      WHERE pia.fk_pedido_item_id = :item_id";
        
        $stmt_acomp = $pdo->prepare($sql_acomp);
        $stmt_acomp->execute([':item_id' => $item['id_pedido_item']]);
        $item['acompanhamentos'] = $stmt_acomp->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Determinar o tipo de pedido (balcão ou delivery)
    $tipo_pedido = ($pedido['nome_entrega'] === 'Retirada no local') ? 'balcao' : 'delivery';
    
    // Formatar endereço completo
    $endereco = $pedido['nome_entrega'];
    if ($tipo_pedido === 'delivery') {
        $endereco .= ', ' . $pedido['numero_entrega'] . ' - ' . $pedido['nome_bairro'];
    }
    
    // Formatar descrição dos itens
    $descricao_itens = '';
    foreach ($itens as $item) {
        $descricao_itens .= $item['quantidade'] . 'x ' . $item['nome_produto'];
        
        if (!empty($item['acompanhamentos'])) {
            $descricao_itens .= ' (';
            $acomps = [];
            foreach ($item['acompanhamentos'] as $acomp) {
                $acomps[] = $acomp['nome_subacomp'] . ' ' . $acomp['quantidade'] . 'x';
            }
            $descricao_itens .= implode(', ', $acomps) . ')';
        }
        
        $descricao_itens .= '; ';
    }
    $descricao_itens = rtrim($descricao_itens, '; ');
    
    // Calcular valor total
    $total = $pedido['sub_total'] + $pedido['taxa_entrega'];
    
    // Preparar resposta
    $resposta = [
        'success' => true,
        'pedido' => [
            'id' => $pedido['id_pedido'],
            'numero' => $pedido['num_pedido'],
            'data' => date('d/m/Y', strtotime($pedido['data_pedido'])),
            'hora' => $pedido['hora_pedido'],
            'status' => $pedido['status_pedido'],
            'status_pagamento' => $pedido['status_pagamento'],
            'cliente' => [
                'id' => $pedido['id_cliente'],
                'nome' => $pedido['nome_cliente'],
                'telefone' => $pedido['telefone_cliente']
            ],
            'endereco' => $endereco,
            'itens' => $descricao_itens,
            'itens_detalhados' => $itens,
            'subtotal' => $pedido['sub_total'],
            'taxa_entrega' => $pedido['taxa_entrega'],
            'total' => $total,
            'pagamento' => $pedido['metodo_pagamento'],
            'tipo' => $tipo_pedido
        ]
    ];
    
    echo json_encode($resposta);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar detalhes do pedido: ' . $e->getMessage()
    ]);
}
?> 