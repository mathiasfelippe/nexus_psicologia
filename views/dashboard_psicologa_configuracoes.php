<?php
$bloqueios = obter_bloqueios_agenda($pdo);
?>

<div class="secao">
    <div class="header-top">
        <h2>Disponibilidade</h2>
        <button class="btn btn-primary" onclick="abrirModalBloqueio()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Bloquear Período
        </button>
    </div>

    <!-- Abas de Disponibilidade -->
    <div style="display: flex; gap: 12px; margin-bottom: 32px; border-bottom: 1px solid #e5e7eb;">
        <button class="btn-tab ativo" onclick="mudarAbaBloqueio('dias', this)">Dias Inteiros</button>
        <button class="btn-tab" onclick="mudarAbaBloqueio('horarios', this)">Horários Específicos</button>
        <button class="btn-tab" onclick="mudarAbaBloqueio('ferias', this)">Férias</button>
    </div>

    <!-- Dias Inteiros -->
    <div id="tab-dias" class="tab-conteudo ativo">
        <div style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 24px; color: #111827;">Dias Bloqueados</h3>
            
            <div style="display: grid; gap: 12px;">
                <?php 
                $dias_bloqueados = array_filter($bloqueios, fn($b) => $b['tipo'] === 'dia_inteiro');
                if (count($dias_bloqueados) > 0):
                    foreach ($dias_bloqueados as $bloqueio):
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f9fafb; border-radius: 12px; border-left: 4px solid #ef4444;">
                        <div>
                            <div style="font-weight: 600; color: #111827;">
                                <?php echo date('d/m/Y', strtotime($bloqueio['data_inicio'])); ?>
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

    <!-- Horários Específicos -->
    <div id="tab-horarios" class="tab-conteudo">
        <div style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 24px; color: #111827;">Horários Bloqueados</h3>
            
            <div style="display: grid; gap: 12px;">
                <?php 
                $horarios_bloqueados = array_filter($bloqueios, fn($b) => $b['tipo'] === 'horario_especifico');
                if (count($horarios_bloqueados) > 0):
                    foreach ($horarios_bloqueados as $bloqueio):
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f9fafb; border-radius: 12px; border-left: 4px solid #f59e0b;">
                        <div>
                            <div style="font-weight: 600; color: #111827;">
                                <?php echo date('d/m/Y', strtotime($bloqueio['data_inicio'])); ?>
                                <?php if (!empty($bloqueio['horario_texto'])): ?>
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
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>Nenhum horário bloqueado</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Férias -->
    <div id="tab-ferias" class="tab-conteudo">
        <div style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 24px; color: #111827;">Períodos de Férias</h3>
            
            <div style="display: grid; gap: 12px;">
                <?php 
                $ferias = array_filter($bloqueios, fn($b) => $b['tipo'] === 'ferias');
                if (count($ferias) > 0):
                    foreach ($ferias as $bloqueio):
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f9fafb; border-radius: 12px; border-left: 4px solid #10b981;">
                        <div>
                            <div style="font-weight: 600; color: #111827;">
                                <?php echo date('d/m/Y', strtotime($bloqueio['data_inicio'])); ?> até 
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
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>Nenhum período de férias registrado</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Bloqueio -->
<div id="modalBloqueio" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Bloquear Período</h2>
            <button class="modal-fechar" onclick="fecharModalBloqueio()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="formBloqueio">
                <input type="hidden" name="acao" value="criar_bloqueio">

                <div class="form-group">
                    <label>Tipo de Bloqueio</label>
                    <select name="tipo_bloqueio" id="tipoBloqueio" onchange="atualizarFormBloqueio()" required>
                        <option value="">Selecione...</option>
                        <option value="dia_inteiro">Dia Inteiro</option>
                        <option value="horario_especifico">Horário Específico</option>
                        <option value="ferias">Férias/Ausência</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" required>
                </div>

                <div class="form-group" id="dataFimGroup" style="display: none;">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim">
                </div>

                <div class="form-group" id="horarioGroup" style="display: none;">
                    <label>Horário</label>
                    <select name="horario_inicio">
                        <option value="">Selecione um horário</option>
                        <?php 
                        $horarios = obter_horarios($pdo);
                        foreach ($horarios as $h): 
                        ?>
                            <option value="<?php echo $h['id_horario']; ?>"><?php echo substr($h['horario'], 0, 5); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

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
    .btn-tab {
        padding: 12px 16px;
        background: none;
        border: none;
        font-size: 14px;
        font-weight: 500;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s ease;
        border-bottom: 2px solid transparent;
    }

    .btn-tab:hover {
        color: #111827;
    }

    .btn-tab.ativo {
        color: #6366f1;
        border-bottom-color: #6366f1;
    }

    .tab-conteudo {
        display: none;
    }

    .tab-conteudo.ativo {
        display: block;
    }

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
    function mudarAbaBloqueio(aba, btn) {
        document.querySelectorAll('.tab-conteudo').forEach(function(el) {
            el.classList.remove('ativo');
            el.style.display = '';
        });
        document.querySelectorAll('.btn-tab').forEach(function(el) { el.classList.remove('ativo'); });
        document.getElementById('tab-' + aba).classList.add('ativo');
        if (btn) btn.classList.add('ativo');
    }

    function abrirModalBloqueio() {
        document.getElementById('modalBloqueio').classList.add('show');
    }

    function fecharModalBloqueio() {
        document.getElementById('modalBloqueio').classList.remove('show');
    }

    function atualizarFormBloqueio() {
        const tipo = document.getElementById('tipoBloqueio').value;
        document.getElementById('dataFimGroup').style.display = tipo === 'ferias' ? 'block' : 'none';
        document.getElementById('horarioGroup').style.display = tipo === 'horario_especifico' ? 'block' : 'none';
    }

    function removerBloqueio(id) {
        if (confirm('Tem certeza que deseja remover este bloqueio?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="acao" value="remover_bloqueio">
                <input type="hidden" name="id_bloqueio" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    document.addEventListener('click', function(event) {
        const modal = document.getElementById('modalBloqueio');
        if (modal && event.target === modal) {
            modal.classList.remove('show');
        }
    });
</script>
