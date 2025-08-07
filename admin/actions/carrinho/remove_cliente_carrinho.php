<?php
session_start();
header('Content-Type: application/json');

try {
    if (isset($_SESSION['carrinho'])) {
        // Remove apenas as informações do cliente, mantendo os produtos
        $_SESSION['carrinho']['cliente'] = null;
        $_SESSION['carrinho']['endereco'] = null;
        $_SESSION['carrinho']['retirada'] = false;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Cliente removido do carrinho'
        ]);
    } else {
        throw new Exception('Carrinho não encontrado');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 