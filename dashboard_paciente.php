<?php
/*
 * ARQUIVO: dashboard_paciente.php
 * DESCRIÇÃO: Página principal do dashboard do paciente.
 *
 * Este arquivo é o controlador central da área do paciente. Ele combina
 * lógica PHP (back-end) com HTML/CSS/JS (front-end) em um único arquivo.
 *
 * RESPONSABILIDADES:
 *   1. Verificar se o paciente está autenticado
 *   2. Processar formulários POST (agendar, pagar, cancelar, reagendar, editar perfil)
 *   3. Carregar dados do banco para exibir no dashboard
 *   4. Renderizar a interface completa com sidebar, abas e widgets
 *
 * ABAS DISPONÍVEIS (parâmetro GET ?aba=):
 *   - dashboard   → Visão geral com próximas consultas
 *   - calendario  → Calendário para novo agendamento
 *   - consultas   → Tabela com todas as consultas
 *   - pagamentos  → Histórico de pagamentos
 *   - notificacoes → Notificações do sistema
 *   - perfil      → Dados pessoais do paciente
 */

// ─── INICIALIZAÇÃO ────────────────────────────────────────────────────────────

// Inclui a conexão com o banco de dados e inicia a sessão
require_once 'config/conexao.php';
// Inclui todas as funções de negócio (agendar, cancelar, notificar, etc.)
require_once 'config/funcoes.php';
// Garante que as datas disponíveis estejam populadas no banco
require_once 'config/inicializar_datas.php';

// Garante que o banco de dados está atualizado (verifica colunas faltantes)
verificar_esquema_banco($pdo);

// ─── AUTENTICAÇÃO ─────────────────────────────────────────────────────────────

// Verifica se o paciente está logado (tem sessão ativa)
// Se não estiver, redireciona para a página de login
if (!isset($_SESSION['id_paciente'])) {
    header('Location: login.php');
    exit;
}

// Obtém o ID do paciente da sessão e carrega seus dados do banco
$id_paciente = $_SESSION['id_paciente'];
$paciente = obter_paciente($pdo, $id_paciente);

// ─── CONTROLE DE ABAS ─────────────────────────────────────────────────────────

// Obtém a aba ativa a partir do parâmetro GET (?aba=dashboard)
// Se não informado, usa 'dashboard' como padrão
$aba_ativa = isset($_GET['aba']) ? $_GET['aba'] : 'dashboard';

// Redireciona a aba antiga 'agendar' para a nova 'calendario'
// (compatibilidade com links antigos)
if ($aba_ativa === 'agendar') {
    header('Location: dashboard_paciente.php?aba=calendario');
    exit;
}

// ─── MENSAGENS FLASH ──────────────────────────────────────────────────────────

// Mensagens flash são armazenadas na sessão e exibidas apenas uma vez
// Usadas para mostrar feedback após redirecionamentos POST → GET
$sucesso = $_SESSION['flash_sucesso'] ?? '';
$erro = $_SESSION['flash_erro'] ?? '';
// Remove as mensagens da sessão após lê-las (exibição única)
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);

// ─── PROCESSAMENTO DE FORMULÁRIOS POST ────────────────────────────────────────

