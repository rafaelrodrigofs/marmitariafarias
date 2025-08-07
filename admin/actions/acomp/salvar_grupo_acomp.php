<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    error_log("[ACOMP_ERROR] Usuário não autenticado");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit();
}

include_once '../config/database.php';

// Função helper para log
function logDebug($message, $data = null) {
    $log = "[ACOMP_DEBUG] " . $message;
    if ($data !== null) {
        $log .= " | Data: " . print_r($data, true);
    }
    error_log($log);
}

$response = ['status' => 'error', 'message' => 'Dados inválidos'];

logDebug("POST recebido:", $_POST);

if (isset($_POST['nomeGrupo'])) {
    try {
        $pdo->beginTransaction();
        logDebug("Iniciando transação");
        
        $nome_acomp = trim($_POST['nomeGrupo']);
        $id_acomp = !empty($_POST['grupoId']) ? $_POST['grupoId'] : null;
        
        logDebug("Dados do grupo:", [
            'nome' => $nome_acomp,
            'id' => $id_acomp
        ]);
        
        // Inserir ou atualizar o grupo principal
        if ($id_acomp) {
            $sql = "UPDATE acomp SET nome_acomp = ? WHERE id_acomp = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome_acomp, $id_acomp]);
            logDebug("Grupo atualizado - ID: $id_acomp");
        } else {
            $sql = "INSERT INTO acomp (nome_acomp) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome_acomp]);
            $id_acomp = $pdo->lastInsertId();
            logDebug("Novo grupo criado - ID: $id_acomp");
        }
        
        // Processar sub-acompanhamentos
        if (isset($_POST['sub_acomp']) && is_array($_POST['sub_acomp'])) {
            logDebug("Sub-acompanhamentos recebidos:", $_POST['sub_acomp']);
            
            $sql_sub = "INSERT INTO sub_acomp (nome_subacomp, preco_subacomp, fk_acomp_id, activated) 
                       VALUES (?, ?, ?, 1)";
            $stmt_sub = $pdo->prepare($sql_sub);
            
            foreach ($_POST['sub_acomp'] as $index => $nome_sub) {
                if (!empty($nome_sub)) {
                    $preco = isset($_POST['preco_sub'][$index]) ? 
                            floatval($_POST['preco_sub'][$index]) : 0.00;
                    
                    logDebug("Inserindo sub-acomp:", [
                        'nome' => $nome_sub,
                        'preco' => $preco,
                        'grupo_id' => $id_acomp
                    ]);
                    
                    try {
                        $stmt_sub->execute([
                            trim($nome_sub),
                            $preco,
                            $id_acomp
                        ]);
                        logDebug("Sub-acomp inserido com sucesso - ID: " . $pdo->lastInsertId());
                    } catch (PDOException $e) {
                        logDebug("Erro ao inserir sub-acomp:", [
                            'erro' => $e->getMessage(),
                            'codigo' => $e->getCode()
                        ]);
                        throw $e;
                    }
                }
            }
        } else {
            logDebug("Nenhum sub-acompanhamento recebido");
        }
        
        $pdo->commit();
        logDebug("Transação commitada com sucesso");
        $response = ['status' => 'success', 'message' => 'Grupo salvo com sucesso'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        logDebug("ERRO - Rollback executado:", [
            'mensagem' => $e->getMessage(),
            'codigo' => $e->getCode(),
            'trace' => $e->getTraceAsString()
        ]);
        $response = [
            'status' => 'error', 
            'message' => 'Erro ao salvar: ' . $e->getMessage(),
            'debug_info' => [
                'codigo' => $e->getCode(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine()
            ]
        ];
    }
} else {
    logDebug("Nome do grupo não fornecido");
}

logDebug("Resposta final:", $response);
header('Content-Type: application/json');
echo json_encode($response); 