<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); 
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Controle - Lunch&Fit</title>
    
    <!-- Adicionar preload para a fonte com crossorigin -->
    <link rel="preload" href="https://fonts.gstatic.com/s/poppins/v22/pxiEyp8kv8JHgFVrJJfecg.woff2" as="font" type="font/woff2" crossorigin>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="../assets/css/pedidos.css">
    <link rel="stylesheet" href="../assets/carrinho/modal-carrinho.css">
    <link rel="stylesheet" href="../assets/carrinho/modal-acompanhamentos.css">
    <link rel="stylesheet" href="../assets/carrinho/modal-endereco.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.0/css/all.min.css">
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>
    
    <!-- Carrinho -->
    <div id="cart-container">
        <div id="empty-cart-message" style="display: none;">
            Carrinho vazio
        </div>
        <div id="cart-items">
            <!-- Items serão inseridos aqui via JavaScript -->
        </div>
        <div id="cart-total-container" style="display: none;">
            Total: <span id="cart-total">R$ 0,00</span>
        </div>
    </div>

    <div class="main-content">
    <!-- <header>
        <i class="fa-solid fa-bars" title="Menu"></i>
        <i class="fa-solid fa-cart-shopping" title="Carrinho"></i>
    </header> -->
    <div id="menu">
    <?php 
        include_once '../config/database.php';
        try {
            $sql = "
                SELECT 
                    c.id_categoria,
                    c.nome_categoria,
                    p.id_produto,
                    p.nome_produto,
                    p.preco_produto,
                    p.img
                FROM 
                    categoria c
                JOIN 
                    produto p ON p.fk_categoria_id = c.id_categoria
                WHERE 
                    p.activated = 1
                ORDER BY 
                    c.id_categoria, p.id_produto
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            
            $currentCategory = null;
            $isFirstCategory = true;

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($currentCategory !== $row['nome_categoria']) {
                    if (!$isFirstCategory) {
                        echo '</div>';
                    }
                    $currentCategory = $row['nome_categoria'];
                    echo '<h3>' . htmlspecialchars($row['nome_categoria']) . '</h3>';
                    echo '<div class="categoria-produtos">';
                    $isFirstCategory = false;
                }
    ?>
                <div class="menu-item" data-id="<?php echo $row['id_produto']; ?>">
                    <div class="menu-item-content">
                    <div class="menu-item-image">
                            <?php if (!empty($row['img']) && file_exists("../uploads/produtos/" . htmlspecialchars($row['img']))): ?>
                                <img src="../uploads/produtos/<?= htmlspecialchars($row['img']) ?>" alt="<?= htmlspecialchars($row['nome_produto']) ?>" onerror="this.src='../uploads/produtos/default.jpg'">
                            <?php else: ?>
                                <img src="../uploads/produtos/default.jpg" alt="Imagem padrão">
                            <?php endif; ?>
                        </div>
                        <div class="menu-item-info">
                            <div class="menu-item-title"><?= htmlspecialchars($row['nome_produto']) ?></div>
                            <div class="preco">R$ <?= number_format($row['preco_produto'], 2, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
    <?php 
            }
            if (!$isFirstCategory) {
                echo '</div>';
            }
        } catch (PDOException $e) {
            echo "Erro no banco de dados: " . $e->getMessage();
        }
    ?>
    </div>
    <div class="modal-base modal-carrinho active">
        <div class="modal-content">

            <!-- Seção Informações do Pedido -->
            <div class="pedido-section">
                <h3>Informações do Pedido</h3>
                <div class="pedido-info">
                    
                </div>
            </div>

            <!-- Seção Cliente -->
            <div class="cliente-section">
                <h3>Cliente</h3>
                <!-- Se tem cliente -->
                <div class="cliente-info">
                    
                </div>
                <!-- OU se não tem cliente -->
                <div class="cliente-busca">
                    
                </div>
            </div>

            <!-- Lista de Produtos -->
            <div class="produtos-lista">
                <h3>Produtos</h3>
                <div class="produto-item">
                    
                </div>
            </div>

            <!-- Seção de Entrega -->
            <div class="entrega-section">
                <h3>Forma de Entrega</h3>
                
            </div>

            <!-- Forma de Pagamento -->
            <div class="pagamento-section">
                <h3>Forma de Pagamento</h3>
                
            </div>

            <!-- Status do Pagamento -->
            <div class="status-pagamento-section">
                <h3>Status do Pagamento</h3>
                <div class="status-toggle">
                    <span class="status-pagamento status-pendente">PENDENTE</span>
                    <button class="btn-toggle-status">Marcar como Pago</button>
                </div>
            </div>

            <!-- Valores -->
            <div class="valores-section">
                <div class="subtotal">
                    <span>Subtotal:</span>
                    <span></span>
                </div>
                <div class="taxa-entrega">
                    <span>Taxa de Entrega:</span>
                    <span></span>
                </div>
                <div class="total">
                    <h4>Total do Pedido</h4>
                    <p></p>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="modal-actions">
                <button class="btn-cancelar">Fechar</button>
                <button class="btn-limpar">Limpar Carrinho</button>
                <button class="btn-finalizar">Finalizar Pedido</button>
            </div>
        </div>
    </div>
    </div>
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Adicionar tratamento para lidar com erros no console
        window.addEventListener('error', function(e) {
            console.log('Erro capturado:', e.message);
            // Evita que o erro seja exibido no console
            if (e.message.includes('preloaded using link preload but not used')) {
                e.preventDefault();
            }
        });
    </script>
    <script src="../assets/carrinho/endereco.js"></script>
    <script src="../assets/carrinho/acompanhamentos.js"></script>
    <script src="../assets/carrinho/carrinho.js"></script>
    <script src="../assets/carrinho/cliente.js"></script>
    <script src="../assets/carrinho/menu.js"></script>
    <script src="../assets/carrinho/main.js"></script>
</body>
</html>
