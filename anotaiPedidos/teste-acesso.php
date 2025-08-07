<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Servidor acessível',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'],
    'ip' => $_SERVER['SERVER_ADDR']
]); 