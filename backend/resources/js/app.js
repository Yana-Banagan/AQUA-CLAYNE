const shell = document.getElementById('admin-shell');
document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => shell?.classList.toggle('sidebar-open'));
document.querySelectorAll('[data-dropdown]').forEach((button) => button.addEventListener('click', (event) => { event.stopPropagation(); const menu = document.getElementById(button.dataset.dropdown); document.querySelectorAll('.dropdown.is-open').forEach((openMenu) => { if (openMenu !== menu) openMenu.classList.remove('is-open'); }); menu?.classList.toggle('is-open'); }));
document.addEventListener('click', () => document.querySelectorAll('.dropdown.is-open').forEach((menu) => menu.classList.remove('is-open')));
document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => document.body.classList.toggle('dark-theme'));
document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); }));
window.showToast = (message) => { const toast = document.getElementById('admin-toast'); if (!toast) return; toast.textContent = message; toast.classList.add('show'); window.setTimeout(() => toast.classList.remove('show'), 4500); };
