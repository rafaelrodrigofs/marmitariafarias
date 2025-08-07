<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include_once '../config/database.php';

// Adicionar no início do arquivo, após a conexão com o banco de dados
$sql_diagnostico = "SELECT a.id_acomp, 
                           a.nome_acomp,
                           COUNT(*) as contagem
                    FROM acomp a 
                    GROUP BY a.id_acomp, a.nome_acomp
                    HAVING COUNT(*) > 1";

$stmt_diagnostico = $pdo->prepare($sql_diagnostico);
$stmt_diagnostico->execute();
$duplicatas = $stmt_diagnostico->fetchAll(PDO::FETCH_ASSOC);

if (!empty($duplicatas)) {
    error_log("ALERTA: Encontradas duplicatas na tabela acomp:");
    error_log(print_r($duplicatas, true));
}

// Buscar grupos de acompanhamentos com suas regras (modificada para evitar duplicatas)
$sql_grupos = "SELECT DISTINCT a.id_acomp, 
                      a.nome_acomp, 
                      par.min_escolhas,
                      par.max_escolhas,
                      par.is_required,
                      par.permite_repetir
               FROM acomp a 
               LEFT JOIN produto_acomp_regras par ON a.id_acomp = par.fk_acomp_id 
               ORDER BY a.id_acomp, a.nome_acomp";

// Adicionar log para debug
error_log("Query grupos: " . print_r($sql_grupos, true));

$stmt_grupos = $pdo->prepare($sql_grupos);
$stmt_grupos->execute();
$grupos = $stmt_grupos->fetchAll(PDO::FETCH_ASSOC);

// Log para verificar os resultados
error_log("Grupos encontrados: " . print_r($grupos, true));

// Adicionar verificação de duplicatas
$ids_verificados = [];
foreach ($grupos as $key => $grupo) {
    if (in_array($grupo['id_acomp'], $ids_verificados)) {
        error_log("Duplicata encontrada para ID: " . $grupo['id_acomp']);
        unset($grupos[$key]);
    } else {
        $ids_verificados[] = $grupo['id_acomp'];
    }
}

// Reindexar array após remoção de duplicatas
$grupos = array_values($grupos);

// Buscar todos os produtos ativos com suas categorias
$sql_produtos = "SELECT p.id_produto, p.nome_produto, 
                        c.id_categoria, c.nome_categoria
                 FROM produto p
                 JOIN categoria c ON p.fk_categoria_id = c.id_categoria 
                 WHERE p.activated = 1 
                 ORDER BY c.id_categoria, p.id_produto";

$stmt_produtos = $pdo->prepare($sql_produtos);
$stmt_produtos->execute();
$produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

// Buscar associações produto-acompanhamento existentes
$sql_produto_acomp = "SELECT fk_produto_id, fk_acomp_id 
                      FROM produto_acomp";
