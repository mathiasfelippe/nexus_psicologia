<div class="dashboard-grid">
    <div class="card card-info">
        <div class="card-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="card-content">
            <h3>Consultas Agendadas</h3>
            <?php
            $data_hoje = date('Y-m-d');
            $proximas = array_filter($consultas, function($c) use ($data_hoje) {
                return $c['data_calendario'] >= $data_hoje && $c['status'] !== 'Cancelada';
            });
            ?>
            <p class="card-valor"><?php echo count($proximas); ?></p>
            <p class="card-desc">Próximas consultas</p>
        </div>
    </div>

    <div class="card card-info">
        <div class="card-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </div>
        <div class="card-content">
            <h3>Pacientes Atendidos</h3>
            <?php
            $pacientes_unicos = array_unique(array_column($consultas, 'id_paciente'));
            ?>
            <p class="card-valor"><?php echo count($pacientes_unicos); ?></p>
            <p class="card-desc">Total de pacientes</p>
        </div>
    </div>

    <div class="card card-info">
        <div class="card-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
        </div>
        <div class="card-content">
            <h3>Notificações</h3>
            <p class="card-valor"><?php echo $notificacoes_nao_lidas; ?></p>
            <p class="card-desc">Não lidas</p>
        </div>
    </div>

    <div class="card card-info">
        <div class="card-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </div>
        <div class="card-content">
            <h3>Consultas Confirmadas</h3>
            <?php
            $confirmadas = array_filter($consultas, function($c) {
                return $c['status'] === 'Confirmada';
            });
            ?>
            <p class="card-valor"><?php echo count($confirmadas); ?></p>
            <p class="card-desc">Total confirmadas</p>
        </div>
    </div>
</div>

<div class="secao">
    <h2>Próximas Consultas</h2>
    <?php if (count($proximas) > 0): ?>
        <div class="consultas-lista">
            <?php
            $proximas_array = array_slice(array_values($proximas), 0, 5);
            foreach ($proximas_array as $consulta):
            ?>
                <div class="consulta-item">
                    <div class="consulta-info">
                        <h4><?php echo htmlspecialchars($consulta['paciente_nome']); ?></h4>
                        <p class="consulta-data">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?php echo formatar_data_hora($consulta['data_calendario'], $consulta['horario']); ?>
                        </p>
                        <p class="consulta-especialidade">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <?php echo htmlspecialchars($consulta['especializacao']); ?>
                        </p>
                    </div>
                    <div class="consulta-status">
                        <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                            <?php echo htmlspecialchars($consulta['status']); ?>
                        </span>
                    </div>
                </div>
            <?php
            endforeach;
            ?>
        </div>
    <?php else: ?>
        <p class="vazio">Nenhuma consulta próxima agendada.</p>
    <?php endif; ?>
</div>
