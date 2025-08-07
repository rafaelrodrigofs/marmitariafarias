<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../admin/config/database.php';

// Token de autenticação do Anota AI
$token = 'eyJhbGciOiJIUzI1NiJ9.eyJpZHBhcnRuZXIiOiI2N2MwNGFmYjU5NWY2MzAwMTI4ODUwNDUiLCJpZHBhZ2UiOiI2ODAwZmRmMTNhNDg3YjAwMTlhNjA3MWMifQ.sKKgw5BCoPffEZI4YA0Pp1D-oJF7w8zaAklQHTQtUFQ';

// URL base da API do Anota AI
$api_base_url = 'https://api-parceiros.anota.ai/partnerauth';

// Função para registrar logs
function registrarLog($mensagem, $dados = null, $tipo = 'INFO') {
    $log_file = __DIR__ . '/webhook_log.json';
    
    // Se não tiver resposta bruta, não registra
    if (!isset($dados['raw_response'])) {
        return;
    }
    
    // Lê os logs existentes
    $logs = [];
    if (file_exists($log_file)) {
        $content = file_get_contents($log_file);
        if (!empty($content)) {
            $logs = json_decode($content, true) ?? [];
        }
    }
    
    // Adiciona apenas a resposta bruta
    $logs[] = json_decode($dados['raw_response'], true);
    
    // Salva os logs
    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
    
    // Garante as permissões corretas
    if (!file_exists($log_file)) {
        chmod($log_file, 0666);
    }
}

// Função para buscar lista de pedidos do Anota AI
function buscarListaPedidos($token) {
    global $api_base_url;
    
    registrarLog("Iniciando busca da lista de pedidos no Anota AI", null, 'DEBUG');
    
    $ch = curl_init("$api_base_url/ping/list");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: $token",
            "Content-Type: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_info = curl_getinfo($ch);
    curl_close($ch);
    
    registrarLog(
        "Resposta bruta da API Anota AI - Lista de Pedidos",
        [
            'http_code' => $http_code,
            'curl_error' => $curl_error ?: null,
            'curl_info' => $curl_info,
            'raw_response' => $response,
            'request_url' => "$api_base_url/ping/list",
            'request_headers' => [
                "Authorization: $token",
                "Content-Type: application/json"
            ]
        ],
        $http_code === 200 ? 'DEBUG' : 'ERROR'
    );
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['success']) && $data['success']) {
            return $data['info']['docs'];
        }
    }
    
    return null;
}

// Função para buscar detalhes do pedido
function buscarPedidoAnotai($order_id, $token) {
    global $api_base_url;
    
    registrarLog("Iniciando busca do pedido no Anota AI", ['order_id' => $order_id], 'DEBUG');
    
    $ch = curl_init("$api_base_url/ping/get/$order_id");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: $token",
            "Content-Type: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_info = curl_getinfo($ch);
    curl_close($ch);
    
    registrarLog(
        "Resposta bruta da API Anota AI - Detalhes do Pedido",
        [
            'order_id' => $order_id,
            'http_code' => $http_code,
            'curl_error' => $curl_error ?: null,
            'curl_info' => $curl_info,
            'raw_response' => $response,
            'request_url' => "$api_base_url/ping/get/$order_id",
            'request_headers' => [
                "Authorization: $token",
                "Content-Type: application/json"
            ]
        ],
        $http_code === 200 ? 'DEBUG' : 'ERROR'
    );
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['success']) && $data['success']) {
            return $data['info'];
        }
    }
    
    return null;
}

try {
    registrarLog("Iniciando busca de pedidos", null, 'INFO');
    
    // Primeiro, busca a lista de pedidos
    $lista_pedidos = buscarListaPedidos($token);
    
    if (!$lista_pedidos) {
        throw new Exception('Erro ao buscar lista de pedidos do Anota AI');
    }
    
    registrarLog("Lista de pedidos obtida", ['quantidade' => count($lista_pedidos)], 'INFO');
    
    $orders = [];
    $erros = 0;
    
    // Para cada pedido na lista, verifica se precisamos atualizar no banco
    foreach ($lista_pedidos as $pedido) {
        $anotai_id = $pedido['_id'];
        
        // Verifica se o pedido já existe no banco
        $stmt = $pdo->prepare("SELECT anotai_id FROM anotai_pedidos WHERE anotai_id = ?");
        $stmt->execute([$anotai_id]);
        
        if (!$stmt->fetch()) {
            // Se não existe, insere no banco
            $stmt = $pdo->prepare("INSERT INTO anotai_pedidos (anotai_id, created_at) VALUES (?, NOW())");
            $stmt->execute([$anotai_id]);
        }
        
        // Busca os detalhes completos do pedido
        $order_data = buscarPedidoAnotai($anotai_id, $token);
        
        if ($order_data) {
            // Formata os dados do pedido
            $orders[] = [
                'id' => $anotai_id,
                'status' => $pedido['check'],
                'customer' => [
                    'name' => $order_data['customer']['name'] ?? 'Cliente não identificado',
                    'phone' => $order_data['customer']['phone'] ?? ''
                ],
                'items' => $order_data['items'] ?? [],
                'total' => $order_data['total'] ?? 0,
                'from' => $pedido['from'] ?? '',
                'salesChannel' => $pedido['salesChannel'] ?? '',
                'updatedAt' => $pedido['updatedAt'] ?? '',
                'original_data' => $order_data
            ];
        } else {
            $erros++;
        }
    }
    
    registrarLog("Processamento de pedidos concluído", [
        'total_processado' => count($lista_pedidos),
        'sucesso' => count($orders),
        'erros' => $erros
    ], 'INFO');
    
    echo json_encode([
        'status' => 'success',
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    registrarLog("Erro ao processar pedidos", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], 'ERROR');
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar pedidos: ' . $e->getMessage()
    ]);
} 