<?php
// Configurações para hospedagem compartilhada
ini_set('display_errors', 0);
ignore_user_abort(false); // Permitir que o script termine quando o cliente desconectar
set_time_limit(60); // Limitar o tempo de execução a 60 segundos

// Iniciar sessão e depois fechá-la para não bloquear outras requisições
session_start();
$user_id = $_SESSION['user_id'] ?? null;
session_write_close();

// Verificar autenticação
if (!$user_id) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Não autorizado');
}

// Desativar buffer de saída
@ob_end_clean();
@ob_end_flush();

// Configurar cabeçalhos para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Para Nginx

// Função para enviar evento
function enviarEvento($data) {
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Função para registrar logs
function registrarLog($mensagem) {
    $arquivo = '../../logs/sse_pedidos.log';
    $data = date('Y-m-d H:i:s');
    @file_put_contents($arquivo, "[$data] $mensagem\n", FILE_APPEND);
}

// Enviar um evento inicial
enviarEvento(['tipo' => 'conexao', 'mensagem' => 'Conexão SSE estabelecida']);

// Obter o último ID de pedido conhecido
$ultimo_id = isset($_GET['ultimo_id']) ? intval($_GET['ultimo_id']) : 0;

try {
    // Incluir conexão com o banco de dados
    include_once '../../config/database.php';
    
    // Definir timeout e intervalo - valores menores para hospedagem
    $timeout = 20; // 20 segundos de timeout (reduzido de 30)
    $intervalo_verificacao = 2; // Verificar a cada 2 segundos (aumentado de 1)
    $intervalo_heartbeat = 5; // Heartbeat a cada 5 segundos (reduzido de 10)
    
    $inicio = time();
    $ultimo_heartbeat = time();
    
    // Registrar início do monitoramento
    registrarLog("Iniciando monitoramento SSE com último ID: $ultimo_id");
    
    // Loop até o timeout
    while (time() - $inicio < $timeout) {
        // Verificar se há novos pedidos - consulta otimizada
        $sql = "SELECT 
                MAX(id_pedido) as max_id, 
                COUNT(*) as quantidade
                FROM pedidos 
                WHERE id_pedido > :ultimo_id 
                AND status_pedido = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ultimo_id', $ultimo_id, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se houver novos pedidos
        if ($resultado['quantidade'] > 0 && $resultado['max_id'] > $ultimo_id) {
            registrarLog("Detectados {$resultado['quantidade']} novos pedidos. ID máximo: {$resultado['max_id']}");
            
            // Buscar informações básicas dos novos pedidos
            $sql_detalhes = "SELECT 
                            p.id_pedido, 
                            p.num_pedido, 
                            p.status_pedido,
                            c.nome_cliente,
                            CONCAT(e.nome_entrega, ', ', e.numero_entrega, ' - ', b.nome_bairro) as endereco_completo,
                            b.valor_taxa,
                            p.sub_total,
                            (p.sub_total + IFNULL(b.valor_taxa, 0)) as total
                        FROM 
                            pedidos p
                        LEFT JOIN 
                            clientes c ON p.fk_cliente_id = c.id_cliente
                        LEFT JOIN
                            cliente_entrega e ON p.fk_entrega_id = e.id_entrega
                        LEFT JOIN
                            cliente_bairro b ON e.fk_Bairro_id_bairro = b.id_bairro
                        WHERE 
                            p.id_pedido > :ultimo_id
                            AND p.id_pedido <= :max_id
                            AND p.status_pedido = 0
                        ORDER BY 
                            p.id_pedido DESC
                        LIMIT 20";
            
            $stmt_detalhes = $pdo->prepare($sql_detalhes);
            $stmt_detalhes->bindParam(':ultimo_id', $ultimo_id, PDO::PARAM_INT);
            $stmt_detalhes->bindParam(':max_id', $resultado['max_id'], PDO::PARAM_INT);
            $stmt_detalhes->execute();
            $pedidos_detalhes = $stmt_detalhes->fetchAll(PDO::FETCH_ASSOC);
            
            // Enviar evento com os novos pedidos e seus detalhes básicos
            enviarEvento([
                'tipo' => 'novos_pedidos',
                'quantidade' => count($pedidos_detalhes),
                'ultimo_id' => (int)$resultado['max_id'],
                'pedidos' => $pedidos_detalhes,
                'timestamp' => time()
            ]);
            
            // Atualizar o último ID
            $ultimo_id = $resultado['max_id'];
            
            // Enviar imediatamente e encerrar a conexão para garantir que o cliente receba
            // Isso é importante em hospedagens que podem limitar conexões persistentes
            registrarLog("Enviando novos pedidos e encerrando conexão");
            exit();
        }
        
        // Enviar heartbeat periodicamente
        if (time() - $ultimo_heartbeat >= $intervalo_heartbeat) {
            enviarEvento(['tipo' => 'heartbeat', 'timestamp' => time()]);
            $ultimo_heartbeat = time();
        }
        
        // Verificar se a conexão foi fechada pelo cliente
        if (connection_status() != CONNECTION_NORMAL) {
            registrarLog("Conexão fechada pelo cliente");
            break;
        }
        
        // Dormir por um curto período para reduzir uso de CPU
        // Usar sleep em vez de usleep para maior compatibilidade
        sleep($intervalo_verificacao);
    }
    
    // Enviar evento de timeout
    registrarLog("Timeout da conexão após $timeout segundos");
    enviarEvento(['tipo' => 'timeout', 'mensagem' => 'Timeout da conexão, reconectando...', 'timestamp' => time()]);
    
} catch (Exception $e) {
    // Registrar erro
    registrarLog("ERRO: " . $e->getMessage());
    
    // Enviar erro como evento
    enviarEvento(['tipo' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage(), 'timestamp' => time()]);
}

// Fechar a conexão
exit();
?> 