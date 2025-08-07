<?php
require_once '../config/database.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lunch&Fit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ffffff">
</head>
<body>
    <div class="welcome-section">
        <h1>Bem-vindo ao Marmitaria Farias</h1>
        <p>Gerencie seu restaurante de forma eficiente e profissional com nossa plataforma completa</p>
        
        <div class="features-grid">
            <div class="feature-item">
                <i class="fas fa-utensils"></i>
                <h3>Gestão de Cardápio</h3>
                <p>Organize seus pratos e categorias facilmente</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-chart-line"></i>
                <h3>Relatórios Detalhados</h3>
                <p>Acompanhe o desempenho do seu negócio</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-mobile-alt"></i>
                <h3>Pedidos Online</h3>
                <p>Receba pedidos de forma organizada</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-users"></i>
                <h3>Gestão de Clientes</h3>
                <p>Mantenha seu relacionamento com clientes</p>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
        <div class="alert alert-success">
            Você foi desconectado com sucesso!
        </div>
    <?php endif; ?>

    <div class="login-container">
        <div class="login-header">
            <div class="logo-container">
                <img src="../assets/img/logo.png" alt="Lunch&Fit Logo">
            </div>
            <!-- <h1>Bem-vindo ao Lunch&Fit</h1> -->
            <p>Entre para gerenciar seu restaurante</p>
        </div>

        <?php if(isset($_GET['error'])): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <span> fdsfUsuário ou senha incorretos. Tente novamente.</span>
        </div>
        <?php endif; ?>

        <?php
        if (isset($_COOKIE['recent_login_token'])) {
            try {
                $stmt = $pdo->prepare("
                    SELECT u.username, u.id 
                    FROM users u 
                    JOIN user_tokens t ON u.id = t.user_id 
                    WHERE t.token = ? AND t.expires_at > NOW()
                ");
                $stmt->execute([$_COOKIE['recent_login_token']]);
                $recent_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($recent_user) {
                    ?>
                    <div class="recent-login">
                        <p class="recent-login-title">Login Recente</p>
                        <form action="../actions/login/auto_login.php" method="POST" id="auto-login-form">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_COOKIE['recent_login_token']) ?>">
                            <div class="recent-user-card" onclick="document.getElementById('auto-login-form').submit();">
                                <img src="../assets/img/logo.png" alt="Avatar" class="recent-avatar">
                                <span class="recent-username"><?= htmlspecialchars($recent_user['username']) ?></span>
                            </div>
                        </form>
                    </div>
                    <?php
                }
            } catch (PDOException $e) {
                // Tratar erro silenciosamente
            }
        }
        ?>

        <form class="login-form" action="../actions/login/valida_login.php" method="POST">
            <div class="form-group">
                <label for="username">Email ou Telefone</label>
                <div class="input-group">
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-control" 
                           placeholder="Digite seu email ou telefone"
                           required>
                    <i class="fas fa-user"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <div class="input-group">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Digite sua senha"
                           required>
                    <i class="fas fa-lock"></i>
                </div>
            </div>

            <div class="form-group">
                <input type="checkbox" name="remember_me" id="remember_me">
                <label for="remember_me">Manter conectado</label>
            </div>

            <button type="submit" class="btn btn-primary">
                Entrar no Sistema
            </button>
        </form>

        <div class="login-footer">
            <a href="#" class="forgot-password">Esqueceu sua senha?</a>
        </div>
    </div>

    <script>
        // Efeitos de interação melhorados
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('i').style.color = 'var(--primary)';
                this.parentElement.style.transform = 'translateY(-2px)';
            });

            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.querySelector('i').style.color = 'var(--gray-400)';
                }
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Verifica se está em uma das páginas permitidas
        const allowedPages = ['login.php', 'dashboard.php'];
        const currentPage = window.location.pathname.split('/').pop();

        if (allowedPages.includes(currentPage)) {
            let deferredPrompt;
            
            // Debug para verificar se o evento está sendo capturado
            console.log('Página permitida detectada:', currentPage);
            
            window.addEventListener('beforeinstallprompt', (e) => {
                console.log('Evento beforeinstallprompt capturado!');
                e.preventDefault();
                deferredPrompt = e;
                
                // Criar botão de instalação
                const installButton = document.createElement('button');
                installButton.textContent = 'Instalar App';
                installButton.classList.add('btn', 'btn-primary');
                installButton.style.position = 'fixed';
                installButton.style.bottom = '20px';
                installButton.style.right = '20px';
                installButton.style.zIndex = '9999';
                installButton.style.padding = '10px 20px';
                
                installButton.addEventListener('click', async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        console.log('Escolha do usuário:', outcome);
                        if (outcome === 'accepted') {
                            console.log('PWA instalado com sucesso');
                        }
                        deferredPrompt = null;
                        installButton.remove();
                    }
                });
                
                document.body.appendChild(installButton);
            });

            // Registra o service worker
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', async () => {
                    try {
                        const registration = await navigator.serviceWorker.register('/admin/service-worker.js', {
                            scope: '/admin/'
                        });
                        console.log('Service Worker registrado com sucesso:', registration.scope);
                    } catch (error) {
                        console.error('Erro no registro do Service Worker:', error);
                    }
                });
            } else {
                console.log('Service Worker não é suportado neste navegador');
            }
        }

        // Verificar se o app já está instalado
        window.addEventListener('load', () => {
            if (window.matchMedia('(display-mode: standalone)').matches) {
                console.log('Aplicativo já está instalado e rodando em modo standalone');
            }
        });

        // Redirecionar após instalação
        window.addEventListener('appinstalled', (event) => {
            console.log('PWA foi instalado com sucesso!');
            const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
            if (isLoggedIn) {
                window.location.href = 'dashboard.php';
            } else {
                window.location.href = 'login.php';
            }
        });
    </script>
</body>
</html>


