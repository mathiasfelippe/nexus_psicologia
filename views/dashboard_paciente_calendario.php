<!--
    ARQUIVO: views/dashboard_paciente_calendario.php
    DESCRIÇÃO: View da aba "Calendário" do dashboard do paciente.

    Este arquivo é incluído pelo dashboard_paciente.php quando a aba 'calendario'
    está ativa. Exibe um calendário interativo (FullCalendar) com as consultas do
    paciente e dois modais: um para agendar e outro para reagendar consultas.

    FLUXO DE AGENDAMENTO (via modal):
      1. Paciente clica em "Agendar Nova Consulta" ou em uma data do calendário
      2. Modal de agendamento é aberto com a data pré-preenchida
      3. buscarHorarios() faz fetch para api/horarios_disponiveis.php?data=YYYY-MM-DD
      4. Paciente preenche modalidade, especialização e horário
      5. Resumo é atualizado em tempo real
      6. Formulário é enviado via POST (acao=agendar_consulta)

    FLUXO DE REAGENDAMENTO:
      1. Paciente clica em um evento do calendário (consulta futura não cancelada)
      2. Modal de reagendamento é aberto com os dados da consulta atual
      3. Paciente escolhe nova data e horário
      4. Formulário é enviado via POST (acao=reagendar_consulta)

    DEPENDÊNCIAS:
      - $especializacoes: array de especializações (carregado pelo arquivo pai)
      - FullCalendar: biblioteca de calendário carregada no dashboard_paciente.php
      - api/consultas_paciente.php: endpoint que retorna os eventos do calendário
      - api/horarios_disponiveis.php: endpoint que retorna horários livres por data
-->

<!-- ═══════════════════════════════════════════════════════════════
     SEÇÃO PRINCIPAL DO CALENDÁRIO
═════════════════════════════════════════════════════════════════ -->
<!-- Classes: calendario-secao (base), calendario-paciente-secao (específico do paciente),
     calendario-melhorado (estilo visual aprimorado com pseudo-elementos decorativos) -->
