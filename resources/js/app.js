import './bootstrap';
import './historial-ventas';

const sidebarLayout = document.querySelector('.app-layout');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

if (sidebarLayout instanceof HTMLElement && sidebarToggle instanceof HTMLButtonElement) {
    const storageKey = 'erp-sidebar-collapsed';
    const setCollapsed = (collapsed) => {
        sidebarLayout.classList.toggle('sidebar-collapsed', collapsed);
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Expandir menú lateral' : 'Contraer menú lateral');
        sidebarToggle.setAttribute('title', collapsed ? 'Expandir menú lateral' : 'Contraer menú lateral');
    };

    setCollapsed(localStorage.getItem(storageKey) === 'true');

    sidebarToggle.addEventListener('click', () => {
        const collapsed = !sidebarLayout.classList.contains('sidebar-collapsed');

        setCollapsed(collapsed);
        localStorage.setItem(storageKey, String(collapsed));
    });
}
