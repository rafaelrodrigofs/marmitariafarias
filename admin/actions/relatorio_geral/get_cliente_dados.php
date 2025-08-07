<?php
session_start();
include_once '../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Garantir que nenhum output HTML seja enviado antes do JSON
ob_start();

header('Content-Type: application/json');

// Função auxiliar para gerar array com todas as datas do período
function gerarArrayDatas($data_inicio, $data_fim) {
    $datas = [];
    $data_atual = new DateTime($data_inicio);
    $data_final = new DateTime($data_fim);

    while ($data_atual <= $data_final) {
        // Pula os domingos (0 = domingo, 1 = segunda, ..., 6 = sábado)
        if ($data_atual->format('w') != '0') {
            $datas[$data_atual->format('Y-m-d')] = [
                'data' => $data_atual->format('Y-m-d'),
                'total_pedidos' => 0,
                'valor_total' => 0.00
            ];
        }
        $data_atual->modify('+1 day');
    }
    return $datas;
}

try {
    $cliente_id = $_GET['cliente_id'] ?? null;
    $data_inicio = $_GET['data_inicio'] ?? null;
    $data_fim = $_GET['data_fim'] ?? null;

    // Validação mais rigorosa dos parâmetros (ajustada)
    if (empty($data_inicio) || empty($data_fim)) {
        throw new Exception('Período é obrigatório');
    }

    // Ajustar a condição WHERE das queries para incluir todos os clientes quando cliente_id for null
    $where_cliente = $cliente_id ? "WHERE fk_cliente_id = ?" : "WHERE 1=1";
    $params = $cliente_id ? [$cliente_id, $data_inicio, $data_fim] : [$data_inicio, $data_fim];

    // Validar se o cliente existe apenas se um cliente_id foi fornecido
    if ($cliente_id) {
        $stmt = $pdo->prepare("SELECT id_cliente FROM clientes WHERE id_cliente = ?");
        $stmt->execute([$cliente_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Cliente não encontrado');
        }
    }

    // Métricas gerais
    $sql_metricas = "SELECT 
        COUNT(*) as total_pedidos,
        SUM(sub_total + taxa_entrega) as valor_total,
        AVG(sub_total + taxa_entrega) as ticket_medio,
        COUNT(DISTINCT DATE(data_pedido)) as dias_com_pedidos
        FROM pedidos 
        $where_cliente 
        AND DATE(data_pedido) BETWEEN ? AND ?";
    
    $stmt = $pdo->prepare($sql_metricas);
    $stmt->execute($params);
    $metricas = $stmt->fetch(PDO::FETCH_ASSOC);

    // Primeiro, buscar a data do primeiro e último pedido no período
    $sql_datas_limite = "SELECT 
        MIN(DATE(data_pedido)) as primeira_data,
        MAX(DATE(data_pedido)) as ultima_data
        FROM pedidos 
        $where_cliente
        AND DATE(data_pedido) BETWEEN ? AND ?";

    $stmt = $pdo->prepare($sql_datas_limite);
    $stmt->execute($params);
    $datas_limite = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se não houver pedidos no período, usar array vazio
    if (!$datas_limite['primeira_data'] || !$datas_limite['ultima_data']) {
        $evolucao = [];
    } else {
        // Evolução dos pedidos por dia
        $sql_evolucao = "SELECT 
            DATE(data_pedido) as data,
            COUNT(*) as total_pedidos,
            SUM(sub_total + taxa_entrega) as valor_total,
            SUM(taxa_entrega) as taxa_entrega
            FROM pedidos 
            $where_cliente
            AND DATE(data_pedido) BETWEEN ? AND ?
            GROUP BY DATE(data_pedido)
            ORDER BY data_pedido";

        $stmt = $pdo->prepare($sql_evolucao);
        $stmt->execute($params);
        $evolucao_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Gerar array com todas as datas entre o primeiro e último pedido
        $evolucao = gerarArrayDatas($datas_limite['primeira_data'], $datas_limite['ultima_data']);

        // Preencher com os dados reais
        foreach ($evolucao_db as $dia) {
            if (isset($evolucao[$dia['data']])) {
                $evolucao[$dia['data']]['total_pedidos'] = intval($dia['total_pedidos']);
                $evolucao[$dia['data']]['valor_total'] = floatval($dia['valor_total']);
                $evolucao[$dia['data']]['taxa_entrega'] = floatval($dia['taxa_entrega']);
            }
        }

        // Converter para array indexado para JSON
        $evolucao = array_values($evolucao);
    }

    // Adicionar logo após o fetch dos dados de evolução
    error_log('Dados de evolução: ' . json_encode($evolucao));

    // Adicionar após a query de evolução
    $sql_pedidos_individuais = "SELECT 
        DATE(data_pedido) as data,
        (sub_total + taxa_entrega) as valor_pedido,
        taxa_entrega
        FROM pedidos 
        $where_cliente
        AND DATE(data_pedido) BETWEEN ? AND ?
        ORDER BY data_pedido";

    $stmt = $pdo->prepare($sql_pedidos_individuais);
    $stmt->execute($params);
    $pedidos_individuais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Produtos mais pedidos
    $sql_produtos = "WITH pedidos_detalhados AS (
        SELECT 
            pi.id_pedido_item,
            pi.quantidade,
            prod.nome_produto,
            GROUP_CONCAT(
                DISTINCT CONCAT(
                    a.nome_acomp, ': ', sa.nome_subacomp
                )
                ORDER BY a.nome_acomp, sa.nome_subacomp
                SEPARATOR ' | '
            ) as combinacao,
            GROUP_CONCAT(
                DISTINCT a.nome_acomp
                ORDER BY a.nome_acomp
                SEPARATOR ', '
            ) as categorias_acomp,
            CASE WHEN a.id_acomp IS NULL THEN 0 ELSE 1 END as tem_acomp
        FROM pedidos p
        JOIN pedido_itens pi ON p.id_pedido = pi.fk_pedido_id
        JOIN produto prod ON prod.id_produto = pi.fk_produto_id
        LEFT JOIN pedido_item_acomp pia ON pia.fk_pedido_item_id = pi.id_pedido_item
        LEFT JOIN acomp a ON a.id_acomp = pia.fk_acomp_id
        LEFT JOIN sub_acomp sa ON sa.id_subacomp = pia.fk_subacomp_id
        $where_cliente
        AND DATE(p.data_pedido) BETWEEN ? AND ?
        GROUP BY pi.id_pedido_item, pi.quantidade, prod.nome_produto
    ),
    contagem_produtos AS (
        SELECT 
            combinacao,
            nome_produto,
            COUNT(*) as qtd_produto
        FROM pedidos_detalhados
        WHERE tem_acomp = 1
        GROUP BY combinacao, nome_produto
    )
    SELECT 
        CASE 
            WHEN pd.tem_acomp = 1 THEN 
                GROUP_CONCAT(
                    DISTINCT CONCAT(cp.qtd_produto, 'x ', pd.nome_produto)
                    ORDER BY cp.qtd_produto DESC, pd.nome_produto
                    SEPARATOR '<br>'
                )
            ELSE pd.nome_produto
        END as produtos,
        pd.combinacao,
        pd.categorias_acomp,
        COUNT(*) as total_pedidos,
        GROUP_CONCAT(
            DISTINCT CONCAT(pd.quantidade, 'x')
            ORDER BY pd.quantidade
            SEPARATOR ', '
        ) as quantidades,
        pd.tem_acomp
    FROM pedidos_detalhados pd
    LEFT JOIN contagem_produtos cp ON pd.combinacao = cp.combinacao 
        AND pd.nome_produto = cp.nome_produto
    GROUP BY 
        CASE WHEN pd.tem_acomp = 1 THEN pd.combinacao ELSE pd.nome_produto END,
        CASE WHEN pd.tem_acomp = 1 THEN pd.categorias_acomp ELSE pd.nome_produto END,
        pd.tem_acomp
    ORDER BY total_pedidos DESC, produtos";

    $stmt = $pdo->prepare($sql_produtos);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimos pedidos (ajustado para funcionar com todos os clientes)
    $sql_ultimos = "SELECT 
        p.id_pedido,
        p.data_pedido,
        (p.sub_total + p.taxa_entrega) as total,
        GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', prod.nome_produto) SEPARATOR ', ') as itens
        FROM pedidos p
        JOIN pedido_itens pi ON pi.fk_pedido_id = p.id_pedido
        JOIN produto prod ON prod.id_produto = pi.fk_produto_id
        $where_cliente
        AND DATE(p.data_pedido) BETWEEN ? AND ?
        GROUP BY p.id_pedido
        ORDER BY p.data_pedido DESC";

    $stmt = $pdo->prepare($sql_ultimos);
    $stmt->execute($params);
    $ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Análise de intervalos sem compras (ajustado para funcionar com todos os clientes)
    $sql_intervalos = "WITH datas_ordenadas AS (
        SELECT 
            data_pedido,
            LAG(data_pedido) OVER (ORDER BY data_pedido) as pedido_anterior
        FROM pedidos 
        $where_cliente
        AND DATE(data_pedido) BETWEEN ? AND ?
        ORDER BY data_pedido
    )
    SELECT 
        COUNT(*) as total_intervalos,
        MAX(DATEDIFF(data_pedido, pedido_anterior)) as maior_intervalo,
        AVG(DATEDIFF(data_pedido, pedido_anterior)) as media_intervalo
    FROM datas_ordenadas
    WHERE pedido_anterior IS NOT NULL";

    $stmt = $pdo->prepare($sql_intervalos);
    $stmt->execute($params);
    $intervalos = $stmt->fetch(PDO::FETCH_ASSOC);

    // Análise RFM (Recency, Frequency, Monetary)
    $sql_rfm = "WITH metricas_rfm AS (
        SELECT 
            DATEDIFF(CURRENT_DATE, MAX(data_pedido)) as recency,
            COUNT(*) as frequency,
            SUM(sub_total) as monetary
        FROM pedidos 
        $where_cliente
        AND DATE(data_pedido) BETWEEN ? AND ?
    ),
    scores AS (
        SELECT 
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
            END as M,
            recency,
            frequency,
            monetary
        FROM metricas_rfm
    )
    SELECT 
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
        END as segmento,
        recency,
        frequency,
        monetary
    FROM scores";

    error_log('SQL RFM: ' . $sql_rfm);
    $stmt = $pdo->prepare($sql_rfm);
    $stmt->execute($params);
    $rfm = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log('Dados RFM: ' . json_encode($rfm));

    // Análise de Afinidade (produtos frequentemente comprados juntos)
    $sql_afinidade = "WITH pedidos_cliente AS (
        SELECT pi1.fk_produto_id as produto1, pi2.fk_produto_id as produto2, 
               COUNT(*) as frequencia
        FROM pedido_itens pi1
        JOIN pedido_itens pi2 ON pi1.fk_pedido_id = pi2.fk_pedido_id 
            AND pi1.fk_produto_id < pi2.fk_produto_id
        JOIN pedidos p ON p.id_pedido = pi1.fk_pedido_id
        $where_cliente
        AND DATE(p.data_pedido) BETWEEN ? AND ?
        GROUP BY pi1.fk_produto_id, pi2.fk_produto_id
        HAVING COUNT(*) > 1
        ORDER BY COUNT(*) DESC
        LIMIT 5
    )
    SELECT 
        p1.nome_produto as produto1,
        p2.nome_produto as produto2,
        pc.frequencia
    FROM pedidos_cliente pc
    JOIN produto p1 ON p1.id_produto = pc.produto1
    JOIN produto p2 ON p2.id_produto = pc.produto2";

    $stmt = $pdo->prepare($sql_afinidade);
    $stmt->execute($params);
    $afinidade = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar dados do cliente
    if ($cliente_id) {
        $sql_cliente = "SELECT 
            c.nome_cliente,
            c.telefone_cliente as telefone,
            GROUP_CONCAT(
                DISTINCT CONCAT(
                    ce.nome_entrega, ' - ', 
                    ce.numero_entrega, ' - ',
                    cb.nome_bairro,
                    ' (R$ ', COALESCE(cb.valor_taxa, '0.00'), ')'
                ) SEPARATOR '||'
            ) as enderecos
            FROM clientes c
            LEFT JOIN cliente_entrega ce ON ce.fk_Cliente_id_cliente = c.id_cliente
            LEFT JOIN cliente_bairro cb ON cb.id_bairro = ce.fk_Bairro_id_bairro
            WHERE c.id_cliente = ?
            GROUP BY c.id_cliente";
        
        $stmt = $pdo->prepare($sql_cliente);
        $stmt->execute([$cliente_id]);
        $cliente_dados = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Adicionar logs antes do retorno final
    error_log('Retornando dados completos: ' . json_encode([
        'metricas' => $metricas,
        'rfm' => $rfm,
        'evolucao' => $evolucao,
        'produtos' => $produtos,
        'ultimos' => $ultimos
    ]));

    echo json_encode([
        'status' => 'success',
        'data' => [
            'cliente' => $cliente_dados ?? null,
            'metricas' => [
                'total_pedidos' => intval($metricas['total_pedidos']),
                'valor_total' => floatval($metricas['valor_total']),
                'ticket_medio' => floatval($metricas['ticket_medio']),
                'dias_com_pedidos' => intval($metricas['dias_com_pedidos']),
                'intervalos' => [
                    'total' => intval($intervalos['total_intervalos']),
                    'maior' => intval($intervalos['maior_intervalo']),
                    'media' => round(floatval($intervalos['media_intervalo']), 1)
                ]
            ],
            'rfm' => [
                'score' => $rfm['rfm_score'],
                'segmento' => $rfm['segmento']
            ],
            'afinidade' => $afinidade,
            'evolucao_pedidos' => $evolucao,
            'produtos_populares' => array_map(function($item) {
                // Separar os itens da combinação
                $itens = [];
                if (!empty($item['combinacao'])) {
                    $itens = explode(' | ', $item['combinacao']);
                }
                
                // Agrupar itens por categoria
                $itens_agrupados = [];
                foreach ($itens as $item_completo) {
                    if (preg_match('/(.*?): (.*)/', $item_completo, $matches)) {
                        $categoria = trim($matches[1]);
                        $nome = trim($matches[2]);
                        if (!isset($itens_agrupados[$categoria])) {
                            $itens_agrupados[$categoria] = [];
                        }
                        $itens_agrupados[$categoria][] = $nome;
                    }
                }
                
                return [
                    'produtos' => $item['produtos'] ?? '',
                    'combinacao' => $item['combinacao'] ?? '',
                    'categorias_acomp' => $item['categorias_acomp'] ?? '',
                    'itens_agrupados' => $itens_agrupados,
                    'quantidades' => $item['quantidades'] ?? '1x',
                    'total_pedidos' => intval($item['total_pedidos'] ?? 0)
                ];
            }, $produtos ?? []),
            'ultimos_pedidos' => $ultimos,
            'pedidos_individuais' => $pedidos_individuais
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 