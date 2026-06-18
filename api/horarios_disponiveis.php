<?php
/*
 * ARQUIVO: api/horarios_disponiveis.php
 * DESCRIÇÃO: Endpoint JSON que retorna os horários disponíveis para uma data específica.
 *
 * Este arquivo é chamado via AJAX (fetch) pelos arquivos:
 *   - views/dashboard_paciente_agendar.php
 *   - views/dashboard_paciente_calendario.php
 *
 * MÉTODO: GET
 * PARÂMETRO: data (string no formato YYYY-MM-DD)
 *
 * RETORNO (JSON):
 *   Sucesso:  { "id_data": int, "data_formatada": "dd/mm/YYYY", "horarios": [...] }
 *   Erro:     { "erro": "mensagem de erro" }
 *
 * LÓGICA:
 *   1. Valida o parâmetro 'data' da query string
 *   2. Busca a data na tabela 'datas_disponiveis' (apenas datas com status 'Disponivel')
 *   3. Chama obter_horarios_disponiveis() para filtrar horários não ocupados
 *   4. Retorna o JSON com id_data, data formatada e lista de horários
 */

// Carrega a conexão com o banco de dados (variável $pdo)
require_once '../config/conexao.php';
// Carrega as funções auxiliares (formatar_data, obter_horarios_disponiveis, etc.)
require_once '../config/funcoes.php';

// Define o cabeçalho HTTP para indicar que a resposta é JSON
header('Content-Type: application/json');

// Obtém o parâmetro 'data' da query string (ex: ?data=2025-01-15)
// O operador '??' retorna '' se o parâmetro não existir (evita undefined index)
$data_str = $_GET['data'] ?? '';

// Valida se o parâmetro foi informado
if (empty($data_str)) {
    echo json_encode(['erro' => 'Data não informada']);
    exit; // Encerra a execução do script
}

// Busca a data na tabela 'datas_disponiveis'
// Condição: data_calendario = data informada E status_dia = 'Disponivel'
// Usa prepared statement (?) para evitar SQL Injection
$stmt = $pdo->prepare("SELECT id_data, data_calendario FROM datas_disponiveis WHERE data_calendario = ? AND status_dia = 'Disponivel'");
// Executa a query passando a data como parâmetro
$stmt->execute([$data_str]);
// Busca o resultado (apenas uma linha, pois a data é única)
$data_banco = $stmt->fetch();

// Se a data não foi encontrada ou não está disponível, retorna erro
if (!$data_banco) {
    echo json_encode(['erro' => 'Data não disponível']);
    exit;
}

// Busca os horários disponíveis para o id_data encontrado
// obter_horarios_disponiveis() filtra horários que não estão ocupados
// por consultas com status 'Confirmada' ou 'Pendente'
$horarios = obter_horarios_disponiveis($pdo, $data_banco['id_data']);

// Retorna o JSON com:
//   - id_data: ID da data no banco (usado como campo oculto no formulário)
//   - data_formatada: data no formato dd/mm/YYYY para exibição ao usuário
//   - horarios: array de objetos { id_horario, horario } com os horários livres
echo json_encode([
    'id_data' => $data_banco['id_data'],
    'data_formatada' => formatar_data($data_banco['data_calendario']),
    'horarios' => $horarios
]);
?>
