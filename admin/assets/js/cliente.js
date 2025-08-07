function mostrarModalCadastroCliente() {
    // Criar estrutura básica do modal usando o estilo do carrinho
    var modal = $('<div>').addClass('modal-base modal-carrinho');
    var modalContent = $('<div>').addClass('modal-content');
    
    // Buscar bairros primeiro
    $.ajax({
        url: "../actions/busca_bairros.php",
        method: "GET",
        dataType: 'json',
        success: function(bairros) {
            // Criar formulário
            var formHtml = `
                <div class="cadastro-cliente">
                    <h3>Cadastro de Cliente</h3>
                    <form id="form-cadastro-cliente">
                        <div class="input-group">
                            <label for="nome_cliente">Nome do Cliente:</label>
                            <input type="text" name="nome_cliente" id="nome_cliente" required>
                        </div>

                        <div class="input-group">
                            <label for="telefone_cliente">Telefone:</label>
                            <input type="text" name="telefone_cliente" id="telefone_cliente" required>
                        </div>

                        <div class="endereco-opcional">
                            <h4>Endereço (Opcional)</h4>
                            <div class="input-group">
                                <label for="endereco_cliente">Endereço:</label>
                                <input type="text" name="endereco_cliente" id="endereco_cliente">
                            </div>

                            <div class="input-group">
                                <label for="numero_endereco">Número:</label>
                                <input type="text" name="numero_endereco" id="numero_endereco">
                            </div>

                            <div class="input-group">
                                <label for="bairro_cliente">Bairro:</label>
                                <select name="bairro_cliente" id="bairro_cliente">
                                    <option value="">Selecione o Bairro</option>
                                    ${bairros.map(b => `
                                        <option value="${b.id_bairro}">${b.nome_bairro} (Taxa: R$ ${Number(b.valor_taxa).toFixed(2)})</option>
                                    `).join('')}
                                </select>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn-cancelar">Cancelar</button>
                            <button type="submit" class="btn-adicionar">Cadastrar Cliente</button>
                        </div>
                    </form>
                </div>
            `;

            // Adiciona o formulário HTML ao conteúdo do modal
            modalContent.append(formHtml);

            // Adiciona o conteúdo ao modal
            modal.append(modalContent); 
            
            // Adiciona o modal completo ao body da página
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

            $('#form-cadastro-cliente').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                
                $.ajax({
                    url: "../actions/cadastra_cliente.php",
                    method: "POST",
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Adicionar cliente ao carrinho
                            $.ajax({
                                url: "../actions/carrinho.php",
                                type: "POST",
                                data: { id_cliente: response.cliente.id_cliente },
                                success: function() {
                                    modal.removeClass('active');
                                    setTimeout(() => {
                                        modal.remove();
                                        // Limpar a busca e mostrar o menu
                                        $('#search').val('').hide();
                                        $('#results').empty();
                                        // Mostrar o carrinho automaticamente
                                        $.ajax({
                                            url: "../actions/ver_carrinho.php",
                                            method: 'GET',
                                            dataType: 'json',
                                            success: function(data) {
                                                mostrarModalCarrinho(data);
                                            }
                                        });
                                    }, 300);
                                }
                            });
                        } else {
                            alert(response.message || 'Erro ao cadastrar cliente');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro ao cadastrar cliente:", error);
                        alert('Erro ao cadastrar cliente. Por favor, tente novamente.');
                    }
                });
            });

            // Máscara para telefone
            $('#telefone_cliente').on('input', function() {
                var phone = $(this).val();
                phone = phone.replace(/\D/g, '');
                phone = phone.replace(/^(\d{2})(\d)/g, '($1) $2');
                phone = phone.replace(/(\d)(\d{4})$/, '$1-$2');
                $(this).val(phone);
            });
        },
        error: function(xhr, status, error) {
            console.error("Erro ao buscar bairros:", error);
            alert('Erro ao carregar bairros. Por favor, tente novamente.');
        }
    });
}

// Exportar função para uso global
window.mostrarModalCadastroCliente = mostrarModalCadastroCliente;
