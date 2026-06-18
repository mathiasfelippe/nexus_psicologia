<!--
    ARQUIVO: views/dashboard_paciente_notificacoes.php
    DESCRIÇÃO: View da aba "Notificações" do dashboard do paciente.

    Este arquivo é incluído pelo dashboard_paciente.php quando a aba 'notificacoes'
    está ativa. Funciona de forma idêntica à view de notificações da psicóloga:
    usa JavaScript com Fetch API para carregar os dados de forma assíncrona (AJAX)
    a partir do endpoint api/notificacoes.php.

    FLUXO:
      1. A página carrega com estado de "Carregando..."
      2. DOMContentLoaded dispara notif.init()
      3. init() chama recarregar() que faz fetch para api/notificacoes.php
      4. Os dados JSON retornados são renderizados dinamicamente no container

    AÇÕES AJAX (via api/notificacoes.php):
      - GET                     → Lista todas as notificações do paciente logado
      - POST marcar_lida        → Marca uma notificação como lida
      - POST marcar_todas_lidas → Marca todas como lidas
      - POST excluir            → Exclui uma notificação
-->

<!-- ═══════════════════════════════════════════════════════════
     ESTRUTURA HTML DA ABA DE NOTIFICAÇÕES DO PACIENTE
═══════════════════════════════════════════════════════════ -->
<div class="secao">
    <!-- Cabeçalho com título e botões de ação -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin-bottom: 0;">Notificacoes</h2>
        <div class="notificacoes-header-acoes" style="display: flex; gap: 8px;">
            <!-- Botão "Marcar todas como lidas": oculto por padrão (display:none)
                 Exibido pelo JavaScript quando há notificações não lidas -->
            <button class="btn btn-pequeno btn-secondary" onclick="notif.marcarTodasLidas()" id="btn-marcar-todas" style="display: none;">
                Marcar todas como lidas
            </button>
            <!-- Botão de atualização manual: força o recarregamento das notificações -->
            <!-- O parâmetro 'true' em recarregar(true) também atualiza o badge de contagem -->
            <button class="btn btn-pequeno btn-secondary" onclick="notif.recarregar(true)" title="Atualizar">
                <!-- Ícone de seta circular (refresh) em SVG -->
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                Atualizar
            </button>
        </div>
    </div>

    <!-- Container onde as notificações serão injetadas pelo JavaScript -->
    <!-- Começa com estado de carregamento; substituído pelo JS após o fetch -->
    <div id="notificacoes-container">
        <div class="vazio-container">
            <!-- Ícone de sino (notificação) em SVG -->
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <p>Carregando notificacoes...</p>
        </div>
    </div>
</div>

<script>
/*
 * OBJETO notif
 * Gerencia todas as operações de notificações desta view do paciente.
 * Usa o padrão de objeto literal para encapsular estado e métodos relacionados.
 * (Idêntico ao da view da psicóloga — ambos consomem o mesmo endpoint)
 */
