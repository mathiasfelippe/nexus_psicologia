<?php
$consultas = obter_todas_consultas($pdo);
$eventos = [];

// Converter consultas para formato do FullCalendar — mesma lógica de cores do Paciente
foreach ($consultas as $consulta) {
    if ($consulta['status'] === 'Cancelada') {
        continue;
    }

    $horario = $consulta['horario'];
    if (strlen($horario) === 5) {
        $horario .= ':00';
    } elseif (strlen($horario) === 4) {
        $horario = '0' . $horario . ':00';
    }

    $inicio = strtotime($consulta['data_calendario'] . ' ' . $horario);
    $passada = $inicio && $inicio < time();

    $cor = '#6366f1';
    if ($consulta['status'] === 'Pendente') {
        $cor = '#f59e0b';
    } elseif ($consulta['status'] === 'Confirmada') {
        $cor = '#10b981';
    }

    $eventos[] = [
        'id' => 'consulta_' . $consulta['id_consulta'],
        'title' => substr($consulta['horario'], 0, 5) . 'h - ' . $consulta['paciente_nome'] . ' (' . $consulta['especializacao'] . ')',
        'start' => $consulta['data_calendario'] . 'T' . $horario,
        'end' => date('Y-m-d\TH:i', strtotime($consulta['data_calendario'] . ' ' . $horario . ' +1 hour')),
        'backgroundColor' => $cor,
        'borderColor' => $passada ? '#6b7280' : $cor,
        'textColor' => '#ffffff',
        'classNames' => [
            'consulta-calendario',
            $passada ? 'consulta-passada-evento' : 'consulta-futura-evento',
            'consulta-status-' . strtolower($consulta['status'])
        ],
        'extendedProps' => [
            'paciente' => $consulta['paciente_nome'],
            'email' => $consulta['paciente_email'],
            'telefone' => $consulta['paciente_telefone'],
            'especializacao' => $consulta['especializacao'],
            'modalidade' => $consulta['modalidade'],
            'status' => $consulta['status'],
            'pagamento' => $consulta['pagamento_status'],
            'valor' => $consulta['valor'],
            'id_consulta' => $consulta['id_consulta'],
            'passada' => $passada
        ]
    ];
}

$eventos_json = json_encode($eventos);
?>

<div class="calendario-secao calendario-psicologa-secao">
    <div class="calendario-header calendario-header-com-legenda">
        <div>
            <h2>Calendário de Consultas</h2>
            <p class="calendario-descricao">Visualize todas as consultas confirmadas, pendentes e passadas.</p>
        </div>
        <div class="calendario-legenda-inline">
            <div class="legenda-item">
                <span class="legenda-cor" style="background-color: #10b981;"></span>
                <span>Confirmada</span>
            </div>
            <div class="legenda-item">
                <span class="legenda-cor" style="background-color: #f59e0b;"></span>
                <span>Pendente</span>
            </div>
            <div class="legenda-item">
                <span class="legenda-cor" style="background-color: #6b7280;"></span>
                <span>Passada</span>
            </div>
        </div>
    </div>
    <div id="calendar-psicologa"></div>
</div>

<!-- Modal de Detalhes da Consulta -->
<div id="modalDetalhesPsicologa" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Detalhes da Consulta</h2>
            <button class="modal-fechar" onclick="fecharModalDetalhesPsicologa()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="detalhes-consulta" id="detalhesConteudoPsicologa"></div>
            <div class="modal-acoes" id="acoesConteudoPsicologa"></div>
        </div>
    </div>
</div>

