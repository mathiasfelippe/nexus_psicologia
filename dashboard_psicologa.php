<?php
/*
 * ARQUIVO: dashboard_psicologa.php
 * DESCRIÇÃO: Página principal do dashboard da psicóloga.
 *
 * Este arquivo é o controlador central da área administrativa da psicóloga.
 * Combina lógica PHP (back-end) com HTML/CSS/JS (front-end) em um único arquivo.
 *
 * RESPONSABILIDADES:
 *   1. Verificar se a psicóloga está autenticada
 *   2. Processar formulários POST (confirmar, cancelar, atualizar preços, perfil, senha, bloqueios)
 *   3. Carregar dados do banco para exibir no dashboard
 *   4. Renderizar a interface completa com sidebar, abas e widgets
 *
 * ABAS DISPONÍVEIS (parâmetro GET ?aba=):
 *   - dashboard      → Calendário de atendimentos e horários de hoje
 *   - agenda         → Tabela de consultas futuras e passadas
 *   - pacientes      → Lista de todos os pacientes cadastrados
 *   - financeiro     → Receitas e gráfico financeiro
 *   - especialidades → Gerenciamento de especialidades e preços
 *   - notificacoes   → Notificações do sistema
 *   - configuracoes  → Bloqueios de disponibilidade
 *   - perfil         → Dados pessoais da psicóloga
 */

// ─── INICIALIZAÇÃO ────────────────────────────────────────────────────────────

// Inclui a conexão com o banco de dados e inicia a sessão
require_once 'config/conexao.php';
// Inclui todas as funções de negócio (confirmar, cancelar, notificar, etc.)
require_once 'config/funcoes.php';

// Garante que o banco de dados está atualizado (verifica colunas faltantes)
verificar_esquema_banco($pdo);

// ─── AUTENTICAÇÃO ─────────────────────────────────────────────────────────────

// Verifica se a psicóloga está logada (tem sessão ativa com id_psicologa)
// Se não estiver, redireciona para a página de login
if (!isset($_SESSION['id_psicologa'])) {
    header('Location: login.php');
    exit;
}

// Obtém o ID da psicóloga da sessão e carrega seus dados do banco
$id_psicologa = $_SESSION['id_psicologa'];
$psicologa = obter_psicologa($pdo, $id_psicologa);

// ─── CONTROLE DE ABAS ─────────────────────────────────────────────────────────

