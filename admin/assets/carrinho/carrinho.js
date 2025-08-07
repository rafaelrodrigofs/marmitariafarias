// Event handlers
$('.btn-cancelar').on('click', function() {
    $('.modal-carrinho').removeClass('active');
});

// Função para buscar dados do carrinho
function buscarDadosCarrinho() {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: "../actions/carrinho/ver_carrinho.php",
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                resolve(data);
            },
            error: function(xhr, status, error) {
                reject(error);
            }
        });
    });
}

function formatarTelefone(telefone) {
    if (!telefone) return '';
    
    // Remove tudo que não for número
    const numero = telefone.replace(/\D/g, '');
    
    // Formata de acordo com o tamanho
    if (numero.length === 11) {
        return `(${numero.slice(0,2)}) ${numero.slice(2,7)}-${numero.slice(7)}`;
    } else if (numero.length === 10) {
        return `(${numero.slice(0,2)}) ${numero.slice(2,6)}-${numero.slice(6)}`;
    }
    return telefone; // Retorna original se não conseguir formatar
}

function mostrarModalCarrinho(data) {
    // Criar estrutura básica do modal
    var modal = $('<div>').addClass('modal-base modal-carrinho');
    var modalContent = $('<div>').addClass('modal-content');
    
    // 1. Seção de Informações do Pedido (unificada)
    var pedidoSection = `
        <div class="pedido-section">
            <h3>Informações do Pedido</h3>
            <div class="pedido-info">
                <div class="form-group">
                    <label>Número do Pedido:</label>
                    <input type="text" id="numero_pedido" value="${data.numero_pedido || ''}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Data do Pedido:</label>
                    <input type="date" id="data_pedido" value="${data.data_pedido || ''}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Hora do Pedido:</label>
                    <input type="time" id="hora_pedido" value="${data.hora_pedido || ''}" class="form-control">
                </div>
            </div>
        </div>
    `;
    modalContent.append(pedidoSection);
    
    // 2. Seção de Cliente
    var clienteSection = criarSecaoCliente(data);
    modalContent.append(clienteSection);

    // 3. Seção de Produtos
    if (data.produtos && data.produtos.length > 0) {
        var produtosSection = criarSecaoProdutos(data);
        modalContent.append(produtosSection);
    } else {
        modalContent.append('<p class="carrinho-vazio">Carrinho vazio</p>');
    }

    // 4. Seção de Entrega
    var entregaSection = criarSecaoEntrega(data);
    modalContent.append(entregaSection);

    // 5. Seção de Pagamento
    var pagamentoSection = criarSecaoPagamento(data);
    modalContent.append(pagamentoSection);

    // 6. Seção de Status de Pagamento
    var statusPagamentoSection = criarSecaoStatusPagamento(data);
    modalContent.append(statusPagamentoSection);

    // 7. Seção de Valores
    var valoresSection = criarSecaoValores(data);
    modalContent.append(valoresSection);

    // 8. Botões de Ação
    var actions = criarBotoesAcao(data);
    modalContent.append(actions);
    
    // Montar e exibir modal
    modal.append(modalContent);
    $('body').append(modal);
    
    // Animar abertura
    setTimeout(() => {
        modal.addClass('active');
        inicializarEnderecoAutomatico(data);
    }, 10);
}

// Funções auxiliares para criar cada seção
function criarSecaoCliente(data) {
    return `
        <div class="cliente-section">
            <h3>Cliente</h3>
            ${data.cliente ? `
                <div class="cliente-info">
                    <p><strong>Nome:</strong> ${data.cliente.nome_cliente}</p>
                    ${data.cliente.nome_empresa ? `<p class="empresa-tag">${data.cliente.nome_empresa}</p>` : ''}
                    <p><strong>Telefone:</strong> ${formatarTelefone(data.cliente.telefone_cliente)}</p>
                    <button class="btn-trocar-cliente">Trocar Cliente</button>
                </div>
            ` : `
                <div class="cliente-busca">
                    <input type="text" id="busca_cliente" placeholder="Buscar cliente por nome ou telefone...">
                    <div id="resultados_cliente"></div>
                    <button type="button" class="btn-novo-cliente">Cadastrar Novo Cliente</button>
                </div>
            `}
        </div>
    `;
}

function criarSecaoProdutos(data) {
    let html = '<div class="produtos-lista"><h3>Produtos</h3>';
    
    data.produtos.forEach((produto, index) => {
        html += `
            <div class="produto-item">
                <div class="produto-header">
                    <h4>${produto.nome_produto}</h4>
                    <button class="btn-remover-produto" onclick="removerProduto(${index})" title="Remover produto">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="preco">R$ ${Number(produto.preco_produto).toFixed(2)}</p>
        `;

        if (produto.subacompanhamentos && produto.subacompanhamentos.length > 0) {
            html += '<div class="subacompanhamentos">';
            produto.subacompanhamentos.forEach(sub => {
                html += `
                    <div class="sub-item">
                        <span>${sub.nome_subacomp}</span>
                        <span>R$ ${Number(sub.preco_subacomp).toFixed(2)}</span>
                    </div>
                `;
            });
            html += '</div>';
        }

        html += '</div>';
    });
    
    return html;
}

// [Continuar com as outras funções auxiliares...]

