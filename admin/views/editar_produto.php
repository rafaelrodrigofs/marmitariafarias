<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); 
    exit();
}

// Verificar se o ID do produto foi fornecido
$produto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$modo = $produto_id > 0 ? 'editar' : 'novo';
$titulo_pagina = $modo === 'editar' ? 'Editar Produto' : 'Novo Produto';

// Se estiver no modo editar, buscar dados do produto
$produto = null;
if ($modo === 'editar') {
    // Conexão com o banco de dados
    require_once '../config/database.php';
    
    // Buscar dados do produto
    $sql = "SELECT * FROM produto WHERE id_produto = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        // Produto não encontrado
        header('Location: produtos.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?> - Lunch&Fit</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="../assets/css/editar_produto.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>
    
    <div class="main-content">
        <div class="cabecalho-pagina">
            <h1><?php echo $titulo_pagina; ?></h1>
            <a href="produtos.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i>
                <span>Voltar para lista</span>
            </a>
        </div>

        <div class="formulario-container">
            <form id="form_produto" method="POST" action="../controllers/salvar_produto.php" enctype="multipart/form-data">
                <input type="hidden" name="produto_id" value="<?php echo $produto_id; ?>">
                
                <div class="form-grid">
                    <div class="form-coluna-principal">
                        <div class="form-grupo">
                            <label for="nome">Nome do Produto*</label>
                            <input type="text" id="nome" name="nome" required 
                                value="<?php echo isset($produto['nome_produto']) ? htmlspecialchars($produto['nome_produto']) : ''; ?>">
                        </div>
                        
                        <div class="form-grupo">
                            <label for="descricao">Descrição</label>
                            <textarea id="descricao" name="descricao" rows="4"><?php echo isset($produto['descricao']) ? htmlspecialchars($produto['descricao']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-linha">
                            <div class="form-grupo">
                                <label for="categoria">Categoria*</label>
                                <select id="categoria" name="categoria_id" required>
                                    <option value="">Selecione uma categoria</option>
                                    <?php
                                    // Buscar categorias do banco de dados
                                    $sql_categorias = "SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria";
                                    $result_categorias = $pdo->query($sql_categorias);
                                    
                                    if ($result_categorias) {
                                        while ($categoria = $result_categorias->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = isset($produto['fk_categoria_id']) && $produto['fk_categoria_id'] == $categoria['id_categoria'] ? 'selected' : '';
                                            echo "<option value='{$categoria['id_categoria']}' {$selected}>{$categoria['nome_categoria']}</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-grupo">
                                <label for="preco">Preço (R$)*</label>
                                <input type="text" id="preco" name="preco" required 
                                    value="<?php echo isset($produto['preco_produto']) ? number_format($produto['preco_produto'], 2, ',', '.') : ''; ?>"
                                    placeholder="0,00">
                            </div>
                        </div>
                        
                        <div class="form-grupo">
                            <label for="ingredientes">Ingredientes</label>
                            <textarea id="ingredientes" name="ingredientes" rows="3"><?php echo isset($produto['ingredientes']) ? htmlspecialchars($produto['ingredientes']) : ''; ?></textarea>
                            <small>Separe os ingredientes por vírgula</small>
                        </div>
                        
                        <div class="form-grupo">
                            <label for="informacoes_nutricionais">Informações Nutricionais</label>
                            <textarea id="informacoes_nutricionais" name="informacoes_nutricionais" rows="4"><?php echo isset($produto['informacoes_nutricionais']) ? htmlspecialchars($produto['informacoes_nutricionais']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-linha">
                            <div class="form-grupo">
                                <label for="calorias">Calorias (kcal)</label>
                                <input type="number" id="calorias" name="calorias" min="0" 
                                    value="<?php echo isset($produto['calorias']) ? $produto['calorias'] : ''; ?>">
                            </div>
                            
                            <div class="form-grupo">
                                <label for="proteinas">Proteínas (g)</label>
                                <input type="number" id="proteinas" name="proteinas" min="0" step="0.1" 
                                    value="<?php echo isset($produto['proteinas']) ? $produto['proteinas'] : ''; ?>">
                            </div>
                            
                            <div class="form-grupo">
                                <label for="carboidratos">Carboidratos (g)</label>
                                <input type="number" id="carboidratos" name="carboidratos" min="0" step="0.1" 
                                    value="<?php echo isset($produto['carboidratos']) ? $produto['carboidratos'] : ''; ?>">
                            </div>
                            
                            <div class="form-grupo">
                                <label for="gorduras">Gorduras (g)</label>
                                <input type="number" id="gorduras" name="gorduras" min="0" step="0.1" 
                                    value="<?php echo isset($produto['gorduras']) ? $produto['gorduras'] : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-coluna-lateral">
                        <div class="form-grupo">
                            <label>Status</label>
                            <div class="opcoes-status">
                                <label class="opcao-radio">
                                    <input type="radio" name="status" value="1" 
                                        <?php echo (!isset($produto['activated']) || $produto['activated'] == 1) ? 'checked' : ''; ?>>
                                    <span>Ativo</span>
                                </label>
                                <label class="opcao-radio">
                                    <input type="radio" name="status" value="0" 
                                        <?php echo (isset($produto['activated']) && $produto['activated'] == 0) ? 'checked' : ''; ?>>
                                    <span>Inativo</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-grupo">
                            <label for="imagem">Imagem do Produto</label>
                            <div class="upload-imagem">
                                <div class="preview-imagem" id="preview_imagem">
                                    <?php if (isset($produto['img']) && !empty($produto['img'])): ?>
                                        <img src="../uploads/produtos/<?php echo $produto['img']; ?>" alt="Imagem do produto">
                                    <?php else: ?>
                                        <div class="sem-imagem">
                                            <i class="fas fa-image"></i>
                                            <span>Sem imagem</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="acoes-imagem">
                                    <label for="input_imagem" class="btn-upload">
                                        <i class="fas fa-upload"></i>
                                        <span>Selecionar imagem</span>
                                    </label>
                                    <input type="file" id="input_imagem" name="imagem" accept="image/*" style="display: none;">
                                    
                                    <?php if (isset($produto['img']) && !empty($produto['img'])): ?>
                                        <button type="button" id="btn_remover_imagem" class="btn-remover-imagem">
                                            <i class="fas fa-trash"></i>
                                            <span>Remover</span>
                                        </button>
                                        <input type="hidden" name="remover_imagem" id="remover_imagem" value="0">
                                    <?php endif; ?>
                                </div>
                                <div class="info-imagem">
                                    <small>Formatos aceitos: JPG, PNG. Tamanho máximo: 2MB</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-grupo">
                            <label>Opções Especiais</label>
                            <div class="opcoes-checkbox">
                                <label class="opcao-check">
                                    <input type="checkbox" name="destaque" value="1" 
                                        <?php echo (isset($produto['destaque']) && $produto['destaque'] == 1) ? 'checked' : ''; ?>>
                                    <span>Produto em destaque</span>
                                </label>
                                <label class="opcao-check">
                                    <input type="checkbox" name="vegetariano" value="1" 
                                        <?php echo (isset($produto['vegetariano']) && $produto['vegetariano'] == 1) ? 'checked' : ''; ?>>
                                    <span>Vegetariano</span>
                                </label>
                                <label class="opcao-check">
                                    <input type="checkbox" name="vegano" value="1" 
                                        <?php echo (isset($produto['vegano']) && $produto['vegano'] == 1) ? 'checked' : ''; ?>>
                                    <span>Vegano</span>
                                </label>
                                <label class="opcao-check">
                                    <input type="checkbox" name="sem_gluten" value="1" 
                                        <?php echo (isset($produto['sem_gluten']) && $produto['sem_gluten'] == 1) ? 'checked' : ''; ?>>
                                    <span>Sem Glúten</span>
                                </label>
                                <label class="opcao-check">
                                    <input type="checkbox" name="sem_lactose" value="1" 
                                        <?php echo (isset($produto['sem_lactose']) && $produto['sem_lactose'] == 1) ? 'checked' : ''; ?>>
                                    <span>Sem Lactose</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="acoes-formulario">
                    <button type="button" class="btn-cancelar" onclick="window.location.href='produtos.php'">
                        <i class="fas fa-times"></i>
                        <span>Cancelar</span>
                    </button>
                    <button type="submit" class="btn-salvar">
                        <i class="fas fa-save"></i>
                        <span>Salvar Produto</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/menu.js"></script>
    <script>
        $(document).ready(function() {
            // Formatação do campo de preço
            $('#preco').on('input', function() {
                let valor = $(this).val().replace(/\D/g, '');
                valor = (parseFloat(valor) / 100).toFixed(2).replace('.', ',');
                $(this).val(valor);
            });
            
            // Preview da imagem
            $('#input_imagem').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#preview_imagem').html(`<img src="${e.target.result}" alt="Preview da imagem">`);
                    }
                    reader.readAsDataURL(file);
                }
            });
            
            // Remover imagem
            $('#btn_remover_imagem').click(function() {
                $('#preview_imagem').html(`
                    <div class="sem-imagem">
                        <i class="fas fa-image"></i>
                        <span>Sem imagem</span>
                    </div>
                `);
                $('#input_imagem').val('');
                $('#remover_imagem').val('1');
                $(this).hide();
            });
            
            // Validação do formulário
            $('#form_produto').submit(function(e) {
                let valido = true;
                
                // Validar campos obrigatórios
                if ($('#nome').val().trim() === '') {
                    alert('Por favor, informe o nome do produto.');
                    $('#nome').focus();
                    valido = false;
                } else if ($('#categoria').val() === '') {
                    alert('Por favor, selecione uma categoria.');
                    $('#categoria').focus();
                    valido = false;
                } else if ($('#preco').val().trim() === '') {
                    alert('Por favor, informe o preço do produto.');
                    $('#preco').focus();
                    valido = false;
                }
                
                if (!valido) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
