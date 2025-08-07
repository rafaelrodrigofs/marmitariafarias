// Variáveis globais
let itemEditando = null;

// Funções globais para manipulação de itens
function editarItem(itemId) {
    itemEditando = itemId;
    const $item = $(`.item-pedido[data-item-id="${itemId}"]`);
    
    // Preencher dados básicos
    const quantidade = $item.find('.quantidade').val();
    $('#quantidade_item').val(quantidade);
    
    // Buscar dados completos do item via AJAX
    $.ajax({
        url: '../actions/buscar_item.php',
        method: 'GET',
        data: { item_id: itemId },
        success: function(response) {
            if (response.status === 'success') {
                $('#produto_select').val(response.produto_id);
                $('#acompanhamentos_container').html(response.acompanhamentos);
                $('#modalItem').fadeIn();
            } else {
                alert('Erro ao carregar dados do item');
            }
        },
        error: function() {
            alert('Erro ao carregar dados do item');
        }
    });
}

function removerItem(itemId) {
    if (confirm('Tem certeza que deseja remover este item?')) {
        const $item = $(`.item-pedido[data-item-id="${itemId}"]`);
        $item.addClass('removido').fadeOut(300, function() {
            atualizarTotal();
        });
    }
}

function atualizarTotal() {
    let subtotal = 0;
    $('#lista_itens .item-pedido').each(function() {
        const quantidade = parseInt($(this).find('.quantidade').val()) || 1;
        const precoText = $(this).find('.item-valor').text().replace('R$', '').trim();
        const preco = parseFloat(precoText.replace(',', '.'));
        subtotal += quantidade * preco;
    });

    const taxa = parseFloat($('#taxa_entrega').val()) || 0;
    const total = subtotal + taxa;
    $('#total_pedido').val(total.toFixed(2));
}

