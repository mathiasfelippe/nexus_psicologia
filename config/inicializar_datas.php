<?php

function inicializar_datas_disponiveis($pdo) {
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

    // Verificar a data mais futura cadastrada
    $stmt = $pdo->query("SELECT MAX(data_calendario) as ultima FROM datas_disponiveis");
    $row = $stmt->fetch();
    $ultima_data = $row['ultima'] ?? null;

    $hoje = new DateTime('now');
    $limite = (new DateTime('now'))->modify('+30 days');

    // Se não existir data alguma, ou a última data for inferior a 30 dias
    if ($ultima_data === null || $ultima_data < $limite->format('Y-m-d')) {

        // Data de início: ou amanhã (se já existem datas), ou hoje (se tabela vazia)
        $data_inicio = $ultima_data
            ? (new DateTime($ultima_data))->modify('+1 day')
            : new DateTime('now');

        $data_fim = clone $data_inicio;
        $data_fim->modify("+6 months");

        $datas = [];
        $intervalo = new DateInterval('P1D');
        $periodo = new DatePeriod($data_inicio, $intervalo, $data_fim);

        foreach ($periodo as $data) {
            $dia_semana = (int)$data->format('w');
            if ($dia_semana >= 1 && $dia_semana <= 5) {
                $data_str = $data->format('Y-m-d');
                $mes_dia = $data->format('m-d');
                if (!in_array($mes_dia, $feriados_fixos)) {
                    $datas[] = $data_str;
                }
            }
        }

        if (count($datas) > 0) {
            $stmt_insert = $pdo->prepare("INSERT OR IGNORE INTO datas_disponiveis (data_calendario, status_dia) VALUES (?, 'Disponivel')");
            foreach ($datas as $data) {
                try {
                    $stmt_insert->execute([$data]);
                } catch (Exception $e) {
                    // Ignorar erros de duplicação
                }
            }
        }
    }
}

// Executar inicialização
inicializar_datas_disponiveis($pdo);
?>