// Processa apenas requisições POST (envio de formulários)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Identifica qual ação foi solicitada pelo campo hidden 'acao'
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
    
    // ── AÇÃO: Agendar Nova Consulta ──────────────────────────────────────────
    if ($acao === 'agendar_consulta') {
        // Obtém e sanitiza os dados do formulário de agendamento
        $id_especializacao = isset($_POST['id_especializacao']) ? intval($_POST['id_especializacao']) : 0;
        $id_horario = isset($_POST['id_horario']) ? intval($_POST['id_horario']) : 0;
        $id_data = isset($_POST['id_data']) ? intval($_POST['id_data']) : 0;
        $modalidade = isset($_POST['modalidade']) ? $_POST['modalidade'] : 'Online';
        
        // Chama a função de agendamento e define a mensagem de feedback
        if (agendar_consulta($pdo, $id_paciente, $id_especializacao, $id_horario, $id_data, $modalidade)) {
            $sucesso = 'Consulta agendada com sucesso! Aguarde a confirmação da psicóloga.';
        } else {
            $erro = 'Erro ao agendar consulta.';
        }

    // ── AÇÃO: Processar Pagamento ────────────────────────────────────────────
    } elseif ($acao === 'processar_pagamento') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        $metodo = isset($_POST['metodo_pagamento']) ? $_POST['metodo_pagamento'] : 'Pix';
        $consulta_pag = obter_consulta($pdo, $id_consulta);

        // Validações antes de processar o pagamento
        if (!$consulta_pag) {
            $erro = 'Consulta não encontrada.';
        } elseif ($consulta_pag['id_paciente'] != $id_paciente) {
            // Segurança: impede pagar consulta de outro paciente
            $erro = 'Sem permissão para pagar esta consulta.';
        } elseif ($consulta_pag['status'] === 'Cancelada') {
            $erro = 'Não é possível pagar uma consulta cancelada.';
        } elseif ($consulta_pag['status'] !== 'Confirmada') {
            // Só permite pagar após a psicóloga confirmar
            $erro = 'O pagamento só pode ser realizado para consultas confirmadas pela psicóloga.';
        } elseif ($consulta_pag['pagamento_status'] === 'Pago' || $consulta_pag['pagamento_status'] === 'Concluído') {
            $erro = 'Esta consulta já foi paga.';
        } elseif (processar_pagamento($pdo, $id_consulta, $id_paciente, $metodo)) {
            $sucesso = 'Pagamento processado com sucesso! Sua consulta está confirmada.';
        } else {
            $erro = 'Erro ao processar pagamento. Tente novamente.';
        }

    // ── AÇÃO: Cancelar Consulta ──────────────────────────────────────────────
    } elseif ($acao === 'cancelar_consulta') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        $consulta_cancelar = obter_consulta($pdo, $id_consulta);

        // Verifica se a consulta existe e pertence ao paciente logado
        if ($consulta_cancelar && $consulta_cancelar['id_paciente'] == $id_paciente) {
            if ($consulta_cancelar['status'] === 'Cancelada') {
                $erro = 'Esta consulta já foi cancelada.';
            } elseif (!consulta_pode_ser_cancelada_pelo_paciente($consulta_cancelar)) {
                // Regra de negócio: cancelamento apenas com 24h de antecedência
                $erro = 'A consulta só pode ser cancelada com pelo menos 24 horas de antecedência.';
            } elseif (cancelar_consulta($pdo, $id_consulta, $id_paciente, 1, false, null, 'paciente')) {
                // Notifica o paciente sobre o cancelamento
                criar_notificacao($pdo, $id_paciente, 1, 'cancelamento', "Sua consulta foi cancelada com sucesso.", 'paciente');
                // Formata data e hora para a notificação da psicóloga
                $data_cancel_fmt = date('d/m/Y', strtotime($consulta_cancelar['data_calendario']));
                $hora_cancel_fmt = substr($consulta_cancelar['horario'], 0, 5);
                // Notifica a psicóloga sobre o cancelamento pelo paciente
                criar_notificacao($pdo, $id_paciente, 1, 'cancelamento', "A(o) paciente {$paciente['nome']} cancelou a consulta do dia $data_cancel_fmt as $hora_cancel_fmt.", 'psicologa');
                $sucesso = 'Consulta cancelada com sucesso!';
            } else {
                $erro = 'Erro ao cancelar consulta.';
            }
        } else {
            $erro = 'Consulta não encontrada ou sem permissão.';
        }

    // ── AÇÃO: Reagendar Consulta ─────────────────────────────────────────────
    } elseif ($acao === 'reagendar_consulta') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        $nova_data = isset($_POST['nova_data']) ? $_POST['nova_data'] : '';
        $novo_horario = isset($_POST['id_horario']) ? intval($_POST['id_horario']) : 0;
        $consulta_reag = obter_consulta($pdo, $id_consulta);

        // Verifica se a consulta existe e pertence ao paciente
        if (!$consulta_reag || $consulta_reag['id_paciente'] != $id_paciente) {
            $erro = 'Consulta nao encontrada ou sem permissao.';
        } elseif (reagendar_consulta($pdo, $id_consulta, $id_paciente, $nova_data, $novo_horario)) {
            $sucesso = 'Consulta reagendada com sucesso!';
        } else {
            $erro = 'Erro ao reagendar consulta.';
        }

    // ── AÇÃO: Marcar Notificação como Lida ──────────────────────────────────
    } elseif ($acao === 'marcar_lida') {
        $id_notificacao = isset($_POST['id_notificacao']) ? intval($_POST['id_notificacao']) : 0;
        marcar_notificacao_lida($pdo, $id_notificacao);

    // ── AÇÃO: Editar Perfil ──────────────────────────────────────────────────
    } elseif ($acao === 'editar_perfil') {
        // Obtém e sanitiza os dados do formulário de perfil
        $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : '';
        $data_nascimento = isset($_POST['data_nascimento']) ? $_POST['data_nascimento'] : '';
        $cpf = isset($_POST['cpf']) ? trim($_POST['cpf']) : '';
        $endereco = isset($_POST['endereco']) ? trim($_POST['endereco']) : '';
        $foto_perfil = null; // Será preenchido apenas se houver upload de foto
        
        // Processa o upload de foto de perfil (se enviado)
        if (!empty($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $diretorio = __DIR__ . '/uploads/fotos/';
            // Cria o diretório de uploads se não existir
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0777, true); // 0777 = permissões totais, true = recursivo
            }
            // Obtém a extensão do arquivo enviado (em minúsculas)
            $extensao = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
            $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            // Valida a extensão do arquivo
            if (!in_array($extensao, $extensoes_permitidas)) {
                $erro = 'Formato de imagem nao permitido. Use: JPG, PNG, GIF ou WEBP.';
            } elseif ($_FILES['foto_perfil']['size'] > 5 * 1024 * 1024) {
                // Valida o tamanho máximo: 5MB (5 * 1024 * 1024 bytes)
                $erro = 'A imagem deve ter no maximo 5MB.';
            } else {
                // Gera um nome único para o arquivo (evita sobrescrever arquivos existentes)
                $nome_arquivo = uniqid() . '.' . $extensao;
                $caminho = $diretorio . $nome_arquivo;
                // Move o arquivo temporário para o diretório de uploads
                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $caminho)) {
                    $foto_perfil = 'uploads/fotos/' . $nome_arquivo; // Caminho relativo para salvar no banco
                } else {
                    $erro = 'Erro ao fazer upload da imagem.';
                }
            }
        }
        
        // Salva os dados do perfil no banco (apenas se não houver erros e campos obrigatórios preenchidos)
        if (empty($erro) && !empty($nome) && !empty($email)) {
            try {
                if ($foto_perfil) {
                    // Atualiza perfil incluindo a nova foto
                    // Usa prepared statement para evitar SQL Injection
                    $stmt = $pdo->prepare("UPDATE pacientes SET nome = ?, email = ?, telefone = ?, data_nascimento = ?, cpf = ?, endereco = ?, foto_perfil = ? WHERE id = ?");
                    // Executa a consulta no banco de dados
                    $stmt->execute([$nome, $email, $telefone, $data_nascimento, $cpf, $endereco, $foto_perfil, $id_paciente]);
                } else {
                    // Atualiza perfil sem alterar a foto existente
                    // Usa prepared statement para evitar SQL Injection
                    $stmt = $pdo->prepare("UPDATE pacientes SET nome = ?, email = ?, telefone = ?, data_nascimento = ?, cpf = ?, endereco = ? WHERE id = ?");
                    // Executa a consulta no banco de dados
                    $stmt->execute([$nome, $email, $telefone, $data_nascimento, $cpf, $endereco, $id_paciente]);
                }
                $sucesso = 'Perfil atualizado com sucesso!';
                // Recarrega os dados do paciente para exibir os valores atualizados
                $paciente = obter_paciente($pdo, $id_paciente);
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar perfil.';
            }
        } elseif (empty($erro)) {
            $erro = 'Nome e email sao obrigatorios.';
        }
    }

    // Armazena as mensagens de feedback na sessão para exibir após o redirecionamento
    if (!empty($sucesso)) {
        $_SESSION['flash_sucesso'] = $sucesso;
    }
    if (!empty($erro)) {
        $_SESSION['flash_erro'] = $erro;
    }

    // Padrão PRG (Post/Redirect/Get): redireciona após o POST para evitar reenvio do formulário
    header('Location: dashboard_paciente.php?aba=' . urlencode($aba_ativa));
    exit;
}

