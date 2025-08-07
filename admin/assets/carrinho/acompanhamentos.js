function debugEscolhas(escolhas) {
    console.log('\n=== DEBUG ESCOLHAS SELECIONADAS ===');
    console.log('Todas as escolhas:', escolhas);
    escolhas.forEach((escolha, index) => {
        console.log(`Escolha ${index + 1}:`, {
            acomp_id: escolha.acomp_id,
            subacomp_id: escolha.subacomp_id,
            quantidade: escolha.quantidade
        });
    });
}

function mostrarModalAcompanhamentos(produtoId, produtoNome, produtoPreco, acompanhamentos) {
    // Se já existe um modal, não criar outro
    if ($('.modal-acompanhamentos').length > 0) {
        console.log('Modal já existe, ignorando segunda chamada');
        return;
    }
    
    console.log('=== DEBUG MODAL ACOMPANHAMENTOS ===');
    console.log('Produto ID:', produtoId);
    console.log('Produto Nome:', produtoNome);
    console.log('Produto Preço:', produtoPreco);
    console.log('Acompanhamentos:', acompanhamentos);
    
    // Debug de cada acompanhamento
    acompanhamentos.forEach((acomp, index) => {
        console.log(`\nAcompanhamento ${index + 1}:`, {
            id: acomp.id_acomp,
            nome: acomp.nome_acomp,
            regras: acomp.regras,
            subacompanhamentos: acomp.subacompanhamentos
        });
    });

    // Criar estrutura do modal
    const modal = $('<div>').addClass('modal-base modal-acompanhamentos');
    const modalContent = $('<div>').addClass('modal-content');
    
    modalContent.append(`
        <div class="modal-header">
            <h3>${produtoNome}</h3>
            <button class="btn-fechar"><i class="fas fa-times"></i></button>
        </div>
    `);

    // Agrupar acompanhamentos por obrigatoriedade
    const obrigatorios = acompanhamentos.filter(a => a.regras.obrigatorio);
    const opcionais = acompanhamentos.filter(a => !a.regras.obrigatorio);

    // Renderizar acompanhamentos obrigatórios primeiro
    if (obrigatorios.length > 0) {
        modalContent.append('<div class="acomp-section"><h4>Escolhas Obrigatórias</h4></div>');
        obrigatorios.forEach(acomp => {
            modalContent.append(renderizarGrupoAcompanhamento(acomp));
        });
    }

    // Depois renderizar os opcionais
    if (opcionais.length > 0) {
        modalContent.append('<div class="acomp-section"><h4>Escolhas Opcionais</h4></div>');
        opcionais.forEach(acomp => {
            modalContent.append(renderizarGrupoAcompanhamento(acomp));
        });
    }

    // Adicionar botão de confirmar
    modalContent.append(`
        <div class="modal-footer">
            <button class="btn-confirmar" disabled>
                Adicionar ao Carrinho
            </button>
        </div>
    `);

    modal.append(modalContent);
    $('body').append(modal);
    
    // Ativar modal
    setTimeout(() => modal.addClass('active'), 10);

    // Eventos
    setupEventos(modal, produtoId, produtoNome, produtoPreco, acompanhamentos);
}

function renderizarGrupoAcompanhamento(acomp) {
    const container = $('<div>').addClass('acomp-grupo');
    
    // Cabeçalho com nome e regras
    container.append(`
        <div class="acomp-header">
            <h4>${acomp.nome_acomp}</h4>
            <div class="acomp-regras">
                ${acomp.regras.obrigatorio ? '<span class="badge-obrigatorio">Obrigatório</span>' : ''}
                <span class="badge-info">
                    ${acomp.regras.min_escolhas} - ${acomp.regras.max_escolhas} itens
                </span>
            </div>
        </div>
        <div class="contador-escolhas" data-acomp-id="${acomp.id_acomp}">
            Selecionados: <span>0</span>/${acomp.regras.max_escolhas}
        </div>
    `);

    // Lista de subacompanhamentos
    const lista = $('<div>').addClass('sub-acomp-lista');
    acomp.subacompanhamentos.forEach(sub => {
        lista.append(renderizarSubacompanhamento(sub, acomp.regras));
    });
    
    container.append(lista);
    return container;
}

function renderizarSubacompanhamento(sub, regras) {
    const preco = parseFloat(sub.preco_subacomp) || 0;
    
    return `
        <div class="sub-acomp-item" data-id="${sub.id_subacomp}">
            <label>
                <input type="${regras.permite_repetir ? 'checkbox' : 'checkbox'}" 
                       min="0" 
                       max="${regras.max_escolhas}"
                       ${regras.permite_repetir ? 'value="0"' : ''}>
                <span>${sub.nome_subacomp}</span>
                ${preco > 0 ? `<span class="preco">+R$ ${preco.toFixed(2)}</span>` : ''}
            </label>
        </div>
    `;
}
function setupEventos(modal, produtoId, produtoNome, produtoPreco, acompanhamentos) {
    // Eventos de input para validação
    modal.find('input').on('change input', function() {
        validarSelecoes(modal, acompanhamentos);
    });

    modal.find('.btn-fechar').click(() => {
        modal.removeClass('active');
        setTimeout(() => modal.remove(), 300);
    });

    modal.find('.btn-confirmar').click(() => {
        const escolhas = coletarEscolhas(modal);
        debugEscolhas(escolhas);
        const data = {
            produto_id: produtoId,
            nome: produtoNome,
            preco: produtoPreco,
            quantidade: 1,
            escolhas: escolhas
        };
        
        $.ajax({
            url: '../actions/carrinho/adiciona_produto_carrinho.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                if (response.status === 'success') {
                    modal.removeClass('active');
                    setTimeout(() => {
                        modal.remove();
                        $.ajax({
                            url: "../actions/carrinho/ver_carrinho.php",
                            method: 'GET',
                            dataType: 'json',
                            success: function(carrinhoData) {
                                
                                if (carrinhoData) {
                                    buscarDadosCarrinho()
                                        .then(function(carrinhoData) {
                                            atualizarModalCarrinho(carrinhoData);
                                            atualizarCarrinho();
                                        })
                                }
                            }
                        });
                    }, 300);
                } else {
                    alert(response.message || 'Erro ao adicionar ao carrinho');
                }
            }
        });
    });

    // Validação inicial
    validarSelecoes(modal, acompanhamentos);
}

