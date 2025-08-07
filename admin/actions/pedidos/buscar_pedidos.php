<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

// Função para registrar logs
function registrarLog($mensagem, $tipo = 'info') {
    $data = date('Y-m-d H:i:s');
    $log = "[{$data}] [{$tipo}] {$mensagem}" . PHP_EOL;
    error_log($log, 3, '../../logs/pedidos_' . date('Y-m-d') . '.log');
}

registrarLog("Iniciando busca de pedidos");

if (!isset($_SESSION['user_id'])) {
    registrarLog("Acesso não autorizado - Usuário não autenticado", "erro");
    echo json_encode([
        'status' => 'error',
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

try {
    // Parâmetros de filtro
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null; // balcao ou delivery
    
    // Verificar se foi passado o parâmetro ano
    if (isset($_GET['ano'])) {
        $ano = $_GET['ano'];
        $data_inicio = $ano . '-01-01';
        $data_fim = $ano . '-12-31';
        registrarLog("Filtro por ano: {$ano}, data_inicio={$data_inicio}, data_fim={$data_fim}");
    } else {
        $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d');
        $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
    }
    
    // Verificar se foi passado um último ID
    $ultimo_id = isset($_GET['ultimo_id']) ? intval($_GET['ultimo_id']) : 0;
    
    registrarLog("Filtros aplicados: status={$status}, tipo={$tipo}, data_inicio={$data_inicio}, data_fim={$data_fim}");
    
    // Construir a consulta base
    $sql = "SELECT 
                p.id_pedido, 
                p.num_pedido, 
                p.data_pedido, 
                p.hora_pedido,
                p.status_pedido,
                p.status_pagamento,
                p.taxa_entrega,
                p.sub_total,
                c.id_cliente,
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
    
    // Adicionar filtros se fornecidos
    $params = [
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ];
    
    if ($status !== null) {
        $sql .= " AND p.status_pedido = :status";
        $params[':status'] = $status;
        registrarLog("Filtro por status: {$status}");
    }
    
    if ($tipo !== null) {
        if ($tipo === 'balcao') {
            $sql .= " AND ce.nome_entrega = 'Retirada no local'";
            registrarLog("Filtro por tipo: balcão");
        } else if ($tipo === 'delivery') {
            $sql .= " AND ce.nome_entrega != 'Retirada no local'";
            registrarLog("Filtro por tipo: delivery");
        }
    }
    
    // Se foi passado um último ID, modificar a consulta para buscar apenas pedidos mais recentes
    if ($ultimo_id > 0) {
        // Adicionar condição para buscar apenas pedidos com ID maior que o último conhecido
        $sql .= " AND p.id_pedido > :ultimo_id";
        $params[':ultimo_id'] = $ultimo_id;
    }
    
    $sql .= " ORDER BY p.data_pedido DESC, p.hora_pedido DESC";
    
    registrarLog("SQL gerado: {$sql}");
    registrarLog("Parâmetros: " . json_encode($params));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    registrarLog("Total de pedidos encontrados: " . count($pedidos));
    
    // Preparar resposta
    $resultado = [];
    foreach ($pedidos as $pedido) {
        registrarLog("Processando pedido ID: {$pedido['id_pedido']}, Número: {$pedido['num_pedido']}");
        
        // Determinar o tipo de pedido (balcão ou delivery)
        $tipo_pedido = ($pedido['nome_entrega'] === 'Retirada no local') ? 'balcao' : 'delivery';
        
        // Formatar endereço completo
        $endereco = $pedido['nome_entrega'];
        if ($tipo_pedido === 'delivery') {
            $endereco .= ', ' . $pedido['numero_entrega'] . ' - ' . $pedido['nome_bairro'];
        }
        
        // Buscar itens do pedido (simplificado)
        $sql_itens = "SELECT pi.id_pedido_item, p.nome_produto 
                      FROM pedido_itens pi
                      JOIN produto p ON pi.fk_produto_id = p.id_produto
                      WHERE pi.fk_pedido_id = :pedido_id";
        
        $stmt_itens = $pdo->prepare($sql_itens);
        $stmt_itens->execute([':pedido_id' => $pedido['id_pedido']]);
        $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
        
        registrarLog("Itens encontrados para o pedido {$pedido['id_pedido']}: " . count($itens));
        
        // Formatar descrição dos itens
        $descricao_itens = '';
        foreach ($itens as $item) {
            $descricao_itens .= $item['nome_produto'] . '; ';
        }
        $descricao_itens = rtrim($descricao_itens, '; ');
        
        // Calcular valor total
        $total = $pedido['sub_total'] + $pedido['taxa_entrega'];
        
        // Adicionar ao resultado
        $resultado[] = [
            'id' => $pedido['id_pedido'],
            'numero' => $pedido['num_pedido'],
            'data' => date('d/m/Y', strtotime($pedido['data_pedido'])),
            'hora' => $pedido['hora_pedido'],
            'status' => $pedido['status_pedido'],
            'status_pagamento' => $pedido['status_pagamento'],
            'cliente' => [
                'id' => $pedido['id_cliente'],
                'nome' => $pedido['nome_cliente'],
                'telefone' => $pedido['telefone_cliente']
            ],
            'endereco' => $endereco,
            'itens' => $descricao_itens,
            'subtotal' => $pedido['sub_total'],
            'taxa_entrega' => $pedido['taxa_entrega'],
            'total' => $total,
            'pagamento' => $pedido['metodo_pagamento'],
            'tipo' => $tipo_pedido
        ];
    }
    
    // Separar pedidos por status
    $pedidos_por_status = [
        'emAnalise' => array_filter($resultado, function($p) { return $p['status'] == 0; }),
        'emProducao' => array_filter($resultado, function($p) { return $p['status'] == 1; }),
        'prontosEntrega' => array_filter($resultado, function($p) { return $p['status'] == 2; })
    ];
    
    // Reindexar arrays
    foreach ($pedidos_por_status as &$lista) {
        $lista = array_values($lista);
    }
    
    registrarLog("Pedidos em análise: " . count($pedidos_por_status['emAnalise']));
    registrarLog("Pedidos em produção: " . count($pedidos_por_status['emProducao']));
    registrarLog("Pedidos prontos para entrega: " . count($pedidos_por_status['prontosEntrega']));
    
    // Verificar se há pedidos em análise para log detalhado
    if (count($pedidos_por_status['emAnalise']) > 0) {
        foreach ($pedidos_por_status['emAnalise'] as $pedido) {
            registrarLog("Pedido em análise - ID: {$pedido['id']}, Número: {$pedido['numero']}, Cliente: {$pedido['cliente']['nome']}");
        }
    }
    
    registrarLog("Busca de pedidos concluída com sucesso");
    
    echo json_encode([
        'status' => 'success',
        'pedidos' => $pedidos_por_status
    ]);
    
} catch (Exception $e) {
    registrarLog("ERRO: " . $e->getMessage(), "erro");
    registrarLog("Stack trace: " . $e->getTraceAsString(), "erro");
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar pedidos: ' . $e->getMessage()
    ]);
}
?> 