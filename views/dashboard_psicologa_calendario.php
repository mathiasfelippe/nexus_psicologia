<?php
/*
 * ARQUIVO: views/dashboard_psicologa_calendario.php
 * DESCRIÇÃO: View da aba "Calendário" do dashboard da psicóloga.
 *
 * Este arquivo é incluído pelo dashboard_psicologa.php quando a aba 'calendario'
 * está ativa. Exibe um calendário interativo (FullCalendar) com todas as consultas
 * do sistema, permitindo visualizar detalhes e executar ações ao clicar em um evento.
 *
 * DIFERENÇAS em relação ao calendário do paciente:
 *   - Exibe o nome do paciente no título do evento
 *   - Inclui email e telefone do paciente nos detalhes
 *   - Permite confirmar consultas pendentes diretamente do modal
 *   - Os dados são embutidos no HTML como JSON (não usa fetch/API)
 *
 * AÇÕES POST GERADAS (via modal):
 *   - confirmar_consulta → Confirma uma consulta pendente
 *   - cancelar_consulta  → Cancela uma consulta (abre modal de cancelamento)
 *
 * LEGENDA DE CORES:
 *   - Verde  (#10b981): Confirmada
 *   - Âmbar  (#f59e0b): Pendente
 *   - Cinza  (#6b7280): Passada (borda)
 *   - Indigo (#6366f1): Padrão
 *
 * DEPENDÊNCIAS:
 *   - FullCalendar (carregado no dashboard_psicologa.php)
 *   - $pdo: conexão com o banco de dados
 *   - obter_todas_consultas(): função de config/funcoes.php
 *   - abrirModalCancelarCalendario(): função definida no dashboard_psicologa.php
 */

// Busca TODAS as consultas do sistema para exibir no calendário
$consultas = obter_todas_consultas($pdo);
// Array que acumulará os eventos formatados para o FullCalendar
$eventos = [];

// ── Conversão de Consultas para Eventos do FullCalendar ──
foreach ($consultas as $consulta) {
    // Ignora consultas canceladas (não exibe no calendário)
    if ($consulta['status'] === 'Cancelada') {
        continue;
    }

    // Normaliza o horário para o formato HH:MM:SS
    $horario = $consulta['horario'];
    if (strlen($horario) === 5) {
        $horario .= ':00'; // "HH:MM" → "HH:MM:00"
    } elseif (strlen($horario) === 4) {
        $horario = '0' . $horario . ':00'; // "H:MM" → "0H:MM:00"
    }

    // Calcula se a consulta já ocorreu
    $inicio = strtotime($consulta['data_calendario'] . ' ' . $horario);
    $passada = $inicio && $inicio < time();

    // Define a cor do evento pelo status
    $cor = '#6366f1'; // Indigo: padrão
    if ($consulta['status'] === 'Pendente') {
        $cor = '#f59e0b'; // Âmbar
    } elseif ($consulta['status'] === 'Confirmada') {
        $cor = '#10b981'; // Verde
    }

    // Monta o objeto de evento para o FullCalendar
    $eventos[] = [
        // Prefixo 'consulta_' evita conflito de IDs com outros tipos de eventos
        'id' => 'consulta_' . $consulta['id_consulta'],
        // Título: "HH:MMh - Nome do Paciente (Especialização)"
        'title' => substr($consulta['horario'], 0, 5) . 'h - ' . $consulta['paciente_nome'] . ' (' . $consulta['especializacao'] . ')',
        'start' => $consulta['data_calendario'] . 'T' . $horario,
        // Fim: 1 hora após o início (formato sem segundos: Y-m-d\TH:i)
        'end' => date('Y-m-d\TH:i', strtotime($consulta['data_calendario'] . ' ' . $horario . ' +1 hour')),
        'backgroundColor' => $cor,
        'borderColor' => $passada ? '#6b7280' : $cor, // Borda cinza para passadas
        'textColor' => '#ffffff',
        'classNames' => [
            'consulta-calendario',
            $passada ? 'consulta-passada-evento' : 'consulta-futura-evento',
            'consulta-status-' . strtolower($consulta['status'])
        ],
        // extendedProps: dados extras acessíveis via info.event.extendedProps no JS
        // Inclui mais campos que o endpoint do paciente (email, telefone, id_consulta)
        'extendedProps' => [
            'paciente'       => $consulta['paciente_nome'],
            'email'          => $consulta['paciente_email'],
            'telefone'       => $consulta['paciente_telefone'],
            'especializacao' => $consulta['especializacao'],
            'modalidade'     => $consulta['modalidade'],
            'status'         => $consulta['status'],
            'pagamento'      => $consulta['pagamento_status'],
            'valor'          => $consulta['valor'],
            'id_consulta'    => $consulta['id_consulta'], // Necessário para as ações do modal
            'passada'        => $passada
        ]
    ];
}

