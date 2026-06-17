<div class="calendario-secao calendario-paciente-secao calendario-melhorado">
    <!-- Header com gradiente e badges -->
    <div class="calendario-header-moderno">
        <div class="calendario-header-esquerda">
            <div class="calendario-icon-wrapper">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div>
                <span class="calendario-badge-moderno">Agenda Pessoal</span>
                <h2 class="calendario-titulo">Meu Calendario</h2>
                <p class="calendario-descricao-moderna">Gerencie suas consultas: visualize, agende e reagende em um clique.</p>
            </div>
        </div>
        <div class="calendario-legenda-moderna">
            <div class="legenda-item-moderno">
                <span class="legenda-cor-moderna" style="background: #75C9C8;"></span>
                <span>Confirmada</span>
            </div>
            <div class="legenda-item-moderno">
                <span class="legenda-cor-moderna" style="background: #e0a85c;"></span>
                <span>Pendente</span>
            </div>
            <div class="legenda-item-moderno">
                <span class="legenda-cor-moderna" style="background: var(--lavanda);"></span>
                <span>Passada</span>
            </div>
        </div>
    </div>

    <!-- Acoes do calendario -->
    <div class="calendario-acoes-moderno">
        <button class="btn-agendar-principal" onclick="abrirModalAgendamento()">
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
        <button class="btn-secundario-moderno" onclick="window.location.href='?aba=consultas'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"></path>
            </svg>
            Ver Todas
        </button>
    </div>

    <!-- Calendario -->
    <div class="calendario-wrapper-moderno">
        <div id="calendar-paciente"></div>
    </div>

    <!-- Dica interativa -->
    <div class="calendario-dica">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        <span>Clique em uma data para agendar ou em uma consulta para reagendar</span>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL AGENDAMENTO — Design Moderno
