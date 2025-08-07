<?php
session_start();

// Limpa todas as variáveis da sessão
$_SESSION = array();

// Destrói o cookie da sessão se existir
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Remove todos os cookies relacionados à aplicação
foreach ($_COOKIE as $name => $value) {
    if (strpos($name, 'lunchfit_') === 0) {
        setcookie($name, '', time()-42000, '/');
    }
}

// Destrói a sessão
session_destroy();

// Redireciona para a página de login sem mensagem
header('Location: ../../index.php');
exit();
?> 