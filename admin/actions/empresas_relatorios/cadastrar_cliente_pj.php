<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$empresa_id = intval($_POST['empresa'] ?? 0);

if (empty($nome) || empty($empresa_id)) {
    echo json_encode(['success' => false, 'message' => 'Nome e empresa são obrigatórios']);
    exit;
}

try {
    // Verificar se o telefone já existe (se fornecido)
    if (!empty($telefone)) {
        $stmt = $pdo->prepare("SELECT id_cliente FROM clientes WHERE telefone_cliente = ?");
        $stmt->execute([$telefone]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Telefone já cadastrado']);
            exit;
        }
    }

    // Inserir novo cliente
    $stmt = $pdo->prepare("INSERT INTO clientes (nome_cliente, telefone_cliente, fk_empresa_id, tipo_cliente) VALUES (?, ?, ?, 1)");
    $stmt->execute([$nome, $telefone, $empresa_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar: ' . $e->getMessage()]);
}