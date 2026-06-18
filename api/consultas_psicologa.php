<?php
/*
 * ARQUIVO: api/consultas_psicologa.php
 * DESCRIÇÃO: Endpoint JSON que retorna todas as consultas como eventos do FullCalendar
 *            para o calendário da psicóloga.
 *
 * Este arquivo é chamado automaticamente pelo FullCalendar como fonte de eventos
 * no dashboard da psicóloga:
 *   events: 'api/consultas_psicologa.php'
 *
 * DIFERENÇA em relação a api/consultas_paciente.php:
 *   - Busca TODAS as consultas (não filtra por paciente)
 *   - O título do evento inclui o nome do paciente
 *   - Usa cores diferentes (paleta mais vibrante: indigo, âmbar, verde)
 *   - Não inclui 'valor' e 'modalidade' nas extendedProps
 *   - Não calcula o horário de fim (sem campo 'end')
 *
 * MÉTODO: GET (chamado automaticamente pelo FullCalendar)
 * AUTENTICAÇÃO: Requer $_SESSION['id_psicologa'] ativo
 *
 * RETORNO (JSON):
 *   Array de objetos de evento no formato FullCalendar:
 *   [
 *     {
 *       "id": 1,
 *       "title": "09:00h - João Silva (Ansiedade)",
 *       "start": "2025-01-15T09:00:00",
 *       "backgroundColor": "#10b981",
 *       "borderColor": "#10b981",
 *       "textColor": "#ffffff",
 *       "classNames": ["consulta-calendario", "consulta-futura-evento", "consulta-status-confirmada"],
 *       "extendedProps": { "status": "Confirmada", "paciente": "João Silva", ... }
 *     }
 *   ]
 *
 * CORES DOS EVENTOS:
 *   - Pendente:   #f59e0b (âmbar/warning)
 *   - Confirmada: #10b981 (verde/success)
 *   - Padrão:     #6366f1 (indigo/primary)
 */

// Carrega a conexão com o banco de dados (variável $pdo)
require_once '../config/conexao.php';
// Carrega as funções auxiliares (obter_todas_consultas, etc.)
require_once '../config/funcoes.php';

// Define o cabeçalho HTTP para indicar que a resposta é JSON
header('Content-Type: application/json');

// Verifica se a psicóloga está autenticada (sessão ativa)
// Se não estiver, retorna array vazio (sem eventos) e encerra
if (!isset($_SESSION['id_psicologa'])) {
    echo json_encode([]);
    exit;
}

// Busca TODAS as consultas do sistema (não filtra por paciente específico)
// A psicóloga vê todas as consultas de todos os pacientes
$consultas = obter_todas_consultas($pdo);
// Array que acumulará os eventos formatados para o FullCalendar
$eventos = [];
// Timestamp Unix atual para determinar se uma consulta é passada
$agora = time();

// Percorre cada consulta e converte para o formato de evento do FullCalendar
foreach ($consultas as $c) {
    // Ignora consultas canceladas (não exibe no calendário da psicóloga)
    if ($c['status'] === 'Cancelada') {
        continue; // Pula para a próxima iteração
    }

    // ── Normalização do Horário ──
    // O banco pode armazenar horários em diferentes formatos; normaliza para HH:MM:SS
    $horario = $c['horario'];
    if (strlen($horario) === 5) {
        // Formato "HH:MM" → adiciona ":00" para obter "HH:MM:00"
        $horario .= ':00';
    } elseif (strlen($horario) === 4) {
        // Formato "H:MM" (sem zero à esquerda) → adiciona "0" no início e ":00" no fim
        $horario = '0' . $horario . ':00';
    }

    // ── Determinação se a Consulta é Passada ──
    $inicio = strtotime($c['data_calendario'] . ' ' . $horario);
    $passada = $inicio && $inicio < $agora;

    // ── Definição da Cor do Evento ──
    // Usa paleta de cores mais vibrante (diferente do calendário do paciente)
    $cor = '#6366f1'; // Indigo: cor padrão para status não mapeados
    if ($c['status'] === 'Pendente') {
        $cor = '#f59e0b';   // Âmbar: aguardando confirmação
    } elseif ($c['status'] === 'Confirmada') {
        $cor = '#10b981';   // Verde: consulta confirmada
    }

    // ── Montagem do Objeto de Evento ──
    $eventos[] = [
        'id'    => $c['id_consulta'],
        // Título inclui horário, nome do paciente e especialização
        // Formato: "09:00h - João Silva (Ansiedade)"
        'title' => substr($c['horario'], 0, 5) . 'h - ' . $c['paciente_nome'] . ' (' . $c['especializacao'] . ')',
        // Início no formato ISO 8601 (YYYY-MM-DDTHH:MM:SS)
        'start' => $c['data_calendario'] . 'T' . $horario,
        // Nota: sem campo 'end' (diferente do endpoint do paciente)
        // O FullCalendar usará a duração padrão configurada
        'backgroundColor' => $cor,
        // Borda: cinza para consultas passadas, mesma cor do fundo para futuras
        'borderColor' => $passada ? '#6b7280' : $cor,
        'textColor' => '#ffffff', // Texto branco para contraste
        // Classes CSS aplicadas ao elemento do evento no calendário
        'classNames' => [
            'consulta-calendario',
            $passada ? 'consulta-passada-evento' : 'consulta-futura-evento',
            'consulta-status-' . strtolower($c['status']) // Ex: consulta-status-confirmada
        ],
        // Propriedades extras acessíveis via info.event.extendedProps no JavaScript
        // Usadas para exibir detalhes no modal ao clicar no evento
        'extendedProps' => [
            'status'         => $c['status'],
            'paciente'       => $c['paciente_nome'],    // Nome do paciente (diferente do endpoint do paciente)
            'especializacao' => $c['especializacao'],
            'pagamento'      => $c['pagamento_status'],
            'passada'        => $passada                // Boolean: indica se a consulta já ocorreu
        ]
    ];
}

// Retorna o array de eventos como JSON
// JSON_UNESCAPED_UNICODE: mantém caracteres especiais (acentos) sem escapar para \uXXXX
echo json_encode($eventos, JSON_UNESCAPED_UNICODE);
