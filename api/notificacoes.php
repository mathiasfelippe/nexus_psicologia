<?php
require_once '../config/conexao.php';
require_once '../config/funcoes.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Determinar papel e ID do usuário logado
// Psicóloga tem prioridade: se ambos existirem na sessão (ex: login anterior de paciente),
// prevalece o papel de psicóloga
$id_psicologa = $_SESSION['id_psicologa'] ?? null;
$id_paciente  = $_SESSION['id_paciente'] ?? null;
$role = $id_psicologa ? 'psicologa' : ($id_paciente ? 'paciente' : null);

if (!$role) {
    http_response_code(401);
    echo json_encode(['erro' => 'Nao autorizado']);
    exit;
}

// ── GET: listar notificações ──
if ($method === 'GET') {
    $limite = isset($_GET['limite']) ? min(intval($_GET['limite']), 200) : 100;
    $stmt = null;

    if ($role === 'paciente') {
        $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' ORDER BY data_criacao DESC LIMIT ?");
        $stmt->execute([$id_paciente, $limite]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' ORDER BY data_criacao DESC LIMIT ?");
        $stmt->execute([$id_psicologa, $limite]);
    }

    $notificacoes = $stmt->fetchAll();

    // Contar não lidas
    if ($role === 'paciente') {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' AND lida = 0");
        $stmt_count->execute([$id_paciente]);
    } else {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' AND lida = 0");
        $stmt_count->execute([$id_psicologa]);
    }
    $nao_lidas = $stmt_count->fetchColumn();

    echo json_encode([
        'notificacoes' => $notificacoes,
        'nao_lidas' => intval($nao_lidas)
    ]);
    exit;
}

// ── POST: ações ──
$acao = isset($_POST['acao']) ? $_POST['acao'] : '';
$id_notificacao = isset($_POST['id_notificacao']) ? intval($_POST['id_notificacao']) : 0;

switch ($acao) {
    case 'marcar_lida':
        if ($id_notificacao <= 0) {
            echo json_encode(['erro' => 'ID invalido']);
            exit;
        }
        if ($role === 'paciente') {
            $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_notificacao = ? AND id_paciente = ? AND destinatario = 'paciente'");
            $sucesso = $stmt->execute([$id_notificacao, $id_paciente]);
        } else {
            $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_notificacao = ? AND id_psicologa = ? AND destinatario = 'psicologa'");
            $sucesso = $stmt->execute([$id_notificacao, $id_psicologa]);
        }
        echo json_encode(['sucesso' => $sucesso]);
        break;

    case 'marcar_todas_lidas':
        if ($role === 'paciente') {
            $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_paciente = ? AND destinatario = 'paciente' AND lida = 0");
            $sucesso = $stmt->execute([$id_paciente]);
        } else {
            $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_psicologa = ? AND destinatario = 'psicologa' AND lida = 0");
            $sucesso = $stmt->execute([$id_psicologa]);
        }
        echo json_encode(['sucesso' => $sucesso]);
        break;

    case 'excluir':
        if ($id_notificacao <= 0) {
            echo json_encode(['erro' => 'ID invalido']);
            exit;
        }
        if ($role === 'paciente') {
            $stmt = $pdo->prepare("DELETE FROM notificacoes WHERE id_notificacao = ? AND id_paciente = ? AND destinatario = 'paciente'");
            $sucesso = $stmt->execute([$id_notificacao, $id_paciente]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM notificacoes WHERE id_notificacao = ? AND id_psicologa = ? AND destinatario = 'psicologa'");
            $sucesso = $stmt->execute([$id_notificacao, $id_psicologa]);
        }
        echo json_encode(['sucesso' => $sucesso]);
        break;

    case 'contar_nao_lidas':
        if ($role === 'paciente') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' AND lida = 0");
            $stmt->execute([$id_paciente]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' AND lida = 0");
            $stmt->execute([$id_psicologa]);
        }
        echo json_encode(['total' => intval($stmt->fetchColumn())]);
        break;

    default:
        echo json_encode(['erro' => 'Acao invalida']);
        break;
}
