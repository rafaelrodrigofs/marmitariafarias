<?php
session_start();
include_once '../config/database.php';
include_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se o ID da empresa foi fornecido
$empresa_id = $_GET['empresa'] ?? null;
if (!$empresa_id) {
    header('Location: empresas_relatorios.php');
    exit;
}

// Adicionar logo após a verificação do empresa_id e antes da busca de informações da empresa
$mes_atual = $_GET['mes'] ?? date('Y-m');
$primeiro_dia = date('Y-m-01', strtotime($mes_atual));
$ultimo_dia = date('Y-m-t', strtotime($mes_atual));

// Busca informações da empresa
$sql_empresa = "SELECT nome_empresa FROM empresas WHERE id_empresa = :id";
$stmt = $pdo->prepare($sql_empresa);
$stmt->bindParam(':id', $empresa_id);
$stmt->execute();
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

// Busca os pedidos da empresa
$sql = "SELECT 
    p.id_pedido,
    p.num_pedido,
    p.data_pedido,
    p.sub_total,
    p.taxa_entrega,
    p.status_pagamento,
    c.id_cliente as fk_cliente_id,
    c.nome_cliente,
    c.telefone_cliente
FROM pedidos p
LEFT JOIN clientes c ON p.fk_cliente_id = c.id_cliente
WHERE c.fk_empresa_id = :empresa_id
AND DATE(p.data_pedido) BETWEEN :data_inicio AND :data_fim
ORDER BY p.data_pedido DESC, p.num_pedido ASC";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':empresa_id', $empresa_id);
$stmt->bindParam(':data_inicio', $primeiro_dia);
$stmt->bindParam(':data_fim', $ultimo_dia);
$stmt->execute();
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adicionar após a query dos pedidos
$sql_clientes = "SELECT DISTINCT 
    c.id_cliente,
    c.nome_cliente,
    COUNT(DISTINCT p.id_pedido) as total_pedidos,
    SUM(p.sub_total + p.taxa_entrega) as valor_total,
    SUM(CASE WHEN p.status_pagamento = 0 THEN p.sub_total + p.taxa_entrega ELSE 0 END) as valor_pendente
FROM clientes c
LEFT JOIN pedidos p ON c.id_cliente = p.fk_cliente_id
WHERE c.fk_empresa_id = :empresa_id
AND (p.id_pedido IS NULL OR DATE(p.data_pedido) BETWEEN :data_inicio AND :data_fim)
AND EXISTS (SELECT 1 FROM pedidos p2 WHERE p2.fk_cliente_id = c.id_cliente)
GROUP BY c.id_cliente, c.nome_cliente
ORDER BY c.nome_cliente ASC";

$stmt = $pdo->prepare($sql_clientes);
$stmt->bindParam(':empresa_id', $empresa_id);
$stmt->bindParam(':data_inicio', $primeiro_dia);
$stmt->bindParam(':data_fim', $ultimo_dia);
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adicionar após as queries existentes e antes do HTML
// Calcular totais
$total_valor = 0;
$total_pedidos = 0;
$total_pagos = 0;
$total_pendentes = 0;
$valor_pago = 0;
$valor_pendente = 0;

foreach ($pedidos as $pedido) {
    $valor_pedido = $pedido['sub_total'] + $pedido['taxa_entrega'];
    $total_valor += $valor_pedido;
    $total_pedidos++;
    
    if ($pedido['status_pagamento']) {
        $total_pagos++;
        $valor_pago += $valor_pedido;
    } else {
        $total_pendentes++;
        $valor_pendente += $valor_pedido;
    }
}

$ticket_medio = $total_pedidos > 0 ? $total_valor / $total_pedidos : 0;

