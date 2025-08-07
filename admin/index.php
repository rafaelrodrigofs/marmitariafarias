<?php
// Aumentar o tempo de vida da sessão (em segundos)
ini_set('session.gc_maxlifetime', 31536000); // 1 ano
ini_set('session.cookie_lifetime', 31536000); // 1 ano

// Configurar o cookie para durar mais
session_set_cookie_params([
    'lifetime' => 31536000,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true, // Use true se estiver usando HTTPS
    'httponly' => true,
    'samesite' => 'Lax' // ou 'Strict', dependendo da sua necessidade
]);

// Iniciar a sessão
session_start();

// Verificar se o usuário está logado
if (isset($_SESSION['user_id'])) {
    // Redirecionar para o dashboard se estiver logado
    header('Location: views/dashboard.php');
    exit;
} else {
    // Redirecionar para a página de login se não estiver logado
    header('Location: views/login.php');
    exit;
}
?>