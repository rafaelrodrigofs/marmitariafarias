<?php
// IMPORTANTE: Não deve haver nenhum espaço ou linha antes de <?php

// Iniciar sessão e depois fechá-la para não bloquear outras requisições
session_start();
$user_id = $_SESSION['user_id'] ?? null;
session_write_close();

// Verificar autenticação
if (!$user_id) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Não autorizado');
}

// Desativar qualquer saída anterior
@ob_clean();

// Configurar cabeçalhos para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Desativar buffer de saída
@ob_end_flush();
flush();

// Função para enviar evento
function enviarEvento($data) {
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Enviar um evento inicial
enviarEvento(['tipo' => 'conexao', 'mensagem' => 'Conexão estabelecida']);

try {
    // Incluir conexão com o banco de dados
    include_once '../../config/database.php';
    
    // Enviar evento após conexão com o banco
    enviarEvento(['tipo' => 'db', 'mensagem' => 'Conexão com o banco estabelecida']);
    
} catch (Exception $e) {
    // Enviar erro como evento
    enviarEvento(['tipo' => 'erro', 'mensagem' => 'Erro na conexão: ' . $e->getMessage()]);
    exit();
}

// Manter a conexão aberta por um tempo
sleep(5);

// Enviar outro evento
enviarEvento(['tipo' => 'teste', 'mensagem' => 'Evento final']);

// Fechar a conexão
exit();
?>
