<?php
session_start();
include_once '../config/database.php';
include_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Inicializar variáveis de filtro
$empresa_id = $_GET['empresa_id'] ?? null;
$mes_atual = $_GET['mes'] ?? date('Y-m');
$primeiro_dia = date('Y-m-01', strtotime($mes_atual));
$ultimo_dia = date('Y-m-t', strtotime($mes_atual));

// Buscar empresas para o filtro
$sql_empresas = "SELECT id_empresa, nome_empresa FROM empresas ORDER BY nome_empresa";
$stmt = $pdo->prepare($sql_empresas);
$stmt->execute();
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query principal
$sql = "SELECT 
    e.nome_empresa,
    e.id_empresa,
    e.status,
    e.cnpj,
    e.telefone,
    e.email,
    COUNT(DISTINCT p.id_pedido) as total_pedidos,
    COUNT(DISTINCT c.id_cliente) as total_funcionarios,
    SUM(p.sub_total + p.taxa_entrega) as valor_total,
    SUM(CASE WHEN p.status_pagamento = 1 THEN (p.sub_total + p.taxa_entrega) ELSE 0 END) as valor_pago,
    SUM(CASE WHEN p.status_pagamento = 0 THEN (p.sub_total + p.taxa_entrega) ELSE 0 END) as valor_pendente,
    MAX(p.data_pedido) as ultimo_pedido
FROM empresas e
LEFT JOIN clientes c ON e.id_empresa = c.fk_empresa_id
LEFT JOIN pedidos p ON c.id_cliente = p.fk_cliente_id
    AND p.data_pedido BETWEEN :data_inicio AND :data_fim
WHERE 1=1 ";

if ($empresa_id) {
    $sql .= "AND e.id_empresa = :empresa_id ";
}

$sql .= "GROUP BY e.id_empresa, e.nome_empresa
ORDER BY total_pedidos DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':data_inicio', $primeiro_dia);
$stmt->bindParam(':data_fim', $ultimo_dia);
if ($empresa_id) {
    $stmt->bindParam(':empresa_id', $empresa_id);
}
$stmt->execute();
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totais
$total_geral = 0;
$total_pedidos = 0;
$total_funcionarios = 0;

foreach ($resultados as $r) {
    $total_geral += floatval($r['valor_total'] ?? 0);
    $total_pedidos += intval($r['total_pedidos'] ?? 0);
    $total_funcionarios += intval($r['total_funcionarios'] ?? 0);
}

// Calcular ticket médio geral
$ticket_medio_geral = $total_pedidos > 0 ? $total_geral / $total_pedidos : 0;

// Query para pedidos pendentes apenas de clientes vinculados a empresas que existem
$sql_pendentes = "SELECT 
    COUNT(p.id_pedido) as total_pendentes,
    COALESCE(SUM(CASE WHEN p.status_pagamento = 0 THEN (p.sub_total + p.taxa_entrega) ELSE 0 END), 0) as valor_pendente
FROM pedidos p
INNER JOIN clientes c ON p.fk_cliente_id = c.id_cliente
INNER JOIN empresas e ON c.fk_empresa_id = e.id_empresa
WHERE p.status_pagamento = 0
    AND c.fk_empresa_id IS NOT NULL";

if ($empresa_id) {
    $sql_pendentes .= " AND c.fk_empresa_id = :empresa_id";
}

if ($primeiro_dia && $ultimo_dia) {
    $sql_pendentes .= " AND p.data_pedido BETWEEN :data_inicio AND :data_fim";
}

$stmt = $pdo->prepare($sql_pendentes);

if ($empresa_id) {
    $stmt->bindParam(':empresa_id', $empresa_id);
}

if ($primeiro_dia && $ultimo_dia) {
    $stmt->bindParam(':data_inicio', $primeiro_dia);
    $stmt->bindParam(':data_fim', $ultimo_dia);
}

$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_pendentes = $result['total_pendentes'] ?? 0;
$valor_pendente = $result['valor_pendente'] ?? 0;

// Add this query before the cards section to get paid orders info
$sql_pagos = "SELECT 
    COUNT(p.id_pedido) as total_pagos,
    COALESCE(SUM(CASE WHEN p.status_pagamento = 1 THEN (p.sub_total + p.taxa_entrega) ELSE 0 END), 0) as valor_pago
FROM pedidos p
INNER JOIN clientes c ON p.fk_cliente_id = c.id_cliente
INNER JOIN empresas e ON c.fk_empresa_id = e.id_empresa
WHERE p.status_pagamento = 1
    AND c.fk_empresa_id IS NOT NULL";

