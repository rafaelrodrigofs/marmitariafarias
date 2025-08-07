<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $categoria_id = $_POST['categoria_id'] ?? null;
    $nome = $_POST['nome'] ?? null;
    $preco = $_POST['preco'] ?? 0;
    $acompanhamentos = $_POST['acompanhamentos'] ?? [];

    if (!$categoria_id || !$nome) {
        throw new Exception('Nome do produto é obrigatório');
    }

    $pdo->beginTransaction();

    // Insere o novo produto
    $stmt = $pdo->prepare("INSERT INTO produto (nome_produto, preco_produto, fk_categoria_id, activated) VALUES (?, ?, ?, 1)");
    $stmt->execute([$nome, $preco, $categoria_id]);

    $id_produto = $pdo->lastInsertId();

    // Insere as conexões com os acompanhamentos
    if (!empty($acompanhamentos)) {
        $stmt = $pdo->prepare("INSERT INTO produto_acomp (fk_produto_id, fk_acomp_id) VALUES (?, ?)");
        foreach ($acompanhamentos as $acomp_id) {
            $stmt->execute([$id_produto, $acomp_id]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Produto adicionado com sucesso',
        'id' => $id_produto
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 