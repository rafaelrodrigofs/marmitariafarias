<?php
session_start();
include_once '../../config/database.php';
header('Content-Type: application/json');

try {
    if (!isset($_POST['acomp_id']) || !isset($_POST['produto_id']) || !isset($_POST['acao'])) {
        throw new Exception('Parâmetros inválidos');
    }

    $acomp_id = (int)$_POST['acomp_id'];
    $produto_id = (int)$_POST['produto_id'];
    $acao = $_POST['acao'];

    if ($acao === 'adicionar') {
        // Primeiro, insere ou atualiza as regras na produto_acomp_regras
        $sql_regras = "INSERT INTO produto_acomp_regras 
                      (fk_acomp_id, min_escolhas, max_escolhas, is_required) 
                      VALUES (:acomp_id, :min, :max, :required)
                      ON DUPLICATE KEY UPDATE 
                      min_escolhas = VALUES(min_escolhas),
                      max_escolhas = VALUES(max_escolhas),
                      is_required = VALUES(is_required)";
        
        $stmt = $pdo->prepare($sql_regras);
        $stmt->execute([
            'acomp_id' => $acomp_id,
            'min' => $_POST['min_escolhas'] ?? 0,
            'max' => $_POST['max_escolhas'] ?? 1,
            'required' => $_POST['is_required'] ?? 0
        ]);

        // Depois, insere na produto_acomp
        $sql_insert = "INSERT INTO produto_acomp (fk_produto_id, fk_acomp_id) 
                      VALUES (:produto_id, :acomp_id)
                      ON DUPLICATE KEY UPDATE fk_acomp_id = VALUES(fk_acomp_id)";
        
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute([
            'produto_id' => $produto_id,
            'acomp_id' => $acomp_id
        ]);

    } else {
        // Remove a associação da produto_acomp
        $sql_delete = "DELETE FROM produto_acomp 
                      WHERE fk_produto_id = :produto_id 
                      AND fk_acomp_id = :acomp_id";
        
        $stmt = $pdo->prepare($sql_delete);
        $stmt->execute([
            'produto_id' => $produto_id,
            'acomp_id' => $acomp_id
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => $acao === 'adicionar' ? 'Regras associadas com sucesso' : 'Regras removidas com sucesso'
    ]);

} catch (Exception $e) {
    error_log("Erro em associar_regras_produto.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 