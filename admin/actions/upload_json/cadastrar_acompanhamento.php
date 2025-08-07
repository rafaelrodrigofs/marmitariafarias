<?php
require_once '../../config/database.php';

// Recebe os dados do POST
$data = json_decode(file_get_contents('php://input'), true);

// Se não houver dados JSON, tenta pegar do POST normal
if (!$data) {
    $data = [
        'nome_acomp' => $_POST['nome_acomp'] ?? null,
        'grupo_acomp' => $_POST['grupo_acomp'] ?? null,
        'preco' => $_POST['preco'] ?? 0.00
    ];
}

// Validação dos campos obrigatórios
if (empty($data['nome_acomp']) || empty($data['grupo_acomp'])) {
    echo json_encode(['success' => false, 'error' => 'Nome e grupo do acompanhamento são obrigatórios']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO sub_acomp (nome_subacomp, fk_acomp_id, preco_subacomp) 
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $data['nome_acomp'],
        $data['grupo_acomp'],
        $data['preco']
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar: ' . $e->getMessage()]);
}