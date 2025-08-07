<?php
session_start();
include_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

try {
    // Parâmetros de filtro
    $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
    $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
    $status = isset($_GET['status']) ? $_GET['status'] : 3; // Por padrão, exporta apenas pedidos finalizados
    
    // Consulta para buscar pedidos
    $sql = "SELECT 
                p.id_pedido, 
                p.num_pedido, 
                p.data_pedido, 
                p.hora_pedido,
                p.status_pedido,
                p.status_pagamento,
                p.taxa_entrega,
                p.sub_total,
                (p.sub_total + p.taxa_entrega) as total,
                c.nome_cliente,
                c.telefone_cliente,
                ce.nome_entrega,
                ce.numero_entrega,
                cb.nome_bairro,
                pag.metodo_pagamento
            FROM pedidos p
            LEFT JOIN clientes c ON p.fk_cliente_id = c.id_cliente
            LEFT JOIN cliente_entrega ce ON p.fk_entrega_id = ce.id_entrega
            LEFT JOIN cliente_bairro cb ON ce.fk_Bairro_id_bairro = cb.id_bairro
            LEFT JOIN pagamento pag ON p.fk_pagamento_id = pag.id_pagamento
            WHERE p.data_pedido BETWEEN :data_inicio AND :data_fim";
    
    // Adicionar filtro de status se fornecido
    if ($status !== null) {
        $sql .= " AND p.status_pedido = :status";
    }
    
    $sql .= " ORDER BY p.data_pedido DESC, p.hora_pedido DESC";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ];
    
    if ($status !== null) {
        $params[':status'] = $status;
    }
    
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar arquivo CSV
    $filename = 'pedidos_' . date('d-m-Y') . '.csv';
    
    // Definir cabeçalhos para download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Criar arquivo CSV
    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos das colunas
    fputcsv($output, [
        'ID',
        'Número',
        'Data',
        'Hora',
        'Cliente',
        'Telefone',
        'Endereço',
        'Forma de Pagamento',
        'Status Pagamento',
        'Subtotal',
        'Taxa de Entrega',
        'Total'
    ]);
    
    // Mapear status de pagamento
    $status_pagamento_map = [
        0 => 'Pendente',
        1 => 'Pago'
    ];
    
    // Adicionar dados dos pedidos
    foreach ($pedidos as $pedido) {
        // Formatar endereço
        $endereco = $pedido['nome_entrega'];
        if ($pedido['nome_entrega'] !== 'Retirada no local') {
            $endereco .= ', ' . $pedido['numero_entrega'] . ' - ' . $pedido['nome_bairro'];
        }
        
        // Formatar valores monetários
        $subtotal = number_format($pedido['sub_total'], 2, ',', '.');
        $taxa_entrega = number_format($pedido['taxa_entrega'], 2, ',', '.');
        $total = number_format($pedido['total'], 2, ',', '.');
        
        // Status de pagamento formatado
        $status_pagamento = $status_pagamento_map[$pedido['status_pagamento']] ?? 'Desconhecido';
        
        fputcsv($output, [
            $pedido['id_pedido'],
            $pedido['num_pedido'],
            date('d/m/Y', strtotime($pedido['data_pedido'])),
            $pedido['hora_pedido'],
            $pedido['nome_cliente'],
            $pedido['telefone_cliente'],
            $endereco,
            $pedido['metodo_pagamento'],
            $status_pagamento,
            $subtotal,
            $taxa_entrega,
            $total
        ]);
    }
    
    // Registrar exportação no log
    $log_sql = "INSERT INTO log_exportacao (fk_user_id, tipo, filtro_inicio, filtro_fim, quantidade, data_exportacao) 
                VALUES (:user_id, 'pedidos', :data_inicio, :data_fim, :quantidade, NOW())";
    
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim,
        ':quantidade' => count($pedidos)
    ]);
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao exportar pedidos: ' . $e->getMessage()
    ]);
    exit;
}
?> 