<div class="calendario-secao calendario-paciente-secao calendario-melhorado">

    <!-- ── Cabeçalho com ícone, título e legenda de cores ── -->
    <div class="calendario-header-moderno">
        <div class="calendario-header-esquerda">
            <!-- Ícone de calendário em SVG dentro de um wrapper com gradiente -->
            <div class="calendario-icon-wrapper">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div>
                <!-- Badge pequeno acima do título -->
                <span class="calendario-badge-moderno">Agenda Pessoal</span>
                <h2 class="calendario-titulo">Meu Calendario</h2>
                <p class="calendario-descricao-moderna">Gerencie suas consultas: visualize, agende e reagende em um clique.</p>
            </div>
        </div>
        <!-- Legenda de cores dos eventos do calendário -->
        <div class="calendario-legenda-moderna">
            <!-- Cor teal (#75C9C8) = consulta confirmada -->
            <div class="legenda-item-moderno">
                <span class="legenda-cor-moderna" style="background: #75C9C8;"></span>
                <span>Confirmada</span>
            </div>
            <!-- Cor âmbar (#e0a85c) = consulta pendente de confirmação -->
            <div class="legenda-item-moderno">
                <span class="legenda-cor-moderna" style="background: #e0a85c;"></span>
                <span>Pendente</span>
            </div>
            <!-- Cor lavanda (variável CSS) = consulta passada -->
            <div class="legenda-item-moderno">
                <span class="legenda-cor-moderna" style="background: var(--lavanda);"></span>
                <span>Passada</span>
            </div>
        </div>
    </div>

    <!-- ── Botões de ação acima do calendário ── -->
    <div class="calendario-acoes-moderno">
        <!-- Botão principal: abre o modal de agendamento sem data pré-selecionada -->
        <button class="btn-agendar-principal" onclick="abrirModalAgendamento()">
            <!-- Ícone de "+" em círculo -->
            <span class="btn-agendar-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </span>
            <span class="btn-agendar-texto">
                <span class="btn-agendar-principal-texto">Agendar Nova Consulta</span>
                <span class="btn-agendar-subtexto">Escolha data e horario</span>
            </span>
        </button>
        <!-- Botão secundário: redireciona para a aba de listagem de consultas -->
        <button class="btn-secundario-moderno" onclick="window.location.href='?aba=consultas'">
            <!-- Ícone de prancheta/lista -->
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"></path>
            </svg>
            Ver Todas
        </button>
    </div>

    <!-- Container onde o FullCalendar será renderizado pelo JavaScript -->
    <div class="calendario-wrapper-moderno">
        <div id="calendar-paciente"></div>
    </div>

    <!-- Dica de uso exibida abaixo do calendário -->
    <div class="calendario-dica">
        <!-- Ícone de informação (i) em círculo -->
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        <span>Clique em uma data para agendar ou em uma consulta para reagendar</span>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL DE AGENDAMENTO — Design Moderno
     Formulário de 4 passos: Data → Modalidade → Especialização → Horário
═════════════════════════════════════════════════════════════════ -->
<!-- Oculto por padrão; exibido pela classe CSS 'show' adicionada pelo JavaScript -->
<div id="modalAgendamento" class="modal">
    <div class="modal-conteudo modal-agendamento">
        <!-- Cabeçalho do modal com ícone, badge, título e botão de fechar -->
        <div class="modal-header-moderno">
            <div class="modal-header-info">
                <!-- Ícone de calendário com símbolo de "+" -->
                <div class="modal-icone-header">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                        <line x1="12" y1="14" x2="12" y2="18"></line>
                        <line x1="10" y1="16" x2="14" y2="16"></line>
                    </svg>
                </div>
                <div>
                    <span class="modal-badge-moderno">Novo Agendamento</span>
                    <h2 class="modal-titulo">Agendar Consulta</h2>
                </div>
            </div>
            <!-- Botão X para fechar o modal -->
            <button class="modal-fechar-moderno" onclick="fecharModalAgendamento()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="modal-body-moderno">
            <!-- Formulário enviado via POST para o dashboard_paciente.php -->
            <form id="form-agendamento" method="POST">
                <!-- Campo oculto: identifica a ação para o controlador PHP -->
                <input type="hidden" name="acao" value="agendar_consulta">
                <!-- Campo oculto: ID da data disponível no banco (preenchido pelo JS via fetch) -->
                <input type="hidden" name="id_data" id="id_data_input">

                <!-- ── Passo 1: Data da Consulta ── -->
                <div class="form-passo">
                    <!-- Número do passo exibido em círculo com gradiente -->
                    <div class="passo-numero">1</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Data da Consulta</label>
                        <!-- min: impede selecionar datas passadas (data de hoje como mínimo) -->
                        <!-- date('Y-m-d') gera a data atual no formato YYYY-MM-DD -->
                        <input type="date" id="data_agendamento" name="data_agendamento" required min="<?php echo date('Y-m-d'); ?>" class="input-moderno">
                    </div>
                </div>

                <!-- ── Passo 2: Modalidade (Online ou Presencial) ── -->
                <div class="form-passo">
                    <div class="passo-numero">2</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Modalidade</label>
                        <!-- Grid de cards clicáveis para selecionar a modalidade -->
                        <div class="modalidade-grid">
                            <!-- Card "Online" ativo por padrão (classe 'active') -->
                            <button type="button" class="modalidade-card active" data-value="Online" onclick="selecionarModalidade(this)">
                                <div class="modalidade-card-icon">
                                    <!-- Ícone de monitor/tela -->
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                                        <line x1="8" y1="21" x2="16" y2="21"></line>
                                        <line x1="12" y1="17" x2="12" y2="21"></line>
                                    </svg>
                                </div>
                                <span class="modalidade-card-titulo">Online</span>
                                <span class="modalidade-card-desc">Videochamada</span>
                            </button>
                            <!-- Card "Presencial" -->
                            <button type="button" class="modalidade-card" data-value="Presencial" onclick="selecionarModalidade(this)">
                                <div class="modalidade-card-icon">
                                    <!-- Ícone de prédio/consultório -->
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 21h18"></path>
                                        <path d="M5 21V7l8-4v18"></path>
                                        <path d="M19 21V11l-6-4"></path>
                                        <path d="M9 9v.01"></path>
                                        <path d="M9 12v.01"></path>
                                        <path d="M9 15v.01"></path>
                                    </svg>
                                </div>
                                <span class="modalidade-card-titulo">Presencial</span>
                                <span class="modalidade-card-desc">No consultorio</span>
                            </button>
                            <!-- Campo oculto que armazena o valor selecionado (atualizado pelo JS) -->
                            <input type="hidden" name="modalidade" id="modalidade_input" value="Online">
                        </div>
                    </div>
                </div>

                <!-- ── Passo 3: Especialização ── -->
                <div class="form-passo">
                    <div class="passo-numero">3</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Especializacao</label>
                        <div class="select-moderno-wrapper">
                            <select id="especializacao" name="id_especializacao" required class="select-moderno">
                                <option value="">Selecione uma especializacao</option>
                                <?php foreach ($especializacoes as $spec): ?>
                                    <!-- value: ID da especialização | texto: nome + preço formatado -->
                                    <option value="<?php echo $spec['id_especializacao']; ?>">
                                        <?php echo htmlspecialchars($spec['nome']); ?> — R$ <?php echo number_format($spec['preco'], 2, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── Passo 4: Horário Disponível ── -->
                <div class="form-passo">
                    <div class="passo-numero">4</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Horario Disponivel</label>
                        <div class="select-moderno-wrapper">
                            <!-- Populado dinamicamente pelo JS após buscar horários via fetch -->
                            <select id="horario_select" name="id_horario" required class="select-moderno">
                                <option value="">Selecione um horario</option>
                            </select>
                        </div>
                        <!-- Mensagem de dica/status abaixo do select de horário -->
                        <!-- Atualizada pelo JS para indicar: carregando / disponíveis / indisponível -->
                        <p class="form-hint-moderno" id="horario-hint">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            Escolha uma data para ver os horarios disponiveis
                        </p>
                    </div>
                </div>

                <!-- ── Resumo do Agendamento (exibido quando data + horário estão preenchidos) ── -->
                <!-- Oculto por padrão; exibido pelo JS quando os campos obrigatórios são preenchidos -->
                <div class="resumo-agendamento-moderno" id="resumo-agendamento" style="display: none;">
                    <div class="resumo-header-moderno">
                        <!-- Ícone de documento -->
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        Resumo do Agendamento
                    </div>
                    <!-- Grid com os 4 itens do resumo (preenchidos pelo JS) -->
                    <div class="resumo-grid">
                        <div class="resumo-item-moderno">
                            <span class="resumo-label-moderno">Data</span>
                            <span class="resumo-valor-moderno" id="resumo-data">—</span>
                        </div>
                        <div class="resumo-item-moderno">
                            <span class="resumo-label-moderno">Horario</span>
                            <span class="resumo-valor-moderno" id="resumo-horario">—</span>
                        </div>
                        <div class="resumo-item-moderno">
                            <span class="resumo-label-moderno">Especializacao</span>
                            <span class="resumo-valor-moderno" id="resumo-espec">—</span>
                        </div>
                        <!-- Item de destaque: valor com texto em gradiente -->
                        <div class="resumo-item-moderno resumo-destaque">
                            <span class="resumo-label-moderno">Valor</span>
                            <span class="resumo-valor-destaque" id="resumo-valor">—</span>
                        </div>
                    </div>
                </div>

                <!-- Botões de ação do modal -->
                <div class="modal-acoes-moderno">
                    <button type="submit" class="btn-confirmar-agendamento">
                        <!-- Ícone de check -->
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Confirmar Agendamento
                    </button>
                    <!-- Botão cancelar: fecha o modal sem enviar o formulário -->
                    <button type="button" class="btn-cancelar-agendamento" onclick="fecharModalAgendamento()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL DE REAGENDAMENTO
     Exibido ao clicar em uma consulta futura no calendário
═════════════════════════════════════════════════════════════════ -->
<div id="modalReagendamento" class="modal">
    <div class="modal-conteudo modal-agendamento">
        <!-- Cabeçalho com ícone de seta circular (reagendamento) -->
        <div class="modal-header-moderno">
            <div class="modal-header-info">
                <!-- Ícone de seta circular (símbolo de reagendamento) -->
                <div class="modal-icone-header modal-icone-reagendar">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                </div>
                <div>
                    <!-- Badge com cor lavanda (diferente do modal de agendamento) -->
                    <span class="modal-badge-moderno modal-badge-reagendar">Reagendamento</span>
                    <h2 class="modal-titulo">Reagendar Consulta</h2>
                </div>
            </div>
            <button class="modal-fechar-moderno" onclick="fecharModalReagendamento()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="modal-body-moderno">
            <!-- Formulário de reagendamento enviado via POST -->
            <form id="form-reagendamento" method="POST">
                <!-- Campo oculto: identifica a ação para o controlador PHP -->
                <input type="hidden" name="acao" value="reagendar_consulta">
                <!-- Campo oculto: ID da consulta a ser reagendada (preenchido pelo JS) -->
                <input type="hidden" name="id_consulta" id="id_consulta_reagendar">

                <!-- Exibe os dados da consulta atual (preenchido pelo JS ao abrir o modal) -->
                <div class="consulta-atual-moderno">
                    <div class="consulta-atual-header-moderno">
                        <!-- Ícone de relógio -->
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        Consulta Atual
                    </div>
                    <!-- Preenchido pelo JS com: especialização, data e hora da consulta atual -->
                    <div id="info-consulta-atual" class="consulta-atual-info-moderno"></div>
                </div>

                <!-- ── Passo 1: Nova Data ── -->
                <div class="form-passo">
                    <!-- Número do passo com gradiente lavanda (diferente do modal de agendamento) -->
                    <div class="passo-numero passo-numero-reagendar">1</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Nova Data</label>
                        <!-- min: impede selecionar datas passadas -->
                        <input type="date" id="nova_data" name="nova_data" required min="<?php echo date('Y-m-d'); ?>" class="input-moderno">
                    </div>
                </div>

                <!-- ── Passo 2: Novo Horário ── -->
                <div class="form-passo">
                    <div class="passo-numero passo-numero-reagendar">2</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Novo Horario</label>
                        <div class="select-moderno-wrapper">
                            <!-- Populado dinamicamente pelo JS ao mudar a data -->
                            <select id="novo_horario" name="id_horario" required class="select-moderno">
                                <option value="">Selecione um horario</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Botões de ação -->
                <div class="modal-acoes-moderno">
                    <button type="submit" class="btn-confirmar-agendamento">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Confirmar Reagendamento
                    </button>
                    <button type="button" class="btn-cancelar-agendamento" onclick="fecharModalReagendamento()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     ESTILOS LOCAIS DO CALENDÁRIO E MODAIS
     (Definidos aqui para evitar conflito com outros componentes)
═════════════════════════════════════════════════════════════════ -->
<style>
/* ── Seção do Calendário Melhorado ──
   Usa pseudo-elementos ::before e ::after para criar círculos decorativos
   com gradiente radial nos cantos superior-direito e inferior-esquerdo */
.calendario-melhorado {
    position: relative;
    overflow: hidden; /* Oculta os pseudo-elementos que saem da borda */
}

/* Círculo decorativo no canto superior direito (azul sereno, 8% de opacidade) */
.calendario-melhorado::before {
    content: '';
    position: absolute;
    top: -120px;
    right: -120px;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(128,161,212,.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none; /* Não interfere com cliques do usuário */
}

/* Círculo decorativo no canto inferior esquerdo (teal, 6% de opacidade) */
.calendario-melhorado::after {
    content: '';
    position: absolute;
    bottom: -80px;
    left: -80px;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(117,201,200,.06) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

/* Garante que o conteúdo fique acima dos pseudo-elementos decorativos */
.calendario-melhorado > * {
    position: relative;
    z-index: 1;
}

/* ── Cabeçalho Moderno ──
   Flexbox com espaçamento entre o lado esquerdo (ícone+título) e a legenda */
.calendario-header-moderno {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-lg);
    gap: var(--spacing-lg);
}

.calendario-header-esquerda {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-md);
}

/* Wrapper do ícone: quadrado 48x48 com gradiente e sombra */
.calendario-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    background: var(--gradiente-principal);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0; /* Não encolhe em telas pequenas */
    box-shadow: 0 4px 12px rgba(123,111,191,.18);
}

/* Badge pequeno acima do título (estilo pílula) */
.calendario-badge-moderno {
    display: inline-block;
    font-family: var(--font-body);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--azul-sereno);
    background: rgba(128,161,212,.1);
    padding: 3px 10px;
    border-radius: 20px;
    margin-bottom: 4px;
}

.calendario-titulo {
    font-family: var(--font-titulo);
    font-size: 24px;
    font-weight: 700;
    color: var(--grafite);
    margin-bottom: 4px;
}

.calendario-descricao-moderna {
    font-family: var(--font-body);
    font-size: 13px;
    color: var(--grafite);
    opacity: 0.5;
}

/* ── Legenda de Cores ──
   Container com fundo semi-transparente e borda branca */
.calendario-legenda-moderna {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 10px 16px;
    background: rgba(255,255,255,.6);
    border-radius: var(--radius-md);
    border: 1px solid rgba(255,255,255,.5);
}

.legenda-item-moderno {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-body);
    font-size: 12px;
    font-weight: 500;
    color: var(--grafite);
    opacity: 0.7;
}