function validarSelecoes(modal, acompanhamentos) {
    let todosObrigatoriosCompletos = true;
    
    modal.find('.acomp-grupo').each(function() {
        const grupo = $(this);
        const acompId = grupo.find('.contador-escolhas').data('acomp-id');
        const acomp = acompanhamentos.find(a => a.id_acomp === acompId);
        const regras = acomp.regras;
        
        const qtdSelecionada = contarSelecoes(grupo);
        
        // Atualizar contador
        grupo.find('.contador-escolhas span').text(qtdSelecionada);
        
        // Verificar se grupo obrigatório está completo
        if (regras.obrigatorio) {
            if (qtdSelecionada < regras.min_escolhas || qtdSelecionada > regras.max_escolhas) {
                todosObrigatoriosCompletos = false;
            }
        }
        
        // Gerenciar estado dos inputs
        grupo.find('.sub-acomp-item').each(function() {
            const item = $(this);
            const input = item.find('input');
            
            if (input.attr('type') === 'checkbox') {
                const isChecked = input.prop('checked');
                item.toggleClass('disabled', !isChecked && qtdSelecionada >= regras.max_escolhas);
                input.prop('disabled', !isChecked && qtdSelecionada >= regras.max_escolhas);
            } else {
                const valorAtual = parseInt(input.val()) || 0;
                const maxPermitido = regras.max_escolhas - qtdSelecionada + valorAtual;
                input.prop('max', maxPermitido);
                
                item.toggleClass('disabled', valorAtual === 0 && qtdSelecionada >= regras.max_escolhas);
                input.prop('disabled', valorAtual === 0 && qtdSelecionada >= regras.max_escolhas);
            }
        });
    });

    // Habilitar botão apenas se todos os grupos obrigatórios estiverem completos
    modal.find('.btn-confirmar').prop('disabled', !todosObrigatoriosCompletos);
}

function contarSelecoes(grupo) {
    let total = 0;
    grupo.find('.sub-acomp-item input').each(function() {
        if (this.type === 'checkbox') {
            total += this.checked ? 1 : 0;
        } else {
            total += parseInt(this.value) || 0;
        }
    });
    return total;
}

function coletarEscolhas(modal) {
    const escolhas = [];
    modal.find('.acomp-grupo').each(function() {
        const grupo = $(this);
        const acompId = grupo.find('.contador-escolhas').data('acomp-id');
        
        grupo.find('.sub-acomp-item input').each(function() {
            const quantidade = this.type === 'checkbox' ? 
                (this.checked ? 1 : 0) : 
                (parseInt(this.value) || 0);
                
            if (quantidade > 0) {
                escolhas.push({
                    acomp_id: acompId,
                    subacomp_id: $(this).closest('.sub-acomp-item').data('id'),
                    quantidade: quantidade
                });
            }
        });
    });
    
    return escolhas;
}

// Função unificada para atualizar o carrinho
function atualizarCarrinhoAposAdicao(modal = null) {
    // Se houver um modal, fecha ele primeiro
    if (modal) {
        modal.removeClass('active');
        setTimeout(() => modal.remove(), 300);
    }

    // Busca dados atualizados do carrinho
    $.ajax({
        url: "../actions/carrinho/ver_carrinho.php",
        method: 'GET',
        dataType: 'json',
        success: function(carrinhoData) {
            console.log('Dados atualizados do carrinho:', carrinhoData);
            if (carrinhoData) {
                // Força uma nova renderização do modal do carrinho
                $('.modal-carrinho').remove();
                mostrarModalCarrinho(carrinhoData);
                
                // Garante que o modal do carrinho esteja visível
                setTimeout(() => {
                    $('.modal-carrinho').addClass('active');
                }, 100);
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao atualizar carrinho:', error);
        }
    });
}

// Remover todos os eventos de clique anteriores
$('.menu-item').off('click');

// Adicionar novo evento de clique
$('.menu-item').on('click', function(e) {
    e.preventDefault();
    e.stopPropagation(); // Impede propagação do evento
    
    // Se já existe um modal aberto, não faz nada
    if ($('.modal-acompanhamentos').length > 0) {
        return;
    }

    const $this = $(this);
    const produtoId = $this.data('id');
    const produtoNome = $this.find('.menu-item-title').text();
    const produtoPreco = parseFloat($this.find('.preco').text().replace('R$ ', '').replace(',', '.'));

    // Verificar acompanhamentos
    $.ajax({
        url: '../actions/carrinho/verificar_acompanhamentos.php',
        method: 'GET',
        data: { produto_id: produtoId },
        success: function(response) {
            if (response.status === 'success') {
                // Sempre mostra o modal, independente de ter ou não acompanhamentos
                mostrarModalAcompanhamentos(
                    produtoId, 
                    produtoNome, 
                    produtoPreco, 
                    response.tem_acompanhamentos ? response.acompanhamentos : []
                );
            } else {
                alert('Erro ao verificar acompanhamentos do produto');
            }
        }
    });
});