// ─── CARREGAMENTO DE DADOS PARA O DASHBOARD ───────────────────────────────────

// Obtém todas as consultas do paciente
$consultas = obter_consultas_paciente($pdo, $id_paciente);
$hoje = date('Y-m-d'); // Data atual no formato do banco
$agora = time();       // Timestamp atual para comparações

// Função anônima para converter data+hora da consulta em timestamp Unix
// Usada para filtrar e ordenar consultas por data/hora
$obter_timestamp_consulta = function($consulta) {
    return strtotime(trim($consulta['data_calendario'] . ' ' . $consulta['horario']));
};

// Filtra as consultas futuras (não canceladas e com data/hora >= agora)
$proximas_consultas = array_filter($consultas, function($c) use ($agora, $obter_timestamp_consulta) {
    $inicio = $obter_timestamp_consulta($c);
    return $c['status'] !== 'Cancelada' && $inicio && $inicio >= $agora;
});

// Filtra as consultas passadas (não canceladas e com data/hora < agora)
$consultas_passadas = array_filter($consultas, function($c) use ($agora, $obter_timestamp_consulta) {
    $inicio = $obter_timestamp_consulta($c);
    return $c['status'] !== 'Cancelada' && $inicio && $inicio < $agora;
});

// Ordena as próximas consultas em ordem crescente (mais próxima primeiro)
usort($proximas_consultas, function($a, $b) use ($obter_timestamp_consulta) {
    return $obter_timestamp_consulta($a) <=> $obter_timestamp_consulta($b);
});

// Ordena as consultas passadas em ordem decrescente (mais recente primeiro)
usort($consultas_passadas, function($a, $b) use ($obter_timestamp_consulta) {
    return $obter_timestamp_consulta($b) <=> $obter_timestamp_consulta($a);
});

