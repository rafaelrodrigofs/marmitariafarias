function carregarProdutos() {
    const busca = $('#busca_produto').val();
    const categoria = $('#filtro_categoria').val();

    $.ajax({
        url: '../actions/produtos/produtos.php',
        method: 'POST',
        data: { busca, categoria },
        dataType: 'json',
        success: function(response) {

            if (response.status === 'error') {
                $('#lista_produtos_content').html(`
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>${response.message}</p>
                    </div>
                `);
                return;
            }
            let html = '';
            response.categorias.forEach((categoria, idxCat) => {

                html += `
                    <div class="categoria-section">
                        <div class="categoria-header" onclick="toggleCategoria(${categoria.id})">
                            <div class="categoria-title">
                                <i class="fas fa-chevron-right toggle-icon" id="toggle-icon-${categoria.id}"></i>
                                <h3>${categoria.nome}</h3>
                            </div>
                            <button class="btn-add-produto" onclick="showAddProdutoModal(${categoria.id}); event.stopPropagation();">
                                <i class="fas fa-plus"></i> Adicionar Produto
                            </button>
                        </div>
                        <div class="produtos-list" id="produtos-list-${categoria.id}">
                `;

                categoria.produtos.forEach((produto, idxProd) => {

                    html += `
                        <div class="produto-item" data-id="${produto.id}">
                            <div class="produto-header produto-toggle" onclick="toggleAcompanhamentos(${produto.id})">
                                <div class="produto-info">
                                    <i class="fas fa-chevron-right icon-toggle"></i>
                                    <div class="produto-nome-preco">
                                        <span class="produto-nome ${parseInt(produto.activated) === 1 ? '' : 'inativo'}">
                                            <i class="fas fa-hamburger produto-icon"></i>
                                            ${produto.nome}
                                            <span class="pedidos-count ${parseInt(produto.pedidos_count) > 0 ? 'positivo' : 'zero'}">
                                                (${produto.pedidos_count || 0})
                                            </span>
                                        </span>
                                        <span class="produto-preco">
                                            <i class="fas fa-tag preco-icon"></i>
                                            R$ ${formatarValor(produto.preco)}
                                        </span>
                                    </div>
                                </div>
                                <div class="produto-acoes">
                                    <button class="btn-acao edit" onclick="editarProduto(${produto.id}); event.stopPropagation();" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-acao toggle ${parseInt(produto.activated) === 1 ? 'ativo' : 'inativo'}" 
                                            onclick="toggleStatus(${produto.id}); event.stopPropagation();" 
                                            title="${parseInt(produto.activated) === 1 ? 'Desativar' : 'Ativar'}">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                    <button class="btn-acao delete ${parseInt(produto.pedidos_count) > 0 ? 'disabled' : ''}"
                                            onclick="excluirProduto(${produto.id}); event.stopPropagation();"
                                            title="${parseInt(produto.pedidos_count) > 0 ? 'Não é possível excluir produtos com pedidos' : 'Excluir produto'}"
                                            ${parseInt(produto.pedidos_count) > 0 ? 'disabled' : ''}>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="acompanhamentos-container" id="acomp-${produto.id}" style="display: none;">
                                ${produto.acompanhamentos && produto.acompanhamentos.length > 0 ? 
                                    produto.acompanhamentos.map((acomp, idxAcomp) => {

                                        return `
                                        <div class="acomp-section" data-acomp-id="${acomp.id}">
                                            <div class="acomp-header">
                                                <i class="fas fa-plus-circle"></i>
                                                ${acomp.nome}
                                                <span class="acomp-count ${parseInt(acomp.pedidos_count) > 0 ? 'positivo' : 'zero'}">
                                                    (${acomp.pedidos_count || 0})
                                                </span>
                                                <div class="acomp-regras">
                                                    <span class="regra-badge ${acomp.is_required ? 'required' : ''}">
                                                        ${acomp.is_required ? 'Obrigatório' : 'Opcional'}
                                                    </span>
                                                    <span class="regra-badge">
                                                        Min: ${acomp.min_escolhas}
                                                    </span>
                                                    <span class="regra-badge">
                                                        Max: ${acomp.max_escolhas}
                                                    </span>
                                                </div>
                                                <button class="btn-add-subacomp" onclick="showAddSubacompModal(${acomp.id})">
                                                    <i class="fas fa-plus"></i> Adicionar Subacompanhamento
                                                </button>
                                            </div>
                                            <div class="subacomp-list">
                                                ${acomp.subacompanhamentos.map((subacomp, idxSubacomp) => {

                                                    return `
                                                    <div class="subacomp-item ${subacomp.activated ? 'active' : ''}" 
                                                         onclick="toggleSubacompStatus(${subacomp.id})" 
                                                         data-id="${subacomp.id}">
                                                        <div class="subacomp-info">
                                                            <i class="fas fa-check-circle status-icon"></i>
                                                            <span class="subacomp-nome">
                                                                ${subacomp.nome}
                                                                <span class="subacomp-count ${parseInt(subacomp.pedidos_count) > 0 ? 'positivo' : 'zero'}">
                                                                    (${subacomp.pedidos_count || 0})
                                                                </span>
                                                            </span>
                                                            ${subacomp.preco > 0 ? `
                                                                <span class="subacomp-preco">
                                                                    <i class="fas fa-coins"></i>
                                                                    R$ ${formatarValor(subacomp.preco)}
                                                                </span>
                                                            ` : ''}
                                                            <button class="btn-acao delete" onclick="deleteSubacomp(${subacomp.id}); event.stopPropagation();" title="Excluir">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                `}).join('')}
                                            </div>
                                        </div>
                                    `}).join('')
                                : `
                                    <div class="empty-acomp-state">
                                        <p>Nenhum acompanhamento cadastrado</p>
                                    </div>
                                `}
                                <div class="add-acomp-section">
                                    <button class="btn-add-acomp" onclick="showAddSubacompModal(${produto.id})">
                                        <i class="fas fa-plus"></i> Adicionar Acompanhamento
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += `
                            </div>
                        </div>
                    `;
            });

            // Adiciona o modal no final do HTML
            html += `
                <div id="addSubacompModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Novo Subacompanhamento</h3>
                            <button class="close-modal" onclick="closeAddSubacompModal()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="acomp_id">
                            <div class="form-group">
                                <label for="subacomp_nome">Nome</label>
                                <input type="text" id="subacomp_nome" class="form-input" placeholder="Nome do subacompanhamento">
                            </div>
                            <div class="form-group">
                                <label for="subacomp_preco">Preço (R$)</label>
                                <input type="number" id="subacomp_preco" class="form-input" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="form-actions">
                                <button class="btn-cancelar" onclick="closeAddSubacompModal()">Cancelar</button>
                                <button class="btn-salvar" onclick="saveSubacomp()">Salvar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Adiciona o modal no final do arquivo
            html += `
                <div id="addProdutoModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Novo Produto</h3>
                            <button class="close-modal" onclick="closeAddProdutoModal()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="categoria_id">
                            <div class="form-group">
                                <label for="produto_nome">Nome do Produto</label>
                                <input type="text" id="produto_nome" class="form-input" placeholder="Nome do produto">
                            </div>
                            <div class="form-group">
                                <label for="produto_preco">Preço (R$)</label>
                                <input type="number" id="produto_preco" class="form-input" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label>Acompanhamentos</label>
                                <div id="acompanhamentos_list" class="acompanhamentos-checkbox-list">
                                    <!-- Checkboxes serão inseridos aqui -->
                                </div>
                            </div>
                            <div class="form-actions">
                                <button class="btn-cancelar" onclick="closeAddProdutoModal()">Cancelar</button>
                                <button class="btn-salvar" onclick="saveProduto()">Salvar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('#lista_produtos_content').html(html);
        },
        error: function(xhr, status, error) {
            console.error('ERRO NA REQUISIÇÃO AJAX:', {
                xhr: xhr,
                status: status,
                error: error
            });
            alert('Erro ao processar requisição');
        }
    });
}

function formatarValor(valor) {
    return parseFloat(valor).toFixed(2).replace('.', ',');
}

function editarProduto(id) {
    window.location.href = `editar_produto.php?id=${id}`;
}

function toggleStatus(id) {
    const btn = $(`.produto-item[data-id="${id}"] .btn-acao.toggle`);
    const nome = $(`.produto-item[data-id="${id}"] .produto-nome`);
    
    $.ajax({
        url: '../actions/produtos/toggle_status_produto.php',
        method: 'POST',
        data: { id_produto: id },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                btn.addClass('status-changing');
                
                if (response.activated == 1) {
                    btn.removeClass('inativo').addClass('ativo');
                    nome.removeClass('inativo');
                    btn.attr('title', 'Desativar');
                } else {
                    btn.removeClass('ativo').addClass('inativo');
                    nome.addClass('inativo');
                    btn.attr('title', 'Ativar');
                }
                
                setTimeout(() => {
                    btn.removeClass('status-changing');
                }, 300);
            } else {
                alert('Erro ao alterar status do produto');
            }
        },
        error: function() {
            alert('Erro ao processar requisição');
        }
    });
}

function excluirProduto(id) {
    if (confirm('Tem certeza que deseja excluir este produto?')) {
        $.ajax({
            url: '../actions/produtos/delete_produto.php',
            method: 'POST',
            data: { id_produto: id },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    carregarProdutos();
                    alert('Produto excluído com sucesso!');
                } else {
                    alert(response.message || 'Erro ao excluir produto');
                }
            },
            error: function(xhr) {
                let mensagem = 'Erro ao excluir produto';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    mensagem = xhr.responseJSON.message;
                }
                alert(mensagem);
            }
        });
    }
}

// Função para expandir/recolher acompanhamentos
function toggleAcompanhamentos(produtoId) {
    const container = $(`#acomp-${produtoId}`);
    const icon = container.siblings('.produto-header').find('.icon-toggle');
    
    if (container.is(':visible')) {
        container.slideUp(300);
        icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
    } else {
        container.slideDown(300);
        icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
    }
}

$(document).ready(function() {
    carregarProdutos();

    $('#btn_filtrar').on('click', function() {
        carregarProdutos();
    });

    $('#busca_produto, #filtro_categoria').on('keyup change', function() {
        carregarProdutos();
    });

    $('#btn_gerenciar_categorias').on('click', function() {
        $('#modal_categorias').fadeIn().css('display', 'flex');
        carregarCategorias();
    });

    $('.close-modal, .btn-cancelar').on('click', function() {
        $('#modal_categorias').fadeOut();
        $('#nome_categoria').val('');
    });

    function carregarCategorias() {
        $.ajax({
            url: '../api/categorias/listar.php',
            method: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    const categorias = response.data;
                    const listaHTML = categorias.map(categoria => `
                        <div class="categoria-item" data-id="${categoria.id_categoria}">
                            <span class="categoria-nome">${categoria.nome_categoria}</span>
                            <div class="categoria-acoes">
                                <button class="btn-editar-categoria" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-excluir-categoria" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `).join('');
                    
                    $('.lista-categorias').html(listaHTML);
                }
            }
        });
    }

    $('.btn-salvar').on('click', function() {
        const nomeCategoria = $('#nome_categoria').val().trim();
        
        if (!nomeCategoria) {
            alert('Por favor, digite o nome da categoria');
            return;
        }

        $.ajax({
            url: '../api/categorias/adicionar.php',
            method: 'POST',
            data: { nome_categoria: nomeCategoria },
            success: function(response) {
                if (response.status === 'success') {
                    alert('Categoria adicionada com sucesso!');
                    $('#nome_categoria').val('');
                    carregarCategorias();
                } else {
                    alert('Erro ao adicionar categoria: ' + response.message);
                }
            }
        });
    });

    $(document).on('click', '.btn-editar-categoria', function() {
        const categoriaItem = $(this).closest('.categoria-item');
        const categoriaId = categoriaItem.data('id');
        const categoriaNome = categoriaItem.find('.categoria-nome').text();
        
        const novoNome = prompt('Digite o novo nome da categoria:', categoriaNome);
        
        if (novoNome && novoNome.trim() !== '') {
            $.ajax({
                url: '../api/categorias/atualizar.php',
                method: 'POST',
                data: {
                    id_categoria: categoriaId,
                    nome_categoria: novoNome.trim()
                },
                success: function(response) {
                    if (response.status === 'success') {
                        alert('Categoria atualizada com sucesso!');
                        carregarCategorias();
                    } else {
                        alert('Erro ao atualizar categoria: ' + response.message);
                    }
                }
            });
        }
    });

    $(document).on('click', '.btn-excluir-categoria', function() {
        const categoriaItem = $(this).closest('.categoria-item');
        const categoriaId = categoriaItem.data('id');
        
        if (confirm('Tem certeza que deseja excluir esta categoria?')) {
            $.ajax({
                url: '../api/categorias/excluir.php',
                method: 'POST',
                data: { id_categoria: categoriaId },
                success: function(response) {
                    if (response.status === 'success') {
                        alert('Categoria excluída com sucesso!');
                        carregarCategorias();
                    } else {
                        alert('Erro ao excluir categoria: ' + response.message);
                    }
                }
            });
        }
    });
});

function toggleSubacompStatus(id_subacomp) {
    const item = $(`.subacomp-item[data-id="${id_subacomp}"]`);
    item.addClass('status-changing');
    
    $.ajax({
        url: '../actions/produtos/toggle_subacomp_status.php',
        method: 'POST',
        data: { id_subacomp: id_subacomp },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                item.toggleClass('active');
            } else {
                alert('Erro ao atualizar status: ' + response.message);
            }
        },
        error: function() {
            alert('Erro na comunicação com o servidor');
        },
        complete: function() {
            setTimeout(() => {
                item.removeClass('status-changing');
            }, 300);
        }
    });
}

// Adicione o HTML do modal no final do seu arquivo, antes do </body>
html += `
<div id="addSubacompModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Novo Subacompanhamento</h3>
            <button class="close-modal" onclick="closeAddSubacompModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="acomp_id">
            <div class="form-group">
                <label for="subacomp_nome">Nome</label>
                <input type="text" id="subacomp_nome" class="form-input" placeholder="Nome do subacompanhamento">
            </div>
            <div class="form-group">
                <label for="subacomp_preco">Preço (R$)</label>
                <input type="number" id="subacomp_preco" class="form-input" step="0.01" min="0" placeholder="0.00">
            </div>
            <div class="form-actions">
                <button class="btn-cancelar" onclick="closeAddSubacompModal()">Cancelar</button>
                <button class="btn-salvar" onclick="saveSubacomp()">Salvar</button>
            </div>
        </div>
    </div>
</div>
`;

// Adicione as novas funções JavaScript
function showAddSubacompModal(acomp_id) {
    $('#acomp_id').val(acomp_id);
    $('#subacomp_nome').val('');
    $('#subacomp_preco').val('');
    $('#addSubacompModal').addClass('show');
}

function closeAddSubacompModal() {
    $('#addSubacompModal').removeClass('show');
}

function saveSubacomp() {
    const acomp_id = $('#acomp_id').val();
    const nome = $('#subacomp_nome').val();
    const preco = $('#subacomp_preco').val();

    if (!nome) {
        alert('Por favor, preencha o nome do subacompanhamento');
        return;
    }

    $.ajax({
        url: '../actions/produtos/add_subacomp.php',
        method: 'POST',
        data: {
            acomp_id: acomp_id,
            nome: nome,
            preco: preco || 0
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                closeAddSubacompModal();
                atualizarAcompanhamento(acomp_id);
            } else {
                alert('Erro ao salvar: ' + response.message);
            }
        },
        error: function() {
            alert('Erro na comunicação com o servidor');
        }
    });
}

function atualizarAcompanhamento(acomp_id) {
    $.ajax({
        url: '../actions/produtos/get_subacomp.php',
        method: 'POST',
        data: { acomp_id: acomp_id },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                let html = '';
                response.subacompanhamentos.forEach(subacomp => {
                    html += `
                        <div class="subacomp-item ${subacomp.activated ? 'active' : ''}" 
                             data-id="${subacomp.id}">
                            <div class="subacomp-info" onclick="toggleSubacompStatus(${subacomp.id})">
                                <i class="fas fa-check-circle status-icon"></i>
                                <span class="subacomp-nome">
                                    ${subacomp.nome}
                                    <span class="subacomp-count ${parseInt(subacomp.pedidos_count) > 0 ? 'positivo' : 'zero'}">
                                        (${subacomp.pedidos_count || 0})
                                    </span>
                                </span>
                                ${subacomp.preco > 0 ? `
                                    <span class="subacomp-preco">
                                        <i class="fas fa-coins"></i>
                                        R$ ${formatarValor(subacomp.preco)}
                                    </span>
                                ` : ''}
                                <button class="btn-acao delete" onclick="deleteSubacomp(${subacomp.id}); event.stopPropagation();" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });

                $(`.acomp-section[data-acomp-id="${acomp_id}"] .subacomp-list`).html(html);
            }
        }
    });
}

function deleteSubacomp(id_subacomp) {
    if (confirm('Tem certeza que deseja excluir este subacompanhamento?')) {
        $.ajax({
            url: '../actions/produtos/delete_subacomp.php',
            method: 'POST',
            data: { id_subacomp: id_subacomp },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Atualiza apenas a seção do acompanhamento
                    atualizarAcompanhamento(response.acomp_id);
                } else {
                    alert('Erro ao excluir: ' + response.message);
                }
            },
            error: function() {
                alert('Erro na comunicação com o servidor');
            }
        });
    }
}

function showAddProdutoModal(categoriaId) {
    $('#categoria_id').val(categoriaId);
    $('#produto_nome').val('');
    $('#produto_preco').val('');
    carregarAcompanhamentos(); // Carrega os acompanhamentos
    $('#addProdutoModal').addClass('show');
}

function closeAddProdutoModal() {
    $('#addProdutoModal').removeClass('show');
}

function saveProduto() {
    const categoriaId = $('#categoria_id').val();
    const nome = $('#produto_nome').val();
    const preco = $('#produto_preco').val();
    
    // Pegar todos os acompanhamentos selecionados
    const acompanhamentos = [];
    $('.acomp-checkbox:checked').each(function() {
        acompanhamentos.push($(this).val());
    });

    if (!nome) {
        alert('Por favor, preencha o nome do produto');
        return;
    }

    $.ajax({
        url: '../actions/produtos/adicionar_produto.php',
        method: 'POST',
        data: {
            categoria_id: categoriaId,
            nome: nome,
            preco: preco || 0,
            acompanhamentos: acompanhamentos
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                closeAddProdutoModal();
                carregarProdutos();
            } else {
                alert(response.message || 'Erro ao adicionar produto');
            }
        },
        error: function() {
            alert('Erro ao processar requisição');
        }
    });
}

function atualizarProdutos(categoria_id) {
    $.ajax({
        url: '../actions/produtos/get_produtos.php',
        method: 'POST',
        data: { categoria_id: categoria_id },
        dataType: 'json',
        success: function(response) {
            console.log('Resposta do servidor:', response);
            
            if (response.status === 'success') {
                let html = '';
                response.produtos.forEach(produto => {
                    html += `
                        <div class="produto-item" data-id="${produto.id}">
                            <div class="produto-header produto-toggle" onclick="toggleAcompanhamentos(${produto.id})">
                                <div class="produto-info">
                                    <i class="fas fa-chevron-right icon-toggle"></i>
                                    <div class="produto-nome-preco">
                                        <span class="produto-nome ${parseInt(produto.activated) === 1 ? '' : 'inativo'}">
                                            <i class="fas fa-hamburger produto-icon"></i>
                                            ${produto.nome}
                                            <span class="pedidos-count ${parseInt(produto.pedidos_count) > 0 ? 'positivo' : 'zero'}">
                                                (${produto.pedidos_count || 0})
                                            </span>
                                        </span>
                                        <span class="produto-preco">
                                            <i class="fas fa-tag preco-icon"></i>
                                            R$ ${formatarValor(produto.preco)}
                                        </span>
                                    </div>
                                </div>
                                <div class="produto-acoes">
                                    <button class="btn-acao edit" onclick="editarProduto(${produto.id}); event.stopPropagation();" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-acao toggle ${parseInt(produto.activated) === 1 ? 'ativo' : 'inativo'}" 
                                            onclick="toggleStatus(${produto.id}); event.stopPropagation();" 
                                            title="${parseInt(produto.activated) === 1 ? 'Desativar' : 'Ativar'}">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                    <button class="btn-acao delete ${parseInt(produto.pedidos_count) > 0 ? 'disabled' : ''}"
                                            onclick="excluirProduto(${produto.id}); event.stopPropagation();"
                                            title="${parseInt(produto.pedidos_count) > 0 ? 'Não é possível excluir produtos com pedidos' : 'Excluir produto'}"
                                            ${parseInt(produto.pedidos_count) > 0 ? 'disabled' : ''}>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="acompanhamentos-container" id="acomp-${produto.id}" style="display: none;">
                                ${produto.acompanhamentos && produto.acompanhamentos.length > 0 ? 
                                    produto.acompanhamentos.map(acomp => `
                                        <div class="acomp-section" data-acomp-id="${acomp.id}">
                                            <div class="acomp-header">
                                                <i class="fas fa-plus-circle"></i>
                                                ${acomp.nome}
                                                <span class="acomp-count ${parseInt(acomp.pedidos_count) > 0 ? 'positivo' : 'zero'}">
                                                    (${acomp.pedidos_count || 0})
                                                </span>
                                                <div class="acomp-regras">
                                                    <span class="regra-badge ${acomp.is_required ? 'required' : ''}">
                                                        ${acomp.is_required ? 'Obrigatório' : 'Opcional'}
                                                    </span>
                                                    <span class="regra-badge">
                                                        Min: ${acomp.min_escolhas}
                                                    </span>
                                                    <span class="regra-badge">
                                                        Max: ${acomp.max_escolhas}
                                                    </span>
                                                </div>
                                                <button class="btn-add-subacomp" onclick="showAddSubacompModal(${acomp.id})">
                                                    <i class="fas fa-plus"></i> Adicionar Subacompanhamento
                                                </button>
                                            </div>
                                            <div class="subacomp-list">
                                                ${acomp.subacompanhamentos.map(subacomp => `
                                                    <div class="subacomp-item ${subacomp.activated ? 'active' : ''}" 
                                                         onclick="toggleSubacompStatus(${subacomp.id})" 
                                                         data-id="${subacomp.id}">
                                                        <div class="subacomp-info">
                                                            <i class="fas fa-check-circle status-icon"></i>
                                                            <span class="subacomp-nome">
                                                                ${subacomp.nome}
                                                                <span class="subacomp-count ${parseInt(subacomp.pedidos_count) > 0 ? 'positivo' : 'zero'}">
                                                                    (${subacomp.pedidos_count || 0})
                                                                </span>
                                                            </span>
                                                            ${subacomp.preco > 0 ? `
                                                                <span class="subacomp-preco">
                                                                    <i class="fas fa-coins"></i>
                                                                    R$ ${formatarValor(subacomp.preco)}
                                                                </span>
                                                            ` : ''}
                                                            <button class="btn-acao delete" onclick="deleteSubacomp(${subacomp.id}); event.stopPropagation();" title="Excluir">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                    `).join('')
                                : `
                                    <div class="empty-acomp-state">
                                        <p>Nenhum acompanhamento cadastrado</p>
                                    </div>
                                `}
                                <div class="add-acomp-section">
                                    <button class="btn-add-acomp" onclick="showAddSubacompModal(${produto.id})">
                                        <i class="fas fa-plus"></i> Adicionar Acompanhamento
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });

                $(`.categoria-section[data-categoria-id="${categoria_id}"] .produtos-list`).html(html);
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro na requisição:', {
                status: status,
                error: error,
                response: xhr.responseText
            });
        }
    });
}

// Função para carregar acompanhamentos
function carregarAcompanhamentos() {
    $.ajax({
        url: '../actions/produtos/get_acompanhamentos.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                let html = '';
                response.acompanhamentos.forEach(acomp => {
                    html += `
                        <div class="acomp-checkbox-item">
                            <input type="checkbox" 
                                   id="acomp_${acomp.id_acomp}" 
                                   value="${acomp.id_acomp}"
                                   class="acomp-checkbox">
                            <label for="acomp_${acomp.id_acomp}">${acomp.nome_acomp}</label>
                        </div>
                    `;
                });
                $('#acompanhamentos_list').html(html);
            }
        }
    });
}

// Adicione esta nova função após as funções existentes
function toggleCategoria(categoriaId) {
    const produtosList = $(`#produtos-list-${categoriaId}`);
    const toggleIcon = $(`#toggle-icon-${categoriaId}`);
    
    produtosList.slideToggle(300);
    toggleIcon.toggleClass('expanded');
}