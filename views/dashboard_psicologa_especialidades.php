<?php
$especializacoes = obter_especializacoes($pdo);
?>

<div class="secao">
    <div class="header-top">
        <h2>Minhas Especialidades</h2>
        <button class="btn btn-primary" onclick="abrirModalNovaEspecializacao()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Adicionar
        </button>
    </div>

    <!-- Grid de Especialidades -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
        <?php foreach ($especializacoes as $esp): ?>
            <div style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                    <div>
                        <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 8px;">
                            <?php echo htmlspecialchars($esp['nome']); ?>
                        </h3>
                        <p style="font-size: 14px; color: #6b7280;">
                            <?php echo htmlspecialchars($esp['descricao'] ?? 'Sem descrição'); ?>
                        </p>
                    </div>
                </div>

                <div style="background: #f3f4f6; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                    <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Valor da Consulta</div>
                    <div style="font-size: 28px; font-weight: 800; color: #6366f1;">
                        R$ <?php echo number_format($esp['preco'], 2, ',', '.'); ?>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-primary" style="flex: 1;" onclick="editarEspecializacao(<?php echo $esp['id_especializacao']; ?>)">
                        Editar Preço
                    </button>
                    <button class="btn btn-secondary" style="flex: 1;">
                        Detalhes
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal de Edição -->
<div id="modalEspecializacao" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Editar Especialidade</h2>
            <button class="modal-fechar" onclick="fecharModalEspecializacao()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="formEspecializacao">
                <input type="hidden" name="acao" value="atualizar_preco">
                <input type="hidden" name="id_especializacao" id="idEspecializacao">

                <div class="form-group">
                    <label>Novo Valor (R$)</label>
                    <input type="number" name="novo_preco" id="novoPreco" step="0.01" min="0" required>
                </div>

                <div class="modal-acoes">
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    <button type="button" class="btn btn-secondary" onclick="fecharModalEspecializacao()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editarEspecializacao(id) {
        document.getElementById('idEspecializacao').value = id;
        document.getElementById('modalEspecializacao').classList.add('show');
    }

    function fecharModalEspecializacao() {
        document.getElementById('modalEspecializacao').classList.remove('show');
    }

    function abrirModalNovaEspecializacao() {
        alert('Funcionalidade em desenvolvimento');
    }

    document.addEventListener('click', function(event) {
        const modal = document.getElementById('modalEspecializacao');
        if (modal && event.target === modal) {
            modal.classList.remove('show');
        }
    });
</script>
