<?php
// Habilitar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

// Função para buscar clientes similares
function buscarClientesSimilares() {
    global $pdo;
    
    // Array para armazenar todos os grupos de possíveis duplicatas
    $duplicatas = [];
    
    try {
        // 1. Buscar por nomes similares com endereços
        $sql_nomes = "
            SELECT 
                c1.id_cliente as id1, 
                c1.nome_cliente as nome1,
                c1.telefone_cliente as telefone1,
                GROUP_CONCAT(DISTINCT e1.nome_entrega) as enderecos1,
                COUNT(DISTINCT p1.id_pedido) as pedidos1,
                c2.id_cliente as id2,
                c2.nome_cliente as nome2,
                c2.telefone_cliente as telefone2,
                GROUP_CONCAT(DISTINCT e2.nome_entrega) as enderecos2,
                COUNT(DISTINCT p2.id_pedido) as pedidos2
            FROM clientes c1
            JOIN clientes c2 ON c1.id_cliente < c2.id_cliente
            LEFT JOIN cliente_entrega e1 ON c1.id_cliente = e1.fk_Cliente_id_cliente
            LEFT JOIN cliente_entrega e2 ON c2.id_cliente = e2.fk_Cliente_id_cliente
            LEFT JOIN pedidos p1 ON c1.id_cliente = p1.fk_cliente_id
            LEFT JOIN pedidos p2 ON c2.id_cliente = p2.fk_cliente_id
            WHERE (
                -- Nomes exatamente iguais ignorando espaços e case
                REPLACE(LOWER(TRIM(c1.nome_cliente)), ' ', '') = REPLACE(LOWER(TRIM(c2.nome_cliente)), ' ', '')
                OR 
                -- Primeira palavra do nome igual (para pegar variações do mesmo nome)
                SUBSTRING_INDEX(LOWER(TRIM(c1.nome_cliente)), ' ', 1) = SUBSTRING_INDEX(LOWER(TRIM(c2.nome_cliente)), ' ', 1)
            )
            GROUP BY c1.id_cliente, c2.id_cliente";
        
        $stmt = $pdo->query($sql_nomes);
        $duplicatas['nomes'] = $stmt->fetchAll();

        // 2. Buscar telefones similares com endereços
        $sql_telefones = "
            SELECT 
                c1.id_cliente as id1,
                c1.nome_cliente as nome1,
                c1.telefone_cliente as telefone1,
                GROUP_CONCAT(DISTINCT e1.nome_entrega) as enderecos1,
                COUNT(DISTINCT p1.id_pedido) as pedidos1,
                c2.id_cliente as id2,
                c2.nome_cliente as nome2,
                c2.telefone_cliente as telefone2,
                GROUP_CONCAT(DISTINCT e2.nome_entrega) as enderecos2,
                COUNT(DISTINCT p2.id_pedido) as pedidos2
            FROM clientes c1
            JOIN clientes c2 ON c1.id_cliente < c2.id_cliente
            LEFT JOIN cliente_entrega e1 ON c1.id_cliente = e1.fk_Cliente_id_cliente
            LEFT JOIN cliente_entrega e2 ON c2.id_cliente = e2.fk_Cliente_id_cliente
            LEFT JOIN pedidos p1 ON c1.id_cliente = p1.fk_cliente_id
            LEFT JOIN pedidos p2 ON c2.id_cliente = p2.fk_cliente_id
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(c1.telefone_cliente, '(', ''), ')', ''), '-', ''), ' ', '') =
                  REPLACE(REPLACE(REPLACE(REPLACE(c2.telefone_cliente, '(', ''), ')', ''), '-', ''), ' ', '')
            GROUP BY c1.id_cliente, c2.id_cliente";
        
        $stmt = $pdo->query($sql_telefones);
        $duplicatas['telefones'] = $stmt->fetchAll();

        // 3. Buscar por endereços iguais
        $sql_enderecos = "
            SELECT 
                c1.id_cliente as id1,
                c1.nome_cliente as nome1,
                c1.telefone_cliente as telefone1,
                COUNT(p1.id_pedido) as pedidos1,
                c2.id_cliente as id2,
                c2.nome_cliente as nome2,
                c2.telefone_cliente as telefone2,
                COUNT(p2.id_pedido) as pedidos2,
                e1.nome_entrega as endereco
            FROM clientes c1
            JOIN cliente_entrega e1 ON c1.id_cliente = e1.fk_Cliente_id_cliente
            JOIN cliente_entrega e2 ON e1.nome_entrega = e2.nome_entrega
            JOIN clientes c2 ON c2.id_cliente = e2.fk_Cliente_id_cliente
            LEFT JOIN pedidos p1 ON c1.id_cliente = p1.fk_cliente_id
            LEFT JOIN pedidos p2 ON c2.id_cliente = p2.fk_cliente_id
            WHERE c1.id_cliente < c2.id_cliente
            GROUP BY c1.id_cliente, c2.id_cliente, e1.nome_entrega";
        
        $stmt = $pdo->query($sql_enderecos);
        $duplicatas['enderecos'] = $stmt->fetchAll();

        return $duplicatas;
    } catch (Exception $e) {
        error_log("Erro ao buscar duplicatas: " . $e->getMessage());
        return false;
    }
}

