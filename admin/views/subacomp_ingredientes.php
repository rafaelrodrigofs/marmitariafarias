<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$pageTitle = "Ingredientes do Sub-acompanhamento";
$activePage = 'acomp';

// Verificar se o usuário está logado
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar se foi fornecido um ID de subacompanhamento
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: acomp.php');
    exit;
}

$subacomp_id = $_GET['id'];

// Buscar dados do subacompanhamento
$sql = "SELECT sa.*, a.nome_acomp 
        FROM sub_acomp sa 
        JOIN acomp a ON sa.fk_acomp_id = a.id_acomp 
        WHERE sa.id_subacomp = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $subacomp_id, PDO::PARAM_INT);
$stmt->execute();
$subacomp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subacomp) {
    header('Location: acomp.php');
    exit;
}

// Buscar ingredientes do subacompanhamento
$sql = "SELECT * FROM subacomp_ingredientes 
        WHERE fk_subacomp_id = :subacomp_id 
        ORDER BY id_ingrediente";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':subacomp_id', $subacomp_id, PDO::PARAM_INT);
$stmt->execute();
$ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adicionar novo ingrediente
if (isset($_POST['add_ingrediente'])) {
    $nome_ingrediente = $_POST['nome_ingrediente'] ?? null;
    $quantidade = $_POST['quantidade'] ? str_replace(',', '.', $_POST['quantidade']) : null;
    $unidade_medida = $_POST['unidade_medida'] ?? null;
    $custo_unitario = $_POST['custo_unitario'] ? str_replace(',', '.', $_POST['custo_unitario']) : null;
    
    // Calcular custo total
    $custo_total = $quantidade * $custo_unitario;
    
    try {
        $sql = "INSERT INTO subacomp_ingredientes 
                (fk_subacomp_id, nome_ingrediente, quantidade, unidade_medida, custo_unitario, custo_total) 
                VALUES 
                (:fk_subacomp_id, :nome_ingrediente, :quantidade, :unidade_medida, :custo_unitario, :custo_total)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':fk_subacomp_id', $subacomp_id, PDO::PARAM_INT);
        $stmt->bindParam(':nome_ingrediente', $nome_ingrediente);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':unidade_medida', $unidade_medida);
        $stmt->bindParam(':custo_unitario', $custo_unitario);
        $stmt->bindParam(':custo_total', $custo_total);
        $stmt->execute();
        
        // Recarregar dados
        header('Location: subacomp_ingredientes.php?id=' . $subacomp_id . '&success=1');
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao adicionar ingrediente: " . $e->getMessage();
    }
}

// Remover ingrediente
if (isset($_GET['remove']) && !empty($_GET['remove'])) {
    $id_ingrediente = $_GET['remove'];
    
    try {
        $sql = "DELETE FROM subacomp_ingredientes WHERE id_ingrediente = :id AND fk_subacomp_id = :subacomp_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id_ingrediente, PDO::PARAM_INT);
        $stmt->bindParam(':subacomp_id', $subacomp_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Recarregar dados
        header('Location: subacomp_ingredientes.php?id=' . $subacomp_id . '&success=2');
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao remover ingrediente: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Painel Administrativo</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Menu Lateral -->
            <?php include '../includes/menu.php'; ?>
            
            <!-- Conteúdo Principal -->
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $pageTitle; ?>: <?php echo $subacomp['nome_subacomp']; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="acomp.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                    <div class="alert alert-success">Ingrediente adicionado com sucesso!</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success']) && $_GET['success'] == 2): ?>
                    <div class="alert alert-success">Ingrediente removido com sucesso!</div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Informações do Sub-acompanhamento</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Nome:</strong> <?php echo $subacomp['nome_subacomp']; ?></p>
                                <p><strong>Acompanhamento:</strong> <?php echo $subacomp['nome_acomp']; ?></p>
                                <p><strong>Preço Atual:</strong> R$ <?php echo number_format($subacomp['preco_subacomp'], 2, ',', '.'); ?></p>
                                <p><strong>Status:</strong> <?php echo $subacomp['activated'] ? 'Ativo' : 'Inativo'; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Adicionar Ingrediente</h5>
                            </div>
                            <div class="card-body">
                                <form action="subacomp_ingredientes.php?id=<?php echo $subacomp_id; ?>" method="post">
                                    <div class="form-group">
                                        <label for="nome_ingrediente">Nome do Ingrediente</label>
                                        <input type="text" name="nome_ingrediente" id="nome_ingrediente" class="form-control" required>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label for="quantidade">Quantidade</label>
                                            <input type="text" name="quantidade" id="quantidade" class="form-control" required>
                                        </div>
                                        
                                        <div class="form-group col-md-4">
                                            <label for="unidade_medida">Unidade</label>
                                            <select name="unidade_medida" id="unidade_medida" class="form-control" required>
                                                <option value="g">Gramas (g)</option>
                                                <option value="kg">Quilogramas (kg)</option>
                                                <option value="ml">Mililitros (ml)</option>
                                                <option value="l">Litros (l)</option>
                                                <option value="un">Unidade (un)</option>
                                                <option value="xic">Xícara (xic)</option>
                                                <option value="colh">Colher (colh)</option>
                                                <option value="colh_sopa">Colher de sopa</option>
                                                <option value="colh_cha">Colher de chá</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group col-md-4">
                                            <label for="custo_unitario">Custo Unitário (R$)</label>
                                            <input type="text" name="custo_unitario" id="custo_unitario" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="add_ingrediente" class="btn btn-success">Adicionar Ingrediente</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Ingredientes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($ingredientes) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Ingrediente</th>
                                            <th>Quantidade</th>
                                            <th>Custo Unitário</th>
                                            <th>Custo Total</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ingredientes as $ingrediente): ?>
                                            <tr>
                                                <td><?php echo $ingrediente['nome_ingrediente']; ?></td>
                                                <td><?php echo $ingrediente['quantidade'] . ' ' . $ingrediente['unidade_medida']; ?></td>
                                                <td>R$ <?php echo number_format($ingrediente['custo_unitario'], 2, ',', '.'); ?></td>
                                                <td>R$ <?php echo number_format($ingrediente['custo_total'], 2, ',', '.'); ?></td>
                                                <td>
                                                    <a href="subacomp_ingredientes.php?id=<?php echo $subacomp_id; ?>&remove=<?php echo $ingrediente['id_ingrediente']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja remover este ingrediente?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3">Custo Total:</th>
                                            <th>
                                                <?php
                                                $custo_total = 0;
                                                foreach ($ingredientes as $ingrediente) {
                                                    $custo_total += $ingrediente['custo_total'];
                                                }
                                                echo 'R$ ' . number_format($custo_total, 2, ',', '.');
                                                ?>
                                            </th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Nenhum ingrediente cadastrado para este sub-acompanhamento.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Formatar campos numéricos
        $(document).ready(function() {
            $('#custo_unitario').on('input', function() {
                $(this).val($(this).val().replace(/[^0-9.,]/g, ''));
            });
            
            $('#quantidade').on('input', function() {
                $(this).val($(this).val().replace(/[^0-9.,]/g, ''));
            });
        });
    </script>
</body>
</html> 