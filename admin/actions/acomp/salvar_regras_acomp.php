<?php
session_start();
include_once '../config/database.php';
header('Content-Type: application/json');

try {
    $data = $_POST;
    
    // Log para debug
    error_log('Dados recebidos: ' . print_r($data, true));

    // Validar dados recebidos
    $campos_obrigatorios = ['acomp_id', 'min_escolhas', 'max_escolhas', 'is_required'];
    foreach ($campos_obrigatorios as $campo) {
        if (!isset($data[$campo])) {
            throw new Exception("Campo obrigatório não fornecido: $campo");
        }
    }

    // Verificar se já existe regra para este acompanhamento
    $sql = "SELECT id_regra FROM produto_acomp_regras 
            WHERE fk_acomp_id = :acomp_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'acomp_id' => $data['acomp_id']
    ]);
    
    $regra_existente = $stmt->fetch();

    if ($regra_existente) {
        // Update
        $sql = "UPDATE produto_acomp_regras SET 
                is_required = :is_required,
                min_escolhas = :min_escolhas,
                max_escolhas = :max_escolhas
                WHERE fk_acomp_id = :acomp_id";
    } else {
        // Insert
        $sql = "INSERT INTO produto_acomp_regras 
                (fk_acomp_id, is_required, min_escolhas, max_escolhas)
                VALUES 
                (:acomp_id, :is_required, :min_escolhas, :max_escolhas)";
    }

    // Log da query que será executada
    error_log('SQL a ser executada: ' . $sql);
    
    $stmt = $pdo->prepare($sql);
    $params = [
        'acomp_id' => $data['acomp_id'],
        'is_required' => $data['is_required'],
        'min_escolhas' => $data['min_escolhas'],
        'max_escolhas' => $data['max_escolhas']
    ];
    
    // Log dos parâmetros
    error_log('Parâmetros: ' . print_r($params, true));
    
    $result = $stmt->execute($params);
    
    // Log do resultado da execução
    error_log('Resultado da execução: ' . ($result ? 'sucesso' : 'falha'));

    if (!$result) {
        throw new Exception("Erro ao salvar no banco de dados");
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Regras salvas com sucesso',
        'data' => $params
    ]);

} catch (Exception $e) {
    error_log('Erro em salvar_regras_acomp.php: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 