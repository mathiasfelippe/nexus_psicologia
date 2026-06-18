<?php
/*
 * ARQUIVO: config/funcoes.php
 * DESCRIÇÃO: Biblioteca central de funções do sistema Nexus Psicologia.
 *
 * Este arquivo é incluído por todos os dashboards e APIs do sistema.
 * Ele centraliza toda a lógica de negócio e acesso ao banco de dados,
 * evitando duplicação de código.
 *
 * ORGANIZAÇÃO DAS FUNÇÕES:
 *   1. Manutenção do Banco de Dados (esquema)
 *   2. Autenticação (verificar sessão)
 *   3. Usuários (obter paciente e psicóloga)
 *   4. Consultas (listar, buscar, agendar, confirmar, cancelar, reagendar)
 *   5. Agenda e Horários (disponibilidade, bloqueios)
 *   6. Financeiro (receita, pagamentos, transações)
 *   7. Notificações (criar, listar, marcar como lida)
 *   8. Utilitários (formatar data, moeda, tipo de notificação)
 *
 * BANCO DE DADOS: SQLite (arquivo local)
 * ACESSO: Via PDO (PHP Data Objects) com prepared statements
 */

/* ═══════════════════════════════════════════════════════════════
   1. MANUTENÇÃO DO BANCO DE DADOS
═══════════════════════════════════════════════════════════════ */

/*
 * FUNÇÃO: verificar_esquema_banco
 * Garante que o banco de dados possui todas as colunas necessárias.
 * Executada na inicialização do sistema para evitar erros de coluna inexistente.
 *
 * PROBLEMA RESOLVIDO: O SQLite não suporta ALTER TABLE com AFTER ou ENUM,
 * então esta função verifica e adiciona colunas faltantes de forma segura.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 */
function verificar_esquema_banco($pdo) {
    try {
        // Consulta a estrutura da tabela 'notificacoes' no SQLite
        // PRAGMA table_info retorna uma linha para cada coluna da tabela
        $stmt = $pdo->query("PRAGMA table_info(notificacoes)");
        $columns = $stmt->fetchAll();
        $has_destinatario = false;

        // Verifica se a coluna 'destinatario' já existe na tabela
        foreach ($columns as $col) {
            if ($col['name'] === 'destinatario') {
                $has_destinatario = true;
                break;
            }
        }

        // Se a coluna não existir, cria ela com valor padrão 'paciente'
        // Necessário para distinguir notificações do paciente vs da psicóloga
        if (!$has_destinatario) {
            // Criar a coluna se não existir (SQLite não suporta ENUM ou AFTER)
            $pdo->exec("ALTER TABLE notificacoes ADD COLUMN destinatario TEXT DEFAULT 'paciente'");
        }
    } catch (Exception $e) {
        // Registra o erro no log do servidor sem interromper o sistema
        error_log("Erro ao verificar/atualizar esquema: " . $e->getMessage());
    }
}

/* ═══════════════════════════════════════════════════════════════
   2. AUTENTICAÇÃO
═══════════════════════════════════════════════════════════════ */

/*
 * FUNÇÃO: verificar_autenticacao
 * Verifica se o usuário está logado. Se não estiver, redireciona para o login.
 * Deve ser chamada no início de cada página protegida do sistema.
 *
 * @param string $tipo - Tipo de usuário: 'paciente' ou 'psicologa'
 *
 * EXEMPLO DE USO:
 *   verificar_autenticacao('paciente');  // No topo do dashboard_paciente.php
 *   verificar_autenticacao('psicologa'); // No topo do dashboard_psicologa.php
 */
function verificar_autenticacao($tipo = 'paciente') {
    if ($tipo === 'paciente') {
        // Verifica se a sessão do paciente está ativa
        if (!isset($_SESSION['id_paciente'])) {
            header('Location: login.php'); // Redireciona para o login
            exit; // Para a execução do PHP imediatamente
        }
    } elseif ($tipo === 'psicologa') {
        // Verifica se a sessão da psicóloga está ativa
        if (!isset($_SESSION['id_psicologa'])) {
            header('Location: login.php');
            exit;
        }
    }
}

/* ═══════════════════════════════════════════════════════════════
   3. USUÁRIOS
═══════════════════════════════════════════════════════════════ */

/*
 * FUNÇÃO: obter_paciente
 * Busca todos os dados de um paciente pelo seu ID.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id - ID do paciente na tabela 'pacientes'
 * @return array|false - Array com os dados do paciente ou false se não encontrado
 */
function obter_paciente($pdo, $id) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
    // Executa a consulta no banco de dados
    $stmt->execute([$id]);
    return $stmt->fetch(); // Retorna uma única linha como array associativo
}

/*
 * FUNÇÃO: obter_psicologa
 * Busca todos os dados da psicóloga pelo seu ID.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_psicologa - ID da psicóloga na tabela 'psicologa'
 * @return array|false - Array com os dados da psicóloga ou false se não encontrado
 */
function obter_psicologa($pdo, $id_psicologa) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT * FROM psicologa WHERE id_psicologa = ?");
    // Executa a consulta no banco de dados
    $stmt->execute([$id_psicologa]);
    return $stmt->fetch();
}

/* ═══════════════════════════════════════════════════════════════
   4. CONSULTAS
═══════════════════════════════════════════════════════════════ */

/*
 * FUNÇÃO: obter_todas_consultas
 * Retorna todas as consultas do sistema com dados completos (JOIN de 5 tabelas).
 * Usada no dashboard da psicóloga para listar todos os atendimentos.
 *
 * TABELAS RELACIONADAS:
 *   consultas → pacientes (nome e email do paciente)
 *   consultas → especializacoes (nome da especialização)
 *   consultas → horarios (horário da consulta)
 *   consultas → datas_disponiveis (data da consulta)
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return array - Lista de todas as consultas ordenadas por data e horário
 */
function obter_todas_consultas($pdo) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT c.*, p.nome as paciente_nome, p.email as paciente_email, 
               e.nome as especializacao, h.horario, d.data_calendario
        FROM consultas c
        JOIN pacientes p ON c.id_paciente = p.id
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        ORDER BY d.data_calendario ASC, h.horario ASC
    ");
    // Executa a consulta no banco de dados
    $stmt->execute();
    return $stmt->fetchAll(); // Retorna todas as linhas como array de arrays
}

