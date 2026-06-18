<?php
/*
 * ARQUIVO: views/dashboard_psicologa_configuracoes.php
 * DESCRIÇÃO: View da aba "Disponibilidade" do dashboard da psicóloga.
 *
 * Este arquivo é incluído pelo dashboard_psicologa.php quando a aba 'configuracoes'
 * está ativa. Permite à psicóloga gerenciar bloqueios de agenda em três categorias:
 *   - Dias Inteiros: bloqueia um dia completo
 *   - Horários Específicos: bloqueia um horário em um dia específico
 *   - Férias: bloqueia um período (data início → data fim)
 *
 * AÇÕES POST GERADAS:
 *   - criar_bloqueio  → Adiciona um novo bloqueio de agenda
 *   - remover_bloqueio → Remove um bloqueio existente (via formulário dinâmico JS)
 *
 * DEPENDÊNCIAS:
 *   - $pdo: conexão com o banco de dados (herdada do arquivo pai)
 *   - obter_bloqueios_agenda(): retorna todos os bloqueios cadastrados
 *   - obter_horarios(): retorna os horários padrão para o select
 */

// Carrega todos os bloqueios de agenda cadastrados no banco
// Retorna array com: id_bloqueio, tipo, data_inicio, data_fim, id_horario, horario_texto, motivo
$bloqueios = obter_bloqueios_agenda($pdo);
?>

<!-- ═══════════════════════════════════════════════════════════
     SEÇÃO DISPONIBILIDADE
     Gerenciamento de bloqueios de agenda (dias, horários, férias)
═══════════════════════════════════════════════════════════ -->
<div class="secao">
    <div class="header-top">
        <h2>Disponibilidade</h2>
        <!-- Botão que abre o modal para criar um novo bloqueio -->
        <button class="btn btn-primary" onclick="abrirModalBloqueio()">
            <!-- Ícone de "+" em SVG -->
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Bloquear Período
        </button>
    </div>

    <!-- ── Abas de Disponibilidade (Dias / Horários / Férias) ── -->
    <!-- Navegação por abas usando JavaScript puro (sem recarregar a página) -->
    <div style="display: flex; gap: 12px; margin-bottom: 32px; border-bottom: 1px solid #e5e7eb;">
        <!-- Aba "Dias Inteiros" ativa por padrão (classe 'ativo') -->
        <button class="btn-tab ativo" onclick="mudarAbaBloqueio('dias', this)">Dias Inteiros</button>
        <button class="btn-tab" onclick="mudarAbaBloqueio('horarios', this)">Horários Específicos</button>
        <button class="btn-tab" onclick="mudarAbaBloqueio('ferias', this)">Férias</button>
    </div>

    <!-- ── Conteúdo: Dias Inteiros Bloqueados ── -->
    <!-- Visível por padrão (classe 'ativo'); oculto pelas outras abas -->
    <div id="tab-dias" class="tab-conteudo ativo">
        <div style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 24px; color: #111827;">Dias Bloqueados</h3>
            
            <div style="display: grid; gap: 12px;">
                <?php 
                // Filtra apenas os bloqueios do tipo 'dia_inteiro' usando arrow function
                // fn($b) é uma arrow function (PHP 7.4+): equivalente a function($b) use (&$var)
                $dias_bloqueados = array_filter($bloqueios, fn($b) => $b['tipo'] === 'dia_inteiro');
                if (count($dias_bloqueados) > 0):
                    foreach ($dias_bloqueados as $bloqueio):
                ?>
                    <!-- Card de bloqueio com borda vermelha à esquerda -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f9fafb; border-radius: 12px; border-left: 4px solid #ef4444;">
                        <div>
                            <!-- Data do bloqueio formatada como dd/mm/YYYY -->
                            <div style="font-weight: 600; color: #111827;">
                                <?php echo date('d/m/Y', strtotime($bloqueio['data_inicio'])); ?>
                            </div>
                            <!-- Motivo do bloqueio ou texto padrão se não informado -->
                            <div style="font-size: 13px; color: #6b7280; margin-top: 4px;">
                                <?php echo htmlspecialchars($bloqueio['motivo'] ?? 'Sem motivo'); ?>
                            </div>
                        </div>
                        <!-- Botão de remoção: chama removerBloqueio() com o ID do bloqueio -->
                        <button class="btn btn-pequeno btn-cancelar" onclick="removerBloqueio(<?php echo $bloqueio['id_bloqueio']; ?>)">
                            Remover
                        </button>
                    </div>
                <?php 
                    endforeach;
                else:
                ?>
                    <!-- Estado vazio: exibido quando não há dias bloqueados -->
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="48" height="48" style="margin: 0 auto 16px; opacity: 0.5;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p>Nenhum dia bloqueado</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Conteúdo: Horários Específicos Bloqueados ── -->
    <!-- Oculto por padrão; exibido ao clicar na aba "Horários Específicos" -->
    <div id="tab-horarios" class="tab-conteudo">
        <div style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 24px; color: #111827;">Horários Bloqueados</h3>
            
            <div style="display: grid; gap: 12px;">
                <?php 
                // Filtra apenas os bloqueios do tipo 'horario_especifico'
                $horarios_bloqueados = array_filter($bloqueios, fn($b) => $b['tipo'] === 'horario_especifico');
                if (count($horarios_bloqueados) > 0):
                    foreach ($horarios_bloqueados as $bloqueio):
                ?>
                    <!-- Card de bloqueio com borda amarela à esquerda -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f9fafb; border-radius: 12px; border-left: 4px solid #f59e0b;">
                        <div>
                            <div style="font-weight: 600; color: #111827;">
                                <!-- Data do bloqueio -->
                                <?php echo date('d/m/Y', strtotime($bloqueio['data_inicio'])); ?>
                                <?php if (!empty($bloqueio['horario_texto'])): ?>
                                    <!-- &bull; é o caractere "•" (ponto médio separador) -->
                                    <!-- substr(..., 0, 5) exibe apenas HH:MM (sem segundos) -->
                                    &bull; <?php echo htmlspecialchars(substr($bloqueio['horario_texto'], 0, 5)); ?>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 13px; color: #6b7280; margin-top: 4px;">
                                <?php echo htmlspecialchars($bloqueio['motivo'] ?? 'Sem motivo'); ?>
                            </div>
                        </div>
                        <button class="btn btn-pequeno btn-cancelar" onclick="removerBloqueio(<?php echo $bloqueio['id_bloqueio']; ?>)">
                            Remover
                        </button>
                    </div>
                <?php 
                    endforeach;
                else:
                ?>
                    <!-- Estado vazio -->
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>Nenhum horário bloqueado</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Conteúdo: Períodos de Férias ── -->
    <!-- Oculto por padrão; exibido ao clicar na aba "Férias" -->
    <div id="tab-ferias" class="tab-conteudo">
        <div style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 24px; color: #111827;">Períodos de Férias</h3>
            
            <div style="display: grid; gap: 12px;">
                <?php 
                // Filtra apenas os bloqueios do tipo 'ferias'
                $ferias = array_filter($bloqueios, fn($b) => $b['tipo'] === 'ferias');
                if (count($ferias) > 0):
                    foreach ($ferias as $bloqueio):
                ?>
                    <!-- Card de férias com borda verde à esquerda -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f9fafb; border-radius: 12px; border-left: 4px solid #10b981;">
                        <div>
                            <div style="font-weight: 600; color: #111827;">
                                <!-- Exibe o período: "dd/mm/YYYY até dd/mm/YYYY" -->
                                <?php echo date('d/m/Y', strtotime($bloqueio['data_inicio'])); ?> até 
                                <!-- Se data_fim for null, usa data_inicio como fallback -->
                                <?php echo date('d/m/Y', strtotime($bloqueio['data_fim'] ?? $bloqueio['data_inicio'])); ?>
                            </div>
                            <div style="font-size: 13px; color: #6b7280; margin-top: 4px;">
                                Férias/Ausência
                            </div>
                        </div>
                        <button class="btn btn-pequeno btn-cancelar" onclick="removerBloqueio(<?php echo $bloqueio['id_bloqueio']; ?>)">
                            Remover
                        </button>
                    </div>
                <?php 
                    endforeach;
                else:
                ?>
                    <!-- Estado vazio -->
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>Nenhum período de férias registrado</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL DE CRIAÇÃO DE BLOQUEIO
     Formulário para criar novos bloqueios de agenda
