/**
 * ARQUIVO: js/dashboard_psicologa.js
 * DESCRIÇÃO: Script JavaScript do dashboard da psicóloga.
 * Responsável por:
 * - Inicializar e configurar o calendário FullCalendar com as consultas
 * - Configurar o modo escuro (dark mode) com persistência via localStorage
 * - Auto-fechar mensagens de alerta após 5 segundos
 * - Animar os elementos do dashboard ao carregar a página
 *
 * Dashboard Psicóloga - JavaScript
 * Nexus Premium SaaS
 */

/* Variável global que armazena a instância do calendário da psicóloga.
   Declarada fora das funções para ser acessível em todo o arquivo. */
let calendarPsicologa = null;

/* Aguarda o carregamento completo do DOM antes de executar.
   Garante que os elementos HTML existam antes de o JS tentar manipulá-los. */
document.addEventListener('DOMContentLoaded', function() {
    inicializarCalendarioPsicologa();
    // Inicializa o calendário de consultas da psicóloga
    configurarModoEscuro();
    // Configura o botão de alternância de tema claro/escuro
});

/* Função: inicializarCalendarioPsicologa
   Cria e renderiza o calendário interativo usando a biblioteca FullCalendar.
   O calendário exibe as consultas da psicóloga buscadas via API. */
function inicializarCalendarioPsicologa() {
    const calendarEl = document.getElementById('calendar');
    // Busca o elemento HTML onde o calendário será renderizado (div com id="calendar")

    if (!calendarEl || typeof FullCalendar === 'undefined') return;
    // Se o elemento não existe OU a biblioteca FullCalendar não foi carregada, encerra a função
    // Isso evita erros em páginas que não possuem o calendário

    if (calendarEl.dataset.inicializado === '1') return;
    // Verifica se o calendário já foi inicializado neste elemento
    // Evita criar múltiplas instâncias do calendário no mesmo elemento

    calendarEl.dataset.inicializado = '1';
    // Marca o elemento como inicializado para evitar duplicação

    /* Cria uma nova instância do calendário FullCalendar com configurações personalizadas */
    calendarPsicologa = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        // Vista inicial: grade mensal (exibe o mês inteiro com os dias em grade)

        headerToolbar: {
            left: 'prev,next today',
            // Botões à esquerda: navegar para mês anterior, próximo e voltar para hoje
            center: 'title',
            // Centro: título com o nome do mês e ano atual
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
            // Botões à direita: alternar entre vista mensal, semanal e diária
        },

        locale: 'pt-br',
        // Define o idioma do calendário como Português do Brasil
        // (nomes dos meses, dias da semana, etc.)

        buttonText: {
            // Traduz os textos dos botões de navegação para português
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            day: 'Dia'
        },

        height: 'auto',
        // Altura do calendário se ajusta automaticamente ao conteúdo
        contentHeight: 'auto',
        // Altura do conteúdo interno também automática
        expandRows: true,
        // As linhas da grade se expandem para preencher o espaço disponível

        events: 'api/consultas_psicologa.php',
        // URL da API que retorna os eventos (consultas) em formato JSON
        // O FullCalendar fará uma requisição GET para este endpoint automaticamente

        displayEventTime: false,
        // Não exibe o horário junto ao título do evento no calendário

        /* Callback chamado quando um evento é montado (adicionado ao DOM do calendário) */
        eventDidMount: function(info) {
            const props = info.event.extendedProps;
            // extendedProps: propriedades extras do evento retornadas pela API (além das padrão)
            info.el.title = (props.paciente || '') + ' | ' + (props.especializacao || '') + ' | ' + (props.status || '');
            // Define o tooltip (texto ao passar o mouse) do evento com:
            // nome do paciente | especialização | status da consulta
            // O operador || '' garante que, se a propriedade for nula, use string vazia
        },

        /* Callback chamado quando o usuário clica em um evento no calendário */
        eventClick: function(info) {
            const event = info.event;
            // O objeto do evento clicado
            const props = event.extendedProps;
            // Propriedades extras do evento (paciente, especialização, status, pagamento)

            const data = event.start ? event.start.toLocaleDateString('pt-BR') : '';
            // Formata a data de início do evento no padrão brasileiro (DD/MM/AAAA)
            // O operador ternário garante que, se não houver data, use string vazia

            const hora = event.start ? event.start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
            // Formata o horário no padrão HH:MM

            alert(
                // Exibe uma caixa de alerta nativa com os detalhes da consulta
                'Paciente: ' + (props.paciente || 'Não informado') + '\n' +
                'Especialidade: ' + (props.especializacao || 'Não informada') + '\n' +
                'Data/Hora: ' + data + ' ' + hora + '\n' +
                'Status: ' + (props.status || 'Não informado') + '\n' +
                'Pagamento: ' + (props.pagamento || 'Não informado')
                // '\n' cria uma nova linha dentro da caixa de alerta
            );
        }
    });

    calendarPsicologa.render();
    // Renderiza (desenha) o calendário no elemento HTML

    setTimeout(function() {
        // Após 150ms, renderiza e atualiza o tamanho novamente
        if (calendarPsicologa) {
            calendarPsicologa.render();
            // Segunda renderização para garantir que o calendário apareça corretamente
            // (às vezes a primeira renderização ocorre antes do layout estar estável)
            calendarPsicologa.updateSize();
            // Força o recálculo do tamanho do calendário para se ajustar ao container
        }
    }, 150);
    // 150ms: tempo suficiente para o layout CSS estabilizar após a renderização inicial
}

