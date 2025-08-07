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
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Verificar status atual da loja
    $sql_check = "SELECT valor FROM configuracoes WHERE tipo = 'geral' AND chave = 'loja_aberta'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute();
    $status_atual = $stmt_check->fetchColumn();
    
    // Se a configuração não existir, criar
    if ($status_atual === false) {
        $sql_insert = "INSERT INTO configuracoes (tipo, chave, valor, criado_em, atualizado_em) 
                       VALUES ('geral', 'loja_aberta', '1', NOW(), NOW())";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute();
    } else {
        // Atualizar para aberto (1)
        $sql_update = "UPDATE configuracoes SET valor = '1', atualizado_em = NOW() 
                       WHERE tipo = 'geral' AND chave = 'loja_aberta'";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute();
    }
    
    // Registrar log de alteração
    $log_sql = "INSERT INTO log_configuracoes (fk_user_id, tipo, chave, valor_anterior, valor_novo, data_alteracao) 
                VALUES (:user_id, 'geral', 'loja_aberta', :valor_anterior, '1', NOW())";
    
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':valor_anterior' => $status_atual !== false ? $status_atual : 'NULL'
    ]);
    
    // Atualizar horário de funcionamento
    $horario_abertura = date('H:i:s');
    $horario_fechamento = date('H:i:s', strtotime('+8 hours')); // Padrão de 8 horas de funcionamento
    
    // Verificar se já existe configuração de horário
    $sql_check_horario = "SELECT COUNT(*) FROM configuracoes WHERE tipo = 'horario_funcionamento' AND chave = 'hoje'";
    $stmt_check_horario = $pdo->prepare($sql_check_horario);
    $stmt_check_horario->execute();
    $horario_existe = $stmt_check_horario->fetchColumn() > 0;
    
    if ($horario_existe) {
        $sql_update_horario = "UPDATE configuracoes SET 
                               valor = :valor, 
                               atualizado_em = NOW() 
                               WHERE tipo = 'horario_funcionamento' AND chave = 'hoje'";
    } else {
        $sql_update_horario = "INSERT INTO configuracoes 
                               (tipo, chave, valor, criado_em, atualizado_em) 
                               VALUES 
                               ('horario_funcionamento', 'hoje', :valor, NOW(), NOW())";
    }
    
    $valor_horario = json_encode([
        'abertura' => $horario_abertura,
        'fechamento' => $horario_fechamento,
        'data' => date('Y-m-d')
    ]);
    
    $stmt_update_horario = $pdo->prepare($sql_update_horario);
    $stmt_update_horario->execute([':valor' => $valor_horario]);
    
    // Confirmar transação
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Loja reaberta com sucesso',
        'horario_abertura' => $horario_abertura,
        'horario_fechamento' => $horario_fechamento
    ]);
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao reabrir loja: ' . $e->getMessage()
    ]);
}
?> 