<?php
/*
 * ARQUIVO: views/dashboard_paciente_agendar.php
 * DESCRIÇÃO: View da aba "Agendar Consulta" do dashboard do paciente.
 *
 * Este arquivo é incluído pelo dashboard_paciente.php quando a aba 'agendar'
 * está ativa. Exibe um calendário interativo (FullCalendar) onde o paciente
 * pode clicar em uma data para ver os horários disponíveis e agendar uma consulta.
 *
 * FLUXO DE AGENDAMENTO:
 *   1. O calendário é renderizado pelo FullCalendar
 *   2. Paciente clica em uma data → buscarHorarios() é chamado
 *   3. buscarHorarios() faz fetch para api/horarios_disponiveis.php?data=YYYY-MM-DD
 *   4. Se a data tiver horários disponíveis, o formulário é exibido
 *   5. Paciente preenche o formulário e envia via POST (acao=agendar_consulta)
 *
 * AÇÃO POST GERADA:
 *   - agendar_consulta → Cria uma nova consulta no banco de dados
 *
 * DEPENDÊNCIAS:
 *   - $pdo: conexão com o banco de dados (herdada do arquivo pai)
 *   - obter_especializacoes(): retorna as especializações disponíveis
 *   - obter_horarios(): retorna os horários padrão
 *   - obter_datas_disponiveis(): retorna as datas com horários livres
 *   - FullCalendar: biblioteca de calendário carregada no dashboard_paciente.php
 */

// Carrega as especializações para o select do formulário
$especializacoes = obter_especializacoes($pdo);
// Carrega os horários padrão (não usados diretamente no HTML, mas disponíveis)
$horarios = obter_horarios($pdo);
// Carrega as datas disponíveis para o calendário
$datas = obter_datas_disponiveis($pdo);
?>

<!-- ═══════════════════════════════════════════════════════════
     SEÇÃO DE AGENDAMENTO
     Calendário interativo + formulário de agendamento
