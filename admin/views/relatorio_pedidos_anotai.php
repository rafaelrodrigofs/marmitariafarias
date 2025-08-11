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
    <title>Relatório de Pedidos Anotai - Lunch&Fit</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../includes/widget/styles.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>

        /* Cards */
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h3 {
            margin-bottom: 20px;
            color: #333;
        }



        /* Cards de Resumo - Estilo Profissional */
        .resumo-cards {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }

        .card-resumo {
            position: relative;
            border-radius: 16px;
            padding: 28px 24px;
            flex: 1;
            min-width: 280px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .card-resumo::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 16px 16px 0 0;
        }

        .card-resumo:hover {
            transform: translateY(-8px) scale(1.02);
        }

        /* Card Laranja - Total de Pedidos (Cor Principal da Logo) */
        .card-azul {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card-azul::before {
            background: linear-gradient(90deg, #ff8c42 0%, #ffa726 100%);
        }

        .card-azul:hover {
            background: linear-gradient(135deg, #e55a2b 0%, #e8801a 100%);
        }

        .card-azul::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 3s infinite;
        }

        /* Card Verde - Faturamento (Cor Secundária da Logo) */
        .card-verde {
            background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card-verde::before {
            background: linear-gradient(90deg, #66bb6a 0%, #9ccc65 100%);
        }

        .card-verde:hover {
            background: linear-gradient(135deg, #43a047 0%, #7cb342 100%);
        }

        .card-verde::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 3s infinite 1s;
        }

        /* Card Laranja/Verde Mix - Ticket Médio */
        .card-laranja {
            background: linear-gradient(135deg, #ff9800 0%, #689f38 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card-laranja::before {
            background: linear-gradient(90deg, #ffb74d 0%, #8bc34a 100%);
        }

        .card-laranja:hover {
            background: linear-gradient(135deg, #f57c00 0%, #558b2f 100%);
        }

        .card-laranja::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 3s infinite 2s;
        }

        /* Conteúdo dos Cards */
        .card-resumo-header {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }

        .card-resumo-icon {
            width: 48px;
            height: 48px;
            margin-right: 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
            animation: iconPulse 4s ease-in-out infinite;
            position: relative;
            z-index: 2;
        }

        .card-resumo:hover .card-resumo-icon {
            transform: scale(1.1) rotate(5deg);
            background: rgba(255, 255, 255, 0.3);
            animation-play-state: paused;
        }

        /* Animação de pulse nos ícones */
        @keyframes iconPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        /* Delays diferentes para cada card */
        .card-azul .card-resumo-icon {
            animation-delay: 0s;
        }

        .card-verde .card-resumo-icon {
            animation-delay: 1.3s;
        }

        .card-laranja .card-resumo-icon {
            animation-delay: 2.6s;
        }

        .card-resumo h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
            flex: 1;
        }

        .card-resumo-value {
            font-size: 32px;
            font-weight: 700;
            margin: 8px 0 0 0;
            line-height: 1.2;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease forwards, valueBreath 6s ease-in-out infinite;
            position: relative;
            z-index: 2;
        }

        .card-resumo:hover .card-resumo-value {
            transform: scale(1.05);
            animation-play-state: paused;
        }

        /* Animação de respiração nos valores */
        @keyframes valueBreath {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
        }

        /* Delays para valores */
        .card-azul .card-resumo-value {
            animation-delay: 0.6s, 1s;
        }

        .card-verde .card-resumo-value {
            animation-delay: 0.8s, 2s;
        }

        .card-laranja .card-resumo-value {
            animation-delay: 1s, 3s;
        }

        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-resumo {
            animation: slideInCards 0.8s ease forwards, cardFloat 8s ease-in-out infinite;
        }

        .card-resumo:nth-child(1) {
            animation-delay: 0.1s, 0s;
        }

        .card-resumo:nth-child(2) {
            animation-delay: 0.2s, 2.6s;
        }

        .card-resumo:nth-child(3) {
            animation-delay: 0.3s, 5.2s;
        }

        /* Animação de flutuação suave */
        @keyframes cardFloat {
            0%, 100% {
                transform: translateY(0px);
            }
            25% {
                transform: translateY(-3px);
            }
            75% {
                transform: translateY(2px);
            }
        }

        @keyframes slideInCards {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Animação shimmer (brilho deslizante) */
        @keyframes shimmer {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }

        .card-azul .card-resumo-value {
            font-size: 36px;
        }

        /* Indicador de crescimento (opcional) */
        .card-resumo-trend {
            display: flex;
            align-items: center;
            margin-top: 12px;
            font-size: 12px;
            opacity: 0.8;
        }

        .trend-icon {
            margin-right: 4px;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .resumo-cards {
                gap: 16px;
            }
            
            .card-resumo {
                min-width: 100%;
                padding: 20px 16px;
            }
            
            .card-resumo-value {
                font-size: 28px;
            }
            
            .card-azul .card-resumo-value {
                font-size: 32px;
            }
        }

        /* Tabela */
        .tabela-container {
            overflow-x: auto;
        }

        .tabela-relatorio {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .tabela-relatorio thead tr {
            background-color: #f8f9fa;
            border-bottom: 2px solid #ddd;
        }

        .tabela-relatorio th {
            padding: 12px;
            border: 1px solid #ddd;
        }

        .tabela-relatorio th.text-left {
            text-align: left;
        }

        .tabela-relatorio th.text-center {
            text-align: center;
        }

        .tabela-relatorio th.text-right {
            text-align: right;
        }

        .tabela-relatorio td {
            padding: 10px;
            border: 1px solid #ddd;
        }

        .tabela-relatorio td.text-center {
            text-align: center;
        }

        .tabela-relatorio td.text-right {
            text-align: right;
        }

        .linha-par {
            background-color: #f8f9fa;
        }

        .linha-impar {
            background-color: #ffffff;
        }

        .tabela-relatorio tbody tr {
            border-bottom: 1px solid #eee;
        }

        /* Mensagens */
        .sem-dados {
            text-align: center;
        }

        .sem-dados h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .sem-dados p {
            color: #666;
            margin: 0;
        }

        .erro-card {
            background: #ffebee;
            border-left: 4px solid #f44336;
        }

        .erro-card h4 {
            color: #c62828;
            margin: 0 0 10px 0;
        }

        .erro-card p {
            color: #666;
            margin: 0;
        }

        /* Título da página */
        .header-title {
            cursor: pointer;
        }


    </style>

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


                        <!-- Widget DatePicker controla os filtros agora -->
                        <div class="dashboard-content">

                            <?php
                            try {
                                // Obter parâmetros de filtro do widget
                                $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
                                $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
                                
                                // Suporte para data única (data específica)
                                if (isset($_GET['data']) && !empty($_GET['data'])) {
                                    $data_inicio = $_GET['data'];
                                    $data_fim = $_GET['data'];
                                }
                                // Construir condições de filtro baseadas no período do widget
                                $filtro_data = "";
                                $parametros = [];

                                // Se tem data específica ou range de datas do widget
                                        if (!empty($data_inicio) && !empty($data_fim)) {
                                    $filtro_data = "AND o.date_order BETWEEN :data_inicio AND :data_fim";
                                            $parametros[':data_inicio'] = $data_inicio;
                                            $parametros[':data_fim'] = $data_fim;
                                        } elseif (!empty($data_inicio)) {
                                    $filtro_data = "AND o.date_order >= :data_inicio";
                                            $parametros[':data_inicio'] = $data_inicio;
                                        } elseif (!empty($data_fim)) {
                                    $filtro_data = "AND o.date_order <= :data_fim";
                                            $parametros[':data_fim'] = $data_fim;
                                } else {
                                    // Padrão: mês atual se nenhuma data foi selecionada no widget
                                    $filtro_data = "AND MONTH(o.date_order) = MONTH(CURDATE()) AND YEAR(o.date_order) = YEAR(CURDATE())";
                                }

                                // Query SQL para relatório de pedidos individuais
                                $sql = "SELECT 
                        o.shortReference_order,
                        DATE(o.date_order) as data,
                        CASE DAYOFWEEK(o.date_order)
                            WHEN 1 THEN 'Domingo'
                            WHEN 2 THEN 'Segunda-feira'
                            WHEN 3 THEN 'Terça-feira'
                            WHEN 4 THEN 'Quarta-feira'
                            WHEN 5 THEN 'Quinta-feira'
                            WHEN 6 THEN 'Sexta-feira'
                            WHEN 7 THEN 'Sábado'
                        END as dia_semana,
                        COALESCE(c.name_client, 'Cliente não informado') as cliente,
                        CASE 
                            WHEN o.fk_id_address IS NOT NULL AND o.fk_id_address > 0 THEN 'Delivery'
                            ELSE 'Retirada'
                        END as forma_entrega,
                        ROUND(o.total_order, 2) as valor_pedido,
                        CASE o.check_order
                            WHEN -2 THEN 'Agendado'
                            WHEN 0 THEN 'Em análise'
                            WHEN 1 THEN 'Em produção'
                            WHEN 2 THEN 'Pronto'
                            WHEN 3 THEN 'Finalizado'
                            WHEN 4 THEN 'Cancelado'
                            WHEN 5 THEN 'Negado'
                            WHEN 6 THEN 'Cancelamento solicitado'
                            ELSE 'Desconhecido'
                        END as status_pedido
                    FROM o01_order o
                    LEFT JOIN client c ON o.fk_id_client = c.id_client
                    WHERE o.date_order IS NOT NULL 
                        AND o.check_order NOT IN (4,5,6)
                        $filtro_data
                    ORDER BY o.date_order DESC, o.id_order DESC";

                                $stmt = $pdo->prepare($sql);

                                // Executar com parâmetros se houver
                                if (!empty($parametros)) {
                                    $stmt->execute($parametros);
                                } else {
                                    $stmt->execute();
                                }

                                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                // Exibir período selecionado baseado no widget
                                $periodo_texto = '';
                                        if (!empty($data_inicio) && !empty($data_fim)) {
                                    if ($data_inicio === $data_fim) {
                                        $periodo_texto = 'Data: ' . date('d/m/Y', strtotime($data_inicio));
                                    } else {
                                            $periodo_texto = 'Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
                                    }
                                        } else {
                                        $periodo_texto = 'Mês Atual';
                                }

                                if ($results) {
                                    // Resumo geral baseado nos pedidos individuais
                                    $total_pedidos = count($results);
                                    $total_faturamento_bruto = array_sum(array_column($results, 'valor_pedido'));
                                    $ticket_medio_geral = $total_pedidos > 0 ? $total_faturamento_bruto / $total_pedidos : 0;

                                    echo '<div class="resumo-cards">';

                                    echo '<div class="card-resumo card-azul">';
                                    echo '<div class="card-resumo-header">';
                                    echo '<div class="card-resumo-icon"><i class="fas fa-shopping-bag"></i></div>';
                                    echo '<h4>Total de Pedidos</h4>';
                                    echo '</div>';
                                    echo '<div class="card-resumo-value">' . $total_pedidos . '</div>';
                                    echo '</div>';

                                    echo '<div class="card-resumo card-verde">';
                                    echo '<div class="card-resumo-header">';
                                    echo '<div class="card-resumo-icon"><i class="fas fa-chart-line"></i></div>';
                                    echo '<h4>Faturamento Bruto</h4>';
                                    echo '</div>';
                                    echo '<div class="card-resumo-value">R$ ' . number_format($total_faturamento_bruto, 2, ',', '.') . '</div>';
                                    echo '</div>';

                                    echo '<div class="card-resumo card-laranja">';
                                    echo '<div class="card-resumo-header">';
                                    echo '<div class="card-resumo-icon"><i class="fas fa-receipt"></i></div>';
                                    echo '<h4>Ticket Médio Geral</h4>';
                                    echo '</div>';
                                    echo '<div class="card-resumo-value">R$ ' . number_format($ticket_medio_geral, 2, ',', '.') . '</div>';
                                    echo '</div>';

                                    echo '</div>';

                                    echo '<div class="card">';
                                    echo '<h3>Lista de Pedidos - ' . $periodo_texto . '</h3>';
                                    echo '<div class="tabela-container">';
                                    echo '<table class="tabela-relatorio">';
                                    echo '<thead>';
                                    echo '<tr>';
                                    echo '<th class="text-center">Ref.</th>';
                                    echo '<th class="text-left">Data</th>';
                                    echo '<th class="text-left">Cliente</th>';
                                    echo '<th class="text-center">Entrega</th>';
                                    echo '<th class="text-right">Valor</th>';
                                    echo '<th class="text-center">Status</th>';
                                    echo '</tr>';
                                    echo '</thead>';
                                    echo '<tbody>';

                                    foreach ($results as $index => $row) {
                                        // Efeito zebra - linhas alternadas
                                        $linha_classe = ($index % 2 == 0) ? 'linha-par' : 'linha-impar';
                                        echo '<tr class="' . $linha_classe . '">';
                                        echo '<td class="text-center">' . ($row['shortReference_order'] ?? '-') . '</td>';
                                        echo '<td>' . date('d/m/Y', strtotime($row['data'])) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['cliente']) . '</td>';
                                        echo '<td class="text-center">' . $row['forma_entrega'] . '</td>';
                                        echo '<td class="text-right">R$ ' . number_format($row['valor_pedido'], 2, ',', '.') . '</td>';
                                        echo '<td class="text-center">' . $row['status_pedido'] . '</td>';
                                        echo '</tr>';
                                    }

                                    echo '</tbody>';
                                    echo '</table>';
                                    echo '</div>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="card sem-dados">';
                                    echo '<h3>Relatório - ' . $periodo_texto . '</h3>';
                                    echo '<p>Nenhum dado encontrado para o período selecionado.</p>';
                                    echo '</div>';
                                }
                            } catch (Exception $e) {
                                echo '<div class="card erro-card">';
                                echo '<h4>Erro ao carregar relatório</h4>';
                                echo '<p>Ocorreu um erro ao processar os dados: ' . htmlspecialchars($e->getMessage()) . '</p>';
                                echo '</div>';
                                error_log('Erro no relatório de pedidos: ' . $e->getMessage());
                            }
                            ?>
                        </div>
                    </main>
                </div>
                <script src="../includes/widget/script.js"></script>
                <script>
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
                        const {
                            startDate,
                            endDate
                        } = event.detail;
                        if (startDate && endDate) {
                            const startFormatted = startDate.toISOString().split('T')[0];
                            const endFormatted = endDate.toISOString().split('T')[0];
                            console.log('Aplicando range:', {
                                startFormatted,
                                endFormatted
                            });
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
                </script>
    </div>
    </main>
    </div>
</body>

</html>