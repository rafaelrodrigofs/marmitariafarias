<?php
// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');
// Inclui o arquivo de configuração do banco de dados
require_once '../config/database.php';

/**
 * Função para enviar notificações push via Firebase Cloud Messaging (FCM)
 * @param array $tokens Array com os tokens dos dispositivos que receberão a notificação
 * @param string $mensagem Mensagem que será exibida na notificação
 * @return string Resposta do servidor FCM
 */
function enviarNotificacaoFCM($tokens, $mensagem) {
    // Chave do servidor FCM para autenticação
    $serverKey = 'AAAAqPvPBXE:APA91bGxZhB_kpRwTn8KPKXPnRhI_kQUvD0nGRUK_-YQkZEXJPcYQneLRLVRzTJc_Wd_YPJQXQDhEoVfBqiQPvXOYkfZPJZhBNwqZMRNRvhPWNtEbXR5uLGwkF_LBRfsYHXdKbxhPxXB';
    
    // Monta o payload da notificação
    $data = [
        'registration_ids' => $tokens, // Aqui é onde seu token é usado
        'notification' => [
            'title' => 'Lunch&Fit', // Título da notificação
            'body' => $mensagem, // Corpo da mensagem
            'icon' => '/img/android-chrome-192x192.png', // Ícone da notificação
            'click_action' => 'https://lunchefit.com.br/dashboard.php', // URL ao clicar
            'vibrate' => [200, 100, 200] // Padrão de vibração
        ],
        'priority' => 'high', // Prioridade alta para entrega imediata
        'data' => [
            'click_action' => 'https://lunchefit.com.br/dashboard.php' // URL para dados adicionais
        ]
    ];

    // Configura e executa a requisição cURL para o FCM
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    // Executa a requisição e verifica erros
    $result = curl_exec($ch);
    
    if ($result === false) {
        error_log('Erro cURL: ' . curl_error($ch));
    } else {
        error_log('Resposta FCM: ' . $result);
    }
    
    curl_close($ch);
    return $result;
}

try {
    // Busca todos os tokens de dispositivo no banco de dados
    $stmt = $pdo->query("SELECT token FROM device_tokens");
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($tokens)) {
        // Se existem tokens, envia a notificação
        $mensagem = 'Novo pedido recebido!';
        $resultado = enviarNotificacaoFCM($tokens, $mensagem);
        
        // Registra logs do resultado
        $resposta = json_decode($resultado, true);
        error_log('Tokens processados: ' . count($tokens));
        error_log('Sucesso: ' . ($resposta['success'] ?? 0));
        
        // Retorna sucesso e o resultado
        echo json_encode(['success' => true, 'result' => $resultado]);
    } else {
        // Se não há tokens, retorna erro
        echo json_encode(['success' => false, 'error' => 'Nenhum dispositivo registrado']);
    }
} catch (Exception $e) {
    // Em caso de erro, registra no log e retorna mensagem de erro
    error_log('Erro ao enviar notificação: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 