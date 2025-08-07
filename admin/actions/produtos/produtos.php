<?php
session_start();

include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $busca = $_POST['busca'] ?? '';
    $categoria = $_POST['categoria'] ?? '';

    $sql = "WITH pedidos_info AS (
        SELECT 
            p.id_produto,
            COUNT(DISTINCT pi.id_pedido_item) as pedidos_count,
            GROUP_CONCAT(DISTINCT pi.id_pedido_item) as pedidos_ids,
            GROUP_CONCAT(DISTINCT ped.id_pedido) as numeros_pedidos
        FROM produto p
        LEFT JOIN pedido_itens pi ON pi.fk_produto_id = p.id_produto
        LEFT JOIN pedidos ped ON pi.fk_pedido_id = ped.id_pedido
        GROUP BY p.id_produto
    ),
    subacomp_info AS (
        SELECT 
            sa.id_subacomp,
            COUNT(DISTINCT pia.id_pedido_item_acomp) as subacomp_count,
            GROUP_CONCAT(DISTINCT CONCAT(pia.id_pedido_item_acomp, ':', ped.id_pedido)) as subacomp_pedidos
        FROM sub_acomp sa
        LEFT JOIN pedido_item_acomp pia ON pia.fk_subacomp_id = sa.id_subacomp
        LEFT JOIN pedido_itens pi ON pia.fk_pedido_item_id = pi.id_pedido_item
        LEFT JOIN pedidos ped ON pi.fk_pedido_id = ped.id_pedido
        GROUP BY sa.id_subacomp
    )
    SELECT DISTINCT 
        c.id_categoria, c.nome_categoria,
        p.id_produto, p.nome_produto, p.preco_produto, COALESCE(p.activated, 1) as activated,
        a.id_acomp, a.nome_acomp,
        sa.id_subacomp, sa.nome_subacomp, sa.preco_subacomp, sa.activated as sa_activated,
        par.min_escolhas, par.max_escolhas, par.is_required,
        pi.pedidos_count, pi.pedidos_ids, pi.numeros_pedidos,
        si.subacomp_count, si.subacomp_pedidos
    FROM categoria c
    LEFT JOIN produto p ON p.fk_categoria_id = c.id_categoria
    LEFT JOIN pedidos_info pi ON pi.id_produto = p.id_produto
    LEFT JOIN produto_acomp pa ON p.id_produto = pa.fk_produto_id
    LEFT JOIN acomp a ON pa.fk_acomp_id = a.id_acomp
    LEFT JOIN sub_acomp sa ON a.id_acomp = sa.fk_acomp_id
    LEFT JOIN subacomp_info si ON si.id_subacomp = sa.id_subacomp
    LEFT JOIN produto_acomp_regras par ON a.id_acomp = par.fk_acomp_id
    WHERE 1=1 " . 
    (!empty($busca) ? "AND (p.nome_produto LIKE :busca OR c.nome_categoria LIKE :busca)" : "") .
    (!empty($categoria) ? "AND c.id_categoria = :categoria" : "") . 
    " ORDER BY c.id_categoria, p.id_produto, a.id_acomp, sa.activated DESC, sa.nome_subacomp ASC";

    $stmt = $pdo->prepare($sql);
    if (!empty($busca)) $stmt->bindValue(':busca', "%$busca%");
    if (!empty($categoria)) $stmt->bindValue(':categoria', $categoria);
    $stmt->execute();
    
    $categorias = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $catId = $row['id_categoria'];
        $prodId = $row['id_produto'];
        $acompId = $row['id_acomp'];
        
        if (!isset($categorias[$catId])) {
            $categorias[$catId] = [
                'id' => $catId,
                'nome' => $row['nome_categoria'],
                'produtos' => []
            ];
        }

        if ($prodId && !isset($categorias[$catId]['produtos'][$prodId])) {
            $categorias[$catId]['produtos'][$prodId] = [
                'id' => $prodId,
                'nome' => $row['nome_produto'],
                'preco' => $row['preco_produto'],
                'activated' => (int)$row['activated'],
                'pedidos_count' => (int)$row['pedidos_count'],
                'pedidos_ids' => $row['pedidos_ids'],
                'numeros_pedidos' => $row['numeros_pedidos'],
                'acompanhamentos' => []
            ];
        }

        if ($acompId && $prodId) {
            if (!isset($categorias[$catId]['produtos'][$prodId]['acompanhamentos'][$acompId])) {
                $categorias[$catId]['produtos'][$prodId]['acompanhamentos'][$acompId] = [
                    'id' => $acompId,
                    'nome' => $row['nome_acomp'],
                    'min_escolhas' => $row['min_escolhas'],
                    'max_escolhas' => $row['max_escolhas'],
                    'is_required' => $row['is_required'],
                    'subacompanhamentos' => []
                ];
            }

            if ($row['id_subacomp']) {
                $categorias[$catId]['produtos'][$prodId]['acompanhamentos'][$acompId]['subacompanhamentos'][] = [
                    'id' => $row['id_subacomp'],
                    'nome' => $row['nome_subacomp'],
                    'preco' => $row['preco_subacomp'],
                    'activated' => $row['sa_activated'],
                    'pedidos_count' => (int)$row['subacomp_count'],
                    'pedidos_detalhes' => $row['subacomp_pedidos']
                ];
            }
        }
    }

    $categorias = array_values(array_map(function($cat) {
        $cat['produtos'] = array_values($cat['produtos']);
        foreach ($cat['produtos'] as &$prod) {
            $prod['acompanhamentos'] = array_values($prod['acompanhamentos']);
        }
        return $cat;
    }, $categorias));

    echo json_encode(['status' => 'success', 'categorias' => $categorias]);

} catch (Exception $e) {
    error_log("Erro em produtos.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar produtos: ' . $e->getMessage()]);
}
