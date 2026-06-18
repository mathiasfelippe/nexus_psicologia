<?php
/*
 * ARQUIVO: views/dashboard_psicologa_disponibilidade.php
 * DESCRIÇÃO: View da aba "Disponibilidade" do dashboard da psicóloga.
 *
 * Este arquivo é incluído pelo dashboard_psicologa.php quando a aba 'disponibilidade'
 * está ativa. Permite à psicóloga gerenciar quais datas estão disponíveis para
 * agendamento de consultas pelos pacientes.
 *
 * AÇÕES POST GERADAS:
 *   - adicionar_data      → Adiciona uma nova data ao calendário de disponibilidade
 *   - marcar_indisponivel → Muda o status de uma data de 'Disponivel' para 'Indisponivel'
 *   - marcar_disponivel   → Muda o status de uma data de 'Indisponivel' para 'Disponivel'
 *
 * REGRAS DE NEGÓCIO:
 *   - Datas de segunda a sexta são geradas automaticamente (via config/gerar_datas.php)
 *   - Horários fixos: 09:00 às 17:30
 *   - Feriados nacionais são ignorados automaticamente
 *   - A psicóloga pode bloquear e desbloquear datas manualmente
 *
 * DEPENDÊNCIAS (variáveis herdadas do arquivo pai dashboard_psicologa.php):
 *   - $pdo: conexão com o banco de dados
 *   - obter_datas_disponiveis(): função de config/funcoes.php
 *   - formatar_data(): converte 'YYYY-MM-DD' para 'dd/mm/YYYY'
 */

// Busca apenas as datas com status 'Disponivel' (usada para contagem, se necessário)
$datas_disponiveis = obter_datas_disponiveis($pdo);
// Busca TODAS as datas cadastradas (disponíveis e indisponíveis), ordenadas por data
$todas_datas = $pdo->query("SELECT * FROM datas_disponiveis ORDER BY data_calendario ASC")->fetchAll();

// ── Agrupamento de Datas por Mês ──
// Organiza as datas em um array associativo: ['YYYY-MM' => [data1, data2, ...]]
$datas_por_mes = [];
foreach ($todas_datas as $data) {
    // Extrai o mês no formato 'YYYY-MM' (ex: '2025-01')
    $mes = date('Y-m', strtotime($data['data_calendario']));
    // Inicializa o array do mês se ainda não existir
    if (!isset($datas_por_mes[$mes])) {
        $datas_por_mes[$mes] = [];
    }
    // Adiciona a data ao grupo do mês correspondente
    $datas_por_mes[$mes][] = $data;
}
?>

