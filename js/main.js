/* ============================================================
   ARQUIVO: js/main.js
   DESCRIÇÃO: Script JavaScript principal do site Nexus Psicologia.
   Responsável por todas as interações e animações da página:
   1. Efeito de scroll na navbar (muda aparência ao rolar)
   2. Menu hambúrguer para dispositivos móveis
   3. Animação de "reveal" (elementos aparecem ao entrar na tela)
   4. Marcação do link ativo na navbar conforme a seção visível
   5. Carrossel de especializações com scroll horizontal
   6. Scroll suave ao clicar em links âncora (#)
   ============================================================ */

/* Função principal que inicializa todos os comportamentos do site.
   É chamada após o DOM estar completamente carregado. */
function initNexus() {

  /* ── 1. Navbar ──
     Adiciona a classe 'scrolled' à navbar quando o usuário
     rola a página mais de 40px. Isso permite que o CSS
     altere a aparência da navbar (ex: fundo sólido, sombra). */
  const navbar = document.getElementById('navbar');
  // Busca o elemento HTML com id="navbar" e armazena na variável

  /* Função chamada toda vez que o usuário rola a página */
  function onScroll() {
    if (!navbar) return;
    // Se a navbar não existir na página, sai da função sem fazer nada

    navbar.classList.toggle('scrolled', window.scrollY > 40);
    // toggle: adiciona 'scrolled' se scrollY > 40, remove se não for
    // window.scrollY: quantos pixels o usuário rolou verticalmente
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  // Escuta o evento de scroll na janela inteira
  // { passive: true }: melhora a performance pois informa ao navegador que não vamos chamar preventDefault()
  onScroll();
  // Chama imediatamente ao carregar para verificar o estado inicial (caso a página já esteja rolada)


  /* ── 2. Menu mobile ──
     Controla a abertura/fechamento do menu hambúrguer
     em dispositivos móveis. Ao clicar no botão hambúrguer,
     o menu mobile é exibido ou ocultado. */
  const hamburger = document.getElementById('hamburger');
  // Botão hambúrguer (três linhas) visível apenas em telas pequenas
  const mobileMenu = document.getElementById('mobile-menu');
  // Lista de links do menu mobile

  if (hamburger && mobileMenu) {
    // Só executa se ambos os elementos existirem no DOM

    hamburger.addEventListener('click', () => {
      // Ao clicar no hambúrguer:
      const open = hamburger.classList.toggle('open');
      // toggle retorna true se adicionou 'open', false se removeu
      // A classe 'open' no hambúrguer anima as 3 linhas em um "X"
      mobileMenu.classList.toggle('open', open);
      // Sincroniza o estado do menu com o estado do botão
      hamburger.setAttribute('aria-expanded', String(open));
      // Atualiza o atributo de acessibilidade: informa leitores de tela se o menu está aberto
    });

    mobileMenu.querySelectorAll('a').forEach(link => {
      // Para cada link dentro do menu mobile:
      link.addEventListener('click', () => {
        // Ao clicar em qualquer link do menu mobile, fecha o menu automaticamente
        hamburger.classList.remove('open');
        mobileMenu.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
        // Garante que o menu feche e o atributo de acessibilidade seja atualizado
      });
    });
  }


  /* ── 3. Scroll reveal ──
     Anima elementos com a classe 'reveal' quando eles
     entram na área visível da tela (viewport).
     Usa a API IntersectionObserver para monitorar
     a visibilidade dos elementos de forma eficiente. */
  const revealEls = document.querySelectorAll('.reveal');
  // Seleciona todos os elementos que têm a classe 'reveal' na página

  /* O IntersectionObserver monitora quando os elementos entram na tela para disparar a animação */
  if ('IntersectionObserver' in window) {
    // Verifica se o navegador suporta a API IntersectionObserver

    const observer = new IntersectionObserver(entries => {
      // Cria um novo observador que recebe uma lista de "entradas" (elementos monitorados)
      entries.forEach(entry => {
        // Para cada elemento monitorado:
        if (entry.isIntersecting) {
          // Se o elemento está visível na tela:
          entry.target.classList.add('visible');
          // Adiciona a classe 'visible', que o CSS usa para disparar a animação de entrada
          observer.unobserve(entry.target); // Remove o monitoramento após animar (otimiza performance)
          // Após animar, para de monitorar o elemento (não precisa mais observar)
        }
      });
    }, { threshold: 0.12 }); // Dispara quando 12% do elemento estiver visível

    revealEls.forEach(el => observer.observe(el));
    // Começa a monitorar cada elemento com classe 'reveal'
  } else {
    // Fallback: se o navegador for muito antigo e não suportar o Observer, mostra tudo direto
    revealEls.forEach(el => el.classList.add('visible'));
    // Adiciona 'visible' em todos de uma vez, sem animação
  }


  /* ── 4. Link ativo navbar ──
     Destaca o link da navbar correspondente à seção
     que está atualmente visível na tela enquanto o
     usuário rola a página. */
  /* Atualizado de '.navbar__links' para '.navbar-links' */
  const navLinks = document.querySelectorAll('.navbar-links a');
  // Seleciona todos os links dentro da lista de navegação da navbar
  const sections = document.querySelectorAll('section[id]');
  // Seleciona todas as seções que possuem um id (ex: #home, #about, #specializations)

  if (navLinks.length && sections.length) {
    // Só executa se existirem links e seções na página

    /* Este Observer altera a classe 'active' no menu baseado na seção que está cruzando a tela */
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          // Se a seção está cruzando a área de detecção:
          navLinks.forEach(link => {
            link.classList.toggle(
              'active',
              // Adiciona 'active' se o href do link corresponde ao id da seção visível
              link.getAttribute('href') === `#${entry.target.id}`
              // Compara o href do link (ex: "#about") com o id da seção (ex: "about")
            );
          });
        }
      });
    }, { rootMargin: '-40% 0px -55% 0px' }); // Define a "linha de corte" virtual na tela para a ativação
    // rootMargin: cria uma margem virtual. A seção é considerada "ativa" quando está
    // entre 40% do topo e 55% do fundo da janela (zona central da tela)

    sections.forEach(sec => observer.observe(sec));
    // Começa a monitorar cada seção
  }


  /* ── 5. CARROSSEL ──
     Permite rolar a grade de especializações horizontalmente
     usando a roda do mouse (scroll vertical converte em
     scroll horizontal na grade). */

  /* ── 5. CARROSSEL (scroll nativo) ── */

