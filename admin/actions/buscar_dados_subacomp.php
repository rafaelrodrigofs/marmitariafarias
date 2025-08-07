<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit();
}

include_once '../config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
    exit();
}

$id_subacomp = intval($_GET['id']);

try {
    // Buscar informações básicas do sub-acompanhamento
    $sql_subacomp = "SELECT id_subacomp, nome_subacomp, preco_subacomp 
                     FROM sub_acomp 
                     WHERE id_subacomp = :id";
    $stmt_subacomp = $pdo->prepare($sql_subacomp);
    $stmt_subacomp->bindParam(':id', $id_subacomp, PDO::PARAM_INT);
    $stmt_subacomp->execute();
    $subacomp = $stmt_subacomp->fetch(PDO::FETCH_ASSOC);
    
    if (!$subacomp) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Sub-acompanhamento não encontrado']);
        exit();
    }
    
    // Buscar ingredientes do sub-acompanhamento
    $sql_ingredientes = "SELECT COUNT(*) as total, SUM(custo_total) as custo_total 
                         FROM subacomp_ingredientes 
                         WHERE fk_subacomp_id = :id";
    $stmt_ingredientes = $pdo->prepare($sql_ingredientes);
    $stmt_ingredientes->bindParam(':id', $id_subacomp, PDO::PARAM_INT);
    $stmt_ingredientes->execute();
    $ingredientes = $stmt_ingredientes->fetch(PDO::FETCH_ASSOC);
    
    // Preparar resposta
    $resposta = [
        'status' => 'success',
        'id_subacomp' => $subacomp['id_subacomp'],
        'nome_subacomp' => $subacomp['nome_subacomp'],
        'preco_subacomp' => $subacomp['preco_subacomp'],
        'total_ingredientes' => $ingredientes['total'] ?? 0,
        'custo_total' => $ingredientes['custo_total'] ?? 0
    ];
    
    header('Content-Type: application/json');
    echo json_encode($resposta);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    exit();
}
?> 