<?php
session_start();
header('Content-Type: application/json');

error_log('=== LIMPA CARRINHO ===');
error_log('SESSION antes: ' . print_r($_SESSION, true));

// Verifica se o carrinho está armazenado na sessão
if (isset($_SESSION['carrinho'])) {
    // Limpa completamente o carrinho, mantendo a estrutura
    $_SESSION['carrinho'] = [
        'cliente' => null,
        'produtos' => [],
        'numero_pedido' => '',
        'hora_pedido' => '',
        'endereco' => null,
        'retirada' => false,
        'pagamento' => null
    ];
    
    error_log('SESSION depois: ' . print_r($_SESSION, true));
    
    // Retorna uma resposta de sucesso para o AJAX
    echo json_encode([
        'status' => 'success', 
        'message' => 'Carrinho limpo com sucesso.',
        'data' => $_SESSION['carrinho']
    ]);
} else {
    // Se o carrinho não existir, retorna um erro
    echo json_encode([
        'status' => 'error', 
        'message' => 'Carrinho não encontrado.'
    ]);
}
?>