const grid = document.getElementById('spec-grid');
// Busca a grade de cards de especializações

if (grid) {
// Só executa se o elemento existir na página

  // Scroll horizontal com roda do mouse (opcional)
  grid.addEventListener('wheel', (e) => {
    // Escuta o evento de roda do mouse sobre a grade
    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
      // Se o scroll é predominantemente vertical (e não diagonal):
      e.preventDefault();
      // Impede o scroll vertical padrão da página
      grid.scrollLeft += e.deltaY;
      // Converte o scroll vertical em scroll horizontal na grade
      // deltaY: quantidade de scroll vertical; scrollLeft: posição horizontal da grade
    }
  }, { passive: false });
  // { passive: false }: necessário para poder chamar e.preventDefault() dentro do handler

}


  /* ── 6. Smooth scroll ──
     Intercepta cliques em links âncora (href="#secao")
     e substitui o comportamento padrão de pulo abrupto
     por uma rolagem suave, descontando a altura da navbar
     para que o título da seção não fique escondido atrás dela. */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    // Seleciona todos os links cujo href começa com "#" (links âncora internos)
    anchor.addEventListener('click', e => {
      // Ao clicar em um link âncora:
      const id = anchor.getAttribute('href');
      // Obtém o valor do href (ex: "#about")
      if (!id || id === '#') return;
      // Se o href é vazio ou apenas "#", não faz nada

      const target = document.querySelector(id);
      // Busca o elemento alvo pelo id (ex: document.querySelector("#about"))
      if (!target) return;
      // Se o elemento não existir na página, não faz nada

      e.preventDefault();
      // Cancela o comportamento padrão do link (pulo abrupto)

      // Calcula a altura da navbar dinamicamente para o topo da seção não ficar escondido atrás dela
      const offset = navbar ? navbar.offsetHeight + 8 : 0;
      // navbar.offsetHeight: altura atual da navbar em pixels
      // + 8: margem extra para respiração visual

      window.scrollTo({
        top: target.getBoundingClientRect().top + window.scrollY - offset,
        // getBoundingClientRect().top: posição do elemento em relação ao topo da janela atual
        // + window.scrollY: converte para posição absoluta na página
        // - offset: desconta a altura da navbar
        behavior: 'smooth'
        // 'smooth': ativa a animação de rolagem suave do navegador
      });
    });
  });

}


/* ── INICIALIZAÇÃO ──
   Expõe a função initNexus globalmente e a chama
   quando o DOM estiver completamente carregado. */
window.initNexus = initNexus;
// Torna a função acessível globalmente (útil se outros scripts precisarem chamá-la)

document.addEventListener('DOMContentLoaded', () => {
  // DOMContentLoaded: evento disparado quando o HTML foi completamente carregado e analisado
  // (sem esperar imagens e estilos externos)
  if (!document.querySelector('[data-component]')) {
    // Verifica se a página não usa o sistema de componentes dinâmicos
    // (páginas com componentes carregados via JS têm elementos com data-component)
    initNexus();
    // Se for uma página simples (como index.html), inicializa tudo imediatamente
  }
});
