<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'];

    $stmt = $pdo->prepare("
        INSERT INTO device_tokens (token) 
        VALUES (?) 
        ON DUPLICATE KEY UPDATE token = VALUES(token)
    ");
    
    $stmt->execute([$token]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 