// Agrupar pedidos por data
$pedidos_por_data = [];
foreach ($pedidos as $pedido) {
    $data = date('Y-m-d', strtotime($pedido['data_pedido']));
    if (!isset($pedidos_por_data[$data])) {
        $pedidos_por_data[$data] = [
            'pedidos' => [],
            'total_pedidos' => 0,
            'valor_total' => 0
        ];
    }
    $pedidos_por_data[$data]['pedidos'][] = $pedido;
    $pedidos_por_data[$data]['total_pedidos']++;
    $pedidos_por_data[$data]['valor_total'] += ($pedido['sub_total'] + $pedido['taxa_entrega']);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - <?= htmlspecialchars($empresa['nome_empresa']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/relatorios.css">
</head>
<body>
    <div class="main-container">

        <div id="modalTaxa" class="modal" style="display: none;">
            <div class="modal-content">
                <h3>Distribuir Taxa</h3>
                <p>Total de pedidos selecionados: <span id="totalPedidosTaxa">0</span></p>
                <div class="form-group">
                    <label for="valorTaxa">Valor total da taxa:</label>
                    <input type="number" id="valorTaxa" step="0.01" class="form-control">
                </div>
                <div class="form-group">
                    <label>Valor por pedido: R$ <span id="valorPorPedido">0.00</span></label>
                </div>
                <div class="modal-actions">
                    <button class="btn-cancelar" onclick="fecharModalTaxa()">Cancelar</button>
                    <button class="btn-confirmar" onclick="aplicarTaxa()">Confirmar</button>
                </div>
            </div>
        </div>
        <div id="modalEnderecoEmpresa" class="modal" style="display: none;">
            <div class="modal-content">
                <h3>Atualizar Endereço dos Pedidos</h3>
                <p>Total de pedidos selecionados: <span id="totalPedidosEndereco">0</span></p>
                
                <form id="formEnderecoEmpresa">
                    <input type="hidden" name="empresa_id" value="<?= $empresa_id ?>">
                    
                    <div class="endereco-section">
                        <div class="endereco-item">
                            <div class="form-group">
                                <label>Rua*</label>
                                <input type="text" name="nome_entrega" id="empresa_rua" required>
                            </div>
                            <div class="form-group">
                                <label>Número</label>
                                <input type="text" name="numero_entrega" id="empresa_numero">
                            </div>
                            <div class="form-group">
                                <label>Bairro*</label>
                                <select name="bairro_id" id="empresa_bairro" required>
                                    <option value="">Selecione...</option>
                                    <?php
                                    $stmt = $pdo->query("SELECT * FROM cliente_bairro ORDER BY nome_bairro");
                                    while ($bairro = $stmt->fetch()) {
                                        echo "<option value='{$bairro['id_bairro']}'>{$bairro['nome_bairro']} (R$ " . 
                                             number_format($bairro['valor_taxa'], 2, ',', '.') . ")</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-cancelar" onclick="fecharModalEnderecoEmpresa()">Cancelar</button>
                        <button type="submit" class="btn-confirmar">Atualizar Endereços</button>
                    </div>
                </form>
            </div>
        </div>
        <div id="modalDetalhes" class="modal" style="display: none;">
            <div class="modal-content">
                <!-- O conteúdo será inserido dinamicamente via JavaScript -->
            </div>
        </div>
        <div class="page-header">
            <div class="header-wrapper">
                <div class="header-title">
                    <h1>Pedidos</h1>
                    <div class="empresa-badge">
                        <i class="fas fa-building"></i>
                        <?= htmlspecialchars($empresa['nome_empresa']) ?>
                    </div>
                </div>
                <div class="header-controls">
                    <div class="mes-selector">
                        <button class="btn-mes" onclick="mesAnterior()">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <input type="month" id="mes-filtro" value="<?= $mes_atual ?>" onchange="mudarMes(this.value)">
                        <button class="btn-mes" onclick="mesProximo()">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="header-actions">
                        <button onclick="abrirModalEnderecoEmpresa()" class="btn-endereco">
                            <i class="fas fa-map-marker-alt"></i>
                            Atualizar Endereços
                        </button>
                        <a href="empresas_relatorios.php" class="btn-voltar">
                            <i class="fas fa-arrow-left"></i>
                            Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Adicionar após a div header-content e antes da tabelas-container -->
        <div class="cards-resumo">
            <!-- Card de Total de Pedidos -->
            <div class="resumo-card card-pedidos">
                <div class="card-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="card-info">
                    <span class="card-label">Total de Pedidos</span>
                    <span class="card-value"><?= $total_pedidos ?></span>
                    <span class="card-subtitle">neste período</span>
                </div>
            </div>

            <!-- Card de Ticket Médio -->
            <div class="resumo-card card-ticket">
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-info">
                    <span class="card-label">Ticket Médio</span>
                    <span class="card-value">R$ <?= number_format($ticket_medio, 2, ',', '.') ?></span>
                    <span class="card-subtitle">por pedido</span>
                </div>
            </div>

            <!-- Card de Faturamento -->
            <div class="resumo-card card-faturamento">
                <div class="card-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="card-info">
                    <span class="card-label">Faturamento Total</span>
                    <span class="card-value">R$ <?= number_format($total_valor, 2, ',', '.') ?></span>
                    <span class="card-subtitle">incluindo taxas</span>
                </div>
            </div>

            <!-- Card de Status de Pagamentos -->
            <div class="resumo-card card-status">
                <div class="card-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-info">
                    <span class="card-label">Pedidos Pagos</span>
                    <span class="card-value">R$ <?= number_format($valor_pago, 2, ',', '.') ?></span>
                    <span class="card-subtitle"><?= $total_pagos ?> pedidos</span>
                </div>
            </div>

            <!-- Novo Card de Pedidos Pendentes -->
            <div class="resumo-card card-pendentes">
                <div class="card-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-info">
                    <span class="card-label">Pedidos Pendentes</span>
                    <span class="card-value">R$ <?= number_format($valor_pendente, 2, ',', '.') ?></span>
                    <span class="card-subtitle"><?= $total_pendentes ?> pedidos</span>
                </div>
            </div>
        </div>

        <div id="filtro-ativo" style="display: none; margin: 1rem 0; padding: 0.5rem 1rem; background-color: #e2e8f0; border-radius: 0.5rem;">
            <span id="filtro-texto"></span>
            <button onclick="limparFiltro()" style="margin-left: 1rem; padding: 0.25rem 0.75rem; border-radius: 0.25rem; background-color: #fff; border: 1px solid #cbd5e0;">
                Limpar filtro
            </button>
        </div>

        <div class="tabelas-container">
            <div class="tabela-clientes-container">
                <div class="section-header">
                    <h2 class="section-title">Lista de Clientes</h2>
                    <button onclick="imprimirLista()" class="btn-imprimir">
                        <i class="fas fa-print"></i>
                        Imprimir Lista
                    </button>
                </div>
                <div class="tabela-clientes">
                    <div class="clientes-header">
                        <div>ID</div>
                        <div>Nome</div>
                        <div>Total Pedidos</div>
                        <div class="header-valor-total" onclick="ordenarPorValor()" style="cursor: pointer;">
                            Valor Total
                            <i class="fas fa-sort"></i>
                        </div>
                        <div>Status</div>
                        <div>Ações</div>
                    </div>
                    <?php foreach ($clientes as $cliente): ?>
                        <?php
                        $status = $cliente['valor_pendente'] > 0 ? 'Pendente' : 'Pago';
                        $valor_exibir = $status == 'Pago' ? $cliente['valor_total'] : $cliente['valor_pendente'];
                        ?>
                        <div class="cliente-item">
                            <div class="cliente-id"><?= $cliente['id_cliente'] ?></div>
                            <div class="cliente-nome"><?= htmlspecialchars($cliente['nome_cliente']) ?></div>
                            <div class="cliente-total-pedidos"><?= $cliente['total_pedidos'] ?></div>
                            <div class="cliente-valor-total">
                                <div class="valor-container">
                                    <span class="simbolo-moeda">R$</span>
                                    <span class="valor"><?= number_format($valor_exibir, 2, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="cliente-status">
                                <span class="status-badge <?= strtolower($status) ?>" 
                                      onclick="toggleStatusPagamentoCliente(this, <?= $cliente['id_cliente'] ?>)" 
                                      style="cursor: pointer;"
                                      title="Clique para alterar o status de todos os pedidos">
                                    <?= $status ?>
                                </span>
                            </div>
                            <div class="cliente-acoes">
                                <button class="btn-icon" onclick="verDetalhes(<?= $cliente['id_cliente'] ?>)" title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" onclick="editarCliente(<?= $cliente['id_cliente'] ?>)" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" onclick="verRelatorioMensal(<?= $cliente['id_cliente'] ?>)" title="Relatório Mensal">
                                    <i class="fas fa-file-alt"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tabela-pedidos-container">
                <h2 class="section-title">Pedidos</h2>
                <div class="tabela-pedidos">
                    <?php foreach ($pedidos_por_data as $data => $grupo): ?>
                        <div class="grupo-pedidos">
                            <div class="grupo-header">
                                <div class="coluna-data">
                                    <?php echo date('d/m', strtotime($data)); ?>
                                </div>
                                <div class="coluna-total">
                                    <?php echo $grupo['total_pedidos']; ?> pedidos
                                </div>
                                <div class="coluna-valor">
                                    <span class="simbolo-moeda">R$</span>
                                    <span class="valor"><?= number_format($grupo['valor_total'], 2, ',', '.') ?></span>
                                </div>
                                <div class="coluna-acoes">
                                    <button class="btn-taxa" onclick="abrirModalTaxa('<?php echo $data; ?>')" title="Distribuir Taxa">
                                        <i class="fas fa-dollar-sign"></i>
                                    </button>
                                    <button class="btn-expandir">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="pedidos-do-dia" style="display: none;">
                                <?php foreach ($grupo['pedidos'] as $pedido): ?>
                                    <div class="pedido selectable" 
                                         data-pedido-id="<?php echo $pedido['id_pedido']; ?>"
                                         data-cliente-id="<?php echo $pedido['fk_cliente_id']; ?>">
                                        <div class="coluna-pedido">
                                            #<?php echo $pedido['num_pedido']; ?>
                                            <small class="id-pedido">(<?php echo $pedido['id_pedido']; ?>)</small>
                                        </div>
                                        <div class="coluna-hora">
                                            <?php echo date('H:i', strtotime($pedido['data_pedido'])); ?>
                                        </div>
                                        <div class="coluna-cliente">
                                            <?php echo htmlspecialchars($pedido['nome_cliente'] ?? ''); ?>
                                            <span class="telefone"><?php echo htmlspecialchars($pedido['telefone_cliente'] ?? ''); ?></span>
                                        </div>
                                        
                                        <div class="coluna-produtos">
                                            <?php
                                            $stmt = $pdo->prepare("
                                                SELECT p.nome_produto, pi.quantidade
                                                FROM pedido_itens pi
                                                JOIN produto p ON p.id_produto = pi.fk_produto_id
                                                WHERE pi.fk_pedido_id = ?
                                            ");
                                            $stmt->execute([$pedido['id_pedido']]);
                                            while ($produto = $stmt->fetch()) {
                                                echo "<div class='produto-item'>";
                                                echo "{$produto['quantidade']}x {$produto['nome_produto']}";
                                                echo "</div>";
                                            }
                                            ?>
                                        </div>

                                        <div class="coluna-valores">
                                            <?php 
                                                $total = floatval($pedido['sub_total'] ?? 0);
                                                $taxa = floatval($pedido['taxa_entrega'] ?? 0);
                                                $total_com_taxa = $total + $taxa;
                                            ?>
                                            <div>Total: R$ <?php echo number_format($total, 2, ',', '.'); ?></div>
                                            <div>Taxa: R$ <?php echo number_format($taxa, 2, ',', '.'); ?></div>
                                            <div>Total+Taxa: R$ <?php echo number_format($total_com_taxa, 2, ',', '.'); ?></div>
                                        </div>
                                        <div class="coluna-status">
                                            <span class="status-badge <?php echo $pedido['status_pagamento'] ? 'pago' : 'pendente'; ?>"
                                                  onclick="toggleStatusPagamento(this, <?php echo $pedido['id_pedido']; ?>)">
                                                <i class="fas <?php echo $pedido['status_pagamento'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                                <?php echo $pedido['status_pagamento'] ? 'PAGO' : 'PENDENTE'; ?>
                                            </span>
                                        </div>
                                        <div class="coluna-acoes">
                                            <button class="btn-icon" onclick="verDetalhes(<?php echo $pedido['id_pedido']; ?>)" title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon" onclick="editarPedido(<?php echo $pedido['id_pedido']; ?>)" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon" onclick="buscarIdClientePorPedido(<?php echo $pedido['id_pedido']; ?>)" title="Relatório Mensal">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="lista-impressao" style="display: none;">
        <div class="cabecalho-impressao">
            <h1><?= htmlspecialchars($empresa['nome_empresa']) ?></h1>
            <h2>Lista de Cobrança - <?= date('d/m/Y', strtotime($primeiro_dia)) ?> a <?= date('d/m/Y', strtotime($ultimo_dia)) ?></h2>
        </div>
        <table class="tabela-impressao">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Pedidos</th>
                    <th>Valor Total</th>
                    <th>Status</th>
                    <th>Assinatura</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): 
                    $status = $cliente['valor_pendente'] > 0 ? 'Pendente' : 'Pago';
                    $valor_exibir = $status == 'Pago' ? $cliente['valor_total'] : $cliente['valor_pendente'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($cliente['nome_cliente']) ?></td>
                    <td class="centro"><?= $cliente['total_pedidos'] ?></td>
                    <td class="direita">R$ <?= number_format($valor_exibir, 2, ',', '.') ?></td>
                    <td class="centro"><?= $status ?></td>
                    <td class="assinatura"></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="rodape-impressao">
            <p>Data: _____/_____/_____</p>
            <p>Assinatura do Motoboy: _________________________________</p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function verDetalhes(pedidoId) {
            console.log('verDetalhes chamado com ID:', pedidoId);
            visualizarPedido(pedidoId);
        }

        function editarPedido(pedidoId) {
            window.location.href = `editar_pedido.php?id=${pedidoId}`;
        }

        document.querySelectorAll('.grupo-header').forEach(header => {
            header.addEventListener('click', function() {
                const content = this.nextElementSibling;
                const btnExpandir = this.querySelector('.btn-expandir');
                
                if (!content.classList.contains('aberto')) {
                    content.classList.add('aberto');
                    btnExpandir.classList.add('rotacionado');
                } else {
                    content.classList.remove('aberto');
                    btnExpandir.classList.remove('rotacionado');
                }
            });
        });

        let pedidosSelecionados = [];
        let dataAtual = '';

        function abrirModalTaxa(data) {
            dataAtual = data;
            
            if (selectedPedidos.size === 0) {
                alert('Por favor, selecione pelo menos um pedido.');
                return;
            }

            pedidosSelecionados = Array.from(selectedPedidos);
            
            console.log('Pedidos selecionados:', pedidosSelecionados);
            
            let pedidosInfo = 'Pedidos selecionados: ' + pedidosSelecionados.join(', ');
            document.getElementById('totalPedidosTaxa').textContent = pedidosSelecionados.length;
            document.getElementById('modalTaxa').style.display = 'flex';
            
            let infoElement = document.createElement('p');
            infoElement.textContent = pedidosInfo;
            document.querySelector('.modal-content').insertBefore(infoElement, document.querySelector('.form-group'));
            
            document.getElementById('valorTaxa').addEventListener('input', calcularValorPorPedido);
        }

        function calcularValorPorPedido() {
            const valorTotal = parseFloat(document.getElementById('valorTaxa').value) || 0;
            const valorPorPedido = pedidosSelecionados.length > 0 ? 
                (valorTotal / pedidosSelecionados.length).toFixed(2) : "0.00";
            document.getElementById('valorPorPedido').textContent = valorPorPedido;
        }

        function fecharModalTaxa() {
            document.getElementById('modalTaxa').style.display = 'none';
            document.getElementById('valorTaxa').value = '';
            document.getElementById('valorPorPedido').textContent = '0.00';
            const infoElement = document.querySelector('.modal-content p:not(:first-child)');
            if (infoElement) {
                infoElement.remove();
            }
        }

        function aplicarTaxa() {
            const valorTotal = parseFloat(document.getElementById('valorTaxa').value);
            if (!valorTotal || valorTotal <= 0) {
                alert('Por favor, insira um valor válido para a taxa.');
                return;
            }

            const valorPorPedido = valorTotal / selectedPedidos.size;

            console.log('Aplicando taxa:', {
                pedidos: Array.from(selectedPedidos),
                valorTotal: valorTotal,
                valorPorPedido: valorPorPedido
            });

            $.ajax({
                url: '../actions/empresas_pedidos/atualizar_taxa_pedidos.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    pedidos: Array.from(selectedPedidos),
                    valor_taxa: valorPorPedido
                },
                success: function(response) {
                    if (response.success) {
                        alert('Taxa aplicada com sucesso nos pedidos: ' + Array.from(selectedPedidos).join(', '));
                        location.reload();
                    } else {
                        alert('Erro ao aplicar taxa: ' + (response.message || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro:', xhr.responseText);
                    alert('Erro ao processar a requisição: ' + error);
                }
            });

            fecharModalTaxa();
            selectedPedidos.clear();
            document.querySelectorAll('.pedido.selected').forEach(el => el.classList.remove('selected'));
        }

        let selectedPedidos = new Set();
        let lastSelectedId = null;

        // Função para atualizar a contagem de selecionados
        function atualizarSelecao() {
            const total = selectedPedidos.size;
            document.getElementById('totalPedidosTaxa').textContent = total;
        }

        // Adicione os event listeners para seleção
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.pedido.selectable').forEach(pedido => {
                pedido.addEventListener('click', function(e) {
                    const pedidoId = this.dataset.pedidoId;

                    // Se a tecla Shift está pressionada
                    if (e.shiftKey && lastSelectedId) {
                        const pedidos = Array.from(document.querySelectorAll('.pedido.selectable'));
                        const currentIndex = pedidos.indexOf(this);
                        const lastIndex = pedidos.findIndex(p => p.dataset.pedidoId === lastSelectedId);
                        
                        const start = Math.min(currentIndex, lastIndex);
                        const end = Math.max(currentIndex, lastIndex);

                        for (let i = start; i <= end; i++) {
                            const id = pedidos[i].dataset.pedidoId;
                            selectedPedidos.add(id);
                            pedidos[i].classList.add('selected');
                        }
                    } else {
                        // Seleção normal (toggle)
                        if (selectedPedidos.has(pedidoId)) {
                            selectedPedidos.delete(pedidoId);
                            this.classList.remove('selected');
                        } else {
                            selectedPedidos.add(pedidoId);
                            this.classList.add('selected');
                        }
                        lastSelectedId = pedidoId;
                    }

                    atualizarSelecao();
                });
            });
        });

        function abrirModalEnderecoEmpresa() {
            if (selectedPedidos.size === 0) {
                alert('Por favor, selecione pelo menos um pedido.');
                return;
            }

            // Buscar endereço de referência da empresa
            $.ajax({
                url: '../controllers/EmpresaController.php',
                type: 'GET',
                data: { 
                    action: 'getEnderecoReferencia',
                    empresa_id: <?= $empresa_id ?>
                },
                success: function(response) {
                    if (typeof response === 'string') {
                        response = JSON.parse(response);
                    }
                    
                    if (response.success && response.endereco) {
                        $('#empresa_rua').val(response.endereco.nome_entrega);
                        $('#empresa_numero').val(response.endereco.numero_entrega);
                        $('#empresa_bairro').val(response.endereco.fk_Bairro_id_bairro);
                    }
                    
                    document.getElementById('totalPedidosEndereco').textContent = selectedPedidos.size;
                    document.getElementById('modalEnderecoEmpresa').style.display = 'flex';
                },
                error: function() {
                    alert('Erro ao carregar dados do endereço');
                }
            });
        }

        function fecharModalEnderecoEmpresa() {
            document.getElementById('modalEnderecoEmpresa').style.display = 'none';
        }

        $('#formEnderecoEmpresa').on('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('Isso irá atualizar o endereço de todos os pedidos selecionados. Deseja continuar?')) {
                return;
            }
            
            const formData = new FormData(this);
            formData.append('pedidos', Array.from(selectedPedidos));
            
            $.ajax({
                url: '../actions/atualizar_endereco_pedidos.php',
                method: 'POST',
                data: Object.fromEntries(formData),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Endereços atualizados com sucesso!');
                        fecharModalEnderecoEmpresa();
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

        function toggleStatusPagamento(element, pedidoId) {
            const isPago = element.classList.contains('pago');
            const novoStatus = isPago ? 0 : 1;

            $.ajax({
                url: '../actions/empresas_pedidos/toggle_status_pagamento.php',
                method: 'POST',
                data: { 
                    pedido_id: pedidoId,
                    status: novoStatus 
                },
                success: function(response) {
                    if (response.status === 'success') {
                        // Atualiza as classes e o texto
                        element.classList.toggle('pago');
                        element.classList.toggle('pendente');
                        
                        // Atualiza o ícone e o texto
                        const icon = element.querySelector('i');
                        if (novoStatus) {
                            icon.classList.remove('fa-clock');
                            icon.classList.add('fa-check-circle');
                            element.innerHTML = '<i class="fas fa-check-circle"></i> PAGO';
                        } else {
                            icon.classList.remove('fa-check-circle');
                            icon.classList.add('fa-clock');
                            element.innerHTML = '<i class="fas fa-clock"></i> PENDENTE';
                        }
                    } else {
                        alert('Erro ao atualizar status do pagamento');
                    }
                },
                error: function() {
                    alert('Erro ao atualizar status do pagamento');
                }
            });
        }

        function buscarIdClientePorPedido(pedidoId) {
            // Fazer uma requisição AJAX para buscar o ID do cliente
            $.ajax({
                url: '../controllers/PedidoController.php',
                type: 'GET',
                data: {
                    action: 'getClienteIdByPedido',
                    pedido_id: pedidoId
                },
                success: function(response) {
                    if (response.cliente_id) {
                        verRelatorioMensal(response.cliente_id);
                    } else {
                        alert('Não foi possível encontrar o cliente deste pedido');
                    }
                },
                error: function() {
                    alert('Erro ao buscar informações do cliente');
                }
            });
        }

        function verRelatorioMensal(clienteId) {
            // Pegar o mês selecionado no filtro ao invés do mês atual
            const mesSelecionado = document.getElementById('mes-filtro').value;
            
            // Gerar token único
            const token = Math.random().toString(36).substring(2) + Date.now().toString(36);
            
            // Construir URL com todos os parâmetros necessários
            const url = `relatorio_empresa_mensal.php?cliente=${clienteId}&mes=${mesSelecionado}&token=${token}`;
            
            // Abrir em nova aba
            window.open(url, '_blank');
        }

        function editarCliente(clienteId) {
            window.location.href = `editar_cliente.php?id=${clienteId}`;
        }

        // Adicione esta nova função após as funções existentes
        function filtrarPedidos(filtro) {
            // Atualizar o indicador de filtro ativo
            const filtroAtivo = document.getElementById('filtro-ativo');
            const filtroTexto = document.getElementById('filtro-texto');
            
            if (filtro === 'todos') {
                filtroAtivo.style.display = 'none';
            } else {
                filtroAtivo.style.display = 'block';
                filtroTexto.textContent = `Mostrando apenas pedidos ${filtro === 'pagos' ? 'pagos' : 'pendentes'}`;
            }
            
            // ... resto do código da função filtrarPedidos ...
        }

        // Adicione os event listeners para os cards após o DOMContentLoaded existente
        document.addEventListener('DOMContentLoaded', function() {
            // ... existing code ...

            // Adicionar eventos de clique nos cards
            document.querySelector('.card-status').addEventListener('click', function() {
                filtrarPedidos('pagos');
            });

            document.querySelector('.card-pendentes').addEventListener('click', function() {
                filtrarPedidos('pendentes');
            });

            // Adicionar cursor pointer aos cards
            document.querySelectorAll('.resumo-card').forEach(card => {
                card.style.cursor = 'pointer';
            });
        });

        function limparFiltro() {
            filtrarPedidos('todos');
            document.getElementById('filtro-ativo').style.display = 'none';
        }

        function visualizarPedido(id) {
            $.ajax({
                url: '../actions/relatorio_pedidos/get_pedido_detalhes.php',
                method: 'POST',
                data: { id_pedido: id },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        let html = `
                            <div class="modal-detalhes-pedido">
                                <div class="pedido-header">
                                    <div>
                                        <h3>Pedido #${response.pedido.num_pedido}</h3>
                                        <button class="btn-fechar-modal">&times;</button>
                                    </div>
                                    <div>
                                        <span class="horario">${response.pedido.data_pedido}</span>
                                        <span class="status">${response.pedido.status || 'Concluído'}</span>
                                    </div>
                                </div>`;
                        
                        let subtotal = 0;
                        response.itens.forEach((item, index) => {
                            const precoUnitario = parseFloat(item.preco_unitario) || 0;
                            let valorItem = item.quantidade * precoUnitario;
                            
                            if (item.acompanhamentos && item.acompanhamentos.length > 0) {
                                item.acompanhamentos.forEach(acomp => {
                                    const precoAcomp = parseFloat(acomp.preco_unitario) || 0;
                                    valorItem += precoAcomp;
                                });
                            }
                            
                            subtotal += valorItem;
                            
                            html += `
                                <div class="item">
                                    <div class="item-principal">
                                        <span class="quantidade">${item.quantidade}x</span>
                                        <span class="nome">${item.nome_produto}</span>
                                        <span class="preco">R$ ${valorItem.toFixed(2)}</span>
                                    </div>`;
                            
                            if (item.acompanhamentos && item.acompanhamentos.length > 0) {
                                html += `<div class="acompanhamentos">`;
                                item.acompanhamentos.forEach(acomp => {
                                    html += `
                                        <div class="acomp-item">
                                            <span class="acomp-nome">- ${acomp.nome_subacomp}</span>
                                        </div>`;
                                });
                                html += `</div>`;
                            }
                            
                            if (item.observacao) {
                                html += `<div class="observacao">Obs: ${item.observacao}</div>`;
                            }
                            
                            html += `</div>`;
                        });

                        const taxaEntrega = parseFloat(response.pedido.taxa_entrega) || 0;
                        const totalFinal = subtotal + taxaEntrega;
                        const totalBanco = parseFloat(response.pedido.total) || 0;
                        
                        // Verifica se há diferença entre os subtotais
                        const temDiferenca = Math.abs(subtotal - totalBanco) > 0.01;
                        const classeDiferenca = temDiferenca ? 'diferenca-subtotal' : '';
                        const avisoHtml = temDiferenca ? `
                            <div class="aviso-diferenca">
                                <i class="fas fa-exclamation-triangle"></i>
                                Diferença detectada nos subtotais
                                <button onclick="atualizarSubtotal(${id}, ${subtotal})" class="btn-atualizar-subtotal">
                                    <i class="fas fa-sync-alt"></i> Atualizar valor
                                </button>
                            </div>` : '';

                        // Adiciona a seção de totais
                        html += `
                            <div class="totais">
                                ${avisoHtml}
                                <div class="total-item">
                                    <span>Subtotal</span>
                                    <span class="${classeDiferenca}">R$ ${subtotal.toFixed(2)}</span>
                                </div>
                                <div class="total-item" style="color: #666; font-size: 0.9em;">
                                    <span>Subtotal registrado</span>
                                    <span class="${classeDiferenca}">R$ ${totalBanco.toFixed(2)}</span>
                                </div>
                                <div class="total-item">
                                    <span>Taxa de Entrega</span>
                                    <span>R$ ${taxaEntrega.toFixed(2)}</span>
                                </div>
                                <div class="total-item total-final">
                                    <span>Total</span>
                                    <span>R$ ${totalFinal.toFixed(2)}</span>
                                </div>
                            </div>
                        </div>`;

                        // Adiciona os estilos necessários
                        html += `
                            <style>
                                .diferenca-subtotal {
                                    color: #dc3545 !important;
                                    font-weight: bold;
                                }
                                .aviso-diferenca {
                                    background-color: #fff3cd;
                                    color: #856404;
                                    padding: 8px;
                                    margin-bottom: 10px;
                                    border-radius: 4px;
                                    border: 1px solid #ffeeba;
                                    display: flex;
                                    align-items: center;
                                    gap: 8px;
                                    font-size: 0.9em;
                                }
                                .aviso-diferenca i {
                                    color: #856404;
                                }
                                .btn-atualizar-subtotal {
                                    margin-left: auto;
                                    background-color: #28a745;
                                    color: white;
                                    border: none;
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    font-size: 0.9em;
                                    display: flex;
                                    align-items: center;
                                    gap: 4px;
                                }
                                .btn-atualizar-subtotal:hover {
                                    background-color: #218838;
                                }
                            </style>
                        `;

                        $('#modalDetalhes .modal-content').html(html);
                        $('#modalDetalhes').css('display', 'flex');
                    } else {
                        alert('Erro ao carregar detalhes do pedido: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição AJAX:', xhr.responseText);
                    alert('Erro na comunicação com o servidor');
                }
            });
        }

        // Função para atualizar o subtotal no banco de dados
        function atualizarSubtotal(pedidoId, novoSubtotal) {
            if (!confirm('Deseja realmente atualizar o valor do subtotal no banco de dados?')) {
                return;
            }

            $.ajax({
                url: '../actions/atualizar_subtotal_pedido.php',
                method: 'POST',
                data: {
                    pedido_id: pedidoId,
                    novo_subtotal: novoSubtotal
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Subtotal atualizado com sucesso!');
                        visualizarPedido(pedidoId); // Recarrega o modal
                    } else {
                        alert('Erro ao atualizar subtotal: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição AJAX:', xhr.responseText);
                    alert('Erro ao atualizar o subtotal');
                }
            });
        }

        $(document).ready(function() {
            // Fechar modal com ESC
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('#modalDetalhes').hide();
                }
            });

            // Fechar modal no X ou clicando fora
            $(document).on('click', '.btn-fechar-modal, #modalDetalhes', function(e) {
                if (e.target === this) {
                    $('#modalDetalhes').hide();
                }
            });
        });

        let ordemValorAscendente = true;

        function ordenarPorValor() {
            const container = document.querySelector('.tabela-clientes');
            const items = Array.from(container.querySelectorAll('.cliente-item'));
            const header = container.querySelector('.clientes-header');
            const sortIcon = header.querySelector('.header-valor-total i');

            items.sort((a, b) => {
                const valorA = parseFloat(a.querySelector('.valor').textContent.replace('.', '').replace(',', '.'));
                const valorB = parseFloat(b.querySelector('.valor').textContent.replace('.', '').replace(',', '.'));
                
                return ordemValorAscendente ? valorA - valorB : valorB - valorA;
            });

            // Atualiza o ícone
            sortIcon.className = ordemValorAscendente ? 'fas fa-sort-up' : 'fas fa-sort-down';

            // Limpa e readiciona os items ordenados
            items.forEach(item => container.appendChild(item));
            
            // Inverte a ordem para o próximo clique
            ordemValorAscendente = !ordemValorAscendente;
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

        function imprimirLista() {
            window.print();
        }

        function toggleStatusPagamentoCliente(element, clienteId) {
            if (!confirm('Deseja alterar o status de pagamento de todos os pedidos deste cliente?')) {
                return;
            }

            const isPago = element.classList.contains('pago');
            const novoStatus = isPago ? 0 : 1;

            // Pegar o mês atual do filtro
            const mesAtual = document.getElementById('mes-filtro').value;

            $.ajax({
                url: '../actions/empresas_pedidos/toggle_status_pagamento_cliente.php',
                method: 'POST',
                data: { 
                    cliente_id: clienteId,
                    status: novoStatus,
                    mes: mesAtual
                },
                success: function(response) {
                    if (response.status === 'success') {
                        // Atualiza o status do cliente
                        element.classList.toggle('pago');
                        element.classList.toggle('pendente');
                        element.textContent = novoStatus ? 'Pago' : 'Pendente';

                        // Atualiza os status dos pedidos individuais
                        const pedidos = document.querySelectorAll(`.pedido[data-cliente-id="${clienteId}"]`);
                        pedidos.forEach(pedido => {
                            const statusBadge = pedido.querySelector('.status-badge');
                            if (statusBadge) {
                                statusBadge.classList.remove('pago', 'pendente');
                                statusBadge.classList.add(novoStatus ? 'pago' : 'pendente');
                                statusBadge.innerHTML = novoStatus ? 
                                    '<i class="fas fa-check-circle"></i> PAGO' : 
                                    '<i class="fas fa-clock"></i> PENDENTE';
                            }
                        });

                        // Recarrega a página para atualizar os totais
                        location.reload();
                    } else {
                        alert('Erro ao atualizar status dos pagamentos');
                    }
                },
                error: function() {
                    alert('Erro ao atualizar status dos pagamentos');
                }
            });
        }
    </script>
</body>
</html>
