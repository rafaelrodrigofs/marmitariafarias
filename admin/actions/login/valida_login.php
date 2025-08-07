<?php

    require '../../config/database.php';

// Iniciar sessão
if (isset($_POST['remember_me'])) {
    // Configurar cookie para durar 1 ano
    ini_set('session.cookie_lifetime', 31536000);
    session_set_cookie_params(31536000);
}
session_start();

// Função para gerar token seguro
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Verificar se existe um cookie de login recente
if (!isset($_POST['username']) && isset($_COOKIE['recent_login_token'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$_COOKIE['recent_login_token']]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token_data) {
            // Buscar dados do usuário
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$token_data['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: ../../views/dashboard.php');
                exit();
            }
        }
    } catch (PDOException $e) {
        die("Erro: " . $e->getMessage());
    }
}

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    try {
        // Consulta ao banco com prepared statements para prevenir SQL Injection
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND password = MD5(:password)");
        $stmt->execute([
            ':username' => $user,
            ':password' => $pass,
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            // Armazenar informações do usuário na sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Se "lembrar-me" estiver marcado, criar token de login recente
            if (isset($_POST['remember_me'])) {
                $token = generateSecureToken();
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

                // Salvar token no banco
                $stmt = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expires_at]);

                // Criar cookie com o token
                setcookie(
                    'recent_login_token',
                    $token,
                    [
                        'expires' => strtotime('+30 days'),
                        'path' => '/',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]
                );
            }

            // Usuário autenticado com sucesso
            header('Location: ../../views/dashboard.php'); // Redireciona para o dashboard
            exit();
        } else {
            // Credenciais inválidas
            echo "<script>alert('Usuário ou senha inválidos');</script>";
            header('Location: ../../index.php');
        }
    } catch (PDOException $e) {
        die("Erro ao consultar o banco de dados: " . $e->getMessage());
    }
}
?>