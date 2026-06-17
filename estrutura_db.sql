-- ============================================================
--  NEXUS — Estrutura completa do banco de dados SQLite
-- ============================================================

-- 1. PSICOLOGA
CREATE TABLE IF NOT EXISTS psicologa (
    id_psicologa   INTEGER PRIMARY KEY AUTOINCREMENT,
    nome           TEXT    NOT NULL,
    email          TEXT    NOT NULL,
    senha          TEXT    NOT NULL,
    telefone       TEXT,
    crp            TEXT,
    bio            TEXT,
    foto_perfil    TEXT,
    data_criacao   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (email)
);

INSERT OR IGNORE INTO psicologa (nome, email, senha, telefone, bio) VALUES (
    'Juliana Moura',
    'admin@nexus.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    '(11) 98765-4321',
    'Psicóloga especializada em terapia clínica e relacionamentos'
);

-- 2. PACIENTES
CREATE TABLE IF NOT EXISTS pacientes (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    nome            TEXT    NOT NULL,
    email           TEXT    NOT NULL,
    senha           TEXT    NOT NULL,
    data_nascimento TEXT,
    telefone        TEXT,
    cpf             TEXT,
    endereco        TEXT,
    data_criacao    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (email),
    UNIQUE (cpf)
);

-- 3. ESPECIALIDADES
CREATE TABLE IF NOT EXISTS especializacoes (
    id_especializacao   INTEGER PRIMARY KEY AUTOINCREMENT,
    nome                TEXT    NOT NULL,
    descricao           TEXT,
    preco               DECIMAL(10,2)   NOT NULL DEFAULT 150.00,
    ativa               INTEGER         NOT NULL DEFAULT 1,
    UNIQUE (nome)
);

INSERT OR IGNORE INTO especializacoes (nome, descricao, preco) VALUES
    ('Clínica',         'Psicoterapia para equilíbrio emocional e bem-estar',       150.00),
    ('Infantil',        'Auxilia no desenvolvimento emocional e social na infância', 150.00),
    ('Casal',           'Terapia para melhorar relacionamentos',                     180.00),
    ('Neuropsicologia', 'Avaliação e reabilitação neuropsicológica',                 200.00);

-- 4. HORÁRIOS DE ATENDIMENTO
CREATE TABLE IF NOT EXISTS horarios (
    id_horario  INTEGER PRIMARY KEY AUTOINCREMENT,
    horario     TEXT                            NOT NULL,
    turno       TEXT                            NOT NULL, -- 'manha' ou 'tarde'
    ativo       INTEGER                         NOT NULL DEFAULT 1,
    UNIQUE (horario)
);

INSERT OR IGNORE INTO horarios (horario, turno) VALUES
    ('09:00', 'manha'),
    ('10:00', 'manha'),
    ('11:00', 'manha'),
    ('12:00', 'manha'),
    ('15:00', 'tarde'),
    ('16:00', 'tarde'),
    ('17:00', 'tarde'),
    ('18:00', 'tarde');

-- 5. DATAS DISPONÍVEIS
CREATE TABLE IF NOT EXISTS datas_disponiveis (
    id_data         INTEGER PRIMARY KEY AUTOINCREMENT,
    data_calendario TEXT                                      NOT NULL,
    status_dia      TEXT                                      NOT NULL DEFAULT 'Disponivel', -- 'Disponivel' ou 'Indisponivel'
    UNIQUE (data_calendario)
);

-- 6. BLOQUEIOS DE AGENDA
CREATE TABLE IF NOT EXISTS bloqueios_agenda (
    id_bloqueio  INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo         TEXT                                        NOT NULL, -- 'dia_inteiro', 'horario_especifico', 'ferias'
    data_inicio  TEXT                                        NOT NULL,
    data_fim     TEXT,
    id_horario   INTEGER,
    motivo       TEXT,
    data_criacao DATETIME                                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_horario) REFERENCES horarios (id_horario) ON DELETE SET NULL
);

