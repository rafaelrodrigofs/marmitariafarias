<?php
require_once '../../config/database.php';

// Recebe o JSON enviado e converte para array
$dados = json_decode(file_get_contents('php://input'), true);

// Verifica se os dados necessários foram recebidos
if (!isset($dados['nome_produto']) || !isset($dados['categoria']) || !isset($dados['preco'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Dados incompletos'
    ]);
    exit;
}

try {
    // Limpa e valida os dados
    $nome_produto = trim($dados['nome_produto']);
    $categoria = intval($dados['categoria']);
    $preco = str_replace(',', '.', $dados['preco']);
    
    // Verifica se o produto já existe
    $stmt = $pdo->prepare("SELECT id_produto FROM produto WHERE nome_produto = ?");
    $stmt->execute([$nome_produto]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Produto já cadastrado'
        ]);
        exit;
    }
    
    // Insere o novo produto
    $stmt = $pdo->prepare("
        INSERT INTO produto (nome_produto, fk_categoria_id, preco_produto) 
        VALUES (?, ?, ?)
    ");
    
    if ($stmt->execute([$nome_produto, $categoria, $preco])) {
        echo json_encode([
            'success' => true,
            'nome_produto' => $nome_produto
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao cadastrar produto'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}
?> 