<?php
$especializacoes = obter_especializacoes($pdo);
$horarios = obter_horarios($pdo);
$datas = obter_datas_disponiveis($pdo);
?>

<div class="secao">
    <h2 id="titulo-agendamento">Agendar Consulta</h2>
    
    <div id="calendar-agendamento" style="margin-bottom: 30px;"></div>

    <form id="form-agendamento" method="POST" class="formulario-agendamento" style="display: none;">
        <input type="hidden" name="acao" id="acao-agendamento" value="agendar_consulta">
        <input type="hidden" name="id_data" id="id_data_input">

        <div class="form-row">
            <div class="form-group">
                <label>Data Selecionada</label>
                <input type="text" id="data_exibicao" readonly class="form-control">
            </div>
            <div class="form-group">
                <label for="especializacao">Especialização *</label>
                <select id="especializacao" name="id_especializacao" required>
                    <option value="">Selecione uma especialização</option>
                    <?php foreach ($especializacoes as $spec): ?>
                        <option value="<?php echo $spec['id_especializacao']; ?>">
                            <?php echo htmlspecialchars($spec['nome']); ?> - R$ <?php echo number_format($spec['preco'], 2, ',', '.'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="modalidade">Modalidade *</label>
                <select id="modalidade" name="modalidade" required>
                    <option value="Online">Online</option>
                    <option value="Presencial">Presencial</option>
                </select>
            </div>
            <div class="form-group">
                <label for="horario_select">Horário Disponível *</label>
                <select id="horario_select" name="id_horario" required>
                    <option value="">Selecione um horário</option>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="submit" class="btn btn-primary" id="btn-submit-agendamento">Agendar Agora</button>
            <button type="button" class="btn btn-secondary" onclick="cancelarSelecao()">Escolher outra data</button>
        </div>
    </form>

    <script>
        var calendarAgendamento = null;

        document.addEventListener('DOMContentLoaded', function() {
            inicializarCalendario();
        });

        function inicializarCalendario() {
            var calendarEl = document.getElementById('calendar-agendamento');
            if (!calendarEl) return;

            // Destruir calendário anterior se existir
            if (calendarAgendamento) {
                calendarAgendamento.destroy();
            }

            calendarAgendamento = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia'
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth'
                },
                selectable: true,
                dateClick: function(info) {
                    buscarHorarios(info.dateStr);
                }
            });
            calendarAgendamento.render();
        }

        function buscarHorarios(dataStr) {
            fetch('api/horarios_disponiveis.php?data=' + dataStr)
                .then(response => response.json())
                .then(data => {
                    if (data.id_data) {
                        document.getElementById('id_data_input').value = data.id_data;
                        document.getElementById('data_exibicao').value = data.data_formatada;
                        
                        var select = document.getElementById('horario_select');
                        select.innerHTML = '<option value="">Selecione um horário</option>';
                        data.horarios.forEach(h => {
                            var opt = document.createElement('option');
                            opt.value = h.id_horario;
                            opt.textContent = h.horario;
                            select.appendChild(opt);
                        });

                        document.getElementById('calendar-agendamento').style.display = 'none';
                        document.getElementById('form-agendamento').style.display = 'block';

                        if (data.horarios.length === 0) {
                            alert('Todos os horários estão ocupados ou bloqueados nesta data. Escolha outra data.');
                            cancelarSelecao();
                        }
                    } else {
                        alert('Esta data não está disponível para agendamento. Escolha outra data.');
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar horários:', error);
                    alert('Erro ao buscar horários disponíveis.');
                });
        }

        function cancelarSelecao() {
            // Limpar formulário
            document.getElementById('form-agendamento').reset();
            document.getElementById('id_data_input').value = '';
            document.getElementById('data_exibicao').value = '';
            
            // Mostrar calendário novamente
            document.getElementById('calendar-agendamento').style.display = 'block';
            document.getElementById('form-agendamento').style.display = 'none';
            
            // Reinicializar calendário
            inicializarCalendario();
        }


    </script>

    <div class="info-box" style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; margin-top: 24px;">
        <h3 style="margin-top: 0; color: #1e40af;">Informações Importantes</h3>
        <ul style="margin: 0; padding-left: 20px;">
            <li>Confirme seus dados antes de agendar</li>
            <li>Você receberá uma confirmação por email</li>
            <li>Cancelamentos são permitidos com pelo menos 24 horas de antecedência</li>
            <li>Se uma consulta paga for cancelada dentro do prazo, o valor será reembolsado</li>
            <li>O pagamento é realizado após a confirmação da psicóloga</li>
        </ul>
    </div>
</div>

