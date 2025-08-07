<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include_once '../config/database.php';

// Período selecionado
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'dia';
$data_especifica = isset($_GET['data']) ? $_GET['data'] : null;
$mes_selecionado = isset($_GET['mes']) ? $_GET['mes'] : null; // Nova variável
$data_inicio = null;
$data_fim = null;

// Cálculo das datas baseado no período
if ($data_especifica) {
    $data_inicio = $data_especifica;
    $data_fim = $data_especifica;
} elseif ($mes_selecionado) {
    // Lógica para mês selecionado
    $data_inicio = date('Y-m-01', strtotime($mes_selecionado));
    $data_fim = date('Y-m-t', strtotime($mes_selecionado));
} else {
    switch ($periodo) {
        case 'ontem':
            $data_inicio = date('Y-m-d', strtotime('yesterday'));
            $data_fim = date('Y-m-d', strtotime('yesterday'));
            break;
        case 'dia':
            $data_inicio = date('Y-m-d');
            $data_fim = date('Y-m-d');
            break;
        case 'semana':
            $data_inicio = date('Y-m-d', strtotime('monday this week'));
            $data_fim = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'mes':
            $data_inicio = date('Y-m-d', strtotime('first day of this month'));
            $data_fim = date('Y-m-d', strtotime('last day of this month'));
            break;
        case '2024':
            $data_inicio = '2024-01-01';
            $data_fim = '2024-12-31';
            break;
        case 'ano':
            $data_inicio = '2025-01-01';
            $data_fim = '2025-12-31';
            break;
        case '2023':
            $data_inicio = '2023-01-01';
            $data_fim = '2023-12-31';
            break;
        case 'todas':
            // Busca o primeiro pedido do sistema
            $sql_primeiro = "SELECT MIN(data_pedido) as primeira_data FROM pedidos";
            $stmt = $pdo->query($sql_primeiro);
            $primeira_data = $stmt->fetch(PDO::FETCH_ASSOC)['primeira_data'];
            
            // Busca o último pedido do sistema
            $sql_ultimo = "SELECT MAX(data_pedido) as ultima_data FROM pedidos";
            $stmt = $pdo->query($sql_ultimo);
            $ultima_data = $stmt->fetch(PDO::FETCH_ASSOC)['ultima_data'];
            
            $data_inicio = date('Y-m-d', strtotime($primeira_data));
            $data_fim = date('Y-m-d', strtotime($ultima_data));
            break;
    }
}

// Query com faixas de valor e top clientes de cada faixa
try {
    $sql = "WITH cliente_totais AS (
                SELECT 
                    c.id_cliente,
                    c.nome_cliente,
                    c.telefone_cliente,
                    COUNT(p.id_pedido) as total_pedidos,
                    SUM(p.sub_total + p.taxa_entrega) as valor_total,
                    CASE 
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 10000 THEN 'Acima de R$ 10.000'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 5000 THEN 'R$ 5.000 a R$ 9.999'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 2000 THEN 'R$ 2.000 a R$ 4.999'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 1000 THEN 'R$ 1.000 a R$ 1.999'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 500 THEN 'R$ 500 a R$ 999'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 300 THEN 'R$ 300 a R$ 499'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 200 THEN 'R$ 200 a R$ 299'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 150 THEN 'R$ 150 a R$ 199'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 100 THEN 'R$ 100 a R$ 149'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 50 THEN 'R$ 50 a R$ 99'
                        WHEN SUM(p.sub_total + p.taxa_entrega) >= 30 THEN 'R$ 30 a R$ 49'
                        ELSE 'Até R$ 29'
                    END as faixa_valor
                FROM pedidos p
                JOIN clientes c ON p.fk_cliente_id = c.id_cliente
                WHERE DATE(p.data_pedido) BETWEEN ? AND ?
                GROUP BY c.id_cliente, c.nome_cliente
            ),
            resumo_faixas AS (
                SELECT 
                    faixa_valor,
                    COUNT(DISTINCT id_cliente) as total_clientes,
                    SUM(total_pedidos) as total_pedidos,
                    SUM(valor_total) as valor_total,
                    MIN(valor_total) as valor_minimo,
                    MAX(valor_total) as valor_maximo,
                    AVG(valor_total) as valor_medio
                FROM cliente_totais
                GROUP BY faixa_valor
            ),
            top_clientes AS (
                SELECT 
                    ct.*,
                    ROW_NUMBER() OVER (PARTITION BY ct.faixa_valor ORDER BY ct.valor_total DESC) as ranking
                FROM cliente_totais ct
            )
            SELECT 
                rf.*,
                GROUP_CONCAT(
                    CONCAT(
                        tc.id_cliente, '|', 
                        tc.nome_cliente, '|',
                        tc.total_pedidos, '|',
                        tc.valor_total)
                    ORDER BY tc.valor_total DESC
                    SEPARATOR ';'
                ) as top_clientes
            FROM resumo_faixas rf
            LEFT JOIN top_clientes tc ON rf.faixa_valor = tc.faixa_valor
            GROUP BY rf.faixa_valor, rf.total_clientes, rf.total_pedidos, 
                     rf.valor_total, rf.valor_minimo, rf.valor_maximo, rf.valor_medio
            ORDER BY rf.valor_minimo DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data_inicio, $data_fim]);
    $faixas_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adiciona nova query para buscar evolução das taxas de entrega
    $sql_taxa_entrega = "SELECT 
        DATE(data_pedido) as data,
        SUM(taxa_entrega) as total_taxa_entrega
        FROM pedidos 
        WHERE DATE(data_pedido) BETWEEN ? AND ?
        GROUP BY DATE(data_pedido)
        ORDER BY data_pedido";

    $stmt = $pdo->prepare($sql_taxa_entrega);
    $stmt->execute([$data_inicio, $data_fim]);
    $taxa_entrega_por_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao buscar faixas de clientes: ' . $e->getMessage());
    $faixas_clientes = [];
}

