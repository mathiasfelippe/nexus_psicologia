<?php
/*
 * ARQUIVO: api/consultas_paciente.php
 * DESCRIÇÃO: Endpoint JSON que retorna as consultas do paciente como eventos do FullCalendar.
 *
 * Este arquivo é chamado automaticamente pelo FullCalendar como fonte de eventos:
 *   events: 'api/consultas_paciente.php'
 *
 * MÉTODO: GET (chamado automaticamente pelo FullCalendar)
 * AUTENTICAÇÃO: Requer $_SESSION['id_paciente'] ativo
 *
 * RETORNO (JSON):
 *   Array de objetos de evento no formato FullCalendar:
 *   [
 *     {
 *       "id": 1,
 *       "title": "09:00h - Ansiedade",
 *       "start": "2025-01-15T09:00:00",
 *       "end": "2025-01-15T10:00:00",
 *       "backgroundColor": "#75C9C8",
 *       "borderColor": "#75C9C8",
 *       "textColor": "#ffffff",
 *       "classNames": ["consulta-calendario", "consulta-futura-evento", "consulta-status-confirmada"],
 *       "extendedProps": { "status": "Confirmada", "especializacao": "Ansiedade", ... }
 *     }
 *   ]
 *
 * CORES DOS EVENTOS:
 *   - Pendente:    #e0a85c (âmbar)
 *   - Confirmada:  #75C9C8 (teal)
 *   - Reembolsado: #c08080 (rosa escuro)
 *   - Padrão:      #80A1D4 (azul sereno)
 *
 * DURAÇÃO PADRÃO: 1 hora por consulta
 */

// Carrega a conexão com o banco de dados (variável $pdo)
require_once '../config/conexao.php';
// Carrega as funções auxiliares (obter_consultas_paciente, etc.)
require_once '../config/funcoes.php';

// Define o cabeçalho HTTP para indicar que a resposta é JSON
header('Content-Type: application/json');

// Verifica se o paciente está autenticado (sessão ativa)
// Se não estiver, retorna array vazio (sem eventos) e encerra
if (!isset($_SESSION['id_paciente'])) {
    echo json_encode([]);
    exit;
}

// Converte o ID da sessão para inteiro (segurança contra injeção)
$id_paciente = intval($_SESSION['id_paciente']);
// Busca todas as consultas do paciente no banco de dados
$consultas = obter_consultas_paciente($pdo, $id_paciente);
// Array que acumulará os eventos formatados para o FullCalendar
$eventos = [];
// Timestamp Unix atual para determinar se uma consulta é passada
$agora = time();

// Percorre cada consulta e converte para o formato de evento do FullCalendar
foreach ($consultas as $c) {
    // Ignora consultas canceladas (não exibe no calendário)
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
    // Após a normalização, $horario está sempre no formato "HH:MM:SS"

    // ── Determinação se a Consulta é Passada ──
    // Combina data e horário para obter o timestamp Unix do início da consulta
    $inicio = strtotime($c['data_calendario'] . ' ' . $horario);
    // Consulta é passada se o timestamp de início for menor que o timestamp atual
    // O operador && garante que $inicio seja válido (não false)
    $passada = $inicio && $inicio < $agora;

    // ── Definição da Cor do Evento ──
    // Cor padrão: azul sereno (usado para status não mapeados)
    $cor = '#80A1D4';

    if ($c['status'] === 'Pendente') {
        $cor = '#e0a85c'; // Âmbar: aguardando confirmação da psicóloga
    } elseif ($c['status'] === 'Confirmada') {
        $cor = '#75C9C8'; // Teal: consulta confirmada
    } elseif ($c['pagamento_status'] === 'Reembolsado') {
        $cor = '#c08080'; // Rosa escuro: consulta cancelada com reembolso
    }

    // ── Montagem do Objeto de Evento ──
    $eventos[] = [
        'id'    => $c['id_consulta'], // ID único do evento no FullCalendar
        // Título exibido no calendário: "HH:MMh - Especialização"
        // substr(..., 0, 5) extrai apenas HH:MM do horário
        'title' => substr($c['horario'], 0, 5) . 'h - ' . $c['especializacao'],
        // Início no formato ISO 8601 (YYYY-MM-DDTHH:MM:SS)
        'start' => $c['data_calendario'] . 'T' . $horario,
        // Fim: 1 hora após o início (duração padrão de uma consulta)
        // date() formata o timestamp calculado por strtotime(... +1 hour)
        'end'   => date('Y-m-d\TH:i:s', strtotime($c['data_calendario'] . ' ' . $horario . ' +1 hour')),
        'backgroundColor' => $cor,
        // Borda: cinza (#6b7280) para consultas passadas, mesma cor do fundo para futuras
        'borderColor' => $passada ? '#6b7280' : $cor,
        'textColor' => '#ffffff', // Texto branco para contraste com o fundo colorido
        // Classes CSS aplicadas ao elemento do evento no calendário
        'classNames' => [
            'consulta-calendario',                                         // Classe base
            $passada ? 'consulta-passada-evento' : 'consulta-futura-evento', // Passada ou futura
            'consulta-status-' . strtolower($c['status'])                  // Ex: consulta-status-confirmada
        ],
        // Propriedades extras acessíveis via info.event.extendedProps no JavaScript
        'extendedProps' => [
            'status'        => $c['status'],
            'especializacao' => $c['especializacao'],
            'modalidade'    => $c['modalidade'] ?? '', // ?? '' evita null se não existir
            'pagamento'     => $c['pagamento_status'],
            'valor'         => $c['valor'],
            'passada'       => $passada // Boolean: usado para desabilitar o reagendamento
        ]
    ];
}

// Retorna o array de eventos como JSON
// JSON_UNESCAPED_UNICODE: mantém caracteres especiais (acentos) sem escapar para \uXXXX
echo json_encode($eventos, JSON_UNESCAPED_UNICODE);
