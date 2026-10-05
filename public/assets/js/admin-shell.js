(function () {
    const shell = document.querySelector('.admin-shell');
    const sidebar = document.getElementById('admin-sidebar');
    if (!shell || !sidebar) return;

    const compactKey = 'seller_africa_sidebar_compact';
    const sectionKey = 'seller_africa_closed_nav_sections';
    const themeKey = 'seller_africa_admin_theme';
    const closedSections = new Set(JSON.parse(localStorage.getItem(sectionKey) || '[]'));

    if (localStorage.getItem(compactKey) === '1') {
        shell.classList.add('sidebar-compact');
    }

    if (localStorage.getItem(themeKey) === 'dark') {
        document.body.classList.add('admin-dark');
    }

    document.querySelectorAll('[data-nav-section]').forEach((section) => {
        const title = section.querySelector('.nav-section-title')?.textContent?.trim();
        const toggle = section.querySelector('[data-section-toggle]');

        if (title && closedSections.has(title)) {
            section.classList.remove('open');
            toggle?.setAttribute('aria-expanded', 'false');
        }

        toggle?.addEventListener('click', () => {
            section.classList.toggle('open');
            const isOpen = section.classList.contains('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            if (title) {
                if (isOpen) {
                    closedSections.delete(title);
                } else {
                    closedSections.add(title);
                }
                localStorage.setItem(sectionKey, JSON.stringify([...closedSections]));
            }
        });
    });

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 920px)').matches) {
                shell.classList.toggle('sidebar-open');
                return;
            }

            shell.classList.toggle('sidebar-compact');
            localStorage.setItem(compactKey, shell.classList.contains('sidebar-compact') ? '1' : '0');
        });
    });

    document.addEventListener('click', (event) => {
        if (!shell.classList.contains('sidebar-open')) return;
        if (sidebar.contains(event.target) || event.target.closest('[data-sidebar-toggle]')) return;
        shell.classList.remove('sidebar-open');
    });

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            document.body.classList.toggle('admin-dark');
            localStorage.setItem(themeKey, document.body.classList.contains('admin-dark') ? 'dark' : 'light');
        });
    });
})();
