$(document).ready(function() {
    // atualizarCarrinho();

    // Menu toggle
    $('.fa-bars').on('click', function() {
        $('#menu').toggle();
        $('.table').toggleClass('table_atived');
    });

    $('.fa-cart-shopping').on('click', function(){
        if (window.innerWidth <= 768) {
            $('.modal-carrinho').removeClass('active').addClass('mobile-active');
        } else {
            $('.modal-carrinho').addClass('active');
        }
        
        // Busca os dados do carrinho independente do dispositivo
        $.ajax({
            url: "../actions/carrinho/ver_carrinho.php",
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data && typeof data === 'object') {
                    atualizarModalCarrinho(data);
                    atualizarCarrinho();
                } else {
                    alert('Erro ao carregar dados do carrinho. Por favor, tente novamente.');
                }
            },
            error: function(xhr, status, error) {
                alert('Erro ao carregar o carrinho. Por favor, tente novamente.');
            }
        });
    });

    // Evento de clique nos produtos do menu
    $('.menu-item').on('click', function() {
        var produtoId = $(this).data('id');
        var produtoNome = $(this).find('.menu-item-title').text();
        var produtoPreco = $(this).find('.preco').text();
        
        $.ajax({
            url: "../actions/carrinho/busca_acompanhamentos.php",
            method: "GET",
            data: { produto_id: produtoId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    mostrarModalAcompanhamentos(produtoId, produtoNome, produtoPreco, response.acompanhamentos);
                } else {
                    alert("Erro ao carregar acompanhamentos. Por favor, tente novamente.");
                }
            },
            error: function(xhr, status, error) {
                alert("Erro ao carregar acompanhamentos. Por favor, tente novamente.");
            }
        });
    });

    // Adicionar handler para fechar no mobile
    $('.btn-cancelar').on('click', function() {
        if (window.innerWidth <= 768) {
            $('.modal-carrinho').removeClass('mobile-active');
        } else {
            $('.modal-carrinho').removeClass('active');
        }
    });
});