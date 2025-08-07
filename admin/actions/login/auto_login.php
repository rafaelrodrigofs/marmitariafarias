<?php
require_once '../../config/database.php';
session_start();

if (isset($_POST['token'])) {
    try {
        // Verificar o token e buscar usuário
        $stmt = $pdo->prepare("
            SELECT u.* 
            FROM users u 
            JOIN user_tokens t ON u.id = t.user_id 
            WHERE t.token = ? AND t.expires_at > NOW()
        ");
        $stmt->execute([$_POST['token']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Login automático
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Atualizar data de expiração do token
            $stmt = $pdo->prepare("
                UPDATE user_tokens 
                SET expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) 
                WHERE token = ?
            ");
            $stmt->execute([$_POST['token']]);

            // Redirecionar para o dashboard
            header('Location: ../../views/dashboard.php');
            exit();
        }
    } catch (PDOException $e) {
        // Log do erro
        error_log("Erro no login automático: " . $e->getMessage());
    }
}

// Se algo der errado, volta para a página de login
header('Location: ../../index.php');
exit();
?> 