// Função para mesclar clientes
function mesclarClientes($id_principal, $ids_secundarios) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Atualizar pedidos
        $sql_pedidos = "UPDATE pedidos SET fk_cliente_id = :id_principal 
                       WHERE fk_cliente_id IN (" . implode(',', $ids_secundarios) . ")";
        $stmt = $pdo->prepare($sql_pedidos);
        $stmt->execute(['id_principal' => $id_principal]);
        
        // Atualizar endereços
        $sql_enderecos = "UPDATE cliente_entrega SET fk_Cliente_id_cliente = :id_principal 
                         WHERE fk_Cliente_id_cliente IN (" . implode(',', $ids_secundarios) . ")";
        $stmt = $pdo->prepare($sql_enderecos);
        $stmt->execute(['id_principal' => $id_principal]);
        
        // Remover clientes secundários
        $sql_delete = "DELETE FROM clientes WHERE id_cliente IN (" . implode(',', $ids_secundarios) . ")";
        $pdo->exec($sql_delete);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao mesclar clientes: " . $e->getMessage());
        throw $e; // Relança o erro para ser capturado no processamento
    }
}

// Processar mesclagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mesclar'])) {
    try {
        $id_principal = $_POST['cliente_principal'] ?? null;
        $ids_secundarios = $_POST['clientes_secundarios'] ?? [];
        
        if (!$id_principal || empty($ids_secundarios)) {
            throw new Exception("Selecione um cliente principal e pelo menos um cliente secundário");
        }
        
        if (mesclarClientes($id_principal, $ids_secundarios)) {
            $mensagem = "Clientes mesclados com sucesso!";
        }
    } catch (Exception $e) {
        $erro = "Erro ao mesclar clientes: " . $e->getMessage();
        error_log($erro);
    }
}

// Buscar duplicatas
$duplicatas = buscarClientesSimilares();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mesclar Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .table tr.table-primary, .table tr.table-secondary {
        border-left: 3px solid #dee2e6;
    }

    .table td {
        vertical-align: middle;
    }

    /* Estilo para os endereços */
    td div {
        margin: 2px 0;
        padding: 2px 5px;
        background-color: #f8f9fa;
        border-radius: 3px;
        font-size: 0.9em;
    }

    /* Espaçamento entre pares de clientes */
    tr.table-light {
        height: 10px;
    }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Possíveis Clientes Duplicados</h2>
        
        <?php if (isset($mensagem)): ?>
            <div class="alert alert-success"><?= $mensagem ?></div>
        <?php endif; ?>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-danger"><?= $erro ?></div>
        <?php endif; ?>

        <!-- Duplicatas por Nome Similar -->
        <?php if (!empty($duplicatas['nomes'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4>Nomes Similares</h4>
            </div>
            <div class="card-body">
                <form method="POST" class="form-mesclar">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Principal</th>
                                <th>Mesclar</th>
                                <th>Nome</th>
                                <th>Telefone</th>
                                <th>Endereços</th>
                                <th>Total Pedidos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($duplicatas['nomes'] as $par): ?>
                                <tr class="table-primary">
                                    <td><input type="radio" name="cliente_principal" value="<?= $par['id1'] ?>"></td>
                                    <td><input type="checkbox" name="clientes_secundarios[]" value="<?= $par['id1'] ?>"></td>
                                    <td><?= htmlspecialchars($par['nome1'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($par['telefone1'] ?? '') ?></td>
                                    <td>
                                        <?php 
                                        $enderecos = explode(',', $par['enderecos1'] ?? '');
                                        foreach ($enderecos as $endereco): ?>
                                            <div><?= htmlspecialchars($endereco) ?></div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?= $par['pedidos1'] ?? 0 ?></td>
                                </tr>
                                <tr class="table-secondary">
                                    <td><input type="radio" name="cliente_principal" value="<?= $par['id2'] ?>"></td>
                                    <td><input type="checkbox" name="clientes_secundarios[]" value="<?= $par['id2'] ?>"></td>
                                    <td><?= htmlspecialchars($par['nome2'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($par['telefone2'] ?? '') ?></td>
                                    <td>
                                        <?php 
                                        $enderecos = explode(',', $par['enderecos2'] ?? '');
                                        foreach ($enderecos as $endereco): ?>
                                            <div><?= htmlspecialchars($endereco) ?></div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?= $par['pedidos2'] ?? 0 ?></td>
                                </tr>
                                <tr><td colspan="6" class="table-light"></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" name="mesclar" class="btn btn-danger" onclick="return confirmarMesclagem()">
                        Mesclar Selecionados
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Duplicatas por Telefone -->
        <?php if (!empty($duplicatas['telefones'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4>Telefones Similares</h4>
            </div>
            <div class="card-body">
                <!-- Similar à seção anterior, mas para telefones -->
            </div>
        </div>
        <?php endif; ?>

        <!-- Duplicatas por Endereço -->
        <?php if (!empty($duplicatas['enderecos'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4>Mesmo Endereço</h4>
            </div>
            <div class="card-body">
                <!-- Similar à seção anterior, mas para endereços -->
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function confirmarMesclagem() {
        return confirm('Tem certeza que deseja mesclar os clientes selecionados? Esta ação não pode ser desfeita.');
    }
    
    // Impedir que o cliente principal seja selecionado como secundário
    document.querySelectorAll('input[name="cliente_principal"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="clientes_secundarios[]"]');
            checkboxes.forEach(checkbox => {
                if (checkbox.value === this.value) {
                    checkbox.checked = false;
                    checkbox.disabled = true;
                } else {
                    checkbox.disabled = false;
                }
            });
        });
    });
    </script>
</body>
</html>
