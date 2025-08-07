<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $acomp_id = $_POST['acomp_id'] ?? null;
    $nome = $_POST['nome'] ?? null;
    $preco = $_POST['preco'] ?? 0;

    if (!$acomp_id || !$nome) {
        throw new Exception('Dados incompletos');
    }

    $stmt = $pdo->prepare("INSERT INTO sub_acomp (nome_subacomp, preco_subacomp, fk_acomp_id, activated) VALUES (?, ?, ?, 1)");
    $resultado = $stmt->execute([$nome, $preco, $acomp_id]);

    if ($resultado) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Subacompanhamento adicionado com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao inserir subacompanhamento');
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 