═════════════════════════════════════════════════════════════════ -->
<div id="modalAgendamento" class="modal">
    <div class="modal-conteudo modal-agendamento">
        <div class="modal-header-moderno">
            <div class="modal-header-info">
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
            <button class="modal-fechar-moderno" onclick="fecharModalAgendamento()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="modal-body-moderno">
            <form id="form-agendamento" method="POST">
                <input type="hidden" name="acao" value="agendar_consulta">
                <input type="hidden" name="id_data" id="id_data_input">

                <!-- Passo 1: Data -->
                <div class="form-passo">
                    <div class="passo-numero">1</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Data da Consulta</label>
                        <input type="date" id="data_agendamento" name="data_agendamento" required min="<?php echo date('Y-m-d'); ?>" class="input-moderno">
                    </div>
                </div>

                <!-- Passo 2: Modalidade -->
                <div class="form-passo">
                    <div class="passo-numero">2</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Modalidade</label>
                        <div class="modalidade-grid">
                            <button type="button" class="modalidade-card active" data-value="Online" onclick="selecionarModalidade(this)">
                                <div class="modalidade-card-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                                        <line x1="8" y1="21" x2="16" y2="21"></line>
                                        <line x1="12" y1="17" x2="12" y2="21"></line>
                                    </svg>
                                </div>
                                <span class="modalidade-card-titulo">Online</span>
                                <span class="modalidade-card-desc">Videochamada</span>
                            </button>
                            <button type="button" class="modalidade-card" data-value="Presencial" onclick="selecionarModalidade(this)">
                                <div class="modalidade-card-icon">
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
                            <input type="hidden" name="modalidade" id="modalidade_input" value="Online">
                        </div>
                    </div>
                </div>

                <!-- Passo 3: Especializacao -->
                <div class="form-passo">
                    <div class="passo-numero">3</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Especializacao</label>
                        <div class="select-moderno-wrapper">
                            <select id="especializacao" name="id_especializacao" required class="select-moderno">
                                <option value="">Selecione uma especializacao</option>
                                <?php foreach ($especializacoes as $spec): ?>
                                    <option value="<?php echo $spec['id_especializacao']; ?>">
                                        <?php echo htmlspecialchars($spec['nome']); ?> — R$ <?php echo number_format($spec['preco'], 2, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Passo 4: Horario -->
                <div class="form-passo">
                    <div class="passo-numero">4</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Horario Disponivel</label>
                        <div class="select-moderno-wrapper">
                            <select id="horario_select" name="id_horario" required class="select-moderno">
                                <option value="">Selecione um horario</option>
                            </select>
                        </div>
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

                <!-- Resumo -->
                <div class="resumo-agendamento-moderno" id="resumo-agendamento" style="display: none;">
                    <div class="resumo-header-moderno">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        Resumo do Agendamento
                    </div>
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
                        <div class="resumo-item-moderno resumo-destaque">
                            <span class="resumo-label-moderno">Valor</span>
                            <span class="resumo-valor-destaque" id="resumo-valor">—</span>
                        </div>
                    </div>
                </div>

                <!-- Acoes -->
                <div class="modal-acoes-moderno">
                    <button type="submit" class="btn-confirmar-agendamento">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Confirmar Agendamento
                    </button>
                    <button type="button" class="btn-cancelar-agendamento" onclick="fecharModalAgendamento()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL REAGENDAMENTO
═════════════════════════════════════════════════════════════════ -->
<div id="modalReagendamento" class="modal">
    <div class="modal-conteudo modal-agendamento">
        <div class="modal-header-moderno">
            <div class="modal-header-info">
                <div class="modal-icone-header modal-icone-reagendar">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                </div>
                <div>
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
            <form id="form-reagendamento" method="POST">
                <input type="hidden" name="acao" value="reagendar_consulta">
                <input type="hidden" name="id_consulta" id="id_consulta_reagendar">

                <!-- Consulta Atual -->
                <div class="consulta-atual-moderno">
                    <div class="consulta-atual-header-moderno">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        Consulta Atual
                    </div>
                    <div id="info-consulta-atual" class="consulta-atual-info-moderno"></div>
                </div>

                <!-- Nova Data -->
                <div class="form-passo">
                    <div class="passo-numero passo-numero-reagendar">1</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Nova Data</label>
                        <input type="date" id="nova_data" name="nova_data" required min="<?php echo date('Y-m-d'); ?>" class="input-moderno">
                    </div>
                </div>

                <!-- Novo Horario -->
                <div class="form-passo">
                    <div class="passo-numero passo-numero-reagendar">2</div>
                    <div class="passo-conteudo">
                        <label class="label-moderno">Novo Horario</label>
                        <div class="select-moderno-wrapper">
                            <select id="novo_horario" name="id_horario" required class="select-moderno">
                                <option value="">Selecione um horario</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Acoes -->
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
     ESTILOS DO CALENDARIO E AGENDAMENTO
═════════════════════════════════════════════════════════════════ -->
<style>
/* Calendario Melhorado */
.calendario-melhorado {
    position: relative;
    overflow: hidden;
}

.calendario-melhorado::before {
    content: '';
    position: absolute;
    top: -120px;
    right: -120px;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(128,161,212,.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

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

.calendario-melhorado > * {
    position: relative;
    z-index: 1;
}

/* Header Moderno */
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

.calendario-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    background: var(--gradiente-principal);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(123,111,191,.18);
}

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

/* Legenda Moderna */
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

.legenda-cor-moderna {
    width: 12px;
    height: 12px;
    border-radius: 4px;
    display: inline-block;
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
}

/* Acoes Modernas */
.calendario-acoes-moderno {
    display: flex;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

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
    transition: all 0.3s cubic-bezier(.4,0,.2,1);
    box-shadow: 0 4px 16px rgba(123,111,191,.2);
    flex: 1;
    max-width: 320px;
}

.btn-agendar-principal:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(123,111,191,.25);
}

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
    backdrop-filter: blur(8px);
}

.btn-secundario-moderno:hover {
    background: rgba(255,255,255,.9);
    border-color: var(--lavanda);
    transform: translateY(-1px);
}

/* Wrapper Calendario */
.calendario-wrapper-moderno {
    border-radius: var(--radius-lg);
    overflow: hidden;
    background: rgba(255,255,255,.4);
    padding: var(--spacing-md);
    border: 1px solid rgba(255,255,255,.5);
}

/* Dica */
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

/* ═══════════════════════════════════════════════════════════════
   MODAL AGENDAMENTO — Estilos
═════════════════════════════════════════════════════════════════ */

.modal-agendamento {
    max-width: 540px;
}

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

.modal-icone-reagendar {
    background: linear-gradient(135deg, var(--lavanda) 0%, var(--azul-sereno) 100%);
}

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

.modal-fechar-moderno:hover {
    background: rgba(239,68,68,.1);
    color: var(--danger);
    opacity: 1;
}

.modal-body-moderno {
    padding: 0 var(--spacing-xl) var(--spacing-xl);
}

