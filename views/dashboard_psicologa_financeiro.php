<?php
/*
 * ARQUIVO: views/dashboard_psicologa_financeiro.php
 * DESCRIÇÃO: View da aba "Financeiro" do dashboard da psicóloga.
 *
 * Este arquivo é incluído pelo dashboard_psicologa.php quando a aba 'financeiro'
 * está ativa. Exibe métricas de receita (mês e ano), um gráfico de linha mensal
 * gerado pelo Chart.js, e o histórico das últimas 20 transações.
 *
 * DEPENDÊNCIAS:
 *   - $pdo: conexão com o banco de dados (herdada do arquivo pai)
 *   - obter_transacoes_financeiras(): retorna as últimas N transações
 *   - obter_receita_mes(): retorna o total de receita do mês atual
 *   - obter_receita_ano(): retorna o total de receita do ano atual
 *   - obter_receita_mensal_ano(): retorna array com receita de cada mês do ano
 *   - Chart.js: biblioteca de gráficos carregada no dashboard_psicologa.php
 */

// Carrega as últimas 20 transações financeiras para o histórico
$transacoes = obter_transacoes_financeiras($pdo, 20);
// Receita total do mês atual (soma de consultas pagas)
$receita_mes = obter_receita_mes($pdo);
// Receita total acumulada no ano vigente
$receita_ano = obter_receita_ano($pdo);
// Array com 12 valores (Jan-Dez) para o gráfico de linha
$receita_mensal = obter_receita_mensal_ano($pdo);
?>

<!-- ═══════════════════════════════════════════════════════════
     SEÇÃO FINANCEIRO
     Métricas, gráfico e histórico de transações
═══════════════════════════════════════════════════════════ -->
<div class="secao">
    <div class="header-top">
        <h2>Financeiro</h2>
    </div>

    <!-- ── Métricas Principais: Receita do Mês e do Ano ── -->
    <!-- auto-fit: cria colunas que se expandem para preencher o espaço disponível -->
    <div class="metricas-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-bottom: 32px; gap: 20px;">
        <!-- Card de Receita do Mês (gradiente roxo-azul) -->
        <div class="metrica-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
            <div class="metrica-label" style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Receita Este Mês</div>
            <!-- number_format: 2 casas decimais, vírgula decimal, ponto milhar -->
            <div class="metrica-valor" style="font-size: 32px; font-weight: 800; margin-bottom: 8px;">R$ <?php echo number_format($receita_mes, 2, ',', '.'); ?></div>
            <div style="font-size: 12px; opacity: 0.8;">Total de consultas pagas este mês</div>
        </div>
        <!-- Card de Receita do Ano (gradiente rosa-vermelho) -->
        <div class="metrica-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 24px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);">
            <div class="metrica-label" style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Receita Este Ano</div>
            <div class="metrica-valor" style="font-size: 32px; font-weight: 800; margin-bottom: 8px;">R$ <?php echo number_format($receita_ano, 2, ',', '.'); ?></div>
            <div style="font-size: 12px; opacity: 0.8;">Total acumulado no ano vigente</div>
        </div>
    </div>

    <!-- ── Gráfico de Receita por Mês ── -->
    <div class="financeiro-card">
        <h3>Receita por Mês</h3>
        <!-- Canvas onde o Chart.js renderiza o gráfico de linha -->
        <!-- height="80" define a proporção de altura (relativa à largura) -->
        <canvas id="revenueChart" height="80"></canvas>
    </div>

    <!-- ── Histórico de Transações ── -->
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
                                    <!-- htmlspecialchars() previne XSS (injeção de HTML) -->
                                    <div class="paciente-nome"><?php echo htmlspecialchars($transacao['paciente_nome']); ?></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($transacao['especializacao']); ?></td>
                            <!-- Valor em verde para indicar receita positiva -->
                            <td style="font-weight: 600; color: #10b981;">R$ <?php echo number_format($transacao['valor'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($transacao['metodo_pagamento']); ?></td>
                            <td>
                                <!-- Badge de status com classe CSS dinâmica (ex: status-pago, status-pendente) -->
                                <!-- strtolower() converte o status para minúsculas para corresponder à classe CSS -->
                                <span class="status-badge status-<?php echo strtolower($transacao['status']); ?>">
                                    <?php echo $transacao['status']; ?>
                                </span>
                            </td>
                            <!-- Usa data_pagamento se disponível, senão usa data_criacao -->
                            <!-- O operador ?? retorna o segundo valor se o primeiro for null -->
                            <td><?php echo date('d/m/Y', strtotime($transacao['data_pagamento'] ?? $transacao['data_criacao'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // ── Inicialização do Gráfico de Receita (Chart.js) ──

    // Obtém o elemento canvas onde o gráfico será renderizado
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        // Cria um novo gráfico de linha no canvas
        new Chart(revenueCtx, {
            type: 'line', // Tipo: gráfico de linha
            data: {
                // Rótulos do eixo X: meses do ano
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                datasets: [{
                    label: 'Receita (R$)',
                    // json_encode() converte o array PHP para JSON (formato JavaScript)
                    // $receita_mensal é um array com 12 valores numéricos
                    data: <?php echo json_encode($receita_mensal); ?>,
                    borderColor: '#6366f1',                    // Cor da linha (roxo)
                    backgroundColor: 'rgba(99, 102, 241, 0.05)', // Preenchimento abaixo da linha (quase transparente)
                    tension: 0.4,                              // Suavização da linha (0 = reto, 1 = muito curvo)
                    fill: true,                                // Preenche a área abaixo da linha
                    pointRadius: 4,                            // Tamanho dos pontos de dados
                    pointBackgroundColor: '#6366f1',           // Cor de preenchimento dos pontos
                    pointBorderColor: '#fff',                  // Cor da borda dos pontos (branco)
                    pointBorderWidth: 2                        // Espessura da borda dos pontos
                }]
            },
            options: {
                responsive: true,          // Redimensiona automaticamente com o container
                maintainAspectRatio: true, // Mantém a proporção de aspecto definida pelo canvas
                plugins: {
                    legend: {
                        display: false     // Oculta a legenda (desnecessária com um único dataset)
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true, // Eixo Y começa em 0 (não em um valor mínimo dos dados)
                        ticks: {
                            // Formata os valores do eixo Y como moeda brasileira
                            // Ex: 1500 → "R$ 1.500"
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
