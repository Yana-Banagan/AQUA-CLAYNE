/**
 * AQUA CLAYNE Water Refilling Station Management System
 * Admin Orders Management Module (admin-orders.js)
 *
 * Handles:
 * - Driver assignment modal dialog (open, populate, driver live preview, validate, submit)
 * - Live AJAX driver dispatch via DeliveryService backend
 * - Seamless table row status & driver display update without full page reload
 * - Search input debounce and auto-filter triggers
 * - Accessible modal trapping, escape key, and backdrop dismiss
 * - Dynamic Toast notifications (success, error, info, warning)
 * - Checkbox multi-select helpers
 */

(function () {
    'use strict';

    // =========================================================================
    // 1. TOAST NOTIFICATION SYSTEM
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
                success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
                error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
                warning: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
                info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
            };

            toast.innerHTML = `
                <div class="toast-icon">${icons[type] || icons.info}</div>
                <div class="toast-message">${escapeHtml(message)}</div>
                <button type="button" class="toast-close-btn" aria-label="Close notification">&times;</button>
            `;

            const closeBtn = toast.querySelector('.toast-close-btn');
            closeBtn.addEventListener('click', () => {
                this.dismiss(toast);
            });

            this.container.appendChild(toast);

            // Animate entrance
            requestAnimationFrame(() => {
                toast.classList.add('toast-visible');
            });

            if (duration > 0) {
                setTimeout(() => {
                    this.dismiss(toast);
                }, duration);
            }
        },

        dismiss(toast) {
            toast.classList.remove('toast-visible');
            toast.classList.add('toast-hiding');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }
    };

    window.Toast = Toast;

    // Helper: Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }

    // Helper: Get CSRF Token
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        const input = document.querySelector('input[name="_token"]');
        if (input && input.value) return input.value;
        return '';
    }

    // =========================================================================
    // 2. ASSIGN DRIVER MODAL CONTROLLER
    // =========================================================================
    const AssignDriverModal = {
        backdrop: null,
        form: null,
        orderIdInput: null,
        orderNumEl: null,
        customerNameEl: null,
        gallonsEl: null,
        serviceEl: null,
        addressEl: null,
        driverSelect: null,
        driverInfoCard: null,
        driverAvatar: null,
        driverName: null,
        driverPhone: null,
        driverBadge: null,
        driverVehicle: null,
        driverDeliveries: null,
        confirmBox: null,
        confirmOrderRef: null,
        confirmDriverTarget: null,
        errorBanner: null,
        errorText: null,
        submitBtn: null,
        spinner: null,
        btnText: null,

        currentOrderData: null,

        init() {
            this.backdrop = document.getElementById('assignDriverModal');
            if (!this.backdrop) return;

            this.form = document.getElementById('assignDriverForm');
            this.orderIdInput = document.getElementById('modalOrderId');
            this.orderNumEl = document.getElementById('modalOrderNumber');
            this.customerNameEl = document.getElementById('modalCustomerName');
            this.gallonsEl = document.getElementById('modalGallons');
            this.serviceEl = document.getElementById('modalService');
            this.addressEl = document.getElementById('modalAddress');

            this.driverSelect = document.getElementById('driverSelect');
            this.driverInfoCard = document.getElementById('driverInfoCard');
            this.driverAvatar = document.getElementById('driverCardAvatar');
            this.driverName = document.getElementById('driverCardName');
            this.driverPhone = document.getElementById('driverCardPhone');
            this.driverBadge = document.getElementById('driverWorkloadBadge');
            this.driverVehicle = document.getElementById('driverCardVehicle');
            this.driverDeliveries = document.getElementById('driverCardDeliveries');

            this.confirmBox = document.getElementById('confirmAssignBox');
            this.confirmOrderRef = document.getElementById('confirmOrderRef');
            this.confirmDriverTarget = document.getElementById('confirmDriverTarget');

            this.errorBanner = document.getElementById('modalErrorMessage');
            this.errorText = document.getElementById('modalErrorText');

            this.submitBtn = document.getElementById('submitAssignBtn');
            this.spinner = document.getElementById('assignSpinner');
            this.btnText = document.getElementById('assignBtnText');

            this.bindEvents();
        },

        bindEvents() {
            // Close triggers
            const closeBtn = document.getElementById('closeAssignModalBtn');
            const cancelBtn = document.getElementById('cancelAssignBtn');

            if (closeBtn) closeBtn.addEventListener('click', () => this.close());
            if (cancelBtn) cancelBtn.addEventListener('click', () => this.close());

            // Click on dark backdrop to close
            this.backdrop.addEventListener('click', (e) => {
                if (e.target === this.backdrop) {
                    this.close();
                }
            });

            // Escape key dismiss
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.backdrop.style.display !== 'none') {
                    this.close();
                }
            });

            // Driver dropdown change
            if (this.driverSelect) {
                this.driverSelect.addEventListener('change', () => this.onDriverChange());
            }

            // Form submission
            if (this.form) {
                this.form.addEventListener('submit', (e) => this.onSubmit(e));
            }

            // Delegated click for .btn-assign-driver on document
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-assign-driver');
                if (!btn) return;
                e.preventDefault();

                const data = {
                    orderId: btn.getAttribute('data-order-id'),
                    orderNum: btn.getAttribute('data-order-num'),
                    customer: btn.getAttribute('data-customer'),
                    address: btn.getAttribute('data-address'),
                    gallons: btn.getAttribute('data-gallons') || '1',
                    service: btn.getAttribute('data-service') || '5-Gallon Purified Water Refill'
                };

                this.open(data);
            });
        },

        open(data) {
            this.currentOrderData = data;

            if (this.orderIdInput) this.orderIdInput.value = data.orderId || '';
            if (this.orderNumEl) this.orderNumEl.textContent = data.orderNum || 'ORD-000000';
            if (this.customerNameEl) this.customerNameEl.textContent = data.customer || 'Customer';
            if (this.gallonsEl) this.gallonsEl.textContent = `${data.gallons} customer gallons`;
            if (this.serviceEl) this.serviceEl.textContent = data.service || '5-Gallon Purified Water Refill';
            if (this.addressEl) this.addressEl.textContent = data.address || 'Station Service Area';

            // Reset driver dropdown
            if (this.driverSelect) {
                this.driverSelect.value = '';
            }

            // Hide cards & error banner
            if (this.driverInfoCard) this.driverInfoCard.style.display = 'none';
            if (this.confirmBox) this.confirmBox.style.display = 'none';
            if (this.errorBanner) this.errorBanner.style.display = 'none';

            // Reset submit button state
            if (this.submitBtn) this.submitBtn.disabled = true;
            if (this.spinner) this.spinner.style.display = 'none';
            if (this.btnText) this.btnText.textContent = 'Assign Driver';

            // Show modal
            this.backdrop.style.display = 'flex';
            this.backdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            // Focus on driver select after animation
            setTimeout(() => {
                if (this.driverSelect) this.driverSelect.focus();
            }, 100);
        },

        close() {
            if (!this.backdrop) return;
            this.backdrop.style.display = 'none';
            this.backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            this.currentOrderData = null;
        },

        onDriverChange() {
            const selectedOpt = this.driverSelect.options[this.driverSelect.selectedIndex];
            if (!selectedOpt || !selectedOpt.value) {
                if (this.driverInfoCard) this.driverInfoCard.style.display = 'none';
                if (this.confirmBox) this.confirmBox.style.display = 'none';
                if (this.submitBtn) this.submitBtn.disabled = true;
                return;
            }

            const name = selectedOpt.getAttribute('data-name') || 'Driver';
            const phone = selectedOpt.getAttribute('data-phone') || 'N/A';
            const plate = selectedOpt.getAttribute('data-plate') || 'Assigned Vehicle';
            const deliveries = parseInt(selectedOpt.getAttribute('data-deliveries') || '0', 10);
            const status = selectedOpt.getAttribute('data-status') || 'Active';

            // Calculate initials
            const initials = name
                .split(' ')
                .map((n) => n[0])
                .slice(0, 2)
                .join('')
                .toUpperCase();

            // Populate driver info card
            if (this.driverAvatar) this.driverAvatar.textContent = initials;
            if (this.driverName) this.driverName.textContent = name;
            if (this.driverPhone) this.driverPhone.textContent = phone;
            if (this.driverVehicle) this.driverVehicle.textContent = plate;
            if (this.driverDeliveries) {
                this.driverDeliveries.textContent = `${deliveries} active ${deliveries === 1 ? 'delivery' : 'deliveries'}`;
            }

            if (this.driverBadge) {
                this.driverBadge.textContent = deliveries === 0 ? 'Ready / Available' : `${deliveries} Active Workload`;
                this.driverBadge.className = `driver-workload-badge ${deliveries === 0 ? 'badge-available' : 'badge-busy'}`;
            }

            if (this.driverInfoCard) this.driverInfoCard.style.display = 'block';

            // Update confirmation prompt
            if (this.confirmOrderRef && this.currentOrderData) {
                this.confirmOrderRef.textContent = this.currentOrderData.orderNum;
            }
            if (this.confirmDriverTarget) {
                this.confirmDriverTarget.textContent = name;
            }
            if (this.confirmBox) this.confirmBox.style.display = 'flex';

            // Enable submit button
            if (this.submitBtn) {
                this.submitBtn.disabled = false;
            }
        },

        async onSubmit(e) {
            e.preventDefault();

            const orderId = this.orderIdInput ? this.orderIdInput.value : '';
            const driverId = this.driverSelect ? this.driverSelect.value : '';

            if (!orderId || !driverId) {
                this.showError('Please select a driver to dispatch.');
                return;
            }

            const selectedOpt = this.driverSelect.options[this.driverSelect.selectedIndex];
            const driverName = selectedOpt ? selectedOpt.getAttribute('data-name') : 'Driver';
            const driverPlate = selectedOpt ? selectedOpt.getAttribute('data-plate') : 'Assigned';

            // UI Loading state
            if (this.submitBtn) this.submitBtn.disabled = true;
            if (this.spinner) this.spinner.style.display = 'inline-block';
            if (this.btnText) this.btnText.textContent = 'Assigning Dispatch...';
            if (this.errorBanner) this.errorBanner.style.display = 'none';

            const csrfToken = getCsrfToken();
            const url = this.form.getAttribute('action') || '/admin/deliveries/assign';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        driver_id: driverId,
                        _token: csrfToken
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    let errMsg = data.message || 'Unable to assign driver.';
                    if (data.errors) {
                        const errs = Object.values(data.errors).flat();
                        if (errs.length > 0) errMsg = errs.join(' ');
                    }
                    throw new Error(errMsg);
                }

                // Success!
                const orderNum = this.currentOrderData ? this.currentOrderData.orderNum : `#${orderId.substring(0, 8)}`;
                this.close();

                Toast.show(data.message || `Order ${orderNum} successfully assigned to ${driverName}!`, 'success', 5000);

                // Update in-place if on Orders table
                this.updateTableRow(orderId, driverName, driverPlate);

                // If on Order Show page, reload to refresh full timeline & cards
                if (document.querySelector('.orders-show-header')) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 900);
                }

            } catch (err) {
                this.showError(err.message || 'Server error occurred during driver assignment.');
                Toast.show(err.message || 'Assignment failed.', 'error', 5000);
            } finally {
                if (this.submitBtn && this.backdrop.style.display !== 'none') {
                    this.submitBtn.disabled = false;
                }
                if (this.spinner) this.spinner.style.display = 'none';
                if (this.btnText) this.btnText.textContent = 'Assign Driver';
            }
        },

        showError(message) {
            if (this.errorText) this.errorText.textContent = message;
            if (this.errorBanner) this.errorBanner.style.display = 'flex';
        },

        updateTableRow(orderId, driverName, driverPlate) {
            const row = document.getElementById(`order-row-${orderId}`);
            if (!row) return;

            // 1. Update row data-status
            row.setAttribute('data-status', 'assigned');

            // 2. Update status badge
            const statusCell = row.querySelector('.td-status');
            if (statusCell) {
                statusCell.innerHTML = `
                    <span class="status-badge status-assigned">
                        <span class="status-dot" aria-hidden="true"></span>
                        <span>Assigned</span>
                    </span>
                `;
            }

            // 3. Update driver cell
            const driverCell = row.querySelector('.td-driver');
            if (driverCell) {
                const initials = driverName
                    .split(' ')
                    .map((n) => n[0])
                    .slice(0, 2)
                    .join('')
                    .toUpperCase();

                driverCell.innerHTML = `
                    <div class="driver-assigned-cell">
                        <div class="driver-initials-badge">${escapeHtml(initials)}</div>
                        <div class="driver-info-wrap">
                            <span class="driver-name-text">${escapeHtml(driverName)}</span>
                            <small class="driver-plate">${escapeHtml(driverPlate || 'Assigned')}</small>
                        </div>
                    </div>
                `;
            }

            // 4. Update actions cell
            const actionsCell = row.querySelector('.td-actions');
            if (actionsCell) {
                actionsCell.innerHTML = `
                    <a href="/admin/orders/${encodeURIComponent(orderId)}" class="btn-table-action">
                        <span>View</span>
                    </a>
                `;
            }

            // 5. Visual highlight animation
            row.classList.add('row-just-assigned');
            setTimeout(() => {
                row.classList.remove('row-just-assigned');
            }, 2500);

            // 6. Update KPI summary cards if present
            this.decrementKpi('kpi-count-confirmed');
            this.incrementKpi('kpi-count-assigned');
        },

        decrementKpi(kpiId) {
            const el = document.getElementById(kpiId);
            if (!el) return;
            const current = parseInt(el.textContent.replace(/[^0-9]/g, ''), 10);
            if (!isNaN(current) && current > 0) {
                el.textContent = current - 1;
            }
        },

        incrementKpi(kpiId) {
            const el = document.getElementById(kpiId);
            if (!el) return;
            const current = parseInt(el.textContent.replace(/[^0-9]/g, ''), 10);
            if (!isNaN(current)) {
                el.textContent = current + 1;
            }
        }
    };

    // =========================================================================
    // 3. ORDERS FILTERING & DEBOUNCE
    // =========================================================================
    const OrdersFilter = {
        form: null,
        searchInput: null,
        debounceTimer: null,

        init() {
            this.form = document.getElementById('ordersFilterForm');
            if (!this.form) return;

            this.searchInput = document.getElementById('ordersSearchInput');

            if (this.searchInput) {
                this.searchInput.addEventListener('input', () => {
                    clearTimeout(this.debounceTimer);
                    this.debounceTimer = setTimeout(() => {
                        this.form.submit();
                    }, 450);
                });
            }

            // Auto-submit on select and date changes
            const filterControls = this.form.querySelectorAll('select, input[type="date"]');
            filterControls.forEach((el) => {
                el.addEventListener('change', () => {
                    this.form.submit();
                });
            });
        }
    };

    // =========================================================================
    // 4. CHECKBOX SELECTION HELPERS
    // =========================================================================
    const OrderSelection = {
        init() {
            const selectAll = document.getElementById('selectAllOrders');
            if (!selectAll) return;

            const checkboxes = document.querySelectorAll('.order-select-checkbox');

            selectAll.addEventListener('change', () => {
                checkboxes.forEach((cb) => {
                    cb.checked = selectAll.checked;
                });
            });

            checkboxes.forEach((cb) => {
                cb.addEventListener('change', () => {
                    const allChecked = Array.from(checkboxes).every((c) => c.checked);
                    const someChecked = Array.from(checkboxes).some((c) => c.checked);
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = someChecked && !allChecked;
                });
            });
        }
    };

    // Re-render the order KPIs and results from the server after a persisted
    // database event. This keeps all filter, pagination, and authorization
    // behavior in the existing Laravel page while avoiding a browser reload.
    const OrderRealtime = {
        timer: null,
        fallbackTimer: null,
        inFlight: false,
        queued: false,
        connected: false,

        init() {
            if (!document.querySelector('.orders-workspace')) return;
            document.addEventListener('submit', async (event) => {
                const form = event.target.closest('form[data-live-refill-action]');
                if (!form) return;
                event.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                if (button?.disabled) return;
                const originalText = button?.textContent;
                if (button) {
                    button.disabled = true;
                    button.textContent = 'Updating…';
                }
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        cache: 'no-store'
                    });
                    const result = await response.json();
                    if (!response.ok || result.success === false) {
                        throw new Error(result.message || 'Unable to update the refill workflow.');
                    }
                    Toast.show(result.message || 'Refill workflow updated.', 'success');
                    this.schedule();
                } catch (error) {
                    Toast.show(error.message || 'Unable to update the refill workflow.', 'error');
                } finally {
                    if (button?.isConnected) {
                        button.disabled = false;
                        button.textContent = originalText || 'Refill';
                    }
                }
            });
            window.addEventListener('aqua:realtime', (event) => {
                const entity = event.detail?.entity_type;
                if (['order', 'delivery', 'payment'].includes(entity)) this.schedule();
            });
            window.addEventListener('aqua:realtime-status', (event) => {
                this.connected = Boolean(event.detail?.connected);
                if (this.connected && this.fallbackTimer) {
                    clearInterval(this.fallbackTimer);
                    this.fallbackTimer = null;
                } else if (!this.connected && !this.fallbackTimer) {
                    this.fallbackTimer = window.setInterval(() => this.schedule(), 30000);
                }
            });
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && !this.connected) this.schedule();
            });
            window.addEventListener('beforeunload', () => {
                clearTimeout(this.timer);
                clearInterval(this.fallbackTimer);
            }, { once: true });
            this.connected = Boolean(window.aquaRealtimeConnected);
            if (!this.connected) this.fallbackTimer = window.setInterval(() => this.schedule(), 30000);
        },

        schedule() {
            if (document.visibilityState !== 'visible') return;
            clearTimeout(this.timer);
            this.timer = window.setTimeout(() => this.refresh(), 180);
        },

        async refresh() {
            if (this.inFlight) {
                this.queued = true;
                return;
            }
            const currentStats = document.querySelector('.orders-stats');
            const currentWorkspace = document.querySelector('.orders-workspace');
            if (!currentStats || !currentWorkspace) return;
            this.inFlight = true;
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 12000);
            try {
                const response = await fetch(window.location.href, {
                    headers: { Accept: 'text/html' },
                    cache: 'no-store',
                    signal: controller.signal
                });
                if (!response.ok) return;
                const html = await response.text();
                const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                const nextStats = nextDocument.querySelector('.orders-stats');
                const nextWorkspace = nextDocument.querySelector('.orders-workspace');
                if (!nextStats || !nextWorkspace || !currentStats.isConnected || !currentWorkspace.isConnected) return;
                currentStats.replaceWith(nextStats);
                currentWorkspace.replaceWith(nextWorkspace);
                OrdersFilter.init();
                OrderSelection.init();
            } catch (error) {
                if (error.name !== 'AbortError') console.warn('[Orders] Live refresh failed.', error);
            } finally {
                window.clearTimeout(timeout);
                this.inFlight = false;
                if (this.queued) {
                    this.queued = false;
                    this.schedule();
                }
            }
        }
    };

    // =========================================================================
    // 5. BOOTSTRAP ON DOM READY
    // =========================================================================
    document.addEventListener('DOMContentLoaded', () => {
        Toast.init();
        AssignDriverModal.init();
        OrdersFilter.init();
        OrderSelection.init();
        OrderRealtime.init();

        const confirmAllButton = document.getElementById('confirmAllOrders');
        confirmAllButton?.addEventListener('click', async () => {
            const count = Number(confirmAllButton.dataset.count || 0);
            if (!count) { Toast.show('There are no pending orders to confirm.', 'info'); return; }
            if (!window.confirm(`Confirm All Pending Orders?\n\nYou are about to confirm ${count} pending customer orders. This action will update their order statuses and process eligible delivery assignments.`)) return;
            confirmAllButton.disabled = true;
            try {
                const response = await fetch(confirmAllButton.dataset.url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Bulk confirmation failed.');
                const failures = (result.failed || []).map(item => `${item.order_id}: ${item.reason}`).join('\n');
                const assignmentFailures = (result.assignment_failed || []).map(item => `${item.order_id}: ${item.reason}`).join('\n');
                const summary = `Successfully Confirmed: ${result.confirmed}\nConfirmation Failed: ${(result.failed || []).length}\nSkipped: ${result.skipped}\nAutomatically Assigned to testdriver123@example.com: ${result.automatically_assigned}\nAssignment Failed: ${(result.assignment_failed || []).length}`;
                window.alert(summary + (failures ? `\n\nConfirmation failure details:\n${failures}` : '') + (assignmentFailures ? `\n\nAssignment failure details:\n${assignmentFailures}` : ''));
                window.location.reload();
            } catch (error) {
                Toast.show(error.message || 'Bulk confirmation failed.', 'error', 6000);
            } finally {
                confirmAllButton.disabled = false;
            }
        });
    });

})();
