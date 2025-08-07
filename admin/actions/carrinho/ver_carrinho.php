<?php
session_start();
include_once '../../config/database.php';

// Remove qualquer saída anterior
ob_clean();

// Define o header apenas uma vez
header('Content-Type: application/json; charset=utf-8');

// Desativa a exibição de erros, mas mantém o log
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Inicializa a resposta mantendo os valores do pedido se existirem
    $response = [
        'cliente' => null,
        'enderecos' => [],
        'endereco_selecionado' => null,
        'produtos' => [],
        'pagamento' => null,
        'status_pagamento' => isset($_SESSION['carrinho']['status_pagamento']) ? $_SESSION['carrinho']['status_pagamento'] : 0,
        'subtotal' => isset($_SESSION['carrinho']['subtotal']) ? floatval($_SESSION['carrinho']['subtotal']) : 0,
        'taxa_entrega' => isset($_SESSION['carrinho']['taxa_entrega']) ? floatval($_SESSION['carrinho']['taxa_entrega']) : 0,
        'total' => isset($_SESSION['carrinho']['subtotal']) && isset($_SESSION['carrinho']['taxa_entrega']) ? 
            floatval($_SESSION['carrinho']['subtotal']) + floatval($_SESSION['carrinho']['taxa_entrega']) : 0,
        'retirada' => isset($_SESSION['carrinho']['retirada']) ? $_SESSION['carrinho']['retirada'] : false,
        'numero_pedido' => isset($_SESSION['carrinho']['numero_pedido']) ? $_SESSION['carrinho']['numero_pedido'] : '',
        'data_pedido' => isset($_SESSION['carrinho']['data_pedido']) ? $_SESSION['carrinho']['data_pedido'] : date('Y-m-d'),
        'hora_pedido' => isset($_SESSION['carrinho']['hora_pedido']) ? $_SESSION['carrinho']['hora_pedido'] : date('H:i')
    ];

    error_log('Valores do pedido:');
    error_log('Número: ' . $response['numero_pedido']);
    error_log('Hora: ' . $response['hora_pedido']);

    // Buscar dados do cliente e endereços em uma única consulta
    if (isset($_SESSION['carrinho']['cliente']['id_cliente'])) {
        $cliente_id = $_SESSION['carrinho']['cliente']['id_cliente'];
        
        $sql = "SELECT c.nome_cliente, c.telefone_cliente, 
                       ce.id_entrega, ce.nome_entrega, ce.numero_entrega, 
                       cb.id_bairro, cb.nome_bairro, cb.valor_taxa,
                       e.nome_empresa
                FROM clientes c
                LEFT JOIN cliente_entrega ce ON ce.fk_Cliente_id_cliente = c.id_cliente
                LEFT JOIN cliente_bairro cb ON ce.fk_Bairro_id_bairro = cb.id_bairro
                LEFT JOIN empresas e ON c.fk_empresa_id = e.id_empresa
                WHERE c.id_cliente = :id_cliente
                ORDER BY ce.nome_entrega";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_cliente' => $cliente_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result) {
            $response['cliente'] = [
                'nome_cliente' => $result[0]['nome_cliente'],
                'telefone_cliente' => $result[0]['telefone_cliente'],
                'nome_empresa' => $result[0]['nome_empresa'] ?? null
            ];

            foreach ($result as $row) {
                $response['enderecos'][] = [
                    'id_entrega' => $row['id_entrega'],
                    'nome_entrega' => $row['nome_entrega'],
                    'numero_entrega' => $row['numero_entrega'],
                    'id_bairro' => $row['id_bairro'],
                    'nome_bairro' => $row['nome_bairro'],
                    'valor_taxa' => $row['valor_taxa']
                ];
            }
        }
    }

    // Buscar endereço selecionado com taxa
    if (isset($_SESSION['carrinho']['endereco']['id_entrega'])) {
        foreach ($response['enderecos'] as $endereco) {
            if ($endereco['id_entrega'] == $_SESSION['carrinho']['endereco']['id_entrega']) {
                $response['endereco_selecionado'] = $endereco;
                if (!$response['retirada']) {
                    $response['taxa_entrega'] = floatval($endereco['valor_taxa']);
                    $_SESSION['carrinho']['endereco']['valor_taxa'] = $response['taxa_entrega'];
                }
                break;
            }
        }
    }

    // Buscar dados do pagamento
    if (isset($_SESSION['carrinho']['pagamento'])) {
        $sql_pag = "SELECT id_pagamento, metodo_pagamento 
                    FROM pagamento 
                    WHERE id_pagamento = :id_pagamento";
        $stmt_pag = $pdo->prepare($sql_pag);
        $stmt_pag->execute(['id_pagamento' => $_SESSION['carrinho']['pagamento']['id_pagamento']]);
        $response['pagamento'] = $stmt_pag->fetch(PDO::FETCH_ASSOC);
    }

    // Buscar produtos do carrinho
    if (isset($_SESSION['carrinho']['produtos'])) {
        // Coletar todos os IDs de produtos e subacompanhamentos
        $todos_produto_ids = array_column($_SESSION['carrinho']['produtos'], 'produto_id');
        $todos_subacomp_ids = [];
        foreach ($_SESSION['carrinho']['produtos'] as $item) {
            if (isset($item['escolhas']) && is_array($item['escolhas'])) {
                foreach ($item['escolhas'] as $escolha) {
                    $todos_subacomp_ids[] = $escolha['subacomp_id'];
                }
            }
        }

        // Buscar todos os produtos de uma vez só
        $produtos_map = [];
        if (!empty($todos_produto_ids)) {
            $placeholders = str_repeat('?,', count($todos_produto_ids) - 1) . '?';
            $sql_prod = "SELECT id_produto, nome_produto FROM produto WHERE id_produto IN ($placeholders)";
            $stmt_prod = $pdo->prepare($sql_prod);
            $stmt_prod->execute($todos_produto_ids);
            $produtos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

            // Criar mapa para acesso rápido
            foreach ($produtos as $produto) {
                $produtos_map[$produto['id_produto']] = $produto['nome_produto'];
            }
        }

        // Buscar todos os subacompanhamentos de uma vez só
        $subacomp_map = [];
        if (!empty($todos_subacomp_ids)) {
            $placeholders = str_repeat('?,', count($todos_subacomp_ids) - 1) . '?';
            $sql_sub = "SELECT id_subacomp, nome_subacomp, preco_subacomp 
                       FROM sub_acomp 
                       WHERE id_subacomp IN ($placeholders)";
            $stmt_sub = $pdo->prepare($sql_sub);
            $stmt_sub->execute($todos_subacomp_ids);
            $subacompanhamentos = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

            // Criar mapa para acesso rápido
            foreach ($subacompanhamentos as $sub) {
                $subacomp_map[$sub['id_subacomp']] = $sub;
            }
        }

        // Zerar o subtotal antes de começar
        $response['subtotal'] = 0;

        error_log("=== PROCESSANDO PRODUTOS DO CARRINHO ===");
        foreach ($_SESSION['carrinho']['produtos'] as $index => $item) {
            error_log("Processando item $index:");
            error_log("- ID do produto: " . $item['produto_id']);
            error_log("- Preço base: " . $item['preco']);
            error_log("- Escolhas: " . print_r($item['escolhas'] ?? [], true));
            
            $produto_completo = [
                'id_produto' => $item['produto_id'],
                'nome_produto' => $produtos_map[$item['produto_id']] ?? '',
                'preco_produto' => floatval($item['preco']),
                'subacompanhamentos' => []
            ];

            // Usar APENAS o preço do item da sessão
            $valor_item = floatval($item['preco']);
            
            // Apenas adicionar subacompanhamentos para exibição
            if (isset($item['escolhas']) && is_array($item['escolhas'])) {
                foreach ($item['escolhas'] as $escolha) {
                    if (isset($subacomp_map[$escolha['subacomp_id']])) {
                        $subacomp = $subacomp_map[$escolha['subacomp_id']];
                        $produto_completo['subacompanhamentos'][] = [
                            'id_subacomp' => $escolha['subacomp_id'],
                            'nome_subacomp' => $subacomp['nome_subacomp'],
                            'preco_subacomp' => floatval($subacomp['preco_subacomp'] ?? 0)
                        ];
                    }
                }
            }

            error_log("Valor final do item: " . $valor_item);
            
            $response['produtos'][] = $produto_completo;
            $response['subtotal'] += $valor_item;
            
            error_log("Subtotal acumulado: " . $response['subtotal']);
        }

        error_log("=== VALORES FINAIS ===");
        error_log("Subtotal final: " . $response['subtotal']);
        error_log("Taxa de entrega: " . $response['taxa_entrega']);
        error_log("Total: " . ($response['subtotal'] + $response['taxa_entrega']));
    }

    // Definir taxa de entrega
    $response['taxa_entrega'] = !$response['retirada'] ? floatval($response['endereco_selecionado']['valor_taxa'] ?? 0) : 0;

    // Calcula o total corretamente
    $response['total'] = $response['subtotal'] + $response['taxa_entrega'];

    error_log('Resposta final: ' . print_r($response, true));
    
    // Limpa o buffer antes de enviar o JSON
    if (ob_get_length()) ob_clean();
    
    // Envia o JSON e encerra o script
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
    
} catch (Exception $e) {
    error_log('Erro: ' . $e->getMessage());
    http_response_code(500);
    
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
    exit();
}

error_log('Dados enviados: ' . print_r($_SESSION['carrinho'], true));

?>
