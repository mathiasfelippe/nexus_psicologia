<?php
/*
 * ARQUIVO: config/inicializar_datas.php
 * DESCRIÇÃO: Inicialização automática das datas disponíveis para agendamento.
 *
 * Este arquivo é incluído automaticamente pelo sistema na inicialização.
 * Ele garante que a tabela 'datas_disponiveis' sempre tenha pelo menos
 * 30 dias de datas futuras disponíveis para agendamento.
 *
 * ESTRATÉGIA DE MANUTENÇÃO:
 *   - Verifica a data mais futura cadastrada no banco
 *   - Se essa data for inferior a 30 dias a partir de hoje, gera novas datas
 *   - Gera datas para os próximos 6 meses a partir da última data cadastrada
 *   - Exclui finais de semana e feriados nacionais fixos
 *
 * INCLUÍDO EM: config/conexao.php (indiretamente via dashboard_*.php)
 *
 * DEPENDÊNCIA: Requer que $pdo (conexão PDO) já esteja disponível
 */

/*
 * FUNÇÃO: inicializar_datas_disponiveis
 * Verifica e popula automaticamente a tabela de datas disponíveis.
 * Deve ser chamada uma vez por requisição para manter o banco atualizado.
 *
 * @param PDO $pdo - Conexão com o banco de dados (já criada em conexao.php)
 */
function inicializar_datas_disponiveis($pdo) {
    // Lista de feriados nacionais com data fixa no formato 'MM-DD'
    // Esses feriados são excluídos da geração de datas disponíveis
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

    // Busca a data mais futura já cadastrada na tabela datas_disponiveis
    // MAX() retorna o maior valor, que para datas no formato 'Y-m-d' é a mais futura
    $stmt = $pdo->query("SELECT MAX(data_calendario) as ultima FROM datas_disponiveis");
    $row = $stmt->fetch();
    $ultima_data = $row['ultima'] ?? null; // null se a tabela estiver vazia

    // Data atual e limite de 30 dias à frente
    $hoje = new DateTime('now');
    $limite = (new DateTime('now'))->modify('+30 days'); // Data mínima aceitável

    // Verifica se precisa gerar novas datas:
    //   - Se a tabela estiver vazia ($ultima_data === null), ou
    //   - Se a última data cadastrada for antes do limite de 30 dias
    // Isso garante que sempre haja pelo menos 30 dias de datas disponíveis
    if ($ultima_data === null || $ultima_data < $limite->format('Y-m-d')) {

        // Define de onde começar a gerar:
        //   - Se já existem datas: começa do dia seguinte à última data
        //   - Se a tabela está vazia: começa de hoje
        $data_inicio = $ultima_data
            ? (new DateTime($ultima_data))->modify('+1 day') // Continua de onde parou
            : new DateTime('now');                           // Começa do zero

        // Data de fim: 6 meses a partir do início
        $data_fim = clone $data_inicio;
        $data_fim->modify("+6 months");

        $datas = []; // Array que vai acumular as datas válidas a inserir
        
        // DateInterval('P1D') = intervalo de 1 dia
        $intervalo = new DateInterval('P1D');
        
        // DatePeriod gera uma sequência de datas diárias entre início e fim
        $periodo = new DatePeriod($data_inicio, $intervalo, $data_fim);

        // Itera sobre cada dia do período para filtrar os válidos
        foreach ($periodo as $data) {
            // format('w') retorna o dia da semana: 0=Dom, 1=Seg, ..., 5=Sex, 6=Sáb
            $dia_semana = (int)$data->format('w');

            // Filtra apenas dias úteis (segunda a sexta)
            if ($dia_semana >= 1 && $dia_semana <= 5) {
                $data_str = $data->format('Y-m-d'); // Formato para o banco: 'YYYY-MM-DD'
                $mes_dia = $data->format('m-d');    // Formato para comparar feriados: 'MM-DD'

                // Verifica se não é feriado nacional
                if (!in_array($mes_dia, $feriados_fixos)) {
                    $datas[] = $data_str; // Adiciona ao array de datas válidas
                }
            }
        }

        // Insere as datas válidas no banco de dados (apenas se houver alguma)
        if (count($datas) > 0) {
            // INSERT OR IGNORE: não gera erro se a data já existir (evita duplicatas)
            $stmt_insert = $pdo->prepare("INSERT OR IGNORE INTO datas_disponiveis (data_calendario, status_dia) VALUES (?, 'Disponivel')");
            
            // Insere cada data individualmente
            foreach ($datas as $data) {
                try {
                    $stmt_insert->execute([$data]); // Insere com status 'Disponivel'
                } catch (Exception $e) {
                    // Ignorar erros de duplicação
                    // (segurança extra caso INSERT OR IGNORE não funcione como esperado)
                }
            }
        }
    }
    // Se a última data já está além de 30 dias, não faz nada (banco já está atualizado)
}

// Executa a inicialização imediatamente ao incluir este arquivo
// $pdo deve estar disponível no escopo (definido em conexao.php)
inicializar_datas_disponiveis($pdo);
?>