/* Quadrado colorido de 12x12 com bordas arredondadas */
.legenda-cor-moderna {
    width: 12px;
    height: 12px;
    border-radius: 4px;
    display: inline-block;
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
}

/* ── Botões de Ação ── */
.calendario-acoes-moderno {
    display: flex;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

/* Botão principal de agendamento com gradiente e sombra colorida */
.btn-agendar-principal {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 14px 24px;
    background: var(--gradiente-principal);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(.4,0,.2,1); /* Transição suave com curva de Bézier */
    box-shadow: 0 4px 16px rgba(123,111,191,.2);
    flex: 1;
    max-width: 320px;
}

/* Hover: sobe 2px e aumenta a sombra */
.btn-agendar-principal:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(123,111,191,.25);
}

/* Círculo branco semi-transparente ao redor do ícone "+" */
.btn-agendar-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.btn-agendar-texto {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
}

.btn-agendar-principal-texto {
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 700;
}

.btn-agendar-subtexto {
    font-family: var(--font-body);
    font-size: 11px;
    opacity: 0.8;
}

/* Botão secundário com fundo semi-transparente e efeito glassmorphism */
.btn-secundario-moderno {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 20px;
    background: rgba(255,255,255,.6);
    color: var(--grafite);
    border: 1.5px solid var(--perola);
    border-radius: var(--radius-md);
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
    backdrop-filter: blur(8px); /* Efeito de desfoque do fundo (glassmorphism) */
}

