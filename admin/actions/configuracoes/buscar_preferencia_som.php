<?php
session_start();
include_once '../../config/database.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Configurar cabeçalhos
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

try {
    // Buscar preferência do usuário
    $sql = "SELECT valor FROM usuario_preferencias 
            WHERE user_id = :user_id AND tipo = 'notificacao_som'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'habilitado' => (bool)$resultado['valor']]);
    } else {
        // Se não encontrar, retornar valor padrão (desabilitado)
        echo json_encode(['success' => true, 'habilitado' => false]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar preferência: ' . $e->getMessage()]);
}
?> 