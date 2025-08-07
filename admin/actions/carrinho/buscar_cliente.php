<?php
require_once '../../config/database.php';

$termo = $_GET['term'] ?? '';
$response = [];

try {
    $sql = "SELECT c.id_cliente, c.nome_cliente, c.telefone_cliente, 
                   ce.id_entrega, ce.nome_entrega, ce.numero_entrega,
                   cb.id_bairro, cb.nome_bairro, cb.valor_taxa,
                   e.nome_empresa
            FROM clientes c
            LEFT JOIN cliente_entrega ce ON ce.fk_Cliente_id_cliente = c.id_cliente
            LEFT JOIN cliente_bairro cb ON ce.fk_Bairro_id_bairro = cb.id_bairro
            LEFT JOIN empresas e ON c.fk_empresa_id = e.id_empresa
            WHERE c.nome_cliente LIKE :termo 
               OR c.telefone_cliente LIKE :termo
            GROUP BY c.id_cliente
            ORDER BY c.nome_cliente
            LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['termo' => "%$termo%"]);
    $response = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Erro na busca: ' . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($response);
?>