.btn-secundario-moderno:hover {
    background: rgba(255,255,255,.9);
    border-color: var(--lavanda);
    transform: translateY(-1px);
}

/* ── Wrapper do Calendário ── */
.calendario-wrapper-moderno {
    border-radius: var(--radius-lg);
    overflow: hidden;
    background: rgba(255,255,255,.4);
    padding: var(--spacing-md);
    border: 1px solid rgba(255,255,255,.5);
}

/* ── Dica de Uso ── */
.calendario-dica {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: var(--spacing-md);
    padding: 10px 14px;
    background: rgba(128,161,212,.06);
    border-radius: var(--radius-sm);
    font-family: var(--font-body);
    font-size: 12px;
    color: var(--grafite);
    opacity: 0.5;
}

.calendario-dica svg {
    flex-shrink: 0;
    opacity: 0.6;
}

/* ══════════════════════════════════════════════════════════════
   ESTILOS DOS MODAIS DE AGENDAMENTO E REAGENDAMENTO
══════════════════════════════════════════════════════════════ */

/* Largura máxima do modal de agendamento */
.modal-agendamento {
    max-width: 540px;
}

/* Cabeçalho do modal: flexbox entre info (ícone+título) e botão fechar */
.modal-header-moderno {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-xl) var(--spacing-xl) var(--spacing-lg);
}

.modal-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Ícone quadrado 44x44 com gradiente (agendamento) */
.modal-icone-header {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-md);
    background: var(--gradiente-principal);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

/* Variação do ícone para reagendamento (gradiente lavanda→azul) */
.modal-icone-reagendar {
    background: linear-gradient(135deg, var(--lavanda) 0%, var(--azul-sereno) 100%);
}

/* Badge do modal (estilo pílula) */
.modal-badge-moderno {
    display: inline-block;
    font-family: var(--font-body);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--azul-sereno);
    background: rgba(128,161,212,.1);
    padding: 2px 8px;
    border-radius: 4px;
    margin-bottom: 2px;
}

/* Variação do badge para reagendamento (cor lavanda) */
.modal-badge-reagendar {
    color: var(--lavanda);
    background: rgba(192,185,221,.12);
}

.modal-titulo {
    font-family: var(--font-titulo);
    font-size: 20px;
    font-weight: 700;
    color: var(--grafite);
}

