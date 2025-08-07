<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
include_once '../config/database.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxo de Pedidos - Marmitaria Farias</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/fluxo_pedidos.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include_once '../includes/menu.php'; ?>

    <div class="main-content">
        <div class="fluxo-pedidos-container">
            <div class="fluxo-header-compact">
                <div class="fluxo-title-actions">
                <h1>Fluxo de Pedidos</h1>
                <div class="fluxo-actions-group">
                    <button id="btnReabrirLoja" class="btn-reabrir">Reabrir loja</button>
                    <button id="btnNovoPedido" class="btn-novo">Novo pedido</button>
                </div>
            </div>
            <div class="fluxo-extraidos">
                <span>Pedidos extraídos: <strong id="totalExtraidos">0</strong></span>
                <div class="extraidos-actions">
                    <button id="btnExportarPedidos" class="btn-exportar">Exportar pedidos</button>
                    <button id="btnLimparDados" class="btn-limpar">Limpar dados</button>
                </div>
            </div>
        </div>

        <div class="fluxo-filtros-compact">
            <div class="filtros-group">
                <button class="filtro-btn active" data-filtro="todos">Todos</button>
                <button class="filtro-btn" data-filtro="balcao">Balcão</button>
                <button class="filtro-btn" data-filtro="delivery">Delivery</button>
            </div>
            <div class="busca-group">
                <div class="busca-pedido">
                    <input type="text" id="numeroPedido" placeholder="Nº Pedido">
                    <button id="btnBuscarPedido"><i class="fas fa-search"></i></button>
                </div>
                <div class="busca-cliente">
                    <input type="text" id="buscarCliente" placeholder="Buscar pelo cliente">
                </div>
            </div>
        </div>

        <div class="fluxo-colunas">
            <div class="coluna em-analise">
                <div class="coluna-header">
                    <h2>Em análise</h2>
                    <span class="contador">0</span>
                </div>
                <div class="coluna-config-compact">
                    <div class="tempos-group">
                        <div class="tempo-estimado">
                            <span>Balcão: <strong>25 a 40 min</strong></span>
                            <button class="btn-editar" data-tipo="balcao"><i class="fas fa-edit"></i></button>
                        </div>
                        <div class="tempo-estimado">
                            <span>Delivery: <strong>35 a 55 min</strong></span>
                            <button class="btn-editar" data-tipo="delivery"><i class="fas fa-edit"></i></button>
                        </div>
                    </div>
                    <div class="aceite-automatico">
                        <label class="switch">
                            <input type="checkbox" id="aceitarAutomatico">
                            <span class="slider"></span>
                        </label>
                        <span>Aceitar os pedidos automaticamente</span>
                    </div>
                </div>
                <div class="pedidos-lista" id="pedidosEmAnalise">
                    <!-- Pedidos em análise serão carregados aqui -->
                    <div class="sem-pedidos">
                        <p>Nenhum pedido no momento.</p>
                        <p>Compartilhe os seus links nas redes sociais e receba pedidos!</p>
                    </div>
                </div>
            </div>

            <div class="coluna em-producao">
                <div class="coluna-header">
                    <h2>Em produção</h2>
                    <span class="contador">0</span>
                </div>
                <div class="pedidos-lista" id="pedidosEmProducao">
                    <!-- Pedidos em produção serão carregados aqui -->
                    <div class="sem-pedidos">
                        <p>Nenhum pedido no momento.</p>
                        <p>Receba pedidos e visualize os que estão em produção.</p>
                    </div>
                </div>
            </div>

            <div class="coluna prontos-entrega">
                <div class="coluna-header">
                    <h2>Prontos para entrega</h2>
                    <span class="contador">0</span>
                </div>
                <div class="pedidos-lista" id="pedidosProntosEntrega">
                    <!-- Pedidos prontos para entrega serão carregados aqui -->
                    <div class="sem-pedidos">
                        <p>Nenhum pedido no momento.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar tempos estimados -->
    <div id="modalTempoEstimado" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Editar tempo estimado</h2>
            <form id="formTempoEstimado">
                <input type="hidden" id="tipoTempo" name="tipo">
                <div class="form-group">
                    <label for="tempoMinimo">Tempo mínimo (minutos):</label>
                    <input type="number" id="tempoMinimo" name="minimo" min="5" max="120">
                </div>
                <div class="form-group">
                    <label for="tempoMaximo">Tempo máximo (minutos):</label>
                    <input type="number" id="tempoMaximo" name="maximo" min="5" max="120">
                </div>
                <button type="submit" class="btn-salvar">Salvar</button>
            </form>
        </div>
    </div>

    <!-- Template para cartão de pedido -->
    <template id="templatePedido">
        <div class="pedido-card" data-id="">
            <div class="pedido-header">
                <div class="pedido-numero">
                    <i class="fas fa-receipt"></i>
                    <span class="numero"></span>
                </div>
                <div class="pedido-data">
                    <i class="far fa-calendar-alt"></i>
                    <span class="data"></span>
                </div>
            </div>
            <div class="pedido-cliente">
                <div class="cliente-nome"></div>
                <div class="cliente-telefone"></div>
            </div>
            <div class="pedido-endereco"></div>
            <div class="pedido-itens"></div>
            <div class="pedido-valores">
                <div class="valor-total">Total: <span class="total"></span></div>
                <div class="forma-pagamento">Pagamento: <span class="pagamento"></span></div>
            </div>
            <div class="pedido-acoes">
                <button class="btn-avancar">Avançar</button>
                <button class="btn-finalizar">Finalizar</button>
                <button class="btn-imprimir"><i class="fas fa-print"></i></button>
                <button class="btn-cancelar"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </template>

    <!-- Modal de detalhes do pedido -->
    <div id="modalDetalhesPedido" class="modal modal-detalhes">
        <div class="modal-content modal-detalhes-content">
           
            
            <div class="modal-header">
            <span class="close">&times;</span>
                <div class="modal-titulo">
                    <div class="pedido-icon-number">
                        <i class="fas fa-receipt"></i>
                        <h2 id="modalPedidoNumero">Pedido #85</h2>
                    </div>
                    <span id="modalPedidoStatus" class="status-tag">Pronto para entrega</span>
                </div>
                <div class="modal-header-actions">
                    <div class="modal-data">
                        <i class="far fa-calendar-alt"></i>
                        <span id="modalPedidoData">13/03</span>
                    </div>
                    <div class="modal-header-buttons">
                        <button id="btnImprimirPedido" class="btn-imprimir-header">
                            <i class="fas fa-print"></i>
                        </button>
                        <button id="btnExcluirPedido" class="btn-excluir-header">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="modal-columns-container">
                <!-- Coluna da esquerda - Informações do cliente/pedido -->
                <div class="modal-left-column">
                    <div class="modal-body">
                        <div class="modal-section cliente-section">
                            <h3>Cliente</h3>
                            <div class="cliente-info">
                                <div class="cliente-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="cliente-dados">
                                    <div id="modalClienteNome" class="cliente-nome">Dilma Fogaça</div>
                                    <a id="modalClienteTelefone" href="tel:" class="cliente-telefone">(41) 9 9918-2965</a>
                                    <div id="modalClienteHistorico" class="cliente-historico">Já pediu <span class="pedidos-count">102</span> vezes em seu restaurante</div>
                                </div>
                                <button class="btn-editar-info">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="modal-section pedido-section">
                            <h3>Pedido agendado</h3>
                            <div class="pedido-agendado">
                                <i class="far fa-clock"></i>
                                <div id="modalPedidoAgendamento" class="agendamento-info">
                                    Entrega na qui. 13/03 entre 13:00 e 13:30
                                    <div class="pedido-gerado">Pedido gerado em 12/03</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-section entrega-section">
                            <h3>Entrega</h3>
                            <div class="entrega-info">
                                <i class="fas fa-motorcycle"></i>
                                <div id="modalEnderecoEntrega" class="endereco-entrega">
                                    Avenida Uirapuru, Nº 438, Jd. Claudia, Pinhais | Complemento: Casa | Região: Jd. Claudia
                                </div>
                            </div>
                            <a href="javascript:void(0)" class="escolher-entregador-link">Escolher entregador</a>
                        </div>
                        
                        <div class="modal-section pagamento-section">
                            <h3>Pagamento</h3>
                            <div class="pagamento-info">
                                <i class="far fa-credit-card"></i>
                                <div id="modalFormaPagamento" class="forma-pagamento">
                                    Cartão
                                    <div class="pagamento-detalhe">Crédito</div>
                                </div>
                                <button class="btn-editar-info">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="modal-section origem-section">
                            <h3>Origem do pedido</h3>
                            <div class="origem-info">
                                <i class="fas fa-link"></i>
                                <div id="modalOrigemPedido" class="origem-pedido">
                                    Pedido via link do cardápio
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Coluna da direita - Itens do pedido -->
                <div class="modal-right-column">
                    <div class="modal-items">
                        <h3>Itens do pedido</h3>
                        <div id="modalItensPedido" class="items-list">
                            <div class="item">
                                <div class="item-info">
                                    <div class="item-quantidade">1x</div>
                                    <div class="item-nome">Marmita P</div>
                                </div>
                                <div class="item-preco">R$ 16,00</div>
                            </div>
                            <div class="item-adicionais">
                                <div class="adicional">
                                    <span class="adicional-nome">Feijão</span>
                                    <span class="adicional-opcao">- Branco</span>
                                </div>
                                <div class="adicional">
                                    <span class="adicional-nome">Monte sua Marmita</span>
                                </div>
                                <div class="adicional-opcoes">
                                    <span class="adicional-opcao">- Arroz</span>
                                </div>
                                <div class="adicional-opcoes">
                                    <span class="adicional-opcao">- Farofa de Bacon</span>
                                </div>
                                <div class="adicional-opcoes">
                                    <span class="adicional-opcao">- Macarrão a Bolonhesa</span>
                                </div>
                                <div class="adicional">
                                    <span class="adicional-nome">Carne</span>
                                    <span class="adicional-opcao">- Filé de Peixe Empanado</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="resumo-valores">
                            <div class="valor-linha">
                                <span class="valor-descricao">Subtotal</span>
                                <span id="modalSubtotal" class="valor-numero">R$ 16,00</span>
                            </div>
                            <div class="valor-linha">
                                <span class="valor-descricao">Taxa de Entrega</span>
                                <span id="modalTaxaEntrega" class="valor-numero">R$ 3,00</span>
                            </div>
                            <div class="valor-linha total">
                                <span class="valor-descricao">Total</span>
                                <span id="modalTotal" class="valor-numero">R$ 19,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <div class="acoes-pedido">
                    <button id="btnFinalizarPedido" class="btn-finalizar-modal">
                        Finalizar pedido
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/fluxo_pedidos.js"></script>
</body>
</html> 