/*
 * FUNÇÃO: obter_consultas_paciente
 * Retorna todas as consultas de um paciente específico.
 * Usada no dashboard do paciente para mostrar seu histórico.
 *
 * NOTA: COALESCE(c.pagamento_status, 'Pendente') retorna 'Pendente'
 * se pagamento_status for NULL no banco.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_paciente - ID do paciente
 * @return array - Lista de consultas do paciente, da mais recente para a mais antiga
 */
function obter_consultas_paciente($pdo, $id_paciente) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT c.*, e.nome as especializacao, e.preco as valor, h.horario, d.data_calendario,
               COALESCE(c.pagamento_status, 'Pendente') as pagamento_status
        FROM consultas c
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        WHERE c.id_paciente = ?
        ORDER BY d.data_calendario DESC, h.horario DESC
    ");
    // Executa a consulta no banco de dados
    $stmt->execute([$id_paciente]);
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: obter_proximas_consultas
 * Retorna as próximas consultas futuras (Pendentes ou Confirmadas).
 * Usada no widget "Próximas Consultas" do dashboard da psicóloga.
 *
 * FILTRO DE DATA: Usa datetime('now', 'localtime') para comparar com
 * a data e hora atual no fuso horário local do servidor.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $limite - Número máximo de consultas a retornar (padrão: 10)
 * @return array - Lista das próximas consultas ordenadas por data/hora
 */
function obter_proximas_consultas($pdo, $limite = 10) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT c.*, p.nome as paciente_nome, e.nome as especializacao, 
               h.horario, d.data_calendario
        FROM consultas c
        JOIN pacientes p ON c.id_paciente = p.id
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        WHERE c.status IN ('Pendente', 'Confirmada')
        AND (d.data_calendario || ' ' || h.horario) >= datetime('now', 'localtime')
        ORDER BY d.data_calendario ASC, h.horario ASC
        LIMIT ?
    ");
    // Executa a consulta no banco de dados
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: obter_consulta
 * Busca os dados completos de uma consulta específica pelo seu ID.
 * Usada em várias operações (confirmar, cancelar, notificar).
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_consulta - ID da consulta
 * @return array|false - Dados da consulta ou false se não encontrada
 */
function obter_consulta($pdo, $id_consulta) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT c.*, p.nome as paciente_nome, e.nome as especializacao, h.horario, d.data_calendario
        FROM consultas c
        JOIN pacientes p ON c.id_paciente = p.id
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        WHERE c.id_consulta = ?
    ");
    // Executa a consulta no banco de dados
    $stmt->execute([$id_consulta]);
    return $stmt->fetch();
}

/*
 * FUNÇÃO: obter_consultas_por_data
 * Retorna todas as consultas de uma data específica.
 * Usada para verificar a agenda de um dia.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param string $data - Data no formato 'Y-m-d' (ex: '2025-06-15')
 * @return array - Lista de consultas da data, ordenadas por horário
 */
function obter_consultas_por_data($pdo, $data) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT c.*, p.nome as paciente_nome, e.nome as especializacao, h.horario, d.data_calendario
        FROM consultas c
        JOIN pacientes p ON c.id_paciente = p.id
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        JOIN horarios h ON c.id_horario = h.id_horario
        JOIN datas_disponiveis d ON c.id_data = d.id_data
        WHERE d.data_calendario = ?
        ORDER BY h.horario ASC
    ");
    // Executa a consulta no banco de dados
    $stmt->execute([$data]);
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: obter_total_consultas
 * Conta o total de consultas não canceladas no sistema.
 * Usada nos cards de métricas do dashboard da psicóloga.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return int - Número total de consultas ativas
 */
function obter_total_consultas($pdo) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM consultas WHERE status != 'Cancelada'");
    // Executa a consulta no banco de dados
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0; // Retorna 0 se o resultado for NULL
}

/*
 * FUNÇÃO: obter_total_pacientes
 * Conta o total de pacientes cadastrados no sistema.
 * Usada nos cards de métricas do dashboard da psicóloga.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return int - Número total de pacientes
 */
function obter_total_pacientes($pdo) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pacientes");
    // Executa a consulta no banco de dados
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

/*
 * FUNÇÃO: agendar_consulta
 * Cria um novo agendamento de consulta no sistema.
 * Usa transação para garantir consistência dos dados.
 *
 * LÓGICA:
 *   1. Busca o preço da especialização
 *   2. Verifica se já existe uma consulta naquele horário/data
 *      - Se existir e for 'Cancelada': reutiliza o registro (UPDATE)
 *      - Se existir e não for cancelada: retorna false (horário ocupado)
 *      - Se não existir: cria novo registro (INSERT)
 *   3. Cria notificações para o paciente e para a psicóloga
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_paciente - ID do paciente
 * @param int $id_especializacao - ID da especialização escolhida
 * @param int $id_horario - ID do horário escolhido
 * @param int $id_data - ID da data escolhida
 * @param string $modalidade - 'Online' ou 'Presencial'
 * @return bool - true se agendado com sucesso, false se o horário estiver ocupado
 */
