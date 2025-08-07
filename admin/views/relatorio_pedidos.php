<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); 
    exit();
}

include_once '../config/database.php';

// Função para buscar itens de um pedido
function buscarItensPedido($itens_por_pedido, $pedido_id) {
    return isset($itens_por_pedido[$pedido_id]) ? $itens_por_pedido[$pedido_id] : [];
}

// Função para contar pedidos do cliente
function contarPedidosCliente($pdo, $cliente_id) {
    try {
        $sql = "SELECT COUNT(*) as total 
                FROM pedidos 
                WHERE fk_cliente_id = ? 
                AND data_pedido >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cliente_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        return 0;
    }
}

// Adicionar parâmetros de filtro
$filtros = [];
$params = [];

if (!empty($_GET['data_inicio'])) {
    $filtros[] = "DATE(p.data_pedido) >= ?";
    $params[] = $_GET['data_inicio'];
}

if (!empty($_GET['data_fim'])) {
    $filtros[] = "DATE(p.data_pedido) <= ?";
    $params[] = $_GET['data_fim'];
}

if (!empty($_GET['cliente'])) {
    $filtros[] = "(c.nome_cliente LIKE ? OR c.telefone_cliente LIKE ?)";
    $params[] = "%{$_GET['cliente']}%";
    $params[] = "%{$_GET['cliente']}%";
}

$mostrarApenasRetirada = isset($_GET['retirada']);
$mostrarApenasPendentes = isset($_GET['pendentes']);

// Lógica de Paginação por Data
$itens_por_pagina = 20; // Agora isso será usado para datas, não pedidos
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Primeiro, vamos buscar todas as datas distintas
$sql_datas = "SELECT DISTINCT DATE(data_pedido) as data
              FROM pedidos p
              LEFT JOIN clientes c ON p.fk_cliente_id = c.id_cliente 
              WHERE 1=1 ";

if ($mostrarApenasRetirada) {
    $sql_datas .= "AND p.is_retirada = 1 ";
} elseif ($mostrarApenasPendentes) {
    $sql_datas .= "AND p.status_pagamento = 0 AND c.fk_empresa_id IS NULL ";
} elseif (isset($_GET['empresas'])) {
    $sql_datas .= "AND c.fk_empresa_id IS NOT NULL ";
}

if (!empty($filtros)) {
    $sql_datas .= " AND " . implode(" AND ", $filtros);
}

$sql_datas .= " ORDER BY data_pedido DESC";

$stmt_datas = $pdo->prepare($sql_datas);
$stmt_datas->execute($params);
$todas_datas = $stmt_datas->fetchAll(PDO::FETCH_COLUMN);

// Calcular total de páginas baseado nas datas
$total_datas = count($todas_datas);
$total_paginas = ceil($total_datas / $itens_por_pagina);

// Calcular o offset e limit para as datas
$offset = ($pagina_atual - 1) * $itens_por_pagina;
$datas_pagina = array_slice($todas_datas, $offset, $itens_por_pagina);