// Serializa os eventos para JSON (será embutido diretamente no JavaScript)
$eventos_json = json_encode($eventos);
?>

<!-- ═══════════════════════════════════════════════════════════
     SEÇÃO DO CALENDÁRIO
═══════════════════════════════════════════════════════════ -->
<div class="calendario-secao calendario-psicologa-secao">
    <div class="calendario-header calendario-header-com-legenda">
        <div>
            <h2>Calendário de Consultas</h2>
            <p class="calendario-descricao">Visualize todas as consultas confirmadas, pendentes e passadas.</p>
        </div>
        <!-- Legenda de cores dos eventos -->
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
    <!-- Container onde o FullCalendar será renderizado -->
    <div id="calendar-psicologa"></div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL DE DETALHES DA CONSULTA
     Exibido ao clicar em um evento no calendário
═══════════════════════════════════════════════════════════ -->
<div id="modalDetalhesPsicologa" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Detalhes da Consulta</h2>
            <button class="modal-fechar" onclick="fecharModalDetalhesPsicologa()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Preenchido dinamicamente pelo JavaScript com os dados do evento clicado -->
            <div class="detalhes-consulta" id="detalhesConteudoPsicologa"></div>
            <!-- Botões de ação (Confirmar/Cancelar/Fechar) preenchidos pelo JavaScript -->
            <div class="modal-acoes" id="acoesConteudoPsicologa"></div>
        </div>
    </div>
</div>

<script>
    /*
     * IIFE (Immediately Invoked Function Expression):
     * Encapsula todo o código em um escopo privado para evitar conflitos
     * com variáveis de outros scripts na página.
     */
    (function() {
        // Recebe os eventos PHP como array JavaScript
        // <?php echo $eventos_json; ?> é substituído pelo JSON gerado no PHP
        const eventosPsicologa = <?php echo $eventos_json; ?>;

        /*
         * Inicializa o calendário FullCalendar no elemento #calendar-psicologa.
         * Protegido contra dupla inicialização via data-inicializado.
         */
        function inicializarCalendarioPsicologaView() {
            const calendarEl = document.getElementById('calendar-psicologa');
            // Verifica se o elemento existe e se o FullCalendar está carregado
            if (!calendarEl || typeof FullCalendar === 'undefined') return;
            // Evita inicializar o calendário duas vezes (ex: ao trocar de aba e voltar)
            if (calendarEl.dataset.inicializado === '1') return;
            calendarEl.dataset.inicializado = '1';

            const cal = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth', // Visão inicial: grade mensal
                headerToolbar: {
                    left: 'prev,next today',                    // Navegação
                    center: 'title',                            // Título (mês/ano)
                    right: 'dayGridMonth,timeGridWeek,timeGridDay' // Alternância de visão
                },
                locale: 'pt-br',          // Idioma português do Brasil
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia'
                },
                height: 'auto',           // Altura automática (sem barra de rolagem interna)
                contentHeight: 'auto',
                expandRows: true,         // Expande as linhas para preencher a altura
                displayEventTime: false,  // Não exibe o horário no título do evento na grade
                events: eventosPsicologa, // Array de eventos gerado pelo PHP

                // Callback: chamado após cada evento ser renderizado no DOM
                eventDidMount: function(info) {
                    const props = info.event.extendedProps;
                    // Adiciona tooltip (title) com informações resumidas ao passar o mouse
                    info.el.title = (props.paciente || '') + ' | ' + (props.especializacao || '') + ' | ' + (props.status || '');
                },

                // Callback: chamado ao clicar em um evento
                eventClick: function(info) {
                    mostrarDetalhesPsicologa(info.event);
                }
            });

            cal.render();
            // Segundo render após 150ms para corrigir problemas de layout em abas ocultas
            setTimeout(function() { cal.render(); cal.updateSize(); }, 150);
        }

        /*
         * Exibe o modal com os detalhes da consulta clicada.
         * Preenche dinamicamente o HTML do modal com os dados do evento.
         *
         * @param {FullCalendar.EventApi} event - Objeto do evento clicado
         */
        function mostrarDetalhesPsicologa(event) {
            const props = event.extendedProps;
            // Formata a data para o padrão brasileiro (dd/mm/YYYY)
            const data = event.start ? event.start.toLocaleDateString('pt-BR') : '';
            // Formata a hora para HH:MM
            const hora = event.start ? event.start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
            // Formata o valor monetário para o padrão brasileiro (ex: 1.234,56)
            const valor = Number(props.valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Classe CSS do badge de status (ex: 'status-confirmada')
            const statusClass = 'status-' + (props.status || '').toLowerCase();

            // Monta o HTML dos detalhes da consulta
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

            // Monta os botões de ação conforme o status da consulta
            let acoesHTML = '';
            if (props.status === 'Pendente') {
                // Consulta pendente: exibe botões de Confirmar e Cancelar
                acoesHTML = `
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="acao" value="confirmar_consulta">
                        <input type="hidden" name="id_consulta" value="${props.id_consulta}">
                        <button type="submit" class="btn btn-confirmar btn-modal-acao"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Confirmar</button>
                    </form>
                    <button type="button" class="btn btn-cancelar btn-modal-acao" onclick="abrirModalCancelarCalendario(${props.id_consulta}); fecharModalDetalhesPsicologa();"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Cancelar</button>
                `;
            } else if (props.status !== 'Cancelada' && !props.passada) {
                // Consulta confirmada e futura: exibe apenas o botão de Cancelar
                acoesHTML = `
                    <button type="button" class="btn btn-cancelar btn-modal-acao" onclick="abrirModalCancelarCalendario(${props.id_consulta}); fecharModalDetalhesPsicologa();"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Cancelar Consulta</button>
                `;
            }
            // Botão Fechar sempre presente
            acoesHTML += `<button type="button" class="btn btn-secondary btn-modal-fechar" onclick="fecharModalDetalhesPsicologa()">Fechar</button>`;

            // Injeta o HTML gerado nos containers do modal
            document.getElementById('detalhesConteudoPsicologa').innerHTML = detalhesHTML;
            document.getElementById('acoesConteudoPsicologa').innerHTML = acoesHTML;
            // Exibe o modal adicionando a classe 'show'
            document.getElementById('modalDetalhesPsicologa').classList.add('show');
        }

        // Expõe fecharModalDetalhesPsicologa globalmente para uso no HTML inline (onclick)
        window.fecharModalDetalhesPsicologa = function() {
            document.getElementById('modalDetalhesPsicologa').classList.remove('show');
        };

        // Inicializa o calendário quando o DOM estiver pronto
        if (document.readyState === 'loading') {
            // DOM ainda não carregou: aguarda o evento DOMContentLoaded
            document.addEventListener('DOMContentLoaded', inicializarCalendarioPsicologaView);
        } else {
            // DOM já carregado (arquivo incluído após o carregamento): inicializa imediatamente
            inicializarCalendarioPsicologaView();
        }
    })();
