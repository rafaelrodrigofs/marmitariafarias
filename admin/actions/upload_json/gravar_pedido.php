<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

error_log('Iniciando processamento de pedido');
error_log('Dados recebidos: ' . file_get_contents('php://input'));

try {
    $pdo->beginTransaction();
    
    // Recebe os dados do pedido
    $pedidoJson = file_get_contents('php://input');
    $pedido = json_decode($pedidoJson, true);
    
    error_log('Dados completos do pedido: ' . print_r($pedido, true));

    // Log do cliente
    error_log('Buscando cliente - Telefone: ' . ($pedido['cliente']['telefone'] ?? 'não informado'));
    error_log('Buscando cliente - Nome: ' . ($pedido['cliente']['nome'] ?? 'não informado'));
    
    // Definir data padrão se não estiver definida
    if (!isset($pedido['data_pedido']) || empty($pedido['data_pedido'])) {
        $pedido['data_pedido'] = date('Y-m-d'); // Define a data atual como padrão
    }
    
    // Log da data recebida
    error_log('Data do pedido recebida: ' . ($pedido['data_pedido'] ?? 'não definida'));
    
    // Valida o formato da data
    $date = DateTime::createFromFormat('Y-m-d', $pedido['data_pedido']);
    if (!$date || $date->format('Y-m-d') !== $pedido['data_pedido']) {
        throw new Exception('Formato de data inválido: ' . $pedido['data_pedido']);
    }
    
    $dataPedido = $pedido['data_pedido'];
    error_log('Data validada e será usada: ' . $dataPedido);
    
    // Verifica se é retirada no local
    $isRetiradaLocal = isset($pedido['endereco']['endereco_completo']) && 
                       $pedido['endereco']['endereco_completo'] === "Retirada no local";

    // Se for retirada no local, não precisa validar endereço
    if (!$isRetiradaLocal) {
        // Validação de endereço apenas para entregas
        if (!isset($pedido['endereco']['rua']) || !isset($pedido['endereco']['numero'])) {
            throw new Exception('Endereço incompleto');
        }
        
        $rua = $pedido['endereco']['rua'];
        $numero = $pedido['endereco']['numero'];
        $bairro = isset($pedido['endereco']['bairro']) ? $pedido['endereco']['bairro'] : '';
    } else {
        // Para retirada no local, usamos valores padrão
        $rua = 'Retirada no local';
        $numero = '';
        $bairro = '';
    }
    
    // Busca o ID do cliente pelo telefone ou nome
    $cliente = null;

    if (!empty($pedido['cliente']['telefone_banco'])) {
        // Primeiro tenta buscar pelo telefone do banco
        $stmtCliente = $pdo->prepare("SELECT id_cliente FROM clientes WHERE telefone_cliente = ?");
        $stmtCliente->execute([$pedido['cliente']['telefone_banco']]);
        $cliente = $stmtCliente->fetch();
    } else if (!empty($pedido['cliente']['telefone'])) {
        // Se não tem telefone_banco, tenta com o telefone normal formatado
        $telefone = preg_replace('/[^0-9]/', '', $pedido['cliente']['telefone']);
        $stmtCliente = $pdo->prepare("SELECT id_cliente FROM clientes WHERE telefone_cliente = ?");
        $stmtCliente->execute([$telefone]);
        $cliente = $stmtCliente->fetch();
    }

    if (!$cliente && !empty($pedido['cliente']['nome'])) {
        // Se não encontrou pelo telefone, busca pelo nome
        $stmtCliente = $pdo->prepare("SELECT id_cliente FROM clientes WHERE nome_cliente = ?");
        $stmtCliente->execute([$pedido['cliente']['nome']]);
        $cliente = $stmtCliente->fetch();
    }

    if (!$cliente) {
        throw new Exception('Cliente não encontrado');
    }
    
    error_log('ID do cliente encontrado: ' . ($cliente['id_cliente'] ?? 'não encontrado'));
    
    // Log do endereço
    if (!$isRetiradaLocal) {
        error_log('Buscando endereço - Rua: ' . $rua . ', Número: ' . $numero);
        error_log('Cliente ID para busca de endereço: ' . $cliente['id_cliente']);
    } else {
        error_log('Pedido é retirada no local');
    }
    
    // Busca o ID do endereço
    $stmtEndereco = $pdo->prepare("
        SELECT id_entrega 
        FROM cliente_entrega 
        WHERE fk_Cliente_id_cliente = ? 
        AND nome_entrega = ? 
        AND numero_entrega = ?
    ");
    $stmtEndereco->execute([
        $cliente['id_cliente'],
        $rua,
        $numero
    ]);
    $endereco = $stmtEndereco->fetch();
    
    error_log('ID do endereço encontrado: ' . ($endereco['id_entrega'] ?? 'não encontrado'));
    error_log('Dados do endereço encontrado: ' . print_r($endereco, true));
    error_log('É retirada local? ' . ($isRetiradaLocal ? 'Sim' : 'Não'));
    
    // Modificar a lógica de determinação do enderecoId
    if ($isRetiradaLocal) {
        $enderecoId = 1; // ID padrão para retirada local
    } elseif ($endereco && isset($endereco['id_entrega'])) {
        $enderecoId = $endereco['id_entrega'];
        error_log('Usando ID do endereço encontrado: ' . $enderecoId);
    } else {
        error_log('Endereço não encontrado ou inválido');
        throw new Exception('Endereço não encontrado para este cliente');
    }
    
    error_log('ID do endereço que será usado: ' . $enderecoId);
    
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
    
    // Log da data que será usada
    error_log('Data que será usada: ' . $dataPedido);
    
    // Informações de pagamento
    $statusPagamento = 1; // Pagamento sempre confirmado
    
    // Informações de valores
    $subtotal = str_replace(',', '.', $pedido['valores']['subtotal'] ?? 0);
    $taxaEntrega = str_replace(',', '.', $pedido['valores']['taxa_entrega'] ?? 0);
    
    // Informações do cupom
    $cupomCodigo = $pedido['cupom_codigo'] ?? null;
    $cupomValor = $pedido['cupom_valor'] ?? 0;
    if (is_string($cupomValor)) {
        $cupomValor = str_replace(',', '.', $cupomValor);
    }
    
    // Status do pedido
    $statusPedido = 1; // Sempre em produção
    
    // Ignorando o status recebido do pedido, sempre definindo como 1 (em produção)
    // Comentário: O status anterior verificava o valor em $pedido['status'] e definia valores de 0 a 4
    
    // Origem do pedido
    $origem = trim($pedido['origem'] ?? '');
    
    // Insere o pedido
    $stmtPedido = $pdo->prepare("
        INSERT INTO pedidos (
            num_pedido, 
            data_pedido, 
            hora_pedido,
            fk_cliente_id,
            fk_pagamento_id,
            taxa_entrega,
            sub_total,
            fk_entrega_id,
            status_pagamento,
            is_retirada,
            cupom_codigo,
            cupom_valor,
            status_pedido,
            origem
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Corrigindo a lógica dos valores para retiradas e PDV
    if ($isRetiradaLocal) {
        // Para retiradas:
        $taxaEntrega = 0;  // Taxa SEMPRE zero para retiradas, INDEPENDENTE do valor no JSON
        $sub_total_produtos = $pedido['valores']['subtotal'] ?? 0;
    } else {
        // Para entregas normais, usar o subtotal e taxa separadamente
        $taxaEntrega = $pedido['valores']['taxa_entrega'] ?? 0;
        $sub_total_produtos = $pedido['valores']['subtotal'] ?? 0;
    }

    // Log para debug
    error_log('=== VALORES DO PEDIDO ===');
    error_log('É retirada local: ' . ($isRetiradaLocal ? 'Sim' : 'Não'));
    error_log('Valores recebidos: ' . print_r($pedido['valores'], true));
    error_log('Taxa de entrega final: ' . $taxaEntrega);
    error_log('Sub-total produtos final: ' . $sub_total_produtos);

    $stmtPedido->execute([
        $pedido['numero'],
        $dataPedido,
        $pedido['horario'],
        $cliente['id_cliente'],
        $metodoPagamento,
        $isRetiradaLocal ? 0 : $taxaEntrega, // GARANTINDO que taxa é ZERO quando for retirada
        $subtotal,
        $enderecoId,
        $statusPagamento,
        $isRetiradaLocal ? 1 : 0,
        $cupomCodigo,
        $cupomValor,
        $statusPedido,
        $origem
    ]);
    
    $pedidoId = $pdo->lastInsertId();
    
    // Log após a execução
    error_log('Pedido inserido com data: ' . $dataPedido);
    
    // Insere os itens do pedido
    foreach ($pedido['itens'] as $item) {
        // Buscar produto e seu preço
        $stmtProduto = $pdo->prepare("
            SELECT id_produto, preco_produto 
            FROM produto 
            WHERE nome_produto = ?
        ");
        $stmtProduto->execute([$item['produto']]);
        $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);
        
        if (!$produto) {
            throw new Exception("Produto não encontrado: " . $item['produto']);
        }

        // Inserir item do pedido com o preço do produto
        $stmtItem = $pdo->prepare("
            INSERT INTO pedido_itens (
                fk_pedido_id,
                fk_produto_id,
                quantidade,
                preco_unitario
            ) VALUES (?, ?, ?, ?)
        ");
        
        $stmtItem->execute([
            $pedidoId,
            $produto['id_produto'],
            $item['quantidade'],
            $produto['preco_produto'] // Usando o preço do banco
        ]);
        
        $itemId = $pdo->lastInsertId();
        
        // Inserir acompanhamentos
        if (!empty($item['acompanhamentos'])) {
            foreach ($item['acompanhamentos'] as $acomp) {
                // Verifica se é um array (tem quantidade) ou string
                $nomeAcomp = is_array($acomp) ? $acomp['nome'] : $acomp;
                $quantidade = is_array($acomp) ? $acomp['quantidade'] : 1;

                // Buscar sub_acomp e seu preço
                $stmtSubAcomp = $pdo->prepare("
                    SELECT sa.id_subacomp, sa.fk_acomp_id, sa.preco_subacomp 
                    FROM sub_acomp sa 
                    WHERE sa.nome_subacomp = ?
                ");
                $stmtSubAcomp->execute([$nomeAcomp]);
                $subAcomp = $stmtSubAcomp->fetch(PDO::FETCH_ASSOC);
                
                if ($subAcomp) {
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
                        $subAcomp['fk_acomp_id'],
                        $subAcomp['id_subacomp'],
                        $quantidade, // Usando a quantidade do acompanhamento
                        $subAcomp['preco_subacomp']
                    ]);
                }
            }
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'pedido_id' => $pedidoId]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 