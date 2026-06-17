<?php
require_once 'config/conexao.php';
require_once 'config/funcoes.php';
require_once 'config/inicializar_datas.php';

// Garantir que o banco de dados está atualizado
verificar_esquema_banco($pdo);

// Verificar se está logado
if (!isset($_SESSION['id_paciente'])) {
    header('Location: login.php');
    exit;
}

$id_paciente = $_SESSION['id_paciente'];
$paciente = obter_paciente($pdo, $id_paciente);

// Obter aba ativa (redirecionar agendar antigo para calendario)
$aba_ativa = isset($_GET['aba']) ? $_GET['aba'] : 'dashboard';
if ($aba_ativa === 'agendar') {
    header('Location: dashboard_paciente.php?aba=calendario');
    exit;
}

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
        $consulta_pag = obter_consulta($pdo, $id_consulta);
        if (!$consulta_pag) {
            $erro = 'Consulta não encontrada.';
        } elseif ($consulta_pag['id_paciente'] != $id_paciente) {
            $erro = 'Sem permissão para pagar esta consulta.';
        } elseif ($consulta_pag['status'] === 'Cancelada') {
            $erro = 'Não é possível pagar uma consulta cancelada.';
        } elseif ($consulta_pag['status'] !== 'Confirmada') {
            $erro = 'O pagamento só pode ser realizado para consultas confirmadas pela psicóloga.';
        } elseif ($consulta_pag['pagamento_status'] === 'Pago' || $consulta_pag['pagamento_status'] === 'Concluído') {
            $erro = 'Esta consulta já foi paga.';
        } elseif (processar_pagamento($pdo, $id_consulta, $id_paciente, $metodo)) {
            $sucesso = 'Pagamento processado com sucesso! Sua consulta está confirmada.';
        } else {
            $erro = 'Erro ao processar pagamento. Tente novamente.';
        }
    } elseif ($acao === 'cancelar_consulta') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        $consulta_cancelar = obter_consulta($pdo, $id_consulta);
        if ($consulta_cancelar && $consulta_cancelar['id_paciente'] == $id_paciente) {
            if ($consulta_cancelar['status'] === 'Cancelada') {
                $erro = 'Esta consulta já foi cancelada.';
            } elseif (!consulta_pode_ser_cancelada_pelo_paciente($consulta_cancelar)) {
                $erro = 'A consulta só pode ser cancelada com pelo menos 24 horas de antecedência.';
            } elseif (cancelar_consulta($pdo, $id_consulta, $id_paciente, 1, false, null, 'paciente')) {
                criar_notificacao($pdo, $id_paciente, 1, 'cancelamento', "Sua consulta foi cancelada com sucesso.", 'paciente');
                $data_cancel_fmt = date('d/m/Y', strtotime($consulta_cancelar['data_calendario']));
                $hora_cancel_fmt = substr($consulta_cancelar['horario'], 0, 5);
                criar_notificacao($pdo, $id_paciente, 1, 'cancelamento', "A(o) paciente {$paciente['nome']} cancelou a consulta do dia $data_cancel_fmt as $hora_cancel_fmt.", 'psicologa');
                $sucesso = 'Consulta cancelada com sucesso!';
            } else {
                $erro = 'Erro ao cancelar consulta.';
            }
        } else {
            $erro = 'Consulta não encontrada ou sem permissão.';
        }
    } elseif ($acao === 'reagendar_consulta') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        $nova_data = isset($_POST['nova_data']) ? $_POST['nova_data'] : '';
        $novo_horario = isset($_POST['id_horario']) ? intval($_POST['id_horario']) : 0;
        $consulta_reag = obter_consulta($pdo, $id_consulta);
        if (!$consulta_reag || $consulta_reag['id_paciente'] != $id_paciente) {
            $erro = 'Consulta nao encontrada ou sem permissao.';
        } elseif (reagendar_consulta($pdo, $id_consulta, $id_paciente, $nova_data, $novo_horario)) {
            $sucesso = 'Consulta reagendada com sucesso!';
        } else {
            $erro = 'Erro ao reagendar consulta.';
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
        $foto_perfil = null;
        
        // Processar upload de foto
        if (!empty($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $diretorio = __DIR__ . '/uploads/fotos/';
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0777, true);
            }
            $extensao = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
            $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($extensao, $extensoes_permitidas)) {
                $erro = 'Formato de imagem nao permitido. Use: JPG, PNG, GIF ou WEBP.';
            } elseif ($_FILES['foto_perfil']['size'] > 5 * 1024 * 1024) {
                $erro = 'A imagem deve ter no maximo 5MB.';
            } else {
                $nome_arquivo = uniqid() . '.' . $extensao;
                $caminho = $diretorio . $nome_arquivo;
                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $caminho)) {
                    $foto_perfil = 'uploads/fotos/' . $nome_arquivo;
                } else {
                    $erro = 'Erro ao fazer upload da imagem.';
                }
            }
        }
        
        if (empty($erro) && !empty($nome) && !empty($email)) {
            try {
                if ($foto_perfil) {
                    $stmt = $pdo->prepare("UPDATE pacientes SET nome = ?, email = ?, telefone = ?, data_nascimento = ?, cpf = ?, endereco = ?, foto_perfil = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $telefone, $data_nascimento, $cpf, $endereco, $foto_perfil, $id_paciente]);
                } else {
                    $stmt = $pdo->prepare("UPDATE pacientes SET nome = ?, email = ?, telefone = ?, data_nascimento = ?, cpf = ?, endereco = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $telefone, $data_nascimento, $cpf, $endereco, $id_paciente]);
                }
                $sucesso = 'Perfil atualizado com sucesso!';
                $paciente = obter_paciente($pdo, $id_paciente);
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar perfil.';
            }
        } elseif (empty($erro)) {
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
$consultas = obter_consultas_paciente($pdo, $id_paciente);
$hoje = date('Y-m-d');
$agora = time();
$obter_timestamp_consulta = function($consulta) {
    return strtotime(trim($consulta['data_calendario'] . ' ' . $consulta['horario']));
};
$proximas_consultas = array_filter($consultas, function($c) use ($agora, $obter_timestamp_consulta) {
    $inicio = $obter_timestamp_consulta($c);
    return $c['status'] !== 'Cancelada' && $inicio && $inicio >= $agora;
});
$consultas_passadas = array_filter($consultas, function($c) use ($agora, $obter_timestamp_consulta) {
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
$minhas_consultas = $consultas; // Alias para uso nas abas de consultas e pagamentos
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Dashboard - Nexus Premium</title>
    <link rel="icon" href="assets/simbologo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&family=Lora:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboards.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.js'></script>
    <script src="js/dashboard_paciente.js" defer></script>
    <style>
        /* ═══════════════════════════════════════════════════════════════
           OVERRIDES NEXUS — Paciente
        ═══════════════════════════════════════════════════════════════ */

        .notificacao-item.nao-lida {
            border-left: 4px solid var(--azul-sereno) !important;
            background: rgba(128,161,212,.06);
        }
        .notificacao-item.lida { opacity: 0.6; }

        .notificacao-icone {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: rgba(128,161,212,.08);
            flex-shrink: 0;
        }

        .notificacao-item {
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: rgba(247,244,234,.4);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--warning);
        }

        .notificacao-conteudo { flex: 1; min-width: 0; }

        .notificacao-conteudo h4 {
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 600;
            color: var(--grafite);
            margin-bottom: var(--spacing-xs);
        }

        .notificacao-conteudo p {
            font-family: var(--font-body);
            font-size: 13px;
            color: var(--grafite);
            opacity: 0.55;
            margin-bottom: var(--spacing-xs);
            line-height: 1.5;
        }

        .notificacao-data {
            font-family: var(--font-body);
            font-size: 11px;
            color: var(--grafite);
            opacity: 0.35;
        }

        .vazio-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-2xl);
            color: var(--grafite);
            opacity: 0.4;
        }

        .vazio-container p {
            font-family: var(--font-body);
            font-size: 14px;
        }

        .consulta-card-moderno {
            background: rgba(255,255,255,.65);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,.5);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--azul-sereno);
            transition: all 0.3s cubic-bezier(.4,0,.2,1);
            position: relative;
            overflow: hidden;
        }
        .consulta-card-moderno::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, rgba(128,161,212,.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .consulta-card-moderno:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }
        .consulta-card-moderno.passada {
            border-left-color: var(--lavanda);
            opacity: 0.55;
        }
        .consulta-card-moderno.passada::before {
            background: radial-gradient(circle, rgba(192,185,221,.08) 0%, transparent 70%);
        }
        .consulta-card-moderno .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .consulta-card-moderno h3 {
            font-family: var(--font-titulo);
            font-size: 16px;
            font-weight: 700;
            color: var(--grafite);
            margin-bottom: 8px;
        }
        .consulta-card-moderno .card-meta {
            font-family: var(--font-body);
            font-size: 12px;
            color: var(--grafite);
            opacity: 0.5;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        .consulta-card-moderno .card-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .consulta-card-moderno .card-meta svg {
            width: 14px;
            height: 14px;
            opacity: 0.6;
        }
        .consulta-card-moderno .card-actions { margin-top: 16px; }
        .consulta-card-moderno .card-actions .btn { width: 100%; }

        .dashboard-empty {
            text-align: center;
            color: var(--grafite);
            opacity: 0.35;
            padding: 32px;
            font-family: var(--font-body);
            font-size: 14px;
            background: rgba(255,255,255,.3);
            border-radius: var(--radius-lg);
            border: 2px dashed rgba(222,217,226,.3);
        }

        .consulta-card-moderno .card-status-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .section-title {
            margin-bottom: 24px;
            font-family: var(--font-titulo);
            font-size: 22px;
            font-weight: 700;
            color: var(--grafite);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: var(--gradiente-principal);
            border-radius: 2px;
        }

        .section-gap { margin-top: 24px; }
        .card-grid { display: grid; gap: 16px; }

        .calendario-secao {
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,.65);
            backdrop-filter: blur(20px) saturate(1.2);
            -webkit-backdrop-filter: blur(20px) saturate(1.2);
            border: 1px solid rgba(255,255,255,.5);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-md), inset 0 1px 0 rgba(255,255,255,.7);
        }
        .calendario-secao::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(128,161,212,.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .calendario-secao::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -15%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(117,201,200,.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .calendario-secao > * { position: relative; z-index: 1; }

        body.dark-mode .notificacao-item {
            background: rgba(255,255,255,.05);
        }
        body.dark-mode .notificacao-conteudo h4,
        body.dark-mode .notificacao-conteudo p,
        body.dark-mode .notificacao-data {
            color: var(--branco);
        }
        body.dark-mode .notificacao-conteudo p { opacity: 0.55; }
        body.dark-mode .notificacao-data { opacity: 0.35; }
        body.dark-mode .vazio-container { color: var(--branco); }
        body.dark-mode .consulta-card-moderno {
            background: rgba(30,30,50,.7);
            border-color: rgba(255,255,255,.1);
        }
        body.dark-mode .consulta-card-moderno h3,
        body.dark-mode .consulta-card-moderno .card-meta {
            color: var(--branco);
        }
        body.dark-mode .consulta-card-moderno .card-meta { opacity: 0.5; }
        body.dark-mode .dashboard-empty {
            color: var(--branco);
            background: rgba(255,255,255,.04);
            border-color: rgba(255,255,255,.08);
        }
        body.dark-mode .section-title { color: var(--branco); }
        body.dark-mode .calendario-secao {
            background: rgba(30,30,50,.7);
            border-color: rgba(255,255,255,.08);
        }
    </style>
</head>
<body>
<script>if(localStorage.getItem('darkMode')==='true'||localStorage.getItem('darkMode')==='enabled')document.body.classList.add('dark-mode')</script>
    <div class="dashboard-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.html" class="logo-link">
                    <img src="assets/logo.png" alt="Nexus Logo" class="sidebar-logo">
                </a>
            </div>

            <nav class="sidebar-nav">
                <a href="?aba=dashboard" class="nav-item <?php echo $aba_ativa === 'dashboard' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 16l4-4m0 0l4 4m-4-4v4"></path>
                    </svg>
                    <span>Dashboard</span>
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
                        case 'calendario': $titulo = "Meu Calendario"; break;
                        case 'consultas': $titulo = "Minhas Consultas"; break;
                        case 'pagamentos': $titulo = "Pagamentos"; break;
                        case 'notificacoes': $titulo = "Notificações"; break;
                        case 'perfil': $titulo = "Perfil"; break;
                    }
                    echo $titulo;
                ?></h1>
                <div class="header-actions">
                    <button class="btn-notificacoes" onclick="window.location.href='?aba=notificacoes'" title="Ver notificações">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if ($notificacoes_nao_lidas > 0): ?>
                            <span class="badge"><?php echo $notificacoes_nao_lidas; ?></span>
                        <?php endif; ?>
                    </button>
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
                    <h2 class="section-title">Suas Próximas Consultas</h2>
                    <div class="card-grid">
                        <?php 
                        foreach (array_slice($proximas_consultas, 0, 3) as $consulta): 
                        ?>
                            <div class="consulta-card-moderno">
                                <div class="card-header">
                                    <div>
                                        <h3><?php echo htmlspecialchars($consulta['especializacao']); ?></h3>
                                        <div class="card-meta">
                                            <span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                                <?php echo date('d/m/Y', strtotime($consulta['data_calendario'])); ?>
                                            </span>
                                            <span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                <?php echo substr($consulta['horario'], 0, 5); ?>h
                                            </span>
                                            <span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                                <?php echo htmlspecialchars($consulta['modalidade']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                                        <?php echo $consulta['status']; ?>
                                    </span>
                                </div>
                                <?php if ($consulta['status'] === 'Confirmada' && $consulta['pagamento_status'] === 'Pendente'): ?>
                                    <div class="card-actions">
                                        <button class="btn btn-primary" onclick="abrirModalPagamento(<?php echo $consulta['id_consulta']; ?>, <?php echo $consulta['valor']; ?>)">
                                            Realizar Pagamento
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($proximas_consultas)): ?>
                            <p class="dashboard-empty">Você não tem consultas agendadas.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="calendario-secao section-gap">
                    <h2 class="section-title">Consultas que já passaram</h2>
                    <div class="card-grid">
                        <?php foreach (array_slice($consultas_passadas, 0, 5) as $consulta): ?>
                            <div class="consulta-card-moderno passada">
                                <div class="card-header">
                                    <div>
                                        <h3><?php echo htmlspecialchars($consulta['especializacao']); ?></h3>
                                        <div class="card-meta">
                                            <span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                                <?php echo date('d/m/Y', strtotime($consulta['data_calendario'])); ?>
                                            </span>
                                            <span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                <?php echo substr($consulta['horario'], 0, 5); ?>h
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-status-group">
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

            <!-- Calendario -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'calendario' ? 'ativo' : ''; ?>" id="aba-calendario">
                <?php include 'views/dashboard_paciente_calendario.php'; ?>
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
                                        <td><?php echo date('d/m/Y', strtotime($consulta['data_calendario'])) . ' ' . substr($consulta['horario'], 0, 5) . 'h'; ?></td>
                                        <td><?php echo htmlspecialchars($consulta['modalidade']); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($consulta['status']); ?>"><?php echo $consulta['status']; ?></span></td>
                                        <td><span class="status-badge status-<?php echo strtolower($consulta['pagamento_status']); ?>"><?php echo $consulta['pagamento_status']; ?></span></td>
                                        <td>
                                            <?php if ($consulta['status'] === 'Cancelada'): ?>
                                                <span style="font-size: 12px; color: #ef4444; font-weight: 600;">Cancelada</span>
                                            <?php elseif (consulta_pode_ser_cancelada_pelo_paciente($consulta)): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja cancelar esta consulta?');">
                                                    <input type="hidden" name="acao" value="cancelar_consulta">
                                                    <input type="hidden" name="id_consulta" value="<?php echo $consulta['id_consulta']; ?>">
                                                    <button type="submit" class="btn btn-pequeno btn-cancelar">Cancelar</button>
                                                </form>
                                            <?php else: ?>
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
                                        <td><?php echo date('d/m/Y', strtotime($pag['data_calendario'])) . ' ' . substr($pag['horario'], 0, 5) . 'h'; ?></td>
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
                <?php include 'views/dashboard_paciente_notificacoes.php'; ?>
            </div>

            <!-- Perfil -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'perfil' ? 'ativo' : ''; ?>" id="aba-perfil">
                <div class="secao">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <h2 style="margin: 0;">Meu Perfil</h2>
                        <button class="btn btn-primary" id="btn-editar-perfil" onclick="ativarEdicaoPerfil()">Editar Perfil</button>
                    </div>
                    <form method="POST" id="form-perfil" class="form-row" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="editar_perfil">

                        <!-- Foto de Perfil -->
                        <div style="grid-column: 1 / -1; display: flex; align-items: center; gap: 20px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #f3f4f6;">
                            <div style="position: relative;">
                                <div id="foto-preview-paciente" style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 32px; font-weight: 700; flex-shrink: 0; overflow: hidden; cursor: pointer;" onclick="document.getElementById('foto_perfil_paciente').click()">
                                    <?php if (!empty($paciente['foto_perfil'])): ?>
                                        <img src="<?php echo htmlspecialchars($paciente['foto_perfil']); ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <span><?php echo strtoupper(substr($paciente['nome'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <input type="file" id="foto_perfil_paciente" name="foto_perfil" class="foto-upload-input" accept="image/*" style="display: none;" onchange="previewFotoPerfilPaciente(this)">
                                <label for="foto_perfil_paciente" id="btn-alterar-foto" style="position: absolute; bottom: 0; right: 0; background: #6366f1; color: white; width: 28px; height: 28px; border-radius: 50%; display: none; align-items: center; justify-content: center; cursor: pointer; border: 2px solid white; font-size: 14px;" title="Alterar foto">&#9998;</label>
                            </div>
                            <div>
                                <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0 0 4px 0;"><?php echo htmlspecialchars($paciente['nome']); ?></h3>
                                <p style="color: #6b7280; margin: 0; font-size: 14px;"><?php echo htmlspecialchars($paciente['email']); ?></p>
                                <p id="foto-upload-hint" style="color: #9ca3af; margin: 4px 0 0 0; font-size: 12px; display: none;">Clique no botao para alterar a foto</p>
                            </div>
                        </div>

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
                                <?php echo date('d/m', strtotime($consulta['data_calendario'])) . ' ' . substr($consulta['horario'], 0, 5) . 'h'; ?>
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
                        <p style="color: var(--neutral-400); font-size: 13px; text-align: center; padding: 8px;">Nenhuma notificação.</p>
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
                <form method="POST" id="formPagamento" onsubmit="return confirmarPagamento()">
                    <input type="hidden" name="acao" value="processar_pagamento">
                    <input type="hidden" name="id_consulta" id="idConsultaPagamento">

                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; margin-bottom: 24px;">
                        <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Valor a Pagar</div>
                        <div style="font-size: 32px; font-weight: 800; color: #059669;">
                            R$ <span id="valorPagamento">0,00</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Método de Pagamento *</label>
                        <select name="metodo_pagamento" required style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;">
                            <option value="">Selecione um método</option>
                            <option value="Pix">Pix</option>
                            <option value="Cartao">Cartão de Crédito</option>
                            <option value="Boleto">Boleto</option>
                        </select>
                    </div>

                    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px; margin-bottom: 16px; font-size: 13px; color: #92400e;">
                        <strong>Atenção:</strong> Ao confirmar, o pagamento será registrado e sua consulta estará confirmada.
                    </div>

                    <div class="modal-acoes">
                        <button type="submit" class="btn btn-primary" id="btnConfirmarPagamento">Confirmar Pagamento</button>
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
            const valorNum = parseFloat(valor);
            document.getElementById('valorPagamento').textContent = isNaN(valorNum) ? '0,00' : valorNum.toFixed(2).replace('.', ',');
            document.getElementById('formPagamento').querySelector('select[name="metodo_pagamento"]').value = '';
            document.getElementById('modalPagamento').classList.add('show');
        }

        function fecharModalPagamento() {
            document.getElementById('modalPagamento').classList.remove('show');
        }

        function confirmarPagamento() {
            const metodo = document.getElementById('formPagamento').querySelector('select[name="metodo_pagamento"]').value;
            if (!metodo) {
                alert('Por favor, selecione um método de pagamento.');
                return false;
            }
            const btn = document.getElementById('btnConfirmarPagamento');
            btn.disabled = true;
            btn.textContent = 'Processando...';
            return true;
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
            document.getElementById('btn-alterar-foto').style.display = 'flex';
            document.getElementById('foto-upload-hint').style.display = 'block';
        }

        function cancelarEdicaoPerfil() {
            const campos = ['nome-perfil', 'email-perfil', 'telefone-perfil', 'data-nascimento-perfil', 'cpf-perfil', 'endereco-perfil'];
            campos.forEach(id => {
                const campo = document.getElementById(id);
                if (campo) campo.disabled = true;
            });
            document.getElementById('btn-editar-perfil').style.display = 'block';
            document.getElementById('botoes-edicao').style.display = 'none';
            document.getElementById('btn-alterar-foto').style.display = 'none';
            document.getElementById('foto-upload-hint').style.display = 'none';
        }

        function previewFotoPerfilPaciente(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById('foto-preview-paciente');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>

