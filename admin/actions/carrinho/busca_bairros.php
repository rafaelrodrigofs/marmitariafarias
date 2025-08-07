<?php
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT id_bairro, nome_bairro, valor_taxa 
            FROM cliente_bairro 
            ORDER BY nome_bairro";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $bairros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($bairros);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>
