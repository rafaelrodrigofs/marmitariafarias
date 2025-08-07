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
    <title>Cardápio - Lunch&Fit</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="../assets/css/produtos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>
    
    <div class="main-content">
        <div class="acoes-cardapio">
            <button id="btn_gerenciar_categorias" class="btn-principal">
                <i class="fas fa-cog"></i>
                <span>Gerenciar Categorias</span>
            </button>
            <div class="filtros">
                <div class="campo-busca">
                    <i class="fas fa-search"></i>
                    <input type="text" id="busca_produto" placeholder="Buscar produto...">
                </div>
                <div class="campo-categoria">
                    <i class="fas fa-tag"></i>
                    <select id="filtro_categoria">
                        <option value="">Todas as categorias</option>
                    </select>
                </div>
                <button id="btn_filtrar" class="btn-filtrar">
                    <i class="fas fa-filter"></i>
                    <span>Filtrar</span>
                </button>
            </div>
        </div>

        <div id="lista_produtos" class="container-produtos">
            <div class="produtos-header">
                <span class="total-produtos">
                    <i class="fas fa-box"></i>
                    Total de produtos: <strong id="contador_produtos">0</strong>
                </span>
            </div>

            <div class="tabela-produtos">
                <div id="lista_produtos_content" class="lista-produtos-content">
                    <!-- Produtos serão carregados aqui via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/menu.js"></script>
    <script src="../assets/js/produtos.js"></script>

    <!-- Modal de Gerenciamento de Categorias -->
    <div id="modal_categorias" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Gerenciar Categorias</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="nome_categoria">Nome da Categoria</label>
                    <input type="text" id="nome_categoria" class="form-input" placeholder="Digite o nome da categoria">
                </div>
                <div class="lista-categorias">
                    <!-- As categorias serão carregadas aqui via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancelar">Cancelar</button>
                <button class="btn-salvar">Adicionar Categoria</button>
            </div>
        </div>
    </div>
</body>
</html>
