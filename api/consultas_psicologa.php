<?php
require_once '../config/conexao.php';
require_once '../config/funcoes.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_psicologa'])) {
    echo json_encode([]);
    exit;
}

$consultas = obter_todas_consultas($pdo);
$eventos = [];
$agora = time();

foreach ($consultas as $c) {
    if ($c['status'] === 'Cancelada') {
        continue;
    }

    $horario = $c['horario'];
    if (strlen($horario) === 5) {
        $horario .= ':00';
    } elseif (strlen($horario) === 4) {
        $horario = '0' . $horario . ':00';
    }

    $inicio = strtotime($c['data_calendario'] . ' ' . $horario);
    $passada = $inicio && $inicio < $agora;

    // Cor por status — mesmo padrão do perfil Paciente
    $cor = '#6366f1'; // padrão (primary)
    if ($c['status'] === 'Pendente') {
        $cor = '#f59e0b';   // warning
    } elseif ($c['status'] === 'Confirmada') {
        $cor = '#10b981';   // success
    }

    $eventos[] = [
        'id' => $c['id_consulta'],
        'title' => substr($c['horario'], 0, 5) . 'h - ' . $c['paciente_nome'] . ' (' . $c['especializacao'] . ')',
        'start' => $c['data_calendario'] . 'T' . $horario,
        'backgroundColor' => $cor,
        'borderColor' => $passada ? '#6b7280' : $cor,
        'textColor' => '#ffffff',
        'classNames' => [
            'consulta-calendario',
            $passada ? 'consulta-passada-evento' : 'consulta-futura-evento',
            'consulta-status-' . strtolower($c['status'])
        ],
        'extendedProps' => [
            'status' => $c['status'],
            'paciente' => $c['paciente_nome'],
            'especializacao' => $c['especializacao'],
            'pagamento' => $c['pagamento_status'],
            'passada' => $passada
        ]
    ];
}

echo json_encode($eventos, JSON_UNESCAPED_UNICODE);