var notif = {
    container: null,       // Referência ao elemento #notificacoes-container
    btnMarcarTodas: null,  // Referência ao botão "Marcar todas como lidas"

    /*
     * Inicializa o módulo de notificações.
     * Chamado pelo evento DOMContentLoaded (quando o HTML está pronto).
     */
    init: function() {
        this.container = document.getElementById('notificacoes-container');
        this.btnMarcarTodas = document.getElementById('btn-marcar-todas');
        // Carrega as notificações ao inicializar
        this.recarregar();
    },

    /*
     * Carrega (ou recarrega) as notificações via AJAX.
     * Faz uma requisição GET para api/notificacoes.php e renderiza o resultado.
     *
     * @param {boolean} forcar - Se true, também atualiza o badge de contagem
     */
    recarregar: function(forcar) {
        var self = this;
        // Exibe estado de carregamento enquanto aguarda a resposta
        self.container.innerHTML = '<div class="vazio-container"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><p>Carregando...</p></div>';
        // Fetch API: faz requisição GET assíncrona para o endpoint de notificações
        fetch('api/notificacoes.php')
            .then(function(r) { return r.json(); }) // Converte a resposta para JSON
            .then(function(data) {
                // Renderiza as notificações recebidas
                self.renderizar(data.notificacoes, data.nao_lidas);
                // Se forçado, atualiza o badge de contagem na sidebar
                if (forcar) self.atualizarBadge(data.nao_lidas);
            })
            .catch(function() {
                // Exibe mensagem de erro se a requisição falhar
                self.container.innerHTML = '<div class="vazio-container"><p>Erro ao carregar notificacoes.</p></div>';
            });
    },

    /*
     * Renderiza a lista de notificações no container.
     * Constrói o HTML dinamicamente e injeta no DOM.
     *
     * @param {Array}  lista    - Array de objetos de notificação
     * @param {number} naoLidas - Quantidade de notificações não lidas
     */
    renderizar: function(lista, naoLidas) {
        var self = this;
        // Se não há notificações, exibe estado vazio
        if (!lista || lista.length === 0) {
            this.container.innerHTML = '<div class="vazio-container"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><p>Voce nao tem notificacoes no momento.</p></div>';
            this.btnMarcarTodas.style.display = 'none';
            return;
        }

        // Exibe o botão "Marcar todas como lidas" apenas se houver não lidas
        this.btnMarcarTodas.style.display = naoLidas > 0 ? 'inline-flex' : 'none';

        // Constrói o HTML da lista de notificações
        var html = '<div class="notificacoes-lista">';
        for (var i = 0; i < lista.length; i++) {
            var n = lista[i];
            // Classe CSS: 'lida' ou 'nao-lida' (n.lida == 1 significa lida)
            var lidaClass = n.lida == 1 ? 'lida' : 'nao-lida';
            // Obtém o ícone SVG correspondente ao tipo da notificação
            var icone = self.getIcone(n.tipo);
            // Obtém o rótulo legível do tipo (ex: 'confirmacao' → 'Consulta Confirmada')
            var tipoLabel = self.getTipoLabel(n.tipo);
            // Formata a data/hora de criação como texto relativo (ex: "2 horas atrás")
            var tempo = self.formatarTempo(n.data_criacao);

            // Monta o card da notificação com data-id para referência futura
            html += '<div class="notificacao-item ' + lidaClass + ' ' + n.tipo + '" data-id="' + n.id_notificacao + '">';
            html += '<div class="notificacao-icone">' + icone + '</div>';
            html += '<div class="notificacao-conteudo">';
            // esc() escapa o texto para prevenir XSS (injeção de HTML malicioso)
            html += '<h4>' + self.esc(tipoLabel) + '</h4>';
            html += '<p>' + self.esc(n.mensagem) + '</p>';
            html += '<span class="notificacao-data">' + tempo + '</span>';
            html += '</div>';
            html += '<div class="notificacao-acoes" style="display: flex; gap: 4px; flex-shrink: 0; align-items: center;">';
            // Botão "Ler" exibido apenas para notificações não lidas
            if (n.lida == 0) {
                html += '<button class="btn btn-pequeno" style="padding: 4px 8px; font-size: 10px;" onclick="notif.marcarLida(' + n.id_notificacao + ', this)">Ler</button>';
            }
            // Botão de exclusão (ícone de lixeira) sempre visível
            html += '<button class="btn btn-pequeno" style="padding: 4px 8px; font-size: 10px; background: transparent; border: 1px solid var(--danger); color: var(--danger);" onclick="notif.excluir(' + n.id_notificacao + ', this)">';
            // Ícone de lixeira em SVG
            html += '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
            html += '</button>';
            html += '</div>';
            html += '</div>';
        }
        html += '</div>';
        // Injeta o HTML construído no container
        this.container.innerHTML = html;
    },

    /*
     * Marca uma notificação individual como lida via AJAX.
     * Atualiza visualmente o item sem recarregar a página.
     *
     * @param {number} id  - ID da notificação
     * @param {HTMLElement} btn - Botão "Ler" que foi clicado (para remover após ação)
     */
    marcarLida: function(id, btn) {
        var self = this;
        // FormData: formato de dados para envio via POST
        var formData = new FormData();
        formData.append('acao', 'marcar_lida');
        formData.append('id_notificacao', id);

        fetch('api/notificacoes.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.sucesso) {
                    // Encontra o card da notificação pelo atributo data-id
                    var item = document.querySelector('.notificacao-item[data-id="' + id + '"]');
                    if (item) {
                        // Troca a classe visual de 'nao-lida' para 'lida'
                        item.classList.remove('nao-lida');
                        item.classList.add('lida');
                        // Remove o botão "Ler" (não é mais necessário)
                        if (btn) btn.remove();
                    }
                    // Decrementa o badge de contagem em 1
                    self.atualizarBadge(self._getBadgeValue() - 1);
                }
            })
            .catch(function(err) { console.error('Erro:', err); });
    },

    /*
     * Marca todas as notificações como lidas via AJAX.
     * Atualiza visualmente todos os itens sem recarregar a página.
     */
    marcarTodasLidas: function() {
        var self = this;
        var formData = new FormData();
        formData.append('acao', 'marcar_todas_lidas');

        fetch('api/notificacoes.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.sucesso) {
                    // Percorre todos os itens não lidos e os marca como lidos
                    document.querySelectorAll('.notificacao-item.nao-lida').forEach(function(item) {
                        item.classList.remove('nao-lida');
                        item.classList.add('lida');
                        // Remove o botão "Ler" de cada item
                        var btn = item.querySelector('button[onclick*="marcarLida"]');
                        if (btn) btn.remove();
                    });
                    // Oculta o botão "Marcar todas como lidas"
                    self.btnMarcarTodas.style.display = 'none';
                    // Zera o badge de contagem
                    self._atualizarBadgeNum(0);
                }
            })
            .catch(function(err) { console.error('Erro:', err); });
    },

    /*
     * Exclui uma notificação via AJAX com animação de saída.
     * Pede confirmação antes de excluir.
     *
     * @param {number} id  - ID da notificação
     * @param {HTMLElement} btn - Botão de exclusão clicado
     */
    excluir: function(id, btn) {
        // Confirmação nativa do navegador antes de excluir
        if (!confirm('Tem certeza que deseja excluir esta notificacao?')) return;
        var self = this;
        var formData = new FormData();
        formData.append('acao', 'excluir');
        formData.append('id_notificacao', id);

        fetch('api/notificacoes.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.sucesso) {
                    var item = document.querySelector('.notificacao-item[data-id="' + id + '"]');
                    if (item) {
                        // Animação de saída: fade out + deslize para a direita
                        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(30px)';
                        // Remove o elemento do DOM após a animação (300ms)
                        setTimeout(function() { item.remove(); self.verificarVazio(); }, 300);
                    }
                    // Decrementa o badge apenas se o item excluído era não lido
                    self.atualizarBadge(self._getBadgeValue() - (item && item.classList.contains('nao-lida') ? 1 : 0));
                }
            })
            .catch(function(err) { console.error('Erro:', err); });
    },

    /*
     * Verifica se o container ficou vazio após uma exclusão.
     * Se sim, exibe o estado vazio com ícone e mensagem.
     */
    verificarVazio: function() {
        var items = this.container.querySelectorAll('.notificacao-item');
        if (items.length === 0) {
            this.container.innerHTML = '<div class="vazio-container"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><p>Voce nao tem notificacoes no momento.</p></div>';
            this.btnMarcarTodas.style.display = 'none';
        }
    },

    /*
     * Lê o valor atual do badge de contagem de notificações.
     * Retorna 0 se o badge não existir.
     *
     * @returns {number} Valor atual do badge
     */
    _getBadgeValue: function() {
        var badge = document.querySelector('.badge');
        // parseInt com base 10; retorna 0 se não for um número válido
        return badge ? parseInt(badge.textContent, 10) || 0 : 0;
    },

    /*
     * Atualiza o badge de contagem garantindo que não seja negativo.
     *
     * @param {number} total - Novo valor do badge
     */
    atualizarBadge: function(total) {
        // Math.max(0, total) garante que o badge nunca mostre valor negativo
        this._atualizarBadgeNum(Math.max(0, total));
    },

    /*
     * Atualiza todos os badges de notificação na página.
     * Oculta o badge quando o total é zero.
     *
     * @param {number} total - Valor a exibir no badge
     */
    _atualizarBadgeNum: function(total) {
        // Atualiza todos os badges (sidebar + cabeçalho)
        var badges = document.querySelectorAll('.badge');
        badges.forEach(function(b) {
            b.textContent = total;
            // Exibe como inline-flex se > 0, oculta se = 0
            b.style.display = total > 0 ? 'inline-flex' : 'none';
        });
    },

    /*
     * Retorna o ícone SVG correspondente ao tipo de notificação.
     * Usa um objeto como mapa (tipo → SVG).
     *
     * @param {string} tipo - Tipo da notificação
     * @returns {string} HTML do ícone SVG
     */
    getIcone: function(tipo) {
        // Mapa de tipos para ícones SVG inline
        var map = {
            'agendamento':         '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
            'nova_consulta':       '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>',
            'confirmacao':         '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            'cancelamento':        '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
            'reagendamento':       '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
            'pagamento':           '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>',
            'pagamento_aprovado':  '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line><path d="M9 12l2 2 4-4"></path></svg>',
            'pagamento_recusado':  '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>',
            'consulta_concluida':  '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            'lembrete':            '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>',
            'comentario_psicologa':'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>'
        };
        // Retorna o ícone do mapa ou um ícone de informação genérico como fallback
        return map[tipo] || '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
    },

    /*
     * Retorna o rótulo legível para um tipo de notificação.
     *
     * @param {string} tipo - Tipo da notificação (chave interna do sistema)
     * @returns {string} Rótulo amigável para exibição
     */
    getTipoLabel: function(tipo) {
        var map = {
            'nova_consulta':        'Nova Consulta',
            'agendamento':          'Consulta Agendada',
            'confirmacao':          'Consulta Confirmada',
            'cancelamento':         'Consulta Cancelada',
            'reagendamento':        'Consulta Reagendada',
            'pagamento':            'Pagamento Confirmado',
            'pagamento_aprovado':   'Pagamento Aprovado',
            'pagamento_recusado':   'Pagamento Recusado',
            'consulta_concluida':   'Consulta Concluida',
            'alteracao_data':       'Alteracao de Data',
            'alteracao_horario':    'Alteracao de Horario',
            'lembrete':             'Lembrete',
            'comentario_psicologa': 'Recado da Psicologa'
        };
        // Retorna o rótulo ou 'Notificacao' como fallback
        return map[tipo] || 'Notificacao';
    },

    /*
     * Formata uma data/hora como texto relativo ao momento atual.
     * Ex: "2 mins atrás", "3 horas atrás", "Ontem às 14:30", "15/06/2025 às 10:00"
     *
     * @param {string} dataCriacao - Data no formato 'YYYY-MM-DD HH:MM:SS'
     * @returns {string} Texto formatado
     */
    formatarTempo: function(dataCriacao) {
        if (!dataCriacao) return '';
        // split(/[\s:-]/) divide a string pelos separadores: espaço, dois-pontos, hífen
        // Ex: '2025-06-15 14:30:00' → ['2025', '06', '15', '14', '30', '00']
        var partes = dataCriacao.split(/[\s:-]/);
        // Cria um objeto Date: mês é 0-indexado (partes[1]-1)
        var data = new Date(partes[0], partes[1]-1, partes[2], partes[3]||0, partes[4]||0, partes[5]||0);
        var agora = new Date();
        var diffMs = agora - data;                          // Diferença em milissegundos
        var diffMin = Math.floor(diffMs / 60000);           // Diferença em minutos
        var diffHoras = Math.floor(diffMs / 3600000);       // Diferença em horas
        var diffDias = Math.floor(diffMs / 86400000);       // Diferença em dias

        if (diffMin < 1) return 'Agora mesmo';
        if (diffMin < 60) return diffMin + ' min' + (diffMin > 1 ? 's' : '') + ' atras';
        if (diffHoras < 24) return diffHoras + ' hora' + (diffHoras > 1 ? 's' : '') + ' atras';
        if (diffDias === 1) return 'Ontem as ' + data.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
        // Para datas mais antigas: formato completo "dd/mm/aaaa às HH:MM"
        return data.toLocaleDateString('pt-BR') + ' as ' + data.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
    },

    /*
     * Escapa caracteres HTML especiais para prevenir XSS.
     * Converte o texto em nó de texto e extrai o HTML resultante.
     *
     * @param {string} str - String a ser escapada
     * @returns {string} String com caracteres HTML escapados
     */
    esc: function(str) {
        // Cria um elemento div temporário
        var div = document.createElement('div');
        // createTextNode() trata a string como texto puro (escapa automaticamente)
        div.appendChild(document.createTextNode(str));
        // innerHTML retorna o texto com os caracteres especiais escapados
        return div.innerHTML;
    }
};

// Inicializa o módulo de notificações quando o DOM estiver completamente carregado
document.addEventListener('DOMContentLoaded', function() { notif.init(); });
</script>