$stmt_produto_acomp = $pdo->prepare($sql_produto_acomp);
$stmt_produto_acomp->execute();
$produto_acomp = $stmt_produto_acomp->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Adicionais - LuncheFit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <style>
        .grupos-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .grupo-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .grupo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .grupo-title {
            font-size: 1.1rem;
            font-weight: 500;
            color: #2c3e50;
        }

        .grupo-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-add {
            background: #2ecc71;
            color: white;
        }

        .subitens-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .subitem {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .subitem:last-child {
            border-bottom: none;
        }

        .subitem-price {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .add-grupo-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #2ecc71;
            color: white;
            padding: 1rem;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 1000;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1001;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 2rem auto;
            padding: 1.5rem;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .grupo-regras {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
            margin: 0.5rem 0;
        }

        .regra-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .quantidade-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantidade-input {
            width: 50px;
            text-align: center;
            padding: 0.25rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .quantidade-control button {
            width: 24px;
            height: 24px;
            border: none;
            background: #e9ecef;
            border-radius: 4px;
            cursor: pointer;
        }

        .precificacao-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-precificacao {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-precificacao:hover {
            background: #f0f0f0;
        }

        .btn-precificacao[data-value="0"].active {
            background: #2ecc71;  /* Verde para Opcional */
            color: white;
            border-color: #27ae60;
        }

        .btn-precificacao[data-value="1"].active {
            background: #e74c3c;  /* Vermelho para Obrigatório */
            color: white;
            border-color: #c0392b;
        }

        .saved {
            position: relative;
        }

        .saved::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #2ecc71;
            color: white;
            padding: 5px;
            border-radius: 50%;
            font-size: 12px;
            opacity: 0;
            animation: fadeInOut 1s ease;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            50% { opacity: 1; }
            100% { opacity: 0; }
        }

        .produtos-associados {
            /* margin-top: 1rem; */
            /* padding: 1rem; */
            background: #f8f9fa;
            border-radius: 4px;
        }

        .produtos-list {
            max-height: 200px;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .produto-checkbox-item {
            display: flex;
            align-items: center;
            padding: 0.3rem 0;
            transition: background-color 0.3s;
        }

        .produto-checkbox-item.updated {
            background-color: #d4edda;
        }

        .produto-checkbox-item input[type="checkbox"] {
            margin-right: 0.5rem;
        }

        .produto-checkbox-item label {
            cursor: pointer;
            font-size: 0.9rem;
        }

        .categoria-grupo {
            margin-bottom: 1rem;
        }

        .categoria-grupo .categoria-header {
            background: #f1f1f1;
            padding: 0.5rem;
            font-weight: bold;
            color: #333;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .produtos-list {
            max-height: 300px; /* Aumentado para acomodar as categorias */
            overflow-y: auto;
        }

        .produto-checkbox-item {
            padding-left: 1rem; /* Indentação para produtos dentro da categoria */
        }

        .sub-acomp-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .sub-acomp-item input[type="text"] {
            flex: 3;
        }
        
        .sub-acomp-item input[type="number"] {
            flex: 1;
        }

        .produtos-header {
            cursor: pointer;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
            margin: 10px 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .produtos-header:hover {
            background-color: #e9e9e9;
        }

        .produtos-header h4 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .produtos-header i {
            transition: transform 0.3s ease;
        }

        .produtos-header.active i {
            transform: rotate(180deg);
        }

        .produtos-content {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 5px;
        }

        .subitem.inactive {
            opacity: 0.6;
            background-color: #f8f9fa;
        }
        
        .badge {
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: normal;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>

    <div class="main-content">
        <h1>Gerenciar Grupos de Adicionais</h1>
        
        <div class="grupos-container">
            <?php foreach ($grupos as $grupo): ?>
            <div class="grupo-card" data-grupo-id="<?= $grupo['id_acomp'] ?>">
                <div class="grupo-header">
                    <h2 class="grupo-title"><?= htmlspecialchars($grupo['nome_acomp']) ?></h2>
                    <div class="grupo-actions">
                        <button class="btn-action btn-edit" onclick="editarGrupo(<?= $grupo['id_acomp'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-delete" onclick="deletarGrupo(<?= $grupo['id_acomp'] ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="btn-action btn-add" onclick="adicionarSubitem(<?= $grupo['id_acomp'] ?>)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>

                <div class="grupo-regras">
                    <div class="regra-item">
                        <label>Mínimo</label>
                        <input type="number" 
                               id="min_<?= $grupo['id_acomp'] ?>" 
                               value="<?= $grupo['min_escolhas'] ?? 0 ?>" 
                               class="quantidade-input"
                               onblur="salvarRegras(<?= $grupo['id_acomp'] ?>)">
                    </div>

                    <div class="regra-item">
                        <label>Máximo</label>
                        <input type="number" 
                               id="max_<?= $grupo['id_acomp'] ?>" 
                               value="<?= $grupo['max_escolhas'] ?? 1 ?>" 
                               class="quantidade-input"
                               onblur="salvarRegras(<?= $grupo['id_acomp'] ?>)">
                    </div>

                    <div class="regra-item">
                        <label>Tipo</label>
                        <div class="precificacao-buttons">
                            <button type="button" 
                                    class="btn-precificacao <?= !($grupo['is_required'] ?? false) ? 'active' : '' ?>" 
                                    data-value="0" 
                                    onclick="alterarPrecificacao(<?= $grupo['id_acomp'] ?>, 0, this)">
                                Opcional
                            </button>
                            <button type="button" 
                                    class="btn-precificacao <?= ($grupo['is_required'] ?? false) ? 'active' : '' ?>" 
                                    data-value="1" 
                                    onclick="alterarPrecificacao(<?= $grupo['id_acomp'] ?>, 1, this)">
                                Obrigatório
                            </button>
                        </div>
                    </div>
                </div>

                <?php
                // Buscar subitens do grupo
                $sql_subitens = "SELECT * FROM sub_acomp WHERE fk_acomp_id = ? ORDER BY nome_subacomp";
                $stmt_subitens = $pdo->prepare($sql_subitens);
                $stmt_subitens->execute([$grupo['id_acomp']]);
                $subitens = $stmt_subitens->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <ul class="subitens-list">
                    <?php foreach ($subitens as $subitem): ?>
                    <li class="subitem <?= $subitem['activated'] == 0 ? 'inactive' : '' ?>">
                        <span><?= htmlspecialchars($subitem['nome_subacomp']) ?></span>
                        <span class="subitem-price">
                            R$ <?= number_format($subitem['preco_subacomp'], 2, ',', '.') ?>
                            <?= $subitem['activated'] == 0 ? '<span class="badge badge-warning">Inativo</span>' : '' ?>
                        </span>
                        <a href="subacomp_ingredientes.php?id=<?= $subitem['id_subacomp'] ?>" class="btn-ingredientes" title="Gerenciar Ingredientes">
                            <i class="fas fa-list"></i>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="produtos-associados">
                    <div class="produtos-section">
                        <div class="produtos-header" onclick="toggleProdutos(<?= $grupo['id_acomp'] ?>)">
                            <h4>Produtos Associados <i class="fas fa-chevron-down"></i></h4>
                        </div>
                        <div class="produtos-content" id="produtos-<?= $grupo['id_acomp'] ?>" style="display: none;">
                            <?php 
                            $categoria_atual = null;
                            foreach ($produtos as $produto): 
                                if ($categoria_atual !== $produto['id_categoria']): 
                                    if ($categoria_atual !== null) echo '</div>'; // Fecha div da categoria anterior
                                    $categoria_atual = $produto['id_categoria'];
                            ?>
                                <div class="categoria-grupo">
                                    <div class="categoria-header">
                                        <?= htmlspecialchars($produto['nome_categoria']) ?>
                                    </div>
                            <?php endif; ?>
                                <div class="produto-checkbox-item">
                                    <input type="checkbox" 
                                           id="produto_<?= $grupo['id_acomp'] ?>_<?= $produto['id_produto'] ?>" 
                                           class="produto-checkbox"
                                           data-acomp-id="<?= $grupo['id_acomp'] ?>"
                                           data-produto-id="<?= $produto['id_produto'] ?>"
                                           <?= isset($produto_acomp[$produto['id_produto']]) && 
                                               array_filter($produto_acomp[$produto['id_produto']], 
                                               function($item) use ($grupo) { 
                                                   return $item['fk_acomp_id'] == $grupo['id_acomp']; 
                                               }) ? 'checked' : '' ?>>
                                    <label for="produto_<?= $grupo['id_acomp'] ?>_<?= $produto['id_produto'] ?>">
                                        <?= htmlspecialchars($produto['nome_produto']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($categoria_atual !== null) echo '</div>'; // Fecha última div de categoria ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="add-grupo-btn" onclick="novoGrupo()">
            <i class="fas fa-plus"></i>
        </div>
    </div>

    <!-- Modal para adicionar/editar grupo -->
    <div id="grupoModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Novo Grupo</h2>
            <form id="grupoForm" onsubmit="salvarGrupo(event)">
                <input type="hidden" id="grupoId" name="grupoId">
                <div class="form-group">
                    <label for="nomeGrupo">Nome do Grupo:</label>
                    <input type="text" id="nomeGrupo" name="nomeGrupo" required>
                </div>
                
                <div class="form-group">
                    <label>Sub-acompanhamentos:</label>
                    <div id="subAcompContainer">
                        <div class="sub-acomp-item">
                            <input type="text" name="sub_acomp[]" placeholder="Nome do sub-acompanhamento" required>
                            <input type="number" name="preco_sub[]" placeholder="Preço" step="0.01" min="0" value="0.00">
                            <button type="button" class="btn-action btn-delete" onclick="removerSubAcomp(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-action btn-add" onclick="adicionarSubAcomp()">
                        <i class="fas fa-plus"></i> Adicionar Sub-acompanhamento
                    </button>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-action btn-add">Salvar</button>
                    <button type="button" class="btn-action btn-delete" onclick="fecharModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/menu.js"></script>
    <script>
        // Funções do JavaScript aqui
        function novoGrupo() {
            document.getElementById('modalTitle').textContent = 'Novo Grupo';
            document.getElementById('grupoId').value = '';
            document.getElementById('nomeGrupo').value = '';
            document.getElementById('grupoModal').style.display = 'block';
        }

        function editarGrupo(id) {
            // Implementar edição
        }

        function deletarGrupo(id) {
            if (confirm('Tem certeza que deseja excluir este grupo?')) {
                // Implementar exclusão
            }
        }

        function adicionarSubitem(grupoId) {
            // Implementar adição de subitem
        }

        function fecharModal() {
            document.getElementById('grupoModal').style.display = 'none';
        }

        // Fechar modal quando clicar fora dele
        window.onclick = function(event) {
            if (event.target == document.getElementById('grupoModal')) {
                fecharModal();
            }
        }

        function alterarQuantidade(grupoId, tipo, valor) {
            const input = document.getElementById(`${tipo}_${grupoId}`);
            let novoValor = parseInt(input.value) + valor;
            
            if (tipo === 'min') {
                novoValor = Math.max(0, novoValor);
            } else {
                novoValor = Math.max(1, novoValor);
            }
            
            input.value = novoValor;
            salvarRegras(grupoId);
        }

        function alterarPrecificacao(grupoId, valor, botaoClicado) {
            // Atualiza visual dos botões
            const buttons = botaoClicado.parentElement.querySelectorAll('.btn-precificacao');
            buttons.forEach(btn => btn.classList.remove('active'));
            botaoClicado.classList.add('active');
            
            // Se tornou obrigatório, força mínimo 1
            if (valor === 1) {
                const minInput = document.getElementById(`min_${grupoId}`);
                if (parseInt(minInput.value) < 1) {
                    minInput.value = 1;
                }
            }

            // Salva imediatamente
            salvarRegras(grupoId);
        }

        function salvarRegras(grupoId) {
            const minInput = document.getElementById(`min_${grupoId}`);
            const maxInput = document.getElementById(`max_${grupoId}`);
            const botaoAtivo = document.querySelector(`[data-grupo-id="${grupoId}"] .btn-precificacao.active`);
            const isRequired = botaoAtivo ? botaoAtivo.dataset.value : '0';
            
            // Se for obrigatório, força mínimo 1
            let min = parseInt(minInput.value) || 0;
            if (isRequired === '1' && min < 1) {
                min = 1;
                minInput.value = 1;
            }
            
            // Garante que máximo seja sempre >= mínimo
            let max = parseInt(maxInput.value) || 1;
            if (max < min) {
                max = min;
                maxInput.value = max;
            }

            console.log('Salvando regras:', {
                grupoId,
                min,
                max,
                isRequired
            });

            const formData = new FormData();
            formData.append('produto_id', 1);
            formData.append('acomp_id', grupoId);
            formData.append('min_escolhas', min);
            formData.append('max_escolhas', max);
            formData.append('is_required', isRequired);

            fetch('../actions/acomp/salvar_regras_acomp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta:', data);
                if (data.status === 'success') {
                    const grupo = document.querySelector(`[data-grupo-id="${grupoId}"]`);
                    if (grupo) {
                        grupo.classList.add('saved');
                        setTimeout(() => grupo.classList.remove('saved'), 1000);
                    }
                } else {
                    console.error('Erro ao salvar:', data.message);
                    alert('Erro ao salvar as alterações');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar as alterações');
            });
        }

        // Função para salvar associação produto-acompanhamento
        $('.produto-checkbox').on('change', function() {
            const acompId = $(this).data('acomp-id');
            const produtoId = $(this).data('produto-id');
            const isChecked = $(this).prop('checked');
            
            // Pegar os valores atuais das regras
            const min = document.getElementById(`min_${acompId}`).value;
            const max = document.getElementById(`max_${acompId}`).value;
            const botaoAtivo = document.querySelector(`[data-grupo-id="${acompId}"] .btn-precificacao.active`);
            const isRequired = botaoAtivo ? botaoAtivo.dataset.value : '0';

            $.ajax({
                url: '../actions/acomp/associar_regras_produto.php',
                method: 'POST',
                data: {
                    acomp_id: acompId,
                    produto_id: produtoId,
                    min_escolhas: min,
                    max_escolhas: max,
                    is_required: isRequired,
                    acao: isChecked ? 'adicionar' : 'remover'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const checkbox = $(`#produto_${acompId}_${produtoId}`).closest('.produto-checkbox-item');
                        checkbox.addClass('updated');
                        setTimeout(() => checkbox.removeClass('updated'), 1000);
                    } else {
                        alert('Erro ao atualizar regras: ' + response.message);
                        $(this).prop('checked', !isChecked);
                    }
                },
                error: function() {
                    alert('Erro ao processar requisição');
                    $(this).prop('checked', !isChecked);
                }
            });
        });

        function adicionarSubAcomp() {
            console.log('Adicionando novo sub-acompanhamento');
            const container = document.getElementById('subAcompContainer');
            const novoItem = document.createElement('div');
            novoItem.className = 'sub-acomp-item';
            novoItem.innerHTML = `
                <input type="text" name="sub_acomp[]" placeholder="Nome do sub-acompanhamento" required>
                <input type="number" name="preco_sub[]" placeholder="Preço" step="0.01" min="0" value="0.00">
                <button type="button" class="btn-action btn-delete" onclick="removerSubAcomp(this)">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(novoItem);
        }

        function removerSubAcomp(botao) {
            console.log('Removendo sub-acompanhamento');
            botao.closest('.sub-acomp-item').remove();
        }

        function salvarGrupo(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            // Debug dos dados sendo enviados
            console.log('Dados do formulário:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            fetch('../actions/acomp/salvar_grupo_acomp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Status da resposta:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Resposta do servidor:', data);
                if (data.status === 'success') {
                    alert('Grupo e sub-acompanhamentos salvos com sucesso!');
                    location.reload();
                } else {
                    console.error('Erro ao salvar:', data);
                    alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Erro ao salvar grupo: ' + error.message);
            });
        }

        function toggleProdutos(grupoId) {
            const content = document.getElementById(`produtos-${grupoId}`);
            const header = content.previousElementSibling;
            const icon = header.querySelector('i');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                header.classList.add('active');
            } else {
                content.style.display = 'none';
                header.classList.remove('active');
            }
        }

        // Opcional: Função para abrir um específico se necessário
        function abrirProdutos(grupoId) {
            const content = document.getElementById(`produtos-${grupoId}`);
            const header = content.previousElementSibling;
            content.style.display = 'block';
            header.classList.add('active');
        }

        // Opcional: Função para fechar um específico se necessário
        function fecharProdutos(grupoId) {
            const content = document.getElementById(`produtos-${grupoId}`);
            const header = content.previousElementSibling;
            content.style.display = 'none';
            header.classList.remove('active');
        }
    </script>
</body>
</html>
