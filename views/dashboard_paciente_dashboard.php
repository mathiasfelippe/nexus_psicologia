<!--
    ARQUIVO: views/dashboard_paciente_dashboard.php
    DESCRIÇÃO: View da aba "Início" (home) do dashboard do paciente.

    Este arquivo é incluído pelo dashboard_paciente.php quando a aba 'dashboard'
    está ativa. Exibe três cards de resumo (próxima consulta, total de consultas
    e notificações não lidas), seguidos de duas listas:
      1. Próximas Consultas (até 3 futuras, excluindo canceladas)
      2. Histórico de Consultas (até 5 passadas ou canceladas)

    DEPENDÊNCIAS (variáveis herdadas do arquivo pai dashboard_paciente.php):
      - $consultas: array com todas as consultas do paciente
      - $notificacoes_nao_lidas: inteiro com o total de notificações não lidas
      - formatar_data(): converte 'YYYY-MM-DD' para 'dd/mm/YYYY'
      - formatar_data_hora(): formata data e hora juntas para exibição
-->

<!-- ═══════════════════════════════════════════════════════════
     CARDS DE RESUMO (grid de 3 colunas)
═══════════════════════════════════════════════════════════ -->
<div class="dashboard-grid">

    <!-- ── Card 1: Próxima Consulta ── -->
    <div class="card card-info">
        <div class="card-icon">
            <!-- Ícone de calendário em SVG -->
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
            // Inicializa a variável da próxima consulta como nula
            $proxima = null;
            // Obtém a data atual no formato YYYY-MM-DD para comparação
            $data_hoje = date('Y-m-d');
            // Percorre as consultas em ordem cronológica (assumindo que já estão ordenadas)
            foreach ($consultas as $consulta) {
                // Seleciona a primeira consulta futura (>= hoje) que não esteja cancelada
                if ($consulta['data_calendario'] >= $data_hoje && $consulta['status'] !== 'Cancelada') {
                    $proxima = $consulta;
                    break; // Para o loop ao encontrar a primeira consulta futura
                }
            }
            if ($proxima):
            ?>
                <!-- Exibe a data formatada como dd/mm/YYYY -->
                <p class="card-valor"><?php echo formatar_data($proxima['data_calendario']); ?></p>
                <!-- Exibe a especialidade e o horário (apenas HH:MM, sem segundos) -->
                <p class="card-desc"><?php echo htmlspecialchars($proxima['especializacao']); ?> as <?php echo substr($proxima['horario'], 0, 5); ?></p>
            <?php else: ?>
                <!-- Mensagem exibida quando não há consultas futuras agendadas -->
                <p class="card-desc">Nenhuma consulta agendada</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Card 2: Total de Consultas Agendadas ── -->
    <div class="card card-info">
        <div class="card-icon">
            <!-- Ícone de check em círculo (confirmação) em SVG -->
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="card-content">
            <h3>Consultas Agendadas</h3>
            <!-- count() retorna o total de consultas no array (todas, incluindo passadas) -->
            <p class="card-valor"><?php echo count($consultas); ?></p>
            <p class="card-desc">Total de consultas</p>
        </div>
    </div>

    <!-- ── Card 3: Notificações Não Lidas ── -->
    <div class="card card-info">
        <div class="card-icon">
            <!-- Ícone de sino (notificação) em SVG -->
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
        </div>
        <div class="card-content">
            <h3>Notificacoes</h3>
            <!-- Exibe o número de notificações não lidas -->
            <p class="card-valor"><?php echo $notificacoes_nao_lidas; ?></p>
            <p class="card-desc">Nao lidas</p>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     SEÇÃO: PRÓXIMAS CONSULTAS (até 3)
