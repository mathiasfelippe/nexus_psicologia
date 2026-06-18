/**
 * ARQUIVO: js/dashboard_paciente.js
 * DESCRIÇÃO: Script JavaScript do dashboard do paciente.
 * Responsável por:
 * - Fechar o modal de pagamento ao clicar fora dele
 * - Redimensionar o calendário quando a janela muda de tamanho
 * - Cancelar uma consulta via envio de formulário dinâmico
 *
 * Dashboard Paciente Novo - JavaScript
 * Nexus Premium SaaS
 */

/* Variável global que armazena a instância do calendário (FullCalendar).
   Declarada aqui para ser acessível por outras funções do arquivo. */
let calendarInstance = null;

/* Aguarda o carregamento completo do DOM antes de executar qualquer código.
   Isso garante que todos os elementos HTML já existem quando o JS tenta acessá-los. */
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    // Chama a função que registra todos os ouvintes de eventos da página
});

/* Função: setupEventListeners
   Registra os ouvintes de eventos globais do dashboard do paciente.
   É chamada uma única vez ao carregar a página. */
function setupEventListeners() {
    document.addEventListener('click', function(event) {
        // Ouvinte de clique no documento inteiro (delegação de eventos)
        const modal = document.getElementById('modalPagamento');
        // Busca o modal de pagamento pelo seu id
        if (modal && event.target === modal) {
            // Se o modal existe E o clique foi diretamente no fundo escuro do modal
            // (e não em um elemento dentro dele):
            modal.classList.remove('show');
            // Remove a classe 'show', que o CSS usa para exibir o modal
            // Isso fecha o modal ao clicar fora da caixa de diálogo
        }
    });

    window.addEventListener('resize', function() {
        // Ouvinte de redimensionamento da janela do navegador
        if (calendarioPaciente) {
            // Se a variável do calendário do paciente existir (definida em outro arquivo/view):
            setTimeout(function() {
                calendarioPaciente.updateSize();
                // Força o calendário a recalcular seu tamanho após o redimensionamento
            }, 100);
            // setTimeout de 100ms: aguarda o layout estabilizar antes de atualizar o calendário
            // Isso evita cálculos incorretos durante o redimensionamento contínuo
        }
    });
}

/* Função: cancelarConsulta
   Exibe uma confirmação ao paciente e, se confirmado,
   envia um formulário POST para cancelar a consulta.
   
   Parâmetro:
   - idConsulta: o ID numérico da consulta a ser cancelada */
function cancelarConsulta(idConsulta) {
    if (confirm('Tem certeza que deseja cancelar esta consulta? O cancelamento so e permitido com pelo menos 24 horas de antecedencia.')) {
        // confirm(): exibe uma caixa de diálogo nativa do navegador com "OK" e "Cancelar"
        // Se o usuário clicar em "OK", o bloco abaixo é executado

        const form = document.createElement('form');
        // Cria um elemento <form> dinamicamente no JavaScript (não existe no HTML)
        form.method = 'POST';
        // Define o método HTTP como POST para enviar dados ao servidor

        form.innerHTML = `
            <input type="hidden" name="acao" value="cancelar_consulta">
            <input type="hidden" name="id_consulta" value="${idConsulta}">
        `;
        // Adiciona dois campos ocultos ao formulário:
        // - "acao": informa ao PHP qual ação executar ("cancelar_consulta")
        // - "id_consulta": o ID da consulta que será cancelada
        // type="hidden": campos invisíveis que enviam dados sem aparecer na tela

        document.body.appendChild(form);
        // Adiciona o formulário ao corpo da página (necessário para poder submetê-lo)

        form.submit();
        // Envia o formulário para o servidor (para a página atual, pois action não foi definido)
        // O PHP da página receberá os dados via $_POST e processará o cancelamento
    }
}
