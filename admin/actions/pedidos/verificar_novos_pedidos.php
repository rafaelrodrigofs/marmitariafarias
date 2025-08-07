<?php
session_start();
include_once '../../config/database.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

// Configurar cabeçalhos
header('Content-Type: application/json');

// Obter o último ID conhecido
$ultimo_id = isset($_GET['ultimo_id']) ? intval($_GET['ultimo_id']) : 0;

try {
    // Consulta otimizada para verificar novos pedidos
    $sql = "SELECT 
                MAX(id_pedido) as max_id, 
                COUNT(*) as quantidade
            FROM 
                pedidos
            WHERE 
                id_pedido > :ultimo_id
                AND status_pedido = 0";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':ultimo_id', $ultimo_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar se há novos pedidos
    if ($resultado['quantidade'] > 0 && $resultado['max_id'] > $ultimo_id) {
        // Retornar informações sobre os novos pedidos
        echo json_encode([
            'status' => 'success',
            'novos_pedidos' => true,
            'quantidade' => (int)$resultado['quantidade'],
            'ultimo_id' => (int)$resultado['max_id']
        ]);
    } else {
        // Nenhum novo pedido
        echo json_encode([
            'status' => 'success',
            'novos_pedidos' => false,
            'ultimo_id' => $ultimo_id
        ]);
    }
} catch (PDOException $e) {
    // Erro na consulta
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao verificar novos pedidos: ' . $e->getMessage()
    ]);
}
?> 