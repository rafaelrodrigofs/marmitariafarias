<?php
// Habilitar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';

header('Content-Type: application/json');

function buscarCategoriaAcompanhamentoCorreta($pdo, $nomeSubAcomp, $nomeProduto) {
    // Primeiro busca a categoria real do subacompanhamento
    $stmt = $pdo->prepare("
        SELECT a.id_acomp, a.nome_acomp 
        FROM sub_acomp sa 
        JOIN acomp a ON sa.fk_acomp_id = a.id_acomp 
        WHERE sa.nome_subacomp = ?
    ");
    $stmt->execute([$nomeSubAcomp]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resultado) {
        return null;
    }
    
    // Verifica se o produto possui essa categoria
    $stmt = $pdo->prepare("
        SELECT pa.id 
        FROM produto_acomp pa
        JOIN produto p ON pa.fk_produto_id = p.id_produto
        WHERE p.nome_produto = ? AND pa.fk_acomp_id = ?
    ");
    $stmt->execute([$nomeProduto, $resultado['id_acomp']]);
    $associacaoProduto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se a categoria não está associada ao produto, busca uma categoria mais adequada
    if (!$associacaoProduto) {
        if (stripos($nomeSubAcomp, 'carne') !== false || 
            stripos($nomeSubAcomp, 'frango') !== false || 
            stripos($nomeSubAcomp, 'peixe') !== false ||
            stripos($nomeSubAcomp, 'strogonoff') !== false ||
            stripos($nomeSubAcomp, 'escondidinho') !== false) {
            
            $stmt = $pdo->prepare("
                SELECT a.id_acomp 
                FROM produto_acomp pa
                JOIN produto p ON pa.fk_produto_id = p.id_produto
                JOIN acomp a ON pa.fk_acomp_id = a.id_acomp
                WHERE p.nome_produto = ? AND (a.nome_acomp = 'Carne' OR a.nome_acomp LIKE '%carne%')
            ");
            $stmt->execute([$nomeProduto]);
            $alternativa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($alternativa) {
                return $alternativa['id_acomp'];
            }
        }
        
        // Se não encontrou, verifica se tem "Monte sua Marmita"
        $stmt = $pdo->prepare("
            SELECT a.id_acomp 
            FROM produto_acomp pa
            JOIN produto p ON pa.fk_produto_id = p.id_produto
            JOIN acomp a ON pa.fk_acomp_id = a.id_acomp
            WHERE p.nome_produto = ? AND a.nome_acomp = 'Monte sua Marmita'
        ");
        $stmt->execute([$nomeProduto]);
        $alternativa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($alternativa) {
            return $alternativa['id_acomp'];
        }
    }
    
    return $resultado['id_acomp'];
}

function buscarSubacompanhamentoCorreto($pdo, $nomeSubAcomp, $acompId) {
    $stmt = $pdo->prepare("
        SELECT sa.id_subacomp, sa.fk_acomp_id, sa.preco_subacomp 
        FROM sub_acomp sa 
        WHERE sa.nome_subacomp = ? 
        AND sa.fk_acomp_id = ?
    ");
    $stmt->execute([$nomeSubAcomp, $acompId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    $pdo->beginTransaction();
    
    // Log inicial
    error_log("\n========== INICIANDO REGRAVAÇÃO DO PEDIDO ==========");
    
    // Recebe e valida os dados do pedido
    $pedidoJson = file_get_contents('php://input');
    $pedido = json_decode($pedidoJson, true);
    
    // Validações básicas
    if (!isset($pedido['numero']) || !isset($pedido['cliente']['telefone_banco']) || 
        !isset($pedido['data_pedido']) || !isset($pedido['horario'])) {
        throw new Exception('Dados incompletos para atualização do pedido');
    }

    // Limpa o telefone
    $telefone = preg_replace('/[^0-9]/', '', $pedido['cliente']['telefone_banco']);
    
    // Log de busca do pedido
    error_log("\n----- DADOS DO PEDIDO -----");
    error_log("Número: " . $pedido['numero']);
    error_log("Data: " . $pedido['data_pedido']);
    error_log("Hora: " . $pedido['horario']);
    error_log("Cliente: " . $pedido['cliente']['telefone_banco']);
    
    // Busca o ID do pedido existente com prepared statement
    $stmt = $pdo->prepare("
        SELECT p.id_pedido, p.fk_cliente_id, p.fk_entrega_id
        FROM pedidos p
        INNER JOIN clientes c ON p.fk_cliente_id = c.id_cliente
        WHERE p.num_pedido = ? 
        AND c.telefone_cliente = ?
        AND DATE(p.data_pedido) = ?
        AND p.hora_pedido = ?
    ");
    
    $stmt->execute([
        $pedido['numero'],
        $telefone,
        $pedido['data_pedido'],
        $pedido['horario']
    ]);
    
    $pedidoExistente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedidoExistente) {
        throw new Exception('Pedido não encontrado para atualização');
    }
    
    $pedidoId = $pedidoExistente['id_pedido'];
    
    // Determina o ID do método de pagamento
    $metodoPagamento = match(true) {
        // Caso especial para Crédito - verifica o status
        $pedido['pagamento']['metodo'] === 'Crédito' => match($pedido['pagamento']['status']) {
            'Sodexo / Pluxee Refeição' => 5,
            'PIX - 44167570000146' => 6,
            'Crédito' => 4,
            default => 9
        },
        // Outros métodos de pagamento
        $pedido['pagamento']['metodo'] === 'Dinheiro' => 1,
        $pedido['pagamento']['metodo'] === 'Voucher' => 2,
        $pedido['pagamento']['metodo'] === 'Debito' => 3,
        $pedido['pagamento']['metodo'] === 'VR Refeição / Alimentação' => 7,
        $pedido['pagamento']['metodo'] === 'Online - Pix' => 8,
        default => 9
    };
    
    // Verifica se é retirada no local
    $isRetiradaLocal = isset($pedido['endereco']['endereco_completo']) && 
                       $pedido['endereco']['endereco_completo'] === "Retirada no local";

    // Ajusta a taxa de entrega para zero se for retirada no local
    $taxaEntrega = $isRetiradaLocal ? 0 : floatval($pedido['taxa_entrega'] ?? 0);

    // Atualiza os dados principais do pedido
    $stmt = $pdo->prepare("
        UPDATE pedidos 
        SET 
            taxa_entrega = ?,
            sub_total = ?,
            cupom_codigo = ?,
            cupom_valor = ?,
            fk_cliente_id = ?,
            fk_entrega_id = ?,
            data_pedido = ?,
            hora_pedido = ?,
            fk_pagamento_id = ?
        WHERE id_pedido = ?
    ");
    
    $stmt->execute([
        $taxaEntrega, // Agora usa a taxa ajustada
        floatval($pedido['total'] ?? 0),
        $pedido['cupom_codigo'] ?? null,
        floatval($pedido['cupom_valor'] ?? 0),
        $pedidoExistente['fk_cliente_id'],
        $pedidoExistente['fk_entrega_id'],
        $pedido['data_pedido'],
        $pedido['horario'],
        $metodoPagamento,
        $pedidoId
    ]);
    
    // Log de remoção de itens antigos
    error_log("\n----- REMOVENDO ITENS ANTIGOS -----");
    error_log("Pedido ID: " . $pedidoId);
    
    $stmt = $pdo->prepare("DELETE FROM pedido_item_acomp WHERE fk_pedido_item_id IN (SELECT id_pedido_item FROM pedido_itens WHERE fk_pedido_id = ?)");
    $stmt->execute([$pedidoId]);
    error_log("Acompanhamentos removidos: " . $stmt->rowCount());
    
    $stmt = $pdo->prepare("DELETE FROM pedido_itens WHERE fk_pedido_id = ?");
    $stmt->execute([$pedidoId]);
    error_log("Itens removidos: " . $stmt->rowCount());

    // Log de inserção de novos itens
    error_log("\n----- INSERINDO NOVOS ITENS -----");
    error_log("Total de itens a inserir: " . count($pedido['itens']));

    // Insere os novos itens
    if (!empty($pedido['itens'])) {
        foreach ($pedido['itens'] as $index => $item) {
            error_log("\n--- ITEM " . ($index + 1) . " ---");
            error_log("Produto: " . $item['produto']);
            error_log("Quantidade: " . $item['quantidade']);
            error_log("Observação: " . ($item['observacao'] ?? 'Nenhuma'));
            
            if (empty($item['produto'])) {
                error_log("Item sem produto definido. Pulando...");
                continue; // Pula itens sem produto
            }

            // Buscar produto
            error_log("Buscando produto no banco: " . $item['produto']);
            $stmtProduto = $pdo->prepare("
                SELECT id_produto, preco_produto 
                FROM produto 
                WHERE nome_produto = ? 
                AND activated = 1
            ");
            $stmtProduto->execute([$item['produto']]);
            $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);
            
            if (!$produto) {
                error_log("ERRO: Produto não encontrado ou inativo: " . $item['produto']);
                throw new Exception("Produto não encontrado ou inativo: " . $item['produto']);
            }
            
            error_log("Produto encontrado: ID=" . $produto['id_produto'] . ", Preço=" . $produto['preco_produto']);

            // Inserir novo item
            error_log("Inserindo item no pedido...");
            $stmtItem = $pdo->prepare("
                INSERT INTO pedido_itens (
                    fk_pedido_id,
                    fk_produto_id,
                    quantidade,
                    preco_unitario,
                    observacao
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $quantidade = intval($item['quantidade']);
            $observacao = $item['observacao'] ?? null;
            
            error_log("Dados do item: Pedido ID=" . $pedidoId . ", Produto ID=" . $produto['id_produto'] . 
                      ", Quantidade=" . $quantidade . ", Preço=" . $produto['preco_produto'] . 
                      ", Observação=" . ($observacao ?? 'nenhuma'));
            
            $stmtItem->execute([
                $pedidoId,
                $produto['id_produto'],
                $quantidade,
                $produto['preco_produto'],
                $observacao
            ]);
            
            $itemId = $pdo->lastInsertId();
            error_log("Item inserido com sucesso. ID do novo item: " . $itemId);
            
            // Inserir novos acompanhamentos
            if (!empty($item['acompanhamentos'])) {
                error_log("\nAcompanhamentos do item " . ($index + 1) . ":");
                foreach ($item['acompanhamentos'] as $acompIndex => $acomp) {
                    $nomeAcomp = is_array($acomp) ? $acomp['nome'] : $acomp;
                    $quantidade = is_array($acomp) ? intval($acomp['quantidade']) : 1;
                    
                    error_log("  " . ($acompIndex + 1) . ". " . $nomeAcomp . " (Qtd: " . $quantidade . ")");
                    
                    error_log("Buscando subacompanhamento no banco: " . $nomeAcomp);
                    $categoriaCorreta = buscarCategoriaAcompanhamentoCorreta($pdo, $nomeAcomp, $item['produto']);
                    
                    if ($categoriaCorreta) {
                        // Agora busca o subacompanhamento correto baseado na categoria
                        $subAcomp = buscarSubacompanhamentoCorreto($pdo, $nomeAcomp, $categoriaCorreta);
                        
                        if ($subAcomp) {
                            error_log("Inserindo acompanhamento no item...");
                            $stmtItemAcomp = $pdo->prepare("
                                INSERT INTO pedido_item_acomp (
                                    fk_pedido_item_id,
                                    fk_acomp_id,
                                    fk_subacomp_id,
                                    quantidade,
                                    preco_unitario
                                ) VALUES (?, ?, ?, ?, ?)
                            ");
                            
                            $stmtItemAcomp->execute([
                                $itemId,
                                $categoriaCorreta,
                                $subAcomp['id_subacomp'],
                                $quantidade,
                                $subAcomp['preco_subacomp']
                            ]);
                            
                            error_log("Acompanhamento inserido com sucesso. ID do item: " . $itemId . 
                                      ", ID do acompanhamento: " . $categoriaCorreta . 
                                      ", ID do subacompanhamento: " . $subAcomp['id_subacomp']);
                        } else {
                            error_log("AVISO: Subacompanhamento não encontrado ou inativo: " . $nomeAcomp);
                        }
                    } else {
                        error_log("AVISO: Categoria não encontrada para o acompanhamento: " . $nomeAcomp);
                    }
                }
            } else {
                error_log("Item sem acompanhamentos.");
            }
        }
    } else {
        error_log("AVISO: Nenhum item encontrado para inserir no pedido.");
    }
    
    error_log("\n========== REGRAVAÇÃO CONCLUÍDA COM SUCESSO ==========\n");
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("\n❌ ERRO NA REGRAVAÇÃO: " . $e->getMessage() . "\n");
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 