-- 7. CONSULTAS
CREATE TABLE IF NOT EXISTS consultas (
    id_consulta         INTEGER PRIMARY KEY AUTOINCREMENT,
    id_paciente         INTEGER                                            NOT NULL,
    id_especializacao   INTEGER                                            NOT NULL,
    id_horario          INTEGER                                            NOT NULL,
    id_data             INTEGER                                            NOT NULL,
    modalidade          TEXT                                               NOT NULL, -- 'Presencial' ou 'Online'
    status              TEXT                                               NOT NULL DEFAULT 'Pendente', -- 'Pendente', 'Confirmada', 'Remarcada', 'Cancelada'
    valor               DECIMAL(10,2)                                      NOT NULL,
    pagamento_status    TEXT                                               NOT NULL DEFAULT 'Pendente', -- 'Pendente', 'Concluído', 'Reembolsado'
    observacoes         TEXT,
    data_criacao        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_data, id_horario),
    FOREIGN KEY (id_paciente)       REFERENCES pacientes       (id)               ON DELETE CASCADE,
    FOREIGN KEY (id_especializacao) REFERENCES especializacoes (id_especializacao),
    FOREIGN KEY (id_horario)        REFERENCES horarios        (id_horario),
    FOREIGN KEY (id_data)           REFERENCES datas_disponiveis (id_data)
);

-- Trigger para emular ON UPDATE CURRENT_TIMESTAMP na tabela consultas
CREATE TRIGGER IF NOT EXISTS trg_consultas_update AFTER UPDATE ON consultas
BEGIN
    UPDATE consultas SET data_atualizacao = CURRENT_TIMESTAMP WHERE id_consulta = OLD.id_consulta;
END;

-- 8. CONSULTAS CANCELADAS
CREATE TABLE IF NOT EXISTS consultas_canceladas (
    id_cancelamento     INTEGER PRIMARY KEY AUTOINCREMENT,
    id_consulta         INTEGER                        NOT NULL,
    id_paciente         INTEGER                        NOT NULL,
    id_especializacao   INTEGER                        NOT NULL,
    data_consulta       TEXT                                NOT NULL,
    horario_consulta    TEXT                                NOT NULL,
    modalidade          TEXT         NOT NULL,
    valor               DECIMAL(10,2)                       NOT NULL,
    motivo_cancelamento TEXT,
    cancelado_por       TEXT        NOT NULL, -- 'paciente' ou 'psicologa'
    data_cancelamento   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_consulta)       REFERENCES consultas       (id_consulta)      ON DELETE CASCADE,
    FOREIGN KEY (id_paciente)       REFERENCES pacientes       (id)               ON DELETE CASCADE,
    FOREIGN KEY (id_especializacao) REFERENCES especializacoes (id_especializacao)
);

-- 9. PAGAMENTOS
CREATE TABLE IF NOT EXISTS pagamentos (
    id_pagamento        INTEGER PRIMARY KEY AUTOINCREMENT,
    id_consulta         INTEGER                                                NOT NULL,
    id_paciente         INTEGER                                                NOT NULL,
    valor               DECIMAL(10,2)                                               NOT NULL,
    metodo_pagamento    TEXT                    NOT NULL, -- 'Pix', 'Cartao', 'Boleto', 'Dinheiro'
    status              TEXT          NOT NULL DEFAULT 'Pendente', -- 'Pendente', 'Concluído', 'Falha', 'Reembolsado'
    referencia_externa  TEXT,
    data_pagamento      DATETIME,
    data_criacao        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_consulta)  REFERENCES consultas (id_consulta) ON DELETE CASCADE,
    FOREIGN KEY (id_paciente)  REFERENCES pacientes (id)          ON DELETE CASCADE
);

-- Trigger para emular ON UPDATE CURRENT_TIMESTAMP na tabela pagamentos
CREATE TRIGGER IF NOT EXISTS trg_pagamentos_update AFTER UPDATE ON pagamentos
BEGIN
    UPDATE pagamentos SET data_atualizacao = CURRENT_TIMESTAMP WHERE id_pagamento = OLD.id_pagamento;
END;

-- 10. NOTIFICAÇÕES
CREATE TABLE IF NOT EXISTS notificacoes (
    id_notificacao  INTEGER PRIMARY KEY AUTOINCREMENT,
    id_paciente     INTEGER,
    id_psicologa    INTEGER,
    tipo            TEXT     NOT NULL,
    mensagem        TEXT            NOT NULL,
    lida            INTEGER         NOT NULL DEFAULT 0,
    data_criacao    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_paciente)  REFERENCES pacientes (id)             ON DELETE CASCADE,
    FOREIGN KEY (id_psicologa) REFERENCES psicologa (id_psicologa)   ON DELETE CASCADE
);
