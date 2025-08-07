<?php
session_start();
include_once '../../config/database.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['produto_id'])) {
        throw new Exception('ID do produto não fornecido');
    }

    $produto_id = $_GET['produto_id'];
    
    // Query única otimizada
    $sql = "SELECT 
        a.id_acomp,
        a.nome_acomp,
        COALESCE(par.is_required, 0) as is_required,
        COALESCE(par.min_escolhas, 0) as min_escolhas,
        COALESCE(par.max_escolhas, 1) as max_escolhas,
        COALESCE(par.permite_repetir, 0) as permite_repetir,
        sa.id_subacomp,
        sa.nome_subacomp,
        sa.preco_subacomp
    FROM produto_acomp pa
    JOIN acomp a ON a.id_acomp = pa.fk_acomp_id
    LEFT JOIN produto_acomp_regras par ON a.id_acomp = par.fk_acomp_id
    LEFT JOIN sub_acomp sa ON sa.fk_acomp_id = a.id_acomp AND sa.activated = 1
    WHERE pa.fk_produto_id = :produto_id
    ORDER BY a.id_acomp ASC, sa.id_subacomp ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['produto_id' => $produto_id]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reorganizar os dados
    $acompanhamentos = [];
    foreach ($resultados as $row) {
        $acomp_id = $row['id_acomp'];
        
        // Se é o primeiro registro deste acompanhamento
        if (!isset($acompanhamentos[$acomp_id])) {
            $acompanhamentos[$acomp_id] = [
                'id_acomp' => $row['id_acomp'],
                'nome_acomp' => $row['nome_acomp'],
                'is_required' => $row['is_required'],
                'min_escolhas' => $row['min_escolhas'],
                'max_escolhas' => $row['max_escolhas'],
                'permite_repetir' => $row['permite_repetir'],
                'subacompanhamentos' => [],
                'regras' => [
                    'obrigatorio' => (bool)$row['is_required'],
                    'min_escolhas' => (int)$row['min_escolhas'],
                    'max_escolhas' => (int)$row['max_escolhas'],
                    'permite_repetir' => (bool)$row['permite_repetir']
                ]
            ];
        }

        // Adicionar subacompanhamento se existir
        if ($row['id_subacomp']) {
            $acompanhamentos[$acomp_id]['subacompanhamentos'][] = [
                'id_subacomp' => $row['id_subacomp'],
                'nome_subacomp' => $row['nome_subacomp'],
                'preco_subacomp' => $row['preco_subacomp']
            ];
        }
    }

    // Converter para array indexado
    $acompanhamentos = array_values($acompanhamentos);

    echo json_encode([
        'status' => 'success',
        'tem_acompanhamentos' => !empty($acompanhamentos),
        'acompanhamentos' => $acompanhamentos
    ]);

} catch (Exception $e) {
    error_log("Erro em verificar_acompanhamentos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 