<div class="secao">
    <h2>Gerenciar Disponibilidade</h2>

    <div class="disponibilidade-container">

        <!-- ── Formulário para Adicionar Nova Data ── -->
        <div class="form-adicionar">
            <h3>Adicionar Nova Data</h3>
            <form method="POST">
                <!-- Campo oculto: identifica a ação para o controlador PHP -->
                <input type="hidden" name="acao" value="adicionar_data">
                <div class="form-group">
                    <label for="data-nova">Data *</label>
                    <!-- min: impede selecionar datas passadas (apenas hoje em diante) -->
                    <input type="date" id="data-nova" name="data" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <!-- Ícone de + (adicionar) -->
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Adicionar Data
                </button>
            </form>
        </div>

        <!-- ── Lista de Todas as Datas Cadastradas ── -->
        <div class="datas-lista">
            <h3>Datas Disponíveis</h3>
            <?php if (count($todas_datas) > 0): ?>
                <!-- Itera sobre os grupos de meses -->
                <?php foreach ($datas_por_mes as $mes => $datas): ?>
                    <div class="mes-grupo">
                        <h4><?php
                            // Cria um objeto DateTime a partir do formato 'Y-m'
                            $data_obj = DateTime::createFromFormat('Y-m', $mes);
                            // Array com os nomes dos meses em português
                            $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
                            // format('m') retorna o número do mês (01-12)
                            // (int) converte para inteiro e -1 ajusta para índice 0-11
                            $mes_nome = $meses[(int)$data_obj->format('m') - 1];
                            // ucfirst() coloca a primeira letra em maiúsculo
                            echo ucfirst($mes_nome) . ' de ' . $data_obj->format('Y');
                        ?></h4>
                        <div class="datas-grid">
                            <!-- Itera sobre as datas do mês atual -->
                            <?php foreach ($datas as $data): ?>
                                <!-- Classe dinâmica: status-disponivel ou status-indisponivel -->
                                <div class="data-item status-<?php echo strtolower($data['status_dia']); ?>">
                                    <div class="data-info">
                                        <!-- Data formatada para exibição (dd/mm/YYYY) -->
                                        <p class="data-valor"><?php echo formatar_data($data['data_calendario']); ?></p>
                                        <!-- Status atual: 'Disponivel' ou 'Indisponivel' -->
                                        <p class="data-status"><?php echo htmlspecialchars($data['status_dia']); ?></p>
                                    </div>
                                    <?php if ($data['status_dia'] === 'Disponivel'): ?>
                                        <!-- Botão para BLOQUEAR a data (marcar como indisponível) -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="marcar_indisponivel">
                                            <input type="hidden" name="id_data" value="<?php echo $data['id_data']; ?>">
                                            <!-- Ícone de X em círculo (bloquear) -->
                                            <button type="submit" class="btn-marcar-indisponivel" title="Marcar como indisponível">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Botão para DESBLOQUEAR a data (marcar como disponível) -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="marcar_disponivel">
                                            <input type="hidden" name="id_data" value="<?php echo $data['id_data']; ?>">
                                            <!-- Ícone de check (desbloquear) -->
                                            <button type="submit" class="btn-marcar-disponivel" title="Marcar como disponível">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Mensagem exibida quando não há nenhuma data cadastrada -->
                <p class="vazio">Nenhuma data cadastrada. Adicione uma para começar!</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Caixa de Dicas ── -->
    <div class="info-box">
        <h3>Dicas de Disponibilidade</h3>
        <ul>
            <li>As datas de segunda a sexta são geradas automaticamente</li>
            <li>Marque datas como indisponíveis quando não puder atender</li>
            <li>Você pode reverter marcando como disponível novamente</li>
            <li>Os horários são fixos (09:00 às 17:30)</li>
            <li>Feriados nacionais são automaticamente ignorados</li>
        </ul>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ESTILOS LOCAIS (específicos desta view)
     Complementam o dashboards.css sem sobrescrever estilos globais
═══════════════════════════════════════════════════════════ -->
<style>
/* Espaçamento entre grupos de meses */
.mes-grupo {
    margin-bottom: 30px;
}

/* Título do grupo de mês (ex: "Janeiro de 2025") */
.mes-grupo h4 {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f0f0; /* Linha separadora abaixo do título */
    text-transform: capitalize;
}

/* Botão circular verde para desbloquear uma data */
.btn-marcar-disponivel {
    position: absolute; /* Posicionado no canto superior direito do card de data */
    top: 8px;
    right: 8px;
    width: 24px;
    height: 24px;
    border-radius: 50%; /* Formato circular */
    background-color: #4caf50; /* Verde */
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease; /* Animação suave no hover */
}

/* Escurece o botão ao passar o mouse */
.btn-marcar-disponivel:hover {
    background-color: #45a049;
}

/* ── Variantes para Modo Escuro ── */
body.dark-mode .mes-grupo h4 {
    color: var(--branco);
    border-bottom-color: rgba(255,255,255,.1); /* Linha separadora semi-transparente */
}
body.dark-mode .btn-marcar-disponivel {
    background-color: var(--verde-agua); /* Cor do tema escuro */
}
body.dark-mode .btn-marcar-disponivel:hover {
    background-color: #5da8a7;
}
</style>
