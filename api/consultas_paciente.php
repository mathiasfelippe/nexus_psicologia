<?php
require_once '../config/conexao.php';
require_once '../config/funcoes.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_paciente'])) {
    echo json_encode([]);
    exit;
}

$id_paciente = intval($_SESSION['id_paciente']);
$consultas = obter_consultas_paciente($pdo, $id_paciente);
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
    $cor = '#80A1D4';

    if ($c['status'] === 'Pendente') {
        $cor = '#e0a85c';
    } elseif ($c['status'] === 'Confirmada') {
        $cor = '#75C9C8';
    } elseif ($c['pagamento_status'] === 'Reembolsado') {
        $cor = '#c08080';
    }

    $eventos[] = [
        'id' => $c['id_consulta'],
        'title' => substr($c['horario'], 0, 5) . 'h - ' . $c['especializacao'],
        'start' => $c['data_calendario'] . 'T' . $horario,
        'end' => date('Y-m-d\TH:i:s', strtotime($c['data_calendario'] . ' ' . $horario . ' +1 hour')),
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
            'especializacao' => $c['especializacao'],
            'modalidade' => $c['modalidade'] ?? '',
            'pagamento' => $c['pagamento_status'],
            'valor' => $c['valor'],
            'passada' => $passada
        ]
    ];
}

echo json_encode($eventos, JSON_UNESCAPED_UNICODE);
