<?php
$consultas = obter_todas_consultas($pdo);
$eventos = [];

// Converter consultas para formato do FullCalendar
foreach ($consultas as $consulta) {
    $cor = '#667eea'; // Padrão
    if ($consulta['status'] === 'Confirmada') {
        $cor = '#4caf50'; // Verde
    } elseif ($consulta['status'] === 'Cancelada') {
        $cor = '#ff6b6b'; // Vermelho
    } elseif ($consulta['status'] === 'Pendente') {
        $cor = '#ffc107'; // Amarelo
    }

    $eventos[] = [
        'id' => 'consulta_' . $consulta['id_consulta'],
        'title' => $consulta['paciente_nome'] . ' - ' . $consulta['especializacao'],
        'start' => $consulta['data_calendario'] . 'T' . $consulta['horario'],
        'end' => date('Y-m-d\TH:i', strtotime($consulta['data_calendario'] . ' ' . $consulta['horario'] . ' +1 hour')),
        'backgroundColor' => $cor,
        'borderColor' => $cor,
        'extendedProps' => [
            'paciente' => $consulta['paciente_nome'],
            'email' => $consulta['paciente_email'],
            'telefone' => $consulta['paciente_telefone'],
            'especializacao' => $consulta['especializacao'],
            'modalidade' => $consulta['modalidade'],
            'status' => $consulta['status'],
            'pagamento' => $consulta['pagamento_status'],
            'valor' => $consulta['valor'],
            'id_consulta' => $consulta['id_consulta']
        ]
    ];
}

$eventos_json = json_encode($eventos);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário - Nexus Psicologia</title>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.js'></script>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.css' rel='stylesheet' />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .calendario-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin: 20px;
        }

        .calendario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .calendario-header h2 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }

        .calendario-legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        /* FullCalendar Customization */
        .fc {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .fc .fc-button-primary {
            background-color: #667eea;
            border-color: #667eea;
        }

        .fc .fc-button-primary:hover {
            background-color: #5568d3;
        }

        .fc .fc-button-primary.fc-button-active {
            background-color: #667eea;
            border-color: #667eea;
        }

        .fc .fc-col-header-cell {
            background-color: #f5f7fa;
            color: #333;
            font-weight: 600;
            border-color: #e0e0e0;
        }

        .fc .fc-daygrid-day.fc-day-other {
            background-color: #fafafa;
        }

        .fc .fc-daygrid-day:hover {
            background-color: #f9f9f9;
        }

        .fc .fc-event {
            cursor: pointer;
            border: none;
        }

        .fc .fc-event-title {
            font-weight: 600;
            font-size: 12px;
            padding: 4px;
        }

        .fc-daygrid-event-frame {
            padding: 2px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-conteudo {
            background-color: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .modal-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .modal-fechar {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
        }

        .detalhes-consulta {
            display: grid;
            gap: 16px;
        }

        .detalhe-item {
            display: flex;
            flex-direction: column;
        }

        .detalhe-label {
            font-size: 12px;
            font-weight: 600;
            color: #999;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .detalhe-valor {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            width: fit-content;
        }

        .status-confirmada {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-cancelada {
            background-color: #f8d7da;
            color: #721c24;
        }

        .modal-acoes {
            display: flex;
            gap: 8px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .btn {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background-color: #5568d3;
        }

        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #e0e0e0;
        }

        @media (max-width: 768px) {
            .calendario-container {
                margin: 10px;
                padding: 16px;
            }

            .calendario-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .calendario-legend {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="calendario-container">
        <div class="calendario-header">
            <h2>Calendário de Consultas</h2>
            <div class="calendario-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #ffc107;"></div>
                    <span>Pendente</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #4caf50;"></div>
                    <span>Confirmada</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #ff6b6b;"></div>
                    <span>Cancelada</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #9ca3af;"></div>
                    <span>Passada</span>
                </div>
            </div>
        </div>

        <div id="calendar"></div>
    </div>

    <!-- Modal de Detalhes -->
    <div id="modalDetalhes" class="modal">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2>Detalhes da Consulta</h2>
                <button class="modal-fechar" onclick="fecharModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detalhes-consulta" id="detalhesConteudo"></div>
                <div class="modal-acoes" id="acoesConteudo"></div>
            </div>
        </div>
    </div>

    <script>
        const eventos = <?php echo $eventos_json; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
            locale: 'pt-br',
            locales: ['pt-br'],
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana',
                day: 'Dia'
            },
            events: eventos,
                eventDidMount: function(info) {
                    if (info.event.extendedProps.passada) {
                        info.el.style.opacity = '0.6';
                        info.el.style.textDecoration = 'line-through';
                    }
                },
                eventClick: function(info) {
                    mostrarDetalhes(info.event);
                },
                datesSet: function(info) {
                    // Atualizar eventos quando mudar de período
                }
            });
            calendar.render();
        });

        function mostrarDetalhes(event) {
            const props = event.extendedProps;
            const detalhesHTML = `
                <div class="detalhe-item">
                    <span class="detalhe-label">Paciente</span>
                    <span class="detalhe-valor">${props.paciente}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Email</span>
                    <span class="detalhe-valor">${props.email}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Telefone</span>
                    <span class="detalhe-valor">${props.telefone || 'Não informado'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Especialização</span>
                    <span class="detalhe-valor">${props.especializacao}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Modalidade</span>
                    <span class="detalhe-valor">${props.modalidade}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Valor</span>
                    <span class="detalhe-valor">R$ ${parseFloat(props.valor).toFixed(2).replace('.', ',')}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Status</span>
                    <span class="status-badge status-${props.status.toLowerCase()}">${props.status}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Pagamento</span>
                    <span class="detalhe-valor">${props.pagamento}</span>
                </div>
            `;

            let acoesHTML = '';
            if (props.status === 'Pendente') {
                acoesHTML = `
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="acao" value="confirmar_consulta">
                        <input type="hidden" name="id_consulta" value="${props.id_consulta}">
                        <button type="submit" class="btn btn-primary">Confirmar</button>
                    </form>
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="acao" value="cancelar_consulta">
                        <input type="hidden" name="id_consulta" value="${props.id_consulta}">
                        <button type="submit" class="btn btn-secondary">Cancelar</button>
                    </form>
                `;
            } else if (props.status !== 'Cancelada') {
                acoesHTML = `
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="acao" value="cancelar_consulta">
                        <input type="hidden" name="id_consulta" value="${props.id_consulta}">
                        <button type="submit" class="btn btn-secondary">Cancelar Consulta</button>
                    </form>
                `;
            }
            acoesHTML += `<button type="button" class="btn btn-secondary" onclick="fecharModal()" style="flex: 1;">Fechar</button>`;

            document.getElementById('detalhesConteudo').innerHTML = detalhesHTML;
            document.getElementById('acoesConteudo').innerHTML = acoesHTML;
            document.getElementById('modalDetalhes').classList.add('show');
        }

        function fecharModal() {
            document.getElementById('modalDetalhes').classList.remove('show');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalDetalhes');
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        }
    </script>
</body>
</html>
