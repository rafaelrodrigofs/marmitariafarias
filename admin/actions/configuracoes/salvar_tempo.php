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
    // Obter dados do formulário
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
    $min = isset($_POST['min']) ? intval($_POST['min']) : 0;
    $max = isset($_POST['max']) ? intval($_POST['max']) : 0;
    
    // Validar dados
    if (!in_array($tipo, ['balcao', 'delivery'])) {
        throw new Exception('Tipo de tempo inválido');
    }
    
    if ($min <= 0 || $max <= 0) {
        throw new Exception('Valores de tempo devem ser maiores que zero');
    }
    
    if ($min > $max) {
        throw new Exception('Tempo mínimo não pode ser maior que o tempo máximo');
    }
    
    // Verificar se já existe configuração para este tipo
    $check_sql = "SELECT id_config FROM configuracoes WHERE tipo = :tipo AND chave = :chave";
    $check_stmt = $pdo->prepare($check_sql);
    
    // Verificar tempo mínimo
    $check_stmt->execute([
        ':tipo' => 'tempo_estimado',
        ':chave' => $tipo . '_min'
    ]);
    $config_min_exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar tempo máximo
    $check_stmt->execute([
        ':tipo' => 'tempo_estimado',
        ':chave' => $tipo . '_max'
    ]);
    $config_max_exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Atualizar ou inserir tempo mínimo
    if ($config_min_exists) {
        $sql_min = "UPDATE configuracoes SET valor = :valor, atualizado_em = NOW() 
                    WHERE tipo = 'tempo_estimado' AND chave = :chave";
    } else {
        $sql_min = "INSERT INTO configuracoes (tipo, chave, valor, criado_em, atualizado_em) 
                    VALUES ('tempo_estimado', :chave, :valor, NOW(), NOW())";
    }
    
    $stmt_min = $pdo->prepare($sql_min);
    $stmt_min->execute([
        ':chave' => $tipo . '_min',
        ':valor' => $min
    ]);
    
    // Atualizar ou inserir tempo máximo
    if ($config_max_exists) {
        $sql_max = "UPDATE configuracoes SET valor = :valor, atualizado_em = NOW() 
                    WHERE tipo = 'tempo_estimado' AND chave = :chave";
    } else {
        $sql_max = "INSERT INTO configuracoes (tipo, chave, valor, criado_em, atualizado_em) 
                    VALUES ('tempo_estimado', :chave, :valor, NOW(), NOW())";
    }
    
    $stmt_max = $pdo->prepare($sql_max);
    $stmt_max->execute([
        ':chave' => $tipo . '_max',
        ':valor' => $max
    ]);
    
    // Registrar log de alteração
    $log_sql = "INSERT INTO log_configuracoes (fk_user_id, tipo, chave, valor_anterior, valor_novo, data_alteracao) 
                VALUES (:user_id, 'tempo_estimado', :chave, :valor_anterior, :valor_novo, NOW())";
    
    $log_stmt = $pdo->prepare($log_sql);
    
    // Log para tempo mínimo
    $valor_anterior_min = $config_min_exists ? $pdo->query("SELECT valor FROM configuracoes WHERE tipo = 'tempo_estimado' AND chave = '{$tipo}_min'")->fetchColumn() : '';
    $log_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':chave' => $tipo . '_min',
        ':valor_anterior' => $valor_anterior_min,
        ':valor_novo' => $min
    ]);
    
    // Log para tempo máximo
    $valor_anterior_max = $config_max_exists ? $pdo->query("SELECT valor FROM configuracoes WHERE tipo = 'tempo_estimado' AND chave = '{$tipo}_max'")->fetchColumn() : '';
    $log_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':chave' => $tipo . '_max',
        ':valor_anterior' => $valor_anterior_max,
        ':valor_novo' => $max
    ]);
    
    // Confirmar transação
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Tempo estimado salvo com sucesso',
        'data' => [
            'tipo' => $tipo,
            'min' => $min,
            'max' => $max
        ]
    ]);
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar tempo estimado: ' . $e->getMessage()
    ]);
}
?> 