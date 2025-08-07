// Adicionar no início do arquivo, antes das outras funções
function mostrarLogs(...args) {
    console.log(...args);
}

// Flag para controlar se já extraiu os dados do pedido atual
let pedidoProcessado = false;

// Adicionar após a declaração das outras variáveis globais
const PEDIDOS_EXTRAIDOS_KEY = 'pedidosExtraidosIds';

// Adicionar variável global para controlar a automação
let automationRunning = false;

// Array para armazenar pedidos já processados
let pedidosProcessados = new Set();

// Função para verificar se um pedido já foi processado
async function verificarPedidoProcessado(numeroPedido) {
    return pedidosProcessados.has(numeroPedido);
}

// Função para marcar um pedido como processado
function marcarPedidoProcessado(numeroPedido) {
    pedidosProcessados.add(numeroPedido);
}

// Função para marcar pedido como extraído
function marcarPedidoExtraido(numeroPedido) {
    chrome.storage.local.get([PEDIDOS_EXTRAIDOS_KEY], function(result) {
        const pedidosExtraidos = result[PEDIDOS_EXTRAIDOS_KEY] || [];
        if (!pedidosExtraidos.includes(numeroPedido)) {
            pedidosExtraidos.push(numeroPedido);
            chrome.storage.local.set({ [PEDIDOS_EXTRAIDOS_KEY]: pedidosExtraidos });
        }
        destacarPedidosExtraidos();
    });
}

// Função para destacar pedidos extraídos
function destacarPedidosExtraidos() {
    chrome.storage.local.get([PEDIDOS_EXTRAIDOS_KEY], function(result) {
        const pedidosExtraidos = result[PEDIDOS_EXTRAIDOS_KEY] || [];
        
        // Remove destaque anterior
        document.querySelectorAll('.pedido-extraido').forEach(el => {
            el.classList.remove('pedido-extraido');
        });
        
        // Adiciona destaque aos pedidos extraídos
        document.querySelectorAll('.table-row').forEach(row => {
            const numeroCelula = row.querySelector('.table-column__start .default');
            if (numeroCelula) {
                const numeroPedido = numeroCelula.textContent.replace('#', '');
                if (pedidosExtraidos.includes(numeroPedido)) {
                    row.classList.add('pedido-extraido');
                }
            }
        });
    });
}

// Adicionar ao style existente
const extraStyle = document.createElement('style');
extraStyle.innerHTML = `
    .pedido-extraido {
        background-color: rgba(0, 200, 83, 0.1) !important;
        border: 1px solid #00c853 !important;
        position: relative;
    }
    .pedido-extraido::after {
        content: '✓';
        position: absolute;
        right: 10px;
        color: #00c853;
        font-weight: bold;
    }
`;
document.head.appendChild(extraStyle);

