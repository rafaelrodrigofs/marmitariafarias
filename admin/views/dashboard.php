<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    error_log('Tentativa de acesso não autorizado ao dashboard');
    header('Location: ../views/login.php');
    exit();
}

try {
    include_once '../config/database.php';
} catch (Exception $e) {
    error_log('Erro ao incluir arquivo database.php: ' . $e->getMessage());
    die('Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.');
}

// Função helper para formatação de números
function formatNumber($number, $decimals = 2)
{
    return number_format($number ?? 0, $decimals, ',', '.');
}

// Função helper para logging
function logWithLine($message, $line)
{
    error_log("[Linha {$line}] {$message}");
}

// Período selecionado
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'dia';
$data_especifica = isset($_GET['data']) ? $_GET['data'] : null;
$data_inicio_especifica = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
$data_fim_especifica = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
$data_inicio = null;
$data_fim = null;

// Cálculo das datas baseado no período
if ($data_especifica) {
    $data_inicio = $data_especifica;
    $data_fim = $data_especifica;
} elseif ($data_inicio_especifica && $data_fim_especifica) {
    $data_inicio = $data_inicio_especifica;
    $data_fim = $data_fim_especifica;
} else {
    switch ($periodo) {
        case 'ontem':
            $data_inicio = date('Y-m-d', strtotime('yesterday'));
            $data_fim = date('Y-m-d', strtotime('yesterday'));
            break;
        case 'dia':
            $data_inicio = date('Y-m-d');
            $data_fim = date('Y-m-d');
            break;
        case 'semana':
            $data_inicio = date('Y-m-d', strtotime('monday this week'));
            $data_fim = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'mes':
            $data_inicio = date('Y-m-d', strtotime('first day of this month'));
            $data_fim = date('Y-m-d', strtotime('last day of this month'));
            break;
        case 'ano':
            $data_inicio = '2025-01-01';
            $data_fim = '2025-12-31';
            break;
    }
}

// Métricas principais
try {
    // Query para o Pré-Fechamento com detalhes
    $sql_anotai = "SELECT 
        COUNT(*) as total_pedidos,
        COALESCE(SUM(total_order), 0) as total_valor,
        COALESCE(SUM(subtotal_order), 0) as subtotal,
        COALESCE(SUM(delivery_fee), 0) as total_taxas,
        COUNT(CASE WHEN delivery_fee > 0 THEN 1 END) as total_entregas,
        COUNT(CASE WHEN delivery_fee = 0 OR delivery_fee IS NULL THEN 1 END) as total_retiradas
        FROM o01_order 
        WHERE DATE(date_order) BETWEEN ? AND ?
        AND check_order != 4"; // Excluindo pedidos cancelados

    $stmt = $pdo->prepare($sql_anotai);
    $stmt->execute([$data_inicio, $data_fim]);
    $metricas_anotai = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query para produtos vendidos
    $sql_produtos = "SELECT 
        c.title_category as nome_categoria,
        c.order_category,
        p.name_product as nome_produto,
        SUM(op.quantity_product) as total_unidades,
        SUM(op.totalPrice_product) as valor_total
        FROM o02_order_products op
        JOIN o01_order o ON op.fk_id_order = o.id_order
        JOIN p02_products p ON op.fk_id_product = p.id_product
        LEFT JOIN p01_categories c ON p.fk_id_category = c.id_category
        WHERE DATE(o.date_order) BETWEEN ? AND ?
        AND o.check_order != 4
        GROUP BY c.title_category, c.order_category, p.name_product
        ORDER BY c.order_category ASC, c.title_category ASC, valor_total DESC";

    $stmt = $pdo->prepare($sql_produtos);
    $stmt->execute([$data_inicio, $data_fim]);
    $produtos_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizando produtos por categoria
    $produtos_por_categoria = [];
    foreach ($produtos_vendidos as $produto) {
        $categoria = $produto['nome_categoria'] ?? 'Sem Categoria';
        if (!isset($produtos_por_categoria[$categoria])) {
            $produtos_por_categoria[$categoria] = [];
        }
        $produtos_por_categoria[$categoria][] = $produto;
    }

    // Debug para verificar os dados
    error_log('Produtos por categoria: ' . print_r($produtos_por_categoria, true));

    // Substituindo a variável original
    $produtos_vendidos = $produtos_por_categoria;

    $sql_metricas = "SELECT 
        COUNT(*) as total_pedidos,
        COALESCE(SUM(CASE WHEN status_pagamento = 1 THEN sub_total ELSE 0 END) + 
                SUM(CASE WHEN status_pagamento = 1 THEN taxa_entrega ELSE 0 END), 0) as faturamento_total,
        COALESCE(AVG(CASE WHEN status_pagamento = 1 THEN (sub_total + taxa_entrega) ELSE NULL END), 0) as ticket_medio,
        COUNT(DISTINCT fk_cliente_id) as clientes_unicos,
        COALESCE(SUM(taxa_entrega), 0) as total_taxas,
        SUM(CASE WHEN status_pagamento = 0 THEN 1 ELSE 0 END) as pedidos_pendentes,
        COALESCE(SUM(CASE WHEN status_pagamento = 0 THEN (sub_total + taxa_entrega) ELSE 0 END), 0) as valor_pendente
        FROM pedidos 
        WHERE DATE(data_pedido) BETWEEN ? AND ?";

    $stmt = $pdo->prepare($sql_metricas);
    $stmt->execute([$data_inicio, $data_fim]);
    $metricas = $stmt->fetch(PDO::FETCH_ASSOC);


    // Definir período anterior baseado no filtro selecionado
    switch ($periodo) {
        case 'ontem':
            $data_inicio_anterior = date('Y-m-d', strtotime('-2 days'));
            $data_fim_anterior = date('Y-m-d', strtotime('-2 days'));
            $comparacao_texto = "vs anteontem";
            break;
        case 'dia':
            $data_inicio_anterior = date('Y-m-d', strtotime('yesterday'));
            $data_fim_anterior = date('Y-m-d', strtotime('yesterday'));
            $comparacao_texto = "vs ontem";
            break;
        case 'semana':
            $data_inicio_anterior = date('Y-m-d', strtotime('sunday last week -1 week'));
            $data_fim_anterior = date('Y-m-d', strtotime('saturday last week'));
            $comparacao_texto = "vs semana anterior";
            break;
        case 'mes':
            $data_inicio_anterior = date('Y-m-d', strtotime('first day of last month'));
            $data_fim_anterior = date('Y-m-d', strtotime('last day of last month'));
            $comparacao_texto = "vs mês anterior";
            break;
        case 'ano':
            $data_inicio_anterior = date('Y-m-d', strtotime('first day of january last year'));
            $data_fim_anterior = date('Y-m-d', strtotime('last day of december last year'));
            $comparacao_texto = "vs 2024";
            break;
    }

    // Query para período anterior
    $sql_anterior = "SELECT 
        COALESCE(SUM(sub_total), 0) as faturamento_anterior
        FROM pedidos p
        JOIN clientes c ON p.fk_cliente_id = c.id_cliente
        WHERE DATE(p.data_pedido) BETWEEN ? AND ?";

    $stmt = $pdo->prepare($sql_anterior);
    $stmt->execute([$data_inicio_anterior, $data_fim_anterior]);
    $faturamento_anterior = $stmt->fetch(PDO::FETCH_ASSOC)['faturamento_anterior'];

    // Calcula a variação percentual
    $variacao_percentual = 0;
    if ($faturamento_anterior > 0) {
        $variacao_percentual = (($metricas['faturamento_total'] - $faturamento_anterior) / $faturamento_anterior) * 100;
    }
} catch (PDOException $e) {
    // Inicialização em caso de erro (mantém como está)
    $metricas = [
        'total_pedidos' => 0,
        'faturamento_total' => 0,
        'ticket_medio' => 0,
        'clientes_unicos' => 0,
        'total_taxas' => 0,
        'pedidos_pendentes' => 0,
        'valor_pendente' => 0
    ];
    $variacao_percentual = 0;
    $faturamento_anterior = 0;
    $comparacao_texto = "";
    $produtos_vendidos = []; // Inicialização da variável em caso de erro
    logWithLine('Erro no dashboard: ' . $e->getMessage(), __LINE__);
}

