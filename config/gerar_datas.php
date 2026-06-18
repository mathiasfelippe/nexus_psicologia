<?php
/*
 * ARQUIVO: config/gerar_datas.php
 * DESCRIÇÃO: Biblioteca de funções para geração automática de datas disponíveis.
 *
 * Este arquivo contém as funções responsáveis por popular a tabela
 * 'datas_disponiveis' do banco de dados com datas úteis (segunda a sexta),
 * excluindo feriados nacionais.
 *
 * INCLUÍDO EM: config/inicializar_datas.php
 *
 * FLUXO DE USO:
 *   1. gerar_datas_automaticas() → gera um array de datas válidas
 *   2. inserir_datas_banco()     → insere essas datas no banco de dados
 */

// ─── FERIADOS NACIONAIS FIXOS ─────────────────────────────────────────────────
// Lista de feriados nacionais com data fixa no formato 'mês-dia' (MM-DD)
// Esses feriados ocorrem na mesma data todos os anos
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

/*
 * FUNÇÃO: eh_feriado
 * Verifica se uma data específica é um feriado nacional.
 * Usada por gerar_datas_automaticas() para filtrar feriados.
 *
 * @param string $data_str - Data no formato 'Y-m-d' (ex: '2025-12-25')
 * @param array $feriados_fixos - Array de feriados no formato 'MM-DD'
 * @return bool - true se a data for feriado, false caso contrário
 */
function eh_feriado($data_str, $feriados_fixos) {
    // Converte a string de data para um objeto DateTime para facilitar a formatação
    $data = DateTime::createFromFormat('Y-m-d', $data_str);

    // Extrai apenas o mês e o dia no formato 'MM-DD' para comparar com a lista
    $mes_dia = $data->format('m-d');
    
    // Verificar feriados fixos
    // in_array verifica se o valor existe no array
    if (in_array($mes_dia, $feriados_fixos)) {
        return true; // É feriado fixo
    }
    
    // Feriados móveis (Páscoa e relacionados) - simplificado
    // Para uma implementação completa, você precisaria calcular a Páscoa
    // (a Páscoa muda de data a cada ano e não está implementada aqui)
    
    return false; // Não é feriado
}

/*
 * FUNÇÃO: gerar_datas_automaticas
 * Gera um array com todas as datas de dias úteis (segunda a sexta)
 * para os próximos N meses, excluindo feriados nacionais.
 *
 * LÓGICA:
 *   1. Define o período: de hoje até N meses à frente
 *   2. Itera dia a dia pelo período usando DatePeriod
 *   3. Para cada dia, verifica se é dia útil (seg-sex) e não é feriado
 *   4. Se passar nos dois filtros, adiciona ao array de datas
 *
 * @param int $meses - Número de meses à frente para gerar datas (padrão: 6)
 * @return array - Array de strings de datas no formato 'Y-m-d'
 */
function gerar_datas_automaticas($meses = 6) {
    global $feriados_fixos; // Acessa a variável global definida no início do arquivo
    
    $datas = []; // Array que vai acumular as datas válidas
    
    // Data de início: hoje
    $data_inicio = new DateTime('now');
    
    // Data de fim: hoje + N meses (clone evita modificar $data_inicio)
    $data_fim = clone $data_inicio;
    $data_fim->modify("+{$meses} months");
    
    // DateInterval('P1D') = intervalo de 1 dia (P = Period, D = Day)
    $intervalo = new DateInterval('P1D');
    
    // DatePeriod gera uma sequência de datas: de $data_inicio até $data_fim, de 1 em 1 dia
    $periodo = new DatePeriod($data_inicio, $intervalo, $data_fim);
    
    // Itera sobre cada dia do período
    foreach ($periodo as $data) {
        // format('w') retorna o dia da semana como número:
        // 0 = Domingo, 1 = Segunda, 2 = Terça, 3 = Quarta, 4 = Quinta, 5 = Sexta, 6 = Sábado
        $dia_semana = $data->format('w');
        
        // Filtra apenas dias úteis: segunda (1) a sexta (5)
        if ($dia_semana >= 1 && $dia_semana <= 5) {
            $data_str = $data->format('Y-m-d'); // Formata como 'YYYY-MM-DD'
            
            // Verifica se não é feriado antes de adicionar
            if (!eh_feriado($data_str, $feriados_fixos)) {
                $datas[] = $data_str; // Adiciona a data ao array
            }
        }
    }
    
    return $datas;
}

/*
 * FUNÇÃO: inserir_datas_banco
 * Insere um array de datas na tabela 'datas_disponiveis' do banco de dados.
 * Usa INSERT OR IGNORE para não duplicar datas já existentes.
 *
 * INSERT OR IGNORE: se a data já existir (violação de UNIQUE), simplesmente
 * ignora o erro e continua para a próxima data.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param array $datas - Array de strings de datas no formato 'Y-m-d'
 * @return array - Array com contagem de datas inseridas e duplicadas
 *                 ['inseridas' => int, 'duplicadas' => int]
 */
function inserir_datas_banco($pdo, $datas) {
    // Prepara o INSERT uma única vez (mais eficiente do que preparar dentro do loop)
    // INSERT OR IGNORE: ignora silenciosamente se a data já existir
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO datas_disponiveis (data_calendario, status_dia) VALUES (?, 'Disponivel')");
    
    $inseridas = 0;  // Contador de datas novas inseridas
    $duplicadas = 0; // Contador de datas que já existiam
    
    // Insere cada data individualmente
    foreach ($datas as $data) {
        try {
            // Executa a consulta no banco de dados
            if ($stmt->execute([$data])) {
                // rowCount() retorna o número de linhas afetadas
                // Se for > 0, a data foi inserida; se for 0, já existia (OR IGNORE)
                if ($stmt->rowCount() > 0) {
                    $inseridas++;
                } else {
                    $duplicadas++; // Data já existia no banco
                }
            }
        } catch (Exception $e) {
            // Em caso de qualquer outro erro, conta como duplicada e continua
            $duplicadas++;
        }
    }
    
    // Retorna um resumo da operação
    return [
        'inseridas' => $inseridas,
        'duplicadas' => $duplicadas
    ];
}

?>