function agendar_consulta($pdo, $id_paciente, $id_especializacao, $id_horario, $id_data, $modalidade = 'Online') {
    // Busca o preço e nome da especialização para salvar na consulta
    $stmt_preco = $pdo->prepare("SELECT preco, nome FROM especializacoes WHERE id_especializacao = ?");
    $stmt_preco->execute([$id_especializacao]);
    $especializacao = $stmt_preco->fetch();
    $valor = $especializacao['preco'] ?? 0;
    $nome_espec = $especializacao['nome'] ?? 'Consulta';

    try {
        // Inicia uma transação: todas as operações abaixo são executadas juntas
        // Se qualquer uma falhar, todas são desfeitas (rollBack)
        $pdo->beginTransaction();

        // Verifica se já existe uma consulta naquele horário e data
        $stmt_check = $pdo->prepare("SELECT id_consulta, status FROM consultas WHERE id_data = ? AND id_horario = ?");
        $stmt_check->execute([$id_data, $id_horario]);
        $existing = $stmt_check->fetch();

        if ($existing) {
            if ($existing['status'] !== 'Cancelada') {
                // Horário ocupado por consulta ativa: cancela a transação e retorna false
                $pdo->rollBack();
                return false;
            }
            // Horário estava cancelado: reutiliza o registro existente com UPDATE
            // Usa prepared statement para evitar SQL Injection
            $stmt = $pdo->prepare("UPDATE consultas SET id_paciente = ?, id_especializacao = ?, modalidade = ?, valor = ?, status = 'Pendente', pagamento_status = 'Pendente' WHERE id_consulta = ?");
            $sucesso = // Executa a consulta no banco de dados
            $stmt->execute([$id_paciente, $id_especializacao, $modalidade, $valor, $existing['id_consulta']]);
            $id_consulta = $existing['id_consulta'];
        } else {
            // Horário livre: cria um novo registro de consulta
            // Usa prepared statement para evitar SQL Injection
            $stmt = $pdo->prepare("
                INSERT INTO consultas (id_paciente, id_especializacao, id_horario, id_data, modalidade, valor, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Pendente')
            ");
            $sucesso = // Executa a consulta no banco de dados
            $stmt->execute([$id_paciente, $id_especializacao, $id_horario, $id_data, $modalidade, $valor]);
            if ($sucesso) {
                $id_consulta = $pdo->lastInsertId(); // Obtém o ID do registro recém-criado
            }
        }

        $pdo->commit(); // Confirma todas as operações da transação
    } catch (PDOException $e) {
        $pdo->rollBack(); // Desfaz todas as operações em caso de erro
        error_log("Erro ao agendar consulta: " . $e->getMessage());
        return false;
    }

    // Se o agendamento foi bem-sucedido, cria as notificações
    if ($sucesso) {
        $id_psicologa = 1; // ID fixo da psicóloga (sistema com uma única psicóloga)
        $paciente = obter_paciente($pdo, $id_paciente);
        $nome_paciente = $paciente['nome'] ?? 'Paciente';

        // Busca a data e horário formatados para incluir nas notificações
        $stmt_dh = $pdo->prepare("SELECT d.data_calendario, h.horario FROM datas_disponiveis d, horarios h WHERE d.id_data = ? AND h.id_horario = ?");
        $stmt_dh->execute([$id_data, $id_horario]);
        $dh = $stmt_dh->fetch();
        $data_fmt = $dh ? date('d/m/Y', strtotime($dh['data_calendario'])) : '';
        $hora_fmt = $dh ? substr($dh['horario'], 0, 5) : ''; // Pega apenas HH:MM

        // Notifica o paciente: confirmação do agendamento
        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'agendamento', "Voce agendou uma consulta de $nome_espec para $data_fmt as $hora_fmt. Aguarde a confirmacao.", 'paciente');

        // Notifica a psicóloga: novo agendamento aguardando confirmação
        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'nova_consulta', "O(a) paciente $nome_paciente agendou uma consulta de $nome_espec para $data_fmt as $hora_fmt. Confirme ou cancele.", 'psicologa');
    }

    return $sucesso;
}

/*
 * FUNÇÃO: confirmar_consulta
 * Muda o status de uma consulta de 'Pendente' para 'Confirmada'.
 * Chamada pela psicóloga ao confirmar um agendamento.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_consulta - ID da consulta a confirmar
 * @param int $id_paciente - ID do paciente (para enviar a notificação)
 * @return bool - true se confirmado com sucesso
 */
function confirmar_consulta($pdo, $id_consulta, $id_paciente) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("UPDATE consultas SET status = 'Confirmada' WHERE id_consulta = ?");
    $sucesso = // Executa a consulta no banco de dados
    $stmt->execute([$id_consulta]);
    
    // Se confirmou com sucesso, notifica o paciente
    if ($sucesso) {
        $id_psicologa = 1;
        $consulta = obter_consulta($pdo, $id_consulta);
        $data_fmt = $consulta ? date('d/m/Y', strtotime($consulta['data_calendario'])) : '';
        $hora_fmt = $consulta ? substr($consulta['horario'], 0, 5) : '';

        // Envia notificação ao paciente informando a confirmação
        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'confirmacao', "Sua consulta do dia $data_fmt as $hora_fmt foi confirmada pela psicologa.", 'paciente');
    }
    
    return $sucesso;
}

/*
 * FUNÇÃO: cancelar_consulta
 * Cancela uma consulta e registra o cancelamento no histórico.
 * Pode ser chamada pelo paciente ou pela psicóloga.
 *
 * AÇÕES REALIZADAS:
 *   1. Atualiza o status da consulta para 'Cancelada'
 *   2. Insere um registro na tabela 'consultas_canceladas' (histórico)
 *   3. Envia notificações para paciente e psicóloga (se solicitado)
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_consulta - ID da consulta a cancelar
 * @param int $id_paciente - ID do paciente
 * @param int $id_psicologa - ID da psicóloga
 * @param bool $notificar_psicologa - Se deve enviar notificação para a psicóloga
 * @param string|null $motivo_cancelamento - Motivo do cancelamento (opcional)
 * @param string $cancelado_por - 'paciente' ou 'psicologa'
 * @return bool - true se cancelado com sucesso
 */