// Função para mostrar dados extraídos e logs
function mostrarDadosExtraidos(pedido) {
    const displayElement = document.createElement('div');
    displayElement.style.cssText = `
        position: fixed;
        bottom: 30px;
        left: 30px;
        background: linear-gradient(to bottom, #ffffff, #f8f9fa);
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        z-index: 9999;
        max-width: 450px;
        max-height: 85vh;
        overflow-y: auto;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 14px;
        line-height: 1.6;
        border: 1px solid rgba(0,0,0,0.1);
        backdrop-filter: blur(10px);
        opacity: 0;
        transform: scale(0.95);
        transition: all 0.2s ease-out;
    `;

    // Adiciona seção de logs
    const logs = `
        <div style="margin-bottom: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px;">
            <strong>Logs:</strong><br>
            <div style="color: #666; font-family: monospace; font-size: 12px;">

            </div>
        </div>
    `;

    const html = `
        ${logs}
        <div style="margin-bottom: 10px;">
            <strong>Pedido #${pedido.numero}</strong> - ${pedido.status}
            <div>${pedido.horario}</div>
            <div style="color: #666;"><strong>Origem:</strong> ${pedido.origem || 'Não informada'}</div>
        </div>
        <div style="margin-bottom: 10px;">
            <strong>Cliente:</strong> ${pedido.cliente.nome}<br>
            <strong>Telefone:</strong> ${pedido.cliente.telefone}
        </div>
        <div style="margin-bottom: 10px;">
            <strong>Endereço:</strong> ${pedido.endereco}
        </div>
        <div style="margin-bottom: 10px;">
            <strong>Pagamento:</strong> ${pedido.pagamento.metodo} - ${pedido.pagamento.status}
        </div>
        <div style="margin-bottom: 10px;">
            <strong>Itens:</strong><br>
            ${pedido.itens.map(item => `
                ${item.quantidade}x ${item.produto} - R$ ${item.subtotal}<br>
                ${item.acompanhamentos.map(acomp => `
                    <div style="padding-left: 15px; font-size: 12px;">- ${acomp}</div>
                `).join('')}
            `).join('')}
        </div>
    `;

    displayElement.innerHTML = html;
    document.body.appendChild(displayElement);

    // Força um reflow para garantir que a transição funcione
    displayElement.offsetHeight;

    // Inicia a transição
    requestAnimationFrame(() => {
        displayElement.style.opacity = '1';
        displayElement.style.transform = 'scale(1)';
    });

    // Adiciona botão de fechar
    const closeButton = document.createElement('button');
    closeButton.innerHTML = '×';
    closeButton.style.cssText = `
        position: absolute;
        top: 5px;
        right: 5px;
        border: none;
        background: none;
        font-size: 20px;
        cursor: pointer;
        color: #666;
    `;
    closeButton.onclick = () => {
        displayElement.style.opacity = '0';
        displayElement.style.transform = 'scale(0.95)';
        setTimeout(() => displayElement.remove(), 200);
    };
    displayElement.appendChild(closeButton);

    setTimeout(() => {
        displayElement.style.opacity = '0';
        displayElement.style.transform = 'scale(0.95)';
        setTimeout(() => displayElement.remove(), 200);
    }, 5000);
}

// Função para formatar o endereço
function formatarEndereco(endereco, origem) {
    // Validações iniciais
    if (!endereco) return { endereco_completo: "Endereço não informado" };
    
    // Lista de palavras-chave que indicam endereço válido
    const palavrasChaveEndereco = ['rua', 'avenida', 'av', 'r.', 'alameda', 'travessa', 'nº', 'n°'];
    
    // Verifica se o campo origem contém um endereço mais completo
    if (origem && palavrasChaveEndereco.some(palavra => 
        origem.toLowerCase().includes(palavra.toLowerCase()))) {
        // Remove o complemento e região do endereço
        endereco = origem.split('|')[0].trim();
    }

    // Lista de textos que não são endereços
    const invalidEnderecos = [
        "pagamento recebido",
        "retirada no local",
        "pedido agendado",
        "entrega",
        "troco",
        "dinheiro",
        "cartão"
    ];

    // Se for endereço inválido e não tiver endereço na origem
    if (invalidEnderecos.some(texto => endereco.toLowerCase().includes(texto.toLowerCase()))) {
        return { endereco_completo: "Retirada no local" };
    }

    try {
        // Padrões de endereço atualizados
        const patterns = [
            // Rua/Av Nome, Nº XX, Bairro | Complemento: X | Região: Y
            /([^,]+?),?\s*[Nn][º°]?\s*(\d+[A-Za-z]?)[,\s]+([^,|]+)/,
            // Nome da rua simples com número e bairro
            /([^,]+?)\s*,?\s*(?:[Nn][º°]?)?\s*(\d+[A-Za-z]?)[,\s]+([^,|]+)/
        ];

        for (let pattern of patterns) {
            const match = endereco.match(pattern);
            if (match) {
                const [_, rua, numero, bairro] = match;
                // Validação adicional dos componentes
                if (rua && numero && bairro && 
                    !rua.includes('R$') && 
                    !rua.toLowerCase().includes('troco')) {
                    return {
                        rua: rua.trim(),
                        numero: numero,
                        bairro: bairro.trim()
                    };
                }
            }
        }

        // Se chegou aqui, tenta extrair da origem novamente
        if (origem) {
            // Remove complemento e região antes de processar
            const enderecoLimpo = origem.split('|')[0].trim();
            const origemMatch = enderecoLimpo.match(/([^,]+?),?\s*[Nn][º°]?\s*(\d+[A-Za-z]?)[,\s]+([^,|]+)/);
            if (origemMatch) {
                const [_, rua, numero, bairro] = origemMatch;
                return {
                    rua: rua.trim(),
                    numero: numero,
                    bairro: bairro.trim()
                };
            }
        }

        mostrarLogs(`Endereço não reconhecido: ${endereco}`, true);
        return { endereco_completo: endereco };

    } catch (error) {
        console.error('Erro ao formatar endereço:', error);
        mostrarLogs(`Erro ao formatar endereço: ${error.message}`, true);
        return { endereco_completo: endereco };
    }
}