/* Botão de fechar (X) circular */
.modal-fechar-moderno {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(247,244,234,.5);
    border: none;
    color: var(--grafite);
    opacity: 0.5;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

/* Hover: fundo vermelho claro, ícone vermelho, opacidade total */
.modal-fechar-moderno:hover {
    background: rgba(239,68,68,.1);
    color: var(--danger);
    opacity: 1;
}

.modal-body-moderno {
    padding: 0 var(--spacing-xl) var(--spacing-xl);
}

/* ── Passos do Formulário ──
   Cada passo tem um número em círculo à esquerda e o conteúdo à direita */
.form-passo {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: var(--spacing-lg);
}

/* Número do passo: círculo 28x28 com gradiente e texto branco */
.passo-numero {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--gradiente-principal);
    color: white;
    font-family: var(--font-body);
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}

/* Variação do número para reagendamento (gradiente lavanda→azul) */
.passo-numero-reagendar {
    background: linear-gradient(135deg, var(--lavanda) 0%, var(--azul-sereno) 100%);
}

/* Conteúdo do passo ocupa o espaço restante */
.passo-conteudo {
    flex: 1;
}

/* Label em maiúsculas com espaçamento entre letras */
.label-moderno {
    display: block;
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--grafite);
    opacity: 0.55;
    margin-bottom: 8px;
}

/* Input de data com borda suave e fundo semi-transparente */
.input-moderno {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid var(--perola);
    border-radius: var(--radius-md);
    font-family: var(--font-body);
    font-size: 14px;
    color: var(--grafite);
    background: rgba(255,255,255,.6);
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
}

/* Focus: borda azul sereno com sombra suave */
.input-moderno:focus {
    outline: none;
    border-color: var(--azul-sereno);
    box-shadow: 0 0 0 3px rgba(128,161,212,.1);
    background: white;
}

/* ── Cards de Modalidade ──
   Grid de 2 colunas com cards clicáveis */
.modalidade-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.modalidade-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 12px;
    background: rgba(255,255,255,.5);
    border: 2px solid var(--perola);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
}

.modalidade-card:hover {
    border-color: var(--azul-sereno);
    background: rgba(128,161,212,.06);
}

/* Card ativo: borda azul com sombra e fundo levemente colorido */
.modalidade-card.active {
    border-color: var(--azul-sereno);
    background: rgba(128,161,212,.08);
    box-shadow: 0 0 0 3px rgba(128,161,212,.1);
}

/* Círculo do ícone dentro do card */
.modalidade-card-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(128,161,212,.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--azul-sereno);
    transition: all 0.25s ease;
}

/* Card ativo: ícone com gradiente e cor branca */
.modalidade-card.active .modalidade-card-icon {
    background: var(--gradiente-principal);
    color: white;
}

.modalidade-card-titulo {
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 700;
    color: var(--grafite);
}

.modalidade-card-desc {
    font-family: var(--font-body);
    font-size: 11px;
    color: var(--grafite);
    opacity: 0.5;
}

/* ── Select Moderno ──
   Oculta o seta nativa (appearance:none) e usa pseudo-elemento ::after como seta */
.select-moderno-wrapper {
    position: relative;
}

.select-moderno {
    width: 100%;
    padding: 12px 40px 12px 16px; /* padding-right extra para a seta customizada */
    border: 1.5px solid var(--perola);
    border-radius: var(--radius-md);
    font-family: var(--font-body);
    font-size: 14px;
    color: var(--grafite);
    background: rgba(255,255,255,.6);
    appearance: none; /* Remove a seta nativa do select */
    cursor: pointer;
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
}

.select-moderno:focus {
    outline: none;
    border-color: var(--azul-sereno);
    box-shadow: 0 0 0 3px rgba(128,161,212,.1);
    background: white;
}

/* Seta customizada criada com bordas rotacionadas 45° */
.select-moderno-wrapper::after {
    content: '';
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-60%) rotate(45deg);
    width: 8px;
    height: 8px;
    border-right: 2px solid var(--grafite);
    border-bottom: 2px solid var(--grafite);
    opacity: 0.35;
    pointer-events: none; /* Não interfere com o clique no select */
}

/* ── Hint/Dica abaixo do select de horário ── */
.form-hint-moderno {
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-body);
    font-size: 12px;
    color: var(--grafite);
    opacity: 0.45;
    margin-top: 8px;
}

.form-hint-moderno svg {
    flex-shrink: 0;
    opacity: 0.6;
}

/* ── Resumo do Agendamento ──
   Caixa com gradiente suave e borda azul */
.resumo-agendamento-moderno {
    background: linear-gradient(135deg, rgba(128,161,212,.06) 0%, rgba(117,201,200,.06) 100%);
    border: 1px solid rgba(128,161,212,.15);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    margin-top: var(--spacing-lg);
}

.resumo-header-moderno {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-body);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--azul-sereno);
    margin-bottom: 14px;
}

.resumo-grid {
    display: grid;
    gap: 8px;
}

/* Cada linha do resumo: label à esquerda, valor à direita */
.resumo-item-moderno {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(222,217,226,.2);
}

/* Último item sem borda inferior */
.resumo-item-moderno:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.resumo-label-moderno {
    font-family: var(--font-body);
    font-size: 13px;
    color: var(--grafite);
    opacity: 0.6;
}

.resumo-valor-moderno {
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 600;
    color: var(--grafite);
}

