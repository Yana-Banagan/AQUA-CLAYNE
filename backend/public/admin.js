/**
 * AQUA CLAYNE - Admin Web JavaScript Suite
 * Pure Water. Healthier Life.
 */

document.addEventListener('DOMContentLoaded', () => {
    // ----------------------------------------------------
    // 1. Sidebar Toggle & Mobile Drawer
    // ----------------------------------------------------
    const shell = document.getElementById('admin-shell');
    const sidebarToggles = document.querySelectorAll('[data-sidebar-toggle]');

    sidebarToggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            shell?.classList.toggle('sidebar-open');
        });
    });

    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768 && shell?.classList.contains('sidebar-open')) {
            const sidebar = document.getElementById('admin-sidebar');
            if (sidebar && !sidebar.contains(e.target) && !e.target.closest('[data-sidebar-toggle]')) {
                shell.classList.remove('sidebar-open');
            }
        }
    });

    // ----------------------------------------------------
    // 2. Dropdown Menus (Notifications & Profile)
    // ----------------------------------------------------
    const dropdownTriggers = document.querySelectorAll('[data-dropdown]');

    dropdownTriggers.forEach(trigger => {
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const targetId = trigger.getAttribute('data-dropdown');
            const targetDropdown = document.getElementById(targetId);

            // Close other dropdowns
            document.querySelectorAll('.dropdown.is-open').forEach(dd => {
                if (dd !== targetDropdown) dd.classList.remove('is-open');
            });
            dropdownTriggers.forEach(button => {
                if (button !== trigger) button.setAttribute('aria-expanded', 'false');
            });

            if (targetDropdown) {
                targetDropdown.classList.toggle('is-open');
                trigger.setAttribute('aria-expanded', String(targetDropdown.classList.contains('is-open')));
            }
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown.is-open').forEach(dd => {
            dd.classList.remove('is-open');
        });
        dropdownTriggers.forEach(trigger => trigger.setAttribute('aria-expanded', 'false'));
    });

    // Prevent clicks inside dropdown from closing it
    document.querySelectorAll('.dropdown').forEach(dd => {
        dd.addEventListener('click', (e) => e.stopPropagation());
    });

    // ----------------------------------------------------
    // 3. Password Visibility Toggle
    // ----------------------------------------------------
    const passwordToggles = document.querySelectorAll('[data-password-toggle]');
    passwordToggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.getAttribute('data-password-toggle'));
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = 'Hide';
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                btn.textContent = 'Show';
                btn.setAttribute('aria-label', 'Show password');
            }
        });
    });

    // ----------------------------------------------------
    // 4. Modal System
    // ----------------------------------------------------
    let lastFocusedElement = null;

    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            lastFocusedElement = document.activeElement;
            modal.classList.add('is-active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            const firstInput = modal.querySelector('input:not([type=hidden]), select, textarea, button.primary-button');
            if (firstInput) firstInput.focus();
        }
    };

    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('is-active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            lastFocusedElement?.focus?.();
        }
    };

    // Close on clicking backdrop or close buttons
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('is-active');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                lastFocusedElement?.focus?.();
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-backdrop');
            if (modal) {
                modal.classList.remove('is-active');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                lastFocusedElement?.focus?.();
            }
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.is-active').forEach(modal => {
                modal.classList.remove('is-active');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            });
            lastFocusedElement?.focus?.();
        }
    });

    // ----------------------------------------------------
    // 5. Toast System
    // ----------------------------------------------------
    const toastElem = document.getElementById('admin-toast');
    let toastTimeout;

    window.showToast = function (message, type = 'success') {
        if (!toastElem) return;
        clearTimeout(toastTimeout);

        toastElem.className = `toast toast-${type} show`;
        const icon = type === 'success' ? '✓' : (type === 'danger' ? '!' : (type === 'warning' ? '⚠' : 'ℹ'));
        toastElem.innerHTML = `<span>${icon}</span> <span>${message}</span>`;

        toastTimeout = setTimeout(() => {
            toastElem.classList.remove('show');
        }, 4000);
    };

    // ----------------------------------------------------
    // 6. AJAX Action & Form Confirmation Dispatcher
    // ----------------------------------------------------
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', (e) => {
            const promptMsg = form.getAttribute('data-confirm');
            if (!confirm(promptMsg || 'Are you sure you want to proceed?')) {
                e.preventDefault();
            }
        });
    });

    // Give regular Laravel form submissions immediate, visible feedback and
    // prevent accidental duplicate actions. Opt out with data-submit-state="off".
    document.querySelectorAll('form:not([data-submit-state="off"])').forEach(form => {
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) return;

            const submitter = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitter || submitter.disabled) return;

            submitter.disabled = true;
            submitter.setAttribute('aria-busy', 'true');
            submitter.dataset.originalLabel = submitter.textContent;
            if (submitter.tagName === 'BUTTON') submitter.textContent = 'Processing...';
        });
    });

    window.executeAction = async function (url, method = 'POST', data = {}, confirmMessage = null) {
        if (confirmMessage && !confirm(confirmMessage)) {
            return false;
        }

        try {
            const res = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify(data),
            });

            const json = await res.json().catch(() => ({}));

            if (res.ok) {
                window.showToast(json.message || 'Operation successful.', 'success');
                setTimeout(() => window.location.reload(), 800);
                return true;
            } else {
                const errorMsg = json.message || Object.values(json.errors || {})[0]?.[0] || 'An error occurred.';
                window.showToast(errorMsg, 'danger');
                return false;
            }
        } catch (err) {
            window.showToast('Network error. Please try again.', 'danger');
            return false;
        }
    };

    // ----------------------------------------------------
    // 7. Global Search Keyboard Shortcut (Ctrl + /)
    // ----------------------------------------------------
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            const searchInput = document.querySelector('.global-search input');
            if (searchInput) searchInput.focus();
        }
    });

    // Surface server-rendered flash messages as transient feedback as well.
    document.querySelectorAll('.flash-message').forEach(message => {
        window.setTimeout(() => {
            message.style.transition = 'opacity 0.25s ease';
            message.style.opacity = '0';
            window.setTimeout(() => message.remove(), 250);
        }, 5000);
    });
});
