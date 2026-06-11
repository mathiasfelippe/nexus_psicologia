<?php
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Verificar se está logado
if (!isset($_SESSION['id_paciente'])) {
    header('Location: login.php');
    exit;
}

$id_paciente = $_SESSION['id_paciente'];
$paciente = obter_paciente($pdo, $id_paciente);

// Obter aba ativa
$aba_ativa = isset($_GET['aba']) ? $_GET['aba'] : 'dashboard';

// Processar ações POST
$sucesso = $_SESSION['flash_sucesso'] ?? '';
$erro = $_SESSION['flash_erro'] ?? '';
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
    
    if ($acao === 'agendar_consulta') {
        $id_especializacao = isset($_POST['id_especializacao']) ? intval($_POST['id_especializacao']) : 0;
        $id_horario = isset($_POST['id_horario']) ? intval($_POST['id_horario']) : 0;
        $id_data = isset($_POST['id_data']) ? intval($_POST['id_data']) : 0;
        $modalidade = isset($_POST['modalidade']) ? $_POST['modalidade'] : 'Online';
        
        if (agendar_consulta($pdo, $id_paciente, $id_especializacao, $id_horario, $id_data, $modalidade)) {
            $sucesso = 'Consulta agendada com sucesso! Aguarde a confirmação da psicóloga.';
        } else {
            $erro = 'Erro ao agendar consulta.';
        }
    } elseif ($acao === 'processar_pagamento') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        $metodo = isset($_POST['metodo_pagamento']) ? $_POST['metodo_pagamento'] : 'Pix';
        
        if (processar_pagamento($pdo, $id_consulta, $id_paciente, $metodo)) {
            $sucesso = 'Pagamento processado com sucesso!';
        } else {
            $erro = 'Erro ao processar pagamento.';
        }
    } elseif ($acao === 'cancelar_consulta') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        $consulta_cancelar = obter_consulta($pdo, $id_consulta);
        if ($consulta_cancelar && $consulta_cancelar['id_paciente'] == $id_paciente) {
            if (!consulta_pode_ser_cancelada_pelo_paciente($consulta_cancelar)) {
                $erro = 'A consulta so pode ser cancelada com pelo menos 24 horas de antecedencia.';
            } elseif (cancelar_consulta($pdo, $id_consulta, null, 1)) {
                $sucesso = 'Consulta cancelada com sucesso!';
            } else {
                $erro = 'Erro ao cancelar consulta.';
            }
        } else {
            $erro = 'Consulta não encontrada ou sem permissão.';
        }
    } elseif ($acao === 'marcar_lida') {
        $id_notificacao = isset($_POST['id_notificacao']) ? intval($_POST['id_notificacao']) : 0;
        marcar_notificacao_lida($pdo, $id_notificacao);
    } elseif ($acao === 'editar_perfil') {
        $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : '';
        $data_nascimento = isset($_POST['data_nascimento']) ? $_POST['data_nascimento'] : '';
        $cpf = isset($_POST['cpf']) ? trim($_POST['cpf']) : '';
        $endereco = isset($_POST['endereco']) ? trim($_POST['endereco']) : '';
        
        if (!empty($nome) && !empty($email)) {
            try {
                $stmt = $pdo->prepare("UPDATE pacientes SET nome = ?, email = ?, telefone = ?, data_nascimento = ?, cpf = ?, endereco = ? WHERE id = ?");
                if ($stmt->execute([$nome, $email, $telefone, $data_nascimento, $cpf, $endereco, $id_paciente])) {
                    $sucesso = 'Perfil atualizado com sucesso!';
                    $paciente = obter_paciente($pdo, $id_paciente);
                } else {
                    $erro = 'Erro ao atualizar perfil.';
                }
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar perfil.';
            }
        } else {
            $erro = 'Nome e email sao obrigatorios.';
        }
    }

    if (!empty($sucesso)) {
        $_SESSION['flash_sucesso'] = $sucesso;
    }
    if (!empty($erro)) {
        $_SESSION['flash_erro'] = $erro;
    }

    header('Location: dashboard_paciente.php?aba=' . urlencode($aba_ativa));
    exit;
}