function parseTopClientes($top_clientes_str) {
    if (empty($top_clientes_str)) return [];
    
    $clientes = [];
    $lista = explode(';', $top_clientes_str);
    
    foreach ($lista as $cliente) {
        $dados = explode('|', $cliente);
        if (count($dados) >= 3) {
            $clientes[] = [
                'id' => $dados[0],
                'nome' => $dados[1],
                'pedidos' => $dados[2],
                'valor' => $dados[3]
            ];
        }
    }
    
    return $clientes;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Geral - Lunch&Fit</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .card-header h2 {
            font-size: 18px;
            color: #333;
            margin: 0;
        }

        .card-header i {
            margin-right: 10px;
            color: #2196F3;
        }

        .faixa-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }

        .faixa-item:hover {
            background-color: #f8f9fa;
        }

        .faixa-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .faixa-titulo {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        .faixa-clientes {
            color: #2196F3;
            font-weight: 500;
        }

        .faixa-detalhes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            font-size: 14px;
            color: #666;
        }

        .detalhe-item {
            display: flex;
            flex-direction: column;
        }

        .detalhe-label {
            font-size: 12px;
            color: #888;
            margin-bottom: 4px;
        }

        .detalhe-valor {
            color: #333;
            font-weight: 500;
        }

        .top-clientes {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #eee;
        }

        .top-clientes h4 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .top-clientes-list {
            display: grid;
            gap: 10px;
        }

        .top-cliente-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .cliente-ranking {
            font-weight: bold;
            color: #2196F3;
            min-width: 30px;
        }

        .cliente-nome {
            flex: 1;
            font-weight: 500;
        }

        .cliente-stats {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 13px;
        }

        .cliente-stats span:last-child {
            color: #fff;
            font-weight: 500;
        }

        .period-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            justify-content: center;
            width: 100%;
            margin-bottom: 20px;
        }

        .period-btn {
            flex: 1;
            min-width: 0;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            color: #2196F3;
            background: white;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .period-btn:hover {
            background: #2196F3;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.15);
        }

        .period-btn.active {
            background: #2196F3;
            color: white;
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.15);
        }

        .date-picker {
            width: 100%;
            margin-top: 8px;
            padding: 8px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #2196F3;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            text-align: center;
        }

        .date-picker:hover, .date-picker:focus {
            border-color: #2196F3;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .period-selector {
                padding: 12px 8px;
                gap: 4px;
                margin: 0;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            .period-btn {
                padding: 8px 12px;
                font-size: 0.85rem;
                flex: 1;
                min-width: auto;
            }

            .date-picker {
                width: 100%;
                margin-top: 8px;
                text-align: center;
            }
            .card{
                margin-top: 10px;
            }
        }

        @media (max-width: 360px) {
            .period-btn {
                padding: 8px 8px;
                font-size: 0.8rem;
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 20px;
            border-radius: 12px;
            width: 95%; /* Aumentado de 90% para 95% */
            max-width: 1400px; /* Aumentado de 1200px para 1400px */
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #2196F3;
        }

        .cliente-metricas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metrica-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .metrica-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .metrica-card p {
            color: #2196F3;
            font-size: 24px;
            font-weight: bold;
        }

        .graficos-container {
            display: flex;
            flex-direction: column; /* Mudado de grid para column */
            gap: 20px;
            margin: 20px 0;
        }

        .grafico-box {
            width: 100%; /* Ocupar toda largura disponível */
            margin-bottom: 20px;
        }

        .grafico-box:first-child {
            height: 500px; /* Aumentado de 300px para 500px */
        }

        .produtos-populares {
            max-height: none; /* Removido o limite de altura */
        }

        /* Ajuste responsivo */
        @media (max-width: 768px) {
            .modal-content {
                margin: 0;
                width: 100%;
                height: 100%;
                border-radius: 0;
                overflow-y: auto;
            }

            .grafico-box:first-child {
                height: 400px; /* Um pouco menor em telas pequenas */
            }
        }

        .ultimos-pedidos {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .pedido-item {
            display: flex;
            gap: 15px;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .pedido-quantidade {
            font-weight: bold;
            min-width: 50px;
        }

        .pedido-produtos {
            flex: 1;
        }

        .item-linha {
            margin-bottom: 5px;
        }

        .pedido-valor {
            color: #2196F3;
            font-weight: 500;
        }

        /* Adicionar estilo para destacar intervalos longos */
        .metrica-card p.alerta {
            color: #ff5722;
        }

        .metrica-card p.atencao {
            color: #ff9800;
        }

        .afinidade-container {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .afinidade-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .afinidade-produtos {
            flex: 1;
        }

        .afinidade-frequencia {
            background: #2196F3;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        #segmentoCliente {
            font-weight: bold;
        }

        #segmentoCliente.Champions { color: #4CAF50; }
        #segmentoCliente.Leais { color: #2196F3; }
        #segmentoCliente.Novos { color: #9C27B0; }
        #segmentoCliente.EmRisco { color: #FF9800; }
        #segmentoCliente.Perdidos { color: #F44336; }
        #segmentoCliente.Regulares { color: #607D8B; }
        #segmentoCliente.AltoRisco { color: #E91E63; }

        .produtos-populares {
            max-height: 300px;
            overflow-y: auto;
        }

        .produto-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }

        .produto-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .produto-nome {
            font-weight: 600;
            color: #333;
        }

        .produto-total {
            color: #2196F3;
            font-weight: 500;
        }

        .combinacoes-lista {
            font-size: 13px;
            color: #666;
            margin-left: 15px;
        }

        .combinacao-item {
            padding: 5px 0;
            border-left: 2px solid #eee;
            padding-left: 10px;
            margin: 5px 0;
        }

        .combinacao-item strong {
            display: block;
            margin-bottom: 5px;
        }

        .combinacao-subitem {
            padding-left: 15px;
            margin: 3px 0;
            color: #666;
        }

        .combinacao-frequencia {
            color: #2196F3;
            font-weight: 500;
        }

        .btn-primary {
            background-color: #2196F3;
            border-color: #2196F3;
            padding: 8px 16px;
            border-radius: 4px;
        }
        
        .btn-primary:hover {
            background-color: #1976D2;
            border-color: #1976D2;
        }
        
        .me-2 {
            margin-right: 8px;
        }

        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            width: 100%;
        }

        .clientes-rfm-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .cliente-rfm-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .cliente-rfm-item:hover {
            background-color: #f8f9fa;
            border-left: 3px solid #2196F3!important;
        }

        .cliente-info {
            flex: 1;
        }

        .cliente-nome {
            font-weight: 500;
            color: #333;
            margin-bottom: 4px;
        }

        .segmento-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            color: white;
        }

        .segmento-badge.champions { background-color: #4CAF50; }
        .segmento-badge.leais { background-color: #2196F3; }
        .segmento-badge.regulares { background-color: #607D8B; }
        .segmento-badge.em-risco { background-color: #FF9800; }
        .segmento-badge.perdidos { background-color: #F44336; }
        .segmento-badge.alto-risco { background-color: #E91E63; }

        .rfm-metrics {
            display: flex;
            gap: 12px;
            font-size: 13px;
            color: #666;
        }

        .rfm-metrics span {
            background: #f1f1f1;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .grupo-rfm {
            margin-bottom: 20px;
        }

        .grupo-rfm-header {
            padding: 10px;
            margin-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .grupo-rfm-header .segmento-badge {
            font-size: 14px;
            padding: 6px 12px;
        }

        .grupo-rfm-clientes {
            padding-left: 15px;
        }

        .cliente-rfm-item {
            border-left: 3px solid transparent;
        }

        .cliente-rfm-item:hover {
            border-left-color: #2196F3;
        }

        /* Ajustar cores específicas para cada grupo */
        .grupo-rfm:has(.segmento-badge.champions) .cliente-rfm-item:hover {
            border-left-color: #4CAF50;
        }

        .grupo-rfm:has(.segmento-badge.leais) .cliente-rfm-item:hover {
            border-left-color: #2196F3;
        }

        .grupo-rfm:has(.segmento-badge.regulares) .cliente-rfm-item:hover {
            border-left-color: #607D8B;
        }

        .grupo-rfm:has(.segmento-badge.em-risco) .cliente-rfm-item:hover {
            border-left-color: #FF9800;
        }

        .grupo-rfm:has(.segmento-badge.perdidos) .cliente-rfm-item:hover {
            border-left-color: #F44336;
        }

        .grupo-rfm:has(.segmento-badge.alto-risco) .cliente-rfm-item:hover {
            border-left-color: #E91E63;
        }

        .grupos-rfm-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .grupo-rfm {
            flex: 1;
            min-width: 200px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .grupo-rfm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .grupo-rfm-header {
            padding: 15px;
            text-align: center;
            border-bottom: none;
        }

        .grupo-rfm-clientes {
            display: none;
            padding: 0;
            max-height: 300px;
            overflow-y: auto;
            border-top: 1px solid #eee;
        }

        .grupo-rfm.active .grupo-rfm-clientes {
            display: block;
        }

        .grupo-rfm.active {
            flex: 1 1 100%;
        }

        .cliente-rfm-item {
            padding: 10px 15px;
            border-left: none;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .cliente-rfm-item:hover {
            background-color: #f8f9fa;
            border-left: none;
        }

        .segmento-badge {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .segmento-count {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.9em;
        }

        /* Cores específicas para cada categoria */
        .grupo-rfm.champions { background-color: #4CAF50; }
        .grupo-rfm.leais { background-color: #2196F3; }
        .grupo-rfm.regulares { background-color: #607D8B; }
        .grupo-rfm.em-risco { background-color: #FF9800; }
        .grupo-rfm.perdidos { background-color: #F44336; }
        .grupo-rfm.alto-risco { background-color: #E91E63; }

        .grupo-rfm .segmento-badge {
            color: white;
            font-weight: 500;
        }

        .segmentos-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .segmentos-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .segmento-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .segmento-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .segmento-btn.active {
            transform: translateY(1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .segmento-count {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.9em;
        }

        /* Cores dos botões */
        .segmento-btn.champions { background-color: #4CAF50; }
        .segmento-btn.leais { background-color: #2196F3; }
        .segmento-btn.regulares { background-color: #607D8B; }
        .segmento-btn.em-risco { background-color: #FF9800; }
        .segmento-btn.perdidos { background-color: #F44336; }
        .segmento-btn.novos { background-color: #9C27B0; }
        .segmento-btn.alto-risco { background-color: #E91E63; }

        .clientes-rfm-lists {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .cliente-rfm-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .cliente-rfm-item:hover {
            background-color: #f8f9fa;
        }

        .rfm-metrics {
            display: flex;
            gap: 15px;
            margin-top: 5px;
            color: #666;
            font-size: 0.9em;
        }

        .rfm-metrics span {
            background: #f1f1f1;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .segmento-btn.risco-crítico { background-color: #D32F2F; }
        #segmentoCliente.RiscoCritico { color: #D32F2F; }

        .endereco-card {
            grid-column: auto;
            margin-bottom: 0;
        }

        .endereco-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .endereco-item {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .cliente-metricas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        #clienteTelefone a:hover {
            color: #2196F3 !important;
            text-decoration: underline !important;
        }

        .rfm-sort-buttons {
            margin: 10px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
        }

        .rfm-sort-buttons span {
            margin-right: 10px;
            font-weight: bold;
        }

        .rfm-sort-btn {
            padding: 5px 10px;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .rfm-sort-btn:hover {
            background: #e9ecef;
        }

        .rfm-sort-btn.active {
            background: #007bff;
            color: white;
            border-color: #0056b3;
        }

        .reset-chart-btn:hover {
            background: #1976D2 !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .reset-chart-btn:active {
            transform: translateY(0);
        }

        .period-btn[type="month"] {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #2196F3;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .period-btn[type="month"]:hover {
            background: #2196F3;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.15);
        }

        .period-btn[type="month"]:focus {
            outline: none;
            border-color: #2196F3;
        }

        .month-picker {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #2196F3;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .month-picker:hover {
            background: #2196F3;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.15);
        }

        .month-picker:focus {
            outline: none;
            border-color: #2196F3;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>

    <main class="main-content">
        <div class="dashboard-container">
            <div class="dashboard-top">
                <div class="period-selector">
                    <button class="period-btn <?php echo ($periodo == 'ontem' && !$data_especifica) ? 'active' : ''; ?>" data-period="ontem">Ontem</button>
                    <button class="period-btn <?php echo ($periodo == 'dia' && !$data_especifica) ? 'active' : ''; ?>" data-period="dia">Hoje</button>
                    <button class="period-btn <?php echo ($periodo == 'semana' && !$data_especifica) ? 'active' : ''; ?>" data-period="semana">Esta Semana</button>
                    <input type="month" id="monthPicker" class="month-picker" style="width: auto;" value="<?php echo $mes_selecionado ?: date('Y-m'); ?>">
                    <button class="period-btn <?php echo ($periodo == '2023' && !$data_especifica) ? 'active' : ''; ?>" data-period="2023">2023</button>
                    <button class="period-btn <?php echo ($periodo == '2024' && !$data_especifica) ? 'active' : ''; ?>" data-period="2024">2024</button>
                    <button class="period-btn <?php echo ($periodo == 'ano' && !$data_especifica) ? 'active' : ''; ?>" data-period="ano">2025</button>
                    <button class="period-btn" data-period="todas">Todas as Datas</button>
                    <input type="text" id="datePicker" class="date-picker" placeholder="Selecionar data" value="<?php echo $data_especifica ? date('d/m/Y', strtotime($data_especifica)) : ''; ?>">
                </div>
                <div class="cards-container">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-users"></i>
                            <h2>Análise de Clientes por Faixa de Consumo</h2>
                        </div>
                        <div class="card-content">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="d-flex align-items-center">
                                    <i class="fas fa-users me-2"></i>
                                    Análise de Clientes por Faixa de Consumo
                                </h2>
                                <button id="btnTodosClientes" class="btn btn-primary">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Ver Relatório Consolidado
                                </button>
                            </div>
                            <?php foreach ($faixas_clientes as $faixa): ?>
                                <div class="faixa-item">
                                    <div class="faixa-header">
                                        <div class="faixa-titulo"><?= $faixa['faixa_valor'] ?></div>
                                        <div class="faixa-clientes">
                                            <?= $faixa['total_clientes'] ?> cliente<?= $faixa['total_clientes'] > 1 ? 's' : '' ?>
                                        </div>
                                    </div>
                                    <div class="faixa-detalhes">
                                        <div class="detalhe-item">
                                            <span class="detalhe-label">Total de Pedidos</span>
                                            <span class="detalhe-valor"><?= $faixa['total_pedidos'] ?> pedidos</span>
                                        </div>
                                        <div class="detalhe-item">
                                            <span class="detalhe-label">Valor Total</span>
                                            <span class="detalhe-valor">R$ <?= number_format($faixa['valor_total'], 2, ',', '.') ?></span>
                                        </div>
                                        <div class="detalhe-item">
                                            <span class="detalhe-label">Ticket Médio</span>
                                            <span class="detalhe-valor">R$ <?= number_format($faixa['valor_medio'], 2, ',', '.') ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="top-clientes">
                                        <h4>Top Clientes desta Faixa:</h4>
                                        <div class="top-clientes-list">
                                            <?php 
                                            $clientes = parseTopClientes($faixa['top_clientes']);
                                            foreach ($clientes as $idx => $cliente): 
                                            ?>
                                                <div class="top-cliente-item" data-cliente-id="<?= $cliente['id'] ?>">
                                                    <div class="cliente-ranking"><?= $idx + 1 ?>º</div>
                                                    <div class="cliente-nome"><?= $cliente['nome'] ?></div>
                                                    <div class="cliente-stats">
                                                        <span><?= $cliente['pedidos'] ?> pedidos</span>
                                                        <span>R$ <?= number_format($cliente['valor'], 2, ',', '.') ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-star"></i>
                            <h2>Ranking RFM de Clientes</h2>
                        </div>
                        <div class="card-content">
                            <div class="segmentos-container">
                                <?php
                                try {
                                    $sql = "WITH rfm_data AS (
                                        SELECT 
                                            c.id_cliente,
                                            c.nome_cliente,
                                            c.telefone_cliente,
                                            COUNT(p.id_pedido) as total_pedidos,
                                            COALESCE(SUM(p.sub_total + p.taxa_entrega), 0) as valor_total,
                                            DATEDIFF(CURRENT_DATE, MAX(DATE(p.data_pedido))) as recency,
                                            COUNT(p.id_pedido) as frequency,
                                            COALESCE(SUM(p.sub_total + p.taxa_entrega), 0) as monetary
                                        FROM clientes c
                                        LEFT JOIN pedidos p ON c.id_cliente = p.fk_cliente_id
                                        WHERE DATE(p.data_pedido) BETWEEN ? AND ?
                                        GROUP BY c.id_cliente, c.nome_cliente
                                    ),
                                    rfm_scores AS (
                                        SELECT 
                                            *,
                                            CASE 
                                                WHEN recency <= 7 THEN 5
                                                WHEN recency <= 14 THEN 4
                                                WHEN recency <= 15 THEN 3
                                                WHEN recency <= 30 THEN 2
                                                ELSE 1
                                            END as R,
                                            CASE 
                                                WHEN frequency >= 10 THEN 5
                                                WHEN frequency >= 7 THEN 4
                                                WHEN frequency >= 5 THEN 3
                                                WHEN frequency >= 3 THEN 2
                                                ELSE 1
                                            END as F,
                                            CASE 
                                                WHEN monetary >= 500 THEN 5
                                                WHEN monetary >= 300 THEN 4
                                                WHEN monetary >= 200 THEN 3
                                                WHEN monetary >= 100 THEN 2
                                                ELSE 1
                                            END as M
                                        FROM rfm_data
                                    ),
                                    rfm_segmentos AS (
                                        SELECT 
                                            *,
                                            CONCAT(R,F,M) as rfm_score,
                                            CASE 
                                                WHEN recency > 30 THEN 'Perdidos'
                                                WHEN recency > 15 AND frequency <= 3 THEN 'Alto Risco'
                                                WHEN (R >= 4 AND F = 1) THEN 'Novos'
                                                WHEN (R >= 4 AND F >= 4 AND M >= 4) THEN 'Champions'
                                                WHEN (R >= 3 AND F >= 3 AND M >= 3) THEN 'Leais'
                                                WHEN (R >= 3) THEN 'Regulares'
                                                WHEN (recency BETWEEN 8 AND 15) THEN 'Em Risco'
                                                WHEN (R <= 2) THEN 'Risco Crítico'
                                                ELSE 'Perdidos'
                                            END as segmento
                                        FROM rfm_scores
                                    )
                                    SELECT 
                                        segmento,
                                        COUNT(*) as total_clientes,
                                        GROUP_CONCAT(
                                            CONCAT(
                                                id_cliente, '|',
                                                nome_cliente, '|',
                                                telefone_cliente, '|',
                                                recency, '|',
                                                frequency, '|',
                                                monetary, '|',
                                                rfm_score
                                            )
                                            ORDER BY monetary DESC, frequency DESC, recency ASC
                                            SEPARATOR ';'
                                        ) as clientes_grupo
                                    FROM rfm_segmentos
                                    GROUP BY segmento
                                    ORDER BY FIELD(segmento, 'Champions', 'Leais', 'Regulares', 'Em Risco', 'Alto Risco', 'Risco Crítico', 'Perdidos', 'Novos')";
                                    
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute([$data_inicio, $data_fim]);
                                    $grupos_rfm = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    // Primeiro, renderizar os botões dos segmentos
                                    echo '<div class="segmentos-buttons">';
                                    foreach ($grupos_rfm as $grupo) {
                                        $segmento_class = strtolower(str_replace(' ', '-', $grupo['segmento']));
                                        echo "<button class='segmento-btn {$segmento_class}' data-segmento='{$segmento_class}'>
                                                {$grupo['segmento']} 
                                                <span class='segmento-count'>{$grupo['total_clientes']}</span>
                                              </button>";
                                    }
                                    echo '</div>';

                                    // Adicionar os botões de ordenação RFM
                                    echo '<div class="rfm-sort-buttons">
                                        <span>Ordenar por:</span>
                                        <button class="rfm-sort-btn" data-sort="recency">Recência (R)</button>
                                        <button class="rfm-sort-btn" data-sort="frequency">Frequência (F)</button>
                                        <button class="rfm-sort-btn" data-sort="monetary">Valor (M)</button>
                                        <button class="rfm-sort-btn" data-sort="rfm">Score RFM</button>
                                    </div>';

                                    // Depois, renderizar as listas de clientes (inicialmente ocultas)
                                    echo '<div class="clientes-rfm-lists">';
                                    foreach ($grupos_rfm as $grupo): 
                                        $clientes = array_map(function($cliente_str) {
                                            $partes = explode('|', $cliente_str);
                                            
                                            if (count($partes) < 6) {
                                                return null;
                                            }
                                            
                                            return [
                                                'id' => $partes[0] ?? '',
                                                'nome' => $partes[1] ?? '',
                                                'telefone' => $partes[2] ?? '',
                                                'recency' => $partes[3] ?? '',
                                                'frequency' => $partes[4] ?? '',
                                                'monetary' => $partes[5] ?? '',
                                                'rfm_score' => $partes[6] ?? 'N/A'
                                            ];
                                        }, explode(';', $grupo['clientes_grupo']));

                                        $clientes = array_filter($clientes);
                                        $segmento_class = strtolower(str_replace(' ', '-', $grupo['segmento']));
                                        ?>
                                        <div class="clientes-list <?= $segmento_class ?>" style="display: none;">
                                            <?php foreach ($clientes as $cliente): ?>
                                                <div class="cliente-rfm-item" data-cliente-id="<?= $cliente['id'] ?>">
                                                    <div class="cliente-info">
                                                        <div class="cliente-nome"><?= $cliente['nome'] ?></div>
                                                    </div>
                                                    <div class="rfm-metrics">
                                                        <span title="Recência (dias)">R: <?= $cliente['recency'] ?></span>
                                                        <span title="Frequência (pedidos)">F: <?= $cliente['frequency'] ?></span>
                                                        <span title="Valor Total">M: R$ <?= number_format($cliente['monetary'], 2, ',', '.') ?></span>
                                                        <span title="Score RFM">RFM: <?= $cliente['rfm_score'] ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach;
                                    echo '</div>';
                                } catch (PDOException $e) {
                                    error_log('Erro ao buscar dados RFM: ' . $e->getMessage());
                                    echo '<p>Erro ao carregar dados dos clientes.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variáveis globais para os gráficos
            let evolucaoChart = null;
            let produtosChart = null;

            // Handlers para os botões de período
            document.querySelectorAll('.period-btn:not([type="month"])').forEach(btn => {
                btn.addEventListener('click', function() {
                    const period = this.getAttribute('data-period');
                    window.location.href = `relatorio_geral.php?periodo=${period}`;
                    document.getElementById('datePicker').value = '';
                });
            });

            // Configuração do Flatpickr
            if (document.getElementById('datePicker')) {
                flatpickr("#datePicker", {
                    locale: "pt",
                    dateFormat: "d/m/Y",
                    maxDate: "today",
                    onChange: function(selectedDates, dateStr) {
                        if (selectedDates[0]) {
                            const formattedDate = selectedDates[0].toISOString().split('T')[0];
                            window.location.href = `relatorio_geral.php?data=${formattedDate}`;
                        }
                    }
                });
            }

            // Evento de clique para os itens de cliente
            document.querySelectorAll('.top-cliente-item').forEach(item => {
                item.addEventListener('click', function() {
                    const clienteId = this.getAttribute('data-cliente-id');
                    const clienteNome = this.querySelector('.cliente-nome').textContent;
                    
                    // Validar se clienteId existe
                    if (!clienteId) {
                        console.error('ID do cliente não encontrado no elemento');
                        return;
                    }
                    
                    abrirModalCliente(clienteId, clienteNome);
                });
            });

            // Configurar fechamento do modal
            const modal = document.getElementById('clienteModal');
            const closeBtn = document.querySelector('.close');
            
            if (closeBtn) {
                closeBtn.onclick = function() {
                    modal.style.display = 'none';
                }
            }

            window.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }

            function abrirModalCliente(clienteId, clienteNome) {
                const modal = document.getElementById('clienteModal');
                document.getElementById('clienteNome').textContent = clienteNome;
                document.getElementById('clienteTelefone').textContent = ''; // Limpa o telefone anterior
                modal.style.display = 'block';

                // Destruir gráficos existentes
                if (evolucaoChart) {
                    evolucaoChart.destroy();
                }
                if (produtosChart) {
                    produtosChart.destroy();
                }

                // Buscar dados usando as variáveis PHP
                fetch(`../actions/relatorio_geral/get_cliente_dados.php?cliente_id=${clienteId}&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>`)
                    .then(response => response.json())
                    .then(response => {
                        if (response.status === 'success') {
                            const data = response.data;
                            
                            // Adicionar telefone do cliente
                            if (data.cliente && data.cliente.telefone) {
                                const telefone = data.cliente.telefone.replace(/\D/g, ''); // Remove caracteres não numéricos
                                document.getElementById('clienteTelefone').innerHTML = ` - <a href="https://wa.me/55${telefone}" target="_blank" style="color: #666; text-decoration: none;">${data.cliente.telefone}</a>`;
                            }

                            // Preencher métricas existentes
                            document.getElementById('totalPedidos').textContent = data.metricas.total_pedidos;
                            document.getElementById('valorTotal').textContent = `R$ ${formatarMoeda(data.metricas.valor_total)}`;
                            document.getElementById('ticketMedio').textContent = `R$ ${formatarMoeda(data.metricas.ticket_medio)}`;
                            
                            // Adicionar métricas de intervalos
                            document.getElementById('maiorIntervalo').textContent = 
                                data.metricas.intervalos.maior > 0 
                                    ? `${data.metricas.intervalos.maior} dias`
                                    : 'N/A';
                            
                            document.getElementById('mediaIntervalo').textContent = 
                                data.metricas.intervalos.media > 0 
                                    ? `${data.metricas.intervalos.media} dias`
                                    : 'N/A';

                            // Criar gráficos
                            criarGraficoEvolucao(data.evolucao_pedidos);
                            criarGraficoProdutos(data.produtos_populares);

                            // Preencher últimos pedidos
                            preencherUltimosPedidos(data.ultimos_pedidos);

                            // Preencher RFM
                            const segmentoEl = document.getElementById('segmentoCliente');
                            segmentoEl.textContent = data.rfm.segmento;
                            segmentoEl.className = data.rfm.segmento.replace(/\s+/g, '');
                            document.getElementById('rfmScore').textContent = `Score RFM: ${data.rfm.score}`;

                            // Preencher Afinidade
                            const afinidadeContainer = document.getElementById('afinidadeLista');
                            if (data.afinidade.length > 0) {
                                afinidadeContainer.innerHTML = data.afinidade.map(item => `
                                    <div class="afinidade-item">
                                        <div class="afinidade-produtos">
                                            ${item.produto1} + ${item.produto2}
                                        </div>
                                        <div class="afinidade-frequencia">
                                            ${item.frequencia}x juntos
                                        </div>
                                    </div>
                                `).join('');
                            } else {
                                afinidadeContainer.innerHTML = '<p>Nenhum padrão de compra conjunto identificado</p>';
                            }

                            // Preencher endereços
                            const enderecosContainer = document.getElementById('clienteEnderecos');
                            if (data.cliente && data.cliente.enderecos) {
                                const enderecos = data.cliente.enderecos.split('||');
                                enderecosContainer.innerHTML = enderecos.map(endereco => 
                                    `<div class="endereco-item">${endereco}</div>`
                                ).join('');
                            } else {
                                enderecosContainer.innerHTML = '<p>Nenhum endereço cadastrado</p>';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        document.getElementById('clienteNome').textContent = 'Erro ao carregar dados';
                    });
            }

            function criarGraficoEvolucao(dados) {
                const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                const coresDias = {
                    'Dom': '#FF0000',
                    'Seg': '#00FF00',
                    'Ter': '#0066FF',
                    'Qua': '#FF9900',
                    'Qui': '#FF00FF',
                    'Sex': '#00FFFF',
                    'Sáb': '#9933FF'
                };

                // Adicionar um dia antes e depois
                if (dados.length > 0) {
                    const primeiroDia = new Date(dados[0].data);
                    const ultimoDia = new Date(dados[dados.length - 1].data);
                    
                    // Adicionar dia anterior
                    primeiroDia.setDate(primeiroDia.getDate() - 1);
                    const diaAnterior = {
                        data: primeiroDia.toISOString().split('T')[0],
                        valor_total: 0
                    };
                    
                    // Adicionar dia posterior
                    ultimoDia.setDate(ultimoDia.getDate() + 1);
                    const diaPosterior = {
                        data: ultimoDia.toISOString().split('T')[0],
                        valor_total: 0
                    };
                    
                    // Inserir os dias extras no array de dados
                    dados = [diaAnterior, ...dados, diaPosterior];
                }
                
                const labels = dados.map(d => {
                    if (!d || !d.data) return '';
                    const [ano, mes, dia] = d.data.split('-');
                    const data = new Date(ano, mes - 1, dia);
                    const diaSemana = diasSemana[data.getDay()];
                    const dataFormatada = `${dia}/${mes} ${diaSemana}`;
                    return dataFormatada;
                }).filter(label => label !== '');

                // Separar dados por dia da semana
                const dadosPorDia = {};
                diasSemana.forEach(dia => {
                    dadosPorDia[dia] = labels.map((label, index) => {
                        return label.includes(dia) ? dados[index].valor_total : null;
                    });
                });
                
                const ctx = document.getElementById('evolucaoPedidosChart').getContext('2d');
                
                // Criar gradiente para o preenchimento
                const gradientFill = ctx.createLinearGradient(0, 0, 0, 400);
                gradientFill.addColorStop(0, 'rgba(33, 150, 243, 0.3)');
                gradientFill.addColorStop(1, 'rgba(33, 150, 243, 0.02)');

                // Criar datasets para cada dia da semana
                const datasets = [
                    {
                        label: 'Valor Total (R$)',
                        data: dados.map(d => d?.valor_total || 0),
                        borderColor: '#2196F3',
                        backgroundColor: gradientFill,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        borderWidth: 3,
                        pointBackgroundColor: function(context) {
                            if (!context || !context.chart || !context.chart.data) return '#e0e0e0';
                            
                            const value = context.dataset.data[context.dataIndex];
                            if (!value || value === 0) return '#e0e0e0';
                            
                            const label = context.chart.data.labels[context.dataIndex];
                            if (!label) return '#e0e0e0';
                            
                            const diaSemana = label.split(' ')[1];
                            return coresDias[diaSemana] || '#e0e0e0';
                        },
                        pointBorderColor: function(context) {
                            if (!context || !context.chart || !context.chart.data) return '#e0e0e0';
                            
                            const value = context.dataset.data[context.dataIndex];
                            if (!value || value === 0) return '#e0e0e0';
                            
                            const label = context.chart.data.labels[context.dataIndex];
                            if (!label) return '#e0e0e0';
                            
                            const diaSemana = label.split(' ')[1];
                            return coresDias[diaSemana] || '#e0e0e0';
                        },
                        pointBorderWidth: 2,
                        cubicInterpolationMode: 'monotone',
                        order: 1
                    },
                    // Adicionar série de taxa de entrega logo após o dataset principal
                    {
                        label: 'Taxa de Entrega',
                        data: dados.map(d => d?.taxa_entrega || 0), // Mapeia a taxa de entrega dos dados
                        borderColor: 'rgba(255, 159, 64, 1)',
                        backgroundColor: 'rgba(255, 159, 64, 0.2)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        order: 1
                    }
                ];

                // Adicionar datasets para cada dia da semana
                diasSemana.forEach(dia => {
                    datasets.push({
                        label: `${dia}`,
                        data: dadosPorDia[dia],
                        borderColor: coresDias[dia],
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        fill: false,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        borderDash: [5, 5],
                        spanGaps: true,
                        cubicInterpolationMode: 'monotone',
                        order: 2
                    });
                });

                // Adiciona série de taxa de entrega ao gráfico
                const taxaEntregaPorDia = <?php echo json_encode($taxa_entrega_por_dia); ?>;
                const taxaEntregaData = taxaEntregaPorDia.map(item => ({
                    x: item.data,
                    y: parseFloat(item.total_taxa_entrega)
                }));

                // No objeto de configuração do gráfico, adiciona nova série
                {
                    // ... existing code ...
                    data: {
                        datasets: [
                            // ... existing datasets ...
                            {
                                label: 'Taxa de Entrega',
                                data: taxaEntregaData,
                                borderColor: 'rgba(255, 159, 64, 1)',
                                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                                borderWidth: 2,
                                fill: true,
                                yAxisID: 'y'
                            }
                        ]
                    }
                    // ... existing code ...
                }

                evolucaoChart = new Chart(ctx, {
                    type: 'line',
                    data: { labels, datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Permitir que o gráfico cresça em altura
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12
                                    }
                                },
                                onClick: function(e, legendItem, legend) {
                                    const index = legendItem.datasetIndex;
                                    const chart = legend.chart;
                                    
                                    if (index === 0) return; // Ignorar cliques na linha principal
                                    
                                    if (!chart.selectedDays) {
                                        chart.selectedDays = new Set();
                                    }

                                    const dia = legendItem.text;
                                    
                                    if (chart.selectedDays.has(dia)) {
                                        chart.selectedDays.delete(dia);
                                    } else {
                                        chart.selectedDays.add(dia);
                                    }

                                    // Se não houver dias selecionados, resetar completamente o gráfico
                                    if (chart.selectedDays.size === 0) {
                                        chart.data.datasets.forEach((dataset, i) => {
                                            const meta = chart.getDatasetMeta(i);
                                            meta.hidden = false;
                                            
                                            if (i === 0) {
                                                // Restaurar linha principal
                                                dataset.borderColor = '#2196F3';
                                                dataset.backgroundColor = gradientFill;
                                                dataset.pointRadius = 6;
                                                dataset.pointHoverRadius = 8;
                                            } else {
                                                // Restaurar linhas dos dias
                                                dataset.borderColor = dataset.borderColor.replace(/[\d.]+\)/, '1)');
                                                dataset.borderDash = [5, 5];
                                                dataset.pointRadius = 4;
                                                dataset.pointHoverRadius = 6;
                                            }
                                        });

                                        // Resetar escalas
                                        chart.options.scales.y.min = 0;
                                        delete chart.options.scales.y.max;
                                        
                                        chart.update();
                                        resetButton.style.display = 'none';
                                        return;
                                    }

                                    // Resto do código para quando há dias selecionados
                                    chart.data.datasets.forEach((dataset, i) => {
                                        if (i === 0) {
                                            const hasSelectedDays = chart.selectedDays.size > 0;
                                            dataset.borderColor = hasSelectedDays ? 'rgba(33, 150, 243, 0)' : '#2196F3';
                                            dataset.backgroundColor = hasSelectedDays ? 'rgba(33, 150, 243, 0)' : gradientFill;
                                            dataset.pointRadius = hasSelectedDays ? 0 : 6;
                                            return;
                                        }
                                        
                                        const isDiaSelecionado = chart.selectedDays.has(dataset.label);
                                        const meta = chart.getDatasetMeta(i);
                                        meta.hidden = !isDiaSelecionado;
                                        
                                        if (isDiaSelecionado) {
                                            dataset.borderColor = dataset.borderColor.replace(/[\d.]+\)/, '1)');
                                            dataset.borderDash = [];
                                            dataset.pointRadius = 4;
                                            dataset.pointHoverRadius = 6;
                                        } else {
                                            dataset.borderColor = dataset.borderColor.replace(/[\d.]+\)/, '0)');
                                            dataset.pointRadius = 0;
                                            dataset.pointHoverRadius = 0;
                                        }
                                    });

                                    if (chart.selectedDays.size > 0) {
                                        const selectedData = [];
                                        chart.data.datasets.forEach((dataset, i) => {
                                            if (i > 0 && chart.selectedDays.has(dataset.label)) {
                                                selectedData.push(...dataset.data.filter(value => value !== null));
                                            }
                                        });

                                        if (selectedData.length > 0) {
                                            const maxValue = Math.max(...selectedData);
                                            const minValue = Math.min(...selectedData.filter(value => value > 0));
                                            
                                            chart.options.scales.y.min = minValue * 0.8;
                                            chart.options.scales.y.max = maxValue * 1.2;
                                        }
                                    }

                                    chart.update();
                                    resetButton.style.display = chart.selectedDays.size > 0 ? 'block' : 'none';
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        return value > 0 
                                            ? 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2})
                                            : 'Sem pedidos';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR');
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: true,
                                    drawOnChartArea: true,
                                    color: 'rgba(0, 0, 0, 0.1)',
                                    lineWidth: 1
                                },
                                ticks: {
                                    autoSkip: false,
                                    minRotation: 45,
                                    maxRotation: 45
                                }
                            }
                        }
                    }
                });

                // Adicionar botão de reset
                const resetButton = document.createElement('button');
                resetButton.textContent = 'Mostrar Todos os Dias';
                resetButton.className = 'reset-chart-btn';
                resetButton.style.cssText = `
                    margin: 10px;
                    padding: 8px 16px;
                    background: #2196F3;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    display: none;
                `;

                resetButton.addEventListener('click', () => {
                    evolucaoChart.data.datasets.forEach((dataset, i) => {
                        const meta = evolucaoChart.getDatasetMeta(i);
                        meta.hidden = false;
                        
                        if (i === 0) {
                            // Restaurar a linha principal
                            dataset.borderColor = '#2196F3';
                            dataset.backgroundColor = gradientFill;
                            dataset.pointRadius = 6;
                            dataset.pointHoverRadius = 8;
                        } else {
                            // Restaurar as linhas dos dias
                            dataset.borderColor = dataset.borderColor.replace(/[\d.]+\)/, '1)');
                            dataset.borderDash = [5, 5];
                            dataset.pointRadius = 4;
                            dataset.pointHoverRadius = 6;
                        }
                    });

                    // Resetar o zoom
                    evolucaoChart.options.scales.y.min = 0;
                    delete evolucaoChart.options.scales.y.max;

                    evolucaoChart.update();
                    resetButton.style.display = 'none';
                });

                const chartContainer = document.getElementById('evolucaoPedidosChart').parentElement;
                chartContainer.insertBefore(resetButton, chartContainer.firstChild);
            }

            function criarGraficoProdutos(dados) {
                const container = document.getElementById('produtosLista');
                container.innerHTML = dados.map(produto => {
                    // Verificar se existem categorias de acompanhamento
                    const temAcompanhamentos = produto.categorias_acomp && produto.categorias_acomp.length > 0;
                    
                    if (!temAcompanhamentos) {
                        // Produtos sem acompanhamento
                        return `
                            <div class="produto-item">
                                <div class="produto-header">
                                    <div class="produto-nome">${produto.produtos}</div>
                                    <div class="produto-total">${produto.total_pedidos}x pedidos</div>
                                </div>
                                <div class="produto-quantidades">${produto.quantidades}</div>
                            </div>
                        `;
                    } else {
                        // Produtos com acompanhamento
                        const acompanhamentos = Object.entries(produto.itens_agrupados || {})
                            .map(([categoria, itens]) => {
                                const itensLista = itens.map(item => 
                                    `<div class="combinacao-subitem">- ${item}</div>`
                                ).join('');

                                return `
                                    <div class="combinacao-item">
                                        <strong>${categoria}</strong>
                                        ${itensLista}
                                    </div>
                                `;
                            }).join('');

                        return `
                            <div class="produto-item">
                                <div class="produto-header">
                                    <div class="produto-nome">${produto.produtos}</div>
                                    <div class="produto-total">${produto.total_pedidos}x pedidos</div>
                                </div>
                                <div class="produto-quantidades">${produto.quantidades}</div>
                                <div class="combinacoes-lista">
                                    ${acompanhamentos}
                                </div>
                            </div>
                        `;
                    }
                }).join('');
            }

            function preencherUltimosPedidos(pedidos) {
                // Criar um objeto para armazenar as combinações únicas
                const combinacoes = {};
                
                pedidos.forEach(pedido => {
                    // Ordenar os itens para garantir que combinações iguais sejam agrupadas
                    const itensOrdenados = pedido.itens.split(' | ')
                        .sort()
                        .join(' | ');
                        
                    if (combinacoes[itensOrdenados]) {
                        combinacoes[itensOrdenados].quantidade++;
                    } else {
                        combinacoes[itensOrdenados] = {
                            quantidade: 1,
                            itens: itensOrdenados
                        };
                    }
                });

                const container = document.getElementById('pedidosLista');
                container.innerHTML = Object.values(combinacoes)
                    .map(combo => `
                        <div class="pedido-item">
                            <div class="pedido-quantidade">${combo.quantidade}x</div>
                            <div class="pedido-produtos">
                                ${combo.itens.split(' | ').map(item => `
                                    <div class="item-linha">${item}</div>
                                `).join('')}
                            </div>
                        </div>
                    `).join('');
            }

            function formatarMoeda(valor) {
                return Number(valor).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // Adicionar evento para o botão
            document.getElementById('btnTodosClientes').addEventListener('click', function() {
                const dataInicio = '<?php echo $data_inicio; ?>';
                const dataFim = '<?php echo $data_fim; ?>';
                
                // Limpar dados anteriores
                if (evolucaoChart) {
                    evolucaoChart.destroy();
                }
                if (produtosChart) {
                    produtosChart.destroy();
                }
                
                // Resetar elementos do modal
                document.getElementById('totalPedidos').textContent = '...';
                document.getElementById('valorTotal').textContent = '...';
                document.getElementById('ticketMedio').textContent = '...';
                document.getElementById('maiorIntervalo').textContent = '...';
                document.getElementById('mediaIntervalo').textContent = '...';
                document.getElementById('segmentoCliente').textContent = '';
                document.getElementById('rfmScore').textContent = '';
                document.getElementById('produtosLista').innerHTML = '';
                document.getElementById('pedidosLista').innerHTML = '';
                document.getElementById('afinidadeLista').innerHTML = '';
                
                // Mostrar modal com título apropriado
                const modal = document.getElementById('clienteModal');
                modal.style.display = 'block';
                document.getElementById('clienteNome').textContent = 'Relatório Consolidado de Todos os Clientes';
                
                // Buscar dados consolidados
                fetch(`../actions/relatorio_geral/get_cliente_dados.php?data_inicio=${dataInicio}&data_fim=${dataFim}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro na requisição');
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response.status === 'success') {
                            const data = response.data;
                            
                            // Atualizar métricas
                            document.getElementById('totalPedidos').textContent = data.metricas.total_pedidos;
                            document.getElementById('valorTotal').textContent = `R$ ${formatarMoeda(data.metricas.valor_total)}`;
                            document.getElementById('ticketMedio').textContent = `R$ ${formatarMoeda(data.metricas.ticket_medio)}`;
                            
                            // Atualizar intervalos
                            if (data.metricas.intervalos) {
                                document.getElementById('maiorIntervalo').textContent = 
                                    data.metricas.intervalos.maior > 0 ? 
                                    `${data.metricas.intervalos.maior} dias` : 'N/A';
                                document.getElementById('mediaIntervalo').textContent = 
                                    data.metricas.intervalos.media > 0 ? 
                                    `${data.metricas.intervalos.media.toFixed(1)} dias` : 'N/A';
                            }
                            
                            // Ocultar elementos específicos de cliente individual
                            document.getElementById('segmentoCliente').parentElement.style.display = 'none';
                            
                            // Criar gráficos
                            if (data.evolucao_pedidos && data.evolucao_pedidos.length > 0) {
                                criarGraficoEvolucao(data.evolucao_pedidos);
                            }
                            
                            if (data.produtos_populares && data.produtos_populares.length > 0) {
                                criarGraficoProdutos(data.produtos_populares);
                            }
                            
                            // Preencher últimos pedidos
                            if (data.ultimos_pedidos && data.ultimos_pedidos.length > 0) {
                                preencherUltimosPedidos(data.ultimos_pedidos);
                            } else {
                                document.getElementById('pedidosLista').innerHTML = 
                                    '<p>Nenhum pedido encontrado no período</p>';
                            }
                            
                            // Preencher afinidade
                            if (data.afinidade && data.afinidade.length > 0) {
                                const afinidadeContainer = document.getElementById('afinidadeLista');
                                afinidadeContainer.innerHTML = data.afinidade.map(item => `
                                    <div class="afinidade-item">
                                        <div class="afinidade-produtos">
                                            ${item.produto1} + ${item.produto2}
                                        </div>
                                        <div class="afinidade-frequencia">
                                            ${item.frequencia}x juntos
                                        </div>
                                    </div>
                                `).join('');
                            } else {
                                document.getElementById('afinidadeLista').innerHTML = 
                                    '<p>Nenhum padrão de compra conjunto identificado</p>';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        document.getElementById('clienteNome').textContent = 'Erro ao carregar dados consolidados';
                    });
            });

            const segmentoBtns = document.querySelectorAll('.segmento-btn');
            const clientesLists = document.querySelectorAll('.clientes-list');
            
            // Mostrar a primeira lista por padrão
            if (clientesLists.length > 0) {
                clientesLists[0].style.display = 'block';
                segmentoBtns[0].classList.add('active');
            }
            
            segmentoBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const segmento = this.dataset.segmento;
                    
                    // Atualizar botões
                    segmentoBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Atualizar listas
                    clientesLists.forEach(list => {
                        list.style.display = list.classList.contains(segmento) ? 'block' : 'none';
                    });
                });
            });
            
            // Manter a funcionalidade de clique nos clientes
            document.querySelectorAll('.cliente-rfm-item').forEach(item => {
                item.addEventListener('click', function() {
                    const clienteId = this.getAttribute('data-cliente-id');
                    const clienteNome = this.querySelector('.cliente-nome').textContent;
                    abrirModalCliente(clienteId, clienteNome);
                });
            });

            // Funções de ordenação RFM
            const rfmSortButtons = document.querySelectorAll('.rfm-sort-btn');
            let currentSort = '';
            let isAscending = true;

            rfmSortButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const sortType = this.dataset.sort;
                    
                    // Alternar direção se clicar no mesmo botão
                    if (currentSort === sortType) {
                        isAscending = !isAscending;
                    } else {
                        isAscending = true;
                    }
                    currentSort = sortType;

                    // Atualizar estado visual dos botões
                    rfmSortButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Ordenar todas as listas de clientes visíveis
                    document.querySelectorAll('.clientes-list').forEach(list => {
                        const items = Array.from(list.querySelectorAll('.cliente-rfm-item'));
                        
                        items.sort((a, b) => {
                            let valueA, valueB;
                            
                            switch(sortType) {
                                case 'recency':
                                    valueA = parseInt(a.querySelector('[title="Recência (dias)"]').textContent.replace('R: ', ''));
                                    valueB = parseInt(b.querySelector('[title="Recência (dias)"]').textContent.replace('R: ', ''));
                                    break;
                                case 'frequency':
                                    valueA = parseInt(a.querySelector('[title="Frequência (pedidos)"]').textContent.replace('F: ', ''));
                                    valueB = parseInt(b.querySelector('[title="Frequência (pedidos)"]').textContent.replace('F: ', ''));
                                    break;
                                case 'monetary':
                                    valueA = parseFloat(a.querySelector('[title="Valor Total"]').textContent.replace('M: R$ ', '').replace('.', '').replace(',', '.'));
                                    valueB = parseFloat(b.querySelector('[title="Valor Total"]').textContent.replace('M: R$ ', '').replace('.', '').replace(',', '.'));
                                    break;
                                case 'rfm':
                                    valueA = a.querySelector('[title="Score RFM"]').textContent.replace('RFM: ', '');
                                    valueB = b.querySelector('[title="Score RFM"]').textContent.replace('RFM: ', '');
                                    break;
                            }
                            
                            if (sortType === 'recency') {
                                // Para recência, menor é melhor
                                return isAscending ? valueA - valueB : valueB - valueA;
                            } else {
                                // Para os outros, maior é melhor
                                return isAscending ? valueB - valueA : valueA - valueB;
                            }
                        });

                        // Reordenar os elementos no DOM
                        items.forEach(item => list.appendChild(item));
                    });
                });
            });

            // Handler para o seletor de mês
            document.getElementById('monthPicker').addEventListener('change', function() {
                const selectedMonth = this.value;
                if (selectedMonth) {
                    window.location.href = `relatorio_geral.php?mes=${selectedMonth}`;
                }
            });
        });
    </script>

    <!-- Adicionar antes do fechamento do body -->
    <div id="clienteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <span id="clienteNome"></span>
                    <span id="clienteTelefone" style="color: #666;"></span>
                </h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="cliente-metricas">
                    <div class="metrica-card endereco-card">
                        <h3>Endereços Cadastrados</h3>
                        <div id="clienteEnderecos" class="endereco-lista"></div>
                    </div>
                    <div class="metrica-card">
                        <h3>Total de Pedidos</h3>
                        <p id="totalPedidos"></p>
                    </div>
                    <div class="metrica-card">
                        <h3>Valor Total</h3>
                        <p id="valorTotal"></p>
                    </div>
                    <div class="metrica-card">
                        <h3>Ticket Médio</h3>
                        <p id="ticketMedio"></p>
                    </div>
                    <div class="metrica-card">
                        <h3>Maior Intervalo sem Compras</h3>
                        <p id="maiorIntervalo"></p>
                    </div>
                    <div class="metrica-card">
                        <h3>Média de Dias Entre Compras</h3>
                        <p id="mediaIntervalo"></p>
                    </div>
                    <div class="metrica-card">
                        <h3>Segmento do Cliente</h3>
                        <p id="segmentoCliente"></p>
                        <small id="rfmScore"></small>
                    </div>
                </div>
                
                <div class="graficos-container">
                    <div class="grafico-box">
                        <h3>Evolução de Pedidos</h3>
                        <canvas id="evolucaoPedidosChart"></canvas>
                    </div>
                </div>

                <div class="grafico-box">
                    <h3>Produtos Mais Pedidos</h3>
                    <div class="produtos-populares">
                        <div id="produtosLista"></div>
                    </div>
                </div>

                <div class="ultimos-pedidos">
                    <h3>Últimos Pedidos</h3>
                    <div id="pedidosLista"></div>
                </div>

                <div class="afinidade-container">
                    <h3>Produtos Frequentemente Comprados Juntos</h3>
                    <div id="afinidadeLista"></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