/* Função: configurarModoEscuro
   Configura o botão de alternância entre tema claro e escuro.
   A preferência do usuário é salva no localStorage para persistir
   entre sessões (o usuário não precisa reconfigurar a cada visita). */
function configurarModoEscuro() {
    const btnDarkMode = document.getElementById('btn-dark-mode');
    // Busca o botão de alternância de tema pelo id
    if (!btnDarkMode) return;
    // Se o botão não existir na página atual, encerra a função

    // Carregar preferência salva
    if (localStorage.getItem('darkMode') === 'true') {
        // localStorage.getItem: lê o valor salvo no armazenamento local do navegador
        // Se o usuário havia ativado o modo escuro anteriormente:
        document.body.classList.add('dark-mode');
        // Adiciona a classe 'dark-mode' ao <body>, ativando os estilos de tema escuro via CSS
    }

    btnDarkMode.addEventListener('click', function() {
        // Ao clicar no botão de modo escuro:
        document.body.classList.toggle('dark-mode');
        // toggle: adiciona 'dark-mode' se não tiver, remove se tiver
        localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        // Salva o estado atual (true/false) no localStorage para persistir a preferência
        // localStorage.setItem: armazena um par chave-valor no navegador
    });
}

// Auto-fechar alertas após 5 segundos
/* Busca todas as mensagens de alerta e as faz desaparecer automaticamente
   após 5 segundos, com uma animação de fade out (opacidade 0 → display none) */
document.addEventListener('DOMContentLoaded', function() {
    const alertas = document.querySelectorAll('.alerta');
    // Seleciona todos os elementos com a classe 'alerta' (mensagens de sucesso, erro, etc.)

    alertas.forEach(function(alerta) {
        // Para cada alerta encontrado:
        setTimeout(function() {
            // Aguarda 5000ms (5 segundos) antes de iniciar o fade out
            alerta.style.opacity = '0';
            // Inicia a animação de desaparecimento (opacidade vai para 0)
            alerta.style.transition = 'opacity 0.3s ease';
            // Define a transição CSS: a mudança de opacidade dura 0.3 segundos com easing suave

            setTimeout(function() {
                alerta.style.display = 'none';
                // Após a animação de fade out (0.3s), remove o elemento do fluxo do layout
                // display: none faz o elemento desaparecer completamente (sem ocupar espaço)
            }, 300);
            // 300ms = duração da transição de opacidade
        }, 5000);
        // 5000ms = 5 segundos de exibição antes de começar o fade out
    });
});

// Animações ao carregar
/* Anima os elementos principais do dashboard com um efeito de "subida"
   (translateY de 20px para 0) e fade in (opacidade 0 para 1) ao carregar a página.
   Cada elemento tem um atraso progressivo para criar um efeito cascata. */
window.addEventListener('load', function() {
    // 'load': disparado quando TUDO foi carregado (HTML, CSS, imagens, scripts)
    // Diferente de DOMContentLoaded, que dispara antes das imagens carregarem

    document.querySelectorAll('.widget, .secao, .calendario-secao').forEach(function(el, index) {
        // Seleciona todos os widgets, seções e a seção do calendário do dashboard
        // index: posição do elemento na lista (0, 1, 2, ...) - usado para o atraso progressivo

        el.style.opacity = '0';
        // Define opacidade inicial como 0 (invisível)
        el.style.transform = 'translateY(20px)';
        // Desloca o elemento 20px para baixo da posição final
        el.style.transition = 'all 0.3s ease ' + (index * 0.08) + 's';
        // Define a animação: duração 0.3s, com atraso progressivo de 0.08s por elemento
        // Ex: 1º elemento: 0s, 2º: 0.08s, 3º: 0.16s, etc. (efeito cascata)

        setTimeout(function() {
            el.style.opacity = '1';
            // Torna o elemento visível
            el.style.transform = 'translateY(0)';
            // Move o elemento para a posição original (sem deslocamento)
        }, 10);
        // Pequeno atraso de 10ms para garantir que o estado inicial (opacity:0) seja aplicado
        // antes de iniciar a animação para o estado final
    });
});