═══════════════════════════════════════════════════════════ -->
<div id="modalBloqueio" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Bloquear Período</h2>
            <button class="modal-fechar" onclick="fecharModalBloqueio()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Formulário enviado via POST para acao=criar_bloqueio no controlador -->
            <form method="POST" id="formBloqueio">
                <!-- Campo oculto que identifica a ação para o PHP -->
                <input type="hidden" name="acao" value="criar_bloqueio">

                <!-- Tipo de bloqueio: controla quais campos adicionais são exibidos -->
                <!-- onchange: chama atualizarFormBloqueio() para mostrar/ocultar campos -->
                <div class="form-group">
                    <label>Tipo de Bloqueio</label>
                    <select name="tipo_bloqueio" id="tipoBloqueio" onchange="atualizarFormBloqueio()" required>
                        <option value="">Selecione...</option>
                        <option value="dia_inteiro">Dia Inteiro</option>
                        <option value="horario_especifico">Horário Específico</option>
                        <option value="ferias">Férias/Ausência</option>
                    </select>
                </div>

                <!-- Data de início do bloqueio (obrigatória para todos os tipos) -->
                <div class="form-group">
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" required>
                </div>

                <!-- Data de fim: visível apenas para o tipo 'ferias' -->
                <!-- display:none por padrão; exibido por atualizarFormBloqueio() -->
                <div class="form-group" id="dataFimGroup" style="display: none;">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim">
                </div>

                <!-- Seleção de horário: visível apenas para o tipo 'horario_especifico' -->
                <!-- display:none por padrão; exibido por atualizarFormBloqueio() -->
                <div class="form-group" id="horarioGroup" style="display: none;">
                    <label>Horário</label>
                    <select name="horario_inicio">
                        <option value="">Selecione um horário</option>
                        <?php 
                        // Carrega os horários padrão do sistema para o select
                        $horarios = obter_horarios($pdo);
                        foreach ($horarios as $h): 
                        ?>
                            <!-- value: ID do horário | texto: HH:MM (sem segundos) -->
                            <option value="<?php echo $h['id_horario']; ?>"><?php echo substr($h['horario'], 0, 5); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Motivo do bloqueio (opcional) -->
                <div class="form-group">
                    <label>Motivo (Opcional)</label>
                    <textarea name="motivo" rows="3"></textarea>
                </div>

                <div class="modal-acoes">
                    <button type="submit" class="btn btn-primary">Bloquear</button>
                    <button type="button" class="btn btn-secondary" onclick="fecharModalBloqueio()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* ── Botões de aba (tabs) ── */
    .btn-tab {
        padding: 12px 16px;
        background: none;
        border: none;
        font-size: 14px;
        font-weight: 500;
        color: #6b7280; /* Cinza por padrão */
        cursor: pointer;
        transition: all 0.2s ease;
        border-bottom: 2px solid transparent; /* Borda inferior invisível por padrão */
    }

    /* Hover: escurece o texto */
    .btn-tab:hover {
        color: #111827;
    }

    /* Aba ativa: texto roxo + borda inferior roxa */
    .btn-tab.ativo {
        color: #6366f1;
        border-bottom-color: #6366f1;
    }

    /* Conteúdo de aba: oculto por padrão */
    .tab-conteudo {
        display: none;
    }

    /* Conteúdo de aba ativa: visível */
    .tab-conteudo.ativo {
        display: block;
    }

    /* ── DARK MODE: Overrides para o tema escuro ── */
    body.dark-mode .btn-tab {
        color: rgba(255,255,255,.5);
    }
    body.dark-mode .btn-tab:hover {
        color: var(--branco);
    }
    body.dark-mode .btn-tab.ativo {
        color: var(--azul-sereno);
        border-bottom-color: var(--azul-sereno);
    }
