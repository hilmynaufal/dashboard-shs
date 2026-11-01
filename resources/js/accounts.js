document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const originalLabel = button.getAttribute('aria-label');
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Sembunyikan' : 'Lihat';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? originalLabel.replace('Tampilkan', 'Sembunyikan') : originalLabel);
    });
});

const navToggle = document.querySelector('[data-nav-toggle]');
const sidebar = document.querySelector('.account-app .sidebar');
const narrowNav = window.matchMedia('(max-width: 1000px)');
const setNav = (open, returnFocus = false) => {
    const drawerOpen = narrowNav.matches && open;
    document.body.classList.toggle('menu-open', drawerOpen);
    navToggle?.setAttribute('aria-expanded', String(drawerOpen));
    if (sidebar) sidebar.inert = narrowNav.matches && !drawerOpen;
    if (drawerOpen) sidebar?.querySelector('a,button')?.focus();
    else if (returnFocus) navToggle?.focus();
};
const closeNav = () => setNav(false, true);
navToggle?.addEventListener('click', () => {
    setNav(!document.body.classList.contains('menu-open'), true);
});
document.querySelector('[data-nav-close]')?.addEventListener('click', closeNav);
document.addEventListener('keydown', (event) => {
    if (!narrowNav.matches || !document.body.classList.contains('menu-open')) return;
    if (event.key === 'Escape') closeNav();
    if (event.key === 'Tab') {
        const targets = [...sidebar.querySelectorAll('a[href],button:not(:disabled)')];
        const first = targets[0], last = targets.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
});
narrowNav.addEventListener('change', () => setNav(false, narrowNav.matches && sidebar?.contains(document.activeElement)));
setNav(false);
document.querySelectorAll('[data-submit-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.statusConfirm && !window.confirm(form.dataset.statusConfirm)) {
            event.preventDefault();
            return;
        }
        const submit = form.querySelector('button[type="submit"]');
        if (submit) {
            submit.dataset.originalLabel = submit.textContent;
            submit.disabled = true;
            submit.textContent = form.action.endsWith('/auth/login') ? 'Memeriksa…'
                : form.querySelector('input[name="_method"]')?.value === 'DELETE' ? 'Menghapus…' : 'Menyimpan…';
        }
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-submit-form] button[type="submit"]').forEach((button) => {
        if (!Object.hasOwn(button.dataset, 'originalLabel')) return;
        button.disabled = false;
        button.textContent = button.dataset.originalLabel;
        delete button.dataset.originalLabel;
    });
});
document.querySelectorAll('.field-error').forEach((error) => {
    const field = error.closest('.form-field')?.querySelector('input,select,textarea');
    if (!field) return;
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
});
