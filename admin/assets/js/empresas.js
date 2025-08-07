$(document).ready(function() {
    $('.cnpj-mask').mask('00.000.000/0000-00');
    $('.phone-mask').mask('(00) 00000-0000');
});

function novaEmpresa() {
    $('#form-empresa')[0].reset();
    $('#id_empresa').val('');
    $('#modal-empresa').addClass('active');
}

function editarEmpresa(id) {
    $.get('../actions/empresa_get.php', { id: id }, function(data) {
        $('#id_empresa').val(data.id_empresa);
        $('#nome_empresa').val(data.nome_empresa);
        $('#cnpj').val(data.cnpj);
        $('#telefone').val(data.telefone);
        $('#email').val(data.email);
        $('#modal-empresa').addClass('active');
    });
}

function fecharModal() {
    $('#modal-empresa').removeClass('active');
}

$('#form-empresa').on('submit', function(e) {
    e.preventDefault();
    
    $.post('../actions/empresa_save.php', $(this).serialize(), function(response) {
        if (response.status === 'success') {
            alert('Empresa salva com sucesso!');
            window.location.reload();
        } else {
            alert('Erro ao salvar empresa: ' + response.message);
        }
    });
});

function toggleStatus(id) {
    if (confirm('Deseja alterar o status desta empresa?')) {
        $.post('../actions/empresa_toggle_status.php', { id: id }, function(response) {
            if (response.status === 'success') {
                window.location.reload();
            } else {
                alert('Erro ao alterar status: ' + response.message);
            }
        });
    }
}

function verFuncionarios(id) {
    window.location.href = 'empresas_funcionarios.php?empresa=' + id;
} 