// Função auxiliar para debug
function mostrarEnderecoLog(endereco, enderecoFormatado) {
    mostrarLogs([
        'Formatação de Endereço:',
        `Original: ${endereco}`,
        `Formatado: ${JSON.stringify(enderecoFormatado)}`
    ]);
}

// Função para extrair quantidade e texto do acompanhamento
function processarAcompanhamento(texto) {
    const match = texto.match(/^(.+?)\s*(?:\((\d+)\))?$/);
    if (!match) return { texto: texto };
    
    // Se não tem quantidade entre parênteses, retorna só o texto
    if (!match[2]) {
        return { texto: match[1].trim() };
    }
    
    // Se tem quantidade, retorna texto e quantidade
    return {
        texto: match[1].trim(),
        quantidade: parseInt(match[2])
    };
}

// Função para extrair dados do pedido
function extrairDadosPedido() {
    if (pedidoProcessado) return;
    
    try {
        // Buscar a origem primeiro para garantir que temos essa informação
        const origem = document.querySelector('.info-box:last-child .text')?.textContent || '';
        
        // Busca o endereço de forma mais compatível
        let endereco = '';
        const infoBoxes = document.querySelectorAll('.info-box');
        for (const box of infoBoxes) {
            const title = box.querySelector('.title')?.textContent || '';
            if (title.includes('Entrega')) {
                endereco = box.querySelector('.text')?.textContent || '';
                break;
            }
        }
        if (!endereco) {
            endereco = document.querySelector('.info-box:nth-child(3) .column-info .text')?.textContent;
        }
        
        // Modificação para lidar com horários agendados
        let horario = document.querySelector('.dialog-header .time span')?.textContent;
        const isAgendado = document.querySelector('.dialog-header .time.-scheduled');
        if (isAgendado) {
            const infoAgendamento = document.querySelector('.info-box:nth-child(2) .column-info .text')?.textContent;
            if (infoAgendamento) {
                const match = infoAgendamento.match(/(\d{1,2}:\d{2})/);
                horario = match ? match[1] : horario;
            }
        }

        // Busca o número do pedido na nova estrutura
        const numeroPedidoElement = document.querySelector('.dialog-header .title');
        const numeroPedido = numeroPedidoElement ? 
            numeroPedidoElement.textContent.replace('Pedido #', '').trim() : '';

        // IMPLEMENTAÇÃO PARA EXTRAIR MÉTODO DE PAGAMENTO EXATAMENTE COMO ESPERADO PELO SISTEMA
        let metodoPagamento = 'Não informado';
        let statusPagamento = '';
        
        // Percorre todas as caixas de informação para encontrar a de pagamento
        for (const box of infoBoxes) {
            const title = box.querySelector('.title')?.textContent?.trim() || '';
            const textContent = box.querySelector('.column-info .text')?.textContent?.trim() || '';
            
            // Verificar se é uma caixa de pagamento
            if (title.includes('Pix') && title.includes('Online') || box.querySelector('anota-pix')) {
                metodoPagamento = 'Online - Pix';  // Formato exato esperado pelo sistema
                statusPagamento = textContent;
                break;
            } else if (title === 'Cartão' || title.includes('Cartão') || box.querySelector('anota-credit-card')) {
                // Verificar o tipo específico de cartão
                if (textContent.toLowerCase().includes('débito') || textContent.toLowerCase().includes('debito')) {
                    metodoPagamento = 'Debito';  // Formato exato esperado pelo sistema
                } else {
                    metodoPagamento = 'Crédito';  // Formato exato esperado pelo sistema
                }
                statusPagamento = textContent;
                break;
            } else if (title === 'Dinheiro' || title.includes('Dinheiro')) {
                metodoPagamento = 'Dinheiro';  // Formato exato esperado pelo sistema
                statusPagamento = textContent;
                break;
            } else if (title.includes('Sodexo') || title.includes('Pluxee')) {
                metodoPagamento = 'Sodexo / Pluxee Refeição';  // Formato exato esperado pelo sistema
                statusPagamento = textContent;
                break;
            } else if (title.includes('VR') || title.includes('Refeição') || title.includes('Alimentação')) {
                metodoPagamento = 'VR Refeição / Alimentação';  // Formato exato esperado pelo sistema
                statusPagamento = textContent;
                break;
            } else if (title.includes('Voucher')) {
                metodoPagamento = 'Voucher';  // Formato exato esperado pelo sistema
                statusPagamento = textContent;
                break;
            } else if (title.includes('Pix') && !title.includes('Online')) {
                metodoPagamento = 'Pix Manual';  // Formato exato esperado pelo sistema
                statusPagamento = textContent;
                break;
            }
        }
        
        // Log para debug
        console.log('Método de pagamento encontrado:', metodoPagamento, statusPagamento);

        const pedido = {
            origem: origem,
            numero: numeroPedido,
            data: new Date().toISOString().split('T')[0],
            horario: horario,
            status: document.querySelector('.dialog-header .status-order')?.textContent?.trim() || '',
            cliente: {
                nome: document.querySelector('.info-box:first-child .column-info .text')?.textContent.trim(),
                telefone: document.querySelector('.info-box:first-child .phone')?.textContent?.replace(/\D/g, '')
            },
            pagamento: {
                metodo: metodoPagamento,
                status: statusPagamento
            },
            endereco: formatarEndereco(endereco, origem),
            itens: Array.from(document.querySelectorAll('.order-item-container')).map(item => {
                const itemTitle = item.querySelector('.item-title');
                if (!itemTitle) return null;
                
                const titleText = itemTitle.textContent.trim();
                const [quantidade, produtoComPreco] = titleText.split('x ');
                const produto = produtoComPreco?.trim().split('R$')[0].trim();
                
                let precoAcompanhamento = '';
                const acompanhamentosProcessados = Array.from(item.querySelectorAll('.filho') || [])
                    .map(acomp => {
                        const texto = acomp.textContent?.trim() || '';
                        const preco = acomp.querySelector('.price')?.textContent?.replace('R$', '')?.trim();
                        
                        const textoLimpo = texto.split('R$')[0].trim().replace(/^-\s*/, '');
                        const acompProcessado = processarAcompanhamento(textoLimpo);
                        
                        if (preco) {
                            precoAcompanhamento = preco;
                        }
                        
                        // Se não tem quantidade, retorna só o nome como string
                        if (!acompProcessado.quantidade) {
                            return acompProcessado.texto;
                        }
                        
                        // Se tem quantidade, retorna o objeto completo
                        return {
                            nome: acompProcessado.texto,
                            quantidade: acompProcessado.quantidade
                        };
                    })
                    .filter(Boolean);

                // Verificar se há observação no item
                const observacao = item.querySelector('.observation')?.textContent.replace('Observação:', '').trim();

                return {
                    quantidade: parseInt(quantidade),
                    produto: produto,
                    preco: precoAcompanhamento || item.querySelector('.main-price')?.textContent?.replace('R$', '')?.trim() || '',
                    acompanhamentos: acompanhamentosProcessados,
                    observacao: observacao || '',
                    subtotal: item.querySelector('.subtotal .money-label')?.textContent?.replace('R$', '')?.trim() || ''
                };
            }).filter(Boolean),
            
            valores: {
                subtotal: document.querySelector('.footer .row:first-child .text:last-child')?.textContent.replace('R$', '').trim() || '',
                taxa_entrega: document.querySelector('.footer .row:nth-child(2) .text:last-child')?.textContent.replace('R$', '').trim() || '',
                total: document.querySelector('.footer .row:last-child .text:last-child')?.textContent.replace('R$', '').trim() || ''
            }
        };

        // Marcar como processado
        pedidoProcessado = true;

        // Salvar no storage do Chrome com ordenação por origem
        chrome.storage.local.get(['pedidos'], function(result) {
            let pedidos = result.pedidos || [];
            pedidos.push(pedido);
            
            // Ordenar pedidos por origem
            pedidos.sort((a, b) => {
                const origemA = (a.origem || '').toLowerCase();
                const origemB = (b.origem || '').toLowerCase();
                return origemA.localeCompare(origemB);
            });
            
            chrome.storage.local.set({ pedidos: pedidos }, function() {
                console.log('Pedido salvo e ordenado por origem com sucesso!');
                atualizarContadorFlutuante();
            });
        });

        // Mostrar dados extraídos
        mostrarDadosExtraidos(pedido);
        
        // Após extrair os dados com sucesso
        marcarPedidoExtraido(pedido.numero);
        
        // Extrair informações do cupom, se houver
        const couponRows = document.querySelectorAll('.footer .row');
        for (const row of couponRows) {
            const firstText = row.querySelector('.text:first-child');
            if (firstText && firstText.textContent.includes('Cupom')) {
                const couponNameText = firstText.textContent.trim();
                const couponMatch = couponNameText.match(/\((.*?)\)/);
                const couponName = couponMatch ? couponMatch[1] : "";
                
                const couponValueElement = row.querySelector('.text:last-child');
                const couponValueText = couponValueElement.textContent.trim();
                const couponValueMatch = couponValueText.match(/- R\$\s*([\d,]+)/);
                const couponValue = couponValueMatch ? couponValueMatch[1] : "0";
                
                pedido.cupom = {
                    nome: couponName,
                    valor: couponValue
                };
            }
        }
        
        return pedido;
    } catch (error) {
        console.error('Erro ao extrair dados:', error);
        mostrarLogs([
            'Erro ao extrair dados do pedido',
            `Erro: ${error.message}`
        ], true);
    }
}

