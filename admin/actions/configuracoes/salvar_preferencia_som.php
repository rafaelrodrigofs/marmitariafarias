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

// Obter dados da requisição
$habilitado = isset($_POST['habilitado']) ? (int)$_POST['habilitado'] : 0;
$user_id = $_SESSION['user_id'];

try {
    // Verificar se já existe uma preferência para este usuário
    $sql_check = "SELECT id FROM usuario_preferencias WHERE user_id = :user_id AND tipo = 'notificacao_som'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() > 0) {
        // Atualizar preferência existente
        $sql = "UPDATE usuario_preferencias 
                SET valor = :valor, data_atualizacao = NOW() 
                WHERE user_id = :user_id AND tipo = 'notificacao_som'";
    } else {
        // Inserir nova preferência
        $sql = "INSERT INTO usuario_preferencias (user_id, tipo, valor, data_atualizacao) 
                VALUES (:user_id, 'notificacao_som', :valor, NOW())";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':valor', $habilitado, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Preferência salva com sucesso']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar preferência: ' . $e->getMessage()]);
}
?> 