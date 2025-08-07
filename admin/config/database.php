<?php
// Define o fuso horário para todas as operações com data
date_default_timezone_set('America/Sao_Paulo');

// Configuração global de logs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar log de erros
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// Detecta o ambiente atual
$is_localhost = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '147.79.84.193');

if ($is_localhost) {
    // Configurações do servidor local
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db = 'u195662740_farias_db';
} else {
    // Configurações do servidor de produção
    $host = 'srv1661.hstgr.io';
    $user = 'u195662740_farias';
    $pass = '#Rodrigo0196';
    $db = 'u195662740_farias_db';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => true
    ]);
} catch (PDOException $e) {
    error_log("Erro de conexão com banco de dados: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro de conexão com o banco de dados'
    ]);
    exit;
}
?>
