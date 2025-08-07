<?php
session_start();
require_once '../includes/functions.php';
include_once '../../config/database.php';

header('Content-Type: application/json');

try {
    inicializarCarrinho();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Carrinho limpo com sucesso'
    ]);

} catch (Exception $e) {
    error_log("Erro ao limpar carrinho: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao limpar carrinho'
    ]);
}
?> 