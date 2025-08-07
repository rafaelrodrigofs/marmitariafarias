<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); 
    exit();
}

include_once '../config/database.php';

// Parâmetros de paginação
$itens_por_pagina = isset($_GET['itens']) ? (int)$_GET['itens'] : 10;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Buscar total de clientes
$sql_totais = "SELECT 
    (SELECT COUNT(*) FROM clientes) as total_clientes,
    COUNT(DISTINCT CASE 
        WHEN DATEDIFF(CURRENT_DATE, p.data_pedido) <= 30 
        THEN c.id_cliente 
    END) as clientes_ativos,
    COUNT(DISTINCT CASE 
        WHEN DATEDIFF(CURRENT_DATE, p.data_pedido) > 30 
        AND DATEDIFF(CURRENT_DATE, p.data_pedido) <= 90 
        THEN c.id_cliente 
    END) as clientes_potenciais,
    (SELECT COUNT(*) FROM clientes 
     WHERE id_cliente NOT IN (
         SELECT DISTINCT fk_cliente_id 
         FROM pedidos 
         WHERE DATEDIFF(CURRENT_DATE, data_pedido) <= 30
     )) as clientes_inativos
FROM clientes c
LEFT JOIN pedidos p ON c.id_cliente = p.fk_cliente_id";

$stmt_totais = $pdo->query($sql_totais);
$totais = $stmt_totais->fetch();

