

<nav class="main-nav">
    <div class="nav-brand">
        <img src="../assets/img/logo.png" alt="LuncheFit" class="nav-logo">
    </div>
    
    <div class="nav-links">
        <div class="nav-dropdown">
            <a href="#" class="nav-item nav-dropdown-toggle">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <div class="nav-dropdown-content">
                <a href="dashboard.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Visão Geral</span>
                </a>
                <a href="despesas.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'despesas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Despesas</span>
                </a>
                <a href="fechamento.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'fechamento.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cash-register"></i>
                    <span>Fechamento</span>
                </a>
            </div>
        </div>

        <div class="nav-dropdown">
            <a href="#" class="nav-item nav-dropdown-toggle">
                <i class="fas fa-file-alt"></i>
                <span>Relatório</span>
            </a>
            <div class="nav-dropdown-content">
                <a href="relatorio_pedidos_anotai.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'relatorio_pedidos_anotai.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice"></i>
                    <span>Pedidos Anotai</span>
                </a>
                <a href="relatorio_pedidos.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'relatorio_pedidos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice"></i>
                    <span>Pedidos</span>
                </a>
                <a href="clientes.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
                <a href="relatorio_geral.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'relatorio_geral.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Relatório Geral</span>
                </a>
            </div>
        </div>
        
        
        <a href="pedidos.php" class="nav-item nav-item-destaque <?php echo basename($_SERVER['PHP_SELF']) == 'pedidos.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Pedidos</span>
        </a>

        <div class="nav-dropdown">
            <a href="#" class="nav-item nav-dropdown-toggle">
                <i class="fas fa-box"></i>
                <span>Produtos</span>
            </a>
            <div class="nav-dropdown-content">
                <a href="produtos.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'produtos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Lista de Produtos</span>
                </a>
                <a href="acomp.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'acomp.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i>
                    <span>Adicionais</span>
                </a>
            </div>
        </div>
        
        <a href="empresas_relatorios.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'empresas_relatorios.php' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Empresas</span>
        </a>
    </div>
    
    <div class="nav-user">
        <div class="user-info">
            <span class="user-name"><?php echo $_SESSION['username'] ?? 'Usuário'; ?></span>
            <div class="menu-item">
                <a href="../actions/login/logout.php" class="menu-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </div>
        </div>
    </div>
</nav> 

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Fecha todos os outros dropdowns
            dropdownToggles.forEach(otherToggle => {
                if (otherToggle !== toggle) {
                    const otherDropdown = otherToggle.closest('.nav-dropdown');
                    otherDropdown.classList.remove('active');
                    const otherIcon = otherToggle.querySelector('.fa-chevron-down');
                    if (otherIcon) otherIcon.style.transform = 'rotate(0)';
                }
            });
            
            const dropdown = this.closest('.nav-dropdown');
            dropdown.classList.toggle('active');
            
            const icon = this.querySelector('.fa-chevron-down');
            icon.style.transform = dropdown.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
        });
    });

    // Remove a classe active ao clicar em um item do menu
    const dropdownItems = document.querySelectorAll('.nav-dropdown-item');
    dropdownItems.forEach(item => {
        item.addEventListener('click', function() {
            const dropdown = this.closest('.nav-dropdown');
            dropdown.classList.remove('active');
        });
    });

    // Fecha o dropdown ao clicar fora dele
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-dropdown')) {
            dropdownToggles.forEach(toggle => {
                const dropdown = toggle.closest('.nav-dropdown');
                dropdown.classList.remove('active');
                const icon = toggle.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(0)';
            });
        }
    });
});
</script>