<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); 
    exit();
}

include_once '../config/database.php';

// Definir mês e ano atual ou do parâmetro
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('m');
$ano = isset($_GET['ano']) ? $_GET['ano'] : date('Y');

// Buscar despesas do mês selecionado
$sql = "SELECT 
    d.id_despesa,
    d.valor,
    d.status_pagamento,
    d.data_despesa,
    d.descricao,
    cd.nome_categoria,
    dp.forma_pagamento,
    cd.id_categoria
FROM despesas d
LEFT JOIN categorias_despesa cd ON d.fk_id_categoria = cd.id_categoria
LEFT JOIN despesas_pagamento dp ON d.fk_id_forma_pagamento = dp.Id_forma_pagamento
WHERE MONTH(d.data_despesa) = ? AND YEAR(d.data_despesa) = ?
ORDER BY d.data_despesa ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mes, $ano]);
    $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao buscar despesas: ' . $e->getMessage());
    $despesas = [];
}

// Função para formatar valor
function formatarValor($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para formatar data
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

// Calcular total de despesas
$total_despesas = array_reduce($despesas, function($carry, $item) {
    return $carry + $item['valor'];
}, 0);

// Calcular totais para os cards
$total_pendente = array_reduce($despesas, function($carry, $item) {
    return $carry + ($item['status_pagamento'] == 0 ? $item['valor'] : 0);
}, 0);

$total_pago = array_reduce($despesas, function($carry, $item) {
    return $carry + ($item['status_pagamento'] == 1 ? $item['valor'] : 0);
}, 0);

// Nome dos meses em português
$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
    4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
    10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Despesas - Lunch&Fit</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="../assets/css/despesas.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    
</head> 

<style>
        .main-content {
            padding: 20px;
        }

        .resumo-cards {
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .despesas-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .month-navigation {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            gap: 20px;
        }

        .month-navigation h2 {
            margin: 0;
            color: #333;
        }

        .nav-arrow {
            color: #666;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .nav-arrow:hover {
            background: #f5f5f5;
        }

        .table-container {
            overflow-x: auto;
        }

        .despesas-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .despesas-table th,
        .despesas-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .despesas-table th {
            background: #f9f9f9;
            font-weight: 600;
            color: #666;
        }

        .categoria-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            background: #e3f2fd;
            border-radius: 4px;
            color: #1976d2;
        }

        .valor {
            font-weight: 600;
            color: #d32f2f;
        }

        .acoes {
            display: flex;
            gap: 8px;
        }

        .acoes button {
            background: none;
            border: none;
            padding: 5px;
            cursor: pointer;
            color: #666;
            border-radius: 4px;
        }

        .acoes button:hover {
            background: #f5f5f5;
        }

        .pagination {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 20px;
            padding: 10px 0;
        }

        .per-page {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .per-page select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .page-controls {
            display: flex;
            gap: 5px;
        }

        .page-controls button {
            background: none;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            color: #666;
            border-radius: 4px;
        }

        .page-controls button:disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .page-controls button:not(:disabled):hover {
            background: #f5f5f5;
        }

        .total-label {
            text-align: right;
            font-weight: 600;
        }

        .total-value {
            font-weight: 600;
            color: #d32f2f;
        }

        /* Efeito Zebra na Tabela */
        .despesas-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .despesas-table tbody tr:hover {
            background-color: #f1f3f5;
        }

        /* Estilos para os Cards */
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .card.pendentes {
            border-left: 4px solid #ff6b6b;
        }

        .card.pagas {
            border-left: 4px solid #51cf66;
        }

        .card.total {
            border-left: 4px solid #339af0;
        }

        .card-content h3 {
            color: #495057;
            font-size: 0.9rem;
            margin: 0 0 8px 0;
        }

        .card-content .valor {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: #212529;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .pendentes .card-icon {
            background: #fff5f5;
            color: #ff6b6b;
        }

        .pagas .card-icon {
            background: #ebfbee;
            color: #51cf66;
        }

        .total .card-icon {
            background: #e7f5ff;
            color: #339af0;
        }

        @media (max-width: 768px) {
            .resumo-cards {
                grid-template-columns: 1fr;
            }

            .card {
                margin-bottom: 15px;
            }

            .despesas-table {
                font-size: 14px;
            }

            .acoes {
                flex-wrap: wrap;
            }

            .pagination {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Adicionar antes dos outros estilos */
        .btn-status {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            min-width: 90px;
        }

        .btn-status.pago {
            background-color: #4CAF50;
            color: white;
        }

        .btn-status.pendente {
            background-color: #FFA726;
            color: white;
        }

        .btn-status:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-status.vencida {
            background-color: #dc3545;
            color: white;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-status.vencida i {
            font-size: 14px;
        }

        .btn-status.vencida:hover {
            background-color: #c82333;
        }

        .btn-status.vence-hoje {
            background-color: #008eff;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-status.vence-hoje i {
            font-size: 14px;
            color: #fff;
        }

        .btn-status.vence-hoje:hover {
            background-color:rgb(0, 119, 216);
        }

        /* Adicionar animação de pulsação para despesas vencidas */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .btn-status.vencida {
            animation: pulse 1.5s infinite;
        }

        /* Adicionar animação suave para despesas que vencem hoje */
        @keyframes highlight {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.02); opacity: 0.9; }
            100% { transform: scale(1); opacity: 1; }
        }

        .btn-status.vence-hoje {
            animation: highlight 2s infinite;
        }
    </style>
<body>

    <?php include_once '../includes/menu.php'; ?>

    <main class="main-content">
        <!-- Cards de Resumo -->
        <div class="resumo-cards">
            <div class="card pendentes">
                <div class="card-content">
                    <h3>Despesas pendentes</h3>
                    <p class="valor"><?= formatarValor($total_pendente) ?></p>
                </div>
                <div class="card-icon">
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
            <div class="card pagas">
                <div class="card-content">
                    <h3>Despesas pagas</h3>
                    <p class="valor"><?= formatarValor($total_pago) ?></p>
                </div>
                <div class="card-icon">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
            <div class="card total">
                <div class="card-content">
                    <h3>Total</h3>
                    <p class="valor"><?= formatarValor($total_despesas) ?></p>
                </div>
                <div class="card-icon">
                    <i class="fas fa-balance-scale"></i>
                </div>
            </div>
        </div>

        <div class="despesas-container">
            <!-- Cabeçalho com navegação do mês -->
            <div class="month-navigation">
                <a href="?mes=<?= $mes-1 ?>&ano=<?= $mes == 1 ? $ano-1 : $ano ?>" class="nav-arrow">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <h2><?= $meses[intval($mes)] ?> <?= $ano ?></h2>
                <a href="?mes=<?= $mes+1 ?>&ano=<?= $mes == 12 ? $ano+1 : $ano ?>" class="nav-arrow">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>

            <!-- Tabela de Despesas -->
            <div class="table-container">
                <table class="despesas-table">
                    <thead>
                        <tr>
                            <th>Situação</th>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Conta</th>
                            <th>Valor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($despesas as $despesa): ?>
                            <tr>
                                <td>
                                    <?php 
                                        $data_despesa = strtotime($despesa['data_despesa']);
                                        $hoje = strtotime(date('Y-m-d'));
                                        $vencida = ($data_despesa < $hoje && $despesa['status_pagamento'] == 0);
                                        $vence_hoje = ($data_despesa == $hoje && $despesa['status_pagamento'] == 0);
                                    ?>
                                    <button 
                                        class="btn-status <?= $despesa['status_pagamento'] == 1 ? 'pago' : ($vencida ? 'vencida' : ($vence_hoje ? 'vence-hoje' : 'pendente')) ?>"
                                        data-id="<?= $despesa['id_despesa'] ?>"
                                        data-status="<?= $despesa['status_pagamento'] ?>"
                                    >
                                        <?php if ($vencida): ?>
                                            <i class="fas fa-exclamation-triangle"></i>
                                        <?php elseif ($vence_hoje): ?>
                                            <i class="fas fa-clock"></i>
                                        <?php endif; ?>
                                        <?= $despesa['status_pagamento'] == 1 ? 'Pago' : 'Pendente' ?>
                                    </button>
                                </td>
                                <td><?= formatarData($despesa['data_despesa']) ?></td>
                                <td><?= htmlspecialchars($despesa['descricao']) ?></td>
                                <td>
                                    <div class="categoria-badge">
                                        <i class="fas fa-tag"></i>
                                        <?= htmlspecialchars($despesa['nome_categoria']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($despesa['forma_pagamento']) ?></td>
                                <td class="valor"><?= formatarValor($despesa['valor']) ?></td>
                                <td class="acoes">
                                    <button class="btn-edit" data-id="<?= $despesa['id_despesa'] ?>">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button class="btn-duplicate" data-id="<?= $despesa['id_despesa'] ?>">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button class="btn-delete" data-id="<?= $despesa['id_despesa'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn-more">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="total-label">Total do Mês:</td>
                            <td colspan="2" class="total-value"><?= formatarValor($total_despesas) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Paginação -->
            <div class="pagination">
                <div class="per-page">
                    <span>Linhas por página:</span>
                    <select id="linhasPorPagina">
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                </div>
                <div class="page-info">
                    1-1 de 1
                </div>
                <div class="page-controls">
                    <button disabled><i class="fas fa-angle-double-left"></i></button>
                    <button disabled><i class="fas fa-angle-left"></i></button>
                    <button disabled><i class="fas fa-angle-right"></i></button>
                    <button disabled><i class="fas fa-angle-double-right"></i></button>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Função para atualizar os totais
        function atualizarTotais() {
            let totalPendente = 0;
            let totalPago = 0;

            document.querySelectorAll('.despesas-table tbody tr').forEach(tr => {
                const valor = parseFloat(tr.querySelector('.valor').textContent
                    .replace('R$ ', '')
                    .replace('.', '')
                    .replace(',', '.')
                );
                const statusBtn = tr.querySelector('.btn-status');
                const isPago = statusBtn.classList.contains('pago');

                if (isPago) {
                    totalPago += valor;
                } else {
                    totalPendente += valor;
                }
            });

            const totalGeral = totalPago + totalPendente;

            // Atualiza os cards
            document.querySelector('.card.pendentes .valor').textContent = 
                `R$ ${totalPendente.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            document.querySelector('.card.pagas .valor').textContent = 
                `R$ ${totalPago.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            document.querySelector('.card.total .valor').textContent = 
                `R$ ${totalGeral.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

            // Atualiza o total no rodapé da tabela
            document.querySelector('.total-value').textContent = 
                `R$ ${totalGeral.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }

        // Função para verificar se a despesa está vencida
        function verificarVencimento(dataDespesa, statusPagamento) {
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);
            const data = new Date(dataDespesa);
            return data < hoje && statusPagamento === 0;
        }

        // Handler para botões de status
        document.querySelectorAll('.btn-status').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.dataset.id;
                const currentStatus = parseInt(this.dataset.status);
                const newStatus = currentStatus === 1 ? 0 : 1;
                const dataDespesa = this.closest('tr').querySelector('td:nth-child(2)').textContent.split('/').reverse().join('-');
                
                // Comparação precisa de datas
                const dataVencimento = new Date(dataDespesa);
                const hoje = new Date();
                
                // Zerar horas, minutos, segundos e milissegundos para ambas as datas
                dataVencimento.setHours(0, 0, 0, 0);
                hoje.setHours(0, 0, 0, 0);
                
                // Converter para timestamp para comparação precisa
                const timestampVencimento = dataVencimento.getTime();
                const timestampHoje = hoje.getTime();
                
                // Verificar se está vencida ou vence hoje
                const vencida = timestampVencimento < timestampHoje;
                const venceHoje = timestampVencimento === timestampHoje;

                try {
                    const formData = new FormData();
                    formData.append('id_despesa', id);
                    formData.append('status', newStatus);

                    const response = await fetch('../actions/dashboard/atualizar_status_despesa.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        // Remove classes existentes
                        this.classList.remove('pago', 'pendente', 'vencida', 'vence-hoje');
                        
                        // Atualiza o status no dataset
                        this.dataset.status = newStatus;

                        // Atualiza classes e conteúdo
                        if (newStatus === 1) {
                            // Se foi marcado como pago, sempre mostra o botão verde normal
                            this.classList.add('pago');
                            this.innerHTML = 'Pago';
                        } else {
                            // Se está pendente, verifica o estado (vencida, vence hoje ou pendente normal)
                            if (vencida) {
                                this.classList.add('vencida');
                                this.innerHTML = '<i class="fas fa-exclamation-triangle"></i>Pendente';
                            } else if (venceHoje) {
                                this.classList.add('vence-hoje');
                                this.innerHTML = '<i class="fas fa-clock"></i>Pendente';
                            } else {
                                this.classList.add('pendente');
                                this.innerHTML = 'Pendente';
                            }
                        }

                        // Atualiza os totais
                        atualizarTotais();
                    } else {
                        alert(data.message);
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    alert('Erro ao atualizar status. Por favor, tente novamente.');
                }
            });
        });

        // Atualizar linhas por página
        document.getElementById('linhasPorPagina').addEventListener('change', function() {
            // Implementar lógica de paginação
        });

        // Handlers para botões de ação
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                // Implementar edição
            });
        });

        document.querySelectorAll('.btn-duplicate').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                // Implementar duplicação
            });
        });

        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.dataset.id;
                if (confirm('Tem certeza que deseja excluir esta despesa?')) {
                    try {
                        const formData = new FormData();
                        formData.append('id_despesa', id);

                        const response = await fetch('../actions/dashboard/excluir_despesa.php', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.status === 'success') {
                            alert(data.message);
                            // Remove a linha da tabela
                            this.closest('tr').remove();
                            // Atualiza o total
                            window.location.reload();
                        } else {
                            alert(data.message);
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        alert('Erro ao excluir despesa. Por favor, tente novamente.');
                    }
                }
            });
        });
    </script>
</body>
</html>

