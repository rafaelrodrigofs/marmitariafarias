<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$log_file = __DIR__ . '/webhook_log.txt';

try {
    if (!file_exists($log_file)) {
        throw new Exception('Arquivo de log não encontrado');
    }

    // Lê as últimas 1000 linhas do arquivo de log
    $lines = [];
    $fp = fopen($log_file, 'r');
    fseek($fp, -1, SEEK_END);
    $pos = ftell($fp);
    $count = 0;
    $entry = '';
    
    // Lê o arquivo de trás para frente
    while ($pos > 0 && count($lines) < 1000) {
        $char = fgetc($fp);
        if ($char === "\n") {
            // Se encontramos uma linha em branco após uma entrada de log
            if (trim($entry) === str_repeat('-', 80)) {
                if ($count > 0) {
                    $lines[] = $count;
                }
                $count = 0;
            }
            $count++;
        }
        $entry = $char . $entry;
        fseek($fp, $pos--, SEEK_SET);
    }
    fclose($fp);

    // Lê as entradas de log completas
    $logs = [];
    $current_entry = '';
    $fp = fopen($log_file, 'r');
    while (($line = fgets($fp)) !== false) {
        $current_entry .= $line;
        if (trim($line) === str_repeat('-', 80)) {
            if (trim($current_entry) !== str_repeat('-', 80)) {
                $logs[] = trim($current_entry);
            }
            $current_entry = '';
        }
    }
    fclose($fp);

    // Pega apenas as últimas entradas
    $logs = array_slice(array_reverse($logs), 0, 1000);

    echo json_encode([
        'status' => 'success',
        'logs' => $logs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 