═══════════════════════════════════════════════════════════ -->
<div class="secao">
    <h2 id="titulo-agendamento">Agendar Consulta</h2>
    
    <!-- Container onde o FullCalendar será renderizado pelo JavaScript -->
    <div id="calendar-agendamento" style="margin-bottom: 30px;"></div>

    <!-- Formulário de agendamento: oculto por padrão (display:none) -->
    <!-- Exibido pelo JavaScript após o paciente selecionar uma data com horários disponíveis -->
    <form id="form-agendamento" method="POST" class="formulario-agendamento" style="display: none;">
        <!-- Campo oculto: identifica a ação para o controlador PHP -->
        <input type="hidden" name="acao" id="acao-agendamento" value="agendar_consulta">
        <!-- Campo oculto: ID da data selecionada (preenchido pelo JavaScript após o fetch) -->
        <input type="hidden" name="id_data" id="id_data_input">

        <!-- ── Linha 1: Data selecionada + Especialização ── -->
        <div class="form-row">
            <div class="form-group">
                <label>Data Selecionada</label>
                <!-- readonly: campo somente exibição, preenchido pelo JS com a data formatada -->
                <input type="text" id="data_exibicao" readonly class="form-control">
            </div>
            <div class="form-group">
                <label for="especializacao">Especialização *</label>
                <select id="especializacao" name="id_especializacao" required>
                    <option value="">Selecione uma especialização</option>
                    <?php foreach ($especializacoes as $spec): ?>
                        <!-- value: ID da especialização | texto: nome + preço formatado -->
                        <!-- number_format: 2 casas decimais, vírgula decimal, ponto milhar -->
                        <option value="<?php echo $spec['id_especializacao']; ?>">
                            <?php echo htmlspecialchars($spec['nome']); ?> - R$ <?php echo number_format($spec['preco'], 2, ',', '.'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- ── Linha 2: Modalidade + Horário ── -->
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
                <!-- Populado dinamicamente pelo JavaScript após buscar os horários disponíveis -->
                <select id="horario_select" name="id_horario" required>
                    <option value="">Selecione um horário</option>
                </select>
            </div>
        </div>

        <!-- Botões de ação do formulário -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="submit" class="btn btn-primary" id="btn-submit-agendamento">Agendar Agora</button>
            <!-- Botão para voltar ao calendário e escolher outra data -->
            <button type="button" class="btn btn-secondary" onclick="cancelarSelecao()">Escolher outra data</button>
        </div>
    </form>

    <script>
        // Variável global para armazenar a instância do calendário
        // Permite destruir e recriar o calendário quando necessário
        var calendarAgendamento = null;

        // Inicializa o calendário quando o DOM estiver completamente carregado
        document.addEventListener('DOMContentLoaded', function() {
            inicializarCalendario();
        });

        /*
         * Inicializa (ou reinicializa) o calendário FullCalendar.
         * Destrói a instância anterior se existir para evitar duplicação.
         */
        function inicializarCalendario() {
            var calendarEl = document.getElementById('calendar-agendamento');
            if (!calendarEl) return; // Sai se o elemento não existir

            // Destrói o calendário anterior para evitar duplicação ao reinicializar
            if (calendarAgendamento) {
                calendarAgendamento.destroy();
            }

            // Cria uma nova instância do FullCalendar com as configurações
            calendarAgendamento = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth', // Visão inicial: grade mensal
                locale: 'pt-br',             // Idioma: português do Brasil
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia'
                },
                headerToolbar: {
                    left: 'prev,next today', // Botões de navegação à esquerda
                    center: 'title',          // Título (mês/ano) ao centro
                    right: 'dayGridMonth'     // Seletor de visão à direita
                },
                selectable: true, // Permite selecionar datas clicando
                // Callback chamado quando o usuário clica em uma data
                // info.dateStr contém a data no formato 'YYYY-MM-DD'
                dateClick: function(info) {
                    buscarHorarios(info.dateStr);
                }
            });
            // Renderiza o calendário no elemento DOM
            calendarAgendamento.render();
        }

        /*
         * Busca os horários disponíveis para uma data específica via AJAX.
         * Se houver horários, exibe o formulário de agendamento.
         * Se não houver, exibe um alerta e mantém o calendário visível.
         *
         * @param {string} dataStr - Data no formato 'YYYY-MM-DD'
         */
        function buscarHorarios(dataStr) {
            // Faz requisição GET para o endpoint de horários disponíveis
            // Passa a data como parâmetro de query string
            fetch('api/horarios_disponiveis.php?data=' + dataStr)
                .then(response => response.json()) // Converte a resposta para JSON
                .then(data => {
                    // data.id_data existe apenas se a data estiver disponível no banco
                    if (data.id_data) {
                        // Preenche o campo oculto com o ID da data no banco
                        document.getElementById('id_data_input').value = data.id_data;
                        // Exibe a data formatada no campo de texto (somente leitura)
                        document.getElementById('data_exibicao').value = data.data_formatada;
                        
                        // Popula o select de horários com os horários disponíveis
                        var select = document.getElementById('horario_select');
                        select.innerHTML = '<option value="">Selecione um horário</option>'; // Limpa as opções anteriores
                        data.horarios.forEach(h => {
                            var opt = document.createElement('option');
                            opt.value = h.id_horario;  // Valor enviado no formulário
                            opt.textContent = h.horario; // Texto exibido (ex: "09:00")
                            select.appendChild(opt);
                        });

                        // Oculta o calendário e exibe o formulário de agendamento
                        document.getElementById('calendar-agendamento').style.display = 'none';
                        document.getElementById('form-agendamento').style.display = 'block';

                        // Se não há horários disponíveis, avisa e volta ao calendário
                        if (data.horarios.length === 0) {
                            alert('Todos os horários estão ocupados ou bloqueados nesta data. Escolha outra data.');
                            cancelarSelecao();
                        }
                    } else {
                        // A data não está cadastrada como disponível no banco
                        alert('Esta data não está disponível para agendamento. Escolha outra data.');
                    }
                })
                .catch(error => {
                    // Trata erros de rede ou de parsing do JSON
                    console.error('Erro ao buscar horários:', error);
                    alert('Erro ao buscar horários disponíveis.');
                });
        }

        /*
         * Cancela a seleção de data e volta ao calendário.
         * Limpa o formulário e reinicializa o calendário.
         */
        function cancelarSelecao() {
            // Limpa todos os campos do formulário para os valores padrão
            document.getElementById('form-agendamento').reset();
            // Limpa explicitamente os campos ocultos (reset() pode não limpá-los)
            document.getElementById('id_data_input').value = '';
            document.getElementById('data_exibicao').value = '';
            
            // Exibe o calendário e oculta o formulário
            document.getElementById('calendar-agendamento').style.display = 'block';
            document.getElementById('form-agendamento').style.display = 'none';
            
            // Reinicializa o calendário para garantir que está funcionando corretamente
            inicializarCalendario();
        }
    </script>

    <!-- ── Caixa de Informações Importantes ── -->
    <!-- Borda azul à esquerda indica uma caixa informativa (não um erro) -->
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