/* Passos do Formulario */
.form-passo {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: var(--spacing-lg);
}

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

.passo-numero-reagendar {
    background: linear-gradient(135deg, var(--lavanda) 0%, var(--azul-sereno) 100%);
}

.passo-conteudo {
    flex: 1;
}

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

.input-moderno:focus {
    outline: none;
    border-color: var(--azul-sereno);
    box-shadow: 0 0 0 3px rgba(128,161,212,.1);
    background: white;
}

/* Modalidade Cards */
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

.modalidade-card.active {
    border-color: var(--azul-sereno);
    background: rgba(128,161,212,.08);
    box-shadow: 0 0 0 3px rgba(128,161,212,.1);
}

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

/* Select Moderno */
.select-moderno-wrapper {
    position: relative;
}

.select-moderno {
    width: 100%;
    padding: 12px 40px 12px 16px;
    border: 1.5px solid var(--perola);
    border-radius: var(--radius-md);
    font-family: var(--font-body);
    font-size: 14px;
    color: var(--grafite);
    background: rgba(255,255,255,.6);
    appearance: none;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
}

.select-moderno:focus {
    outline: none;
    border-color: var(--azul-sereno);
    box-shadow: 0 0 0 3px rgba(128,161,212,.1);
    background: white;
}

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
    pointer-events: none;
}

/* Hint */
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

/* Resumo Moderno */
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

.resumo-item-moderno {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(222,217,226,.2);
}

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

.resumo-destaque {
    padding-top: 12px;
    margin-top: 4px;
    border-top: 1px solid rgba(128,161,212,.15);
    border-bottom: none !important;
}

.resumo-valor-destaque {
    font-family: var(--font-titulo);
    font-size: 18px;
    font-weight: 700;
    background: var(--gradiente-principal);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Acoes do Modal */
.modal-acoes-moderno {
    display: flex;
    gap: 12px;
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-lg);
    border-top: 1px solid rgba(222,217,226,.25);
}

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

/* Consulta Atual (Reagendamento) */
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

/* Responsivo */
@media (max-width: 600px) {
    .calendario-header-moderno {
        flex-direction: column;
    }

    .calendario-legenda-moderna {
        width: 100%;
        justify-content: flex-start;
    }

    .calendario-acoes-moderno {
        flex-direction: column;
    }

    .btn-agendar-principal {
        max-width: 100%;
    }

    .modalidade-grid {
        grid-template-columns: 1fr;
    }

    .modal-acoes-moderno {
        flex-direction: column;
    }
}

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
     JAVASCRIPT
═════════════════════════════════════════════════════════════════ -->
<script>
var calendarioPaciente = null;

document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('calendar-paciente');
    if (el && typeof FullCalendar !== 'undefined' && !el.dataset.inicializado) {
        el.dataset.inicializado = '1';
        calendarioPaciente = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'pt-br',
            buttonText: { today: 'Hoje', month: 'Mes', week: 'Semana', day: 'Dia' },
            height: 'auto',
            contentHeight: 'auto',
            events: 'api/consultas_paciente.php',
            displayEventTime: false,
            selectable: true,
            dayMaxEvents: 3,
            dateClick: function(info) {
                abrirModalAgendamentoComData(info.dateStr);
            },
            eventClick: function(info) {
                var props = info.event.extendedProps;
                if (!props.passada && props.status !== 'Cancelada') {
                    abrirModalReagendamento(info.event);
                }
            },
            eventDidMount: function(info) {
                var p = info.event.extendedProps;
                var tooltip = p.especializacao + ' | ' + p.status;
                if (p.modalidade) tooltip += ' | ' + p.modalidade;
                info.el.title = tooltip;
            }
        });
        calendarioPaciente.render();
    }

    document.getElementById('especializacao').addEventListener('change', atualizarResumo);
    document.getElementById('horario_select').addEventListener('change', atualizarResumo);
});

function selecionarModalidade(el) {
    document.querySelectorAll('.modalidade-card').forEach(function(b) { b.classList.remove('active'); });
    el.classList.add('active');
    document.getElementById('modalidade_input').value = el.dataset.value;
}

