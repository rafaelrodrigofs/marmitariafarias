<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit;
}

require_once '../../config/database.php';

try {
    // Recebe os dados do formulário via POST
    $dados = json_decode(file_get_contents('php://input'), true);

    if (!$dados) {
        throw new Exception('Dados não recebidos corretamente');
    }

    // Validação dos campos obrigatórios
    if (empty($dados['valor']) || empty($dados['data']) || empty($dados['descricao'])) {
        throw new Exception('Campos obrigatórios não preenchidos');
    }

    // Trata o valor recebido (converte de "1.000,00" para "1000.00")
    $valor = str_replace(['.', ','], ['', '.'], $dados['valor']);

    // Prepara os dados para inserção
    $status_pagamento = isset($dados['pago']) && $dados['pago'] ? 1 : 0;
    $categoria_id = isset($dados['categoria']) ? $dados['categoria'] : null;
    $forma_pagamento = isset($dados['forma_pagamento']) ? $dados['forma_pagamento'] : 1; // Default: 1

    // Prepara a query de inserção
    $sql = "INSERT INTO despesas (
        fk_id_categoria,
        valor,
        status_pagamento,
        data_despesa,
        fk_id_forma_pagamento,
        descricao
    ) VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $categoria_id,
        $valor,
        $status_pagamento,
        $dados['data'],
        $forma_pagamento,
        $dados['descricao']
    ]);

    $id_despesa = $pdo->lastInsertId();

    // Se a despesa for recorrente, criar as repetições
    if (isset($dados['repetir']) && $dados['repetir'] && isset($dados['vezes']) && isset($dados['periodo'])) {
        $data_base = new DateTime($dados['data']);
        $intervalo = '';

        switch ($dados['periodo']) {
            case 'dias':
                $intervalo = 'P1D';
                break;
            case 'semanas':
                $intervalo = 'P1W';
                break;
            case 'meses':
                $intervalo = 'P1M';
                break;
            case 'anos':
                $intervalo = 'P1Y';
                break;
        }

        if ($intervalo) {
            $interval = new DateInterval($intervalo);
            
            for ($i = 1; $i < $dados['vezes']; $i++) {
                $data_base->add($interval);
                
                $stmt->execute([
                    $categoria_id,
                    $valor,
                    $status_pagamento,
                    $data_base->format('Y-m-d'),
                    $forma_pagamento,
                    $dados['descricao']
                ]);
            }
        }
    }

    // Retorna sucesso
    echo json_encode([
        'status' => 'success',
        'message' => 'Despesa registrada com sucesso',
        'id_despesa' => $id_despesa
    ]);

} catch (Exception $e) {
    error_log('Erro ao inserir despesa: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao registrar despesa: ' . $e->getMessage()
    ]);
}
