        document.addEventListener('DOMContentLoaded', function() {
            // Elementos do formulário
            const form = document.getElementById('enqueteForm');
            const cards = document.querySelectorAll('.lembrete-card');
            const radioGroups = {
                'almoco_perfeito': document.querySelectorAll('input[name="almoco_perfeito"]'),
                'repetir_pedido': document.querySelectorAll('input[name="repetir_pedido"]'),
                'mudar_experiencia': document.querySelectorAll('input[name="mudar_experiencia"]')
            };
            const textareas = document.querySelectorAll('textarea');
            const progressDots = document.querySelectorAll('.progress-dot');
            const preSubmitBtn = document.getElementById('pre-submit-btn');
            const submitBtn = document.getElementById('submitBtn');
            const body = document.querySelector('body');
            
            let currentStep = 1;
            
            // Variáveis para controle de tempo mínimo
            let cardActivationTimes = {1: Date.now()}; // O primeiro card já está ativo no carregamento
            const TEMPO_MINIMO_LEITURA = 3000; // 3 segundos em milissegundos
            
            // Ajustar o requisito mínimo para a resposta de texto
            const CARACTERES_MINIMOS = 15; // Ajustado para 15 caracteres
            
            // Mensagem informativa sobre o requisito mínimo de caracteres
            // Adicionar ao HTML do textarea
            textareas.forEach(textarea => {
                // Criar contador de caracteres e adicioná-lo após o textarea
                const contadorContainer = document.createElement('div');
                contadorContainer.classList.add('contador-caracteres');
                contadorContainer.style.cssText = `
                    text-align: right;
                    font-size: 0.8rem;
                    color: #777;
                    margin-top: 5px;
                    transition: color 0.3s;
                `;
                
                const contadorSpan = document.createElement('span');
                contadorSpan.id = 'contador-' + textarea.name;
                contadorSpan.textContent = '0';
                
                contadorContainer.innerHTML = `
                    <span id="contador-${textarea.name}">0</span>/${CARACTERES_MINIMOS} caracteres mínimos
                `;
                
                // Inserir após o textarea
                textarea.parentNode.insertBefore(contadorContainer, textarea.nextSibling);
                
                // Atualizar placeholder do textarea
                textarea.placeholder = `Conte-nos o que você gostaria (mínimo de ${CARACTERES_MINIMOS} caracteres)...`;
                
                // Remover evento anterior e adicionar novo
                let typingTimer;
                textarea.addEventListener('input', function() {
                    const cardStep = parseInt(this.closest('.lembrete-card').dataset.step);
                    clearTimeout(typingTimer);
                    
                    // Atualizar contador de caracteres
                    const caracteresDigitados = this.value.trim().length;
                    const contador = document.getElementById('contador-' + this.name);
                    if (contador) {
                        contador.textContent = caracteresDigitados;
                        
                        // Mudar cor com base na quantidade de caracteres
                        const contadorParent = contador.parentNode;
                        if (caracteresDigitados < CARACTERES_MINIMOS) {
                            contadorParent.style.color = caracteresDigitados === 0 ? '#777' : '#e74c3c';
                        } else {
                            contadorParent.style.color = '#4cd27d';
                        }
                    }
                    
                    if (cardStep === currentStep) {
                        typingTimer = setTimeout(() => {
                            // AUMENTADO: Verificar se tem pelo menos 20 caracteres
                            if (this.value.trim().length >= CARACTERES_MINIMOS) {
                                // Verificar se passou tempo mínimo
                                if (!verificarTempoMinimo(cardStep, null)) {
                                    return; // Impede a continuação se não passou tempo suficiente
                                }
                                
                                // Bloquear scroll durante a transição
                                blockScroll();
                                
                                // Rolagem para o botão
                                preSubmitBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                
                                // Habilitação do botão só após a rolagem
                                setTimeout(() => {
                                    // Habilitar o botão
                                    preSubmitBtn.disabled = false;
                                    
                                    // Atualizar a barra de progresso
                                    progressDots.forEach(dot => {
                                        if (parseInt(dot.dataset.step) <= currentStep) {
                                            dot.classList.add('active');
                                        }
                                    });
                                    
                                    // Desbloquear o scroll
                                    unblockScroll();
                                }, 1000);
                            } else {
                                preSubmitBtn.disabled = true;
                                
                                // Se o texto tiver algum conteúdo mas for insuficiente, mostrar dica
                                if (this.value.trim().length > 0 && this.value.trim().length < CARACTERES_MINIMOS) {
                                    // Verificar se já existe uma dica
                                    if (!document.getElementById('dica-texto-minimo')) {
                                        const dica = document.createElement('div');
                                        dica.id = 'dica-texto-minimo';
                                        dica.style.cssText = `
                                            color: #e74c3c;
                                            font-size: 0.85rem;
                                            margin-top: 5px;
                                            animation: fadeIn 0.3s ease-out forwards;
                                        `;
                                        dica.innerHTML = `
                                            <i class="fas fa-info-circle"></i> 
                                            Por favor, escreva pelo menos ${CARACTERES_MINIMOS} caracteres para continuar.
                                        `;
                                        contadorContainer.parentNode.insertBefore(dica, contadorContainer.nextSibling);
                                    }
                                } else {
                                    // Remover a dica se existir e o campo estiver vazio
                                    const dica = document.getElementById('dica-texto-minimo');
                                    if (dica) {
                                        dica.parentNode.removeChild(dica);
                                    }
                                }
                            }
                        }, 500); // Tempo reduzido para atualizar mais rapidamente
                    }
                });
            });
            
            // Função para bloquear o scroll
            function blockScroll() {
                body.classList.add('scroll-blocked');
                
                // Marcar o card atual para manter seus eventos
                const currentCard = document.querySelector(`.lembrete-card[data-step="${currentStep}"]`);
                if (currentCard) {
                    currentCard.classList.add('current-card');
                }
            }
            
            // Função para desbloquear o scroll
            function unblockScroll() {
                body.classList.remove('scroll-blocked');
                
                // Remover a marcação de todos os cards
                cards.forEach(card => {
                    card.classList.remove('current-card');
                });
            }
            
            // Função otimizada para mostrar alerta de tempo mínimo com contagem regressiva
            function mostrarAlertaTempoMinimo(segundosRestantes, cardElement) {
                // Remover qualquer alerta existente
                const alertaExistente = document.getElementById('alerta-tempo-minimo');
                if (alertaExistente && alertaExistente.parentNode) {
                    alertaExistente.parentNode.removeChild(alertaExistente);
                }
                
                // Criar elemento de alerta
                const alertaEl = document.createElement('div');
                alertaEl.id = 'alerta-tempo-minimo';
                alertaEl.style.cssText = `
                    margin: 10px auto 5px;
                    background-color: #fff3cd;
                    color: #856404;
                    border-left: 4px solid #ffd600;
                    border-radius: 4px;
                    padding: 8px 12px;
                    text-align: center;
                    font-size: 0.9rem;
                    max-width: 100%;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    animation: fadeIn 0.3s ease-out forwards;
                `;
                
                // Elemento para o contador
                const contadorEl = document.createElement('span');
                contadorEl.id = 'contador-tempo';
                contadorEl.textContent = segundosRestantes;
                
                // Conteúdo com contador dinâmico
                alertaEl.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-clock" style="margin-right: 8px; font-size: 0.9rem;"></i>
                        Aguarde <span id="contador-tempo" style="font-weight: bold; margin: 0 4px;">${segundosRestantes}</span>s para responder...
                    </div>
                `;
                
                // Adicionar CSS de animação se não existir
                if (!document.getElementById('alerta-animations')) {
                    const style = document.createElement('style');
                    style.id = 'alerta-animations';
                    style.textContent = `
                        @keyframes fadeIn {
                            from { opacity: 0; transform: translateY(-10px); }
                            to { opacity: 1; transform: translateY(0); }
                        }
                        @keyframes pulseCount {
                            0% { transform: scale(1); }
                            50% { transform: scale(1.2); }
                            100% { transform: scale(1); }
                        }
                    `;
                    document.head.appendChild(style);
                }
                
                // Inserir o alerta no início do conteúdo do card
                const cardContent = cardElement.querySelector('.lembrete-content');
                if (cardContent) {
                    cardContent.insertBefore(alertaEl, cardContent.firstChild);
                }
                
                // Iniciar a contagem regressiva
                let tempoAtual = segundosRestantes;
                const intervalo = setInterval(() => {
                    tempoAtual--;
                    
                    // Atualizar o texto do contador
                    const contador = document.getElementById('contador-tempo');
                    if (contador) {
                        contador.textContent = tempoAtual;
                        contador.style.animation = 'pulseCount 0.5s ease-in-out';
                        
                        // Remover a animação após ela terminar
                        setTimeout(() => {
                            contador.style.animation = '';
                        }, 500);
                    }
                    
                    // Quando chegar a zero, limpar o intervalo e remover o alerta
                    if (tempoAtual <= 0) {
                        clearInterval(intervalo);
                        
                        if (alertaEl && alertaEl.parentNode) {
                            alertaEl.style.opacity = '0';
                            alertaEl.style.transition = 'opacity 0.3s';
                            
                            setTimeout(() => {
                                if (alertaEl && alertaEl.parentNode) {
                                    alertaEl.parentNode.removeChild(alertaEl);
                                }
                            }, 300);
                        }
                    }
                }, 1000); // Atualizar a cada segundo
                
                // Garantir que o intervalo seja limpo se o alerta for removido prematuramente
                alertaEl.setAttribute('data-interval-id', intervalo);
                
                // Retornar o ID do intervalo para possível limpeza posterior
                return intervalo;
            }
            
            // Função para verificar se o cliente está respondendo muito rapidamente - AJUSTADA
            function verificarTempoMinimo(cardStep, input) {
                const tempoAtivacao = cardActivationTimes[cardStep] || 0;
                const tempoAgora = Date.now();
                const tempoDecorrido = tempoAgora - tempoAtivacao;
                
                // Se o tempo decorrido for menor que o mínimo, mostrar alerta
                if (tempoDecorrido < TEMPO_MINIMO_LEITURA) {
                    // Calcular quanto tempo ainda falta em segundos (arredondado para cima)
                    const tempoRestante = Math.ceil((TEMPO_MINIMO_LEITURA - tempoDecorrido) / 1000);
                    
                    // CORREÇÃO: Desmarcar o input que foi clicado muito rápido
                    if (input && input.type === 'radio') {
                        input.checked = false;
                    }
                    
                    // Obter o card atual para posicionar o alerta dentro dele
                    const cardAtual = document.querySelector(`.lembrete-card[data-step="${cardStep}"]`);
                    
                    // Criar um alerta compacto dentro do card com contagem regressiva
                    mostrarAlertaTempoMinimo(tempoRestante, cardAtual);
                    
                    // Retornar false para impedir a continuação
                    return false;
                }
                
                // Se passou tempo suficiente, permitir a continuação
                return true;
            }
            
            // Modificar a função para buscar resultados, incluindo a opção selecionada
            async function buscarResultadosVotacao(grupo, opcaoSelecionada) {
                try {
                    const formData = new FormData();
                    formData.append('pergunta', grupo);
                    if (opcaoSelecionada) {
                        formData.append('opcao', opcaoSelecionada);
                    }
                    
                    const response = await fetch('buscar_resultados.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error('Erro ao buscar resultados');
                    }
                    
                    const data = await response.json();
                    console.log(`Resultados para ${grupo} (opção: ${opcaoSelecionada}):`, data);
                    return data;
                } catch (error) {
                    console.error('Erro ao buscar resultados:', error);
                    // Retornar valores padrão em caso de erro
                    const padrao = {};
                    
                    // Obter todas as opções disponíveis para a pergunta
                    const opcoes = document.querySelectorAll(`input[name="${grupo}"]`);
                    
                    // Se temos uma opção selecionada, vamos dar 100% para ela
                    if (opcaoSelecionada) {
                        opcoes.forEach(opcao => {
                            padrao[opcao.value] = opcao.value === opcaoSelecionada ? 100 : 0;
                        });
                    } else {
                        // Distribuir igualmente se não temos opção selecionada
                        const valorPadrao = Math.floor(100 / opcoes.length);
                        opcoes.forEach(opcao => {
                            padrao[opcao.value] = valorPadrao;
                        });
                        
                        // Ajustar para garantir soma 100%
                        if (opcoes.length > 0) {
                            padrao[opcoes[0].value] += 100 - (valorPadrao * opcoes.length);
                        }
                    }
                    
                    return padrao;
                }
            }
            
            // Modificar a função mostrarResultados para enviar a opção selecionada
            async function mostrarResultados(grupo) {
                // Bloquear o scroll imediatamente após a escolha
                blockScroll();
                
                // Encontrar qual opção foi selecionada
                let respostaSelecionada = '';
                const opcoes = document.querySelectorAll(`input[name="${grupo}"]`);
                
                opcoes.forEach(opcao => {
                    if (opcao.checked) {
                        respostaSelecionada = opcao.value;
                    }
                });
                
                // Buscar resultados do banco de dados, incluindo a opção selecionada
                const resultados = await buscarResultadosVotacao(grupo, respostaSelecionada);
                
                // Para cada opção, mostrar sua barra de resultado
                opcoes.forEach(opcao => {
                    const valor = opcao.value;
                    const porcentagem = resultados[valor] !== undefined ? resultados[valor] : 0;
                    const optionItem = opcao.closest('.option-item');
                    const barra = optionItem.querySelector('.barra-resultado');
                    const porcentagemEl = optionItem.querySelector('.porcentagem-resultado');
                    
                    // Adicionar classe para ajustar aparência
                    optionItem.classList.add('resultado-exibido');
                    
                    // Marcar a opção selecionada com destaque
                    if (valor === respostaSelecionada) {
                        optionItem.classList.add('selecionado');
                    }
                    
                    // Animar a largura da barra
                    if (barra) {
                        barra.style.width = porcentagem + '%';
                    }
                    
                    // Atualizar o texto da porcentagem
                    if (porcentagemEl) {
                        porcentagemEl.textContent = porcentagem + '%';
                    }
                });
                
                // Desativar os radios para prevenir mudanças após ver os resultados
                opcoes.forEach(opcao => {
                    opcao.disabled = true;
                });
                
                // Resto da função permanece igual...
                setTimeout(() => {
                    const nextCardIndex = currentStep + 1;
                    const nextCard = document.querySelector(`.lembrete-card[data-step="${nextCardIndex}"]`);
                    
                    if (nextCard) {
                        nextCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        setTimeout(() => {
                            activateNextCard();
                            unblockScroll();
                        }, 1000);
                    } else if (currentStep >= cards.length) {
                        submitBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            unblockScroll();
                        }, 1000);
                    }
                }, 2000);
            }
            
            // Função modificada para ativar o próximo card
            function activateNextCard() {
                // Atualizar o indicador de progresso
                progressDots.forEach(dot => {
                    if (parseInt(dot.dataset.step) <= currentStep + 1) { // +1 porque agora ativamos depois
                        dot.classList.add('active');
                    }
                });
                
                // Ativar o próximo card
                if (currentStep < cards.length) {
                    const nextCard = document.querySelector(`.lembrete-card[data-step="${currentStep + 1}"]`);
                    if (nextCard) {
                        nextCard.classList.remove('disabled');
                        const inputs = nextCard.querySelectorAll('input, textarea');
                        inputs.forEach(input => input.disabled = false);
                        
                        // NOVO: Registrar o momento de ativação do próximo card
                        cardActivationTimes[currentStep + 1] = Date.now();
                    }
                    currentStep++;
                }
            }
            
            // Atualizar os event listeners para os radios (deixando o async)
            Object.keys(radioGroups).forEach(grupo => {
                radioGroups[grupo].forEach(radio => {
                    radio.removeEventListener('change', null);
                    
                    radio.addEventListener('click', async function(event) {
                        const cardStep = parseInt(this.closest('.lembrete-card').dataset.step);
                        
                        if (cardStep === currentStep) {
                            if (!verificarTempoMinimo(cardStep, this)) {
                                event.preventDefault();
                                return false;
                            }
                            
                            await mostrarResultados(this.name);
                        }
                    });
                });
            });
            
            // Modifique os listeners dos textareas para incluir a verificação de tempo
            textareas.forEach(textarea => {
                let typingTimer;
                textarea.addEventListener('input', function() {
                    const cardStep = parseInt(this.closest('.lembrete-card').dataset.step);
                    clearTimeout(typingTimer);
                    
                    if (cardStep === currentStep) {
                        typingTimer = setTimeout(() => {
                            if (this.value.trim().length >= 5) {
                                // Verificar se passou tempo mínimo
                                if (!verificarTempoMinimo(cardStep, this)) {
                                    return; // Impede a continuação se não passou tempo suficiente
                                }
                                
                                // Bloquear scroll durante a transição
                                blockScroll();
                                
                                // Rolagem para o botão
                                preSubmitBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                
                                // Habilitação do botão só após a rolagem
                                setTimeout(() => {
                                    // CORRIGIDO: Habilitação do botão
                                    preSubmitBtn.disabled = false;
                                    
                                    // Atualizar a barra de progresso
                                    progressDots.forEach(dot => {
                                        if (parseInt(dot.dataset.step) <= currentStep) {
                                            dot.classList.add('active');
                                        }
                                    });
                                    
                                    // Desbloquear o scroll
                                    unblockScroll();
                                }, 1000);
                            } else {
                                preSubmitBtn.disabled = true;
                            }
                        }, 800);
                    }
                });
            });
            
            // Desabilitar todos os inputs dos cards inativos inicialmente
            cards.forEach(card => {
                if (parseInt(card.dataset.step) > 1) {
                    const inputs = card.querySelectorAll('input, textarea');
                    inputs.forEach(input => input.disabled = true);
                }
            });
            
            // CORRIGIDO: Modal de confirmação
            const confirmacaoModal = document.getElementById('confirmacao-modal');
            const resumoRespostas = document.getElementById('respostas-resumo');
            const responderNovamente = document.getElementById('responder-novamente');
            const confirmarRespostas = document.getElementById('confirmar-respostas');
            
            // Função revisada para obter apenas o texto da opção, sem a porcentagem
            function getTextoOpcao(nome, valor) {
                const input = document.querySelector(`input[name="${nome}"][value="${valor}"]`);
                if (input) {
                    const label = input.closest('label');
                    if (label) {
                        const textoSpan = label.querySelector('.option-text');
                        if (textoSpan) {
                            // Obter o texto da opção sem modificações
                            return textoSpan.textContent.trim();
                        }
                    }
                    
                    // Se não encontrar o span específico, tenta pegar o texto do label
                    const labelEl = document.querySelector(`label[for="${input.id}"]`);
                    if (labelEl) {
                        // Remover o texto da letra (A, B, C, D)
                        const textoCompleto = labelEl.textContent.trim();
                        
                        // Remover qualquer texto de porcentagem (como "45%")
                        return textoCompleto.replace(/^[A-D]\s+/, '').replace(/\s+\d+%$/, '');
                    }
                }
                return valor; // Fallback para o valor bruto
            }
            
            // Dicionário para nomes das perguntas
            const nomesPergunta = {
                'almoco_perfeito': 'O que faz seu almoço perfeito?',
                'repetir_pedido': 'O que faz você repetir o pedido?',
                'mudar_experiencia': 'O que você mudaria na experiência?',
                'novidade_cardapio': 'Novidade que gostaria no cardápio?'
            };
            
            // CORRIGIDO: Substituir o comportamento do botão de submit sem mostrar porcentagens
            preSubmitBtn.addEventListener('click', function() {
                // Certificar-se de que qualquer bloqueio anterior seja removido
                unblockScroll();
                
                // Bloqueio específico para o modal
                document.body.classList.add('modal-open');
                
                // Mostrar o modal de confirmação
                confirmacaoModal.style.display = 'flex';
                
                // Coletar todas as respostas para exibir
                let htmlResumo = '';
                
                // Perguntas de múltipla escolha - CORRIGIDO para não mostrar porcentagem
                const gruposRespostas = ['almoco_perfeito', 'repetir_pedido', 'mudar_experiencia'];
                gruposRespostas.forEach(grupo => {
                    const selecionado = document.querySelector(`input[name="${grupo}"]:checked`);
                    if (selecionado) {
                        const textoOpcao = getTextoOpcao(grupo, selecionado.value);
                        htmlResumo += `<div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <p style="font-weight: bold; color: var(--secondary-color); margin-bottom: 5px; font-size: 0.9rem;">
                                ${nomesPergunta[grupo]}
                            </p>
                            <p style="margin-left: 10px;">
                                <i class="fas fa-check-circle" style="color: var(--success-color); margin-right: 5px;"></i>
                                ${textoOpcao}
                            </p>
                        </div>`;
                    }
                });
                
                // Texto de resposta livre
                const textoResposta = document.querySelector('textarea[name="novidade_cardapio"]').value.trim();
                if (textoResposta) {
                    htmlResumo += `<div style="margin-bottom: 15px;">
                        <p style="font-weight: bold; color: var(--secondary-color); margin-bottom: 5px; font-size: 0.9rem;">
                            ${nomesPergunta['novidade_cardapio']}
                        </p>
                        <p style="margin-left: 10px; font-style: italic;">
                            "${textoResposta}"
                        </p>
                    </div>`;
                }
                
                // Adicionar ao resumo
                resumoRespostas.innerHTML = htmlResumo;
            });
            
            // Função para resetar a enquete completamente - CORRIGIDA
            function resetarEnquete() {
                console.log("Iniciando reset da enquete..."); // Debugging
                
                // 1. Fechar o modal
                confirmacaoModal.style.display = 'none';
                
                // 2. Remover bloqueio de scroll
                document.body.classList.remove('modal-open');
                document.body.classList.remove('scroll-blocked');
                
                // 3. Resetar o controle de passo atual
                currentStep = 1;
                
                // 4. Desmarcar todas as opções selecionadas e limpar resultados
                Object.keys(radioGroups).forEach(grupo => {
                    radioGroups[grupo].forEach(radio => {
                        // Desmarcar o rádio
                        radio.checked = false;
                        
                        // Obter o item de opção e resetar suas classes
                        const optionItem = radio.closest('.option-item');
                        if (optionItem) {
                            optionItem.classList.remove('resultado-exibido', 'selecionado');
                            
                            // Resetar a barra de resultado
                            const barra = optionItem.querySelector('.barra-resultado');
                            if (barra) {
                                barra.style.width = '0';
                            }
                            
                            // Resetar a visibilidade da porcentagem
                            const porcentagem = optionItem.querySelector('.porcentagem-resultado');
                            if (porcentagem) {
                                porcentagem.style.opacity = '0';
                            }
                        }
                    });
                });
                
                // 5. Limpar o campo de texto
                const textArea = document.querySelector('textarea[name="novidade_cardapio"]');
                if (textArea) {
                    textArea.value = '';
                }
                
                // 6. Processar cada card individualmente
                cards.forEach(card => {
                    const cardStep = parseInt(card.dataset.step);
                    
                    if (cardStep === 1) {
                        // Primeiro card: ativar e habilitar seus inputs
                        card.classList.remove('disabled');
                        const inputs = card.querySelectorAll('input, textarea');
                        inputs.forEach(input => {
                            input.disabled = false;
                        });
                    } else {
                        // Outros cards: desativar e desabilitar seus inputs
                        card.classList.add('disabled');
                        const inputs = card.querySelectorAll('input, textarea');
                        inputs.forEach(input => {
                            input.disabled = true;
                        });
                    }
                });
                
                // 7. Resetar os indicadores de progresso
                progressDots.forEach(dot => {
                    const step = parseInt(dot.dataset.step);
                    if (step === 1) {
                        dot.classList.add('active');
                    } else {
                        dot.classList.remove('active');
                    }
                });
                
                // 8. Desabilitar o botão de submit
                preSubmitBtn.disabled = true;
                
                // 9. Rolar para o primeiro card com um pequeno delay
                setTimeout(() => {
                    const primeiroCard = document.querySelector(`.lembrete-card[data-step="1"]`);
                    if (primeiroCard) {
                        primeiroCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        console.log("Rolando para o primeiro card..."); // Debugging
                    }
                }, 200);
                
                // Resetar os tempos de ativação dos cards
                cardActivationTimes = {1: Date.now()}; // Registrar o tempo atual para o primeiro card
                
                // Limpar qualquer intervalo de contagem regressiva
                const alertaExistente = document.getElementById('alerta-tempo-minimo');
                if (alertaExistente) {
                    const intervaloID = parseInt(alertaExistente.getAttribute('data-interval-id'));
                    if (!isNaN(intervaloID)) {
                        clearInterval(intervaloID);
                    }
                    if (alertaExistente.parentNode) {
                        alertaExistente.parentNode.removeChild(alertaExistente);
                    }
                }
                
                console.log("Reset da enquete concluído!"); // Debugging
            }
            
            // Evento para o botão de responder novamente
            responderNovamente.addEventListener('click', function() {
                console.log("Botão Responder Novamente clicado"); // Debugging
                resetarEnquete();
            });
            
            // Modificar confirmação para registrar a resposta no banco
            confirmarRespostas.addEventListener('click', async function() {
                // Remover bloqueios antes de enviar
                document.body.classList.remove('modal-open');
                
                // Verificar novamente se todas as perguntas obrigatórias foram respondidas
                const almocoPerfeitoSelecionado = document.querySelector('input[name="almoco_perfeito"]:checked');
                const repetirPedidoSelecionado = document.querySelector('input[name="repetir_pedido"]:checked');
                const mudarExperienciaSelecionado = document.querySelector('input[name="mudar_experiencia"]:checked');
                const textareaResposta = document.querySelector('textarea[name="novidade_cardapio"]');
                
                // Log das escolhas para depuração
                console.log("Almoço perfeito:", almocoPerfeitoSelecionado ? almocoPerfeitoSelecionado.value : "não selecionado");
                console.log("Repetir pedido:", repetirPedidoSelecionado ? repetirPedidoSelecionado.value : "não selecionado");
                console.log("Mudar experiência:", mudarExperienciaSelecionado ? mudarExperienciaSelecionado.value : "não selecionado");
                
                // Inserir campos ocultos no formulário para garantir que os valores sejam enviados
                if (almocoPerfeitoSelecionado) {
                    const hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = 'almoco_perfeito';
                    hiddenField.value = almocoPerfeitoSelecionado.value;
                    document.getElementById('enqueteForm').appendChild(hiddenField);
                }
                
                if (repetirPedidoSelecionado) {
                    const hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = 'repetir_pedido';
                    hiddenField.value = repetirPedidoSelecionado.value;
                    document.getElementById('enqueteForm').appendChild(hiddenField);
                }
                
                if (mudarExperienciaSelecionado) {
                    const hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = 'mudar_experiencia';
                    hiddenField.value = mudarExperienciaSelecionado.value;
                    document.getElementById('enqueteForm').appendChild(hiddenField);
                }
                
                if (textareaResposta) {
                    const hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = 'novidade_cardapio';
                    hiddenField.value = textareaResposta.value.trim();
                    document.getElementById('enqueteForm').appendChild(hiddenField);
                }
                
                // Clica no botão real de submit
                submitBtn.click();
            });
            
            // Adicionar estilos específicos para o modal
            const style = document.createElement('style');
            style.textContent = `
                body.modal-open {
                    overflow: hidden;
                }
                
                #confirmacao-modal {
                    overflow-y: auto;
                }
                
                #confirmacao-modal > div {
                    margin: 30px auto;
                }
            `;
            document.head.appendChild(style);
        });