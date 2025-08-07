<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['error' => 'Não autorizado']));
}

include_once '../../config/database.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$itens_por_pagina = isset($_GET['itens']) ? (int)$_GET['itens'] : 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $itens_por_pagina;

try {
    // Busca total de registros com o filtro
    $sql_total = "SELECT COUNT(*) as total 
                  FROM clientes 
                  WHERE nome_cliente LIKE :search 
                  OR telefone_cliente LIKE :search";
    
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute(['search' => "%$search%"]);
    $total = $stmt_total->fetch()['total'];
    
    // Busca os clientes
    $sql = "SELECT 
                c.id_cliente,
                c.nome_cliente,
                c.telefone_cliente,
                DATEDIFF(CURRENT_DATE, MAX(p.data_pedido)) as dias_sem_comprar,
                COUNT(p.id_pedido) as total_pedidos,
                CASE 
                    WHEN MAX(p.data_pedido) IS NULL THEN 'Nunca comprou'
                    WHEN DATEDIFF(CURRENT_DATE, MAX(p.data_pedido)) <= 30 THEN 'Ativo'
                    ELSE 'Inativo'
                END as status_cliente
            FROM clientes c
            LEFT JOIN pedidos p ON c.id_cliente = p.fk_cliente_id
            WHERE c.nome_cliente LIKE :search 
            OR c.telefone_cliente LIKE :search
            GROUP BY c.id_cliente
            ORDER BY 
                CASE WHEN COUNT(p.id_pedido) = 0 THEN 1 ELSE 0 END, -- Coloca quem nunca comprou por último
                c.nome_cliente ASC -- Ordena alfabeticamente dentro de cada grupo
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'total' => $total,
        'clientes' => $clientes,
        'total_paginas' => ceil($total / $itens_por_pagina)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao buscar clientes']);
} 