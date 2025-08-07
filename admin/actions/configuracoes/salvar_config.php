<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
    exit;
}

try {
    // Verificar qual configuração está sendo salva
    if (isset($_POST['aceitar_automatico'])) {
        $chave = 'aceitar_automatico';
        $valor = $_POST['aceitar_automatico'] ? '1' : '0';
    } else {
        // Se não for a configuração de aceitar automaticamente, verificar outras
        $chave = isset($_POST['chave']) ? $_POST['chave'] : '';
        $valor = isset($_POST['valor']) ? $_POST['valor'] : '';
        
        if (empty($chave)) {
            throw new Exception('Chave de configuração não fornecida');
        }
    }
    
    // Verificar se a configuração já existe
    $check_sql = "SELECT COUNT(*) FROM configuracoes WHERE chave = :chave";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':chave' => $chave]);
    $config_exists = $check_stmt->fetchColumn() > 0;
    
    // Atualizar ou inserir configuração
    if ($config_exists) {
        $sql = "UPDATE configuracoes SET valor = :valor WHERE chave = :chave";
    } else {
        $sql = "INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':chave' => $chave,
        ':valor' => $valor
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuração salva com sucesso',
        'data' => [
            'chave' => $chave,
            'valor' => $valor
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar configuração: ' . $e->getMessage()
    ]);
}
?> 