/* Item de destaque (valor): borda superior e sem borda inferior */
.resumo-destaque {
    padding-top: 12px;
    margin-top: 4px;
    border-top: 1px solid rgba(128,161,212,.15);
    border-bottom: none !important;
}

/* Valor em destaque com texto em gradiente (técnica de clip de texto) */
.resumo-valor-destaque {
    font-family: var(--font-titulo);
    font-size: 18px;
    font-weight: 700;
    background: var(--gradiente-principal);
    -webkit-background-clip: text;   /* Clip do gradiente no texto (prefixo WebKit) */
    -webkit-text-fill-color: transparent; /* Torna o texto transparente para mostrar o gradiente */
    background-clip: text;           /* Versão padrão do clip */
}

/* ── Botões de Ação do Modal ── */
.modal-acoes-moderno {
    display: flex;
    gap: 12px;
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-lg);
    border-top: 1px solid rgba(222,217,226,.25);
}

/* Botão de confirmar: flex: 1 para ocupar o espaço restante */
.btn-confirmar-agendamento {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 24px;
    background: var(--gradiente-principal);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(.4,0,.2,1);
    box-shadow: 0 4px 16px rgba(123,111,191,.2);
}

.btn-confirmar-agendamento:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(123,111,191,.25);
}

/* Botão de cancelar: fundo semi-transparente com borda */
.btn-cancelar-agendamento {
    padding: 14px 24px;
    background: rgba(255,255,255,.6);
    color: var(--grafite);
    border: 1.5px solid var(--perola);
    border-radius: var(--radius-md);
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
}

.btn-cancelar-agendamento:hover {
    background: rgba(255,255,255,.9);
    border-color: var(--lavanda);
}

/* ── Caixa de Consulta Atual (Reagendamento) ── */
.consulta-atual-moderno {
    background: linear-gradient(135deg, rgba(192,185,221,.08) 0%, rgba(128,161,212,.08) 100%);
    border: 1px solid rgba(192,185,221,.2);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

.consulta-atual-header-moderno {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--lavanda);
    margin-bottom: 10px;
}

.consulta-atual-info-moderno {
    font-family: var(--font-body);
    font-size: 14px;
    color: var(--grafite);
    line-height: 1.7;
}

.consulta-atual-info-moderno strong {
    font-weight: 700;
    color: var(--grafite);
}

/* ── Responsividade para telas pequenas (≤ 600px) ── */
@media (max-width: 600px) {
    /* Cabeçalho empilhado verticalmente */
    .calendario-header-moderno {
        flex-direction: column;
    }

    /* Legenda ocupa a largura total */
    .calendario-legenda-moderna {
        width: 100%;
        justify-content: flex-start;
    }

    /* Botões de ação empilhados */
    .calendario-acoes-moderno {
        flex-direction: column;
    }

    /* Botão principal ocupa a largura total */
    .btn-agendar-principal {
        max-width: 100%;
    }

    /* Cards de modalidade em coluna única */
    .modalidade-grid {
        grid-template-columns: 1fr;
    }

    /* Botões do modal empilhados */
    .modal-acoes-moderno {
        flex-direction: column;
    }
}

/* ── Suporte ao Modo Escuro (dark-mode) ── */
body.dark-mode .calendario-titulo,
body.dark-mode .calendario-descricao-moderna {
    color: var(--branco);
}
body.dark-mode .calendario-badge-moderno {
    background: rgba(128,161,212,.2);
    color: #8ab4e8;
}
body.dark-mode .calendario-legenda-moderna {
    background: rgba(255,255,255,.06);
    border-color: rgba(255,255,255,.08);
}
body.dark-mode .legenda-item-moderno {
    color: rgba(255,255,255,.6);
}
body.dark-mode .calendario-wrapper-moderno {
    background: rgba(30,30,50,.5);
    border-color: rgba(255,255,255,.08);
}
body.dark-mode .calendario-dica {
    color: rgba(255,255,255,.5);
    background: rgba(128,161,212,.08);
}
body.dark-mode .btn-secundario-moderno {
    background: rgba(255,255,255,.08);
    border-color: rgba(255,255,255,.12);
    color: var(--branco);
}
body.dark-mode .btn-secundario-moderno:hover {
    background: rgba(255,255,255,.14);
    border-color: var(--azul-sereno);
}
</style>

<!-- ═══════════════════════════════════════════════════════════════
     JAVASCRIPT DO CALENDÁRIO
     Inicializa o FullCalendar, gerencia os modais e busca horários
