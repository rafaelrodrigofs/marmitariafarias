<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    error_log('Tentativa de acesso não autorizado ao fechamento');
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

// Período selecionado
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'dia';
$data_especifica = isset($_GET['data']) ? $_GET['data'] : null;
$data_inicio = null;
$data_fim = null;

// Cálculo das datas baseado no período
if ($data_especifica) {
    $data_inicio = $data_especifica;
    $data_fim = $data_especifica;
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
        case 'mes_passado':
            $data_inicio = date('Y-m-d', strtotime('first day of last month'));
            $data_fim = date('Y-m-d', strtotime('last day of last month'));
            break;
        case 'ano':
            $data_inicio = '2025-01-01';
            $data_fim = '2025-12-31';
            break;
    }
}



// Buscar fechamento manual se existir
try {
    $sql_fechamento = "SELECT * FROM fechamento WHERE data = ?";
    $stmt = $pdo->prepare($sql_fechamento);
    $stmt->execute([$data_inicio]);
    $fechamento_manual = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao buscar fechamento manual: ' . $e->getMessage());
    $fechamento_manual = null;
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
    <title>Fechamento - Lunch&Fit</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Manifesto PWA -->
    <link rel="manifest" href="../manifest.json">

    <style>
        .fechamento-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .fechamento-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .fechamento-title {
            font-size: 2rem;
            color: #333;
            margin: 0;
        }

        .period-selector {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .period-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            background: #f0f0f0;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .period-btn.active {
            background: #4CAF50;
            color: white;
        }

        .period-btn:hover {
            background: #45a049;
            color: white;
        }

        .period-btn.mes-passado {
            background: #9C27B0;
            color: white;
        }

        .period-btn.mes-passado:hover,
        .period-btn.mes-passado.active {
            background: #7B1FA2;
        }

        .fechamento-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .fechamento-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        .card-title {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .metric-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            color: #2e7d32;
        }

        .metric-label {
            color: #666;
            font-weight: 500;
        }

        .metric-value {
            font-weight: 600;
            color: #333;
        }

        .total-row {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }



        .btn-novo-fechamento {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-novo-fechamento:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .btn-voltar {
            background: #666;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-voltar:hover {
            background: #555;
        }

        .fechamento-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-pendente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-concluido {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        @media (max-width: 768px) {
            .fechamento-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .fechamento-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .period-selector {
                justify-content: center;
                flex-wrap: wrap;
            }

                    .fechamento-container {
            padding: 15px;
        }
    }

    /* Estilos para a tabela de fechamentos */
    .table-container {
        overflow-x: auto;
        margin-top: 15px;
    }

    .fechamento-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .fechamento-table th {
        background: #f8f9fa;
        color: #333;
        font-weight: 600;
        padding: 12px 8px;
        text-align: left;
        border-bottom: 2px solid #dee2e6;
        font-size: 13px;
    }

    .fechamento-table td {
        padding: 10px 8px;
        border-bottom: 1px solid #f1f1f1;
        color: #333;
    }

    .fechamento-table tr:hover {
        background-color: #f8f9fa;
    }

    .fechamento-table tr.current-date {
        background-color: #e3f2fd;
        font-weight: 600;
    }

    .fechamento-table tr.current-date:hover {
        background-color: #bbdefb;
    }

    .total-cell {
        font-weight: 600;
        color: #2e7d32;
        background-color: #f1f8e9;
    }

    .no-data {
        text-align: center;
        color: #666;
        font-style: italic;
        padding: 20px;
    }

    .totais-row {
        background: #f8f9fa;
        border-top: 2px solid #dee2e6;
    }

    .totais-row td {
        font-weight: 600;
        color: #2e7d32;
        background-color: #f1f8e9;
    }

    .btn-exportar {
        background: #28a745;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-exportar:hover {
        background: #218838;
        transform: translateY(-1px);
    }

            .btn-exportar i {
            font-size: 1rem;
        }

        /* Estilos para botão flutuante e modal */
        .floating-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: background-color 0.3s ease;
            z-index: 1000;
        }

        .floating-button:hover {
            background-color: #45a049;
            transform: scale(1.1);
        }

        .modal {
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 95vh;
            overflow-y: auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-button:hover {
            color: #000;
        }

        .valor-input {
            font-size: 18px;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            text-align: left;
            outline: none;
            padding: 8px;
        }

        .valor-input:focus {
            border-color: #4CAF50;
        }

        .date-options button {
            margin-right: 5px;
            padding: 4px 8px;
            background-color: #60bb51;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: white;
            font-size: 12px;
        }

        .date-options button:hover {
            background-color: #45a049;
        }

        button[type="submit"] {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            margin-top: 15px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            width: 100%;
            border-radius: 4px;
        }

        button[type="submit"]:hover {
            background-color: #45a049;
        }

        @media (max-width: 768px) {
        .fechamento-table {
            font-size: 12px;
        }

        .fechamento-table th,
        .fechamento-table td {
            padding: 8px 4px;
        }

        .table-container {
            margin-top: 10px;
        }

        .btn-exportar {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
    }
    </style>
</head>

<body class="dashboard-page">
    <?php include_once '../includes/menu.php'; ?>
    
    <div class="main-content">
        <div class="fechamento-container">
            <div class="fechamento-header">
                <div>
                    <h1 class="fechamento-title">
                        <i class="fas fa-cash-register"></i>
                        Fechamento do Caixa
                    </h1>
                    <p style="color: #666; margin: 5px 0 0 0;">
                        <?php
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
                            case 'mes_passado':
                                echo date('F/Y', strtotime('last month'));
                                break;
                            case 'ano':
                                echo '2025';
                                break;
                        }
                        ?>
                    </p>
                </div>
                
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div class="period-selector">
                        <button class="period-btn <?php echo ($periodo == 'ontem' && !$data_especifica) ? 'active' : ''; ?>" onclick="window.location.href='?periodo=ontem'">Ontem</button>
                        <button class="period-btn <?php echo ($periodo == 'dia' && !$data_especifica) ? 'active' : ''; ?>" onclick="window.location.href='?periodo=dia'">Hoje</button>
                        <button class="period-btn <?php echo ($periodo == 'semana' && !$data_especifica) ? 'active' : ''; ?>" onclick="window.location.href='?periodo=semana'">Semana</button>
                        <button class="period-btn <?php echo ($periodo == 'mes' && !$data_especifica) ? 'active' : ''; ?>" onclick="window.location.href='?periodo=mes'">Mês</button>
                        <button class="period-btn <?php echo ($periodo == 'mes_passado' && !$data_especifica) ? 'active' : ''; ?> mes-passado" onclick="window.location.href='?periodo=mes_passado'">Mês Passado</button>
                    </div>
                    
                    <button class="btn-voltar" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </button>
                </div>
            </div>

            <!-- Tabela de Histórico de Fechamentos -->
            <div class="fechamento-card">
                <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <i class="fas fa-history" style="color: #9C27B0;"></i>
                        Histórico de Fechamentos
                    </div>
                    <button class="btn-exportar" onclick="exportarPlanilha()">
                        <i class="fas fa-file-excel"></i>
                        Exportar Planilha
                    </button>
                </div>
                
                <?php
                // Calcular totais por categoria para o período selecionado
                try {
                    $sql_totais = "SELECT 
                        COALESCE(SUM(dinheiro), 0) as total_dinheiro,
                        COALESCE(SUM(pix_normal), 0) as total_pix_normal,
                        COALESCE(SUM(online_pix), 0) as total_online_pix,
                        COALESCE(SUM(debito), 0) as total_debito,
                        COALESCE(SUM(credito), 0) as total_credito,
                        COALESCE(SUM(vouchers), 0) as total_vouchers,
                        COALESCE(SUM(ifood), 0) as total_ifood
                        FROM fechamento 
                        WHERE data BETWEEN ? AND ?";
                    
                    $stmt = $pdo->prepare($sql_totais);
                    $stmt->execute([$data_inicio, $data_fim]);
                    $totais_categoria = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $total_geral = $totais_categoria['total_dinheiro'] + $totais_categoria['total_pix_normal'] + 
                                 $totais_categoria['total_online_pix'] + $totais_categoria['total_debito'] + 
                                 $totais_categoria['total_credito'] + $totais_categoria['total_vouchers'] + 
                                 $totais_categoria['total_ifood'];
                } catch (PDOException $e) {
                    error_log('Erro ao calcular totais por categoria: ' . $e->getMessage());
                    $totais_categoria = [
                        'total_dinheiro' => 0,
                        'total_pix_normal' => 0,
                        'total_online_pix' => 0,
                        'total_debito' => 0,
                        'total_credito' => 0,
                        'total_vouchers' => 0,
                        'total_ifood' => 0
                    ];
                    $total_geral = 0;
                }
                
                // Verificar se há fechamentos para o período
                $tem_fechamentos = $total_geral > 0;
                ?>
                
                <div class="table-container">
                    <table class="fechamento-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Dinheiro</th>
                                <th>PIX Normal</th>
                                <th>PIX Online</th>
                                <th>Débito</th>
                                <th>Crédito</th>
                                <th>Vouchers</th>
                                <th>iFood</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Buscar histórico de fechamentos para o período selecionado
                            try {
                                $sql_historico = "SELECT * FROM fechamento WHERE data BETWEEN ? AND ? ORDER BY data DESC";
                                $stmt = $pdo->prepare($sql_historico);
                                $stmt->execute([$data_inicio, $data_fim]);
                                $historico_fechamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                

                                
                                if (!empty($historico_fechamentos)):
                                    foreach ($historico_fechamentos as $fechamento):
                                        $total = $fechamento['dinheiro'] + $fechamento['pix_normal'] + $fechamento['online_pix'] + 
                                                $fechamento['debito'] + $fechamento['credito'] + $fechamento['vouchers'] + $fechamento['ifood'];
                                        $is_current_date = $fechamento['data'] == $data_inicio;
                            ?>
                                <tr class="<?php echo $is_current_date ? 'current-date' : ''; ?>">
                                    <td><?php echo date('d/m/Y', strtotime($fechamento['data'])); ?></td>
                                    <td>R$ <?php echo formatNumber($fechamento['dinheiro']); ?></td>
                                    <td>R$ <?php echo formatNumber($fechamento['pix_normal']); ?></td>
                                    <td>R$ <?php echo formatNumber($fechamento['online_pix']); ?></td>
                                    <td>R$ <?php echo formatNumber($fechamento['debito']); ?></td>
                                    <td>R$ <?php echo formatNumber($fechamento['credito']); ?></td>
                                    <td>R$ <?php echo formatNumber($fechamento['vouchers']); ?></td>
                                    <td>R$ <?php echo formatNumber($fechamento['ifood']); ?></td>
                                    <td class="total-cell">R$ <?php echo formatNumber($total); ?></td>
                                </tr>
                            <?php 
                                    endforeach;
                                else:
                            ?>
                                <tr>
                                    <td colspan="9" class="no-data">Nenhum fechamento registrado</td>
                                </tr>
                            <?php 
                                endif;
                            } catch (PDOException $e) {
                                error_log('Erro ao buscar histórico de fechamentos: ' . $e->getMessage());
                            ?>
                                <tr>
                                    <td colspan="9" class="no-data">Erro ao carregar dados</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr class="totais-row">
                                <td><strong>TOTAIS (<?php echo ucfirst($periodo); ?>):</strong></td>
                                <td><strong>R$ <?php echo formatNumber($totais_categoria['total_dinheiro']); ?></strong></td>
                                <td><strong>R$ <?php echo formatNumber($totais_categoria['total_pix_normal']); ?></strong></td>
                                <td><strong>R$ <?php echo formatNumber($totais_categoria['total_online_pix']); ?></strong></td>
                                <td><strong>R$ <?php echo formatNumber($totais_categoria['total_debito']); ?></strong></td>
                                <td><strong>R$ <?php echo formatNumber($totais_categoria['total_credito']); ?></strong></td>
                                <td><strong>R$ <?php echo formatNumber($totais_categoria['total_vouchers']); ?></strong></td>
                                <td><strong>R$ <?php echo formatNumber($totais_categoria['total_ifood']); ?></strong></td>
                                <td class="total-cell"><strong>R$ <?php echo formatNumber($total_geral); ?></strong></td>
                            </tr>
                            <?php if (!$tem_fechamentos): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #666; font-style: italic; padding: 15px;">
                                    <i class="fas fa-info-circle"></i>
                                    Nenhum fechamento registrado para o período selecionado (<?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?>)
                                    <br>
                                    <small style="color: #999;">
                                        <a href="?periodo=mes_passado" style="color: #4CAF50; text-decoration: none;">
                                            <i class="fas fa-calendar-alt"></i> Ver mês passado
                                        </a> | 
                                        <a href="?periodo=dia" style="color: #4CAF50; text-decoration: none;">
                                            <i class="fas fa-calendar-day"></i> Ver hoje
                                        </a>
                                    </small>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão flutuante para fechamento -->
    <button class="floating-button fechamento-btn" id="fechamentoBtn">
        <i class="fas fa-cash-register"></i>
    </button>

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
                    <input type="tel" id="dinheiro" class="valor-input" placeholder="0,00" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 8px;">
                    <label for="pixNormal">PIX Normal:</label>
                    <input type="tel" id="pixNormal" class="valor-input" placeholder="0,00" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 8px;">
                    <label for="onlinePix">PIX Online:</label>
                    <input type="tel" id="onlinePix" class="valor-input" placeholder="0,00" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 8px;">
                    <label for="debito">Débito:</label>
                    <input type="tel" id="debito" class="valor-input" placeholder="0,00" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 8px;">
                    <label for="credito">Crédito:</label>
                    <input type="tel" id="credito" class="valor-input" placeholder="0,00" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 8px;">
                    <label for="vouchers">Vouchers:</label>
                    <input type="tel" id="vouchers" class="valor-input" placeholder="0,00" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="ifood">iFood:</label>
                    <input type="tel" id="ifood" class="valor-input" placeholder="0,00" required style="width: 100%;">
                </div>

                <button type="submit">Salvar Fechamento</button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        // Função para formatar valores monetários
        function formatarValor(input) {
            let value = input.value.replace(/\D/g, '');
            value = (parseInt(value) / 100).toFixed(2);
            input.value = value.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Event listeners para o modal de fechamento
        document.addEventListener('DOMContentLoaded', function() {
            const fechamentoModal = document.getElementById('fechamentoModal');
            const fechamentoBtn = document.getElementById('fechamentoBtn');
            const closeFechamento = document.getElementById('closeFechamento');

            // Abrir modal
            fechamentoBtn.onclick = function() {
                fechamentoModal.style.display = 'flex';
                document.getElementById('dinheiro').focus();
            }

            // Fechar modal
            closeFechamento.onclick = function() {
                fechamentoModal.style.display = 'none';
            }

            // Fechar modal quando clicar fora
            window.onclick = function(event) {
                if (event.target === fechamentoModal) {
                    fechamentoModal.style.display = 'none';
                }
            }

            // Botões de data
            document.getElementById('hojeFechamento').addEventListener('click', function() {
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('dataFechamento').value = today;
            });

            document.getElementById('ontemFechamento').addEventListener('click', function() {
                const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
                document.getElementById('dataFechamento').value = yesterday;
            });

            // Formatação de valores
            ['dinheiro', 'pixNormal', 'onlinePix', 'debito', 'credito', 'vouchers', 'ifood'].forEach(id => {
                document.getElementById(id).addEventListener('input', function(e) {
                    formatarValor(e.target);
                });
            });

            // Submit do formulário
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
        });

        function exportarPlanilha() {
            // Obter dados da tabela
            const table = document.querySelector('.fechamento-table');
            
            // Preparar dados para o Excel
            const data = [];
            
            // Adicionar cabeçalho
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent);
            });
            data.push(headers);
            
            // Adicionar dados
            table.querySelectorAll('tbody tr').forEach(row => {
                const rowData = [];
                row.querySelectorAll('td').forEach(td => {
                    rowData.push(td.textContent);
                });
                data.push(rowData);
            });
            
            // Adicionar linha de totais
            const totaisRow = table.querySelector('tfoot tr');
            if (totaisRow) {
                const totaisData = [];
                totaisRow.querySelectorAll('td').forEach(td => {
                    totaisData.push(td.textContent);
                });
                data.push(totaisData);
            }
            
            // Criar workbook e worksheet
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(data);
            
            // Ajustar largura das colunas
            const colWidths = [
                { wch: 12 }, // Data
                { wch: 12 }, // Dinheiro
                { wch: 12 }, // PIX Normal
                { wch: 12 }, // PIX Online
                { wch: 12 }, // Débito
                { wch: 12 }, // Crédito
                { wch: 12 }, // Vouchers
                { wch: 12 }, // iFood
                { wch: 15 }  // Total
            ];
            ws['!cols'] = colWidths;
            
            // Adicionar worksheet ao workbook
            XLSX.utils.book_append_sheet(wb, ws, "Fechamentos");
            
            // Gerar arquivo e fazer download
            const fileName = "fechamentos_<?php echo date('Y-m-d'); ?>.xlsx";
            XLSX.writeFile(wb, fileName);
        }
    </script>
</body>

</html> 