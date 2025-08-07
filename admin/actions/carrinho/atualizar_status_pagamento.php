<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = isset($_POST['status']) ? intval($_POST['status']) : 0;
    
    // Log para debug
    error_log('Status recebido: ' . $status);
    
    // Valida o status
    if (!in_array($status, [0, 1])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Status inválido'
        ]);
        exit;
    }
    
    try {
        // Atualiza o status no carrinho da sessão
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
        }
        
        $_SESSION['carrinho']['status_pagamento'] = $status;
        
        // Log do carrinho atualizado
        error_log('Carrinho atualizado: ' . print_r($_SESSION['carrinho'], true));
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Status de pagamento atualizado',
            'data' => [
                'status_pagamento' => $status
            ]
        ]);
    } catch (Exception $e) {
        error_log('Erro ao atualizar status: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao atualizar status: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido'
    ]);
} 