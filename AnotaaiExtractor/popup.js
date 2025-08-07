document.addEventListener('DOMContentLoaded', function() {
    // Definir a data padrão como hoje
    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('dataPedidos').value = hoje;

    const playButton = document.getElementById('playButton');
    const stopButton = document.getElementById('stopButton');
    const resetButton = document.getElementById('resetButton');
    
    playButton.addEventListener('click', async function() {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        chrome.tabs.sendMessage(tab.id, { action: 'START_AUTOMATION' });
        
        playButton.style.display = 'none';
        stopButton.style.display = 'block';
    });
    
    stopButton.addEventListener('click', async function() {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        chrome.tabs.sendMessage(tab.id, { action: 'STOP_AUTOMATION' });
        
        stopButton.style.display = 'none';
        playButton.style.display = 'block';
    });
    
    resetButton.addEventListener('click', async function() {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        chrome.tabs.sendMessage(tab.id, { action: 'RESET_AUTOMATION' });
        
        // Resetar interface
        stopButton.style.display = 'none';
        playButton.style.display = 'block';
        document.getElementById('numeroPedidos').textContent = '0';
    });
    
    // Listener para quando a automação terminar
    chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
        if (message.type === 'AUTOMATION_FINISHED') {
            stopButton.style.display = 'none';
            playButton.style.display = 'block';
        }
    });

    // Botão Exportar
    document.getElementById('exportar').addEventListener('click', async function() {
        const dataSelecionada = document.getElementById('dataPedidos').value;
        
        // Obter a aba ativa para poder se comunicar com o content script
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        
        chrome.storage.local.get(['pedidos'], async function(result) {
            if (result.pedidos && result.pedidos.length > 0) {
                // Atualizar a data dos pedidos antes de exportar
                const pedidosAtualizados = await new Promise((resolve) => {
                    chrome.tabs.sendMessage(tab.id, {
                        action: 'atualizarDataPedidos',
                        data: dataSelecionada
                    }, (response) => {
                        resolve(response.pedidos);
                    });
                });

                // Criar arquivo JSON para download
                const blob = new Blob([JSON.stringify(pedidosAtualizados, null, 2)], 
                    {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                
                // Criar link de download
                const a = document.createElement('a');
                a.href = url;
                a.download = `pedidos_anotaai_${dataSelecionada}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                exibirMensagem(`${pedidosAtualizados.length} pedidos exportados com sucesso!`);
            } else {
                exibirMensagem('Nenhum pedido para exportar.');
            }
        });
    });

    // Botão Limpar
    document.getElementById('limpar').addEventListener('click', function() {
        chrome.storage.local.clear(function() {
            atualizarContador();
            exibirMensagem('Dados limpos com sucesso!');
        });
    });

    // Função para atualizar o contador
    function atualizarContador() {
        chrome.storage.local.get(['pedidos'], function(result) {
            const numeroPedidos = result.pedidos ? result.pedidos.length : 0;
            document.getElementById('numeroPedidos').textContent = numeroPedidos;
        });
    }

    // Função para exibir ou ocultar a mensagem de status
    function exibirMensagem(mensagem) {
        const statusDiv = document.getElementById('status');
        statusDiv.textContent = mensagem;
        statusDiv.style.display = mensagem ? 'block' : 'none';
    }

    // Atualizar contador quando o popup é aberto
    atualizarContador();
}); 