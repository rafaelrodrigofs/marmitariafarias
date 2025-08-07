<?php
header('Content-Type: application/json');

// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../admin/config/database.php';

// Recebe os dados do POST
$jsonData = file_get_contents('php://input');

// Verifica se os dados são válidos
$data = json_decode($jsonData);
if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados JSON inválidos']);
    exit;
}

// Formata o JSON com indentação para melhor legibilidade
$formattedJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Salva os dados no arquivo cardapio.json
$result = file_put_contents('cardapio.json', $formattedJson);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao salvar o arquivo']);
    exit;
}

echo json_encode(['success' => true]);
?>