</style>

<script>
    /*
     * Troca a aba ativa de disponibilidade.
     * Oculta todas as abas e exibe apenas a selecionada.
     *
     * @param {string} aba - Identificador da aba ('dias', 'horarios' ou 'ferias')
     * @param {HTMLElement} btn - O botão que foi clicado (para aplicar classe 'ativo')
     */
    function mudarAbaBloqueio(aba, btn) {
        // Remove a classe 'ativo' de todos os conteúdos de aba
        document.querySelectorAll('.tab-conteudo').forEach(function(el) {
            el.classList.remove('ativo');
            el.style.display = ''; // Reseta o display inline (deixa o CSS controlar)
        });
        // Remove a classe 'ativo' de todos os botões de aba
        document.querySelectorAll('.btn-tab').forEach(function(el) { el.classList.remove('ativo'); });
        // Exibe o conteúdo da aba selecionada (ex: 'tab-dias', 'tab-horarios', 'tab-ferias')
        document.getElementById('tab-' + aba).classList.add('ativo');
        // Marca o botão clicado como ativo
        if (btn) btn.classList.add('ativo');
    }

    /*
     * Abre o modal de criação de bloqueio.
     */
    function abrirModalBloqueio() {
        document.getElementById('modalBloqueio').classList.add('show');
    }

    /*
     * Fecha o modal de criação de bloqueio.
     */
    function fecharModalBloqueio() {
        document.getElementById('modalBloqueio').classList.remove('show');
    }

    /*
     * Atualiza os campos do formulário de bloqueio conforme o tipo selecionado.
     * Mostra/oculta os campos de data fim e horário.
     */
    function atualizarFormBloqueio() {
        const tipo = document.getElementById('tipoBloqueio').value;
        // Exibe "Data Fim" apenas para férias (período com início e fim)
        document.getElementById('dataFimGroup').style.display = tipo === 'ferias' ? 'block' : 'none';
        // Exibe "Horário" apenas para bloqueio de horário específico
        document.getElementById('horarioGroup').style.display = tipo === 'horario_especifico' ? 'block' : 'none';
    }

    /*
     * Remove um bloqueio de agenda criando e enviando um formulário dinamicamente.
     * Pede confirmação antes de remover.
     *
     * @param {number} id - ID do bloqueio a ser removido
     */
    function removerBloqueio(id) {
        // Exibe caixa de confirmação nativa do navegador
        if (confirm('Tem certeza que deseja remover este bloqueio?')) {
            // Cria um formulário HTML dinamicamente (sem precisar de um form no HTML)
            const form = document.createElement('form');
            form.method = 'POST';
            // Template literal (backticks) para criar o HTML com o ID do bloqueio
            form.innerHTML = `
                <input type="hidden" name="acao" value="remover_bloqueio">
                <input type="hidden" name="id_bloqueio" value="${id}">
            `;
            // Adiciona o formulário ao body e envia imediatamente
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Fecha o modal ao clicar fora da área de conteúdo (no overlay escuro)
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('modalBloqueio');
        if (modal && event.target === modal) {
            modal.classList.remove('show');
        }
    });
</script>
