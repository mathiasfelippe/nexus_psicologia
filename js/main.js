/* ============================================================
   js/main.js
   ============================================================ */

function initNexus() {

  /* ── 1. Navbar ── */
  const navbar = document.getElementById('navbar');

  function onScroll() {
    if (!navbar) return;
    navbar.classList.toggle('scrolled', window.scrollY > 40);
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();


  /* ── 2. Menu mobile ── */
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobile-menu');

  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      const open = hamburger.classList.toggle('open');
      mobileMenu.classList.toggle('open', open);
      hamburger.setAttribute('aria-expanded', String(open));
    });

    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        hamburger.classList.remove('open');
        mobileMenu.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
      });
    });
  }


  /* ── 3. Scroll reveal ── */
  const revealEls = document.querySelectorAll('.reveal');

  /* O IntersectionObserver monitora quando os elementos entram na tela para disparar a animação */
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target); // Remove o monitoramento após animar (otimiza performance)
        }
      });
    }, { threshold: 0.12 }); // Dispara quando 12% do elemento estiver visível

    revealEls.forEach(el => observer.observe(el));
  } else {
    // Fallback: se o navegador for muito antigo e não suportar o Observer, mostra tudo direto
    revealEls.forEach(el => el.classList.add('visible'));
  }


  /* ── 4. Link ativo navbar ── */
  /* Atualizado de '.navbar__links' para '.navbar-links' */
  const navLinks = document.querySelectorAll('.navbar-links a');
  const sections = document.querySelectorAll('section[id]');

  if (navLinks.length && sections.length) {
    /* Este Observer altera a classe 'active' no menu baseado na seção que está cruzando a tela */
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          navLinks.forEach(link => {
            link.classList.toggle(
              'active',
              link.getAttribute('href') === `#${entry.target.id}`
            );
          });
        }
      });
    }, { rootMargin: '-40% 0px -55% 0px' }); // Define a "linha de corte" virtual na tela para a ativação

    sections.forEach(sec => observer.observe(sec));
  }


  /* ── 5. CARROSSEL ── */

  /* ── 5. CARROSSEL (scroll nativo) ── */

const grid = document.getElementById('spec-grid');

if (grid) {

  // Scroll horizontal com roda do mouse (opcional)
  grid.addEventListener('wheel', (e) => {
    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
      e.preventDefault();
      grid.scrollLeft += e.deltaY;
    }
  }, { passive: false });

}


  /* ── 6. Smooth scroll ── */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', e => {
      const id = anchor.getAttribute('href');
      if (!id || id === '#') return;

      const target = document.querySelector(id);
      if (!target) return;

      e.preventDefault();

      // Calcula a altura da navbar dinamicamente para o topo da seção não ficar escondido atrás dela
      const offset = navbar ? navbar.offsetHeight + 8 : 0;

      window.scrollTo({
        top: target.getBoundingClientRect().top + window.scrollY - offset,
        behavior: 'smooth'
      });
    });
  });

}


/* ── INICIALIZAÇÃO ── */
window.initNexus = initNexus;

document.addEventListener('DOMContentLoaded', () => {
  if (!document.querySelector('[data-component]')) {
    initNexus();
  }
});