// Função para atualizar o carrinho após mudanças
function atualizarCarrinho(atualizarModal = false) {
    $.ajax({
        url: "../actions/carrinho/ver_carrinho.php",
        method: 'GET',
        dataType: 'json',
        success: function(carrinhoData) {
            if (carrinhoData) {
                // Verificar se as propriedades existem antes de usar
                const subtotal = parseFloat(carrinhoData.subtotal) || 0;
                const taxaEntrega = parseFloat(carrinhoData.taxa_entrega) || 0;
                const total = subtotal + taxaEntrega;

                // Atualizar valores
                $('.subtotal span:last-child').text(`R$ ${subtotal.toFixed(2)}`);
                $('.taxa-entrega span:last-child').text(`R$ ${taxaEntrega.toFixed(2)}`);
                $('.total p').text(`R$ ${total.toFixed(2)}`);
                
                if (atualizarModal) {
                    atualizarModalCarrinho(carrinhoData);
                }
            } else {
                console.error('Dados do carrinho inválidos');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao atualizar carrinho:', error);
            console.error('Resposta:', xhr.responseText);
        }
    });
}

// Adicione a função para remover produto
function removerProduto(index) {
    $.ajax({
        url: '../actions/carrinho/remover_produto.php',
        method: 'POST',
        data: { index: index },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Atualiza o modal do carrinho
                // $.ajax({
                //     url: "../actions/carrinho/ver_carrinho.php",
                //     method: 'GET',
                //     dataType: 'json',
                //     success: function(data) {
                //         // Remove o modal atual
                //         $('.modal-base').removeClass('active');
                //         setTimeout(() => {
                //             $('.modal-base').remove();
                //             // Mostra o novo modal atualizado
                //             mostrarModalCarrinho(data);
                //         }, 300);
                //     }
                // });
            } else {
                alert(response.message || 'Erro ao remover produto');
            }
        },
        error: function() {
            alert('Erro ao processar requisição');
        }
    });
}

// Exportar função para uso global
window.mostrarModalCarrinho = mostrarModalCarrinho;

// Função para atualizar os dados do pedido na sessão
function atualizarDadosPedido(dados) {
    console.log('Atualizando dados do pedido:', dados);
    
    $.ajax({
        url: '../actions/carrinho/carrinho.php',
        type: 'POST',
        data: dados,
        dataType: 'json',
        success: function(response) {
            console.log('Resposta do servidor:', response);
            if (response.status === 'success') {
                // Atualizar os valores nos inputs se necessário
                if (response.data) {
                    if (response.data.numero_pedido) {
                        $('#numero_pedido').val(response.data.numero_pedido);
                    }
                    if (response.data.hora_pedido) {
                        $('#hora_pedido').val(response.data.hora_pedido);
                    }
                    if (response.data.data_pedido) {
                        $('#data_pedido').val(response.data.data_pedido);
                    }
                }
            } else {
                console.error('Erro ao atualizar dados do pedido:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro na requisição:', error);
            console.error('Status:', status);
            console.error('Resposta:', xhr.responseText);
        }
    });
}

// Event listeners para os inputs
$(document).ready(function() {
    

    // Quando o número do pedido perder o foco
    $(document).on('blur', '#numero_pedido', function() {
        console.log('Número do pedido alterado:', $(this).val());
        atualizarDadosPedido({
            numero_pedido: $(this).val()
        });
    });

    // Quando a hora do pedido perder o foco
    $(document).on('blur', '#hora_pedido', function() {
        console.log('Hora do pedido alterada:', $(this).val());
        atualizarDadosPedido({
            hora_pedido: $(this).val()
        });
    });

    // Quando a data do pedido perder o foco
    $(document).on('blur', '#data_pedido', function() {
        console.log('Data do pedido alterada:', $(this).val());
        atualizarDadosPedido({
            data_pedido: $(this).val()
        });
    });

    // Adicionar evento de change também para garantir
    $(document).on('change', '#numero_pedido, #hora_pedido, #data_pedido', function() {
        console.log('Valor alterado em:', this.id, 'Novo valor:', $(this).val());
        let dados = {};
        dados[this.id] = $(this).val();
        atualizarDadosPedido(dados);
    });
});

// Função para atualizar o carrinho quando houver mudanças
function atualizarCarrinho() {
    // Guardar o status atual antes de atualizar
    const statusPagamentoAtual = $('.status-pagamento').hasClass('status-pago') ? 1 : 0;
    
    $.ajax({
        url: "../actions/carrinho/ver_carrinho.php",
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            const modalCarrinho = $('.modal-base.modal-carrinho');
            
            // Atualiza cliente
            if (data.cliente) {
                modalCarrinho.find('.cliente-section').html(`
                    <h3>Cliente</h3>
                    <div class="cliente-info">
                        <p><strong>Nome:</strong> ${data.cliente.nome_cliente}</p>
                        ${data.cliente.nome_empresa ? `<p class="empresa-tag">${data.cliente.nome_empresa}</p>` : ''}
                        <p><strong>Telefone:</strong> ${formatarTelefone(data.cliente.telefone_cliente)}</p>
                        <button class="btn-trocar-cliente">Trocar Cliente</button>
                    </div>
                `);
            } else {
                modalCarrinho.find('.cliente-section').html(`
                    <h3>Cliente</h3>
                    <div class="cliente-busca">
                        <input type="text" id="busca_cliente" placeholder="Buscar cliente por nome ou telefone...">
                        <div id="resultados_cliente"></div>
                        <button type="button" class="btn-novo-cliente">Cadastrar Novo Cliente</button>
                    </div>
                `);
            }

            // Atualiza informações do pedido
            modalCarrinho.find('#numero_pedido').val(data.numero_pedido || '');
            modalCarrinho.find('#data_pedido').val(data.data_pedido || '');
            modalCarrinho.find('#hora_pedido').val(data.hora_pedido || '');

            // Atualiza produtos
            let produtosHtml = '';
            if (data.produtos && data.produtos.length > 0) {
                data.produtos.forEach((produto, index) => {
                    produtosHtml += `
                        <div class="produto-item">
                            <div class="produto-header">
                                <h4>${produto.nome_produto}</h4>
                                <button class="btn-remover-produto" data-index="${index}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <p class="preco">R$ ${Number(produto.preco_produto).toFixed(2)}</p>
                            ${produto.subacompanhamentos.map(sub => `
                                <div class="sub-item">
                                    <span>${sub.nome_subacomp}</span>
                                    <span>R$ ${Number(sub.preco_subacomp).toFixed(2)}</span>
                                </div>
                            `).join('')}
                        </div>
                    `;
                });
            } else {
                produtosHtml = '<p class="carrinho-vazio">Carrinho vazio</p>';
            }
            modalCarrinho.find('.produtos-lista').html(produtosHtml);

            // Atualiza valores
            modalCarrinho.find('.subtotal span:last-child').text(`R$ ${Number(data.subtotal).toFixed(2)}`);
            modalCarrinho.find('.taxa-entrega span:last-child').text(`R$ ${Number(data.taxa_entrega).toFixed(2)}`);
            modalCarrinho.find('.total p').text(`R$ ${Number(data.total).toFixed(2)}`);

            // Atualiza forma de entrega
            if (data.retirada) {
                modalCarrinho.find('.btn-tipo-entrega[data-tipo="retirada"]').addClass('active');
                modalCarrinho.find('.btn-tipo-entrega[data-tipo="endereco"]').removeClass('active');
                modalCarrinho.find('#endereco-container').hide();
            } else {
                modalCarrinho.find('.btn-tipo-entrega[data-tipo="endereco"]').addClass('active');
                modalCarrinho.find('.btn-tipo-entrega[data-tipo="retirada"]').removeClass('active');
                modalCarrinho.find('#endereco-container').show();
            }

            // Restaurar o status do pagamento após atualizar
            if (statusPagamentoAtual === 1) {
                $('.status-pagamento')
                    .removeClass('status-pendente')
                    .addClass('status-pago')
                    .text('PAGO');
                $('.btn-toggle-status').text('Marcar como Pendente');
            } else {
                $('.status-pagamento')
                    .removeClass('status-pago')
                    .addClass('status-pendente')
                    .text('PENDENTE');
                $('.btn-toggle-status').text('Marcar como Pago');
            }
        }
    });
}