// Produtos mais vendidos
$sql_produtos = "SELECT 
    p.nome_produto,
    COUNT(pi.fk_produto_id) as quantidade_vendida,
    SUM(pi.preco_unitario) as receita_total,
    c.nome_categoria
    FROM pedido_itens pi
    JOIN produto p ON pi.fk_produto_id = p.id_produto
    JOIN pedidos pd ON pi.fk_pedido_id = pd.id_pedido
    JOIN categoria c ON p.fk_categoria_id = c.id_categoria
    WHERE pd.data_pedido >= ? AND pd.data_pedido <= ?
    GROUP BY p.id_produto, c.nome_categoria
    ORDER BY c.ordem ASC, c.nome_categoria, quantidade_vendida DESC";

$stmt = $pdo->prepare($sql_produtos);
$stmt->execute([$data_inicio, $data_fim]);
$produtos_populares = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query de análise de bairros baseada nas tabelas novas
$sql_bairros = "SELECT 
    d.name_district AS nome_bairro,
    COUNT(o.id_order) AS total_pedidos,
    COALESCE(SUM(o.total_order), 0) AS faturamento
FROM o01_order o
JOIN client_address a ON o.fk_id_address = a.id_address
JOIN client_district d ON a.fk_id_district = d.id_district
WHERE DATE(o.date_order) BETWEEN ? AND ?
AND o.check_order != 4
GROUP BY d.id_district, d.name_district
HAVING COUNT(o.id_order) > 0
ORDER BY faturamento DESC
LIMIT 10";

try {
    $stmt = $pdo->prepare($sql_bairros);
    $stmt->execute([$data_inicio, $data_fim]);
    $analise_bairros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $analise_bairros = [];
    error_log('Erro na análise de bairros: ' . $e->getMessage());
}

// Verificação adicional antes de passar para o JavaScript
if (empty($analise_bairros)) {
    error_log('Nenhum dado de bairro encontrado para o período');
}

// Análise de acompanhamentos mais pedidos
$sql_acomp = "SELECT 
    sa.nome_subacomp,
    COUNT(*) as quantidade,
    a.nome_acomp as categoria_acomp
    FROM pedido_item_acomp pia
    JOIN sub_acomp sa ON pia.fk_subacomp_id = sa.id_subacomp
    JOIN acomp a ON pia.fk_acomp_id = a.id_acomp
    JOIN pedido_itens pi ON pia.fk_pedido_item_id = pi.id_pedido_item
    JOIN pedidos p ON pi.fk_pedido_id = p.id_pedido
    WHERE p.data_pedido >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
    GROUP BY sa.id_subacomp
    ORDER BY quantidade DESC
    LIMIT 5";

$stmt = $pdo->prepare($sql_acomp);
$stmt->execute([$periodo]);
$acomp_populares = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Análise de produtos por categoria (Todas as categorias)
$sql_produtos_categoria = "SELECT 
    c.nome_categoria,
    COUNT(pi.id_pedido_item) as total_vendido,
    SUM(pi.quantidade) as quantidade_total,
    SUM(pi.preco_unitario * pi.quantidade) as valor_total
    FROM pedido_itens pi
    JOIN produto p ON pi.fk_produto_id = p.id_produto
    JOIN categoria c ON p.fk_categoria_id = c.id_categoria
    JOIN pedidos pd ON pi.fk_pedido_id = pd.id_pedido
    WHERE pd.data_pedido >= ? AND pd.data_pedido <= ?
    GROUP BY c.id_categoria
    ORDER BY quantidade_total DESC";

$stmt = $pdo->prepare($sql_produtos_categoria);
$stmt->execute([$data_inicio, $data_fim]);
$produtos_categoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Primeiro, vamos verificar se existem pedidos no banco
$sql_check_pedidos = "SELECT 
    COUNT(*) as total_pedidos,
    MIN(data_pedido) as primeira_data,
    MAX(data_pedido) as ultima_data
FROM pedidos";

