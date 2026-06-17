<div class="dashboard-grid">
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
            <h3>Proxima Consulta</h3>
            <?php
            $proxima = null;
            $data_hoje = date('Y-m-d');
            foreach ($consultas as $consulta) {
                if ($consulta['data_calendario'] >= $data_hoje && $consulta['status'] !== 'Cancelada') {
                    $proxima = $consulta;
                    break;
                }
            }
            if ($proxima):
            ?>
                <p class="card-valor"><?php echo formatar_data($proxima['data_calendario']); ?></p>
                <p class="card-desc"><?php echo htmlspecialchars($proxima['especializacao']); ?> as <?php echo substr($proxima['horario'], 0, 5); ?></p>
            <?php else: ?>
                <p class="card-desc">Nenhuma consulta agendada</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-info">
        <div class="card-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="card-content">
            <h3>Consultas Agendadas</h3>
            <p class="card-valor"><?php echo count($consultas); ?></p>
            <p class="card-desc">Total de consultas</p>
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
            <h3>Notificacoes</h3>
            <p class="card-valor"><?php echo $notificacoes_nao_lidas; ?></p>
            <p class="card-desc">Nao lidas</p>
        </div>
    </div>
</div>

<div class="secao">
    <h2>Proximas Consultas</h2>
    <?php if (count($consultas) > 0): ?>
        <div class="consultas-lista">
            <?php
            $data_hoje = date('Y-m-d');
            $proximas = array_filter($consultas, function($c) use ($data_hoje) {
                return $c['data_calendario'] >= $data_hoje && $c['status'] !== 'Cancelada';
            });
            $proximas = array_slice($proximas, 0, 3);

            if (count($proximas) > 0):
                foreach ($proximas as $consulta):
            ?>
                <div class="consulta-item">
                    <div class="consulta-info">
                        <h4><?php echo htmlspecialchars($consulta['especializacao']); ?></h4>
                        <p class="consulta-data">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?php echo formatar_data_hora($consulta['data_calendario'], $consulta['horario']); ?>
                        </p>
                        <p class="consulta-modalidade">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            <?php echo htmlspecialchars($consulta['modalidade']); ?>
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
            else:
            ?>
                <p class="vazio">Nenhuma consulta proxima agendada.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="vazio">Voce ainda nao tem consultas agendadas. <a href="?aba=calendario">Agende pelo calendario!</a></p>
    <?php endif; ?>
</div>

<div class="secao" style="margin-top: 24px;">
    <h2>Historico de Consultas</h2>
    <?php 
    $consultas_passadas = array_filter($consultas, function($c) use ($data_hoje) {
        return $c['data_calendario'] < $data_hoje || $c['status'] === 'Cancelada';
    });
    $consultas_passadas = array_slice($consultas_passadas, 0, 5);
    
    if (count($consultas_passadas) > 0): 
    ?>
        <div class="consultas-lista">
            <?php foreach ($consultas_passadas as $consulta): ?>
                <div class="consulta-item" style="opacity: 0.7;">
                    <div class="consulta-info">
                        <h4><?php echo htmlspecialchars($consulta['especializacao']); ?></h4>
                        <p class="consulta-data">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?php echo formatar_data_hora($consulta['data_calendario'], $consulta['horario']); ?>
                        </p>
                    </div>
                    <div class="consulta-status">
                        <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                            <?php echo htmlspecialchars($consulta['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="vazio">Nenhum historico de consultas.</p>
    <?php endif; ?>
</div>
