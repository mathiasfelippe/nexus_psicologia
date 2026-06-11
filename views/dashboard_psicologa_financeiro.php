<?php
$transacoes = obter_transacoes_financeiras($pdo, 20);
$receita_mes = obter_receita_mes($pdo);
$receita_ano = obter_receita_ano($pdo);
$receita_mensal = obter_receita_mensal_ano($pdo);
?>

<div class="secao">
    <div class="header-top">
        <h2>Financeiro</h2>
    </div>

    <!-- Métricas Principais -->
    <div class="metricas-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-bottom: 32px; gap: 20px;">
        <div class="metrica-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
            <div class="metrica-label" style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Receita Este Mês</div>
            <div class="metrica-valor" style="font-size: 32px; font-weight: 800; margin-bottom: 8px;">R$ <?php echo number_format($receita_mes, 2, ',', '.'); ?></div>
            <div style="font-size: 12px; opacity: 0.8;">Total de consultas pagas este mês</div>
        </div>
        <div class="metrica-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 24px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);">
            <div class="metrica-label" style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Receita Este Ano</div>
            <div class="metrica-valor" style="font-size: 32px; font-weight: 800; margin-bottom: 8px;">R$ <?php echo number_format($receita_ano, 2, ',', '.'); ?></div>
            <div style="font-size: 12px; opacity: 0.8;">Total acumulado no ano vigente</div>
        </div>
    </div>

    <!-- Gráfico de Receita -->
    <div class="financeiro-card">
        <h3>Receita por Mês</h3>
        <canvas id="revenueChart" height="80"></canvas>
    </div>

    <!-- Histórico de Transações -->
    <div class="financeiro-card">
        <h3>Histórico de Transações</h3>
        
        <div class="tabela-container">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Especialidade</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Status</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transacoes as $transacao): ?>
                        <tr>
                            <td>
                                <div class="paciente-info">
                                    <div class="paciente-nome"><?php echo htmlspecialchars($transacao['paciente_nome']); ?></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($transacao['especializacao']); ?></td>
                            <td style="font-weight: 600; color: #10b981;">R$ <?php echo number_format($transacao['valor'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($transacao['metodo_pagamento']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($transacao['status']); ?>">
                                    <?php echo $transacao['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($transacao['data_pagamento'] ?? date('Y-m-d'))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Gráfico de Receita
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                datasets: [{
                    label: 'Receita (R$)',
                    data: <?php echo json_encode($receita_mensal); ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.05)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
    }
</script>