try {
    $stmt = $pdo->prepare($sql_check_pedidos);
    $stmt->execute();
    $check_pedidos = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar pedidos específicos do período
    $sql_check_periodo = "SELECT COUNT(*) as total 
        FROM pedidos 
        WHERE DATE(data_pedido) = ?";

    $stmt = $pdo->prepare($sql_check_periodo);
    $stmt->execute([$data_inicio]);
    $total_periodo = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#f1f1f1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="img/x-icon" href="../assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <title>Dashboard - Lunch&Fit</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Manifesto PWA -->
    <link rel="manifest" href="../manifest.json">

    <!-- Ícones para Android -->
    <link rel="icon" type="image/png" sizes="192x192" href="../img/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="../img/icon-512x512.png">

    <!-- Meta tags para PWA -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Lunch&Fit">
    <meta name="theme-color" content="#2ecc71">

    <!-- Meta tags para Splash Screen -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Lunch&Fit">

    <!-- Cores e estilos da Splash Screen -->
    <link rel="apple-touch-startup-image" href="../assets/img/splash-640x1136.png" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)">
    <link rel="apple-touch-startup-image" href="../assets/img/splash-750x1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)">
    <link rel="apple-touch-startup-image" href="../assets/img/splash-1242x2208.png" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3)">
    <link rel="apple-touch-startup-image" href="../assets/img/splash-1125x2436.png" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)">

    <!-- Firebase App (obrigatório) -->
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
    <!-- Firebase Messaging -->
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-messaging-compat.js"></script>

    <!-- Inicialização do Firebase -->
    <script>
        // Configuração do Firebase
        const firebaseConfig = {
            apiKey: "AIzaSyBY1OF3VBtyxHRkniQm2cSeLLs9uV3Izak",
            authDomain: "lunchefit-4c903.firebaseapp.com",
            projectId: "lunchefit-4c903",
            storageBucket: "lunchefit-4c903.firebasestorage.app",
            messagingSenderId: "646788288611",
            appId: "1:646788288611:web:2d6dcf0cb4ca49593ba856",
            vapidKey: "BG7dvi1j2eq_-b-7YWTOpNXFWwNTz2W2pidCRxQdDLsQgcVMLmqa9jI8UKRoqDHkaN_2rbuUUpMW7sHol_VwqS4"
        };

        // Inicializa o Firebase
        firebase.initializeApp(firebaseConfig);
        const messaging = firebase.messaging();

        // Função para registrar o dispositivo
        async function registrarDispositivo() {
            try {
                console.log('Iniciando registro do dispositivo...');

                // Verifica se é uma instalação PWA
                const isPWA = window.matchMedia('(display-mode: standalone)').matches;
                console.log('É PWA:', isPWA);

                const permission = await Notification.requestPermission();
                console.log('Permissão:', permission);

                if (permission === 'granted') {
                    console.log('Obtendo token...');
                    const token = await messaging.getToken();
                    console.log('Token obtido:', token);

                    // Salvar token no banco
                    const response = await fetch('../actions/save_token.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            token,
                            isPWA: isPWA
                        })
                    });
                    const data = await response.json();
                    console.log('Resposta do servidor:', data);
                }
            } catch (error) {
                console.error('Erro ao registrar dispositivo:', error);
            }
        }

        // Registrar quando a página carregar
        document.addEventListener('DOMContentLoaded', registrarDispositivo);

        // Registrar quando o app for instalado como PWA
        window.addEventListener('beforeinstallprompt', (e) => {
            e.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    registrarDispositivo();
                }
            });
        });

        // Registrar quando o app for aberto (PWA ou navegador)
        window.addEventListener('focus', registrarDispositivo);

        messaging.onMessage((payload) => {
            console.log('Mensagem recebida:', payload);
        });
    </script>

    <!-- Widget DatePicker CSS -->
    <link rel="stylesheet" href="../includes/widget/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/pt.js"></script>
</head>

