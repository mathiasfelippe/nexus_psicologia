<?php
function verificar_esquema_banco($pdo) {
    try {
        // Verificar se a coluna 'destinatario' existe na tabela 'notificacoes'
        $stmt = $pdo->query("PRAGMA table_info(notificacoes)");
        $columns = $stmt->fetchAll();
        $has_destinatario = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'destinatario') {
                $has_destinatario = true;
                break;
            }
        }
        if (!$has_destinatario) {
            // Criar a coluna se não existir (SQLite não suporta ENUM ou AFTER)
            $pdo->exec("ALTER TABLE notificacoes ADD COLUMN destinatario TEXT DEFAULT 'paciente'");
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar/atualizar esquema: " . $e->getMessage());
    }
}

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
        SELECT c.*, e.nome as especializacao, e.preco as valor, h.horario, d.data_calendario,
               COALESCE(c.pagamento_status, 'Pendente') as pagamento_status
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
        AND (d.data_calendario || ' ' || h.horario) >= datetime('now', 'localtime')
        ORDER BY d.data_calendario ASC, h.horario ASC
        LIMIT ?
    ");
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

// ─── AGENDA E HORÁRIOS ──────────────────────────────────────────────────────

function obter_horarios_disponiveis($pdo, $id_data) {
    // Obter a data correspondente ao id_data
    $stmt_data = $pdo->prepare("SELECT data_calendario FROM datas_disponiveis WHERE id_data = ?");
    $stmt_data->execute([$id_data]);
    $data_row = $stmt_data->fetch();
    $data_calendario = $data_row['data_calendario'] ?? null;
    
    if (!$data_calendario) {
        return [];
    }
    
    // Verificar se existe bloqueio de dia inteiro ou ferias para esta data
    // data_fim NULL significa bloqueio de 1 dia (data_inicio exata)
    // data_fim preenchido significa intervalo [data_inicio, data_fim]
    $stmt_dia_bloqueado = $pdo->prepare("
        SELECT id_bloqueio FROM bloqueios_agenda 
        WHERE tipo IN ('dia_inteiro', 'ferias') 
        AND (
            (data_fim IS NULL AND data_inicio = ?)
            OR 
            (data_fim IS NOT NULL AND data_inicio <= ? AND data_fim >= ?)
        )
        LIMIT 1
    ");
    $stmt_dia_bloqueado->execute([$data_calendario, $data_calendario, $data_calendario]);
    if ($stmt_dia_bloqueado->fetch()) {
        return [];
    }
    
    // Obter horarios que nao estao ocupados por consultas
    $stmt = $pdo->prepare("
        SELECT h.* FROM horarios h
        WHERE h.ativo = 1 AND h.id_horario NOT IN (
            SELECT id_horario FROM consultas 
            WHERE id_data = ? AND status != 'Cancelada'
        )
        ORDER BY h.horario ASC
    ");
    $stmt->execute([$id_data]);
    $horarios = $stmt->fetchAll();
    
    // Filtrar horarios que estao bloqueados (horario especifico)
    $stmt_bloqueios = $pdo->prepare("
        SELECT id_horario FROM bloqueios_agenda 
        WHERE tipo = 'horario_especifico' 
        AND (
            (data_fim IS NULL AND data_inicio = ?)
            OR 
            (data_fim IS NOT NULL AND data_inicio <= ? AND data_fim >= ?)
        )
    ");
    $stmt_bloqueios->execute([$data_calendario, $data_calendario, $data_calendario]);
    $bloqueios = $stmt_bloqueios->fetchAll(PDO::FETCH_COLUMN);
    
    // Remover horarios bloqueados
    $horarios_filtrados = array_filter($horarios, function($h) use ($bloqueios) {
        return !in_array($h['id_horario'], $bloqueios);
    });
    
    return array_values($horarios_filtrados);
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
        AND strftime('%m', COALESCE(data_pagamento, data_criacao)) = strftime('%m', 'now', 'localtime')
        AND strftime('%Y', COALESCE(data_pagamento, data_criacao)) = strftime('%Y', 'now', 'localtime')
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

function obter_receita_ano($pdo) {
    $stmt = $pdo->prepare("
        SELECT SUM(valor) as total FROM pagamentos 
        WHERE status = 'Concluído' 
        AND strftime('%Y', COALESCE(data_pagamento, data_criacao)) = strftime('%Y', 'now', 'localtime')
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// ─── NOTIFICAÇÕES ───────────────────────────────────────────────────────────

function criar_notificacao($pdo, $id_paciente, $id_psicologa, $tipo, $mensagem, $destinatario = 'paciente') {
    $stmt = $pdo->prepare("INSERT INTO notificacoes (id_paciente, id_psicologa, tipo, mensagem, destinatario) VALUES (?, ?, ?, ?, ?)");
    $r = $stmt->execute([$id_paciente, $id_psicologa, $tipo, $mensagem, $destinatario]);
    if ($r) {
        error_log("[NOTIFICACAO] tipo=$tipo dest=$destinatario msg=" . substr($mensagem, 0, 80));
    }
    return $r;
}

function criar_notificacao_consulta($pdo, $id_consulta, $tipo, $mensagem_paciente, $mensagem_psicologa) {
    $consulta = obter_consulta($pdo, $id_consulta);
    if (!$consulta) return false;
    $id_paciente = $consulta['id_paciente'];
    $id_psicologa = 1;
    $r1 = criar_notificacao($pdo, $id_paciente, $id_psicologa, $tipo, $mensagem_paciente, 'paciente');
    $r2 = criar_notificacao($pdo, $id_paciente, $id_psicologa, $tipo, $mensagem_psicologa, 'psicologa');
    return $r1 && $r2;
}

function obter_notificacoes_psicologa($pdo, $id_psicologa, $limite = 10) {
    $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' ORDER BY data_criacao DESC LIMIT ?");
    $stmt->execute([$id_psicologa, $limite]);
    return $stmt->fetchAll();
}

function contar_notificacoes_nao_lidas($pdo, $id_psicologa = null) {
    if ($id_psicologa === null) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' AND lida = 0");
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
 * Formatar data e hora juntas
 */
function formatar_data_hora($data, $hora) {
    $date = DateTime::createFromFormat('Y-m-d', $data);
    $data_formatada = $date ? $date->format('d/m/Y') : $data;
    $hora_formatada = substr($hora, 0, 5);
    return $data_formatada . ' às ' . $hora_formatada;
}

/**
 * Obter receita mensal do ano atual para gráficos
 */
function obter_receita_mensal_ano($pdo) {
    $receita = array_fill(0, 12, 0);
    $stmt = $pdo->prepare("
        SELECT strftime('%m', COALESCE(data_pagamento, data_criacao)) as mes, SUM(valor) as total 
        FROM pagamentos 
        WHERE status = 'Concluído' AND strftime('%Y', COALESCE(data_pagamento, data_criacao)) = strftime('%Y', 'now', 'localtime')
        GROUP BY mes
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
    $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' ORDER BY data_criacao DESC LIMIT ?");
    $stmt->execute([$id_paciente, $limite]);
    return $stmt->fetchAll();
}

/**
 * Contar notificações não lidas para paciente
 */
function contar_notificacoes_nao_lidas_paciente($pdo, $id_paciente) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' AND lida = 0");
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

/**
 * Obter todos os horários cadastrados
 */
function obter_horarios($pdo) {
    $stmt = $pdo->query("SELECT * FROM horarios WHERE ativo = 1 ORDER BY horario ASC");
    return $stmt->fetchAll();
}

/**
 * Formatar tipo de notificação para exibição amigável
 */
function formatar_tipo_notificacao($tipo) {
    $tipos = [
        'nova_consulta'       => 'Nova Consulta',
        'agendamento'         => 'Consulta Agendada',
        'confirmacao'         => 'Consulta Confirmada',
        'cancelamento'        => 'Consulta Cancelada',
        'reagendamento'       => 'Consulta Reagendada',
        'pagamento'           => 'Pagamento Confirmado',
        'pagamento_aprovado'  => 'Pagamento Aprovado',
        'pagamento_recusado'  => 'Pagamento Recusado',
        'consulta_concluida'  => 'Consulta Concluida',
        'alteracao_data'      => 'Alteracao de Data',
        'alteracao_horario'   => 'Alteracao de Horario',
        'lembrete'            => 'Lembrete',
        'comentario_psicologa'=> 'Recado da Psicologa'
    ];
    return $tipos[$tipo] ?? 'Notificacao';
}

/**
 * Obter bloqueios de agenda
 */
function obter_bloqueios_agenda($pdo) {
    $stmt = $pdo->query("
        SELECT b.*, h.horario as horario_texto
        FROM bloqueios_agenda b
        LEFT JOIN horarios h ON b.id_horario = h.id_horario
        ORDER BY b.data_inicio DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Agendar uma nova consulta
 */
function agendar_consulta($pdo, $id_paciente, $id_especializacao, $id_horario, $id_data, $modalidade = 'Online') {
    // Obter o valor da especialização para salvar na consulta
    $stmt_preco = $pdo->prepare("SELECT preco, nome FROM especializacoes WHERE id_especializacao = ?");
    $stmt_preco->execute([$id_especializacao]);
    $especializacao = $stmt_preco->fetch();
    $valor = $especializacao['preco'] ?? 0;
    $nome_espec = $especializacao['nome'] ?? 'Consulta';

    try {
        $pdo->beginTransaction();

        $stmt_check = $pdo->prepare("SELECT id_consulta, status FROM consultas WHERE id_data = ? AND id_horario = ?");
        $stmt_check->execute([$id_data, $id_horario]);
        $existing = $stmt_check->fetch();

        if ($existing) {
            if ($existing['status'] !== 'Cancelada') {
                $pdo->rollBack();
                return false;
            }
            $stmt = $pdo->prepare("UPDATE consultas SET id_paciente = ?, id_especializacao = ?, modalidade = ?, valor = ?, status = 'Pendente', pagamento_status = 'Pendente' WHERE id_consulta = ?");
            $sucesso = $stmt->execute([$id_paciente, $id_especializacao, $modalidade, $valor, $existing['id_consulta']]);
            $id_consulta = $existing['id_consulta'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO consultas (id_paciente, id_especializacao, id_horario, id_data, modalidade, valor, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Pendente')
            ");
            $sucesso = $stmt->execute([$id_paciente, $id_especializacao, $id_horario, $id_data, $modalidade, $valor]);
            if ($sucesso) {
                $id_consulta = $pdo->lastInsertId();
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao agendar consulta: " . $e->getMessage());
        return false;
    }

    if ($sucesso) {
        $id_psicologa = 1;
        $paciente = obter_paciente($pdo, $id_paciente);
        $nome_paciente = $paciente['nome'] ?? 'Paciente';

        // Buscar data e horário
        $stmt_dh = $pdo->prepare("SELECT d.data_calendario, h.horario FROM datas_disponiveis d, horarios h WHERE d.id_data = ? AND h.id_horario = ?");
        $stmt_dh->execute([$id_data, $id_horario]);
        $dh = $stmt_dh->fetch();
        $data_fmt = $dh ? date('d/m/Y', strtotime($dh['data_calendario'])) : '';
        $hora_fmt = $dh ? substr($dh['horario'], 0, 5) : '';

        // Notificar Paciente
        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'agendamento', "Voce agendou uma consulta de $nome_espec para $data_fmt as $hora_fmt. Aguarde a confirmacao.", 'paciente');

        // Notificar Psicóloga (nova consulta para confirmar)
        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'nova_consulta', "O(a) paciente $nome_paciente agendou uma consulta de $nome_espec para $data_fmt as $hora_fmt. Confirme ou cancele.", 'psicologa');
    }

    return $sucesso;
}

/**
 * Processar um pagamento
 */
function processar_pagamento($pdo, $id_consulta, $id_paciente, $metodo = 'Pix') {
    // Obter valor da consulta diretamente da tabela consultas
    $stmt = $pdo->prepare("SELECT valor FROM consultas WHERE id_consulta = ? AND id_paciente = ?");
    $stmt->execute([$id_consulta, $id_paciente]);
    $valor = $stmt->fetchColumn();
    
    if ($valor === false) {
        error_log('processar_pagamento: consulta não encontrada ou não pertence ao paciente. id_consulta=' . $id_consulta . ', id_paciente=' . $id_paciente);
        return false;
    }

    $pdo->beginTransaction();
    try {
        // Inserir pagamento
        $stmt_pag = $pdo->prepare("
            INSERT INTO pagamentos (id_consulta, id_paciente, valor, metodo_pagamento, status, data_pagamento)
            VALUES (?, ?, ?, ?, 'Concluído', datetime('now', 'localtime'))
        ");
        $stmt_pag->execute([$id_consulta, $id_paciente, $valor, $metodo]);

        // Atualizar status da consulta
        $stmt_con = $pdo->prepare("UPDATE consultas SET pagamento_status = 'Concluído' WHERE id_consulta = ?");
        $stmt_con->execute([$id_consulta]);

        // Notificar
        $id_psicologa = 1;
        $paciente = obter_paciente($pdo, $id_paciente);
        $nome_paciente = $paciente['nome'] ?? 'Paciente';
        $consulta = obter_consulta($pdo, $id_consulta);
        $data_pag_fmt = $consulta ? date('d/m/Y', strtotime($consulta['data_calendario'])) : '';

        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'pagamento', "Pagamento confirmado com sucesso para a consulta do dia $data_pag_fmt.", 'paciente');

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Verificar se uma consulta pode ser cancelada pelo paciente (regra de 24h)
 */
function consulta_pode_ser_cancelada_pelo_paciente($consulta) {
    $data_hora_consulta = strtotime($consulta['data_calendario'] . ' ' . $consulta['horario']);
    $agora = time();
    $vinte_quatro_horas = 24 * 60 * 60;
    
    return ($data_hora_consulta - $agora) >= $vinte_quatro_horas;
}

/**
 * Obter consultas por data específica
 */
function obter_consultas_por_data($pdo, $data) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nome as paciente_nome, e.nome as especializacao, h.horario, d.data_calendario
        FROM consultas c
        JOIN pacientes p ON c.id_paciente = p.id
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        WHERE d.data_calendario = ?
        ORDER BY h.horario ASC
    ");
    $stmt->execute([$data]);
    return $stmt->fetchAll();
}

/**
 * Obter total de consultas
 */
function obter_total_consultas($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM consultas WHERE status != 'Cancelada'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

/**
 * Obter total de pacientes
 */
function obter_total_pacientes($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pacientes");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

/**
 * Confirmar consulta
 */
function confirmar_consulta($pdo, $id_consulta, $id_paciente) {
    $stmt = $pdo->prepare("UPDATE consultas SET status = 'Confirmada' WHERE id_consulta = ?");
    $sucesso = $stmt->execute([$id_consulta]);
    
    if ($sucesso) {
        $id_psicologa = 1;
        $consulta = obter_consulta($pdo, $id_consulta);
        $data_fmt = $consulta ? date('d/m/Y', strtotime($consulta['data_calendario'])) : '';
        $hora_fmt = $consulta ? substr($consulta['horario'], 0, 5) : '';

        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'confirmacao', "Sua consulta do dia $data_fmt as $hora_fmt foi confirmada pela psicologa.", 'paciente');
    }
    
    return $sucesso;
}

/**
 * Cancelar consulta
 */
function cancelar_consulta($pdo, $id_consulta, $id_paciente, $id_psicologa, $notificar_psicologa = true, $motivo_cancelamento = null, $cancelado_por = 'paciente') {
    $stmt = $pdo->prepare("UPDATE consultas SET status = 'Cancelada' WHERE id_consulta = ?");
    $sucesso = $stmt->execute([$id_consulta]);

    if ($sucesso) {
        $consulta = obter_consulta($pdo, $id_consulta);
        $data_fmt = $consulta ? date('d/m/Y', strtotime($consulta['data_calendario'])) : '';
        $hora_fmt = $consulta ? substr($consulta['horario'], 0, 5) : '';

        $stmt_ins = $pdo->prepare("INSERT INTO consultas_canceladas (id_consulta, id_paciente, id_especializacao, data_consulta, horario_consulta, modalidade, valor, motivo_cancelamento, cancelado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ins->execute([
            $id_consulta,
            $consulta['id_paciente'],
            $consulta['id_especializacao'],
            $consulta['data_calendario'],
            $consulta['horario'],
            $consulta['modalidade'],
            $consulta['valor'],
            $motivo_cancelamento,
            $cancelado_por
        ]);

        if ($notificar_psicologa) {
            $paciente = obter_paciente($pdo, $id_paciente);
            $nome_paciente = $paciente['nome'] ?? 'Paciente';
            criar_notificacao($pdo, $id_paciente, $id_psicologa, 'cancelamento', "A consulta do(a) $nome_paciente do dia $data_fmt as $hora_fmt foi cancelada.", 'psicologa');
            criar_notificacao($pdo, $id_paciente, $id_psicologa, 'cancelamento', "Sua consulta do dia $data_fmt as $hora_fmt foi cancelada.", 'paciente');
        }
    }

    return $sucesso;
}

/**
 * Reagendar consulta (trocar data e/ou horário)
 */
function reagendar_consulta($pdo, $id_consulta, $id_paciente, $nova_data, $novo_horario) {
    $consulta = obter_consulta($pdo, $id_consulta);
    if (!$consulta || $consulta['id_paciente'] != $id_paciente) return false;

    $data_antiga = $consulta['data_calendario'];
    $hora_antiga = $consulta['horario'];

    $stmt_data = $pdo->prepare("SELECT id_data FROM datas_disponiveis WHERE data_calendario = ? AND status_dia = 'Disponivel'");
    $stmt_data->execute([$nova_data]);
    $id_data = $stmt_data->fetchColumn();
    if (!$id_data) return false;

    $stmt_hor = $pdo->prepare("SELECT id_horario FROM horarios WHERE id_horario = ? AND ativo = 1");
    $stmt_hor->execute([$novo_horario]);
    if (!$stmt_hor->fetchColumn()) return false;

    $stmt = $pdo->prepare("UPDATE consultas SET id_data = ?, id_horario = ? WHERE id_consulta = ? AND id_paciente = ?");
    $sucesso = $stmt->execute([$id_data, $novo_horario, $id_consulta, $id_paciente]);

    if ($sucesso) {
        $id_psicologa = 1;
        $paciente = obter_paciente($pdo, $id_paciente);
        $nome = $paciente['nome'] ?? 'Paciente';
        $data_fmt = date('d/m/Y', strtotime($nova_data));

        $stmt_h = $pdo->prepare("SELECT horario FROM horarios WHERE id_horario = ?");
        $stmt_h->execute([$novo_horario]);
        $nova_hora = $stmt_h->fetchColumn();
        $hora_fmt = $nova_hora ? substr($nova_hora, 0, 5) : '';

        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'reagendamento',
            "Sua consulta foi reagendada para $data_fmt as $hora_fmt.",
            'paciente');
    }

    return $sucesso;
}

/**
 * Atualizar preço de especialização
 */
function atualizar_preco_especializacao($pdo, $id_especializacao, $novo_preco) {
    $stmt = $pdo->prepare("UPDATE especializacoes SET preco = ? WHERE id_especializacao = ?");
    return $stmt->execute([$novo_preco, $id_especializacao]);
}

/**
 * Criar bloqueio de agenda - VERSÃO CORRIGIDA
 * Usa id_horario em vez de horario_inicio/horario_fim
 */
function criar_bloqueio_agenda($pdo, $tipo_bloqueio, $data_inicio, $data_fim = null, $id_horario = null, $horario_fim = null, $motivo = null) {
    try {
        // Validar tipo de bloqueio
        $tipos_validos = ['dia_inteiro', 'horario_especifico', 'ferias'];
        if (!in_array($tipo_bloqueio, $tipos_validos)) {
            error_log('Tipo de bloqueio inválido: ' . $tipo_bloqueio);
            return false;
        }
        
        // Validar data de início (formato YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio)) {
            error_log('Data de início inválida: ' . $data_inicio);
            return false;
        }
        
        // Converter valores vazios para NULL
        $data_fim = (empty($data_fim) || $data_fim === '') ? null : $data_fim;
        $id_horario = (empty($id_horario) || $id_horario === '') ? null : intval($id_horario);
        $motivo = (empty($motivo) || $motivo === '') ? null : $motivo;
        
        // Preparar SQL - usar id_horario em vez de horario_inicio/horario_fim
        $sql = "INSERT INTO bloqueios_agenda (tipo, data_inicio, data_fim, id_horario, motivo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        // Parâmetros
        $params = [$tipo_bloqueio, $data_inicio, $data_fim, $id_horario, $motivo];
        
        // Log antes de executar
        error_log('Criando bloqueio: tipo=' . $tipo_bloqueio . ', data=' . $data_inicio . ', id_horario=' . ($id_horario ?? 'NULL'));
        
        // Executar
        $result = $stmt->execute($params);
        
        if ($result) {
            error_log('Bloqueio criado com sucesso. ID: ' . $pdo->lastInsertId());
            return true;
        } else {
            $erro = implode(' | ', $stmt->errorInfo());
            error_log('Falha ao executar INSERT. Erro: ' . $erro);
            return false;
        }
        
    } catch (PDOException $e) {
        error_log('PDOException em criar_bloqueio_agenda: ' . $e->getMessage());
        error_log('SQL State: ' . $e->errorInfo[0]);
        return false;
    } catch (Exception $e) {
        error_log('Exceção em criar_bloqueio_agenda: ' . $e->getMessage());
        return false;
    }
}

/**
 * Remover bloqueio de agenda
 */
function remover_bloqueio_agenda($pdo, $id_bloqueio) {
    $stmt = $pdo->prepare("DELETE FROM bloqueios_agenda WHERE id_bloqueio = ?");
    return $stmt->execute([$id_bloqueio]);
}
?>
