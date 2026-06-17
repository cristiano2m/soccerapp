// SoccerAPP — inicialización global

document.addEventListener('DOMContentLoaded', () => {
    // Menú móvil del sitio público
    const navToggle = document.querySelector('.nav-toggle');
    const siteNav = document.querySelector('.site-nav');
    if (navToggle && siteNav) {
        navToggle.addEventListener('click', () => siteNav.classList.toggle('open'));
    }

    // Menú móvil del panel admin
    const menuToggle = document.querySelector('.admin-menu-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // Auto-cerrar alertas flash después de unos segundos
    document.querySelectorAll('.alert[data-autohide]').forEach((el) => {
        setTimeout(() => el.remove(), 5000);
    });
});
