<?php
session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

try {
    // Verificar se foi solicitada uma configuração específica
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'geral';
    $chave = isset($_GET['chave']) ? $_GET['chave'] : null;
    
    // Construir a consulta base
    $sql = "SELECT tipo, chave, valor FROM configuracoes WHERE tipo = :tipo";
    $params = [':tipo' => $tipo];
    
    // Se uma chave específica foi solicitada, adicionar à consulta
    if ($chave !== null) {
        $sql .= " AND chave = :chave";
        $params[':chave'] = $chave;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se foi solicitada uma chave específica, retornar apenas o valor
    if ($chave !== null && count($configs) === 1) {
        echo json_encode([
            'success' => true,
            'valor' => $configs[0]['valor']
        ]);
        exit;
    }
    
    // Organizar as configurações em um formato mais amigável
    $resultado = [];
    
    if ($tipo === 'tempo_estimado') {
        // Organizar tempos estimados por tipo (balcão/delivery)
        $tempos = [
            'balcao' => ['min' => 0, 'max' => 0],
            'delivery' => ['min' => 0, 'max' => 0]
        ];
        
        foreach ($configs as $config) {
            $partes = explode('_', $config['chave']);
            if (count($partes) === 2 && isset($tempos[$partes[0]])) {
                $tempos[$partes[0]][$partes[1]] = intval($config['valor']);
            }
        }
        
        $resultado = $tempos;
    } else {
        // Para outras configurações, usar formato chave-valor
        foreach ($configs as $config) {
            $resultado[$config['chave']] = $config['valor'];
        }
        
        // Garantir que aceitar_automatico sempre exista
        if ($tipo === 'geral' && !isset($resultado['aceitar_automatico'])) {
            $resultado['aceitar_automatico'] = '0';
        }
    }
    
    echo json_encode([
        'success' => true,
        'configuracoes' => $resultado
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar configurações: ' . $e->getMessage()
    ]);
}
?> 