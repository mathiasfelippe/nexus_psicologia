<!--
    ARQUIVO: views/dashboard_psicologa_consultas.php
    DESCRIÇÃO: View da aba "Consultas" do dashboard da psicóloga.

    Este arquivo é incluído pelo dashboard_psicologa.php quando a aba 'consultas'
    está ativa. Exibe uma tabela com TODAS as consultas do sistema (de todos os
    pacientes), com filtros por status e ações de confirmar/cancelar.

    AÇÕES POST GERADAS:
      - confirmar_consulta → Confirma uma consulta pendente
      - cancelar_consulta  → Cancela uma consulta (requer motivo obrigatório)

    DIFERENÇAS em relação à view do paciente:
      - Exibe o nome e email do paciente em cada linha
      - Permite confirmar consultas pendentes
      - O cancelamento requer motivo obrigatório (via modal)
      - Consultas passadas são exibidas com opacidade reduzida e label "(Passada)"

    DEPENDÊNCIAS (variáveis herdadas do arquivo pai dashboard_psicologa.php):
      - $consultas: array com TODAS as consultas do sistema
      - formatar_data(): converte 'YYYY-MM-DD' para 'dd/mm/YYYY'
-->
<div class="secao">
    <h2>Gerenciar Consultas</h2>

    <?php if (count($consultas) > 0): ?>
        <!-- Botões de filtro por status (filtram as linhas da tabela via JavaScript) -->
        <div class="filtros">
            <button class="filtro-btn ativo" onclick="filtrarConsultas('todas')">Todas</button>
            <button class="filtro-btn" onclick="filtrarConsultas('pendente')">Pendentes</button>
            <button class="filtro-btn" onclick="filtrarConsultas('confirmada')">Confirmadas</button>
            <button class="filtro-btn" onclick="filtrarConsultas('cancelada')">Canceladas</button>
        </div>

        <div class="consultas-tabela-container">
            <table class="consultas-tabela">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Especialização</th>
                        <th>Modalidade</th>
                        <th>Status</th>
                        <th>Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consultas as $consulta): ?>
                        <?php
                        // Verifica se a consulta já ocorreu (data < hoje)
                        // strtotime() converte a data para timestamp Unix para comparação
                        $passada = strtotime($consulta['data_calendario']) < time();
                        ?>
                        <!-- data-status: usado pelo JavaScript para filtrar as linhas -->
                        <!-- Consultas passadas recebem opacidade reduzida e fundo cinza -->
                        <tr class="consulta-row" data-status="<?php echo strtolower($consulta['status']); ?>" style="<?php echo $passada ? 'opacity: 0.6; background-color: #f9fafb;' : ''; ?>">
                            <!-- Coluna Paciente: nome + email -->
                            <td>
                                <div class="paciente-info">
                                    <p class="paciente-nome"><?php echo htmlspecialchars($consulta['paciente_nome']); ?></p>
                                    <p class="paciente-email"><?php echo htmlspecialchars($consulta['paciente_email']); ?></p>
                                </div>
                            </td>
                            <!-- Data formatada + label "(Passada)" para consultas antigas -->
                            <td><?php echo formatar_data($consulta['data_calendario']); ?><?php if ($passada) echo ' <span style="color: #9ca3af; font-size: 11px;">(Passada)</span>'; ?></td>
                            <!-- Horário: apenas HH:MM (substr remove os segundos) -->
                            <td><?php echo substr($consulta['horario'], 0, 5); ?></td>
                            <td><?php echo htmlspecialchars($consulta['especializacao']); ?></td>
                            <td><?php echo htmlspecialchars($consulta['modalidade']); ?></td>
                            <td>
                                <!-- Badge de status com cor dinâmica via classe CSS -->
                                <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                                    <?php echo htmlspecialchars($consulta['status']); ?>
                                </span>
                            </td>
                            <td>
                                <!-- Badge de status de pagamento com cor dinâmica -->
                                <span class="pagamento-badge pagamento-<?php echo strtolower($consulta['pagamento_status']); ?>">
                                    <?php echo htmlspecialchars($consulta['pagamento_status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="acoes-btn">
                                    <?php if ($consulta['status'] === 'Pendente' && !$passada): ?>
                                        <!-- Botão de confirmar: apenas para consultas pendentes e futuras -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="confirmar_consulta">
                                            <input type="hidden" name="id_consulta" value="<?php echo $consulta['id_consulta']; ?>">
                                            <!-- Ícone de check (confirmar) -->
                                            <button type="submit" class="btn-pequeno btn-confirmar" title="Confirmar">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($consulta['status'] !== 'Cancelada'): ?>
                                        <!-- Botão de cancelar: exibido para todas as consultas não canceladas -->
                                        <!-- Abre o modal de cancelamento com o ID da consulta -->
                                        <button type="button" class="btn-pequeno btn-cancelar" title="Cancelar" onclick="abrirModalCancelarConsulta(<?php echo $consulta['id_consulta']; ?>)">
                                            <!-- Ícone de X em círculo (cancelar) -->
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                                <line x1="9" y1="9" x2="15" y2="15"></line>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- Estado vazio: exibido quando não há nenhuma consulta no sistema -->
        <div class="vazio-container">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p>Nenhuma consulta agendada.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL DE CANCELAMENTO COM MOTIVO OBRIGATÓRIO
     Exibido ao clicar no botão de cancelar de uma consulta
═══════════════════════════════════════════════════════════ -->
<!-- Oculto por padrão; exibido pela classe CSS 'show' adicionada pelo JavaScript -->
<div id="modalCancelarConsulta" class="modal">
    <div class="modal-conteudo" style="max-width: 480px;">
        <div class="modal-header">
            <div>
                <!-- Badge vermelho indicando ação destrutiva -->
                <span class="modal-badge-acao" style="background:rgba(239,68,68,0.12);color:var(--danger);">Cancelamento</span>
                <h2>Cancelar Consulta</h2>
            </div>
            <!-- Botão X para fechar o modal sem cancelar -->
            <button class="modal-fechar" onclick="fecharModalCancelarConsulta()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Ícone de X em círculo grande (visual de alerta) -->
            <div class="modal-icone-msg">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            </div>
            <p style="text-align:center; font-size:15px; color:var(--neutral-700); margin-bottom:20px;">Para cancelar esta consulta, informe obrigatoriamente o motivo abaixo.</p>
            <!-- Formulário de cancelamento com validação JavaScript antes do envio -->
            <!-- onsubmit="return validarCancelamentoPsicologa()" impede o envio se o motivo estiver vazio -->
            <form method="POST" id="formCancelarConsultaPsicologa" onsubmit="return validarCancelamentoPsicologa()">
                <!-- Campo oculto: identifica a ação para o controlador PHP -->
                <input type="hidden" name="acao" value="cancelar_consulta">
                <!-- Campo oculto: ID da consulta a ser cancelada (preenchido pelo JS) -->
                <input type="hidden" name="id_consulta" id="idConsultaCancelar">
                <div class="form-group">
                    <label for="motivoCancelamentoPsicologa">Motivo do Cancelamento *</label>
                    <!-- Textarea para o motivo: enviado como 'comentario' para o PHP -->
                    <!-- resize:vertical permite que o usuário redimensione verticalmente -->
                    <textarea name="comentario" id="motivoCancelamentoPsicologa" rows="3" placeholder="Informe o motivo do cancelamento para o paciente..." style="resize:vertical;"></textarea>
                    <!-- Mensagem de erro: exibida pelo JS se o motivo estiver vazio -->
                    <p id="erroCancelamento" class="form-erro" style="display:none;">O motivo do cancelamento e obrigatorio.</p>
                </div>
                <div class="modal-acoes">
                    <button type="button" class="btn btn-modal-fechar" onclick="fecharModalCancelarConsulta()">Voltar</button>
                    <button type="submit" class="btn btn-cancelar btn-modal-acao">Confirmar Cancelamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    /*
     * Filtra as linhas da tabela de consultas pelo status.
     * Usa o atributo data-status de cada linha para comparar.
     *
     * @param {string} status - Status para filtrar ('todas', 'pendente', 'confirmada', 'cancelada')
     */
    function filtrarConsultas(status) {
        const rows = document.querySelectorAll('.consulta-row');
        const buttons = document.querySelectorAll('.filtro-btn');

        // Remove a classe 'ativo' de todos os botões
        buttons.forEach(btn => btn.classList.remove('ativo'));
        // Marca o botão clicado como ativo (event.target é o botão que disparou o evento)
        event.target.classList.add('ativo');

        // Mostra ou oculta cada linha conforme o filtro selecionado
        rows.forEach(row => {
            if (status === 'todas' || row.dataset.status === status) {
                row.style.display = ''; // Exibe a linha (reseta o display)
            } else {
                row.style.display = 'none'; // Oculta a linha
            }
        });
    }

    /*
     * Abre o modal de cancelamento preenchendo o ID da consulta.
     * Limpa o campo de motivo e oculta mensagens de erro anteriores.
     *
     * @param {number} idConsulta - ID da consulta a ser cancelada
     */
    function abrirModalCancelarConsulta(idConsulta) {
        // Preenche o campo oculto com o ID da consulta
        document.getElementById('idConsultaCancelar').value = idConsulta;
        // Limpa o campo de motivo para evitar que o motivo anterior apareça
        document.getElementById('motivoCancelamentoPsicologa').value = '';
        // Oculta a mensagem de erro (pode estar visível de uma tentativa anterior)
        document.getElementById('erroCancelamento').style.display = 'none';
        // Exibe o modal adicionando a classe 'show'
        document.getElementById('modalCancelarConsulta').classList.add('show');
    }

    /*
     * Fecha o modal de cancelamento removendo a classe 'show'.
     */
    function fecharModalCancelarConsulta() {
        document.getElementById('modalCancelarConsulta').classList.remove('show');
    }

    /*
     * Valida o formulário de cancelamento antes do envio.
     * Impede o envio se o motivo estiver vazio.
     * Chamado pelo onsubmit do formulário.
     *
     * @returns {boolean} - true se válido (permite envio), false se inválido (bloqueia envio)
     */
    function validarCancelamentoPsicologa() {
        // trim() remove espaços em branco das extremidades
        const motivo = document.getElementById('motivoCancelamentoPsicologa').value.trim();
        if (!motivo) {
            // Exibe a mensagem de erro e bloqueia o envio
            document.getElementById('erroCancelamento').style.display = 'block';
            return false; // Retornar false impede o envio do formulário
        }
        return true; // Permite o envio do formulário
    }

    // Fecha o modal ao clicar fora da área de conteúdo (no overlay escuro)
    document.addEventListener('click', function(e) {
        var modal = document.getElementById('modalCancelarConsulta');
        // e.target === modal: verifica se o clique foi no overlay (não no conteúdo)
        if (modal && e.target === modal) {
            modal.classList.remove('show');
        }
    });
</script>