$(document).ready(function() {
    // Expandir/Recolher detalhes do pedido
    $('.btn-expandir').click(function() {
        const pedido = $(this).closest('.pedido');
        const detalhes = pedido.find('.pedido-detalhes');
        const icon = $(this).find('i');
        
        detalhes.slideToggle();
        icon.toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Excluir pedido
    $('.btn-excluir').click(function() {
        const pedido = $(this).closest('.pedido');
        const pedidoId = pedido.data('pedido-id');
        
        if (confirm('Tem certeza que deseja excluir este pedido?')) {
            $.ajax({
                url: '../actions/excluir_pedido.php',
                method: 'POST',
                data: { id_pedido: pedidoId },
                success: function(response) {
                    if (response.status === 'success') {
                        pedido.slideUp(400, function() {
                            $(this).remove();
                            atualizarTotalRegistros();
                        });
                    } else {
                        alert('Erro ao excluir pedido: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro:', error);
                    alert('Erro ao excluir pedido. Por favor, tente novamente.');
                }
            });
        }
    });

    // Filtrar pedidos
    $('#btn_filtrar').click(function() {
        const cliente = $('#busca_cliente').val();
        const dataInicio = $('#data_inicio').val();
        const dataFim = $('#data_fim').val();

        $.ajax({
            url: '../actions/filtrar_pedidos.php',
            method: 'POST',
            data: {
                cliente: cliente,
                data_inicio: dataInicio,
                data_fim: dataFim
            },
            success: function(response) {
                $('.tabela-pedidos').html(response);
                atualizarTotalRegistros();
            },
            error: function(xhr, status, error) {
                console.error('Erro:', error);
                alert('Erro ao filtrar pedidos. Por favor, tente novamente.');
            }
        });
    });

    function atualizarTotalRegistros() {
        const total = $('.pedido').length;
        $('.total-registros').text(`Total: ${total} pedidos`);
    }

    // Função para abrir o modal de edição
    $('.btn-editar').click(function(e) {
        e.preventDefault();
        const pedidoId = $(this).closest('.pedido').data('pedido-id');
        
        // Carregar dados do pedido via AJAX usando busca_pedidos.php
        $.ajax({
            url: '../actions/busca_pedidos.php',
            method: 'GET',
            data: { id_pedido: pedidoId },
            success: function(response) {
                if (response.status === 'success') {
                    // Armazenar o ID do pedido no modal
                    $('#modalEditarPedido').data('pedido-id', pedidoId);
                    preencherModal(response);
                    $('#modalEditarPedido').fadeIn();
                } else {
                    alert('Erro ao carregar dados do pedido: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro:', error);
                alert('Erro ao carregar dados do pedido.');
            }
        });
    });

    // Fechar modal
    $('.close-modal').click(function() {
        $('#modalEditarPedido').fadeOut();
    });

    // Fechar modal clicando fora
    $(window).click(function(e) {
        if ($(e.target).is('#modalEditarPedido')) {
            $('#modalEditarPedido').fadeOut();
        }
    });

    // Função para preencher o modal com os dados do pedido
    function preencherModal(response) {
        const pedido = response.pedido;
        
        // Garantir que o ID do pedido está armazenado
        $('#pedido_id').val(pedido.id_pedido);
        $('#modalEditarPedido').data('pedido-id', pedido.id_pedido);
        
        $('#numeroPedidoModal').text('#' + String(pedido.num_pedido).padStart(5, '0'));
        $('#nome_cliente').val(pedido.nome_cliente);
        $('#telefone_cliente').val(pedido.telefone_cliente);
        $('#endereco_entrega').val(pedido.nome_entrega);
        $('#numero_entrega').val(pedido.numero_entrega);
        $('#bairro_entrega').val(pedido.bairro_id);
        
        // Selecionar o método de pagamento correto
        $('#metodo_pagamento').val(pedido.id_pagamento);
        
        $('#taxa_entrega').val(pedido.taxa_entrega);
        $('#total_pedido').val(pedido.total);
        
        // Preencher lista de itens com botões de edição/exclusão
        let listaItens = '';
        response.itens.forEach(item => {
            const acompsHtml = item.acompanhamentos ? 
                `<div class="acompanhamentos"><small>${item.acompanhamentos}</small></div>` : '';

            listaItens += `
                <div class="item-pedido" data-item-id="${item.id_pedido_item}">
                    <div class="item-info">
                        <input type="number" value="${item.quantidade}" min="1" class="quantidade" onchange="atualizarTotal()">
                        <span>${item.nome_produto}</span>
                        ${item.acompanhamentos && item.acompanhamentos.length > 0 ? 
                            `<div class="acompanhamentos">
                                ${item.acompanhamentos.map(acomp => 
                                    `<small>${acomp.nome_subacomp} (${acomp.nome_acomp})</small>`
                                ).join('<br>')}
                            </div>` 
                            : ''
                        }
                    </div>
                    <div class="item-valor">
                        R$ ${parseFloat(item.preco_unitario).toFixed(2)}
                    </div>
                    <div class="item-acoes">
                        <button type="button" class="btn-editar-item" onclick="editarItem(${item.id_pedido_item})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn-remover-item" onclick="removerItem(${item.id_pedido_item})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        $('#lista_itens').html(listaItens);

        // Atualizar total inicial
        atualizarTotal();
    }

    // Salvar alterações do pedido
    $('#btn_salvar_pedido').click(function() {
        const itens = [];
        $('#lista_itens .item-pedido').each(function() {
            const $item = $(this);
            const itemId = $item.data('item-id');
            const precoUnitario = parseFloat($item.data('preco')) || 0;
            
            itens.push({
                id: itemId.toString().startsWith('novo_') ? null : itemId,
                produto_id: $item.data('produto-id'),
                quantidade: parseInt($item.find('.quantidade').val()) || 1,
                preco_unitario: precoUnitario,
                acompanhamentos: $item.data('acompanhamentos') || [],
                removido: $item.hasClass('removido')
            });
        });

        const dadosAtualizados = {
            id_pedido: $('#modalEditarPedido').data('pedido-id'),
            endereco_entrega: $('#endereco_entrega').val().trim(),
            numero_entrega: $('#numero_entrega').val().trim(),
            bairro_id: $('#bairro_entrega').val(),
            metodo_pagamento: $('#metodo_pagamento').val(),
            taxa_entrega: $('#taxa_entrega').val(),
            itens: itens
        };

        console.log('Dados sendo enviados:', dadosAtualizados);

        $.ajax({
            url: '../actions/atualizar_pedido.php',
            method: 'POST',
            data: dadosAtualizados,
            success: function(response) {
                if (response.status === 'success') {
                    location.reload();
                } else {
                    alert('Erro ao salvar pedido: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro:', error);
                console.error('Resposta:', xhr.responseText);
                alert('Erro ao salvar pedido: ' + error);
            }
        });
    });

    // Adicionar evento de mudança no select de bairro
    $('#bairro_entrega').change(function() {
        // Pegar o valor da taxa do texto da opção selecionada
        const selectedOption = $(this).find('option:selected');
        const taxaText = selectedOption.text().split('R$')[1].trim();
        const taxa = parseFloat(taxaText);
        
        // Atualizar campo de taxa de entrega
        $('#taxa_entrega').val(taxa.toFixed(2));
        
        // Recalcular total
        atualizarTotal();
    });

    // Botão adicionar item
    $('#btn_adicionar_item').click(function() {
        itemEditando = null;
        $('#produto_select').val('');
        $('#quantidade_item').val(1);
        $('#acompanhamentos_container').empty();
        $('#modalItem').fadeIn();
    });

    // Salvar item
    $('#btn_salvar_item').click(function() {
        const produtoId = $('#produto_select').val();
        const quantidade = parseInt($('#quantidade_item').val()) || 1;
        const precoBase = parseFloat($('#produto_select option:selected').data('preco')) || 0;
        
        if (!produtoId) {
            alert('Selecione um produto');
            return;
        }

        // Coletar acompanhamentos selecionados e seus preços
        const acompanhamentos = [];
        let precoTotal = precoBase;
        
        $('.acomp-select').each(function() {
            const $select = $(this);
            const $selected = $select.find('option:selected');
            if ($selected.val()) {
                const precoSubacomp = parseFloat($selected.data('preco')) || 0;
                precoTotal += precoSubacomp;
                
                acompanhamentos.push({
                    acomp_id: $select.data('acomp-id'),
                    subacomp_id: $selected.val(),
                    nome_subacomp: $selected.text(),
                    nome_acomp: $select.prev('label').text().replace(':', ''),
                    preco_subacomp: precoSubacomp
                });
            }
        });

        // Criar HTML do item com o preço total calculado
        const itemHtml = `
            <div class="item-pedido" 
                 data-item-id="${itemEditando || 'novo_' + Date.now()}"
                 data-produto-id="${produtoId}"
                 data-preco="${precoTotal}"
                 data-acompanhamentos='${JSON.stringify(acompanhamentos)}'>
                <div class="item-info">
                    <input type="number" value="${quantidade}" min="1" class="quantidade" onchange="atualizarTotal()">
                    <span>${$('#produto_select option:selected').text()}</span>
                    ${acompanhamentos.length > 0 ? 
                        `<div class="acompanhamentos">
                            ${acompanhamentos.map(acomp => 
                                `<small>${acomp.nome_subacomp} (R$ ${acomp.preco_subacomp.toFixed(2)})</small>`
                            ).join('<br>')}
                        </div>` : 
                        ''}
                </div>
                <div class="item-valor">
                    R$ ${(precoTotal * quantidade).toFixed(2)}
                </div>
                <div class="item-acoes">
                    <button type="button" class="btn-editar-item" onclick="editarItem('${itemEditando || 'novo_' + Date.now()}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-remover-item" onclick="removerItem('${itemEditando || 'novo_' + Date.now()}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;

        // Adicionar ou atualizar item na lista
        if (itemEditando) {
            $(`.item-pedido[data-item-id="${itemEditando}"]`).replaceWith(itemHtml);
        } else {
            $('#lista_itens').append(itemHtml);
        }

        $('#modalItem').fadeOut();
        atualizarTotal();
    });

    // Fechar modal de item
    $('.close-modal-item, #modalItem .close-modal').click(function() {
        $('#modalItem').fadeOut();
    });

    // Fechar modal clicando fora
    $(window).click(function(e) {
        if ($(e.target).is('#modalItem')) {
            $('#modalItem').fadeOut();
        }
    });

    // Quando selecionar um produto, carregar acompanhamentos
    $('#produto_select').change(function() {
        const produtoId = $(this).val();
        if (produtoId) {
            $.ajax({
                url: '../actions/buscar_acompanhamentos.php',
                method: 'GET',
                data: { produto_id: produtoId },
                success: function(response) {
                    $('#acompanhamentos_container').html(response);
                }
            });
        } else {
            $('#acompanhamentos_container').empty();
        }
    });

    // Função para visualizar detalhes do pedido
    function visualizarPedido(pedidoId) {
        $.ajax({
            url: '../actions/relatorio_pedidos/get_pedido_detalhes.php',
            method: 'POST',
            data: { id_pedido: pedidoId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let html = `
                        <div class="modal-detalhes-pedido">
                            <div class="pedido-header">
                                <div>
                                    <h3>Pedido #${response.pedido.num_pedido}</h3>
                                    <button class="btn-fechar-modal">&times;</button>
                                </div>
                                <div>
                                    <span class="horario">${response.pedido.data_pedido}</span>
                                    <span class="status">${response.pedido.status || 'Concluído'}</span>
                                </div>
                            </div>

                            <div class="pedido-itens">
                                <h4>Itens do pedido</h4>
                                <div class="itens-lista">`;
                    
                    let subtotal = 0;
                    response.itens.forEach(item => {
                        // Garantir que o preço unitário seja um número
                        const precoUnitario = parseFloat(item.preco_unitario) || 0;
                        let valorItem = item.quantidade * precoUnitario;
                        
                        if (item.acompanhamentos && item.acompanhamentos.length > 0) {
                            item.acompanhamentos.forEach(acomp => {
                                // Garantir que o preço do acompanhamento seja um número
                                const precoAcomp = parseFloat(acomp.preco_unitario) || 0;
                                valorItem += precoAcomp;
                            });
                        }
                        
                        subtotal += valorItem;
                        
                        html += `
                            <div class="item">
                                <div class="item-principal">
                                    <span class="quantidade">${item.quantidade}x</span>
                                    <span class="nome">${item.nome_produto}</span>
                                    <span class="preco">R$ ${valorItem.toFixed(2)}</span>
                                </div>`;
                        
                        if (item.acompanhamentos && item.acompanhamentos.length > 0) {
                            html += `<div class="acompanhamentos">`;
                            item.acompanhamentos.forEach(acomp => {
                                // Garantir que o preço do acompanhamento seja um número
                                const precoAcomp = parseFloat(acomp.preco_unitario) || 0;
                                html += `
                                    <div class="acomp-item">
                                        <span class="acomp-nome">- ${acomp.nome_subacomp}</span>
                                        ${precoAcomp > 0 ? 
                                            `<span class="acomp-preco">R$ ${precoAcomp.toFixed(2)}</span>` 
                                            : ''}
                                    </div>`;
                            });
                            html += `</div>`;
                        }
                        
                        if (item.observacao) {
                            html += `<div class="observacao">Obs: ${item.observacao}</div>`;
                        }
                        
                        html += `</div>`;
                    });

                    const taxaEntrega = parseFloat(response.pedido.taxa_entrega) || 0;
                    const totalFinal = subtotal + taxaEntrega;
                    const totalSalvo = parseFloat(response.pedido.sub_total) || 0;

                    // Verificar diferença entre subtotal calculado e total salvo
                    const temDiferenca = Math.abs(totalSalvo - subtotal) > 0.01; // diferença maior que 1 centavo

                    html += `
                                </div>
                            </div>
                            <div class="pedido-totais">
                                <div class="total-item">
                                    <span>Subtotal</span>
                                    <span>R$ ${subtotal.toFixed(2)}</span>
                                </div>
                                <div class="total-item">
                                    <span>Taxa de Entrega</span>
                                    <span>R$ ${taxaEntrega.toFixed(2)}</span>
                                </div>
                                <div class="total-item total-final">
                                    <span>Total</span>
                                    <span>R$ ${totalFinal.toFixed(2)}</span>
                                </div>`;

                    // Adicionar aviso se houver diferença
                    if (temDiferenca) {
                        html += `
                            <div class="alerta-diferenca">
                                <div class="mensagem-alerta">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Atenção: O subtotal calculado (R$ ${subtotal.toFixed(2)}) está diferente do valor salvo no banco (R$ ${totalSalvo.toFixed(2)})</span>
                                </div>
                                <button class="btn-atualizar-total" data-pedido-id="${pedidoId}" data-novo-total="${subtotal}">
                                    <i class="fas fa-sync-alt"></i> Atualizar valor no banco
                                </button>
                            </div>`;
                    }

                    html += `
                            </div>
                        </div>`;

                    $('#modalDetalhes .modal-content').html(html);
                    $('#modalDetalhes').fadeIn();
                } else {
                    alert('Erro ao carregar detalhes do pedido: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Erro na comunicação com o servidor');
            }
        });
    }

    // Adicionar evento de clique no ícone de visualização
    $(document).on('click', '.btn-acao.visualizar', function() {
        const pedidoId = $(this).closest('.pedido').data('pedido-id');
        visualizarPedido(pedidoId);
    });

    // Fechar modal
    $(document).on('click', '.btn-fechar-modal, .close-modal, .modal-backdrop', function() {
        $('#modalDetalhes').fadeOut();
    });

    // Adicionar handler para o botão de atualização
    $(document).on('click', '.btn-atualizar-total', function() {
        const pedidoId = $(this).data('pedido-id');
        const novoTotal = $(this).data('novo-total');
        
        if (confirm('Deseja atualizar o valor total deste pedido no banco de dados?')) {
            $.ajax({
                url: '../actions/corrigir_total_pedido.php',
                method: 'POST',
                data: {
                    id_pedido: pedidoId,
                    novo_total: novoTotal
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert('Valor atualizado com sucesso!');
                        $('.alerta-diferenca').fadeOut();
                        // Atualizar o valor na lista de pedidos também
                        $(`.pedido[data-pedido-id="${pedidoId}"] .coluna-valor`).text(
                            `R$ ${parseFloat(novoTotal).toFixed(2).replace('.', ',')}`
                        );
                    } else {
                        alert('Erro ao atualizar valor: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro na comunicação com o servidor');
                }
            });
        }
    });
    
}); 

// Função para ir para uma página específica
function irParaPagina() {
    const input = document.getElementById('pagina-input');
    const pagina = parseInt(input.value);
    const maxPagina = parseInt(input.max);
    
    if (pagina >= 1 && pagina <= maxPagina) {
        mudarPagina(pagina);
    } else {
        alert('Por favor, insira um número de página válido entre 1 e ' + maxPagina);
        input.value = input.defaultValue;
    }
}

// Adicionar evento de tecla Enter no input
document.getElementById('pagina-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        irParaPagina();
    }
});

// Função para mudar de página (atualizada)
function mudarPagina(pagina) {
    let url = new URL(window.location.href);
    url.searchParams.set('pagina', pagina);
    
    // Manter os filtros existentes
    const filtros = ['data_inicio', 'data_fim', 'cliente', 'retirada', 'empresas', 'pendentes'];
    filtros.forEach(filtro => {
        if (url.searchParams.has(filtro)) {
            url.searchParams.set(filtro, url.searchParams.get(filtro));
        }
    });
    
    window.location.href = url.toString();
}

// Função para exportar para Excel
function exportarExcel() {
    let data = [];
    const pedidos = document.querySelectorAll('.pedido');
    
    pedidos.forEach(pedido => {
        const valorElement = pedido.querySelector('.valor').textContent.trim();
        // Removendo "R$ " e convertendo para número
        const valor = parseFloat(valorElement.replace('R$ ', '').replace('.', '').replace(',', '.'));
        
        data.push({
            'Nº Pedido': pedido.querySelector('.numero-pedido').textContent.trim(),
            'Cliente': pedido.querySelector('.cliente-nome').textContent.trim(),
            'Telefone': pedido.querySelector('.cliente-telefone').textContent.trim(),
            'Data': pedido.querySelector('.data').textContent.trim(),
            'Bairro': pedido.querySelector('.forma-entrega span').textContent.trim(),
            'Pagamento': pedido.querySelector('.pagamento').textContent.trim(),
            'Status': pedido.querySelector('.status-pagamento').textContent.trim(),
            'Valor Total': `R$ ${valor.toFixed(2).replace('.', ',')}`
        });
    });
    
    const worksheet = XLSX.utils.json_to_sheet(data);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Pedidos");
    XLSX.writeFile(workbook, "relatorio_pedidos.xlsx");
}

// Função para filtrar
document.getElementById('btn_filtrar').addEventListener('click', function() {
    const dataInicio = document.getElementById('data_inicio').value;
    const dataFim = document.getElementById('data_fim').value;
    const cliente = document.getElementById('busca_cliente').value;
    
    let url = new URL(window.location.href);
    url.searchParams.set('data_inicio', dataInicio);
    url.searchParams.set('data_fim', dataFim);
    url.searchParams.set('cliente', cliente);
    
    window.location.href = url.toString();
});

// Inicializar valores dos filtros
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has('data_inicio')) {
        document.getElementById('data_inicio').value = urlParams.get('data_inicio');
    }
    if (urlParams.has('data_fim')) {
        document.getElementById('data_fim').value = urlParams.get('data_fim');
    }
    if (urlParams.has('cliente')) {
        document.getElementById('busca_cliente').value = urlParams.get('cliente');
    }
});

