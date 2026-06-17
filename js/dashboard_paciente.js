/**
 * Dashboard Paciente Novo - JavaScript
 * Nexus Premium SaaS
 */

let calendarInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
});

function setupEventListeners() {
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('modalPagamento');
        if (modal && event.target === modal) {
            modal.classList.remove('show');
        }
    });

    window.addEventListener('resize', function() {
        if (calendarioPaciente) {
            setTimeout(function() {
                calendarioPaciente.updateSize();
            }, 100);
        }
    });
}

function cancelarConsulta(idConsulta) {
    if (confirm('Tem certeza que deseja cancelar esta consulta? O cancelamento so e permitido com pelo menos 24 horas de antecedencia.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="acao" value="cancelar_consulta">
            <input type="hidden" name="id_consulta" value="${idConsulta}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
