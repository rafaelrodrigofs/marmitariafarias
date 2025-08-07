<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include_once '../../config/database.php';
    
    if (isset($_POST['retirada']) && $_POST['retirada'] === 'true') {
        // Salvar como retirada no estabelecimento
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
        }
        
        $_SESSION['carrinho']['retirada'] = true;
        unset($_SESSION['carrinho']['endereco']); // Remove endereço se existir
        
        echo json_encode(['status' => 'success', 'message' => 'Retirada no estabelecimento selecionada']);
    } else {
        // Salvar endereço de entrega
        $id_entrega = $_POST['id_entrega'] ?? null;
        $id_bairro = $_POST['id_bairro'] ?? null;
        
        if ($id_entrega && $id_bairro) {
            if (!isset($_SESSION['carrinho'])) {
                $_SESSION['carrinho'] = [];
            }
            
            // Buscar a taxa do bairro
            $sql = "SELECT valor_taxa FROM cliente_bairro WHERE id_bairro = :id_bairro";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id_bairro' => $id_bairro]);
            $bairro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['carrinho']['endereco'] = [
                'id_entrega' => $id_entrega,
                'id_bairro' => $id_bairro,
                'valor_taxa' => $bairro['valor_taxa'] ?? 0
            ];
            $_SESSION['carrinho']['retirada'] = false;
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Endereço salvo',
                'taxa' => floatval($bairro['valor_taxa'] ?? 0)
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Dados do endereço incompletos']);
        }
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
?>