function excluirPedido(id) {
    if (confirm('Tem certeza que deseja excluir este pedido?')) {
        fetch(`excluir_pedido.php?id=${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove o pedido da tela
                document.querySelector(`[data-pedido-id="${id}"]`).remove();
                alert('Pedido excluído com sucesso!');
            } else {
                alert('Erro ao excluir pedido: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao excluir pedido');
        });
    }
}

function excluirTodosPedidos() {
    if (!confirm('ATENÇÃO: Você está prestes a excluir TODOS os pedidos. Esta ação não pode ser desfeita. Deseja continuar?')) {
        return;
    }

    fetch('../actions/excluir_todos_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Todos os pedidos foram excluídos com sucesso!');
            location.reload();
        } else {
            alert(data.error || 'Erro ao excluir pedidos');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir pedidos');
    });
}

// Variáveis globais
let selectedIds = new Set();
const btnExcluirSelecionados = document.getElementById('excluir-selecionados');
const countSelecionados = document.getElementById('count-selecionados');

// Função para atualizar o botão de excluir
function atualizarBotaoExcluir() {
    const quantidade = selectedIds.size;
    countSelecionados.textContent = quantidade;
    btnExcluirSelecionados.style.display = quantidade > 0 ? 'block' : 'none';
}

// Selecionar todos
document.getElementById('selecionar-todos').addEventListener('click', function() {
    const pedidos = document.querySelectorAll('.pedido');
    const isSelectAll = this.classList.toggle('selected');
    
    pedidos.forEach(pedido => {
        const pedidoId = pedido.dataset.pedidoId;
        if (isSelectAll) {
            pedido.classList.add('selected');
            selectedIds.add(pedidoId);
        } else {
            pedido.classList.remove('selected');
            selectedIds.delete(pedidoId);
        }
    });
    
    atualizarBotaoExcluir();
});

// Evento para seleção de pedido
document.addEventListener('click', function(e) {
    // Se o clique foi no status-badge ou em seus elementos filhos, não seleciona o pedido
    if (e.target.closest('.status-badge')) return;
    
    // Se o clique foi em algum botão de ação, não seleciona o pedido
    if (e.target.closest('.btn-acao')) return;

    const pedido = e.target.closest('.pedido');
    if (!pedido) return;

    const pedidoId = pedido.dataset.pedidoId;
    pedido.classList.toggle('selected');
    
    if (pedido.classList.contains('selected')) {
        selectedIds.add(pedidoId);
    } else {
        selectedIds.delete(pedidoId);
    }
    
    atualizarBotaoExcluir();
});

// Evento separado para status-badge
document.addEventListener('click', function(e) {
    const statusBadge = e.target.closest('.status-badge');
    if (!statusBadge) return;

    e.stopPropagation(); // Impede a propagação do evento para o pedido
    
    const pedidoId = statusBadge.dataset.pedidoId;
    const statusAtual = statusBadge.dataset.status === '1';

    fetch('../actions/relatorio_pedidos/alterar_status_pagamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            pedido_id: pedidoId,
            novo_status: !statusAtual
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusBadge.classList.toggle('pago');
            statusBadge.classList.toggle('pendente');
            statusBadge.textContent = statusAtual ? 'PENDENTE' : 'PAGO';
            statusBadge.dataset.status = statusAtual ? '0' : '1';
            
            const pedido = statusBadge.closest('.pedido');
            pedido.style.backgroundColor = 'rgba(72, 187, 120, 0.1)';
            setTimeout(() => {
                pedido.style.backgroundColor = '';
            }, 500);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
    });
});

// Excluir selecionados
btnExcluirSelecionados.addEventListener('click', function() {
    if (!confirm(`Deseja excluir ${selectedIds.size} pedidos selecionados?`)) return;
    
    const idsArray = Array.from(selectedIds);
    
    fetch('../actions/relatorio_pedidos/excluir_pedidos_selecionados.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(idsArray)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove os elementos da página
            idsArray.forEach(id => {
                const pedido = document.querySelector(`.pedido[data-pedido-id="${id}"]`);
                if (pedido) {
                    pedido.remove();
                }
            });
            
            // Limpa a seleção
            selectedIds.clear();
            document.getElementById('selecionar-todos').classList.remove('selected');
            atualizarBotaoExcluir();
            
            // Atualiza o total de pedidos
            const totalElement = document.querySelector('.total-registros');
            if (totalElement) {
                const currentTotal = parseInt(totalElement.textContent.match(/\d+/)[0]);
                totalElement.textContent = `Total: ${currentTotal - idsArray.length} pedidos`;
            }
            
            alert('Pedidos excluídos com sucesso!');
        } else {
            alert(data.message || 'Erro ao excluir pedidos');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir pedidos');
    });
});

// Inicialização
atualizarBotaoExcluir();

// Inicialização dos grupos de pedidos
document.addEventListener('DOMContentLoaded', function() {
    // Prevenir que o clique no select propague para o header
    document.querySelectorAll('.filtro-pagamento-select').forEach(select => {
        select.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });

    document.querySelectorAll('.grupo-header').forEach(header => {
        header.addEventListener('click', function(e) {
            // Ignorar se o clique foi no select
            if (e.target.closest('.filtro-pagamento-select')) {
                return;
            }

            const pedidosDoDia = this.nextElementSibling;
            const btnExpandir = this.querySelector('.btn-expandir i');
            
            if (pedidosDoDia.style.display === 'none') {
                pedidosDoDia.style.display = 'block';
                this.classList.add('expanded');
            } else {
                pedidosDoDia.style.display = 'none';
                this.classList.remove('expanded');
            }
        });
    });
});

// Adicionar evento para excluir individual
document.addEventListener('click', function(e) {
    const btnExcluir = e.target.closest('.btn-acao.excluir');
    if (!btnExcluir) return;

    const pedido = btnExcluir.closest('.pedido');
    const pedidoId = pedido.dataset.pedidoId;

    if (!confirm('Deseja realmente excluir este pedido?')) return;

    fetch('../actions/relatorio_pedidos/excluir_pedidos_selecionados.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify([pedidoId]) // Enviando como array com um único ID
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            pedido.remove();
            
            // Atualiza o total de pedidos
            const totalElement = document.querySelector('.total-registros');
            if (totalElement) {
                const currentTotal = parseInt(totalElement.textContent.match(/\d+/)[0]);
                totalElement.textContent = `Total: ${currentTotal - 1} pedidos`;
            }
            
            alert('Pedido excluído com sucesso!');
        } else {
            alert(data.message || 'Erro ao excluir pedido');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir pedido');
    });
});

function atualizarPagamento(select) {
    const pedidoId = select.dataset.pedidoId;
    const metodoPagamento = select.value;

    fetch('../actions/atualizar_pagamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            pedido_id: pedidoId,
            metodo_pagamento: metodoPagamento
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Feedback visual
            const coluna = select.closest('.coluna-pagamento');
            coluna.style.backgroundColor = 'rgba(72, 187, 120, 0.1)';
            setTimeout(() => {
                coluna.style.backgroundColor = '';
            }, 500);
        } else {
            alert('Erro ao atualizar forma de pagamento');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar forma de pagamento');
    });
}

function atualizarTaxaRetirada(pedidoId) {
    if (confirm('Deseja zerar a taxa de entrega deste pedido de retirada?')) {
        fetch('../actions/atualizar_taxa_retirada.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                pedido_id: pedidoId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Taxa de entrega zerada com sucesso!');
                location.reload();
            } else {
                alert('Erro ao atualizar taxa: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao atualizar taxa');
        });
    }
}

// Adicionar o filtro de pagamento
document.addEventListener('change', function(e) {
    if (!e.target.matches('.filtro-pagamento-select')) return;
    
    const data = e.target.dataset.data;
    const metodoPagamento = e.target.value;
    const grupoPedidos = e.target.closest('.grupo-pedidos');
    const pedidos = grupoPedidos.querySelectorAll('.pedido');
    
    pedidos.forEach(pedido => {
        const pagamentoElement = pedido.querySelector('.pagamento-select');
        const pagamentoTexto = pagamentoElement.options[pagamentoElement.selectedIndex].text;
        
        if (metodoPagamento === '' || pagamentoTexto === metodoPagamento) {
            pedido.style.display = '';
        } else {
            pedido.style.display = 'none';
        }
    });
    
    // Atualizar estatísticas do grupo
    const pedidosVisiveis = Array.from(pedidos).filter(p => p.style.display !== 'none');
    const totalPedidos = pedidosVisiveis.length;
    
    // Atualizar contador de pedidos
    const statValue = grupoPedidos.querySelector('.stat-value');
    statValue.textContent = `${totalPedidos} pedidos`;
    
    // Atualizar valor total
    let valorTotal = 0;
    pedidosVisiveis.forEach(pedido => {
        const valorTexto = pedido.querySelector('.coluna-valor').textContent
            .replace('R$ ', '')
            .replace('.', '')
            .replace(',', '.');
        valorTotal += parseFloat(valorTexto);
    });
    
    const valorTotalElement = grupoPedidos.querySelector('.valor-total');
    valorTotalElement.textContent = `R$ ${valorTotal.toFixed(2).replace('.', ',')}`;
});

