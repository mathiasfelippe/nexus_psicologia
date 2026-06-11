<div class="secao">
    <h2>Gerenciar Consultas</h2>

    <?php if (count($consultas) > 0): ?>
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
                        <?php $passada = strtotime($consulta['data_calendario']) < time(); ?>
                        <tr class="consulta-row" data-status="<?php echo strtolower($consulta['status']); ?>" style="<?php echo $passada ? 'opacity: 0.6; background-color: #f9fafb;' : ''; ?>">
                            <td>
                                <div class="paciente-info">
                                    <p class="paciente-nome"><?php echo htmlspecialchars($consulta['paciente_nome']); ?></p>
                                    <p class="paciente-email"><?php echo htmlspecialchars($consulta['paciente_email']); ?></p>
                                </div>
                            </td>
                            <td><?php echo formatar_data($consulta['data_calendario']); ?><?php if ($passada) echo ' <span style="color: #9ca3af; font-size: 11px;">(Passada)</span>'; ?></td>
                            <td><?php echo $consulta['horario']; ?></td>
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
                                    <?php if ($consulta['status'] === 'Pendente' && !$passada): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="confirmar_consulta">
                                            <input type="hidden" name="id_consulta" value="<?php echo $consulta['id_consulta']; ?>">
                                            <button type="submit" class="btn-pequeno btn-confirmar" title="Confirmar">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($consulta['status'] !== 'Cancelada'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja cancelar esta consulta?');">
                                            <input type="hidden" name="acao" value="cancelar_consulta">
                                            <input type="hidden" name="id_consulta" value="<?php echo $consulta['id_consulta']; ?>">
                                            <button type="submit" class="btn-pequeno btn-cancelar" title="Cancelar">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="vazio-container">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p>Nenhuma consulta agendada.</p>
        </div>
    <?php endif; ?>
</div>

<script>
function filtrarConsultas(status) {
    const rows = document.querySelectorAll('.consulta-row');
    const buttons = document.querySelectorAll('.filtro-btn');

    buttons.forEach(btn => btn.classList.remove('ativo'));
    event.target.classList.add('ativo');

    rows.forEach(row => {
        if (status === 'todas' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