<script>
(function() {
    const eventosPsicologa = <?php echo $eventos_json; ?>;

    function inicializarCalendarioPsicologaView() {
        const calendarEl = document.getElementById('calendar-psicologa');
        if (!calendarEl || typeof FullCalendar === 'undefined') return;
        if (calendarEl.dataset.inicializado === '1') return;
        calendarEl.dataset.inicializado = '1';

        const cal = new FullCalendar.Calendar(calendarEl, {
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
            displayEventTime: false,
            events: eventosPsicologa,
            eventDidMount: function(info) {
                const props = info.event.extendedProps;
                info.el.title = (props.paciente || '') + ' | ' + (props.especializacao || '') + ' | ' + (props.status || '');
            },
            eventClick: function(info) {
                mostrarDetalhesPsicologa(info.event);
            }
        });

        cal.render();
        setTimeout(function() { cal.render(); cal.updateSize(); }, 150);
    }

    function mostrarDetalhesPsicologa(event) {
        const props = event.extendedProps;
        const data = event.start ? event.start.toLocaleDateString('pt-BR') : '';
        const hora = event.start ? event.start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
        const valor = Number(props.valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const statusClass = 'status-' + (props.status || '').toLowerCase();

        const detalhesHTML = `
            <div class="detalhe-item">
                <span class="detalhe-label">Paciente</span>
                <span class="detalhe-valor">${props.paciente || 'Não informado'}</span>
            </div>
            <div class="detalhe-item">
                <span class="detalhe-label">Email</span>
                <span class="detalhe-valor">${props.email || 'Não informado'}</span>
            </div>
            <div class="detalhe-item">
                <span class="detalhe-label">Telefone</span>
                <span class="detalhe-valor">${props.telefone || 'Não informado'}</span>
            </div>
            <div class="detalhe-item">
                <span class="detalhe-label">Especialização</span>
                <span class="detalhe-valor">${props.especializacao || 'Não informada'}</span>
            </div>
            <div class="detalhe-item">
                <span class="detalhe-label">Modalidade</span>
                <span class="detalhe-valor">${props.modalidade || 'Não informada'}</span>
            </div>
            <div class="detalhe-item">
                <span class="detalhe-label">Data / Hora</span>
                <span class="detalhe-valor">${data} ${hora}</span>
            </div>
            <div class="detalhe-item">
                <span class="detalhe-label">Valor</span>
                <span class="detalhe-valor">R$ ${valor}</span>
            </div>
            <div class="detalhe-item">
                <span class="detalhe-label">Status</span>
                <span class="status-badge ${statusClass}">${props.status || ''}</span>
            </div>
            <div class="detalhe-item">
                <span class="detalhe-label">Pagamento</span>
                <span class="detalhe-valor">${props.pagamento || 'Não informado'}</span>
            </div>
        `;

        let acoesHTML = '';
        if (props.status === 'Pendente') {
            acoesHTML = `
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="acao" value="confirmar_consulta">
                    <input type="hidden" name="id_consulta" value="${props.id_consulta}">
                    <button type="submit" class="btn btn-confirmar btn-modal-acao"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Confirmar</button>
                </form>
                <button type="button" class="btn btn-cancelar btn-modal-acao" onclick="abrirModalCancelarCalendario(${props.id_consulta}); fecharModalDetalhesPsicologa();"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Cancelar</button>
            `;
        } else if (props.status !== 'Cancelada' && !props.passada) {
            acoesHTML = `
                <button type="button" class="btn btn-cancelar btn-modal-acao" onclick="abrirModalCancelarCalendario(${props.id_consulta}); fecharModalDetalhesPsicologa();"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Cancelar Consulta</button>
            `;
        }
        acoesHTML += `<button type="button" class="btn btn-secondary btn-modal-fechar" onclick="fecharModalDetalhesPsicologa()">Fechar</button>`;

        document.getElementById('detalhesConteudoPsicologa').innerHTML = detalhesHTML;
        document.getElementById('acoesConteudoPsicologa').innerHTML = acoesHTML;
        document.getElementById('modalDetalhesPsicologa').classList.add('show');
    }

    window.fecharModalDetalhesPsicologa = function() {
        document.getElementById('modalDetalhesPsicologa').classList.remove('show');
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarCalendarioPsicologaView);
    } else {
        inicializarCalendarioPsicologaView();
    }
})();
</script>

<style>
/* Estilos do modal de detalhes */
.detalhes-consulta {
    display: grid;
    gap: var(--spacing-md);
}

.detalhe-item {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.detalhe-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--neutral-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detalhe-valor {
    font-size: 14px;
    color: var(--neutral-900);
    font-weight: 500;
}

.detalhe-item .status-badge {
    align-self: flex-start;
}

/* Botões de ação no modal */
.btn-modal-acao,
.btn-modal-fechar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 22px;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    line-height: 1;
    min-height: 40px;
}
.btn-modal-acao {
    border: none;
}
.btn-modal-acao svg,
.btn-modal-fechar svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}
.btn-modal-fechar {
    border: 1px solid var(--neutral-200);
    background: white;
    color: var(--neutral-600);
}
.btn-modal-fechar:hover {
    background: var(--neutral-100);
    color: var(--neutral-900);
    border-color: var(--neutral-300);
}
.modal-acoes {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--neutral-200);
}
.modal-acoes form {
    display: contents;
}

/* Calendário da psicóloga */
#calendar-psicologa {
    --fc-border-color: var(--neutral-200);
    --fc-button-bg-color: var(--primary);
    --fc-button-border-color: var(--primary);
    --fc-button-hover-bg-color: var(--primary-dark);
    --fc-button-active-bg-color: var(--primary-dark);
    --fc-today-bg-color: rgba(128, 161, 212, 0.1);
    --fc-event-bg-color: var(--primary);
    --fc-event-border-color: var(--primary);
    width: 100%;
    min-height: 560px;
    margin-top: var(--spacing-xl);
}

body.dark-mode .detalhe-label {
    color: rgba(255,255,255,.4);
}
body.dark-mode .detalhe-valor {
    color: var(--branco);
}
body.dark-mode .btn-modal-fechar {
    background: rgba(255,255,255,.08);
    border-color: rgba(255,255,255,.12);
    color: var(--branco);
}
body.dark-mode .btn-modal-fechar:hover {
    background: rgba(255,255,255,.14);
    color: var(--branco);
    border-color: var(--azul-sereno);
}
body.dark-mode .modal-acoes {
    border-top-color: rgba(255,255,255,.08);
}
body.dark-mode #calendar-psicologa {
    --fc-border-color: rgba(255,255,255,.1);
    --fc-button-bg-color: rgba(255,255,255,.08);
    --fc-button-border-color: rgba(255,255,255,.12);
    --fc-button-hover-bg-color: rgba(255,255,255,.14);
    --fc-button-active-bg-color: rgba(128,161,212,.3);
    --fc-today-bg-color: rgba(128,161,212,.08);
    --fc-event-bg-color: var(--azul-sereno);
    --fc-event-border-color: var(--azul-sereno);
}
</style>
