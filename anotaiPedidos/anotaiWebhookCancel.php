<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../admin/config/database.php';

// Função para registrar logs
function registrarLog($mensagem, $dados = null) {
    $log_file = __DIR__ . '/webhook_cancel_log.json';
    
    // Lê os logs existentes
    $logs = [];
    if (file_exists($log_file)) {
        $content = file_get_contents($log_file);
        if (!empty($content)) {
            $logs = json_decode($content, true) ?? [];
        }
    }
    
    // Formata o log
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'mensagem' => $mensagem
    ];
    
    if ($dados !== null) {
        $log['dados'] = $dados;
    }
    
    // Adiciona o log
    $logs[] = $log;
    
    // Salva os logs
    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
    
    // Garante as permissões corretas
    if (!file_exists($log_file)) {
        chmod($log_file, 0666);
    }
}

// Token de autenticação
$token = 'eyJhbGciOiJIUzI1NiJ9.eyJpZHBhcnRuZXIiOiI2N2MwNGFmYjU5NWY2MzAwMTI4ODUwNDUiLCJpZHBhZ2UiOiI2NDM0MGM5YzYyODg0MDAwMTJhNmQ1MWMifQ.YYiAKDdkokeFpDEYy0bAkau5BYt7X4JqVoQPkK1Qjxc';

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    registrarLog("Erro: Método não permitido - " . $_SERVER['REQUEST_METHOD']);
    exit;
}

// Verifica o token
$headers = getallheaders();
$auth_token = '';

if (isset($headers['Authorization'])) {
    $auth_token = str_replace('Bearer ', '', $headers['Authorization']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth_token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth_token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
}

registrarLog("Token recebido", ['token' => $auth_token]);

if ($auth_token !== $token) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    registrarLog("Erro: Token inválido");
    exit;
}

// Recebe os dados do POST
$raw_data = file_get_contents('php://input');
registrarLog("Dados brutos recebidos", ['raw_data' => $raw_data]);

$data = json_decode($raw_data, true);

// Verifica se o JSON é válido
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);
    registrarLog("Erro: JSON inválido - " . json_last_error_msg());
    exit;
}

// Adaptação para o modelo do Anota AI
if (isset($data['info'])) {
    $pedido = $data['info'];
} else {
    $pedido = $data;
}

// Verifica se o pedido tem ID
if (!isset($pedido['id']) && !isset($pedido['_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do pedido não encontrado']);
    registrarLog("Erro: ID do pedido não encontrado");
    exit;
}

// Obtém o ID do pedido
$anotai_id = isset($pedido['id']) ? $pedido['id'] : $pedido['_id'];

try {
    // Inicia a transação
    $pdo->beginTransaction();

    // Verifica se o pedido existe e obtém seus dados atuais
    $stmt = $pdo->prepare("SELECT id_order, check_order FROM o01_order WHERE external_id_order = ?");
    $stmt->execute([$anotai_id]);
    $pedidoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedidoExistente) {
        throw new Exception("Pedido não encontrado no banco de dados");
    }

    // Atualiza o status do pedido para cancelado (4)
    $stmt = $pdo->prepare("
        UPDATE o01_order 
        SET check_order = ?, 
            updatedAt = ? 
        WHERE external_id_order = ?
    ");

    $stmt->execute([
        4, // Status cancelado
        date('Y-m-d'),
        $anotai_id
    ]);

    // Commit da transação
    $pdo->commit();
    
    registrarLog("Pedido cancelado com sucesso", [
        'external_id_order' => $anotai_id,
        'id_order' => $pedidoExistente['id_order'],
        'status_anterior' => $pedidoExistente['check_order'],
        'novo_status' => 4
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Pedido cancelado com sucesso',
        'data' => [
            'id_order' => $pedidoExistente['id_order'],
            'external_id_order' => $anotai_id,
            'check_order' => 4,
            'processed_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    // Em caso de erro, faz rollback
    if (isset($pdo)) {
        $pdo->rollBack();
    }

    http_response_code(500);
    registrarLog("Erro ao cancelar pedido", [
        'external_id_order' => $anotai_id,
        'erro' => $e->getMessage()
    ]);
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao cancelar pedido: ' . $e->getMessage()
    ]);
}