// Carrega as últimas 5 notificações do paciente
$notificacoes = obter_notificacoes_paciente($pdo, $id_paciente, 5);
// Conta notificações não lidas para exibir no badge
$notificacoes_nao_lidas = contar_notificacoes_nao_lidas_paciente($pdo, $id_paciente);
// Carrega especializações disponíveis para o formulário de agendamento
$especializacoes = obter_especializacoes($pdo);
// Carrega datas disponíveis para o calendário de agendamento
$datas_disponiveis = obter_datas_disponiveis($pdo);
// Alias para uso nas abas de consultas e pagamentos
$minhas_consultas = $consultas;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Dashboard - Nexus Premium</title>
    <!-- Favicon do site -->
    <link rel="icon" href="assets/simbologo.png">
    <!-- Pré-conexão com o Google Fonts para melhor performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Fontes: Comfortaa (títulos) e Lora (corpo de texto) -->
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&family=Lora:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <!-- CSS principal dos dashboards -->
    <link rel="stylesheet" href="css/dashboards.css">
    <!-- CSS do FullCalendar (biblioteca de calendário interativo) -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.css' rel='stylesheet' />
    <!-- JS do FullCalendar -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.js'></script>
    <!-- JS específico do dashboard do paciente (defer = carrega após o HTML) -->
    <script src="js/dashboard_paciente.js" defer></script>
    <style>
        /* ═══════════════════════════════════════════════════════════════
           OVERRIDES NEXUS — Paciente
           Estilos específicos do dashboard do paciente que sobrescrevem
           ou complementam o CSS global (dashboards.css)
        ═══════════════════════════════════════════════════════════════ */

        /* Notificação não lida: borda azul e fundo levemente azulado */
        .notificacao-item.nao-lida {
            border-left: 4px solid var(--azul-sereno) !important;
            background: rgba(128,161,212,.06);
        }
        /* Notificação lida: opacidade reduzida para indicar que já foi vista */
        .notificacao-item.lida { opacity: 0.6; }

        /* Ícone circular dentro de cada item de notificação */
        .notificacao-icone {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: rgba(128,161,212,.08);
            flex-shrink: 0; /* Não encolhe quando o texto é longo */
        }

        /* Card de notificação: layout flexível com ícone à esquerda e texto à direita */
        .notificacao-item {
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: rgba(247,244,234,.4);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--warning); /* Borda amarela padrão */
        }

        /* Área de texto da notificação: ocupa o espaço restante */
        .notificacao-conteudo { flex: 1; min-width: 0; }

        /* Título da notificação */
        .notificacao-conteudo h4 {
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 600;
            color: var(--grafite);
            margin-bottom: var(--spacing-xs);
        }

        /* Texto da mensagem da notificação */
        .notificacao-conteudo p {
            font-family: var(--font-body);
            font-size: 13px;
            color: var(--grafite);
            opacity: 0.55; /* Texto secundário mais suave */
            margin-bottom: var(--spacing-xs);
            line-height: 1.5;
        }

        /* Data/hora da notificação (menor e mais discreta) */
        .notificacao-data {
            font-family: var(--font-body);
            font-size: 11px;
            color: var(--grafite);
            opacity: 0.35;
        }

        /* Container para estado vazio (quando não há itens para exibir) */
        .vazio-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-2xl);
            color: var(--grafite);
            opacity: 0.4; /* Visualmente apagado para indicar ausência de conteúdo */
        }

        .vazio-container p {
            font-family: var(--font-body);
            font-size: 14px;
        }

        /* Card moderno de consulta com efeito glassmorphism (vidro fosco) */
        .consulta-card-moderno {
            background: rgba(255,255,255,.65);       /* Fundo semi-transparente */
            backdrop-filter: blur(16px);              /* Desfoque do fundo (glassmorphism) */
            -webkit-backdrop-filter: blur(16px);      /* Prefixo para Safari */
            border: 1px solid rgba(255,255,255,.5);   /* Borda semi-transparente */
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--azul-sereno); /* Destaque azul à esquerda */
            transition: all 0.3s cubic-bezier(.4,0,.2,1); /* Animação suave */
            position: relative;
            overflow: hidden; /* Esconde o pseudo-elemento que sai do card */
        }
        /* Círculo decorativo no canto superior direito do card */
        .consulta-card-moderno::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, rgba(128,161,212,.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none; /* Não interfere com cliques */
        }
        /* Efeito hover: sombra maior e leve elevação */
        .consulta-card-moderno:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }
        /* Variação para consultas passadas: borda lavanda e opacidade reduzida */
        .consulta-card-moderno.passada {
            border-left-color: var(--lavanda);
            opacity: 0.55;
        }
        .consulta-card-moderno.passada::before {
            background: radial-gradient(circle, rgba(192,185,221,.08) 0%, transparent 70%);
        }
        /* Cabeçalho do card: título à esquerda, badge de status à direita */
        .consulta-card-moderno .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        /* Título da especialização no card */
        .consulta-card-moderno h3 {
            font-family: var(--font-titulo);
            font-size: 16px;
            font-weight: 700;
            color: var(--grafite);
            margin-bottom: 8px;
        }
        /* Metadados do card (data, hora, modalidade) */
        .consulta-card-moderno .card-meta {
            font-family: var(--font-body);
            font-size: 12px;
            color: var(--grafite);
            opacity: 0.6;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .consulta-card-moderno .card-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        /* Área de ações no rodapé do card (botões) */
        .consulta-card-moderno .card-actions {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(0,0,0,.06);
        }
        /* Grupo de badges de status (consulta + pagamento) */
        .card-status-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
        }
        /* Aviso sobre regra de cancelamento */
        .aviso-cancelamento-paciente {
            background: rgba(251,191,36,.08);
            border: 1px solid rgba(251,191,36,.3);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            font-size: 13px;
            color: var(--grafite);
            margin-bottom: 16px;
        }
        /* Espaçamento entre seções do dashboard */
        .section-gap { margin-top: 32px; }
    </style>