<body class="dashboard-page">
    <?php include_once '../includes/menu.php'; ?>
    <div class="main-content">
        <main class="dashboard-container">
            <div class="dashboard-top">
                <div class="dashboard-header">
                    <div class="header-content">
                        <h1 class="header-title" id="testNotification" style="cursor: pointer;">Dashboard</h1>
                        <div class="header-subtitle">
                            <!-- Widget DatePicker -->
                            <div class="datepicker-container" style="max-width: 200px; margin: 0;">
                                <div class="input-wrapper">
                                    <input type="text" class="date-input" value="<?php
                                        if ($data_especifica) {
                                            echo date('d/m/Y', strtotime($data_especifica));
                                        } elseif ($data_inicio_especifica && $data_fim_especifica) {
                                            echo date('d/m/Y', strtotime($data_inicio_especifica)) . ' - ' . date('d/m/Y', strtotime($data_fim_especifica));
                                        } else {
                                switch ($periodo) {
                                    case 'ontem':
                                        echo date('d/m/Y', strtotime('yesterday'));
                                        break;
                                    case 'dia':
                                        echo date('d/m/Y');
                                        break;
                                    case 'semana':
                                                    echo date('d/m', strtotime('monday this week')) . ' - ' . date('d/m/Y', strtotime('sunday this week'));
                                        break;
                                    case 'mes':
                                        echo date('F/Y');
                                        break;
                                    case 'ano':
                                        echo '2025';
                                        break;
                                }
                                        }
                                    ?>" readonly>
                            <svg class="calendar-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        
                        <!-- Calendário -->
                        <div class="calendar-dropdown">
                            <!-- Cabeçalho do calendário -->
                            <div class="calendar-header">
                                <button class="month-year">
                                    <span>Mês 2025</span>
                                </button>
                                <div class="nav-buttons">
                                    <button class="nav-btn" title="Mês anterior">
                                        <span>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                <polyline points="15 18 9 12 15 6"></polyline>
                                            </svg>
                                        </span>
                                    </button>
                                    <button class="nav-btn" title="Próximo mês">
                                        <span>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                <polyline points="9 18 15 12 9 6"></polyline>
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Grid do calendário -->
                            <div class="calendar-grid">
                                <!-- Cabeçalhos dos dias da semana -->
                                <span class="day-header">Dom</span>
                                <span class="day-header">Seg</span>
                                <span class="day-header">Ter</span>
                                <span class="day-header">Qua</span>
                                <span class="day-header">Qui</span>
                                <span class="day-header">Sex</span>
                                <span class="day-header">Sab</span>
                                
                                <!-- Os dias serão gerados dinamicamente pelo JavaScript -->
                            </div>
                            
                            <!-- Botões de ação -->
                            <div class="action-buttons">
                                <button class="btn btn-clear">Limpar</button>
                                <button class="btn btn-apply">Aplicar</button>
                            </div>
                        </div>
                    </div>
                            <span style="color: rgba(255, 255, 255, 0.7);">
                        </div>
                    </div>
                </div>


            </div>
            <div style="padding:20px">
                <!-- Botões de período rápido (mantidos para compatibilidade) -->
                <div class="period-selector" id="periodSelector" style="display:none;">
                    <button class="period-btn <?php echo ($periodo == 'ontem' && !$data_especifica) ? 'active' : ''; ?>" data-period="ontem">Ontem</button>
                    <button class="period-btn <?php echo ($periodo == 'dia' && !$data_especifica) ? 'active' : ''; ?>" data-period="dia">Hoje</button>
                    <button class="period-btn <?php echo ($periodo == 'semana' && !$data_especifica) ? 'active' : ''; ?>" data-period="semana">Semana</button>
                    <button class="period-btn <?php echo ($periodo == 'mes' && !$data_especifica) ? 'active' : ''; ?>" data-period="mes">Mês</button>
                    <button class="period-btn <?php echo ($periodo == 'ano' && !$data_especifica) ? 'active' : ''; ?>" data-period="ano">2025</button>
                </div>
                <!-- Cards Principais -->
                <div class="metrics-wrapper">
                    <div class="metrics-grid">
                        <!-- Card de Pré-Fechamento -->
                        <div class="metric-card success" onclick="window.location.href='fechamento.php'" style="cursor: pointer;">
                            <div class="metric-info" style="width:100%">

                                <div class="metric-title">
                                    <div>
                                        <h3>Pré-Fechamento</h3>
                                        <p class="metric-value">R$ <?php echo formatNumber($metricas_anotai['total_valor']); ?></p>
                                    </div>
                                    <div>
                                        <span class="badge-pendente" style="background: #333;">
                                            <?php echo formatNumber($metricas_anotai['total_pedidos'], 0); ?> pedidos hoje
                                        </span>
                                    </div>
                                </div>
                                <div class="metric-details" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 1rem;">
                                    <!-- Card Subtotal -->
                                    <div class="metric-card info" style="padding: 1rem;">
                                        <div class="metric-icon" style="background: #2196f3;">
                                            <i class="fas fa-calculator"></i>
                                        </div>
                                        <div class="metric-info">
                                            <h4>Subtotal</h4>
                                            <p class="metric-value">R$ <?php echo formatNumber($metricas_anotai['subtotal']); ?></p>
                                        </div>
                                    </div>

                                    <!-- Card Taxas -->
                                    <div class="metric-card info" style="padding: 1rem;">
                                        <div class="metric-icon" style="background: #4caf50;">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <div class="metric-info">
                                            <h4>Taxas</h4>
                                            <p class="metric-value">R$ <?php echo formatNumber($metricas_anotai['total_taxas']); ?></p>
                                        </div>
                                    </div>

                                    <!-- Card Entregas -->
                                    <div class="metric-card info" style="padding: 1rem;">
                                        <div class="metric-icon" style="background: #ff9800;">
                                            <i class="fas fa-motorcycle"></i>
                                        </div>
                                        <div class="metric-info">
                                            <h4>Entregas</h4>
                                            <p class="metric-value"><?php echo formatNumber($metricas_anotai['total_entregas'], 0); ?></p>
                                        </div>
                                    </div>

                                    <!-- Card Retiradas -->
                                    <div class="metric-card info" style="padding: 1rem;">
                                        <div class="metric-icon" style="background: #9c27b0;">
                                            <i class="fas fa-store"></i>
                                        </div>
                                        <div class="metric-info">
                                            <h4>Retiradas</h4>
                                            <p class="metric-value"><?php echo formatNumber($metricas_anotai['total_retiradas'], 0); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Produtos Vendidos -->
                                <div class="produtos-vendidos">
                                    <?php foreach ($produtos_vendidos as $categoria => $produtos): ?>
                                        <div class="categoria-section">
                                            <h5><?php echo $categoria; ?></h5>
                                            <?php foreach ($produtos as $produto): ?>
                                                <div class="produto-item">
                                                    <div class="produto-info">
                                                        <span class="produto-nome"><?php echo $produto['nome_produto']; ?></span>
                                                        <div class="produto-detalhes">
                                                            <span class="quantidade"><?php echo formatNumber($produto['total_unidades'], 0); ?> un</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="metrics-cards">
                            <div class="analytics-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-map-marker-alt"></i> Performance por Bairro</h3>
                                </div>
                                <div class="card-body">
                                    <div class="neighborhood-chart">
                                        <canvas id="neighborhoodChart"></canvas>
                                    </div>
                                    <div class="neighborhood-list">
                                        <?php foreach ($analise_bairros as $bairro): ?>
                                            <div class="neighborhood-item">
                                                <div class="neighborhood-info">
                                                    <span class="neighborhood-name"><?php echo $bairro['nome_bairro']; ?></span>
                                                    <span class="neighborhood-orders"><?php echo $bairro['total_pedidos']; ?> pedidos</span>
                                                </div>
                                                <div class="neighborhood-stats">
                                                    <span class="neighborhood-revenue">R$ <?php echo formatNumber($bairro['faturamento']); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <!-- Card de Faturamento -->
                            <div class="metric-card info">
                                <div class="metric-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="metric-info">
                                    <h3>Faturamento Total</h3>
                                    <p class="metric-value">R$ <?php echo formatNumber($metricas['faturamento_total']); ?></p>
                                    <p class="metric-comparison">
                                        <?php if ($faturamento_anterior > 0): ?>
                                            <i class="fas fa-arrow-<?php echo $variacao_percentual > 0 ? 'up' : 'down'; ?>"></i>
                                            <span><?php echo abs(round($variacao_percentual, 1)); ?>% <?php echo $comparacao_texto; ?></span>
                                        <?php else: ?>
                                            <span>Primeiro período de análise</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Card de Despesas -->
                            <div class="metric-card danger" onclick="window.location.href='despesas.php'" style="cursor: pointer;">
                                <div class="metric-icon">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="metric-info">
                                    <h3>Despesas</h3>
                                    <?php
                                    // Buscar total de despesas do período
                                    $sql_despesas = "SELECT 
                            COALESCE(SUM(valor), 0) as total_despesas,
                            COALESCE(SUM(CASE WHEN status_pagamento = 0 THEN valor ELSE 0 END), 0) as total_pendente
                            FROM despesas 
                            WHERE DATE(data_despesa) BETWEEN ? AND ?";
                                    try {
                                        $stmt = $pdo->prepare($sql_despesas);
                                        $stmt->execute([$data_inicio, $data_fim]);
                                        $despesas = $stmt->fetch(PDO::FETCH_ASSOC);

                                        $total_despesas = $despesas['total_despesas'];
                                        $total_pendente = $despesas['total_pendente'];
                                    } catch (PDOException $e) {
                                        error_log('Erro ao buscar despesas: ' . $e->getMessage());
                                        $total_despesas = 0;
                                        $total_pendente = 0;
                                    }
                                    ?>
                                    <p class="metric-value">R$ <?php echo formatNumber($total_despesas); ?></p>
                                    <p class="metric-detail">
                                        <span class="badge-pendente" style="background: #ff9800;">R$ <?php echo formatNumber($total_pendente); ?></span>
                                        pendente
                                    </p>
                                </div>
                            </div>

                            <!-- Card de Pagamentos Pendentes -->
                            <div class="metric-card pendentes" onclick="window.location.href='empresas_relatorios.php'" style="cursor: pointer;">
                                <div class="metric-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="metric-info">
                                    <h3>Pagamentos Pendentes</h3>
                                    <p class="metric-value">R$ <?php echo formatNumber($metricas['valor_pendente']); ?></p>
                                    <p class="metric-detail">
                                        <span class="badge-pendente" style="background: #ff9800;"><?php echo formatNumber($metricas['pedidos_pendentes'], 0); ?></span>
                                        pedidos aguardando pagamento
                                    </p>
                                </div>
                            </div>

                            <!-- Card de Pedidos -->
                            <div class="metric-card primary">
                                <div class="metric-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="metric-info">
                                    <h2><?php echo formatNumber($metricas['total_pedidos'], 0); ?> Pedidos</h2>

                                    <h4 style="color: var(--gray-500);">Produtos Vendidos</h4>
                                    <div class="produtos-info">
                                        <div class="produto-item">
                                            <?php
                                            $count = 0;
                                            foreach ($produtos_categoria as $categoria):
                                                $class = $count >= 3 ? 'hidden' : '';
                                            ?>
                                                <div class="produto-detalhe <?php echo $class; ?>">
                                                    <span><b style="color: #09f;"><?php echo formatNumber($categoria['quantidade_total'], 0); ?></b></span>
                                                    <span><?php echo $categoria['nome_categoria']; ?></span>
                                                </div>
                                            <?php
                                                $count++;
                                            endforeach;

                                            if (count($produtos_categoria) > 3):
                                            ?>
                                                <button class="show-more-btn" onclick="toggleProdutos(this)">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos e Análises -->
                <div class="analytics-wrapper">
                    <div class="analytics-grid">
                        <!-- Produtos Mais Vendidos -->
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3><i class="fas fa-crown"></i> Top Produtos</h3>
                                <div class="card-actions">
                                    <button class="btn-view-mode active" data-view="quantity">Quantidade</button>
                                    <button class="btn-view-mode" data-view="revenue">Receita</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="products-chart">
                                    <canvas id="productsChart"></canvas>
                                </div>
                                <div class="products-list">
                                    <?php
                                    $categoria_atual = '';
                                    foreach ($produtos_populares as $produto):
                                        if ($categoria_atual != $produto['nome_categoria']):
                                            if ($categoria_atual != ''): ?>
                                                <div class="category-divider"></div>
                                            <?php endif;
                                            $categoria_atual = $produto['nome_categoria'];
                                            ?>
                                            <div class="category-header">
                                                <h4><?php echo $categoria_atual; ?>:</h4>
                                            </div>
                                        <?php endif; ?>
                                        <div class="product-item">
                                            <div class="product-info">
                                                <span class="product-name"><?php echo $produto['nome_produto']; ?></span>
                                            </div>
                                            <div class="product-stats">
                                                <span class="quantity"><?php echo $produto['quantidade_vendida']; ?> un</span>
                                                <span class="revenue">R$ <?php echo formatNumber($produto['receita_total']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Análise por Bairro -->

                    </div>
                </div>

                <!-- Botão de Logout Mobile -->
                <div class="mobile-logout">
                    <a href="../actions/login/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair do Sistema
                    </a>
                </div>
            </div>
        </main>
        <button class="floating-button" id="despesaBtn">+</button>
        <button class="floating-button fechamento-btn" id="fechamentoBtn">
            <i class="fas fa-cash-register"></i>
        </button>

        <!-- Modal de Despesa -->
        <div id="despesaModal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close-button">&times;</span>
                <h2>Nova Despesa</h2>
                <form>
                    <label for="valor">Valor:</label>
                    <input type="tel" id="valor" class="valor-input" placeholder="0,00" required>
                    <span class="error-message">Deve ter um valor maior que 0</span>

                    <div class="toggle-paid">
                        <button type="button" id="pago-button" class="not-paid">Não foi pago</button>
                    </div>

                    <label for="data">Data:</label>
                    <div class="date-options">
                        <button type="button" id="hoje">Hoje</button>
                        <button type="button" id="ontem">Ontem</button>
                    </div>
                    <input type="date" id="data" name="data" required>

                    <label for="descricao">Descrição:</label>
                    <input type="text" id="descricao" name="descricao" required>

                    <label for="categoria">Categoria:</label>
                    <select id="categoria" name="categoria">
                        <?php
                        // Busca categorias do banco de dados
                        $sql_categorias = "SELECT id_categoria, nome_categoria FROM categorias_despesa ORDER BY nome_categoria";
                        try {
                            $stmt = $pdo->prepare($sql_categorias);
                            $stmt->execute();
                            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($categorias as $categoria) {
                                echo "<option value='" . $categoria['id_categoria'] . "'>" . htmlspecialchars($categoria['nome_categoria']) . "</option>";
                            }
                        } catch (PDOException $e) {
                            error_log('Erro ao buscar categorias: ' . $e->getMessage());
                            echo "<option value=''>Erro ao carregar categorias</option>";
                        }
                        ?>
                    </select>

                    <div class="toggle-options">
                        <label>
                            <input type="checkbox" name="despesa_fixa"> Despesa fixa
                        </label>
                        <label>
                            <input type="checkbox" name="repetir"> Repetir
                        </label>
                        <input type="number" name="vezes" placeholder="2" style="width: 50px;">
                        <select name="periodo">
                            <option value="dias">Dias</option>
                            <option value="semanas">Semanas</option>
                            <option value="meses">Meses</option>
                            <option value="anos">Anos</option>
                        </select>
                    </div>

                    <button type="submit">Salvar</button>
                </form>
            </div>
        </div>

        <!-- Modal de Fechamento -->
        <div id="fechamentoModal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close-button" id="closeFechamento">&times;</span>
                <h2 style="margin-bottom: 15px; font-size: 18px;">Fechamento do Caixa</h2>
                <form id="fechamentoForm">
                    <div style="margin-bottom: 10px;">
                        <label for="dataFechamento">Data:</label>
                        <div class="date-options">
                            <button type="button" id="hojeFechamento">Hoje</button>
                            <button type="button" id="ontemFechamento">Ontem</button>
                        </div>
                        <input type="date" id="dataFechamento" name="data" required>
                    </div>

                    <div style="margin-bottom: 8px;">
                        <label for="dinheiro">Dinheiro:</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="tel" id="dinheiro" class="valor-input" placeholder="0,00" required style="flex: 1;">
                            <button type="button" class="calc-btn" data-target="dinheiro">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-bottom: 8px;">
                        <label for="pixNormal">PIX Normal:</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="tel" id="pixNormal" class="valor-input" placeholder="0,00" required style="flex: 1;">
                            <button type="button" class="calc-btn" data-target="pixNormal">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-bottom: 8px;">
                        <label for="onlinePix">PIX Online:</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="tel" id="onlinePix" class="valor-input" placeholder="0,00" required style="flex: 1;">
                            <button type="button" class="calc-btn" data-target="onlinePix">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-bottom: 8px;">
                        <label for="debito">Débito:</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="tel" id="debito" class="valor-input" placeholder="0,00" required style="flex: 1;">
                            <button type="button" class="calc-btn" data-target="debito">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-bottom: 8px;">
                        <label for="credito">Crédito:</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="tel" id="credito" class="valor-input" placeholder="0,00" required style="flex: 1;">
                            <button type="button" class="calc-btn" data-target="credito">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-bottom: 8px;">
                        <label for="vouchers">Vouchers:</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="tel" id="vouchers" class="valor-input" placeholder="0,00" required style="flex: 1;">
                            <button type="button" class="calc-btn" data-target="vouchers">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label for="ifood">iFood:</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="tel" id="ifood" class="valor-input" placeholder="0,00" required style="flex: 1;">
                            <button type="button" class="calc-btn" data-target="ifood">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit">Salvar Fechamento</button>
                </form>
            </div>
        </div>

        <!-- Modal da Calculadora -->
        <div id="calculadoraModal" class="modal" style="display:none;">
            <div class="modal-content calculadora-content">
                <span class="close-button" id="closeCalculadora">&times;</span>
                <h3 style="margin-bottom: 15px; text-align: center;">Calculadora</h3>
                
                <div class="calculadora">
                    <div class="calc-display">
                        <input type="text" id="calcDisplay" readonly placeholder="0,00">
                    </div>
                    
                    <div class="calc-buttons">
                        <div class="calc-row">
                            <button type="button" class="calc-number" data-value="7">7</button>
                            <button type="button" class="calc-number" data-value="8">8</button>
                            <button type="button" class="calc-number" data-value="9">9</button>
                            <button type="button" class="calc-operator" data-value="+">+</button>
                        </div>
                        <div class="calc-row">
                            <button type="button" class="calc-number" data-value="4">4</button>
                            <button type="button" class="calc-number" data-value="5">5</button>
                            <button type="button" class="calc-number" data-value="6">6</button>
                            <button type="button" class="calc-operator" data-value="-">-</button>
                        </div>
                        <div class="calc-row">
                            <button type="button" class="calc-number" data-value="1">1</button>
                            <button type="button" class="calc-number" data-value="2">2</button>
                            <button type="button" class="calc-number" data-value="3">3</button>
                            <button type="button" class="calc-operator" data-value="*">×</button>
                        </div>
                        <div class="calc-row">
                            <button type="button" class="calc-number" data-value="0">0</button>
                            <button type="button" class="calc-number" data-value=".">.</button>
                            <button type="button" class="calc-clear">C</button>
                            <button type="button" class="calc-operator" data-value="/">÷</button>
                        </div>
                        <div class="calc-row">
                            <button type="button" class="calc-equals" style="grid-column: span 2;">=</button>
                            <button type="button" class="calc-enter" style="grid-column: span 2;">Enter</button>
                        </div>
                    </div>
                    
                    <div class="calc-shortcuts" style="margin-top: 15px; padding: 10px; background: #f5f5f5; border-radius: 8px; font-size: 12px; text-align: center; color: #666;">
                        <strong>Atalhos de Teclado:</strong><br>
                        <span style="display: inline-block; margin: 2px 5px;">Números: 0-9</span>
                        <span style="display: inline-block; margin: 2px 5px;">Operadores: + - * /</span>
                        <span style="display: inline-block; margin: 2px 5px;">Decimal: . ou ,</span><br>
                        <span style="display: inline-block; margin: 2px 5px;">Calcular: =</span>
                        <span style="display: inline-block; margin: 2px 5px;">Aplicar: Enter</span>
                        <span style="display: inline-block; margin: 2px 5px;">Limpar: Delete</span>
                        <span style="display: inline-block; margin: 2px 5px;">Apagar: Backspace</span>
                        <span style="display: inline-block; margin: 2px 5px;">Fechar: Esc</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Scripts -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <!-- Widget DatePicker Script -->
        <script src="../includes/widget/script.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Configuração dos gráficos
                const productsData = <?php echo json_encode($produtos_populares); ?>;
                const neighborhoodsData = <?php echo json_encode($analise_bairros) ?: '[]'; ?>;

                // Gráfico de Produtos
                new Chart(document.getElementById('productsChart'), {
                    type: 'bar',
                    data: {
                        labels: productsData.map(item => item.nome_produto),
                        datasets: [{
                            label: 'Quantidade Vendida',
                            data: productsData.map(item => item.quantidade_vendida),
                            backgroundColor: '#2196F3',
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Debug dos dados dos bairros
                console.log('Período selecionado:', '<?php echo $periodo; ?>');
                console.log('Data início:', '<?php echo $data_inicio; ?>');
                console.log('Data fim:', '<?php echo $data_fim; ?>');
                console.log('Dados dos bairros:', neighborhoodsData);

                // Gráfico de Bairros
                const ctx = document.getElementById('neighborhoodChart');

                if (!ctx) {
                    console.error('Canvas do gráfico não encontrado');
                    return;
                }

                if (neighborhoodsData && neighborhoodsData.length > 0) {
                    console.log('Criando gráfico com dados:', neighborhoodsData);

                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: neighborhoodsData.map(item => item.nome_bairro),
                            datasets: [{
                                data: neighborhoodsData.map(item => Number(item.faturamento)),
                                backgroundColor: [
                                    '#2196F3',
                                    '#4CAF50',
                                    '#FF9800',
                                    '#9C27B0',
                                    '#F44336',
                                    '#00BCD4',
                                    '#FFC107',
                                    '#795548',
                                    '#607D8B',
                                    '#E91E63'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            let value = context.raw || 0;
                                            return `${label}: R$ ${value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    console.log('Sem dados para exibir no gráfico');
                    ctx.style.height = '200px';
                    ctx.style.display = 'flex';
                    ctx.style.alignItems = 'center';
                    ctx.style.justifyContent = 'center';
                    ctx.innerHTML = '<span style="color: var(--gray-500);">Nenhum dado disponível para o período selecionado</span>';
                }

                // Handlers para filtros e botões
                document.querySelectorAll('.period-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const period = this.getAttribute('data-period');
                        window.location.href = `?periodo=${period}`;
                    });
                });

                document.querySelectorAll('.btn-view-mode').forEach(btn => {
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.btn-view-mode').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        // Implementar lógica de alternar visualização
                    });
                });

                // Integração do Widget DatePicker com o sistema
                window.addEventListener('dateSelected', (event) => {
                    console.log('📅 Data selecionada:', event.detail);
                    const selectedDate = event.detail.dateObject; // Usar o objeto Date
                    if (selectedDate) {
                        const formattedDate = selectedDate.toISOString().split('T')[0];
                        console.log('Aplicando data única:', formattedDate);
                            window.location.href = `?data=${formattedDate}`;
                    }
                });

                window.addEventListener('dateRangeSelected', (event) => {
                    console.log('🎯 Range selecionado:', event.detail);
                    const { startDate, endDate } = event.detail;
                    if (startDate && endDate) {
                        const startFormatted = startDate.toISOString().split('T')[0];
                        const endFormatted = endDate.toISOString().split('T')[0];
                        console.log('Aplicando range:', { startFormatted, endFormatted });
                        window.location.href = `?data_inicio=${startFormatted}&data_fim=${endFormatted}`;
                    }
                });

                // Debug: verificar configuração do widget
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(() => {
                        const currentPeriod = '<?php echo $periodo; ?>';
                        const currentDate = '<?php echo $data_especifica; ?>';
                        const currentDataInicio = '<?php echo $data_inicio_especifica; ?>';
                        const currentDataFim = '<?php echo $data_fim_especifica; ?>';
                        
                        console.log('Dashboard configurado com sucesso');
                        console.log('Período atual:', currentPeriod);
                        console.log('Data atual:', currentDate);
                        console.log('Data início:', currentDataInicio);
                        console.log('Data fim:', currentDataFim);
                        console.log('Data início para filtro:', '<?php echo $data_inicio; ?>');
                        console.log('Data fim para filtro:', '<?php echo $data_fim; ?>');
                    }, 500);
                });
            });

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('../service-worker.js')
                        .then(registration => {
                            console.log('Service Worker registrado com sucesso:', registration.scope);
                        })
                        .catch(error => {
                            console.error('Erro no registro do Service Worker:', error);
                        });
                });
            }

            document.addEventListener('DOMContentLoaded', async () => {
                if ('Notification' in window) {
                    if (Notification.permission === 'default') {
                        try {
                            const permission = await Notification.requestPermission();
                            console.log('Permissão de notificação:', permission);
                        } catch (error) {
                            console.error('Erro ao solicitar permissão:', error);
                        }
                    }
                }
            });

            document.getElementById('testNotification').addEventListener('click', async () => {
                try {
                    // Se já tem permissão, manda a notificação na hora
                    if (Notification.permission === 'granted') {
                        new Notification('Novo Pedido!', {
                            body: 'Pedido #' + Math.floor(Math.random() * 1000) + ' acabou de chegar!',
                            icon: '../assets/img/icon-192x192.png'
                        });
                    }
                    // Se não tem permissão ainda, pede e já manda a notificação
                    else {
                        const permission = await Notification.requestPermission();
                        if (permission === 'granted') {
                            new Notification('Novo Pedido!', {
                                body: 'Pedido #' + Math.floor(Math.random() * 1000) + ' acabou de chegar!',
                                icon: '../assets/img/icon-192x192.png'
                            });
                        }
                    }
                } catch (error) {
                    console.error('Erro:', error);
                }
            });

            const modal = document.getElementById('despesaModal');
            const fechamentoModal = document.getElementById('fechamentoModal');
            const btn = document.getElementById('despesaBtn');
            const fechamentoBtn = document.getElementById('fechamentoBtn');
            const span = document.getElementsByClassName('close-button')[0];
            const closeFechamento = document.getElementById('closeFechamento');

            btn.onclick = function() {
                modal.style.display = 'flex';
                document.getElementById('valor').focus();
            }

            fechamentoBtn.onclick = function() {
                fechamentoModal.style.display = 'flex';
                document.getElementById('dinheiro').focus();
            }

            span.onclick = function() {
                modal.style.display = 'none';
            }

            closeFechamento.onclick = function() {
                fechamentoModal.style.display = 'none';
            }

            // Variáveis da calculadora
            const calculadoraModal = document.getElementById('calculadoraModal');
            const closeCalculadora = document.getElementById('closeCalculadora');
            let currentTarget = null;
            let calcDisplay = '';
            let calcResult = 0;
            let calcOperation = '';
            let calcWaitingForOperand = false;

            closeCalculadora.onclick = function() {
                calculadoraModal.style.display = 'none';
            }

            // Fechar modais quando clicar fora deles
            window.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
                if (event.target === fechamentoModal) {
                    fechamentoModal.style.display = 'none';
                }
                if (event.target === calculadoraModal) {
                    calculadoraModal.style.display = 'none';
                }
            }

            // Função para formatar valores monetários
            function formatarValor(input) {
                let value = input.value.replace(/\D/g, '');
                value = (parseInt(value) / 100).toFixed(2);
                // Formata com vírgula como decimal e ponto como separador de milhares
                input.value = value.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            document.getElementById('valor').addEventListener('input', function(e) {
                formatarValor(e.target);
            });

            // Aplicar formatação para todos os campos de valor do fechamento
            ['dinheiro', 'pixNormal', 'onlinePix', 'debito', 'credito', 'vouchers', 'ifood'].forEach(id => {
                document.getElementById(id).addEventListener('input', function(e) {
                    formatarValor(e.target);
                });
            });

            // Event listeners para botões de calculadora
            document.querySelectorAll('.calc-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    currentTarget = this.getAttribute('data-target');
                    calculadoraModal.style.display = 'flex';
                    document.getElementById('calcDisplay').focus();
                });
            });

            // Funções da calculadora
            function updateCalcDisplay() {
                document.getElementById('calcDisplay').value = calcDisplay || '0';
            }

            function inputDigit(digit) {
                if (calcWaitingForOperand) {
                    calcDisplay = digit;
                    calcWaitingForOperand = false;
                } else {
                    calcDisplay = calcDisplay === '0' ? digit : calcDisplay + digit;
                }
                updateCalcDisplay();
            }

            function inputDecimal() {
                if (calcWaitingForOperand) {
                    calcDisplay = '0.';
                    calcWaitingForOperand = false;
                } else if (calcDisplay.indexOf('.') === -1) {
                    calcDisplay += '.';
                }
                updateCalcDisplay();
            }

            function clearCalculator() {
                calcDisplay = '';
                calcResult = 0;
                calcOperation = '';
                calcWaitingForOperand = false;
                updateCalcDisplay();
            }

            function performOperation(nextOperation) {
                const inputValue = parseFloat(calcDisplay);

                if (calcResult === 0) {
                    calcResult = inputValue;
                } else if (calcOperation) {
                    const result = performCalculation(calcResult, inputValue, calcOperation);
                    calcDisplay = String(result);
                    calcResult = result;
                }

                calcWaitingForOperand = true;
                calcOperation = nextOperation;
                updateCalcDisplay();
            }

            function performCalculation(firstValue, secondValue, operation) {
                switch (operation) {
                    case '+': return firstValue + secondValue;
                    case '-': return firstValue - secondValue;
                    case '*': return firstValue * secondValue;
                    case '/': return firstValue / secondValue;
                    default: return secondValue;
                }
            }

            function calculateResult() {
                const inputValue = parseFloat(calcDisplay);

                if (calcOperation && !calcWaitingForOperand) {
                    calcDisplay = String(performCalculation(calcResult, inputValue, calcOperation));
                    calcOperation = '';
                    calcWaitingForOperand = true;
                    updateCalcDisplay();
                }
            }

            function applyResultToInput() {
                const result = parseFloat(calcDisplay);
                if (!isNaN(result) && currentTarget) {
                    const targetInput = document.getElementById(currentTarget);
                    const formattedResult = result.toFixed(2).replace('.', ',');
                    targetInput.value = formattedResult;
                    calculadoraModal.style.display = 'none';
                    clearCalculator();
                    
                    // Focar no próximo campo após aplicar o resultado
                    setTimeout(() => {
                        const nextInput = targetInput.parentElement.nextElementSibling?.querySelector('input');
                        if (nextInput) {
                            nextInput.focus();
                        }
                    }, 100);
                }
            }

            // Event listeners para botões da calculadora
            document.querySelectorAll('.calc-number').forEach(btn => {
                btn.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    if (value === '.') {
                        inputDecimal();
                    } else {
                        inputDigit(value);
                    }
                });
            });

            document.querySelectorAll('.calc-operator').forEach(btn => {
                btn.addEventListener('click', function() {
                    const operation = this.getAttribute('data-value');
                    performOperation(operation);
                });
            });

            document.querySelector('.calc-clear').addEventListener('click', clearCalculator);

            document.querySelector('.calc-equals').addEventListener('click', calculateResult);

            document.querySelector('.calc-enter').addEventListener('click', applyResultToInput);

            // Suporte a teclado para a calculadora
            document.addEventListener('keydown', function(e) {
                // Só processar teclas se a calculadora estiver aberta
                if (calculadoraModal.style.display === 'flex') {
                    e.preventDefault();
                    
                    // Números
                    if (/^[0-9]$/.test(e.key)) {
                        inputDigit(e.key);
                    }
                    // Ponto decimal
                    else if (e.key === '.' || e.key === ',') {
                        inputDecimal();
                    }
                    // Operadores
                    else if (e.key === '+') {
                        performOperation('+');
                    }
                    else if (e.key === '-') {
                        performOperation('-');
                    }
                    else if (e.key === '*') {
                        performOperation('*');
                    }
                    else if (e.key === '/') {
                        performOperation('/');
                    }
                    // Enter para aplicar resultado
                    else if (e.key === 'Enter') {
                        applyResultToInput();
                    }
                    // Escape para fechar
                    else if (e.key === 'Escape') {
                        calculadoraModal.style.display = 'none';
                        clearCalculator();
                    }
                    // Backspace para apagar último dígito
                    else if (e.key === 'Backspace') {
                        if (calcDisplay.length > 0) {
                            calcDisplay = calcDisplay.slice(0, -1);
                            updateCalcDisplay();
                        }
                    }
                    // Delete para limpar
                    else if (e.key === 'Delete') {
                        clearCalculator();
                    }
                    // Igual para calcular
                    else if (e.key === '=') {
                        calculateResult();
                    }
                }
            });

            const pagoButton = document.getElementById('pago-button');

            pagoButton.addEventListener('click', function() {
                if (pagoButton.classList.contains('not-paid')) {
                    pagoButton.classList.remove('not-paid');
                    pagoButton.classList.add('paid');
                    pagoButton.textContent = 'Foi paga';
                } else {
                    pagoButton.classList.remove('paid');
                    pagoButton.classList.add('not-paid');
                    pagoButton.textContent = 'Não foi pago';
                }
            });

            document.getElementById('hoje').addEventListener('click', function() {
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('data').value = today;
            });

            document.getElementById('ontem').addEventListener('click', function() {
                const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
                document.getElementById('data').value = yesterday;
            });

            // Botões de data para o fechamento
            document.getElementById('hojeFechamento').addEventListener('click', function() {
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('dataFechamento').value = today;
            });

            document.getElementById('ontemFechamento').addEventListener('click', function() {
                const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
                document.getElementById('dataFechamento').value = yesterday;
            });

            const repetirCheckbox = document.querySelector('input[name="repetir"]');
            const repetirInputs = document.querySelectorAll('input[name="vezes"], select[name="periodo"]');

            repetirCheckbox.addEventListener('change', function() {
                repetirInputs.forEach(input => {
                    input.disabled = !repetirCheckbox.checked;
                });
            });

            // Inicialmente desabilitar os inputs
            repetirInputs.forEach(input => {
                input.disabled = true;
            });

            const despesaFixaCheckbox = document.querySelector('input[name="despesa_fixa"]');

            despesaFixaCheckbox.addEventListener('change', function() {
                if (despesaFixaCheckbox.checked) {
                    repetirCheckbox.checked = false;
                    repetirInputs.forEach(input => {
                        input.disabled = true;
                    });
                }
            });

            repetirCheckbox.addEventListener('change', function() {
                if (repetirCheckbox.checked) {
                    despesaFixaCheckbox.checked = false;
                }
            });

            // Adiciona o handler de submit do formulário de despesa
            document.querySelector('#despesaModal form').addEventListener('submit', async function(e) {
                e.preventDefault();

                const formData = {
                    valor: document.getElementById('valor').value,
                    data: document.getElementById('data').value,
                    descricao: document.getElementById('descricao').value,
                    categoria: document.getElementById('categoria').value,
                    pago: document.getElementById('pago-button').classList.contains('paid'),
                    repetir: document.querySelector('input[name="repetir"]').checked,
                    vezes: document.querySelector('input[name="vezes"]').value,
                    periodo: document.querySelector('select[name="periodo"]').value,
                    despesa_fixa: document.querySelector('input[name="despesa_fixa"]').checked
                };

                try {
                    const response = await fetch('../actions/dashboard/inserir_despesa.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(formData)
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        alert('Despesa registrada com sucesso!');
                        modal.style.display = 'none';
                        // Recarrega a página para atualizar os dados
                        window.location.reload();
                    } else {
                        alert('Erro ao registrar despesa: ' + data.message);
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    alert('Erro ao registrar despesa. Por favor, tente novamente.');
                }
            });

            // Adiciona o handler de submit do formulário de fechamento
            document.getElementById('fechamentoForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                const formData = {
                    data: document.getElementById('dataFechamento').value,
                    dinheiro: document.getElementById('dinheiro').value,
                    pix_normal: document.getElementById('pixNormal').value,
                    online_pix: document.getElementById('onlinePix').value,
                    debito: document.getElementById('debito').value,
                    credito: document.getElementById('credito').value,
                    vouchers: document.getElementById('vouchers').value,
                    ifood: document.getElementById('ifood').value
                };

                try {
                    const response = await fetch('../actions/dashboard/inserir_fechamento.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(formData)
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        alert('Fechamento registrado com sucesso!');
                        fechamentoModal.style.display = 'none';
                        // Recarrega a página para atualizar os dados
                        window.location.reload();
                    } else {
                        alert('Erro ao registrar fechamento: ' + data.message);
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    alert('Erro ao registrar fechamento. Por favor, tente novamente.');
                }
            });

            function toggleProdutos(btn) {
                const hiddenItems = document.querySelectorAll('.produto-detalhe.hidden');
                hiddenItems.forEach(item => {
                    if (item.style.display === 'none' || !item.style.display) {
                        item.style.display = 'flex';
                        btn.classList.add('active');
                    } else {
                        item.style.display = 'none';
                        btn.classList.remove('active');
                    }
                });
            }
        </script>
</body>

</html>