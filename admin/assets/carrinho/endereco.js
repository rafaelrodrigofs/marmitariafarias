function mostrarFormularioEndereco() {
    // Criar estrutura básica do modal
    var modal = $('<div>').addClass('modal-base modal-endereco');
    var modalContent = $('<div>').addClass('modal-content');
    
    // Buscar bairros primeiro
    $.ajax({
        url: "../actions/carrinho/busca_bairros.php",
        method: "GET",
        dataType: 'json',
        success: function(bairros) {
            // Criar formulário
            var formHtml = `
                <div class="form-endereco">
                    <h3>Cadastro de Endereço</h3>
                    <form id="form-cadastro-endereco">
                        <div class="input-group">
                            <label for="nome_entrega">Endereço:</label>
                            <input type="text" name="nome_entrega" id="nome_entrega" required>
                        </div>

                        <div class="input-group">
                            <label for="numero_entrega">Número:</label>
                            <input type="text" name="numero_entrega" id="numero_entrega" required>
                        </div>

                        <div class="input-group">
                            <label for="bairro_id">Bairro:</label>
                            <select name="bairro_id" id="bairro_id" required>
                                <option value="">Selecione o Bairro</option>
                                ${bairros.map(b => `
                                    <option value="${b.id_bairro}">${b.nome_bairro} (Taxa: R$ ${Number(b.valor_taxa).toFixed(2)})</option>
                                `).join('')}
                            </select>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn-cancelar">Cancelar</button>
                            <button type="submit" class="btn-adicionar">Cadastrar Endereço</button>
                        </div>
                    </form>
                </div>
            `;

            modalContent.append(formHtml);
            modal.append(modalContent);
            $('body').append(modal);

            // Animar abertura
            setTimeout(() => {
                modal.addClass('active');
            }, 10);

            // Event handlers
            $('.btn-cancelar').on('click', function() {
                modal.removeClass('active');
                setTimeout(() => {
                    modal.remove();
                }, 300);
            });

            $('#form-cadastro-endereco').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                
                $.ajax({
                    url: "../actions/carrinho/cadastra_endereco.php",
                    method: "POST",
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Fechar modal de endereço
                            modal.removeClass('active');
                            setTimeout(() => {
                                modal.remove();
                                // Atualizar carrinho para mostrar novo endereço
                                $.ajax({
                                    url: "../actions/carrinho/ver_carrinho.php",
                                    method: 'GET',
                                    dataType: 'json',
                                    success: function(data) {
                                        // Remover modal atual do carrinho
                                        // $('.modal-carrinho').remove();
                                        // Mostrar carrinho atualizado
                                        // mostrarModalCarrinho(data);
                                        atualizarCarrinho(data);
                                        atualizarModalCarrinho(data);
                                    }
                                });
                            }, 300);
                        } else {
                            alert(response.message || 'Erro ao cadastrar endereço');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro ao cadastrar endereço:", error);
                        alert('Erro ao cadastrar endereço. Por favor, tente novamente.');
                    }
                });
            });
        },
        error: function(xhr, status, error) {
            console.error("Erro ao buscar bairros:", error);
            alert('Erro ao carregar bairros. Por favor, tente novamente.');
        }
    });
}

// Exportar função para uso global
window.mostrarFormularioEndereco = mostrarFormularioEndereco;
