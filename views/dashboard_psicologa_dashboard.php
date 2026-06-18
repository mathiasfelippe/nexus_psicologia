<!--
    ARQUIVO: views/dashboard_psicologa_dashboard.php
    DESCRIÇÃO: View da aba "Início" (home) do dashboard da psicóloga.

    Este arquivo é incluído pelo dashboard_psicologa.php quando a aba 'dashboard'
    está ativa. Exibe quatro cards de resumo e uma lista das próximas consultas.

    CARDS EXIBIDOS:
      1. Consultas Agendadas: total de consultas futuras não canceladas
      2. Pacientes Atendidos: total de pacientes únicos com consultas
      3. Notificações: total de notificações não lidas
      4. Consultas Confirmadas: total de consultas com status 'Confirmada'

    DEPENDÊNCIAS (variáveis herdadas do arquivo pai dashboard_psicologa.php):
      - $consultas: array com TODAS as consultas do sistema
      - $notificacoes_nao_lidas: inteiro com o total de notificações não lidas
      - formatar_data_hora(): formata data e hora juntas para exibição
-->

<!-- ═══════════════════════════════════════════════════════════
     CARDS DE RESUMO (grid de 4 colunas)
═══════════════════════════════════════════════════════════ -->
<div class="dashboard-grid">

    <!-- ── Card 1: Consultas Agendadas (futuras, não canceladas) ── -->
    <div class="card card-info">
        <div class="card-icon">
            <!-- Ícone de check em círculo (confirmação) -->
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="card-content">
            <h3>Consultas Agendadas</h3>
            <?php
            // Obtém a data atual no formato YYYY-MM-DD para comparação
            $data_hoje = date('Y-m-d');
            // Filtra consultas futuras (>= hoje) e não canceladas
            // 'use ($data_hoje)' importa a variável para a função anônima
            $proximas = array_filter($consultas, function($c) use ($data_hoje) {
                return $c['data_calendario'] >= $data_hoje && $c['status'] !== 'Cancelada';
            });
            ?>
            <!-- count() retorna o total de consultas futuras -->
            <p class="card-valor"><?php echo count($proximas); ?></p>
            <p class="card-desc">Próximas consultas</p>
        </div>
    </div>

    <!-- ── Card 2: Pacientes Únicos Atendidos ── -->
    <div class="card card-info">
        <div class="card-icon">
            <!-- Ícone de pessoa/usuário -->
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </div>
        <div class="card-content">
            <h3>Pacientes Atendidos</h3>
            <?php
            // array_column() extrai apenas os valores de 'id_paciente' de todas as consultas
            // array_unique() remove IDs duplicados (mesmo paciente com múltiplas consultas)
            // Resultado: array com IDs únicos de pacientes que têm pelo menos uma consulta
            $pacientes_unicos = array_unique(array_column($consultas, 'id_paciente'));
            ?>
            <p class="card-valor"><?php echo count($pacientes_unicos); ?></p>
            <p class="card-desc">Total de pacientes</p>
        </div>
    </div>

    <!-- ── Card 3: Notificações Não Lidas ── -->
    <div class="card card-info">
        <div class="card-icon">
            <!-- Ícone de sino (notificação) -->
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
        </div>
        <div class="card-content">
            <h3>Notificações</h3>
            <!-- Variável herdada do arquivo pai com o total de notificações não lidas -->
            <p class="card-valor"><?php echo $notificacoes_nao_lidas; ?></p>
            <p class="card-desc">Não lidas</p>
        </div>
    </div>

    <!-- ── Card 4: Total de Consultas Confirmadas ── -->
    <div class="card card-info">
        <div class="card-icon">
            <!-- Ícone de calendário -->
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
            // Filtra apenas consultas com status exatamente igual a 'Confirmada'
            // (independente de data — inclui passadas e futuras)
            $confirmadas = array_filter($consultas, function($c) {
                return $c['status'] === 'Confirmada';
            });
            ?>
            <p class="card-valor"><?php echo count($confirmadas); ?></p>
            <p class="card-desc">Total confirmadas</p>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     SEÇÃO: PRÓXIMAS CONSULTAS (até 5)
═══════════════════════════════════════════════════════════ -->
<div class="secao">
    <h2>Próximas Consultas</h2>
    <?php if (count($proximas) > 0): ?>
        <div class="consultas-lista">
            <?php
            // array_values() reindexar o array após o array_filter() (que mantém os índices originais)
            // array_slice() limita a exibição às 5 primeiras consultas futuras
            $proximas_array = array_slice(array_values($proximas), 0, 5);
            foreach ($proximas_array as $consulta):
            ?>
                <div class="consulta-item">
                    <div class="consulta-info">
                        <!-- Nome do paciente (diferente do dashboard do paciente que mostra a especialização) -->
                        <h4><?php echo htmlspecialchars($consulta['paciente_nome']); ?></h4>
                        <!-- Data e hora formatadas juntas -->
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
                        <!-- Especialização da consulta -->
                        <p class="consulta-especialidade">
                            <!-- Ícone de pessoa/usuário em SVG (miniatura 16x16) -->
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <?php echo htmlspecialchars($consulta['especializacao']); ?>
                        </p>
                    </div>
                    <div class="consulta-status">
                        <!-- Badge de status com classe dinâmica (ex: status-confirmada, status-pendente) -->
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
        <!-- Mensagem exibida quando não há consultas futuras -->
        <p class="vazio">Nenhuma consulta próxima agendada.</p>
    <?php endif; ?>
</div>
