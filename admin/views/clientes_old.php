<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); 
    exit();
}

include_once '../config/database.php';

// Função helper para formatação
function formatNumber($number, $decimals = 2) {
    return number_format($number ?? 0, $decimals, ',', '.');
}

// Filtros e ordenação
$busca = $_GET['busca'] ?? '';
$ordem = $_GET['ordem'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'ASC';

try {
    // Query modificada para mostrar apenas os bairros
    $sql = "SELECT 
        c.*,
        COUNT(DISTINCT p.id_pedido) as total_pedidos,
        COALESCE(SUM(p.sub_total), 0) as valor_total,
        MAX(p.data_pedido) as ultimo_pedido,
        (
            SELECT GROUP_CONCAT(
                DISTINCT cb2.nome_bairro
                ORDER BY cb2.nome_bairro ASC
                SEPARATOR ' | '
            )
            FROM cliente_entrega ce2
            LEFT JOIN cliente_bairro cb2 ON ce2.fk_Bairro_id_bairro = cb2.id_bairro
            WHERE ce2.fk_Cliente_id_cliente = c.id_cliente
        ) as bairros,
        CASE 
            WHEN COUNT(DISTINCT p.id_pedido) > 0 THEN 1 
            ELSE 2 
        END as ordem_prioridade
    FROM clientes c
    LEFT JOIN pedidos p ON c.id_cliente = p.fk_cliente_id";

    if ($busca) {
        $sql .= " WHERE (
            c.nome_cliente LIKE ? OR 
            c.telefone_cliente LIKE ?
        )";
        $params = ["%$busca%", "%$busca%"];
    } else {
        $sql .= " WHERE 1=1";
        $params = [];
    }
    
    $sql .= " GROUP BY c.id_cliente";
    
    // Ordenação modificada usando a coluna calculada
    $sql .= " ORDER BY ordem_prioridade, c.nome_cliente " . $direcao;
    
    // Debug da query antes da execução
    error_log("Executando query: " . $sql);
    error_log("Parâmetros: " . print_r($params, true));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Debug do resultado
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Número de registros encontrados: " . count($clientes));
    if (count($clientes) > 0) {
        error_log("Primeiro registro: " . print_r($clientes[0], true));
    } else {
        error_log("Nenhum registro encontrado");
    }
    
    // Calcular estatísticas
    $total_clientes = count($clientes);
    $clientes_ativos = count(array_filter($clientes, function($c) {
        return !empty($c['ultimo_pedido']) && 
               strtotime($c['ultimo_pedido']) > strtotime('-30 days');
    }));
    $valor_total = array_sum(array_column($clientes, 'valor_total'));
    $valor_medio = $total_clientes > 0 ? $valor_total / $total_clientes : 0;
    
} catch (PDOException $e) {
    error_log('Erro ao buscar clientes: ' . $e->getMessage());
    $clientes = [];
    $total_clientes = $clientes_ativos = $valor_total = $valor_medio = 0;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Lunch&Fit</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="../assets/css/modal-cliente.css">
    <link rel="stylesheet" href="../assets/css/clientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <!-- Cards de Estatísticas -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_clientes; ?></div>
                    <div class="stat-label">Total de Clientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $clientes_ativos; ?></div>
                    <div class="stat-label">Clientes Ativos (30 dias)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">R$ <?php echo formatNumber($valor_medio); ?></div>
                    <div class="stat-label">Ticket Médio</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">R$ <?php echo formatNumber($valor_total); ?></div>
                    <div class="stat-label">Valor Total</div>
                </div>
            </div>

            <!-- Ações e Filtros -->
            <div class="actions-bar">
                <div class="search-box">
                    <input type="text" 
                           id="busca_cliente" 
                           placeholder="Buscar cliente..."
                           value="<?php echo htmlspecialchars($busca); ?>">
                    <i class="fas fa-search"></i>
                </div>
                
                <button id="selecionar-todos" class="btn-secundario">
                    <i class="fas fa-check-square"></i>
                    Selecionar Todos
                </button>
                
                <div class="filtros-avancados">
                    <button type="button" id="toggle-filtros" class="btn-secundario">
                        <i class="fas fa-filter"></i> Filtros Avançados
                    </button>
                    
                    <div class="painel-filtros" style="display: none;">
                        <div class="filtro-grupo">
                            <label>Bairro</label>
                            <select id="filtro_bairro">
                                <option value="">Todos</option>
                                <?php
                                $stmt = $pdo->query("SELECT * FROM cliente_bairro ORDER BY nome_bairro");
                                while ($bairro = $stmt->fetch()) {
                                    echo "<option value='{$bairro['id_bairro']}'>{$bairro['nome_bairro']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label>Período</label>
                            <select id="filtro_periodo">
                                <option value="">Todos</option>
                                <option value="7">Últimos 7 dias</option>
                                <option value="30">Últimos 30 dias</option>
                                <option value="90">Últimos 90 dias</option>
                                <option value="180">Últimos 180 dias</option>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label>Valor Mínimo</label>
                            <input type="number" id="filtro_valor_min" min="0" step="0.01">
                        </div>
                        
                        <div class="filtro-grupo">
                            <label>Status do Cliente</label>
                            <select id="filtro_status">
                                <option value="">Todos</option>
                                <option value="ativo">Ativos</option>
                                <option value="inativo">Inativos</option>
                            </select>
                        </div>
                        
                        <button type="button" id="aplicar-filtros" class="btn-primario">
                            Aplicar Filtros
                        </button>
                        
                        <button type="button" id="limpar-filtros" class="btn-secundario">
                            Limpar Filtros
                        </button>
                    </div>
                </div>
                <div class="action-buttons">
                    <button onclick="exportarExcel()" class="btn-exportar">
                        <i class="fas fa-file-excel"></i>
                        Exportar Excel
                    </button>
                    <button class="btn-novo-cliente">
                        <i class="fas fa-plus"></i>
                        Novo Cliente
                    </button>
                </div>
            </div>

            <!-- Tabela de Clientes -->
            <div class="table-responsive">
                <button id="excluir-selecionados" class="btn btn-danger" style="display: none;">
                    Excluir Selecionados (<span id="count-selecionados">0</span>)
                </button>

                <table class="clientes-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="?ordem=nome&direcao=<?php echo $ordem == 'nome' && $direcao == 'ASC' ? 'DESC' : 'ASC'; ?>&busca=<?php echo urlencode($busca); ?>">
                                    Nome
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Telefone</th>
                            <th>Bairros</th>
                            <th>
                                <a href="?ordem=pedidos&direcao=<?php echo $ordem == 'pedidos' && $direcao == 'ASC' ? 'DESC' : 'ASC'; ?>&busca=<?php echo urlencode($busca); ?>">
                                    Pedidos
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>
                                <a href="?ordem=valor&direcao=<?php echo $ordem == 'valor' && $direcao == 'ASC' ? 'DESC' : 'ASC'; ?>&busca=<?php echo urlencode($busca); ?>">
                                    Total Gasto
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>
                                <a href="?ordem=ultimo&direcao=<?php echo $ordem == 'ultimo' && $direcao == 'ASC' ? 'DESC' : 'ASC'; ?>&busca=<?php echo urlencode($busca); ?>">
                                    Último Pedido
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr class="cliente-row" data-id="<?= $cliente['id_cliente'] ?>">
                            <td><?php echo htmlspecialchars($cliente['nome_cliente']); ?></td>
                            <td><?php 
                                echo $cliente['telefone_cliente'] 
                                    ? preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $cliente['telefone_cliente']) 
                                    : '-'; 
                            ?></td>
                            <td class="bairros"><?php echo htmlspecialchars($cliente['bairros'] ?? '-'); ?></td>
                            <td class="text-center"><?php echo $cliente['total_pedidos']; ?></td>
                            <td class="text-right">R$ <?php echo formatNumber($cliente['valor_total']); ?></td>
                            <td><?php echo $cliente['ultimo_pedido'] ? date('d/m/Y', strtotime($cliente['ultimo_pedido'])) : '-'; ?></td>
                            <td class="actions">
                                <button class="btn-visualizar" data-id="<?php echo $cliente['id_cliente']; ?>" title="Visualizar">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-editar" data-id="<?php echo $cliente['id_cliente']; ?>" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-excluir" data-id="<?php echo $cliente['id_cliente']; ?>" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Cliente -->
    <div class="modal-cliente">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Cliente</h2>
            <form id="form-cliente">
                <input type="hidden" id="id_cliente" name="id_cliente">
                
                <div class="form-group">
                    <label for="nome_cliente">Nome*</label>
                    <input type="text" id="nome_cliente" name="nome_cliente" required>
                </div>
                
                <div class="form-group">
                    <label for="telefone_cliente">Telefone*</label>
                    <input type="text" id="telefone_cliente" name="telefone_cliente" required>
                </div>

                <!-- Endereços -->
                <div class="enderecos-container">
                    <h3>Endereços</h3>
                    <div id="lista-enderecos">
                        <!-- Endereços serão adicionados aqui dinamicamente -->
                    </div>
                    <button type="button" id="add-endereco" class="btn-secundario">
                        <i class="fas fa-plus"></i> Adicionar Endereço
                    </button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primario">Salvar</button>
                    <button type="button" class="btn-secundario close-modal">Cancelar</button>
                </div>
            </form>

            <div class="historico-pedidos">
                <h3>Histórico de Pedidos</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Nº Pedido</th>
                            <th>Itens</th>
                            <th>Taxa</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-historico">
                        <!-- Os pedidos serão inseridos aqui -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Template do Endereço -->
    <template id="template-endereco">
        <div class="endereco-item">
            <div class="form-group">
                <label>Rua*</label>
                <input type="text" name="nome_entrega[]" required>
            </div>
            <div class="form-group">
                <label>Número</label>
                <input type="text" name="numero_entrega[]">
            </div>
            <div class="form-group">
                <label>Bairro*</label>
                <select name="fk_Bairro_id_bairro[]" required>
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
            <button type="button" class="btn-remover-endereco">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </template>

    <!-- Modal de Visualização do Cliente -->
    <div class="modal-visualizacao">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="cliente-nome">Cliente</h2>
                <button class="btn-editar-cliente">
                    <i class="fas fa-edit"></i>
                    Editar cliente
                </button>
                <span class="close">&times;</span>
            </div>

            <div class="cards-info">
                <!-- Card Item Mais Pedido -->
                <div class="info-card">
                    <div class="card-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="item-mais-pedido">-</h3>
                        <div class="pedidos-detalhes">
                            <span id="qtd-item-1">0</span> <span id="nome-item-1">-</span><br>
                            <span id="qtd-item-2">0</span> <span id="nome-item-2">-</span><br>
                            <span id="qtd-item-3">0</span> <span id="nome-item-3">-</span>
                        </div>
                        <small>Item mais pedido</small>
                    </div>
                </div>

                <!-- Card Total Pedidos -->
                <div class="info-card">
                    <div class="card-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="total-pedidos">0</h3>
                        <small>Pedidos realizados</small>
                    </div>
                </div>

                <!-- Card Valor Total -->
                <div class="info-card">
                    <div class="card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="valor-total">R$ 0,00</h3>
                        <small>Total movimentado</small>
                    </div>
                </div>
            </div>

            <div class="cards-info-secundario">
                <!-- Telefone -->
                <div class="info-card-sec">
                    <div class="card-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="telefone-cliente">-</h3>
                        <small>Telefone</small>
                    </div>
                </div>

                <!-- Endereço -->
                <div class="info-card-sec">
                    <div class="card-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="endereco-cliente">-</h3>
                        <small>Endereço</small>
                    </div>
                </div>

                <!-- Primeiro Pedido -->
                <div class="info-card-sec">
                    <div class="card-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="primeiro-pedido">-</h3>
                        <small>Primeiro pedido</small>
                    </div>
                </div>

                <!-- Último Pedido -->
                <div class="info-card-sec">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="ultimo-pedido">-</h3>
                        <small>Pedido mais recente</small>
                    </div>
                </div>
            </div>

            <div class="cards-info-terciario">
                <!-- Aniversário -->
                <div class="info-card-sec">
                    <div class="card-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="aniversario-cliente">Não Informado</h3>
                        <small>Aniversário</small>
                    </div>
                </div>

                <!-- Cupons -->
                <div class="info-card-sec">
                    <div class="card-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="cupons-resgatados">0</h3>
                        <small>Cupons resgatados</small>
                    </div>
                </div>

                <!-- Cashback -->
                <div class="info-card-sec">
                    <div class="card-icon">
                        <i class="fas fa-undo"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="cashback-resgatado">R$ 0,00</h3>
                        <small>Cashback resgatado</small>
                    </div>
                </div>
            </div>

            <!-- Avaliações e Feedbacks -->
            <div class="feedback-section">
                <div class="info-card-wide">
                    <div class="card-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="card-content">
                        <h3 id="total-avaliacoes">0</h3>
                        <small>Avaliações e Feedbacks</small>
                        <p id="ultimo-feedback">Não há nenhum feedback</p>
                    </div>
                </div>
            </div>

            <!-- Adicione esta seção após os cards de informação -->
            <div class="historico-section">
                <h3>Histórico de Pedidos</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Nº Pedido</th>
                                <th>Itens</th>
                                <th>Pagamento</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="historico-pedidos">
                            <!-- Os pedidos serão inseridos aqui via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Exportação Excel
    function exportarExcel() {
        const data = [];
        const rows = document.querySelectorAll('.clientes-table tbody tr');
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            data.push({
                'Nome': cells[0].textContent,
                'Telefone': cells[1].textContent,
                'Total Pedidos': cells[2].textContent,
                'Total Gasto': cells[3].textContent,
                'Último Pedido': cells[4].textContent
            });
        });
        
        const worksheet = XLSX.utils.json_to_sheet(data);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Clientes");
        XLSX.writeFile(workbook, "relatorio_clientes.xlsx");
    }

    // Busca com debounce
    let timeoutBusca;
    $('#busca_cliente').on('input', function() {
        clearTimeout(timeoutBusca);
        const termo = $(this).val();
        
        timeoutBusca = setTimeout(() => {
            window.location.href = `?busca=${encodeURIComponent(termo)}&ordem=${ordem}&direcao=${direcao}`;
        }, 500);
    });

    // Variáveis para controlar a seleção
    let lastSelected = null;
    let selectedIds = new Set();

    // Selecionar/deselecionar ao clicar na linha
    $(document).on('click', '.cliente-row', function(e) {
        // Ignorar clique nos botões de ação
        if ($(e.target).closest('.actions').length > 0) {
            return;
        }
        
        const id = $(this).data('id');
        
        // Se pressionar shift, seleciona o intervalo
        if (e.shiftKey && lastSelected) {
            const rows = $('.cliente-row');
            const currentIndex = rows.index(this);
            const lastIndex = rows.index(lastSelected);
            
            const start = Math.min(currentIndex, lastIndex);
            const end = Math.max(currentIndex, lastIndex);
            
            rows.slice(start, end + 1).each(function() {
                const rowId = $(this).data('id');
                selectedIds.add(rowId);
                $(this).addClass('selected');
            });
        } else {
            // Comportamento normal de toggle
            if (selectedIds.has(id)) {
                selectedIds.delete(id);
                $(this).removeClass('selected');
            } else {
                selectedIds.add(id);
                $(this).addClass('selected');
            }
            lastSelected = this;
        }
        
        // Atualizar botão de excluir
        const count = selectedIds.size;
        $('#count-selecionados').text(count);
        $('#excluir-selecionados').toggle(count > 0);
    });
    
    // Excluir selecionados
    $('#excluir-selecionados').click(function() {
        if (selectedIds.size === 0) return;
        
        if (confirm(`Tem certeza que deseja excluir ${selectedIds.size} cliente(s)?`)) {
            excluirMultiplosClientes(Array.from(selectedIds));
        }
    });

    // Função global para atualizar o botão de excluir
    function atualizarBotaoExcluir() {
        const selecionados = $('.selecionar-cliente:checked').length;
        $('#count-selecionados').text(selecionados);
        $('#excluir-selecionados').toggle(selecionados > 0);
    }

    $(document).ready(function() {
        // Máscara para telefone
        $('#telefone_cliente').mask('(00) 00000-0000');
        
        // Abrir modal
        $('.btn-novo-cliente, .btn-editar').click(function() {
            const id = $(this).data('id');
            resetForm();
            if (id) {
                carregarCliente(id);
                carregarHistoricoPedidos(id);
            }
            $('.modal-cliente').fadeIn();
        });
        
        // Fechar modal
        $('.close, .close-modal').click(function() {
            $('.modal-cliente').fadeOut();
        });
        
        // Adicionar endereço
        $('#add-endereco').click(function() {
            const template = document.querySelector('#template-endereco');
            const clone = document.importNode(template.content, true);
            $('#lista-enderecos').append(clone);
        });
        
        // Remover endereço
        $(document).on('click', '.btn-remover-endereco', function() {
            $(this).closest('.endereco-item').remove();
        });
        
        // Submit do formulário
        $('#form-cliente').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            $.ajax({
                url: '../controllers/ClienteController.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Cliente salvo com sucesso!');
                        window.location.reload();
                    } else {
                        alert('Erro ao salvar cliente: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao processar requisição');
                }
            });
        });

        // Toggle dos filtros avançados
        $('#toggle-filtros').click(function() {
            $('.painel-filtros').slideToggle();
        });
        
        // Aplicar filtros
        $('#aplicar-filtros').click(function() {
            aplicarFiltros();
        });
        
        // Limpar filtros
        $('#limpar-filtros').click(function() {
            $('#filtro_bairro').val('');
            $('#filtro_periodo').val('');
            $('#filtro_valor_min').val('');
            $('#filtro_status').val('');
            aplicarFiltros();
        });
        
        function aplicarFiltros() {
            const filtros = {
                bairro: $('#filtro_bairro').val(),
                periodo: $('#filtro_periodo').val(),
                valor_min: $('#filtro_valor_min').val(),
                status: $('#filtro_status').val(),
                busca: $('#busca_cliente').val(),
                ordem: ordem,
                direcao: direcao
            };
            
            const queryString = Object.entries(filtros)
                .filter(([_, value]) => value !== '')
                .map(([key, value]) => `${key}=${encodeURIComponent(value)}`)
                .join('&');
                
            window.location.href = `?${queryString}`;
        }

        // Selecionar/Deselecionar todos
        $('#selecionar-todos').change(function() {
            const isChecked = $(this).prop('checked');
            $('.selecionar-cliente').prop('checked', isChecked);
            atualizarBotaoExcluir();
        });

        // Atualizar contagem quando checkbox individual é clicado
        $(document).on('change', '.selecionar-cliente', function() {
            atualizarBotaoExcluir();
        });

        // Atualiza visibilidade e contagem do botão
        function atualizarBotaoExcluir() {
            const selecionados = $('.selecionar-cliente:checked').length;
            $('#count-selecionados').text(selecionados);
            $('#excluir-selecionados').toggle(selecionados > 0);
        }

        // Excluir selecionados
        $('#excluir-selecionados').click(function() {
            const ids = $('.selecionar-cliente:checked').map(function() {
                return $(this).data('id');
            }).get();

            if (ids.length === 0) return;

            if (confirm(`Tem certeza que deseja excluir ${ids.length} cliente(s)?`)) {
                excluirMultiplosClientes(ids);
            }
        });
    });

    function carregarCliente(id) {
        $.ajax({
            url: '../controllers/ClienteController.php',
            type: 'GET',
            data: { 
                action: 'get', 
                id: id 
            },
            success: function(data) {
                // Garantir que data seja um objeto
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }

                // Verificar se a resposta contém dados
                if (!data) {
                    console.error('Nenhum dado recebido do servidor');
                    return;
                }

                // Preencher dados básicos do cliente
                $('#id_cliente').val(data.id_cliente);
                $('#nome_cliente').val(data.nome_cliente);
                
                // Formatar e preencher telefone
                let telefone = data.telefone_cliente;
                if (telefone && telefone.length === 11) {
                    telefone = telefone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                }
                $('#telefone_cliente').val(telefone);
                
                // Limpar endereços existentes
                $('#lista-enderecos').empty();
                
                // Adicionar endereços do cliente
                if (data.enderecos && data.enderecos.length > 0) {
                    data.enderecos.forEach(function(endereco) {
                        const template = document.querySelector('#template-endereco');
                        const clone = document.importNode(template.content, true);
                        
                        // Preencher campos do endereço
                        $(clone).find('[name="nome_entrega[]"]').val(endereco.nome_entrega);
                        $(clone).find('[name="numero_entrega[]"]').val(endereco.numero_entrega);
                        $(clone).find('[name="fk_Bairro_id_bairro[]"]').val(endereco.fk_Bairro_id_bairro);
                        
                        // Adicionar ID do endereço se existir
                        if (endereco.id_entrega) {
                            $(clone).find('.endereco-item').attr('data-id', endereco.id_entrega);
                        }
                        
                        $('#lista-enderecos').append(clone);
                    });
                } else {
                    // Se não houver endereços, adicionar um endereço vazio
                    const template = document.querySelector('#template-endereco');
                    const clone = document.importNode(template.content, true);
                    $('#lista-enderecos').append(clone);
                }

                // Adicionar logs para debug
                console.log('Dados recebidos:', data);
                console.log('ID do cliente:', data.id_cliente);
                console.log('Nome do cliente:', data.nome_cliente);
                console.log('Telefone do cliente:', data.telefone_cliente);
                console.log('Endereços:', data.enderecos);

                // Exibir o modal
                $('.modal-cliente').fadeIn();
            },
            error: function(xhr, status, error) {
                console.error('Erro ao carregar cliente:', error);
                console.error('Status:', status);
                console.error('Resposta:', xhr.responseText);
                alert('Erro ao carregar dados do cliente. Por favor, tente novamente.');
            }
        });
    }

    function resetForm() {
        $('#form-cliente')[0].reset();
        $('#id_cliente').val('');
        $('#lista-enderecos').empty();
    }

    function carregarHistoricoPedidos(clienteId) {
        $.get('../controllers/ClienteController.php', { 
            action: 'historico', 
            id: clienteId 
        }, function(data) {
            const tbody = $('#tbody-historico');
            tbody.empty();
            
            data.forEach(pedido => {
                tbody.append(`
                    <tr>
                        <td>${pedido.data_pedido}</td>
                        <td>#${pedido.id_pedido}</td>
                        <td>${pedido.itens}</td>
                        <td>R$ ${pedido.taxa_entrega}</td>
                        <td>R$ ${pedido.total}</td>
                        <td>${pedido.status || 'Pendente'}</td>
                    </tr>
                `);
            });
        });
    }

    function formatarData(data) {
        return new Date(data).toLocaleDateString('pt-BR');
    }

    function formatarNumero(numero) {
        return Number(numero).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatarItensPedido(itens) {
        return itens.map(item => 
            `<div class="item-pedido">
                ${item.quantidade}x ${item.nome_produto}
                ${item.observacao ? `<small>(${item.observacao})</small>` : ''}
            </div>`
        ).join('');
    }

    // Função para carregar os detalhes do cliente
    function carregarDetalhesCliente(id) {
        $.ajax({
            url: '../controllers/ClienteController.php',
            type: 'GET',
            data: {
                action: 'detalhes',
                id: id
            },
            success: function(response) {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }

                if (response.error) {
                    alert(response.message);
                    return;
                }

                // Preenche os dados do cliente
                $('#cliente-nome').text(response.nome_cliente);
                $('#telefone-cliente').text(response.telefone_cliente || '-');
                $('#endereco-cliente').text(response.endereco_principal || 'Não cadastrado');
                $('#total-pedidos').text(response.total_pedidos || 0);
                $('#valor-total').text('R$ ' + formatarNumero(response.valor_total || 0));
                $('#primeiro-pedido').text(formatarData(response.primeiro_pedido) || '-');
                $('#ultimo-pedido').text(formatarData(response.ultimo_pedido) || '-');
                
                // Preenche itens mais pedidos (com verificação de segurança)
                if (Array.isArray(response.itens_populares) && response.itens_populares.length > 0) {
                    $('#item-mais-pedido').text(response.itens_populares[0].nome);
                    response.itens_populares.forEach((item, index) => {
                        if (index < 3) {
                            $(`#qtd-item-${index + 1}`).text(item.quantidade + 'x');
                            $(`#nome-item-${index + 1}`).text(item.nome);
                        }
                    });
                } else {
                    $('#item-mais-pedido').text('Nenhum pedido');
                    for (let i = 1; i <= 3; i++) {
                        $(`#qtd-item-${i}`).text('');
                        $(`#nome-item-${i}`).text('-');
                    }
                }

                // Preenche dados adicionais
                $('#aniversario-cliente').text(formatarData(response.aniversario) || 'Não informado');
                $('#cupons-resgatados').text(response.cupons_resgatados || 0);
                $('#cashback-resgatado').text('R$ ' + formatarNumero(response.cashback_resgatado || 0));
                $('#total-avaliacoes').text(response.total_avaliacoes || 0);
                $('#ultimo-feedback').text(response.ultimo_feedback || 'Não há nenhum feedback');
                
                // Exibe o modal
                $('.modal-visualizacao').fadeIn();

                // Carrega o histórico de pedidos
                $.ajax({
                    url: '../controllers/ClienteController.php',
                    type: 'GET',
                    data: {
                        action: 'historicoPedidos',
                        id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        const tbody = $('#historico-pedidos');
                        tbody.empty();
                        
                        if (response.success && response.pedidos && response.pedidos.length > 0) {
                            response.pedidos.forEach(pedido => {
                                const dataFormatada = new Date(pedido.data_pedido).toLocaleDateString('pt-BR');
                                const totalFormatado = parseFloat(pedido.total).toLocaleString('pt-BR', {
                                    style: 'currency',
                                    currency: 'BRL'
                                });
                                
                                // Remove os preços dos itens
                                let itensFormatados = '';
                                if (pedido.itens) {
                                    itensFormatados = pedido.itens
                                        .split(',')
                                        .map(item => {
                                            // Remove qualquer valor em reais entre parênteses
                                            return item.replace(/\(R\$\s*[\d.,]+\)/g, '').trim();
                                        })
                                        .join('<br>');
                                }
                                
                                tbody.append(`
                                    <tr>
                                        <td>${dataFormatada}</td>
                                        <td>#${pedido.id_pedido}</td>
                                        <td class="itens-pedido">${itensFormatados || 'Não disponível'}</td>
                                        <td>${pedido.metodo_pagamento || 'Não informado'}</td>
                                        <td>${totalFormatado}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            tbody.append(`
                                <tr>
                                    <td colspan="5" class="text-center">Nenhum pedido encontrado</td>
                                </tr>
                            `);
                        }
                    }
                });

                // Exibe o modal
                $('.modal-visualizacao').fadeIn();
            }
        });
    }

    // Event listener para o botão de visualizar
    $(document).on('click', '.btn-visualizar', function() {
        const id = $(this).data('id');
        carregarDetalhesCliente(id);
    });

    // Fechar modal
    $(document).on('click', '.close', function() {
        $('.modal-visualizacao').fadeOut();
    });

    // Funções auxiliares
    function formatarNumero(numero) {
        return parseFloat(numero || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatarData(data) {
        if (!data) return '-';
        return new Date(data).toLocaleDateString('pt-BR');
    }

    // Adicione esta função após as outras funções JavaScript
    function excluirCliente(id) {
        if (confirm('Tem certeza que deseja excluir este cliente?')) {
            $.ajax({
                url: '../actions/excluir_cliente.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Remove a linha da tabela
                        $(`button[data-id="${id}"]`).closest('tr').fadeOut(400, function() {
                            $(this).remove();
                            
                            // Atualiza os contadores
                            const totalClientes = parseInt($('.stat-card:first .stat-value').text()) - 1;
                            $('.stat-card:first .stat-value').text(totalClientes);
                            
                            // Mostra mensagem suave
                            const mensagem = $('<div>')
                                .addClass('alert alert-success')
                                .text('Cliente excluído com sucesso!')
                                .css({
                                    'position': 'fixed',
                                    'top': '20px',
                                    'right': '20px',
                                    'padding': '10px 20px',
                                    'background': '#4CAF50',
                                    'color': 'white',
                                    'border-radius': '4px',
                                    'box-shadow': '0 2px 4px rgba(0,0,0,0.2)',
                                    'z-index': 9999
                                });
                            
                            $('body').append(mensagem);
                            setTimeout(() => mensagem.fadeOut(400, function() { $(this).remove(); }), 3000);
                        });
                    } else {
                        // Mostra erro suave
                        const mensagem = $('<div>')
                            .addClass('alert alert-danger')
                            .text('Erro ao excluir cliente: ' + response.message)
                            .css({
                                'position': 'fixed',
                                'top': '20px',
                                'right': '20px',
                                'padding': '10px 20px',
                                'background': '#f44336',
                                'color': 'white',
                                'border-radius': '4px',
                                'box-shadow': '0 2px 4px rgba(0,0,0,0.2)',
                                'z-index': 9999
                            });
                        
                        $('body').append(mensagem);
                        setTimeout(() => mensagem.fadeOut(400, function() { $(this).remove(); }), 3000);
                    }
                },
                error: function(xhr, status, error) {
                    // Mostra erro de requisição
                    const mensagem = $('<div>')
                        .addClass('alert alert-danger')
                        .text('Erro ao processar requisição: ' + error)
                        .css({
                            'position': 'fixed',
                            'top': '20px',
                            'right': '20px',
                            'padding': '10px 20px',
                            'background': '#f44336',
                            'color': 'white',
                            'border-radius': '4px',
                            'box-shadow': '0 2px 4px rgba(0,0,0,0.2)',
                            'z-index': 9999
                        });
                    
                    $('body').append(mensagem);
                    setTimeout(() => mensagem.fadeOut(400, function() { $(this).remove(); }), 3000);
                }
            });
        }
    }

    // Adicione o event listener para o botão excluir
    $(document).on('click', '.btn-excluir', function() {
        const id = $(this).data('id');
        excluirCliente(id);
    });

    function excluirMultiplosClientes(ids) {
        $.ajax({
            url: '../actions/excluir_cliente.php',
            type: 'POST',
            data: { ids: ids },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Remove as linhas da tabela
                    ids.forEach(id => {
                        $(`.cliente-row[data-id="${id}"]`).fadeOut(400, function() {
                            $(this).remove();
                            
                            // Atualiza os contadores
                            const totalClientes = parseInt($('.stat-card:first .stat-value').text()) - 1;
                            $('.stat-card:first .stat-value').text(totalClientes);
                        });
                    });

                    // Reset seleção
                    selectedIds.clear();
                    $('#excluir-selecionados').hide();
                    
                    // Mostra mensagem suave
                    const mensagem = $('<div>')
                        .addClass('alert alert-success')
                        .text(`${ids.length} cliente(s) excluído(s) com sucesso!`)
                        .css({
                            'position': 'fixed',
                            'top': '20px',
                            'right': '20px',
                            'padding': '10px 20px',
                            'background': '#4CAF50',
                            'color': 'white',
                            'border-radius': '4px',
                            'box-shadow': '0 2px 4px rgba(0,0,0,0.2)',
                            'z-index': 9999
                        });
                    
                    $('body').append(mensagem);
                    setTimeout(() => mensagem.fadeOut(400, function() { $(this).remove(); }), 3000);
                } else {
                    // Mostra erro suave
                    const mensagem = $('<div>')
                        .addClass('alert alert-danger')
                        .text('Erro ao excluir clientes: ' + response.message)
                        .css({
                            'position': 'fixed',
                            'top': '20px',
                            'right': '20px',
                            'padding': '10px 20px',
                            'background': '#f44336',
                            'color': 'white',
                            'border-radius': '4px',
                            'box-shadow': '0 2px 4px rgba(0,0,0,0.2)',
                            'z-index': 9999
                        });
                    
                    $('body').append(mensagem);
                    setTimeout(() => mensagem.fadeOut(400, function() { $(this).remove(); }), 3000);
                }
            },
            error: function(xhr, status, error) {
                // Mostra erro de requisição
                const mensagem = $('<div>')
                    .addClass('alert alert-danger')
                    .text('Erro ao processar requisição: ' + error)
                    .css({
                        'position': 'fixed',
                        'top': '20px',
                        'right': '20px',
                        'padding': '10px 20px',
                        'background': '#f44336',
                        'color': 'white',
                        'border-radius': '4px',
                        'box-shadow': '0 2px 4px rgba(0,0,0,0.2)',
                        'z-index': 9999
                    });
                
                $('body').append(mensagem);
                setTimeout(() => mensagem.fadeOut(400, function() { $(this).remove(); }), 3000);
            }
        });
    }

    // Adicione esta função junto com os outros scripts
    $(document).ready(function() {
        let todosEstaoSelecionados = false;
        
        $('#selecionar-todos').click(function() {
            todosEstaoSelecionados = !todosEstaoSelecionados;
            
            if (todosEstaoSelecionados) {
                // Seleciona todas as linhas
                $('.cliente-row').each(function() {
                    const id = $(this).data('id');
                    selectedIds.add(id);
                    $(this).addClass('selected');
                });
                $(this).html('<i class="fas fa-square"></i> Desselecionar Todos');
            } else {
                // Desseleciona todas as linhas
                $('.cliente-row').each(function() {
                    const id = $(this).data('id');
                    selectedIds.delete(id);
                    $(this).removeClass('selected');
                });
                $(this).html('<i class="fas fa-check-square"></i> Selecionar Todos');
            }
            
            // Atualiza o botão de excluir
            const count = selectedIds.size;
            $('#count-selecionados').text(count);
            $('#excluir-selecionados').toggle(count > 0);
        });
    });

    let lastChecked = null; // Guarda a última checkbox marcada

    // Função para selecionar checkboxes em um intervalo
    function handleCheckboxClick(e) {
        const checkboxes = document.querySelectorAll('.cliente-checkbox');
        
        if (!lastChecked) {
            lastChecked = e.target;
            return;
        }

        if (e.shiftKey) {
            const start = Array.from(checkboxes).indexOf(e.target);
            const end = Array.from(checkboxes).indexOf(lastChecked);
            
            const checkboxesArray = Array.from(checkboxes);
            
            const [min, max] = [start, end].sort((a, b) => a - b);
            
            checkboxesArray.slice(min, max + 1).forEach(checkbox => {
                checkbox.checked = lastChecked.checked;
                const row = checkbox.closest('tr');
                if (checkbox.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }
        
        lastChecked = e.target;
        
        // Atualiza o botão de excluir
        const totalSelecionados = document.querySelectorAll('.cliente-checkbox:checked').length;
        const btnExcluir = document.getElementById('excluir-selecionados');
        if (totalSelecionados > 0) {
            btnExcluir.style.display = 'block';
            btnExcluir.textContent = `Excluir Selecionados (${totalSelecionados})`;
        } else {
            btnExcluir.style.display = 'none';
        }
    }

    // Adiciona o evento de click em todas as checkboxes
    document.querySelectorAll('.cliente-checkbox').forEach(checkbox => {
        checkbox.addEventListener('click', handleCheckboxClick);
    });

    // Atualiza o botão "Selecionar Todos"
    document.getElementById('selecionar-todos').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.cliente-checkbox');
        const todasMarcadas = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = !todasMarcadas;
            const row = checkbox.closest('tr');
            if (checkbox.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        });
        
        // Atualiza o botão de excluir
        const totalSelecionados = document.querySelectorAll('.cliente-checkbox:checked').length;
        const btnExcluir = document.getElementById('excluir-selecionados');
        if (totalSelecionados > 0) {
            btnExcluir.style.display = 'block';
            btnExcluir.textContent = `Excluir Selecionados (${totalSelecionados})`;
        } else {
            btnExcluir.style.display = 'none';
        }
    });
    </script>

    <!-- Manter os scripts existentes -->
    <script src="../assets/js/menu.js"></script>
    <script src="../assets/js/cliente.js"></script>

    <!-- Adicionar CSS específico -->
    <style>
    /* Container principal */
    .main-content {
        padding: 0.5rem;
    }

    /* Container da tabela e cards */
    .container {
        max-width: 100%;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Cards de estatísticas */
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
        width: 100%;
        max-width: 100%;
    }

    .stat-card {
        background: white;
        padding: 1.25rem;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
    }

    /* Barra de ações */
    .actions-bar {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr auto auto;
        gap: 1rem;
        align-items: center;
        margin-bottom: 1rem;
        padding: 1rem;
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    /* Tabela */
    .table-responsive {
        width: 100%;
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow-x: auto;
    }

    /* Responsividade */
    @media (max-width: 1200px) {
        .stats-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-cards {
            grid-template-columns: 1fr 1fr;
        }
        
        .actions-bar {
            grid-template-columns: 1fr;
        }
        
        .container {
            padding: 0 0.5rem;
        }
    }

    .search-box {
        position: relative;
        flex: 1;
        max-width: 300px;
    }

    .search-box input {
        width: 100%;
        padding: 0.5rem 2rem 0.5rem 1rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .search-box i {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #7f8c8d;
    }

    .table-responsive {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-top: 1rem;
    }

    .clientes-table {
        width: 100%;
        border-collapse: collapse;
    }

    .clientes-table th {
        background: #f8f9fa;
        padding: 0.75rem 1rem;
        text-align: left;
        font-weight: 600;
        color: #495057;
    }

    .clientes-table td {
        padding: 0.75rem 1rem;
        border-top: 1px solid #e9ecef;
    }

    .clientes-table th:nth-child(1), 
    .clientes-table td:nth-child(1) { width: 25%; } /* Nome */
    .clientes-table th:nth-child(2),
    .clientes-table td:nth-child(2) { width: 15%; } /* Telefone */
    .clientes-table th:nth-child(3),
    .clientes-table td:nth-child(3) { width: 20%; } /* Bairros */
    .clientes-table th:nth-child(4),
    .clientes-table td:nth-child(4) { width: 10%; text-align: center; } /* Pedidos */
    .clientes-table th:nth-child(5),
    .clientes-table td:nth-child(5) { width: 120px; text-align: center; padding-right: 1.5rem; white-space: nowrap; } /* Total Gasto */
    .clientes-table th:nth-child(6),
    .clientes-table td:nth-child(6) { width: 10%; } /* Último Pedido */
    .clientes-table th:nth-child(7),
    .clientes-table td:nth-child(7) { width: 100px; text-align: center; padding: 0.75rem; white-space: nowrap; } /* Ações */

    .clientes-table tr:hover {
        background-color: #f8f9fa;
    }

    .actions {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        align-items: center;
    }

    .btn-visualizar,
    .btn-editar,
    .btn-excluir {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        padding: 0;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .btn-visualizar {
        background-color: #e8f5e9;
        color: #2e7d32;
    }

    .btn-visualizar:hover {
        background-color: #c8e6c9;
    }

    .btn-visualizar i,
    .btn-editar i,
    .btn-excluir i {
        font-size: 14px;
    }

    .text-center { text-align: center; }
    .text-right { text-align: right; }

    .enderecos {
        max-width: 300px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .enderecos:hover {
        white-space: normal;
        overflow: visible;
        position: relative;
        z-index: 1;
    }

    .search-box input {
        width: 100%;
        padding: 0.5rem 2rem 0.5rem 1rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .search-box input::placeholder {
        color: #999;
    }

    .modal-cliente {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
    }

    .modal-content {
        position: relative;
        background: white;
        margin: 50px auto;
        padding: 20px;
        width: 90%;
        max-width: 600px;
        border-radius: 8px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .close {
        position: absolute;
        right: 20px;
        top: 20px;
        font-size: 24px;
        cursor: pointer;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .endereco-item {
        display: grid;
        grid-template-columns: 2fr 1fr 2fr auto;
        gap: 10px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .btn-remover-endereco {
        align-self: end;
        padding: 8px;
        background: #ff4444;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .historico-pedidos {
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 1px solid #ddd;
    }

    .historico-content {
        max-height: 400px;
        overflow-y: auto;
    }

    .historico-table {
        width: 100%;
        border-collapse: collapse;
    }

    .historico-table th,
    .historico-table td {
        padding: 0.5rem;
        border: 1px solid #ddd;
        font-size: 0.9rem;
    }

    .itens-pedido {
        max-height: 100px;
        overflow-y: auto;
    }

    .item-pedido {
        margin-bottom: 0.25rem;
        font-size: 0.85rem;
    }

    .item-pedido small {
        color: #666;
    }

    .status-pedido {
        padding: 0.25rem 0.5rem;
        border-radius: 3px;
        font-size: 0.8rem;
        font-weight: bold;
    }

    .status-pedido.pendente { background: #fff3cd; color: #856404; }
    .status-pedido.confirmado { background: #d4edda; color: #155724; }
    .status-pedido.entregue { background: #cce5ff; color: #004085; }
    .status-pedido.cancelado { background: #f8d7da; color: #721c24; }

    .filtros-avancados {
        margin: 1rem 0;
    }

    .painel-filtros {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 4px;
        margin-top: 0.5rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .filtro-grupo {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .filtro-grupo label {
        font-weight: bold;
        font-size: 0.9rem;
        color: #495057;
    }

    .filtro-grupo select,
    .filtro-grupo input {
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 0.9rem;
    }

    #aplicar-filtros,
    #limpar-filtros {
        align-self: end;
    }

    @media (max-width: 768px) {
        .painel-filtros {
            grid-template-columns: 1fr;
        }
    }

    .modal-visualizacao {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
    }

    .modal-content {
        position: relative;
        background: #f8f9fa;
        margin: 2% auto;
        padding: 20px;
        width: 90%;
        max-width: 1200px;
        border-radius: 12px;
        max-height: 96vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .btn-editar-cliente {
        background: #007bff;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cards-info {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .cards-info-secundario,
    .cards-info-terciario {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .info-card,
    .info-card-sec {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .card-icon {
        width: 40px;
        height: 40px;
        background: #e3f2fd;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1976d2;
    }

    .card-content {
        flex: 1;
    }

    .card-content h3 {
        margin: 0;
        font-size: 1.25rem;
        color: #2c3e50;
    }

    .card-content small {
        color: #7f8c8d;
        font-size: 0.875rem;
    }

    .pedidos-detalhes {
        margin: 8px 0;
        font-size: 0.9rem;
        color: #666;
    }

    .feedback-section {
        margin-top: 1.5rem;
    }

    .info-card-wide {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    @media (max-width: 1200px) {
        .cards-info-secundario,
        .cards-info-terciario {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .cards-info {
            grid-template-columns: 1fr;
        }
        
        .modal-content {
            margin: 0;
            width: 100%;
            height: 100%;
            border-radius: 0;
        }
    }

    .historico-pedidos {
        margin-top: 2rem;
        padding: 1rem;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .historico-pedidos h3 {
        margin-bottom: 1rem;
        color: #2c3e50;
    }

    .table-responsive {
        overflow-x: auto;
    }

    #historico-pedidos {
        width: 100%;
        border-collapse: collapse;
    }

    #historico-pedidos th,
    #historico-pedidos td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    #historico-pedidos th {
        background: #f8f9fa;
        font-weight: 600;
    }

    #historico-pedidos tr:hover {
        background: #f8f9fa;
    }

    .historico-section {
        margin-top: 2rem;
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .historico-section h3 {
        margin-bottom: 1rem;
        color: #2c3e50;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th,
    .table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .table th {
        background: #f8f9fa;
        font-weight: 600;
    }

    .table tr:hover {
        background: #f8f9fa;
    }

    .itens-pedido {
        font-size: 0.9em;
        line-height: 1.4;
    }

    .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
    }

    .table td {
        vertical-align: middle;
        padding: 0.75rem;
    }

    .table tr:hover {
        background-color: #f8f9fa;
    }

    .cliente-row {
        cursor: pointer;
    }
    
    .cliente-row.selected {
        background-color: #e3f2fd !important;
    }
    
    .cliente-row:hover {
        background-color: #f5f5f5;
    }
    
    #excluir-selecionados {
        margin: 10px 0;
        padding: 8px 16px;
        background-color: #dc3545;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        display: none;
    }
    
    #excluir-selecionados:hover {
        background-color: #c82333;
    }

    #selecionar-todos {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }

    #selecionar-todos:hover {
        background-color: #e9ecef;
    }

    #selecionar-todos i {
        font-size: 14px;
    }
    </style>
</body>
</html> 