// Eventos para atualizar o carrinho
$(document).ready(function() {
    // Atualiza quando remove produto
    $(document).off('click', '.btn-remover-produto');

    // Evento para remover produto
    $(document).off('click.removerProduto', '.btn-remover-produto').on('click.removerProduto', '.btn-remover-produto', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $btn = $(this);
        const $produtoItem = $btn.closest('.produto-item');
        const index = $btn.data('index');
        
        console.log('Tentando remover produto:');
        console.log('- Botão:', $btn);
        console.log('- Item produto:', $produtoItem);
        console.log('- Index:', index);

        if (typeof index !== 'undefined' && index !== null) {
            $.ajax({
                url: "../actions/carrinho/carrinho.php",
                method: "POST",
                data: { 
                    action: 'remover',
                    index: index 
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta da remoção:', response);
                    
                    if (response && response.status === 'success') {
                        console.log('Produto removido com sucesso');
                        
                        // Buscar dados atualizados do carrinho
                        $.ajax({
                            url: "../actions/carrinho/ver_carrinho.php",
                            method: 'GET',
                            dataType: 'json',
                            success: function(carrinhoData) {
                                console.log('Dados atualizados do carrinho:', carrinhoData);
                                if (carrinhoData) {
                                    atualizarModalCarrinho(carrinhoData);
                                    atualizarCarrinho();
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Erro ao buscar dados atualizados do carrinho:', error);
                            }
                        });
                    } else {
                        console.error('Erro ao remover produto:', response);
                        alert(response.message || 'Erro ao remover produto do carrinho');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição de remoção:', error);
                    console.error('Status:', status);
                    console.error('Resposta:', xhr.responseText);
                    alert('Erro ao remover produto do carrinho');
                }
            });
        } else {
            console.error('Índice do produto não encontrado ou inválido');
        }
    });

    // Atualiza quando muda forma de entrega
    $('.btn-tipo-entrega').on('click', function() {
        const tipo = $(this).data('tipo');
        $.ajax({
            url: "../actions/carrinho/carrinho.php",
            method: "POST",
            data: { 
                retirada: tipo === 'retirada'
            },
            success: function(response) {
                if (response.status === 'success') {
                    buscarDadosCarrinho()
                        .then(function(carrinhoData) {
                            atualizarModalCarrinho(carrinhoData);
                        })
                        .catch(function(error) {
                            console.error('Erro ao atualizar carrinho:', error);
                        });
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao atualizar forma de entrega:', error);
            }
        });
    });

    // Busca inicial do carrinho
    $.ajax({
        url: "../actions/carrinho/ver_carrinho.php",
        method: 'GET',
        dataType: 'json',
        success: function(carrinhoData) {
            console.log('Dados do carrinho recebidos:', carrinhoData);
            atualizarModalCarrinho(carrinhoData);
            atualizarCarrinho();
        },
        error: function(xhr, status, error) {
            console.error("Erro ao buscar carrinho:", error);
            console.error("Status:", status);
            console.error("Resposta:", xhr.responseText);
        }
    });
});

// Função para atualizar o modal do carrinho
function atualizarModalCarrinho(data) {
    console.log('Dados recebidos em atualizarModalCarrinho:', data);

    if (!data) {
        console.error('Dados do carrinho não foram recebidos');
        return;
    }

    const modalCarrinho = $('.modal-base.modal-carrinho');
    
    // Atualização da seção de produtos
    const produtosSection = modalCarrinho.find('.produtos-lista');
    if (data.produtos && data.produtos.length > 0) {
        let produtosHtml = '<h3>Produtos</h3>';
        data.produtos.forEach((produto, index) => {
            produtosHtml += `
                <div class="produto-item">
                    <div class="produto-header">
                        <h4>${produto.nome_produto}</h4>
                        <button class="btn-remover-produto" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <p class="preco">R$ ${Number(produto.preco_produto).toFixed(2)}</p>
                    ${produto.subacompanhamentos && produto.subacompanhamentos.length > 0 ? `
                        <div class="subacompanhamentos">
                            ${produto.subacompanhamentos.map(sub => `
                                <div class="sub-item">
                                    <span>${sub.nome_subacomp}</span>
                                    <span>R$ ${Number(sub.preco_subacomp).toFixed(2)}</span>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        });
        produtosSection.html(produtosHtml);
    } else {
        produtosSection.html('<h3>Produtos</h3><p class="carrinho-vazio">Carrinho vazio</p>');
    }

    // Informações do Pedido (movido para cima)
    const pedidoSection = modalCarrinho.find('.pedido-section');
    if (pedidoSection.length) {
        pedidoSection.html(`
            <h3>Informações do Pedido</h3>
            <div class="pedido-info">
                <div class="pedido-input">
                    <strong>Número do Pedido</strong>
                    <input type="text" id="numero_pedido" value="${data.numero_pedido || ''}">
                </div>
                <div class="pedido-input">
                    <strong>Hora do Pedido</strong>
                    <input type="time" id="hora_pedido" value="${data.hora_pedido || ''}">
                </div>
                <div class="pedido-input">
                    <strong>Data do Pedido</strong>
                    <input type="date" id="data_pedido" value="${data.data_pedido || ''}">
                </div>
            </div>
        `);
    }

    // Seção do Cliente (agora vem depois)
    const clienteSection = modalCarrinho.find('.cliente-section');
    if (data.cliente) {
        clienteSection.html(`
            <h3>Cliente</h3>
            <div class="cliente-info">
                <p><strong>Nome:</strong> ${data.cliente.nome_cliente}</p>
                ${data.cliente.nome_empresa ? `<p class="empresa-tag">${data.cliente.nome_empresa}</p>` : ''}
                <p><strong>Telefone:</strong> ${formatarTelefone(data.cliente.telefone_cliente)}</p>
                <button class="btn-trocar-cliente">Trocar Cliente</button>
            </div>
        `);
    } else {
        clienteSection.html(`
            <h3>Cliente</h3>
            <div class="cliente-busca">
                <input type="text" id="busca_cliente" placeholder="Buscar cliente por nome ou telefone...">
                <div id="resultados_cliente"></div>
                <button type="button" class="btn-novo-cliente">Cadastrar Novo Cliente</button>
            </div>
        `);
    }

    // Atualiza informações do pedido com verificações
    try {
        if (data.numero_pedido !== undefined) {
            modalCarrinho.find('#numero_pedido').val(data.numero_pedido);
        }
        if (data.data_pedido !== undefined) {
            modalCarrinho.find('#data_pedido').val(data.data_pedido);
        }
        if (data.hora_pedido !== undefined) {
            modalCarrinho.find('#hora_pedido').val(data.hora_pedido);
        }
    } catch (error) {
        console.error('Erro ao atualizar informações do pedido:', error);
    }

    // Atualiza valores com verificações
    try {
        if (typeof data.subtotal !== 'undefined') {
            modalCarrinho.find('.subtotal span:last-child').text(`R$ ${Number(data.subtotal).toFixed(2)}`);
        }
        if (typeof data.taxa_entrega !== 'undefined') {
            modalCarrinho.find('.taxa-entrega span:last-child').text(`R$ ${Number(data.taxa_entrega).toFixed(2)}`);
        }
        if (typeof data.total !== 'undefined') {
            modalCarrinho.find('.total p').text(`R$ ${Number(data.total).toFixed(2)}`);
        }
    } catch (error) {
        console.error('Erro ao atualizar valores:', error);
    }

    // Atualiza forma de entrega
    try {
        if (typeof data.retirada !== 'undefined') {
            const tipoEntrega = data.retirada ? 'retirada' : 'endereco';
            modalCarrinho.find(`.btn-tipo-entrega[data-tipo="${tipoEntrega}"]`)
                .addClass('active')
                .siblings()
                .removeClass('active');
            
            modalCarrinho.find('#endereco-container').toggle(!data.retirada);
            modalCarrinho.find('.taxa-entrega').toggle(!data.retirada);
        }
    } catch (error) {
        console.error('Erro ao atualizar forma de entrega:', error);
    }

    // Seção de Entrega
    const entregaSection = modalCarrinho.find('.entrega-section');
    if (entregaSection.length) {
        let entregaHtml = `
            <h3>Forma de Entrega</h3>
            <div class="entrega-botoes">
                <button type="button" class="btn-tipo-entrega ${!data.retirada ? 'active' : ''}" data-tipo="endereco">ENTREGA</button>
                <button type="button" class="btn-tipo-entrega ${data.retirada ? 'active' : ''}" data-tipo="retirada">RETIRADA</button>
            </div>
            <div id="endereco-container" class="endereco-container" ${data.retirada ? 'style="display:none"' : ''}>
        `;

        if (data.enderecos && data.enderecos.length > 0) {
            entregaHtml += `
                <select id="endereco_entrega" class="endereco-select" ${data.retirada ? 'disabled' : ''}>
                    ${data.enderecos.map((e, index) => `
                        <option value="${e.id_entrega}" 
                                data-bairro="${e.id_bairro}"
                                data-taxa="${e.valor_taxa}"
                                ${(data.endereco_selecionado && data.endereco_selecionado.id_entrega == e.id_entrega) || (!data.endereco_selecionado && index === 0) ? 'selected' : ''}>
                            ${e.nome_entrega}, ${e.numero_entrega} - ${e.nome_bairro}
                        </option>
                    `).join('')}
                </select>
            `;
        }

        entregaHtml += `
                <button type="button" class="btn-novo-endereco">Cadastrar Novo Endereço</button>
            </div>
        `;

        entregaSection.html(entregaHtml);

        // Calcula a taxa inicial se houver cliente e endereço selecionado
        if (data.cliente && !data.retirada) {
            const selectedOption = $('#endereco_entrega option:selected');
            if (selectedOption.length) {
                console.log('Calculando taxa inicial para o endereço selecionado');
                $.ajax({
                    url: "../actions/carrinho/salva_endereco.php",
                    method: "POST",
                    dataType: 'json',
                    data: { 
                        id_entrega: selectedOption.val(),
                        id_bairro: selectedOption.data('bairro'),
                        retirada: 'false'
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            console.log('Taxa calculada com sucesso');
                            atualizarCarrinho();
                        }
                    }
                });
            }
        }

        // Reaplica os eventos da entrega
        $('.btn-tipo-entrega').off('click.tipoEntrega').on('click.tipoEntrega', function() {
            const tipo = $(this).data('tipo');
            $('.btn-tipo-entrega').removeClass('active');
            $(this).addClass('active');
            
            if (tipo === 'endereco') {
                $('#endereco-container').show();
                $('#endereco_entrega').prop('disabled', false);
                $('.taxa-entrega').removeClass('hidden');
                
                // Se tem endereço selecionado, usa ele, senão usa o primeiro
                const enderecoSelect = $('#endereco_entrega');
                if (enderecoSelect.length > 0) {
                    const selectedOption = enderecoSelect.find('option:selected');
                    $.ajax({
                        url: "../actions/carrinho/salva_endereco.php",
                        method: "POST",
                        dataType: 'json',
                        data: { 
                            id_entrega: selectedOption.val(),
                            id_bairro: selectedOption.data('bairro'),
                            retirada: 'false'
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                atualizarCarrinho();
                            }
                        }
                    });
                }
            } else {
                $('#endereco-container').hide();
                $('#endereco_entrega').prop('disabled', true);
                $('.taxa-entrega').addClass('hidden');
                
                $.ajax({
                    url: "../actions/carrinho/salva_endereco.php",
                    method: "POST",
                    dataType: 'json',
                    data: { retirada: 'true' },
                    success: function(response) {
                        if (response.status === 'success') {
                            atualizarCarrinho();
                        }
                    }
                });
            }
        });

        // Evento para mudança de endereço
        $('#endereco_entrega').off('change.endereco').on('change.endereco', function() {
            const selectedOption = $(this).find('option:selected');
            $.ajax({
                url: "../actions/carrinho/salva_endereco.php",
                method: "POST",
                dataType: 'json',
                data: { 
                    id_entrega: selectedOption.val(),
                    id_bairro: selectedOption.data('bairro'),
                    retirada: 'false'
                },
                success: function(response) {
                    if (response.status === 'success') {
                        atualizarCarrinho();
                    }
                }
            });
        });

        // Evento para novo endereço
        $('.btn-novo-endereco').off('click.novoEndereco').on('click.novoEndereco', function() {
            mostrarFormularioEndereco();
        });
    }

    // Seção de Forma de Pagamento
    const pagamentoSection = modalCarrinho.find('.pagamento-section');
    if (pagamentoSection.length) {
        pagamentoSection.html(`
            <h3>Forma de Pagamento</h3>
            <select id="forma_pagamento" class="pagamento-select" required>
                <option value="">Selecione a forma de pagamento</option>
            </select>
        `);

        // Carregar formas de pagamento do banco
        $.ajax({
            url: "../actions/carrinho/busca_pagamentos.php",
            method: "GET",
            dataType: 'json',
            success: function(pagamentos) {
                var pagamentoSelect = $('#forma_pagamento');
                pagamentos.forEach(function(p) {
                    pagamentoSelect.append(`
                        <option value="${p.id_pagamento}" ${data.pagamento && data.pagamento.id_pagamento == p.id_pagamento ? 'selected' : ''}>
                            ${p.metodo_pagamento}
                        </option>
                    `);
                });
            },
            error: function(xhr, status, error) {
                console.error('Erro ao carregar formas de pagamento:', error);
            }
        });

        // Evento para salvar forma de pagamento (mantendo a lógica existente)
        $('#forma_pagamento').off('change').on('change', function() {
            var idPagamento = $(this).val();
            if (idPagamento) {
                $.ajax({
                    url: "../actions/carrinho/salva_pagamento.php",
                    method: "POST",
                    dataType: 'json',
                    data: { id_pagamento: idPagamento },
                    success: function(response) {
                        console.log("Forma de pagamento salva com sucesso");
                        atualizarCarrinho(true);
                    }
                });
            }
        });
    }

    // Atualizar status do pagamento baseado no retorno do servidor
    if (data.status_pagamento === 1) {
        $('.status-pagamento')
            .removeClass('status-pendente')
            .addClass('status-pago')
            .text('PAGO');
        $('.btn-toggle-status').text('Marcar como Pendente');
    } else {
        $('.status-pagamento')
            .removeClass('status-pago')
            .addClass('status-pendente')
            .text('PENDENTE');
        $('.btn-toggle-status').text('Marcar como Pago');
    }
}

// Eventos do carrinho
$(document).ready(function() {

    // Evento para limpar o carrinho
    $(document).on('click', '.btn-limpar', function() {
        if (confirm('Tem certeza que deseja limpar o carrinho?')) {
            $.ajax({
                url: "../actions/carrinho/limpa_carrinho.php",
                method: 'POST',
                dataType: 'json',
                data: { action: 'limpar' },
                success: function() {
                    // Depois busca os dados atualizados e atualiza o carrinho
                    buscarDadosCarrinho().then(function(carrinhoData) {
                        // Atualiza o carrinho aqui
                        atualizarModalCarrinho(carrinhoData);
                        atualizarCarrinho();
                    }).catch(function(error) {
                        console.error('Erro ao atualizar carrinho:', error);
                    });
                }
            });
        }
    });

    // Evento para trocar cliente
    $(document).on('click', '.btn-trocar-cliente', function() {
        $('.cliente-section').html(`
            <h3>Cliente</h3>
            <div class="cliente-busca">
                <input type="text" id="busca_cliente" placeholder="Buscar cliente por nome ou telefone...">
                <div id="resultados_cliente"></div>
                <button type="button" class="btn-novo-cliente">Cadastrar Novo Cliente</button>
            </div>
        `);
    });

    // Evento para o botão de trocar cliente
    $(document).on('click', '.btn-trocar-cliente', function() {
        console.log('Botão trocar cliente clicado');
        
        $.ajax({
            url: "../actions/carrinho/remove_cliente_carrinho.php",
            method: "POST", 
            dataType: 'json',
            success: function(response) {
                console.log('Resposta do remove_cliente_carrinho:', response);
                
                if (response.status === 'success') {
                    console.log('Cliente removido com sucesso');
                    
                    // Buscar dados atualizados do carrinho
                    $.ajax({
                        url: "../actions/carrinho/ver_carrinho.php",
                        method: 'GET',
                        dataType: 'json',
                        success: function(novosDados) {
                            console.log('Novos dados do carrinho:', novosDados);
                      
                            
                            setTimeout(() => {
                                console.log('Timeout executado');
                                // $('.modal-base').remove();
                                
                                // Mostra o novo modal atualizado
                                console.log('Atualizando modal com novos dados');
                                atualizarModalCarrinho(novosDados);
                            }, 300);
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro ao atualizar carrinho:');
                            console.error('Status:', status);
                            console.error('Erro:', error);
                            console.error('Resposta:', xhr.responseText);
                        }
                    });
                } else {
                    console.error('Erro ao remover cliente:');
                    console.error('Status:', response.status);
                    console.error('Mensagem:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição de remover cliente:');
                console.error('Status:', status);
                console.error('Erro:', error);
                console.error('Resposta:', xhr.responseText);
            }
        });
    });

    // Eventos de forma de entrega
    $(document).on('click', '.btn-tipo-entrega', function() {
        const tipo = $(this).data('tipo');
        $('.btn-tipo-entrega').removeClass('active');
        $(this).addClass('active');
        
        if (tipo === 'retirada') {
            $('#endereco-container').hide();
            $('.taxa-entrega').hide();
        } else {
            $('#endereco-container').show();
            $('.taxa-entrega').show();
        }

        $.ajax({
            url: "../actions/carrinho/carrinho.php",
            method: "POST",
            data: { retirada: tipo === 'retirada' },
            success: function(response) {
                if (response.status === 'success') {
                    buscarDadosCarrinho()
                        .then(function(carrinhoData) {
                            atualizarModalCarrinho(carrinhoData);
                        })
                        .catch(function(error) {
                            console.error('Erro ao atualizar carrinho:', error);
                        });
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao atualizar forma de entrega:', error);
            }
        });
    });

    // Evento para atualizar dados do pedido
    $(document).on('change', '#numero_pedido, #data_pedido, #hora_pedido', function() {
        let dados = {};
        dados[this.id] = $(this).val();
        $.ajax({
            url: "../actions/carrinho/carrinho.php",
            method: "POST",
            data: dados,
            success: function() {
                atualizarModalCarrinho();
            }
        });
    });

    // Evento para finalizar pedido
    $(document).on('click', '.btn-finalizar', function() {
        if (!$('#forma_pagamento').val()) {
            alert('Selecione uma forma de pagamento');
            return;
        }

        $.ajax({
            url: "../actions/carrinho/salvar_pedido.php",
            method: "POST",
            data: {
                forma_pagamento: $('#forma_pagamento').val()
            },
            success: function(response) {
                if (response.status === 'success') {
                    // alert('Pedido finalizado com sucesso!');
                    buscarDadosCarrinho().then(function(carrinhoData) {
                        // Atualiza o carrinho aqui
                        atualizarModalCarrinho(carrinhoData);
                        atualizarCarrinho();
                    }).catch(function(error) {
                        console.error('Erro ao atualizar carrinho:', error);
                    });
                    // Atualiza a página ou faz outras ações necessárias
                } else {
                    alert(response.message || 'Erro ao finalizar pedido');
                }
            },
            error: function() {
                alert('Erro ao processar pedido');
            }
        });
    });

    // Evento para busca de cliente
    $(document).on('input', '#busca_cliente', function() {
        const termo = $(this).val();
        if (termo.length >= 3) {
            $.ajax({
                url: "../actions/carrinho/buscar_cliente.php",
                method: "GET",
                data: { term: termo },
                dataType: 'json',
                success: function(response) {
                    let html = '';
                    console.log('Resposta da busca:', response);
                    
                    if (response && response.length > 0) {
                        response.forEach(cliente => {
                            html += `
                                <div class="cliente-resultado" data-id="${cliente.id_cliente}">
                                    <p><strong>${cliente.nome_cliente}</strong></p>
                                    ${cliente.nome_empresa ? `<p class="empresa-tag">${cliente.nome_empresa}</p>` : ''}
                                    ${cliente.telefone_cliente ? `<p>${cliente.telefone_cliente}</p>` : ''}
                                    ${cliente.nome_entrega ? `
                                        <p><small>${cliente.nome_entrega}, ${cliente.numero_entrega}</small></p>
                                        <p><small>${cliente.nome_bairro}</small></p>
                                    ` : ''}
                                </div>
                            `;
                        });
                    } else {
                        html = '<p>Nenhum cliente encontrado</p>';
                    }
                    $('#resultados_cliente').html(html);
                },
                error: function(xhr, status, error) {
                    console.error('Erro na busca:', error);
                    $('#resultados_cliente').html('<p>Erro ao buscar clientes</p>');
                }
            });
        } else {
            $('#resultados_cliente').empty();
        }
    });

    // Evento para selecionar cliente da busca
    $(document).on('click', '.cliente-resultado', function() {
        const clienteId = $(this).data('id');
        $.ajax({
            url: "../actions/carrinho/carrinho.php",
            method: "POST",
            data: { id_cliente: clienteId },
            dataType: 'json',
            success: function(response) {  // Alterado de data para response
                if (response && response.status === 'success') {
                    // Buscar dados atualizados do carrinho
                    $.ajax({
                        url: "../actions/carrinho/ver_carrinho.php",
                        method: 'GET',
                        dataType: 'json',
                        success: function(carrinhoData) {
                            console.log('Dados do carrinho atualizados:', carrinhoData);
                            atualizarModalCarrinho(carrinhoData);
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro ao atualizar carrinho:', error);
                            console.error('Status:', status);
                            console.error('Resposta:', xhr.responseText);
                        }
                    });
                } else {
                    console.error('Erro ao selecionar cliente:', response);
                    alert('Erro ao selecionar cliente. Por favor, tente novamente.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao selecionar cliente:', error);
                console.error('Status:', status);
                console.error('Resposta:', xhr.responseText);
                alert('Erro ao selecionar cliente. Por favor, tente novamente.');
            }
        });
    });

    // Adicionar este evento para o botão de novo cliente
    $(document).on('click', '.btn-novo-cliente', function() {
        console.log('Botão novo cliente clicado');
        try {
            console.log('Tentando mostrar modal de cadastro');
            mostrarModalCadastroCliente();
            console.log('Modal de cadastro mostrado com sucesso');
        } catch (erro) {
            console.error('Erro ao mostrar modal de cadastro:', erro);
            console.error('Stack trace:', erro.stack);
            alert('Erro ao abrir formulário de cadastro. Por favor, tente novamente.');
        }
    });

    // Evento para adicionar produto ao carrinho
    $(document).off('click.adicionarProduto', '.btn-adicionar-carrinho').on('click.adicionarProduto', '.btn-adicionar-carrinho', function(e) {
        e.preventDefault();
        const produtoId = $(this).data('produto');
        const subacompanhamentos = [];
        
        // Coleta os subacompanhamentos selecionados
        $(this).closest('.modal-produto').find('.subacompanhamento-checkbox:checked').each(function() {
            subacompanhamentos.push({
                id: $(this).val(),
                nome: $(this).data('nome'),
                preco: $(this).data('preco')
            });
        });

        console.log('Adicionando produto:', produtoId);
        console.log('Subacompanhamentos:', subacompanhamentos);

        $.ajax({
            url: "../actions/carrinho/carrinho.php",
            method: "POST",
            data: { 
                action: 'adicionar',
                produto_id: produtoId,
                subacompanhamentos: subacompanhamentos 
            },
            dataType: 'json',
            success: function(response) {
                console.log('Resposta da adição:', response);
                
                if (response && response.status === 'success') {
                    console.log('Produto adicionado com sucesso');
                    
                    // Fecha o modal do produto
                    $('.modal-produto').removeClass('active');
                    
                    // Busca dados atualizados do carrinho
                    buscarDadosCarrinho()
                        .then(function(carrinhoData) {
                            console.log('Dados atualizados do carrinho:', carrinhoData);
                            atualizarModalCarrinho(carrinhoData);
                            atualizarCarrinho();
                        })
                        .catch(function(error) {
                            console.error('Erro ao atualizar carrinho:', error);
                        });
                } else {
                    console.error('Erro ao adicionar produto:', response);
                    alert(response.message || 'Erro ao adicionar produto ao carrinho');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição de adição:', error);
                alert('Erro ao adicionar produto ao carrinho');
            }
        });
    });
});

// Quando um cliente é selecionado
function selecionarCliente(cliente) {
    console.log('Selecionando cliente:', cliente);
    $.ajax({
        url: "../actions/carrinho/carrinho.php",
        method: "POST",
        data: { 
            id_cliente: cliente.id_cliente,
            calcular_taxa: true // Adiciona flag para calcular taxa
        },
        success: function(response) {
            console.log('Resposta da seleção do cliente:', response);
            if (response.status === 'success') {
                // Força o cálculo da taxa imediatamente
                const enderecoSelecionado = response.data.endereco;
                if (enderecoSelecionado && !response.data.retirada) {
                    console.log('Calculando taxa para o endereço:', enderecoSelecionado);
                    $.ajax({
                        url: "../actions/carrinho/salva_endereco.php",
                        method: "POST",
                        dataType: 'json',
                        data: { 
                            id_entrega: enderecoSelecionado.id_entrega,
                            id_bairro: enderecoSelecionado.id_bairro,
                            retirada: 'false',
                            calcular_taxa: true
                        },
                        success: function(enderecoResponse) {
                            console.log('Resposta do cálculo da taxa:', enderecoResponse);
                            if (enderecoResponse.status === 'success') {
                                atualizarCarrinho();
                            }
                        }
                    });
                }
            }
        }
    });
}

function adicionarProdutoAoCarrinho(produto) {
    $.ajax({
        url: '../actions/carrinho/adiciona_produto_carrinho.php',
        method: 'POST',
        data: produto,
        success: function(response) {
            if (response.status === 'success') {
                // Fecha o modal de acompanhamentos
                $('.modal-acompanhamentos').removeClass('active');
                
                // Atualiza o carrinho
                if (typeof window.atualizarCarrinho === 'function') {
                    window.atualizarCarrinho();
                }
                
                // Chama o callback se existir
                if (typeof window.onProdutoAdicionado === 'function') {
                    window.onProdutoAdicionado();
                }
            } else {
                console.error('Erro ao adicionar produto:', response.message);
                alert('Erro ao adicionar produto ao carrinho');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro na requisição:', error);
            alert('Erro ao adicionar produto ao carrinho');
        }
    });
}

// Adicione esta função
function atualizarStatusPagamento(novoStatus) {
    console.log('Atualizando status para:', novoStatus); // Debug

    $.ajax({
        url: '../actions/carrinho/atualizar_status_pagamento.php',
        method: 'POST',
        data: { status: novoStatus },
        dataType: 'json',
        success: function(response) {
            console.log('Resposta:', response); // Debug
            
            if (response.status === 'success') {
                const statusElement = $('.status-pagamento');
                const btnToggle = $('.btn-toggle-status');
                
                // Atualiza a interface com o novo status
                if (novoStatus === 1) {
                    statusElement
                        .removeClass('status-pendente')
                        .addClass('status-pago')
                        .text('PAGO');
                    btnToggle.text('Marcar como Pendente');
                } else {
                    statusElement
                        .removeClass('status-pago')
                        .addClass('status-pendente')
                        .text('PENDENTE');
                    btnToggle.text('Marcar como Pago');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro:', error);
            alert('Erro ao atualizar status de pagamento');
        }
    });
}

$(document).ready(function() {
    // Delegação de evento para o botão de toggle
    $(document).on('click', '.btn-toggle-status', function(e) {
        e.preventDefault();
        // Verifica o status atual pelo texto do span
        const isPendente = $('.status-pagamento').hasClass('status-pendente');
        // Se está pendente, muda para pago (1), se está pago, muda para pendente (0)
        const novoStatus = isPendente ? 1 : 0;
        atualizarStatusPagamento(novoStatus);
    });
});

// Na função mostrarModalCarrinho, atualize a seção de status
function mostrarModalCarrinho(data) {
    // ... código existente ...
    
    const statusPagamentoHtml = `
        <div class="status-pagamento-section">
            <h3>Status do Pagamento</h3>
            <div class="status-toggle">
                <span class="status-pagamento ${data.status_pagamento === 1 ? 'status-pago' : 'status-pendente'}">
                    ${data.status_pagamento === 1 ? 'PAGO' : 'PENDENTE'}
                </span>
                <button type="button" class="btn-toggle-status">
                    ${data.status_pagamento === 1 ? 'Marcar como Pendente' : 'Marcar como Pago'}
                </button>
            </div>
        </div>
    `;
    
    // Remove a seção antiga se existir
    $('.status-pagamento-section').remove();
    
    // Adiciona a nova seção após a seção de pagamento
    $('.pagamento-section').after(statusPagamentoHtml);
}

function limparCarrinho() {
    console.log('Iniciando limpeza do carrinho...');
    
    $.ajax({
        url: '../actions/carrinho/limpar_carrinho.php',
        method: 'POST',
        success: function(response) {
            console.log('Resposta do servidor:', response);
            
            if (response.status === 'success') {
                atualizarCarrinho();
                
                // Definir status como PAGO por padrão em novo pedido
                $('.status-pagamento')
                    .removeClass('status-pendente')
                    .addClass('status-pago')
                    .text('PAGO');
                $('.btn-toggle-status').text('Marcar como Pendente');
                
                console.log('Carrinho limpo com sucesso');
            } else {
                console.error('Erro ao limpar carrinho:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro na requisição:', error);
            console.error('Status:', status);
            console.error('Detalhes:', xhr.responseText);
        }
    });
}

function atualizarCarrinho() {
    $.ajax({
        url: '../actions/carrinho/buscar_carrinho.php',
        method: 'GET',
        success: function(response) {
            if (response.status === 'success') {
                // Se não houver cliente selecionado, significa que é um novo pedido
                if (!response.data.cliente) {
                    $('.status-pagamento')
                        .removeClass('status-pendente')
                        .addClass('status-pago')
                        .text('PAGO');
                    $('.btn-toggle-status').text('Marcar como Pendente');
                }
                // Se houver cliente, mantém o status atual
            }
        }
    });
}
