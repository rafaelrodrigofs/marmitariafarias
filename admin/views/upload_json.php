<?php
require_once '../config/database.php';

// Habilitar debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once '../includes/functionsUpload.php';

// Buscar informações das categorias de acompanhamentos e verificar se estão associadas ao produto
function buscarCategoriaAcompanhamento($pdo, $nomeSubAcomp, $nomeProduto) {
    // Primeiro busca o produto
    $stmt = $pdo->prepare("SELECT id_produto FROM produto WHERE nome_produto = ?");
    $stmt->execute([$nomeProduto]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        return 'Produto não encontrado';
    }
    
    // Busca a categoria do subacompanhamento considerando apenas os acompanhamentos associados ao produto
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.id_acomp, a.nome_acomp 
        FROM sub_acomp sa 
        JOIN acomp a ON sa.fk_acomp_id = a.id_acomp 
        JOIN produto_acomp pa ON a.id_acomp = pa.fk_acomp_id
        WHERE sa.nome_subacomp = ?
        AND pa.fk_produto_id = ?
    ");
    $stmt->execute([$nomeSubAcomp, $produto['id_produto']]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resultado) {
        return 'Categoria não permitida para este produto';
    }
    
    return $resultado['nome_acomp'];
}

// Organizar acompanhamentos por categoria
function organizarAcompanhamentosPorCategoria($pdo, $acompanhamentos, $nomeProduto) {

    $categorizados = [];
    $todasCategoriasValidas = true;
    
    foreach ($acompanhamentos as $index => $acomp) {
        
        $nomeAcomp = is_array($acomp) ? $acomp['nome'] : $acomp;
        
        $categoriaAcomp = buscarCategoriaAcompanhamento($pdo, $nomeAcomp, $nomeProduto);
        
        // Se a categoria não foi encontrada ou não é permitida
        if ($categoriaAcomp === 'Categoria não encontrada' || 
            $categoriaAcomp === 'Categoria não permitida para este produto') {
            $todasCategoriasValidas = false;
            
        }
        
        if (!isset($categorizados[$categoriaAcomp])) {
            $categorizados[$categoriaAcomp] = [];
        }
        
        $categorizados[$categoriaAcomp][] = $acomp;
        
    }
    
    

    
    if (!$todasCategoriasValidas) {
        
    }
    
    return [
        'categorias' => $categorizados,
        'valido' => $todasCategoriasValidas
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload de Pedidos</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/upload_json.css">
    
    
    <!-- Adicione o jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Seu código JavaScript existente deve vir depois do jQuery -->
    <script>
        $(document).ready(function() {
            // ... seu código JavaScript ...
        });
    </script>

    <!-- Adicione no head -->
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-messaging-compat.js"></script>
    
    <!-- Inicialização do Firebase -->
    <script>
        // Configuração do Firebase
        const firebaseConfig = {
            apiKey: "AIzaSyBY1OF3VBtyxHRkniQm2cSeLLs9uV3Izak",
            authDomain: "lunchefit-4c903.firebaseapp.com",
            projectId: "lunchefit-4c903",
            storageBucket: "lunchefit-4c903.firebasestorage.app",
            messagingSenderId: "646788288611",
            appId: "1:646788288611:web:2d6dcf0cb4ca49593ba856",
            vapidKey: "BG7dvi1j2eq_-b-7YWTOpNXFWwNTz2W2pidCRxQdDLsQgcVMLmqa9jI8UKRoqDHkaN_2rbuUUpMW7sHol_VwqS4"
        };

        // Inicializa o Firebase
        firebase.initializeApp(firebaseConfig);
        const messaging = firebase.messaging();
    </script>
</head>
<body>

    <p><?php
    echo date_default_timezone_get(); // Mostra o fuso horário atual do PHP
    echo PHP_EOL;
    echo date('P'); // Mostra o offset (ex: -03:00)
    ?></p>
    <h2>Upload de Arquivo de Pedidos</h2>
    
    <div class="upload-form">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="arquivo_json" accept=".json" required>
            <button type="submit">Enviar Arquivo</button>
        </form>
    </div>

    <?php if ($pedidos): ?>
        <h3>Dados dos Pedidos</h3>
        <div class="acoes-em-massa">
            <button class="btn-cadastrar-todos" onclick="cadastrarTodosClientes()">
                Cadastrar Todos os Clientes e Endereços
            </button>
            <button class="btn-gravar-todos" onclick="gravarTodosPedidos()">
                Gravar Todos os Pedidos
            </button>
        </div>
        <div class="data-geral-container" style="margin: 10px 0;">
            <label for="dataGeral">Data para todos os pedidos:</label>
            <input 
                type="date" 
                id="dataGeral" 
                value="<?php echo $_SESSION['data_pedidos'] ?? date('Y-m-d'); ?>" 
                onchange="atualizarTodasDatas(this.value)"
                style="margin-left: 10px; padding: 5px;"
            >
            <!-- Adicione um elemento para debug -->
            <span id="debug-data" style="margin-left: 10px; color: #666;">
                Data da sessão: <?php echo $_SESSION['data_pedidos'] ?? 'não definida'; ?>
            </span>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Data</th>
                    <th>Horário</th>
                    <th>Origem</th>
                    <th>Cliente</th>
                    <th>Telefone</th>
                    <th>Status Cliente</th>
                    <th>Endereço</th>
                    <th>Status Endereço</th>
                    <th>Forma Pagamento</th>
                    <th>Cupom</th>
                    <th>Valores JSON</th>
                    <th>Valores Banco</th>
                    <th>Produtos</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $pedido): 
                    $telefone = $pedido['cliente']['telefone_banco'];
                    error_log("DEBUG TELEFONE - Início do processamento:");
                    error_log("1. Telefone direto do banco: " . print_r($telefone, true));
                    error_log("2. Telefone do pedido original: " . print_r($pedido['cliente']['telefone'], true));
                    error_log("3. Pedido completo: " . print_r($pedido['cliente'], true));

                    $clienteExiste = verificarCliente($pdo, $telefone);
                    error_log("4. Após verificarCliente - Telefone usado: " . print_r($telefone, true));
                    error_log("5. Cliente existe? " . ($clienteExiste ? "Sim" : "Não"));

                    $enderecoExiste = false;
                    if (isset($pedido['endereco']['rua']) && isset($pedido['endereco']['numero'])) {
                        $enderecoExiste = verificarEndereco(
                            $pdo, 
                            $telefone, 
                            $pedido['endereco']['rua'], 
                            $pedido['endereco']['numero']
                        );
                    }
                    
                    $isRetiradaLocal = isset($pedido['endereco']['endereco_completo']) && 
                                       $pedido['endereco']['endereco_completo'] === "Retirada no local";

                    $enderecoTexto = $isRetiradaLocal ? 
                        'Retirada no local' : 
                        (!empty($pedido['endereco']) ? formatarEndereco($pedido['endereco']) : 'Não informado, S/N - Não informado');
                    
                    // Informação do cupom
                    $temCupom = isset($pedido['cupom']) && !empty($pedido['cupom']['nome']);
                    $cupomNome = $temCupom ? $pedido['cupom']['nome'] : '';
                    $cupomValor = $temCupom ? $pedido['cupom']['valor'] : '0,00';
                ?>
                    <?php
                    // Busca os IDs do cliente e endereço
                    $telefone = formatarTelefoneParaBanco($pedido['cliente']['telefone']);
                    
                    // Busca ID do cliente
                    $stmtCliente = $pdo->prepare("SELECT id_cliente FROM clientes WHERE telefone_cliente = ?");
                    $stmtCliente->execute([$telefone]);
                    $clienteResult = $stmtCliente->fetch();
                    $clienteId = $clienteResult ? $clienteResult['id_cliente'] : 'Não cadastrado';
                    
                    // Busca ID do endereço se o cliente existir
                    $enderecoId = 'Não cadastrado';
                    if ($clienteResult && !empty($pedido['endereco']['rua']) && !empty($pedido['endereco']['numero'])) {
                        $stmtEndereco = $pdo->prepare("
                            SELECT id_entrega 
                            FROM cliente_entrega 
                            WHERE fk_Cliente_id_cliente = ? 
                            AND nome_entrega = ? 
                            AND numero_entrega = ?
                        ");
                        $stmtEndereco->execute([
                            $clienteResult['id_cliente'],
                            $pedido['endereco']['rua'],
                            $pedido['endereco']['numero']
                        ]);
                        $enderecoResult = $stmtEndereco->fetch();
                        $enderecoId = $enderecoResult ? $enderecoResult['id_entrega'] : 'Não cadastrado';
                    }
                    ?>
                    
                    <?php 
                    $pedidoExiste = verificarPedidoExistente($pdo, $pedido);
                    $classePedido = $pedidoExiste ? 'pedido-duplicado' : '';
                    ?>
                    
                    <tr class="<?= $classePedido ?>" 
                        data-telefone="<?= htmlspecialchars($pedido['cliente']['telefone']) ?>" 
                        data-pedido-numero="<?= htmlspecialchars($pedido['numero']) ?>"
                        data-cliente-id="<?= $clienteId ?>"
                        data-endereco-id="<?= $enderecoId ?>">
                        <td>#<?= htmlspecialchars($pedido['numero']) ?></td>
                        <td>
                            <input type="date" 
                                   class="data-pedido" 
                                   value="<?= date('Y-m-d') ?>" 
                                   style="width: 130px;">
                        </td>
                        <td><?php echo htmlspecialchars($pedido['horario']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['origem']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['cliente']['nome']); ?></td>
                        <td><?php 
                            echo "<!-- DEBUG: Telefone antes do display: $telefone -->"; 
                            echo htmlspecialchars($telefone); 
                        ?></td>
                        <td class="status-cliente <?php echo $clienteExiste ? 'status-cadastrado' : 'status-nao-cadastrado'; ?>">
                            <?php if (!$clienteExiste): ?>
                                <button class="btn-cadastrar" 
                                        onclick="cadastrarClienteIndividual(<?php 
                                            echo htmlspecialchars(json_encode([
                                                'telefone' => $pedido['cliente']['telefone'],
                                                'nome' => $pedido['cliente']['nome'],
                                                'endereco' => $pedido['endereco'] ?? null
                                            ])); 
                                        ?>)">
                                    Cadastrar Cliente e Endereço
                                </button>
                            <?php else: ?>
                                Cadastrado
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($enderecoTexto); ?></td>
                        <td class="status-endereco <?php echo ($isRetiradaLocal || $enderecoExiste) ? 'status-cadastrado' : 'status-nao-cadastrado'; ?>">
                            <?php if (!$isRetiradaLocal): ?>
                                <?php if (!$enderecoExiste && $clienteExiste): ?>
                                    <button class="btn-cadastrar" 
                                            onclick="cadastrarEnderecoAdicional(
                                                '<?php echo $telefone; ?>', 
                                                '<?php echo isset($pedido['endereco']['rua']) ? htmlspecialchars($pedido['endereco']['rua']) : ''; ?>', 
                                                '<?php echo isset($pedido['endereco']['numero']) ? htmlspecialchars($pedido['endereco']['numero']) : ''; ?>', 
                                                '<?php echo isset($pedido['endereco']['bairro']) ? htmlspecialchars($pedido['endereco']['bairro']) : ''; ?>'
                                            )">
                                        Cadastrar Endereço
                                    </button>
                                <?php else: ?>
                                    <?php echo $enderecoExiste ? 'Cadastrado' : 'Cliente não cadastrado'; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Retirada no Local
                            <?php endif; ?>
                        </td>
                        <td><?php 
                            // Verifica se é um caso especial onde o método é "Crédito"
                            if ($pedido['pagamento']['metodo'] === "Crédito") {
                                echo htmlspecialchars($pedido['pagamento']['status']);
                            } else {
                                echo htmlspecialchars($pedido['pagamento']['metodo']);
                            }
                        ?></td>
                        <td class="cupom-info">
                            <?php if ($temCupom): ?>
                                <div class="cupom-nome">Código: <?php echo htmlspecialchars($cupomNome); ?></div>
                                <div class="cupom-valor">Desconto: R$ <?php echo htmlspecialchars($cupomValor); ?></div>
                            <?php else: ?>
                                Sem cupom
                            <?php endif; ?>
                        </td>
                        <td class="valores-pedido">
                            <?php if (isset($pedido['valores'])): ?>
                                <div class="subtotal">Subtotal: R$ <?php echo $pedido['valores']['subtotal']; ?></div>
                                <div class="taxa">Taxa: R$ <?php echo $pedido['valores']['taxa_entrega']; ?></div>
                                <div class="total">Total: R$ <?php echo $pedido['valores']['total']; ?></div>
                            <?php else: ?>
                                Valores não informados
                            <?php endif; ?>
                        </td>
                        <td class="valores-banco">
                            <?php 
                            // Pega a data do input da linha atual
                            $dataSelecionada = isset($_POST['data_pedido']) ? $_POST['data_pedido'] : (
                                isset($_SESSION['data_pedidos']) ? $_SESSION['data_pedidos'] : date('Y-m-d')
                            );
                            
                            // Debug detalhado
                            // error_log("\n=== VERIFICAÇÃO DE VALORES DO PEDIDO ===");
                            // error_log("Data Selecionada: " . $dataSelecionada);
                            // error_log("Número do Pedido: " . $pedido['numero']);
                            // error_log("Telefone Banco: " . $pedido['cliente']['telefone_banco']);

                            $pedidoExiste = verificarPedidoExistente($pdo, $pedido, $dataSelecionada);
                            // error_log("Pedido existe? " . ($pedidoExiste ? "Sim" : "Não"));

                            if ($pedidoExiste) {
                                $valoresBanco = buscarValoresPedidoBanco($pdo, $pedido['numero'], $pedido['cliente']['telefone_banco'], $dataSelecionada);
                                // error_log("Valores encontrados? " . ($valoresBanco ? "Sim" : "Não"));
                            }
                            
                            if ($pedidoExiste && $valoresBanco): 
                                $classSubtotal = ($valoresBanco['subtotal'] == str_replace(',', '.', $pedido['valores']['subtotal'])) ? 'match' : 'mismatch';
                                $classTaxa = ($valoresBanco['taxa_entrega'] == str_replace(',', '.', $pedido['valores']['taxa_entrega'])) ? 'match' : 'mismatch';
                                
                                // Verifica correspondência do cupom
                                $classCupom = '';
                                if ($temCupom) {
                                    $cupomValorNumerico = str_replace(',', '.', $cupomValor);
                                    $classCupom = ($valoresBanco['cupom_valor'] == $cupomValorNumerico) ? 'match' : 'mismatch';
                                }
                                
                                $classTotal = ($valoresBanco['total'] == str_replace(',', '.', $pedido['valores']['total'])) ? 'match' : 'mismatch';
                            ?>
                                <div class="subtotal <?php echo $classSubtotal; ?>">
                                    Subtotal: R$ <?php echo number_format($valoresBanco['subtotal'], 2, ',', '.'); ?>
                                </div>
                                <?php if ($temCupom): ?>
                                <div class="cupom-valor <?php echo $classCupom; ?>">
                                    Cupom: R$ <?php echo number_format($valoresBanco['cupom_valor'] ?? 0, 2, ',', '.'); ?>
                                </div>
                                <?php endif; ?>
                                <div class="taxa <?php echo $classTaxa; ?>">
                                    Taxa: R$ <?php echo number_format($valoresBanco['taxa_entrega'], 2, ',', '.'); ?>
                                </div>
                                <div class="total <?php echo $classTotal; ?>">
                                    Total: R$ <?php echo number_format($valoresBanco['total'], 2, ',', '.'); ?>
                                </div>
                            <?php else: ?>
                                Pedido não encontrado no banco
                            <?php endif; ?>
                        </td>
                        <td class="produtos-lista">
                            <?php foreach ($pedido['itens'] as $item): ?>
                                <div class="produto-item">
                                    <span class="quantidade"><?php echo $item['quantidade']; ?>x</span>
                                    <?php 
                                    $produtoExiste = verificarProduto($pdo, $item['produto']);
                                    $statusClass = $produtoExiste ? 'status-cadastrado' : 'status-nao-cadastrado';
                                    ?>
                                    <span class="<?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($item['produto']); ?>
                                        <?php if (!$produtoExiste): ?>
                                            <button class="btn-cadastrar" onclick="abrirModalProduto('<?php echo htmlspecialchars($item['produto']); ?>')">
                                                Cadastrar
                                            </button>
                                        <?php endif; ?>
                                    </span>
                                    <div class="acompanhamentos">
                                        <?php 
                                        if (!empty($item['acompanhamentos'])) {
                                            // Passa o nome do produto para a função
                                            $acompanhamentosPorCategoria = organizarAcompanhamentosPorCategoria($pdo, $item['acompanhamentos'], $item['produto']);
                                            foreach ($acompanhamentosPorCategoria['categorias'] as $categoria => $acomps) {
                                                ?>
                                                <div class="acompanhamento-item" data-acomp="<?php echo htmlspecialchars($categoria); ?>">
                                                    - <?php echo htmlspecialchars($categoria); ?>
                                                    <?php if (!empty($acomps)): ?>
                                                        <ul>
                                                            <?php foreach ($acomps as $acomp): ?>
                                                                <?php 
                                                                    $nomeAcomp = is_array($acomp) ? $acomp['nome'] : $acomp;
                                                                    $quantidade = is_array($acomp) ? $acomp['quantidade'] : 1;
                                                                ?>
                                                                <li>
                                                                    <?php if ($quantidade > 1): ?>
                                                                        <?php echo $quantidade; ?>x 
                                                                    <?php endif; ?>
                                                                    <?php echo $nomeAcomp; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                    <span class="status <?php echo $statusClass; ?>">
                                                        <?php if (!$produtoExiste): ?>
                                                            <button class="btn-cadastrar" onclick="abrirModalAcompanhamento('<?php echo htmlspecialchars($categoria); ?>')">
                                                                Cadastrar
                                                            </button>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php 
                            $todasCategoriasValidas = true;
                            foreach ($pedido['itens'] as $item) {
                                if (!empty($item['acompanhamentos'])) {
                                    $resultado = organizarAcompanhamentosPorCategoria($pdo, $item['acompanhamentos'], $item['produto']);
                                    if (!$resultado['valido']) {
                                        $todasCategoriasValidas = false;
                                        break;
                                    }
                                }
                            }
                            
                            if ($clienteExiste && ($isRetiradaLocal || $enderecoExiste) && !$pedidoExiste && $todasCategoriasValidas): 
                            ?>
                                <button class="btn-gravar-pedido" onclick='gravarPedido(<?= json_encode($pedido) ?>)'>
                                    Gravar Pedido
                                </button>
                            <?php elseif (!$todasCategoriasValidas): ?>
                                <span class="erro-categoria">Categorias inválidas</span>
                            <?php elseif ($pedidoExiste): ?>
                                <div style="display: flex; gap: 5px;">
                                    <button class="btn-regravar-pedido" onclick='regravarPedido(<?= json_encode($pedido) ?>)'>
                                        Regravar
                                    </button>
                                    <button class="btn-excluir-pedido" onclick='excluirPedido(<?= json_encode($pedido) ?>)'>
                                        Excluir
                                    </button>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
        <p>Erro ao processar o arquivo. Certifique-se de que é um arquivo JSON válido.</p>
    <?php endif; ?>

    <!-- Modal Produto -->
    <div id="modalProduto" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modalProduto')">&times;</span>
            <h2>Cadastrar Produto</h2>
            <form id="formProduto" method="POST" action="../actions/upload_json/cadastrar_produto.php">
                <input type="hidden" id="nomeProduto" name="nome_produto">
                <label>Categoria:</label>
                <select name="categoria" required>
                    <option value="">Selecione uma categoria</option>
                    <?php
                    $stmt = $pdo->query("SELECT id_categoria, nome_categoria FROM categoria");
                    while ($cat = $stmt->fetch()) {
                        echo "<option value='{$cat['id_categoria']}'>{$cat['nome_categoria']}</option>";
                    }
                    ?>
                </select>
                <label>Preço:</label>
                <input type="number" name="preco" step="0.01" required>
                <button type="submit">Cadastrar</button>
            </form>
        </div>
    </div>

    <!-- Modal Acompanhamento -->
    <div id="modalAcompanhamento" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modalAcompanhamento')">&times;</span>
            <h2>Cadastrar Acompanhamento</h2>
            <form id="formAcompanhamento" method="POST" action="../actions/upload_json/cadastrar_acompanhamento.php">
                <input type="hidden" id="nomeAcomp" name="nome_acomp">
                <label>Grupo de Acompanhamento:</label>
                <select name="grupo_acomp" required>
                    <option value="">Selecione um grupo</option>
                    <?php
                    $stmt = $pdo->query("SELECT id_acomp, nome_acomp FROM acomp");
                    while ($acomp = $stmt->fetch()) {
                        echo "<option value='{$acomp['id_acomp']}'>{$acomp['nome_acomp']}</option>";
                    }
                    ?>
                </select>
                <label>Preço:</label>
                <input type="number" name="preco" step="0.01" value="0.00">
                <button type="submit">Cadastrar</button>
            </form>
        </div>
    </div>

    <!-- Modal Cliente e Endereço -->
    <div id="modalClienteEndereco" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modalClienteEndereco')">&times;</span>
            <h2>Cadastrar Cliente e Endereço</h2>
            <form id="formClienteEndereco" method="POST" action="../actions/upload_json/cadastrar_cliente_endereco.php">
                <h3>Dados do Cliente</h3>
                <div>
                    <label>Nome:</label>
                    <input type="text" id="nomeCliente" name="nome_cliente" required>
                </div>
                <div>
                    <label>Telefone:</label>
                    <input type="text" id="telefoneCliente" name="telefone_cliente" required>
                </div>
                <div>
                    <label>Tipo:</label>
                    <select name="tipo_cliente" required>
                        <option value="0">Pessoa Física</option>
                        <option value="1">Funcionário</option>
                    </select>
                </div>

                <h3>Dados do Endereço</h3>
                <div>
                    <label>Rua:</label>
                    <input type="text" id="ruaEndereco" name="rua" required>
                </div>
                <div>
                    <label>Número:</label>
                    <input type="text" id="numeroEndereco" name="numero" required>
                </div>
                <div>
                    <label>Bairro:</label>
                    <select name="bairro" id="bairroEndereco" required>
                        <?php
                        $stmt = $pdo->query("SELECT id_bairro, nome_bairro FROM cliente_bairro ORDER BY nome_bairro");
                        while ($bairro = $stmt->fetch()) {
                            echo "<option value='{$bairro['id_bairro']}'>{$bairro['nome_bairro']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn-cadastrar">Cadastrar</button>
            </form>
        </div>
    </div>

    <!-- Modal Endereço -->
    <div id="modalEndereco" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modalEndereco')">&times;</span>
            <h2>Cadastrar Endereço</h2>
            <form id="formEndereco" method="POST" action="../actions/upload_json/cadastrar_endereco.php">
                <input type="hidden" id="telefoneEndereco" name="telefone_cliente">
                <div>
                    <label>Rua:</label>
                    <input type="text" id="ruaEndereco" name="rua" required>
                </div>
                <div>
                    <label>Número:</label>
                    <input type="text" id="numeroEndereco" name="numero" required>
                </div>
                <div>
                    <label>Bairro:</label>
                    <select name="bairro" id="bairroEndereco" required>
                        <?php
                        $stmt = $pdo->query("SELECT id_bairro, nome_bairro FROM cliente_bairro ORDER BY nome_bairro");
                        while ($bairro = $stmt->fetch()) {
                            echo "<option value='{$bairro['id_bairro']}'>{$bairro['nome_bairro']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn-cadastrar">Cadastrar</button>
            </form>
        </div>
    </div>

    <!-- Adicione o botão onde desejar na página -->
    <button id="btnNotificar" class="btn-notificar">
        <i class="fas fa-bell"></i> Notificar
    </button>

    <script>
        function abrirModalProduto(nomeProduto) {
            document.getElementById('nomeProduto').value = nomeProduto;
            document.getElementById('modalProduto').style.display = 'block';
        }

        function abrirModalAcompanhamento(nomeAcomp) {
            document.getElementById('nomeAcomp').value = nomeAcomp;
            document.getElementById('modalAcompanhamento').style.display = 'block';
            
            // Limpa o preço ao abrir o modal
            document.querySelector('#modalAcompanhamento input[name="preco"]').value = "0.00";
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function abrirModalClienteEndereco(nome, telefone, rua, numero, bairro) {
            document.getElementById('nomeCliente').value = nome;
            document.getElementById('telefoneCliente').value = telefone;
            document.getElementById('ruaEndereco').value = rua;
            document.getElementById('numeroEndereco').value = numero;
            
            // Selecionar o bairro correto no select
            const selectBairro = document.getElementById('bairroEndereco');
            for (let i = 0; i < selectBairro.options.length; i++) {
                if (selectBairro.options[i].text.toLowerCase() === bairro.toLowerCase()) {
                    selectBairro.selectedIndex = i;
                    break;
                }
            }
            
            document.getElementById('modalClienteEndereco').style.display = 'block';
        }

        // Ajuste a função que cadastra via AJAX
        function cadastrarViaAjax(formId, url) {
            const form = document.getElementById(formId);
            if (!form) return;

            form.onsubmit = function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const data = {};
                formData.forEach((value, key) => data[key] = value);

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (formId === 'formAcompanhamento') {
                            atualizarItemAcompanhamento(document.getElementById('nomeAcomp').value);
                            fecharModal('modalAcompanhamento');
                        }
                        form.reset();
                    } else {
                        alert(data.error || 'Erro ao cadastrar');
                    }
                })
                .catch(error => {
                    // console.error('Erro:', error);
                    alert('Erro ao cadastrar');
                });
            };
        }

        // Função para atualizar a linha após cadastro do cliente
        function atualizarLinhaClienteEndereco(telefone) {
            const row = document.querySelector(`tr[data-telefone="${telefone}"]`);
            if (!row) return;

            // Buscar os IDs do cliente e endereço recém cadastrados
            fetch(`../actions/upload_json/buscar_ids_cliente.php?telefone=${telefone}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Atualizar os atributos data-* da linha
                        row.setAttribute('data-cliente-id', data.cliente_id);
                        row.setAttribute('data-endereco-id', data.endereco_id);
                        
                        // Atualizar os status visuais
                        const statusCliente = row.querySelector('.status-cliente');
                        const statusEndereco = row.querySelector('.status-endereco');
                        
                        if (statusCliente) {
                            statusCliente.classList.remove('status-nao-cadastrado');
                            statusCliente.classList.add('status-cadastrado');
                        }
                        
                        if (statusEndereco) {
                            statusEndereco.classList.remove('status-nao-cadastrado');
                            statusEndereco.classList.add('status-cadastrado');
                        }

                        // console.log('IDs atualizados:', {
                        //     telefone,
                        //     cliente_id: data.cliente_id,
                        //     endereco_id: data.endereco_id
                        // });
                    }
                })
                .catch(error => console.error('Erro ao buscar IDs:', error));
        }

        // Função para atualizar a linha após cadastro do endereço
        function atualizarLinhaEndereco(telefone) {
            const row = document.querySelector(`tr[data-telefone="${telefone}"]`);
            if (row) {
                const statusCell = row.querySelector('.status-endereco');
                statusCell.innerHTML = 'Cadastrado';
                statusCell.className = 'status-endereco status-cadastrado';
            }
        }

        // Função para atualizar o item após cadastro do acompanhamento
        function atualizarItemAcompanhamento(nomeAcomp) {
            const items = document.querySelectorAll(`.acompanhamento-item[data-acomp="${nomeAcomp}"]`);
            items.forEach(item => {
                item.querySelector('.status').innerHTML = 'Cadastrado';
                item.querySelector('.status').className = 'status status-cadastrado';
                item.querySelector('.btn-cadastrar')?.remove();
            });
        }

        // Inicializar os formulários com AJAX
        document.addEventListener('DOMContentLoaded', () => {
            cadastrarViaAjax('formClienteEndereco', '../actions/cadastrar_cliente_endereco.php');
            cadastrarViaAjax('formEndereco', 'cadastrar_endereco.php');
            cadastrarViaAjax('formAcompanhamento', 'cadastrar_acompanhamento.php');
            cadastrarViaAjax('formProduto', 'cadastrar_produto.php');
        });

        function gravarPedido(pedidoData) {
            if (!confirm('Deseja gravar este pedido?')) return;

            const scrollPosition = window.scrollY;
            const pedidoNumero = pedidoData.numero;

            const row = event.target.closest('tr');
            const dataPedido = row.querySelector('.data-pedido').value;
            
            sessionStorage.setItem('selectedDate', dataPedido);
            
            const pedidoComData = {
                ...pedidoData,
                data_pedido: dataPedido || new Date().toISOString().split('T')[0],
                total: pedidoData.valores.subtotal,
                taxa_entrega: pedidoData.valores.taxa_entrega ?? 0,
                cupom_codigo: pedidoData.cupom?.nome || null,
                cupom_valor: pedidoData.cupom?.valor ? parseFloat(pedidoData.cupom.valor.replace(',', '.')) : null
            };

            // console.log('Enviando pedido:', pedidoComData);

            fetch('../actions/upload_json/gravar_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(pedidoComData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    sessionStorage.setItem('lastPedidoNumero', pedidoNumero);
                    sessionStorage.setItem('scrollPosition', scrollPosition);
                    location.reload();
                } else {
                    alert('Erro ao gravar pedido: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                // console.error('Erro:', error);
                alert('Erro ao gravar pedido: ' + error.message);
            });
        }

        // Adicionar esta função para gerar telefone aleatório
        function gerarTelefoneAleatorio() {
            const numeros = Math.floor(Math.random() * 100000000).toString().padStart(8, '0');
            return `(00) ${numeros.slice(0,5)}-${numeros.slice(5)}`;
        }

        function cadastrarTodosClientes() {
            if (!confirm('Deseja cadastrar todos os clientes e endereços pendentes?')) return;
            
            const pedidos = <?php echo json_encode($pedidos); ?>;
            // console.log('Total de pedidos:', pedidos.length);
            
            const clientesPendentes = pedidos.filter(pedido => {
                const row = document.querySelector(`tr[data-telefone="${pedido.cliente.telefone}"]`);
                // console.log('Verificando pedido:', pedido.cliente.nome, pedido.cliente.telefone);
                
                const isPendente = row && (
                    row.querySelector('.status-cliente:not(.status-cadastrado)') || 
                    row.querySelector('.status-endereco:not(.status-cadastrado)')
                );
                // console.log('Cliente pendente?', isPendente);
                return isPendente;
            });
            
            // console.log('Clientes pendentes encontrados:', clientesPendentes.length);
            
            if (clientesPendentes.length === 0) {
                alert('Não há clientes pendentes para cadastrar!');
                return;
            }
            
            let processados = 0;
            let erros = [];
            
            clientesPendentes.forEach(pedido => {
                // console.log('Processando cliente:', pedido.cliente.nome);
                
                const dados = {
                    nome_cliente: pedido.cliente.nome,
                    telefone_cliente: pedido.cliente.telefone,
                    tipo_cliente: pedido.cliente.tipo || 0,
                    rua: pedido.endereco.rua,
                    numero: pedido.endereco.numero,
                    bairro: pedido.endereco.bairro
                };
                
                // console.log('Enviando dados:', dados);
                
                fetch('../actions/upload_json/cadastrar_cliente_endereco.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dados)
                })
                .then(response => {
                    // console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    processados++;
                    // console.log('Resposta do servidor:', data);
                    
                    if (!data.success) {
                        erros.push(`${pedido.cliente.nome}: ${data.error}`);
                    } else {
                        atualizarLinhaClienteEndereco(pedido.cliente.telefone);
                    }
                    
                    if (processados === clientesPendentes.length) {
                        if (erros.length > 0) {
                            // console.error('Erros encontrados:', erros);
                            alert(`Processo concluído com ${erros.length} erros:\n${erros.join('\n')}`);
                        } else {
                            alert('Todos os clientes e endereços foram cadastrados com sucesso!');
                            location.reload();
                        }
                    }
                })
                .catch(error => {
                    processados++;
                    // console.error('Erro na requisição:', error);
                    erros.push(`${pedido.cliente.nome}: ${error.message}`);
                });
            });
        }

        function gravarTodosPedidos() {
            if (!confirm('Deseja gravar todos os pedidos pendentes?')) return;
            
            const pedidos = <?php echo json_encode($pedidos); ?>;
            const dataGeral = document.getElementById('dataGeral')?.value || new Date().toISOString().split('T')[0];
            
            // console.log('=== INICIANDO GRAVAÇÃO DE TODOS OS PEDIDOS ===');
            // console.log('Total de pedidos recebidos:', pedidos.length);
            // console.log('Data geral:', dataGeral);
            // console.log('Todos os pedidos:', pedidos);
            
            // CORREÇÃO: Modificar o filtro para verificar se cliente e endereço estão cadastrados
            const pedidosPendentes = pedidos.filter(pedido => {
                // console.log('\n=== VERIFICANDO PEDIDO ===');
                // console.log('Número do pedido:', pedido.numero);
                // console.log('Cliente:', pedido.cliente);
                // console.log('Endereço:', pedido.endereco);
                
                const row = document.querySelector(
                    `tr[data-telefone="${pedido.cliente.telefone}"][data-pedido-numero="${pedido.numero}"]`
                );
                
                if (!row) {
                    // console.log('❌ Linha não encontrada para o pedido');
                    return false;
                }
                // console.log('✅ Linha encontrada');

                // Verifica se o cliente e endereço estão cadastrados
                const statusCliente = row.querySelector('.status-cliente');
                const statusEndereco = row.querySelector('.status-endereco');
                const isPedidoNaoGravado = !row.querySelector('.pedido-gravado');

                // Busca IDs do cliente e endereço
                const clienteId = row.getAttribute('data-cliente-id') || 'Não cadastrado';
                const enderecoId = row.getAttribute('data-endereco-id') || 'Não cadastrado';

                // console.log('Status Cliente:', {
                //     elemento: statusCliente?.outerHTML,
                //     cadastrado: statusCliente?.classList.contains('status-cadastrado'),
                //     id: clienteId
                // });
                
                // console.log('Status Endereço:', {
                //     elemento: statusEndereco?.outerHTML,
                //     cadastrado: statusEndereco?.classList.contains('status-cadastrado'),
                //     id: enderecoId
                // });
                
                // console.log('Pedido já gravado?', !isPedidoNaoGravado);

                const podeGravar = statusCliente?.classList.contains('status-cadastrado') && 
                                  statusEndereco?.classList.contains('status-cadastrado') && 
                                  isPedidoNaoGravado;
                                  
                // console.log('Pode gravar este pedido?', podeGravar);
                
                return podeGravar;
            });
            
            // console.log('\n=== RESULTADO DA FILTRAGEM ===');
            // console.log('Pedidos pendentes encontrados:', pedidosPendentes.length);
            // console.log('Pedidos que serão gravados:', pedidosPendentes);
            
            if (pedidosPendentes.length === 0) {
                // console.log('❌ Nenhum pedido pendente encontrado');
                alert('Não há pedidos pendentes para gravar ou alguns pedidos precisam ter cliente/endereço cadastrados primeiro!');
                return;
            }
            
            let processados = 0;
            let erros = [];
            
            pedidosPendentes.forEach(pedido => {
                // console.log('\n=== PROCESSANDO PEDIDO ===');
                // console.log('Número:', pedido.numero);
                
                const row = document.querySelector(
                    `tr[data-telefone="${pedido.cliente.telefone}"][data-pedido-numero="${pedido.numero}"]`
                );
                
                const clienteId = row.getAttribute('data-cliente-id');
                const enderecoId = row.getAttribute('data-endereco-id');
                
                // Pegando a data do input individual do pedido
                const dataPedido = row.querySelector('.data-pedido')?.value || dataGeral;
                
                // console.log('IDs e data encontrados:', {
                //     clienteId,
                //     enderecoId,
                //     dataPedido,
                //     telefone: pedido.cliente.telefone,
                //     numeroPedido: pedido.numero
                // });

                const pedidoComData = {
                    ...pedido,
                    data_pedido: dataPedido,
                    cliente_id: clienteId,
                    endereco_id: enderecoId,
                    // Separando o valor total da taxa de entrega
                    total: pedido.valores.subtotal, // Valor dos produtos sem a taxa
                    taxa_entrega: pedido.valores.taxa_entrega ?? 0, // Taxa de entrega separada
                    // Informações do cupom, se existir
                    cupom_codigo: pedido.cupom?.nome || null,
                    cupom_valor: pedido.cupom?.valor ? parseFloat(pedido.cupom.valor.replace(',', '.')) : null
                };
                
                // console.log('Dados completos que serão enviados:', pedidoComData);
                
                fetch('../actions/upload_json/gravar_pedido.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(pedidoComData)
                })
                .then(response => {
                    // console.log(`Resposta do servidor para pedido ${pedido.numero}:`, response);
                    return response.json();
                })
                .then(data => {
                    processados++;
                    // console.log(`Resultado do processamento do pedido ${pedido.numero}:`, data);
                    
                    if (!data.success) {
                        // console.error(`Erro no pedido ${pedido.numero}:`, data.error);
                        erros.push(`Pedido ${pedido.numero}: ${data.error}`);
                    }
                    
                    if (processados === pedidosPendentes.length) {
                        // console.log('\n=== FINALIZAÇÃO DO PROCESSO ===');
                        // console.log('Total processado:', processados);
                        // console.log('Total de erros:', erros.length);
                        
                        if (erros.length > 0) {
                            // console.error('Erros encontrados:', erros);
                            alert(`Processo concluído com ${erros.length} erros:\n${erros.join('\n')}`);
                        } else {
                            // console.log('✅ Todos os pedidos foram gravados com sucesso!');
                            alert('Todos os pedidos foram gravados com sucesso!');
                            location.reload();
                        }
                    }
                })
                .catch(error => {
                    processados++;
                    // console.error(`Erro na requisição do pedido ${pedido.numero}:`, error);
                    erros.push(`Pedido ${pedido.numero}: ${error.message}`);
                });
            });
        }

        function atualizarItemProduto(nomeProduto) {
            const items = document.querySelectorAll(`.produto-item`);
            items.forEach(item => {
                const statusSpan = item.querySelector('.status-nao-cadastrado');
                if (statusSpan && statusSpan.textContent.trim().includes(nomeProduto)) {
                    // Remove o botão de cadastrar se existir
                    const btnCadastrar = statusSpan.querySelector('.btn-cadastrar');
                    if (btnCadastrar) {
                        btnCadastrar.remove();
                    }
                    
                    // Atualiza o status
                    statusSpan.className = 'status-cadastrado';
                    statusSpan.textContent = nomeProduto; // Mantém apenas o nome do produto
                }
            });
        }

        function atualizarTodasDatas(novaData) {
            // console.log('Atualizando todas as datas para:', novaData);
            
            // Atualiza todos os inputs de data individual
            const datasIndividuais = document.querySelectorAll('.data-pedido');
            datasIndividuais.forEach(input => {
                input.value = novaData;
                // console.log('Input atualizado:', input.id || 'sem id', 'Nova data:', novaData);
            });
            
            // Atualiza a data na sessão via AJAX
            fetch('../actions/upload_json/atualizar_data_sessao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ data: novaData })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // console.log('Data atualizada na sessão com sucesso');
                } else {
                    // console.error('Erro ao atualizar data na sessão:', data.error);
                }
            })
            .catch(error => {
                // console.error('Erro ao atualizar data:', error);
            });
        }

        // No JavaScript, modifique a função de cadastro do cliente
        function cadastrarClienteEndereco(data) {
            $.ajax({
                url: '../actions/upload_json/cadastrar_cliente_endereco.php',
                method: 'POST',
                data: data,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success' || result.error.includes('Duplicate entry')) {
                            // Buscar os IDs atualizados
                            fetch(`../actions/upload_json/buscar_ids_cliente.php?telefone=${data.telefone}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // Atualizar os atributos data-* da linha
                                        const row = document.querySelector(`tr[data-telefone="${data.telefone}"]`);
                                        if (row) {
                                            row.setAttribute('data-cliente-id', data.cliente_id);
                                            row.setAttribute('data-endereco-id', data.endereco_id);
                                            
                                            // console.log('IDs atualizados:', {
                                            //     cliente_id: data.cliente_id,
                                            //     endereco_id: data.endereco_id
                                            // });
                                        }
                                    }
                                });

                            // Continua com o cadastro do endereço se necessário
                            cadastrarEnderecoAjax({
                                telefone: data.telefone,
                                rua: data.rua,
                                numero: data.numero,
                                bairro: data.bairro
                            });
                        } else {
                            alert(result.error || 'Erro ao cadastrar');
                        }
                    } catch (e) {
                        alert('Erro ao processar resposta do servidor');
                    }
                },
                error: function() {
                    alert('Erro na requisição');
                }
            });
        }

        function cadastrarEnderecoAjax(data) {
            $.ajax({
                url: '../actions/upload_json/cadastrar_endereco.php',
                method: 'POST',
                data: data,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            location.reload(); // Recarrega a página após sucesso
                        } else {
                            alert(result.error || 'Erro ao cadastrar endereço');
                        }
                    } catch (e) {
                        alert('Erro ao processar resposta do servidor');
                    }
                },
                error: function() {
                    alert('Erro na requisição do endereço');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Check if we have a stored pedido number and scroll position
            const lastPedidoNumero = sessionStorage.getItem('lastPedidoNumero');
            const scrollPosition = sessionStorage.getItem('scrollPosition');
            
            if (lastPedidoNumero && scrollPosition) {
                // Find the row with the matching pedido number
                const row = document.querySelector(`tr[data-pedido-numero="${lastPedidoNumero}"]`);
                if (row) {
                    // Scroll to the saved position
                    window.scrollTo(0, scrollPosition);
                    
                    // Highlight the row briefly
                    row.style.transition = 'background-color 1s';
                    row.style.backgroundColor = '#ffffd0';
                    setTimeout(() => {
                        row.style.backgroundColor = '';
                    }, 2000);
                }
                
                // Clear the stored values
                sessionStorage.removeItem('lastPedidoNumero');
                sessionStorage.removeItem('scrollPosition');
            }
        });

        function cadastrarClienteIndividual(dadosCliente) {
            if (!confirm('Deseja cadastrar este cliente e endereço?')) return;

            // Debug do telefone antes da limpeza
            console.log('DEBUG - Telefone antes da limpeza:', dadosCliente.telefone);

            // Primeiro limpa o telefone de qualquer formatação
            let telefone = dadosCliente.telefone.replace(/[^0-9]/g, '');
            
            // Debug do telefone após limpeza
            console.log('DEBUG - Telefone após limpeza:', telefone);

            // Reorganiza os dados para o formato esperado pelo servidor
            const dadosCompletos = {
                nome_cliente: dadosCliente.nome,
                telefone_cliente: telefone,
                endereco: {
                    rua: dadosCliente.endereco.rua,
                    numero: dadosCliente.endereco.numero,
                    bairro: dadosCliente.endereco.bairro
                }
            };

            // console.log('Dados que serão enviados:', dadosCompletos);

            fetch('../actions/upload_json/cadastrar_cliente_endereco.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dadosCompletos)
            })
            .then(response => response.json())
            .then(data => {
                // console.log('Resposta do servidor:', data);
                if (data.success) {
                    // Atualiza o status na interface
                    const row = document.querySelector(`tr[data-telefone="${dadosCliente.telefone}"]`);
                    if (row) {
                        // Atualiza o atributo data-telefone da linha com o número limpo
                        row.setAttribute('data-telefone', telefone);
                        
                        const statusCliente = row.querySelector('.status-cliente');
                        const statusEndereco = row.querySelector('.status-endereco');
                        if (statusCliente) {
                            statusCliente.className = 'status-cadastrado';
                            statusCliente.textContent = 'Cadastrado';
                        }
                        if (statusEndereco) {
                            statusEndereco.className = 'status-cadastrado';
                            statusEndereco.textContent = 'Cadastrado';
                        }
                        
                        // Atualiza o telefone exibido na tabela
                        const tdTelefone = row.querySelector('td:nth-child(6)');
                        if (tdTelefone) tdTelefone.textContent = telefone;

                        // Busca os IDs do cliente e endereço recém cadastrados
                        fetch(`../actions/upload_json/buscar_ids_cliente.php?telefone=${encodeURIComponent(telefone)}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Erro na resposta do servidor');
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    row.setAttribute('data-cliente-id', data.cliente_id);
                                    row.setAttribute('data-endereco-id', data.endereco_id);
                                }
                            })
                            .catch(error => {
                                // console.error('Erro ao buscar IDs:', error);
                            });
                    }
                    alert('Cliente e endereço cadastrados com sucesso!');
                } else {
                    alert('Erro ao cadastrar: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                // console.error('Erro:', error);
                alert('Erro ao cadastrar cliente e endereço');
            });
        }

        // Modifique a parte onde define o valor inicial da data
        document.addEventListener('DOMContentLoaded', function() {
            // Recupera a data salva ou usa a data atual como fallback
            const savedDate = sessionStorage.getItem('selectedDate') || new Date().toISOString().split('T')[0];
            
            // Atualiza o input de data geral
            const dataGeral = document.getElementById('dataGeral');
            if (dataGeral) {
                dataGeral.value = savedDate;
            }
            
            // Atualiza todos os inputs de data individual
            const datasIndividuais = document.querySelectorAll('.data-pedido');
            datasIndividuais.forEach(input => {
                input.value = savedDate;
            });

            // ... resto do código do DOMContentLoaded ...
        });

        function cadastrarEnderecoAdicional(telefone, rua, numero, bairro) {
            // Usa o telefone exatamente como está, já que vem do banco de dados
            console.log('CADASTRANDO ENDEREÇO - DADOS INICIAIS:', {
                telefone_original: telefone,
                rua: rua,
                numero: numero,
                bairro: bairro
            });
            
            const dados = {
                telefone_cliente: telefone,
                rua: rua,
                numero: numero,
                bairro: bairro
            };

            console.log('ENVIANDO PARA API:', JSON.stringify(dados));

            fetch('../actions/upload_json/cadastrar_endereco.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dados)
            })
            .then(response => {
                console.log('RESPOSTA RECEBIDA - STATUS:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('DADOS RETORNADOS DA API:', data);
                if (data.success) {
                    // Atualiza o status na interface
                    const row = document.querySelector(`tr[data-telefone="${telefone}"]`);
                    console.log('LINHA ENCONTRADA:', row ? 'SIM' : 'NÃO');
                    
                    if (row) {
                        const statusEndereco = row.querySelector('.status-endereco');
                        console.log('ELEMENTO STATUS ENCONTRADO:', statusEndereco ? 'SIM' : 'NÃO');
                        
                        if (statusEndereco) {
                            statusEndereco.className = 'status-endereco status-cadastrado';
                            statusEndereco.innerHTML = 'Cadastrado';
                            console.log('STATUS ATUALIZADO PARA: Cadastrado');
                            
                            // Atualizar o atributo data-endereco-id se disponível
                            if (data.endereco_id) {
                                row.setAttribute('data-endereco-id', data.endereco_id);
                                console.log('ATRIBUTO data-endereco-id ATUALIZADO PARA:', data.endereco_id);
                            }
                        }
                    }
                    alert('Endereço adicional cadastrado com sucesso!');
                } else {
                    console.error('ERRO AO CADASTRAR ENDEREÇO:', data.error);
                    alert('Erro ao cadastrar endereço: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('EXCEÇÃO CAPTURADA:', error);
                alert('Erro ao cadastrar endereço: ' + error.message);
            });
        }

        // Adicione esta nova função para verificar e atualizar subtotais
        function verificarSubtotais() {
            const linhas = document.querySelectorAll('tr[data-pedido-numero]');
            
            // console.log('Total de linhas encontradas:', linhas.length);
            
            linhas.forEach(linha => {
                const subtotalElement = linha.querySelector('.subtotal-valor');
                const pedidoNumero = linha.getAttribute('data-pedido-numero');
                
                // console.log('Verificando pedido:', pedidoNumero);
                // console.log('Elemento subtotal encontrado:', subtotalElement);
                
                if (!subtotalElement) {
                    // console.log('Elemento .subtotal-valor não encontrado para o pedido:', pedidoNumero);
                    return;
                }
                
                const subtotalJson = parseFloat(subtotalElement.textContent.replace('R$ ', '').replace(',', '.'));
                const totalBanco = parseFloat(linha.getAttribute('data-total-banco'));
                
                // console.log('Valores para pedido', pedidoNumero, {
                //     subtotalJson,
                //     totalBanco,
                //     subtotalText: subtotalElement.textContent,
                //     dataTotal: linha.getAttribute('data-total-banco')
                // });
                
                if (subtotalJson !== totalBanco) {
                    // console.log('Diferença encontrada no pedido:', pedidoNumero);
                    // Criar botão de atualização se ainda não existir
                    if (!linha.querySelector('.btn-atualizar-total')) {
                        const tdAcoes = linha.querySelector('td:last-child');
                        // console.log('TD Ações encontrada:', tdAcoes);
                        
                        const btnAtualizar = document.createElement('button');
                        btnAtualizar.className = 'btn btn-warning btn-sm btn-atualizar-total';
                        btnAtualizar.innerHTML = '<i class="fas fa-sync-alt"></i> Atualizar';
                        btnAtualizar.onclick = () => atualizarTotal(pedidoNumero, subtotalJson);
                        tdAcoes.appendChild(btnAtualizar);
                    }
                }
            });
        }

        function atualizarTotal(pedidoNumero, novoTotal) {
            if (!confirm('Deseja atualizar o total deste pedido?')) return;

            fetch('../actions/upload_json/atualizar_total_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    pedido_numero: pedidoNumero,
                    novo_total: novoTotal
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar a interface
                    const linha = document.querySelector(`tr[data-pedido-numero="${pedidoNumero}"]`);
                    linha.setAttribute('data-total-banco', novoTotal);
                    
                    // Remover o botão de atualização
                    const btnAtualizar = linha.querySelector('.btn-atualizar-total');
                    if (btnAtualizar) btnAtualizar.remove();
                    
                    alert('Total atualizado com sucesso!');
                } else {
                    alert('Erro ao atualizar total: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                // console.error('Erro:', error);
                alert('Erro ao atualizar total');
            });
        }

        // Adicione a chamada da função após o carregamento da página
        document.addEventListener('DOMContentLoaded', function() {
            // ... existing DOMContentLoaded code ...
            
            verificarSubtotais();
        });

        $(document).ready(function() {
            // Handler para o botão de atualizar valor
            $(document).on('click', '.btn-atualizar-valor', function() {
                const btn = $(this);
                const tr = btn.closest('tr');
                const numeroPedido = btn.data('pedido');
                const novoValor = btn.data('valor');
                const horaPedido = tr.find('td:eq(2)').text().trim();
                const telefoneCliente = tr.data('telefone').replace(/[^\d]/g, '');
                const dataPedido = tr.find('.data-pedido').val() || '<?php echo $_SESSION["data_pedidos"] ?? date("Y-m-d"); ?>';

                // console.log('Dados sendo enviados:', {
                //     numero_pedido: numeroPedido,
                //     novo_valor: novoValor,
                //     hora_pedido: horaPedido,
                //     telefone_cliente: telefoneCliente,
                //     data_pedido: dataPedido
                // });

                if (confirm('Deseja atualizar o valor deste pedido no banco de dados?')) {
                    $.ajax({
                        url: '../actions/upload_json/atualizar_valor_pedido.php',
                        method: 'POST',
                        data: {
                            numero_pedido: numeroPedido,
                            novo_valor: novoValor,
                            hora_pedido: horaPedido,
                            telefone_cliente: telefoneCliente,
                            data_pedido: dataPedido
                        },
                        success: function(response) {
                            // console.log('Resposta do servidor:', response);
                            if (response.success) {
                                btn.closest('.subtotal').removeClass('mismatch').addClass('match');
                                btn.remove();
                                alert('Valor atualizado com sucesso!');
                            } else {
                                alert('Erro ao atualizar valor: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            // console.error('Erro na requisição:', {xhr, status, error});
                            alert('Erro ao processar a requisição');
                        }
                    });
                }
            });
        });

        // Atualiza a data quando o documento carrega
        document.addEventListener('DOMContentLoaded', function() {
            const dataGeral = document.getElementById('dataGeral');
            if (dataGeral) {
                atualizarTodasDatas(dataGeral.value);
            }
        });

        // Adicione esta função no seu bloco de script existente
        document.addEventListener('DOMContentLoaded', function() {
            // Verifica se há uma data na sessão PHP
            <?php if (isset($_SESSION['data_pedidos'])): ?>
                const dataSessao = '<?php echo $_SESSION['data_pedidos']; ?>';
                // console.log('Data da sessão:', dataSessao);
                
                // Atualiza o input de data geral
                const dataGeral = document.getElementById('dataGeral');
                if (dataGeral) {
                    dataGeral.value = dataSessao;
                    // console.log('Input data geral atualizado:', dataSessao);
                }
                
                // Atualiza todos os inputs de data individual
                const datasIndividuais = document.querySelectorAll('.data-pedido');
                datasIndividuais.forEach(input => {
                    input.value = dataSessao;
                    // console.log('Input data individual atualizado:', dataSessao);
                });
            <?php endif; ?>
        });

        document.getElementById('btnNotificar').addEventListener('click', async () => {
            try {
                const response = await fetch('../actions/upload_json/send_notification.php');
                const data = await response.json();
                
                if (data.success) {
                    alert('Notificação enviada para todos os dispositivos!');
                } else {
                    alert('Erro ao enviar notificação: ' + data.error);
                }
            } catch (error) {
                // console.error('Erro:', error);
                alert('Erro ao enviar notificação');
            }
        });

        // Solicitar permissão e registrar token
        async function registrarDispositivo() {
            try {
                // console.log('Iniciando registro do dispositivo...');
                const permission = await Notification.requestPermission();
                // console.log('Permissão:', permission);
                
                if (permission === 'granted') {
                    // console.log('Obtendo token...');
                    const token = await messaging.getToken();
                    // console.log('Token obtido:', token);
                    
                    // Salvar token no banco
                    const response = await fetch('../actions/upload_json/save_token.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ token })
                    });
                    const data = await response.json();
                    // console.log('Resposta do servidor:', data);
                }
            } catch (error) {
                // console.error('Erro ao registrar dispositivo:', error);
            }
        }

        // Event listener para o botão de notificação
        document.getElementById('btnNotificar').addEventListener('click', async () => {
            try {
                const response = await fetch('../actions/upload_json/send_notification.php');
                const data = await response.json();
                
                if (data.success) {
                    alert('Notificação enviada para todos os dispositivos!');
                } else {
                    alert('Erro ao enviar notificação: ' + data.error);
                }
            } catch (error) {
                // console.error('Erro:', error);
                alert('Erro ao enviar notificação');
            }
        });

        // Registrar dispositivo quando a página carregar
        document.addEventListener('DOMContentLoaded', registrarDispositivo);

        function regravarPedido(pedidoData) {
            if (!confirm('Deseja regravar este pedido?')) return;

            // Pega a data selecionada do input
            const row = event.target.closest('tr');
            const dataPedido = row.querySelector('.data-pedido').value;
            
            // Adiciona informações do cupom se existir
            const pedidoComData = {
                ...pedidoData,
                data_pedido: dataPedido || new Date().toISOString().split('T')[0],
                // Separando o valor total da taxa de entrega
                total: pedidoData.valores.subtotal, // Valor dos produtos sem a taxa
                taxa_entrega: pedidoData.valores.taxa_entrega ?? 0, // Taxa de entrega separada
                // Informações do cupom, se existir
                cupom_codigo: pedidoData.cupom?.nome || null,
                cupom_valor: pedidoData.cupom?.valor ? parseFloat(pedidoData.cupom.valor.replace(',', '.')) : null
            };

            fetch('../actions/upload_json/regravar_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(pedidoComData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pedido regravado com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao regravar pedido: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                // console.error('Erro:', error);
                alert('Erro ao regravar pedido: ' + error.message);
            });
        }

        function excluirPedido(pedidoData) {
            if (!confirm('Tem certeza que deseja excluir este pedido? Esta ação não pode ser desfeita.')) return;

            // Pega a data selecionada do input
            const row = event.target.closest('tr');
            const dataPedido = row.querySelector('.data-pedido').value;
            
            const dadosExclusao = {
                numero_pedido: pedidoData.numero,
                telefone_cliente: pedidoData.cliente.telefone,
                data_pedido: dataPedido
            };

            fetch('../actions/upload_json/excluir_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dadosExclusao)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pedido excluído com sucesso!');
                    
                    // Ao invés de remover a linha, vamos atualizar os botões
                    const acoesCell = row.querySelector('td:last-child');
                    acoesCell.innerHTML = `
                        <button class="btn-gravar-pedido" onclick='gravarPedido(${JSON.stringify(pedidoData)})'>
                            Gravar Pedido
                        </button>
                    `;
                    
                    // Remove a classe que indica pedido duplicado
                    row.classList.remove('pedido-duplicado');
                    
                } else {
                    alert('Erro ao excluir pedido: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                // console.error('Erro:', error);
                alert('Erro ao excluir pedido: ' + error.message);
            });
        }
    </script>

</body>
</html>
