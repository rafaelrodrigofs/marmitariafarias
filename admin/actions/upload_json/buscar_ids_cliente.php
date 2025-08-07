<?php
require_once '../../config/database.php';

// Adiciona log para debug
error_log("Iniciando busca de IDs para telefone: " . ($_GET['telefone'] ?? 'não informado'));

$telefone = preg_replace('/[^0-9]/', '', $_GET['telefone'] ?? '');
error_log("Telefone após limpeza: " . $telefone); // Log para debug

$response = ['success' => false];

try {
    // Buscar ID do cliente usando apenas números
    $stmt = $pdo->prepare("SELECT id_cliente FROM clientes WHERE telefone_cliente = ?");
    $stmt->execute([$telefone]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Resultado da busca do cliente: " . print_r($cliente, true));

    if ($cliente) {
        // Buscar ID do endereço mais recente do cliente
        $stmt = $pdo->prepare("
            SELECT id_entrega 
            FROM cliente_entrega 
            WHERE fk_Cliente_id_cliente = ? 
            ORDER BY id_entrega DESC 
            LIMIT 1
        ");
        $stmt->execute([$cliente['id_cliente']]);
        $endereco = $stmt->fetch(PDO::FETCH_ASSOC);

        $response = [
            'success' => true,
            'telefone' => $telefone,
            'cliente_id' => $cliente['id_cliente'],
            'endereco_id' => $endereco ? $endereco['id_entrega'] : null
        ];
        
        error_log("Resposta final: " . print_r($response, true));
    } else {
        $response['error'] = 'Cliente não encontrado';
        error_log("Cliente não encontrado para o telefone: " . $telefone);
    }
} catch (PDOException $e) {
    error_log("Erro PDO: " . $e->getMessage());
    $response['error'] = 'Erro ao buscar dados: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    $response['error'] = 'Erro inesperado: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response); 