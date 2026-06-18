<?php
/*
 * ARQUIVO: api/notificacoes.php
 * DESCRIÇÃO: Endpoint JSON para gerenciamento de notificações de pacientes e psicólogas.
 *
 * Este arquivo serve tanto pacientes quanto psicólogas, detectando o papel
 * do usuário pela sessão ativa. A psicóloga tem prioridade sobre o paciente.
 *
 * MÉTODOS SUPORTADOS:
 *
 *   GET  → Lista notificações e conta as não lidas
 *     Parâmetro opcional: limite (int, máx 200, padrão 100)
 *     Retorno: { "notificacoes": [...], "nao_lidas": int }
 *
 *   POST → Executa ações sobre notificações
 *     Parâmetro: acao (string)
 *     Ações disponíveis:
 *       - marcar_lida      → Marca uma notificação como lida (requer id_notificacao)
 *       - marcar_todas_lidas → Marca todas as notificações como lidas
 *       - excluir          → Exclui uma notificação (requer id_notificacao)
 *       - contar_nao_lidas → Retorna apenas o total de não lidas
 *
 * SEGURANÇA:
 *   - Requer sessão ativa (retorna 401 se não autenticado)
 *   - Todas as queries usam prepared statements para evitar SQL Injection
 *   - Filtro por destinatario + id_usuario garante isolamento entre usuários
 */

// Carrega a conexão com o banco de dados (variável $pdo)
require_once '../config/conexao.php';
// Carrega as funções auxiliares
require_once '../config/funcoes.php';

// Define o cabeçalho HTTP para indicar que a resposta é JSON
header('Content-Type: application/json');

// Obtém o método HTTP da requisição (GET ou POST)
$method = $_SERVER['REQUEST_METHOD'];

// ── Detecção do Papel do Usuário ──
// Psicóloga tem prioridade: se ambos existirem na sessão, prevalece a psicóloga
$id_psicologa = $_SESSION['id_psicologa'] ?? null;
$id_paciente  = $_SESSION['id_paciente'] ?? null;
// Operador ternário encadeado: psicologa → paciente → null (não autenticado)
$role = $id_psicologa ? 'psicologa' : ($id_paciente ? 'paciente' : null);

// Se não há papel definido, o usuário não está autenticado
if (!$role) {
    http_response_code(401); // HTTP 401 Unauthorized
    echo json_encode(['erro' => 'Nao autorizado']);
    exit;
}

// ══════════════════════════════════════════════════════════════
// MÉTODO GET: Listar notificações e contar não lidas
// ══════════════════════════════════════════════════════════════
if ($method === 'GET') {
    // Obtém o limite de notificações a retornar
    // min(..., 200) impede que o cliente solicite mais de 200 registros de uma vez
    $limite = isset($_GET['limite']) ? min(intval($_GET['limite']), 200) : 100;
    $stmt = null;

    if ($role === 'paciente') {
        // Busca notificações do paciente logado, ordenadas da mais recente para a mais antiga
        $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' ORDER BY data_criacao DESC LIMIT ?");
        $stmt->execute([$id_paciente, $limite]);
    } else {
        // Busca notificações da psicóloga logada
        $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' ORDER BY data_criacao DESC LIMIT ?");
        $stmt->execute([$id_psicologa, $limite]);
    }

    // Busca todos os resultados como array associativo
    $notificacoes = $stmt->fetchAll();

    // ── Contagem de Não Lidas ──
    // Query separada para contar apenas as notificações com lida = 0
    if ($role === 'paciente') {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' AND lida = 0");
        $stmt_count->execute([$id_paciente]);
    } else {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' AND lida = 0");
        $stmt_count->execute([$id_psicologa]);
    }
    // fetchColumn() retorna o valor da primeira coluna da primeira linha (o COUNT)
    $nao_lidas = $stmt_count->fetchColumn();

    // Retorna as notificações e o total de não lidas
    echo json_encode([
        'notificacoes' => $notificacoes,
        'nao_lidas' => intval($nao_lidas) // intval() garante que é um número inteiro
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// MÉTODO POST: Executar ações sobre notificações
// ══════════════════════════════════════════════════════════════
// Obtém a ação solicitada (ex: 'marcar_lida', 'excluir')
$acao = isset($_POST['acao']) ? $_POST['acao'] : '';
// Obtém o ID da notificação (0 se não informado)
$id_notificacao = isset($_POST['id_notificacao']) ? intval($_POST['id_notificacao']) : 0;

switch ($acao) {

    // ── Ação: Marcar uma notificação como lida ──
    case 'marcar_lida':
        // Valida o ID da notificação
        if ($id_notificacao <= 0) {
            echo json_encode(['erro' => 'ID invalido']);
            exit;
        }
        if ($role === 'paciente') {
            // UPDATE com filtro por id_notificacao + id_paciente + destinatario
            // Garante que o paciente só pode marcar suas próprias notificações
            $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_notificacao = ? AND id_paciente = ? AND destinatario = 'paciente'");
            $sucesso = $stmt->execute([$id_notificacao, $id_paciente]);
        } else {
            $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_notificacao = ? AND id_psicologa = ? AND destinatario = 'psicologa'");
            $sucesso = $stmt->execute([$id_notificacao, $id_psicologa]);
        }
        // Retorna true se a query foi executada com sucesso
        echo json_encode(['sucesso' => $sucesso]);
        break;

    // ── Ação: Marcar todas as notificações como lidas ──
    case 'marcar_todas_lidas':
        if ($role === 'paciente') {
            // Atualiza apenas as não lidas (lida = 0) para otimizar a query
            $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_paciente = ? AND destinatario = 'paciente' AND lida = 0");
            $sucesso = $stmt->execute([$id_paciente]);
        } else {
            $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_psicologa = ? AND destinatario = 'psicologa' AND lida = 0");
            $sucesso = $stmt->execute([$id_psicologa]);
        }
        echo json_encode(['sucesso' => $sucesso]);
        break;

    // ── Ação: Excluir uma notificação ──
    case 'excluir':
        if ($id_notificacao <= 0) {
            echo json_encode(['erro' => 'ID invalido']);
            exit;
        }
        if ($role === 'paciente') {
            // DELETE com filtro triplo: id_notificacao + id_paciente + destinatario
            // Impede que um paciente exclua notificações de outro usuário
            $stmt = $pdo->prepare("DELETE FROM notificacoes WHERE id_notificacao = ? AND id_paciente = ? AND destinatario = 'paciente'");
            $sucesso = $stmt->execute([$id_notificacao, $id_paciente]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM notificacoes WHERE id_notificacao = ? AND id_psicologa = ? AND destinatario = 'psicologa'");
            $sucesso = $stmt->execute([$id_notificacao, $id_psicologa]);
        }
        echo json_encode(['sucesso' => $sucesso]);
        break;

    // ── Ação: Contar apenas as notificações não lidas ──
    // Usado para atualizar badges/contadores sem buscar todas as notificações
    case 'contar_nao_lidas':
        if ($role === 'paciente') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' AND lida = 0");
            $stmt->execute([$id_paciente]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' AND lida = 0");
            $stmt->execute([$id_psicologa]);
        }
        // fetchColumn() retorna diretamente o valor do COUNT
        echo json_encode(['total' => intval($stmt->fetchColumn())]);
        break;

    // ── Ação inválida ou não reconhecida ──
    default:
        echo json_encode(['erro' => 'Acao invalida']);
        break;
}