function abrirModalAgendamento() {
    document.getElementById('form-agendamento').reset();
    document.getElementById('resumo-agendamento').style.display = 'none';
    document.querySelectorAll('.modalidade-card').forEach(function(b) { b.classList.remove('active'); });
    document.querySelector('.modalidade-card[data-value="Online"]').classList.add('active');
    document.getElementById('modalidade_input').value = 'Online';
    var hoje = new Date().toISOString().split('T')[0];
    document.getElementById('data_agendamento').value = hoje;
    document.getElementById('modalAgendamento').classList.add('show');
    buscarHorarios(hoje);
    document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Carregando horarios...';
}

function abrirModalAgendamentoComData(dataStr) {
    document.getElementById('form-agendamento').reset();
    document.getElementById('resumo-agendamento').style.display = 'none';
    document.querySelectorAll('.modalidade-card').forEach(function(b) { b.classList.remove('active'); });
    document.querySelector('.modalidade-card[data-value="Online"]').classList.add('active');
    document.getElementById('modalidade_input').value = 'Online';
    document.getElementById('data_agendamento').value = dataStr;
    document.getElementById('modalAgendamento').classList.add('show');
    buscarHorarios(dataStr);
    document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Carregando horarios...';
}

function fecharModalAgendamento() {
    document.getElementById('modalAgendamento').classList.remove('show');
}

function buscarHorarios(dataStr) {
    fetch('api/horarios_disponiveis.php?data=' + dataStr)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.id_data) {
                document.getElementById('id_data_input').value = data.id_data;
                var select = document.getElementById('horario_select');
                select.innerHTML = '<option value="">Selecione um horario</option>';
                if (data.horarios && data.horarios.length > 0) {
                    data.horarios.forEach(function(h) {
                        var opt = document.createElement('option');
                        opt.value = h.id_horario;
                        opt.textContent = h.horario;
                        select.appendChild(opt);
                    });
                    document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> ' + data.horarios.length + ' horario(s) disponivel(is).';
                } else {
                    document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Todos os horarios estao ocupados ou bloqueados nesta data. Tente outra data.';
                }
            } else {
                document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Esta data nao esta disponivel. Escolha outra data.';
            }
        })
        .catch(function(err) {
            console.error('Erro ao buscar horarios:', err);
            document.getElementById('horario-hint').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Erro ao carregar horarios.';
        });
}

function abrirModalReagendamento(evento) {
    var props = evento.extendedProps;
    var data = evento.start ? evento.start.toLocaleDateString('pt-BR') : '';
    var hora = evento.start ? evento.start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';

    document.getElementById('id_consulta_reagendar').value = props.id_consulta;
    document.getElementById('info-consulta-atual').innerHTML = '<strong>' + (props.especializacao || '') + '</strong><br>' + data + ' as ' + hora;
    document.getElementById('modalReagendamento').classList.add('show');
}

function fecharModalReagendamento() {
    document.getElementById('modalReagendamento').classList.remove('show');
}

function atualizarResumo() {
    var data = document.getElementById('data_agendamento').value;
    var horarioSelect = document.getElementById('horario_select');
    var horario = horarioSelect.options[horarioSelect.selectedIndex] ? horarioSelect.options[horarioSelect.selectedIndex].text : '';
    var especSelect = document.getElementById('especializacao');
    var espec = especSelect.options[especSelect.selectedIndex] ? especSelect.options[especSelect.selectedIndex].text : '';
    var valorMatch = espec.match(/R\$ ([\d\.,]+)/);
    var valor = valorMatch ? 'R$ ' + valorMatch[1] : '';

    if (data && horario && horario !== 'Selecione um horario') {
        document.getElementById('resumo-data').textContent = new Date(data + 'T12:00:00').toLocaleDateString('pt-BR');
        document.getElementById('resumo-horario').textContent = horario;
        document.getElementById('resumo-espec').textContent = espec ? espec.replace(/ — R\$.*$/, '') : '—';
        document.getElementById('resumo-valor').textContent = valor || '—';
        document.getElementById('resumo-agendamento').style.display = 'block';
    } else {
        document.getElementById('resumo-agendamento').style.display = 'none';
    }
}

document.getElementById('data_agendamento').addEventListener('change', function() {
    if (this.value) {
        buscarHorarios(this.value);
        atualizarResumo();
    }
});

document.getElementById('nova_data').addEventListener('change', function() {
    var dataStr = this.value;
    if (dataStr) {
        fetch('api/horarios_disponiveis.php?data=' + dataStr)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var select = document.getElementById('novo_horario');
                select.innerHTML = '<option value="">Selecione um horario</option>';
                if (data.horarios) {
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
