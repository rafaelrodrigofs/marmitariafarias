<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit();
}

// Incluir conexão com o banco de dados
try {
    include_once '../../config/database.php';
} catch (Exception $e) {
    error_log('Erro ao incluir arquivo database.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro de conexão com o banco de dados']);
    exit();
}

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    exit();
}

// Validar campos obrigatórios
$required_fields = ['data', 'dinheiro', 'pix_normal', 'online_pix', 'debito', 'credito', 'vouchers', 'ifood'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Campo '$field' é obrigatório"]);
        exit();
    }
}

// Função para converter valor monetário do formato brasileiro para decimal
function parseMoneyValue($value) {
    // Remove todos os caracteres não numéricos exceto vírgula e ponto
    $value = preg_replace('/[^\d,.-]/', '', $value);
    
    // Se tem vírgula, é formato brasileiro (vírgula = decimal, ponto = milhares)
    if (strpos($value, ',') !== false) {
        // Remove pontos (separadores de milhares)
        $value = str_replace('.', '', $value);
        // Converte vírgula para ponto (decimal)
        $value = str_replace(',', '.', $value);
    }
    // Se não tem vírgula mas tem ponto, verifica se é decimal ou milhares
    else if (strpos($value, '.') !== false) {
        $parts = explode('.', $value);
        // Se tem mais de 2 partes ou a segunda parte tem mais de 2 dígitos, é separador de milhares
        if (count($parts) > 2 || (count($parts) == 2 && strlen($parts[1]) > 2)) {
            // É separador de milhares (ex: 1.500 ou 1.500.000)
            $value = str_replace('.', '', $value);
        }
        // Se tem exatamente 2 partes e a segunda tem 1 ou 2 dígitos, é decimal
        else if (count($parts) == 2 && strlen($parts[1]) <= 2) {
            // É decimal (ex: 1500.50 ou 1.50)
            // Não faz nada, mantém como está
        }
    }
    
    // Converte para float
    $floatValue = floatval($value);
    
    return $floatValue;
}

try {
    // Preparar dados para inserção
    $data = $input['data'];
    $dinheiro = parseMoneyValue($input['dinheiro']);
    $pix_normal = parseMoneyValue($input['pix_normal']);
    $online_pix = parseMoneyValue($input['online_pix']);
    $debito = parseMoneyValue($input['debito']);
    $credito = parseMoneyValue($input['credito']);
    $vouchers = parseMoneyValue($input['vouchers']);
    $ifood = parseMoneyValue($input['ifood']);

    // Verificar se já existe um fechamento para esta data
    $sql_check = "SELECT Id FROM fechamento WHERE data = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$data]);
    
    if ($stmt_check->fetch()) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Já existe um fechamento registrado para esta data']);
        exit();
    }

    // Inserir fechamento
    $sql = "INSERT INTO fechamento (data, dinheiro, pix_normal, online_pix, debito, credito, vouchers, ifood) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $data,
        $dinheiro,
        $pix_normal,
        $online_pix,
        $debito,
        $credito,
        $vouchers,
        $ifood
    ]);

    if ($result) {
        // Calcular total do fechamento
        $total = $dinheiro + $pix_normal + $online_pix + $debito + $credito + $vouchers + $ifood;
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Fechamento registrado com sucesso',
            'total' => number_format($total, 2, ',', '.')
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Erro ao inserir fechamento']);
    }

} catch (PDOException $e) {
    error_log('Erro ao inserir fechamento: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno do servidor']);
} catch (Exception $e) {
    error_log('Erro inesperado: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro inesperado']);
}
?> 