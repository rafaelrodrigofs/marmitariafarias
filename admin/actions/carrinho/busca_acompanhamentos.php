<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['produto_id'])) {
        throw new Exception('ID do produto não fornecido');
    }

    $produto_id = $_GET['produto_id'];

    // Buscar acompanhamentos associados ao produto com suas regras
    $sql = "SELECT DISTINCT 
                a.id_acomp, 
                a.nome_acomp,
                par.min_escolhas,
                par.max_escolhas,
                par.is_required,
                par.permite_repetir
            FROM produto_acomp pa
            JOIN acomp a ON a.id_acomp = pa.fk_acomp_id
            LEFT JOIN produto_acomp_regras par ON a.id_acomp = par.fk_acomp_id
            WHERE pa.fk_produto_id = :produto_id
            ORDER BY a.nome_acomp";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $acompanhamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Coletar todos os IDs de acompanhamentos
    $acomp_ids = array_column($acompanhamentos, 'id_acomp');

    // Buscar todos os subacompanhamentos de uma vez
    if (!empty($acomp_ids)) {
        $placeholders = str_repeat('?,', count($acomp_ids) - 1) . '?';
        $sql_sub = "SELECT id_subacomp, nome_subacomp, preco_subacomp, fk_acomp_id 
                    FROM sub_acomp 
                    WHERE fk_acomp_id IN ($placeholders) 
                    AND activated = 1
                    ORDER BY fk_acomp_id, nome_subacomp";
                    
        $stmt_sub = $pdo->prepare($sql_sub);
        $stmt_sub->execute($acomp_ids);
        
        $todos_subacomp = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

        // Organizar subacompanhamentos por acompanhamento
        $subacomp_por_acomp = [];
        foreach ($todos_subacomp as $sub) {
            $subacomp_por_acomp[$sub['fk_acomp_id']][] = [
                'id_subacomp' => $sub['id_subacomp'],
                'nome_subacomp' => $sub['nome_subacomp'],
                'preco_subacomp' => $sub['preco_subacomp']
            ];
        }

        // Adicionar subacompanhamentos aos seus respectivos acompanhamentos
        foreach ($acompanhamentos as &$acomp) {
            $acomp['subacompanhamentos'] = $subacomp_por_acomp[$acomp['id_acomp']] ?? [];
        }
    }

    echo json_encode([
        'status' => 'success',
        'acompanhamentos' => $acompanhamentos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
