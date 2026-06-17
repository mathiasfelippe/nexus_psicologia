<?php

// Feriados nacionais brasileiros fixos (mês-dia)
$feriados_fixos = [
    '01-01', // Ano Novo
    '04-21', // Tiradentes
    '05-01', // Dia do Trabalho
    '09-07', // Independência
    '10-12', // Nossa Senhora Aparecida
    '11-02', // Finados
    '11-15', // Proclamação da República
    '11-20', // Consciência Negra
    '12-25', // Natal
];

/**
 * Verifica se uma data é feriado
 */
function eh_feriado($data_str, $feriados_fixos) {
    $data = DateTime::createFromFormat('Y-m-d', $data_str);
    $mes_dia = $data->format('m-d');
    
    // Verificar feriados fixos
    if (in_array($mes_dia, $feriados_fixos)) {
        return true;
    }
    
    // Feriados móveis (Páscoa e relacionados) - simplificado
    // Para uma implementação completa, você precisaria calcular a Páscoa
    
    return false;
}

/**
 * Gera datas de segunda a sexta para os próximos meses
 */
function gerar_datas_automaticas($meses = 6) {
    global $feriados_fixos;
    
    $datas = [];
    $data_inicio = new DateTime('now');
    $data_fim = clone $data_inicio;
    $data_fim->modify("+{$meses} months");
    
    $intervalo = new DateInterval('P1D');
    $periodo = new DatePeriod($data_inicio, $intervalo, $data_fim);
    
    foreach ($periodo as $data) {
        // Verificar se é segunda a sexta (1-5, sendo 0 = domingo)
        $dia_semana = $data->format('w');
        
        if ($dia_semana >= 1 && $dia_semana <= 5) {
            $data_str = $data->format('Y-m-d');
            
            // Verificar se não é feriado
            if (!eh_feriado($data_str, $feriados_fixos)) {
                $datas[] = $data_str;
            }
        }
    }
    
    return $datas;
}

/**
 * Insere as datas no banco de dados
 */
function inserir_datas_banco($pdo, $datas) {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO datas_disponiveis (data_calendario, status_dia) VALUES (?, 'Disponivel')");
    
    $inseridas = 0;
    $duplicadas = 0;
    
    foreach ($datas as $data) {
        try {
            if ($stmt->execute([$data])) {
                if ($stmt->rowCount() > 0) {
                    $inseridas++;
                } else {
                    $duplicadas++;
                }
            }
        } catch (Exception $e) {
            $duplicadas++;
        }
    }
    
    return [
        'inseridas' => $inseridas,
        'duplicadas' => $duplicadas
    ];
}

?>
