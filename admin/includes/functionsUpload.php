<?php
// Habilitar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


function formatarEndereco($endereco) {
    $rua = isset($endereco['rua']) ? $endereco['rua'] : 'Não informado';
    $numero = isset($endereco['numero']) ? $endereco['numero'] : 'S/N';
    $bairro = isset($endereco['bairro']) ? $endereco['bairro'] : 'Não informado';
    
    if (isset($endereco['endereco_completo'])) {
        return $endereco['endereco_completo'];
    }
    
    return "{$rua}, Nº {$numero} - {$bairro}";
}

// Função para ler e decodificar o arquivo JSON
function lerPedidosJson($arquivoTemp) {
    if (file_exists($arquivoTemp)) {
        $jsonContent = file_get_contents($arquivoTemp);
        $pedidos = json_decode($jsonContent, true);
        
        if (is_array($pedidos) && !empty($pedidos)) {
            $dataPedido = $pedidos[0]['data'] ?? date('Y-m-d');
            $_SESSION['data_pedidos'] = $dataPedido;
            // error_log("Data extraída do JSON: " . $dataPedido);
            return $pedidos;
        }
    }
    return false;
}

// Adicione esta função para gerar um identificador único para clientes iFood
function gerarTelefoneIFood($nome, $origem = 'ifood') {
    // Remove espaços e caracteres especiais do nome
    $nomeFormatado = preg_replace('/[^a-zA-Z0-9]/', '', $nome);
    
    // Gera um hash baseado no nome para garantir consistência
    $hash = substr(md5($nomeFormatado), 0, 8);
    
    // Cria um número único no formato: 00 + origem (1=ifood) + hash
    return "001" . $hash;
}