if ($empresa_id) {
    $sql_pagos .= " AND c.fk_empresa_id = :empresa_id";
}

if ($primeiro_dia && $ultimo_dia) {
    $sql_pagos .= " AND p.data_pedido BETWEEN :data_inicio AND :data_fim";
}

$stmt = $pdo->prepare($sql_pagos);

if ($empresa_id) {
    $stmt->bindParam(':empresa_id', $empresa_id);
}

if ($primeiro_dia && $ultimo_dia) {
    $stmt->bindParam(':data_inicio', $primeiro_dia);
    $stmt->bindParam(':data_fim', $ultimo_dia);
}

$stmt->execute();
$result_pagos = $stmt->fetch(PDO::FETCH_ASSOC);
$total_pagos = $result_pagos['total_pagos'] ?? 0;
$valor_pago = $result_pagos['valor_pago'] ?? 0;

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Empresas - Lunch&Fit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../assets/css/empresas_relatorios.css">
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>


    <div class="main-content">
            <div class="page-header">
                <div class="header-wrapper">
                    <div class="header-title">
                        <h1>Relatório de Empresas</h1>
                    </div>
                    <div class="header-actions">
                        <button onclick="abrirModalCadastro()" class="btn-endereco">
                            <i class="fas fa-plus"></i>
                            Nova Empresa
                        </button>
                        <button onclick="abrirModalClientePJ()" class="btn-endereco">
                            <i class="fas fa-user-plus"></i>
                            Novo Cliente PJ
                        </button>
                    </div>
                </div>
            </div>
        <div>

        <!-- Filtros Estilizados -->
        <div class="filtros-container">
            <form id="form-filtros" class="filtros-grid">
                <div class="mes-selector">
                    <button type="button" class="btn-mes" onclick="mesAnterior()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <input type="month" id="mes-filtro" name="mes" 
                           value="<?= $mes_atual ?>" onchange="mudarMes(this.value)">
                    <button type="button" class="btn-mes" onclick="mesProximo()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <div class="filtro-item">
                    <i class="fas fa-building filtro-icon"></i>
                    <select id="empresa_id" name="empresa_id" class="filtro-input">
                        <option value="">Todas</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?= $empresa['id_empresa'] ?>" 
                                <?= $empresa_id == $empresa['id_empresa'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($empresa['nome_empresa']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-filtrar">
                    <i class="fas fa-search"></i>
                    Filtrar
                </button>
            </form>
        </div>

        <!-- Cards de Resumo -->
        <div class="cards-resumo">
            <div class="resumo-card card-faturamento">
                <div class="card-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="card-info">
                    <span class="card-label">Total Faturado</span>
                    <span class="card-value"><?= formatMoney($total_geral) ?></span>
                </div>
            </div>

            <!-- New card for paid payments -->
            <div class="resumo-card card-pagos">
                <div class="card-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-info">
                    <span class="card-label">Pagamentos Realizados</span>
                    <span class="card-value"><?= formatMoney($valor_pago) ?></span>
                    <small style="color: #64748b;"><?= $total_pagos ?> pedidos pagos</small>
                </div>
            </div>

            <div class="metric-card danger">
                <div class="metric-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="metric-info">
                    <h3>Pagamentos Pendentes</h3>
                    <p class="metric-value"><?= formatMoney($valor_pendente) ?></p>
                    <p class="metric-detail">
                        <span class="badge-pendente"><?= $total_pendentes ?></span>
                        pedidos aguardando pagamento
                    </p>
                </div>
            </div>

            <div class="resumo-card card-pedidos">
                <div class="card-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="card-info">
                    <span class="card-label">Total Pedidos</span>
                    <span class="card-value"><?= $total_pedidos ?></span>
                </div>
            </div>
        </div>

        <!-- Cards de Empresas (Mobile) -->
        <?php foreach ($resultados as $r): ?>
            <div class="empresa-card">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="empresa-nome"><?= htmlspecialchars($r['nome_empresa'] ?? '-') ?></h3>
                        <span class="status-badge <?= $r['status'] ? 'active' : 'inactive' ?>">
                            <?= $r['status'] ? 'Ativa' : 'Inativa' ?>
                        </span>
                    </div>
                    
                    <div class="card-actions">
                        <button onclick="editarEmpresa(<?= $r['id_empresa'] ?>)" class="btn-icon">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="toggleStatus(<?= $r['id_empresa'] ?>)" class="btn-icon">
                            <i class="fas fa-power-off"></i>
                        </button>
                    </div>
                </div>
                
                <div class="empresa-stats">
                    <div class="stat-item clickable" onclick="verPedidos(<?= $r['id_empresa'] ?>)">
                        <span class="stat-label">Pedidos</span>
                        <span class="stat-value">
                            <i class="fas fa-shopping-cart"></i>
                            <?= intval($r['total_pedidos'] ?? 0) ?>
                        </span>
                    </div>
                    
                    <div class="stat-item clickable" onclick="verFuncionarios(<?= $r['id_empresa'] ?>)">
                        <span class="stat-label">Funcionários</span>
                        <span class="stat-value">
                            <i class="fas fa-users"></i>
                            <?= intval($r['total_funcionarios'] ?? 0) ?>
                        </span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-label">Valor Total</span>
                        <span class="stat-value stat-highlight"><?= formatMoney($r['valor_total']) ?></span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-label">Pagos</span>
                        <span class="stat-value" style="color: #4caf50;"><?= formatMoney($r['valor_pago']) ?></span>
                    </div>

                    <div class="stat-item">
                        <span class="stat-label">Pendentes</span>
                        <span class="stat-value" style="color: <?= $r['valor_pendente'] > 0 ? '#ff9800' : '#9e9e9e' ?>">
                            <?= formatMoney($r['valor_pendente']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="ultimo-pedido">
                    <i class="fas fa-clock"></i>
                    <span>Último pedido: <?= $r['ultimo_pedido'] ? formatDate($r['ultimo_pedido']) : '-' ?></span>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Layout Desktop (Tabela - mantida a original) -->
        <div class="desktop-cards-container">
            <?php foreach ($resultados as $r): ?>
                <div class="desktop-empresa-card">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="desktop-empresa-nome"><?= htmlspecialchars($r['nome_empresa'] ?? '-') ?></h3>
                            <span class="status-badge <?= $r['status'] ? 'active' : 'inactive' ?>">
                                <?= $r['status'] ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </div>
                        
                        <div class="card-actions">
                            <button onclick="editarEmpresa(<?= $r['id_empresa'] ?>)" class="btn-icon">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="toggleStatus(<?= $r['id_empresa'] ?>)" class="btn-icon">
                                <i class="fas fa-power-off"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Grid de estatísticas -->
                    <div class="desktop-card-stats">
                        <div class="desktop-stat-group">
                            <div class="desktop-stat-item clickable" onclick="verPedidos(<?= $r['id_empresa'] ?>)">
                                <span class="desktop-stat-label">Pedidos</span>
                                <span class="desktop-stat-value">
                                    <i class="fas fa-shopping-cart"></i>
                                    <?= intval($r['total_pedidos'] ?? 0) ?>
                                </span>
                            </div>
                            
                            <div class="desktop-stat-item clickable" onclick="verFuncionarios(<?= $r['id_empresa'] ?>)">
                                <span class="desktop-stat-label">Funcionários</span>
                                <span class="desktop-stat-value">
                                    <i class="fas fa-users"></i>
                                    <?= intval($r['total_funcionarios'] ?? 0) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="desktop-stat-group">
                            <div class="desktop-stat-item">
                                <span class="desktop-stat-label">Valor Total</span>
                                <span class="desktop-stat-value highlight"><?= formatMoney($r['valor_total']) ?></span>
                            </div>
                            
                            <div class="desktop-stat-item">
                                <span class="desktop-stat-label">Pagos</span>
                                <span class="desktop-stat-value" style="color: #4caf50;">
                                    <i class="fas fa-check-circle"></i>
                                    <?= formatMoney($r['valor_pago']) ?>
                                </span>
                            </div>

                            <div class="desktop-stat-item">
                                <span class="desktop-stat-label">Pendentes</span>
                                <span class="desktop-stat-value" style="color: <?= $r['valor_pendente'] > 0 ? '#ff9800' : '#9e9e9e' ?>">
                                    <i class="fas fa-clock"></i>
                                    <?= formatMoney($r['valor_pendente']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="desktop-ultimo-pedido">
                        <i class="fas fa-clock"></i>
                        <span>Último pedido: <?= $r['ultimo_pedido'] ? formatDate($r['ultimo_pedido']) : '-' ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
 
    <div id="modalCadastro" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Cadastrar Nova Empresa</h2>
            <form id="formCadastroEmpresa">
                <div class="form-group">
                    <label for="nome">Nome da Empresa*</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone">
                </div>
                <div class="form-group">
                    <label for="cnpj">CNPJ</label>
                    <input type="text" id="cnpj" name="cnpj">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save">Salvar</button>
                    <button type="button" class="btn-cancel" onclick="fecharModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalClientePJ" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cadastrar Cliente PJ</h2>
                <button class="close-modal" onclick="fecharModalClientePJ()">&times;</button>
            </div>
            <form id="formClientePJ">
                <div class="form-group">
                    <label for="nome">Nome*</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                
                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" class="phone-mask">
                </div>
                
                <div class="form-group">
                    <label for="empresa">Empresa*</label>
                    <select id="empresa" name="empresa" required>
                        <option value="">Selecione uma empresa</option>
                        <?php
                        $sql_empresas = "SELECT id_empresa, nome_empresa FROM empresas WHERE status = 1 ORDER BY nome_empresa";
                        $stmt = $pdo->prepare($sql_empresas);
                        $stmt->execute();
                        while($empresa = $stmt->fetch()) {
                            echo "<option value='{$empresa['id_empresa']}'>{$empresa['nome_empresa']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" onclick="fecharModalClientePJ()">Cancelar</button>
                    <button type="submit" class="btn-salvar">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="../assets/js/empresas.js"></script>
    <script>
        // Inicializa os date pickers
        flatpickr(".date-picker", {
            locale: "pt",
            dateFormat: "Y-m-d",
            allowInput: true
        });
    </script>
    <script>
    function toggleStatus(empresaId) {
        if (confirm('Deseja alterar o status desta empresa?')) {
            fetch(`../actions/toggle_empresa_status.php?id=${empresaId}`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Erro ao alterar status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao alterar status da empresa');
            });
        }
    }

    function editarEmpresa(empresaId) {
        // Redirecionar para a página de edição ou abrir modal
        window.location.href = `editar_empresa.php?id=${empresaId}`;
    }

    function verFuncionarios(empresaId) {
        // Redirecionar para a página correta de funcionários da empresa
        window.location.href = `empresas_funcionarios.php?empresa=${empresaId}`;
    }

    function abrirModalCadastro() {
        document.getElementById('modalCadastro').style.display = 'block';
    }

    function fecharModal() {
        document.getElementById('modalCadastro').style.display = 'none';
    }

    // Fechar modal quando clicar fora dele
    window.onclick = function(event) {
        if (event.target == document.getElementById('modalCadastro')) {
            fecharModal();
        }
    }

    document.getElementById('formCadastroEmpresa').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('nome', document.getElementById('nome').value);
        formData.append('telefone', document.getElementById('telefone').value);
        formData.append('cnpj', document.getElementById('cnpj').value);
        formData.append('email', document.getElementById('email').value);
        
        fetch('../actions/cadastrar_empresa.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Empresa cadastrada com sucesso!');
                fecharModal();
                location.reload();
            } else {
                alert('Erro ao cadastrar empresa: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao cadastrar empresa');
        });
    });

    // Adicionar máscaras aos campos
    $(document).ready(function() {
        $('#telefone').mask('(00) 00000-0000');
        $('#cnpj').mask('00.000.000/0000-00');
    });

    function abrirModalClientePJ() {
        document.getElementById('modalClientePJ').classList.add('active');
        $('.phone-mask').mask('(00) 00000-0000');
    }

    function fecharModalClientePJ() {
        document.getElementById('modalClientePJ').classList.remove('active');
        document.getElementById('formClientePJ').reset();
    }

    $('#formClientePJ').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '../actions/empresas_relatorios/cadastrar_cliente_pj.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Cliente PJ cadastrado com sucesso!');
                    fecharModalClientePJ();
                    window.location.reload();
                } else {
                    alert('Erro: ' + response.message);
                }
            },
            error: function() {
                alert('Erro ao processar a requisição');
            }
        });
    });

    function verPedidos(empresaId) {
        // Redirecionar para a página de pedidos da empresa
        window.location.href = `empresas_pedidos.php?empresa=${empresaId}`;
    }

    function mudarMes(mes) {
        const url = new URL(window.location.href);
        url.searchParams.set('mes', mes);
        window.location.href = url.toString();
    }

    function mesAnterior() {
        const input = document.getElementById('mes-filtro');
        const data = new Date(input.value + '-01');
        data.setMonth(data.getMonth() - 1);
        const novoMes = data.toISOString().slice(0, 7);
        mudarMes(novoMes);
    }

    function mesProximo() {
        const input = document.getElementById('mes-filtro');
        const data = new Date(input.value + '-01');
        data.setMonth(data.getMonth() + 1);
        const novoMes = data.toISOString().slice(0, 7);
        mudarMes(novoMes);
    }
    </script>
</body>
</html> 