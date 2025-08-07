<?php
// Desativar buffer de saída
ob_end_clean();

// Configurar cabeçalhos para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Função para enviar evento
function enviarEvento($data) {
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Enviar um evento inicial
enviarEvento(['tipo' => 'conexao', 'mensagem' => 'Conexão SSE estabelecida']);

// Manter a conexão aberta por um tempo
sleep(2);

// Enviar outro evento
enviarEvento(['tipo' => 'teste', 'mensagem' => 'Evento de teste']);

// Fechar a conexão
exit();
?> 