function cancelar_consulta($pdo, $id_consulta, $id_paciente, $id_psicologa, $notificar_psicologa = true, $motivo_cancelamento = null, $cancelado_por = 'paciente') {
    // Atualiza o status da consulta para 'Cancelada'
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("UPDATE consultas SET status = 'Cancelada' WHERE id_consulta = ?");
    $sucesso = // Executa a consulta no banco de dados
    $stmt->execute([$id_consulta]);

    if ($sucesso) {
        $consulta = obter_consulta($pdo, $id_consulta);
        $data_fmt = $consulta ? date('d/m/Y', strtotime($consulta['data_calendario'])) : '';
        $hora_fmt = $consulta ? substr($consulta['horario'], 0, 5) : '';

        // Registra o cancelamento na tabela de histórico (consultas_canceladas)
        // Isso permite auditoria e relatórios de cancelamentos
        $stmt_ins = $pdo->prepare("INSERT INTO consultas_canceladas (id_consulta, id_paciente, id_especializacao, data_consulta, horario_consulta, modalidade, valor, motivo_cancelamento, cancelado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ins->execute([
            $id_consulta,
            $consulta['id_paciente'],
            $consulta['id_especializacao'],
            $consulta['data_calendario'],
            $consulta['horario'],
            $consulta['modalidade'],
            $consulta['valor'],
            $motivo_cancelamento,
            $cancelado_por
        ]);

        // Envia notificações se solicitado
        if ($notificar_psicologa) {
            $paciente = obter_paciente($pdo, $id_paciente);
            $nome_paciente = $paciente['nome'] ?? 'Paciente';
            // Notifica a psicóloga sobre o cancelamento
            criar_notificacao($pdo, $id_paciente, $id_psicologa, 'cancelamento', "A consulta do(a) $nome_paciente do dia $data_fmt as $hora_fmt foi cancelada.", 'psicologa');
            // Notifica o paciente sobre o cancelamento
            criar_notificacao($pdo, $id_paciente, $id_psicologa, 'cancelamento', "Sua consulta do dia $data_fmt as $hora_fmt foi cancelada.", 'paciente');
        }
    }

    return $sucesso;
}

/*
 * FUNÇÃO: reagendar_consulta
 * Muda a data e/ou horário de uma consulta existente.
 * Verifica se a nova data está disponível antes de reagendar.
 *
 * VALIDAÇÕES:
 *   - A consulta deve pertencer ao paciente informado
 *   - A nova data deve existir e estar com status 'Disponivel'
 *   - O novo horário deve existir e estar ativo
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_consulta - ID da consulta a reagendar
 * @param int $id_paciente - ID do paciente (segurança: só pode reagendar a própria consulta)
 * @param string $nova_data - Nova data no formato 'Y-m-d'
 * @param int $novo_horario - ID do novo horário
 * @return bool - true se reagendado com sucesso
 */
function reagendar_consulta($pdo, $id_consulta, $id_paciente, $nova_data, $novo_horario) {
    // Busca a consulta e verifica se pertence ao paciente
    $consulta = obter_consulta($pdo, $id_consulta);
    if (!$consulta || $consulta['id_paciente'] != $id_paciente) return false;

    // Guarda os dados antigos (para possível uso em notificações)
    $data_antiga = $consulta['data_calendario'];
    $hora_antiga = $consulta['horario'];

    // Verifica se a nova data existe e está disponível no sistema
    $stmt_data = $pdo->prepare("SELECT id_data FROM datas_disponiveis WHERE data_calendario = ? AND status_dia = 'Disponivel'");
    $stmt_data->execute([$nova_data]);
    $id_data = $stmt_data->fetchColumn();
    if (!$id_data) return false; // Data não encontrada ou indisponível

    // Verifica se o novo horário existe e está ativo
    $stmt_hor = $pdo->prepare("SELECT id_horario FROM horarios WHERE id_horario = ? AND ativo = 1");
    $stmt_hor->execute([$novo_horario]);
    if (!$stmt_hor->fetchColumn()) return false; // Horário não encontrado ou inativo

    // Atualiza a consulta com a nova data e horário
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("UPDATE consultas SET id_data = ?, id_horario = ? WHERE id_consulta = ? AND id_paciente = ?");
    $sucesso = // Executa a consulta no banco de dados
    $stmt->execute([$id_data, $novo_horario, $id_consulta, $id_paciente]);

    // Se reagendou com sucesso, notifica o paciente
    if ($sucesso) {
        $id_psicologa = 1;
        $paciente = obter_paciente($pdo, $id_paciente);
        $nome = $paciente['nome'] ?? 'Paciente';
        $data_fmt = date('d/m/Y', strtotime($nova_data));

        // Busca o texto do novo horário para incluir na notificação
        $stmt_h = $pdo->prepare("SELECT horario FROM horarios WHERE id_horario = ?");
        $stmt_h->execute([$novo_horario]);
        $nova_hora = $stmt_h->fetchColumn();
        $hora_fmt = $nova_hora ? substr($nova_hora, 0, 5) : '';

        // Notifica o paciente sobre o reagendamento
        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'reagendamento',
            "Sua consulta foi reagendada para $data_fmt as $hora_fmt.",
            'paciente');
    }

    return $sucesso;
}

/*
 * FUNÇÃO: consulta_pode_ser_cancelada_pelo_paciente
 * Verifica se o paciente ainda pode cancelar uma consulta.
 * Regra de negócio: só pode cancelar com pelo menos 24 horas de antecedência.
 *
 * @param array $consulta - Array com os dados da consulta (data_calendario e horario)
 * @return bool - true se pode cancelar, false se já passou das 24h
 */
function consulta_pode_ser_cancelada_pelo_paciente($consulta) {
    // Converte a data e hora da consulta para timestamp Unix
    $data_hora_consulta = strtotime($consulta['data_calendario'] . ' ' . $consulta['horario']);
    $agora = time(); // Timestamp atual
    $vinte_quatro_horas = 24 * 60 * 60; // 24 horas em segundos
    
    // Retorna true se a consulta for daqui a mais de 24 horas
    return ($data_hora_consulta - $agora) >= $vinte_quatro_horas;
}

/* ═══════════════════════════════════════════════════════════════
   5. AGENDA E HORÁRIOS
═══════════════════════════════════════════════════════════════ */

/*
 * FUNÇÃO: obter_horarios_disponiveis
 * Retorna os horários disponíveis para uma data específica.
 * Leva em conta: horários ocupados por consultas E bloqueios de agenda.
 *
 * LÓGICA:
 *   1. Busca a data pelo ID
 *   2. Verifica se há bloqueio de dia inteiro ou férias para essa data
 *      → Se houver, retorna array vazio (nenhum horário disponível)
 *   3. Busca horários ativos que não estão ocupados por consultas
 *   4. Remove os horários com bloqueio específico
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_data - ID da data na tabela 'datas_disponiveis'
 * @return array - Lista de horários disponíveis (pode ser vazia)
 */
