<?php
session_start();
include_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_SESSION['carrinho']['cliente']['id_cliente'] ?? null;
    $nome_entrega = $_POST['nome_entrega'] ?? null;
    $numero_entrega = $_POST['numero_entrega'] ?? null;
    $bairro_id = $_POST['bairro_id'] ?? null;
    
    // Verifica se é retirada no local
    $isRetiradaLocal = $nome_entrega === 'Retirada no local';
    
    if ($cliente_id) {
        try {
            if ($isRetiradaLocal) {
                // Para retirada no local, usamos valores padrão
                $nome_entrega = 'Retirada no local';
                $numero_entrega = '';
                $bairro_id = 1; // ID do bairro "Retirada Local" no banco
            } else if (!$nome_entrega || !$numero_entrega || !$bairro_id) {
                throw new Exception('Dados incompletos para entrega');
            }

            // Inserir novo endereço
            $sql = "INSERT INTO cliente_entrega (nome_entrega, numero_entrega, fk_Cliente_id_cliente, fk_Bairro_id_bairro) 
                    VALUES (:nome_entrega, :numero_entrega, :cliente_id, :bairro_id)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'nome_entrega' => $nome_entrega,
                'numero_entrega' => $numero_entrega,
                'cliente_id' => $cliente_id,
                'bairro_id' => $bairro_id
            ]);
            
            $id_entrega = $pdo->lastInsertId();
            
            // Salvar o novo endereço como selecionado no carrinho
            $_SESSION['carrinho']['endereco'] = [
                'id_entrega' => $id_entrega,
                'id_bairro' => $bairro_id
            ];
            
            // Buscar dados completos do endereço
            $sql_end = "SELECT ce.id_entrega, ce.nome_entrega, ce.numero_entrega, 
                              cb.id_bairro, cb.nome_bairro
                       FROM cliente_entrega ce
                       LEFT JOIN cliente_bairro cb ON ce.fk_Bairro_id_bairro = cb.id_bairro
                       WHERE ce.id_entrega = :id_entrega";
                       
            $stmt_end = $pdo->prepare($sql_end);
            $stmt_end->execute(['id_entrega' => $id_entrega]);
            $endereco = $stmt_end->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Endereço cadastrado com sucesso',
                'endereco' => $endereco
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao cadastrar endereço: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Cliente não identificado'
        ]);
    }
    exit;
}

// Buscar bairros disponíveis
$sql_bairros = "SELECT id_bairro, nome_bairro FROM cliente_bairro ORDER BY nome_bairro";
$stmt_bairros = $pdo->prepare($sql_bairros);
$stmt_bairros->execute();
$bairros = $stmt_bairros->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'bairros' => $bairros
]);
?>
