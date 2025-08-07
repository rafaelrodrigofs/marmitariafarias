<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../views/login.php');
    exit();
}

// Verificar se o formulário foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/produtos.php');
    exit();
}

// Incluir conexão com o banco de dados
require_once '../config/database.php';

// Função para sanitizar inputs
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Capturar dados do formulário
$produto_id = isset($_POST['produto_id']) ? intval($_POST['produto_id']) : 0;
$nome = sanitize($_POST['nome']);
$categoria_id = intval($_POST['categoria_id']);
$preco = str_replace(',', '.', sanitize($_POST['preco']));
$status = isset($_POST['status']) ? intval($_POST['status']) : 1;
$remover_imagem = isset($_POST['remover_imagem']) ? intval($_POST['remover_imagem']) : 0;

// Validar campos obrigatórios
if (empty($nome) || empty($categoria_id) || empty($preco)) {
    $_SESSION['erro'] = "Por favor, preencha todos os campos obrigatórios.";
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../views/produtos.php'));
    exit();
}

// Processar upload de imagem
$imagem = null;
$imagem_atual = null;

// Se for edição, buscar imagem atual
if ($produto_id > 0) {
    $stmt = $pdo->prepare("SELECT img FROM produto WHERE id_produto = ?");
    $stmt->execute([$produto_id]);
    $produto_atual = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($produto_atual && isset($produto_atual['img'])) {
        $imagem_atual = $produto_atual['img'];
    }
}

// Verificar se é para remover a imagem
if ($remover_imagem == 1 && $imagem_atual) {
    $caminho_imagem = "../uploads/produtos/" . $imagem_atual;
    if (file_exists($caminho_imagem)) {
        unlink($caminho_imagem);
    }
    $imagem = null;
} else {
    $imagem = $imagem_atual;
}

// Verificar se foi enviada uma nova imagem
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
    $arquivo = $_FILES['imagem'];
    
    // Verificar tipo de arquivo
    $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png'];
    if (!in_array($arquivo['type'], $tipos_permitidos)) {
        $_SESSION['erro'] = "Tipo de arquivo não permitido. Envie apenas imagens JPG ou PNG.";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../views/produtos.php'));
        exit();
    }
    
    // Verificar tamanho (máximo 2MB)
    if ($arquivo['size'] > 2 * 1024 * 1024) {
        $_SESSION['erro'] = "O arquivo é muito grande. Tamanho máximo permitido: 2MB.";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../views/produtos.php'));
        exit();
    }
    
    // Criar diretório de upload se não existir
    $diretorio_upload = "../uploads/produtos/";
    if (!is_dir($diretorio_upload)) {
        mkdir($diretorio_upload, 0755, true);
    }
    
    // Gerar nome único para o arquivo
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $nome_arquivo = uniqid('produto_') . '.' . $extensao;
    $caminho_destino = $diretorio_upload . $nome_arquivo;
    
    // Mover o arquivo para o diretório de destino
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_destino)) {
        // Se houver uma imagem anterior e foi enviada uma nova, remover a anterior
        if ($imagem_atual && $imagem_atual != $nome_arquivo) {
            $caminho_imagem_anterior = $diretorio_upload . $imagem_atual;
            if (file_exists($caminho_imagem_anterior)) {
                unlink($caminho_imagem_anterior);
            }
        }
        $imagem = $nome_arquivo;
    } else {
        $_SESSION['erro'] = "Erro ao fazer upload da imagem.";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../views/produtos.php'));
        exit();
    }
}

try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Verificar se é inserção ou atualização
    if ($produto_id > 0) {
        // Atualizar produto existente
        $sql = "UPDATE produto SET 
                nome_produto = ?, 
                fk_categoria_id = ?, 
                preco_produto = ?, 
                activated = ?";
        
        $params = [
            $nome, 
            $categoria_id, 
            $preco, 
            $status
        ];
        
        // Adicionar imagem à query se necessário
        if ($imagem !== null) {
            $sql .= ", img = ?";
            $params[] = $imagem;
        } elseif ($remover_imagem == 1) {
            $sql .= ", img = NULL";
        }
        
        $sql .= " WHERE id_produto = ?";
        $params[] = $produto_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $mensagem = "Produto atualizado com sucesso!";
    } else {
        // Inserir novo produto
        $sql = "INSERT INTO produto (
                nome_produto, 
                fk_categoria_id, 
                preco_produto, 
                activated";
        
        $params = [
            $nome, 
            $categoria_id, 
            $preco, 
            $status
        ];
        
        // Adicionar imagem à query se necessário
        if ($imagem !== null) {
            $sql .= ", img";
            $values_placeholder = "?, ?, ?, ?, ?";
            $params[] = $imagem;
        } else {
            $values_placeholder = "?, ?, ?, ?";
        }
        
        $sql .= ") VALUES (" . $values_placeholder . ")";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $produto_id = $pdo->lastInsertId();
        $mensagem = "Produto cadastrado com sucesso!";
    }
    
    // Confirmar transação
    $pdo->commit();
    
    // Redirecionar com mensagem de sucesso
    $_SESSION['sucesso'] = $mensagem;
    header('Location: ../views/produtos.php');
    exit();
    
} catch (PDOException $e) {
    // Reverter transação em caso de erro
    $pdo->rollBack();
    
    // Registrar erro e redirecionar
    error_log("Erro ao salvar produto: " . $e->getMessage());
    $_SESSION['erro'] = "Erro ao salvar o produto. Por favor, tente novamente.";
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../views/produtos.php'));
    exit();
} 