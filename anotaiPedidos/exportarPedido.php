<?php
header('Content-Type: application/json');

// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../admin/config/database.php';

// Função para registrar logs
function registrarLog($mensagem, $dados = null) {
    $log_file = __DIR__ . '/export_log.txt';
    $log = date('Y-m-d H:i:s') . " - " . $mensagem;
    if ($dados !== null) {
        $log .= "\n" . print_r($dados, true);
    }
    $log .= "\n----------------------------------------\n";
    file_put_contents($log_file, $log, FILE_APPEND);
}

// Recebe os dados do POST
$jsonData = file_get_contents('php://input');
registrarLog("Dados recebidos:", $jsonData);

$data = json_decode($jsonData, true);

if ($data === null) {
    $erro = "Erro ao decodificar JSON: " . json_last_error_msg();
    registrarLog($erro);
    http_response_code(400);
    echo json_encode(['error' => $erro]);
    exit;
}

try {
    // Inicia a transação
    $pdo->beginTransaction();
    registrarLog("Transação iniciada");

    // Contadores para o relatório
    $resultados = [
        'inseridos' => 0,
        'atualizados' => 0,
        'erros' => 0
    ];

    // Verifica se os dados estão no formato correto
    if (!is_array($data)) {
        throw new Exception('Formato de dados inválido');
    }

    foreach ($data as $pedido) {
        registrarLog("Processando pedido:", $pedido);

        // Verifica se o pedido já existe
        $stmtCheck = $pdo->prepare("SELECT * FROM o01_order WHERE external_id_order = ?");
        $stmtCheck->execute([$pedido['_id']]);
        $pedidoExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

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
                registrarLog("Cliente existente atualizado: " . $fk_id_client);
            } else {
                // Insere novo cliente
                $stmtClient = $pdo->prepare("INSERT INTO client (name_client, phone_client, external_id_client) VALUES (?, ?, ?)");
                $stmtClient->execute([
                    $pedido['customer']['name'] ?? null,
                    $pedido['customer']['phone'] ?? null,
                    $pedido['customer']['id'] ?? null
                ]);
                $fk_id_client = $pdo->lastInsertId();
                registrarLog("Novo cliente inserido: " . $fk_id_client);
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
                registrarLog("AVISO: Bairro não encontrado: " . $pedido['deliveryAddress']['neighborhood']);
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

                    registrarLog("Endereço existente atualizado: " . $enderecoExistente['id_address']);
                }

                $fk_id_address = $enderecoExistente['id_address'];
                registrarLog("Usando endereço existente: " . $fk_id_address);
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
                registrarLog("Novo endereço inserido: " . $fk_id_address);
            }
        }

        // Calcula o subtotal somando o total de cada item
        $subtotal = 0;
        if (isset($pedido['items']) && is_array($pedido['items'])) {
            foreach ($pedido['items'] as $item) {
                $subtotal += $item['total'] ?? 0;
            }
        }
        registrarLog("Subtotal calculado: " . $subtotal);

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

        if ($pedidoExistente) {
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

                registrarLog("Pedido atualizado por ter informações diferentes: " . $pedidoExistente['id_order']);
                $resultados['atualizados']++;
            } else {
                registrarLog("Pedido já existe e está atualizado: " . $pedidoExistente['id_order']);
            }
            
            // Define o ID do pedido para uso posterior
            $idPedido = $pedidoExistente['id_order'];
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
            $idPedido = $pdo->lastInsertId();
            
            // Verifica se o ID foi obtido corretamente
            if (!$idPedido || $idPedido <= 0) {
                throw new Exception("Erro ao obter ID do pedido inserido");
            }
            
            // Verifica se o pedido foi realmente inserido
            $stmtVerify = $pdo->prepare("SELECT id_order FROM o01_order WHERE id_order = ?");
            $stmtVerify->execute([$idPedido]);
            $pedidoVerificado = $stmtVerify->fetch();
            
            if (!$pedidoVerificado) {
                throw new Exception("Pedido não foi encontrado após inserção. ID: " . $idPedido);
            }
            
            registrarLog("Novo pedido inserido com ID: " . $idPedido);
            $resultados['inseridos']++;
        }

        // Processa os produtos do pedido
        if (isset($pedido['items']) && is_array($pedido['items']) && isset($idPedido) && $idPedido > 0) {
            registrarLog("Processando produtos para o pedido ID: " . $idPedido);
            
            // Remove produtos existentes do pedido (para evitar duplicatas)
            if ($idPedido > 0) {
                $stmtDeleteProducts = $pdo->prepare("DELETE FROM o02_order_products WHERE fk_id_order = ?");
                $stmtDeleteProducts->execute([$idPedido]);
                registrarLog("Produtos antigos removidos do pedido ID: " . $idPedido);
            }
            
            foreach ($pedido['items'] as $item) {
                // Busca o produto pelo ID externo
                $stmtProduct = $pdo->prepare("SELECT id_product FROM p02_products WHERE external_id_product = ?");
                $stmtProduct->execute([$item['internalId']]);
                $produto = $stmtProduct->fetch(PDO::FETCH_ASSOC);

                if ($produto && $produto['id_product'] > 0) {
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
                        $idPedido,
                        $produto['id_product'],
                        $item['quantity'],
                        $item['price'],
                        $item['total']
                    ]);

                    registrarLog("Produto adicionado ao pedido", [
                        'produto' => $item['name'],
                        'id_produto' => $produto['id_product'],
                        'quantidade' => $item['quantity'],
                        'preco' => $item['price'],
                        'total' => $item['total']
                    ]);
                } else {
                    registrarLog("AVISO: Produto não encontrado no banco de dados", [
                        'nome' => $item['name'],
                        'internal_id' => $item['internalId']
                    ]);
                }
            }
        }
    }

    // Commit da transação
    $pdo->commit();
    registrarLog("Transação finalizada com sucesso", $resultados);
    
    echo json_encode([
        'success' => true,
        'resultados' => $resultados
    ]);

} catch (Exception $e) {
    // Em caso de erro, faz rollback
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    $erro = 'Erro ao salvar no banco de dados: ' . $e->getMessage();
    registrarLog("ERRO: " . $erro);
    
    http_response_code(500);
    echo json_encode([
        'error' => $erro,
        'details' => $e->getMessage()
    ]);
}

// Função para mapear o status do Anota.ai para o nosso sistema
function mapearStatus($status) {
    switch ($status) {
        case 1: return 1;  // Em produção
        case 2: return 2;  // Pronto
        case 3: return 3;  // Finalizado
        case 4: return 4;  // Cancelado
        case 5: return 5;  // Negado
        case 6: return 6;  // Solicitação de cancelamento
        case -2: return -2; // Agendado aceito
        case 0: return 0;   // Em análise
        default: return 0;  // Em análise (padrão)
    }
}
?>
