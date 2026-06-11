<?php

/**
 * Funções de Negócio - Nexus (MySQL)
 * Baseado na estrutura de banco de dados atualizada.
 */

// ─── AUTENTICAÇÃO ────────────────────────────────────────────────────────────

function verificar_autenticacao($tipo = 'paciente') {
    if ($tipo === 'paciente') {
        if (!isset($_SESSION['id_paciente'])) {
            header('Location: login.php');
            exit;
        }
    } elseif ($tipo === 'psicologa') {
        if (!isset($_SESSION['id_psicologa'])) {
            header('Location: login.php');
            exit;
        }
    }
}

// ─── USUÁRIOS ───────────────────────────────────────────────────────────────

function obter_paciente($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function obter_psicologa($pdo, $id_psicologa) {
    $stmt = $pdo->prepare("SELECT * FROM psicologa WHERE id_psicologa = ?");
    $stmt->execute([$id_psicologa]);
    return $stmt->fetch();
}

// ─── CONSULTAS ──────────────────────────────────────────────────────────────

function obter_todas_consultas($pdo) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nome as paciente_nome, p.email as paciente_email, 
               e.nome as especializacao, h.horario, d.data_calendario
        FROM consultas c
        JOIN pacientes p ON c.id_paciente = p.id
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        ORDER BY d.data_calendario ASC, h.horario ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function obter_consultas_paciente($pdo, $id_paciente) {
    $stmt = $pdo->prepare("
        SELECT c.*, e.nome as especializacao, h.horario, d.data_calendario
        FROM consultas c
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        WHERE c.id_paciente = ?
        ORDER BY d.data_calendario DESC, h.horario DESC
    ");
    $stmt->execute([$id_paciente]);
    return $stmt->fetchAll();
}

function obter_proximas_consultas($pdo, $limite = 10) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nome as paciente_nome, e.nome as especializacao, 
               h.horario, d.data_calendario
        FROM consultas c
        JOIN pacientes p ON c.id_paciente = p.id
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        WHERE c.status IN ('Pendente', 'Confirmada')
        AND CONCAT(d.data_calendario, ' ', h.horario) >= NOW()
        ORDER BY d.data_calendario ASC, h.horario ASC
        LIMIT ?
    ");
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

// ─── AGENDA E HORÁRIOS ──────────────────────────────────────────────────────

function obter_horarios_disponiveis($pdo, $id_data) {
    $stmt = $pdo->prepare("
        SELECT h.* FROM horarios h
        WHERE h.ativo = 1 AND h.id_horario NOT IN (
            SELECT id_horario FROM consultas 
            WHERE id_data = ? AND status != 'Cancelada'
        )
        ORDER BY h.horario ASC
    ");
    $stmt->execute([$id_data]);
    return $stmt->fetchAll();
}

function obter_especializacoes($pdo) {
    $stmt = $pdo->query("SELECT * FROM especializacoes WHERE ativa = 1 ORDER BY nome ASC");
    return $stmt->fetchAll();
}

// ─── FINANCEIRO ─────────────────────────────────────────────────────────────

function obter_receita_mes($pdo) {
    $stmt = $pdo->prepare("
        SELECT SUM(valor) as total FROM pagamentos 
        WHERE status = 'Concluído' 
        AND MONTH(data_pagamento) = MONTH(CURRENT_DATE)
        AND YEAR(data_pagamento) = YEAR(CURRENT_DATE)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

function obter_receita_ano($pdo) {
    $stmt = $pdo->prepare("
        SELECT SUM(valor) as total FROM pagamentos 
        WHERE status = 'Concluído' 
        AND YEAR(data_pagamento) = YEAR(CURRENT_DATE)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// ─── NOTIFICAÇÕES ───────────────────────────────────────────────────────────

function criar_notificacao($pdo, $id_paciente, $id_psicologa, $tipo, $mensagem) {
    $stmt = $pdo->prepare("INSERT INTO notificacoes (id_paciente, id_psicologa, tipo, mensagem) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$id_paciente, $id_psicologa, $tipo, $mensagem]);
}

function obter_notificacoes_psicologa($pdo, $id_psicologa, $limite = 10) {
    $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_psicologa = ? ORDER BY data_criacao DESC LIMIT ?");
    $stmt->execute([$id_psicologa, $limite]);
    return $stmt->fetchAll();
}

function contar_notificacoes_nao_lidas($pdo, $id_psicologa) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_psicologa = ? AND lida = 0");
    $stmt->execute([$id_psicologa]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// ─── UTILITÁRIOS ────────────────────────────────────────────────────────────

function formatar_data($data) {
    $date = DateTime::createFromFormat('Y-m-d', $data);
    return $date ? $date->format('d/m/Y') : $data;
}

function formatar_moeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Obter receita mensal do ano atual para gráficos
 */
function obter_receita_mensal_ano($pdo) {
    $receita = array_fill(0, 12, 0);
    $stmt = $pdo->prepare("
        SELECT MONTH(data_pagamento) as mes, SUM(valor) as total 
        FROM pagamentos 
        WHERE status = 'Concluído' AND YEAR(data_pagamento) = YEAR(CURRENT_DATE)
        GROUP BY MONTH(data_pagamento)
    ");
    $stmt->execute();
    $resultados = $stmt->fetchAll();
    foreach ($resultados as $row) {
        $receita[intval($row['mes']) - 1] = floatval($row['total']);
    }
    return $receita;
}

/**
 * Obter histórico de transações financeiras
 */
function obter_transacoes_financeiras($pdo, $limite = 20) {
    $stmt = $pdo->prepare("
        SELECT p.*, pac.nome as paciente_nome, e.nome as especializacao
        FROM pagamentos p
        JOIN pacientes pac ON p.id_paciente = pac.id
        JOIN consultas c ON p.id_consulta = c.id_consulta
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        ORDER BY p.data_criacao DESC
        LIMIT ?
    ");
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

/**
 * Obter uma consulta específica
 */
function obter_consulta($pdo, $id_consulta) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nome as paciente_nome, e.nome as especializacao, h.horario, d.data_calendario
        FROM consultas c
        JOIN pacientes p ON c.id_paciente = p.id
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        WHERE c.id_consulta = ?
    ");
    $stmt->execute([$id_consulta]);
    return $stmt->fetch();
}

/**
 * Marcar notificação como lida
 */
function marcar_notificacao_lida($pdo, $id_notificacao) {
    $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_notificacao = ?");
    return $stmt->execute([$id_notificacao]);
}

/**
 * Obter consultas de um paciente (API/Dashboard)
 */
function obter_notificacoes_paciente($pdo, $id_paciente, $limite = 10) {
    $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_paciente = ? ORDER BY data_criacao DESC LIMIT ?");
    $stmt->execute([$id_paciente, $limite]);
    return $stmt->fetchAll();
}

/**
 * Contar notificações não lidas para paciente
 */
function contar_notificacoes_nao_lidas_paciente($pdo, $id_paciente) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_paciente = ? AND lida = 0");
    $stmt->execute([$id_paciente]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

/**
 * Obter datas disponíveis para agendamento
 */
function obter_datas_disponiveis($pdo) {
    $stmt = $pdo->query("SELECT * FROM datas_disponiveis WHERE status_dia = 'Disponivel' ORDER BY data_calendario ASC");
    return $stmt->fetchAll();
}
?>
