<?php
header('Content-Type: application/json');

// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../admin/config/database.php';

// Função para registrar logs
function registrarLog($mensagem, $dados = null) {
    $log_file = __DIR__ . '/teste_log.txt';
    $log = date('Y-m-d H:i:s') . " - " . $mensagem;
    if ($dados !== null) {
        $log .= "\n" . print_r($dados, true);
    }
    $log .= "\n----------------------------------------\n";
    file_put_contents($log_file, $log, FILE_APPEND);
}

try {
    // Inicia a transação
    $pdo->beginTransaction();
    registrarLog("Transação iniciada");

    // Dados de teste
    $dadosPedido = [
        'shortReference_order' => 'TESTE001',
        'date_order' => date('Y-m-d'),
        'time_order' => date('H:i:s'),
        'subtotal_order' => 25.00,
        'delivery_fee' => 5.00,
        'total_order' => 30.00,
        'check_order' => 30.00,
        'external_id_order' => 'teste_' . time(),
        'fk_id_client' => null,
        'fk_id_address' => null,
        'createdAt' => date('Y-m-d'),
        'updatedAt' => date('Y-m-d')
    ];

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
    registrarLog("ID do pedido obtido: " . $idPedido);

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

    registrarLog("Pedido verificado com sucesso. ID: " . $idPedido);

    // Commit da transação
    $pdo->commit();
    registrarLog("Transação finalizada com sucesso");

    echo json_encode([
        'success' => true,
        'id_pedido' => $idPedido,
        'message' => 'Pedido inserido com sucesso'
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
?> 