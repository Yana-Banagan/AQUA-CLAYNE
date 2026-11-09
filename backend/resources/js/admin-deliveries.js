/**
 * AQUA CLAYNE — Admin Deliveries Dashboard
 * admin-deliveries.js
 *
 * Architecture:
 *   DeliveriesDashboard object — modular, no external dependencies beyond
 *   the Supabase JS client (loaded from CDN in the Blade template).
 *
 * Security model:
 *   All mutations go through Laravel backend (CSRF-protected JSON endpoints).
 *   Supabase Realtime is used only as a signal channel via the realtime_events
 *   table — full data always fetched from the authenticated Laravel endpoint.
 *
 * Config injection (from Blade):
 *   window.deliveryConfig = {
 *       listUrl, assignUrl, detailUrl,
 *       csrfToken, supabaseUrl, supabaseAnonKey
 *   };
 */

'use strict';

/* ==========================================================================
   TOAST NOTIFICATION SYSTEM
   Matches the pattern used in admin-orders.js (window.Toast)
   ========================================================================== */
const Toast = {
    container: null,

    _ensureContainer() {
        if (!this.container) {
            this.container = document.getElementById('toastContainer');
        }
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toastContainer';
            this.container.className = 'toast-container';
            this.container.setAttribute('aria-live', 'polite');
            document.body.appendChild(this.container);
        }
    },

    show(message, type = 'info', duration = 4500) {
        this._ensureContainer();

        const icons = {
            success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            error:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
            warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            info:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
        };

        const toast = document.createElement('div');
        toast.className = `toast-item toast-${type}`;
        toast.setAttribute('role', 'status');
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${_escHtml(message)}</span>
            <button type="button" class="toast-close-btn" aria-label="Dismiss notification">&times;</button>
        `;

        toast.querySelector('.toast-close-btn').addEventListener('click', () => this.dismiss(toast));
        this.container.appendChild(toast);

        requestAnimationFrame(() => toast.classList.add('toast-visible'));

        if (duration > 0) {
            setTimeout(() => this.dismiss(toast), duration);
        }
    },

    dismiss(toast) {
        toast.classList.remove('toast-visible');
        toast.classList.add('toast-hiding');
        setTimeout(() => toast.parentNode && toast.parentNode.removeChild(toast), 320);
    },
};

window.Toast = Toast;

/* ==========================================================================
   UTILITIES
   ========================================================================== */
function _escHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
}

function _csrf() {
    return window.deliveryConfig?.csrfToken
        || document.querySelector('meta[name="csrf-token"]')?.content
        || '';
}

function _cfg(key) {
    return window.deliveryConfig?.[key] || null;
}

/* ==========================================================================
   DELIVERIES DASHBOARD MODULE
   ========================================================================== */
const DeliveriesDashboard = {

    searchTimeout:   null,
    heartbeatTimer:  null,
    supabaseClient:  null,
    realtimeChannel: null,
    realtimeConnected: false,
    pendingRealtimeUpdate: false,
    refreshDebounceTimer: null,
    statusRevision: null,
    statusRequest: null,
    isSubmitting:    false,

    // ── Bootstrap ──────────────────────────────────────────────────────────
    init() {
        this.bindFilters();
        this.bindSearch();
        this.bindActions();
        this.bindModals();
        this.bindDriverSelect();
        this.bindOrderSelect();
        this.initRealtime();
        this.initHeartbeat();
    },

    // ── Filter selects auto-submit ──────────────────────────────────────────
    bindFilters() {
        const form = document.getElementById('deliveryFilterForm');
        if (!form) return;

        form.querySelectorAll('select, input[type="date"]').forEach(el => {
            el.addEventListener('change', () => form.submit());
        });
    },

    // ── Search input with 300ms debounce ───────────────────────────────────
    bindSearch() {
        const input = document.getElementById('deliverySearchInput');
        if (!input) return;

        input.addEventListener('input', () => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                const form = document.getElementById('deliveryFilterForm');
                if (form) form.submit();
            }, 300);
        });
    },

    // ── Button / click actions ─────────────────────────────────────────────
    bindActions() {
        // Manual refresh button
        const refreshBtn = document.getElementById('btnRefreshDeliveries');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.refresh(true);
            });
        }

        // "View Details" buttons (live panel + table) — delegated
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-view-delivery');
            if (btn) {
                e.preventDefault();
                const id = btn.dataset.deliveryId;
                if (id) this.viewDelivery(id);
            }
        });

        // "Assign Driver" trigger buttons — delegated
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-open-assign-modal');
            if (btn) {
                e.preventDefault();
                this.openAssignModal({
                    orderId:      btn.dataset.orderId      || '',
                    deliveryId:   btn.dataset.deliveryId   || '',
                    orderNumber:  btn.dataset.orderNumber  || '',
                    customerName: btn.dataset.customerName || '',
                    address:      btn.dataset.address      || '',
                });
            }
        });

        // Assign form submission
        const assignForm = document.getElementById('assignDriverForm');
        if (assignForm) {
            assignForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitAssignment();
            });
        }
    },

    // ── Modal lifecycle ────────────────────────────────────────────────────
    bindModals() {
        // Close on backdrop click
        document.addEventListener('click', (e) => {
            if (e.target.matches('.delivery-modal-backdrop')) this.closeAllModals();
        });

        // Close on .modal-close or .btn-cancel-modal click
        document.addEventListener('click', (e) => {
            if (e.target.closest('.modal-close') || e.target.closest('.btn-cancel-modal')) {
                this.closeAllModals();
            }
        });

        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeAllModals();
        });
    },

    closeAllModals() {
        document.querySelectorAll('.delivery-modal-backdrop.is-open').forEach(m => {
            m.classList.remove('is-open');
        });
        document.body.style.overflow = '';
    },

    // ── Assign Driver Modal ────────────────────────────────────────────────
    openAssignModal(data = {}) {
        const modal = document.getElementById('assignDriverModal');
        if (!modal) return;

        // Reset form state
        const form = document.getElementById('assignDriverForm');
        if (form) form.reset();
        this._hideAssignError();
        this._hideDriverPreview();
        this._disableAssignSubmit(true);

        if (data.orderId || data.deliveryId) {
            // Pre-filled from a specific delivery row
            this._setVal('assignModalOrderId',    data.orderId);
            this._setVal('assignModalDeliveryId', data.deliveryId);
            this._setText('assignModalOrderDisplay',   data.orderNumber ? '#' + data.orderNumber : '—');
            this._setText('assignModalCustomerDisplay', data.customerName || 'Customer');
            this._setText('assignModalAddressDisplay',  data.address     || 'Station Pickup');

            this._show('assignModalOrderInfoWrap');
            this._hide('assignModalOrderSelectWrap');
        } else {
            // Opened from header button — show order selector
            this._setVal('assignModalOrderId',    '');
            this._setVal('assignModalDeliveryId', '');
            this._show('assignModalOrderSelectWrap');
            this._hide('assignModalOrderInfoWrap');
        }

        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        // Focus driver select after animation
        setTimeout(() => {
            document.getElementById('assignModalDriverSelect')?.focus();
        }, 150);
    },

    // ── Order selector change (populates hidden fields) ────────────────────
    bindOrderSelect() {
        const sel = document.getElementById('assignModalOrderSelect');
        if (!sel) return;

        sel.addEventListener('change', () => {
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) return;

            this._setVal('assignModalOrderId',    opt.value);
            this._setVal('assignModalDeliveryId', opt.dataset.deliveryId || '');

            this._setText('assignModalOrderDisplay',
                opt.dataset.orderNumber ? '#' + opt.dataset.orderNumber : '—');
            this._setText('assignModalCustomerDisplay', opt.dataset.customer || 'Customer');
            this._setText('assignModalAddressDisplay',  opt.dataset.address  || 'Station Pickup');

            this._show('assignModalOrderInfoWrap');
        });
    },

    // ── Driver select — live preview card ──────────────────────────────────
    bindDriverSelect() {
        const sel = document.getElementById('assignModalDriverSelect');
        if (!sel) return;

        sel.addEventListener('change', () => {
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) {
                this._hideDriverPreview();
                this._disableAssignSubmit(true);
                return;
            }

            const name       = opt.dataset.name       || 'Driver';
            const phone      = opt.dataset.phone      || 'N/A';
            const plate      = opt.dataset.plate      || 'N/A';
            const deliveries = parseInt(opt.dataset.deliveries || '0', 10);

            const initials = name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();

            this._setText('driverPreviewAvatar',    initials);
            this._setText('driverPreviewName',      name);
            this._setText('driverPreviewPhone',     phone);
            this._setText('driverPreviewPlate',     plate);
            this._setText('driverPreviewDeliveries', String(deliveries));

            const badge = document.getElementById('driverPreviewBadge');
            if (badge) {
                if (deliveries === 0) {
                    badge.textContent = 'Available';
                    badge.className = 'driver-preview-badge';
                } else {
                    badge.textContent = `${deliveries} Active`;
                    badge.className = 'driver-preview-badge busy';
                }
            }

            this._showEl('driverPreviewCard');
            this._disableAssignSubmit(false);
        });
    },

    // ── Assignment submission ──────────────────────────────────────────────
    async submitAssignment() {
        if (this.isSubmitting) return;

        const orderId    = this._getVal('assignModalOrderId');
        const deliveryId = this._getVal('assignModalDeliveryId');
        const driverId   = this._getVal('assignModalDriverSelect');
        const eta        = this._getVal('assignModalEtaInput');

        if (!driverId) {
            this._showAssignError('Please select an active driver.');
            return;
        }
        if (!orderId && !deliveryId) {
            this._showAssignError('Please select an order to assign.');
            return;
        }

        this.isSubmitting = true;
        this._disableAssignSubmit(true);
        this._setSubmitText('Assigning…');
        this._showSpinner(true);
        this._hideAssignError();

        const assignUrl = _cfg('assignUrl') || '/admin/deliveries/assign';

        try {
            const payload = { driver_id: driverId };
            if (orderId)    payload.order_id    = orderId;
            if (deliveryId) payload.delivery_id = deliveryId;
            if (eta)        payload.eta         = eta;

            const resp = await fetch(assignUrl, {
                method: 'POST',
                headers: {
                    'Content-Type':  'application/json',
                    'Accept':        'application/json',
                    'X-CSRF-TOKEN':  _csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const data = await resp.json();

            if (!resp.ok) {
                // Flatten Laravel validation errors
                let msg = data.message || 'Unable to assign driver.';
                if (data.errors) {
                    const flat = Object.values(data.errors).flat();
                    if (flat.length) msg = flat[0];
                }
                throw new Error(msg);
            }

            Toast.show(data.message || 'Driver successfully assigned!', 'success');
            this.closeAllModals();
            this.refresh(false);

        } catch (err) {
            this._showAssignError(err.message || 'Unable to complete assignment.');
            Toast.show(err.message || 'Assignment failed.', 'error');
        } finally {
            this.isSubmitting = false;
            this._disableAssignSubmit(false);
            this._setSubmitText('Assign Driver');
            this._showSpinner(false);
        }
    },

    // ── Delivery Details Modal ─────────────────────────────────────────────
    async viewDelivery(deliveryId) {
        const modal = document.getElementById('deliveryDetailsModal');
        if (!modal) return;

        const body = document.getElementById('deliveryDetailsBody');
        const fullBtn = document.getElementById('deliveryDetailsFullPageBtn');

        // Show loading state
        if (body) {
            body.innerHTML = `
                <div class="modal-loading-state">
                    <div class="modal-spinner" aria-label="Loading"></div>
                    <span>Loading delivery details…</span>
                </div>`;
        }

        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        const baseUrl = _cfg('detailUrl') || '/admin/deliveries/:id';
        const detailUrl = baseUrl.replace(':id', encodeURIComponent(deliveryId));

        // Show "Open Full Page" link
        if (fullBtn) {
            fullBtn.href = detailUrl;
            fullBtn.style.display = 'inline-flex';
        }

        try {
            const resp = await fetch(detailUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!resp.ok) throw new Error('Unable to load delivery details.');

            const { delivery: d } = await resp.json();

            if (!body) return;

            body.innerHTML = `
                ${this._renderDetailHeader(d)}
                ${this._renderDetailCustomer(d)}
                ${this._renderDetailDriver(d)}
                ${this._renderDetailMeta(d)}
                ${this._renderDetailTimeline(d)}
                ${this._renderDetailProof(d)}
            `;

        } catch (err) {
            if (body) {
                body.innerHTML = `
                    <div style="text-align:center; padding: 32px; color: #EF4444;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block;margin:0 auto 8px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <strong>Unable to load delivery details.</strong>
                        <p style="font-size:13px; margin:4px 0 0; color:#94A3B8;">${_escHtml(err.message)}</p>
                    </div>`;
            }
        }
    },

    // Detail modal render helpers
    _renderDetailHeader(d) {
        return `
            <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:14px;border-bottom:1px solid var(--border-subtle, #EDF4F8);gap:10px;flex-wrap:wrap;">
                <div>
                    <span style="font-family:monospace;font-weight:800;font-size:15px;color:#0066CC;">#${_escHtml(d.id.substring(0,8).toUpperCase())}</span>
                    <div style="font-size:12px;color:#64748B;margin-top:2px;font-weight:600;">Order #${_escHtml(d.order_number)}</div>
                </div>
                <span class="delivery-status status-${_escHtml(d.status)}">${_escHtml(d.status_label)}</span>
            </div>`;
    },
    _renderDetailCustomer(d) {
        const addr = d.customer?.address;
        return `
            <div class="modal-info-box">
                <div class="modal-info-label">Customer &amp; Delivery Destination</div>
                <div style="font-weight:700;color:var(--text-heading,#0F172A);font-size:14px;margin-top:4px;">
                    ${_escHtml(d.customer?.name || '—')}
                    ${d.customer?.phone ? `<span style="font-weight:500;color:#64748B;"> · ${_escHtml(d.customer.phone)}</span>` : ''}
                </div>
                ${addr ? `<div style="font-size:13px;color:#475569;margin-top:4px;">📍 ${_escHtml(addr.full || addr.line1 || '—')}</div>` : '<div style="font-size:13px;color:#94A3B8;margin-top:4px;">Station Pickup</div>'}
                ${addr?.notes ? `<div style="font-size:12px;color:#D97706;margin-top:4px;">Note: ${_escHtml(addr.notes)}</div>` : ''}
            </div>`;
    },
    _renderDetailDriver(d) {
        if (!d.driver) {
            return `<div style="background:#F8FAFC;border:1px solid #EDF4F8;border-radius:12px;padding:14px 16px;font-size:13px;color:#94A3B8;">No driver assigned yet.</div>`;
        }
        const statusClass = d.driver.status === 'active' ? 'status-delivered' : 'status-failed';
        return `
            <div style="background:#F8FAFC;border:1px solid #EDF4F8;border-radius:12px;padding:14px 16px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#64748B;margin-bottom:8px;">Assigned Driver</div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                    <div>
                        <div style="font-weight:700;color:#0F172A;font-size:14px;">${_escHtml(d.driver.name)}</div>
                        <div style="font-size:12px;color:#64748B;margin-top:2px;">Plate: ${_escHtml(d.driver.plate)} · Tel: ${_escHtml(d.driver.phone)}</div>
                    </div>
                    <span class="delivery-status ${statusClass}" style="font-size:11px;">${_escHtml(d.driver.status)}</span>
                </div>
            </div>`;
    },
    _renderDetailMeta(d) {
        return `
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;font-size:12px;">
                ${[['ETA', d.eta], ['Created', d.created_at], [`Payment (${(d.payment?.method || 'cash').toUpperCase()})`, d.payment?.status?.toUpperCase() || 'PENDING']].map(([label, val]) => `
                    <div style="background:#FFFFFF;border:1px solid #E2EEF7;border-radius:8px;padding:10px;">
                        <span style="color:#64748B;display:block;margin-bottom:2px;">${_escHtml(label)}</span>
                        <strong style="color:#0F172A;">${_escHtml(String(val || '—'))}</strong>
                    </div>`).join('')}
            </div>`;
    },
    _renderDetailTimeline(d) {
        const items = d.history || [];
        return `
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#64748B;margin-bottom:10px;">Delivery Timeline</div>
                <div class="delivery-timeline" style="margin:0;">
                    ${items.length > 0
                        ? items.map(h => `
                            <div class="delivery-timeline-item done">
                                <div class="timeline-step-title">${_escHtml(h.status_label)}</div>
                                <div class="timeline-step-meta">${_escHtml(h.time)} · ${_escHtml(h.date)}${h.note ? ' — ' + _escHtml(h.note) : ''}</div>
                            </div>`).join('')
                        : '<p class="timeline-empty">No recorded status transitions yet.</p>'}
                </div>
            </div>`;
    },
    _renderDetailProof(d) {
        const renderMedia = (url, label, missingLabel) => {
            if (url) {
                return `
                    <div class="proof-media-card">
                        <strong class="proof-media-label">${_escHtml(label)}</strong>
                        <a href="${_escHtml(url)}" target="_blank" rel="noopener noreferrer" title="Open full size">
                            <img src="${_escHtml(url)}" alt="${_escHtml(label)}" />
                        </a>
                        <span class="proof-verified-badge">✓ Captured</span>
                    </div>`;
            }
            return `
                <div class="proof-media-card">
                    <strong class="proof-media-label">${_escHtml(label)}</strong>
                    <div class="proof-missing">${_escHtml(missingLabel)}</div>
                </div>`;
        };

        return `
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#64748B;margin-bottom:10px;">Proof of Delivery &amp; Signature</div>
                <div class="proof-media-grid">
                    ${renderMedia(d.proof_photo_url, 'Photo Proof', d.proof_status === 'skipped' ? 'Not provided (driver skipped).' : 'No proof photo submitted.')}
                    ${renderMedia(d.signature_url,   'Customer Signature',  'No signature recorded yet.')}
                </div>
            </div>`;
    },

    // ── AJAX Refresh — KPI cards, status counts, live deliveries panel ─────
    async refresh(manual = false) {
        const refreshBtn = document.getElementById('btnRefreshDeliveries');
        if (refreshBtn) refreshBtn.classList.add('spinning');

        const listUrl = _cfg('listUrl') || window.location.href;

        try {
            // Preserve current URL query filters when refreshing
            const url = new URL(listUrl, window.location.origin);
            const params = new URLSearchParams(window.location.search);
            params.forEach((v, k) => url.searchParams.set(k, v));

            const resp = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!resp.ok) throw new Error('Refresh request failed.');

            const data = await resp.json();

            // Update KPI cards
            if (data.stats) {
                this._setText('kpiTotalDeliveries', this._fmt(data.stats.total));
                this._setText('kpiPendingDeliveries', this._fmt(data.stats.pending));
                this._setText('kpiOnDelivery', this._fmt(data.stats.onDelivery));
                this._setText('kpiCompletedToday', this._fmt(data.stats.completedToday));
            }

            // Update status breakdown counts
            if (data.statusCounts) {
                this._setText('sbCountPending',   this._fmt(data.statusCounts.pending));
                this._setText('sbCountAssigned',  this._fmt(data.statusCounts.assigned));
                this._setText('sbCountEnRoute',   this._fmt(data.statusCounts.en_route));
                this._setText('sbCountDelivered', this._fmt(data.statusCounts.delivered));
                this._setText('sbCountFailed',    this._fmt(data.statusCounts.failed));
            }

            // Re-render live deliveries panel
            if (data.liveDeliveries !== undefined) {
                this.renderLiveDeliveries(data.liveDeliveries);
            }

            if (manual) Toast.show('Deliveries refreshed.', 'info', 2500);

        } catch (err) {
            if (manual) Toast.show('Unable to refresh delivery data.', 'error');
        } finally {
            if (refreshBtn) refreshBtn.classList.remove('spinning');
        }
    },

    scheduleRefresh() {
        if (this.refreshDebounceTimer) clearTimeout(this.refreshDebounceTimer);
        this.refreshDebounceTimer = setTimeout(() => {
            this.refreshDebounceTimer = null;
            this.refresh(false);
        }, 250);
    },

    async checkStatus() {
        const statusUrl = _cfg('statusUrl');
        if (!statusUrl || this.statusRequest || document.visibilityState !== 'visible') return;

        const controller = new AbortController();
        this.statusRequest = controller;
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
                signal: controller.signal,
            });
            if (!response.ok) return;
            const data = await response.json();
            if (data.stats) {
                this._setText('kpiTotalDeliveries', this._fmt(data.stats.total));
                this._setText('kpiPendingDeliveries', this._fmt(data.stats.pending));
                this._setText('kpiOnDelivery', this._fmt(data.stats.onDelivery));
                this._setText('kpiCompletedToday', this._fmt(data.stats.completedToday));
            }
            if (data.statusCounts) {
                this._setText('sbCountPending', this._fmt(data.statusCounts.pending));
                this._setText('sbCountAssigned', this._fmt(data.statusCounts.assigned));
                this._setText('sbCountEnRoute', this._fmt(data.statusCounts.en_route));
                this._setText('sbCountDelivered', this._fmt(data.statusCounts.delivered));
                this._setText('sbCountFailed', this._fmt(data.statusCounts.failed));
            }
            const revision = JSON.stringify(data.revision || []);
            if (this.statusRevision !== null && revision !== this.statusRevision) {
                this.statusRevision = revision;
                this.refresh(false);
            } else {
                this.statusRevision = revision;
            }
        } catch (_) {
            // Realtime or the next lightweight status check can recover.
        } finally {
            clearTimeout(timeout);
            this.statusRequest = null;
        }
    },

    // ── Live Deliveries Panel Renderer ─────────────────────────────────────
    renderLiveDeliveries(items) {
        const container = document.getElementById('liveDeliveriesContainer');
        if (!container) return;

        if (!items || items.length === 0) {
            container.innerHTML = `
                <div class="live-empty-state">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    <strong>No active deliveries on route</strong>
                    <p>Deliveries in transit will appear here automatically.</p>
                </div>`;
            return;
        }

        container.innerHTML = items.map(d => `
            <div class="live-delivery-item">
                <div class="live-item-top">
                    <div class="live-ids">
                        <span class="live-delivery-id">#${_escHtml(d.id.substring(0,8).toUpperCase())}</span>
                        <span class="live-order-id">ORD&nbsp;#${_escHtml(d.order_number)}</span>
                    </div>
                    <span class="delivery-status status-${_escHtml(d.status)}">${_escHtml(d.status_label)}</span>
                </div>
                <div class="live-customer-info">
                    <span class="live-customer-name">${_escHtml(d.customer_name)}</span>
                    <span class="live-driver-info">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                        ${_escHtml(d.driver_name)}
                    </span>
                    <span class="live-address">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        ${_escHtml(d.address)}
                    </span>
                </div>
                <div class="live-item-footer">
                    <div class="live-eta">
                        <small>ETA</small>
                        ${_escHtml(d.eta)}
                    </div>
                    <button type="button" class="delivery-action btn-view-delivery"
                            data-delivery-id="${_escHtml(d.id)}"
                            aria-label="View delivery details">
                        View Details
                    </button>
                </div>
            </div>`).join('');
    },

    // ── Supabase Realtime Signal Listener ──────────────────────────────────
    initRealtime() {
        this.realtimeConnected = Boolean(window.aquaRealtimeConnected);
        this._handleRealtimeSignal = (event) => {
            const entityType = event.detail?.entity_type;
            if (!['delivery', 'order', 'pickup_batch', 'delivery_route'].includes(entityType)) return;
            if (document.visibilityState !== 'visible') {
                this.pendingRealtimeUpdate = true;
                return;
            }
            this.scheduleRefresh();
        };
        this._handleRealtimeStatus = (event) => {
            this.realtimeConnected = Boolean(event.detail?.connected);
            if (this.realtimeConnected) this._stopHeartbeat();
            else this._startHeartbeat();
        };
        window.addEventListener('aqua:realtime', this._handleRealtimeSignal);
        window.addEventListener('aqua:realtime-status', this._handleRealtimeStatus);
    },

    // ── Heartbeat fallback polling (30s) ───────────────────────────────────
    initHeartbeat() {
        this._startHeartbeat();
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState !== 'visible') {
                this._stopHeartbeat();
            } else if (this.pendingRealtimeUpdate) {
                this.pendingRealtimeUpdate = false;
                this.scheduleRefresh();
            } else if (!this.realtimeConnected) {
                this._startHeartbeat();
                this.checkStatus();
            }
        });
        window.addEventListener('beforeunload', () => {
            this._stopHeartbeat();
            if (this.refreshDebounceTimer) clearTimeout(this.refreshDebounceTimer);
            if (this.statusRequest) this.statusRequest.abort();
            window.removeEventListener('aqua:realtime', this._handleRealtimeSignal);
            window.removeEventListener('aqua:realtime-status', this._handleRealtimeStatus);
        }, { once: true });
    },

    _startHeartbeat() {
        if (document.visibilityState !== 'visible' || this.realtimeConnected || this.heartbeatTimer) return; // already running, connected, or hidden
        this.checkStatus();
        this.heartbeatTimer = setInterval(() => this.checkStatus(), 30_000);
    },

    _stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    },

    // ── Small DOM helpers ──────────────────────────────────────────────────
    _getVal(id)          { return document.getElementById(id)?.value?.trim() || ''; },
    _setVal(id, v)       { const el = document.getElementById(id); if (el) el.value = v; },
    _setText(id, v)      { const el = document.getElementById(id); if (el) el.textContent = v; },
    _show(id)            { const el = document.getElementById(id); if (el) el.style.display = ''; },
    _hide(id)            { const el = document.getElementById(id); if (el) el.style.display = 'none'; },
    _showEl(id)          { const el = document.getElementById(id); if (el) el.style.display = 'block'; },
    _fmt(n)              { return typeof n === 'number' ? n.toLocaleString() : String(n ?? 0); },

    _disableAssignSubmit(disabled) {
        const btn = document.getElementById('assignModalSubmitBtn');
        if (btn) btn.disabled = disabled;
    },
    _setSubmitText(text) {
        const el = document.getElementById('assignBtnText');
        if (el) el.textContent = text;
    },
    _showSpinner(show) {
        const el = document.getElementById('assignSpinner');
        if (el) {
            el.style.display = show ? 'inline-block' : 'none';
            if (show) el.classList.add('active');
            else      el.classList.remove('active');
        }
    },
    _showAssignError(msg) {
        const banner = document.getElementById('assignModalError');
        const text   = document.getElementById('assignModalErrorText');
        if (text)   text.textContent = msg;
        if (banner) banner.style.display = 'flex';
    },
    _hideAssignError() {
        const banner = document.getElementById('assignModalError');
        if (banner) banner.style.display = 'none';
    },
    _hideDriverPreview() {
        const el = document.getElementById('driverPreviewCard');
        if (el) el.style.display = 'none';
    },
};

/* ==========================================================================
   BOOT
   ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
    DeliveriesDashboard.init();
});
