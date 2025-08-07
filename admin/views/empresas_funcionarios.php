<?php
session_start();
include_once '../config/database.php';
include_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se tem o parâmetro empresa na URL
$empresa_id = $_GET['empresa'] ?? null;

// Se não tiver empresa_id, redireciona para a lista
if (!$empresa_id) {
    header('Location: empresas_relatorios.php');
    exit;
}

// Buscar dados da empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id_empresa = ?");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

// Se a empresa não existir, redireciona para a lista
if (!$empresa) {
    header('Location: empresas_relatorios.php');
    exit;
}

// Buscar funcionários da empresa
$sql = "SELECT 
    c.*,
    COUNT(p.id_pedido) as total_pedidos
FROM clientes c
LEFT JOIN pedidos p ON c.id_cliente = p.fk_cliente_id
WHERE c.fk_empresa_id = ?
GROUP BY c.id_cliente
ORDER BY c.nome_cliente";

$stmt = $pdo->prepare($sql);
$stmt->execute([$empresa_id]);
$funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funcionários Empresas - <?= htmlspecialchars($empresa['nome_empresa']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/empresas.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <style>
        /* Estilos gerais */
        .container {
            padding: 1rem;
            max-width: 100%;
        }

        /* Header responsivo */
        .header-content {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .btn-voltar {
            color: #64748b;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #f8fafc;
        }

        /* Cards para mobile */
        .funcionarios-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            /* padding: 1rem; */
        }

        .funcionario-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            display: block; /* Removido o display: none inicial */
        }

        .funcionario-info {
            display: grid;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .funcionario-nome {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
        }

        .info-item i {
            width: 20px;
            color: #3b82f6;
        }

        .pedidos-badge {
            background: #dbeafe;
            color: #2563eb;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Botões de ação */
        .card-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .btn-action {
            padding: 0.8rem;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-view {
            background: #e0f2fe;
            color: #0284c7;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .table-responsive {
                display: none; /* Oculta a tabela */
            }

            .funcionarios-container {
                grid-template-columns: 1fr; /* Uma coluna no mobile */
            }

            .header-content {
                margin: -1rem -1rem 1rem -1rem;
                border-radius: 0;
            }

            .header-title h1 {
                font-size: 1.2rem;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
            }
        }

        /* Desktop */
        @media (min-width: 769px) {
            .table-responsive {
                display: none; /* Oculta a tabela também no desktop */
            }

            .funcionarios-container {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); /* Grid responsivo */
                max-width: 1200px;
                /* margin: 0 auto; */
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal.active {
            display: flex;
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: #fefefe;
            margin:auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }

        .modal.active .modal-content {
            transform: translateY(0);
        }

        .btn-add {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4b5563;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-group input:disabled {
            background-color: #f3f4f6;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn-cancelar {
            padding: 0.5rem 1rem;
            background-color: #f3f4f6;
            border: none;
            border-radius: 4px;
            color: #4b5563;
            cursor: pointer;
        }

        .btn-salvar {
            padding: 0.5rem 1rem;
            background-color: #2563eb;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
        }

        .btn-salvar:hover {
            background-color: #1d4ed8;
        }

        .enderecos-section {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .enderecos-section h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        .endereco-item {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            position: relative;
            border: 1px solid #e2e8f0;
        }

        .btn-add-endereco {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-remover-endereco {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Ajustes no modal */
        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
            padding: 1.5rem;
            width: 90%;
            max-width: 800px; /* Aumentado para acomodar o layout em grid */
        }

        /* Grid layout para o formulário */
        #formEditarClientePJ {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* 2 colunas */
            gap: 1.5rem;
        }

        /* Campos que ocupam linha inteira */
        #formEditarClientePJ .form-group:first-child,
        .enderecos-section {
            grid-column: 1 / -1;
        }

        /* Layout dos endereços */
        .enderecos-section {
            margin-top: 1.5rem;
        }

        #edit_lista_enderecos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .endereco-item {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            position: relative;
            border: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 2fr 1fr; /* Rua e número */
            gap: 1rem;
        }

        /* O campo do bairro ocupa linha inteira */
        .endereco-item .form-group:last-of-type {
            grid-column: 1 / -1;
        }

        .btn-add-endereco {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        .btn-remover-endereco {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Ajustes nos campos do formulário */
        .form-group {
            margin-bottom: 0; /* Removido margin bottom pois já usamos gap */
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4b5563;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        /* Botões de ação no final do formulário */
        .modal-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            #formEditarClientePJ {
                grid-template-columns: 1fr; /* Uma coluna em telas menores */
            }

            .endereco-item {
                grid-template-columns: 1fr; /* Uma coluna em telas menores */
            }

            .modal-content {
                padding: 1rem;
                margin: 1rem;
                width: calc(100% - 2rem);
            }
        }

        /* Scrollbar personalizada */
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #666;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn-edit-endereco {
            background: #4b5563;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-edit-endereco:hover {
            background: #374151;
        }

        /* Ajuste no modal para este caso específico */
        #modalEnderecoEmpresa .modal-content {
            max-width: 600px;
        }

        #modalEnderecoEmpresa .endereco-item {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
        }

        #modalEnderecoEmpresa .endereco-item .form-group:last-child {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>

    <div class="main-content">
        <div style="padding: 1rem;">
        <div class="header-content">
            <div class="header-title">
                <a href="empresas_relatorios.php" class="btn-voltar">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1><?= htmlspecialchars($empresa['nome_empresa']) ?></h1>
            </div>
            <div class="header-actions">
                <button onclick="abrirModalEnderecoEmpresa()" class="btn-edit-endereco">
                    <i class="fas fa-map-marker-alt"></i> Editar Endereço da Empresa
                </button>
                <button onclick="abrirModalClientePJ()" class="btn-add">
                    <i class="fas fa-user-plus"></i> Novo Funcionário
                </button>
            </div>
        </div>

        <!-- Layout Mobile (Cards) -->
        <div class="funcionarios-container">
            <?php foreach ($funcionarios as $funcionario): ?>
                <div class="funcionario-card">
                    <div class="funcionario-info">
                        <div class="funcionario-nome">
                            <?= htmlspecialchars($funcionario['nome_cliente']) ?>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <span><?= $funcionario['telefone_cliente'] ? formatPhone($funcionario['telefone_cliente']) : '-' ?></span>
                        </div>
                        
                        <div class="pedidos-badge">
                            <i class="fas fa-shopping-cart"></i>
                            <span><?= $funcionario['total_pedidos'] ?? 0 ?> Pedidos</span>
                        </div>
                    </div>

                    <div class="card-actions">
                        <button onclick="editarFuncionario(<?= $funcionario['id_cliente'] ?>)" class="btn-action btn-edit">
                            <i class="fas fa-edit"></i>
                            <span>Editar</span>
                        </button>
                        
                        <button onclick="verPedidos(<?= $funcionario['id_cliente'] ?>)" class="btn-action btn-view">
                            <i class="fas fa-list"></i>
                            <span>Pedidos</span>
                        </button>
                        
                        <button onclick="excluirFuncionario(<?= $funcionario['id_cliente'] ?>)" class="btn-action btn-delete">
                            <i class="fas fa-trash"></i>
                            <span>Excluir</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Layout Desktop (Tabela - mantida a original) -->
        <div class="table-responsive">
            <table class="empresas-table">
                <thead>
                    <tr>
                        <th class="th-nome">Nome</th>
                        <th class="th-telefone">Telefone</th>
                        <th class="th-pedidos text-center">Total Pedidos</th>
                        <th class="th-acoes text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $funcionario): ?>
                        <tr>
                            <td class="td-nome"><?= htmlspecialchars($funcionario['nome_cliente']) ?></td>
                            <td class="td-telefone"><?= $funcionario['telefone_cliente'] ? formatPhone($funcionario['telefone_cliente']) : '-' ?></td>
                            <td class="td-pedidos text-center">
                                <span class="contador-pedidos">
                                    <?= $funcionario['total_pedidos'] ?? 0 ?>
                                </span>
                            </td>
                            <td class="td-acoes">
                                <div class="acoes-wrapper">
                                    <button onclick="editarFuncionario(<?= $funcionario['id_cliente'] ?>)" 
                                            class="btn-icon" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="excluirFuncionario(<?= $funcionario['id_cliente'] ?>)" 
                                            class="btn-icon btn-danger" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button onclick="verPedidos(<?= $funcionario['id_cliente'] ?>)" 
                                            class="btn-icon btn-info" title="Ver Pedidos">
                                        <i class="fas fa-list"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    
    </div>
    </div>

    <div id="modalClientePJ" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cadastrar Novo Funcionário</h2>
                <button class="close-modal" onclick="fecharModalClientePJ()">&times;</button>
            </div>
            <form id="formClientePJ">
                <div class="form-group">
                    <label for="nome">Nome*</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                
                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" class="phone-mask">
                </div>
                
                <div class="form-group">
                    <label for="empresa">Empresa</label>
                    <input type="text" value="<?= htmlspecialchars($empresa['nome_empresa']) ?>" disabled>
                    <input type="hidden" name="empresa" value="<?= $empresa_id ?>">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" onclick="fecharModalClientePJ()">Cancelar</button>
                    <button type="submit" class="btn-salvar">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEditarClientePJ" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Funcionário</h2>
                <button class="close-modal" onclick="fecharModalEditarClientePJ()">&times;</button>
            </div>
            <form id="formEditarClientePJ">
                <input type="hidden" id="edit_id_cliente" name="id_cliente">
                
                <div class="form-group">
                    <label for="edit_nome">Nome*</label>
                    <input type="text" id="edit_nome" name="nome" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_telefone">Telefone</label>
                    <input type="text" id="edit_telefone" name="telefone" class="phone-mask">
                </div>
                
                <div class="form-group">
                    <label for="edit_empresa">Empresa</label>
                    <input type="text" value="<?= htmlspecialchars($empresa['nome_empresa']) ?>" disabled>
                    <input type="hidden" name="empresa" value="<?= $empresa_id ?>">
                </div>

                <!-- Seção de Endereços -->
                <div class="enderecos-section">
                    <h3>Endereços</h3>
                    <div id="edit_lista_enderecos">
                        <!-- Os endereços serão inseridos aqui dinamicamente -->
                    </div>
                    <button type="button" class="btn-add-endereco" onclick="adicionarEnderecoEdit()">
                        <i class="fas fa-plus"></i> Adicionar Endereço
                    </button>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" onclick="fecharModalEditarClientePJ()">Cancelar</button>
                    <button type="submit" class="btn-salvar">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Template para endereço na edição -->
    <template id="template-endereco-edit">
        <div class="endereco-item">
            <input type="hidden" name="id_entrega[]" value="">
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
            <button type="button" class="btn-remover-endereco" onclick="removerEnderecoEdit(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </template>

    <div id="modalEnderecoEmpresa" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Endereço - <?= htmlspecialchars($empresa['nome_empresa']) ?></h2>
                <button class="close-modal" onclick="fecharModalEnderecoEmpresa()">&times;</button>
            </div>
            <form id="formEnderecoEmpresa">
                <input type="hidden" name="empresa_id" value="<?= $empresa_id ?>">
                
                <div class="endereco-section">
                    <div class="endereco-item">
                        <div class="form-group">
                            <label>Rua*</label>
                            <input type="text" name="nome_entrega" id="empresa_rua" required>
                        </div>
                        <div class="form-group">
                            <label>Número</label>
                            <input type="text" name="numero_entrega" id="empresa_numero">
                        </div>
                        <div class="form-group">
                            <label>Bairro*</label>
                            <select name="bairro_id" id="empresa_bairro" required>
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
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" onclick="fecharModalEnderecoEmpresa()">Cancelar</button>
                    <button type="submit" class="btn-salvar">Atualizar Endereços</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
    function abrirModalClientePJ() {
        document.getElementById('modalClientePJ').classList.add('active');
        $('.phone-mask').mask('(00) 00000-0000');
    }

    function fecharModalClientePJ() {
        document.getElementById('modalClientePJ').classList.remove('active');
        document.getElementById('formClientePJ').reset();
    }

    // Fechar modal quando clicar fora dele
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }

    $('#formClientePJ').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '../actions/cadastrar_cliente_pj.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Funcionário cadastrado com sucesso!');
                    fecharModalClientePJ();
                    window.location.reload();
                } else {
                    alert('Erro: ' + response.message);
                }
            },
            error: function() {
                alert('Erro ao processar a requisição');
            }
        });
    });

    function editarFuncionario(id) {
        $.ajax({
            url: '../controllers/ClienteController.php',
            type: 'GET',
            data: { 
                action: 'get', 
                id: id 
            },
            success: function(response) {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }

                if (response.success) {
                    // Preencher dados básicos
                    $('#edit_id_cliente').val(response.id_cliente);
                    $('#edit_nome').val(response.nome_cliente);
                    
                    // Formatar e preencher telefone
                    let telefone = response.telefone_cliente;
                    if (telefone && telefone.length === 11) {
                        telefone = telefone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                    }
                    $('#edit_telefone').val(telefone);
                    
                    // Limpar lista de endereços
                    $('#edit_lista_enderecos').empty();
                    
                    // Adicionar endereços existentes
                    if (response.enderecos && response.enderecos.length > 0) {
                        response.enderecos.forEach(function(endereco) {
                            adicionarEnderecoEdit(endereco);
                        });
                    } else {
                        adicionarEnderecoEdit(); // Adiciona um endereço vazio
                    }
                    
                    // Aplicar máscara de telefone
                    $('#edit_telefone').mask('(00) 00000-0000');
                    
                    // Abrir o modal
                    document.getElementById('modalEditarClientePJ').classList.add('active');
                } else {
                    alert('Erro ao carregar dados do funcionário');
                }
            },
            error: function() {
                alert('Erro ao carregar dados do funcionário');
            }
        });
    }

    function fecharModalEditarClientePJ() {
        document.getElementById('modalEditarClientePJ').classList.remove('active');
        document.getElementById('formEditarClientePJ').reset();
    }

    function adicionarEnderecoEdit(dadosEndereco = null) {
        const template = document.querySelector('#template-endereco-edit');
        const clone = document.importNode(template.content, true);
        
        if (dadosEndereco) {
            $(clone).find('[name="id_entrega[]"]').val(dadosEndereco.id_entrega);
            $(clone).find('[name="nome_entrega[]"]').val(dadosEndereco.nome_entrega);
            $(clone).find('[name="numero_entrega[]"]').val(dadosEndereco.numero_entrega);
            $(clone).find('[name="fk_Bairro_id_bairro[]"]').val(dadosEndereco.fk_Bairro_id_bairro);
        }
        
        $('#edit_lista_enderecos').append(clone);
    }

    function removerEnderecoEdit(button) {
        $(button).closest('.endereco-item').remove();
    }

    // Atualizar o envio do formulário para incluir os endereços
    $('#formEditarClientePJ').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '../actions/editar_cliente_pj.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Funcionário atualizado com sucesso!');
                    fecharModalEditarClientePJ();
                    window.location.reload();
                } else {
                    alert('Erro: ' + response.message);
                }
            },
            error: function() {
                alert('Erro ao processar a requisição');
            }
        });
    });

    // Atualizar o evento de fechar modal para incluir o novo modal
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }

    function abrirModalEnderecoEmpresa() {
        // Limpar formulário
        document.getElementById('formEnderecoEmpresa').reset();
        
        // Buscar endereço atual (primeiro funcionário como referência)
        $.ajax({
            url: '../controllers/EmpresaController.php',
            type: 'GET',
            data: { 
                action: 'getEnderecoReferencia',
                empresa_id: <?= $empresa_id ?>
            },
            success: function(response) {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                
                if (response.success && response.endereco) {
                    $('#empresa_rua').val(response.endereco.nome_entrega);
                    $('#empresa_numero').val(response.endereco.numero_entrega);
                    $('#empresa_bairro').val(response.endereco.fk_Bairro_id_bairro);
                }
                
                document.getElementById('modalEnderecoEmpresa').classList.add('active');
            },
            error: function() {
                alert('Erro ao carregar dados do endereço');
            }
        });
    }

    function fecharModalEnderecoEmpresa() {
        document.getElementById('modalEnderecoEmpresa').classList.remove('active');
    }

    $('#formEnderecoEmpresa').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('Isso irá atualizar o endereço de todos os funcionários desta empresa. Deseja continuar?')) {
            return;
        }
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '../actions/atualizar_endereco_empresa.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Endereços atualizados com sucesso!');
                    fecharModalEnderecoEmpresa();
                    window.location.reload();
                } else {
                    alert('Erro: ' + response.message);
                }
            },
            error: function() {
                alert('Erro ao processar a requisição');
            }
        });
    });
    </script>
</body>
</html> 