<!--
    ARQUIVO: views/dashboard_paciente_consultas.php
    DESCRIÇÃO: View da aba "Minhas Consultas" do dashboard do paciente.

    Este arquivo é incluído pelo dashboard_paciente.php quando a aba 'consultas'
    está ativa. Exibe duas tabelas separadas:
      1. Próximas Consultas (data >= hoje): com filtros e ações (pagar/cancelar)
      2. Histórico de Consultas (data < hoje): somente leitura, com opacidade reduzida

    AÇÕES POST GERADAS:
      - cancelar_consulta   → Cancela uma consulta (com confirmação)
      - processar_pagamento → Processa o pagamento via modal de cartão

    DEPENDÊNCIAS (variáveis herdadas do arquivo pai):
      - $consultas: array com todas as consultas do paciente
      - formatar_data(): converte 'YYYY-MM-DD' para 'dd/mm/YYYY'
      - consulta_pode_ser_cancelada_pelo_paciente(): verifica regra das 24h
-->
<div class="secao">
    <h2>Minhas Consultas</h2>
    <!-- Aviso sobre a política de cancelamento (regra das 24 horas) -->
    <div class="aviso-cancelamento-paciente">
        Cancelamentos são permitidos com pelo menos 24 horas de antecedência. Consultas pagas e canceladas dentro do prazo serão reembolsadas.
    </div>

    <?php 
    // Filtra consultas futuras: data >= hoje (usando strtotime para comparação de timestamps)
    // time() retorna o timestamp Unix atual
    $consultas_futuras = array_filter($consultas, function($c) { return strtotime($c['data_calendario']) >= time(); });
    // Filtra consultas passadas: data < hoje
    $consultas_passadas = array_filter($consultas, function($c) { return strtotime($c['data_calendario']) < time(); });
    ?>

    <!-- ── Tabela de Próximas Consultas (exibida apenas se houver consultas futuras) ── -->
    <?php if (count($consultas_futuras) > 0): ?>
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #111827;">Próximas Consultas</h3>
            
            <!-- Botões de filtro por status (filtram as linhas da tabela via JavaScript) -->
            <div class="filtros">
                <!-- Botão "Todas" ativo por padrão (classe 'ativo') -->
                <button class="filtro-btn ativo" onclick="filtrarConsultas('todas')">Todas</button>
                <button class="filtro-btn" onclick="filtrarConsultas('confirmada')">Confirmadas</button>
                <button class="filtro-btn" onclick="filtrarConsultas('pendente')">Pendentes</button>
                <button class="filtro-btn" onclick="filtrarConsultas('cancelada')">Canceladas</button>
            </div>

            <div class="consultas-tabela-container">
                <table class="consultas-tabela">
                    <thead>
                        <tr>
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
                        <?php foreach ($consultas_futuras as $consulta): ?>
                            <!-- data-status: usado pelo JavaScript para filtrar as linhas -->
                            <!-- strtolower() converte para minúsculas (ex: "Confirmada" → "confirmada") -->
                            <tr class="consulta-row" data-status="<?php echo strtolower($consulta['status']); ?>">
                                <!-- Data formatada como dd/mm/YYYY -->
                                <td><?php echo formatar_data($consulta['data_calendario']); ?></td>
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
                                        <?php if ($consulta['status'] === 'Cancelada'): ?>
                                            <!-- Consulta já cancelada: apenas exibe texto informativo -->
                                            <span style="font-size: 12px; color: #ef4444; font-weight: 600;">Cancelada</span>
                                        <?php else: ?>
                                            <?php if ($consulta['status'] === 'Confirmada' && $consulta['pagamento_status'] === 'Pendente'): ?>
                                                <!-- Botão de pagamento: exibido apenas quando confirmada mas não paga -->
                                                <!-- Passa o ID e o valor para o modal de pagamento -->
                                                <button class="btn-pequeno btn-pagar" onclick="abrirPagamento(<?php echo $consulta['id_consulta']; ?>, <?php echo $consulta['valor']; ?>)">
                                                    Pagar
                                                </button>
                                            <?php endif; ?>
                                            <?php if (consulta_pode_ser_cancelada_pelo_paciente($consulta)): ?>
                                                <!-- Formulário de cancelamento com confirmação nativa do navegador -->
                                                <!-- onsubmit="return confirm(...)" impede o envio se o usuário clicar "Cancelar" -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja cancelar esta consulta?');">
                                                    <input type="hidden" name="acao" value="cancelar_consulta">
                                                    <input type="hidden" name="id_consulta" value="<?php echo $consulta['id_consulta']; ?>">
                                                    <button type="submit" class="btn-pequeno btn-cancelar">Cancelar</button>
                                                </form>
                                            <?php else: ?>
                                                <!-- Exibido quando o prazo de 24h para cancelamento já passou -->
                                                <span style="font-size: 12px; color: #9ca3af;">Prazo encerrado</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── Tabela de Histórico de Consultas (passadas) ── -->
    <?php if (count($consultas_passadas) > 0): ?>
        <div style="margin-top: 32px;">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #6b7280;">Histórico de Consultas</h3>
            
            <div class="consultas-tabela-container">
                <table class="consultas-tabela">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Especialização</th>
                            <th>Modalidade</th>
                            <th>Status</th>
                            <th>Pagamento</th>
                            <!-- Sem coluna "Ações" pois consultas passadas não podem ser modificadas -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultas_passadas as $consulta): ?>
                            <!-- opacity: 0.6 e fundo cinza indicam visualmente que são consultas passadas -->
                            <tr class="consulta-row" style="opacity: 0.6; background-color: #f9fafb;">
                                <td><?php echo formatar_data($consulta['data_calendario']); ?></td>
                                <td><?php echo substr($consulta['horario'], 0, 5); ?></td>
                                <td><?php echo htmlspecialchars($consulta['especializacao']); ?></td>
                                <td><?php echo htmlspecialchars($consulta['modalidade']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                                        <?php echo htmlspecialchars($consulta['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="pagamento-badge pagamento-<?php echo strtolower($consulta['pagamento_status']); ?>">
                                        <?php echo htmlspecialchars($consulta['pagamento_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <!-- Estado vazio: exibido quando não há nenhuma consulta (nem futura nem passada) -->
        <div class="vazio-container">
            <!-- Ícone de calendário em SVG -->
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <p>Você ainda não tem consultas agendadas.</p>
            <!-- Link para a aba de calendário para facilitar o agendamento -->
            <a href="?aba=calendario" class="btn btn-primary">Agendar Consulta</a>
        </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL DE PAGAMENTO
     Formulário de cartão de crédito para pagar uma consulta
═══════════════════════════════════════════════════════════ -->
<!-- display:none por padrão; exibido por abrirPagamento() como flex -->
<div id="modal-pagamento" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Realizar Pagamento</h2>
            <!-- Botão X: fecha o modal chamando fecharModal() -->
            <button class="modal-fechar" onclick="fecharModal('modal-pagamento')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Formulário de pagamento enviado via POST -->
            <form id="form-pagamento" method="POST">
                <!-- Campo oculto: ID da consulta a ser paga (preenchido pelo JS) -->
                <input type="hidden" name="id_consulta" id="id_consulta_pagamento">
                <!-- Campo oculto: identifica a ação para o controlador PHP -->
                <input type="hidden" name="acao" value="processar_pagamento">

                <!-- Valor a pagar (exibido como texto, preenchido pelo JS) -->
                <div class="form-group">
                    <label>Valor a Pagar</label>
                    <p class="valor-pagamento" id="valor-pagamento">R$ 0,00</p>
                </div>

                <!-- Número do cartão: maxlength=19 (16 dígitos + 3 espaços) -->
                <div class="form-group">
                    <label for="numero-cartao">Número do Cartão *</label>
                    <input type="text" id="numero-cartao" name="numero_cartao" placeholder="0000 0000 0000 0000" maxlength="19" required>
                </div>

                <!-- Linha com dois campos lado a lado: validade e CVV -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="validade">Validade *</label>
                        <!-- maxlength=5: formato MM/AA -->
                        <input type="text" id="validade" name="validade" placeholder="MM/AA" maxlength="5" required>
                    </div>
                    <div class="form-group">
                        <label for="cvv">CVV *</label>
                        <!-- maxlength=3: código de segurança de 3 dígitos -->
                        <input type="text" id="cvv" name="cvv" placeholder="000" maxlength="3" required>
                    </div>
                </div>

                <!-- Nome do titular do cartão -->
                <div class="form-group">
                    <label for="nome-titular">Nome do Titular *</label>
                    <input type="text" id="nome-titular" name="nome_titular" required>
                </div>

                <!-- btn-bloco: botão de largura total (100%) -->
                <button type="submit" class="btn btn-primary btn-bloco">Confirmar Pagamento</button>
            </form>
        </div>
    </div>
</div>

<script>
    /*
     * Filtra as linhas da tabela de consultas futuras pelo status.
     * Usa o atributo data-status de cada linha para comparar.
     *
     * @param {string} status - Status para filtrar ('todas', 'confirmada', 'pendente', 'cancelada')
     */
    function filtrarConsultas(status) {
        // Seleciona todas as linhas de consulta
        const rows = document.querySelectorAll('.consulta-row');
        // Seleciona todos os botões de filtro
        const buttons = document.querySelectorAll('.filtro-btn');

        // Remove a classe 'ativo' de todos os botões
        buttons.forEach(btn => btn.classList.remove('ativo'));
        // Marca o botão clicado como ativo (event.target é o botão que disparou o evento)
        event.target.classList.add('ativo');

        // Mostra ou oculta cada linha conforme o filtro selecionado
        rows.forEach(row => {
            // Apenas filtra linhas com data-status (linhas de consultas futuras)
            // Linhas do histórico não têm data-status e não são afetadas
            if (row.dataset.status) {
                if (status === 'todas' || row.dataset.status === status) {
                    row.style.display = ''; // Exibe a linha (reseta o display)
                } else {
                    row.style.display = 'none'; // Oculta a linha
                }
            }
        });
    }

    /*
     * Abre o modal de pagamento preenchendo o ID da consulta e o valor.
     *
     * @param {number} id_consulta - ID da consulta a ser paga
     * @param {number} valor       - Valor da consulta em reais
     */
    function abrirPagamento(id_consulta, valor) {
        // Preenche o campo oculto com o ID da consulta
        document.getElementById('id_consulta_pagamento').value = id_consulta;
        // Formata o valor como moeda brasileira (ex: 150.00 → "R$ 150,00")
        // toFixed(2) garante 2 casas decimais; replace('.', ',') troca o separador
        document.getElementById('valor-pagamento').textContent = 'R$ ' + valor.toFixed(2).replace('.', ',');
        // Exibe o modal como flexbox (centraliza o conteúdo)
        document.getElementById('modal-pagamento').style.display = 'flex';
    }

    /*
     * Fecha um modal pelo seu ID.
     * Função genérica reutilizável para qualquer modal desta view.
     *
     * @param {string} id - ID do elemento modal a ser fechado
     */
    function fecharModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    /*
     * Cancela uma consulta criando e enviando um formulário dinamicamente.
     * Pede confirmação antes de cancelar.
     * (Função alternativa ao formulário inline — mantida por compatibilidade)
     *
     * @param {number} id_consulta - ID da consulta a ser cancelada
     */
    function cancelarConsulta(id_consulta) {
        if (confirm('Tem certeza que deseja cancelar esta consulta?')) {
            // Cria um formulário HTML dinamicamente
            const form = document.createElement('form');
            form.method = 'POST';
            // Template literal para inserir o ID da consulta
            form.innerHTML = `
                <input type="hidden" name="acao" value="cancelar_consulta">
                <input type="hidden" name="id_consulta" value="${id_consulta}">
            `;
            // Adiciona ao body e envia imediatamente
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Fecha o modal de pagamento ao clicar fora da área de conteúdo (no overlay)
    window.onclick = function(event) {
        const modal = document.getElementById('modal-pagamento');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>
