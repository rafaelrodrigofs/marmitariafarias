<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

error_log('=== CARRINHO ===');
error_log('POST recebido: ' . print_r($_POST, true));
error_log('SESSION antes: ' . print_r($_SESSION['carrinho']['produtos'], true));

// Garantir que não haverá warnings no PHP misturados com o JSON
error_reporting(E_ERROR);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remover') {
    if (!isset($_POST['index'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Índice não fornecido'
        ]);
        exit;
    }

    $index = intval($_POST['index']);
    error_log('Tentando remover índice: ' . $index);
    error_log('Total de produtos: ' . count($_SESSION['carrinho']['produtos']));
    
    if (isset($_SESSION['carrinho']['produtos']) && 
        is_array($_SESSION['carrinho']['produtos']) && 
        array_key_exists($index, $_SESSION['carrinho']['produtos'])) {
        
        // Remove o produto
        unset($_SESSION['carrinho']['produtos'][$index]);
        
        // Reindexar array
        $_SESSION['carrinho']['produtos'] = array_values($_SESSION['carrinho']['produtos']);
        
        // Recalcula totais
        $subtotal = 0;
        foreach ($_SESSION['carrinho']['produtos'] as $produto) {
            $preco = 0;
            if (isset($produto['preco'])) {
                $preco = floatval($produto['preco']);
            } elseif (isset($produto['preco_produto'])) {
                $preco = floatval($produto['preco_produto']);
            } elseif (isset($produto['preco_base'])) {
                $preco = floatval($produto['preco_base']);
            }
            
            $subtotal += $preco;
            
            // Soma subacompanhamentos
            if (isset($produto['subacompanhamentos'])) {
                foreach ($produto['subacompanhamentos'] as $sub) {
                    $subtotal += floatval($sub['preco_subacomp']);
                }
            }
        }
        
        $_SESSION['carrinho']['subtotal'] = $subtotal;
        $_SESSION['carrinho']['total'] = $subtotal;
        $_SESSION['carrinho']['taxa_entrega'] = floatval($_SESSION['carrinho']['taxa_entrega'] ?? 0);
        
        error_log('Produto removido. Produtos restantes: ' . print_r($_SESSION['carrinho']['produtos'], true));
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Produto removido com sucesso',
            'carrinho' => $_SESSION['carrinho']
        ]);
    } else {
        error_log('Produto não encontrado. Index: ' . $index);
        error_log('Produtos disponíveis: ' . print_r($_SESSION['carrinho']['produtos'], true));
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Produto não encontrado no carrinho'
        ]);
    }
    exit;
}

// Inicializa valores padrão se não existirem
if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [
        'cliente' => null,
        'produtos' => [],
        'subtotal' => 0,
        'taxa_entrega' => 0,
        'total' => 0,
        'retirada' => false,
        'endereco' => null,
        'pagamento' => null,
        'status_pagamento' => 0,
        'numero_pedido' => '',
        'data_pedido' => date('Y-m-d'),
        'hora_pedido' => date('H:i')
    ];
}

// Verificar se o cliente tem endereço cadastrado
$tem_endereco = false;
if (isset($_POST['id_cliente'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cliente_entrega WHERE fk_Cliente_id_cliente = ?");
    $stmt->execute([$_POST['id_cliente']]);
    $tem_endereco = $stmt->fetchColumn() > 0;
}

// Se não existir carrinho na sessão, criar com estrutura completa
if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [
        'cliente' => [
            'id_cliente' => $_POST['id_cliente'] ?? null
        ],
        'produtos' => [],
        'numero_pedido' => $_POST['numero_pedido'] ?? '',
        'data_pedido' => $_POST['data_pedido'] ?? date('Y-m-d'),
        'hora_pedido' => $_POST['hora_pedido'] ?? date('H:i'),
        'endereco' => null,
        'retirada' => !$tem_endereco,
        'pagamento' => null
    ];
} else {
    // Se já existir carrinho, atualizar apenas os campos necessários
    if (isset($_POST['id_cliente'])) {
        $_SESSION['carrinho']['cliente']['id_cliente'] = $_POST['id_cliente'];
        $_SESSION['carrinho']['retirada'] = !$tem_endereco;
    }
    
    // Atualizar número do pedido, data e hora se fornecidos
    if (isset($_POST['numero_pedido'])) {
        $_SESSION['carrinho']['numero_pedido'] = $_POST['numero_pedido'];
    }
    if (isset($_POST['data_pedido'])) {
        $_SESSION['carrinho']['data_pedido'] = $_POST['data_pedido'];
    }
    if (isset($_POST['hora_pedido'])) {
        $_SESSION['carrinho']['hora_pedido'] = $_POST['hora_pedido'];
    }
}

error_log('SESSION depois: ' . print_r($_SESSION, true));

// Verificar e definir valores padrão para evitar undefined array key
$carrinho = [
    'numero_pedido' => $_SESSION['carrinho']['numero_pedido'] ?? time(),
    'data_pedido' => $_SESSION['carrinho']['data_pedido'] ?? date('Y-m-d'),
    'hora_pedido' => $_SESSION['carrinho']['hora_pedido'] ?? date('H:i:s'),
    'retirada' => $_SESSION['carrinho']['retirada'] ?? false
];

echo json_encode([
    'status' => 'success', 
    'message' => 'Carrinho atualizado com sucesso',
    'data' => $carrinho
]);
?>
