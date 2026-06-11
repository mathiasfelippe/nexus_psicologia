<?php
require_once '../config/conexao.php';
require_once '../config/funcoes.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_psicologa'])) {
    echo json_encode([]);
    exit;
}

$consultas = obter_todas_consultas($pdo);
$cores_pacientes = [
    '#2563eb',
    '#7c3aed',
    '#0891b2',
    '#059669',
    '#d97706',
    '#dc2626',
    '#be185d',
    '#4f46e5',
    '#0f766e',
    '#9333ea'
];

function cor_paciente_calendario($id_paciente, $cores) {
    return $cores[abs(intval($id_paciente)) % count($cores)];
}

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
    $cor_paciente = cor_paciente_calendario($c['id_paciente'], $cores_pacientes);
    $cor_status = '#6366f1';

    if ($c['status'] === 'Pendente') {
        $cor_status = '#f59e0b';
    } elseif ($c['status'] === 'Confirmada') {
        $cor_status = '#10b981';
    }

    $eventos[] = [
        'id' => $c['id_consulta'],
        'title' => $c['horario'] . 'h - ' . $c['paciente_nome'] . ' (' . $c['especializacao'] . ')',
        'start' => $c['data_calendario'] . 'T' . $horario,
        'backgroundColor' => $cor_paciente,
        'borderColor' => $passada ? '#6b7280' : $cor_status,
        'textColor' => '#ffffff',
        'classNames' => [
            'consulta-calendario',
            'paciente-' . intval($c['id_paciente']),
            $passada ? 'consulta-passada-evento' : 'consulta-futura-evento',
            'consulta-status-' . strtolower($c['status'])
        ],
        'extendedProps' => [
            'status' => $c['status'],
            'paciente' => $c['paciente_nome'],
            'especializacao' => $c['especializacao'],
            'pagamento' => $c['pagamento_status'],
            'corPaciente' => $cor_paciente,
            'passada' => $passada
        ]
    ];
}

echo json_encode($eventos, JSON_UNESCAPED_UNICODE);
