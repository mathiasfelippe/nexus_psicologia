<?php
$datas_disponiveis = obter_datas_disponiveis($pdo);
$todas_datas = $pdo->query("SELECT * FROM datas_disponiveis ORDER BY data_calendario ASC")->fetchAll();

// Agrupar datas por mês
$datas_por_mes = [];
foreach ($todas_datas as $data) {
    $mes = date('Y-m', strtotime($data['data_calendario']));
    if (!isset($datas_por_mes[$mes])) {
        $datas_por_mes[$mes] = [];
    }
    $datas_por_mes[$mes][] = $data;
}
?>

<div class="secao">
    <h2>Gerenciar Disponibilidade</h2>

    <div class="disponibilidade-container">
        <div class="form-adicionar">
            <h3>Adicionar Nova Data</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="adicionar_data">
                <div class="form-group">
                    <label for="data-nova">Data *</label>
                    <input type="date" id="data-nova" name="data" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Adicionar Data
                </button>
            </form>
        </div>

        <div class="datas-lista">
            <h3>Datas Disponíveis</h3>
            <?php if (count($todas_datas) > 0): ?>
                <?php foreach ($datas_por_mes as $mes => $datas): ?>
                    <div class="mes-grupo">
                        <h4><?php 
                            $data_obj = DateTime::createFromFormat('Y-m', $mes);
                            $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
                            $mes_nome = $meses[(int)$data_obj->format('m') - 1];
                            echo ucfirst($mes_nome) . ' de ' . $data_obj->format('Y');
                        ?></h4>
                        <div class="datas-grid">
                            <?php foreach ($datas as $data): ?>
                                <div class="data-item status-<?php echo strtolower($data['status_dia']); ?>">
                                    <div class="data-info">
                                        <p class="data-valor"><?php echo formatar_data($data['data_calendario']); ?></p>
                                        <p class="data-status"><?php echo htmlspecialchars($data['status_dia']); ?></p>
                                    </div>
                                    <?php if ($data['status_dia'] === 'Disponivel'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="marcar_indisponivel">
                                            <input type="hidden" name="id_data" value="<?php echo $data['id_data']; ?>">
                                            <button type="submit" class="btn-marcar-indisponivel" title="Marcar como indisponível">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="marcar_disponivel">
                                            <input type="hidden" name="id_data" value="<?php echo $data['id_data']; ?>">
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
                <p class="vazio">Nenhuma data cadastrada. Adicione uma para começar!</p>
            <?php endif; ?>
        </div>
    </div>

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

<style>
.mes-grupo {
    margin-bottom: 30px;
}

.mes-grupo h4 {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f0f0;
    text-transform: capitalize;
}

.btn-marcar-disponivel {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #4caf50;
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-marcar-disponivel:hover {
    background-color: #45a049;
}

body.dark-mode .mes-grupo h4 {
    color: var(--branco);
    border-bottom-color: rgba(255,255,255,.1);
}
body.dark-mode .btn-marcar-disponivel {
    background-color: var(--verde-agua);
}
body.dark-mode .btn-marcar-disponivel:hover {
    background-color: #5da8a7;
}
</style>