</head>
<body>
    <!-- Layout principal: sidebar + conteúdo + widgets -->
    <div class="dashboard-layout">

        <!-- ═══════════════════════════════════════════════════════════
             SIDEBAR (Menu lateral esquerdo)
        ═══════════════════════════════════════════════════════════ -->
        <aside class="sidebar">
            <!-- Logo do sistema -->
            <div class="sidebar-logo">
                <a href="index.html">
                    <img src="assets/logo.png" alt="Nexus Psicologia">
                </a>
            </div>

            <!-- Menu de navegação entre as abas -->
            <!-- Cada link usa ?aba= para trocar a aba ativa -->
            <!-- A classe 'ativo' é aplicada ao item da aba atual -->
            <nav class="sidebar-nav">
                <!-- Aba: Dashboard (visão geral) -->
                <a href="?aba=dashboard" class="nav-item <?php echo $aba_ativa === 'dashboard' ? 'ativo' : ''; ?>">
                    <!-- Ícone SVG inline (sem dependência de biblioteca de ícones) -->
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <!-- Aba: Calendário (novo agendamento) -->
                <a href="?aba=calendario" class="nav-item <?php echo $aba_ativa === 'calendario' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>Agendar</span>
                </a>
                <!-- Aba: Minhas Consultas -->
                <a href="?aba=consultas" class="nav-item <?php echo $aba_ativa === 'consultas' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <span>Consultas</span>
                </a>
                <!-- Aba: Pagamentos -->
                <a href="?aba=pagamentos" class="nav-item <?php echo $aba_ativa === 'pagamentos' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <span>Pagamentos</span>
                </a>
                <!-- Aba: Notificações (com badge de contagem) -->
                <a href="?aba=notificacoes" class="nav-item <?php echo $aba_ativa === 'notificacoes' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <span>Notificações</span>
                    <?php if ($notificacoes_nao_lidas > 0): ?>
                        <!-- Badge vermelho com contagem de notificações não lidas -->
                        <span class="badge"><?php echo $notificacoes_nao_lidas; ?></span>
                    <?php endif; ?>
                </a>
                <!-- Aba: Perfil -->
                <a href="?aba=perfil" class="nav-item <?php echo $aba_ativa === 'perfil' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span>Perfil</span>
                </a>
            </nav>

            <!-- Rodapé da sidebar: avatar e botão de logout -->
            <div class="sidebar-footer">
                <div class="usuario-info">
                    <!-- Avatar com a inicial do nome do paciente -->
                    <!-- strtoupper(substr(..., 0, 1)) = primeira letra maiúscula -->
                    <div class="usuario-avatar"><?php echo strtoupper(substr($paciente['nome'] ?? 'U', 0, 1)); ?></div>
                    <div class="usuario-dados">
                        <!-- htmlspecialchars() previne XSS (Cross-Site Scripting) -->
                        <p class="usuario-nome"><?php echo htmlspecialchars($paciente['nome'] ?? 'Usuário'); ?></p>
                        <p class="usuario-email"><?php echo htmlspecialchars($paciente['email'] ?? ''); ?></p>
                    </div>
                </div>
                <!-- Botão de logout: redireciona para logout.php -->
                <a href="logout.php" class="btn-logout">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    Sair
                </a>
            </div>
        </aside>

        <!-- ═══════════════════════════════════════════════════════════
             CONTEÚDO CENTRAL (área principal das abas)
        ═══════════════════════════════════════════════════════════ -->
        <main class="main-content">
            <!-- Cabeçalho superior: título dinâmico + botões de ação -->
            <div class="header-top" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <!-- Título muda conforme a aba ativa -->
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
                    <!-- Botão de notificações com badge de contagem -->
                    <button class="btn-notificacoes" onclick="window.location.href='?aba=notificacoes'" title="Ver notificações">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if ($notificacoes_nao_lidas > 0): ?>
                            <span class="badge"><?php echo $notificacoes_nao_lidas; ?></span>
                        <?php endif; ?>
                    </button>
                    <!-- Botão de alternância de modo escuro -->
                    <button class="btn-notificacoes" id="btn-dark-mode" title="Alternar modo escuro">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Alerta de sucesso (exibido após ações bem-sucedidas) -->
            <?php if ($sucesso): ?>
                <div class="alerta alerta-sucesso">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>

            <!-- Alerta de erro (exibido quando uma ação falha) -->
            <?php if ($erro): ?>
                <div class="alerta alerta-erro">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════════
                 ABA: DASHBOARD (visão geral)
                 Classe 'ativo' controla qual aba está visível via CSS
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'dashboard' ? 'ativo' : ''; ?>" id="aba-dashboard">
                <!-- Cabeçalho alternativo (oculto, mantido por compatibilidade) -->
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

                <!-- Seção: Próximas Consultas (mostra até 3) -->
                <div class="calendario-secao">
                    <h2 class="section-title">Suas Próximas Consultas</h2>
                    <div class="card-grid">
                        <?php 
                        // array_slice limita a exibição a no máximo 3 consultas
                        foreach (array_slice($proximas_consultas, 0, 3) as $consulta): 
                        ?>
                            <div class="consulta-card-moderno">
                                <div class="card-header">
                                    <div>
                                        <!-- Nome da especialização -->
                                        <h3><?php echo htmlspecialchars($consulta['especializacao']); ?></h3>
                                        <!-- Metadados: data, hora e modalidade com ícones SVG -->
                                        <div class="card-meta">
                                            <span>
                                                <!-- Ícone de calendário -->
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                                <!-- Data formatada como dd/mm/aaaa -->
                                                <?php echo date('d/m/Y', strtotime($consulta['data_calendario'])); ?>
                                            </span>
                                            <span>
                                                <!-- Ícone de relógio -->
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                <!-- Horário: apenas HH:MM (sem segundos) -->
                                                <?php echo substr($consulta['horario'], 0, 5); ?>h
                                            </span>
                                            <span>
                                                <!-- Ícone de telefone (modalidade) -->
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                                <?php echo htmlspecialchars($consulta['modalidade']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Badge de status: Pendente, Confirmada, etc. -->
                                    <!-- A classe CSS é gerada dinamicamente com o status em minúsculas -->
                                    <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>">
                                        <?php echo $consulta['status']; ?>
                                    </span>
                                </div>
                                <!-- Botão de pagamento: aparece apenas para consultas confirmadas com pagamento pendente -->
                                <?php if ($consulta['status'] === 'Confirmada' && $consulta['pagamento_status'] === 'Pendente'): ?>
                                    <div class="card-actions">
                                        <!-- Chama a função JS para abrir o modal de pagamento -->
                                        <button class="btn btn-primary" onclick="abrirModalPagamento(<?php echo $consulta['id_consulta']; ?>, <?php echo $consulta['valor']; ?>)">
                                            Realizar Pagamento
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <!-- Mensagem quando não há consultas agendadas -->
                        <?php if (empty($proximas_consultas)): ?>
                            <p class="dashboard-empty">Você não tem consultas agendadas.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Seção: Consultas Passadas (mostra até 5) -->
                <div class="calendario-secao section-gap">
                    <h2 class="section-title">Consultas que já passaram</h2>
                    <div class="card-grid">
                        <?php foreach (array_slice($consultas_passadas, 0, 5) as $consulta): ?>
                            <!-- Classe 'passada' aplica estilo diferenciado (opacidade reduzida) -->
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
                                    <!-- Exibe dois badges: status da consulta e status do pagamento -->
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

            <!-- ═══════════════════════════════════════════════════════
                 ABA: CALENDÁRIO (novo agendamento)
                 O conteúdo é carregado a partir de um arquivo separado
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'calendario' ? 'ativo' : ''; ?>" id="aba-calendario">
                <?php include 'views/dashboard_paciente_calendario.php'; ?>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 ABA: MINHAS CONSULTAS (tabela completa)
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'consultas' ? 'ativo' : ''; ?>" id="aba-consultas">
                <div class="secao">
                    <h2>Minhas Consultas</h2>
                    <!-- Aviso sobre a regra de cancelamento de 24 horas -->
                    <div class="aviso-cancelamento-paciente">
                        Cancelamentos são permitidos com pelo menos 24 horas de antecedência. Consultas pagas e canceladas dentro do prazo serão reembolsadas.
                    </div>
                    <!-- Tabela com todas as consultas do paciente -->
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
                                        <!-- Formata data como dd/mm/aaaa e hora como HH:MM -->
                                        <td><?php echo date('d/m/Y', strtotime($consulta['data_calendario'])) . ' ' . substr($consulta['horario'], 0, 5) . 'h'; ?></td>
                                        <td><?php echo htmlspecialchars($consulta['modalidade']); ?></td>
                                        <!-- Badge de status com classe CSS dinâmica -->
                                        <td><span class="status-badge status-<?php echo strtolower($consulta['status']); ?>"><?php echo $consulta['status']; ?></span></td>
                                        <td><span class="status-badge status-<?php echo strtolower($consulta['pagamento_status']); ?>"><?php echo $consulta['pagamento_status']; ?></span></td>
                                        <td>
                                            <?php if ($consulta['status'] === 'Cancelada'): ?>
                                                <!-- Consulta já cancelada: apenas exibe o texto -->
                                                <span style="font-size: 12px; color: #ef4444; font-weight: 600;">Cancelada</span>
                                            <?php elseif (consulta_pode_ser_cancelada_pelo_paciente($consulta)): ?>
                                                <!-- Dentro do prazo: exibe botão de cancelamento com confirmação -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja cancelar esta consulta?');">
                                                    <input type="hidden" name="acao" value="cancelar_consulta">
                                                    <input type="hidden" name="id_consulta" value="<?php echo $consulta['id_consulta']; ?>">
                                                    <button type="submit" class="btn btn-pequeno btn-cancelar">Cancelar</button>
                                                </form>
                                            <?php else: ?>
                                                <!-- Fora do prazo: informa que não é mais possível cancelar -->
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

            <!-- ═══════════════════════════════════════════════════════
                 ABA: PAGAMENTOS (histórico financeiro)
            ═══════════════════════════════════════════════════════ -->
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
                                // Filtra apenas consultas não canceladas para o histórico de pagamentos
                                // fn($c) é uma arrow function (sintaxe curta de função anônima)
                                $pagamentos = array_filter($minhas_consultas, fn($c) => $c['status'] !== 'Cancelada');
                                foreach ($pagamentos as $pag): 
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pag['especializacao']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($pag['data_calendario'])) . ' ' . substr($pag['horario'], 0, 5) . 'h'; ?></td>
                                        <!-- Valor em verde com formatação monetária brasileira -->
                                        <td style="font-weight: 600; color: #10b981;">R$ <?php echo number_format($pag['valor'], 2, ',', '.'); ?></td>
                                        <!-- Método de pagamento (Pix, Cartão, etc.) ou '-' se não informado -->
                                        <td><?php echo htmlspecialchars($pag['metodo_pagamento'] ?? '-'); ?></td>
                                        <td>
                                            <?php 
                                            // Define a classe CSS do badge baseado no status do pagamento
                                            $ps = $pag['pagamento_status'];
                                            $cls = ($ps === 'Concluído') ? 'status-confirmada' : (($ps === 'Reembolsado') ? 'status-cancelada' : 'status-pendente');
                                            ?>
                                            <span class="status-badge <?php echo $cls; ?>"><?php echo htmlspecialchars($ps); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($pag['status'] === 'Confirmada' && $pag['pagamento_status'] === 'Pendente'): ?>
                                                <!-- Botão de pagamento: apenas para consultas confirmadas com pagamento pendente -->
                                                <button class="btn btn-pequeno btn-primary" onclick="abrirModalPagamento(<?php echo $pag['id_consulta']; ?>, <?php echo $pag['valor']; ?>)">Pagar</button>
                                            <?php else: ?>
                                                - <!-- Sem ação disponível -->
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

            <!-- ═══════════════════════════════════════════════════════
                 ABA: NOTIFICAÇÕES
                 Conteúdo carregado de arquivo separado
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'notificacoes' ? 'ativo' : ''; ?>" id="aba-notificacoes">
                <?php include 'views/dashboard_paciente_notificacoes.php'; ?>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 ABA: PERFIL (dados pessoais)
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'perfil' ? 'ativo' : ''; ?>" id="aba-perfil">
                <div class="secao">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <h2 style="margin: 0;">Meu Perfil</h2>
                        <!-- Botão que ativa o modo de edição (via JavaScript) -->
                        <button class="btn btn-primary" id="btn-editar-perfil" onclick="ativarEdicaoPerfil()">Editar Perfil</button>
                    </div>
                    <!-- Formulário de perfil com enctype para upload de arquivo (foto) -->
                    <form method="POST" id="form-perfil" class="form-row" enctype="multipart/form-data">
                        <!-- Campo oculto que identifica a ação para o PHP -->
                        <input type="hidden" name="acao" value="editar_perfil">

                        <!-- Área de foto de perfil com preview -->
                        <div style="grid-column: 1 / -1; display: flex; align-items: center; gap: 20px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #f3f4f6;">
                            <div style="position: relative;">
                                <!-- Círculo da foto: clicável para abrir o seletor de arquivo -->
                                <div id="foto-preview-paciente" style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 32px; font-weight: 700; flex-shrink: 0; overflow: hidden; cursor: pointer;" onclick="document.getElementById('foto_perfil_paciente').click()">
                                    <?php if (!empty($paciente['foto_perfil'])): ?>
                                        <!-- Exibe a foto atual se existir -->
                                        <img src="<?php echo htmlspecialchars($paciente['foto_perfil']); ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <!-- Exibe a inicial do nome se não houver foto -->
                                        <span><?php echo strtoupper(substr($paciente['nome'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <!-- Input de arquivo oculto (ativado pelo clique no círculo) -->
                                <input type="file" id="foto_perfil_paciente" name="foto_perfil" class="foto-upload-input" accept="image/*" style="display: none;" onchange="previewFotoPerfilPaciente(this)">
                                <!-- Botão de lápis para alterar foto (visível apenas no modo de edição) -->
                                <label for="foto_perfil_paciente" id="btn-alterar-foto" style="position: absolute; bottom: 0; right: 0; background: #6366f1; color: white; width: 28px; height: 28px; border-radius: 50%; display: none; align-items: center; justify-content: center; cursor: pointer; border: 2px solid white; font-size: 14px;" title="Alterar foto">&#9998;</label>
                            </div>
                            <div>
                                <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0 0 4px 0;"><?php echo htmlspecialchars($paciente['nome']); ?></h3>
                                <p style="color: #6b7280; margin: 0; font-size: 14px;"><?php echo htmlspecialchars($paciente['email']); ?></p>
                                <!-- Dica de upload (visível apenas no modo de edição) -->
                                <p id="foto-upload-hint" style="color: #9ca3af; margin: 4px 0 0 0; font-size: 12px; display: none;">Clique no botao para alterar a foto</p>
                            </div>
                        </div>

                        <!-- Campos do formulário (desabilitados por padrão, habilitados no modo de edição) -->
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
                        <!-- Botões de salvar/cancelar (ocultos por padrão, visíveis no modo de edição) -->
                        <div id="botoes-edicao" style="display: none; gap: 12px; grid-column: 1 / -1;">
                            <button type="submit" class="btn btn-primary">Salvar Alteracoes</button>
                            <button type="button" class="btn btn-secondary" onclick="cancelarEdicaoPerfil()">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <!-- ═══════════════════════════════════════════════════════════
             WIDGETS DIREITA (coluna lateral direita)
        ═══════════════════════════════════════════════════════════ -->
        <aside class="widgets-coluna">
            <!-- Widget: Próximas Consultas (resumo rápido) -->
            <div class="widget">
                <div class="widget-header">
                    <h3>Próximas Consultas</h3>
                </div>
                <div class="proximas-consultas">
                    <!-- Mostra apenas as 2 próximas consultas no widget -->
                    <?php foreach (array_slice($proximas_consultas, 0, 2) as $consulta): ?>
                        <div class="consulta-card">
                            <div class="consulta-card-titulo"><?php echo htmlspecialchars($consulta['especializacao']); ?></div>
                            <div class="consulta-card-info">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <!-- Formato compacto: dd/mm HH:MMh -->
                                <?php echo date('d/m', strtotime($consulta['data_calendario'])) . ' ' . substr($consulta['horario'], 0, 5) . 'h'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Widget: Notificações (resumo rápido) -->
            <div class="widget">
                <div class="widget-header">
                    <h3>Notificações</h3>
                </div>
                <div class="notificacoes-lista">
                    <!-- Mostra apenas as 2 notificações mais recentes no widget -->
                    <?php foreach (array_slice($notificacoes, 0, 2) as $notif): ?>
                        <!-- Classe CSS dinâmica: 'lida' ou 'nao-lida' + tipo da notificação -->
                        <div class="notificacao-item <?php echo $notif['lida'] ? 'lida' : 'nao-lida'; ?> <?php echo $notif['tipo']; ?>">
                            <!-- Título amigável do tipo de notificação -->
                            <div class="notificacao-titulo"><?php echo htmlspecialchars(formatar_tipo_notificacao($notif['tipo'])); ?></div>
                            <!-- Texto truncado em 60 caracteres para o widget compacto -->
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

    <!-- ═══════════════════════════════════════════════════════════
         MODAL DE PAGAMENTO
         Janela flutuante para processar pagamentos de consultas
    ═══════════════════════════════════════════════════════════ -->
    <!-- O modal é oculto por padrão e exibido pela função abrirModalPagamento() -->
    <div id="modalPagamento" class="modal">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2>Realizar Pagamento</h2>
                <!-- Botão X para fechar o modal -->
                <button class="modal-fechar" onclick="fecharModalPagamento()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Formulário de pagamento: envia via POST para o PHP -->
                <form method="POST" id="formPagamento" onsubmit="return confirmarPagamento()">
                    <input type="hidden" name="acao" value="processar_pagamento">
                    <!-- Campo oculto preenchido pelo JavaScript com o ID da consulta -->
                    <input type="hidden" name="id_consulta" id="idConsultaPagamento">

                    <!-- Exibição do valor a pagar em destaque verde -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; margin-bottom: 24px;">
                        <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Valor a Pagar</div>
                        <div style="font-size: 32px; font-weight: 800; color: #059669;">
                            R$ <span id="valorPagamento">0,00</span>
                        </div>
                    </div>

                    <!-- Seleção do método de pagamento -->
                    <div class="form-group">
                        <label style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Método de Pagamento *</label>
                        <select name="metodo_pagamento" required style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;">
                            <option value="">Selecione um método</option>
                            <option value="Pix">Pix</option>
                            <option value="Cartao">Cartão de Crédito</option>
                            <option value="Boleto">Boleto</option>
                        </select>
                    </div>

                    <!-- Aviso de confirmação antes de pagar -->
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
        // ═══════════════════════════════════════════════════════════
        // MODO ESCURO
        // Salva a preferência do usuário no localStorage do navegador
        // ═══════════════════════════════════════════════════════════

        const btnDarkModeGlobal = document.getElementById('btn-dark-mode');
        const body = document.body;

        // Verifica se o modo escuro estava ativo na última visita
        const darkModeEnabled = localStorage.getItem('darkMode') === 'true';
        if (darkModeEnabled) {
            body.classList.add('dark-mode'); // Aplica o modo escuro imediatamente
        }

        // Adiciona o listener de clique para alternar o modo escuro
        if (btnDarkModeGlobal) {
            btnDarkModeGlobal.addEventListener('click', function() {
                body.classList.toggle('dark-mode'); // Alterna a classe dark-mode
                const isEnabled = body.classList.contains('dark-mode');
                localStorage.setItem('darkMode', isEnabled); // Salva a preferência
            });
        }

        // ═══════════════════════════════════════════════════════════
        // MODAL DE PAGAMENTO
        // ═══════════════════════════════════════════════════════════

        /*
         * Abre o modal de pagamento preenchendo os dados da consulta.
         * Chamada pelo botão "Realizar Pagamento" nos cards de consulta.
         *
         * @param {number} idConsulta - ID da consulta a ser paga
         * @param {number} valor - Valor da consulta em reais
         */
        function abrirModalPagamento(idConsulta, valor) {
            // Preenche o campo oculto com o ID da consulta
            document.getElementById('idConsultaPagamento').value = idConsulta;
            // Formata o valor como número com 2 casas decimais e vírgula
            const valorNum = parseFloat(valor);
            document.getElementById('valorPagamento').textContent = isNaN(valorNum) ? '0,00' : valorNum.toFixed(2).replace('.', ',');
            // Limpa a seleção do método de pagamento
            document.getElementById('formPagamento').querySelector('select[name="metodo_pagamento"]').value = '';
            // Exibe o modal adicionando a classe 'show'
            document.getElementById('modalPagamento').classList.add('show');
        }

        /*
         * Fecha o modal de pagamento.
         * Chamada pelo botão X ou pelo botão "Cancelar".
         */
        function fecharModalPagamento() {
            document.getElementById('modalPagamento').classList.remove('show');
        }

        /*
         * Valida o formulário antes de enviar o pagamento.
         * Retorna false para cancelar o envio se inválido.
         *
         * @returns {boolean} - true para enviar, false para cancelar
         */
        function confirmarPagamento() {
            const metodo = document.getElementById('formPagamento').querySelector('select[name="metodo_pagamento"]').value;
            if (!metodo) {
                alert('Por favor, selecione um método de pagamento.');
                return false; // Cancela o envio do formulário
            }
            // Desabilita o botão e muda o texto para evitar duplo clique
            const btn = document.getElementById('btnConfirmarPagamento');
            btn.disabled = true;
            btn.textContent = 'Processando...';
            return true; // Permite o envio do formulário
        }

        // Fecha o modal ao clicar fora da área de conteúdo (no overlay escuro)
        document.getElementById('modalPagamento').addEventListener('click', function(e) {
            // e.target === this: o clique foi no overlay, não no conteúdo do modal
            if (e.target === this) {
                fecharModalPagamento();
            }
        });

        // ═══════════════════════════════════════════════════════════
        // EDIÇÃO DE PERFIL
        // Habilita/desabilita os campos do formulário de perfil
        // ═══════════════════════════════════════════════════════════

        /*
         * Ativa o modo de edição do perfil:
         * - Habilita todos os campos de input
         * - Exibe os botões de salvar e cancelar
         * - Exibe o botão de alterar foto
         */
        function ativarEdicaoPerfil() {
            const campos = ['nome-perfil', 'email-perfil', 'telefone-perfil', 'data-nascimento-perfil', 'cpf-perfil', 'endereco-perfil'];
            // Habilita cada campo para edição
            campos.forEach(id => {
                const campo = document.getElementById(id);
                if (campo) campo.disabled = false;
            });
            document.getElementById('btn-editar-perfil').style.display = 'none'; // Oculta botão "Editar"
            document.getElementById('botoes-edicao').style.display = 'flex';     // Exibe botões salvar/cancelar
            document.getElementById('btn-alterar-foto').style.display = 'flex';  // Exibe botão de foto
            document.getElementById('foto-upload-hint').style.display = 'block'; // Exibe dica de upload
        }

        /*
         * Cancela o modo de edição do perfil:
         * - Desabilita todos os campos de input
         * - Oculta os botões de salvar e cancelar
         * - Oculta o botão de alterar foto
         */
        function cancelarEdicaoPerfil() {
            const campos = ['nome-perfil', 'email-perfil', 'telefone-perfil', 'data-nascimento-perfil', 'cpf-perfil', 'endereco-perfil'];
            // Desabilita cada campo (volta ao modo de visualização)
            campos.forEach(id => {
                const campo = document.getElementById(id);
                if (campo) campo.disabled = true;
            });
            document.getElementById('btn-editar-perfil').style.display = 'block'; // Exibe botão "Editar"
            document.getElementById('botoes-edicao').style.display = 'none';      // Oculta botões salvar/cancelar
            document.getElementById('btn-alterar-foto').style.display = 'none';   // Oculta botão de foto
            document.getElementById('foto-upload-hint').style.display = 'none';   // Oculta dica de upload
        }

        /*
         * Exibe um preview da foto selecionada antes do upload.
         * Usa FileReader para ler o arquivo localmente sem enviar ao servidor.
         *
         * @param {HTMLInputElement} input - O input de arquivo que acionou o evento
         */
        function previewFotoPerfilPaciente(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader(); // API nativa do navegador para ler arquivos
                reader.onload = function(e) {
                    // Substitui o conteúdo do círculo pela imagem selecionada
                    var preview = document.getElementById('foto-preview-paciente');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">';
                };
                reader.readAsDataURL(input.files[0]); // Lê o arquivo como URL base64
            }
        }
    </script>
</body>
</html>
