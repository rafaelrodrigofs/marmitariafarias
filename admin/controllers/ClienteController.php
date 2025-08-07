<?php
// Habilitar temporariamente para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

class ClienteController {
    private $pdo;

    public function __construct() {
        try {
            global $pdo;
            if (!$pdo) {
                throw new Exception('Conexão com banco de dados não estabelecida');
            }
            $this->pdo = $pdo;
        } catch (Exception $e) {
            $this->returnError('Erro de conexão: ' . $e->getMessage());
        }
    }

    private function returnError($message) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => true,
            'message' => $message,
            'itens_populares' => []
        ]);
        exit;
    }

    public function detalhes() {
        try {
            header('Content-Type: application/json');

            if (!isset($_GET['id'])) {
                throw new Exception('ID do cliente não informado');
            }

            $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
            
            // Busca dados básicos do cliente e pedidos
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.*,
                    COUNT(DISTINCT p.id_pedido) as total_pedidos,
                    MIN(p.data_pedido) as primeiro_pedido,
                    MAX(p.data_pedido) as ultimo_pedido,
                    COALESCE(SUM(p.sub_total), 0) as valor_total
                FROM clientes c
                LEFT JOIN pedidos p ON c.id_cliente = p.fk_cliente_id
                WHERE c.id_cliente = ?
                GROUP BY c.id_cliente
            ");
            
            if (!$stmt->execute([$id])) {
                throw new Exception('Erro ao buscar dados do cliente');
            }
            
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Busca endereço principal
            $stmt = $this->pdo->prepare("
                SELECT 
                    ce.nome_entrega,
                    ce.numero_entrega,
                    cb.nome_bairro
                FROM cliente_entrega ce
                JOIN cliente_bairro cb ON ce.fk_Bairro_id_bairro = cb.id_bairro
                WHERE ce.fk_Cliente_id_cliente = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $endereco = $stmt->fetch(PDO::FETCH_ASSOC);

            // Busca produtos mais pedidos
            $stmt = $this->pdo->prepare("
                SELECT 
                    pr.nome_produto as nome,
                    COUNT(*) as quantidade
                FROM pedido_itens pi
                JOIN produto pr ON pi.fk_produto_id = pr.id_produto
                JOIN pedidos pe ON pi.fk_pedido_id = pe.id_pedido
                WHERE pe.fk_cliente_id = ?
                GROUP BY pr.id_produto, pr.nome_produto
                ORDER BY quantidade DESC
                LIMIT 3
            ");
            $stmt->execute([$id]);
            $itens_populares = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $resposta = [
                'success' => true,
                'nome_cliente' => $cliente['nome_cliente'] ?? '',
                'telefone_cliente' => $cliente['telefone_cliente'] ?? '',
                'endereco_principal' => $endereco ? 
                    $endereco['nome_entrega'] . ', ' . $endereco['numero_entrega'] . ' - ' . $endereco['nome_bairro'] : 
                    'Não cadastrado',
                'total_pedidos' => (int)($cliente['total_pedidos'] ?? 0),
                'valor_total' => (float)($cliente['valor_total'] ?? 0),
                'primeiro_pedido' => $cliente['primeiro_pedido'] ?? null,
                'ultimo_pedido' => $cliente['ultimo_pedido'] ?? null,
                'itens_populares' => $itens_populares,
                'aniversario' => null, // Removido pois não existe na tabela
                'cupons_resgatados' => 0,
                'cashback_resgatado' => 0,
                'total_avaliacoes' => 0,
                'ultimo_feedback' => null
            ];

            echo json_encode($resposta);
            exit;

        } catch (Exception $e) {
            $this->returnError($e->getMessage());
        }
    }

    public function processRequest() {
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'detalhes':
                $this->detalhes();
                break;
            case 'historicoPedidos':
                $this->historicoPedidos();
                break;
            case 'verificarPedidos':
                try {
                    error_log("Verificando pedidos para o cliente ID: " . $_GET['id']);
                    
                    $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
                    $query = "SELECT COUNT(*) as total FROM pedidos WHERE fk_cliente_id = ?";
                    
                    error_log("Executando query: " . $query);
                    error_log("Parâmetro ID: " . $id);
                    
                    $stmt = $this->pdo->prepare($query);
                    $stmt->execute([$id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    error_log("Resultado da contagem: " . print_r($result, true));
                    
                    echo json_encode([
                        'success' => true,
                        'temPedidos' => $result['total'] > 0
                    ]);
                } catch (Exception $e) {
                    error_log("Erro ao verificar pedidos: " . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                break;
            case 'delete':
                $this->delete();
                break;
            case 'get':
                try {
                    if (!isset($_GET['id'])) {
                        throw new Exception('ID do cliente não informado');
                    }

                    $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

                    // Busca dados do cliente
                    $stmt = $this->pdo->prepare("
                        SELECT c.*
                        FROM clientes c
                        WHERE c.id_cliente = ?
                    ");
                    $stmt->execute([$id]);
                    $dadosCliente = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$dadosCliente) {
                        throw new Exception('Cliente não encontrado');
                    }

                    // Busca os endereços do cliente
                    $stmt = $this->pdo->prepare("
                        SELECT 
                            ce.id_entrega,
                            ce.nome_entrega,
                            ce.numero_entrega,
                            ce.fk_Bairro_id_bairro,
                            cb.nome_bairro,
                            cb.valor_taxa
                        FROM cliente_entrega ce
                        LEFT JOIN cliente_bairro cb ON ce.fk_Bairro_id_bairro = cb.id_bairro
                        WHERE ce.fk_Cliente_id_cliente = ?
                    ");
                    $stmt->execute([$id]);
                    $dadosCliente['enderecos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Debug
                    error_log('Dados do cliente: ' . print_r($dadosCliente, true));

                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'id_cliente' => $dadosCliente['id_cliente'],
                        'nome_cliente' => $dadosCliente['nome_cliente'],
                        'telefone_cliente' => $dadosCliente['telefone_cliente'],
                        'enderecos' => array_map(function($endereco) {
                            return [
                                'id_entrega' => $endereco['id_entrega'],
                                'nome_entrega' => $endereco['nome_entrega'],
                                'numero_entrega' => $endereco['numero_entrega'],
                                'fk_Bairro_id_bairro' => $endereco['fk_Bairro_id_bairro'],
                                'nome_bairro' => $endereco['nome_bairro'],
                                'valor_taxa' => $endereco['valor_taxa']
                            ];
                        }, $dadosCliente['enderecos'])
                    ]);
                    exit;

                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                    exit;
                }
                break;
            default:
                echo json_encode(['error' => 'Ação não encontrada']);
                break;
        }
    }

    public function historicoPedidos() {
        try {
            header('Content-Type: application/json');
            
            if (!isset($_GET['id'])) {
                throw new Exception('ID do cliente não informado');
            }

            $id_cliente = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
            
            $sql = "SELECT 
                    p.id_pedido,
                    DATE_FORMAT(p.data_pedido, '%d/%m/%Y') as data_pedido,
                    p.hora_pedido,
                    p.sub_total,
                    p.taxa_entrega,
                    p.status,
                    pg.metodo_pagamento,
                    GROUP_CONCAT(
                        CONCAT(
                            pi.quantidade, 
                            'x ', 
                            pr.nome_produto
                        ) SEPARATOR ', '
                    ) as itens
                FROM pedidos p
                INNER JOIN pedido_itens pi ON p.id_pedido = pi.fk_pedido_id
                INNER JOIN produto pr ON pi.fk_produto_id = pr.id_produto
                LEFT JOIN pagamento pg ON p.fk_pagamento_id = pg.id_pagamento
                WHERE p.fk_cliente_id = ?
                GROUP BY p.id_pedido
                ORDER BY p.data_pedido DESC, p.hora_pedido DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id_cliente]);
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Debug
            error_log("SQL Query: " . $sql);
            error_log("Cliente ID: " . $id_cliente);
            error_log("Resultados: " . print_r($pedidos, true));

            if (empty($pedidos)) {
                echo json_encode([
                    'success' => true,
                    'pedidos' => []
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'pedidos' => array_map(function($pedido) {
                    return [
                        'data_pedido' => $pedido['data_pedido'],
                        'hora_pedido' => $pedido['hora_pedido'],
                        'id_pedido' => $pedido['id_pedido'],
                        'itens' => $pedido['itens'],
                        'taxa_entrega' => number_format($pedido['taxa_entrega'], 2, ',', '.'),
                        'total' => number_format($pedido['sub_total'], 2, ',', '.'),
                        'metodo_pagamento' => $pedido['metodo_pagamento'] ?? 'Não informado',
                        'status' => $pedido['status'] ?? 'Pendente'
                    ];
                }, $pedidos)
            ]);
            
        } catch (Exception $e) {
            error_log("Erro no histórico de pedidos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao buscar histórico de pedidos: ' . $e->getMessage(),
                'pedidos' => []
            ]);
        }
    }

    public function verificarPedidos() {
        try {
            if (!isset($_GET['id'])) {
                throw new Exception('ID do cliente não informado');
            }

            $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
            
            // Verifica se existem pedidos para este cliente
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM pedidos 
                WHERE fk_cliente_id = ?
            ");
            
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'temPedidos' => $result['total'] > 0
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao verificar pedidos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function delete() {
        ob_start(); // Captura qualquer saída não intencional
        
        try {
            // Limpa qualquer saída anterior
            ob_clean();
            
            // Força o tipo de conteúdo para JSON
            header('Content-Type: application/json; charset=utf-8');

            // Verifica se tem ID
            if (!isset($_POST['id'])) {
                throw new Exception('ID do cliente não informado');
            }

            $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

            // Verifica se o cliente existe
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM clientes WHERE id_cliente = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('Cliente não encontrado');
            }

            // Verifica pedidos
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE fk_cliente_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cliente possui pedidos e não pode ser excluído');
            }

            $this->pdo->beginTransaction();

            // Exclui endereços
            $stmt = $this->pdo->prepare("DELETE FROM cliente_entrega WHERE fk_Cliente_id_cliente = ?");
            $success1 = $stmt->execute([$id]);

            // Exclui cliente
            $stmt = $this->pdo->prepare("DELETE FROM clientes WHERE id_cliente = ?");
            $success2 = $stmt->execute([$id]);

            if ($success1 && $success2) {
                $this->pdo->commit();
                $response = ['success' => true, 'message' => 'Cliente excluído com sucesso'];
            } else {
                $this->pdo->rollBack();
                throw new Exception('Falha ao excluir registros');
            }

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $response = ['success' => false, 'message' => $e->getMessage()];
        }

        // Limpa qualquer saída anterior
        ob_clean();
        
        // Garante que a resposta seja JSON
        echo json_encode($response);
        
        // Envia a saída e encerra o buffer
        ob_end_flush();
        exit;
    }
}

// Instancia e executa
$controller = new ClienteController();
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Verifica se o método existe diretamente na classe
    if (method_exists($controller, $action)) {
        $controller->$action();
    } 
    // Se não existir como método, tenta processar via processRequest
    else {
        $controller->processRequest();
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Nenhuma ação especificada',
        'itens_populares' => []
    ]);
} 