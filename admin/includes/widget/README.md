# Widget DatePicker - Uso Autônomo

Este widget de seleção de datas agora é completamente autônomo e pode ser usado em qualquer página sem configuração adicional.

## 🚀 Como Usar

### 1. Incluir os Arquivos

```html
<!-- CSS do Widget -->
<link rel="stylesheet" href="caminho/para/widget/styles.css">

<!-- HTML do Widget -->
<div class="datepicker-container">
    <div class="input-wrapper">
        <input type="text" class="date-input" value="" readonly>
        <svg class="calendar-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
    </div>
    
    <!-- Calendário -->
    <div class="calendar-dropdown">
        <!-- Cabeçalho do calendário -->
        <div class="calendar-header">
            <button class="month-year">
                <span>Mês 2025</span>
            </button>
            <div class="nav-buttons">
                <button class="nav-btn" title="Mês anterior">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </span>
                </button>
                <button class="nav-btn" title="Próximo mês">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </span>
                </button>
            </div>
        </div>
        
        <!-- Grid do calendário -->
        <div class="calendar-grid">
            <!-- Cabeçalhos dos dias da semana -->
            <span class="day-header">Dom</span>
            <span class="day-header">Seg</span>
            <span class="day-header">Ter</span>
            <span class="day-header">Qua</span>
            <span class="day-header">Qui</span>
            <span class="day-header">Sex</span>
            <span class="day-header">Sab</span>
            
            <!-- Os dias serão gerados dinamicamente pelo JavaScript -->
        </div>
        
        <!-- Botões de ação -->
        <div class="action-buttons">
            <button class="btn btn-clear">Limpar</button>
            <button class="btn btn-apply">Aplicar</button>
        </div>
    </div>
</div>

<!-- JavaScript do Widget -->
<script src="caminho/para/widget/script.js"></script>
```

### 2. Configuração Automática

O widget se configura automaticamente baseado nos parâmetros da URL:

- **Data única:** `?data=2025-08-01`
- **Range de datas:** `?data_inicio=2025-08-01&data_fim=2025-08-06`

### 3. Eventos Disponíveis

```javascript
// Escutar seleção de data única
window.addEventListener('dateSelected', (event) => {
    console.log('Data selecionada:', event.detail);
    // event.detail.dateObject - Objeto Date
    // event.detail.date - String formatada
});

// Escutar seleção de range
window.addEventListener('dateRangeSelected', (event) => {
    console.log('Range selecionado:', event.detail);
    // event.detail.startDate - Data de início
    // event.detail.endDate - Data de fim
});
```

### 4. Múltiplos Widgets

Você pode ter múltiplos widgets na mesma página:

```html
<!-- Widget 1 -->
<div class="datepicker-container">
    <!-- ... HTML do widget ... -->
</div>

<!-- Widget 2 -->
<div class="datepicker-container">
    <!-- ... HTML do widget ... -->
</div>
```

Cada widget funcionará independentemente.

### 5. Acesso Programático

```javascript
// Obter todas as instâncias
const instances = window.datePickerWidget.getAllInstances();

// Obter instância específica
const firstInstance = window.datePickerWidget.getInstance(0);

// Obter datas selecionadas
const selectedDates = firstInstance.getSelectedDates();
```

## 🎨 Personalização

### CSS Customizado

```css
/* Personalizar cores */
.datepicker-container .date-input {
    background: #f0f0f0;
    border-color: #333;
}

/* Personalizar posicionamento */
.datepicker-container .calendar-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1000;
}
```

### Configuração Inicial

```javascript
// Configurar data inicial programaticamente
document.addEventListener('DOMContentLoaded', () => {
    const instance = window.datePickerWidget.getInstance(0);
    if (instance) {
        const startDate = new Date(2025, 7, 1); // 1º de agosto de 2025
        const endDate = new Date(2025, 7, 6);   // 6º de agosto de 2025
        instance.startDate = startDate;
        instance.endDate = endDate;
        instance.updateCalendarHighlight();
        instance.updateDateInput();
    }
});
```

## 📋 Exemplo Completo

```html
<!DOCTYPE html>
<html>
<head>
    <title>Exemplo Widget DatePicker</title>
    <link rel="stylesheet" href="widget/styles.css">
</head>
<body>
    <h1>Selecionar Período</h1>
    
    <div class="datepicker-container">
        <!-- HTML do widget aqui -->
    </div>
    
    <script src="widget/script.js"></script>
    <script>
        // Escutar eventos
        window.addEventListener('dateSelected', (event) => {
            const date = event.detail.dateObject;
            const formatted = event.detail.date;
            console.log(`Data selecionada: ${formatted}`);
            // Redirecionar ou atualizar página
            window.location.href = `?data=${date.toISOString().split('T')[0]}`;
        });
        
        window.addEventListener('dateRangeSelected', (event) => {
            const start = event.detail.startDate;
            const end = event.detail.endDate;
            console.log(`Range selecionado: ${start} até ${end}`);
            // Redirecionar ou atualizar página
            window.location.href = `?data_inicio=${start.toISOString().split('T')[0]}&data_fim=${end.toISOString().split('T')[0]}`;
        });
    </script>
</body>
</html>
```

## ✨ Funcionalidades

- ✅ Seleção de data única
- ✅ Seleção de range de datas
- ✅ Navegação por meses
- ✅ Visualização por mês/ano
- ✅ Configuração automática via URL
- ✅ Múltiplas instâncias
- ✅ Eventos customizados
- ✅ Formatação brasileira (dd/mm/aaaa)
- ✅ Responsivo
- ✅ Sem dependências externas

O widget agora é completamente autônomo e pode ser usado em qualquer página sem configuração adicional! 🎉