</script>

<!-- ═══════════════════════════════════════════════════════════
     ESTILOS LOCAIS (específicos desta view)
═══════════════════════════════════════════════════════════ -->
<style>
/* Grid de detalhes da consulta no modal */
.detalhes-consulta {
    display: grid;
    gap: var(--spacing-md);
}

/* Cada par label + valor */
.detalhe-item {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

/* Rótulo do campo (ex: "PACIENTE", "DATA / HORA") */
.detalhe-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--neutral-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Valor do campo */
.detalhe-valor {
    font-size: 14px;
    color: var(--neutral-900);
    font-weight: 500;
}

/* Badge de status alinhado à esquerda dentro do item */
.detalhe-item .status-badge {
    align-self: flex-start;
}

/* Botões de ação e fechar no modal */
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
    flex-shrink: 0; /* Impede que o ícone encolha */
}
/* Botão Fechar: estilo secundário com borda */
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
/* Container dos botões de ação: linha horizontal com separador */
.modal-acoes {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--neutral-200);
}
/* display: contents: o form não cria um bloco visual, apenas envolve o botão */
.modal-acoes form {
    display: contents;
}

/* Variáveis CSS do FullCalendar para o tema da psicóloga */
#calendar-psicologa {
    --fc-border-color: var(--neutral-200);
    --fc-button-bg-color: var(--primary);
    --fc-button-border-color: var(--primary);
    --fc-button-hover-bg-color: var(--primary-dark);
    --fc-button-active-bg-color: var(--primary-dark);
    --fc-today-bg-color: rgba(128, 161, 212, 0.1); /* Destaque sutil no dia atual */
    --fc-event-bg-color: var(--primary);
    --fc-event-border-color: var(--primary);
    width: 100%;
    min-height: 560px;
    margin-top: var(--spacing-xl);
}

/* ── Variantes para Modo Escuro ── */
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
