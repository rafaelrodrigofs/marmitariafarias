<?php
// Habilitar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';

header('Content-Type: application/json');

// Recebe os dados JSON
$dados = json_decode(file_get_contents('php://input'), true);

// Log dos dados recebidos
error_log("Dados recebidos: " . print_r($dados, true));

try {
    // Formata o telefone
    $telefone = $dados['telefone_cliente'];
    
    error_log("Telefone formatado: " . $telefone);
    
    // Busca o ID do cliente
    $stmt = $pdo->prepare("SELECT id_cliente FROM clientes WHERE telefone_cliente = ?");
    $stmt->execute([$telefone]);
    $cliente = $stmt->fetch();
    
    if ($cliente) {
        error_log("Cliente encontrado com ID: " . $cliente['id_cliente']);
        
        // Busca o ID do bairro
        $bairroId = 1; // ID padrão
        
        if (!empty($dados['bairro'])) {
            $bairroNome = trim($dados['bairro']);
            error_log("Buscando bairro: '" . $bairroNome . "'");
            
            $stmtBairro = $pdo->prepare("SELECT id_bairro FROM cliente_bairro WHERE nome_bairro = ?");
            $stmtBairro->execute([$bairroNome]);
            $bairroResult = $stmtBairro->fetch();
            
            if ($bairroResult) {
                $bairroId = $bairroResult['id_bairro'];
                error_log("Bairro encontrado com ID: " . $bairroId);
            } else {
                error_log("Bairro '" . $bairroNome . "' não encontrado na tabela cliente_bairro. Usando ID padrão: " . $bairroId);
                // Verificar todos os bairros disponíveis para debug
                $stmtTodosBairros = $pdo->query("SELECT id_bairro, nome_bairro FROM cliente_bairro LIMIT 10");
                $todosBairros = $stmtTodosBairros->fetchAll(PDO::FETCH_ASSOC);
                error_log("Primeiros 10 bairros disponíveis: " . print_r($todosBairros, true));
            }
        } else {
            error_log("Nome do bairro não fornecido. Usando ID padrão: " . $bairroId);
        }

        // Verificar se o endereço já existe para este cliente
        $stmtEnderecoExistente = $pdo->prepare("
            SELECT id_entrega FROM cliente_entrega 
            WHERE nome_entrega = ? AND numero_entrega = ? AND fk_Cliente_id_cliente = ?
        ");
        $stmtEnderecoExistente->execute([$dados['rua'], $dados['numero'], $cliente['id_cliente']]);
        $enderecoExistente = $stmtEnderecoExistente->fetch();
        
        if ($enderecoExistente) {
            error_log("Endereço já existe para este cliente com ID: " . $enderecoExistente['id_entrega']);
            echo json_encode([
                'success' => true,
                'message' => 'Endereço já cadastrado para este cliente',
                'endereco_id' => $enderecoExistente['id_entrega']
            ]);
            exit;
        }

        // Prepara a query para inserção
        $query = "
            INSERT INTO cliente_entrega 
            (nome_entrega, numero_entrega, fk_Cliente_id_cliente, fk_Bairro_id_bairro) 
            VALUES (?, ?, ?, ?)
        ";
        error_log("Inserindo endereço: Rua='" . $dados['rua'] . "', Número='" . $dados['numero'] . 
                 "', Cliente ID=" . $cliente['id_cliente'] . ", Bairro ID=" . $bairroId);
        
        $stmt = $pdo->prepare($query);
        
        $stmt->execute([
            $dados['rua'],
            $dados['numero'],
            $cliente['id_cliente'],
            $bairroId
        ]);
        
        $novoEnderecoId = $pdo->lastInsertId();
        error_log("Endereço cadastrado com sucesso. ID inserido: " . $novoEnderecoId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Endereço cadastrado com sucesso',
            'endereco_id' => $novoEnderecoId
        ]);
    } else {
        error_log("Erro: Cliente com telefone '" . $telefone . "' não encontrado");
        // Verificar os primeiros clientes no banco para debug
        $stmtClientes = $pdo->query("SELECT id_cliente, telefone_cliente FROM clientes LIMIT 5");
        $clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
        error_log("Exemplos de clientes no banco: " . print_r($clientes, true));
        
        // Registra informações detalhadas para debug
        error_log("Dados recebidos na requisição: " . json_encode($dados));
        error_log("Telefone formatado para busca: " . $telefone);
        
        // Verifica a estrutura dos dados
        $dadosDebug = [
            'telefone_recebido' => $dados['telefone_cliente'] ?? 'não informado',
            'telefone_formatado' => $telefone,
            'rua' => $dados['rua'] ?? 'não informada',
            'numero' => $dados['numero'] ?? 'não informado',
            'bairro' => $dados['bairro'] ?? 'não informado',
            'query_executada' => "SELECT id_cliente FROM clientes WHERE telefone_cliente = '$telefone'"
        ];
        error_log("Detalhes da tentativa de cadastro: " . print_r($dadosDebug, true));
        
        echo json_encode([
            'success' => false,
            'error' => 'Cliente não encontrado',
            'debug' => $dadosDebug // Inclui informações de debug na resposta
        ]);
    }
} catch (Exception $e) {
    error_log("Exceção capturada: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao cadastrar endereço'
    ]);
}