document.addEventListener('DOMContentLoaded', function() {
    // Elementos do DOM
    const pedidosEmAnalise = document.getElementById('pedidosEmAnalise');
    const pedidosEmProducao = document.getElementById('pedidosEmProducao');
    const pedidosProntosEntrega = document.getElementById('pedidosProntosEntrega');
    const contadores = document.querySelectorAll('.contador');
    const btnReabrirLoja = document.getElementById('btnReabrirLoja');
    const btnNovoPedido = document.getElementById('btnNovoPedido');
    const btnExportarPedidos = document.getElementById('btnExportarPedidos');
    const btnLimparDados = document.getElementById('btnLimparDados');
    const aceitarAutomatico = document.getElementById('aceitarAutomatico');
    const modalTempoEstimado = document.getElementById('modalTempoEstimado');
    const formTempoEstimado = document.getElementById('formTempoEstimado');
    const tipoTempo = document.getElementById('tipoTempo');
    const tempoMinimo = document.getElementById('tempoMinimo');
    const tempoMaximo = document.getElementById('tempoMaximo');
    const btnEditarTempos = document.querySelectorAll('.btn-editar');
    const closeModal = document.querySelector('.close');
    const filtros = document.querySelectorAll('.filtro-btn');
    const buscarPedido = document.getElementById('btnBuscarPedido');
    const numeroPedido = document.getElementById('numeroPedido');
    const buscarCliente = document.getElementById('buscarCliente');
    
    // Configurações iniciais
    let temposEstimados = {
        balcao: { min: 25, max: 40 },
        delivery: { min: 35, max: 55 }
    };
    
    let pedidos = {
        emAnalise: [],
        emProducao: [],
        prontosEntrega: []
    };
    
    let filtroAtual = 'todos';
    let pedidosExtraidos = 0;
    
    // Variável global para controlar se o áudio pode ser reproduzido
    window.audioEnabled = false;
    
    // Inicializar a página
    inicializar();
    
    // Funções principais
    function inicializar() {
        carregarConfiguracoes();
        carregarPedidos();
        configurarEventListeners();
        
        // Solicitar permissão para notificações
        solicitarPermissaoNotificacao();
        
        // Adicionar botão de teste de som
        adicionarBotaoTesteSom();
        
        // Carregar preferência de som do usuário
        carregarPreferenciaSom();
        
        // Tentar iniciar com SSE primeiro
        try {
            window.pararMonitoramento = iniciarSSEPedidos();
            
            // Verificar se o SSE está funcionando após 30 segundos
            setTimeout(function() {
                if (window.sseErrorCount && window.sseErrorCount >= 5) {
                    console.log("SSE apresentou muitos erros, alternando para polling");
                    
                    // Parar SSE
                    if (window.pararMonitoramento) {
                        window.pararMonitoramento();
                    }
                    
                    // Iniciar polling como fallback
                    window.pararMonitoramento = iniciarPollingFallback();
                }
            }, 30000);
        } catch (error) {
            console.error("Erro ao iniciar SSE:", error);
            
            // Iniciar polling como fallback
            window.pararMonitoramento = iniciarPollingFallback();
        }
        
        // Adicionar botão para pausar/retomar monitoramento
        adicionarBotaoControleMonitoramento();
        
        console.log("Monitoramento iniciado");
    }
    
    function configurarEventListeners() {
        // Botões principais
        btnReabrirLoja.addEventListener('click', reabrirLoja);
        btnNovoPedido.addEventListener('click', criarNovoPedido);
        btnExportarPedidos.addEventListener('click', exportarPedidos);
        btnLimparDados.addEventListener('click', limparDados);
        
        // Aceitar automaticamente
        aceitarAutomatico.addEventListener('change', toggleAceitarAutomatico);
        
        // Modal de tempo estimado
        btnEditarTempos.forEach(btn => {
            btn.addEventListener('click', () => abrirModalTempo(btn.dataset.tipo));
        });
        
        closeModal.addEventListener('click', fecharModal);
        formTempoEstimado.addEventListener('submit', salvarTempoEstimado);
        
        // Filtros
        filtros.forEach(filtro => {
            filtro.addEventListener('click', () => aplicarFiltro(filtro));
        });
        
        // Busca
        buscarPedido.addEventListener('click', buscarPorNumeroPedido);
        buscarCliente.addEventListener('input', buscarPorCliente);
        
        // Fechar modal ao clicar fora
        window.addEventListener('click', (e) => {
            if (e.target === modalTempoEstimado) {
                fecharModal();
            }
        });
        
        // Registrar interação do usuário para permitir reprodução de áudio
        document.addEventListener('click', registrarInteracaoUsuario);
        document.addEventListener('keydown', registrarInteracaoUsuario);
        document.addEventListener('touchstart', registrarInteracaoUsuario);
    }
    
    function carregarConfiguracoes() {
        // Carregar configurações de tempo estimado
        fetch('../actions/configuracoes/buscar_config.php?tipo=tempo_estimado')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    temposEstimados = data.configuracoes;
                    
                    // Atualizar interface com os valores carregados
                    document.querySelector('.tempo-estimado[data-tipo="balcao"] strong').textContent = 
                        `${temposEstimados.balcao.min} a ${temposEstimados.balcao.max} min`;
                    
                    document.querySelector('.tempo-estimado[data-tipo="delivery"] strong').textContent = 
                        `${temposEstimados.delivery.min} a ${temposEstimados.delivery.max} min`;
                }
            })
            .catch(error => {
                console.error('Erro ao carregar configurações de tempo:', error);
            });
        
        // Carregar configuração de aceitar automaticamente
        fetch('../actions/configuracoes/buscar_config.php?tipo=geral&chave=aceitar_automatico')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    aceitarAutomatico.checked = data.valor === '1';
                }
            })
            .catch(error => {
                console.error('Erro ao carregar configuração de aceite automático:', error);
            });
    }
    
    function carregarPedidos() {
        // Buscar pedidos do servidor com parâmetro para o ano 2025 inteiro
        fetch('../actions/pedidos/buscar_pedidos.php?ano=2025')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    pedidos = data.pedidos;
                    atualizarContadores();
                    renderizarPedidos();
                } else {
                    console.error('Erro ao carregar pedidos:', data.message);
                }
            })
            .catch(error => {
                console.error('Erro na requisição de pedidos:', error);
            });
    }
    
    function renderizarPedidos() {
        // Limpar as listas
        limparElemento(pedidosEmAnalise);
        limparElemento(pedidosEmProducao);
        limparElemento(pedidosProntosEntrega);
        
        // Filtrar pedidos conforme o filtro atual
        const pedidosFiltrados = {
            emAnalise: filtrarPedidos(pedidos.emAnalise),
            emProducao: filtrarPedidos(pedidos.emProducao),
            prontosEntrega: filtrarPedidos(pedidos.prontosEntrega)
        };
        
        // Renderizar cada seção
        if (pedidosFiltrados.emAnalise.length === 0) {
            mostrarMensagemVazia(pedidosEmAnalise, 'Nenhum pedido no momento. Compartilhe os seus links nas redes sociais e receba pedidos!');
        } else {
            pedidosFiltrados.emAnalise.forEach(pedido => {
                const card = criarCardPedido(pedido, 'analise');
                pedidosEmAnalise.appendChild(card);
            });
        }
        
        if (pedidosFiltrados.emProducao.length === 0) {
            mostrarMensagemVazia(pedidosEmProducao, 'Nenhum pedido no momento. Receba pedidos e visualize os que estão em produção.');
        } else {
            pedidosFiltrados.emProducao.forEach(pedido => {
                const card = criarCardPedido(pedido, 'producao');
                pedidosEmProducao.appendChild(card);
            });
        }
        
        if (pedidosFiltrados.prontosEntrega.length === 0) {
            mostrarMensagemVazia(pedidosProntosEntrega, 'Nenhum pedido no momento.');
        } else {
            pedidosFiltrados.prontosEntrega.forEach(pedido => {
                const card = criarCardPedido(pedido, 'entrega');
                pedidosProntosEntrega.appendChild(card);
            });
        }
    }
    
    function criarCardPedido(pedido, etapa) {
        const template = document.getElementById('templatePedido');
        const clone = template.content.cloneNode(true);
        
        // Preencher dados do pedido
        const card = clone.querySelector('.pedido-card');
        card.dataset.id = pedido.id;
        card.classList.add('novo'); // Adicionar classe para animação
        card.classList.add(pedido.tipo); // Adicionar classe para o tipo (balcao ou delivery)
        
        // Adicionar evento de clique para abrir o modal
        card.addEventListener('click', function(e) {
            // Verificar se o clique foi em um botão de ação
            if (!e.target.closest('.pedido-acoes button')) {
                abrirModalPedido(pedido.id);
            }
        });
        
        // Remover a classe após a animação
        setTimeout(() => {
            const cardElement = document.querySelector(`.pedido-card[data-id="${pedido.id}"]`);
            if (cardElement) {
                cardElement.classList.remove('novo');
            }
        }, 500);
        
        // Preencher dados do pedido com telefone formatado
        clone.querySelector('.pedido-numero .numero').textContent = `Pedido #${pedido.numero}`;
        clone.querySelector('.pedido-data .data').textContent = `${pedido.data}`;
        clone.querySelector('.cliente-nome').textContent = pedido.cliente.nome;
        clone.querySelector('.cliente-telefone').textContent = formatarTelefone(pedido.cliente.telefone);
        clone.querySelector('.pedido-endereco').textContent = pedido.endereco;
        clone.querySelector('.pedido-itens').textContent = pedido.itens;
        clone.querySelector('.valor-total .total').textContent = formatarMoeda(pedido.total);
        clone.querySelector('.forma-pagamento .pagamento').textContent = pedido.pagamento;
        
        // Configurar botões conforme a etapa
        const btnAvancar = clone.querySelector('.btn-avancar');
        const btnFinalizar = clone.querySelector('.btn-finalizar');
        
        if (etapa === 'analise') {
            btnAvancar.textContent = 'Aceitar';
            btnAvancar.addEventListener('click', function(e) {
                e.stopPropagation(); // Evitar que o clique abra o modal
                moverParaProducao(pedido.id);
            });
            btnFinalizar.style.display = 'none';
        } else if (etapa === 'producao') {
            btnAvancar.textContent = 'Pronto';
            btnAvancar.addEventListener('click', function(e) {
                e.stopPropagation(); // Evitar que o clique abra o modal
                moverParaEntrega(pedido.id);
            });
            btnFinalizar.style.display = 'none';
        } else if (etapa === 'entrega') {
            btnAvancar.style.display = 'none';
            btnFinalizar.textContent = 'Finalizar';
            btnFinalizar.addEventListener('click', function(e) {
                e.stopPropagation(); // Evitar que o clique abra o modal
                finalizarPedido(pedido.id);
            });
            
            // Adicionar link "Escolher Entregador" para pedidos de delivery
            if (pedido.tipo === 'delivery') {
                const enderecoElement = clone.querySelector('.pedido-endereco');
                const escolherEntregador = document.createElement('a');
                escolherEntregador.href = 'javascript:void(0)';
                escolherEntregador.className = 'escolher-entregador';
                escolherEntregador.textContent = 'Escolher Entregador';
                escolherEntregador.addEventListener('click', function(e) {
                    e.stopPropagation(); // Evitar que o clique abra o modal
                    abrirModalEntregador(pedido.id);
                });
                enderecoElement.appendChild(escolherEntregador);
            }
        }
        
        // Configurar botões de ação
        clone.querySelector('.btn-imprimir').addEventListener('click', function(e) {
            e.stopPropagation(); // Evitar que o clique abra o modal
            imprimirPedido(pedido.id);
        });
        clone.querySelector('.btn-cancelar').addEventListener('click', function(e) {
            e.stopPropagation(); // Evitar que o clique abra o modal
            cancelarPedido(pedido.id);
        });
        
        return clone;
    }
    
    function mostrarMensagemVazia(elemento, mensagem) {
        const div = document.createElement('div');
        div.className = 'sem-pedidos';
        div.innerHTML = `<p>${mensagem}</p>`;
        elemento.appendChild(div);
    }
    
    function limparElemento(elemento) {
        while (elemento.firstChild) {
            elemento.removeChild(elemento.firstChild);
        }
    }
    
    function atualizarContadores() {
        // Valores anteriores para comparação
        const valoresAnteriores = [
            parseInt(contadores[0].textContent),
            parseInt(contadores[1].textContent),
            parseInt(contadores[2].textContent)
        ];
        
        // Atualizar contadores de cada coluna
        const novosValores = [
            filtrarPedidos(pedidos.emAnalise).length,
            filtrarPedidos(pedidos.emProducao).length,
            filtrarPedidos(pedidos.prontosEntrega).length
        ];
        
        // Atualizar e animar se houver mudança
        for (let i = 0; i < contadores.length; i++) {
            contadores[i].textContent = novosValores[i];
            if (novosValores[i] !== valoresAnteriores[i]) {
                animarContador(contadores[i]);
            }
        }
        
        // Atualizar contador de pedidos extraídos
        document.getElementById('totalExtraidos').textContent = pedidosExtraidos;
    }
    
    function filtrarPedidos(listaPedidos) {
        if (filtroAtual === 'todos') {
            return listaPedidos;
        }
        
        return listaPedidos.filter(pedido => pedido.tipo === filtroAtual);
    }
    
    // Ações de pedidos
    function moverParaProducao(id) {
        // Animar o card antes de mover
        const card = document.querySelector(`.pedido-card[data-id="${id}"]`);
        if (card) {
            card.classList.add('movendo-direita');
            
            // Aguardar a animação terminar antes de atualizar
            setTimeout(() => {
                atualizarStatusPedido(id, 'producao');
            }, 400); // Metade do tempo da animação
        } else {
            atualizarStatusPedido(id, 'producao');
        }
    }
    
    function moverParaEntrega(id) {
        // Animar o card antes de mover
        const card = document.querySelector(`.pedido-card[data-id="${id}"]`);
        if (card) {
            card.classList.add('movendo-direita');
            
            // Aguardar a animação terminar antes de atualizar
            setTimeout(() => {
                atualizarStatusPedido(id, 'entrega');
            }, 400); // Metade do tempo da animação
        } else {
            atualizarStatusPedido(id, 'entrega');
        }
    }
    
    function finalizarPedido(id) {
        // Animar o card antes de finalizar
        const card = document.querySelector(`.pedido-card[data-id="${id}"]`);
        if (card) {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.8)';
            
            // Aguardar a animação terminar antes de atualizar
            setTimeout(() => {
                atualizarStatusPedido(id, 'finalizado');
                pedidosExtraidos++;
            }, 500);
        } else {
            atualizarStatusPedido(id, 'finalizado');
            pedidosExtraidos++;
        }
    }
    
    function imprimirPedido(id) {
        // Buscar detalhes do pedido para impressão
        fetch(`../actions/pedidos/buscar_pedido_detalhes.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Criar um iframe oculto para impressão
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    document.body.appendChild(iframe);
                    
                    // Escrever o conteúdo HTML no iframe com telefone formatado
                    iframe.contentDocument.write(`
                        <html>
                        <head>
                            <title>Pedido #${data.pedido.numero}</title>
                            <style>
                                @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@500;700&display=swap');
                                * { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; box-sizing: border-box; font-weight: 700; margin-bottom: 7px!important; }
                                body { margin: 0; padding: 0; width: 100%; line-height: 1.2; font-size: 12pt; }
                                @page { margin: 20px; size: auto; }
                                .data-pedido { text-align: center; font-size: 10pt; margin-bottom: 0; }
                                h1 { text-align: center; padding: 5px 0; border-bottom: 1px solid #333; margin-bottom: 8px; font-size: 16pt; }
                                h2 { background-color: #f5f5f5; padding: 4px; border-left: 3px solid #333; margin: 8px 0; font-size: 11pt; font-weight: normal; }
                                .itens-container { border: 1px solid; border-radius: 3px; padding: 5px; margin-bottom: 10px; }
                                .item { margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px dashed #000; }
                                .item:last-child { border-bottom: none; }
                                .item p { font-size: 12pt; }
                                .item ul { margin-left: 15px; list-style-type: circle; font-size: 11pt; }
                                .info-pedido { background-color: #f9f9f9; padding: 8px; border-radius: 3px; margin-bottom: 10px; }
                                .info-pedido p { margin: 2px 0; font-size: 12pt; }
                                .total { border: 1px solid; border-radius: 3px; padding: 8px; margin-top: 10px; margin-bottom: 10px!important; background-color: #f9f9f9; }
                                .total p { margin: 2px 0; font-size: 12pt; }
                                .total p:last-child { font-size: 18pt; color: #000; text-align: center; font-weight: bold; }
                                .emoji { font-size: 14pt; margin-right: 5px; }
                                .numero-pedido { font-size: 36pt; font-weight: 900; text-align: center; margin: 15px 0; }
                                /* Espaço adicional no final da página */
                                .espaco-final { height: 50px; width: 100%; display: block; text-align: center; font-size: 12pt; font-weight: bold; color: #333; }
                            </style>
                        </head>
                        <body>
                            <p class="data-pedido">${data.pedido.data} ${data.pedido.hora}</p>
                            <h1>🧾 Pedido</h1>
                            <div class="numero-pedido">#${data.pedido.numero}</div>
                            
                            <h2>🍔 Itens do Pedido</h2>
                            <div class="itens-container">
                                ${data.pedido.itens_detalhados.map(item => `
                                    <div class="item">
                                        <p><strong>✅ ${item.quantidade}x ${item.nome_produto}</strong> - ${formatarMoeda(item.preco_unitario)}</p>
                                        ${item.acompanhamentos.length > 0 ? `
                                            <ul>
                                                ${item.acompanhamentos.map(acomp => `
                                                    <li>${acomp.quantidade > 1 ? `${acomp.quantidade}x ` : ''}${acomp.nome_subacomp}${parseFloat(acomp.preco_subacomp) > 0 ? ` - ${formatarMoeda(acomp.preco_subacomp)}` : ''}</li>
                                                `).join('')}
                                            </ul>
                                        ` : ''}
                                    </div>
                                `).join('')}
                            </div>
                            
                            <h2>👤 Cliente</h2>
                            <div class="info-pedido">
                                <p>👤 ${data.pedido.cliente.nome}</p>
                                <p>📱 ${formatarTelefone(data.pedido.cliente.telefone)}</p>
                                <p>🏠 ${data.pedido.endereco}</p>
                                <p>💰 ${data.pedido.pagamento}</p>
                            </div>
                            <div class="total">
                                <p>🛒 Subtotal: ${formatarMoeda(data.pedido.subtotal)}</p>
                                <p>🚚 Taxa de Entrega: ${formatarMoeda(data.pedido.taxa_entrega)}</p>
                                <p>💵 Total: ${formatarMoeda(data.pedido.total)}</p>
                            </div>
                            <!-- Espaço adicional no final da página -->
                            <div class="espaco-final">--------------------------------</div>
                            <div class="espaco-final">--------------------------------</div>
                            
                        </body>
                        </html>
                    `);
                    
                    // Aguardar o carregamento do conteúdo
                    iframe.onload = function() {
                        try {
                            // Imprimir o iframe
                            iframe.contentWindow.print();
                            
                            // Remover o iframe após a impressão
                            setTimeout(() => {
                                document.body.removeChild(iframe);
                            }, 1000);
                        } catch (error) {
                            console.error('Erro ao imprimir:', error);
                            alert('Erro ao imprimir pedido. Tente novamente.');
                        }
                    };
                    
                    iframe.contentDocument.close();
                } else {
                    alert('Erro ao buscar detalhes do pedido para impressão');
                }
            })
            .catch(error => {
                console.error('Erro ao buscar detalhes do pedido:', error);
                alert('Erro ao buscar detalhes do pedido');
            });
    }
    
    function cancelarPedido(id) {
        if (confirm('Tem certeza que deseja cancelar este pedido?')) {
            // Animar o card antes de cancelar
            const card = document.querySelector(`.pedido-card[data-id="${id}"]`);
            if (card) {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateX(-100px)';
                
                // Aguardar a animação terminar antes de atualizar
                setTimeout(() => {
                    atualizarStatusPedido(id, 'cancelado');
                }, 500);
            } else {
                atualizarStatusPedido(id, 'cancelado');
            }
        }
    }
    
    function atualizarStatusPedido(id, status) {
        // Enviar requisição para atualizar status
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', status);
        
        fetch('../actions/pedidos/atualizar_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Recarregar pedidos após atualização
                carregarPedidos();
            } else {
                console.error('Erro ao atualizar status:', data.message);
                alert('Erro ao atualizar status do pedido: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            alert('Erro ao atualizar status do pedido');
        });
    }
    
    // Ações da interface
    function reabrirLoja() {
        // Implementar lógica para reabrir loja
        fetch('../actions/configuracoes/reabrir_loja.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Loja reaberta com sucesso!');
            } else {
                alert('Erro ao reabrir loja: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao reabrir loja:', error);
            alert('Erro ao reabrir loja');
        });
    }
    
    function criarNovoPedido() {
        // Redirecionar para página de criação de pedido
        window.location.href = 'novo_pedido.php';
    }
    
    function exportarPedidos() {
        // Implementar lógica para exportar pedidos
        fetch('../actions/pedidos/exportar_pedidos.php')
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'pedidos_' + formatarDataArquivo(new Date()) + '.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                alert('Pedidos exportados com sucesso!');
            })
            .catch(error => {
                console.error('Erro ao exportar pedidos:', error);
                alert('Erro ao exportar pedidos');
            });
    }
    
    function limparDados() {
        if (confirm('Tem certeza que deseja limpar todos os dados de pedidos extraídos?')) {
            fetch('../actions/pedidos/limpar_extraidos.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    pedidosExtraidos = 0;
                    atualizarContadores();
                    alert('Dados limpos com sucesso!');
                } else {
                    alert('Erro ao limpar dados: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro ao limpar dados:', error);
                alert('Erro ao limpar dados');
            });
        }
    }
    
    function toggleAceitarAutomatico() {
        const isChecked = aceitarAutomatico.checked;
        
        // Enviar configuração para o servidor
        const formData = new FormData();
        formData.append('aceitar_automatico', isChecked ? 1 : 0);
        
        fetch('../actions/configuracoes/salvar_config.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Erro ao salvar configuração:', data.message);
                // Reverter checkbox se houver erro
                aceitarAutomatico.checked = !isChecked;
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            // Reverter checkbox se houver erro
            aceitarAutomatico.checked = !isChecked;
        });
    }
    
    function abrirModalTempo(tipo) {
        tipoTempo.value = tipo;
        tempoMinimo.value = temposEstimados[tipo].min;
        tempoMaximo.value = temposEstimados.delivery.max;
        modalTempoEstimado.style.display = 'block';
    }
    
    function fecharModal() {
        modalTempoEstimado.style.display = 'none';
    }
    
    function salvarTempoEstimado(e) {
        e.preventDefault();
        const tipo = tipoTempo.value;
        const min = parseInt(tempoMinimo.value);
        const max = parseInt(tempoMaximo.value);
        
        if (min > max) {
            alert('O tempo mínimo não pode ser maior que o tempo máximo.');
            return;
        }
        
        // Enviar configuração para o servidor
        const formData = new FormData();
        formData.append('tipo', tipo);
        formData.append('min', min);
        formData.append('max', max);
        
        fetch('../actions/configuracoes/salvar_tempo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar valores locais
                temposEstimados[tipo] = { min, max };
                
                // Atualizar texto na interface
                document.querySelector(`.tempo-estimado[data-tipo="${tipo}"] strong`).textContent = `${min} a ${max} min`;
                
                fecharModal();
            } else {
                alert('Erro ao salvar tempo estimado: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            alert('Erro ao salvar tempo estimado');
        });
    }
    
    function aplicarFiltro(elemento) {
        // Remover classe active de todos os filtros
        filtros.forEach(f => f.classList.remove('active'));
        
        // Adicionar classe active ao filtro clicado
        elemento.classList.add('active');
        
        // Definir filtro atual
        if (elemento.dataset.filtro === 'balcao') {
            filtroAtual = 'balcao';
        } else if (elemento.dataset.filtro === 'delivery') {
            filtroAtual = 'delivery';
        } else {
            filtroAtual = 'todos';
        }
        
        // Renderizar pedidos com o novo filtro
        renderizarPedidos();
        atualizarContadores();
    }
    
    function buscarPorNumeroPedido() {
        const numero = numeroPedido.value.trim();
        if (!numero) return;
        
        fetch(`../actions/pedidos/buscar_pedido.php?numero=${numero}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Verificar se encontrou algum pedido
                    let encontrado = false;
                    for (const lista in data.pedidos) {
                        if (data.pedidos[lista].length > 0) {
                            encontrado = true;
                            
                            // Substituir temporariamente para mostrar apenas o pedido encontrado
                            const pedidosOriginais = {...pedidos};
                            pedidos = data.pedidos;
                            
                            renderizarPedidos();
                            
                            // Destacar o pedido encontrado com animação
                            setTimeout(() => {
                                const pedidoCard = document.querySelector(`.pedido-card[data-id="${data.pedidos[lista][0].id}"]`);
                                if (pedidoCard) {
                                    pedidoCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    pedidoCard.classList.add('destacado');
                                    
                                    // Pulsar o card 3 vezes
                                    let pulsos = 0;
                                    const intervalo = setInterval(() => {
                                        pedidoCard.classList.toggle('destacado');
                                        pulsos++;
                                        if (pulsos >= 6) { // 3 ciclos completos
                                            clearInterval(intervalo);
                                            pedidoCard.classList.remove('destacado');
                                        }
                                    }, 500);
                                }
                            }, 100);
                            
                            break;
                        }
                    }
                    
                    if (!encontrado) {
                        alert(`Pedido #${numero} não encontrado`);
                        // Recarregar pedidos originais
                        carregarPedidos();
                    }
                } else {
                    alert('Erro ao buscar pedido: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro na busca de pedido:', error);
                alert('Erro ao buscar pedido');
            });
    }
    
    function buscarPorCliente() {
        const termo = buscarCliente.value.trim();
        if (!termo) {
            carregarPedidos();
            return;
        }
        
        if (termo.length < 3) return;
        
        fetch(`../actions/pedidos/buscar_pedidos.php?cliente=${termo}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    pedidos = data.pedidos;
                    renderizarPedidos();
                    atualizarContadores();
                } else {
                    console.error('Erro ao buscar pedidos por cliente:', data.message);
                }
            })
            .catch(error => {
                console.error('Erro na busca por cliente:', error);
            });
    }
    
    // Funções utilitárias
    function formatarMoeda(valor) {
        return 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',');
    }
    
    function formatarDataArquivo(data) {
        const dia = String(data.getDate()).padStart(2, '0');
        const mes = String(data.getMonth() + 1).padStart(2, '0');
        const ano = data.getFullYear();
        return `${dia}-${mes}-${ano}`;
    }
    
    // Função para animar contador quando atualizado
    function animarContador(contador) {
        contador.classList.add('updated');
        setTimeout(() => {
            contador.classList.remove('updated');
        }, 500);
    }
    
    // Adicionar função para abrir modal de entregador
    function abrirModalEntregador(pedidoId) {
        alert(`Funcionalidade de escolher entregador para o pedido ${pedidoId} será implementada em breve.`);
        // Aqui você pode implementar a lógica para abrir um modal de seleção de entregador
    }
    
    // Função para abrir o modal com os detalhes do pedido
    function abrirModalPedido(pedidoId) {
        const modalDetalhesPedido = document.getElementById('modalDetalhesPedido');
        const modalClose = modalDetalhesPedido.querySelector('.close');
        const btnFinalizarModal = document.getElementById('btnFinalizarPedido');
        const btnImprimirModal = document.getElementById('btnImprimirPedido');
        const btnExcluirPedido = document.getElementById('btnExcluirPedido');
        
        // Buscar detalhes completos do pedido
        fetch(`../actions/pedidos/buscar_pedido_detalhes.php?id=${pedidoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pedido = data.pedido;
                    
                    // Preencher os dados no modal
                    document.getElementById('modalPedidoNumero').textContent = `Pedido #${pedido.numero}`;
                    
                    // Definir o status do pedido
                    const statusElement = document.getElementById('modalPedidoStatus');
                    let statusText = 'Em análise';
                    let statusClass = 'status-analise';
                    
                    if (pedido.status == 1) {
                        statusText = 'Em produção';
                        statusClass = 'status-producao';
                    } else if (pedido.status == 2) {
                        statusText = 'Pronto para entrega';
                        statusClass = 'status-entrega';
                    }
                    
                    statusElement.textContent = statusText;
                    statusElement.className = 'status-tag ' + statusClass;
                    
                    // Preencher data
                    document.getElementById('modalPedidoData').textContent = pedido.data;
                    
                    // Preencher dados do cliente com telefone formatado
                    document.getElementById('modalClienteNome').textContent = pedido.cliente.nome;
                    document.getElementById('modalClienteTelefone').textContent = formatarTelefone(pedido.cliente.telefone);
                    document.getElementById('modalClienteTelefone').href = `tel:${pedido.cliente.telefone}`;
                    
                    // Preencher agendamento (simplificado)
                    document.getElementById('modalPedidoAgendamento').innerHTML = 
                        `Pedido realizado em ${pedido.data} às ${pedido.hora}`;
                    
                    // Preencher endereço
                    document.getElementById('modalEnderecoEntrega').textContent = pedido.endereco;
                    
                    // Preencher forma de pagamento
                    document.getElementById('modalFormaPagamento').innerHTML = 
                        `${pedido.pagamento}`;
                    
                    // Preencher origem do pedido (simplificado)
                    document.getElementById('modalOrigemPedido').textContent = 'Pedido via sistema';
                    
                    // Preencher itens do pedido
                    const itensContainer = document.getElementById('modalItensPedido');
                    itensContainer.innerHTML = '';
                    
                    pedido.itens_detalhados.forEach(item => {
                        const itemHtml = `
                            <div class="item">
                                <div class="item-info">
                                    <div class="item-quantidade">${item.quantidade}x</div>
                                    <div class="item-nome">${item.nome_produto}</div>
                                </div>
                                <div class="item-preco">R$ ${parseFloat(item.preco_unitario).toFixed(2).replace('.', ',')}</div>
                            </div>
                        `;
                        
                        let adicionaisHtml = '<div class="item-adicionais">';
                        
                        if (item.acompanhamentos && item.acompanhamentos.length > 0) {
                            let acompAtual = '';
                            
                            item.acompanhamentos.forEach(acomp => {
                                if (acompAtual !== acomp.nome_acomp) {
                                    if (acompAtual !== '') {
                                        adicionaisHtml += '</div>';
                                    }
                                    
                                    acompAtual = acomp.nome_acomp;
                                    adicionaisHtml += `
                                        <div class="adicional">
                                            <span class="adicional-nome">${acomp.nome_acomp}</span>
                                    `;
                                }
                                
                                adicionaisHtml += `
                                    <div class="adicional-opcoes">
                                        <span class="adicional-opcao">- ${acomp.nome_subacomp} ${acomp.quantidade > 1 ? `(${acomp.quantidade}x)` : ''}</span>
                                    </div>
                                `;
                            });
                            
                            adicionaisHtml += '</div>';
                        }
                        
                        adicionaisHtml += '</div>';
                        
                        itensContainer.innerHTML += itemHtml + adicionaisHtml;
                    });
                    
                    // Preencher valores
                    document.getElementById('modalSubtotal').textContent = formatarMoeda(pedido.subtotal);
                    document.getElementById('modalTaxaEntrega').textContent = formatarMoeda(pedido.taxa_entrega);
                    document.getElementById('modalTotal').textContent = formatarMoeda(pedido.total);
                    
                    // Configurar botões de ação
                    btnFinalizarModal.onclick = () => {
                        finalizarPedido(pedido.id);
                        fecharModalPedido();
                    };
                    
                    btnImprimirModal.onclick = () => {
                        imprimirPedido(pedido.id);
                    };
                    
                    btnExcluirPedido.onclick = () => {
                        if (confirm('Tem certeza que deseja cancelar este pedido?')) {
                            cancelarPedido(pedido.id);
                            fecharModalPedido();
                        }
                    };
                    
                    // Mostrar ou esconder botão finalizar conforme o status
                    if (pedido.status == 2) {
                        btnFinalizarModal.style.display = 'flex';
                    } else {
                        btnFinalizarModal.style.display = 'none';
                    }
                    
                    // Exibir o modal
                    modalDetalhesPedido.style.display = 'block';
                    
                    // Adicionar classe ao body para evitar scroll
                    document.body.classList.add('modal-open');
                } else {
                    alert('Erro ao buscar detalhes do pedido: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Erro ao buscar detalhes do pedido');
            });
    }
    
    // Função para fechar o modal
    function fecharModalPedido() {
        const modalDetalhesPedido = document.getElementById('modalDetalhesPedido');
        modalDetalhesPedido.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
    
    // Adicionar eventos de fechamento do modal quando o documento estiver pronto
    const modalDetalhesPedido = document.getElementById('modalDetalhesPedido');
    const modalClose = modalDetalhesPedido.querySelector('.close');
    
    // Configurar evento de fechar o modal
    modalClose.addEventListener('click', fecharModalPedido);
    
    // Fechar o modal ao clicar fora dele
    window.addEventListener('click', function(e) {
        if (e.target === modalDetalhesPedido) {
            fecharModalPedido();
        }
    });
    
    // Adicionar estilo para evitar scroll quando o modal estiver aberto
    const style = document.createElement('style');
    style.textContent = `
        body.modal-open {
            overflow: hidden;
        }
        
        .status-analise {
            background-color: #17a2b8;
        }
        
        .status-producao {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-entrega {
            background-color: #28a745;
        }
    `;
    document.head.appendChild(style);
    
    // Função para solicitar permissão para notificações do navegador
    function solicitarPermissaoNotificacao() {
        if (!("Notification" in window)) {
            console.log("Este navegador não suporta notificações de desktop");
            return;
        }
        
        if (Notification.permission !== "granted" && Notification.permission !== "denied") {
            Notification.requestPermission().then(permission => {
                if (permission === "granted") {
                    console.log("Permissão para notificações concedida");
                    // Mostrar uma notificação de teste
                    const notification = new Notification("Marmitaria Farias", {
                        body: "Notificações ativadas com sucesso!",
                        icon: "../assets/img/logo.png"
                    });
                    
                    // Fechar a notificação após 5 segundos
                    setTimeout(() => notification.close(), 5000);
                }
            });
        }
    }

    // Função para mostrar notificação do navegador
    function mostrarNotificacaoNavegador(titulo, mensagem) {
        if (Notification.permission === "granted") {
            const notification = new Notification(titulo, {
                body: mensagem,
                icon: "../assets/img/logo.png"
            });
            
            // Quando o usuário clicar na notificação, focar na janela
            notification.onclick = function() {
                window.focus();
                this.close();
            };
            
            // Fechar a notificação após 10 segundos
            setTimeout(() => notification.close(), 10000);
        }
    }

    // Função para registrar interação do usuário e habilitar áudio
    function registrarInteracaoUsuario() {
        document.documentElement.setAttribute('data-user-interacted', 'true');
    }

    // Função para reproduzir som de notificação com tratamento de erros
    function reproduzirSomNotificacao() {
        // Verificar se o áudio está habilitado para este usuário
        if (!window.audioEnabled) {
            console.log('Áudio não reproduzido: desabilitado pelo usuário');
            return;
        }
        
        // Verificar se houve interação do usuário
        if (!document.documentElement.hasAttribute('data-user-interacted')) {
            console.log('Áudio não reproduzido: usuário ainda não interagiu com a página');
            return;
        }
        
        try {
            // Usar o áudio pré-carregado ou criar um novo
            const audio = window.notificationSound || new Audio('../assets/sounds/notification.mp3');
            audio.volume = 0.5;
            
            // Tentar reproduzir com tratamento de promessa
            const playPromise = audio.play();
            
            // Tratar possíveis erros na reprodução
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        console.log('Áudio reproduzido com sucesso');
                    })
                    .catch(err => {
                        console.log('Não foi possível reproduzir o som:', err.message);
                    });
            }
        } catch (error) {
            console.log('Erro ao tentar reproduzir som:', error);
        }
    }

    // Função para adicionar botão de teste de som
    function adicionarBotaoTesteSom() {
        // Verificar se o botão já existe
        if (document.getElementById('btnTesteSom')) {
            return;
        }
        
        // Criar o botão
        const btn = document.createElement('button');
        btn.id = 'btnTesteSom';
        btn.className = 'btn-teste-som';
        btn.innerHTML = '<i class="fas fa-volume-mute"></i> Ativar notificações sonoras';
        
        // Estilizar o botão
        btn.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        `;
        
        // Adicionar evento de clique
        btn.addEventListener('click', function() {
            // Registrar interação do usuário
            registrarInteracaoUsuario();
            
            // Alternar estado do áudio
            window.audioEnabled = !window.audioEnabled;
            
            // Salvar preferência no servidor
            salvarPreferenciaSom(window.audioEnabled);
            
            // Atualizar aparência do botão
            atualizarBotaoSom(window.audioEnabled);
            
            // Se foi habilitado, testar o som
            if (window.audioEnabled) {
                try {
                    const testAudio = new Audio('../assets/sounds/notification.mp3');
                    testAudio.volume = 0.5;
                    
                    const playPromise = testAudio.play();
                    if (playPromise !== undefined) {
                        playPromise
                            .then(() => {
                                // Som reproduzido com sucesso
                                mostrarNotificacao('Notificações sonoras ativadas com sucesso!');
                            })
                            .catch(err => {
                                console.log('Erro no teste de som:', err.message);
                                mostrarNotificacao('Não foi possível reproduzir o som. Verifique as permissões do navegador.');
                            });
                    }
                } catch (error) {
                    console.log('Erro ao testar som:', error);
                }
            } else {
                mostrarNotificacao('Notificações sonoras desativadas.');
            }
        });
        
        // Adicionar ao DOM
        document.body.appendChild(btn);
        
        return btn;
    }

    // Função para atualizar o botão de som com base na preferência
    function atualizarBotaoSom(habilitado) {
        const btn = document.getElementById('btnTesteSom');
        if (!btn) return;
        
        if (habilitado) {
            btn.innerHTML = '<i class="fas fa-volume-up"></i> Notificações sonoras ativadas';
            btn.style.backgroundColor = '#28a745';
        } else {
            btn.innerHTML = '<i class="fas fa-volume-mute"></i> Ativar notificações sonoras';
            btn.style.backgroundColor = '#6c757d';
        }
    }

    // Função para carregar a preferência de som do usuário
    function carregarPreferenciaSom() {
        fetch('../actions/configuracoes/buscar_preferencia_som.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Definir o estado global com base na preferência do usuário
                    window.audioEnabled = data.habilitado;
                    
                    // Atualizar o botão de som
                    atualizarBotaoSom(data.habilitado);
                    
                    // Se estiver habilitado, pré-carregar o som
                    if (data.habilitado) {
                        if (!window.notificationSound) {
                            window.notificationSound = new Audio('../assets/sounds/notification.mp3');
                            window.notificationSound.load();
                        }
                    }
                    
                    console.log(`Notificações sonoras ${data.habilitado ? 'habilitadas' : 'desabilitadas'} para este usuário`);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar preferência de som:', error);
            });
    }

    // Função para salvar a preferência de som do usuário
    function salvarPreferenciaSom(habilitado) {
        const formData = new FormData();
        formData.append('habilitado', habilitado ? 1 : 0);
        
        fetch('../actions/configuracoes/salvar_preferencia_som.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Preferência de som salva com sucesso');
            } else {
                console.error('Erro ao salvar preferência de som:', data.message);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
        });
    }

    // Função para iniciar monitoramento via SSE
    function iniciarSSEPedidos() {
        console.log("Iniciando monitoramento via SSE...");
        
        // Obter o último ID de pedido conhecido
        let ultimoIdPedido = 0;
        for (const status in pedidos) {
            for (const pedido of pedidos[status]) {
                ultimoIdPedido = Math.max(ultimoIdPedido, pedido.id);
            }
        }
        
        console.log("Último ID de pedido conhecido:", ultimoIdPedido);
        
        // Adicionar timestamp para evitar cache
        const timestamp = new Date().getTime();
        
        // Criar conexão SSE
        const source = new EventSource(`../actions/pedidos/sse_pedidos.php?ultimo_id=${ultimoIdPedido}&t=${timestamp}`);
        
        // Armazenar referência
        window.pedidosEventSource = source;
        
        // Evento quando a conexão é aberta
        source.onopen = function() {
            console.log("Conexão SSE aberta com sucesso!");
            
            // Mostrar indicador de conexão ativa
            mostrarStatusSSE(true);
            
            // Resetar contador de erros
            window.sseErrorCount = 0;
        };
        
        // Evento quando receber dados
        source.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                console.log("Evento SSE recebido:", data);
                
                // Processar diferentes tipos de eventos
                switch (data.tipo) {
                    case 'novos_pedidos':
                        if (data.quantidade > 0) {
                            console.log(`${data.quantidade} novos pedidos detectados!`);
                            
                            // Texto da notificação
                            const mensagem = `${data.quantidade} ${data.quantidade > 1 ? 'novos pedidos' : 'novo pedido'} recebido!`;
                            
                            // Mostrar notificação visual na página
                            mostrarNotificacao(mensagem);
                            
                            // Mostrar notificação do navegador
                            mostrarNotificacaoNavegador("Novos Pedidos", mensagem);
                            
                            // Reproduzir som de notificação usando a função atualizada
                            reproduzirSomNotificacao();
                            
                            // Recarregar pedidos
                            carregarPedidos();
                            
                            // Atualizar o último timestamp conhecido
                            window.ultimoTimestampPedido = data.ultimo_timestamp;
                            
                            // Reconectar imediatamente para pegar mais pedidos
                            source.close();
                            setTimeout(iniciarSSEPedidos, 500);
                        }
                        break;
                        
                    case 'timeout':
                        console.log("Timeout da conexão SSE, reconectando...");
                        // Fechar conexão atual
                        source.close();
                        // Reconectar após 1 segundo
                        setTimeout(iniciarSSEPedidos, 1000);
                        break;
                        
                    case 'erro':
                        console.error("Erro reportado pelo servidor:", data.mensagem);
                        break;
                        
                    case 'heartbeat':
                        // Heartbeat recebido, conexão está ativa
                        atualizarStatusHeartbeat();
                        break;
                }
            } catch (error) {
                console.error("Erro ao processar evento SSE:", error);
            }
        };
        
        // Tratamento de erros
        source.onerror = function(error) {
            console.error("Erro na conexão SSE:", error);
            
            // Mostrar indicador de conexão com erro
            mostrarStatusSSE(false);
            
            // Incrementar contador de erros
            if (!window.sseErrorCount) {
                window.sseErrorCount = 1;
            } else {
                window.sseErrorCount++;
            }
            
            // Fechar conexão
            source.close();
            
            // Determinar tempo de espera baseado no número de erros (backoff exponencial)
            let tempoReconexao = Math.min(1000 * Math.pow(1.5, window.sseErrorCount - 1), 10000);
            
            console.log(`Tentando reconectar em ${tempoReconexao/1000} segundos (tentativa ${window.sseErrorCount})`);
            
            // Tentar reconectar após o tempo calculado
            setTimeout(iniciarSSEPedidos, tempoReconexao);
        };
        
        // Retornar função para parar o SSE
        return function pararSSE() {
            if (window.pedidosEventSource) {
                window.pedidosEventSource.close();
                window.pedidosEventSource = null;
                
                // Remover indicador de status
                const statusElement = document.getElementById('statusSSE');
                if (statusElement) {
                    document.body.removeChild(statusElement);
                }
                
                console.log("Monitoramento SSE interrompido");
            }
        };
    }

    // Função para mostrar status da conexão SSE
    function mostrarStatusSSE(conectado) {
        let statusElement = document.getElementById('statusSSE');
        
        if (!statusElement) {
            statusElement = document.createElement('div');
            statusElement.id = 'statusSSE';
            statusElement.style.cssText = `
                position: fixed;
                bottom: 10px;
                left: 10px;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 12px;
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 5px;
            `;
            document.body.appendChild(statusElement);
        }
        
        if (conectado) {
            statusElement.innerHTML = '<span style="color: #28a745;">●</span> Monitoramento ativo';
            statusElement.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
            statusElement.style.border = '1px solid #28a745';
        } else {
            statusElement.innerHTML = '<span style="color: #dc3545;">●</span> Reconectando...';
            statusElement.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
            statusElement.style.border = '1px solid #dc3545';
        }
    }

    // Função para atualizar o indicador de heartbeat
    function atualizarStatusHeartbeat() {
        const statusElement = document.getElementById('statusSSE');
        if (statusElement) {
            // Piscar brevemente para indicar atividade
            statusElement.style.backgroundColor = 'rgba(40, 167, 69, 0.4)';
            setTimeout(() => {
                statusElement.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
            }, 300);
        }
    }

    // Função para adicionar botão de controle de monitoramento
    function adicionarBotaoControleMonitoramento() {
        // Verificar se o botão já existe
        if (document.getElementById('btnControleMonitoramento')) {
            return;
        }
        
        // Criar o botão
        const btn = document.createElement('button');
        btn.id = 'btnControleMonitoramento';
        btn.className = 'btn-controle-monitoramento';
        btn.innerHTML = '<i class="fas fa-pause"></i> Pausar monitoramento';
        
        // Estilizar o botão
        btn.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 15px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        `;
        
        // Adicionar evento de clique
        btn.addEventListener('click', function() {
            if (this.innerHTML.includes('Pausar')) {
                // Pausar monitoramento
                if (window.pararMonitoramento) {
                    window.pararMonitoramento();
                    window.pararMonitoramento = null;
                }
                
                this.innerHTML = '<i class="fas fa-play"></i> Retomar monitoramento';
                this.style.backgroundColor = '#dc3545';
            } else {
                // Retomar monitoramento
                window.pararMonitoramento = iniciarSSEPedidos();
                
                this.innerHTML = '<i class="fas fa-pause"></i> Pausar monitoramento';
                this.style.backgroundColor = '#17a2b8';
            }
        });
        
        // Adicionar ao DOM
        document.body.appendChild(btn);
        
        return btn;
    }

    // Função para mostrar notificação visual na página
    function mostrarNotificacao(mensagem) {
        // Verificar se já existe uma notificação
        const notificacaoExistente = document.querySelector('.notificacao-pedido');
        if (notificacaoExistente) {
            document.body.removeChild(notificacaoExistente);
        }
        
        // Criar elemento de notificação
        const notificacao = document.createElement('div');
        notificacao.className = 'notificacao-pedido';
        notificacao.innerHTML = `
            <i class="fas fa-bell"></i>
            <span>${mensagem}</span>
        `;
        
        // Adicionar estilo inline para garantir que funcione
        notificacao.style.cssText = `
            position: fixed;
            top: 20px;
            left: 20px;
            background-color: #28a745;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            align-items: center;
            gap: 10px;
            z-index: 9999;
            transition: right 0.5s ease;
        `;
        
        // Adicionar ao DOM
        document.body.appendChild(notificacao);
        
        // Animar entrada
        setTimeout(() => {
            notificacao.style.left = '20px';
        }, 100);
        
        // Remover após 5 segundos
        setTimeout(() => {
            notificacao.style.left = '0px';
            setTimeout(() => {
                if (notificacao.parentNode) {
                    document.body.removeChild(notificacao);
                }
            }, 500);
        }, 5000);
    }

    // Adicionar esta função para verificar periodicamente novos pedidos via AJAX
    function iniciarPollingFallback() {
        console.log("Iniciando fallback de polling para verificação de pedidos...");
        
        // Obter o último ID de pedido conhecido
        let ultimoIdPedido = 0;
        for (const status in pedidos) {
            for (const pedido of pedidos[status]) {
                ultimoIdPedido = Math.max(ultimoIdPedido, pedido.id);
            }
        }
        
        console.log("Último ID de pedido conhecido para polling:", ultimoIdPedido);
        
        // Função para verificar novos pedidos
        function verificarNovosPedidos() {
            // Adicionar timestamp para evitar cache
            const timestamp = new Date().getTime();
            
            fetch(`../actions/pedidos/verificar_novos_pedidos.php?ultimo_id=${ultimoIdPedido}&t=${timestamp}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.novos_pedidos) {
                        console.log(`${data.quantidade} novos pedidos detectados via polling!`);
                        
                        // Texto da notificação
                        const mensagem = `${data.quantidade} ${data.quantidade > 1 ? 'novos pedidos' : 'novo pedido'} recebido!`;
                        
                        // Mostrar notificação visual na página
                        mostrarNotificacao(mensagem);
                        
                        // Mostrar notificação do navegador
                        mostrarNotificacaoNavegador("Novos Pedidos", mensagem);
                        
                        // Tocar som de notificação
                        try {
                            if (document.documentElement.hasAttribute('data-user-interacted')) {
                                const audio = new Audio('../assets/sounds/notification.mp3');
                                audio.volume = 0.5;
                                audio.play().catch(err => {
                                    console.log('Não foi possível reproduzir o som:', err.message);
                                });
                            }
                        } catch (error) {
                            console.log('Erro ao tentar reproduzir som:', error);
                        }
                        
                        // Recarregar pedidos
                        carregarPedidos();
                        
                        // Atualizar o último ID conhecido
                        ultimoIdPedido = data.ultimo_id;
                    }
                })
                .catch(error => {
                    console.error("Erro na verificação de polling:", error);
                });
        }
        
        // Iniciar verificação periódica
        const intervalId = setInterval(verificarNovosPedidos, 10000); // 10 segundos
        
        // Armazenar o ID do intervalo
        window.pollingIntervalId = intervalId;
        
        // Mostrar indicador de polling
        mostrarStatusPolling();
        
        console.log("Fallback de polling iniciado com intervalo de 10 segundos");
        
        // Retornar função para parar o polling
        return function pararPolling() {
            if (window.pollingIntervalId) {
                clearInterval(window.pollingIntervalId);
                window.pollingIntervalId = null;
                
                // Remover indicador de status
                const statusElement = document.getElementById('statusPolling');
                if (statusElement) {
                    document.body.removeChild(statusElement);
                }
                
                console.log("Fallback de polling interrompido");
            }
        };
    }

    // Função para mostrar status do polling
    function mostrarStatusPolling() {
        let statusElement = document.getElementById('statusPolling');
        
        if (!statusElement) {
            statusElement = document.createElement('div');
            statusElement.id = 'statusPolling';
            statusElement.style.cssText = `
                position: fixed;
                bottom: 40px;
                right: 10px;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 12px;
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 5px;
                background-color: rgba(0, 123, 255, 0.2);
                border: 1px solid #007bff;
            `;
            statusElement.innerHTML = '<span style="color: #007bff;">●</span> Modo fallback: Polling ativo';
            document.body.appendChild(statusElement);
        }
    }

    // Adicionar esta função utilitária para formatar números de telefone
    function formatarTelefone(telefone) {
        // Remover todos os caracteres não numéricos
        const numeroLimpo = telefone.replace(/\D/g, '');
        
        // Verificar o tamanho do número para aplicar a formatação correta
        if (numeroLimpo.length === 11) {
            // Celular: (XX) XXXXX-XXXX
            return `(${numeroLimpo.substring(0, 2)}) ${numeroLimpo.substring(2, 7)}-${numeroLimpo.substring(7)}`;
        } else if (numeroLimpo.length === 10) {
            // Fixo: (XX) XXXX-XXXX
            return `(${numeroLimpo.substring(0, 2)}) ${numeroLimpo.substring(2, 6)}-${numeroLimpo.substring(6)}`;
        } else {
            // Se não for um formato reconhecido, retorna como está
            return telefone;
        }
    }
}); 