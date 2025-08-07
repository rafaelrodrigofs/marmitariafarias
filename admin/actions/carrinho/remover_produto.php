<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $index = $_POST['index'] ?? null;
    
    if ($index === null) {
        throw new Exception('Índice do produto não fornecido');
    }
    
    if (isset($_SESSION['carrinho']['produtos'][$index])) {
        // Remove o produto específico do array
        array_splice($_SESSION['carrinho']['produtos'], $index, 1);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Produto removido com sucesso'
        ]);
    } else {
        throw new Exception('Produto não encontrado no carrinho');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 