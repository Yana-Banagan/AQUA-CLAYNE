/** Branded confirmation dialogs for admin edit and delete actions. */
(function () {
    'use strict';

    var pendingAction = null;
    var confirmedForms = new WeakSet();

    function createDialog() {
        if (document.getElementById('admin-action-confirmation')) return;

        var style = document.createElement('style');
        style.textContent = [
            '.action-confirm-backdrop{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:20px;background:rgba(10,25,47,.58);backdrop-filter:blur(3px)}',
            '.action-confirm-backdrop[hidden]{display:none}',
            '.action-confirm-card{width:min(100%,420px);padding:28px;border:1px solid #e4edf4;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(8,31,55,.28);font-family:Inter,system-ui,sans-serif}',
            '.action-confirm-icon{display:grid;place-items:center;width:46px;height:46px;margin-bottom:16px;border-radius:14px;background:#e9f4fb;color:#0874a5;font-size:22px;font-weight:800}',
            '.action-confirm-card[data-action="delete"] .action-confirm-icon{background:#fff0ef;color:#c53030}',
            '.action-confirm-card h2{margin:0 0 8px;color:#102a43;font-size:20px}',
            '.action-confirm-card p{margin:0;color:#526578;font-size:14px;line-height:1.55}',
            '.action-confirm-buttons{display:flex;justify-content:flex-end;gap:10px;margin-top:24px}',
            '.action-confirm-buttons button{min-height:40px;padding:0 16px;border:0;border-radius:9px;font:600 14px Inter,system-ui,sans-serif;cursor:pointer}',
            '.action-confirm-cancel{background:#edf2f7;color:#334e68}',
            '.action-confirm-accept{background:#0874a5;color:#fff}',
            '.action-confirm-card[data-action="delete"] .action-confirm-accept{background:#c53030}',
            '@media(prefers-reduced-motion:no-preference){.action-confirm-card{animation:action-confirm-in .16s ease-out}@keyframes action-confirm-in{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}}'
        ].join('');
        document.head.appendChild(style);

        var backdrop = document.createElement('div');
        backdrop.id = 'admin-action-confirmation';
        backdrop.className = 'action-confirm-backdrop';
        backdrop.hidden = true;
        backdrop.innerHTML = '<section class="action-confirm-card" role="alertdialog" aria-modal="true" aria-labelledby="action-confirm-title" aria-describedby="action-confirm-message"><div class="action-confirm-icon" aria-hidden="true">?</div><h2 id="action-confirm-title">Please confirm</h2><p id="action-confirm-message"></p><div class="action-confirm-buttons"><button type="button" class="action-confirm-cancel">Cancel</button><button type="button" class="action-confirm-accept">Continue</button></div></section>';
        document.body.appendChild(backdrop);

        backdrop.querySelector('.action-confirm-cancel').addEventListener('click', closeDialog);
        backdrop.querySelector('.action-confirm-accept').addEventListener('click', acceptAction);
        backdrop.addEventListener('click', function (event) {
            if (event.target === backdrop) closeDialog();
        });
    }

    function closeDialog() {
        var backdrop = document.getElementById('admin-action-confirmation');
        if (backdrop) backdrop.hidden = true;
        document.body.style.overflow = '';
        var action = pendingAction;
        pendingAction = null;
        if (action && action.trigger && typeof action.trigger.focus === 'function') {
            action.trigger.focus();
        }
    }

    function ask(action, message, trigger) {
        createDialog();
        pendingAction = { action: action, trigger: trigger };
        var backdrop = document.getElementById('admin-action-confirmation');
        var card = backdrop.querySelector('.action-confirm-card');
        var deleting = action === 'delete';
        var loggingOut = action === 'logout';
        card.dataset.action = action;
        backdrop.querySelector('#action-confirm-title').textContent = deleting ? 'Delete this item?' : (loggingOut ? 'Log out of admin?' : 'Edit this item?');
        backdrop.querySelector('#action-confirm-message').textContent = message || (deleting
            ? 'This action may be permanent. Do you want to continue?'
            : (loggingOut ? 'Are you sure you want to end your admin session?' : 'Do you want to open this item for editing?'));
        backdrop.querySelector('.action-confirm-icon').textContent = deleting ? '!' : '✎';
        backdrop.querySelector('.action-confirm-accept').textContent = deleting ? 'Delete' : (loggingOut ? 'Log out' : 'Continue to edit');
        backdrop.hidden = false;
        document.body.style.overflow = 'hidden';
        backdrop.querySelector('.action-confirm-cancel').focus();
    }

    function acceptAction() {
        if (!pendingAction) return;
        var current = pendingAction;
        pendingAction = null;
        closeDialog();

        if (current.action === 'delete' || current.action === 'logout') {
            var form = current.trigger;
            if (form instanceof HTMLFormElement) {
                confirmedForms.add(form);
                if (form.requestSubmit) form.requestSubmit();
                else form.submit();
                return;
            }
        }

        if (current.trigger) {
            current.trigger.dataset.actionConfirmed = 'true';
            if (current.trigger.tagName === 'A' && current.trigger.href) {
                window.location.href = current.trigger.href;
            } else {
                current.trigger.click();
            }
            setTimeout(function () {
                if (current.trigger) delete current.trigger.dataset.actionConfirmed;
            }, 250);
        }
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && pendingAction) closeDialog();
    });

    document.addEventListener('click', function (event) {
        var target = event.target.closest('a, button, input[type="button"]');
        if (!target || target.dataset.actionConfirmed === 'true') return;
        // Never intercept clicks inside the confirmation dialog itself!
        if (target.closest('#admin-action-confirmation')) return;

        // Modal triggers and explicit actions must open directly without an extra confirmation dialog
        if (target.hasAttribute('data-customer-edit') ||
            target.hasAttribute('data-driver-edit') ||
            target.closest('[data-customer-edit], [data-driver-edit], [data-no-confirm], [data-modal-target], [data-bs-toggle]')) {
            return;
        }

        var label = (target.getAttribute('aria-label') || target.title || target.textContent || '').trim().toLowerCase();
        var isStandaloneDelete = /\bdelete\b/.test(label) && !target.closest('form');
        if (isStandaloneDelete) {
            event.preventDefault();
            event.stopImmediatePropagation();
            ask('delete', 'This action may be permanent. Do you want to delete this item?', target);
            return;
        }

        // Only prompt confirmation on navigation links or explicit confirmation requests, NOT general buttons opening local modals
        var isEditLink = target.tagName === 'A' && /(?:^|\/)edit(?:\/|$|\?)/i.test(new URL(target.href, location.href).pathname);
        var hasExplicitConfirm = target.hasAttribute('data-confirm-edit');
        if (!isEditLink && !hasExplicitConfirm) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        ask('edit', target.dataset.confirmEdit || 'Do you want to continue and edit this item?', target);
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (confirmedForms.has(form)) {
            confirmedForms.delete(form);
            return;
        }

        var method = (form.querySelector('input[name="_method"]')?.value || form.method || 'GET').toUpperCase();
        if (form.matches('.logout-form, .dropdown-logout-form') || form.action.includes('/admin/logout')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            ask('logout', 'Are you sure you want to log out of the admin dashboard?', form);
            return;
        }
        var submitterLabel = (event.submitter?.textContent || event.submitter?.value || '').trim().toLowerCase();
        var isDelete = method === 'DELETE' || /\bdelete\b/.test(submitterLabel) || form.hasAttribute('data-confirm-delete');
        if (!isDelete) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        ask('delete', form.dataset.confirm || form.dataset.confirmDelete || 'This action may be permanent. Do you want to delete this item?', form);
    }, true);
}());
