<?php
function formatNumber($number, $decimals = 2) {
    return number_format($number ?? 0, $decimals, ',', '.');
}

/**
 * Formata um CNPJ
 */
function formatCNPJ($cnpj) {
    if (!$cnpj) return '-';
    
    // Remove caracteres não numéricos
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Aplica a máscara
    return substr($cnpj, 0, 2) . '.' . 
           substr($cnpj, 2, 3) . '.' . 
           substr($cnpj, 5, 3) . '/' . 
           substr($cnpj, 8, 4) . '-' . 
           substr($cnpj, 12, 2);
}

/**
 * Formata um número de telefone
 */
function formatPhone($phone) {
    if (!$phone) return '-';
    
    // Remove caracteres não numéricos
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Verifica se é celular (9 dígitos) ou fixo (8 dígitos)
    if (strlen($phone) === 11) {
        return '(' . substr($phone, 0, 2) . ') ' . 
               substr($phone, 2, 5) . '-' . 
               substr($phone, 7);
    } else {
        return '(' . substr($phone, 0, 2) . ') ' . 
               substr($phone, 2, 4) . '-' . 
               substr($phone, 6);
    }
}

/**
 * Formata um valor monetário
 */
function formatMoney($value) {
    if (!$value) return 'R$ 0,00';
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Formata uma data
 */
function formatDate($date) {
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

/**
 * Formata data e hora
 */
function formatDateTime($datetime) {
    if (!$datetime) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Inicializa ou reinicializa o carrinho com a estrutura completa
 */
function inicializarCarrinho() {
    $_SESSION['carrinho'] = [
        'cliente' => null,
        'produtos' => [],
        'numero_pedido' => '',
        'data_pedido' => date('Y-m-d'),
        'hora_pedido' => date('H:i:s'),
        'total' => 0,
        'endereco' => null,
        'pagamento' => null,
        'retirada' => false,
        'status_pagamento' => 1  // PAGO por padrão
    ];
    return $_SESSION['carrinho'];
}

/**
 * Garante que o carrinho tem todos os campos necessários
 */
function garantirEstruturaCarrinho() {
    if (!isset($_SESSION['carrinho'])) {
        return inicializarCarrinho();
    }

    $estrutura_padrao = [
        'cliente' => null,
        'produtos' => [],
        'numero_pedido' => '',
        'data_pedido' => date('Y-m-d'),
        'hora_pedido' => date('H:i:s'),
        'total' => 0,
        'endereco' => null,
        'pagamento' => null,
        'retirada' => false,
        'status_pagamento' => 1
    ];

    // Garante que todos os campos existem
    foreach ($estrutura_padrao as $campo => $valor_padrao) {
        if (!isset($_SESSION['carrinho'][$campo])) {
            $_SESSION['carrinho'][$campo] = $valor_padrao;
        }
    }

    return $_SESSION['carrinho'];
}
?> 