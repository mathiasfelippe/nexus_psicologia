<div class="secao">
    <h2>Minhas Consultas</h2>
    <div class="aviso-cancelamento-paciente">
        Cancelamentos são permitidos com pelo menos 24 horas de antecedência. Consultas pagas e canceladas dentro do prazo serão reembolsadas.
    </div>

    <?php 
    $consultas_futuras = array_filter($consultas, function($c) { return strtotime($c['data_calendario']) >= time(); });
    $consultas_passadas = array_filter($consultas, function($c) { return strtotime($c['data_calendario']) < time(); });
    ?>

    <?php if (count($consultas_futuras) > 0): ?>
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #111827;">Próximas Consultas</h3>
            
            <div class="filtros">
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
                            <tr class="consulta-row" data-status="<?php echo strtolower($consulta['status']); ?>">
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
                                <td>
                                    <div class="acoes-btn">
                                        <?php if ($consulta['status'] === 'Cancelada'): ?>
                                            <span style="font-size: 12px; color: #ef4444; font-weight: 600;">Cancelada</span>
                                        <?php else: ?>
                                            <?php if ($consulta['status'] === 'Confirmada' && $consulta['pagamento_status'] === 'Pendente'): ?>
                                                <button class="btn-pequeno btn-pagar" onclick="abrirPagamento(<?php echo $consulta['id_consulta']; ?>, <?php echo $consulta['valor']; ?>)">
                                                    Pagar
                                                </button>
                                            <?php endif; ?>
                                            <?php if (consulta_pode_ser_cancelada_pelo_paciente($consulta)): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja cancelar esta consulta?');">
                                                    <input type="hidden" name="acao" value="cancelar_consulta">
                                                    <input type="hidden" name="id_consulta" value="<?php echo $consulta['id_consulta']; ?>">
                                                    <button type="submit" class="btn-pequeno btn-cancelar">Cancelar</button>
                                                </form>
                                            <?php else: ?>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultas_passadas as $consulta): ?>
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
        <div class="vazio-container">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <p>Você ainda não tem consultas agendadas.</p>
            <a href="?aba=calendario" class="btn btn-primary">Agendar Consulta</a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Pagamento -->
<div id="modal-pagamento" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Realizar Pagamento</h2>
            <button class="modal-fechar" onclick="fecharModal('modal-pagamento')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="form-pagamento" method="POST">
                <input type="hidden" name="id_consulta" id="id_consulta_pagamento">
                <input type="hidden" name="acao" value="processar_pagamento">

                <div class="form-group">
                    <label>Valor a Pagar</label>
                    <p class="valor-pagamento" id="valor-pagamento">R$ 0,00</p>
                </div>

                <div class="form-group">
                    <label for="numero-cartao">Número do Cartão *</label>
                    <input type="text" id="numero-cartao" name="numero_cartao" placeholder="0000 0000 0000 0000" maxlength="19" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="validade">Validade *</label>
                        <input type="text" id="validade" name="validade" placeholder="MM/AA" maxlength="5" required>
                    </div>
                    <div class="form-group">
                        <label for="cvv">CVV *</label>
                        <input type="text" id="cvv" name="cvv" placeholder="000" maxlength="3" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nome-titular">Nome do Titular *</label>
                    <input type="text" id="nome-titular" name="nome_titular" required>
                </div>

                <button type="submit" class="btn btn-primary btn-bloco">Confirmar Pagamento</button>
            </form>
        </div>
    </div>
</div>

<script>
function filtrarConsultas(status) {
    const rows = document.querySelectorAll('.consulta-row');
    const buttons = document.querySelectorAll('.filtro-btn');

    buttons.forEach(btn => btn.classList.remove('ativo'));
    event.target.classList.add('ativo');

    rows.forEach(row => {
        // Apenas filtrar linhas que têm data-status (próximas consultas)
        if (row.dataset.status) {
            if (status === 'todas' || row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

function abrirPagamento(id_consulta, valor) {
    document.getElementById('id_consulta_pagamento').value = id_consulta;
    document.getElementById('valor-pagamento').textContent = 'R$ ' + valor.toFixed(2).replace('.', ',');
    document.getElementById('modal-pagamento').style.display = 'flex';
}

function fecharModal(id) {
    document.getElementById(id).style.display = 'none';
}

function cancelarConsulta(id_consulta) {
    if (confirm('Tem certeza que deseja cancelar esta consulta?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="acao" value="cancelar_consulta">
            <input type="hidden" name="id_consulta" value="${id_consulta}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}


// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('modal-pagamento');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

