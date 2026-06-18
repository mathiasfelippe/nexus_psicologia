-- ============================================================
--  NEXUS — Estrutura completa do banco de dados SQLite
--
--  DESCRIÇÃO:
--    Este arquivo define todas as tabelas, relacionamentos,
--    triggers e dados iniciais do sistema Nexus Psicologia.
--
--  BANCO DE DADOS: SQLite (arquivo nexus.sqlite)
--
--  DIAGRAMA DE RELACIONAMENTOS:
--    psicologa (1) ──── (N) notificacoes
--    pacientes (1) ──── (N) consultas
--    pacientes (1) ──── (N) pagamentos
--    pacientes (1) ──── (N) notificacoes
--    pacientes (1) ──── (N) consultas_canceladas
--    especializacoes (1) ── (N) consultas
--    horarios (1) ──── (N) consultas
--    datas_disponiveis (1) ─ (N) consultas
--    consultas (1) ──── (N) pagamentos
--    consultas (1) ──── (N) consultas_canceladas
--
--  OBSERVAÇÕES SOBRE O SQLite:
--    - Não suporta ON UPDATE CURRENT_TIMESTAMP nativamente
--      (simulado via triggers)
--    - DECIMAL(10,2) é armazenado como REAL internamente
--    - AUTOINCREMENT garante IDs únicos e crescentes
-- ============================================================

-- ════════════════════════════════════════════════════════════
-- TABELA 1: psicologa
-- Armazena os dados da psicóloga (único usuário administrador)
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS psicologa (
    id_psicologa   INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único auto-incrementado
    nome           TEXT    NOT NULL,                  -- Nome completo da psicóloga
    email          TEXT    NOT NULL,                  -- Email de login (único)
    senha          TEXT    NOT NULL,                  -- Senha criptografada com bcrypt (password_hash)
    telefone       TEXT,                              -- Telefone de contato (opcional)
    crp            TEXT,                              -- Número do CRP (Conselho Regional de Psicologia)
    bio            TEXT,                              -- Biografia/apresentação exibida no perfil
    foto_perfil    TEXT,                              -- Caminho do arquivo de foto (ex: uploads/fotos/abc.png)
    data_criacao   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Data de cadastro (automática)
    UNIQUE (email)                                    -- Garante que não existam dois cadastros com o mesmo email
);

-- Dados iniciais: cadastro padrão da psicóloga
-- Senha: 'password' (hash bcrypt gerado com password_hash)
-- INSERT OR IGNORE: não insere se já existir (evita duplicatas ao re-executar o script)
INSERT OR IGNORE INTO psicologa (nome, email, senha, telefone, bio) VALUES (
    'Juliana Moura',
    'admin@nexus.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    '(11) 98765-4321',
    'Psicóloga especializada em terapia clínica e relacionamentos'
);

-- ════════════════════════════════════════════════════════════
-- TABELA 2: pacientes
-- Armazena os dados dos pacientes cadastrados no sistema
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS pacientes (
    id              INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único auto-incrementado
    nome            TEXT    NOT NULL,                  -- Nome completo do paciente
    email           TEXT    NOT NULL,                  -- Email de login (único)
    senha           TEXT    NOT NULL,                  -- Senha criptografada com bcrypt
    data_nascimento TEXT,                              -- Data de nascimento (formato YYYY-MM-DD)
    telefone        TEXT,                              -- Telefone de contato
    cpf             TEXT,                              -- CPF do paciente (único, opcional)
    endereco        TEXT,                              -- Endereço completo
    data_criacao    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Data de cadastro (automática)
    UNIQUE (email),                                    -- Email único por paciente
    UNIQUE (cpf)                                       -- CPF único por paciente
);

-- ════════════════════════════════════════════════════════════
-- TABELA 3: especializacoes
-- Armazena as especialidades/modalidades de atendimento
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS especializacoes (
    id_especializacao   INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único
    nome                TEXT    NOT NULL,                  -- Nome da especialização (ex: 'Clínica')
    descricao           TEXT,                              -- Descrição exibida ao paciente
    preco               DECIMAL(10,2) NOT NULL DEFAULT 150.00, -- Valor da consulta (padrão R$150)
    ativa               INTEGER       NOT NULL DEFAULT 1,  -- 1=ativa, 0=inativa (soft delete)
    UNIQUE (nome)                                          -- Nome único por especialização
);

-- Dados iniciais: especializações padrão do sistema
INSERT OR IGNORE INTO especializacoes (nome, descricao, preco) VALUES
    ('Clínica',         'Psicoterapia para equilíbrio emocional e bem-estar',       150.00),
    ('Infantil',        'Auxilia no desenvolvimento emocional e social na infância', 150.00),
    ('Casal',           'Terapia para melhorar relacionamentos',                     180.00),
    ('Neuropsicologia', 'Avaliação e reabilitação neuropsicológica',                 200.00);

