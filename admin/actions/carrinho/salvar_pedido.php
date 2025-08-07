<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');
error_log('=== SALVA PEDIDO ===');

try {
    if (!isset($_SESSION['carrinho'])) {
        throw new Exception('Carrinho não encontrado na sessão');
    }

    $pdo->beginTransaction();
    $carrinho = $_SESSION['carrinho'];

    // Atualizar a sessão com os valores do pedido
    $_SESSION['carrinho']['numero_pedido'] = $carrinho['numero_pedido'] ?? time();
    $_SESSION['carrinho']['data_pedido'] = $carrinho['data_pedido'] ?? date('Y-m-d');
    $_SESSION['carrinho']['hora_pedido'] = $carrinho['hora_pedido'] ?? date('H:i:s');

    // Atualizar $carrinho com os novos valores
    $carrinho = $_SESSION['carrinho'];

    error_log('Dados do carrinho: ' . print_r($carrinho, true));

    // Determinar o ID de entrega
    $fk_entrega_id = null;
    if (isset($carrinho['retirada']) && $carrinho['retirada'] === true) {
        // Se for retirada, usar ID padrão para retirada (assumindo que seja 1)
        $fk_entrega_id = 1; // ID para retirada no local
    } else if (isset($carrinho['endereco']['id_entrega'])) {
        // Se for entrega, usar o ID do endereço selecionado
        $fk_entrega_id = $carrinho['endereco']['id_entrega'];
    } else {
        throw new Exception('Método de entrega não especificado');
    }

    // Calcular o total apenas dos produtos
    $total_produtos = 0;
    foreach ($carrinho['produtos'] as $produto) {
        // Valor base do produto
        $preco_base = floatval($produto['preco']);
        $quantidade = isset($produto['quantidade']) ? intval($produto['quantidade']) : 1;
        $total_item = $preco_base * $quantidade;
        
        // Adicionar valores dos acompanhamentos se existirem
        if (isset($produto['escolhas']) && is_array($produto['escolhas'])) {
            foreach ($produto['escolhas'] as $escolha) {
                if (isset($escolha['preco_subacomp'])) {
                    $total_item += floatval($escolha['preco_subacomp']);
                }
            }
        }
        
        $total_produtos += $total_item;
    }

    // 1. Inserir pedido principal
    $sql_pedido = "INSERT INTO pedidos (
        num_pedido, data_pedido, hora_pedido, 
        fk_cliente_id, fk_pagamento_id, fk_entrega_id,
        taxa_entrega, sub_total, status_pagamento
    ) VALUES (
        :num_pedido, :data_pedido, :hora_pedido,
        :fk_cliente_id, :fk_pagamento_id, :fk_entrega_id,
        :taxa_entrega, :sub_total, :status_pagamento
    )";

    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->execute([
        'num_pedido' => $carrinho['numero_pedido'],
        'data_pedido' => $carrinho['data_pedido'],
        'hora_pedido' => $carrinho['hora_pedido'],
        'fk_cliente_id' => $carrinho['cliente']['id_cliente'],
        'fk_pagamento_id' => $carrinho['pagamento']['id_pagamento'],
        'fk_entrega_id' => $fk_entrega_id,
        'taxa_entrega' => $carrinho['endereco']['valor_taxa'] ?? 0,
        'sub_total' => $total_produtos,
        'status_pagamento' => isset($carrinho['status_pagamento']) ? $carrinho['status_pagamento'] : 0
    ]);

    $pedido_id = $pdo->lastInsertId();
    error_log('Pedido inserido: ' . $pedido_id);

    // 2. Inserir itens do pedido
    foreach ($carrinho['produtos'] as $produto) {
        $sql_item = "INSERT INTO pedido_itens (
            fk_pedido_id, fk_produto_id, quantidade, preco_unitario
        ) VALUES (
            :fk_pedido_id, :fk_produto_id, :quantidade, :preco_unitario
        )";

        $stmt_item = $pdo->prepare($sql_item);
        $stmt_item->execute([
            'fk_pedido_id' => $pedido_id,
            'fk_produto_id' => $produto['produto_id'],
            'quantidade' => 1, // ou $produto['quantidade'] se existir
            'preco_unitario' => $produto['preco']
        ]);

        $item_id = $pdo->lastInsertId();
        error_log('Item inserido: ' . $item_id);

        // 3. Inserir acompanhamentos do item
        if (isset($produto['escolhas']) && is_array($produto['escolhas'])) {
            foreach ($produto['escolhas'] as $escolha) {
                $sql_acomp = "INSERT INTO pedido_item_acomp (
                    fk_pedido_item_id, fk_acomp_id, fk_subacomp_id, quantidade
                ) VALUES (
                    :fk_pedido_item_id, :fk_acomp_id, :fk_subacomp_id, :quantidade
                )";

                $stmt_acomp = $pdo->prepare($sql_acomp);
                $stmt_acomp->execute([
                    'fk_pedido_item_id' => $item_id,
                    'fk_acomp_id' => $escolha['acomp_id'],
                    'fk_subacomp_id' => $escolha['subacomp_id'],
                    'quantidade' => $escolha['quantidade']
                ]);
                error_log('Acompanhamento inserido: acomp_id=' . $escolha['acomp_id'] . ', subacomp_id=' . $escolha['subacomp_id']);
            }
        }
    }

    $pdo->commit();
    error_log('Transação completada com sucesso');
    
    // Limpar carrinho após salvar com sucesso
    unset($_SESSION['carrinho']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Pedido salvo com sucesso',
        'id_pedido' => $pedido_id
    ]);

} catch (Exception $e) {
    error_log('ERRO: ' . $e->getMessage());
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao finalizar pedido: ' . $e->getMessage()
    ]);
}
?>