<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../admin/config/database.php';

// Token de autenticação do Anota AI
$token = 'eyJhbGciOiJIUzI1NiJ9.eyJpZHBhcnRuZXIiOiI2N2MwNGFmYjU5NWY2MzAwMTI4ODUwNDUiLCJpZHBhZ2UiOiI2NDM0MGM5YzYyODg0MDAwMTJhNmQ1MWMifQ.YYiAKDdkokeFpDEYy0bAkau5BYt7X4JqVoQPkK1Qjxc';

// URL base da API do Anota AI
$api_base_url = 'https://api-parceiros.anota.ai/partnerauth';

function buscarPedidoAnotai($order_id, $token) {
    global $api_base_url;
    
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
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['success']) && $data['success']) {
            return $data['info'];
        }
    }
    
    return null;
}

try {
    // Busca todos os pedidos que não têm nome do cliente ou total
    $stmt = $pdo->prepare("SELECT anotai_id FROM anotai_pedidos WHERE (customer_name IS NULL OR customer_name = '' OR total = 0 OR total IS NULL)");
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $atualizados = 0;
    $erros = 0;
    $detalhes = [];
    
    foreach ($pedidos as $pedido) {
        $info = buscarPedidoAnotai($pedido['anotai_id'], $token);
        
        if ($info) {
            try {
                $stmt = $pdo->prepare("UPDATE anotai_pedidos SET customer_name = ?, total = ? WHERE anotai_id = ?");
                $customer_name = isset($info['customer']['name']) ? $info['customer']['name'] : 'Cliente não identificado';
                $total = isset($info['total']) ? $info['total'] : 0;
                
                $stmt->execute([$customer_name, $total, $pedido['anotai_id']]);
                $atualizados++;
                
                $detalhes[] = [
                    'id' => $pedido['anotai_id'],
                    'status' => 'success',
                    'customer' => $customer_name,
                    'total' => $total
                ];
            } catch (PDOException $e) {
                $erros++;
                $detalhes[] = [
                    'id' => $pedido['anotai_id'],
                    'status' => 'error',
                    'message' => 'Erro ao atualizar no banco'
                ];
            }
        } else {
            $erros++;
            $detalhes[] = [
                'id' => $pedido['anotai_id'],
                'status' => 'error',
                'message' => 'Pedido não encontrado na API'
            ];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "Atualização concluída. Atualizados: $atualizados, Erros: $erros",
        'detalhes' => $detalhes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar atualização: ' . $e->getMessage()
    ]);
} 