-- ════════════════════════════════════════════════════════════
-- TABELA 4: horarios
-- Define os horários fixos de atendimento disponíveis
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS horarios (
    id_horario  INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único
    horario     TEXT    NOT NULL,                  -- Horário no formato HH:MM (ex: '09:00')
    turno       TEXT    NOT NULL,                  -- 'manha' (09:00-12:00) ou 'tarde' (15:00-18:00)
    ativo       INTEGER NOT NULL DEFAULT 1,        -- 1=ativo, 0=inativo
    UNIQUE (horario)                               -- Horário único (sem duplicatas)
);

-- Dados iniciais: horários de atendimento (09:00 às 18:00)
INSERT OR IGNORE INTO horarios (horario, turno) VALUES
    ('09:00', 'manha'),
    ('10:00', 'manha'),
    ('11:00', 'manha'),
    ('12:00', 'manha'),
    ('15:00', 'tarde'),
    ('16:00', 'tarde'),
    ('17:00', 'tarde'),
    ('18:00', 'tarde');

-- ════════════════════════════════════════════════════════════
-- TABELA 5: datas_disponiveis
-- Controla quais datas estão abertas para agendamento
-- Populada automaticamente por config/gerar_datas.php
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS datas_disponiveis (
    id_data         INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único
    data_calendario TEXT    NOT NULL,                  -- Data no formato YYYY-MM-DD
    status_dia      TEXT    NOT NULL DEFAULT 'Disponivel', -- 'Disponivel' ou 'Indisponivel'
    UNIQUE (data_calendario)                           -- Cada data aparece apenas uma vez
);

-- ════════════════════════════════════════════════════════════
-- TABELA 6: bloqueios_agenda
-- Registra bloqueios manuais na agenda da psicóloga
-- (dias inteiros, horários específicos ou períodos de férias)
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS bloqueios_agenda (
    id_bloqueio  INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único
    tipo         TEXT    NOT NULL,                  -- 'dia_inteiro', 'horario_especifico' ou 'ferias'
    data_inicio  TEXT    NOT NULL,                  -- Data de início do bloqueio (YYYY-MM-DD)
    data_fim     TEXT,                              -- Data de fim (apenas para bloqueios de período/férias)
    id_horario   INTEGER,                           -- Referência ao horário bloqueado (apenas para 'horario_especifico')
    motivo       TEXT,                              -- Motivo do bloqueio (opcional)
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Data de criação do bloqueio
    -- Se o horário referenciado for excluído, o campo id_horario vira NULL (não exclui o bloqueio)
    FOREIGN KEY (id_horario) REFERENCES horarios (id_horario) ON DELETE SET NULL
);

-- ════════════════════════════════════════════════════════════
-- TABELA 7: consultas
-- Tabela principal: registra todas as consultas agendadas
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS consultas (
    id_consulta         INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único da consulta
    id_paciente         INTEGER NOT NULL,                  -- Referência ao paciente
    id_especializacao   INTEGER NOT NULL,                  -- Referência à especialização
    id_horario          INTEGER NOT NULL,                  -- Referência ao horário
    id_data             INTEGER NOT NULL,                  -- Referência à data disponível
    modalidade          TEXT    NOT NULL,                  -- 'Presencial' ou 'Online'
    status              TEXT    NOT NULL DEFAULT 'Pendente', -- 'Pendente', 'Confirmada', 'Remarcada' ou 'Cancelada'
    valor               DECIMAL(10,2) NOT NULL,            -- Valor cobrado (copiado da especialização no momento do agendamento)
    pagamento_status    TEXT    NOT NULL DEFAULT 'Pendente', -- 'Pendente', 'Concluído' ou 'Reembolsado'
    observacoes         TEXT,                              -- Observações do paciente ao agendar
    data_criacao        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Data do agendamento
    data_atualizacao    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Última atualização (mantida pelo trigger)
    -- Garante que não haja duas consultas no mesmo horário e data (sem conflito de agenda)
    UNIQUE (id_data, id_horario),
    FOREIGN KEY (id_paciente)       REFERENCES pacientes       (id)               ON DELETE CASCADE,
    FOREIGN KEY (id_especializacao) REFERENCES especializacoes (id_especializacao),
    FOREIGN KEY (id_horario)        REFERENCES horarios        (id_horario),
    FOREIGN KEY (id_data)           REFERENCES datas_disponiveis (id_data)
);

-- Trigger: atualiza data_atualizacao automaticamente ao modificar uma consulta
-- SQLite não suporta ON UPDATE CURRENT_TIMESTAMP, então usa-se um trigger AFTER UPDATE
CREATE TRIGGER IF NOT EXISTS trg_consultas_update AFTER UPDATE ON consultas
BEGIN
    UPDATE consultas SET data_atualizacao = CURRENT_TIMESTAMP WHERE id_consulta = OLD.id_consulta;