// Observer para detectar quando o modal é aberto ou fechado
const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        if (mutation.addedNodes.length) {
            // Verificar se o modal foi aberto (nova estrutura)
            const orderDialog = document.querySelector('.wrapper');
            const dialogHeader = document.querySelector('.dialog-header');
            
            if ((orderDialog || dialogHeader) && !pedidoProcessado) {
                console.log('Modal detectado, extraindo dados...');
                // Aumentar o tempo de espera para garantir que o modal carregue completamente
                setTimeout(extrairDadosPedido, 1500);
            }
        }
        // Resetar a flag quando o modal é fechado
        if (mutation.removedNodes.length) {
            const noOrderDialog = !document.querySelector('.wrapper') && !document.querySelector('.dialog-header');
            if (noOrderDialog) {
                console.log('Modal fechado, resetando flag');
                pedidoProcessado = false;
            }
        }
    }
});

// Iniciar observação com configurações mais abrangentes
observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['class']
});

// Adicionar um listener de clique global para detectar cliques nos botões de visualização
document.addEventListener('click', function(event) {
    // Verificar se o clique foi em um botão de visualização
    const isViewButton = event.target.closest('anota-table-button .table-button-icon.primary') || 
                         event.target.closest('anota-corner-down-right');
    
    if (isViewButton) {
        console.log('Clique em botão de visualização detectado');
        // Resetar a flag para permitir nova extração
        pedidoProcessado = false;
        
        // Aguardar a abertura do modal e então extrair os dados
        setTimeout(() => {
            if (!pedidoProcessado) {
                console.log('Extraindo dados após clique manual');
                extrairDadosPedido();
            }
        }, 1500);
    }
});

