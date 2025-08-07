<?php
session_start();
include_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    error_log("=== INÍCIO DO PROCESSO ===");
    error_log("Dados recebidos: " . print_r($data, true));
    
    if (!isset($data['produto_id'])) {
        throw new Exception('produto_id não encontrado');
    }

    // Buscar preço real do produto no banco
    $stmt = $pdo->prepare("SELECT id_produto, nome_produto, preco_produto FROM produto WHERE id_produto = ?");
    $stmt->execute([$data['produto_id']]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        throw new Exception('Produto não encontrado');
    }

    // Buscar todos os subacompanhamentos de uma vez
    $subacomp_ids = array_column($data['escolhas'], 'subacomp_id');
    $acomp_ids = array_column($data['escolhas'], 'acomp_id');
    
    if (!empty($subacomp_ids)) {
        $placeholders = str_repeat('?,', count($subacomp_ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT 
                sa.id_subacomp,
                sa.nome_subacomp,
                sa.preco_subacomp,
                sa.fk_acomp_id,
                a.nome_acomp
            FROM sub_acomp sa
            JOIN acomp a ON a.id_acomp = sa.fk_acomp_id
            WHERE sa.id_subacomp IN ($placeholders)
        ");
        $stmt->execute($subacomp_ids);
        $subacompanhamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapear subacompanhamentos por ID
        $subacomp_map = [];
        foreach ($subacompanhamentos as $sub) {
            $subacomp_map[$sub['id_subacomp']] = $sub;
        }
    }

    // Calcular preço dos acompanhamentos
    $preco_acompanhamentos = 0;
    foreach ($data['escolhas'] as $escolha) {
        if (isset($subacomp_map[$escolha['subacomp_id']])) {
            $subacomp = $subacomp_map[$escolha['subacomp_id']];
            $preco_item = floatval($subacomp['preco_subacomp']) * $escolha['quantidade'];
            $preco_acompanhamentos += $preco_item;
            error_log("Preço do acompanhamento {$subacomp['nome_subacomp']}: {$preco_item}");
        }
    }

    error_log("Total dos acompanhamentos: {$preco_acompanhamentos}");

    // Preço final do item
    $preco_final = $produto['preco_produto'] + $preco_acompanhamentos;
    error_log("Preço final calculado: {$preco_final}");

    // Adicionar produto ao carrinho
    $_SESSION['carrinho']['produtos'][] = [
        'produto_id' => $produto['id_produto'],
        'nome' => $produto['nome_produto'],
        'preco' => $preco_final,
        'preco_base' => $produto['preco_produto'],
        'preco_acompanhamentos' => $preco_acompanhamentos,
        'escolhas' => $data['escolhas']
    ];

    // Atualizar totais
    $_SESSION['carrinho']['quantidade_itens'] = count($_SESSION['carrinho']['produtos']);
    $_SESSION['carrinho']['total'] = array_reduce(
        $_SESSION['carrinho']['produtos'], 
        function($total, $item) {
            return $total + $item['preco'];
        }, 
        0
    );

    if (isset($_SESSION['carrinho']['endereco']['valor_taxa'])) {
        $_SESSION['carrinho']['total'] += $_SESSION['carrinho']['endereco']['valor_taxa'];
    }

    // Calcular valores
    $preco_final = floatval($data['preco']); // Preço base do produto
    $quantidade = intval($data['quantidade']);

    // O preço dos acompanhamentos já foi somado anteriormente ao $data['preco']
    // então não precisamos somar novamente aqui
    $subtotal = $preco_final * $quantidade;

    $taxa_entrega = isset($_SESSION['carrinho']['taxa_entrega']) ? 
        floatval($_SESSION['carrinho']['taxa_entrega']) : 0;

    // Armazenar valores separadamente
    $_SESSION['carrinho']['subtotal'] = $subtotal;
    $_SESSION['carrinho']['taxa_entrega'] = $taxa_entrega;
    $_SESSION['carrinho']['total'] = $subtotal + $taxa_entrega;

    error_log("Preço base do produto: " . $data['preco']);
    error_log("Quantidade: " . $data['quantidade']);
    error_log("Subtotal calculado: " . $subtotal);

    echo json_encode([
        'status' => 'success',
        'message' => 'Produto adicionado ao carrinho',
        'debug' => [
            'produto_base' => $produto['preco_produto'],
            'nome_produto' => $produto['nome_produto'],
            'acompanhamentos' => [
                'total' => $preco_acompanhamentos,
                'detalhes' => $data['escolhas']
            ],
            'preco_final' => $preco_final,
            'carrinho' => $_SESSION['carrinho']
        ]
    ]);

} catch (Exception $e) {
    error_log("ERRO: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
