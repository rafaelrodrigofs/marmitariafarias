
    <!-- DatePicker Widget -->
        <!-- Campo de entrada da data -->
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

    <!-- Modal removido - agora usa visualização inline para melhor performance -->

    <!-- Script do widget -->
    <script src="script.js"></script>
    
    <!-- Script de exemplo (opcional) -->
    <script>
        // Escuta os eventos do datepicker para integração
        window.addEventListener('dateRangeSelected', (event) => {
            console.log('🎯 Range selecionado:', event.detail);
            // Aqui você pode integrar com seu sistema
        });
        
        window.addEventListener('dateSelected', (event) => {
            console.log('📅 Data selecionada:', event.detail);
            // Aqui você pode integrar com seu sistema
        });
    </script>