═════════════════════════════════════════════════════════════════ -->
<script>
    // Variável global para armazenar a instância do calendário do paciente
    var calendarioPaciente = null;

    // Aguarda o DOM estar completamente carregado antes de inicializar
    document.addEventListener('DOMContentLoaded', function() {
        var el = document.getElementById('calendar-paciente');
        // Verifica se o elemento existe, se o FullCalendar está disponível
        // e se o calendário ainda não foi inicializado (evita duplicação)
        if (el && typeof FullCalendar !== 'undefined' && !el.dataset.inicializado) {
            el.dataset.inicializado = '1'; // Marca como inicializado para evitar duplicação

            // Cria a instância do FullCalendar com as configurações
            calendarioPaciente = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth', // Visão inicial: grade mensal
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay' // Opções de visão
                },
                locale: 'pt-br',
                buttonText: { today: 'Hoje', month: 'Mes', week: 'Semana', day: 'Dia' },
                height: 'auto',        // Altura automática baseada no conteúdo
                contentHeight: 'auto',
                // Fonte de eventos: endpoint PHP que retorna JSON com as consultas
                events: 'api/consultas_paciente.php',
                displayEventTime: false, // Não exibe o horário no título do evento
                selectable: true,        // Permite selecionar datas
                dayMaxEvents: 3,         // Máximo de 3 eventos visíveis por dia (resto vira "+N")

                // Callback: clique em uma data vazia → abre modal de agendamento com a data
                dateClick: function(info) {
                    abrirModalAgendamentoComData(info.dateStr);
                },

                // Callback: clique em um evento (consulta) → abre modal de reagendamento
                // Apenas para consultas futuras e não canceladas
                eventClick: function(info) {
                    var props = info.event.extendedProps; // Dados extras do evento
                    if (!props.passada && props.status !== 'Cancelada') {
                        abrirModalReagendamento(info.event);
                    }
                },

                // Callback: após montar o elemento do evento no DOM → adiciona tooltip
                eventDidMount: function(info) {
                    var p = info.event.extendedProps;
                    // Tooltip: "Especialização | Status | Modalidade"
                    var tooltip = p.especializacao + ' | ' + p.status;
                    if (p.modalidade) tooltip += ' | ' + p.modalidade;
                    info.el.title = tooltip; // Atributo HTML title = tooltip nativo do navegador
                }
            });
            calendarioPaciente.render(); // Renderiza o calendário no DOM
        }

        // Adiciona listeners para atualizar o resumo quando especialização ou horário mudam
        document.getElementById('especializacao').addEventListener('change', atualizarResumo);
        document.getElementById('horario_select').addEventListener('change', atualizarResumo);
    });

    /*
     * Seleciona uma modalidade clicando no card correspondente.
     * Remove 'active' de todos os cards e adiciona no clicado.
     * Atualiza o campo oculto com o valor selecionado.
     *
     * @param {HTMLElement} el - O card de modalidade clicado
     */
    function selecionarModalidade(el) {
        // Remove a classe 'active' de todos os cards
        document.querySelectorAll('.modalidade-card').forEach(function(b) { b.classList.remove('active'); });
        // Adiciona 'active' no card clicado
        el.classList.add('active');
        // Atualiza o campo oculto com o valor do data-value do card
        document.getElementById('modalidade_input').value = el.dataset.value;
    }

    /*
     * Abre o modal de agendamento com a data de hoje pré-selecionada.
     * Reseta o formulário e busca os horários disponíveis para hoje.
     */
    function abrirModalAgendamento() {
        document.getElementById('form-agendamento').reset(); // Limpa o formulário
        document.getElementById('resumo-agendamento').style.display = 'none'; // Oculta o resumo
        // Reseta os cards de modalidade: remove 'active' de todos
        document.querySelectorAll('.modalidade-card').forEach(function(b) { b.classList.remove('active'); });
        // Ativa o card "Online" por padrão
        document.querySelector('.modalidade-card[data-value="Online"]').classList.add('active');
        document.getElementById('modalidade_input').value = 'Online';
        // Define a data de hoje como padrão (formato YYYY-MM-DD)
        var hoje = new Date().toISOString().split('T')[0];
        document.getElementById('data_agendamento').value = hoje;
        // Exibe o modal adicionando a classe 'show'
        document.getElementById('modalAgendamento').classList.add('show');
        // Busca os horários disponíveis para hoje
        buscarHorarios(hoje);
        // Atualiza a mensagem de hint para indicar que está carregando
        document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Carregando horarios...';
    }

    /*
     * Abre o modal de agendamento com uma data específica pré-selecionada.
     * Chamado quando o usuário clica em uma data no calendário.
     *
     * @param {string} dataStr - Data no formato 'YYYY-MM-DD'
     */
    function abrirModalAgendamentoComData(dataStr) {
        document.getElementById('form-agendamento').reset();
        document.getElementById('resumo-agendamento').style.display = 'none';
        document.querySelectorAll('.modalidade-card').forEach(function(b) { b.classList.remove('active'); });
        document.querySelector('.modalidade-card[data-value="Online"]').classList.add('active');
        document.getElementById('modalidade_input').value = 'Online';
        // Pré-preenche com a data clicada no calendário
        document.getElementById('data_agendamento').value = dataStr;
        document.getElementById('modalAgendamento').classList.add('show');
        buscarHorarios(dataStr); // Busca horários para a data clicada
        document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Carregando horarios...';
    }

    /*
     * Fecha o modal de agendamento removendo a classe 'show'.
     */
    function fecharModalAgendamento() {
        document.getElementById('modalAgendamento').classList.remove('show');
    }

    /*
     * Busca os horários disponíveis para uma data via AJAX.
     * Popula o select de horários e atualiza a mensagem de hint.
     *
     * @param {string} dataStr - Data no formato 'YYYY-MM-DD'
     */
    function buscarHorarios(dataStr) {
        // Faz requisição GET para o endpoint de horários disponíveis
        fetch('api/horarios_disponiveis.php?data=' + dataStr)
            .then(function(r) { return r.json(); }) // Converte resposta para JSON
            .then(function(data) {
                if (data.id_data) {
                    // Preenche o campo oculto com o ID da data no banco
                    document.getElementById('id_data_input').value = data.id_data;
                    var select = document.getElementById('horario_select');
                    select.innerHTML = '<option value="">Selecione um horario</option>'; // Limpa opções

                    if (data.horarios && data.horarios.length > 0) {
                        // Adiciona cada horário disponível como opção no select
                        data.horarios.forEach(function(h) {
                            var opt = document.createElement('option');
                            opt.value = h.id_horario;
                            opt.textContent = h.horario; // Texto exibido (ex: "09:00")
                            select.appendChild(opt);
                        });
                        // Atualiza hint com o número de horários disponíveis
                        document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> ' + data.horarios.length + ' horario(s) disponivel(is).';
                    } else {
                        // Nenhum horário disponível: exibe mensagem de erro no hint
                        document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Todos os horarios estao ocupados ou bloqueados nesta data. Tente outra data.';
                    }
                } else {
                    // Data não disponível no banco: exibe mensagem de erro
                    document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Esta data nao esta disponivel. Escolha outra data.';
                }
            })
            .catch(function(err) {
                // Trata erros de rede ou de parsing
                console.error('Erro ao buscar horarios:', err);
                document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Erro ao carregar horarios.';
            });
    }

    /*
     * Abre o modal de reagendamento com os dados do evento clicado.
     * Preenche o campo oculto com o ID da consulta e exibe os dados atuais.
     *
     * @param {Object} evento - Objeto de evento do FullCalendar
     */
    function abrirModalReagendamento(evento) {
        var props = evento.extendedProps; // Dados extras: id_consulta, especializacao, etc.
        // Formata a data do evento para exibição em pt-BR (ex: "15/01/2025")
        var data = evento.start ? evento.start.toLocaleDateString('pt-BR') : '';
        // Formata o horário para exibição (ex: "09:00")
        var hora = evento.start ? evento.start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';

        // Preenche o campo oculto com o ID da consulta a ser reagendada
        document.getElementById('id_consulta_reagendar').value = props.id_consulta;
        // Exibe os dados da consulta atual no modal
        document.getElementById('info-consulta-atual').innerHTML = '<strong>' + (props.especializacao || '') + '</strong><br>' + data + ' as ' + hora;
        // Exibe o modal
        document.getElementById('modalReagendamento').classList.add('show');
    }

    /*
     * Fecha o modal de reagendamento removendo a classe 'show'.
     */
    function fecharModalReagendamento() {
        document.getElementById('modalReagendamento').classList.remove('show');
    }

    /*
     * Atualiza o resumo do agendamento em tempo real.
     * Exibido quando data, horário e especialização estão preenchidos.
     * Extrai o valor do texto da opção de especialização via regex.
     */
    function atualizarResumo() {
        var data = document.getElementById('data_agendamento').value;
        var horarioSelect = document.getElementById('horario_select');
        // Obtém o texto da opção selecionada (ex: "09:00")
        var horario = horarioSelect.options[horarioSelect.selectedIndex] ? horarioSelect.options[horarioSelect.selectedIndex].text : '';
        var especSelect = document.getElementById('especializacao');
        // Obtém o texto completo da especialização (ex: "Ansiedade — R$ 150,00")
        var espec = especSelect.options[especSelect.selectedIndex] ? especSelect.options[especSelect.selectedIndex].text : '';
        // Extrai o valor monetário do texto usando regex (ex: "150,00")
        var valorMatch = espec.match(/R\$ ([\d\.,]+)/);
        var valor = valorMatch ? 'R$ ' + valorMatch[1] : '';

        // Exibe o resumo apenas quando data e horário estão preenchidos
        if (data && horario && horario !== 'Selecione um horario') {
            // Formata a data: adiciona T12:00:00 para evitar problemas de fuso horário
            document.getElementById('resumo-data').textContent = new Date(data + 'T12:00:00').toLocaleDateString('pt-BR');
            document.getElementById('resumo-horario').textContent = horario;
            // Remove o preço do nome da especialização para exibição limpa
            document.getElementById('resumo-espec').textContent = espec ? espec.replace(/ — R\$.*$/, '') : '—';
            document.getElementById('resumo-valor').textContent = valor || '—';
            document.getElementById('resumo-agendamento').style.display = 'block'; // Exibe o resumo
        } else {
            document.getElementById('resumo-agendamento').style.display = 'none'; // Oculta o resumo
        }
    }

    // Listener: quando a data de agendamento muda, busca horários e atualiza o resumo
    document.getElementById('data_agendamento').addEventListener('change', function() {
        if (this.value) {
            buscarHorarios(this.value);
            atualizarResumo();
        }
    });

    // Listener: quando a nova data de reagendamento muda, busca horários disponíveis
    document.getElementById('nova_data').addEventListener('change', function() {
        var dataStr = this.value;
        if (dataStr) {
            // Faz fetch para buscar horários da nova data
            fetch('api/horarios_disponiveis.php?data=' + dataStr)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var select = document.getElementById('novo_horario');
                    select.innerHTML = '<option value="">Selecione um horario</option>'; // Limpa opções
                    if (data.horarios) {
                        // Popula o select com os horários disponíveis
                        data.horarios.forEach(function(h) {
                            var opt = document.createElement('option');
                            opt.value = h.id_horario;
                            opt.textContent = h.horario;
                            select.appendChild(opt);
                        });
                    }
                })
                .catch(function(err) { console.error('Erro:', err); });
        }
    });
</script>
