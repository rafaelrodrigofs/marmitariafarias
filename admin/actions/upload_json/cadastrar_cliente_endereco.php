<?php
require_once '../../config/database.php';

// Habilitar logs de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar arquivo de log personalizado
$logFile = __DIR__ . '/../../../logs/cliente_endereco_' . date('Y-m-d') . '.log';

// Função para registrar logs
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Garantir que o diretório de logs existe
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Escrever no arquivo de log
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Também registrar no log padrão do PHP
    error_log($message);
}

// Log inicial
logMessage("Iniciando processamento de cadastro de cliente e endereço");


// Recebe os dados do POST
$data = json_decode(file_get_contents('php://input'), true);

// Log dos dados recebidos
error_log("Dados recebidos: " . print_r($data, true));

// Validação básica apenas dos campos obrigatórios do cliente
if (empty($data['nome_cliente']) || empty($data['telefone_cliente'])) {
    echo json_encode(['success' => false, 'error' => 'Nome e telefone são obrigatórios']);
    exit;
}

// Função para formatar telefone
function formatarTelefoneParaBanco($numero) {
    // Primeiro remove TODOS os caracteres não numéricos
    $numero = preg_replace('/[^0-9]/', '', $numero);
    
    // Agora verifica o tamanho e ajusta conforme necessário
    if (substr($numero, 0, 2) === '00') {
        return $numero;
    }
    
    if (strlen($numero) === 11) {
        return $numero;
    }
    
    if (strlen($numero) === 9) {
        return '41' . $numero;
    }
    
    if (strlen($numero) === 8) {
        return '41' . '9' . $numero;
    }
    
    return $numero;
}

try {
    $pdo->beginTransaction();
    
    // Formata o telefone para o banco - garante que será apenas números
    $telefone_banco = formatarTelefoneParaBanco($data['telefone_cliente']);
    error_log("Telefone formatado para banco: " . $telefone_banco); // Log para debug
    
    // Verifica se o cliente já existe
    $stmtCheck = $pdo->prepare("SELECT id_cliente FROM clientes WHERE telefone_cliente = ?");
    $stmtCheck->execute([$telefone_banco]);
    $clienteExistente = $stmtCheck->fetch();
    
    if ($clienteExistente) {
        $clienteId = $clienteExistente['id_cliente'];
    } else {
        // Insere novo cliente
        $stmt = $pdo->prepare("INSERT INTO clientes (nome_cliente, telefone_cliente) VALUES (?, ?)");
        $stmt->execute([$data['nome_cliente'], $telefone_banco]);
        $clienteId = $pdo->lastInsertId();
    }

    // Verifica se existe endereço nos dados
    if (isset($data['endereco']) && is_array($data['endereco'])) {
        $endereco = $data['endereco'];
        
        // Busca o ID do bairro
        $bairroId = 1; // ID padrão
        if (!empty($endereco['bairro'])) {
            $stmtBairro = $pdo->prepare("SELECT id_bairro FROM cliente_bairro WHERE nome_bairro = ?");
            $stmtBairro->execute([$endereco['bairro']]);
            if ($bairroResult = $stmtBairro->fetch()) {
                $bairroId = $bairroResult['id_bairro'];
            }
        }

        // Verifica se já existe este endereço para o cliente
        $stmtEndCheck = $pdo->prepare("
            SELECT id_entrega 
            FROM cliente_entrega 
            WHERE fk_Cliente_id_cliente = ? 
            AND nome_entrega = ? 
            AND numero_entrega = ?
        ");
        $stmtEndCheck->execute([
            $clienteId, 
            $endereco['rua'], 
            $endereco['numero']
        ]);
        
        if (!$stmtEndCheck->fetch()) {
            // Insere novo endereço
            $stmt = $pdo->prepare("
                INSERT INTO cliente_entrega 
                (nome_entrega, numero_entrega, fk_Cliente_id_cliente, fk_Bairro_id_bairro) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $endereco['rua'],
                $endereco['numero'],
                $clienteId,
                $bairroId
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Erro no cadastro: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar: ' . $e->getMessage()]);
}
?> 