function obter_horarios_disponiveis($pdo, $id_data) {
    // Busca a data correspondente ao id_data
    $stmt_data = $pdo->prepare("SELECT data_calendario FROM datas_disponiveis WHERE id_data = ?");
    $stmt_data->execute([$id_data]);
    $data_row = $stmt_data->fetch();
    $data_calendario = $data_row['data_calendario'] ?? null;
    
    // Se a data não existir no banco, retorna array vazio
    if (!$data_calendario) {
        return [];
    }
    
    // Verifica se há bloqueio de dia inteiro ou férias para esta data
    // data_fim NULL significa bloqueio de 1 dia (data_inicio exata)
    // data_fim preenchido significa intervalo [data_inicio, data_fim]
    $stmt_dia_bloqueado = $pdo->prepare("
        SELECT id_bloqueio FROM bloqueios_agenda 
        WHERE tipo IN ('dia_inteiro', 'ferias') 
        AND (
            (data_fim IS NULL AND data_inicio = ?)
            OR 
            (data_fim IS NOT NULL AND data_inicio <= ? AND data_fim >= ?)
        )
        LIMIT 1
    ");
    $stmt_dia_bloqueado->execute([$data_calendario, $data_calendario, $data_calendario]);
    if ($stmt_dia_bloqueado->fetch()) {
        // Dia inteiramente bloqueado: nenhum horário disponível
        return [];
    }
    
    // Busca horários ativos que não estão ocupados por consultas ativas
    // (exclui consultas canceladas, que liberam o horário)
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT h.* FROM horarios h
        WHERE h.ativo = 1 AND h.id_horario NOT IN (
            SELECT id_horario FROM consultas 
            WHERE id_data = ? AND status != 'Cancelada'
        )
        ORDER BY h.horario ASC
    ");
    // Executa a consulta no banco de dados
    $stmt->execute([$id_data]);
    $horarios = $stmt->fetchAll();
    
    // Busca os IDs de horários com bloqueio específico para esta data
    $stmt_bloqueios = $pdo->prepare("
        SELECT id_horario FROM bloqueios_agenda 
        WHERE tipo = 'horario_especifico' 
        AND (
            (data_fim IS NULL AND data_inicio = ?)
            OR 
            (data_fim IS NOT NULL AND data_inicio <= ? AND data_fim >= ?)
        )
    ");
    $stmt_bloqueios->execute([$data_calendario, $data_calendario, $data_calendario]);
    // Retorna apenas os IDs como array simples (PDO::FETCH_COLUMN)
    $bloqueios = $stmt_bloqueios->fetchAll(PDO::FETCH_COLUMN);
    
    // Remove os horários que estão bloqueados especificamente
    // array_filter mantém apenas os horários cujo id_horario NÃO está na lista de bloqueios
    $horarios_filtrados = array_filter($horarios, function($h) use ($bloqueios) {
        return !in_array($h['id_horario'], $bloqueios);
    });
    
    // array_values reindexar o array (remove gaps nos índices após o filter)
    return array_values($horarios_filtrados);
}

/*
 * FUNÇÃO: obter_especializacoes
 * Retorna todas as especializações ativas da psicóloga.
 * Usada para popular o select de especialização no formulário de agendamento.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return array - Lista de especializações ativas, ordenadas por nome
 */
function obter_especializacoes($pdo) {
    $stmt = $pdo->query("SELECT * FROM especializacoes WHERE ativa = 1 ORDER BY nome ASC");
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: obter_datas_disponiveis
 * Retorna todas as datas disponíveis para agendamento.
 * Usada para popular o calendário de agendamento.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return array - Lista de datas disponíveis, ordenadas cronologicamente
 */
function obter_datas_disponiveis($pdo) {
    $stmt = $pdo->query("SELECT * FROM datas_disponiveis WHERE status_dia = 'Disponivel' ORDER BY data_calendario ASC");
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: obter_horarios
 * Retorna todos os horários ativos cadastrados no sistema.
 * Usada para exibir a grade de horários disponíveis.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return array - Lista de horários ativos, ordenados cronologicamente
 */
function obter_horarios($pdo) {
    $stmt = $pdo->query("SELECT * FROM horarios WHERE ativo = 1 ORDER BY horario ASC");
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: obter_bloqueios_agenda
 * Retorna todos os bloqueios de agenda cadastrados.
 * Usada na aba de disponibilidade do dashboard da psicóloga.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return array - Lista de bloqueios com o texto do horário (via LEFT JOIN)
 */
function obter_bloqueios_agenda($pdo) {
    $stmt = $pdo->query("
        SELECT b.*, h.horario as horario_texto
        FROM bloqueios_agenda b
        LEFT JOIN horarios h ON b.id_horario = h.id_horario
        ORDER BY b.data_inicio DESC
    ");
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: criar_bloqueio_agenda
 * Cria um novo bloqueio na agenda da psicóloga.
 * Pode bloquear: um dia inteiro, um horário específico, ou um período de férias.
 *
 * TIPOS DE BLOQUEIO:
 *   - 'dia_inteiro': Bloqueia todos os horários de uma data
 *   - 'horario_especifico': Bloqueia um horário específico em uma data
 *   - 'ferias': Bloqueia um intervalo de datas (data_inicio até data_fim)
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param string $tipo_bloqueio - Tipo do bloqueio (ver tipos acima)
 * @param string $data_inicio - Data de início no formato 'Y-m-d'
 * @param string|null $data_fim - Data de fim (apenas para férias)
 * @param int|null $id_horario - ID do horário (apenas para horario_especifico)
 * @param string|null $horario_fim - Não utilizado (mantido por compatibilidade)
 * @param string|null $motivo - Motivo do bloqueio (opcional)
 * @return bool - true se criado com sucesso
 */
function criar_bloqueio_agenda($pdo, $tipo_bloqueio, $data_inicio, $data_fim = null, $id_horario = null, $horario_fim = null, $motivo = null) {
    try {
        // Valida se o tipo de bloqueio é um dos valores permitidos
        $tipos_validos = ['dia_inteiro', 'horario_especifico', 'ferias'];
        if (!in_array($tipo_bloqueio, $tipos_validos)) {
            error_log('Tipo de bloqueio inválido: ' . $tipo_bloqueio);
            return false;
        }
        
        // Valida o formato da data de início (deve ser YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio)) {
            error_log('Data de início inválida: ' . $data_inicio);
            return false;
        }
        
        // Converte strings vazias para NULL (o banco de dados aceita NULL, não string vazia)
        $data_fim = (empty($data_fim) || $data_fim === '') ? null : $data_fim;
        $id_horario = (empty($id_horario) || $id_horario === '') ? null : intval($id_horario);
        $motivo = (empty($motivo) || $motivo === '') ? null : $motivo;
        
        // Prepara a consulta SQL para interagir com o banco de dados
        $sql = "INSERT INTO bloqueios_agenda (tipo, data_inicio, data_fim, id_horario, motivo) VALUES (?, ?, ?, ?, ?)";
        // Usa prepared statement para evitar SQL Injection
        $stmt = $pdo->prepare($sql);
        
        // Array de parâmetros na mesma ordem dos "?" na query
        $params = [$tipo_bloqueio, $data_inicio, $data_fim, $id_horario, $motivo];
        
        // Log de debug para rastrear a criação de bloqueios
        error_log('Criando bloqueio: tipo=' . $tipo_bloqueio . ', data=' . $data_inicio . ', id_horario=' . ($id_horario ?? 'NULL'));
        
        // Executa a consulta no banco de dados
        $result = // Executa a consulta no banco de dados
        $stmt->execute($params);
        
        if ($result) {
            error_log('Bloqueio criado com sucesso. ID: ' . $pdo->lastInsertId());
            return true;
        } else {
            // Captura e registra o erro do PDO
            $erro = implode(' | ', $stmt->errorInfo());
            error_log('Falha ao executar INSERT. Erro: ' . $erro);
            return false;
        }
        
    } catch (PDOException $e) {
        // Erro de banco de dados (ex: constraint violation)
        error_log('PDOException em criar_bloqueio_agenda: ' . $e->getMessage());
        error_log('SQL State: ' . $e->errorInfo[0]);
        return false;
    } catch (Exception $e) {
        // Qualquer outro erro
        error_log('Exceção em criar_bloqueio_agenda: ' . $e->getMessage());
        return false;
    }
}

/*
 * FUNÇÃO: remover_bloqueio_agenda
 * Remove um bloqueio de agenda pelo seu ID.
 * Chamada quando a psicóloga exclui um bloqueio no dashboard.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_bloqueio - ID do bloqueio a remover
 * @return bool - true se removido com sucesso
 */
function remover_bloqueio_agenda($pdo, $id_bloqueio) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("DELETE FROM bloqueios_agenda WHERE id_bloqueio = ?");
    return // Executa a consulta no banco de dados
    $stmt->execute([$id_bloqueio]);
}

/*
 * FUNÇÃO: atualizar_preco_especializacao
 * Atualiza o preço de uma especialização.
 * Chamada quando a psicóloga edita o valor de um serviço no dashboard.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_especializacao - ID da especialização
 * @param float $novo_preco - Novo preço em reais
 * @return bool - true se atualizado com sucesso
 */
function atualizar_preco_especializacao($pdo, $id_especializacao, $novo_preco) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("UPDATE especializacoes SET preco = ? WHERE id_especializacao = ?");
    return // Executa a consulta no banco de dados
    $stmt->execute([$novo_preco, $id_especializacao]);
}

/* ═══════════════════════════════════════════════════════════════
   6. FINANCEIRO
═══════════════════════════════════════════════════════════════ */

/*
 * FUNÇÃO: obter_receita_mes
 * Calcula a receita total do mês atual.
 * Usada nos cards de métricas financeiras do dashboard.
 *
 * NOTA: strftime('%m', ...) extrai o mês (01-12) da data.
 * COALESCE usa data_pagamento se disponível, senão data_criacao.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return float - Receita total do mês atual (0 se não houver pagamentos)
 */
function obter_receita_mes($pdo) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT SUM(valor) as total FROM pagamentos 
        WHERE status = 'Concluído' 
        AND strftime('%m', COALESCE(data_pagamento, data_criacao)) = strftime('%m', 'now', 'localtime')
        AND strftime('%Y', COALESCE(data_pagamento, data_criacao)) = strftime('%Y', 'now', 'localtime')
    ");
    // Executa a consulta no banco de dados
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0; // Retorna 0 se não houver pagamentos no mês
}

/*
 * FUNÇÃO: obter_receita_ano
 * Calcula a receita total do ano atual.
 * Usada nos cards de métricas financeiras do dashboard.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return float - Receita total do ano atual
 */
function obter_receita_ano($pdo) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT SUM(valor) as total FROM pagamentos 
        WHERE status = 'Concluído' 
        AND strftime('%Y', COALESCE(data_pagamento, data_criacao)) = strftime('%Y', 'now', 'localtime')
    ");
    // Executa a consulta no banco de dados
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

/*
 * FUNÇÃO: obter_receita_mensal_ano
 * Retorna um array com a receita de cada mês do ano atual.
 * Usada para gerar o gráfico de receita mensal no dashboard.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @return array - Array de 12 posições (índice 0 = Janeiro, 11 = Dezembro)
 *                 com o valor total de cada mês (0 se não houver pagamentos)
 */
function obter_receita_mensal_ano($pdo) {
    // Inicializa o array com 12 zeros (um para cada mês)
    $receita = array_fill(0, 12, 0);

    // Busca a receita agrupada por mês
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT strftime('%m', COALESCE(data_pagamento, data_criacao)) as mes, SUM(valor) as total 
        FROM pagamentos 
        WHERE status = 'Concluído' AND strftime('%Y', COALESCE(data_pagamento, data_criacao)) = strftime('%Y', 'now', 'localtime')
        GROUP BY mes
    ");
    // Executa a consulta no banco de dados
    $stmt->execute();
    $resultados = $stmt->fetchAll();

    // Preenche o array com os valores reais (mês '01' → índice 0, etc.)
    foreach ($resultados as $row) {
        $receita[intval($row['mes']) - 1] = floatval($row['total']);
    }
    return $receita;
}

/*
 * FUNÇÃO: obter_transacoes_financeiras
 * Retorna o histórico de transações financeiras com dados completos.
 * Usada na aba financeira do dashboard da psicóloga.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $limite - Número máximo de transações a retornar (padrão: 20)
 * @return array - Lista de transações com nome do paciente e especialização
 */
function obter_transacoes_financeiras($pdo, $limite = 20) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("
        SELECT p.*, pac.nome as paciente_nome, e.nome as especializacao
        FROM pagamentos p
        JOIN pacientes pac ON p.id_paciente = pac.id
        JOIN consultas c ON p.id_consulta = c.id_consulta
        JOIN especializacoes e ON c.id_especializacao = e.id_especializacao
        ORDER BY p.data_criacao DESC
        LIMIT ?
    ");
    // Executa a consulta no banco de dados
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: processar_pagamento
 * Registra um pagamento no sistema e atualiza o status da consulta.
 * Usa transação para garantir que o pagamento e a atualização ocorram juntos.
 *
 * AÇÕES REALIZADAS:
 *   1. Insere um registro na tabela 'pagamentos'
 *   2. Atualiza pagamento_status da consulta para 'Concluído'
 *   3. Envia notificação de confirmação ao paciente
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_consulta - ID da consulta sendo paga
 * @param int $id_paciente - ID do paciente (segurança: só pode pagar a própria consulta)
 * @param string $metodo - Método de pagamento (ex: 'Pix', 'Cartão')
 * @return bool - true se processado com sucesso
 */
function processar_pagamento($pdo, $id_consulta, $id_paciente, $metodo = 'Pix') {
    // Busca o valor da consulta e verifica se pertence ao paciente
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT valor FROM consultas WHERE id_consulta = ? AND id_paciente = ?");
    // Executa a consulta no banco de dados
    $stmt->execute([$id_consulta, $id_paciente]);
    $valor = $stmt->fetchColumn();
    
    // Se não encontrou a consulta ou não pertence ao paciente, retorna false
    if ($valor === false) {
        error_log('processar_pagamento: consulta não encontrada ou não pertence ao paciente. id_consulta=' . $id_consulta . ', id_paciente=' . $id_paciente);
        return false;
    }

    // Inicia transação: pagamento + atualização de status devem ocorrer juntos
    $pdo->beginTransaction();
    try {
        // Insere o registro de pagamento com status 'Concluído' e data atual
        $stmt_pag = $pdo->prepare("
            INSERT INTO pagamentos (id_consulta, id_paciente, valor, metodo_pagamento, status, data_pagamento)
            VALUES (?, ?, ?, ?, 'Concluído', datetime('now', 'localtime'))
        ");
        $stmt_pag->execute([$id_consulta, $id_paciente, $valor, $metodo]);

        // Atualiza o campo pagamento_status da consulta para 'Concluído'
        $stmt_con = $pdo->prepare("UPDATE consultas SET pagamento_status = 'Concluído' WHERE id_consulta = ?");
        $stmt_con->execute([$id_consulta]);

        // Envia notificação de confirmação ao paciente
        $id_psicologa = 1;
        $paciente = obter_paciente($pdo, $id_paciente);
        $nome_paciente = $paciente['nome'] ?? 'Paciente';
        $consulta = obter_consulta($pdo, $id_consulta);
        $data_pag_fmt = $consulta ? date('d/m/Y', strtotime($consulta['data_calendario'])) : '';

        criar_notificacao($pdo, $id_paciente, $id_psicologa, 'pagamento', "Pagamento confirmado com sucesso para a consulta do dia $data_pag_fmt.", 'paciente');

        $pdo->commit(); // Confirma todas as operações
        return true;
    } catch (Exception $e) {
        $pdo->rollBack(); // Desfaz tudo em caso de erro
        return false;
    }
}

/* ═══════════════════════════════════════════════════════════════
   7. NOTIFICAÇÕES
═══════════════════════════════════════════════════════════════ */

/*
 * FUNÇÃO: criar_notificacao
 * Cria uma nova notificação no banco de dados.
 * Chamada por várias outras funções (agendar, confirmar, cancelar, pagar).
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_paciente - ID do paciente relacionado
 * @param int $id_psicologa - ID da psicóloga relacionada
 * @param string $tipo - Tipo da notificação (ex: 'agendamento', 'cancelamento')
 * @param string $mensagem - Texto da notificação
 * @param string $destinatario - 'paciente' ou 'psicologa' (quem vai ver)
 * @return bool - true se criada com sucesso
 */
function criar_notificacao($pdo, $id_paciente, $id_psicologa, $tipo, $mensagem, $destinatario = 'paciente') {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("INSERT INTO notificacoes (id_paciente, id_psicologa, tipo, mensagem, destinatario) VALUES (?, ?, ?, ?, ?)");
    $r = // Executa a consulta no banco de dados
    $stmt->execute([$id_paciente, $id_psicologa, $tipo, $mensagem, $destinatario]);
    if ($r) {
        // Registra no log do servidor para debug (primeiros 80 caracteres da mensagem)
        error_log("[NOTIFICACAO] tipo=$tipo dest=$destinatario msg=" . substr($mensagem, 0, 80));
    }
    return $r;
}

/*
 * FUNÇÃO: criar_notificacao_consulta
 * Cria notificações para AMBOS (paciente e psicóloga) sobre uma consulta.
 * Wrapper de conveniência que chama criar_notificacao() duas vezes.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_consulta - ID da consulta relacionada
 * @param string $tipo - Tipo da notificação
 * @param string $mensagem_paciente - Mensagem para o paciente
 * @param string $mensagem_psicologa - Mensagem para a psicóloga
 * @return bool - true se ambas foram criadas com sucesso
 */
function criar_notificacao_consulta($pdo, $id_consulta, $tipo, $mensagem_paciente, $mensagem_psicologa) {
    $consulta = obter_consulta($pdo, $id_consulta);
    if (!$consulta) return false;
    $id_paciente = $consulta['id_paciente'];
    $id_psicologa = 1; // ID fixo da psicóloga
    // Cria notificação para o paciente
    $r1 = criar_notificacao($pdo, $id_paciente, $id_psicologa, $tipo, $mensagem_paciente, 'paciente');
    // Cria notificação para a psicóloga
    $r2 = criar_notificacao($pdo, $id_paciente, $id_psicologa, $tipo, $mensagem_psicologa, 'psicologa');
    return $r1 && $r2; // Retorna true apenas se ambas foram criadas
}

/*
 * FUNÇÃO: obter_notificacoes_psicologa
 * Retorna as notificações destinadas à psicóloga.
 * Usada no widget de notificações do dashboard da psicóloga.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_psicologa - ID da psicóloga
 * @param int $limite - Número máximo de notificações (padrão: 10)
 * @return array - Lista de notificações, da mais recente para a mais antiga
 */
function obter_notificacoes_psicologa($pdo, $id_psicologa, $limite = 10) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' ORDER BY data_criacao DESC LIMIT ?");
    // Executa a consulta no banco de dados
    $stmt->execute([$id_psicologa, $limite]);
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: obter_notificacoes_paciente
 * Retorna as notificações destinadas ao paciente.
 * Usada no widget de notificações do dashboard do paciente.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_paciente - ID do paciente
 * @param int $limite - Número máximo de notificações (padrão: 10)
 * @return array - Lista de notificações, da mais recente para a mais antiga
 */
function obter_notificacoes_paciente($pdo, $id_paciente, $limite = 10) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' ORDER BY data_criacao DESC LIMIT ?");
    // Executa a consulta no banco de dados
    $stmt->execute([$id_paciente, $limite]);
    return $stmt->fetchAll();
}

/*
 * FUNÇÃO: contar_notificacoes_nao_lidas
 * Conta as notificações não lidas da psicóloga.
 * Usada para exibir o badge de contagem no botão de notificações.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int|null $id_psicologa - ID da psicóloga (retorna 0 se null)
 * @return int - Número de notificações não lidas
 */
function contar_notificacoes_nao_lidas($pdo, $id_psicologa = null) {
    if ($id_psicologa === null) {
        return 0; // Sem ID: retorna 0 por segurança
    }
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_psicologa = ? AND destinatario = 'psicologa' AND lida = 0");
    // Executa a consulta no banco de dados
    $stmt->execute([$id_psicologa]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

/*
 * FUNÇÃO: contar_notificacoes_nao_lidas_paciente
 * Conta as notificações não lidas do paciente.
 * Usada para exibir o badge de contagem no dashboard do paciente.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_paciente - ID do paciente
 * @return int - Número de notificações não lidas
 */
function contar_notificacoes_nao_lidas_paciente($pdo, $id_paciente) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_paciente = ? AND destinatario = 'paciente' AND lida = 0");
    // Executa a consulta no banco de dados
    $stmt->execute([$id_paciente]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

/*
 * FUNÇÃO: marcar_notificacao_lida
 * Marca uma notificação como lida (lida = 1).
 * Chamada quando o usuário clica em uma notificação.
 *
 * @param PDO $pdo - Conexão com o banco de dados
 * @param int $id_notificacao - ID da notificação a marcar
 * @return bool - true se marcada com sucesso
 */
function marcar_notificacao_lida($pdo, $id_notificacao) {
    // Usa prepared statement para evitar SQL Injection
    $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id_notificacao = ?");
    return // Executa a consulta no banco de dados
    $stmt->execute([$id_notificacao]);
}

/* ═══════════════════════════════════════════════════════════════
   8. UTILITÁRIOS
═══════════════════════════════════════════════════════════════ */

/*
 * FUNÇÃO: formatar_data
 * Converte uma data do formato banco (Y-m-d) para o formato brasileiro (d/m/Y).
 *
 * @param string $data - Data no formato 'Y-m-d' (ex: '2025-06-15')
 * @return string - Data formatada (ex: '15/06/2025') ou a string original se inválida
 */
function formatar_data($data) {
    $date = DateTime::createFromFormat('Y-m-d', $data);
    return $date ? $date->format('d/m/Y') : $data; // Retorna o original se falhar
}

/*
 * FUNÇÃO: formatar_moeda
 * Formata um valor numérico como moeda brasileira (R$).
 *
 * @param float $valor - Valor numérico (ex: 150.00)
 * @return string - Valor formatado (ex: 'R$ 150,00')
 */
function formatar_moeda($valor) {
    // number_format(valor, casas_decimais, separador_decimal, separador_milhar)
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/*
 * FUNÇÃO: formatar_data_hora
 * Combina e formata data e hora para exibição amigável.
 *
 * @param string $data - Data no formato 'Y-m-d'
 * @param string $hora - Hora no formato 'HH:MM:SS' ou 'HH:MM'
 * @return string - Data e hora formatadas (ex: '15/06/2025 às 14:30')
 */
function formatar_data_hora($data, $hora) {
    $date = DateTime::createFromFormat('Y-m-d', $data);
    $data_formatada = $date ? $date->format('d/m/Y') : $data;
    $hora_formatada = substr($hora, 0, 5); // Pega apenas HH:MM (sem segundos)
    return $data_formatada . ' às ' . $hora_formatada;
}

/*
 * FUNÇÃO: formatar_tipo_notificacao
 * Converte o tipo interno de notificação para um texto legível pelo usuário.
 *
 * @param string $tipo - Tipo interno (ex: 'nova_consulta', 'cancelamento')
 * @return string - Texto amigável (ex: 'Nova Consulta', 'Consulta Cancelada')
 */
function formatar_tipo_notificacao($tipo) {
    // Mapeamento de tipos internos para textos amigáveis
    $tipos = [
        'nova_consulta'       => 'Nova Consulta',
        'agendamento'         => 'Consulta Agendada',
        'confirmacao'         => 'Consulta Confirmada',
        'cancelamento'        => 'Consulta Cancelada',
        'reagendamento'       => 'Consulta Reagendada',
        'pagamento'           => 'Pagamento Confirmado',
        'pagamento_aprovado'  => 'Pagamento Aprovado',
        'pagamento_recusado'  => 'Pagamento Recusado',
        'consulta_concluida'  => 'Consulta Concluida',
        'alteracao_data'      => 'Alteracao de Data',
        'alteracao_horario'   => 'Alteracao de Horario',
        'lembrete'            => 'Lembrete',
        'comentario_psicologa'=> 'Recado da Psicologa'
    ];
    // Retorna o texto mapeado ou 'Notificacao' como fallback
    return $tipos[$tipo] ?? 'Notificacao';
}
