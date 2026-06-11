<?php
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Verificar se está logado
if (!isset($_SESSION['id_psicologa'])) {
    header('Location: login.php');
    exit;
}

$id_psicologa = $_SESSION['id_psicologa'];
$psicologa = obter_psicologa($pdo, $id_psicologa);

// Obter aba ativa
$aba_ativa = isset($_GET['aba']) ? $_GET['aba'] : 'dashboard';

// Processar ações POST
$sucesso = $_SESSION['flash_sucesso'] ?? '';
$erro = $_SESSION['flash_erro'] ?? '';
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
    
    if ($acao === 'confirmar_consulta') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
        $consulta = obter_consulta($pdo, $id_consulta);
        if (confirmar_consulta($pdo, $id_consulta, $consulta['id_paciente'])) {
            if (!empty($comentario)) {
                criar_notificacao($pdo, 'comentario_psicologa', "Recado da psicóloga: $comentario", $consulta['id_paciente']);
            }
            $sucesso = 'Consulta confirmada com sucesso!';
        }
    } elseif ($acao === 'cancelar_consulta') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
        $consulta = obter_consulta($pdo, $id_consulta);
        if (!$consulta) {
            $erro = 'Consulta não encontrada.';
        } elseif (cancelar_consulta($pdo, $id_consulta, $consulta['id_paciente'], $id_psicologa)) {
            if (!empty($comentario)) {
                criar_notificacao($pdo, 'comentario_psicologa', "Motivo do cancelamento: $comentario", $consulta['id_paciente']);
            }
            $sucesso = 'Consulta cancelada com sucesso! Se havia pagamento concluído, o valor foi reembolsado.';
        } else {
            $erro = 'Erro ao cancelar consulta.';
        }
    } elseif ($acao === 'atualizar_preco') {
        $id_especializacao = isset($_POST['id_especializacao']) ? intval($_POST['id_especializacao']) : 0;
        $novo_preco = isset($_POST['novo_preco']) ? floatval($_POST['novo_preco']) : 0;
        if (atualizar_preco_especializacao($pdo, $id_especializacao, $novo_preco)) {
            $sucesso = 'Preço atualizado com sucesso!';
        }
    } elseif ($acao === 'marcar_lida') {
        $id_notificacao = isset($_POST['id_notificacao']) ? intval($_POST['id_notificacao']) : 0;
        marcar_notificacao_lida($pdo, $id_notificacao);
        $sucesso = 'Notificação marcada como lida!';
    } elseif ($acao === 'criar_bloqueio') {
        $tipo_bloqueio = isset($_POST['tipo_bloqueio']) ? trim($_POST['tipo_bloqueio']) : '';
        $data_inicio = isset($_POST['data_inicio']) ? trim($_POST['data_inicio']) : '';
        $data_fim = isset($_POST['data_fim']) ? trim($_POST['data_fim']) : null;
        $horario_inicio = isset($_POST['horario_inicio']) ? trim($_POST['horario_inicio']) : null;
        $horario_fim = isset($_POST['horario_fim']) ? trim($_POST['horario_fim']) : null;
        $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : null;
        if (!empty($tipo_bloqueio) && !empty($data_inicio)) {
            if (criar_bloqueio_agenda($pdo, $tipo_bloqueio, $data_inicio, $data_fim ?: null, $horario_inicio ?: null, $horario_fim ?: null, $motivo ?: null)) {
                $sucesso = 'Bloqueio criado com sucesso!';
            } else {
                $erro = 'Erro ao criar bloqueio.';
            }
        } else {
            $erro = 'Preencha todos os campos obrigatórios.';
        }
    } elseif ($acao === 'remover_bloqueio') {
        $id_bloqueio = isset($_POST['id_bloqueio']) ? intval($_POST['id_bloqueio']) : 0;
        if (remover_bloqueio_agenda($pdo, $id_bloqueio)) {
            $sucesso = 'Bloqueio removido com sucesso!';
        } else {
            $erro = 'Erro ao remover bloqueio.';
        }
    } elseif ($acao === 'atualizar_perfil') {
        $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
        $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : '';
        $crp = isset($_POST['crp']) ? trim($_POST['crp']) : '';
        $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
        if (!empty($nome)) {
            $stmt = $pdo->prepare("UPDATE psicologa SET nome = ?, telefone = ?, crp = ?, bio = ? WHERE id_psicologa = ?");
            if ($stmt->execute([$nome, $telefone, $crp, $bio, $id_psicologa])) {
                $sucesso = 'Perfil atualizado com sucesso!';
            } else {
                $erro = 'Erro ao atualizar perfil.';
            }
        } else {
            $erro = 'O nome é obrigatório.';
        }
    } elseif ($acao === 'atualizar_precos') {
        $atualizados = 0;
        foreach ($_POST as $chave => $valor) {
            if (strpos($chave, 'preco_') === 0) {
                $id_esp = intval(str_replace('preco_', '', $chave));
                $preco = floatval($valor);
                if ($id_esp > 0 && $preco >= 0) {
                    atualizar_preco_especializacao($pdo, $id_esp, $preco);
                    $atualizados++;
                }
            }
        }
        $sucesso = "$atualizados especialidade(s) atualizada(s) com sucesso!";
    } elseif ($acao === 'alterar_senha') {
        $senha_atual = isset($_POST['senha_atual']) ? trim($_POST['senha_atual']) : '';
        $nova_senha = isset($_POST['nova_senha']) ? trim($_POST['nova_senha']) : '';
        $confirmar = isset($_POST['confirmar_nova_senha']) ? trim($_POST['confirmar_nova_senha']) : '';
        $stmt = $pdo->prepare("SELECT senha FROM psicologa WHERE id_psicologa = ?");
        $stmt->execute([$id_psicologa]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($senha_atual, $row['senha'])) {
            $erro = 'Senha atual incorreta.';
        } elseif ($nova_senha !== $confirmar) {
            $erro = 'As novas senhas não coincidem.';
        } elseif (strlen($nova_senha) < 6) {
            $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
        } else {
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt2 = $pdo->prepare("UPDATE psicologa SET senha = ? WHERE id_psicologa = ?");
            if ($stmt2->execute([$hash, $id_psicologa])) {
                $sucesso = 'Senha alterada com sucesso!';
            } else {
                $erro = 'Erro ao alterar senha.';
            }
        }
    }

    if (!empty($sucesso)) {
        $_SESSION['flash_sucesso'] = $sucesso;
    }
    if (!empty($erro)) {
        $_SESSION['flash_erro'] = $erro;
    }

    header('Location: dashboard_psicologa.php?aba=' . urlencode($aba_ativa));
    exit;
}

