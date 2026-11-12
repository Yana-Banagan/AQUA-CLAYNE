/**
 * AQUA CLAYNE - Complete Customer Web Application
 * Single unified client-side application managing state, routing,
 * ordering checkout, delivery tracking, customer wallet, realtime events,
 * support chat, and account management.
 */

(function () {
    'use strict';

    // =========================================================================
    // 1. STATE STORE
    // =========================================================================
    const AppState = {
        token: window.aquaCustomerToken || localStorage.getItem('aqua_customer_token') || null,
        user: window.aquaAuthenticatedUser || JSON.parse(localStorage.getItem('aqua_customer_user') || 'null'),
        cart: JSON.parse(localStorage.getItem('aqua_customer_cart') || '{"items":[], "deliveryMethod":"home_delivery", "selectedAddressId":null, "expectedGallons":1, "notes":"", "paymentMethod":"online"}'),
        currentView: 'home',
        products: window.aquaPreloadedProducts || [],
        categories: ['All', 'Purified', 'Mineral'],
        selectedCategory: 'All',
        selectedService: null,
        serviceQuantity: 1,
        orders: [],
        activeOrder: null,
        orderFilter: 'All',
        orderDetail: null,
        trackingMap: null,
        trackingMarkerStation: null,
        trackingMarkerDest: null,
        trackingMarkerDriver: null,
        trackingPolyline: null,
        addresses: [],
        payments: [],
        paymentSummary: { outstandingBalance: 0, totalPaid: 0, allPaidUp: true, totalCount: 0 },
        paymentFilter: 'ALL',
        paymentRules: { first_order: true, methods: ['online'] },
        paymentProvider: 'qrph',
        creditAccount: null,
        notifications: [],
        unreadNotifCount: 0,
        notifFilter: 'All',
        complaints: [],
        complaintCategories: [],
        complaintFilter: 'ALL',
        complaintDetail: null,
        faqs: [],
        faqQuery: '',
        chatMessages: [],
        chatSending: false,
        realtimeConnected: false,
        checkoutStep: 1,
        station: window.aquaStationConfig || {
            name: 'Aqua Clayne Water Refilling Station',
            address: 'Aqua Clayne Water Refilling Station, 007 Batisan St., Brgy. Gatid, Santa Cruz, Laguna',
            latitude: 14.265111,
            longitude: 121.405602,
            delivery_fee: 5.00
        }
    };

    // Ensure valid cart object structure
    if (!Array.isArray(AppState.cart.items)) {
        AppState.cart.items = [];
    }

    // =========================================================================
    // 2. HTTP API HELPER (Communicates with /api/v1/...)
    // =========================================================================
    async function apiRequest(endpoint, method = 'GET', data = null, requireAuth = true) {
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };

        if (AppState.token) {
            headers['Authorization'] = `Bearer ${AppState.token}`;
        } else if (requireAuth) {
            window.location.href = '/admin/login';
            throw new Error('Authentication required');
        }

        const config = {
            method: method,
            headers: headers
        };

        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            config.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`/api/v1${endpoint}`, config);
            const resJson = await response.json().catch(() => null);

            if (response.status === 401 && requireAuth) {
                // Token expired or invalid
                localStorage.removeItem('aqua_customer_token');
                localStorage.removeItem('aqua_customer_user');
                window.location.href = '/admin/login';
                throw new Error('Session expired');
            }

            if (!response.ok) {
                const errorMsg = resJson?.message || (resJson?.errors ? Object.values(resJson.errors).flat().join(' ') : 'Request failed.');
                throw new Error(errorMsg);
            }

            return resJson;
        } catch (error) {
            console.error(`[API Error] ${method} ${endpoint}:`, error);
            throw error;
        }
    }

    // =========================================================================
    // 3. TOAST NOTIFICATIONS & CONFIRMATIONS
    // =========================================================================
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast-message toast-${type}`;
        toast.innerHTML = `
            <span>${escapeHtml(message)}</span>
        `;

        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3800);
    }

    function showConfirmDialog({ title, message, confirmText = 'Confirm', isDanger = false, onConfirm }) {
        const modal = document.getElementById('confirmModal');
        const titleEl = document.getElementById('confirmModalTitle');
        const msgEl = document.getElementById('confirmModalMsg');
        const btn = document.getElementById('confirmModalBtn');

        if (!modal || !titleEl || !msgEl || !btn) {
            if (window.confirm(`${title}\n\n${message}`)) {
                if (typeof onConfirm === 'function') onConfirm();
            }
            return;
        }

        titleEl.textContent = title;
        msgEl.textContent = message;
        btn.textContent = confirmText;

        const iconCircle = document.getElementById('confirmIconCircle');
        if (isDanger) {
            btn.className = 'btn-modal-save danger-confirm-btn';
            btn.style.background = '#DC2626';
            btn.style.color = '#FFFFFF';
            btn.style.borderColor = '#DC2626';
            if (iconCircle) {
                iconCircle.className = 'confirm-icon-circle danger-icon-circle';
            }
        } else {
            btn.className = 'btn-modal-save';
            btn.style.background = '#0879D1';
            btn.style.color = '#FFFFFF';
            btn.style.borderColor = '#0879D1';
            if (iconCircle) {
                iconCircle.className = 'confirm-icon-circle primary-icon-circle';
            }
        }

        btn.onclick = () => {
            closeModal('confirmModal');
            if (typeof onConfirm === 'function') onConfirm();
        };

        openModal('confirmModal');
    }

    // =========================================================================
    // 4. ROUTING & VIEW NAVIGATION
    // =========================================================================
    function toggleMobileMenu(show) {
        const drawer = document.getElementById('mobileNavDrawer');
        const toggleBtn = document.getElementById('navMobileToggle');
        if (!drawer) return;
        const willShow = typeof show === 'boolean' ? show : (!drawer.classList.contains('show') && !drawer.classList.contains('open'));
        drawer.classList.toggle('show', willShow);
        drawer.classList.toggle('open', willShow);
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        }
    }

    function toggleNotifDropdown(event) {
        if (event) event.stopPropagation();
        const popover = document.getElementById('navNotifPopover');
        const userDropdown = document.getElementById('userProfileDropdown');
        if (userDropdown) userDropdown.classList.remove('show');
        if (popover) {
            popover.classList.toggle('show');
        }
    }

    function toggleSidebar(show) {
        const sidebar = document.getElementById('customerSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar) return;
        if (show === undefined) {
            sidebar.classList.toggle('open');
        } else if (show) {
            sidebar.classList.add('open');
        } else {
            sidebar.classList.remove('open');
        }
        if (backdrop) {
            backdrop.classList.toggle('active', sidebar.classList.contains('open'));
        }
    }

    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    function enlargeQr(tagUid, qrData, containerType, orderNumber) {
        const modal = document.getElementById('qrEnlargeModal');
        if (!modal) return;
        const tagUidEl = document.getElementById('qrEnlargeTagUid');
        const containerTypeEl = document.getElementById('qrEnlargeContainerType');
        const svgWrap = document.getElementById('qrEnlargeSvgWrap');
        if (tagUidEl) tagUidEl.textContent = tagUid || 'AC-GAL-0001';
        if (containerTypeEl) containerTypeEl.textContent = `${containerType || '5-Gallon Container'} • Order #${orderNumber || 'Active'}`;
        if (svgWrap) {
            svgWrap.innerHTML = `
                <svg width="200" height="200" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="120" height="120" fill="white"/>
                    <rect x="10" y="10" width="30" height="30" rx="4" fill="#12344D"/>
                    <rect x="16" y="16" width="18" height="18" rx="2" fill="white"/>
                    <rect x="20" y="20" width="10" height="10" rx="1" fill="#0878C9"/>
                    <rect x="80" y="10" width="30" height="30" rx="4" fill="#12344D"/>
                    <rect x="86" y="16" width="18" height="18" rx="2" fill="white"/>
                    <rect x="90" y="20" width="10" height="10" rx="1" fill="#0878C9"/>
                    <rect x="10" y="80" width="30" height="30" rx="4" fill="#12344D"/>
                    <rect x="16" y="86" width="18" height="18" rx="2" fill="white"/>
                    <rect x="20" y="90" width="10" height="10" rx="1" fill="#0878C9"/>
                    <rect x="48" y="14" width="6" height="6" fill="#12344D"/>
                    <rect x="58" y="14" width="6" height="6" fill="#12344D"/>
                    <rect x="48" y="24" width="6" height="6" fill="#0878C9"/>
                    <rect x="66" y="24" width="6" height="6" fill="#12344D"/>
                    <rect x="14" y="48" width="6" height="6" fill="#12344D"/>
                    <rect x="24" y="48" width="6" height="6" fill="#0878C9"/>
                    <rect x="34" y="48" width="6" height="6" fill="#12344D"/>
                    <rect x="80" y="48" width="6" height="6" fill="#12344D"/>
                    <rect x="92" y="48" width="6" height="6" fill="#0878C9"/>
                    <rect x="100" y="48" width="6" height="6" fill="#12344D"/>
                    <rect x="48" y="80" width="6" height="6" fill="#0878C9"/>
                    <rect x="58" y="80" width="6" height="6" fill="#12344D"/>
                    <rect x="68" y="80" width="6" height="6" fill="#12344D"/>
                    <rect x="80" y="80" width="6" height="6" fill="#12344D"/>
                    <rect x="92" y="80" width="6" height="6" fill="#0878C9"/>
                    <rect x="48" y="96" width="6" height="6" fill="#12344D"/>
                    <rect x="60" y="96" width="6" height="6" fill="#0878C9"/>
                    <rect x="76" y="96" width="6" height="6" fill="#12344D"/>
                    <circle cx="60" cy="60" r="14" fill="#0878C9"/>
                    <path d="M60 52C60 52 54 58.5 54 62C54 65.3 56.7 68 60 68C63.3 68 66 65.3 66 62C66 58.5 60 52 60 52Z" fill="white"/>
                </svg>
            `;
        }
        openModal('qrEnlargeModal');
    }

    function trackByOrderNumber(orderNum) {
        if (!orderNum) {
            const input = document.getElementById('dashTrackingInput');
            if (input) orderNum = input.value.trim();
        }
        if (!orderNum) {
            showToast('Please enter an order number to track.', 'warning');
            return;
        }
        const cleaned = orderNum.replace('#', '').trim();
        navigateTo('tracking', cleaned);
    }

        function adjustLocalQty(prodKey, delta) {
        const el = document.getElementById(`qty_${prodKey}`);
        if (!el) return;
        let val = parseInt(el.textContent.trim(), 10) || 1;
        val += delta;
        if (val < 1) val = 1;
        if (val > 99) val = 99;
        el.textContent = val;
    }

    function addConfiguredProductToCart(prodKey, name, price, type) {
        const qtyEl = document.getElementById(`qty_${prodKey}`);
        const qty = qtyEl ? (parseInt(qtyEl.textContent.trim(), 10) || 1) : 1;

        let targetProduct = (AppState.catalog || []).find(p => p.id === prodKey || p.product_type === type);
        if (!targetProduct) {
            targetProduct = {
                id: prodKey,
                name: name,
                price: Number(price) || 0,
                product_type: type
            };
        }

        addToCart(targetProduct, qty);

        // Reset local stepper to 1
        if (qtyEl) qtyEl.textContent = '1';
    }

    function adjustDashQty(productId, delta) {
        if (!AppState.dashQtyMap) AppState.dashQtyMap = {};
        const current = AppState.dashQtyMap[productId] || 1;
        const next = Math.max(1, Math.min(50, current + delta));
        AppState.dashQtyMap[productId] = next;
        const el = document.getElementById(`dashQty_${productId}`);
        if (el) el.textContent = next;
    }

    function addDashToCart(productId) {
        const qty = AppState.dashQtyMap?.[productId] || 1;
        addToCart(productId, qty);
        AppState.dashQtyMap[productId] = 1;
        const el = document.getElementById(`dashQty_${productId}`);
        if (el) el.textContent = 1;
    }

    function renderHomeQuickOrder() {
        const grid = document.getElementById('homeQuickOrderGrid');
        if (!grid) return;
        const products = (AppState.products && AppState.products.length > 0) ? AppState.products : (window.aquaPreloadedProducts || []);
        if (!products || products.length === 0) {
            grid.innerHTML = '<div style="color:#64748B; font-size:13px; padding:12px;">Loading available water refills...</div>';
            return;
        }
        if (!AppState.dashQtyMap) AppState.dashQtyMap = {};
        grid.innerHTML = products.map(p => {
            const price = Number(p.price || 0).toFixed(2);
            const isNew = (p.name || '').toLowerCase().includes('new');
            const badgeText = isNew ? 'With Container' : 'Refill Swap';
            const badgeClass = isNew ? 'container-new' : 'refill-swap';
            const qty = AppState.dashQtyMap[p.id] || 1;
            const imgUrl = p.image_url || '/images/aqua_clayne_gallon.png';
            return `
                <div class="quick-order-card">
                    <div class="quick-order-image-wrap">
                        <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(p.name)}" class="quick-order-img" onerror="this.src='/images/aqua_clayne_gallon.png'">
                        <span class="quick-order-badge ${badgeClass}">${badgeText}</span>
                    </div>
                    <div class="quick-order-body">
                        <h4 class="quick-order-name">${escapeHtml(p.name)}</h4>
                        <p class="quick-order-desc">${escapeHtml(p.description || '5-Gallon purified drinking water refill.')}</p>
                        <div class="quick-order-price-row">
                            <span class="quick-order-price">&#8369;${price}</span>
                            <span class="quick-order-unit">per gallon</span>
                        </div>
                    </div>
                    <div class="quick-order-actions">
                        <div class="quick-qty-stepper">
                            <button type="button" onclick="window.aquaApp.adjustDashQty('${p.id}', -1)" aria-label="Decrease quantity">&minus;</button>
                            <span id="dashQty_${p.id}">${qty}</span>
                            <button type="button" onclick="window.aquaApp.adjustDashQty('${p.id}', 1)" aria-label="Increase quantity">&plus;</button>
                        </div>
                        <button type="button" class="btn-add-cart" onclick="window.aquaApp.addDashToCart('${p.id}')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            Add to Cart
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function filterCatalogProducts(query) {
        AppState.catalogSearchQuery = (query || '').trim().toLowerCase();
        renderCatalog();
    }

    function sortCatalogProducts(sortType) {
        AppState.catalogSortOrder = sortType || 'default';
        renderCatalog();
    }

    function handleGlobalSearch(query) {
        const q = (query || '').trim().toLowerCase();
        if (!q) return;
        if (q.startsWith('#') || q.startsWith('ac-ord') || q.startsWith('ac-gal')) {
            navigateTo('orders');
            const ordersFilter = document.getElementById('ordersSearchFilter');
            if (ordersFilter) {
                ordersFilter.value = q;
                if (typeof filterOrders === 'function') filterOrders();
            }
        } else {
            navigateTo('catalog');
            const catInput = document.getElementById('catalogSearchInput');
            if (catInput) catInput.value = q;
            filterCatalogProducts(q);
        }
    }

        function setActiveNav(viewId) {
        document.querySelectorAll('[data-nav]').forEach(el => {
            const matches = el.getAttribute('data-nav') === viewId;
            el.classList.toggle('active', matches);
        });
    }

function navigateTo(viewId, param = null, pushHistory = true) {
        AppState.currentView = viewId;

        // Hide all views
        document.querySelectorAll('.view-section').forEach(el => {
            el.classList.remove('active-view');
        });

        // Show target view
        const target = document.getElementById(`view-${viewId}`);
        if (target) {
            target.classList.add('active-view');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Close mobile drawer and dropdowns
        toggleSidebar(false);
        toggleMobileMenu(false);
        const profileDropdown = document.getElementById('userProfileDropdown');
        if (profileDropdown) profileDropdown.classList.remove('show');
        const notifPopover = document.getElementById('navNotifPopover');
        if (notifPopover) notifPopover.classList.remove('show');

        // CRITICAL FIX: Highlight only the active route
        setActiveNav(viewId);

        // Load specific view data
        if (viewId === 'home') {
            loadDashboard();
        } else if (viewId === 'catalog') {
            renderCatalog();
        } else if (viewId === 'cart') {
            renderCart();
        } else if (viewId === 'checkout') {
            initCheckout();
        } else if (viewId === 'orders') {
            loadOrders();
        } else if (viewId === 'order-detail' || viewId === 'tracking') {
            loadTrackingView(param);
        } else if (viewId === 'profile') {
            loadProfile();
        }

        // Update URL state without page reload
        if (pushHistory) {
            const newUrl = viewId === 'home' ? '/customer' : `/customer/${viewId}${param ? `/${param}` : ''}`;
            window.history.pushState({ view: viewId, param: param }, '', newUrl);
        }
    }

    window.addEventListener('popstate', (e) => {
        if (e.state && e.state.view) {
            navigateTo(e.state.view, e.state.param, false);
        } else {
            const path = window.location.pathname.replace(/^\/customer\/?/, '');
            const parts = path.split('/').filter(Boolean);
            const view = parts[0] || 'home';
            const id = parts[1] || null;
            navigateTo(view, id, false);
        }
    });

    // =========================================================================
    // 5. AUTHENTICATION & SESSION
    // =========================================================================
    function updateAuthUI() {
        const isAuth = Boolean(AppState.token && AppState.user);
        const userMenu = document.getElementById('userProfileMenu');
        const guestMenu = document.getElementById('guestAuthMenu');
        const userAvatar = document.getElementById('topUserAvatar');
        const userName = document.getElementById('topUserName');
        const greetUser = document.getElementById('dashboardGreetUser');

        if (isAuth) {
            if (userMenu) userMenu.style.display = 'block';
            if (guestMenu) guestMenu.style.display = 'none';
            if (userName) userName.textContent = AppState.user.name || 'Customer';
            if (greetUser) greetUser.textContent = AppState.user.name || 'Customer';

            const initials = getInitials(AppState.user.name);
            if (userAvatar) userAvatar.textContent = initials;
        } else {
            if (userMenu) userMenu.style.display = 'none';
            if (guestMenu) guestMenu.style.display = 'flex';
            if (greetUser) greetUser.textContent = 'Welcome to Aqua Clayne';
        }

        updateCartBadge();
        updateNotifBadge();
    }

    async function handleLogin(identifier, password, remember = false) {
        try {
            const res = await apiRequest('/auth/login', 'POST', {
                email: identifier,
                username: identifier,
                password: password
            }, false);

            if (res?.data?.token) {
                AppState.token = res.data.token;
                AppState.user = res.data.user;

                localStorage.setItem('aqua_customer_token', AppState.token);
                localStorage.setItem('aqua_customer_user', JSON.stringify(AppState.user));

                updateAuthUI();
                closeModal('loginModal');
                showToast(`Welcome back, ${AppState.user.name}!`, 'success');

                // Start realtime
                initRealtime();

                // Refresh dashboard
                loadDashboard();
                return true;
            }
        } catch (err) {
            showToast(err.message || 'Login failed. Please check your credentials.', 'error');
            return false;
        }
    }

    async function handleRegister(formData) {
        try {
            const res = await apiRequest('/auth/register', 'POST', formData, false);
            showToast('Account registered successfully! You can now log in.', 'success');
            closeModal('registerModal');
            showLoginModal();
            return true;
        } catch (err) {
            showToast(err.message || 'Registration failed. Please check form inputs.', 'error');
            return false;
        }
    }

    async function handleLogout(callApi = true) {
        if (callApi === 'force') {
            return executeLogout();
        }
        confirmLogout();
    }

    // =========================================================================
    // 6. DASHBOARD LOGIC (#view-home)
    // =========================================================================
    async function loadDashboard() {
        // Greet time
        const hour = new Date().getHours();
        let greet = 'Good Evening,';
        if (hour < 12) greet = 'Good Morning,';
        else if (hour < 17) greet = 'Good Afternoon,';
        const greetTimeEl = document.getElementById('dashboardGreetTime');
        if (greetTimeEl) greetTimeEl.textContent = greet;

        // Render Quick Ordering section on home dashboard
        renderHomeQuickOrder();

        // If authenticated, fetch recent orders, credit balance, unread notifications
        if (AppState.token) {
            try {
                const [ordersRes, notifsRes, creditRes] = await Promise.all([
                    apiRequest('/customer/orders', 'GET', null, true).catch(() => null),
                    apiRequest('/customer/notifications', 'GET', null, true).catch(() => null),
                    apiRequest('/customer/credit', 'GET', null, true).catch(() => null)
                ]);

                if (ordersRes?.data) {
                    AppState.orders = ordersRes.data;
                    AppState.activeOrder = AppState.orders.find(o =>
                        !['delivered', 'cancelled'].includes(o.status.toLowerCase())
                    ) || null;
                    renderRecentOrderCard();

                    const activeCount = AppState.orders.filter(o => ['pending', 'confirmed', 'assigned_to_driver', 'out_for_delivery', 'waiting_in_queue', 'refilling'].includes(o.status.toLowerCase())).length;
                    const inProgressCount = AppState.orders.filter(o => ['confirmed', 'assigned_to_driver', 'waiting_in_queue', 'refilling', 'out_for_delivery'].includes(o.status.toLowerCase())).length;
                    const completedCount = AppState.orders.filter(o => ['delivered', 'completed'].includes(o.status.toLowerCase())).length;

                    const activeEl = document.getElementById('statsActiveOrders');
                    if (activeEl) activeEl.textContent = activeCount;
                    const progEl = document.getElementById('statsInProgress');
                    if (progEl) progEl.textContent = inProgressCount;
                    const compEl = document.getElementById('statsCompletedOrders');
                    if (compEl) compEl.textContent = completedCount;

                    const sidebarBadge = document.getElementById('sidebarActiveOrdersBadge');
                    if (sidebarBadge) {
                        sidebarBadge.textContent = activeCount;
                        sidebarBadge.style.display = activeCount > 0 ? 'inline-block' : 'none';
                    }
                }

                if (notifsRes?.data) {
                    AppState.notifications = notifsRes.data;
                    AppState.unreadNotifCount = AppState.notifications.filter(n => !n.is_read).length;
                    updateNotifBadge();
                }

                if (creditRes?.data) {
                    AppState.creditAccount = creditRes.data;
                    const bal = Number(creditRes.data.balance || 0);
                    const balEl = document.getElementById('statsOutstandingBalance');
                    if (balEl) balEl.textContent = `₱${bal.toFixed(2)}`;
                }
            } catch (e) {
                console.warn('Dashboard load error:', e);
            }
        } else {
            renderRecentOrderCard();
        }
    }

    function renderRecentOrderCard() {
        const container = document.getElementById('recentOrderSection');
        if (!container) return;

        const isAuth = Boolean(AppState.token);
        const orders = (AppState.orders && AppState.orders.length > 0) ? AppState.orders : (AppState.activeOrder ? [AppState.activeOrder] : []);

        if (!isAuth || orders.length === 0) {
            container.innerHTML = `
                <div class="recent-order-box">
                    <div class="recent-order-left">
                        <div class="order-splash-thumb">
                            <img src="/images/banner_gallon_splash.png" alt="Gallon" onerror="this.src='/images/aqua_clayne_gallon.png'">
                        </div>
                        <div class="order-info-meta">
                            <span class="order-number-title">No recent orders yet</span>
                            <span class="order-date-row">Place your customer gallon refill order.</span>
                        </div>
                    </div>
                    <button type="button" class="btn-view-details-pill" onclick="window.aquaApp.navigateTo('catalog')">
                        Order Water
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
            `;
            return;
        }

        const recentOrders = orders.slice(0, 3);
        container.innerHTML = recentOrders.map(order => {
            const statusClass = `badge-${(order.status || '').toLowerCase()}`;
            const dateStr = formatDateTime(order.created_at);
            return `
                <div class="recent-order-box" onclick="window.aquaApp.navigateTo('order-detail', '${order.id}')" style="cursor:pointer; margin-bottom: 12px;">
                    <div class="recent-order-left">
                        <div class="order-splash-thumb">
                            <img src="/images/banner_gallon_splash.png" alt="Gallon" onerror="this.src='/images/aqua_clayne_gallon.png'">
                        </div>
                        <div class="order-info-meta">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <span class="order-number-title">Order #${escapeHtml(order.order_number)}</span>
                                <span class="order-badge-status ${statusClass}">
                                    ${escapeHtml((order.status || 'Active').replace(/_/g, ' '))}
                                </span>
                            </div>
                            <span class="order-date-row">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                ${escapeHtml(dateStr)}
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn-view-details-pill" onclick="event.stopPropagation(); window.aquaApp.navigateTo('order-detail', '${order.id}')">
                        View Details
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
            `;
        }).join('');
    }

    // =========================================================================
    // 7. PRODUCT CATALOG & ORDERING (#view-catalog)
    // =========================================================================
    async function loadCatalog() {
        try {
            const res = await apiRequest('/products', 'GET', null, false);
            if (res?.data) {
                AppState.products = res.data;
            }
        } catch (_) {}
        renderCatalog();
    }

    function renderCatalog() {
        const grid = document.getElementById('catalogProductsGrid');
        if (!grid) return;

        if (!AppState.products || AppState.products.length === 0) {
            AppState.products = window.aquaPreloadedProducts || [];
        }

        let filtered = [...AppState.products];

        // 1. Category Filter
        if (AppState.selectedCategory && AppState.selectedCategory !== 'All') {
            const target = AppState.selectedCategory.toLowerCase();
            filtered = filtered.filter(p =>
                p.name.toLowerCase().includes(target) || (p.description || '').toLowerCase().includes(target)
            );
        }

        // 2. Search Query Filter
        if (AppState.catalogSearchQuery) {
            const query = AppState.catalogSearchQuery;
            filtered = filtered.filter(p =>
                p.name.toLowerCase().includes(query) || (p.description || '').toLowerCase().includes(query)
            );
        }

        // 3. Sorting
        if (AppState.catalogSortOrder === 'price_low') {
            filtered.sort((a, b) => Number(a.price || 0) - Number(b.price || 0));
        } else if (AppState.catalogSortOrder === 'price_high') {
            filtered.sort((a, b) => Number(b.price || 0) - Number(a.price || 0));
        } else if (AppState.catalogSortOrder === 'name_asc') {
            filtered.sort((a, b) => a.name.localeCompare(b.name));
        }

        if (filtered.length === 0) {
            grid.innerHTML = `
                <div class="empty-data-card" style="grid-column: 1 / -1;">
                    <div class="empty-icon-circle">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    </div>
                    <div class="empty-title">No products found</div>
                    <div class="empty-subtitle">No water refill options match your filter criteria.</div>
                </div>
            `;
            return;
        }

        grid.innerHTML = filtered.map(p => {
            const isAvail = p.in_stock !== false && p.is_available !== false;
            const isNewGallon = p.product_type === 'new_gallon';
            const price = Number(p.price || 0).toFixed(2);
            const isSelected = AppState.selectedService?.id === p.id;

            return `
                <div class="product-card ${isSelected ? 'selected-service-card' : ''}" id="product-card-${p.id}">
                    <div>
                        <div class="product-card-top">
                            <div class="product-droplet-icon">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                            </div>
                            <span class="availability-pill ${isAvail ? 'available' : 'unavailable'}">
                                <span class="availability-dot"></span>
                                ${isAvail ? 'Available' : 'Unavailable'}
                            </span>
                        </div>
                        <h3 class="product-name">${escapeHtml(p.name)}</h3>
                        <p class="product-desc">${escapeHtml(p.description || '')}</p>
                    </div>
                    <div class="product-meta-row">
                        <div class="product-price-col">
                            <span class="product-type-label">${isNewGallon ? 'New gallon with water' : 'Refill existing gallon'}</span>
                            <span class="product-price-tag">₱${price}</span>
                        </div>
                        <div class="product-actions-col">
                            ${isAvail ? `
                                <button type="button" class="btn-add-cart" onclick="window.aquaApp.addToCart('${p.id}')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    Add to Cart
                                </button>
                            ` : `
                                <button type="button" class="btn-add-cart" disabled>Unavailable</button>
                            `}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function setCatalogCategory(cat) {
        AppState.selectedCategory = cat;
        document.querySelectorAll('.category-pill').forEach(el => {
            if (el.textContent.trim() === cat) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });
        renderCatalog();
    }

    // =========================================================================
    // 8. SHOPPING CART LOGIC (#view-cart)
    // =========================================================================
    function addToCart(productId, qty = 1, fallbackData = null) {
        if (!AppState.products || AppState.products.length === 0) {
            AppState.products = window.aquaPreloadedProducts || [];
        }

        let product = AppState.products.find(p => String(p.id) === String(productId));
        if (!product && fallbackData) {
            product = AppState.products.find(p => 
                (fallbackData.product_type === 'new_gallon' && p.product_type === 'new_gallon') ||
                (p.name && fallbackData.name && p.name.toLowerCase().includes(fallbackData.name.toLowerCase().split(' ')[0]))
            );
        }

        if (!product && fallbackData) {
            product = {
                id: productId,
                name: fallbackData.name,
                price: Number(fallbackData.price) || 0,
                product_type: fallbackData.product_type || 'refill',
                image_url: '/images/banner_gallon_splash.png'
            };
        }

        if (!product) {
            showToast('Unable to find product details.', 'error');
            return;
        }

        const parsedQty = Math.max(1, parseInt(qty, 10) || 1);
        const existing = AppState.cart.items.find(item => String(item.product.id) === String(product.id));
        if (existing) {
            existing.quantity += parsedQty;
        } else {
            AppState.cart.items.push({
                product: {
                    id: product.id,
                    name: product.name,
                    price: Number(product.price) || 0,
                    product_type: product.product_type,
                    image_url: product.image_url || '/images/banner_gallon_splash.png'
                },
                quantity: parsedQty
            });
        }

        saveCart();
        updateCartBadge();
        showToast(`Added ${parsedQty} × ${product.name} to cart!`, 'success');

        if (AppState.currentView === 'cart') {
            renderCart();
        }
    }

    function addConfiguredProductToCart(productId, name, price, type, qtyElementId) {
        const qtyEl = document.getElementById(qtyElementId || `qty_${productId}`);
        const qty = qtyEl ? (parseInt(qtyEl.textContent.trim(), 10) || 1) : 1;

        addToCart(productId, qty, { name, price, product_type: type });

        if (qtyEl) qtyEl.textContent = '1';
    }

    function updateCartItemQuantity(productId, delta) {
        const item = AppState.cart.items.find(i => String(i.product.id) === String(productId));
        if (!item) return;

        item.quantity += delta;
        if (item.quantity < 1) {
            item.quantity = 1;
        }

        saveCart();
        updateCartBadge();
        renderCart();
    }

    function removeCartItem(productId) {
        AppState.cart.items = AppState.cart.items.filter(i => String(i.product.id) !== String(productId));
        saveCart();
        updateCartBadge();
        renderCart();
        showToast('Item removed from cart.', 'info');
    }

    function clearCart() {
        AppState.cart.items = [];
        saveCart();
        updateCartBadge();
        renderCart();
        showToast('Cart cleared.', 'info');
    }

    function saveCart() {
        localStorage.setItem('aqua_customer_cart', JSON.stringify(AppState.cart));
    }

    function updateCartBadge() {
        const totalCount = AppState.cart.items.reduce((sum, item) => sum + item.quantity, 0);
        document.querySelectorAll('.cart-badge-count, #navCartBadge, #mobileCartBadge').forEach(el => {
            el.textContent = totalCount > 99 ? '99+' : totalCount;
            el.style.display = totalCount > 0 ? 'block' : 'none';
        });
    }

    function getCartCalculations() {
        let subtotal = 0;
        let refillCount = 0;
        let newGallonCount = 0;

        AppState.cart.items.forEach(item => {
            const itemPrice = Number(item.product.price || 0);
            subtotal += itemPrice * item.quantity;
            if (item.product.product_type === 'new_gallon') {
                newGallonCount += item.quantity;
            } else {
                refillCount += item.quantity;
            }
        });

        const isPickup = AppState.cart.deliveryMethod === 'station_pickup';
        const deliveryFee = isPickup || subtotal === 0 ? 0.00 : AppState.station.delivery_fee;
        const total = subtotal + deliveryFee;

        return { subtotal, refillCount, newGallonCount, deliveryFee, total, isPickup };
    }

    function renderCart() {
        const itemsContainer = document.getElementById('cartItemsList');
        if (!itemsContainer) return;

        const heading = document.getElementById('cartContainerHeading');
        const bottomGrid = document.getElementById('cartBottomGrid');
        const actionsRow = document.getElementById('cartActionsRow');
        const subtotalEl = document.getElementById('cartSummarySubtotal');
        const deliveryFeeEl = document.getElementById('cartSummaryDeliveryFee');
        const totalEl = document.getElementById('cartSummaryTotal');
        const instructionsEl = document.getElementById('cartSpecialInstructions');

        const totalItems = AppState.cart.items.reduce((sum, item) => sum + item.quantity, 0);

        if (heading) {
            heading.textContent = `Your Cart (${totalItems} ${totalItems === 1 ? 'Item' : 'Items'})`;
        }

        if (AppState.cart.items.length === 0) {
            itemsContainer.innerHTML = `
                <div class="empty-data-card" style="padding:48px 20px; text-align:center;">
                    <div class="empty-icon-circle" style="margin:0 auto 16px;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    </div>
                    <div class="empty-title" style="font-size:18px; font-weight:800; color:#102F52; margin-bottom:6px;">Your cart is empty</div>
                    <div class="empty-subtitle" style="font-size:14px; color:#6483A5; margin-bottom:20px;">Browse our pure water offerings and add what you need.</div>
                    <button type="button" class="btn-card-action" style="max-width:220px; margin:0 auto;" onclick="window.aquaApp.navigateTo('home')">
                        Browse Water Services
                    </button>
                </div>
            `;
            if (bottomGrid) bottomGrid.style.display = 'none';
            if (actionsRow) actionsRow.style.display = 'none';
            return;
        }

        if (bottomGrid) bottomGrid.style.display = 'grid';
        if (actionsRow) actionsRow.style.display = 'flex';

        // Render Cart Items matching Screenshot 4
        itemsContainer.innerHTML = AppState.cart.items.map(item => {
            const unitPrice = Number(item.product.price || 0).toFixed(2);
            const itemSubtotal = (Number(item.product.price || 0) * item.quantity).toFixed(2);
            const isNewGallon = item.product.product_type === 'new_gallon';
            const desc = isNewGallon
                ? 'A brand-new 5-gallon container filled with purified water.'
                : (item.product.name.toLowerCase().includes('mineral')
                    ? 'Natural mineral-enriched drinking water replenishing.'
                    : 'Multi-stage purified water with reverse osmosis and UV filtration.');

            return `
                <div class="cart-item-card" style="display:flex; align-items:center; justify-content:space-between; padding:20px; border-bottom:1px solid #EDF5FC; gap:16px;">
                    <div style="display:flex; align-items:center; gap:16px; flex:1; min-width:0;">
                        <div class="cart-splash-thumb" style="width:70px; height:70px; border-radius:14px; background:#F0F8FF; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden;">
                            <img src="/images/banner_gallon_splash.png" alt="Gallon" onerror="this.src='/images/aqua_clayne_gallon.png'" style="width:100%; height:100%; object-fit:contain;">
                        </div>
                        <div>
                            <div style="font-size:16px; font-weight:700; color:#102F52; margin-bottom:4px;">${escapeHtml(item.product.name)}</div>
                            <div style="font-size:13px; color:#6483A5; margin-bottom:6px; line-height:1.4;">${escapeHtml(desc)}</div>
                            <div style="font-size:15px; font-weight:700; color:#0879D1;">₱${unitPrice}</div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:20px; flex-shrink:0;">
                        <div class="stepper-wrap" style="display:inline-flex; align-items:center; background:#F0F7FD; border:1px solid #D9EAFB; border-radius:24px; padding:4px 8px; gap:10px;">
                            <button type="button" class="stepper-btn" onclick="window.aquaApp.updateCartItemQuantity('${item.product.id}', -1)" style="width:28px; height:28px; border-radius:50%; border:none; background:#FFFFFF; color:#0879D1; font-weight:800; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,0.06);">-</button>
                            <span style="font-size:15px; font-weight:700; color:#102F52; min-width:18px; text-align:center;">${item.quantity}</span>
                            <button type="button" class="stepper-btn" onclick="window.aquaApp.updateCartItemQuantity('${item.product.id}', 1)" style="width:28px; height:28px; border-radius:50%; border:none; background:#FFFFFF; color:#0879D1; font-weight:800; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,0.06);">+</button>
                        </div>
                        <div style="font-size:18px; font-weight:800; color:#0879D1; min-width:85px; text-align:right;">
                            ₱${itemSubtotal}
                        </div>
                        <button type="button" class="btn-trash-item" title="Remove Item" onclick="window.aquaApp.removeCartItem('${item.product.id}')" style="width:36px; height:36px; border-radius:10px; border:1px solid #D9EAFB; background:#FFFFFF; color:#6483A5; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.15s ease;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        const subtotal = AppState.cart.items.reduce((sum, item) => sum + (Number(item.product.price || 0) * item.quantity), 0);
        const deliveryFee = 5.00;
        const total = subtotal + deliveryFee;

        if (subtotalEl) subtotalEl.textContent = `₱${subtotal.toFixed(2)}`;
        if (deliveryFeeEl) deliveryFeeEl.textContent = `₱${deliveryFee.toFixed(2)}`;
        if (totalEl) totalEl.textContent = `₱${total.toFixed(2)}`;

        // Also update legacy container if exists
        const summaryContainer = document.getElementById('cartSummaryContent');
        if (summaryContainer) {
            summaryContainer.innerHTML = `
                <div class="order-summary-box">
                    <h4 class="summary-title">Order Summary</h4>
                    <div class="summary-row"><span>Subtotal</span><span>₱${subtotal.toFixed(2)}</span></div>
                    <div class="summary-row"><span>Delivery Fee</span><span>₱${deliveryFee.toFixed(2)}</span></div>
                    <div class="summary-row total-row"><span>Total Amount</span><span>₱${total.toFixed(2)}</span></div>
                    <button type="button" class="btn-primary-action" onclick="window.aquaApp.openCheckoutModal()">
                        Proceed to Checkout
                    </button>
                </div>
            `;
        }

        if (instructionsEl && AppState.cart.specialInstructions) {
            instructionsEl.value = AppState.cart.specialInstructions;
        }
    }

    // =========================================================================
    // 9. CHECKOUT CONTROLLER & MODAL WORKFLOW
    // =========================================================================
    async function openCheckoutModal() {
        if (!AppState.token) {
            window.location.href = '/admin/login';
            return;
        }

        if (!AppState.cart || AppState.cart.items.length === 0) {
            showToast('Your cart is empty. Add items before checking out.', 'error');
            return;
        }

        // Set default fulfillment to Home Delivery
        const homeRadio = document.querySelector('input[name="fulfillment_method"][value="home_delivery"]');
        if (homeRadio) homeRadio.checked = true;

        // Set default payment method to Online
        const onlineRadio = document.querySelector('input[name="payment_method"][value="online"]');
        if (onlineRadio) onlineRadio.checked = true;

        // Pre-fill customer mobile phone
        const phoneEl = document.getElementById('checkoutPhone');
        if (phoneEl && AppState.user && AppState.user.phone) {
            phoneEl.value = AppState.user.phone;
        }

        // Pre-fill notes from cart special instructions if any
        const notesEl = document.getElementById('checkoutNotes');
        if (notesEl && AppState.cart.specialInstructions) {
            notesEl.value = AppState.cart.specialInstructions;
        }

        // Calculate and update fee and total for Home Delivery
        updateCheckoutMethod('home_delivery');

        // Fetch customer's latest saved addresses
        try {
            const res = await apiRequest('/customer/addresses', 'GET', null, true);
            if (res && res.data && Array.isArray(res.data)) {
                AppState.addresses = res.data;
            }
        } catch (e) {
            console.warn('Could not refresh customer addresses', e);
        }

        // Check if customer has an existing address strictly within Gatid, Duhat, or Bubukal
        const validAddresses = (AppState.addresses || []).filter(a => {
            const brgy = (a.barangay || '').trim().toLowerCase();
            return brgy === 'gatid' || brgy === 'duhat' || brgy === 'bubukal';
        });

        const defaultAddr = validAddresses.find(a => a.is_default) || validAddresses[0];

        if (defaultAddr) {
            AppState.currentAddressId = defaultAddr.id;
            displaySavedAddress(defaultAddr);
        } else {
            AppState.currentAddressId = null;
            showAddressEditForm(true);
        }

        openModal('checkoutModal');
    }

    function displaySavedAddress(addr) {
        const savedCard = document.getElementById('checkoutSavedAddressCard');
        const formFields = document.getElementById('checkoutAddressFormFields');
        const btnToggle = document.getElementById('btnToggleEditAddress');
        const brgyTag = document.getElementById('savedAddressBrgyTag');
        const fullText = document.getElementById('savedAddressFullText');
        const landmarkText = document.getElementById('savedAddressLandmark');
        const saveRow = document.getElementById('checkoutAddressSaveRow');

        if (savedCard) savedCard.style.display = 'flex';
        if (formFields) formFields.style.display = 'none';
        if (btnToggle) btnToggle.style.display = 'inline-flex';
        if (saveRow) saveRow.style.display = 'none';

        if (brgyTag) brgyTag.textContent = 'Brgy. ' + (addr.barangay || 'Gatid');

        let displayLine = addr.address_line || '';
        let landmarkPart = '';
        const landmarkMatch = displayLine.match(/\(Landmark:\s*([^)]+)\)/i);
        if (landmarkMatch) {
            landmarkPart = landmarkMatch[1].trim();
            displayLine = displayLine.replace(/\(Landmark:\s*[^)]+\)/i, '').trim().replace(/,\s*$/, '');
        }

        const fullAddrString = `${displayLine}, Brgy. ${addr.barangay || 'Gatid'}, ${addr.city || 'Santa Cruz'}, ${addr.province || 'Laguna'}`;
        if (fullText) fullText.textContent = fullAddrString;

        if (landmarkText) {
            if (landmarkPart) {
                landmarkText.textContent = 'Landmark: ' + landmarkPart;
                landmarkText.style.display = 'block';
            } else {
                landmarkText.style.display = 'none';
            }
        }

        populateAddressInputs(addr);
    }

    function populateAddressInputs(addr) {
        const brgyEl = document.getElementById('checkoutBarangay');
        const houseNoEl = document.getElementById('checkoutHouseNo');
        const streetEl = document.getElementById('checkoutStreet');
        const subdvEl = document.getElementById('checkoutSubdivision');
        const landmarkEl = document.getElementById('checkoutLandmark');

        if (brgyEl) {
            const val = (addr?.barangay || 'Gatid').trim();
            const norm = val.charAt(0).toUpperCase() + val.slice(1).toLowerCase();
            brgyEl.value = ['Gatid', 'Duhat', 'Bubukal'].includes(norm) ? norm : 'Gatid';
        }

        if (!addr) {
            if (houseNoEl) houseNoEl.value = '';
            if (streetEl) streetEl.value = '';
            if (subdvEl) subdvEl.value = '';
            if (landmarkEl) landmarkEl.value = '';
            return;
        }

        let line = addr.address_line || '';
        let landmarkVal = '';
        const lmMatch = line.match(/\(Landmark:\s*([^)]+)\)/i);
        if (lmMatch) {
            landmarkVal = lmMatch[1].trim();
            line = line.replace(/\(Landmark:\s*[^)]+\)/i, '').trim().replace(/,\s*$/, '');
        }
        if (landmarkEl) landmarkEl.value = landmarkVal;

        const parts = line.split(',').map(p => p.trim());
        if (parts.length >= 2) {
            if (houseNoEl) houseNoEl.value = parts[0];
            if (streetEl) streetEl.value = parts[1];
            if (subdvEl) subdvEl.value = parts.slice(2).join(', ');
        } else {
            if (streetEl) streetEl.value = line;
            if (houseNoEl && !houseNoEl.value) houseNoEl.value = 'House No.';
        }
    }

    function showAddressEditForm(isNew) {
        const savedCard = document.getElementById('checkoutSavedAddressCard');
        const formFields = document.getElementById('checkoutAddressFormFields');
        const btnToggle = document.getElementById('btnToggleEditAddress');
        const saveRow = document.getElementById('checkoutAddressSaveRow');

        if (savedCard) savedCard.style.display = 'none';
        if (formFields) formFields.style.display = 'flex';
        if (btnToggle) btnToggle.style.display = 'none';

        if (saveRow) {
            saveRow.style.display = isNew ? 'none' : 'flex';
        }
    }

    function toggleEditAddressForm() {
        showAddressEditForm(false);
    }

    function cancelEditAddress() {
        if (AppState.currentAddressId) {
            const addr = (AppState.addresses || []).find(a => a.id === AppState.currentAddressId);
            if (addr) {
                displaySavedAddress(addr);
                return;
            }
        }
        showToast('Please enter and save your delivery address.', 'info');
    }

    async function saveEditedAddress() {
        const houseNo = document.getElementById('checkoutHouseNo')?.value.trim();
        const street = document.getElementById('checkoutStreet')?.value.trim();
        const subdv = document.getElementById('checkoutSubdivision')?.value.trim();
        const landmark = document.getElementById('checkoutLandmark')?.value.trim();
        const barangay = document.getElementById('checkoutBarangay')?.value;

        if (!houseNo) {
            showToast('Please enter your house or building number.', 'error');
            document.getElementById('checkoutHouseNo')?.focus();
            return false;
        }

        if (!street) {
            showToast('Please enter your street or road name.', 'error');
            document.getElementById('checkoutStreet')?.focus();
            return false;
        }

        if (!['Gatid', 'Duhat', 'Bubukal'].includes(barangay)) {
            showToast('Delivery is strictly limited to Gatid, Duhat, and Bubukal in Santa Cruz, Laguna.', 'error');
            return false;
        }

        let composedLine = `${houseNo}, ${street}`;
        if (subdv) composedLine += `, ${subdv}`;
        if (landmark) composedLine += ` (Landmark: ${landmark})`;

        const payload = {
            label: 'Home',
            address_line: composedLine,
            barangay: barangay,
            city: 'Santa Cruz',
            province: 'Laguna',
            is_default: true
        };

        try {
            let res;
            if (AppState.currentAddressId) {
                res = await apiRequest(`/customer/addresses/${AppState.currentAddressId}`, 'PUT', payload, true);
            } else {
                res = await apiRequest('/customer/addresses', 'POST', payload, true);
            }

            const savedAddr = res?.data || res;
            if (savedAddr && savedAddr.id) {
                AppState.currentAddressId = savedAddr.id;
                if (!AppState.addresses) AppState.addresses = [];
                const idx = AppState.addresses.findIndex(a => a.id === savedAddr.id);
                if (idx >= 0) {
                    AppState.addresses[idx] = savedAddr;
                } else {
                    AppState.addresses.push(savedAddr);
                }
                displaySavedAddress(savedAddr);
                showToast('Delivery address saved successfully!', 'success');
                return true;
            }
            return false;
        } catch (err) {
            showToast(err.message || 'Failed to save delivery address.', 'error');
            return false;
        }
    }

    function updateCheckoutMethod(method) {
        const addrSection = document.getElementById('checkoutDeliveryAddressSection');
        const noticeSection = document.getElementById('checkoutStationNotice');
        const feeEl = document.getElementById('checkoutFee');
        const feeLabelEl = document.getElementById('checkoutFeeLabel');
        const subtotalEl = document.getElementById('checkoutSubtotal');
        const totalEl = document.getElementById('checkoutTotal');

        const subtotal = (AppState.cart.items || []).reduce((sum, item) => sum + (Number(item.product.price || 0) * item.quantity), 0);
        const fee = method === 'home_delivery' ? 5.00 : 0.00;
        const total = subtotal + fee;

        if (subtotalEl) subtotalEl.textContent = `₱${subtotal.toFixed(2)}`;
        if (feeEl) feeEl.textContent = method === 'home_delivery' ? '₱5.00' : 'FREE (₱0.00)';
        if (feeLabelEl) feeLabelEl.textContent = method === 'home_delivery' ? 'Delivery Fee (Home Delivery):' : 'Delivery Fee (Station Pickup):';
        if (totalEl) totalEl.textContent = `₱${total.toFixed(2)}`;

        if (method === 'home_delivery') {
            if (addrSection) addrSection.style.display = 'block';
            if (noticeSection) noticeSection.style.display = 'none';
        } else {
            if (addrSection) addrSection.style.display = 'none';
            if (noticeSection) noticeSection.style.display = 'flex';
        }
    }

    async function handleCheckoutSubmit(form) {
        const btn = document.getElementById('btnSubmitOrder');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="spin-animation" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" /></svg>
                <span>Processing Order...</span>
            `;
        }

        try {
            if (!AppState.token) {
                window.location.href = '/admin/login';
                return;
            }

            if (!AppState.cart || AppState.cart.items.length === 0) {
                showToast('Your cart is empty. Please add items before checking out.', 'error');
                return;
            }

            const fulfillmentRadio = document.querySelector('input[name="fulfillment_method"]:checked');
            const fulfillmentMethod = fulfillmentRadio ? fulfillmentRadio.value : 'home_delivery';

            const paymentRadio = document.querySelector('input[name="payment_method"]:checked');
            const paymentMethod = paymentRadio ? paymentRadio.value : 'online';
            const paymentProvider = document.getElementById('checkoutPaymentProvider')?.value || AppState.paymentProvider || 'qrph';

            const phone = document.getElementById('checkoutPhone')?.value.trim() || '';
            if (!phone) {
                showToast('Please enter your mobile contact number.', 'error');
                document.getElementById('checkoutPhone')?.focus();
                return;
            }

            let addressId = null;

            // If Home Delivery, ensure valid address in Gatid, Duhat, or Bubukal
            if (fulfillmentMethod === 'home_delivery') {
                const formFields = document.getElementById('checkoutAddressFormFields');
                const isEditing = formFields && formFields.style.display !== 'none';

                if (!AppState.currentAddressId || isEditing) {
                    const saved = await saveEditedAddress();
                    if (!saved) {
                        return;
                    }
                }
                addressId = AppState.currentAddressId;

                if (!addressId) {
                    showToast('Please provide a complete delivery address.', 'error');
                    return;
                }
            }

            // Calculate expected empty gallons for refill products
            let expectedEmptyGallons = 0;
            AppState.cart.items.forEach(item => {
                const pName = (item.product?.name || '').toLowerCase();
                const pDesc = (item.product?.description || '').toLowerCase();
                if (pName.includes('refill') || pDesc.includes('refill')) {
                    expectedEmptyGallons += item.quantity;
                }
            });

            const notes = document.getElementById('checkoutNotes')?.value.trim() || AppState.cart.specialInstructions || '';

            const payload = {
                delivery_method: fulfillmentMethod,
                payment_method: paymentMethod === 'online' ? 'online' : 'cash',
                address_id: fulfillmentMethod === 'home_delivery' ? addressId : null,
                expected_empty_gallons: expectedEmptyGallons,
                container_ownership: expectedEmptyGallons > 0 ? 'customer_owned' : 'new_gallon',
                notes: notes,
                items: AppState.cart.items.map(item => ({
                    product_id: item.product.id,
                    quantity: item.quantity
                })),
                client_request_id: 'web_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9)
            };

            const res = await apiRequest('/customer/orders', 'POST', payload, true);
            const orderData = res?.data || res?.order || res;
            const orderId = orderData?.id;

            showToast('Order confirmed successfully!', 'success');
            clearCart();
            closeModal('checkoutModal');

            // Handle online payment checkout link
            if (paymentMethod === 'online') {
                if (res?.checkout_url || res?.data?.checkout_url) {
                    window.location.href = res.checkout_url || res.data.checkout_url;
                    return;
                }
                try {
                    const payRes = await apiRequest(`/customer/orders/${orderId}/payment/checkout`, 'POST', { payment_method_type: paymentProvider }, true);
                    if (payRes?.checkout_url || payRes?.data?.checkout_url) {
                        window.location.href = payRes.checkout_url || payRes.data.checkout_url;
                        return;
                    }
                } catch (payErr) {
                    console.warn('Payment checkout link generation error', payErr);
                }
            }

            // Cash / Default: navigate to tracking view
            navigateTo('tracking', orderId);
            if (typeof loadOrders === 'function') {
                loadOrders();
            }
        } catch (err) {
            showToast(err.message || 'Failed to place order. Please check your details and try again.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Confirm &amp; Place Order</span>
                `;
            }
        }
    }

    async function startCheckout() {
        if (!AppState.token) {
            window.location.href = '/admin/login';
            return;
        }

        if (AppState.cart.items.length === 0) {
            showToast('Your cart is empty. Please add items before checking out.', 'error');
            navigateTo('catalog');
            return;
        }

        navigateTo('checkout');
    }

    async function initCheckout() {
        AppState.checkoutStep = 1;
        renderCheckoutStepBar();

        // Preload addresses and payment rules
        try {
            const [addrRes, rulesRes] = await Promise.all([
                apiRequest('/customer/addresses', 'GET', null, true).catch(() => null),
                apiRequest('/customer/payment-options', 'GET', null, true).catch(() => null)
            ]);

            if (addrRes?.data) {
                AppState.addresses = addrRes.data;
                const def = AppState.addresses.find(a => a.is_default) || AppState.addresses[0];
                if (def && !AppState.cart.selectedAddressId) {
                    AppState.cart.selectedAddressId = def.id;
                }
            }

            if (rulesRes?.data) {
                AppState.paymentRules = rulesRes.data;
                if (AppState.paymentRules.first_order) {
                    AppState.cart.paymentMethod = 'online';
                }
            }
        } catch (_) {}

        renderCheckoutStepView();
    }

    function renderCheckoutStepBar() {
        const bar = document.getElementById('checkoutStepBarContainer');
        if (!bar) return;

        const steps = ['Address', 'Gallons', 'Payment', 'Review'];
        bar.innerHTML = `
            <div class="checkout-step-bar">
                ${steps.map((label, idx) => {
                    const stepNum = idx + 1;
                    const isDone = stepNum < AppState.checkoutStep;
                    const isActive = stepNum === AppState.checkoutStep;
                    const cls = isDone ? 'step-done' : (isActive ? 'step-active' : '');

                    return `
                        <div class="step-item ${cls}">
                            <div class="step-circle">
                                ${isDone ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>' : stepNum}
                            </div>
                            <span class="step-label">${label}</span>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function renderCheckoutStepView() {
        const container = document.getElementById('checkoutStepContent');
        if (!container) return;

        renderCheckoutStepBar();

        if (AppState.checkoutStep === 1) {
            renderCheckoutStep1(container);
        } else if (AppState.checkoutStep === 2) {
            renderCheckoutStep2(container);
        } else if (AppState.checkoutStep === 3) {
            renderCheckoutStep3(container);
        } else if (AppState.checkoutStep === 4) {
            renderCheckoutStep4(container);
        }
    }

    // Step 1: Address & Fulfillment
    function renderCheckoutStep1(container) {
        const isStation = AppState.cart.deliveryMethod === 'station_pickup';

        container.innerHTML = `
            <div class="order-detail-card">
                <h3 class="card-heading-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Choose Fulfillment Method
                </h3>
                <div class="fulfillment-selector-grid">
                    <div class="fulfillment-card ${!isStation ? 'selected' : ''}" onclick="window.aquaApp.setCheckoutDeliveryMethod('home_delivery')">
                        <div class="fulfillment-icon-box">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        </div>
                        <div>
                            <div class="fulfillment-title">Home Delivery</div>
                            <div class="fulfillment-desc">Driver delivers to your address (+₱5.00 fee)</div>
                        </div>
                    </div>
                    <div class="fulfillment-card ${isStation ? 'selected' : ''}" onclick="window.aquaApp.setCheckoutDeliveryMethod('station_pickup')">
                        <div class="fulfillment-icon-box">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </div>
                        <div>
                            <div class="fulfillment-title">Station Pickup</div>
                            <div class="fulfillment-desc">Pick up containers at the counter (FREE)</div>
                        </div>
                    </div>
                </div>

                ${isStation ? `
                    <div style="background:#F0F7FC; padding:16px; border-radius:14px; border:1px solid #BCE3FD; margin-top:16px;">
                        <h4 style="font-size:14px; font-weight:800; color:#064B87; margin-bottom:4px;">Aqua Clayne Refilling Station</h4>
                        <p style="font-size:12.5px; color:#4A6B82; line-height:1.4;">${escapeHtml(AppState.station.address)}</p>
                        <p style="font-size:12px; color:#0878C9; font-weight:600; margin-top:6px;">Operating Hours: 7:00 AM - 7:00 PM</p>
                    </div>
                ` : `
                    <div style="margin-top:20px;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                            <span style="font-size:14px; font-weight:700; color:#12344D;">Select Delivery Address</span>
                            <button type="button" class="btn-play-tutorial" style="padding:6px 14px; font-size:12px;" onclick="window.aquaApp.openAddAddressModal()">
                                + Add Address
                            </button>
                        </div>
                        <div class="address-options-list">
                            ${AppState.addresses.length === 0 ? `
                                <div style="padding:20px; text-align:center; background:#F8FAFC; border-radius:12px; border:1px dashed #CBD5E1;">
                                    <p style="font-size:13px; color:#64748B; margin-bottom:10px;">No saved addresses found.</p>
                                    <button type="button" class="btn-guest-register" onclick="window.aquaApp.openAddAddressModal()">
                                        Add Your First Address
                                    </button>
                                </div>
                            ` : AppState.addresses.map(addr => `
                                <div class="address-option-tile ${AppState.cart.selectedAddressId === addr.id ? 'selected' : ''}" onclick="window.aquaApp.selectCheckoutAddress('${addr.id}')">
                                    <input type="radio" name="checkoutAddress" class="address-radio" ${AppState.cart.selectedAddressId === addr.id ? 'checked' : ''}>
                                    <div class="address-content-side">
                                        <div class="address-label-badge">
                                            <span>${escapeHtml(addr.label || 'Home')}</span>
                                            ${addr.is_default ? '<span class="default-pill">Default</span>' : ''}
                                        </div>
                                        <div class="address-text-line">
                                            ${escapeHtml(addr.address_line || '')}${addr.barangay ? `, ${escapeHtml(addr.barangay)}` : ''}, ${escapeHtml(addr.city || 'Santa Cruz')}, ${escapeHtml(addr.province || 'Laguna')}
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `}

                <button type="button" class="btn-primary-action" onclick="window.aquaApp.goToCheckoutStep(2)">
                    Continue to Gallons
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </div>
        `;
    }

    // Step 2: Gallons & Instructions
    function renderCheckoutStep2(container) {
        const calcs = getCartCalculations();

        container.innerHTML = `
            <div class="order-detail-card">
                <h3 class="card-heading-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    Gallon Details & Special Notes
                </h3>

                ${calcs.refillCount > 0 ? `
                    <div style="background:#F0F7FC; padding:16px; border-radius:14px; border:1px solid #BCE3FD; margin-bottom:18px;">
                        <span style="font-size:13px; font-weight:700; color:#064B87; display:block; margin-bottom:4px;">Empty Container Preparation</span>
                        <p style="font-size:12.5px; color:#4A6B82; line-height:1.4;">
                            Please have your empty customer-owned water gallons ready for container intake. Our team sanitizes, inspects, and refills each container strictly under station FIFO queue protocols.
                        </p>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label class="form-label">Expected Empty Gallons for Intake (Max 5)</label>
                        <div class="quantity-stepper" style="margin-top:6px;">
                            <button type="button" class="stepper-btn" onclick="window.aquaApp.adjustExpectedGallons(-1)">-</button>
                            <span class="stepper-val" id="expectedGallonsVal">${AppState.cart.expectedGallons || calcs.refillCount}</span>
                            <button type="button" class="stepper-btn" onclick="window.aquaApp.adjustExpectedGallons(1)">+</button>
                        </div>
                    </div>
                ` : `
                    <div style="background:#E8F8F0; padding:16px; border-radius:14px; border:1px solid #BBE5D0; margin-bottom:18px;">
                        <span style="font-size:13px; font-weight:700; color:#128A47; display:block; margin-bottom:4px;">New Gallon Order</span>
                        <p style="font-size:12.5px; color:#2E7D32; line-height:1.4;">
                            Your order includes new filled water container(s). No empty gallon pickup is required!
                        </p>
                    </div>
                `}

                <div class="form-group">
                    <label class="form-label">Special Delivery / Pickup Instructions (Optional)</label>
                    <textarea class="form-textarea" id="checkoutNotesInput" placeholder="e.g., Gate code, ring doorbell, leave by porch door..." onchange="window.aquaApp.updateCheckoutNotes(this.value)">${escapeHtml(AppState.cart.notes || '')}</textarea>
                </div>

                <div style="display:flex; gap:12px; margin-top:20px;">
                    <button type="button" class="btn-guest-login" style="flex:1;" onclick="window.aquaApp.goToCheckoutStep(1)">
                        Back
                    </button>
                    <button type="button" class="btn-primary-action" style="flex:2; margin-top:0;" onclick="window.aquaApp.goToCheckoutStep(3)">
                        Continue to Payment
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </div>
            </div>
        `;
    }

    // Step 3: Payment Method
    function renderCheckoutStep3(container) {
        const isFirst = AppState.paymentRules.first_order;
        const isStation = AppState.cart.deliveryMethod === 'station_pickup';
        const calcs = getCartCalculations();

        container.innerHTML = `
            <div class="order-detail-card">
                <h3 class="card-heading-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    Select Payment Method
                </h3>

                ${isFirst ? `
                    <div style="background:#EAF7FF; border:1px solid #BCE3FD; padding:14px; border-radius:14px; margin-bottom:18px; display:flex; align-items:flex-start; gap:10px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0878C9" stroke-width="2" style="flex-shrink:0; margin-top:2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <p style="font-size:12.5px; color:#335C7D; line-height:1.4;">
                            <strong>First-Time Customer Policy:</strong> Your first order requires secure online payment via PayMongo (GCash, Maya, or Card). Cash on Delivery and Debt become available after your first completed order.
                        </p>
                    </div>
                ` : ''}

                <div class="payment-methods-list">
                    <div class="payment-method-card ${AppState.cart.paymentMethod === 'online' && AppState.paymentProvider === 'qrph' ? 'selected' : ''}" onclick="window.aquaApp.selectCheckoutProvider('qrph')">
                        <div class="payment-method-left">
                            <div class="payment-icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                            </div>
                            <div>
                                <div class="payment-method-name">QR Ph</div>
                                <div class="payment-method-desc">Scan with a supported bank or e-wallet app</div>
                            </div>
                        </div>
                        <span style="font-size:11px; font-weight:800; color:#059669; background:#D1FAE5; padding:3px 8px; border-radius:10px;">Fast & Secure</span>
                    </div>

                    <div class="payment-method-card ${AppState.cart.paymentMethod === 'online' && AppState.paymentProvider === 'gcash' ? 'selected' : ''}" onclick="window.aquaApp.selectCheckoutProvider('gcash')">
                        <div class="payment-method-left"><div class="payment-icon-box">₱</div><div><div class="payment-method-name">GCash</div><div class="payment-method-desc">Pay securely with GCash via PayMongo</div></div></div>
                    </div>

                    ${!isFirst ? `
                        <div class="payment-method-card ${AppState.cart.paymentMethod === 'cash' ? 'selected' : ''}" onclick="window.aquaApp.selectCheckoutPaymentMethod('cash')">
                            <div class="payment-method-left">
                                <div class="payment-icon-box">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </div>
                                <div>
                                    <div class="payment-method-name">${isStation ? 'Cash on Pickup' : 'Cash on Delivery (COD)'}</div>
                                    <div class="payment-method-desc">${isStation ? 'Pay directly at station counter upon receipt' : 'Pay cash to driver upon delivery arrival'}</div>
                                </div>
                            </div>
                        </div>

                        <div class="payment-method-card ${AppState.cart.paymentMethod === 'debt' ? 'selected' : ''}" onclick="window.aquaApp.selectCheckoutPaymentMethod('debt')">
                            <div class="payment-method-left">
                                <div class="payment-icon-box">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                </div>
                                <div>
                                    <div class="payment-method-name">Customer Credit (Debt)</div>
                                    <div class="payment-method-desc">Charge order to pre-approved credit balance</div>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>

                <div class="summary-row total-row" style="margin-bottom:16px;">
                    <span>Order Total</span>
                    <span>₱${calcs.total.toFixed(2)}</span>
                </div>

                <div style="display:flex; gap:12px;">
                    <button type="button" class="btn-guest-login" style="flex:1;" onclick="window.aquaApp.goToCheckoutStep(2)">
                        Back
                    </button>
                    <button type="button" class="btn-primary-action" style="flex:2; margin-top:0;" onclick="window.aquaApp.goToCheckoutStep(4)">
                        Review Order
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </div>
            </div>
        `;
    }

    // Step 4: Final Review & Place Order
    function renderCheckoutStep4(container) {
        const calcs = getCartCalculations();
        const address = AppState.addresses.find(a => a.id === AppState.cart.selectedAddressId);
        const isStation = AppState.cart.deliveryMethod === 'station_pickup';

        container.innerHTML = `
            <div class="order-detail-card">
                <h3 class="card-heading-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Final Order Review
                </h3>

                <!-- Products Summary -->
                <div style="margin-bottom:16px;">
                    <span style="font-size:12px; font-weight:800; color:#6F8BA3; text-transform:uppercase; letter-spacing:0.5px;">Products</span>
                    <div style="margin-top:8px; display:flex; flex-direction:column; gap:8px;">
                        ${AppState.cart.items.map(item => `
                            <div style="display:flex; align-items:center; justify-content:space-between; font-size:13.5px;">
                                <span>${item.quantity} × ${escapeHtml(item.product.name)}</span>
                                <span style="font-weight:700; color:#0878C9;">₱${(Number(item.product.price) * item.quantity).toFixed(2)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <!-- Fulfillment Summary -->
                <div style="padding-top:14px; border-top:1px solid #E5F1FA; margin-bottom:14px;">
                    <span style="font-size:12px; font-weight:800; color:#6F8BA3; text-transform:uppercase; letter-spacing:0.5px;">Fulfillment</span>
                    <p style="font-size:13.5px; font-weight:700; color:#12344D; margin-top:4px;">
                        ${isStation ? 'Station Pickup' : 'Home Delivery'}
                    </p>
                    <p style="font-size:12.5px; color:#6F8BA3; line-height:1.35;">
                        ${isStation ? escapeHtml(AppState.station.address) : (address ? `${escapeHtml(address.address_line)}, ${escapeHtml(address.barangay || '')}, ${escapeHtml(address.city || 'Santa Cruz')}` : 'No address selected')}
                    </p>
                </div>

                <!-- Payment Summary -->
                <div style="padding-top:14px; border-top:1px solid #E5F1FA; margin-bottom:14px;">
                    <span style="font-size:12px; font-weight:800; color:#6F8BA3; text-transform:uppercase; letter-spacing:0.5px;">Payment Method</span>
                    <p style="font-size:13.5px; font-weight:700; color:#12344D; margin-top:4px; text-transform:capitalize;">
                        ${escapeHtml(AppState.cart.paymentMethod === 'online' ? 'Online (PayMongo GCash / Maya / Card)' : AppState.cart.paymentMethod)}
                    </p>
                </div>

                <!-- Financial Breakdown -->
                <div style="padding-top:14px; border-top:1px solid #E5F1FA; margin-bottom:20px;">
                    <div class="summary-row">
                        <span>Items Subtotal</span>
                        <span>₱${calcs.subtotal.toFixed(2)}</span>
                    </div>
                    <div class="summary-row">
                        <span>Delivery Fee</span>
                        <span>${calcs.isPickup ? 'FREE (₱0.00)' : `₱${calcs.deliveryFee.toFixed(2)}`}</span>
                    </div>
                    <div class="summary-row total-row">
                        <span>Total Due</span>
                        <span>₱${calcs.total.toFixed(2)}</span>
                    </div>
                </div>

                <div style="display:flex; gap:12px;">
                    <button type="button" class="btn-guest-login" style="flex:1;" onclick="window.aquaApp.goToCheckoutStep(3)">
                        Back
                    </button>
                    <button type="button" class="btn-primary-action" id="btnPlaceOrder" style="flex:2; margin-top:0;" onclick="window.aquaApp.placeOrder()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        ${AppState.cart.paymentMethod === 'online' ? 'Proceed to Online Payment' : 'Confirm & Place Order'}
                    </button>
                </div>
            </div>
        `;
    }

    function goToCheckoutStep(step) {
        if (step === 2 && AppState.cart.deliveryMethod === 'home_delivery' && !AppState.cart.selectedAddressId) {
            showToast('Please select or add a delivery address first.', 'warning');
            return;
        }

        AppState.checkoutStep = step;
        renderCheckoutStepView();
    }

    function setCheckoutDeliveryMethod(method) {
        AppState.cart.deliveryMethod = method;
        saveCart();
        renderCheckoutStepView();
    }

    function selectCheckoutAddress(addrId) {
        AppState.cart.selectedAddressId = addrId;
        saveCart();
        renderCheckoutStepView();
    }

    function selectCheckoutPaymentMethod(method) {
        AppState.cart.paymentMethod = method;
        saveCart();
        renderCheckoutStepView();
    }

    function selectCheckoutProvider(provider) {
        AppState.cart.paymentMethod = 'online';
        AppState.paymentProvider = provider;
        saveCart();
        renderCheckoutStepView();
    }

    function adjustExpectedGallons(delta) {
        const calcs = getCartCalculations();
        let cur = AppState.cart.expectedGallons || calcs.refillCount;
        cur = Math.max(1, Math.min(5, cur + delta));
        AppState.cart.expectedGallons = cur;
        saveCart();
        const valEl = document.getElementById('expectedGallonsVal');
        if (valEl) valEl.textContent = cur;
    }

    function updateCheckoutNotes(text) {
        AppState.cart.notes = text;
        saveCart();
    }

    // Submit Order API
    async function placeOrder() {
        const btn = document.getElementById('btnPlaceOrder');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Submitting order...';
        }

        const calcs = getCartCalculations();
        const clientRequestId = `${Date.now()}-${Math.floor(Math.random() * 1000000)}`;

        const payload = {
            delivery_method: AppState.cart.deliveryMethod,
            address_id: AppState.cart.deliveryMethod === 'home_delivery' ? AppState.cart.selectedAddressId : null,
            items: AppState.cart.items.map(i => ({
                product_id: i.product.id,
                quantity: i.quantity
            })),
            expected_empty_gallons: calcs.refillCount > 0 ? (AppState.cart.expectedGallons || calcs.refillCount) : 0,
            payment_method: AppState.cart.paymentMethod,
            notes: AppState.cart.notes || '',
            client_request_id: clientRequestId
        };

        try {
            const res = await apiRequest('/customer/orders', 'POST', payload, true);
            const createdOrder = res?.data;

            if (!createdOrder) {
                throw new Error('Order creation did not return details.');
            }

            // Clear cart
            AppState.cart.items = [];
            AppState.cart.notes = '';
            saveCart();
            updateCartBadge();

            // Handle PayMongo Online Redirect if paymentMethod === 'online'
            if (AppState.cart.paymentMethod === 'online') {
                try {
                    const checkoutRes = await apiRequest(`/customer/orders/${createdOrder.id}/payment/checkout`, 'POST', { payment_method_type: AppState.paymentProvider || 'qrph' }, true);
                    const checkoutUrl = checkoutRes?.checkout_url || checkoutRes?.data?.checkout_url;

                    if (checkoutUrl) {
                        showToast('Redirecting to secure PayMongo payment...', 'info');
                        window.open(checkoutUrl, '_blank') || (window.location.href = checkoutUrl);
                    }
                } catch (payErr) {
                    console.warn('PayMongo checkout error:', payErr);
                    showToast('Order created! Please complete payment in Order Details.', 'warning');
                }
            }

            // Show order success modal
            showOrderSuccessModal(createdOrder);

        } catch (err) {
            showToast(err.message || 'Failed to place order. Please review your details.', 'error');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Confirm & Place Order';
            }
        }
    }

    function showOrderSuccessModal(order) {
        const modal = document.getElementById('orderSuccessModal');
        const numEl = document.getElementById('successOrderNumber');
        const totalEl = document.getElementById('successOrderTotal');
        const viewBtn = document.getElementById('successViewOrderBtn');

        if (numEl) numEl.textContent = `#${order.order_number}`;
        if (totalEl) totalEl.textContent = `₱${Number(order.total_amount).toFixed(2)}`;
        if (viewBtn) {
            viewBtn.onclick = () => {
                closeModal('orderSuccessModal');
                navigateTo('order-detail', order.id);
            };
        }

        openModal('orderSuccessModal');
    }

    // =========================================================================
    // 10. ORDERS LIST & DETAIL LOGIC (#view-orders, #view-order-detail)
    // =========================================================================
    async function loadOrders() {
        if (!AppState.token) {
            renderOrdersList();
            return;
        }

        try {
            const res = await apiRequest('/customer/orders', 'GET', null, true);
            if (res?.data) {
                AppState.orders = res.data;
            }
        } catch (_) {}

        renderOrdersList();
    }

    function renderOrdersList() {
        const container = document.getElementById('ordersListContainer');
        if (!container) return;

        if (!AppState.token) {
            window.location.href = '/admin/login';
            return;
        }

        let filtered = AppState.orders || [];
        const fLower = (AppState.orderFilter || 'all').toLowerCase();
        if (fLower === 'active' || fLower === 'active deliveries') {
            filtered = (AppState.orders || []).filter(o => !['delivered', 'cancelled'].includes(o.status.toLowerCase()));
        } else if (fLower === 'completed') {
            filtered = (AppState.orders || []).filter(o => ['delivered', 'completed'].includes(o.status.toLowerCase()));
        } else if (fLower === 'cancelled') {
            filtered = (AppState.orders || []).filter(o => o.status.toLowerCase() === 'cancelled');
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="empty-data-card" style="padding:48px 20px; text-align:center;">
                    <div class="empty-icon-circle" style="margin:0 auto 16px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    </div>
                    <div class="empty-title" style="font-size:18px; font-weight:800; color:#102F52; margin-bottom:6px;">No ${escapeHtml(AppState.orderFilter || 'Orders')} Found</div>
                    <div class="empty-subtitle" style="font-size:14px; color:#6483A5; margin-bottom:20px;">There are no orders matching this category filter.</div>
                    <button type="button" class="btn-card-action" style="max-width:200px; margin:0 auto;" onclick="window.aquaApp.navigateTo('home')">
                        Order Water Refill
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = filtered.map(order => {
            const statusLower = (order.status || '').toLowerCase();
            const total = Number(order.total_amount || 0).toFixed(2);
            const dateStr = formatDateTime(order.created_at);
            const itemsCount = (order.items || []).reduce((sum, i) => sum + i.quantity, 0);
            const isStation = order.delivery_method === 'station_pickup';

            let dotColor = '#1599E8';
            let statusName = order.status.replace(/_/g, ' ');
            if (statusLower === 'pending') {
                dotColor = '#F59E0B';
                statusName = 'Pending';
            } else if (statusLower === 'confirmed') {
                dotColor = '#1599E8';
                statusName = 'Confirmed';
            } else if (['delivered', 'completed'].includes(statusLower)) {
                dotColor = '#10B981';
                statusName = 'Delivered';
            } else if (statusLower === 'cancelled') {
                dotColor = '#EF4444';
                statusName = 'Cancelled';
            }

            return `
                <div class="order-card-row" onclick="window.aquaApp.navigateTo('tracking', '${order.id}')" style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid #EDF5FC; cursor:pointer; transition:background 0.15s ease;">
                    <div style="display:flex; align-items:center; gap:18px;">
                        <div class="order-splash-thumb" style="width:62px; height:62px; border-radius:14px; background:#F0F8FF; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0;">
                            <img src="/images/banner_gallon_splash.png" alt="Gallon" onerror="this.src='/images/aqua_clayne_gallon.png'" style="width:100%; height:100%; object-fit:contain;">
                        </div>
                        <div>
                            <div style="font-size:16px; font-weight:800; color:#102F52; margin-bottom:4px;">
                                Order #${escapeHtml(order.order_number)}
                            </div>
                            <div style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:${dotColor}; margin-bottom:4px;">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${dotColor};"></span>
                                ${escapeHtml(statusName)}
                            </div>
                            <div style="display:flex; align-items:center; gap:6px; font-size:13px; color:#6483A5;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                                <span>${escapeHtml(dateStr)} · ${itemsCount || 1} items · ${isStation ? 'Pickup' : 'Delivery'}</span>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:24px; flex-shrink:0;">
                        <div style="font-size:18px; font-weight:800; color:#0879D1;">
                            ₱${total}
                        </div>
                        <button type="button" class="btn-card-action-outline" style="padding:8px 22px; border-radius:20px; font-size:14px; font-weight:700; border:1.5px solid #0879D1; background:#FFFFFF; color:#0879D1; cursor:pointer;" onclick="event.stopPropagation(); window.aquaApp.navigateTo('tracking', '${order.id}')">
                            Details
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function setOrderFilter(filter) {
        AppState.orderFilter = filter;
        document.querySelectorAll('.order-filter-pill, .order-filter-btn').forEach(btn => {
            const f = btn.getAttribute('data-filter') || btn.textContent.trim().toLowerCase();
            const isActive = f.toLowerCase() === filter.toLowerCase() || 
                             (filter.toLowerCase() === 'all' && (f.includes('all') || f === ''));
            btn.classList.toggle('active', isActive);
        });
        renderOrdersList();
    }

    // Load Single Order Details
    async function loadOrderDetail(orderId) {
        const container = document.getElementById('orderDetailContainer');
        if (!container) return;

        container.innerHTML = `
            <div style="padding:40px; text-align:center;">
                <p style="color:#6F8BA3; font-weight:600;">Loading order details...</p>
            </div>
        `;

        try {
            const res = await apiRequest(`/customer/orders/${orderId}`, 'GET', null, true);
            AppState.orderDetail = res?.data;
            renderOrderDetailView();
        } catch (err) {
            container.innerHTML = `
                <div class="empty-data-card">
                    <div class="empty-title">Order not found</div>
                    <div class="empty-subtitle">${escapeHtml(err.message || 'Unable to retrieve order details.')}</div>
                    <button type="button" class="btn-guest-register" onclick="window.aquaApp.navigateTo('orders')">Back to Orders</button>
                </div>
            `;
        }
    }

    async function loadTrackingView(orderId = null) {
        if (!AppState.token) return;
        try {
            if (!AppState.orders || AppState.orders.length === 0) {
                const res = await apiRequest('/customer/orders', 'GET', null, true);
                if (res?.data) AppState.orders = res.data;
            }

            let targetOrder = null;
            if (orderId) {
                const searchStr = String(orderId).toLowerCase().trim();
                targetOrder = AppState.orders.find(o => 
                    String(o.id).toLowerCase() === searchStr || 
                    (o.order_number && o.order_number.toLowerCase().includes(searchStr))
                ) || null;
            }
            if (!targetOrder) {
                targetOrder = AppState.orders.find(o => !['delivered', 'cancelled'].includes(o.status.toLowerCase())) || AppState.orders[0] || null;
            }

            if (targetOrder) {
                loadOrderDetail(targetOrder.id);
            } else {
                const container = document.getElementById('orderDetailContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="empty-state-card" style="text-align:center; padding:48px 20px;">
                            <img src="/images/aqua_clayne_gallon.png" width="70" style="opacity:0.35; margin-bottom:16px;">
                            <h3 style="font-size:18px; font-weight:800; color:#12344D;">No Active Delivery in Transit</h3>
                            <p style="font-size:14px; color:#64748B; max-width:400px; margin:8px auto 20px;">You do not currently have any active orders in transit. Place a refill order to see live driver route tracking.</p>
                            <button type="button" class="btn-hero-primary" onclick="window.aquaApp.navigateTo('catalog')">Order Water Refill</button>
                        </div>
                    `;
                }
            }
        } catch (e) {
            console.error('Tracking view load failed:', e);
        }
    }

    async function loadGallonsView() {
        if (!AppState.token) return;
        try {
            const ordersRes = await apiRequest('/customer/orders', 'GET', null, true);
            if (ordersRes?.data) {
                AppState.orders = ordersRes.data;
                const inQueueOrder = AppState.orders.find(o => ['waiting_in_queue', 'refilling', 'in_progress'].includes(o.status.toLowerCase()));
                const queueHero = document.getElementById('queuePositionHero');
                const queueExpl = document.getElementById('queueStatusExplanation');
                if (inQueueOrder && queueHero && queueExpl) {
                    const isRefilling = inQueueOrder.status.toLowerCase() === 'refilling';
                    queueHero.textContent = isRefilling ? '#1 Refilling' : '#1 In Line';
                    queueExpl.textContent = `Order #${inQueueOrder.order_number} is currently being processed in our automated sanitized refill station.`;
                }
            }
        } catch (_) {}
    }

        function openReportIssueModal(orderNum) {
        const numInput = document.getElementById('reportIssueOrderNum');
        if (numInput && orderNum) {
            numInput.value = orderNum;
        }
        openModal('reportIssueModal');
    }

    function renderOrderDetailView() {
        const container = document.getElementById('orderDetailContainer');
        const o = AppState.orderDetail;
        if (!container || !o) return;

        const isStation = o.delivery_method === 'station_pickup';
        const isOnline = (o.payment_method || '').toLowerCase() === 'online';
        const isPaid = (o.payment?.status || '').toLowerCase() === 'paid';
        const total = Number(o.total_amount || 0).toFixed(2);
        const subtotal = (Number(o.total_amount || 0) - Number(o.delivery_fee || 0)).toFixed(2);
        const dateStr = formatDateTime(o.created_at);

        // Status badge styling
        let statusBadgeClass = 'badge-pending';
        let statusText = o.status.replace(/_/g, ' ');
        const sLower = (o.status || '').toLowerCase();
        if (sLower === 'confirmed') {
            statusBadgeClass = 'badge-confirmed';
        } else if (['delivered', 'completed'].includes(sLower)) {
            statusBadgeClass = 'badge-delivered';
        } else if (sLower === 'cancelled') {
            statusBadgeClass = 'badge-cancelled';
        }

        container.innerHTML = `
            <!-- Top Controls Row -->
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; width:100%;">
                <button type="button" class="btn-card-action-outline" style="padding:8px 20px; border-radius:24px; font-size:14px; font-weight:700; border:1.5px solid #0879D1; background:#FFFFFF; color:#0879D1; cursor:pointer;" onclick="window.aquaApp.navigateTo('orders')">
                    &larr; Back to Orders
                </button>
                <div style="display:inline-flex; align-items:center; gap:8px; padding:6px 14px; border-radius:20px; background:#EAF6FF; border:1px solid #D9EAFB;">
                    <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#0879D1;"></span>
                    <span style="font-size:13px; font-weight:700; color:#0879D1; text-transform:capitalize;">${escapeHtml(statusText)}</span>
                </div>
            </div>

            <!-- 2-Column Responsive Layout: Left 68% | Right 32% -->
            <div class="order-detail-layout">
                <!-- LEFT COLUMN: Main Tracking Information -->
                <div>
                    <!-- 1. Order Status Timeline Card -->
                    <div class="order-detail-card" style="background:#FFFFFF; border:1px solid #D9EAFB; border-radius:18px; padding:24px; margin-bottom:20px; box-shadow:0 4px 14px rgba(8,121,209,0.04);">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                            <h3 style="font-size:17px; font-weight:800; color:#102F52; display:flex; align-items:center; gap:8px; margin:0;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                                <span>Order Status Timeline</span>
                            </h3>
                            <span style="font-size:12px; font-weight:700; color:#0879D1; background:#F0F8FF; padding:4px 10px; border-radius:12px;">● In Progress</span>
                        </div>
                        ${renderOrderTimeline(o)}
                    </div>

                    <!-- 2. Order Items Card -->
                    <div class="order-detail-card" style="background:#FFFFFF; border:1px solid #D9EAFB; border-radius:18px; padding:24px; box-shadow:0 4px 14px rgba(8,121,209,0.04);">
                        <h3 style="font-size:17px; font-weight:800; color:#102F52; display:flex; align-items:center; gap:8px; margin:0 0 16px 0;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2.2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            <span>Order Items</span>
                        </h3>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            ${(o.items || []).map(i => `
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid #EDF5FC; gap:14px;">
                                    <div style="display:flex; align-items:center; gap:14px;">
                                        <div style="width:54px; height:54px; border-radius:12px; background:#F0F8FF; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0;">
                                            <img src="/images/banner_gallon_splash.png" alt="Gallon" onerror="this.src='/images/aqua_clayne_gallon.png'" style="width:100%; height:100%; object-fit:contain;">
                                        </div>
                                        <div>
                                            <div style="font-size:15px; font-weight:700; color:#102F52;">${escapeHtml(i.product?.name || 'Purified Water Refill')}</div>
                                            <div style="font-size:13px; color:#6483A5; margin-top:2px;">Qty: ${i.quantity} &times; ₱${Number(i.unit_price).toFixed(2)}</div>
                                        </div>
                                    </div>
                                    <span style="font-size:16px; font-weight:800; color:#0879D1;">₱${(Number(i.unit_price) * i.quantity).toFixed(2)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Order Information -->
                <div>
                    <!-- Order Summary Card -->
                    <div class="order-detail-card" style="background:#FFFFFF; border:1px solid #D9EAFB; border-radius:18px; padding:22px; margin-bottom:18px; box-shadow:0 4px 14px rgba(8,121,209,0.04);">
                        <h4 style="font-size:16px; font-weight:800; color:#102F52; display:flex; align-items:center; gap:8px; margin:0 0 16px 0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span>Order Summary</span>
                        </h4>
                        <div class="summary-row" style="display:flex; justify-content:space-between; padding:6px 0; font-size:14px; color:#6483A5;">
                            <span>Order Number</span><span style="font-weight:700; color:#102F52;">#${escapeHtml(o.order_number)}</span>
                        </div>
                        <div class="summary-row" style="display:flex; justify-content:space-between; padding:6px 0; font-size:14px; color:#6483A5;">
                            <span>Date</span><span style="font-weight:600; color:#102F52;">${escapeHtml(dateStr)}</span>
                        </div>
                        <div class="summary-row" style="display:flex; justify-content:space-between; padding:6px 0; font-size:14px; color:#6483A5;">
                            <span>Fulfillment</span><span style="font-weight:600; color:#102F52;">${isStation ? 'Station Pickup' : 'Home Delivery'}</span>
                        </div>
                        <div class="summary-row" style="display:flex; justify-content:space-between; padding:6px 0; font-size:14px; color:#6483A5;">
                            <span>Items Subtotal</span><span style="font-weight:600; color:#102F52;">₱${subtotal}</span>
                        </div>
                        <div class="summary-row" style="display:flex; justify-content:space-between; padding:6px 0; font-size:14px; color:#6483A5;">
                            <span>Delivery Fee</span><span style="font-weight:600; color:#102F52;">₱${Number(o.delivery_fee || 5.00).toFixed(2)}</span>
                        </div>
                        <div class="dropdown-divider" style="height:1px; background:#EDF5FC; margin:12px 0;"></div>
                        <div class="summary-row total-row" style="display:flex; justify-content:space-between; padding:4px 0; font-size:17px; font-weight:800; color:#0879D1;">
                            <span>Total Amount</span><span>₱${total}</span>
                        </div>
                    </div>

                    <!-- Payment Info Card -->
                    <div class="order-detail-card" style="background:#FFFFFF; border:1px solid #D9EAFB; border-radius:18px; padding:22px; margin-bottom:18px; box-shadow:0 4px 14px rgba(8,121,209,0.04);">
                        <h4 style="font-size:16px; font-weight:800; color:#102F52; display:flex; align-items:center; gap:8px; margin:0 0 16px 0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2.2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            <span>Payment Info</span>
                        </h4>
                        <div class="summary-row" style="display:flex; justify-content:space-between; padding:6px 0; font-size:14px; color:#6483A5;">
                            <span>Method</span><span style="font-weight:700; color:#102F52; text-transform:capitalize;">${escapeHtml(o.payment_method || 'Online')}</span>
                        </div>
                        <div class="summary-row" style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; font-size:14px; color:#6483A5;">
                            <span>Status</span>
                            <span style="font-size:12px; font-weight:800; padding:4px 10px; border-radius:12px; background:${isPaid ? '#ECFDF5' : '#FFFBEB'}; color:${isPaid ? '#10B981' : '#F59E0B'}; border:1px solid ${isPaid ? '#A7F3D0' : '#FDE68A'};">
                                ${isPaid ? 'PAID & CONFIRMED' : 'PENDING'}
                            </span>
                        </div>
                    </div>

                    <!-- Report Issue with this Order Button -->
                    <button type="button" class="btn-card-action-outline" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:12px; border-radius:14px; font-size:14px; font-weight:700; border:1.5px solid #D9EAFB; background:#FFFFFF; color:#102F52; cursor:pointer; box-shadow:0 2px 6px rgba(8,121,209,0.04); transition:all 0.2s ease;" onclick="window.aquaApp.openReportIssueModal('#${escapeHtml(o.order_number)}')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>Report Issue with this Order</span>
                    </button>
                </div>
            </div>
        `;
    }

    function renderOrderTimeline(order) {
        const isStation = order.delivery_method === 'station_pickup';
        const milestones = [
            { key: 'pending', title: 'Order Submitted', desc: 'Received at station' },
            { key: 'confirmed', title: 'Confirmed', desc: 'Order verified & scheduled' },
            { key: 'refilling', title: 'Refill Processing', desc: 'Container sanitized & refilled' },
            { key: 'ready_for_delivery', title: isStation ? 'Ready for Pickup' : 'Ready for Dispatch', desc: 'Quality seals applied' },
            { key: 'delivered', title: isStation ? 'Order Completed' : 'Delivered', desc: 'Order fulfilled' }
        ];

        const statusLower = (order.status || '').toLowerCase();
        let activeIdx = 0;
        if (statusLower === 'confirmed') activeIdx = 1;
        else if (['waiting_in_queue', 'refilling', 'in_refill', 'processing', 'in_progress'].includes(statusLower)) activeIdx = 2;
        else if (['ready_for_delivery', 'ready_for_pickup', 'out_for_delivery'].includes(statusLower)) activeIdx = 3;
        else if (['delivered', 'completed'].includes(statusLower)) activeIdx = 4;

        if (statusLower === 'cancelled') {
            return `
                <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:14px; padding:18px; text-align:center; margin:16px 0;">
                    <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#EF4444; margin-right:6px;"></span>
                    <strong style="color:#B91C1C; font-size:15px;">Order Cancelled</strong>
                    <p style="color:#7F1D1D; font-size:13px; margin-top:4px;">This order has been cancelled.</p>
                </div>
            `;
        }

        return `
            <div class="order-timeline-track" style="position:relative; padding-left:36px; margin:20px 0; display:flex; flex-direction:column; gap:22px;">
                <div style="position:absolute; top:12px; bottom:12px; left:13px; width:2px; background:#D9EAFB; z-index:1;"></div>
                ${milestones.map((m, idx) => {
                    const isDone = idx < activeIdx;
                    const isCurrent = idx === activeIdx;
                    let dotStyle = 'width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; position:absolute; left:-36px; top:0; z-index:2;';
                    if (isDone) {
                        dotStyle += 'background:#0879D1; color:#FFFFFF; border:2px solid #0879D1; box-shadow:0 2px 6px rgba(8,121,209,0.3);';
                    } else if (isCurrent) {
                        dotStyle += 'background:#FFFFFF; color:#0879D1; border:2.5px solid #0879D1; box-shadow:0 0 0 4px rgba(8,121,209,0.18);';
                    } else {
                        dotStyle += 'background:#FFFFFF; color:#94A3B8; border:2px solid #CBD5E1;';
                    }

                    return `
                        <div class="timeline-milestone" style="position:relative;">
                            <div class="timeline-dot" style="${dotStyle}">
                                ${isDone ? '✓' : idx + 1}
                            </div>
                            <div class="milestone-title" style="font-size:15px; font-weight:${isCurrent || isDone ? '700' : '600'}; color:${isCurrent || isDone ? '#102F52' : '#6483A5'};">
                                ${m.title}
                            </div>
                            <div class="milestone-time" style="font-size:13px; color:#6483A5; margin-top:2px;">
                                ${m.desc}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    // Leaflet.js Route Map for live tracking
    function initTrackingMap(order) {
        const mapEl = document.getElementById('deliveryTrackingMap');
        if (!mapEl || !window.L) return;

        if (AppState.trackingMap) {
            AppState.trackingMap.remove();
            AppState.trackingMap = null;
        }

        const stationLat = AppState.station.latitude;
        const stationLng = AppState.station.longitude;
        const destLat = Number(order.address?.latitude || (stationLat + 0.012));
        const destLng = Number(order.address?.longitude || (stationLng + 0.008));

        const map = L.map(mapEl).setView([stationLat, stationLng], 14);
        AppState.trackingMap = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        // Station Marker
        const stationIcon = L.divIcon({
            html: '<div style="background:#064B87; color:#FFFFFF; padding:6px; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.3); font-size:14px;">💧</div>',
            className: '',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });
        L.marker([stationLat, stationLng], { icon: stationIcon }).addTo(map).bindPopup('<strong>Aqua Clayne Station</strong>');

        // Destination Marker
        const destIcon = L.divIcon({
            html: '<div style="background:#16A34A; color:#FFFFFF; padding:6px; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.3); font-size:14px;">📍</div>',
            className: '',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });
        L.marker([destLat, destLng], { icon: destIcon }).addTo(map).bindPopup(`<strong>Delivery Destination</strong><br>${escapeHtml(order.address?.address_line || '')}`);

        // Polyline connecting station to destination
        const routeLine = L.polyline([
            [stationLat, stationLng],
            [destLat, destLng]
        ], { color: '#0878C9', weight: 4, dashArray: '6, 8' }).addTo(map);

        map.fitBounds(routeLine.getBounds(), { padding: [40, 40] });
    }

    async function cancelOrder(orderId) {
        showConfirmDialog({
            title: 'Cancel Order?',
            message: 'Are you sure you want to cancel this pending order? This action cannot be undone.',
            confirmText: 'Yes, Cancel Order',
            isDanger: true,
            onConfirm: async () => {
                try {
                    await apiRequest(`/customer/orders/${orderId}/cancel`, 'POST', {}, true);
                    showToast('Order cancelled.', 'info');
                    loadOrderDetail(orderId);
                } catch (err) {
                    showToast(err.message || 'Could not cancel order.', 'error');
                }
            }
        });
    }

    async function toggleOrderDeliveryMethod(orderId, newMethod) {
        try {
            const payload = {
                delivery_method: newMethod
            };
            if (newMethod === 'home_delivery') {
                const def = AppState.addresses[0];
                if (!def) {
                    showToast('Please add a delivery address first.', 'warning');
                    return;
                }
                payload.address_id = def.id;
            }

            await apiRequest(`/customer/orders/${orderId}/delivery-method`, 'PATCH', payload, true);
            showToast(`Order updated to ${newMethod.replace('_', ' ')}.`, 'success');
            loadOrderDetail(orderId);
        } catch (err) {
            showToast(err.message || 'Could not change fulfillment method.', 'error');
        }
    }

    async function payOnlineNow(orderId) {
        try {
            const res = await apiRequest(`/customer/orders/${orderId}/payment/checkout`, 'POST', {}, true);
            const url = res?.checkout_url || res?.data?.checkout_url;
            if (url) {
                window.open(url, '_blank') || (window.location.href = url);
            }
        } catch (err) {
            showToast(err.message || 'Payment link unavailable.', 'error');
        }
    }

    // =========================================================================
    // 11. SAVED ADDRESSES BOOK (#view-addresses)
    // =========================================================================
    async function loadAddresses() {
        if (!AppState.token) return;

        try {
            const res = await apiRequest('/customer/addresses', 'GET', null, true);
            if (res?.data) {
                AppState.addresses = res.data;
            }
        } catch (_) {}

        renderAddressesView();
    }

    function renderAddressesView() {
        const container = document.getElementById('addressesListContainer');
        if (!container) return;

        if (AppState.addresses.length === 0) {
            container.innerHTML = `
                <div class="empty-data-card">
                    <div class="empty-icon-circle">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div class="empty-title">No Saved Addresses</div>
                    <div class="empty-subtitle">Save your home, office, or store location in Santa Cruz, Laguna for fast ordering.</div>
                    <button type="button" class="btn-guest-register" onclick="window.aquaApp.openAddAddressModal()">
                        + Add New Address
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = AppState.addresses.map(addr => `
            <div class="order-detail-card" style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px;">
                <div>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                        <span style="font-size:16px; font-weight:800; color:#12344D;">${escapeHtml(addr.label || 'Home')}</span>
                        ${addr.is_default ? '<span class="default-pill">Default Address</span>' : ''}
                    </div>
                    <p style="font-size:13.5px; color:#4A6B82; line-height:1.4;">
                        ${escapeHtml(addr.address_line || '')}${addr.barangay ? `, ${escapeHtml(addr.barangay)}` : ''}, ${escapeHtml(addr.city || 'Santa Cruz')}, ${escapeHtml(addr.province || 'Laguna')} ${escapeHtml(addr.zip_code || '4009')}
                    </p>
                    <div style="font-size:11.5px; color:#6F8BA3; margin-top:4px;">
                        Coordinates: Lat ${Number(addr.latitude || 0).toFixed(4)}, Lng ${Number(addr.longitude || 0).toFixed(4)}
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    ${!addr.is_default ? `
                        <button type="button" class="btn-view-details-pill" onclick="window.aquaApp.setDefaultAddress('${addr.id}')">
                            Make Default
                        </button>
                    ` : ''}
                    <button type="button" class="btn-remove-item" title="Delete Address" onclick="window.aquaApp.deleteAddress('${addr.id}')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>
        `).join('');
    }

    async function setDefaultAddress(addrId) {
        try {
            await apiRequest(`/customer/addresses/${addrId}/set-default`, 'POST', {}, true);
            showToast('Default address updated.', 'success');
            loadAddresses();
        } catch (err) {
            showToast(err.message || 'Could not set default address.', 'error');
        }
    }

    async function deleteAddress(addrId) {
        showConfirmDialog({
            title: 'Delete Address?',
            message: 'Are you sure you want to delete this saved delivery address?',
            confirmText: 'Delete',
            isDanger: true,
            onConfirm: async () => {
                try {
                    await apiRequest(`/customer/addresses/${addrId}`, 'DELETE', {}, true);
                    showToast('Address deleted.', 'info');
                    loadAddresses();
                } catch (err) {
                    showToast(err.message || 'Could not delete address.', 'error');
                }
            }
        });
    }

    function openAddAddressModal() {
        const form = document.getElementById('addressForm');
        if (form) form.reset();
        openModal('addressModal');
    }

    async function saveAddressForm(formData) {
        try {
            await apiRequest('/customer/addresses', 'POST', formData, true);
            showToast('New address saved successfully!', 'success');
            closeModal('addressModal');
            loadAddresses();
            if (AppState.currentView === 'checkout') {
                renderCheckoutStepView();
            }
        } catch (err) {
            showToast(err.message || 'Failed to save address.', 'error');
        }
    }

    // =========================================================================
    // 12. PAYMENTS & WALLET (#view-payments)
    // =========================================================================
    async function loadPayments() {
        if (!AppState.token) return;

        try {
            const [paymentsRes, summaryRes] = await Promise.all([
                apiRequest('/customer/payments', 'GET', null, true).catch(() => null),
                apiRequest('/customer/payments/summary', 'GET', null, true).catch(() => null)
            ]);

            if (paymentsRes?.data) {
                AppState.payments = paymentsRes.data;
            }

            if (summaryRes?.data) {
                AppState.paymentSummary = {
                    outstandingBalance: Number(summaryRes.data.outstanding_balance || 0),
                    totalPaid: Number(summaryRes.data.total_paid || 0),
                    allPaidUp: Boolean(summaryRes.data.all_paid_up),
                    totalCount: Number(summaryRes.data.total_payments_count || 0)
                };
            }
        } catch (_) {}

        renderPaymentsView();
    }

    function renderPaymentsView() {
        const summaryCard = document.getElementById('paymentSummaryCard');
        const listContainer = document.getElementById('paymentsListContainer');
        if (!summaryCard || !listContainer) return;

        const isAllPaid = AppState.paymentSummary.allPaidUp || AppState.paymentSummary.outstandingBalance <= 0;

        summaryCard.innerHTML = `
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <span style="font-size:12px; font-weight:800; color:#064B87; letter-spacing:0.6px; text-transform:uppercase;">Payment Summary</span>
                <span class="order-badge-status ${isAllPaid ? 'badge-delivered' : 'badge-pending'}">
                    ${isAllPaid ? 'All Paid Up' : 'Balance Due'}
                </span>
            </div>
            <div style="font-size:12px; color:#6F8BA3; font-weight:600;">Outstanding Balance</div>
            <div style="font-size:32px; font-weight:900; color:${isAllPaid ? '#064B87' : '#D97706'}; letter-spacing:-0.5px;">
                ₱${AppState.paymentSummary.outstandingBalance.toFixed(2)}
            </div>
            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:14px; padding-top:12px; border-top:1px solid #E5F1FA; font-size:12.5px; color:#6F8BA3;">
                <span>Total Amount Paid: <strong>₱${AppState.paymentSummary.totalPaid.toFixed(2)}</strong></span>
                <span>Total Transactions: <strong>${AppState.payments.length}</strong></span>
            </div>
        `;

        let filtered = AppState.payments;
        if (AppState.paymentFilter === 'PAID') {
            filtered = AppState.payments.filter(p => p.status.toLowerCase() === 'paid');
        } else if (AppState.paymentFilter === 'PENDING') {
            filtered = AppState.payments.filter(p => p.status.toLowerCase() === 'pending');
        } else if (AppState.paymentFilter === 'OVERDUE') {
            filtered = AppState.payments.filter(p => p.status.toLowerCase() === 'overdue');
        }

        if (filtered.length === 0) {
            listContainer.innerHTML = `
                <div class="empty-data-card">
                    <div class="empty-title">No ${escapeHtml(AppState.paymentFilter)} Payment Records</div>
                    <div class="empty-subtitle">Receipts, online settlements, and ledger balances will appear here.</div>
                </div>
            `;
            return;
        }

        listContainer.innerHTML = filtered.map(pay => `
            <div class="order-card-row">
                <div>
                    <div style="font-size:15px; font-weight:800; color:#12344D;">Order #${escapeHtml(pay.order_number || pay.order?.order_number || 'Receipt')}</div>
                    <span class="order-badge-status badge-${pay.status.toLowerCase()}" style="margin:4px 0;">
                        ${escapeHtml(pay.status.toUpperCase())}
                    </span>
                    <div style="font-size:12px; color:#6F8BA3;">Method: ${escapeHtml((pay.method || 'online').toUpperCase())} · ${escapeHtml(formatDateTime(pay.created_at))}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:17px; font-weight:800; color:#0878C9;">₱${Number(pay.amount).toFixed(2)}</div>
                    ${pay.order_id ? `
                        <button type="button" class="btn-view-details-pill" onclick="window.aquaApp.navigateTo('order-detail', '${pay.order_id}')">
                            View Order
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');
    }

    function setPaymentFilter(filter) {
        AppState.paymentFilter = filter;
        document.querySelectorAll('.payment-filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-filter') === filter);
        });
        renderPaymentsView();
    }

    // =========================================================================
    // 13. NOTIFICATIONS LOGIC (#view-notifications)
    // =========================================================================
    async function loadNotifications() {
        if (!AppState.token) return;

        try {
            const res = await apiRequest('/customer/notifications', 'GET', null, true);
            if (res?.data) {
                AppState.notifications = res.data;
                AppState.unreadNotifCount = AppState.notifications.filter(n => !n.is_read).length;
                updateNotifBadge();
            }
        } catch (_) {}

        renderNotificationsView();
    }

    function updateNotifBadge() {
        document.querySelectorAll('.notif-badge-count').forEach(el => {
            el.textContent = AppState.unreadNotifCount > 99 ? '99+' : AppState.unreadNotifCount;
            el.style.display = AppState.unreadNotifCount > 0 ? 'block' : 'none';
        });
    }

    function renderNotificationsView() {
        const container = document.getElementById('notificationsListContainer');
        if (!container) return;

        let filtered = AppState.notifications;
        if (AppState.notifFilter === 'Unread') {
            filtered = AppState.notifications.filter(n => !n.is_read);
        } else if (AppState.notifFilter === 'Orders') {
            filtered = AppState.notifications.filter(n => n.entity_type === 'order');
        } else if (AppState.notifFilter === 'Payments') {
            filtered = AppState.notifications.filter(n => n.entity_type === 'payment');
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="empty-data-card">
                    <div class="empty-icon-circle">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                    </div>
                    <div class="empty-title">No notifications</div>
                    <div class="empty-subtitle">Station updates and order milestones will appear here.</div>
                </div>
            `;
            return;
        }

        container.innerHTML = filtered.map(notif => `
            <div class="order-detail-card" style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; border-left:4px solid ${notif.is_read ? '#D5E7F4' : '#0878C9'};">
                <div style="flex:1;">
                    <h4 style="font-size:14.5px; font-weight:800; color:#12344D; margin-bottom:4px;">${escapeHtml(notif.title)}</h4>
                    <p style="font-size:13px; color:#4A6B82; line-height:1.4;">${escapeHtml(notif.body || notif.message || '')}</p>
                    <span style="font-size:11.5px; color:#6F8BA3; margin-top:6px; display:inline-block;">${escapeHtml(formatDateTime(notif.created_at))}</span>
                </div>
                <div>
                    ${!notif.is_read ? `
                        <button type="button" class="btn-view-details-pill" onclick="window.aquaApp.markNotificationRead('${notif.id}')">
                            Mark Read
                        </button>
                    ` : ''}
                    ${notif.entity_type === 'order' && notif.entity_id ? `
                        <button type="button" class="btn-view-details-pill" style="margin-top:4px;" onclick="window.aquaApp.navigateTo('order-detail', '${notif.entity_id}')">
                            View Order
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');
    }

    async function markNotificationRead(notifId) {
        try {
            await apiRequest(`/customer/notifications/${notifId}/read`, 'PATCH', {}, true);
            const n = AppState.notifications.find(x => x.id === notifId);
            if (n) n.is_read = true;
            AppState.unreadNotifCount = Math.max(0, AppState.unreadNotifCount - 1);
            updateNotifBadge();
            renderNotificationsView();
        } catch (_) {}
    }

    async function markAllNotificationsRead() {
        try {
            await apiRequest('/customer/notifications/read-all', 'POST', {}, true);
            AppState.notifications.forEach(n => n.is_read = true);
            AppState.unreadNotifCount = 0;
            updateNotifBadge();
            renderNotificationsView();
            showToast('All notifications marked as read.', 'info');
        } catch (_) {}
    }

    // =========================================================================
    // 14. COMPLAINTS & INQUIRIES (#view-complaints)
    // =========================================================================
    async function loadComplaints() {
        if (!AppState.token) return;

        try {
            const [complaintsRes, catsRes] = await Promise.all([
                apiRequest('/customer/complaints', 'GET', null, true).catch(() => null),
                apiRequest('/customer/complaint-categories', 'GET', null, false).catch(() => null)
            ]);

            if (complaintsRes?.data) {
                AppState.complaints = complaintsRes.data;
            }

            if (catsRes?.data) {
                AppState.complaintCategories = catsRes.data;
            }
        } catch (_) {}

        renderComplaintsView();
    }

    function renderComplaintsView() {
        const container = document.getElementById('complaintsListContainer');
        if (!container) return;

        let filtered = AppState.complaints;
        if (AppState.complaintFilter !== 'ALL') {
            filtered = AppState.complaints.filter(c => c.status.toUpperCase() === AppState.complaintFilter);
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="empty-data-card">
                    <div class="empty-title">No ${escapeHtml(AppState.complaintFilter)} Complaints</div>
                    <div class="empty-subtitle">Need assistance with water quality, containers, or delivery? Submit a ticket anytime.</div>
                    <button type="button" class="btn-guest-register" onclick="window.aquaApp.openSubmitComplaintModal()">
                        Submit Complaint / Inquiry
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = filtered.map(c => `
            <div class="order-detail-card" onclick="window.aquaApp.openComplaintDetailModal('${c.id}')" style="cursor:pointer;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                    <span style="font-size:12px; font-weight:800; color:#0878C9;">TICKET #${escapeHtml(c.ticket_number || c.id.substring(0, 8))}</span>
                    <span class="order-badge-status badge-${c.status.toLowerCase().replace(/\s/g, '_')}">
                        ${escapeHtml(c.status)}
                    </span>
                </div>
                <h4 style="font-size:15px; font-weight:800; color:#12344D; margin-bottom:4px;">${escapeHtml(c.subject || 'Support Ticket')}</h4>
                <p style="font-size:13px; color:#6F8BA3; line-height:1.4;">${escapeHtml(c.description || '')}</p>
                <div style="font-size:11.5px; color:#90A9BE; margin-top:8px;">Submitted on ${escapeHtml(formatDateTime(c.created_at))}</div>
            </div>
        `).join('');
    }

    function openSubmitComplaintModal(prefilledOrderId = '') {
        const catSelect = document.getElementById('complaintCategorySelect');
        const orderInput = document.getElementById('complaintOrderInput');

        if (catSelect && AppState.complaintCategories.length > 0) {
            catSelect.innerHTML = `
                <option value="">Select Category...</option>
                ${AppState.complaintCategories.map(cat => `
                    <option value="${cat.id}">${escapeHtml(cat.name)}</option>
                `).join('')}
            `;
        }

        if (orderInput) {
            orderInput.value = prefilledOrderId;
        }

        openModal('complaintModal');
    }

    async function submitComplaintForm(data) {
        try {
            await apiRequest('/customer/complaints', 'POST', data, true);
            showToast('Complaint ticket submitted. Our station supervisor will investigate.', 'success');
            closeModal('complaintModal');
            loadComplaints();
        } catch (err) {
            showToast(err.message || 'Failed to submit complaint.', 'error');
        }
    }

    async function openComplaintDetailModal(complaintId) {
        try {
            const res = await apiRequest(`/customer/complaints/${complaintId}`, 'GET', null, true);
            const c = res?.data;
            if (!c) return;

            const modalTitle = document.getElementById('complaintDetailTitle');
            const modalBody = document.getElementById('complaintDetailBody');

            if (modalTitle) modalTitle.textContent = `Ticket #${c.ticket_number || c.id.substring(0, 8)}`;
            if (modalBody) {
                modalBody.innerHTML = `
                    <div style="margin-bottom:12px;">
                        <span class="order-badge-status badge-${c.status.toLowerCase().replace(/\s/g, '_')}">${escapeHtml(c.status)}</span>
                        <h4 style="font-size:16px; font-weight:800; color:#12344D; margin-top:8px;">${escapeHtml(c.subject || 'Complaint')}</h4>
                        <p style="font-size:13px; color:#4A6B82; margin-top:4px;">${escapeHtml(c.description || '')}</p>
                    </div>
                    ${c.admin_response ? `
                        <div style="background:#F0F7FC; padding:14px; border-radius:12px; border:1px solid #BCE3FD; margin-top:14px;">
                            <strong style="color:#064B87; font-size:13px;">Station Management Response:</strong>
                            <p style="font-size:12.5px; color:#12344D; margin-top:4px; line-height:1.4;">${escapeHtml(c.admin_response)}</p>
                        </div>
                    ` : `
                        <div style="background:#FFFBEB; padding:12px; border-radius:10px; border:1px solid #FDE68A; margin-top:14px; font-size:12px; color:#B45309;">
                            This ticket is currently under review by our operations team.
                        </div>
                    `}
                `;
            }

            openModal('complaintDetailModal');
        } catch (_) {}
    }

    // =========================================================================
    // 15. FAQS & KNOWLEDGE BASE (#view-faqs)
    // =========================================================================
    async function loadFaqs() {
        try {
            const res = await apiRequest('/customer/faqs', 'GET', null, false);
            if (res) {
                AppState.faqs = Array.isArray(res) ? res : (res.data || []);
            }
        } catch (_) {}

        renderFaqsView();
    }

    function renderFaqsView() {
        const container = document.getElementById('faqsAccordionList');
        if (!container) return;

        let filtered = AppState.faqs;
        if (AppState.faqQuery.trim()) {
            const q = AppState.faqQuery.toLowerCase();
            filtered = AppState.faqs.filter(f =>
                (f.question || '').toLowerCase().includes(q) || (f.answer || '').toLowerCase().includes(q)
            );
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="empty-data-card">
                    <div class="empty-title">No FAQs found</div>
                    <div class="empty-subtitle">Try searching for other terms like "refill", "delivery", or "payment".</div>
                </div>
            `;
            return;
        }

        container.innerHTML = filtered.map((faq, idx) => `
            <div class="order-detail-card" style="padding:16px 20px; cursor:pointer;" onclick="window.aquaApp.toggleFaq(${idx})">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size:14.5px; font-weight:800; color:#12344D;">${escapeHtml(faq.question)}</span>
                    <span id="faq-chevron-${idx}" style="font-size:14px; color:#0878C9; transition:transform 0.2s;">▼</span>
                </div>
                <div id="faq-body-${idx}" style="display:none; margin-top:10px; padding-top:10px; border-top:1px solid #F1F5F9; font-size:13px; color:#4A6B82; line-height:1.5;">
                    ${escapeHtml(faq.answer)}
                </div>
            </div>
        `).join('');
    }

    function toggleFaq(idx) {
        const body = document.getElementById(`faq-body-${idx}`);
        const chevron = document.getElementById(`faq-chevron-${idx}`);
        if (!body) return;

        const isHidden = body.style.display === 'none';
        body.style.display = isHidden ? 'block' : 'none';
        if (chevron) {
            chevron.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }

    function filterFaqs(query) {
        AppState.faqQuery = query;
        renderFaqsView();
    }

    // =========================================================================
    // 16. LIVE SUPPORT CHAT (#view-chat)
    // =========================================================================
    async function loadChat() {
        if (!AppState.token) {
            renderChatView();
            return;
        }

        try {
            const [chatRes, faqsRes] = await Promise.all([
                apiRequest('/chat', 'GET', null, true).catch(() => null),
                apiRequest('/customer/faqs', 'GET', null, false).catch(() => null)
            ]);

            if (chatRes?.messages) {
                AppState.chatMessages = chatRes.messages;
            }
            if (faqsRes) {
                AppState.faqs = Array.isArray(faqsRes) ? faqsRes : (faqsRes.data || []);
            }
        } catch (_) {}

        renderChatView();
    }

    function renderChatView() {
        const msgContainer = document.getElementById('chatMessagesBox');
        const chipsContainer = document.getElementById('chatFaqChipsBox');
        if (!msgContainer) return;

        if (!AppState.token) {
            window.location.href = '/admin/login';
            return;
        }

        // Render Messages
        msgContainer.innerHTML = AppState.chatMessages.map(m => {
            const isCustomer = (m.sender_role || '').toLowerCase() === 'customer';
            const timeStr = formatTimeOnly(m.created_at);

            return `
                <div class="chat-bubble ${isCustomer ? 'customer-bubble' : 'staff-bubble'}">
                    <div>${escapeHtml(m.content)}</div>
                    <div class="chat-bubble-time">${escapeHtml(timeStr)}</div>
                </div>
            `;
        }).join('');

        // Scroll to bottom
        msgContainer.scrollTop = msgContainer.scrollHeight;

        // Render Quick Chips
        if (chipsContainer && AppState.faqs.length > 0) {
            chipsContainer.innerHTML = AppState.faqs.slice(0, 5).map(f => `
                <button type="button" class="faq-chip-btn" onclick="window.aquaApp.sendFaqQuestion('${escapeHtml(f.question)}', '${escapeHtml(f.answer)}')">
                    ${escapeHtml(f.question)}
                </button>
            `).join('');
        }
    }

    async function sendChatMessage(content) {
        if (!content || !content.trim() || AppState.chatSending) return;

        AppState.chatSending = true;
        const text = content.trim();

        // Optimistic append
        AppState.chatMessages.push({
            content: text,
            sender_role: 'customer',
            created_at: new Date().toISOString()
        });
        renderChatView();

        try {
            await apiRequest('/chat/messages', 'POST', { content: text }, true);
            const res = await apiRequest('/chat', 'GET', null, true);
            if (res?.messages) {
                AppState.chatMessages = res.messages;
                renderChatView();
            }
        } catch (err) {
            showToast('Failed to send message.', 'error');
        } finally {
            AppState.chatSending = false;
        }
    }

    function sendFaqQuestion(question, answer) {
        // Post FAQ into chat as immediate local answer
        AppState.chatMessages.push(
            { content: question, sender_role: 'customer', created_at: new Date().toISOString() },
            { content: answer, sender_role: 'station', created_at: new Date().toISOString() }
        );
        renderChatView();
    }

    // =========================================================================
    // 17. CUSTOMER PROFILE & SECURITY (#view-profile)
    // =========================================================================
    async function loadProfile() {
        if (!AppState.token) return;

        try {
            const res = await apiRequest('/auth/me', 'GET', null, true);
            if (res?.data?.user) {
                AppState.user = res.data.user;
                localStorage.setItem('aqua_customer_user', JSON.stringify(AppState.user));
            }
        } catch (_) {}

        renderProfileView();
    }

    function renderProfileView() {
        const container = document.getElementById('profileViewContainer');
        if (!container || !AppState.user) return;

        const u = AppState.user;
        const initials = getInitials(u.name);

        container.innerHTML = `
            <!-- 1. Profile Identity Card -->
            <div class="order-detail-card" style="display:flex; align-items:center; gap:16px;">
                <div class="user-avatar-mini" style="width:60px; height:60px; font-size:22px;">
                    ${initials}
                </div>
                <div>
                    <h3 style="font-size:18px; font-weight:800; color:#12344D;">${escapeHtml(u.name)}</h3>
                    <p style="font-size:13px; color:#6F8BA3;">${escapeHtml(u.email || '')}</p>
                    <span class="order-badge-status badge-delivered" style="margin-top:6px;">Verified Customer</span>
                </div>
            </div>

            <!-- 2. Personal Information Card -->
            <div class="order-detail-card">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                    <h4 class="card-heading-title" style="margin-bottom:0;">Personal Information</h4>
                    <button type="button" class="section-view-all" onclick="window.aquaApp.openEditProfileModal()">Edit</button>
                </div>
                <div class="summary-row"><span>Full Name</span><span style="font-weight:700;">${escapeHtml(u.name)}</span></div>
                <div class="summary-row"><span>Email Address</span><span>${escapeHtml(u.email || '—')}</span></div>
                <div class="summary-row"><span>Mobile Number</span><span>${escapeHtml(u.phone || '—')}</span></div>
                <div class="summary-row"><span>Birthdate</span><span>${escapeHtml(u.birthdate || 'Confirmed')}</span></div>
            </div>

            <!-- 3. Security Settings Card -->
            <div class="order-detail-card">
                <h4 class="card-heading-title">Security Settings</h4>
                <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0;">
                    <div>
                        <div style="font-size:14px; font-weight:700; color:#12344D;">Change Login Password</div>
                        <div style="font-size:12px; color:#6F8BA3;">Update your account password with security validation</div>
                    </div>
                    <button type="button" class="btn-guest-login" onclick="window.aquaApp.openChangePasswordModal()">
                        Change
                    </button>
                </div>
            </div>

            <!-- 4. Quick Actions -->
            <div class="order-detail-card">
                <h4 class="card-heading-title">Account Shortcuts</h4>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <button type="button" class="dropdown-link" onclick="window.aquaApp.navigateTo('orders')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                        My Order History
                    </button>
                    <button type="button" class="dropdown-link" onclick="window.aquaApp.navigateTo('addresses')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/></svg>
                        Manage Delivery Addresses
                    </button>
                    <button type="button" class="dropdown-link" onclick="window.aquaApp.navigateTo('payments')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/></svg>
                        Payments & Credit Records
                    </button>
                    <button type="button" class="dropdown-link" onclick="window.aquaApp.navigateTo('complaints')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/></svg>
                        Complaints & Inquiries
                    </button>
                </div>
            </div>

            <!-- 5. Logout -->
            <button type="button" class="btn-guest-login danger-btn" style="width:100%; color:#DC2626; border-color:#FCA5A5; background:#FEF2F2; padding:12px;" onclick="window.aquaApp.confirmLogout()">
                Log Out of AQUA CLAYNE
            </button>
        `;
    }

    function confirmLogout() {
        // Dismiss menus if open
        const profileDropdown = document.getElementById('userProfileDropdown');
        if (profileDropdown) profileDropdown.classList.remove('show');
        toggleMobileMenu(false);

        showConfirmDialog({
            title: 'Log Out of Aqua Clayne?',
            message: 'Are you sure you want to log out of your session? You will need to sign in again to place orders and track deliveries.',
            confirmText: 'Log Out',
            isDanger: true,
            onConfirm: () => {
                executeLogout();
            }
        });
    }

    async function executeLogout() {
        showToast('Logging out...', 'info');

        // Dismiss menus if open
        const profileDropdown = document.getElementById('userProfileDropdown');
        if (profileDropdown) profileDropdown.classList.remove('show');
        toggleMobileMenu(false);

        // 1. Try revoking API token
        const token = AppState.token || localStorage.getItem('aqua_customer_token');
        if (token) {
            try {
                await fetch('/api/auth/logout', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });
            } catch (_) {}
        }

        // 2. Clear client storage
        try {
            localStorage.removeItem('aqua_customer_token');
            localStorage.removeItem('aqua_customer_user');
            localStorage.removeItem('aqua_customer_cart');
            sessionStorage.clear();
        } catch (_) {}

        // 3. Reset AppState
        AppState.token = null;
        AppState.user = null;
        AppState.cart = {
            items: [],
            deliveryMethod: 'home_delivery',
            selectedAddressId: null,
            expectedGallons: 1,
            notes: '',
            paymentMethod: 'online'
        };

        // 4. Invalidate web session via form POST to /customer/logout
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/customer/logout';
        form.style.display = 'none';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrf) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = csrf;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();

        // 5. Fallback redirect if form submit does not navigate
        setTimeout(() => {
            window.location.href = '/customer/logout';
        }, 600);
    }

        function openEditProfileModal() {
        if (!AppState.user) return;
        const u = AppState.user;
        const nameEl = document.getElementById('editModalName');
        const emailEl = document.getElementById('editModalEmail');
        const phoneEl = document.getElementById('editModalPhone');
        const birthEl = document.getElementById('editModalBirthdate');
        const avatarEl = document.getElementById('editModalAvatar');

        if (nameEl) nameEl.value = u.name || '';
        if (emailEl) emailEl.value = u.email || '';
        if (phoneEl) phoneEl.value = u.phone || '';
        if (birthEl) birthEl.value = u.birthdate || '';
        if (avatarEl) {
            avatarEl.textContent = getInitials(u.name);
        }

        // Also support legacy IDs
        const legacyName = document.getElementById('editProfileName');
        if (legacyName) legacyName.value = u.name || '';
        const legacyEmail = document.getElementById('editProfileEmail');
        if (legacyEmail) legacyEmail.value = u.email || '';
        const legacyPhone = document.getElementById('editProfilePhone');
        if (legacyPhone) legacyPhone.value = u.phone || '';

        openModal('editPersonalModal');
        openModal('editProfileModal');
    }

    async function handleSaveProfile(event) {
        if (event) event.preventDefault();
        const btn = document.getElementById('btnSaveProfile');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving Changes...';
        }

        const name = document.getElementById('editModalName')?.value.trim() || document.getElementById('editProfileName')?.value.trim() || '';
        const email = document.getElementById('editModalEmail')?.value.trim() || document.getElementById('editProfileEmail')?.value.trim() || '';
        const phone = document.getElementById('editModalPhone')?.value.trim() || document.getElementById('editProfilePhone')?.value.trim() || '';
        const birthdate = document.getElementById('editModalBirthdate')?.value.trim() || '';
        const photoInput = document.getElementById('profilePhotoInput');

        try {
            // 1. Update text profile info
            const res = await apiRequest('/auth/profile', 'POST', {
                name: name,
                email: email,
                phone: phone,
                birthdate: birthdate
            }, true);

            if (res?.data?.user) {
                AppState.user = res.data.user;
            } else if (res?.user) {
                AppState.user = res.user;
            } else {
                AppState.user = Object.assign({}, AppState.user, { name, email, phone, birthdate });
            }

            // 2. Upload photo if selected
            if (photoInput && photoInput.files && photoInput.files[0]) {
                const photoData = new FormData();
                photoData.append('photo', photoInput.files[0]);

                const photoRes = await fetch('/api/v1/auth/profile/photo', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${AppState.token}`,
                        'Accept': 'application/json'
                    },
                    body: photoData
                });
                const photoJson = await photoRes.json().catch(() => null);
                if (photoJson?.data?.user) {
                    AppState.user = photoJson.data.user;
                }
            }

            localStorage.setItem('aqua_customer_user', JSON.stringify(AppState.user));
            updateAuthUI();
            renderProfileView();
            closeModal('editPersonalModal');
            closeModal('editProfileModal');
            showToast('Profile updated successfully!', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to update profile.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = '✓ Save Changes';
            }
        }
    }

    async function handleReportIssueSubmit(event) {
        if (event) event.preventDefault();
        const orderNum = document.getElementById('reportIssueOrderNum')?.value.trim() || '';
        const category = document.getElementById('reportIssueCategory')?.value || 'Delivery';
        const desc = document.getElementById('reportIssueDesc')?.value.trim() || '';

        if (!desc) {
            showToast('Please describe the issue with your order.', 'error');
            return;
        }

        try {
            await apiRequest('/customer/complaints', 'POST', {
                order_number: orderNum,
                category: category,
                description: desc
            }, true);
            showToast('Your issue report has been submitted. Our station team will review it.', 'success');
            closeModal('reportIssueModal');
        } catch (err) {
            showToast(err.message || 'Failed to submit report.', 'error');
        }
    }


    async function submitEditProfileForm(formData) {
        try {
            const res = await apiRequest('/auth/profile', 'POST', formData, true);
            if (res?.data?.user) {
                AppState.user = res.data.user;
                localStorage.setItem('aqua_customer_user', JSON.stringify(AppState.user));
                updateAuthUI();
                renderProfileView();
                showToast('Profile updated successfully!', 'success');
                closeModal('editProfileModal');
            }
        } catch (err) {
            showToast(err.message || 'Failed to update profile.', 'error');
        }
    }

    async function submitChangePasswordForm(currentPassword, newPassword, confirmPassword) {
        try {
            await apiRequest('/auth/change-password', 'POST', {
                current_password: currentPassword,
                password: newPassword,
                password_confirmation: confirmPassword
            }, true);
            showToast('Password changed successfully!', 'success');
            closeModal('changePasswordModal');
        } catch (err) {
            showToast(err.message || 'Failed to change password.', 'error');
        }
    }

    let resetPasswordToken = null;
    let resetPasswordEmail = '';

    async function submitForgotOtp(email) {
        try {
            resetPasswordEmail = email;
            await apiRequest('/auth/password/otp', 'POST', { email }, false);
            showToast('Verification OTP sent to your email.', 'success');
            const step1 = document.getElementById('forgotStep1');
            const step2 = document.getElementById('forgotStep2');
            if (step1) step1.style.display = 'none';
            if (step2) step2.style.display = 'block';
        } catch (err) {
            showToast(err.message || 'Failed to send OTP code.', 'error');
        }
    }

    async function submitVerifyOtp(otp) {
        try {
            const res = await apiRequest('/auth/password/verify-otp', 'POST', {
                email: resetPasswordEmail,
                otp: otp
            }, false);
            if (res?.data?.reset_token) {
                resetPasswordToken = res.data.reset_token;
                showToast('Code verified! Enter your new password.', 'success');
                const step2 = document.getElementById('forgotStep2');
                const step3 = document.getElementById('forgotStep3');
                if (step2) step2.style.display = 'none';
                if (step3) step3.style.display = 'block';
            }
        } catch (err) {
            showToast(err.message || 'Invalid or expired OTP code.', 'error');
        }
    }

    async function submitResetPassword(password, confirmPassword) {
        try {
            await apiRequest('/auth/password/reset', 'POST', {
                email: resetPasswordEmail,
                reset_token: resetPasswordToken,
                password: password,
                password_confirmation: confirmPassword
            }, false);
            showToast('Password reset successfully! Please log in with your new password.', 'success');
            closeModal('forgotPasswordModal');
            showLoginModal();
        } catch (err) {
            showToast(err.message || 'Failed to reset password.', 'error');
        }
    }

    // =========================================================================
    // 18. SUPABASE REALTIME SUBSCRIPTION
    // =========================================================================
    function initRealtime() {
        const config = window.aquaRealtimeConfig || {};
        if (!config.url || !config.anon_key || !window.supabase?.createClient) {
            startHeartbeatPolling();
            return;
        }

        try {
            const client = window.supabase.createClient(config.url, config.anon_key);
            client.channel('aqua-customer-web-signals')
                .on('postgres_changes', {
                    event: 'INSERT',
                    schema: 'public',
                    table: 'realtime_events'
                }, (payload) => {
                    const signal = payload.new || {};
                    handleRealtimeSignal(signal);
                })
                .subscribe((status) => {
                    AppState.realtimeConnected = status === 'SUBSCRIBED';
                    if (!AppState.realtimeConnected) {
                        startHeartbeatPolling();
                    }
                });
        } catch (e) {
            console.warn('[Realtime] Failed to subscribe to Supabase Realtime; using polling fallback.', e);
            startHeartbeatPolling();
        }
    }

    function handleRealtimeSignal(signal) {
        const type = signal.entity_type;
        console.log('[Realtime Signal Received]', signal);

        if (type === 'order') {
            loadOrders();
            if (AppState.currentView === 'order-detail' && AppState.orderDetail?.id === signal.entity_id) {
                loadOrderDetail(signal.entity_id);
            }
            showToast(`Order update received!`, 'info');
        } else if (type === 'notification') {
            loadNotifications();
        } else if (type === 'payment') {
            loadPayments();
            if (AppState.currentView === 'order-detail') {
                loadOrderDetail(AppState.orderDetail?.id);
            }
        } else if (type === 'chat') {
            loadChat();
        }
    }

    let heartbeatTimer = null;
    function startHeartbeatPolling() {
        if (heartbeatTimer) return;
        heartbeatTimer = setInterval(() => {
            if (AppState.token && !document.hidden) {
                if (AppState.currentView === 'order-detail' && AppState.orderDetail) {
                    loadOrderDetail(AppState.orderDetail.id);
                } else if (AppState.currentView === 'orders') {
                    loadOrders();
                } else if (AppState.currentView === 'chat') {
                    loadChat();
                }
            }
        }, 15000);
    }

    // =========================================================================
    // 19. MODALS SYSTEM
    // =========================================================================
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    function showLoginModal(msg = '') {
        const alertBox = document.getElementById('loginModalAlert');
        if (alertBox) {
            alertBox.textContent = msg;
            alertBox.style.display = msg ? 'block' : 'none';
        }
        openModal('loginModal');
    }

    // Close on backdrop click
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Close user dropdown if click outside
        const menu = document.getElementById('userProfileDropdown');
        const btn = document.getElementById('userProfileMenuBtn');
        if (menu && menu.classList.contains('show') && !btn.contains(e.target)) {
            menu.classList.remove('show');
        }
    });

    // =========================================================================
    // 20. UTILITIES
    // =========================================================================
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDateTime(raw) {
        if (!raw) return 'Recent';
        try {
            const dt = new Date(raw);
            return dt.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        } catch (_) {
            return raw;
        }
    }

    function formatTimeOnly(raw) {
        if (!raw) return '';
        try {
            const dt = new Date(raw);
            return dt.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        } catch (_) {
            return '';
        }
    }

    function getInitials(name) {
        if (!name) return 'AC';
        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    // =========================================================================
    // 21. GLOBAL REGISTRATION & INIT
    // =========================================================================
    window.aquaApp = {
        adjustLocalQty,
        addConfiguredProductToCart,
        toggleMobileMenu,
        toggleNotifDropdown,
        openCheckoutModal,
        updateCheckoutMethod,
        toggleEditAddressForm,
        cancelEditAddress,
        saveEditedAddress,
        handleCheckoutSubmit,
        submitCheckout: handleCheckoutSubmit,
        handleSaveProfile,
        handleReportIssueSubmit,
        openReportIssueModal,
        navigateTo,
        toggleSidebar,
        togglePasswordVisibility,
        enlargeQr,
        trackByOrderNumber,
        adjustDashQty,
        addDashToCart,
        renderHomeQuickOrder,
        filterCatalogProducts,
        sortCatalogProducts,
        handleGlobalSearch,
        handleLogin,
        handleRegister,
        handleLogout,
        confirmLogout,
        executeLogout,
        addToCart,
        updateCartItemQuantity,
        removeCartItem,
        clearCart,
        setCatalogCategory,
        startCheckout,
        goToCheckoutStep,
        setCheckoutDeliveryMethod,
        selectCheckoutAddress,
        selectCheckoutPaymentMethod,
        selectCheckoutProvider,
        adjustExpectedGallons,
        updateCheckoutNotes,
        placeOrder,
        setOrderFilter,
        loadOrderDetail,
        loadTrackingView,
        loadGallonsView,
        cancelOrder,
        toggleOrderDeliveryMethod,
        payOnlineNow,
        openAddAddressModal,
        saveAddressForm,
        setDefaultAddress,
        deleteAddress,
        setPaymentFilter,
        markNotificationRead,
        markAllNotificationsRead,
        openSubmitComplaintModal,
        submitComplaintForm,
        openComplaintDetailModal,
        toggleFaq,
        filterFaqs,
        sendChatMessage,
        sendFaqQuestion,
        openEditProfileModal,
        submitEditProfileForm,
        openChangePasswordModal: () => openModal('changePasswordModal'),
        submitChangePasswordForm,
        openTutorialModal: () => openModal('tutorialModal'),
        openModal,
        closeModal
    };

    // DOM Ready Initialization
    document.addEventListener('DOMContentLoaded', () => {
        // Photo preview handler for Edit Profile modal
        const photoInputEl = document.getElementById('profilePhotoInput');
        if (photoInputEl) {
            photoInputEl.addEventListener('change', () => {
                if (photoInputEl.files && photoInputEl.files[0]) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const avatarEl = document.getElementById('editModalAvatar');
                        if (avatarEl) {
                            avatarEl.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">`;
                        }
                    };
                    reader.readAsDataURL(photoInputEl.files[0]);
                }
            });
        }

        // Authenticated Session Check
        if (window.aquaCustomerToken) {
            AppState.token = window.aquaCustomerToken;
            localStorage.setItem('aqua_customer_token', window.aquaCustomerToken);
        }
        if (window.aquaAuthenticatedUser) {
            AppState.user = window.aquaAuthenticatedUser;
            localStorage.setItem('aqua_customer_user', JSON.stringify(window.aquaAuthenticatedUser));
        }

        if (!AppState.token && !window.aquaCustomerToken) {
            window.location.href = '/admin/login';
            return;
        }

        updateAuthUI();

        // Initial view resolution based on actual current URL path
        let initialView = 'home';
        const path = window.location.pathname.replace(/^\/customer\/?/, '');
        const parts = path.split('/').filter(Boolean);
        if (parts.length > 0) {
            initialView = parts[0];
        } else if (window.aquaInitialView) {
            initialView = window.aquaInitialView;
        }
        if (['login', 'register', 'forgot_password'].includes(initialView)) {
            initialView = 'home';
        }
        if (initialView === 'order_detail' || initialView === 'order-detail') {
            initialView = 'tracking';
        }
        const initialId = parts[1] || window.aquaInitialId || null;

        navigateTo(initialView, initialId, false);

        // Preload catalog
        loadCatalog();

        // Start realtime listener
        initRealtime();

        // Setup User Menu Dropdown Toggle & Auto-Dismissal
        const profileBtn = document.getElementById('userProfileMenuBtn');
        const profileDropdown = document.getElementById('userProfileDropdown');
        if (profileBtn && profileDropdown) {
            profileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });

            // Close when clicking any menu option inside dropdown
            profileDropdown.querySelectorAll('.dropdown-link').forEach(link => {
                link.addEventListener('click', () => {
                    profileDropdown.classList.remove('show');
                });
            });
        }

        // Close on clicking outside
        document.addEventListener('click', (e) => {
            if (profileDropdown && profileDropdown.classList.contains('show')) {
                if (!profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                }
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && profileDropdown && profileDropdown.classList.contains('show')) {
                profileDropdown.classList.remove('show');
            }
        });
    });

})();
