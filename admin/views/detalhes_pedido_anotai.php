<?php
require_once 'ApiAnotai.php';

// Carrega o token do arquivo de configuração
$config = require_once __DIR__ . '/../config/anotai.php';
$token = $config['token'];

if (!isset($_GET['id'])) {
    echo 'ID do pedido não fornecido';
    exit;
}

$api = new ApiAnotai($token);

try {
    $pedido = $api->buscarPedido($_GET['id']);
    $info = $pedido['info'] ?? null;

    if (!$info) {
        throw new Exception('Pedido não encontrado');
    }
} catch (Exception $e) {
    echo 'Erro ao buscar pedido: ' . htmlspecialchars($e->getMessage());
    exit;
}

// Formata o valor monetário
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Formata o endereço completo
function formatarEndereco($endereco) {
    $partes = [];
    
    if (!empty($endereco['streetName'])) {
        $partes[] = $endereco['streetName'];
        if (!empty($endereco['streetNumber'])) {
            $partes[0] .= ", " . $endereco['streetNumber'];
        }
    }
    
    if (!empty($endereco['complement'])) {
        $partes[] = $endereco['complement'];
    }
    
    if (!empty($endereco['neighborhood'])) {
        $partes[] = $endereco['neighborhood'];
    }
    
    if (!empty($endereco['city'])) {
        $cidade = $endereco['city'];
        if (!empty($endereco['state'])) {
            $cidade .= "/" . $endereco['state'];
        }
        $partes[] = $cidade;
    }
    
    if (!empty($endereco['reference'])) {
        $partes[] = "Referência: " . $endereco['reference'];
    }
    
    return implode(" - ", $partes);
}

?>

<div class="container-fluid p-3">
    <div class="row">
        <!-- Coluna da Esquerda -->
        <div class="col-md-6">
            <!-- Informações do Cliente -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        Informações do Cliente
                    </h5>
                </div>
                <div class="card-body">
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($info['customer']['name'] ?? 'N/A'); ?></p>
                    <p><strong>Telefone:</strong> <?php echo htmlspecialchars($info['customer']['phone'] ?? 'N/A'); ?></p>
                    <p><strong>CPF/CNPJ:</strong> <?php echo htmlspecialchars($info['customer']['taxPayerIdentificationNumber'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <?php if (!empty($info['deliveryAddress'])): ?>
            <!-- Endereço de Entrega -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        Endereço de Entrega
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><?php echo htmlspecialchars(formatarEndereco($info['deliveryAddress'])); ?></p>
                    <?php if (!empty($info['deliveryAddress']['postalCode'])): ?>
                        <p class="mb-2"><strong>CEP:</strong> <?php echo htmlspecialchars($info['deliveryAddress']['postalCode']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($info['deliveryFee'])): ?>
                        <p class="mb-0"><strong>Taxa de Entrega:</strong> <?php echo formatarMoeda($info['deliveryFee']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Informações do Pedido -->
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informações do Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Tipo:</strong> <?php echo htmlspecialchars($info['type'] ?? 'N/A'); ?></p>
                            <p><strong>Canal:</strong> <?php echo htmlspecialchars($info['salesChannel'] ?? 'N/A'); ?></p>
                            <p><strong>Origem:</strong> <?php echo htmlspecialchars($info['from'] ?? 'N/A'); ?></p>
                            <p><strong>Referência:</strong> #<?php echo htmlspecialchars($info['shortReference'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Criado em:</strong><br>
                                <?php echo isset($info['createdAt']) ? date('d/m/Y H:i:s', strtotime($info['createdAt'])) : 'N/A'; ?>
                            </p>
                            <p><strong>Atualizado em:</strong><br>
                                <?php echo isset($info['updatedAt']) ? date('d/m/Y H:i:s', strtotime($info['updatedAt'])) : 'N/A'; ?>
                            </p>
                        </div>
                    </div>
                    <?php if (!empty($info['observation'])): ?>
                        <div class="alert alert-info mt-2">
                            <strong>Observação:</strong><br>
                            <?php echo nl2br(htmlspecialchars($info['observation'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Coluna da Direita -->
        <div class="col-md-6">
            <!-- Itens do Pedido -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Itens do Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qtd</th>
                                    <th>Preço</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($info['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                            <?php if (!empty($item['subItems'])): ?>
                                                <ul class="list-unstyled ms-3 mb-0 small">
                                                    <?php foreach ($item['subItems'] as $subItem): ?>
                                                        <li>• <?php echo htmlspecialchars($subItem['name']); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td><?php echo formatarMoeda($item['price']); ?></td>
                                        <td><?php echo formatarMoeda($item['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                    <td><?php echo formatarMoeda($info['total'] - ($info['deliveryFee'] ?? 0)); ?></td>
                                </tr>
                                <?php if (!empty($info['deliveryFee'])): ?>
                                    <tr class="table-info">
                                        <td colspan="3" class="text-end"><strong>Taxa de Entrega:</strong></td>
                                        <td><?php echo formatarMoeda($info['deliveryFee']); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($info['discounts'])): ?>
                                    <?php foreach ($info['discounts'] as $discount): ?>
                                        <tr class="table-warning">
                                            <td colspan="3" class="text-end"><strong>Desconto:</strong></td>
                                            <td>-<?php echo formatarMoeda($discount['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <tr class="table-success">
                                    <td colspan="3" class="text-end"><strong>Total Final:</strong></td>
                                    <td><strong><?php echo formatarMoeda($info['total']); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Formas de Pagamento -->
            <?php if (!empty($info['payments'])): ?>
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>
                            Formas de Pagamento
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Forma</th>
                                        <th>Valor</th>
                                        <th>Troco</th>
                                        <th>Pré-pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($info['payments'] as $payment): ?>
                                        <tr>
                                            <td>
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
                                            </td>
                                            <td><?php echo formatarMoeda($payment['value']); ?></td>
                                            <td><?php echo $payment['changeFor'] ? formatarMoeda($payment['changeFor']) : '-'; ?></td>
                                            <td>
                                                <?php if ($payment['prepaid']): ?>
                                                    <span class="badge bg-success">Sim</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Não</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div> 