// Agora vamos buscar os pedidos apenas das datas desta página
if (!empty($datas_pagina)) {
    $placeholders = str_repeat('?,', count($datas_pagina) - 1) . '?';
    $sql = "SELECT 
        p.*,
        c.nome_cliente,
        c.telefone_cliente,
        c.fk_empresa_id,
        c.tipo_cliente,
        e.nome_empresa,
        cb.nome_bairro,
        pg.metodo_pagamento,
        CONCAT(p.data_pedido, ' ', p.hora_pedido) as data_pedido_completa
    FROM pedidos p
    LEFT JOIN clientes c ON p.fk_cliente_id = c.id_cliente 
    LEFT JOIN empresas e ON c.fk_empresa_id = e.id_empresa
    LEFT JOIN cliente_entrega ce ON p.fk_entrega_id = ce.id_entrega
    LEFT JOIN cliente_bairro cb ON ce.fk_Bairro_id_bairro = cb.id_bairro
    LEFT JOIN pagamento pg ON p.fk_pagamento_id = pg.id_pagamento
    WHERE 1=1 
    AND DATE(p.data_pedido) IN ($placeholders) ";

    if ($mostrarApenasRetirada) {
        $sql .= "AND p.is_retirada = 1 ";
    } elseif ($mostrarApenasPendentes) {
        $sql .= "AND p.status_pagamento = 0 AND c.fk_empresa_id IS NULL ";
    } elseif (isset($_GET['empresas'])) {
        $sql .= "AND c.fk_empresa_id IS NOT NULL ";
    }

    if (!empty($filtros)) {
        $sql .= " AND " . implode(" AND ", $filtros);
    }

    $sql .= " ORDER BY p.data_pedido DESC, p.hora_pedido DESC";

    $stmt = $pdo->prepare($sql);
    // Combinar os parâmetros das datas com os outros parâmetros
    $todos_params = array_merge($datas_pagina, $params);
    $stmt->execute($todos_params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $pedidos = [];
}

// Buscar total de pedidos para cada cliente
$totais_pedidos = [];
$sql_totais = "SELECT fk_cliente_id, COUNT(*) as total 
               FROM pedidos 
               GROUP BY fk_cliente_id";
$stmt_totais = $pdo->query($sql_totais);
while ($total = $stmt_totais->fetch(PDO::FETCH_ASSOC)) {
    $totais_pedidos[$total['fk_cliente_id']] = $total['total'];
}

// Busca todos os métodos de pagamento de uma vez só
$sql_pagamentos = "SELECT id_pagamento, metodo_pagamento FROM pagamento ORDER BY metodo_pagamento";
$stmt_pagamentos = $pdo->query($sql_pagamentos);
$metodos_pagamento = $stmt_pagamentos->fetchAll(PDO::FETCH_ASSOC);

// Coleta todos os IDs dos pedidos
$pedidos_ids = array_map(function($pedido) {
    return $pedido['id_pedido'];
}, $pedidos);

// Busca todos os itens de todos os pedidos de uma vez só
if (!empty($pedidos_ids)) {
    $sql_itens = "SELECT 
        pi.*,
        p.nome_produto,
        pi.fk_pedido_id,
        GROUP_CONCAT(
            CONCAT(
                sa.nome_subacomp,
                CASE 
                    WHEN pia.preco_unitario > 0 
                    THEN CONCAT(' (R$ ', FORMAT(pia.preco_unitario, 2, 'pt_BR'), ')')
                    ELSE ''
                END
            )
            ORDER BY sa.nome_subacomp
            SEPARATOR '<br>'
        ) as acompanhamentos,
        (
            pi.quantidade * pi.preco_unitario + 
            COALESCE((
                SELECT SUM(pia2.quantidade * pia2.preco_unitario)
                FROM pedido_item_acomp pia2
                WHERE pia2.fk_pedido_item_id = pi.id_pedido_item
            ), 0)
        ) as valor_total_item
    FROM pedido_itens pi
    LEFT JOIN produto p ON pi.fk_produto_id = p.id_produto
    LEFT JOIN pedido_item_acomp pia ON pi.id_pedido_item = pia.fk_pedido_item_id
    LEFT JOIN sub_acomp sa ON pia.fk_subacomp_id = sa.id_subacomp
    WHERE pi.fk_pedido_id IN (" . implode(',', $pedidos_ids) . ")
    GROUP BY pi.id_pedido_item";

    $stmt_itens = $pdo->query($sql_itens);
    $todos_itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    // Organiza os itens por pedido
    $itens_por_pedido = [];
    foreach ($todos_itens as $item) {
        $pedido_id = $item['fk_pedido_id'];
        if (!isset($itens_por_pedido[$pedido_id])) {
            $itens_por_pedido[$pedido_id] = [];
        }
        $itens_por_pedido[$pedido_id][] = $item;
    }
}

// Consulta para contar o total de registros
$sql_count = "SELECT COUNT(*) as total FROM pedidos p
              LEFT JOIN clientes c ON p.fk_cliente_id = c.id_cliente 
              LEFT JOIN empresas e ON c.fk_empresa_id = e.id_empresa
              WHERE 1=1 ";

if ($mostrarApenasRetirada) {
    $sql_count .= "AND p.is_retirada = 1 ";
} elseif ($mostrarApenasPendentes) {
    $sql_count .= "AND p.status_pagamento = 0 AND c.fk_empresa_id IS NULL ";
} elseif (isset($_GET['empresas'])) {
    $sql_count .= "AND c.fk_empresa_id IS NOT NULL ";
}

if (!empty($filtros)) {
    $sql_count .= " AND " . implode(" AND ", $filtros);
}

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Pedidos - Lunch&Fit</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/relatorio_pedidos.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="../assets/css/relatorio.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modal-detalhes.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <style>
        .paginacao {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .paginacao button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: #fff;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s ease;
            min-width: 40px;
        }

        .paginacao button:not(.disabled):hover {
            background: #f0f0f0;
            border-color: #999;
        }

        .paginacao button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .paginacao span {
            font-size: 14px;
            color: #666;
        }

        .pagina-info {
            padding: 0 10px;
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .ir-para-pagina {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }

        .ir-para-pagina input {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 60px;
            text-align: center;
        }

        .btn-ir {
            padding: 6px 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-ir:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <!-- Cabeçalho com Filtros -->
            <div class="filtros">
                <div class="btn-group">
                    <a href="?" class="btn btn-primary <?php echo !isset($_GET['retirada']) && !isset($_GET['empresas']) ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Ver Todos
                    </a>
                    <a href="?retirada" class="btn btn-primary <?php echo isset($_GET['retirada']) ? 'active' : ''; ?>">
                        <i class="fas fa-store"></i> Ver Retiradas
                    </a>
                    <a href="?empresas" class="btn btn-primary <?php echo isset($_GET['empresas']) ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i> Ver Empresas
                    </a>
                    <a href="?pendentes" class="btn btn-primary <?php echo isset($_GET['pendentes']) ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Ver Pendentes
                    </a>
                </div>
            </div>

            <!-- Adicionar aqui os botões de seleção e exclusão -->
            <div class="actions-bar">
                <button id="selecionar-todos" class="btn-secundario">
                    <i class="fas fa-check-square"></i> Selecionar Todos
                </button>
                
                <button id="excluir-selecionados" class="btn-danger" style="display: none;">
                    Excluir Selecionados (<span id="count-selecionados">0</span>)
                </button>
            </div>

            <!-- Cabeçalho com Total e Paginação -->
            <div class="relatorio-header" style="text-align: center;">
                <span class="total-registros">Total: <?php echo $total_registros; ?> pedidos</span>
                <div class="paginacao">
                    <?php if ($total_paginas > 1): ?>
                        <!-- Primeira página -->
                        <button class="btn-acao <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" 
                                onclick="mudarPagina(1)" 
                                <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                        
                        <!-- Página anterior -->
                        <button class="btn-acao <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" 
                                onclick="mudarPagina(<?php echo $pagina_atual - 1; ?>)" 
                                <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-angle-left"></i>
                        </button>

                        <!-- Número da página atual e total -->
                        <div class="pagina-info">
                            Página <span class="pagina-atual"><?php echo $pagina_atual; ?></span> 
                            de <span class="total-paginas"><?php echo $total_paginas; ?></span>
                        </div>

                        <!-- Próxima página -->
                        <button class="btn-acao <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>" 
                                onclick="mudarPagina(<?php echo $pagina_atual + 1; ?>)" 
                                <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>>
                            <i class="fas fa-angle-right"></i>
                        </button>

                        <!-- Última página -->
                        <button class="btn-acao <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>" 
                                onclick="mudarPagina(<?php echo $total_paginas; ?>)" 
                                <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>>
                            <i class="fas fa-angle-double-right"></i>
                        </button>

                        <!-- Ir para página específica -->
                        <div class="ir-para-pagina">
                            <input type="number" id="pagina-input" min="1" max="<?php echo $total_paginas; ?>" 
                                   value="<?php echo $pagina_atual; ?>" style="width: 60px;">
                            <button onclick="irParaPagina()" class="btn-ir">Ir</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
<button id="btn_filtrar">
                    <i class="fas fa-search"></i>
                    Filtrar
                </button>
            <!-- Lista de Pedidos -->
            <div class="tabela-pedidos">
                <!-- Cabeçalho da Tabela -->
                <div class="tabela-header">
                    <div class="coluna-data">Data</div>
                    <div class="coluna-hora">Hora</div>
                    <div class="coluna-cliente">Cliente</div>
                    <div class="coluna-endereco">Endereço</div>
                    <div class="coluna-pagamento">Pagamento</div>
                    <div class="coluna-status">Status</div>
                    <div class="coluna-taxa">Taxa</div>
                    <div class="coluna-valor">Valor</div>
                    <div class="coluna-valor-banco">Total Banco</div>
                    <div class="coluna-acoes">Ações</div>
                </div>

                <!-- Grupos de Pedidos por Data -->
                <?php 
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

                // Ordena os pedidos dentro de cada grupo
                foreach ($pedidos_por_data as &$grupo) {
                    usort($grupo['pedidos'], function($a, $b) {
                        return $a['num_pedido'] - $b['num_pedido'];
                    });
                }
                unset($grupo); // Limpa a referência do foreach

                foreach ($pedidos_por_data as $data => $grupo): ?>
                    <div class="grupo-pedidos">
                        <div class="grupo-header">
                            <div class="data-section">
                                <span class="data"><?php echo date('d/m', strtotime($data)); ?></span>
                            </div>
                            
                            <div class="info-section">
                                <div class="stat">
                                    <i class="fas fa-shopping-bag"></i>
                                    <span class="stat-value"><?php echo $grupo['total_pedidos']; ?> pedidos</span>
                                </div>
                                
                                <div class="stat">
                                    <span class="stat-value valor-total">
                                        R$ <?php echo number_format($grupo['valor_total'], 2, ',', '.'); ?>
                                    </span>
                                </div>

                                <!-- Adicionando o filtro de pagamento -->
                                <div class="filtro-pagamento">
                                    <select class="filtro-pagamento-select" data-data="<?php echo $data; ?>">
                                        <option value="">Todos os pagamentos</option>
                                        <?php
                                        foreach ($metodos_pagamento as $metodo): ?>
                                            <option value="<?php echo $metodo['id_pagamento']; ?>">
                                                <?php echo $metodo['metodo_pagamento']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button class="btn-expandir">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="pedidos-do-dia" style="display: none;">
                            <!-- Aqui vão os pedidos individuais -->
                            <?php foreach ($grupo['pedidos'] as $pedido): ?>
                                <!-- Estrutura do pedido para desktop -->
                                <div class="pedido" data-pedido-id="<?php echo $pedido['id_pedido']; ?>">
                                    <div class="coluna-numero">#<?php echo str_pad($pedido['num_pedido'], 3, '0', STR_PAD_LEFT); ?></div>
                                    <div class="coluna-hora">
                                        <?php 
                                            echo date('H:i', strtotime($pedido['hora_pedido']));
                                        ?>
                                    </div>
                                    
                                    <div class="coluna-cliente">
                                        <span class="nome"><?php echo explode(' ', $pedido['nome_cliente'])[0]; ?></span>
                                        <span class="telefone"><?php echo $pedido['telefone_cliente']; ?></span>
                                    </div>
                                    
                                    <div class="coluna-endereco">
                                        <span><?php echo $pedido['nome_bairro']; ?></span>
                                    </div>
                                    
                                    <div class="coluna-pagamento">
                                        <select class="pagamento-select" data-pedido-id="<?php echo $pedido['id_pedido']; ?>">
                                            <?php foreach ($metodos_pagamento as $metodo): ?>
                                                <option value="<?php echo $metodo['id_pagamento']; ?>" 
                                                        <?php echo ($pedido['fk_pagamento_id'] == $metodo['id_pagamento']) ? 'selected' : ''; ?>>
                                                    <?php echo $metodo['metodo_pagamento']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="coluna-status">
                                        <span class="status-badge <?php echo $pedido['status_pagamento'] ? 'pago' : 'pendente'; ?>" 
                                              data-pedido-id="<?php echo $pedido['id_pedido']; ?>"
                                              data-status="<?php echo $pedido['status_pagamento']; ?>">
                                            <?php echo $pedido['status_pagamento'] ? 'PAGO' : 'PENDENTE'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="coluna-taxa">
                                        R$ <?php echo number_format($pedido['taxa_entrega'], 2, ',', '.'); ?>
                                    </div>
                                    
                                    <div class="coluna-valor">
                                        <?php 
                                        $itens = buscarItensPedido($itens_por_pedido, $pedido['id_pedido']);
                                        $total_calculado = 0;
                                        foreach ($itens as $item) {
                                            $total_calculado += $item['valor_total_item'];
                                        }
                                        echo "R$ " . number_format($total_calculado, 2, ',', '.');
                                        ?>
                                    </div>
                                    
                                    <div class="coluna-valor-banco">
                                        <?php
                                        $total_banco = $pedido['sub_total'];
                                        echo "R$ " . number_format($total_banco, 2, ',', '.');
                                        
                                        // Verifica diferenças entre valores calculados e banco
                                        $tem_diferenca = false;
                                        
                                        // Verifica se é retirada com taxa de entrega
                                        if ($pedido['nome_bairro'] === 'Retirada Local' && $pedido['taxa_entrega'] > 0) {
                                            $tem_diferenca = true;
                                            echo "<button class='btn-atualizar-taxa' 
                                                        onclick='atualizarTaxaRetirada({$pedido['id_pedido']})' 
                                                        title='Clique para zerar a taxa de entrega desta retirada'>
                                                    <i class='fas fa-sync-alt'></i>
                                                </button>";
                                        }
                                        
                                        // Verifica diferença entre valor calculado e valor do banco
                                        if (abs($total_calculado - $total_banco) > 0.01) {
                                            $tem_diferenca = true;
                                        }
                                        
                                        if ($tem_diferenca) {
                                            echo "<button class='btn-diferenca' title='Há diferença entre valores ou taxa de entrega incorreta para retirada'>
                                                    <i class='fas fa-exclamation-triangle'></i>
                                                  </button>";
                                        }
                                        ?>
                                    </div>
                                    
                                    <div class="coluna-acoes">
                                        <button class="btn-acao visualizar"><i class="fas fa-eye"></i></button>
                                        <button class="btn-acao editar"><i class="fas fa-edit"></i></button>
                                        <button class="btn-acao excluir"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/menu.js"></script>
    <script src="../assets/js/relatorio.js"></script>

    <!-- Modal de Visualização -->
    <div id="modalDetalhes" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- O conteúdo será inserido dinamicamente via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="modalEditarPedido" class="modal" data-pedido-id="">
        <input type="hidden" id="pedido_id">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Pedido <span id="numeroPedidoModal"></span></h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Informações do Cliente -->
                <div class="secao-modal">
                    <h3>Informações do Cliente</h3>
                    <div class="form-group">
                        <label>Nome:</label>
                        <input type="text" id="nome_cliente" readonly>
                        <label>Telefone:</label>
                        <input type="text" id="telefone_cliente" readonly>
                    </div>
                </div>

                <!-- Endereço de Entrega -->
                <div class="secao-modal">
                    <h3>Endereço de Entrega</h3>
                    <div class="form-group">
                        <label>Endereço:</label>
                        <input type="text" id="endereco_entrega">
                        <label>Número:</label>
                        <input type="text" id="numero_entrega">
                        <label>Bairro:</label>
                        <select id="bairro_entrega" name="bairro_id" required>
                            <?php
                            $sql_bairros = "SELECT id_bairro, nome_bairro, valor_taxa 
                                            FROM cliente_bairro 
                                            ORDER BY nome_bairro";
                            $stmt_bairros = $pdo->query($sql_bairros);
                            while ($bairro = $stmt_bairros->fetch()) {
                                echo "<option value='{$bairro['id_bairro']}'>{$bairro['nome_bairro']} - R$ {$bairro['valor_taxa']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Itens do Pedido -->
                <div class="secao-modal">
                    <h3>Itens do Pedido</h3>
                    <button type="button" id="btn_adicionar_item" class="btn-acao">
                        <i class="fas fa-plus"></i> Adicionar Item
                    </button>
                    
                    <div id="lista_itens"></div>

                    <!-- Modal para adicionar/editar item -->
                    <div id="modalItem" class="modal">
                        <div class="modal-content">
                            <h3>Adicionar/Editar Item</h3>
                            <select id="produto_select" required>
                                <option value="">Selecione um produto</option>
                                <?php
                                $sql_produtos = "SELECT id_produto, nome_produto, preco_produto 
                                               FROM produto 
                                               WHERE activated = 1 
                                               ORDER BY nome_produto";
                                $stmt_produtos = $pdo->query($sql_produtos);
                                while ($produto = $stmt_produtos->fetch()) {
                                    echo "<option value='{$produto['id_produto']}' 
                                          data-preco='{$produto['preco_produto']}'>{$produto['nome_produto']}</option>";
                                }
                                ?>
                            </select>

                            <div id="acompanhamentos_container">
                                <!-- Será preenchido via AJAX baseado no produto selecionado -->
                            </div>

                            <input type="number" id="quantidade_item" min="1" value="1">
                            <button type="button" id="btn_salvar_item">Salvar Item</button>
                            <button type="button" class="close-modal-item">Cancelar</button>
                        </div>
                    </div>
                </div>

                <!-- Pagamento -->
                <div class="secao-modal">
                    <h3>Pagamento</h3>
                    <div class="form-group">
                        <label>Método de Pagamento:</label>
                        <select id="metodo_pagamento" name="metodo_pagamento" required>
                            <?php
                            foreach ($metodos_pagamento as $metodo): ?>
                                <option value="<?php echo $metodo['id_pagamento']; ?>">
                                    <?php echo $metodo['metodo_pagamento']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label>Taxa de Entrega:</label>
                        <input type="number" id="taxa_entrega" step="0.01">
                        <label>Total:</label>
                        <input type="text" id="total_pedido" readonly>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn_salvar_pedido" class="btn-primary">Salvar Alterações</button>
                <button class="btn-secondary close-modal">Cancelar</button>
            </div>
        </div>
    </div>
</body>
</html>