// Modificar a função que salva o JSON
function salvarPedidosJSON(pedidos) {
    const pedidosFormatados = pedidos.map(pedido => {
        const enderecoFormatado = formatarEndereco(pedido.endereco);
        mostrarEnderecoLog(pedido.endereco, enderecoFormatado);
        
        return {
            ...pedido,
            endereco: enderecoFormatado
        };
    });

    const json = JSON.stringify(pedidosFormatados, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = `pedidos_anotaai_${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Adiciona a animação de piscar
const style = document.createElement('style');
style.innerHTML = `
    @keyframes piscar {
        0%, 100% {
            background-color: #ffffff;
        }
        50% {
            background-color: #c6f6d5;
        }
    }
    .piscar {
        animation: piscar 0.5s linear 3;
    }
`;
document.head.appendChild(style);

// Adiciona o contador flutuante na página
function adicionarContadorFlutuante() {
    const contadorFlutuante = document.createElement('div');
    contadorFlutuante.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #009aff 0%, #2F6180 100%);
        color: white;
        padding: 15px;
        border-radius: 12px;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 14px;
        z-index: 9999;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s ease-out;
        max-height: 80vh;
        overflow-y: auto;
    `;
    contadorFlutuante.id = 'contadorFlutuante';

    // Atualiza o contador inicial e lista de pedidos
    chrome.storage.local.get(['pedidos'], function(result) {
        const pedidos = result.pedidos || [];
        const numeroPedidos = pedidos.length;
        
        let listaPedidosHtml = '';
        if (pedidos.length > 0) {
            listaPedidosHtml = `
                <div style="margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 10px;">
                    <div style="font-size: 12px; margin-bottom: 8px;">Últimos pedidos extraídos:</div>
                    <div style="max-height: 200px; overflow-y: auto;">
                        ${[...pedidos].reverse().map(p => `
                            <div style="
                                padding: 8px;
                                background: rgba(255,255,255,0.1);
                                border-radius: 6px;
                                margin-bottom: 5px;
                                font-size: 12px;
                            ">
                                <strong>#${p.numero}</strong> - ${p.cliente.nome}
                                <div style="font-size: 11px; opacity: 0.8;">Origem: ${p.origem || 'Não informada'}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        contadorFlutuante.innerHTML = `
            <div style="font-weight: bold; font-size: 16px;">
                Pedidos extraídos: <span>${numeroPedidos}</span>
            </div>
            ${listaPedidosHtml}
            <div style="margin-top: 15px; display: flex; flex-direction: column; gap: 8px;">
                <button id="exportarFlutuante" style="
                    width: 100%;
                    padding: 8px 15px;
                    border: none;
                    border-radius: 6px;
                    font-size: 14px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-weight: 500;
                    background-color: #2b6cb0;
                    color: white;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                ">Exportar Pedidos</button>
                <button id="limparFlutuante" style="
                    width: 100%;
                    padding: 8px 15px;
                    border: none;
                    border-radius: 6px;
                    font-size: 14px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-weight: 500;
                    background-color: #e53e3e;
                    color: white;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                ">Limpar Dados</button>
            </div>
        `;
        
        document.body.appendChild(contadorFlutuante);
        
        // Adiciona os event listeners aos botões
        document.getElementById('exportarFlutuante').addEventListener('click', function() {
            chrome.storage.local.get(['pedidos'], function(result) {
                if (result.pedidos && result.pedidos.length > 0) {
                    const blob = new Blob([JSON.stringify(result.pedidos, null, 2)], 
                        {type: 'application/json'});
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `pedidos_anotaai_${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }
            });
        });

        document.getElementById('limparFlutuante').addEventListener('click', function() {
            chrome.storage.local.clear(function() {
                atualizarContadorFlutuante();
            });
        });
        
        // Força um reflow e inicia a animação
        contadorFlutuante.offsetHeight;
        requestAnimationFrame(() => {
            contadorFlutuante.style.opacity = '1';
            contadorFlutuante.style.transform = 'translateY(0)';
        });
    });
}

// Função para atualizar o contador flutuante
function atualizarContadorFlutuante() {
    chrome.storage.local.get(['pedidos'], function(result) {
        const pedidos = result.pedidos || [];
        const numeroPedidos = pedidos.length;
        const contadorFlutuante = document.getElementById('contadorFlutuante');
        
        if (contadorFlutuante) {
            // Atualiza apenas o conteúdo interno, preservando os botões
            const conteudoHtml = `
                <div style="font-weight: bold; font-size: 16px;">
                    Pedidos extraídos: <span>${numeroPedidos}</span>
                </div>
                ${pedidos.length > 0 ? `
                    <div style="margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 10px;">
                        <div style="font-size: 12px; margin-bottom: 8px;">Últimos pedidos extraídos:</div>
                        <div style="max-height: 200px; overflow-y: auto;">
                            ${[...pedidos].reverse().map(p => `
                                <div style="
                                    padding: 8px;
                                    background: rgba(255,255,255,0.1);
                                    border-radius: 6px;
                                    margin-bottom: 5px;
                                    font-size: 12px;
                                ">
                                    <strong>#${p.numero}</strong> - ${p.cliente.nome}
                                    <div style="font-size: 11px; opacity: 0.8;">Origem: ${p.origem || 'Não informada'}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                <div id="botoesContainer" style="margin-top: 15px; display: flex; flex-direction: column; gap: 8px;">
                    <button id="exportarFlutuante" style="
                        width: 100%;
                        padding: 8px 15px;
                        border: none;
                        border-radius: 6px;
                        font-size: 14px;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        font-weight: 500;
                        background-color: #2b6cb0;
                        color: white;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    ">Exportar Pedidos</button>
                    <button id="limparFlutuante" style="
                        width: 100%;
                        padding: 8px 15px;
                        border: none;
                        border-radius: 6px;
                        font-size: 14px;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        font-weight: 500;
                        background-color: #e53e3e;
                        color: white;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    ">Limpar Dados</button>
                </div>
            `;

            contadorFlutuante.innerHTML = conteudoHtml;

            // Readiciona os event listeners aos botões
            document.getElementById('exportarFlutuante').addEventListener('click', function() {
                chrome.storage.local.get(['pedidos'], function(result) {
                    if (result.pedidos && result.pedidos.length > 0) {
                        const blob = new Blob([JSON.stringify(result.pedidos, null, 2)], 
                            {type: 'application/json'});
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `pedidos_anotaai_${new Date().toISOString().split('T')[0]}.json`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    }
                });
            });

            document.getElementById('limparFlutuante').addEventListener('click', function() {
                chrome.storage.local.clear(function() {
                    atualizarContadorFlutuante();
                });
            });
        }
    });
}

// Adiciona o contador quando a página carrega
window.addEventListener('load', adicionarContadorFlutuante);

// Adicionar observer para atualizar destaques quando a tabela for atualizada
const tableObserver = new MutationObserver(() => {
    destacarPedidosExtraidos();
});

// Modificar a função iniciarObservacaoTabela
function iniciarObservacaoTabela() {
    // Tenta encontrar a tabela imediatamente
    let tabelaPedidos = document.querySelector('.table-container');
    
    if (tabelaPedidos) {
        tableObserver.observe(tabelaPedidos, {
            childList: true,
            subtree: true
        });
        destacarPedidosExtraidos();
    } else {
        // Se a tabela ainda não existe, cria um observer para o body
        const bodyObserver = new MutationObserver((mutations, observer) => {
            tabelaPedidos = document.querySelector('.table-container');
            if (tabelaPedidos) {
                // Quando encontrar a tabela, inicia o observer dela
                tableObserver.observe(tabelaPedidos, {
                    childList: true,
                    subtree: true
                });
                destacarPedidosExtraidos();
                // Para o observer do body após encontrar a tabela
                observer.disconnect();
            }
        });

        // Observa mudanças no body
        bodyObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
}

// Modificar o event listener de load
window.addEventListener('load', iniciarObservacaoTabela);

// Adicionar um event listener para o DOMContentLoaded também
document.addEventListener('DOMContentLoaded', iniciarObservacaoTabela);

// Adicionar verificação periódica como backup
setInterval(() => {
    destacarPedidosExtraidos();
}, 2000);

// Adicione esta função para atualizar a data dos pedidos antes de exportar
function atualizarDataPedidos(data) {
    return new Promise((resolve) => {
        chrome.storage.local.get(['pedidos'], function(result) {
            const pedidos = result.pedidos || [];
            const pedidosAtualizados = pedidos.map(pedido => ({
                ...pedido,
                data: data
            }));
            
            chrome.storage.local.set({ pedidos: pedidosAtualizados }, () => {
                resolve(pedidosAtualizados);
            });
        });
    });
}

// Adicione este listener no final do arquivo
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'atualizarDataPedidos') {
        atualizarDataPedidos(request.data).then(pedidosAtualizados => {
            sendResponse({ pedidos: pedidosAtualizados });
        });
        return true; // Importante para manter a conexão aberta para resposta assíncrona
    }
});

// Função para processar pedidos automaticamente
async function processarPedidosAutomaticamente() {
    if (!automationRunning) return;

    // Seletor atualizado para os botões de visualização
    const botoesModal = document.querySelectorAll('anota-table-button .table-button-icon.primary anota-corner-down-right');
    console.log('Botões encontrados:', botoesModal.length);

    for (let i = 0; i < botoesModal.length; i++) {
        if (!automationRunning) break;

        const botao = botoesModal[i];
        
        // Navegar até o elemento pai correto para encontrar a linha do pedido
        const linhaPedido = botao.closest('.table-row');
        if (!linhaPedido) {
            console.log('Não foi possível encontrar a linha do pedido');
            continue;
        }
        
        const numeroCelula = linhaPedido.querySelector('.table-column__start .default');
        if (!numeroCelula) {
            console.log('Não foi possível encontrar o número do pedido');
            continue;
        }
        
        const numeroPedido = numeroCelula.textContent.replace('#', '').trim();
        console.log('Processando pedido:', numeroPedido);

        const jaProcessado = await verificarPedidoProcessado(numeroPedido);
        
        if (!jaProcessado) {
            try {
                // Clicar no botão de visualização (ajustado para a nova estrutura)
                const botaoContainer = botao.closest('.table-button-icon');
                if (botaoContainer) {
                    botaoContainer.click();
                    console.log('Clicou no botão de visualização');
                } else {
                    console.log('Botão de visualização não encontrado');
                    continue;
                }
                
                // Aguardar o modal abrir
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                // Extrair dados do pedido
                await extrairDadosPedido();
                marcarPedidoProcessado(numeroPedido);
                
                // Fechar o modal (seletor atualizado)
                const closeButton = document.querySelector('.dialog-close-button');
                if (closeButton) {
                    closeButton.click();
                    console.log('Modal fechado');
                } else {
                    // Tentar outro seletor para o botão de fechar
                    const alternativeCloseButton = document.querySelector('.outside anota-x');
                    if (alternativeCloseButton) {
                        alternativeCloseButton.closest('.outside').click();
                        console.log('Modal fechado (alternativo)');
                    } else {
                        console.log('Botão de fechar não encontrado');
                    }
                }
                
                // Aguardar o modal fechar
                await new Promise(resolve => setTimeout(resolve, 500));
            } catch (error) {
                console.error('Erro ao processar pedido:', numeroPedido, error);
            }
        } else {
            console.log('Pedido já processado:', numeroPedido);
        }
    }
    
    automationRunning = false;
    chrome.runtime.sendMessage({ type: 'AUTOMATION_FINISHED' });
    console.log('Automação finalizada');
}

// Função para resetar a automação
function resetarAutomacao() {
    pedidosProcessados.clear();
    automationRunning = false;
    console.log('Automação resetada');
}

// Listener para mensagens do popup
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    console.log('Mensagem recebida:', request.action);
    
    if (request.action === 'START_AUTOMATION') {
        console.log('Iniciando automação');
        automationRunning = true;
        processarPedidosAutomaticamente();
    } else if (request.action === 'STOP_AUTOMATION') {
        console.log('Parando automação');
        automationRunning = false;
    } else if (request.action === 'RESET_AUTOMATION') {
        console.log('Resetando automação');
        resetarAutomacao();
    }
}); 