// Obter dados para o dashboard
$minhas_consultas = obter_consultas_paciente($pdo, $id_paciente);
$hoje = date('Y-m-d');
$agora = time();
$obter_timestamp_consulta = function($consulta) {
    return strtotime(trim($consulta['data_calendario'] . ' ' . $consulta['horario']));
};
$proximas_consultas = array_filter($minhas_consultas, function($c) use ($agora, $obter_timestamp_consulta) {
    $inicio = $obter_timestamp_consulta($c);
    return $c['status'] !== 'Cancelada' && $inicio && $inicio >= $agora;
});
$consultas_passadas = array_filter($minhas_consultas, function($c) use ($agora, $obter_timestamp_consulta) {
    $inicio = $obter_timestamp_consulta($c);
    return $c['status'] !== 'Cancelada' && $inicio && $inicio < $agora;
});
usort($proximas_consultas, function($a, $b) use ($obter_timestamp_consulta) {
    return $obter_timestamp_consulta($a) <=> $obter_timestamp_consulta($b);
});
usort($consultas_passadas, function($a, $b) use ($obter_timestamp_consulta) {
    return $obter_timestamp_consulta($b) <=> $obter_timestamp_consulta($a);
});
$notificacoes = obter_notificacoes_paciente($pdo, $id_paciente, 5);
$notificacoes_nao_lidas = contar_notificacoes_nao_lidas_paciente($pdo, $id_paciente);
$especializacoes = obter_especializacoes($pdo);
$datas_disponiveis = obter_datas_disponiveis($pdo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Dashboard - Nexus Premium</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/saas-premium.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.js'></script>
    <script src="assets/js/dashboard_paciente_novo.js" defer></script>
    <style>
        .notificacao-item.nao-lida { border-left: 4px solid #6366f1 !important; background-color: #f8faff; }
        .notificacao-item.lida { opacity: 0.7; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.html" class="logo-link">
                    <img src="assets/img/logo.png" alt="Nexus Logo" class="sidebar-logo">
                </a>
            </div>

            <nav class="sidebar-nav">
                <a href="?aba=dashboard" class="nav-item <?php echo $aba_ativa === 'dashboard' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 16l4-4m0 0l4 4m-4-4v4"></path>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="?aba=agendar" class="nav-item <?php echo $aba_ativa === 'agendar' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Agendar Consulta</span>
                </a>
                <a href="?aba=calendario" class="nav-item <?php echo $aba_ativa === 'calendario' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>Meu Calendário</span>
                </a>
                <a href="?aba=consultas" class="nav-item <?php echo $aba_ativa === 'consultas' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <span>Minhas Consultas</span>
                </a>
                <a href="?aba=pagamentos" class="nav-item <?php echo $aba_ativa === 'pagamentos' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Pagamentos</span>
                </a>
                <a href="?aba=notificacoes" class="nav-item <?php echo $aba_ativa === 'notificacoes' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <span>Notificações <?php if ($notificacoes_nao_lidas > 0) echo '<span class="badge">' . $notificacoes_nao_lidas . '</span>'; ?></span>
                </a>
                <a href="?aba=perfil" class="nav-item <?php echo $aba_ativa === 'perfil' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span>Perfil</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="usuario-info">
                    <div class="usuario-avatar"><?php echo strtoupper(substr($paciente['nome'] ?? 'U', 0, 1)); ?></div>
                    <div class="usuario-dados">
                        <p class="usuario-nome"><?php echo htmlspecialchars($paciente['nome'] ?? 'Usuário'); ?></p>
                        <p class="usuario-email"><?php echo htmlspecialchars($paciente['email'] ?? ''); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    Sair
                </a>
            </div>
        </aside>

        <!-- CONTEÚDO CENTRAL -->
        <main class="main-content">
            <div class="header-top" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h1><?php
                    $titulo = "Dashboard";
                    switch($aba_ativa) {
                        case 'dashboard': $titulo = "Bem-vindo(a), " . htmlspecialchars(explode(' ', $paciente['nome'])[0]) . "!"; break;
                        case 'agendar': $titulo = "Agendar Consulta"; break;
                        case 'calendario': $titulo = "Meu Calendário"; break;
                        case 'consultas': $titulo = "Minhas Consultas"; break;
                        case 'pagamentos': $titulo = "Pagamentos"; break;
                        case 'notificacoes': $titulo = "Notificações"; break;
                        case 'perfil': $titulo = "Perfil"; break;
                    }
                    echo $titulo;
                ?></h1>
                <div class="header-actions">
                    <button class="btn-notificacoes" id="btn-dark-mode" title="Alternar modo escuro">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <?php if ($sucesso): ?>
                <div class="alerta alerta-sucesso">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>
            <?php if ($erro): ?>
                <div class="alerta alerta-erro">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard Principal -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'dashboard' ? 'ativo' : ''; ?>" id="aba-dashboard">
                <div class="header-top" style="display: none;">
                    <h1>Bem-vindo(a), <?php echo htmlspecialchars(explode(' ', $paciente['nome'])[0]); ?>!</h1>
                    <div class="header-actions">
                        <button class="btn-notificacoes" id="btn-dark-mode-dashboard-antigo" title="Alternar modo escuro">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Próximas Consultas -->
                <div class="calendario-secao">
                    <h2 style="margin-bottom: 24px;">Suas Próximas Consultas</h2>
                    <div style="display: grid; gap: 16px;">
                        <?php 
                        foreach (array_slice($proximas_consultas, 0, 3) as $consulta): 
                        ?>
                            <div style="background: white; border-radius: 16px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #6366f1;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h3 style="font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 8px;">
                                            <?php echo htmlspecialchars($consulta['especializacao']); ?>
                                        </h3>
                                        <div style="font-size: 14px; color: #6b7280; display: flex; gap: 16px;">
                                            <div>📅 <?php echo date('d/m/Y', strtotime($consulta['data_calendario'])); ?></div>
                                            <div>🕐 <?php echo $consulta['horario']; ?>h</div>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                                        <?php echo $consulta['status']; ?>
                                    </span>
                                </div>
                                <?php if ($consulta['status'] === 'Confirmada' && $consulta['pagamento_status'] === 'Pendente'): ?>
                                    <button class="btn btn-primary" style="margin-top: 16px; width: 100%;" onclick="abrirModalPagamento(<?php echo $consulta['id_consulta']; ?>, <?php echo $consulta['valor']; ?>)">
                                        Realizar Pagamento
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($proximas_consultas)): ?>
                            <p style="text-align: center; color: #6b7280; padding: 20px;">Você não tem consultas agendadas.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="calendario-secao" style="margin-top: 24px;">
                    <h2 style="margin-bottom: 24px;">Consultas que já passaram</h2>
                    <div style="display: grid; gap: 16px;">
                        <?php foreach (array_slice($consultas_passadas, 0, 5) as $consulta): ?>
                            <div style="background: white; border-radius: 16px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #9ca3af;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h3 style="font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 8px;">
                                            <?php echo htmlspecialchars($consulta['especializacao']); ?>
                                        </h3>
                                        <div style="font-size: 14px; color: #6b7280; display: flex; gap: 16px;">
                                            <div>📅 <?php echo date('d/m/Y', strtotime($consulta['data_calendario'])); ?></div>
                                            <div>🕐 <?php echo $consulta['horario']; ?>h</div>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                                        <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                                            <?php echo $consulta['status']; ?>
                                        </span>
                                        <span class="status-badge status-<?php echo strtolower($consulta['pagamento_status']); ?>">
                                            <?php echo $consulta['pagamento_status']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($consultas_passadas)): ?>
                            <p style="text-align: center; color: #6b7280; padding: 20px;">Você ainda não tem consultas passadas.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Agendar -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'agendar' ? 'ativo' : ''; ?>" id="aba-agendar">
                <?php include 'views/dashboard_paciente_agendar.php'; ?>
            </div>

            <!-- Calendário -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'calendario' ? 'ativo' : ''; ?>" id="aba-calendario">
                <div class="calendario-secao calendario-paciente-secao">
                    <div class="calendario-header calendario-header-com-legenda">
                        <div>
                            <h2>Calendário de Atendimentos</h2>
                            <p class="calendario-descricao">Visualize suas consultas confirmadas, pendentes e passadas.</p>
                        </div>
                        <div class="calendario-legenda-inline">
                            <div class="legenda-item">
                                <span class="legenda-cor" style="background-color: #10b981;"></span>
                                <span>Confirmada</span>
                            </div>
                            <div class="legenda-item">
                                <span class="legenda-cor" style="background-color: #f59e0b;"></span>
                                <span>Pendente</span>
                            </div>
                            <div class="legenda-item">
                                <span class="legenda-cor" style="background-color: #6b7280;"></span>
                                <span>Passada</span>
                            </div>
                        </div>
                    </div>
                    <div id="calendar"></div>
                </div>
                <script>
                    (function() {
                        function montarCalendarioPaciente() {
                            const calendarEl = document.getElementById('calendar');
                            if (!calendarEl || typeof FullCalendar === 'undefined') return;
                            if (calendarEl.dataset.inicializado === '1') return;

                            calendarEl.dataset.inicializado = '1';
                            const calendarioPaciente = new FullCalendar.Calendar(calendarEl, {
                                initialView: 'dayGridMonth',
                                headerToolbar: {
                                    left: 'prev,next today',
                                    center: 'title',
                                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                                },
                                locale: 'pt-br',
                                buttonText: {
                                    today: 'Hoje',
                                    month: 'Mês',
                                    week: 'Semana',
                                    day: 'Dia'
                                },
                                height: 'auto',
                                contentHeight: 'auto',
                                events: 'api/consultas_paciente.php',
                                displayEventTime: false,
                                eventDidMount: function(info) {
                                    const props = info.event.extendedProps;
                                    const estado = props.passada ? 'Consulta passada' : 'Consulta futura';
                                    info.el.title = `${props.especializacao} | ${props.modalidade || 'Modalidade não informada'} | ${props.status} | ${props.pagamento || 'Pagamento não informado'} | ${estado}`;
                                },
                                eventClick: function(info) {
                                    const event = info.event;
                                    const props = event.extendedProps;
                                    const data = event.start ? event.start.toLocaleDateString('pt-BR') : '';
                                    const hora = event.start ? event.start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
                                    const valor = Number(props.valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    const estado = props.passada ? 'Consulta que já passou' : 'Consulta futura';

                                    alert(
                                        `Especialidade: ${props.especializacao}\n` +
                                        `Modalidade: ${props.modalidade || 'Não informada'}\n` +
                                        `Data/Hora: ${data} ${hora}\n` +
                                        `Status: ${props.status}\n` +
                                        `Pagamento: ${props.pagamento || 'Não informado'}\n` +
                                        `Valor: R$ ${valor}\n` +
                                        `${estado}`
                                    );
                                }
                            });

                            calendarioPaciente.render();
                            setTimeout(function() {
                                calendarioPaciente.render();
                                calendarioPaciente.updateSize();
                            }, 150);
                        }

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', montarCalendarioPaciente);
                        } else {
                            montarCalendarioPaciente();
                        }
                    })();
                </script>
            </div>

            <!-- Minhas Consultas -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'consultas' ? 'ativo' : ''; ?>" id="aba-consultas">
                <div class="secao">
                    <h2>Minhas Consultas</h2>
                    <div class="aviso-cancelamento-paciente">
                        Cancelamentos são permitidos com pelo menos 24 horas de antecedência. Consultas pagas e canceladas dentro do prazo serão reembolsadas.
                    </div>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Especialidade</th>
                                    <th>Data/Hora</th>
                                    <th>Modalidade</th>
                                    <th>Status</th>
                                    <th>Pagamento</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($minhas_consultas as $consulta): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($consulta['especializacao']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($consulta['data_calendario'])) . ' ' . $consulta['horario'] . 'h'; ?></td>
                                        <td><?php echo htmlspecialchars($consulta['modalidade']); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($consulta['status']); ?>"><?php echo $consulta['status']; ?></span></td>
                                        <td><span class="status-badge status-<?php echo strtolower($consulta['pagamento_status']); ?>"><?php echo $consulta['pagamento_status']; ?></span></td>
                                        <td>
                                            <?php if (consulta_pode_ser_cancelada_pelo_paciente($consulta)): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja cancelar esta consulta?');">
                                                    <input type="hidden" name="acao" value="cancelar_consulta">
                                                    <input type="hidden" name="id_consulta" value="<?php echo $consulta['id_consulta']; ?>">
                                                    <button type="submit" class="btn btn-pequeno btn-cancelar">Cancelar</button>
                                                </form>
                                            <?php elseif ($consulta['status'] !== 'Cancelada'): ?>
                                                <span style="font-size: 12px; color: #9ca3af;">Prazo encerrado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagamentos -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'pagamentos' ? 'ativo' : ''; ?>" id="aba-pagamentos">
                <div class="secao">
                    <h2>Histórico de Pagamentos</h2>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Especialidade</th>
                                    <th>Data da Consulta</th>
                                    <th>Valor</th>
                                    <th>Método</th>
                                    <th>Status Pagamento</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $pagamentos = array_filter($minhas_consultas, fn($c) => $c['status'] !== 'Cancelada');
                                foreach ($pagamentos as $pag): 
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pag['especializacao']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($pag['data_calendario'])) . ' ' . $pag['horario'] . 'h'; ?></td>
                                        <td style="font-weight: 600; color: #10b981;">R$ <?php echo number_format($pag['valor'], 2, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($pag['metodo_pagamento'] ?? '-'); ?></td>
                                        <td>
                                            <?php 
                                            $ps = $pag['pagamento_status'];
                                            $cls = ($ps === 'Concluído') ? 'status-confirmada' : (($ps === 'Reembolsado') ? 'status-cancelada' : 'status-pendente');
                                            ?>
                                            <span class="status-badge <?php echo $cls; ?>"><?php echo htmlspecialchars($ps); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($pag['status'] === 'Confirmada' && $pag['pagamento_status'] === 'Pendente'): ?>
                                                <button class="btn btn-pequeno btn-primary" onclick="abrirModalPagamento(<?php echo $pag['id_consulta']; ?>, <?php echo $pag['valor']; ?>)">Pagar</button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($pagamentos)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #6b7280; padding: 20px;">Nenhuma consulta encontrada.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Notificações -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'notificacoes' ? 'ativo' : ''; ?>" id="aba-notificacoes">
                <div class="secao">
                    <h2>Notificações</h2>
                    <div class="notificacoes-lista">
                        <?php foreach ($notificacoes as $notif): ?>
		                            <div class="notificacao-item <?php echo $notif['lida'] ? 'lida' : 'nao-lida'; ?> <?php echo $notif['tipo']; ?>" style="<?php echo $notif['lida'] ? 'opacity: 0.7;' : ''; ?>">
		                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div>
                                                <div class="notificacao-titulo"><?php echo htmlspecialchars(formatar_tipo_notificacao($notif['tipo'])); ?></div>
                                                <div class="notificacao-desc"><?php echo htmlspecialchars($notif['mensagem']); ?></div>
                                                <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;"><?php echo date('d/m/Y H:i', strtotime($notif['data_criacao'])); ?></div>
                                            </div>
                                            <?php if (!$notif['lida']): ?>
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="acao" value="marcar_lida">
                                                    <input type="hidden" name="id_notificacao" value="<?php echo $notif['id_notificacao']; ?>">
                                                    <button type="submit" class="btn btn-pequeno" style="padding: 4px 8px; font-size: 10px;">Marcar como lida</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
		                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Perfil -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'perfil' ? 'ativo' : ''; ?>" id="aba-perfil">
                <div class="secao">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <h2 style="margin: 0;">Meu Perfil</h2>
                        <button class="btn btn-primary" id="btn-editar-perfil" onclick="ativarEdicaoPerfil()">Editar Perfil</button>
                    </div>
                    <form method="POST" id="form-perfil" class="form-row">
                        <input type="hidden" name="acao" value="editar_perfil">
                        <div class="form-group">
                            <label>Nome Completo</label>
                            <input type="text" name="nome" id="nome-perfil" value="<?php echo htmlspecialchars($paciente['nome']); ?>" disabled required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="email-perfil" value="<?php echo htmlspecialchars($paciente['email']); ?>" disabled required>
                        </div>
                        <div class="form-group">
                            <label>Telefone</label>
                            <input type="tel" name="telefone" id="telefone-perfil" value="<?php echo htmlspecialchars($paciente['telefone'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Data de Nascimento</label>
                            <input type="date" name="data_nascimento" id="data-nascimento-perfil" value="<?php echo htmlspecialchars($paciente['data_nascimento'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>CPF</label>
                            <input type="text" name="cpf" id="cpf-perfil" value="<?php echo htmlspecialchars($paciente['cpf'] ?? ''); ?>" disabled placeholder="000.000.000-00">
                        </div>
                        <div class="form-group">
                            <label>Endereco</label>
                            <input type="text" name="endereco" id="endereco-perfil" value="<?php echo htmlspecialchars($paciente['endereco'] ?? ''); ?>" disabled placeholder="Rua, numero, complemento">
                        </div>
                        <div id="botoes-edicao" style="display: none; gap: 12px; grid-column: 1 / -1;">
                            <button type="submit" class="btn btn-primary">Salvar Alteracoes</button>
                            <button type="button" class="btn btn-secondary" onclick="cancelarEdicaoPerfil()">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <!-- WIDGETS DIREITA -->
        <aside class="widgets-coluna">
            <!-- Próximas Consultas -->
            <div class="widget">
                <div class="widget-header">
                    <h3>Próximas Consultas</h3>
                </div>
                <div class="proximas-consultas">
                    <?php foreach (array_slice($proximas_consultas, 0, 2) as $consulta): ?>
                        <div class="consulta-card">
                            <div class="consulta-card-titulo"><?php echo htmlspecialchars($consulta['especializacao']); ?></div>
                            <div class="consulta-card-info">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <?php echo date('d/m', strtotime($consulta['data_calendario'])) . ' ' . $consulta['horario'] . 'h'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Notificações -->
            <div class="widget">
                <div class="widget-header">
                    <h3>Notificações</h3>
                </div>
                <div class="notificacoes-lista">
                    <?php foreach (array_slice($notificacoes, 0, 2) as $notif): ?>
                        <div class="notificacao-item <?php echo $notif['lida'] ? 'lida' : 'nao-lida'; ?> <?php echo $notif['tipo']; ?>">
                            <div class="notificacao-titulo"><?php echo htmlspecialchars(formatar_tipo_notificacao($notif['tipo'])); ?></div>
                            <div class="notificacao-desc"><?php echo htmlspecialchars(substr($notif['mensagem'], 0, 60)); ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($notificacoes)): ?>
                        <p style="color: #9ca3af; font-size: 13px; text-align: center; padding: 8px;">Nenhuma notificação.</p>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>

    <!-- Modal de Pagamento -->
    <div id="modalPagamento" class="modal">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2>Realizar Pagamento</h2>
                <button class="modal-fechar" onclick="fecharModalPagamento()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="formPagamento">
                    <input type="hidden" name="acao" value="processar_pagamento">
                    <input type="hidden" name="id_consulta" id="idConsultaPagamento">

                    <div style="background: #f3f4f6; border-radius: 12px; padding: 16px; margin-bottom: 24px;">
                        <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Valor a Pagar</div>
                        <div style="font-size: 32px; font-weight: 800; color: #6366f1;">
                            R$ <span id="valorPagamento">0,00</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Método de Pagamento</label>
                        <select name="metodo_pagamento" required>
                            <option value="Pix">Pix</option>
                            <option value="Cartao">Cartão de Crédito</option>
                            <option value="Boleto">Boleto</option>
                        </select>
                    </div>

                    <div class="modal-acoes">
                        <button type="submit" class="btn btn-primary">Confirmar Pagamento</button>
                        <button type="button" class="btn btn-secondary" onclick="fecharModalPagamento()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modo Escuro Global
        const btnDarkModeGlobal = document.getElementById('btn-dark-mode');
        const body = document.body;

        // Verificar preferência salva
        const darkModeEnabled = localStorage.getItem('darkMode') === 'true';
        if (darkModeEnabled) {
            body.classList.add('dark-mode');
        }

        // Toggle modo escuro
        if (btnDarkModeGlobal) {
            btnDarkModeGlobal.addEventListener('click', function() {
                body.classList.toggle('dark-mode');
                const isEnabled = body.classList.contains('dark-mode');
                localStorage.setItem('darkMode', isEnabled);
            });
        }

        // Funções de Modal de Pagamento
        function abrirModalPagamento(idConsulta, valor) {
            document.getElementById('idConsultaPagamento').value = idConsulta;
            document.getElementById('valorPagamento').textContent = valor.toFixed(2).replace('.', ',');
            document.getElementById('modalPagamento').classList.add('show');
        }

        function fecharModalPagamento() {
            document.getElementById('modalPagamento').classList.remove('show');
        }

        // Fechar modal ao clicar fora
        document.getElementById('modalPagamento').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalPagamento();
            }
        });

        // Funcoes de Edicao de Perfil
        function ativarEdicaoPerfil() {
            const campos = ['nome-perfil', 'email-perfil', 'telefone-perfil', 'data-nascimento-perfil', 'cpf-perfil', 'endereco-perfil'];
            campos.forEach(id => {
                const campo = document.getElementById(id);
                if (campo) campo.disabled = false;
            });
            document.getElementById('btn-editar-perfil').style.display = 'none';
            document.getElementById('botoes-edicao').style.display = 'flex';
        }

        function cancelarEdicaoPerfil() {
            const campos = ['nome-perfil', 'email-perfil', 'telefone-perfil', 'data-nascimento-perfil', 'cpf-perfil', 'endereco-perfil'];
            campos.forEach(id => {
                const campo = document.getElementById(id);
                if (campo) campo.disabled = true;
            });
            document.getElementById('btn-editar-perfil').style.display = 'block';
            document.getElementById('botoes-edicao').style.display = 'none';
        }
    </script>
</body>
</html>

