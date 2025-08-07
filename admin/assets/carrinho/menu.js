$(document).ready(function() {
    // Abrir menu
    $('.fa-bars').click(function() {
        $('.main-nav').addClass('active');
        $('.main-content').addClass('menu-active');
    });

    // Fechar menu
    $('.btn-fechar-menu, .menu-overlay').click(function() {
        $('.main-nav').removeClass('active');
        $('.main-content').removeClass('menu-active');
    });

    // Remover classe active ao clicar em nav-dropdown-item
    $('.nav-dropdown-item').click(function() {
        $(this).closest('.nav-dropdown').removeClass('active');
    });

    // Garantir que nenhum dropdown esteja aberto ao carregar a página
    $('.nav-dropdown').removeClass('active');
});
