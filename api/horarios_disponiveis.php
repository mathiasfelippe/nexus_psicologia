<?php
require_once '../config/conexao.php';
require_once '../config/funcoes.php';

header('Content-Type: application/json');

$data_str = $_GET['data'] ?? '';

if (empty($data_str)) {
    echo json_encode(['erro' => 'Data não informada']);
    exit;
}

// Buscar id_data para a data informada
$stmt = $pdo->prepare("SELECT id_data, data_calendario FROM datas_disponiveis WHERE data_calendario = ? AND status_dia = 'Disponivel'");
$stmt->execute([$data_str]);
$data_banco = $stmt->fetch();

if (!$data_banco) {
    echo json_encode(['erro' => 'Data não disponível']);
    exit;
}

// Obter horários que não estão ocupados por consultas confirmadas ou pendentes
$horarios = obter_horarios_disponiveis($pdo, $data_banco['id_data']);

echo json_encode([
    'id_data' => $data_banco['id_data'],
    'data_formatada' => formatar_data($data_banco['data_calendario']),
    'horarios' => $horarios
]);
?>