// Contar total de registros
$sql_total = "SELECT COUNT(*) as total FROM clientes";
$stmt_total = $pdo->query($sql_total);
$total_registros = $stmt_total->fetch()['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);

// Buscar lista de clientes com informações de último pedido
$sql_clientes = "SELECT 
    c.id_cliente,
    c.nome_cliente,
    c.telefone_cliente,
    DATEDIFF(CURRENT_DATE, MAX(p.data_pedido)) as dias_sem_comprar,
    COUNT(p.id_pedido) as total_pedidos,
    CASE 
        WHEN MAX(p.data_pedido) IS NULL THEN 'Nunca comprou'
        WHEN DATEDIFF(CURRENT_DATE, MAX(p.data_pedido)) <= 30 THEN 'Ativo'
        ELSE 'Inativo'
    END as status_cliente
FROM clientes c
LEFT JOIN pedidos p ON c.id_cliente = p.fk_cliente_id
GROUP BY c.id_cliente
ORDER BY 
    CASE WHEN COUNT(p.id_pedido) = 0 THEN 1 ELSE 0 END, -- Coloca quem nunca comprou por último
    c.nome_cliente ASC -- Ordena alfabeticamente dentro de cada grupo
LIMIT :limit OFFSET :offset";

$stmt_clientes = $pdo->prepare($sql_clientes);
$stmt_clientes->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
$stmt_clientes->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_clientes->execute();
$clientes = $stmt_clientes->fetchAll();

// Adicionar função de formatação de telefone
function formatarTelefone($telefone) {
    // Remove tudo que não for número
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    $length = strlen($telefone);

    // Verifica o tamanho para definir a formatação
    if ($length == 11) {
        // Celular com 9 dígitos: (41) 9 9999-9999
        return sprintf('(%s) %s %s-%s',
            substr($telefone, 0, 2),
            substr($telefone, 2, 1),
            substr($telefone, 3, 4),
            substr($telefone, 7)
        );
    } elseif ($length == 10) {
        // Telefone fixo: (41) 3333-3333
        return sprintf('(%s) %s-%s',
            substr($telefone, 0, 2),
            substr($telefone, 2, 4),
            substr($telefone, 6)
        );
    }
    
    // Retorna o número original se não se encaixar nos padrões
    return $telefone;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Lunch&Fit</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="../assets/css/clientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<style>
    
</style>
<body>
    <?php include_once '../includes/menu.php'; ?>
   
    <div class="main-content">
        <header>
            <h1>Clientes</h1>
        </header>
        <div class="dashboard-container">
            <div class="card-container">
                <div class="card">
                    <div class="icon">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="info">
                        <h2><?php echo $totais['clientes_potenciais']; ?></h2>
                        <p>Clientes em potencial</p>
                    </div>
                </div>

                <div class="card">
                    <div class="icon">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="info">
                        <h2><?php echo $totais['clientes_ativos']; ?></h2>
                        <p>Clientes ativos</p>
                    </div>
                </div>

                <div class="card">
                    <div class="icon">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>
                    <div class="info">
                        <h2><?php echo $totais['clientes_inativos']; ?></h2>
                        <p>Clientes inativos</p>
                    </div>
                </div>
            </div>
            
            <div class="search-card">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchCliente" placeholder="Buscar cliente por nome ou telefone...">
                </div>
            </div>
            
            <div class="clients-table-card">
                <div class="table-header">
                    <div class="table-title">
                        <h3>Total de registros: <?php echo $total_registros; ?></h3>
                    </div>
                    <div class="table-pagination">
                        <span><?php echo $pagina_atual; ?></span> de <?php echo $total_paginas; ?>
                        <div class="pagination-controls">
                            <a href="?pagina=1&itens=<?php echo $itens_por_pagina; ?>" 
                               class="<?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                                <button <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-angle-double-left"></i>
                                </button>
                            </a>
                            <a href="?pagina=<?php echo max(1, $pagina_atual - 1); ?>&itens=<?php echo $itens_por_pagina; ?>"
                               class="<?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                                <button <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-angle-left"></i>
                                </button>
                            </a>
                            <a href="?pagina=<?php echo min($total_paginas, $pagina_atual + 1); ?>&itens=<?php echo $itens_por_pagina; ?>"
                               class="<?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                                <button <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>>
                                    <i class="fas fa-angle-right"></i>
                                </button>
                            </a>
                            <a href="?pagina=<?php echo $total_paginas; ?>&itens=<?php echo $itens_por_pagina; ?>"
                               class="<?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                                <button <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>>
                                    <i class="fas fa-angle-double-right"></i>
                                </button>
                            </a>
                            <select onchange="window.location.href='?pagina=1&itens=' + this.value">
                                <option value="10" <?php echo $itens_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="50" <?php echo $itens_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $itens_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                    </div>
                </div>

                <table class="clients-table">
                    <thead>
                        <tr>
                            <th class="letra-column sticky-column">Letra</th>
                            <th>ID</th>
                            <th>Cliente <i class="fas fa-sort"></i></th>
                            <th>Dias sem comprar <i class="fas fa-sort"></i></th>
                            <th>Total de pedidos <i class="fas fa-sort"></i></th>
                            <th>Status</th>
                            <th>Contato</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $letra_atual = '';
                        foreach($clientes as $cliente): 
                            $primeira_letra = mb_strtoupper(mb_substr($cliente['nome_cliente'], 0, 1, 'UTF-8'));
                            $nova_letra = ($primeira_letra !== $letra_atual);
                            $letra_atual = $primeira_letra;
                        ?>
                        <tr>
                            <td class="letra-column sticky-column <?php echo $nova_letra ? 'nova-letra' : ''; ?>">
                                <?php echo $nova_letra ? $primeira_letra : ''; ?>
                            </td>
                            <td class="id-column"><?php echo $cliente['id_cliente']; ?></td>
                            <td><?php echo htmlspecialchars($cliente['nome_cliente']); ?></td>
                            <td><?php echo $cliente['dias_sem_comprar'] ?? 'Nunca comprou'; ?></td>
                            <td><?php echo $cliente['total_pedidos']; ?></td>
                            <td class="status-column">
                                <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $cliente['status_cliente'])); ?>">
                                    <?php echo $cliente['status_cliente']; ?>
                                </span>
                            </td>
                            <td><?php echo formatarTelefone($cliente['telefone_cliente']); ?></td>
                            <td class="actions">
                                <a href="#" class="view-link" title="Visualizar cliente">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $cliente['telefone_cliente']); ?>" 
                                   class="whatsapp-link" 
                                   target="_blank" 
                                   title="Enviar WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <a href="#" class="edit-link" title="Editar cliente">
                                    <i class="fas fa-pencil"></i>
                                </a>
                                <a href="#" class="delete-link" title="Excluir cliente">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    let timeoutId;
    const searchInput = document.getElementById('searchCliente');
    const tbody = document.querySelector('.clients-table tbody');
    const totalRegistros = document.querySelector('.table-title h3');
    const paginationInfo = document.querySelector('.table-pagination span');
    const paginationTotal = document.querySelector('.table-pagination');

    function formatarTelefone(telefone) {
        // Remove tudo que não for número
        telefone = telefone.replace(/[^0-9]/g, '');
        
        if (telefone.length === 11) {
            // Celular com 9 dígitos
            return `(${telefone.slice(0,2)}) ${telefone.slice(2,3)} ${telefone.slice(3,7)}-${telefone.slice(7)}`;
        } else if (telefone.length === 10) {
            // Telefone fixo
            return `(${telefone.slice(0,2)}) ${telefone.slice(2,6)}-${telefone.slice(6)}`;
        }
        
        return telefone;
    }

    function atualizarTabela(data) {
        tbody.innerHTML = '';
        let letraAtual = '';
        
        data.clientes.forEach(cliente => {
            const statusClass = cliente.status_cliente.toLowerCase().replace(' ', '-');
            const primeiraLetra = cliente.nome_cliente.charAt(0).toUpperCase();
            const novaLetra = primeiraLetra !== letraAtual;
            letraAtual = primeiraLetra;
            const telefoneFormatado = formatarTelefone(cliente.telefone_cliente);
            const telefoneNumerico = cliente.telefone_cliente.replace(/[^0-9]/g, '');
            
            tbody.innerHTML += `
                <tr>
                    <td class="letra-column sticky-column ${novaLetra ? 'nova-letra' : ''}">
                        ${novaLetra ? primeiraLetra : ''}
                    </td>
                    <td class="id-column">${cliente.id_cliente}</td>
                    <td>${cliente.nome_cliente}</td>
                    <td>${cliente.dias_sem_comprar ?? 'Nunca comprou'}</td>
                    <td>${cliente.total_pedidos}</td>
                    <td class="status-column">
                        <span class="status-badge ${statusClass}">
                            ${cliente.status_cliente}
                        </span>
                    </td>
                    <td>${telefoneFormatado}</td>
                    <td class="actions">
                        <a href="#" class="view-link" title="Visualizar cliente">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="https://wa.me/55${telefoneNumerico}" 
                           class="whatsapp-link" 
                           target="_blank" 
                           title="Enviar WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="#" class="edit-link" title="Editar cliente">
                            <i class="fas fa-pencil"></i>
                        </a>
                        <a href="#" class="delete-link" title="Excluir cliente">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            `;
        });
        
        totalRegistros.textContent = `Total de registros: ${data.total}`;
        atualizarPaginacao(data.total_paginas);
    }

    function atualizarPaginacao(totalPaginas) {
        // Atualiza os links de paginação com a nova busca
        const urlParams = new URLSearchParams(window.location.search);
        const paginaAtual = parseInt(urlParams.get('pagina')) || 1;
        const itensPorPagina = parseInt(urlParams.get('itens')) || 10;
        
        paginationInfo.textContent = `${paginaAtual} de ${totalPaginas}`;
        // Atualizar os links de paginação mantendo o termo de busca
    }

    searchInput.addEventListener('input', (e) => {
        clearTimeout(timeoutId);
        
        timeoutId = setTimeout(() => {
            const searchTerm = e.target.value.trim();
            const urlParams = new URLSearchParams(window.location.search);
            const itensPorPagina = urlParams.get('itens') || 10;
            
            fetch(`../actions/clientes/buscar_clientes.php?search=${searchTerm}&itens=${itensPorPagina}&pagina=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error(data.error);
                        return;
                    }
                    atualizarTabela(data);
                })
                .catch(error => console.error('Erro:', error));
        }, 300); // Aguarda 300ms após o último caractere digitado
    });
    </script>
</body>
</html>