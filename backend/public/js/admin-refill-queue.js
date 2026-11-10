/**
 * AQUA CLAYNE Water Refilling Station Management System
 * Admin Refill Queue Management Module (admin-refill-queue.js)
 */

(function () {
    'use strict';

    // =========================================================================
    // 1. DYNAMIC TOAST NOTIFICATION SYSTEM
    // =========================================================================
    const Toast = {
        container: null,

        init() {
            this.container = document.getElementById('toastContainer');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainer';
                this.container.className = 'toast-container';
                this.container.setAttribute('aria-live', 'polite');
                document.body.appendChild(this.container);
            }
        },

        show(message, type = 'info', duration = 4500) {
            this.init();

            const toast = document.createElement('div');
            toast.className = `toast-item toast-${type}`;
            toast.setAttribute('role', 'status');

            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };

            toast.innerHTML = `
                <span style="font-size: 16px; font-weight: 800;">${icons[type] || 'ℹ'}</span>
                <span style="flex: 1;">${escapeHtml(message)}</span>
                <button type="button" class="toast-close-btn" aria-label="Close notification">&times;</button>
            `;

            const closeBtn = toast.querySelector('.toast-close-btn');
            closeBtn.addEventListener('click', () => this.dismiss(toast));

            this.container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.add('toast-visible');
            });

            if (duration > 0) {
                setTimeout(() => this.dismiss(toast), duration);
            }
        },

        dismiss(toast) {
            toast.classList.remove('toast-visible');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }
    };

    window.Toast = Toast;

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // =========================================================================
    // 2. REFILL QUEUE CONTROLLER OBJECT
    // =========================================================================
    const RefillQueue = {
        searchTimeout: null,
        heartbeatInterval: null,
        timerInterval: null,
        activeModal: null,
        refreshing: false,
        refreshPending: false,
        realtimeConnected: Boolean(window.aquaRealtimeConnected),

        init() {
            this.initFilters();
            this.initSearch();
            this.initActions();
            this.initModals();
            this.initRealtime();
            this.initWaitingTimers();
        },

        // ---------------------------------------------------------------------
        // FILTERS & SEARCH
        // ---------------------------------------------------------------------
        initFilters() {
            const filterForm = document.getElementById('queueFilterForm');
            if (filterForm) {
                const autoSubmitControls = filterForm.querySelectorAll('select, input[type="date"]');
                autoSubmitControls.forEach((control) => {
                    control.addEventListener('change', () => {
                        filterForm.submit();
                    });
                });
            }
        },

        initSearch() {
            const searchInput = document.getElementById('queueSearchInput');
            const filterForm = document.getElementById('queueFilterForm');
            if (searchInput && filterForm) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        filterForm.submit();
                    }, 400);
                });
            }
        },

        // ---------------------------------------------------------------------
        // BUTTON ACTIONS & EVENT DELEGATION
        // ---------------------------------------------------------------------
        initActions() {
            // Start Refill Button Click
            document.addEventListener('click', (e) => {
                const startBtn = e.target.closest('[data-action="start-refill"]');
                if (startBtn) {
                    e.preventDefault();
                    const orderId = startBtn.dataset.orderId;
                    const orderNum = startBtn.dataset.orderNum;
                    const customer = startBtn.dataset.customer;
                    const quantity = startBtn.dataset.quantity;
                    const queuePos = startBtn.dataset.queuePos;
                    const received = startBtn.dataset.received;

                    this.startRefill(orderId, startBtn);
                }

                const completeBtn = e.target.closest('[data-action="complete-refill"]');
                if (completeBtn) {
                    e.preventDefault();
                    const orderId = completeBtn.dataset.orderId;
                    const orderNum = completeBtn.dataset.orderNum;
                    const customer = completeBtn.dataset.customer;
                    const quantity = completeBtn.dataset.quantity;

                    this.openCompleteRefillModal({ orderId, orderNum, customer, quantity });
                }

                const priorityBtn = e.target.closest('[data-action="change-priority"]');
                if (priorityBtn) {
                    e.preventDefault();
                    const orderId = priorityBtn.dataset.orderId;
                    const orderNum = priorityBtn.dataset.orderNum;
                    const queuePos = priorityBtn.dataset.queuePos;
                    const currentPriority = priorityBtn.dataset.currentPriority;

                    this.openPriorityModal({ orderId, orderNum, queuePos, currentPriority });
                }

                const intakeBtn = e.target.closest('[data-action="confirm-intake"]');
                if (intakeBtn) {
                    e.preventDefault();
                    const orderId = intakeBtn.dataset.orderId;
                    const orderNum = intakeBtn.dataset.orderNum;
                    const customer = intakeBtn.dataset.customer;
                    const expected = intakeBtn.dataset.expected;

                    this.openIntakeModal({ orderId, orderNum, customer, expected });
                }

                const resolveBtn = e.target.closest('[data-action="resolve-intake"]');
                if (resolveBtn) {
                    e.preventDefault();
                    const orderId = resolveBtn.dataset.orderId;
                    const orderNum = resolveBtn.dataset.orderNum;
                    const customer = resolveBtn.dataset.customer;
                    const expected = resolveBtn.dataset.expected;
                    const received = resolveBtn.dataset.received;
                    const accepted = resolveBtn.dataset.accepted;
                    const notes = resolveBtn.dataset.notes || '';

                    this.openResolveModal({ orderId, orderNum, customer, expected, received, accepted, notes });
                }
            });

            // Start Refill Form Submission
            const startForm = document.getElementById('startRefillForm');
            if (startForm) {
                startForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const orderId = document.getElementById('startRefillOrderId').value;
                    const submitBtn = startForm.querySelector('button[type="submit"]');
                    this.startRefill(orderId, submitBtn);
                });
            }

            // Complete Refill Form Submission
            const completeForm = document.getElementById('completeRefillForm');
            if (completeForm) {
                completeForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const orderId = document.getElementById('completeRefillOrderId').value;
                    const submitBtn = completeForm.querySelector('button[type="submit"]');
                    this.completeRefill(orderId, submitBtn);
                });
            }

            // Priority Change Form Submission
            const priorityForm = document.getElementById('priorityChangeForm');
            if (priorityForm) {
                priorityForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const orderId = document.getElementById('priorityOrderId').value;
                    const priority = document.getElementById('prioritySelect').value;
                    const reason = document.getElementById('priorityReasonSelect').value;
                    const explanation = document.getElementById('priorityExplanation').value.trim();

                    if (!reason) {
                        Toast.show('Please select an operational reason.', 'warning');
                        return;
                    }

                    const combinedReason = explanation ? `${reason}: ${explanation}` : reason;
                    const submitBtn = priorityForm.querySelector('button[type="submit"]');
                    this.changePriority(orderId, priority, combinedReason, submitBtn);
                });
            }

            // Physical Intake Form Submission
            const intakeForm = document.getElementById('stationIntakeForm');
            if (intakeForm) {
                intakeForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const orderId = document.getElementById('intakeOrderId').value;
                    const receivedCount = parseInt(document.getElementById('intakeReceivedCount').value, 10);
                    const acceptedCount = parseInt(document.getElementById('intakeAcceptedCount').value, 10);
                    const damagedCount = parseInt(document.getElementById('intakeDamagedCount').value || '0', 10);
                    const notes = document.getElementById('intakeNotes').value;
                    const submitBtn = intakeForm.querySelector('button[type="submit"]');

                    this.confirmIntake(orderId, {
                        received_count: receivedCount,
                        accepted_count: acceptedCount,
                        damaged_count: damagedCount,
                        condition_notes: notes
                    }, submitBtn);
                });
            }

            // Resolve Intake Form Submission
            const resolveForm = document.getElementById('resolveIntakeForm');
            if (resolveForm) {
                resolveForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const orderId = document.getElementById('resolveOrderId').value;
                    const acceptedCount = parseInt(document.getElementById('resolveAcceptedCount').value, 10);
                    const resolutionReason = document.getElementById('resolveReason').value.trim();
                    const notes = document.getElementById('resolveNotes').value;
                    const submitBtn = resolveForm.querySelector('button[type="submit"]');

                    if (!resolutionReason) {
                        Toast.show('Please provide a resolution explanation.', 'warning');
                        return;
                    }

                    this.resolveIntake(orderId, {
                        accepted_count: acceptedCount,
                        resolution_reason: resolutionReason,
                        condition_notes: notes
                    }, submitBtn);
                });
            }
        },

        // ---------------------------------------------------------------------
        // MODALS MANAGEMENT & ACCESSIBILITY
        // ---------------------------------------------------------------------
        initModals() {
            // Close modal buttons
            document.querySelectorAll('[data-modal-close]').forEach((btn) => {
                btn.addEventListener('click', () => this.closeModal());
            });

            // Close on backdrop click
            document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
                backdrop.addEventListener('click', (e) => {
                    if (e.target === backdrop) {
                        this.closeModal();
                    }
                });
            });

            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.activeModal) {
                    this.closeModal();
                }
            });
        },

        openModal(modalId) {
            this.closeModal();
            const modalEl = document.getElementById(modalId);
            if (modalEl) {
                modalEl.classList.add('is-active');
                modalEl.setAttribute('aria-hidden', 'false');
                this.activeModal = modalEl;

                const firstFocusable = modalEl.querySelector('input:not([readonly]), select, textarea, button[type="submit"]');
                if (firstFocusable) {
                    setTimeout(() => firstFocusable.focus(), 100);
                }
            }
        },

        closeModal() {
            if (this.activeModal) {
                this.activeModal.classList.remove('is-active');
                this.activeModal.setAttribute('aria-hidden', 'true');
                this.activeModal = null;
            }
        },

        openStartRefillModal(data) {
            document.getElementById('startRefillOrderId').value = data.orderId;
            document.getElementById('startOrderNumText').textContent = `#${data.orderNum}`;
            document.getElementById('startCustomerText').textContent = data.customer;
            document.getElementById('startQuantityText').textContent = `${data.quantity} gallons (Customer-Owned)`;
            document.getElementById('startPositionText').textContent = data.queuePos;
            document.getElementById('startReceivedText').textContent = data.received || 'N/A';

            this.openModal('startRefillModal');
        },

        openCompleteRefillModal(data) {
            document.getElementById('completeRefillOrderId').value = data.orderId;
            document.getElementById('completeOrderNumText').textContent = `#${data.orderNum}`;
            document.getElementById('completeCustomerText').textContent = data.customer;
            document.getElementById('completeQuantityText').textContent = `${data.quantity} gallons`;

            this.openModal('completeRefillModal');
        },

        openPriorityModal(data) {
            document.getElementById('priorityOrderId').value = data.orderId;
            document.getElementById('priorityOrderNumText').textContent = `#${data.orderNum}`;
            document.getElementById('priorityCurrentPosText').textContent = data.queuePos;
            document.getElementById('prioritySelect').value = data.currentPriority || '0';
            document.getElementById('priorityReasonSelect').value = '';
            document.getElementById('priorityExplanation').value = '';

            this.openModal('priorityModal');
        },

        openIntakeModal(data) {
            document.getElementById('intakeOrderId').value = data.orderId;
            document.getElementById('intakeOrderNumText').textContent = `#${data.orderNum}`;
            document.getElementById('intakeCustomerText').textContent = data.customer;
            document.getElementById('intakeExpectedCount').value = data.expected;
            document.getElementById('intakeReceivedCount').value = data.expected;
            document.getElementById('intakeAcceptedCount').value = data.expected;
            document.getElementById('intakeDamagedCount').value = '0';
            document.getElementById('intakeNotes').value = '';

            this.openModal('intakeModal');
        },

        openResolveModal(data) {
            document.getElementById('resolveOrderId').value = data.orderId;
            document.getElementById('resolveOrderNumText').textContent = `#${data.orderNum}`;
            document.getElementById('resolveCustomerText').textContent = data.customer;
            document.getElementById('resolveExpectedText').textContent = `${data.expected} gallons`;
            document.getElementById('resolveReceivedText').textContent = `${data.received} gallons`;
            document.getElementById('resolveAcceptedCount').value = data.accepted || data.expected;
            document.getElementById('resolveReason').value = '';
            document.getElementById('resolveNotes').value = data.notes;

            this.openModal('resolveModal');
        },

        // ---------------------------------------------------------------------
        // ASYNC BACKEND ACTIONS
        // ---------------------------------------------------------------------
        async startRefill(orderId, button) {
            if (!orderId) {
                Toast.show('Invalid order specified.', 'error');
                return;
            }

            this.setButtonLoading(button, true, 'Starting...');

            try {
                const response = await fetch(`/admin/refill-queue/${orderId}/start`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Unable to start refill.');
                }

                Toast.show(result.message || 'Refill started successfully.', 'success');
                this.closeModal();
                this.refreshQueue();
            } catch (err) {
                Toast.show(err.message || 'Unable to start refill.', 'error');
            } finally {
                this.setButtonLoading(button, false, 'Start Refill');
            }
        },

        async completeRefill(orderId, button) {
            if (!orderId) {
                Toast.show('Invalid order specified.', 'error');
                return;
            }

            this.setButtonLoading(button, true, 'Completing...');

            try {
                const response = await fetch(`/admin/refill-queue/${orderId}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Unable to complete refill.');
                }

                Toast.show(result.message || 'Refill marked ready for delivery.', 'success');
                this.closeModal();
                this.refreshQueue();
            } catch (err) {
                Toast.show(err.message || 'Unable to complete refill.', 'error');
            } finally {
                this.setButtonLoading(button, false, 'Mark Refill Ready');
            }
        },

        async changePriority(orderId, priority, reason, button) {
            if (!orderId) {
                Toast.show('Invalid order specified.', 'error');
                return;
            }

            this.setButtonLoading(button, true, 'Saving...');

            try {
                const response = await fetch(`/admin/refill-queue/${orderId}/priority`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ priority: parseInt(priority, 10), reason })
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Unable to update priority.');
                }

                Toast.show('Queue priority updated and logged.', 'success');
                this.closeModal();
                this.refreshQueue();
            } catch (err) {
                Toast.show(err.message || 'Unable to update priority.', 'error');
            } finally {
                this.setButtonLoading(button, false, 'Confirm Priority Change');
            }
        },

        async confirmIntake(orderId, data, button) {
            if (!orderId) {
                Toast.show('Invalid order specified.', 'error');
                return;
            }

            this.setButtonLoading(button, true, 'Confirming...');

            try {
                const response = await fetch(`/admin/orders/${orderId}/intake`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Unable to record intake.');
                }

                if (result.data && result.data.has_discrepancy) {
                    Toast.show('Intake discrepancy recorded. Must be resolved before queueing.', 'warning');
                } else {
                    Toast.show('Intake verified. Order entered the refill queue.', 'success');
                }

                this.closeModal();
                this.refreshQueue();
            } catch (err) {
                Toast.show(err.message || 'Unable to record container intake.', 'error');
            } finally {
                this.setButtonLoading(button, false, 'Confirm Intake');
            }
        },

        async resolveIntake(orderId, data, button) {
            if (!orderId) {
                Toast.show('Invalid order specified.', 'error');
                return;
            }

            this.setButtonLoading(button, true, 'Reconciling...');

            try {
                const response = await fetch(`/admin/refill-queue/${orderId}/resolve-intake`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Unable to reconcile discrepancy.');
                }

                Toast.show(result.message || 'Discrepancy resolved. Order queued.', 'success');
                this.closeModal();
                this.refreshQueue();
            } catch (err) {
                Toast.show(err.message || 'Unable to resolve discrepancy.', 'error');
            } finally {
                this.setButtonLoading(button, false, 'Resolve & Enter Queue');
            }
        },

        setButtonLoading(button, isLoading, text) {
            if (!button) return;
            button.disabled = isLoading;
            if (isLoading) {
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = `<span>⟳</span> <span>${text}</span>`;
            } else {
                button.innerHTML = button.dataset.originalText || text;
            }
        },

        // ---------------------------------------------------------------------
        // REFRESH DATA & REALTIME
        // ---------------------------------------------------------------------
        refreshQueue() {
            if (this.refreshing) {
                this.refreshPending = true;
                return;
            }
            this.refreshing = true;
            fetch(window.location.href, {
                headers: { 'Accept': 'text/html' },
                cache: 'no-store'
            })
                .then((response) => {
                    if (!response.ok) throw new Error('Could not refresh the refill queue.');
                    return response.text();
                })
                .then((html) => {
                    const parsed = new DOMParser().parseFromString(html, 'text/html');
                    const current = document.querySelector('.refill-queue-page');
                    const updated = parsed.querySelector('.refill-queue-page');
                    if (!current || !updated) return;
                    current.replaceWith(updated);
                    this.initFilters();
                    this.initSearch();
                })
                .catch(() => {})
                .finally(() => {
                    this.refreshing = false;
                    if (this.refreshPending) {
                        this.refreshPending = false;
                        this.refreshQueue();
                    }
                });
        },

        initRealtime() {
            this.realtimeHandler = (event) => {
                if (!['refill_queue', 'order', 'delivery'].includes(event.detail?.entity_type)) return;
                if (document.visibilityState !== 'visible') {
                    this.refreshPending = true;
                    return;
                }
                this.refreshQueue();
            };
            this.realtimeStatusHandler = (event) => {
                this.realtimeConnected = Boolean(event.detail?.connected);
            };
            window.addEventListener('aqua:realtime', this.realtimeHandler);
            window.addEventListener('aqua:realtime-status', this.realtimeStatusHandler);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && this.refreshPending) {
                    this.refreshPending = false;
                    this.refreshQueue();
                }
            });

            // Reliable 30s background heartbeat polling fallback using lightweight status endpoint
            this.heartbeatInterval = setInterval(async () => {
                try {
                    if (this.realtimeConnected || document.visibilityState !== 'visible') return;
                    const res = await fetch('/admin/refill-queue/status', {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const json = await res.json();
                        const waitingCount = json.waiting !== undefined ? json.waiting : json.stats?.waiting;
                        const refillingCount = json.refilling !== undefined ? json.refilling : json.stats?.refilling;
                        if (waitingCount !== undefined && refillingCount !== undefined) {
                            // Update KPI badges if DOM elements present
                            const waitingBadge = document.querySelector('.kpi-waiting .kpi-value');
                            const activeBadge = document.querySelector('.kpi-refilling .kpi-value');
                            if ((waitingBadge && parseInt(waitingBadge.textContent.trim(), 10) !== waitingCount) ||
                                (activeBadge && parseInt(activeBadge.textContent.trim(), 10) !== refillingCount)) {
                                this.refreshQueue();
                            }
                        }
                    }
                } catch (e) {
                    // silent fallback
                }
            }, 30000);

            window.addEventListener('beforeunload', () => {
                if (this.heartbeatInterval) clearInterval(this.heartbeatInterval);
                if (this.timerInterval) clearInterval(this.timerInterval);
                window.removeEventListener('aqua:realtime', this.realtimeHandler);
                window.removeEventListener('aqua:realtime-status', this.realtimeStatusHandler);
            }, { once: true });
        },

        // ---------------------------------------------------------------------
        // WAITING TIMER DURATION COUNTER (LOCAL 60s TICK)
        // ---------------------------------------------------------------------
        initWaitingTimers() {
            const updateDurations = () => {
                const timerElements = document.querySelectorAll('[data-queued-at]');
                const now = new Date();

                timerElements.forEach((el) => {
                    const queuedAtStr = el.getAttribute('data-queued-at');
                    if (!queuedAtStr) return;

                    const queuedAt = new Date(queuedAtStr);
                    const diffMs = now - queuedAt;
                    if (isNaN(diffMs) || diffMs < 0) return;

                    const diffMin = Math.floor(diffMs / 60000);
                    let display = '';

                    if (diffMin < 60) {
                        display = `${diffMin} min`;
                    } else {
                        const hours = Math.floor(diffMin / 60);
                        const mins = diffMin % 60;
                        display = `${hours}h ${mins}m`;
                    }

                    el.textContent = display;
                });
            };

            updateDurations();
            this.timerInterval = setInterval(updateDurations, 60000);
        }
    };

    window.RefillQueue = RefillQueue;

    document.addEventListener('DOMContentLoaded', () => {
        RefillQueue.init();
    });
})();
