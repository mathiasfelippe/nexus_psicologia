<?php
/*
 * ARQUIVO: views/dashboard_psicologa_especialidades.php
 * DESCRIÇÃO: View da aba "Especialidades" do dashboard da psicóloga.
 *
 * Este arquivo é incluído pelo dashboard_psicologa.php quando a aba 'especialidades'
 * está ativa. Ele exibe um grid de cards com as especialidades cadastradas,
 * permite editar o preço de cada uma via modal, e envia o formulário de atualização
 * para a ação 'atualizar_preco' no controlador principal.
 *
 * DEPENDÊNCIAS:
 *   - $pdo: conexão com o banco de dados (herdada do arquivo pai)
 *   - obter_especializacoes(): função definida em config/funcoes.php
 *   - CSS: dashboards.css (classes .modal, .btn, .form-group, etc.)
 */

// Carrega todas as especializações cadastradas no banco de dados
// Retorna um array com: id_especializacao, nome, descricao, preco
$especializacoes = obter_especializacoes($pdo);
?>

<!-- ═══════════════════════════════════════════════════════════
     GRID DE ESPECIALIDADES
     Exibe um card para cada especialização cadastrada
═══════════════════════════════════════════════════════════ -->
<div class="secao">
    <div class="header-top">
        <h2>Minhas Especialidades</h2>
    </div>

    <!-- Grid responsivo: auto-fill com colunas mínimas de 300px -->
    <!-- auto-fill cria quantas colunas couberem na largura disponível -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
        <?php foreach ($especializacoes as $esp): ?>
            <!-- Card individual de especialização com efeito glassmorphism -->
            <div class="card-white" style="border-radius: 16px; 
padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s ease; background: rgba(255,255,255,.85);">
                <!-- Cabeçalho do card: nome e descrição -->
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                    <div>
                        <!-- Nome da especialização (ex: "Terapia Cognitivo-Comportamental") -->
                        <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 8px;">
                            <?php echo htmlspecialchars($esp['nome']); ?>
                        </h3>
                        <!-- Descrição da especialização ou texto padrão se não houver -->
                        <!-- O operador ?? retorna o segundo valor se o primeiro for null -->
                        <p style="font-size: 14px; color: #6b7280;">
                            <?php echo htmlspecialchars($esp['descricao'] ?? 'Sem descrição'); ?>
                        </p>
                    </div>
                </div>

                <!-- Destaque do preço atual em roxo -->
                <div style="background: #f3f4f6; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                    <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Valor da Consulta</div>
                    <div style="font-size: 28px; font-weight: 800; color: #6366f1;">
                        <!-- number_format: 2 casas decimais, vírgula como separador decimal, ponto como separador de milhar -->
                        R$ <?php echo number_format($esp['preco'], 2, ',', '.'); ?>
                    </div>
                </div>

                <!-- Botão que abre o modal de edição de preço -->
                <!-- Passa id, nome e preço atual como argumentos para o JavaScript -->
                <!-- addslashes() escapa aspas simples no nome para não quebrar o JS -->
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-primary" style="flex: 1;" onclick="editarEspecializacao(<?php echo $esp['id_especializacao']; ?>, '<?php echo htmlspecialchars(addslashes($esp['nome'])); ?>', <?php echo $esp['preco']; ?>)">
                        Editar Preço
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL DE EDIÇÃO DE PREÇO
     Janela flutuante para alterar o preço de uma especialização
═══════════════════════════════════════════════════════════ -->
<!-- O modal é oculto por padrão e exibido pela função editarEspecializacao() -->
<div id="modalEspecializacao" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Editar Preço da Especialidade</h2>
            <!-- Botão X para fechar o modal -->
            <button class="modal-fechar" onclick="fecharModalEspecializacao()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Nome da especialização selecionada (preenchido pelo JavaScript) -->
            <p id="nomeEspecializacaoModal" style="font-size:15px; font-weight:600; color:#374151; margin-bottom:16px;"></p>
            <!-- Formulário enviado via POST para acao=atualizar_preco no controlador -->
            <form method="POST" id="formEspecializacao">
                <!-- Campo oculto: identifica a ação para o PHP -->
                <input type="hidden" name="acao" value="atualizar_preco">
                <!-- Campo oculto: ID da especialização (preenchido pelo JavaScript) -->
                <input type="hidden" name="id_especializacao" id="idEspecializacao">

                <!-- Campo somente leitura com o preço atual (apenas visual) -->
                <div class="form-group">
                    <label>Preço Atual (R$)</label>
                    <!-- disabled: não pode ser editado nem enviado no formulário -->
                    <input type="text" id="precoAtualDisplay" disabled style="background:#f3f4f6; color:#6b7280;">
                </div>

                <!-- Campo para digitar o novo preço -->
                <div class="form-group">
                    <label>Novo Valor (R$)</label>
                    <!-- step="0.01": aceita centavos | min="0": não permite valores negativos -->
                    <input type="number" name="novo_preco" id="novoPreco" step="0.01" min="0" required placeholder="0,00">
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
    /*
     * Abre o modal de edição de preço preenchendo os dados da especialização.
     * Chamada pelo botão "Editar Preço" em cada card.
     *
     * @param {number} id    - ID da especialização
     * @param {string} nome  - Nome da especialização (para exibição)
     * @param {number} preco - Preço atual da especialização
     */
    function editarEspecializacao(id, nome, preco) {
        // Preenche o campo oculto com o ID da especialização
        document.getElementById('idEspecializacao').value = id;
        // Exibe o nome da especialização no modal
        document.getElementById('nomeEspecializacaoModal').textContent = 'Especialidade: ' + nome;
        // Exibe o preço atual formatado (ex: "R$ 150,00")
        // toFixed(2) garante 2 casas decimais; replace('.', ',') usa vírgula brasileira
        document.getElementById('precoAtualDisplay').value = 'R$ ' + parseFloat(preco).toFixed(2).replace('.', ',');
        // Limpa o campo de novo preço para o usuário digitar
        document.getElementById('novoPreco').value = '';
        // Exibe o modal adicionando a classe 'show'
        document.getElementById('modalEspecializacao').classList.add('show');
    }

    /*
     * Fecha o modal de edição de preço.
     * Chamada pelo botão X ou pelo botão "Cancelar".
     */
    function fecharModalEspecializacao() {
        document.getElementById('modalEspecializacao').classList.remove('show');
    }

    // Fecha o modal ao clicar fora da área de conteúdo (no overlay escuro)
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('modalEspecializacao');
        // event.target === modal: o clique foi no overlay, não no conteúdo do modal
        if (modal && event.target === modal) {
            modal.classList.remove('show');
        }
    });
</script>
