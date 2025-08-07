<?php
setlocale(LC_TIME, 'pt_BR.utf8', 'portuguese');

include_once '../config/database.php';

// Validar parâmetros
$cliente_id = filter_input(INPUT_GET, 'cliente', FILTER_VALIDATE_INT);
$mes = filter_input(INPUT_GET, 'mes', FILTER_DEFAULT);
$token = filter_input(INPUT_GET, 'token', FILTER_DEFAULT);

// Sanitização adicional
$mes = htmlspecialchars($mes ?? '');
$token = htmlspecialchars($token ?? '');

if (!$cliente_id || !$mes || !$token) {
    die('Parâmetros inválidos');
}

// Buscar dados do cliente e empresa
$stmt = $pdo->prepare("
    SELECT 
        c.nome_cliente,
        e.nome_empresa,
        e.cnpj
    FROM clientes c
    JOIN empresas e ON c.fk_empresa_id = e.id_empresa
    WHERE c.id_cliente = ?
");
$stmt->execute([$cliente_id]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dados) {
    die('Cliente não encontrado');
}

// Buscar pedidos do mês para este cliente específico
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        GROUP_CONCAT(
            CONCAT(pi.quantidade, 'x ', prod.nome_produto)
            SEPARATOR '\n'
        ) as produtos
    FROM pedidos p
    JOIN pedido_itens pi ON p.id_pedido = pi.fk_pedido_id
    JOIN produto prod ON pi.fk_produto_id = prod.id_produto
    WHERE p.fk_cliente_id = ?
    AND DATE_FORMAT(p.data_pedido, '%Y-%m') = ?
    GROUP BY p.id_pedido
    ORDER BY p.data_pedido DESC
");
$stmt->execute([$cliente_id, $mes]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totais
$total_pedidos = count($pedidos);
$subtotal = array_sum(array_map(function($p) { return $p['sub_total']; }, $pedidos));
$total_taxas = array_sum(array_map(function($p) { return $p['taxa_entrega'] ?? 0; }, $pedidos));
$total_valor = $subtotal + $total_taxas;

// Função para formatar o mês em português
function formatarMesAno($data) {
    $meses = [
        '01' => 'Janeiro',
        '02' => 'Fevereiro',
        '03' => 'Março',
        '04' => 'Abril',
        '05' => 'Maio',
        '06' => 'Junho',
        '07' => 'Julho',
        '08' => 'Agosto',
        '09' => 'Setembro',
        '10' => 'Outubro',
        '11' => 'Novembro',
        '12' => 'Dezembro'
    ];
    
    $partes = explode('-', $data);
    $mes = $meses[$partes[1]] ?? '';
    $ano = $partes[0] ?? '';
    
    return $mes . ' de ' . $ano;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Mensal - <?php echo htmlspecialchars($dados['nome_cliente']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/relatorio_empresa_mensal.css">
</head>
<body>
    <div class="container">
        <div class="cabecalho">
            <div class="info-empresa">
                <h1><?php echo htmlspecialchars($dados['nome_empresa']); ?></h1>
                <div class="info-cliente">
                    Cliente: <?php echo htmlspecialchars($dados['nome_cliente']); ?>
                </div>
                <div class="info-periodo">
                    Relatório de Pedidos - <?php echo formatarMesAno($mes); ?>
                </div>
            </div>
        </div>

        <div class="resumo">
            <div class="card-resumo">
                <h3>Total de Pedidos</h3>
                <div class="valor"><?php echo $total_pedidos; ?></div>
            </div>
            <div class="card-resumo">
                <h3>Valor Total</h3>
                <div class="valor">R$ <?php echo number_format($total_valor, 2, ',', '.'); ?></div>
            </div>
        </div>

        <table class="pedidos-lista">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Nº Pedido</th>
                    <th>Produtos</th>
                    <th>Valor Produtos</th>
                    <th>Taxa</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $pedido): 
                    $valor_total = $pedido['sub_total'] + ($pedido['taxa_entrega'] ?? 0);
                ?>
                    <tr>
                        <td data-label="Data"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                        <td data-label="Nº Pedido">#<?php echo $pedido['num_pedido']; ?></td>
                        <td data-label="Produtos"><?php echo nl2br(htmlspecialchars($pedido['produtos'])); ?></td>
                        <td data-label="Valor Produtos">R$ <?php echo number_format($pedido['sub_total'], 2, ',', '.'); ?></td>
                        <td data-label="Taxa">R$ <?php echo number_format($pedido['taxa_entrega'] ?? 0, 2, ',', '.'); ?></td>
                        <td data-label="Total" class="valor-total">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></td>
                        <td data-label="Status">
                            <span class="status-badge <?php echo $pedido['status_pagamento'] ? 'pago' : 'pendente'; ?>">
                                <?php echo $pedido['status_pagamento'] ? 'PAGO' : 'PENDENTE'; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="no-print" style="margin-top: 30px; text-align: center; display: flex; gap: 10px; justify-content: center;">
            <button onclick="capturarRelatorio()" class="btn-acao">
                <i class="fas fa-camera"></i>
                Capturar Relatório
            </button>
            <button onclick="copiarLink()" class="btn-acao">
                <i class="fas fa-link"></i>
                Copiar Link
            </button>
        </div>

        <!-- Canvas escondido para gerar a imagem -->
        <canvas id="canvas" style="display: none;"></canvas>

        <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
        <script>
        async function capturarRelatorio() {
            const relatorio = document.querySelector('.container');
            const botoes = document.querySelector('.no-print');
            
            // Esconde os botões temporariamente
            botoes.style.display = 'none';
            
            try {
                // Captura o conteúdo como imagem
                const canvas = await html2canvas(relatorio, {
                    scale: 2, // Melhor qualidade
                    backgroundColor: '#ffffff'
                });
                
                // Converte para imagem e copia para o clipboard
                canvas.toBlob(function(blob) {
                    const item = new ClipboardItem({ "image/png": blob });
                    navigator.clipboard.write([item]).then(function() {
                        alert('Imagem do relatório copiada com sucesso!');
                    }).catch(function(error) {
                        console.error('Erro ao copiar imagem:', error);
                        // Fallback: fazer download se não conseguir copiar
                        const link = document.createElement('a');
                        link.download = 'relatorio.png';
                        link.href = canvas.toDataURL();
                        link.click();
                    });
                });
            } catch (error) {
                console.error('Erro ao capturar relatório:', error);
                alert('Erro ao capturar relatório');
            } finally {
                // Restaura os botões
                botoes.style.display = 'flex';
            }
        }

        function copiarLink() {
            const url = window.location.href;
            const nomeCliente = '<?php echo htmlspecialchars($dados['nome_cliente']); ?>';
            const periodo = '<?php echo formatarMesAno($mes); ?>';
            
            const mensagem = `Olá ${nomeCliente}! 👋

📊 Segue o link do seu relatório mensal de pedidos da Lunchefit referente a ${periodo}:

${url}

Qualquer dúvida estamos à disposição! 😊

Atenciosamente,
Equipe Lunchefit 🍱`;

            navigator.clipboard.writeText(mensagem).then(function() {
                alert('Mensagem copiada com sucesso!');
            }).catch(function(error) {
                console.error('Erro ao copiar mensagem:', error);
                alert('Erro ao copiar mensagem');
            });
        }
        </script>
    </div>
</body>
</html> 