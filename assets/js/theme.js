// Shared light/dark theme toggle for the public site and CMS.
(function () {
  const storageKey = 'webcms-theme';
  const root = document.documentElement;

  function readStoredTheme() {
    try {
      return localStorage.getItem(storageKey);
    } catch (error) {
      return null;
    }
  }

  function storeTheme(theme) {
    try {
      localStorage.setItem(storageKey, theme);
    } catch (error) {
      // Theme switching still works for the current page if storage is unavailable.
    }
  }

  function getInitialTheme() {
    const stored = readStoredTheme();
    if (stored === 'light' || stored === 'dark') return stored;
    return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function applyTheme(theme) {
    const nextTheme = theme === 'dark' ? 'dark' : 'light';
    root.setAttribute('data-theme', nextTheme);
    storeTheme(nextTheme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      const isDark = nextTheme === 'dark';
      button.setAttribute('aria-pressed', String(isDark));
      button.setAttribute('aria-label', isDark ? 'Hellmodus aktivieren' : 'Dunkelmodus aktivieren');
      button.title = isDark ? 'Hellmodus aktivieren' : 'Dunkelmodus aktivieren';

      const label = button.querySelector('[data-theme-label]');
      if (label) label.textContent = isDark ? 'Sonne' : 'Mond';
    });
  }

  applyTheme(getInitialTheme());

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(root.getAttribute('data-theme'));

    document.querySelectorAll('input[type="password"]').forEach((input) => {
      if (input.closest('.password-input')) return;

      const wrap = document.createElement('div');
      wrap.className = 'password-input';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);

      const button = document.createElement('button');
      button.className = 'password-toggle';
      button.type = 'button';
      button.setAttribute('aria-label', 'Passwort anzeigen');
      button.setAttribute('aria-pressed', 'false');
      button.innerHTML = `
        <svg class="password-toggle-eye" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        <svg class="password-toggle-eye-off" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.6 10.6A2 2 0 0012 14a2 2 0 001.4-.6"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.9 4.3A10.5 10.5 0 0112 4c6.5 0 10 8 10 8a17.5 17.5 0 01-2.1 3.1"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.1 6.1C3.5 8 2 12 2 12s3.5 8 10 8a10.5 10.5 0 005.9-1.9"/>
        </svg>
      `;

      button.addEventListener('click', () => {
        const isVisible = input.type === 'text';
        input.type = isVisible ? 'password' : 'text';
        button.classList.toggle('is-visible', !isVisible);
        button.setAttribute('aria-pressed', String(!isVisible));
        button.setAttribute('aria-label', isVisible ? 'Passwort anzeigen' : 'Passwort verstecken');
      });

      wrap.appendChild(button);
    });

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
      });
    });

    document.querySelectorAll('.navbar').forEach((navbar) => {
      const mobileNav = window.matchMedia('(max-width: 768px), (hover: none) and (pointer: coarse)');

      function setSubmenuOpen(item, isOpen) {
        item.classList.toggle('submenu-open', isOpen);
        const button = Array.from(item.children).find((child) => child.matches('[data-submenu-toggle]'));
        if (button) button.setAttribute('aria-expanded', String(isOpen));
      }

      function closeSubmenus() {
        navbar.querySelectorAll('.nav-item.submenu-open').forEach((item) => {
          setSubmenuOpen(item, false);
        });
      }

      const menuToggle = navbar.querySelector('[data-nav-toggle]');
      if (menuToggle) {
        menuToggle.addEventListener('click', () => {
          const isOpen = navbar.classList.toggle('nav-open');
          menuToggle.setAttribute('aria-expanded', String(isOpen));
          closeSubmenus();
        });
      }

      navbar.querySelectorAll('[data-submenu-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
          const item = button.closest('.nav-item');
          if (!item) return;

          setSubmenuOpen(item, !item.classList.contains('submenu-open'));
        });
      });

      navbar.querySelectorAll('.has-submenu > .nav-link').forEach((link) => {
        link.addEventListener('click', (event) => {
          if (!mobileNav.matches) return;

          const item = link.closest('.nav-item');
          if (!item) return;

          event.preventDefault();
          setSubmenuOpen(item, !item.classList.contains('submenu-open'));
        });
      });
    });

    document.querySelectorAll('.accordion-trigger').forEach((button) => {
      button.addEventListener('click', () => {
        const item = button.closest('.accordion-item');
        if (!item) return;

        const willOpen = !item.classList.contains('open');
        const group = item.parentElement;

        if (group) {
          Array.from(group.children).forEach((openItem) => {
            if (!openItem.classList.contains('accordion-item') || !openItem.classList.contains('open')) return;
            if (openItem === item) return;
            openItem.classList.remove('open');
            const openButton = openItem.querySelector('.accordion-trigger');
            if (openButton) openButton.setAttribute('aria-expanded', 'false');
          });
        }

        item.classList.toggle('open', willOpen);
        button.setAttribute('aria-expanded', String(willOpen));
      });
    });
  });
})();
