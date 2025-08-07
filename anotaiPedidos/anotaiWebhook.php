<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../admin/config/database.php';

// Configura o timezone
date_default_timezone_set('America/Sao_Paulo');

// Token de autenticação
$token = 'eyJhbGciOiJIUzI1NiJ9.eyJpZHBhcnRuZXIiOiI2N2MwNGFmYjU5NWY2MzAwMTI4ODUwNDUiLCJpZHBhZ2UiOiI2NDM0MGM5YzYyODg0MDAwMTJhNmQ1MWMifQ.YYiAKDdkokeFpDEYy0bAkau5BYt7X4JqVoQPkK1Qjxc';

// Função para registrar logs
function registrarLog($mensagem, $dados = null) {
    $log_file = __DIR__ . '/webhook_log.json';
    
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

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
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

if ($auth_token !== $token) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

// Recebe os dados do POST
$raw_data = file_get_contents('php://input');
$data = json_decode($raw_data, true);

// Verifica se o JSON é válido
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);
    registrarLog('JSON inválido', ['erro' => json_last_error_msg()]);
    exit;
}

// Registra os dados brutos
registrarLog('Dados recebidos do webhook', $data);

try {
    // Inicia a transação
    $pdo->beginTransaction();
    
    // Adaptação para o modelo do Anota AI
    if (isset($data['info'])) {
        $pedido = $data['info'];
    } else {
        $pedido = $data;
    }

    // Processa o cliente primeiro
    if (isset($pedido['customer'])) {
        $fk_id_client = null;
        $clienteEncontrado = false;

        // Primeiro tenta encontrar pelo external_id se existir
        if (!empty($pedido['customer']['id'])) {
            $stmtCheckClient = $pdo->prepare("SELECT id_client FROM client WHERE external_id_client = ?");
            $stmtCheckClient->execute([$pedido['customer']['id']]);
            $clienteExistente = $stmtCheckClient->fetch();
            
            if ($clienteExistente) {
                $clienteEncontrado = true;
                $fk_id_client = $clienteExistente['id_client'];
            }
        }

        // Se não encontrou pelo external_id e não tem telefone, tenta encontrar pelo nome
        if (!$clienteEncontrado && empty($pedido['customer']['phone']) && !empty($pedido['customer']['name'])) {
            $stmtCheckClientName = $pdo->prepare("SELECT id_client FROM client WHERE name_client = ? AND (phone_client IS NULL OR phone_client = '') AND (external_id_client IS NULL OR external_id_client = '')");
            $stmtCheckClientName->execute([$pedido['customer']['name']]);
            $clienteExistentePorNome = $stmtCheckClientName->fetch();

            if ($clienteExistentePorNome) {
                $clienteEncontrado = true;
                $fk_id_client = $clienteExistentePorNome['id_client'];
            }
        }

        // Se não encontrou por nenhum método anterior, tenta pelo telefone
        if (!$clienteEncontrado && !empty($pedido['customer']['phone'])) {
            $stmtCheckClientPhone = $pdo->prepare("SELECT id_client FROM client WHERE phone_client = ?");
            $stmtCheckClientPhone->execute([$pedido['customer']['phone']]);
            $clienteExistentePorTelefone = $stmtCheckClientPhone->fetch();

            if ($clienteExistentePorTelefone) {
                $clienteEncontrado = true;
                $fk_id_client = $clienteExistentePorTelefone['id_client'];
            }
        }

        if ($clienteEncontrado) {
            // Atualiza cliente existente
            $stmtClient = $pdo->prepare("UPDATE client SET name_client = ?, phone_client = ?, external_id_client = ? WHERE id_client = ?");
            $stmtClient->execute([
                $pedido['customer']['name'] ?? null,
                $pedido['customer']['phone'] ?? null,
                $pedido['customer']['id'] ?? null,
                $fk_id_client
            ]);
            registrarLog('Cliente existente atualizado', ['id_client' => $fk_id_client]);
        } else {
            // Insere novo cliente
            $stmtClient = $pdo->prepare("INSERT INTO client (name_client, phone_client, external_id_client) VALUES (?, ?, ?)");
            $stmtClient->execute([
                $pedido['customer']['name'] ?? null,
                $pedido['customer']['phone'] ?? null,
                $pedido['customer']['id'] ?? null
            ]);
            $fk_id_client = $pdo->lastInsertId();
            registrarLog('Novo cliente inserido', ['id_client' => $fk_id_client]);
        }
    }

    // Processa o endereço do cliente se existir
    $fk_id_address = null;
    if (isset($pedido['deliveryAddress']) && isset($pedido['type']) && $pedido['type'] === 'DELIVERY') {
        // Busca o ID do bairro pelo nome
        $stmtDistrict = $pdo->prepare("SELECT id_district FROM client_district WHERE name_district = ?");
        $stmtDistrict->execute([$pedido['deliveryAddress']['neighborhood']]);
        $district = $stmtDistrict->fetch(PDO::FETCH_ASSOC);
        
        if (!$district) {
            registrarLog("AVISO: Bairro não encontrado", ['bairro' => $pedido['deliveryAddress']['neighborhood']]);
        }

        // Primeiro verifica se existe um endereço com a mesma rua e número
        $stmtCheckAddress = $pdo->prepare("
            SELECT id_address, complement_address, reference_address, fk_id_district 
            FROM client_address 
            WHERE fk_id_client = ? 
            AND name_address = ? 
            AND number_address = ?
        ");
        
        $stmtCheckAddress->execute([
            $fk_id_client,
            $pedido['deliveryAddress']['streetName'],
            $pedido['deliveryAddress']['streetNumber']
        ]);
        
        $enderecoExistente = $stmtCheckAddress->fetch(PDO::FETCH_ASSOC);

        if ($enderecoExistente) {
            // Verifica se precisa atualizar complemento, referência ou bairro
            $needsUpdate = 
                $enderecoExistente['complement_address'] !== $pedido['deliveryAddress']['complement'] ||
                $enderecoExistente['reference_address'] !== $pedido['deliveryAddress']['reference'] ||
                $enderecoExistente['fk_id_district'] !== ($district ? $district['id_district'] : null);

            if ($needsUpdate) {
                // Atualiza os campos que podem ter mudado
                $stmtUpdateAddress = $pdo->prepare("
                    UPDATE client_address 
                    SET complement_address = ?,
                        reference_address = ?,
                        fk_id_district = ?
                    WHERE id_address = ?
                ");

                $stmtUpdateAddress->execute([
                    $pedido['deliveryAddress']['complement'],
                    $pedido['deliveryAddress']['reference'],
                    $district ? $district['id_district'] : null,
                    $enderecoExistente['id_address']
                ]);

                registrarLog("Endereço existente atualizado", ['id_address' => $enderecoExistente['id_address']]);
            }

            $fk_id_address = $enderecoExistente['id_address'];
            registrarLog("Usando endereço existente", ['id_address' => $fk_id_address]);
        } else {
            // Insere novo endereço
            $stmtAddress = $pdo->prepare("
                INSERT INTO client_address (
                    name_address,
                    number_address,
                    complement_address,
                    reference_address,
                    fk_id_client,
                    fk_id_district
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmtAddress->execute([
                $pedido['deliveryAddress']['streetName'],
                $pedido['deliveryAddress']['streetNumber'],
                $pedido['deliveryAddress']['complement'],
                $pedido['deliveryAddress']['reference'],
                $fk_id_client,
                $district ? $district['id_district'] : null
            ]);

            $fk_id_address = $pdo->lastInsertId();
            registrarLog("Novo endereço inserido", ['id_address' => $fk_id_address]);
        }
    }

    // Calcula o subtotal somando o total de cada item
    $subtotal = 0;
    if (isset($pedido['items']) && is_array($pedido['items'])) {
        foreach ($pedido['items'] as $item) {
            $subtotal += $item['total'] ?? 0;
        }
    }
    registrarLog("Subtotal calculado", ['subtotal' => $subtotal]);

    // Prepara os dados do pedido
    $dadosPedido = [
        'shortReference_order' => $pedido['shortReference'],
        'date_order' => date('Y-m-d', strtotime($pedido['createdAt'])),
        'time_order' => date('H:i:s', strtotime($pedido['createdAt'])),
        'subtotal_order' => $subtotal,
        'delivery_fee' => $pedido['deliveryFee'] ?? null,
        'total_order' => $pedido['total'],
        'check_order' => $pedido['check'],
        'external_id_order' => $pedido['_id'],
        'fk_id_client' => $fk_id_client ?? null,
        'fk_id_address' => $fk_id_address,
        'createdAt' => date('Y-m-d', strtotime($pedido['createdAt'])),
        'updatedAt' => date('Y-m-d', strtotime($pedido['updatedAt']))
    ];

    // Verifica se o pedido já existe
    $stmtCheck = $pdo->prepare("SELECT * FROM o01_order WHERE external_id_order = ?");
    $stmtCheck->execute([$pedido['_id']]);
    $pedidoExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    // Variável para armazenar o ID do pedido
    $id_order = null;

    if ($pedidoExistente) {
        // Usa o ID do pedido existente
        $id_order = $pedidoExistente['id_order'];
        
        // Verifica se há diferenças nos dados
        $needsUpdate = false;
        foreach ($dadosPedido as $key => $value) {
            if ($pedidoExistente[$key] != $value) {
                $needsUpdate = true;
                break;
            }
        }

        if ($needsUpdate) {
            // Atualiza apenas se houver diferenças
            $stmt = $pdo->prepare("UPDATE o01_order SET 
                shortReference_order = ?,
                date_order = ?,
                time_order = ?,
                subtotal_order = ?,
                delivery_fee = ?,
                total_order = ?,
                check_order = ?,
                fk_id_client = ?,
                fk_id_address = ?,
                updatedAt = ?
                WHERE external_id_order = ?");

            $stmt->execute([
                $dadosPedido['shortReference_order'],
                $dadosPedido['date_order'],
                $dadosPedido['time_order'],
                $dadosPedido['subtotal_order'],
                $dadosPedido['delivery_fee'],
                $dadosPedido['total_order'],
                $dadosPedido['check_order'],
                $dadosPedido['fk_id_client'],
                $dadosPedido['fk_id_address'],
                $dadosPedido['updatedAt'],
                $dadosPedido['external_id_order']
            ]);

            registrarLog('Pedido atualizado', [
                'id_order' => $id_order,
                'external_id' => $pedido['_id']
            ]);
        } else {
            registrarLog('Pedido já existe e está atualizado', [
                'id_order' => $id_order,
                'external_id' => $pedido['_id']
            ]);
        }
    } else {
        // Insere novo pedido
        $stmt = $pdo->prepare("INSERT INTO o01_order (
            shortReference_order,
            date_order,
            time_order,
            subtotal_order,
            delivery_fee,
            total_order,
            check_order,
            external_id_order,
            fk_id_client,
            fk_id_address,
            createdAt,
            updatedAt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $dadosPedido['shortReference_order'],
            $dadosPedido['date_order'],
            $dadosPedido['time_order'],
            $dadosPedido['subtotal_order'],
            $dadosPedido['delivery_fee'],
            $dadosPedido['total_order'],
            $dadosPedido['check_order'],
            $dadosPedido['external_id_order'],
            $dadosPedido['fk_id_client'],
            $dadosPedido['fk_id_address'],
            $dadosPedido['createdAt'],
            $dadosPedido['updatedAt']
        ]);

        // Obtém o ID do pedido recém-inserido
        $id_order = $pdo->lastInsertId();

        registrarLog('Novo pedido inserido', [
            'id_order' => $id_order,
            'external_id' => $pedido['_id']
        ]);
    }

    // Processa os produtos do pedido
    if (isset($pedido['items']) && is_array($pedido['items']) && $id_order !== null) {
        foreach ($pedido['items'] as $item) {
            // Busca o produto pelo ID externo
            $stmtProduct = $pdo->prepare("SELECT id_product FROM p02_products WHERE external_id_product = ?");
            $stmtProduct->execute([$item['internalId']]);
            $produto = $stmtProduct->fetch(PDO::FETCH_ASSOC);

            if ($produto) {
                // Insere o produto no pedido
                $stmtOrderProduct = $pdo->prepare("
                    INSERT INTO o02_order_products (
                        fk_id_order,
                        fk_id_product,
                        quantity_product,
                        price_product,
                        totalPrice_product
                    ) VALUES (?, ?, ?, ?, ?)
                ");

                $stmtOrderProduct->execute([
                    $id_order,
                    $produto['id_product'],
                    $item['quantity'],
                    $item['price'],
                    $item['total']
                ]);

                registrarLog('Produto adicionado ao pedido', [
                    'produto' => $item['name'],
                    'id_produto' => $produto['id_product'],
                    'id_order' => $id_order,
                    'quantidade' => $item['quantity'],
                    'preco' => $item['price'],
                    'total' => $item['total']
                ]);
            } else {
                registrarLog('AVISO: Produto não encontrado no banco de dados', [
                    'nome' => $item['name'],
                    'internal_id' => $item['internalId']
                ]);
            }
        }
    } elseif ($id_order === null) {
        registrarLog('ERRO: ID do pedido não foi definido, produtos não serão inseridos', [
            'external_id' => $pedido['_id']
        ]);
    }

    // Commit da transação
    $pdo->commit();

    // Resposta de sucesso
    echo json_encode([
        'status' => 'success',
        'message' => 'Pedido processado com sucesso',
        'processed_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    // Em caso de erro, faz rollback
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    registrarLog('Erro ao processar pedido', [
        'erro' => $e->getMessage(),
        'dados' => $data
    ]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar pedido: ' . $e->getMessage()
    ]);
}