// Obter dados para o dashboard
$consultas_hoje = obter_consultas_por_data($pdo, date('Y-m-d'));
$proximas_consultas = obter_proximas_consultas($pdo, 5);
$todas_consultas_psicologa = obter_todas_consultas($pdo);
$agora = time();
$obter_timestamp_consulta = function($consulta) {
    return strtotime(trim($consulta['data_calendario'] . ' ' . $consulta['horario']));
};
$consultas_futuras_psicologa = array_filter($todas_consultas_psicologa, function($consulta) use ($agora, $obter_timestamp_consulta) {
    $inicio = $obter_timestamp_consulta($consulta);
    return $consulta['status'] !== 'Cancelada' && $inicio && $inicio >= $agora;
});
$consultas_passadas_psicologa = array_filter($todas_consultas_psicologa, function($consulta) use ($agora, $obter_timestamp_consulta) {
    $inicio = $obter_timestamp_consulta($consulta);
    return $consulta['status'] !== 'Cancelada' && $inicio && $inicio < $agora;
});
usort($consultas_futuras_psicologa, function($a, $b) use ($obter_timestamp_consulta) {
    return $obter_timestamp_consulta($a) <=> $obter_timestamp_consulta($b);
});
usort($consultas_passadas_psicologa, function($a, $b) use ($obter_timestamp_consulta) {
    return $obter_timestamp_consulta($b) <=> $obter_timestamp_consulta($a);
});
$total_consultas = obter_total_consultas($pdo);
$total_pacientes = obter_total_pacientes($pdo);
$receita_mes = obter_receita_mes($pdo);
$receita_ano = obter_receita_ano($pdo);
$notificacoes = obter_notificacoes_psicologa($pdo, $id_psicologa, 5);
$notificacoes_nao_lidas = contar_notificacoes_nao_lidas($pdo, null, $id_psicologa);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Nexus Premium</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/saas-premium.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
    <script src="assets/js/dashboard_novo.js" defer></script>
    <style>
        .notificacao-item.nao-lida { border-left: 4px solid #6366f1 !important; }
        .notificacao-item.lida { opacity: 0.7; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 15% auto; padding: 20px; border-radius: 12px; width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .modal-header { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-footer { margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px; }
        textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-top: 10px; resize: vertical; }
        .btn-cancelar-modal { background-color: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; }
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
                <a href="?aba=agenda" class="nav-item <?php echo $aba_ativa === 'agenda' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>Agenda</span>
                </a>
                <a href="?aba=pacientes" class="nav-item <?php echo $aba_ativa === 'pacientes' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m4 5H9m6 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Pacientes</span>
                </a>
                <a href="?aba=financeiro" class="nav-item <?php echo $aba_ativa === 'financeiro' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Financeiro</span>
                </a>
                <a href="?aba=especialidades" class="nav-item <?php echo $aba_ativa === 'especialidades' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Especialidades</span>
                </a>
                <a href="?aba=notificacoes" class="nav-item <?php echo $aba_ativa === 'notificacoes' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <span>Notificações <?php if ($notificacoes_nao_lidas > 0) echo '<span class="badge">' . $notificacoes_nao_lidas . '</span>'; ?></span>
                </a>
                <a href="?aba=configuracoes" class="nav-item <?php echo $aba_ativa === 'configuracoes' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span>Configurações</span>
                </a>
                <a href="?aba=perfil" class="nav-item <?php echo $aba_ativa === 'perfil' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span>Meu Perfil</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="usuario-info">
                    <div class="usuario-avatar"><?php echo strtoupper(substr($psicologa['nome'] ?? 'P', 0, 1)); ?></div>
                    <div class="usuario-dados">
                        <p class="usuario-nome"><?php echo htmlspecialchars($psicologa['nome'] ?? 'Psicóloga'); ?></p>
                        <p class="usuario-email"><?php echo htmlspecialchars($psicologa['email'] ?? ''); ?></p>
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
                        case 'agenda': $titulo = "Agenda"; break;
                        case 'pacientes': $titulo = "Meus Pacientes"; break;
                        case 'financeiro': $titulo = "Financeiro"; break;
                        case 'especialidades': $titulo = "Especialidades"; break;
                        case 'notificacoes': $titulo = "Notificações"; break;
                        case 'configuracoes': $titulo = "Configurações"; break;
                        case 'perfil': $titulo = "Meu Perfil"; break;
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
                <!-- Título e ícone de sino removidos para evitar duplicidade e atender solicitação -->

                <!-- Calendário Central -->
                <div class="calendario-secao">
                    <div class="calendario-header">
                        <h2>Calendário de Atendimentos</h2>
                    </div>
                    <div id="calendar"></div>
                    <div class="horarios-grid">
                        <h3>Horários Disponíveis Hoje</h3>
                        <div class="horarios-container">
                            <?php 
                            $horarios_padrao = obter_horarios($pdo);
                            $horarios_hoje = obter_horarios_disponiveis($pdo, $pdo->query("SELECT id_data FROM datas_disponiveis WHERE data_calendario = '" . date('Y-m-d') . "'")->fetchColumn());
                            $ids_disponiveis = array_column($horarios_hoje, 'id_horario');
                            
                            foreach ($horarios_padrao as $h): 
                                $disponivel = in_array($h['id_horario'], $ids_disponiveis);
                            ?>
                                <div class="horario-item <?php echo $disponivel ? '' : 'indisponivel'; ?>">
                                    <?php echo $h['horario']; ?>h
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agenda -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'agenda' ? 'ativo' : ''; ?>" id="aba-agenda">
                <div class="secao">
                    <h2>Minhas Consultas</h2>
                    <h3 class="subsecao-titulo">Próximas consultas</h3>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Data/Hora</th>
                                    <th>Especialidade</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultas_futuras_psicologa as $consulta): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($consulta['paciente_nome']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($consulta['data_calendario'])) . ' ' . $consulta['horario']; ?></td>
                                        <td><?php echo htmlspecialchars($consulta['especializacao']); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($consulta['status']); ?>"><?php echo $consulta['status']; ?></span></td>
                                        <td>
                                            <?php if ($consulta['status'] === 'Pendente'): ?>
                                                <button class="btn btn-pequeno btn-confirmar" onclick="abrirModalAcao('confirmar', <?php echo $consulta['id_consulta']; ?>)">Confirmar</button>
                                                <button class="btn btn-pequeno btn-cancelar-modal" onclick="abrirModalAcao('cancelar', <?php echo $consulta['id_consulta']; ?>)">Cancelar</button>
                                            <?php elseif ($consulta['status'] === 'Confirmada'): ?>
                                                <button class="btn btn-pequeno btn-cancelar-modal" onclick="abrirModalAcao('cancelar', <?php echo $consulta['id_consulta']; ?>)">Cancelar</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($consultas_futuras_psicologa)): ?>
                                    <tr>
                                        <td colspan="5" class="tabela-vazio">Nenhuma consulta futura.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="subsecao-titulo subsecao-titulo-passadas">Consultas que já passaram</h3>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Data/Hora</th>
                                    <th>Especialidade</th>
                                    <th>Status</th>
                                    <th>Pagamento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultas_passadas_psicologa as $consulta): ?>
                                    <tr class="consulta-passada-row">
                                        <td><?php echo htmlspecialchars($consulta['paciente_nome']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($consulta['data_calendario'])) . ' ' . $consulta['horario']; ?></td>
                                        <td><?php echo htmlspecialchars($consulta['especializacao']); ?></td>
                                        <td>
                                            <span class="status-badge status-passada">Passada</span>
                                            <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>"><?php echo $consulta['status']; ?></span>
                                        </td>
                                        <td><span class="status-badge status-<?php echo strtolower($consulta['pagamento_status']); ?>"><?php echo $consulta['pagamento_status']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($consultas_passadas_psicologa)): ?>
                                    <tr>
                                        <td colspan="5" class="tabela-vazio">Nenhuma consulta passada.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pacientes -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'pacientes' ? 'ativo' : ''; ?>" id="aba-pacientes">
                <div class="secao">
                    <h2>Meus Pacientes</h2>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Telefone</th>
                                    <th>Data Cadastro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $stmt = $pdo->query("SELECT * FROM pacientes ORDER BY nome ASC");
                                $todos_pacientes = $stmt->fetchAll();
                                foreach ($todos_pacientes as $p): 
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($p['email']); ?></td>
                                        <td><?php echo htmlspecialchars($p['telefone']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($p['data_criacao'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Financeiro -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'financeiro' ? 'ativo' : ''; ?>" id="aba-financeiro">
                <?php include 'views/dashboard_psicologa_financeiro.php'; ?>
            </div>

            <!-- Especialidades -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'especialidades' ? 'ativo' : ''; ?>" id="aba-especialidades">
                <?php include 'views/dashboard_psicologa_especialidades.php'; ?>
            </div>

            <!-- Configurações -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'configuracoes' ? 'ativo' : ''; ?>" id="aba-configuracoes">
                <?php include 'views/dashboard_psicologa_configuracoes.php'; ?>
            </div>

            <!-- Perfil -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'perfil' ? 'ativo' : ''; ?>" id="aba-perfil">
                <?php include 'views/dashboard_psicologa_perfil.php'; ?>
            </div>

            <!-- Notificações -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'notificacoes' ? 'ativo' : ''; ?>" id="aba-notificacoes">
                <div class="secao">
                    <h2>Notificações</h2>
                    <div class="notificacoes-lista">
                        <?php foreach ($notificacoes as $notif): ?>
                            <div class="notificacao-item <?php echo $notif['lida'] ? 'lida' : 'nao-lida'; ?>" style="<?php echo $notif['lida'] ? 'opacity: 0.7;' : 'border-left: 4px solid #6366f1;'; ?>">
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

        </main>

        <!-- WIDGETS DIREITA -->
        <aside class="widgets-coluna">
            <!-- Próximas Consultas -->
            <div class="widget">
                <div class="widget-header">
                    <h3>Próximas Consultas</h3>
                    <a href="?aba=agenda" class="widget-link">Ver tudo</a>
                </div>
                <div class="proximas-consultas">
                    <?php foreach (array_slice($proximas_consultas, 0, 3) as $consulta): ?>
                        <div class="consulta-card">
                            <div class="consulta-card-titulo"><?php echo htmlspecialchars($consulta['paciente_nome']); ?></div>
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

            <!-- Notificações Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h3>Notificações Recentes</h3>
                    <a href="?aba=notificacoes" class="widget-link">Ver tudo</a>
                </div>
                <div class="notificacoes-lista">
                    <?php foreach (array_slice($notificacoes, 0, 2) as $notif): ?>
                        <div class="notificacao-item <?php echo $notif['lida'] ? 'lida' : 'nao-lida'; ?>">
                            <div class="notificacao-titulo"><?php echo htmlspecialchars(formatar_tipo_notificacao($notif['tipo'])); ?></div>
                            <div class="notificacao-desc"><?php echo htmlspecialchars(substr($notif['mensagem'], 0, 60)); ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($notificacoes)): ?>
                        <p style="color: #9ca3af; font-size: 13px; text-align: center; padding: 8px;">Nenhuma notificação.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Métricas -->
            <div class="widget">
                <div class="widget-header">
                    <h3>Métricas</h3>
                </div>
                <div class="metricas-grid">
                    <div class="metrica-card">
                        <div class="metrica-label">Total de Consultas</div>
                        <div class="metrica-valor"><?php echo $total_consultas; ?></div>
                    </div>
                    <div class="metrica-card">
                        <div class="metrica-label">Pacientes Ativos</div>
                        <div class="metrica-valor"><?php echo $total_pacientes; ?></div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <div id="modalAcao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitulo">Ação</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" id="inputAcao">
                <input type="hidden" name="id_consulta" id="inputIdConsulta">
                <p id="modalMensagem">Deseja realizar esta ação?</p>
                <textarea name="comentario" placeholder="Adicione um comentário para o paciente (opcional)" rows="3"></textarea>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secundario" onclick="fecharModalAcao()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnConfirmarAcao">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function abrirModalAcao(tipo, id) {
            document.getElementById('inputAcao').value = tipo + '_consulta';
            document.getElementById('inputIdConsulta').value = id;
            document.getElementById('modalTitulo').textContent = tipo === 'confirmar' ? 'Confirmar Consulta' : 'Cancelar Consulta';
            document.getElementById('modalMensagem').textContent = tipo === 'confirmar' ? 'Deseja confirmar esta consulta?' : 'Tem certeza que deseja cancelar esta consulta?';
            document.getElementById('btnConfirmarAcao').className = tipo === 'confirmar' ? 'btn btn-primary' : 'btn-cancelar-modal';
            document.getElementById('modalAcao').style.display = 'block';
        }
        function fecharModalAcao() {
            document.getElementById('modalAcao').style.display = 'none';
        }
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('modalAcao');
            if (modal && event.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
    <script>
        // Modo Escuro
        const btnDarkMode = document.getElementById('btn-dark-mode');
        const body = document.body;

        // Verificar preferência salva
        if (localStorage.getItem('darkMode') === 'true') {
            body.classList.add('dark-mode');
        }

        // Toggle modo escuro
        btnDarkMode.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', body.classList.contains('dark-mode'));
        });
    </script>
</body>
</html>
