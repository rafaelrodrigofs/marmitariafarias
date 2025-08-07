<?php
require_once 'ApiAnotai.php';

// Carrega o token do arquivo de configuração
$config = require_once __DIR__ . '/../config/anotai.php';
$token = $config['token'];

$api = new ApiAnotai($token);

// Parâmetros da página
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$status = isset($_GET['status']) ? intval($_GET['status']) : null;
$mostrar_ifood = isset($_GET['ifood']) ? $_GET['ifood'] === '1' : false;

try {
    // Busca os pedidos de acordo com os filtros
    if ($status !== null) {
        $resultado = $api->listarPedidosPorStatus($status);
    } else {
        $resultado = $api->listarPedidos($pagina, !$mostrar_ifood);
    }

    $pedidos = $resultado['info']['docs'] ?? [];
    $total_pedidos = $resultado['info']['count'] ?? 0;
    $limite_por_pagina = $resultado['info']['limit'] ?? 100;
    $total_paginas = ceil($total_pedidos / $limite_por_pagina);
} catch (Exception $e) {
    $erro = $e->getMessage();
}

// Array com descrição dos status
$status_descricao = [
    0 => 'Em análise',
    1 => 'Em produção',
    2 => 'Pronto',
    3 => 'Finalizado',
    4 => 'Cancelado',
    5 => 'Negado',
    6 => 'Solicitação de cancelamento'
];

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Anota AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.9em;
            padding: 5px 10px;
        }
        .pedido-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .pedido-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .pedido-card .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .pedido-card .card-footer {
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        .filtros {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .border-bottom {
            border-bottom: 1px solid rgba(0,0,0,0.1) !important;
        }
        h6 {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .fs-5 {
            font-size: 1.1rem !important;
        }
        .text-success {
            color: #28a745 !important;
        }
        .badge {
            padding: 0.5em 0.8em;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Pedidos Anota AI</h1>

        <?php if (isset($erro)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filtros mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <?php foreach ($status_descricao as $key => $descricao): ?>
                            <option value="<?php echo $key; ?>" <?php echo isset($_GET['status']) && $_GET['status'] == $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($descricao); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mostrar pedidos iFood</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ifood" value="1" 
                               <?php echo $mostrar_ifood ? 'checked' : ''; ?> 
                               onchange="this.form.submit()">
                        <label class="form-check-label">Incluir pedidos iFood</label>
                    </div>
                </div>
            </form>
        </div>

        <!-- Lista de Pedidos -->
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($pedidos as $pedido): ?>
                <?php
                    // Busca detalhes completos do pedido
                    try {
                        $detalhes = $api->buscarPedido($pedido['_id']);
                        $info = $detalhes['info'] ?? null;
                    } catch (Exception $e) {
                        $info = null;
                    }
                ?>
                <div class="col">
                    <div class="card pedido-card h-100">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    Pedido #<?php echo htmlspecialchars($info['shortReference'] ?? 'N/A'); ?>
                                </h5>
                                <span class="badge bg-<?php 
                                    $status_class = 'secondary';
                                    switch($pedido['check']) {
                                        case 0: $status_class = 'warning'; break;
                                        case 1: $status_class = 'info'; break;
                                        case 2: $status_class = 'primary'; break;
                                        case 3: $status_class = 'success'; break;
                                        case 4:
                                        case 5: $status_class = 'danger'; break;
                                        case 6: $status_class = 'secondary'; break;
                                    }
                                    echo $status_class;
                                ?> status-badge">
                                    <?php echo htmlspecialchars($status_descricao[$pedido['check']] ?? 'Status desconhecido'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($info): ?>
                                <!-- Informações do Cliente -->
                                <div class="mb-3">
                                    <h6 class="border-bottom pb-2">
                                        <i class="fas fa-user me-2"></i>Cliente
                                    </h6>
                                    <p class="mb-1">
                                        <strong>Nome:</strong> <?php echo htmlspecialchars($info['customer']['name'] ?? 'N/A'); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Telefone:</strong> <?php echo htmlspecialchars($info['customer']['phone'] ?? 'N/A'); ?>
                                    </p>
                                </div>

                                <!-- Tipo e Valor -->
                                <div class="mb-3">
                                    <h6 class="border-bottom pb-2">
                                        <i class="fas fa-info-circle me-2"></i>Informações
                                    </h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1">
                                                <strong>Tipo:</strong><br>
                                                <span class="badge bg-<?php echo $info['type'] === 'DELIVERY' ? 'primary' : 'success'; ?>">
                                                    <i class="fas fa-<?php echo $info['type'] === 'DELIVERY' ? 'motorcycle' : 'store'; ?> me-1"></i>
                                                    <?php echo $info['type'] === 'DELIVERY' ? 'Entrega' : 'Retirada'; ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="col-6 text-end">
                                            <p class="mb-1">
                                                <strong>Total:</strong><br>
                                                <span class="fs-5 text-success">
                                                    <?php echo 'R$ ' . number_format($info['total'], 2, ',', '.'); ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pagamento -->
                                <div class="mb-3">
                                    <h6 class="border-bottom pb-2">
                                        <i class="fas fa-credit-card me-2"></i>Pagamento
                                    </h6>
                                    <?php foreach ($info['payments'] as $payment): ?>
                                        <p class="mb-1">
                                            <?php
                                                $icone = 'fa-money-bill';
                                                switch($payment['code']) {
                                                    case 'credit': $icone = 'fa-credit-card'; break;
                                                    case 'debit': $icone = 'fa-credit-card'; break;
                                                    case 'money': $icone = 'fa-money-bill'; break;
                                                    case 'pix': $icone = 'fa-qrcode'; break;
                                                }
                                            ?>
                                            <i class="fas <?php echo $icone; ?> me-2"></i>
                                            <?php echo htmlspecialchars(ucfirst($payment['name'])); ?>
                                            <?php if ($payment['prepaid']): ?>
                                                <span class="badge bg-success ms-2">Pré-pago</span>
                                            <?php endif; ?>
                                        </p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Origem e Data -->
                            <div class="mt-3 pt-2 border-top">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Atualizado em: <?php 
                                        echo isset($pedido['updatedAt']) 
                                            ? date('d/m/Y H:i:s', strtotime($pedido['updatedAt']))
                                            : 'N/A';
                                    ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <button class="btn btn-primary btn-sm w-100" 
                                    onclick="verDetalhesPedido('<?php echo $pedido['_id']; ?>')">
                                <i class="fas fa-eye me-1"></i> Ver Detalhes Completos
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Navegação de páginas" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $pagina == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php 
                                echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; 
                                echo $mostrar_ifood ? '&ifood=1' : '';
                            ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="modalDetalhesPedido" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalConteudo">
                    Carregando...
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let modalDetalhes;

        document.addEventListener('DOMContentLoaded', function() {
            modalDetalhes = new bootstrap.Modal(document.getElementById('modalDetalhesPedido'));
        });

        function verDetalhesPedido(pedidoId) {
            const modalConteudo = document.getElementById('modalConteudo');
            modalConteudo.innerHTML = 'Carregando...';
            modalDetalhes.show();

            fetch(`detalhes_pedido_anotai.php?id=${pedidoId}`)
                .then(response => response.text())
                .then(html => {
                    modalConteudo.innerHTML = html;
                })
                .catch(error => {
                    modalConteudo.innerHTML = `Erro ao carregar detalhes: ${error.message}`;
                });
        }
    </script>
</body>
</html> 