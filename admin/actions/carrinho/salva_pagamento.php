<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pagamento = $_POST['id_pagamento'] ?? null;
    
    if ($id_pagamento) {
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
        }
        
        $_SESSION['carrinho']['pagamento'] = [
            'id_pagamento' => $id_pagamento
        ];
        
        echo json_encode(['status' => 'success', 'message' => 'Forma de pagamento salva']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID do pagamento não fornecido']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
?>
