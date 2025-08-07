chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    console.log('Mensagem recebida no background:', message); // Debug
    
    if (message.type === 'SHOW_NOTIFICATION') {
        chrome.notifications.create({
            type: 'basic',
            iconUrl: chrome.runtime.getURL('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
            title: message.title,
            message: message.message,
            priority: 2
        }, (notificationId) => {
            console.log('Notificação criada:', notificationId); // Debug
            if (chrome.runtime.lastError) {
                console.error('Erro ao criar notificação:', chrome.runtime.lastError);
            }
        });
    }
    
    // Importante: retornar true se você planeja usar sendResponse assincronamente
    return true;
});

// Log quando o background script é carregado
console.log('Background script carregado!'); 