// Obtém a aba ativa a partir do parâmetro GET (?aba=dashboard)
// Se não informado, usa 'dashboard' como padrão
$aba_ativa = isset($_GET['aba']) ? $_GET['aba'] : 'dashboard';

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
    
    // ── AÇÃO: Confirmar Consulta ─────────────────────────────────────────────
    if ($acao === 'confirmar_consulta') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        // Comentário opcional para enviar ao paciente junto com a confirmação
        $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
        $consulta = obter_consulta($pdo, $id_consulta);
        // Confirma a consulta e notifica o paciente
        if (confirmar_consulta($pdo, $id_consulta, $consulta['id_paciente'])) {
            // Se houver comentário, envia como notificação adicional ao paciente
            if (!empty($comentario)) {
                criar_notificacao($pdo, $consulta['id_paciente'], $id_psicologa, 'comentario_psicologa', "Recado da psicóloga: $comentario", 'paciente');
            }
            $sucesso = 'Consulta confirmada com sucesso!';
        }

    // ── AÇÃO: Cancelar Consulta ──────────────────────────────────────────────
    } elseif ($acao === 'cancelar_consulta') {
        $id_consulta = isset($_POST['id_consulta']) ? intval($_POST['id_consulta']) : 0;
        // O motivo do cancelamento é obrigatório para a psicóloga
        $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
        if (empty($comentario)) {
            $erro = 'O motivo do cancelamento é obrigatório.';
        } else {
            $consulta = obter_consulta($pdo, $id_consulta);
            if (!$consulta) {
                $erro = 'Consulta não encontrada.';
            } elseif ($consulta['status'] === 'Cancelada') {
                $erro = 'Esta consulta já foi cancelada.';
            } elseif (cancelar_consulta($pdo, $id_consulta, $consulta['id_paciente'], $id_psicologa, false, $comentario, 'psicologa')) {
                // Notifica o paciente com o motivo do cancelamento
                criar_notificacao($pdo, $consulta['id_paciente'], $id_psicologa, 'cancelamento', "Sua consulta foi cancelada pela psicóloga. Motivo: $comentario", 'paciente');
                $sucesso = 'Consulta cancelada com sucesso! O paciente foi notificado com o motivo informado.';
            } else {
                $erro = 'Erro ao cancelar consulta.';
            }
        }

    // ── AÇÃO: Atualizar Preço de Especialização (individual) ────────────────
    } elseif ($acao === 'atualizar_preco') {
        $id_especializacao = isset($_POST['id_especializacao']) ? intval($_POST['id_especializacao']) : 0;
        $novo_preco = isset($_POST['novo_preco']) ? floatval($_POST['novo_preco']) : 0;
        if (atualizar_preco_especializacao($pdo, $id_especializacao, $novo_preco)) {
            $sucesso = 'Preço atualizado com sucesso!';
        }

    // ── AÇÃO: Marcar Notificação como Lida ──────────────────────────────────
    } elseif ($acao === 'marcar_lida') {
        $id_notificacao = isset($_POST['id_notificacao']) ? intval($_POST['id_notificacao']) : 0;
        marcar_notificacao_lida($pdo, $id_notificacao);
        $sucesso = 'Notificação marcada como lida!';

    // ── AÇÃO: Criar Bloqueio de Agenda ───────────────────────────────────────
    } elseif ($acao === 'criar_bloqueio') {
        try {
            // Tipos de bloqueio: 'dia_inteiro', 'horario_especifico', 'ferias'
            $tipo_bloqueio = isset($_POST['tipo_bloqueio']) ? trim($_POST['tipo_bloqueio']) : '';
            $data_inicio = isset($_POST['data_inicio']) ? trim($_POST['data_inicio']) : '';
            // data_fim é opcional (usado para bloqueios de período como férias)
            $data_fim = isset($_POST['data_fim']) ? trim($_POST['data_fim']) : null;
            // id_horario é opcional (usado apenas para bloqueios de horário específico)
            $id_horario = isset($_POST['horario_inicio']) ? intval($_POST['horario_inicio']) : null;
            $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : null;
            if (!empty($tipo_bloqueio) && !empty($data_inicio)) {
                // O operador ?: retorna null se a variável for vazia (falsy)
                if (criar_bloqueio_agenda($pdo, $tipo_bloqueio, $data_inicio, $data_fim ?: null, $id_horario ?: null, null, $motivo ?: null)) {
                    $sucesso = 'Bloqueio criado com sucesso!';
                } else {
                    $erro = 'Erro ao criar bloqueio.';
                }
            } else {
                $erro = 'Preencha todos os campos obrigatorios.';
            }
        } catch (Exception $e) {
            // Registra o erro no log do servidor para depuração
            error_log('Erro em criar_bloqueio: ' . $e->getMessage());
            $erro = 'Erro ao criar bloqueio: ' . $e->getMessage();
        }

    // ── AÇÃO: Remover Bloqueio de Agenda ────────────────────────────────────
    } elseif ($acao === 'remover_bloqueio') {
        $id_bloqueio = isset($_POST['id_bloqueio']) ? intval($_POST['id_bloqueio']) : 0;
        if (remover_bloqueio_agenda($pdo, $id_bloqueio)) {
            $sucesso = 'Bloqueio removido com sucesso!';
        } else {
            $erro = 'Erro ao remover bloqueio.';
        }

    // ── AÇÃO: Atualizar Perfil da Psicóloga ─────────────────────────────────
    } elseif ($acao === 'atualizar_perfil') {
        $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
        $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : '';
        $crp = isset($_POST['crp']) ? trim($_POST['crp']) : '';
        $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
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
        
        if (empty($erro)) {
            if (!empty($nome)) {
                if ($foto_perfil) {
                    // Atualiza perfil incluindo a nova foto
                    // Usa prepared statement para evitar SQL Injection
                    $stmt = $pdo->prepare("UPDATE psicologa SET nome = ?, telefone = ?, crp = ?, bio = ?, foto_perfil = ? WHERE id_psicologa = ?");
                    // Executa a consulta no banco de dados
                    if ($stmt->execute([$nome, $telefone, $crp, $bio, $foto_perfil, $id_psicologa])) {
                        $sucesso = 'Perfil atualizado com sucesso!';
                    } else {
                        $erro = 'Erro ao atualizar perfil.';
                    }
                } else {
                    // Atualiza perfil sem alterar a foto existente
                    // Usa prepared statement para evitar SQL Injection
                    $stmt = $pdo->prepare("UPDATE psicologa SET nome = ?, telefone = ?, crp = ?, bio = ? WHERE id_psicologa = ?");
                    // Executa a consulta no banco de dados
                    if ($stmt->execute([$nome, $telefone, $crp, $bio, $id_psicologa])) {
                        $sucesso = 'Perfil atualizado com sucesso!';
                    } else {
                        $erro = 'Erro ao atualizar perfil.';
                    }
                }
            } else {
                $erro = 'O nome eh obrigatorio.';
            }
        }

    // ── AÇÃO: Atualizar Preços em Lote ──────────────────────────────────────
    } elseif ($acao === 'atualizar_precos') {
        $atualizados = 0;
        // Percorre todos os campos POST procurando por campos com prefixo 'preco_'
        // Exemplo: preco_1 = 150.00, preco_2 = 200.00
        foreach ($_POST as $chave => $valor) {
            if (strpos($chave, 'preco_') === 0) {
                // Extrai o ID da especialização do nome do campo (ex: 'preco_3' → 3)
                $id_esp = intval(str_replace('preco_', '', $chave));
                $preco = floatval($valor);
                if ($id_esp > 0 && $preco >= 0) {
                    atualizar_preco_especializacao($pdo, $id_esp, $preco);
                    $atualizados++;
                }
            }
        }
        $sucesso = "$atualizados especialidade(s) atualizada(s) com sucesso!";

    // ── AÇÃO: Alterar Senha da Psicóloga ────────────────────────────────────
    } elseif ($acao === 'alterar_senha') {
        $senha_atual = isset($_POST['senha_atual']) ? trim($_POST['senha_atual']) : '';
        $nova_senha = isset($_POST['nova_senha']) ? trim($_POST['nova_senha']) : '';
        $confirmar = isset($_POST['confirmar_nova_senha']) ? trim($_POST['confirmar_nova_senha']) : '';
        // Busca o hash da senha atual no banco para verificação
        // Usa prepared statement para evitar SQL Injection
        $stmt = $pdo->prepare("SELECT senha FROM psicologa WHERE id_psicologa = ?");
        // Executa a consulta no banco de dados
        $stmt->execute([$id_psicologa]);
        $row = $stmt->fetch();
        // password_verify() compara a senha digitada com o hash armazenado (bcrypt)
        if (!$row || !password_verify($senha_atual, $row['senha'])) {
            $erro = 'Senha atual incorreta.';
        } elseif ($nova_senha !== $confirmar) {
            $erro = 'As novas senhas não coincidem.';
        } elseif (strlen($nova_senha) < 6) {
            $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
        } else {
            // password_hash() gera um hash seguro (bcrypt) da nova senha
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt2 = $pdo->prepare("UPDATE psicologa SET senha = ? WHERE id_psicologa = ?");
            if ($stmt2->execute([$hash, $id_psicologa])) {
                $sucesso = 'Senha alterada com sucesso!';
            } else {
                $erro = 'Erro ao alterar senha.';
            }
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
    header('Location: dashboard_psicologa.php?aba=' . urlencode($aba_ativa));
    exit;
}

// ─── CARREGAMENTO DE DADOS PARA O DASHBOARD ───────────────────────────────────

// Consultas do dia atual (para a grade de horários)
$consultas_hoje = obter_consultas_por_data($pdo, date('Y-m-d'));
// Próximas 5 consultas para o widget lateral
$proximas_consultas = obter_proximas_consultas($pdo, 5);
// Todas as consultas da psicóloga (para as abas de agenda)
$todas_consultas_psicologa = obter_todas_consultas($pdo);
$agora = time(); // Timestamp atual para comparações de data/hora

// Função anônima para converter data+hora da consulta em timestamp Unix
// Usada para filtrar e ordenar consultas por data/hora
$obter_timestamp_consulta = function($consulta) {
    return strtotime(trim($consulta['data_calendario'] . ' ' . $consulta['horario']));
};

// Filtra as consultas futuras (não canceladas e com data/hora >= agora)
$consultas_futuras_psicologa = array_filter($todas_consultas_psicologa, function($consulta) use ($agora, $obter_timestamp_consulta) {
    $inicio = $obter_timestamp_consulta($consulta);
    return $consulta['status'] !== 'Cancelada' && $inicio && $inicio >= $agora;
});

// Filtra as consultas passadas (não canceladas e com data/hora < agora)
$consultas_passadas_psicologa = array_filter($todas_consultas_psicologa, function($consulta) use ($agora, $obter_timestamp_consulta) {
    $inicio = $obter_timestamp_consulta($consulta);
    return $consulta['status'] !== 'Cancelada' && $inicio && $inicio < $agora;
});

// Ordena as consultas futuras em ordem crescente (mais próxima primeiro)
usort($consultas_futuras_psicologa, function($a, $b) use ($obter_timestamp_consulta) {
    return $obter_timestamp_consulta($a) <=> $obter_timestamp_consulta($b);
});

// Ordena as consultas passadas em ordem decrescente (mais recente primeiro)
usort($consultas_passadas_psicologa, function($a, $b) use ($obter_timestamp_consulta) {
    return $obter_timestamp_consulta($b) <=> $obter_timestamp_consulta($a);
});

// Métricas gerais para o widget lateral
$total_consultas = obter_total_consultas($pdo);
$total_pacientes = obter_total_pacientes($pdo);
$receita_mes = obter_receita_mes($pdo);
$receita_ano = obter_receita_ano($pdo);

// Notificações para o widget lateral e aba de notificações
$notificacoes = obter_notificacoes_psicologa($pdo, $id_psicologa, 5);
// Conta notificações não lidas para exibir no badge
$notificacoes_nao_lidas = contar_notificacoes_nao_lidas($pdo, $id_psicologa);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Nexus Premium</title>
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
    <!-- Chart.js: biblioteca para gráficos (usada na aba financeiro) -->
    <script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
    <!-- JS específico do dashboard da psicóloga (defer = carrega após o HTML) -->
    <script src="js/dashboard_psicologa.js" defer></script>
    <style>
        /* ═══════════════════════════════════════════════════════════════
           OVERRIDES NEXUS — Psicóloga
           Estilos específicos do dashboard da psicóloga que sobrescrevem
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

        /* Badge de ação no cabeçalho do modal (ex: "CONFIRMAÇÃO", "CANCELAMENTO") */
        .modal-badge-acao {
            display: inline-block;
            font-family: var(--font-body);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 3px 10px;
            border-radius: 4px;
            margin-bottom: 6px;
        }

        /* Container centralizado para o ícone de confirmação/cancelamento no modal */
        .modal-icone-msg {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }

        /* Texto de mensagem centralizado no modal */
        .modal-msg-texto {
            text-align: center;
            font-family: var(--font-body);
            font-size: 14px;
            color: var(--grafite);
            opacity: 0.7;
            line-height: 1.6;
            margin-bottom: 4px;
        }

        /* Mensagem de erro inline abaixo de campos obrigatórios */
        .form-erro {
            font-family: var(--font-body);
            font-size: 12px;
            margin-top: 4px;
            color: var(--danger);
        }

        /* Conteúdo do modal com efeito glassmorphism (vidro fosco) */
        .modal-content {
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(24px) saturate(1.3);
            -webkit-backdrop-filter: blur(24px) saturate(1.3);
            border: 1px solid rgba(255,255,255,.6);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 440px;
            box-shadow: var(--shadow-lg), inset 0 1px 0 rgba(255,255,255,.8);
            padding: 0;
            overflow: hidden;
        }

        /* Cabeçalho do modal com separador inferior */
        .modal-header {
            padding: var(--spacing-xl);
            border-bottom: 1px solid rgba(222,217,226,.3);
        }

        /* Título do modal */
        .modal-header h3 {
            font-family: var(--font-titulo);
            font-size: 18px;
            font-weight: 700;
            color: var(--grafite);
            margin: 0;
        }

        /* Subtítulo/descrição do modal */
        .modal-header p,
        #modalMensagem {
            font-family: var(--font-body);
            font-size: 13px;
            color: var(--grafite);
            opacity: 0.6;
            margin-top: var(--spacing-sm);
        }

        /* Container do campo de comentário dentro do modal */
        #grupoComentario {
            padding: 0 var(--spacing-xl);
        }

        /* Label do campo de comentário */
        #grupoComentario label,
        #labelComentario {
            display: block;
            font-family: var(--font-body);
            font-size: 11px;
            font-weight: 700;
            color: var(--grafite);
            opacity: 0.55;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: var(--spacing-sm);
            margin-top: var(--spacing-md);
        }

        /* Estilo do textarea de comentário */
        textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 1.5px solid var(--perola);
            border-radius: var(--radius-md);
            font-family: var(--font-body);
            font-size: 13px;
            color: var(--grafite);
            resize: vertical; /* Permite redimensionar apenas verticalmente */
            transition: all 0.25s cubic-bezier(.4,0,.2,1);
            background: rgba(255,255,255,.6);
        }

        /* Textarea em foco: borda azul e sombra suave */
        textarea:focus {
            outline: none;
            border-color: var(--azul-sereno);
            box-shadow: 0 0 0 3px rgba(128,161,212,.1);
            background: var(--branco);
        }

        /* Rodapé do modal com botões de ação */
        .modal-footer {
            display: flex;
            gap: var(--spacing-md);
            padding: var(--spacing-xl);
            border-top: 1px solid rgba(222,217,226,.3);
            margin-top: var(--spacing-md);
        }

        /* Botões de ação e fechar do modal */
        .btn-modal-acao,
        .btn-modal-fechar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 22px;
            border-radius: var(--radius-md);
            font-family: var(--font-body);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(.4,0,.2,1);
            white-space: nowrap;
            line-height: 1;
            min-height: 40px;
        }

        /* Botão de ação: sem borda (usa cor de fundo) */
        .btn-modal-acao { border: none; }

        /* Ícones dentro dos botões do modal */
        .btn-modal-acao svg,
        .btn-modal-fechar svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        /* Botão de fechar: borda sutil e fundo semi-transparente */
        .btn-modal-fechar {
            border: 1.5px solid var(--perola);
            background: rgba(255,255,255,.6);
            color: var(--grafite);
            opacity: 0.7;
        }

        /* Hover do botão de fechar: mais opaco e borda lavanda */
        .btn-modal-fechar:hover {
            background: rgba(255,255,255,.9);
            opacity: 1;
            border-color: var(--lavanda);
        }

        /* Container de ações no rodapé do modal */
        .modal-acoes {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 1px solid rgba(222,217,226,.3);
        }

        /* Formulários dentro de modal-acoes: display contents para não quebrar o flex */
        .modal-acoes form { display: contents; }

        /* Hover do botão secundário */
        .btn-secundario:hover {
            background: rgba(222,217,226,.3);
        }

        /* ── DARK MODE: Overrides para o tema escuro ── */
        body.dark-mode .notificacao-item {
            background: rgba(255,255,255,.05);
        }
        body.dark-mode .notificacao-conteudo h4,
        body.dark-mode .notificacao-conteudo p,
        body.dark-mode .notificacao-data {
            color: var(--branco);
        }
        body.dark-mode .notificacao-conteudo p {
            opacity: 0.55;
        }
        body.dark-mode .notificacao-data {
            opacity: 0.35;
        }
        body.dark-mode .vazio-container {
            color: var(--branco);
        }
        body.dark-mode .modal-content {
            background: rgba(30,30,50,.9);
            border-color: rgba(255,255,255,.1);
        }
        body.dark-mode .modal-header h3,
        body.dark-mode .modal-header p,
        body.dark-mode #modalMensagem {
            color: var(--branco);
        }
        body.dark-mode #grupoComentario label,
        body.dark-mode #labelComentario {
            color: rgba(255,255,255,.5);
        }
        body.dark-mode textarea {
            background: rgba(255,255,255,.08);
            color: var(--branco);
            border-color: rgba(255,255,255,.1);
        }
        body.dark-mode textarea:focus {
            background: rgba(255,255,255,.12);
            border-color: var(--azul-sereno);
        }
        body.dark-mode .btn-modal-fechar {
            background: rgba(255,255,255,.08);
            color: var(--branco);
            border-color: rgba(255,255,255,.12);
        }
        body.dark-mode .btn-modal-fechar:hover {
            background: rgba(255,255,255,.14);
        }
        body.dark-mode .modal-msg-texto {
            color: var(--branco);
        }
        body.dark-mode .modal-header {
            border-bottom-color: rgba(255,255,255,.08);
        }
        body.dark-mode .modal-footer {
            border-top-color: rgba(255,255,255,.08);
        }
        body.dark-mode .modal-acoes {
            border-top-color: rgba(255,255,255,.08);
        }
    </style>