END;

-- ════════════════════════════════════════════════════════════
-- TABELA 8: consultas_canceladas
-- Histórico de consultas canceladas (audit log)
-- Registra os dados no momento do cancelamento para fins de auditoria
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS consultas_canceladas (
    id_cancelamento     INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único do cancelamento
    id_consulta         INTEGER NOT NULL,                  -- Referência à consulta original
    id_paciente         INTEGER NOT NULL,                  -- Referência ao paciente
    id_especializacao   INTEGER NOT NULL,                  -- Referência à especialização
    data_consulta       TEXT    NOT NULL,                  -- Data da consulta (cópia no momento do cancelamento)
    horario_consulta    TEXT    NOT NULL,                  -- Horário da consulta (cópia)
    modalidade          TEXT    NOT NULL,                  -- Modalidade (cópia)
    valor               DECIMAL(10,2) NOT NULL,            -- Valor (cópia)
    motivo_cancelamento TEXT,                              -- Motivo informado ao cancelar
    cancelado_por       TEXT    NOT NULL,                  -- 'paciente' ou 'psicologa'
    data_cancelamento   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Quando foi cancelado
    -- ON DELETE CASCADE: se a consulta for excluída, o registro de cancelamento também é excluído
    FOREIGN KEY (id_consulta)       REFERENCES consultas       (id_consulta)      ON DELETE CASCADE,
    FOREIGN KEY (id_paciente)       REFERENCES pacientes       (id)               ON DELETE CASCADE,
    FOREIGN KEY (id_especializacao) REFERENCES especializacoes (id_especializacao)
);

-- ════════════════════════════════════════════════════════════
-- TABELA 9: pagamentos
-- Registra os pagamentos associados às consultas
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS pagamentos (
    id_pagamento        INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único do pagamento
    id_consulta         INTEGER NOT NULL,                  -- Referência à consulta paga
    id_paciente         INTEGER NOT NULL,                  -- Referência ao paciente
    valor               DECIMAL(10,2) NOT NULL,            -- Valor pago
    metodo_pagamento    TEXT    NOT NULL,                  -- 'Pix', 'Cartao', 'Boleto' ou 'Dinheiro'
    status              TEXT    NOT NULL DEFAULT 'Pendente', -- 'Pendente', 'Concluído', 'Falha' ou 'Reembolsado'
    referencia_externa  TEXT,                              -- Código externo (ex: ID de transação Pix)
    data_pagamento      DATETIME,                          -- Data em que o pagamento foi confirmado (NULL se pendente)
    data_criacao        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Mantida pelo trigger
    -- ON DELETE CASCADE: ao excluir uma consulta, os pagamentos associados também são excluídos
    FOREIGN KEY (id_consulta)  REFERENCES consultas (id_consulta) ON DELETE CASCADE,
    FOREIGN KEY (id_paciente)  REFERENCES pacientes (id)          ON DELETE CASCADE
);

-- Trigger: atualiza data_atualizacao automaticamente ao modificar um pagamento
CREATE TRIGGER IF NOT EXISTS trg_pagamentos_update AFTER UPDATE ON pagamentos
BEGIN
    UPDATE pagamentos SET data_atualizacao = CURRENT_TIMESTAMP WHERE id_pagamento = OLD.id_pagamento;
END;

-- ════════════════════════════════════════════════════════════
-- TABELA 10: notificacoes
-- Armazena notificações para pacientes e para a psicóloga
-- Usada para alertas de agendamento, confirmação, cancelamento, etc.
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS notificacoes (
    id_notificacao  INTEGER PRIMARY KEY AUTOINCREMENT, -- ID único
    id_paciente     INTEGER,                           -- Referência ao paciente (NULL se destinatário for a psicóloga)
    id_psicologa    INTEGER,                           -- Referência à psicóloga (NULL se destinatário for o paciente)
    tipo            TEXT    NOT NULL,                  -- Tipo da notificação (ex: 'agendamento', 'cancelamento')
    mensagem        TEXT    NOT NULL,                  -- Texto da notificação exibido ao usuário
    lida            INTEGER NOT NULL DEFAULT 0,        -- 0=não lida, 1=lida
    destinatario    TEXT,                              -- 'paciente' ou 'psicologa' (para filtro na API)
    data_criacao    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Data de criação
    -- ON DELETE CASCADE: ao excluir paciente ou psicóloga, as notificações são excluídas
    FOREIGN KEY (id_paciente)  REFERENCES pacientes (id)             ON DELETE CASCADE,
    FOREIGN KEY (id_psicologa) REFERENCES psicologa (id_psicologa)   ON DELETE CASCADE
);
