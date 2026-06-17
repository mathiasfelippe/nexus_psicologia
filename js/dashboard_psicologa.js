/**
 * Dashboard Psicóloga - JavaScript
 * Nexus Premium SaaS
 */

let calendarPsicologa = null;

document.addEventListener('DOMContentLoaded', function() {
    inicializarCalendarioPsicologa();
    configurarModoEscuro();
});

function inicializarCalendarioPsicologa() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl || typeof FullCalendar === 'undefined') return;
    if (calendarEl.dataset.inicializado === '1') return;

    calendarEl.dataset.inicializado = '1';

    calendarPsicologa = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        locale: 'pt-br',
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            day: 'Dia'
        },
        height: 'auto',
        contentHeight: 'auto',
        expandRows: true,
        events: 'api/consultas_psicologa.php',
        displayEventTime: false,
        eventDidMount: function(info) {
            const props = info.event.extendedProps;
            info.el.title = (props.paciente || '') + ' | ' + (props.especializacao || '') + ' | ' + (props.status || '');
        },
        eventClick: function(info) {
            const event = info.event;
            const props = event.extendedProps;
            const data = event.start ? event.start.toLocaleDateString('pt-BR') : '';
            const hora = event.start ? event.start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
            alert(
                'Paciente: ' + (props.paciente || 'Não informado') + '\n' +
                'Especialidade: ' + (props.especializacao || 'Não informada') + '\n' +
                'Data/Hora: ' + data + ' ' + hora + '\n' +
                'Status: ' + (props.status || 'Não informado') + '\n' +
                'Pagamento: ' + (props.pagamento || 'Não informado')
            );
        }
    });

    calendarPsicologa.render();
    setTimeout(function() {
        if (calendarPsicologa) {
            calendarPsicologa.render();
            calendarPsicologa.updateSize();
        }
    }, 150);
}

function configurarModoEscuro() {
    const btnDarkMode = document.getElementById('btn-dark-mode');
    if (!btnDarkMode) return;

    // Carregar preferência salva
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
    }

    btnDarkMode.addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
    });
}

// Auto-fechar alertas após 5 segundos
document.addEventListener('DOMContentLoaded', function() {
    const alertas = document.querySelectorAll('.alerta');
    alertas.forEach(function(alerta) {
        setTimeout(function() {
            alerta.style.opacity = '0';
            alerta.style.transition = 'opacity 0.3s ease';
            setTimeout(function() {
                alerta.style.display = 'none';
            }, 300);
        }, 5000);
    });
});

// Animações ao carregar
window.addEventListener('load', function() {
    document.querySelectorAll('.widget, .secao, .calendario-secao').forEach(function(el, index) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'all 0.3s ease ' + (index * 0.08) + 's';
        setTimeout(function() {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, 10);
    });
});