═══════════════════════════════════════════════════════════ -->
<div class="secao">
    <h2>Proximas Consultas</h2>
    <?php if (count($consultas) > 0): ?>
        <div class="consultas-lista">
            <?php
            // Reutiliza a data de hoje (já definida acima)
            $data_hoje = date('Y-m-d');
            // Filtra apenas consultas futuras (>= hoje) e não canceladas
            // 'use ($data_hoje)' importa a variável externa para dentro da função anônima
            $proximas = array_filter($consultas, function($c) use ($data_hoje) {
                return $c['data_calendario'] >= $data_hoje && $c['status'] !== 'Cancelada';
            });
            // Limita a exibição às 3 primeiras consultas futuras
            $proximas = array_slice($proximas, 0, 3);

            if (count($proximas) > 0):
                foreach ($proximas as $consulta):
            ?>
                <!-- Card de consulta futura -->
                <div class="consulta-item">
                    <div class="consulta-info">
                        <!-- Nome da especialidade (ex: "Ansiedade", "Depressão") -->
                        <h4><?php echo htmlspecialchars($consulta['especializacao']); ?></h4>
                        <!-- Data e hora formatadas juntas -->
                        <p class="consulta-data">
                            <!-- Ícone de calendário em SVG (miniatura 16x16) -->
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <!-- formatar_data_hora() combina data e horário em texto legível -->
                            <?php echo formatar_data_hora($consulta['data_calendario'], $consulta['horario']); ?>
                        </p>
                        <!-- Modalidade da consulta (ex: "Online", "Presencial") -->
                        <p class="consulta-modalidade">
                            <!-- Ícone de telefone em SVG -->
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            <?php echo htmlspecialchars($consulta['modalidade']); ?>
                        </p>
                    </div>
                    <div class="consulta-status">
                        <!-- Badge de status com classe dinâmica (ex: status-agendada, status-confirmada) -->
                        <!-- strtolower() converte o status para minúsculas para corresponder à classe CSS -->
                        <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                            <?php echo htmlspecialchars($consulta['status']); ?>
                        </span>
                    </div>
                </div>
            <?php
                endforeach;
            else:
            ?>
                <!-- Mensagem quando não há consultas futuras -->
                <p class="vazio">Nenhuma consulta proxima agendada.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Mensagem quando o paciente não tem nenhuma consulta cadastrada -->
        <!-- Link para a aba de calendário para facilitar o agendamento -->
        <p class="vazio">Voce ainda nao tem consultas agendadas. <a href="?aba=calendario">Agende pelo calendario!</a></p>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════
     SEÇÃO: HISTÓRICO DE CONSULTAS (até 5 passadas/canceladas)
═══════════════════════════════════════════════════════════ -->
<div class="secao" style="margin-top: 24px;">
    <h2>Historico de Consultas</h2>
    <?php 
    // Filtra consultas passadas (data < hoje) OU canceladas
    // 'use ($data_hoje)' importa a variável para a função anônima
    $consultas_passadas = array_filter($consultas, function($c) use ($data_hoje) {
        return $c['data_calendario'] < $data_hoje || $c['status'] === 'Cancelada';
    });
    // Limita a exibição às 5 consultas mais recentes do histórico
    $consultas_passadas = array_slice($consultas_passadas, 0, 5);
    
    if (count($consultas_passadas) > 0): 
    ?>
        <div class="consultas-lista">
            <?php foreach ($consultas_passadas as $consulta): ?>
                <!-- opacity: 0.7 indica visualmente que são consultas passadas -->
                <div class="consulta-item" style="opacity: 0.7;">
                    <div class="consulta-info">
                        <h4><?php echo htmlspecialchars($consulta['especializacao']); ?></h4>
                        <p class="consulta-data">
                            <!-- Ícone de calendário em SVG (miniatura 16x16) -->
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
                        <!-- Badge de status com classe dinâmica -->
                        <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                            <?php echo htmlspecialchars($consulta['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Mensagem quando não há histórico de consultas -->
        <p class="vazio">Nenhum historico de consultas.</p>
    <?php endif; ?>
</div>