// Modifique a função verificarCliente para considerar também o nome
function verificarCliente($pdo, $telefone, $nome = null, $origem = null) {
    if ($origem === 'ifood') {
        // Para pedidos do iFood, gera um telefone único baseado no nome
        $telefone = gerarTelefoneIFood($nome);
    }
    
    // Se não tiver telefone, só procura por clientes sem vínculo com empresa
    if (!$telefone) {
        $stmt = $pdo->prepare("
            SELECT id_cliente 
            FROM clientes 
            WHERE nome_cliente = ? 
            AND (fk_empresa_id IS NULL OR fk_empresa_id = 0)
            AND nome_cliente IS NOT NULL
        ");
        $stmt->execute([$nome]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Se tiver telefone, busca normalmente
    $stmt = $pdo->prepare("
        SELECT id_cliente 
        FROM clientes 
        WHERE telefone_cliente = ? 
        OR (nome_cliente = ? AND nome_cliente IS NOT NULL)
    ");
    $stmt->execute([$telefone, $nome]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Função para verificar se o endereço existe para este cliente específico
function verificarEndereco($pdo, $telefone_cliente, $rua, $numero) {
    try {
        error_log("DEBUG ENDERECO - Início da verificação de endereço:");
        error_log("1. Telefone do cliente: " . print_r($telefone_cliente, true));
        error_log("2. Rua: " . print_r($rua, true));
        error_log("3. Número: " . print_r($numero, true));
        
        // Primeiro pega o id do cliente pelo telefone
        $stmt = $pdo->prepare("
            SELECT id_cliente 
            FROM clientes 
            WHERE telefone_cliente = ?
        ");
        $stmt->execute([$telefone_cliente]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("4. Resultado da busca do cliente: " . print_r($cliente, true));
        
        if (!$cliente) {
            error_log("5. Cliente não encontrado para o telefone: " . $telefone_cliente);
            return false;
        }
        
        // Agora verifica se existe o endereço vinculado a este cliente
        $stmt = $pdo->prepare("
            SELECT ce.id_entrega 
            FROM cliente_entrega ce
            WHERE ce.fk_Cliente_id_cliente = ? 
            AND ce.nome_entrega = ? 
            AND ce.numero_entrega = ?
        ");
        
        $params = [
            $cliente['id_cliente'],
            $rua,
            $numero
        ];
        
        error_log("6. Parâmetros para busca de endereço: " . print_r($params, true));
        
        $stmt->execute($params);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("7. Resultado da busca de endereço: " . print_r($resultado, true));
        error_log("8. Endereço existe? " . ($resultado ? "Sim" : "Não"));
        
        return $resultado ? true : false;
        
    } catch (PDOException $e) {
        error_log("ERRO CRÍTICO ao verificar endereço: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// Função para verificar se o produto existe no banco
function verificarProduto($pdo, $nomeProduto) {
    if (!$nomeProduto) return false;
    
    $stmt = $pdo->prepare("SELECT id_produto FROM produto WHERE nome_produto = ?");
    $stmt->execute([$nomeProduto]);
    return $stmt->rowCount() > 0;
}

// Função para verificar se o acompanhamento existe no banco
function verificarSubAcompanhamento($pdo, $nomeAcomp) {
    if (!$nomeAcomp) return false;
    
    $stmt = $pdo->prepare("
        SELECT id_subacomp 
        FROM sub_acomp 
        WHERE nome_subacomp = ?
    ");
    $stmt->execute([$nomeAcomp]);
    return $stmt->rowCount() > 0;
}

// Funções de formatação de telefone
function formatarTelefoneParaExibicao($numero, $comZero = false) {
    // Remove tudo que não for número
    $numero = preg_replace('/[^0-9]/', '', $numero);
    
    if ($comZero) {
        // Para números formatados sem adicionar zeros
        return "(" . substr($numero, 0, 2) . ") " . substr($numero, 2, 5) . "-" . substr($numero, 7);
    } else {
        // Para números reais (com DDD)
        return "(" . substr($numero, 0, 2) . ") " . 
               substr($numero, 2, 5) . "-" . 
               substr($numero, 7);
    }
}

function formatarTelefoneParaBanco($numero) {
    // Se o número for nulo, retorna nulo
    if ($numero === null) {
        return null;
    }
    
    // Remove tudo que não for número
    $numero = preg_replace('/[^0-9]/', '', $numero);
    
    // Se o número já começa com 00, retorna exatamente como está
    if (substr($numero, 0, 2) === '00') {
        return $numero;
    }
    
    // Se é um número normal (sem 00 no início), retorna como está
    return $numero;
}

// Processa o upload do arquivo
$pedidos = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["arquivo_json"])) {
    $arquivo = $_FILES["arquivo_json"];
    
    if ($arquivo["error"] === UPLOAD_ERR_OK && $arquivo["type"] === "application/json") {
        $pedidos = lerPedidosJson($arquivo["tmp_name"]);
    }
}

// Função para verificar cliente pelo nome
function verificarClientePeloNome($pdo, $nome, $telefone = null) {
    if ($telefone) {
        // Se tiver telefone, busca pelo nome E telefone
        $stmt = $pdo->prepare("
            SELECT id_cliente, telefone_cliente 
            FROM clientes 
            WHERE nome_cliente = ? 
            AND telefone_cliente = ?
        ");
        $stmt->execute([$nome, $telefone]);
    } else {
        // Se não tiver telefone, busca só pelo nome
        $stmt = $pdo->prepare("
            SELECT id_cliente, telefone_cliente 
            FROM clientes 
            WHERE nome_cliente = ?
        ");
        $stmt->execute([$nome]);
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Modifique a função verificarPedidoExistente para receber a data como parâmetro
function verificarPedidoExistente($pdo, $pedido, $dataPedido = null) {
    if (!is_array($pedido)) {
        return false;
    }
    
    $numero = $pedido['numero'];
    $horario = $pedido['horario'];
    $telefone = $pedido['cliente']['telefone_banco'];
    $data = $dataPedido ?? $_SESSION['data_pedidos'] ?? date('Y-m-d');
    
    // error_log("Verificando pedido existente: {$numero} | Data: {$data} | Hora: {$horario} | Tel: {$telefone}");
    
    $stmt = $pdo->prepare("
        SELECT p.num_pedido 
        FROM pedidos p 
        INNER JOIN clientes c ON p.fk_cliente_id = c.id_cliente 
        WHERE p.num_pedido = ? 
        AND p.hora_pedido = ? 
        AND c.telefone_cliente = ?
        AND DATE(p.data_pedido) = ?
        AND p.fk_cliente_id IS NOT NULL
    ");
    
    $stmt->execute([$numero, $horario, $telefone, $data]);
    $resultado = $stmt->fetch() !== false;
    
    // error_log("Resultado da verificação - Pedido: {$numero} | Existe: " . ($resultado ? 'Sim' : 'Não'));
    
    return $resultado;
}

// Após o upload do arquivo JSON e antes de gravar os pedidos
function verificarPedidosDuplicados($pdo, $pedidos) {
    $duplicados = [];
    
    // error_log("Data na sessão: " . ($_SESSION['data_pedidos'] ?? 'não definida'));
    
    foreach ($pedidos as $pedido) {
        $telefone = isset($pedido['cliente']['telefone_banco']) ? 
            $pedido['cliente']['telefone_banco'] : 
            (isset($pedido['cliente']['telefone']) ? 
                formatarTelefoneParaBanco($pedido['cliente']['telefone']) : 
                null);

        $data = $pedido['data'] ?? $_SESSION['data_pedidos'] ?? date('Y-m-d');
        
        // error_log("Verificando duplicidade - Pedido: {$pedido['numero']} | Data: {$data}");
        
        $numero = $pedido['numero'];
        $hora = $pedido['horario'];
        $total = str_replace(',', '.', $pedido['valores']['total']);

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM pedidos p
            INNER JOIN clientes c ON p.fk_cliente_id = c.id_cliente
            WHERE c.telefone_cliente = ?
            AND DATE(p.data_pedido) = ?
            AND p.num_pedido = ?
            AND p.hora_pedido = ?
            AND p.sub_total = ?
        ");
        
        $stmt->execute([$telefone, $data, $numero, $hora, $total]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // error_log("Resultado da verificação - Pedido: {$numero} | Total encontrado: {$result['total']}");
        
        if ($result['total'] > 0) {
            $duplicados[] = $numero;
        }
    }
    
    // if (!empty($duplicados)) {
    //     error_log("Pedidos duplicados encontrados: " . implode(', ', $duplicados));
    // }
    
    return $duplicados;
}

// No processamento do arquivo JSON
if (isset($_POST['gravar_pedidos'])) {
    $pedidos = json_decode($_SESSION['pedidos_json'], true);
    
    // Debug da data que será usada
    error_log("Data que será usada para gravar pedidos: " . ($_SESSION['data_pedidos'] ?? 'não definida'));
    
    // Verifica duplicados antes de gravar
    $duplicados = verificarPedidosDuplicados($pdo, $pedidos);
    
    foreach ($pedidos as $pedido) {
        // Garante que a data do pedido está correta
        $pedido['data'] = $pedido['data'] ?? $_SESSION['data_pedidos'] ?? date('Y-m-d');
        
        if (in_array($pedido['numero'], $duplicados)) {
            error_log("Pedido {$pedido['numero']} ignorado por ser duplicado");
            // Adiciona verificação antes de manipular o DOM
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const row = document.querySelector('tr[data-numero=\"{$pedido['numero']}\"]');
                    if (row) {
                        row.classList.add('pedido-duplicado');
                    }
                });
            </script>";
            continue; // Pula a gravação deste pedido
        }
        
        // Processa e grava o pedido normalmente
        processarPedido($pdo, $pedido);
    }
}

// No início do arquivo, antes de processar os pedidos
foreach ($pedidos as &$pedido) {
    // Verifica se o telefone está vazio ou no formato inválido '() -'
    $telefoneVazio = !isset($pedido['cliente']['telefone']) || 
                     $pedido['cliente']['telefone'] === '() -' ||
                     trim($pedido['cliente']['telefone']) === '';

    // Primeiro verifica se existe um cliente com o mesmo nome E telefone
    $telefone_busca = isset($pedido['cliente']['telefone']) ? 
        formatarTelefoneParaBanco($pedido['cliente']['telefone']) : 
        null;
    $clienteExistente = verificarClientePeloNome($pdo, $pedido['cliente']['nome'], $telefone_busca);
    
    if ($clienteExistente) {
        // Se encontrou o cliente, usa EXATAMENTE o telefone dele do banco
        $telefone = $clienteExistente['telefone_cliente'];
        $pedido['cliente']['telefone_banco'] = $telefone;
        $pedido['cliente']['telefone'] = formatarTelefoneParaExibicao($telefone, 
            substr($telefone, 0, 2) === '00');
    } else if ($telefoneVazio) {
        // Se o telefone estiver vazio, gera um novo com apenas 2 zeros no início
        $numeroBase = str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $pedido['cliente']['telefone_banco'] = "00" . $numeroBase;
        $pedido['cliente']['telefone'] = formatarTelefoneParaExibicao("00" . $numeroBase, true);
    } else {
        // Se tem telefone, apenas remove caracteres não numéricos
        $telefone = formatarTelefoneParaBanco($pedido['cliente']['telefone']);
        // Se já começa com 00, mantém como está
        if (substr($telefone, 0, 2) !== '00') {
            // Se não começa com 00, é um número normal, não adiciona zeros
            $pedido['cliente']['telefone_banco'] = $telefone;
            $pedido['cliente']['telefone'] = formatarTelefoneParaExibicao($telefone, false);
        } else {
            // Se já começa com 00, mantém exatamente como está
            $pedido['cliente']['telefone_banco'] = $telefone;
            $pedido['cliente']['telefone'] = formatarTelefoneParaExibicao($telefone, true);
        }
    }
    
    // Prepara os dados do endereço se necessário
    if (isset($pedido['endereco'])) {
        $endereco = [
            'rua' => $pedido['endereco']['rua'] ?? '',
            'numero' => $pedido['endereco']['numero'] ?? '',
            'bairro' => $pedido['endereco']['bairro'] ?? '',
            'complemento' => $pedido['endereco']['complemento'] ?? ''
        ];
        
        // Adiciona o endereço ao cliente
        $pedido['cliente']['endereco'] = $endereco;
        
        // Log para debug
        // error_log("Endereço preparado para " . $pedido['cliente']['nome'] . ": " . print_r($endereco, true));
    } else {
        // error_log("Pedido sem endereço para " . $pedido['cliente']['nome']);
    }
}
unset($pedido);

function cadastrarEndereco($pdo, $telefone_cliente, $rua, $numero, $bairro) {
    try {
        // Primeiro pega o id do cliente
        $stmt = $pdo->prepare("
            SELECT id_cliente 
            FROM clientes 
            WHERE telefone_cliente = ?
        ");
        $stmt->execute([$telefone_cliente]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cliente) {
            throw new Exception("Cliente não encontrado");
        }
        
        // Insere o novo endereço vinculado ao cliente
        $stmt = $pdo->prepare("
            INSERT INTO cliente_entrega 
            (nome_entrega, numero_entrega, fk_Cliente_id_cliente, fk_Bairro_id_bairro) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $rua,
            $numero,
            $cliente['id_cliente'],
            $bairro
        ]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erro ao cadastrar endereço: " . $e->getMessage());
        throw new Exception("Erro ao cadastrar endereço");
    }
}

// No loop de processamento dos pedidos, adicione a verificação antes de inserir
foreach ($pedidos as $pedido) {
    // Verifica se o pedido já existe
    if (verificarPedidoExistente($pdo, $pedido)) {
        // Adiciona verificação antes de manipular o DOM
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                const row = document.querySelector('tr[data-numero=\"{$pedido['numero']}\"]');
                if (row) {
                    row.classList.add('pedido-duplicado');
                }
            });
        </script>";
        continue; // Pula para o próximo pedido
    }
    
    // Se não existe, continua com o processamento normal do pedido
    try {
        $pdo->beginTransaction();
        
        // ... resto do código de processamento do pedido ...
        
        $pdo->commit();
        $response = ['status' => 'success', 'message' => 'Pedido processado com sucesso'];
        // echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $response = ['status' => 'error', 'message' => $e->getMessage()];
        // echo json_encode($response);
    }
}

// Modifique a função de processamento do pedido
function processarPedido($pdo, $pedido) {
    $origem = isset($pedido['origem']) ? strtolower($pedido['origem']) : null;
    $nome = isset($pedido['cliente']['nome']) ? $pedido['cliente']['nome'] : null;
    $telefone = isset($pedido['cliente']['telefone']) ? $pedido['cliente']['telefone'] : null;

    // Se for um pedido do iFood, gera um telefone único
    if ($origem === 'ifood') {
        $telefone = gerarTelefoneIFood($nome);
    }

    // Verifica se o cliente já existe
    $cliente = verificarCliente($pdo, $telefone, $nome, $origem);
    
    if (!$cliente) {
        // Cadastra novo cliente
        $stmt = $pdo->prepare("
            INSERT INTO clientes (
                nome_cliente, 
                telefone_cliente, 
                tipo_cliente
            ) VALUES (?, ?, 0)
        ");
        
        $stmt->execute([
            $nome,
            $telefone
        ]);
        
        $clienteId = $pdo->lastInsertId();
    } else {
        $clienteId = $cliente['id_cliente'];
    }

    return $clienteId;
}

// Na função que cadastra o cliente
function cadastrarCliente($pdo, $cliente) {
    try {
        $pdo->beginTransaction();
        
        // Insere o cliente
        $stmt = $pdo->prepare("
            INSERT INTO clientes (nome_cliente, telefone_cliente) 
            VALUES (?, ?)
        ");
        $stmt->execute([$cliente['nome'], $cliente['telefone_banco']]);
        $idCliente = $pdo->lastInsertId();
        
        // Se houver endereço, cadastra
        if (isset($cliente['endereco'])) {
            // Primeiro verifica se o bairro existe
            $stmt = $pdo->prepare("
                SELECT id_bairro 
                FROM cliente_bairro 
                WHERE nome_bairro = ?
            ");
            $stmt->execute([$cliente['endereco']['bairro']]);
            $bairro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bairro) {
                // Se o bairro não existe, cria
                $stmt = $pdo->prepare("
                    INSERT INTO cliente_bairro (nome_bairro) 
                    VALUES (?)
                ");
                $stmt->execute([$cliente['endereco']['bairro']]);
                $idBairro = $pdo->lastInsertId();
            } else {
                $idBairro = $bairro['id_bairro'];
            }
            
            // Insere o endereço
            $stmt = $pdo->prepare("
                INSERT INTO cliente_entrega 
                (fk_Cliente_id_cliente, fk_Bairro_id_bairro, nome_entrega, numero_entrega) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $idCliente,
                $idBairro,
                $cliente['endereco']['rua'],
                $cliente['endereco']['numero']
            ]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao cadastrar cliente: " . $e->getMessage());
        return false;
    }
}

// Modifique a função buscarValoresPedidoBanco também
function buscarValoresPedidoBanco($pdo, $numeroPedido, $telefoneCliente, $dataPedido = null) {
    try {
        $data = $dataPedido ?? date('Y-m-d');
        
        // error_log("\n=== BUSCANDO VALORES DO PEDIDO ===");
        // error_log("Número do Pedido: $numeroPedido");
        // error_log("Telefone Cliente: $telefoneCliente");
        // error_log("Data do Pedido: $data");
        
        $query = "
            SELECT 
                p.sub_total as subtotal,
                p.taxa_entrega,
                p.cupom_valor,
                p.cupom_codigo,
                (p.sub_total - COALESCE(p.cupom_valor, 0) + COALESCE(p.taxa_entrega, 0)) as total
            FROM pedidos p
            INNER JOIN clientes c ON p.fk_cliente_id = c.id_cliente
            WHERE p.num_pedido = :numero 
            AND c.telefone_cliente = :telefone
            AND DATE(p.data_pedido) = :data
        ";
        
        $queryDebug = $query;
        $queryDebug = str_replace(':numero', "'$numeroPedido'", $queryDebug);
        $queryDebug = str_replace(':telefone', "'$telefoneCliente'", $queryDebug);
        $queryDebug = str_replace(':data', "'$data'", $queryDebug);
        // error_log("\nQuery que será executada:");
        // error_log($queryDebug);
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':numero', $numeroPedido, PDO::PARAM_INT);
        $stmt->bindValue(':telefone', $telefoneCliente, PDO::PARAM_STR);
        $stmt->bindValue(':data', $data, PDO::PARAM_STR);
        
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // error_log("\nResultado da busca:");
        // error_log($resultado ? print_r($resultado, true) : "Nenhum resultado encontrado");
        
        return $resultado;
    } catch (PDOException $e) {
        // error_log("Erro ao buscar valores do pedido: " . $e->getMessage());
        return null;
    }
}

// Função para verificar se o pedido existe no banco
function verificarValorPedidoNoBanco($pdo, $numeroPedido, $telefoneCliente, $horaPedido, $data = null) {
    // Usa a data fornecida, ou a data da sessão, ou a data atual como fallback
    $dataPedido = $data ?? $_SESSION['data_pedidos'] ?? date('Y-m-d');
    
    // Debug dos valores recebidos
    // error_log("\n=== VERIFICAÇÃO DE PEDIDO NO BANCO ===");
    // error_log("Valores recebidos:");
    // error_log("numeroPedido: " . var_export($numeroPedido, true));
    // error_log("telefoneCliente: " . var_export($telefoneCliente, true));
    // error_log("horaPedido: " . var_export($horaPedido, true));
    // error_log("dataPedido: " . var_export($dataPedido, true));
    
    $query = "
        SELECT p.sub_total, p.num_pedido, p.data_pedido, p.hora_pedido, c.telefone_cliente
        FROM pedidos p
        INNER JOIN clientes c ON p.fk_cliente_id = c.id_cliente
        WHERE p.num_pedido = :numero
        AND c.telefone_cliente = :telefone
        AND p.hora_pedido = :hora
        AND DATE(p.data_pedido) = :data
    ";
    
    try {
        $stmt = $pdo->prepare($query);
        
        // Debug da query antes da execução
        $queryDebug = $query;
        $queryDebug = str_replace(':numero', "'" . $numeroPedido . "'", $queryDebug);
        $queryDebug = str_replace(':telefone', "'" . $telefoneCliente . "'", $queryDebug);
        $queryDebug = str_replace(':hora', "'" . $horaPedido . "'", $queryDebug);
        $queryDebug = str_replace(':data', "'" . $dataPedido . "'", $queryDebug);
        
        // error_log("\nQuery que será executada:");
        // error_log($queryDebug);
        
        // Vincula os parâmetros
        $stmt->bindValue(':numero', $numeroPedido, PDO::PARAM_INT);
        $stmt->bindValue(':telefone', $telefoneCliente, PDO::PARAM_STR);
        $stmt->bindValue(':hora', $horaPedido, PDO::PARAM_STR);
        $stmt->bindValue(':data', $dataPedido, PDO::PARAM_STR);
        
        // Debug dos parâmetros vinculados
        // error_log("\nParâmetros vinculados:");
        // error_log(print_r([
        //     ':numero' => $numeroPedido,
        //     ':telefone' => $telefoneCliente,
        //     ':hora' => $horaPedido,
        //     ':data' => $dataPedido
        // ], true));
        
        $stmt->execute();
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug do resultado
        // error_log("\nResultado da query:");
        // error_log($resultado ? print_r($resultado, true) : "Nenhum resultado encontrado");
        
        return $resultado;
        
    } catch (PDOException $e) {
        // error_log("Erro na execução da query: " . $e->getMessage());
        return false;
    }
}

// Na parte onde você gera a tabela, adicione mais logs
foreach ($pedidos as $pedido) {
    $telefone = formatarTelefoneParaBanco($pedido['cliente']['telefone']);
    $numeroPedido = $pedido['numero'];
    $horaPedido = $pedido['horario'];
    $dataPedido = $pedido['data'] ?? $_SESSION['data_pedidos'] ?? date('Y-m-d');
    
    // error_log("=== PROCESSANDO PEDIDO ===");
    // error_log("Dados do pedido JSON:");
    // error_log(print_r($pedido, true));
    
    // Verifica o valor no banco usando a data correta
    $valorBanco = verificarValorPedidoNoBanco($pdo, $numeroPedido, $telefone, $horaPedido, $dataPedido);
    
    // ... resto do código da tabela ...
}
?>