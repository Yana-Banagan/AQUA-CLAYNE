/**
 * Page-specific actions for the Aqua Clayne admin workspace.
 *
 * Views call these functions from buttons and links; keeping them here means
 * Blade templates contain markup and Laravel route data only.
 */
(function () {
    'use strict';

    const byId = (id) => document.getElementById(id);

    function setText(id, value) {
        const element = byId(id);
        if (element) element.textContent = value;
    }

    function setValue(id, value) {
        const element = byId(id);
        if (element) element.value = value;
    }

    window.switchTab = function (tabId, trigger) {
        document.querySelectorAll('.tab-link').forEach((button) => {
            const selected = button === trigger;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-selected', String(selected));
        });

        document.querySelectorAll('.tab-pane').forEach((pane) => {
            const selected = pane.id === `tab-${tabId}`;
            pane.hidden = !selected;
            pane.style.display = selected ? '' : 'none';
        });
    };

    window.openEditFaqModal = function (id, categoryId, question, answer) {
        setValue('edit-faq-category', categoryId);
        setValue('edit-faq-question', question);
        setValue('edit-faq-answer', answer);

        const form = byId('edit-faq-form');
        if (form) form.action = `/admin/faq/${id}`;

        window.openModal?.('edit-faq-modal');
    };

    window.openLogMaintenanceModal = function (id, name) {
        setValue('modal-maintenance-id', id);
        setText('modal-equipment-name', name);
        window.openModal?.('log-maintenance-modal');
    };

    window.openRejectModal = function () {
        window.openModal?.('reject-modal');
    };

    window.openIntakeModal = function (orderId, orderCode, customerName, expectedGallons) {
        setText('intake-order-code', `#${orderCode}`);
        setText('intake-customer-name', customerName);
        setValue('intake-expected', expectedGallons);
        setValue('intake-received', expectedGallons);
        setValue('intake-accepted', expectedGallons);
        setValue('intake-damaged', 0);

        const form = byId('intake-form');
        if (form) form.action = `/admin/orders/${orderId}/intake`;

        window.openModal?.('intake-modal');
    };

    window.calculateIntakeCounts = function () {
        const received = Number.parseInt(byId('intake-received')?.value, 10) || 0;
        const damaged = Number.parseInt(byId('intake-damaged')?.value, 10) || 0;
        setValue('intake-accepted', Math.max(0, received - damaged));
    };

    document.addEventListener('DOMContentLoaded', () => {
        ['intake-received', 'intake-damaged'].forEach((id) => {
            byId(id)?.addEventListener('input', window.calculateIntakeCounts);
        });
    });
}());