</head>
<body>
    <!-- Script inline para aplicar o modo escuro ANTES do carregamento da página
         Evita o "flash" de tema claro ao carregar com modo escuro ativo -->
    <script>if(localStorage.getItem('darkMode')==='true'||localStorage.getItem('darkMode')==='enabled')document.body.classList.add('dark-mode')</script>

    <!-- Layout principal: sidebar + conteúdo + widgets -->
    <div class="dashboard-container">

        <!-- ═══════════════════════════════════════════════════════════
             SIDEBAR (Menu lateral esquerdo)
        ═══════════════════════════════════════════════════════════ -->
        <aside class="sidebar">
            <!-- Logo do sistema com link para a página inicial -->
            <div class="sidebar-header">
                <a href="index.html" class="logo-link">
                    <img src="assets/logo.png" alt="Nexus Logo" class="sidebar-logo">
                </a>
            </div>

            <!-- Menu de navegação entre as abas -->
            <!-- Cada link usa ?aba= para trocar a aba ativa -->
            <!-- A classe 'ativo' é aplicada ao item da aba atual -->
            <nav class="sidebar-nav">
                <!-- Aba: Dashboard (calendário de atendimentos) -->
                <a href="?aba=dashboard" class="nav-item <?php echo $aba_ativa === 'dashboard' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 16l4-4m0 0l4 4m-4-4v4"></path>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <!-- Aba: Agenda (consultas futuras e passadas) -->
                <a href="?aba=agenda" class="nav-item <?php echo $aba_ativa === 'agenda' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>Agenda</span>
                </a>
                <!-- Aba: Pacientes (lista de todos os pacientes) -->
                <a href="?aba=pacientes" class="nav-item <?php echo $aba_ativa === 'pacientes' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m4 5H9m6 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Pacientes</span>
                </a>
                <!-- Aba: Financeiro (receitas e gráfico) -->
                <a href="?aba=financeiro" class="nav-item <?php echo $aba_ativa === 'financeiro' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Financeiro</span>
                </a>
                <!-- Aba: Especialidades (gerenciamento de preços) -->
                <a href="?aba=especialidades" class="nav-item <?php echo $aba_ativa === 'especialidades' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Especialidades</span>
                </a>
                <!-- Aba: Notificações (com badge de contagem) -->
                <a href="?aba=notificacoes" class="nav-item <?php echo $aba_ativa === 'notificacoes' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <!-- Badge de contagem de notificações não lidas (inline no texto) -->
                    <span>Notificações <?php if ($notificacoes_nao_lidas > 0) echo '<span class="badge">' . $notificacoes_nao_lidas . '</span>'; ?></span>
                </a>
                <!-- Aba: Disponibilidade (bloqueios de agenda) -->
                <a href="?aba=configuracoes" class="nav-item <?php echo $aba_ativa === 'configuracoes' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>Disponibilidade</span>
                </a>
                <!-- Aba: Meu Perfil -->
                <a href="?aba=perfil" class="nav-item <?php echo $aba_ativa === 'perfil' ? 'ativo' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span>Meu Perfil</span>
                </a>
            </nav>

            <!-- Rodapé da sidebar: avatar e botão de logout -->
            <div class="sidebar-footer">
                <div class="usuario-info">
                    <!-- Avatar com a inicial do nome da psicóloga -->
                    <div class="usuario-avatar"><?php echo strtoupper(substr($psicologa['nome'] ?? 'P', 0, 1)); ?></div>
                    <div class="usuario-dados">
                        <p class="usuario-nome"><?php echo htmlspecialchars($psicologa['nome'] ?? 'Psicóloga'); ?></p>
                        <p class="usuario-email"><?php echo htmlspecialchars($psicologa['email'] ?? ''); ?></p>
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
                        case 'agenda': $titulo = "Agenda"; break;
                        case 'pacientes': $titulo = "Meus Pacientes"; break;
                        case 'financeiro': $titulo = "Financeiro"; break;
                        case 'especialidades': $titulo = "Especialidades"; break;
                        case 'notificacoes': $titulo = "Notificações"; break;
                        case 'configuracoes': $titulo = "Disponibilidade"; break;
                        case 'perfil': $titulo = "Meu Perfil"; break;
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
                 ABA: DASHBOARD (calendário de atendimentos)
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'dashboard' ? 'ativo' : ''; ?>" id="aba-dashboard">
                <!-- Seção do calendário interativo -->
                <div class="calendario-secao">
                    <div class="calendario-header calendario-header-com-legenda">
                        <div>
                            <h2>Calendário de Atendimentos</h2>
                            <p class="calendario-descricao">Visualize todas as consultas confirmadas, pendentes e passadas.</p>
                        </div>
                        <!-- Legenda de cores do calendário -->
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
                    <!-- Container do FullCalendar: o JS do dashboard_psicologa.js inicializa o calendário aqui -->
                    <div id="calendar"></div>

                    <!-- Grade de horários disponíveis hoje -->
                    <div class="horarios-grid">
                        <h3>Horários Disponíveis Hoje</h3>
                        <div class="horarios-container">
                            <?php 
                            // Obtém todos os horários padrão cadastrados no sistema
                            $horarios_padrao = obter_horarios($pdo);
                            // Busca o ID da data de hoje na tabela de datas disponíveis
                            $stmt_hoje = $pdo->prepare("SELECT id_data FROM datas_disponiveis WHERE data_calendario = ?");
                            $stmt_hoje->execute([date('Y-m-d')]);
                            $id_data_hoje = $stmt_hoje->fetchColumn();
                            // Se hoje estiver cadastrado, obtém os horários disponíveis; senão, array vazio
                            $horarios_hoje = $id_data_hoje ? obter_horarios_disponiveis($pdo, $id_data_hoje) : [];
                            // Extrai apenas os IDs dos horários disponíveis para comparação rápida
                            $ids_disponiveis = array_column($horarios_hoje, 'id_horario');
                            
                            // Renderiza cada horário com estilo diferente se disponível ou não
                            foreach ($horarios_padrao as $h): 
                                $disponivel = in_array($h['id_horario'], $ids_disponiveis);
                            ?>
                                <!-- Classe 'indisponivel' aplica estilo visual de horário bloqueado -->
                                <div class="horario-item <?php echo $disponivel ? '' : 'indisponivel'; ?>">
                                    <?php echo substr($h['horario'], 0, 5); ?>h
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 ABA: AGENDA (consultas futuras e passadas)
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'agenda' ? 'ativo' : ''; ?>" id="aba-agenda">
                <div class="secao">
                    <h2>Minhas Consultas</h2>

                    <!-- Subseção: Próximas consultas (futuras) -->
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
                                        <td><?php echo date('d/m/Y', strtotime($consulta['data_calendario'])) . ' ' . substr($consulta['horario'], 0, 5); ?></td>
                                        <td><?php echo htmlspecialchars($consulta['especializacao']); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($consulta['status']); ?>"><?php echo $consulta['status']; ?></span></td>
                                        <td>
                                            <?php if ($consulta['status'] === 'Pendente'): ?>
                                                <!-- Consulta pendente: pode confirmar ou cancelar -->
                                                <!-- abrirModalAcao() abre o modal com o tipo de ação e ID da consulta -->
                                                <button class="btn btn-pequeno btn-confirmar" onclick="abrirModalAcao('confirmar', <?php echo $consulta['id_consulta']; ?>)">Confirmar</button>
                                                <button class="btn btn-pequeno btn-cancelar-modal" onclick="abrirModalAcao('cancelar', <?php echo $consulta['id_consulta']; ?>)">Cancelar</button>
                                            <?php elseif ($consulta['status'] === 'Confirmada'): ?>
                                                <!-- Consulta confirmada: só pode cancelar -->
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

                    <!-- Subseção: Consultas passadas (históricas) -->
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
                                    <!-- Classe 'consulta-passada-row' aplica estilo diferenciado -->
                                    <tr class="consulta-passada-row">
                                        <td><?php echo htmlspecialchars($consulta['paciente_nome']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($consulta['data_calendario'])) . ' ' . substr($consulta['horario'], 0, 5); ?></td>
                                        <td><?php echo htmlspecialchars($consulta['especializacao']); ?></td>
                                        <td>
                                            <!-- Dois badges: um fixo "Passada" e um dinâmico com o status real -->
                                            <span class="status-badge status-passada">Passada</span>
                                            <span class="status-badge status-<?php echo strtolower($consulta['status']); ?>"><?php echo $consulta['status']; ?></span>
                                        </td>
                                        <!-- Badge de status do pagamento -->
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

            <!-- ═══════════════════════════════════════════════════════
                 ABA: PACIENTES (lista de todos os pacientes)
            ═══════════════════════════════════════════════════════ -->
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
                                // Consulta direta ao banco: lista todos os pacientes em ordem alfabética
                                $stmt = $pdo->query("SELECT * FROM pacientes ORDER BY nome ASC");
                                $todos_pacientes = $stmt->fetchAll();
                                foreach ($todos_pacientes as $p): 
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($p['email']); ?></td>
                                        <td><?php echo htmlspecialchars($p['telefone']); ?></td>
                                        <!-- Formata a data de cadastro como dd/mm/aaaa -->
                                        <td><?php echo date('d/m/Y', strtotime($p['data_criacao'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 ABA: FINANCEIRO (receitas e gráfico)
                 Conteúdo carregado de arquivo separado
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'financeiro' ? 'ativo' : ''; ?>" id="aba-financeiro">
                <?php include 'views/dashboard_psicologa_financeiro.php'; ?>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 ABA: ESPECIALIDADES (gerenciamento de preços)
                 Conteúdo carregado de arquivo separado
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'especialidades' ? 'ativo' : ''; ?>" id="aba-especialidades">
                <?php include 'views/dashboard_psicologa_especialidades.php'; ?>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 ABA: DISPONIBILIDADE (bloqueios de agenda)
                 Conteúdo carregado de arquivo separado
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'configuracoes' ? 'ativo' : ''; ?>" id="aba-configuracoes">
                <?php include 'views/dashboard_psicologa_configuracoes.php'; ?>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 ABA: PERFIL (dados pessoais da psicóloga)
                 Conteúdo carregado de arquivo separado
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'perfil' ? 'ativo' : ''; ?>" id="aba-perfil">
                <?php include 'views/dashboard_psicologa_perfil.php'; ?>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 ABA: NOTIFICAÇÕES
                 Conteúdo carregado de arquivo separado
            ═══════════════════════════════════════════════════════ -->
            <div class="aba-conteudo <?php echo $aba_ativa === 'notificacoes' ? 'ativo' : ''; ?>" id="aba-notificacoes">
                <?php include 'views/dashboard_psicologa_notificacoes.php'; ?>
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
                    <!-- Link para a aba de agenda completa -->
                    <a href="?aba=agenda" class="widget-link">Ver tudo</a>
                </div>
                <div class="proximas-consultas">
                    <!-- Mostra apenas as 3 próximas consultas no widget -->
                    <?php foreach (array_slice($proximas_consultas, 0, 3) as $consulta): ?>
                        <div class="consulta-card">
                            <!-- Nome do paciente -->
                            <div class="consulta-card-titulo"><?php echo htmlspecialchars($consulta['paciente_nome']); ?></div>
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

            <!-- Widget: Notificações Recentes (resumo rápido) -->
            <div class="widget">
                <div class="widget-header">
                    <h3>Notificações Recentes</h3>
                    <!-- Link para a aba de notificações completa -->
                    <a href="?aba=notificacoes" class="widget-link">Ver tudo</a>
                </div>
                <div class="notificacoes-lista">
                    <!-- Mostra apenas as 2 notificações mais recentes no widget -->
                    <?php foreach (array_slice($notificacoes, 0, 2) as $notif): ?>
                        <!-- Classe CSS dinâmica: 'lida' ou 'nao-lida' + tipo da notificação -->
                        <div class="notificacao-item <?php echo $notif['lida'] ? 'lida' : 'nao-lida'; ?> <?php echo htmlspecialchars($notif['tipo']); ?>">
                            <!-- Título amigável do tipo de notificação -->
                            <div class="notificacao-titulo"><?php echo htmlspecialchars(formatar_tipo_notificacao($notif['tipo'])); ?></div>
                            <!-- Texto truncado em 60 caracteres + "..." se necessário -->
                            <div class="notificacao-desc"><?php echo htmlspecialchars(substr($notif['mensagem'], 0, 60)); ?><?php echo strlen($notif['mensagem']) > 60 ? '...' : ''; ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($notificacoes)): ?>
                        <p style="color: var(--neutral-400); font-size: 13px; text-align: center; padding: 8px;">Nenhuma notificação.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Widget: Métricas (totais do sistema) -->
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

    <!-- ═══════════════════════════════════════════════════════════
         MODAL DE AÇÃO (confirmar ou cancelar consulta)
         Janela flutuante reutilizável para ambas as ações
    ═══════════════════════════════════════════════════════════ -->
    <!-- O modal é oculto por padrão e exibido pela função abrirModalAcao() -->
    <div id="modalAcao" class="modal">
        <div class="modal-conteudo">
            <div class="modal-header">
                <div>
                    <!-- Badge de ação (preenchido pelo JS: "CONFIRMAÇÃO" ou "CANCELAMENTO") -->
                    <span class="modal-badge-acao" id="modalAcaoBadge">Acao</span>
                    <!-- Título do modal (preenchido pelo JS) -->
                    <h2 id="modalTitulo">Acao</h2>
                </div>
                <!-- Botão X para fechar o modal -->
                <button class="modal-fechar" onclick="fecharModalAcao()">&times;</button>
            </div>
            <!-- Formulário enviado via POST para o PHP -->
            <form method="POST" id="formModalAcao" onsubmit="return validarModalAcao()">
                <!-- Campo oculto: 'confirmar_consulta' ou 'cancelar_consulta' (preenchido pelo JS) -->
                <input type="hidden" name="acao" id="inputAcao">
                <!-- Campo oculto: ID da consulta (preenchido pelo JS) -->
                <input type="hidden" name="id_consulta" id="inputIdConsulta">
                <div class="modal-body">
                    <!-- Ícone de confirmação/cancelamento (substituído pelo JS) -->
                    <div class="modal-icone-msg" id="modalIconeMsg">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <!-- Mensagem descritiva (substituída pelo JS) -->
                    <p id="modalMensagem" class="modal-msg-texto">Deseja realizar esta acao?</p>
                    <!-- Campo de comentário/motivo (label e placeholder alterados pelo JS) -->
                    <div id="grupoComentario" class="form-group" style="margin-top: 20px;">
                        <label id="labelComentario">Comentario para o paciente</label>
                        <textarea name="comentario" id="comentarioModal" placeholder="Adicione um comentario..." rows="3"></textarea>
                        <!-- Mensagem de erro exibida quando o motivo de cancelamento está vazio -->
                        <p id="erroMotivo" class="form-erro" style="display:none;">O motivo do cancelamento e obrigatorio.</p>
                    </div>
                </div>
                <div class="modal-acoes">
                    <button type="button" class="btn btn-secondary btn-modal-fechar" onclick="fecharModalAcao()">Voltar</button>
                    <!-- Botão de confirmação (texto e estilo alterados pelo JS) -->
                    <button type="submit" class="btn btn-primary btn-modal-acao" id="btnConfirmarAcao">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variável global que armazena o tipo de ação atual do modal ('confirmar' ou 'cancelar')
        var _modalTipoAcao = '';

        /*
         * Abre o modal de ação configurando-o para confirmar ou cancelar uma consulta.
         * Adapta o visual e os textos conforme o tipo de ação.
         *
         * @param {string} tipo - 'confirmar' ou 'cancelar'
         * @param {number} id   - ID da consulta a ser processada
         */
        function abrirModalAcao(tipo, id) {
            _modalTipoAcao = tipo;
            // Define a ação do formulário: 'confirmar_consulta' ou 'cancelar_consulta'
            document.getElementById('inputAcao').value = tipo + '_consulta';
            document.getElementById('inputIdConsulta').value = id;
            // Limpa o campo de comentário e oculta a mensagem de erro
            document.getElementById('comentarioModal').value = '';
            document.getElementById('erroMotivo').style.display = 'none';
            var badge = document.getElementById('modalAcaoBadge');
            var icone = document.getElementById('modalIconeMsg');
            if (tipo === 'confirmar') {
                // Configuração visual para confirmação (verde)
                document.getElementById('modalTitulo').textContent = 'Confirmar Consulta';
                document.getElementById('modalMensagem').textContent = 'Deseja confirmar esta consulta? A paciente sera notificada.';
                document.getElementById('labelComentario').textContent = 'Comentario (opcional)';
                document.getElementById('comentarioModal').placeholder = 'Adicione um comentario para a paciente...';
                document.getElementById('btnConfirmarAcao').className = 'btn btn-primary btn-modal-acao';
                // Ícone de check (confirmação) com SVG inline
                document.getElementById('btnConfirmarAcao').innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Confirmar Consulta';
                badge.textContent = 'Confirmacao';
                badge.style.background = 'rgba(16, 185, 129, 0.12)'; // Verde claro
                badge.style.color = 'var(--success)';
                // Ícone de check grande no corpo do modal
                icone.innerHTML = '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
            } else {
                // Configuração visual para cancelamento (vermelho)
                document.getElementById('modalTitulo').textContent = 'Cancelar Consulta';
                document.getElementById('modalMensagem').textContent = 'Ao cancelar, a paciente sera notificada automaticamente. Informe o motivo:';
                document.getElementById('labelComentario').textContent = 'Motivo do Cancelamento *'; // * = obrigatório
                document.getElementById('comentarioModal').placeholder = 'Explique o motivo do cancelamento para a paciente...';
                document.getElementById('btnConfirmarAcao').className = 'btn btn-cancelar btn-modal-acao';
                // Ícone de X (cancelamento) com SVG inline
                document.getElementById('btnConfirmarAcao').innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Confirmar Cancelamento';
                badge.textContent = 'Cancelamento';
                badge.style.background = 'rgba(239, 68, 68, 0.12)'; // Vermelho claro
                badge.style.color = 'var(--danger)';
                // Ícone de X grande no corpo do modal
                icone.innerHTML = '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
            }
            // Exibe o modal adicionando a classe 'show'
            document.getElementById('modalAcao').classList.add('show');
        }

        /*
         * Fecha o modal de ação.
         * Chamada pelo botão X, pelo botão "Voltar" ou pelo clique fora do modal.
         */
        function fecharModalAcao() {
            document.getElementById('modalAcao').classList.remove('show');
        }

        /*
         * Valida o formulário do modal antes de enviar.
         * Para cancelamentos, o motivo é obrigatório.
         *
         * @returns {boolean} - true para enviar, false para cancelar o envio
         */
        function validarModalAcao() {
            if (_modalTipoAcao === 'cancelar') {
                var motivo = document.getElementById('comentarioModal').value.trim();
                if (!motivo) {
                    // Exibe a mensagem de erro e impede o envio do formulário
                    document.getElementById('erroMotivo').style.display = 'block';
                    return false;
                }
            }
            return true; // Permite o envio do formulário
        }

        // Fecha o modal ao clicar fora da área de conteúdo (no overlay escuro)
        document.addEventListener('click', function(event) {
            var modal = document.getElementById('modalAcao');
            // event.target === modal: o clique foi no overlay, não no conteúdo
            if (modal && event.target === modal) {
                modal.classList.remove('show');
            }
        });
    </script>
</body>
</html>
