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
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Layout Geral */
        .main-content {
            padding: 20px;
        }
        
        .dashboard-content {
            margin-top: 20px;
        }

        /* Cards */
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h3 {
            margin-bottom: 20px;
            color: #333;
        }

        /* Filtros */
        .filtros-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }

        .filtro-grupo {
            flex: 1;
            min-width: 120px;
        }

        .filtro-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .filtro-select, .filtro-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .datas-personalizadas {
            display: flex;
            gap: 10px;
        }

        .datas-personalizadas.hidden {
            display: none;
        }

        .btn-primary {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 4px;
            margin-left: 5px;
            display: inline-block;
        }

        /* Cards de Resumo */
        .resumo-cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .card-resumo {
            border-radius: 8px;
            padding: 20px;
            flex: 1;
            min-width: 200px;
        }

        .card-azul {
            background: #e3f2fd;
        }

        .card-verde {
            background: #e8f5e8;
        }

        .card-laranja {
            background: #fff3e0;
        }

        .card-resumo h4 {
            margin: 0 0 10px 0;
        }

        .card-resumo p {
            font-size: 20px;
            font-weight: bold;
            margin: 0;
        }

        .card-resumo.card-azul h4,
        .card-resumo.card-azul p {
            color: #1976d2;
        }

        .card-resumo.card-verde h4,
        .card-resumo.card-verde p {
            color: #2e7d32;
        }

        .card-resumo.card-laranja h4,
        .card-resumo.card-laranja p {
            color: #ef6c00;
        }

        .card-azul p {
            font-size: 24px;
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

        /* Filtros por dia da semana */
        .filtros-dia-semana {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .dias-semana-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .btn-dia-semana {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
            min-width: 80px;
            text-align: center;
        }

        .btn-dia-semana:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .btn-dia-semana.ativo {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .btn-dia-semana.todos {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .btn-dia-semana.todos:hover {
            background: #218838;
            border-color: #1e7e34;
        }
    </style>

</head>
<body class="dashboard-page">
    <?php include_once '../includes/menu.php'; ?>
    <div class="main-content" style="padding: 20px;">
        <main class="dashboard-container">
            <div class="dashboard-top">
                <div class="dashboard-header">
                    <div class="header-content">
                        <h1 class="header-title" id="testNotification">Relatório de Pedidos Anotai</h1>
                    </div>
                </div>
            </div>

            <!-- Filtros de Período -->
            <div class="dashboard-content">
                <div class="card">
                    <h3>Filtros de Período</h3>
                    <form method="GET" class="filtros-form">
                        <div class="filtro-grupo">
                            <label class="filtro-label">Período:</label>
                            <select name="periodo" class="filtro-select">
                                <option value="total" <?php echo (isset($_GET['periodo']) && $_GET['periodo'] == 'total') ? 'selected' : ''; ?>>Total</option>
                                <option value="semana" <?php echo (isset($_GET['periodo']) && $_GET['periodo'] == 'semana') ? 'selected' : ''; ?>>Última Semana</option>
                                <option value="mes" <?php echo (!isset($_GET['periodo']) || $_GET['periodo'] == 'mes') ? 'selected' : ''; ?>>Mês Atual</option>
                                <option value="ano" <?php echo (isset($_GET['periodo']) && $_GET['periodo'] == 'ano') ? 'selected' : ''; ?>>Último Ano</option>
                                <option value="personalizado" <?php echo (isset($_GET['periodo']) && $_GET['periodo'] == 'personalizado') ? 'selected' : ''; ?>>Período Personalizado</option>
                            </select>
                        </div>
                        
                        <div id="datas-personalizadas" class="datas-personalizadas <?php echo (!isset($_GET['periodo']) || $_GET['periodo'] != 'personalizado') ? 'hidden' : ''; ?>">
                            <div>
                                <label class="filtro-label">Data Inicial:</label>
                                <input type="date" name="data_inicio" value="<?php echo isset($_GET['data_inicio']) ? $_GET['data_inicio'] : ''; ?>" class="filtro-input">
                            </div>
                            <div>
                                <label class="filtro-label">Data Final:</label>
                                <input type="date" name="data_fim" value="<?php echo isset($_GET['data_fim']) ? $_GET['data_fim'] : ''; ?>" class="filtro-input">
                            </div>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="?" class="btn-secondary">
                                <i class="fas fa-times"></i> Limpar
                            </a>
                        </div>
                    </form>

                    <!-- Filtros por Dia da Semana -->
                    <div class="filtros-dia-semana">
                        <label class="filtro-label">Filtrar por Dia da Semana:</label>
                        <div class="dias-semana-container">
                            <?php 
                            $dia_selecionado = isset($_GET['dia_semana']) ? $_GET['dia_semana'] : '';
                            $params_filtro = $_GET;
                            ?>
                            <a href="?<?php unset($params_filtro['dia_semana']); echo http_build_query($params_filtro); ?>" 
                               class="btn-dia-semana todos <?php echo ($dia_selecionado == '') ? 'ativo' : ''; ?>">
                               Todos
                            </a>
                            
                            <?php
                            $dias_semana = [
                                '2' => 'Segunda',
                                '3' => 'Terça', 
                                '4' => 'Quarta',
                                '5' => 'Quinta',
                                '6' => 'Sexta',
                                '7' => 'Sábado',
                                '1' => 'Domingo'
                            ];
                            
                            foreach($dias_semana as $numero => $nome) {
                                $params_filtro = $_GET;
                                $params_filtro['dia_semana'] = $numero;
                                $classe_ativo = ($dia_selecionado == $numero) ? 'ativo' : '';
                                echo '<a href="?' . http_build_query($params_filtro) . '" class="btn-dia-semana ' . $classe_ativo . '">' . $nome . '</a>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <script>
                document.querySelector('select[name="periodo"]').addEventListener('change', function() {
                    const datasPersonalizadas = document.getElementById('datas-personalizadas');
                    if (this.value === 'personalizado') {
                        datasPersonalizadas.classList.remove('hidden');
                    } else {
                        datasPersonalizadas.classList.add('hidden');
                    }
                });
                </script>

                <?php
                try {
                    // Obter parâmetros de filtro
                    $periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
                    $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
                    $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
                    $dia_semana = isset($_GET['dia_semana']) ? $_GET['dia_semana'] : '';

                    // Construir condições de filtro baseadas no período
                    $filtro_data = "";
                    $parametros = [];

                    switch ($periodo) {
                        case 'semana':
                            $filtro_data = "AND date_order >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                            break;
                        case 'mes':
                            $filtro_data = "AND MONTH(date_order) = MONTH(CURDATE()) AND YEAR(date_order) = YEAR(CURDATE())";
                            break;
                        case 'ano':
                            $filtro_data = "AND date_order >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                            break;
                        case 'personalizado':
                            if (!empty($data_inicio) && !empty($data_fim)) {
                                $filtro_data = "AND date_order BETWEEN :data_inicio AND :data_fim";
                                $parametros[':data_inicio'] = $data_inicio;
                                $parametros[':data_fim'] = $data_fim;
                            } elseif (!empty($data_inicio)) {
                                $filtro_data = "AND date_order >= :data_inicio";
                                $parametros[':data_inicio'] = $data_inicio;
                            } elseif (!empty($data_fim)) {
                                $filtro_data = "AND date_order <= :data_fim";
                                $parametros[':data_fim'] = $data_fim;
                            }
                            break;
                        case 'total':
                            $filtro_data = "";
                            break;
                        default: // 'mes' (padrão)
                            $filtro_data = "AND MONTH(date_order) = MONTH(CURDATE()) AND YEAR(date_order) = YEAR(CURDATE())";
                            break;
                    }

                    // Adicionar filtro por dia da semana se selecionado
                    $filtro_dia_semana = "";
                    if (!empty($dia_semana)) {
                        $filtro_dia_semana = "AND DAYOFWEEK(date_order) = :dia_semana";
                        $parametros[':dia_semana'] = $dia_semana;
                    }

                    // Query SQL para relatório de pedidos
                    $sql = "SELECT 
                        DATE(date_order) as data,
                        CASE DAYOFWEEK(date_order)
                            WHEN 1 THEN 'Domingo'
                            WHEN 2 THEN 'Segunda-feira'
                            WHEN 3 THEN 'Terça-feira'
                            WHEN 4 THEN 'Quarta-feira'
                            WHEN 5 THEN 'Quinta-feira'
                            WHEN 6 THEN 'Sexta-feira'
                            WHEN 7 THEN 'Sábado'
                        END as dia_semana,
                        COUNT(*) as total_pedidos,
                        ROUND(SUM(total_order), 2) as faturamento_bruto,
                        ROUND(AVG(total_order), 2) as ticket_medio
                    FROM o01_order 
                    WHERE date_order IS NOT NULL 
                        AND check_order NOT IN (4,5,6)
                        $filtro_data
                        $filtro_dia_semana
                    GROUP BY DATE(date_order)
                    ORDER BY data DESC";

                    $stmt = $pdo->prepare($sql);
                    
                    // Executar com parâmetros se houver
                    if (!empty($parametros)) {
                        $stmt->execute($parametros);
                    } else {
                        $stmt->execute();
                    }
                    
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Exibir período selecionado
                    $periodo_texto = '';
                    switch ($periodo) {
                        case 'semana': $periodo_texto = 'Última Semana'; break;
                        case 'mes': $periodo_texto = 'Mês Atual'; break;
                        case 'ano': $periodo_texto = 'Último Ano'; break;
                        case 'total': $periodo_texto = 'Todos os Registros'; break;
                        case 'personalizado': 
                            if (!empty($data_inicio) && !empty($data_fim)) {
                                $periodo_texto = 'Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
                            } else {
                                $periodo_texto = 'Período Personalizado';
                            }
                            break;
                        default: $periodo_texto = 'Mês Atual'; break; // padrão
                    }

                    // Adicionar dia da semana ao texto se filtrado
                    if (!empty($dia_semana)) {
                        $dias_semana_nomes = [
                            '1' => 'Domingos',
                            '2' => 'Segundas-feiras', 
                            '3' => 'Terças-feiras',
                            '4' => 'Quartas-feiras',
                            '5' => 'Quintas-feiras',
                            '6' => 'Sextas-feiras',
                            '7' => 'Sábados'
                        ];
                        $periodo_texto .= ' - ' . $dias_semana_nomes[$dia_semana];
                    }

                    if ($results) {
                        // Resumo geral
                        $total_pedidos = array_sum(array_column($results, 'total_pedidos'));
                        $total_faturamento_bruto = array_sum(array_column($results, 'faturamento_bruto'));
                        $ticket_medio_geral = $total_pedidos > 0 ? $total_faturamento_bruto / $total_pedidos : 0;
                        
                        echo '<div class="resumo-cards">';
                        
                        echo '<div class="card-resumo card-azul">';
                        echo '<h4>Total de Pedidos</h4>';
                        echo '<p>' . $total_pedidos . '</p>';
                        echo '</div>';
                        
                        echo '<div class="card-resumo card-verde">';
                        echo '<h4>Faturamento Bruto</h4>';
                        echo '<p>R$ ' . number_format($total_faturamento_bruto, 2, ',', '.') . '</p>';
                        echo '</div>';
                        
                        echo '<div class="card-resumo card-laranja">';
                        echo '<h4>Ticket Médio Geral</h4>';
                        echo '<p>R$ ' . number_format($ticket_medio_geral, 2, ',', '.') . '</p>';
                        echo '</div>';
                        
                        echo '</div>';

                        echo '<div class="card">';
                        echo '<h3>Relatório Detalhado de Pedidos - ' . $periodo_texto . '</h3>';
                        echo '<div class="tabela-container">';
                        echo '<table class="tabela-relatorio">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th class="text-left">Data</th>';
                        echo '<th class="text-left">Dia da Semana</th>';
                        echo '<th class="text-center">Total Pedidos</th>';
                        echo '<th class="text-right">Faturamento Bruto</th>';
                        echo '<th class="text-right">Ticket Médio</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        foreach ($results as $index => $row) {
                            // Efeito zebra - linhas alternadas
                            $linha_classe = ($index % 2 == 0) ? 'linha-par' : 'linha-impar';
                            echo '<tr class="' . $linha_classe . '">';
                            echo '<td>' . date('d/m/Y', strtotime($row['data'])) . '</td>';
                            echo '<td>' . $row['dia_semana'] . '</td>';
                            echo '<td class="text-center">' . $row['total_pedidos'] . '</td>';
                            echo '<td class="text-right">R$ ' . number_format($row['faturamento_bruto'], 2, ',', '.') . '</td>';
                            echo '<td class="text-right">R$ ' . number_format($row['ticket_medio'], 2, ',', '.') . '</td>';
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
</body>
</html>