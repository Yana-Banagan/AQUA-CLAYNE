/**
 * AQUA CLAYNE Water Refilling Station Management System
 * Admin Dashboard & Reusable Administration Layout Scripts
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // Transient success/error banners stay visible for five seconds, then fade.
    document.querySelectorAll('.flash-message[role="status"], .flash-message[role="alert"]').forEach(function (notice) {
        window.setTimeout(function () {
            notice.style.transition = 'opacity 180ms ease, transform 180ms ease';
            notice.style.opacity = '0';
            notice.style.transform = 'translateY(-4px)';
            window.setTimeout(function () { notice.remove(); }, 200);
        }, 5000);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-disable-on-submit]')) return;
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = 'true';
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
            if (button.dataset.loadingText) button.textContent = button.dataset.loadingText;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    });

    // =========================================================================
    // 0. DARK / LIGHT MODE THEME TOGGLE
    //    - Persists choice in localStorage under 'aquaclayneTheme'
    //    - data-theme="dark" is set on <html> element
    //    - Inline <script> in <head> reads localStorage and applies it before
    //      paint, preventing flash of wrong theme.
    // =========================================================================
    function initThemeToggle() {
        var toggleBtn = document.getElementById('themeToggleBtn');
        if (!toggleBtn) return;

        var html = document.documentElement;

        function getCurrentTheme() {
            return html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        }

        function applyTheme(theme) {
            if (theme === 'dark') {
                html.setAttribute('data-theme', 'dark');
            } else {
                html.removeAttribute('data-theme');
            }
            localStorage.setItem('aquaclayneTheme', theme);
            refreshDashboardCharts(theme);
            toggleBtn.setAttribute('title', theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode');
            toggleBtn.setAttribute('aria-label', theme === 'dark' ? 'Switch to Light Mode ☀' : 'Switch to Dark Mode 🌙');
        }

        toggleBtn.addEventListener('click', function () {
            var next = getCurrentTheme() === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });

        // Set correct initial title
        applyTheme(getCurrentTheme());
    }

    initThemeToggle();

    function refreshDashboardCharts(theme) {
        if (typeof Chart === 'undefined' || !Chart.instances) return;

        var isDark = theme === 'dark';
        var textColor = isDark ? '#CBD5E1' : '#334155';
        var tickColor = isDark ? '#94A3B8' : '#64748B';
        var gridColor = isDark ? '#2D3F55' : '#E2EEF8';
        var cardColor = isDark ? '#13243A' : '#FFFFFF';

        Object.keys(Chart.instances).forEach(function (key) {
            var chart = Chart.instances[key];
            if (!chart) return;

            // These two dashboard charts sit on permanently blue operational
            // panels, so their chart colours stay fixed in either UI theme.
            var canvas = chart.canvas;
            if (canvas && canvas.closest('.order-overview-card, .status-overview-card')) return;

            if (chart.options.plugins && chart.options.plugins.legend) chart.options.plugins.legend.labels.color = textColor;
            if (chart.options.scales) {
                Object.keys(chart.options.scales).forEach(function (axis) {
                    var scale = chart.options.scales[axis];
                    if (scale.ticks) scale.ticks.color = tickColor;
                    if (scale.grid) scale.grid.color = gridColor;
                });
            }
            if (chart.config.type === 'doughnut') chart.data.datasets.forEach(function (dataset) { dataset.borderColor = cardColor; });
            chart.update();
        });
    }

    // =========================================================================
    // 1. LIVE PHILIPPINE STANDARD TIME (PST) CLOCK
    // =========================================================================
    function initLiveClock() {
        var dateEl = document.getElementById('currentDate');
        var timeEl = document.getElementById('currentTime');

        if (!dateEl && !timeEl) return;

        function updateClock() {
            var now = new Date();

            if (dateEl) {
                var options = {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    timeZone: 'Asia/Manila'
                };
                dateEl.textContent = now.toLocaleDateString('en-PH', options);
            }

            if (timeEl) {
                var timeOptions = {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                    timeZone: 'Asia/Manila'
                };
                timeEl.textContent = now.toLocaleTimeString('en-PH', timeOptions);
            }
        }

        updateClock();
        setInterval(updateClock, 1000);
    }

    initLiveClock();

    // =========================================================================
    // 2. RESPONSIVE SIDEBAR NAVIGATION (DESKTOP / TABLET COLLAPSIBLE / MOBILE DRAWER)
    // =========================================================================
    function initMobileSidebar() {
        var menuBtn = document.getElementById('menuButton');
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebarBackdrop');

        if (!menuBtn || !sidebar || !backdrop) return;

        function isTablet() {
            return window.innerWidth >= 768 && window.innerWidth < 1024;
        }

        function openSidebar() {
            if (isTablet()) {
                document.body.classList.add('sidebar-expanded');
                sidebar.classList.add('open');
                backdrop.classList.add('active');
                menuBtn.setAttribute('aria-expanded', 'true');
            } else {
                sidebar.classList.add('open');
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
                menuBtn.setAttribute('aria-expanded', 'true');
            }
        }

        function closeSidebar() {
            document.body.classList.remove('sidebar-expanded');
            sidebar.classList.remove('open');
            backdrop.classList.remove('active');
            document.body.style.overflow = '';
            menuBtn.setAttribute('aria-expanded', 'false');
        }

        menuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open') || document.body.classList.contains('sidebar-expanded')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        backdrop.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && (sidebar.classList.contains('open') || document.body.classList.contains('sidebar-expanded'))) {
                closeSidebar();
            }
        });

        // Close sidebar drawer after selecting a navigation item on tablet/mobile
        sidebar.querySelectorAll('.sidebar-nav .nav-link, .sidebar-footer a, .sidebar-footer button').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 1024) {
                    closeSidebar();
                }
            });
        });

        // Reset sidebar state on breakpoint transitions
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) {
                closeSidebar();
            }
        });
    }

    initMobileSidebar();

    // =========================================================================
    // 3. TOPBAR DROPDOWN MENUS (NOTIFICATIONS & PROFILE)
    // =========================================================================
    function initDropdowns() {
        var notifBtn = document.getElementById('notifButton');
        var notifDropdown = document.getElementById('notifDropdown');
        var profileBtn = document.getElementById('profileButton');
        var profileDropdown = document.getElementById('profileDropdown');

        function closeAllDropdowns() {
            if (notifDropdown) notifDropdown.classList.remove('show');
            if (profileDropdown) profileDropdown.classList.remove('show');
            if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
            if (profileBtn) profileBtn.setAttribute('aria-expanded', 'false');
        }

        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = notifDropdown.classList.contains('show');
                closeAllDropdowns();
                if (!isOpen) {
                    notifDropdown.classList.add('show');
                    notifBtn.setAttribute('aria-expanded', 'true');
                }
            });
        }

        if (profileBtn && profileDropdown) {
            profileBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = profileDropdown.classList.contains('show');
                closeAllDropdowns();
                if (!isOpen) {
                    profileDropdown.classList.add('show');
                    profileBtn.setAttribute('aria-expanded', 'true');
                }
            });
        }

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            if (notifDropdown && notifDropdown.contains(e.target)) return;
            if (profileDropdown && profileDropdown.contains(e.target)) return;
            closeAllDropdowns();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });
    }

    initDropdowns();

    // =========================================================================
    // 4. CHART.JS VISUALIZATIONS
    // =========================================================================
    function initCharts() {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js library not loaded yet.');
            return;
        }

        var data = window.AQUA_CLAYNE_DASHBOARD_DATA || {};

        // Both chart panels use a fixed blue operational surface. Their
        // contents deliberately do not inherit the page's light/dark theme.
        var tickColor = '#94A3B8';
        var gridColor = '#2D3F55';
        var tooltipBg = '#1E293B';
        var tooltipBody = '#CBD5E1';
        var legendColor = '#CBD5E1';

        // 4.1 Order Overview Chart (Last 7 Days Line Chart)
        var overviewCanvas = document.getElementById('orderOverviewChart');
        if (overviewCanvas) {
            var ctxOverview = overviewCanvas.getContext('2d');

            var labels = data.chartLabels && data.chartLabels.length ? data.chartLabels : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            var completedDaily = data.completedDaily || [0, 0, 0, 0, 0, 0, 0];
            var inProgressDaily = data.inProgressDaily || [0, 0, 0, 0, 0, 0, 0];
            var pendingDaily = data.pendingDaily || [0, 0, 0, 0, 0, 0, 0];

            new Chart(ctxOverview, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Completed',
                            data: completedDaily,
                            borderColor: '#16A34A',
                            backgroundColor: 'rgba(22, 163, 74, 0.08)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#16A34A'
                        },
                        {
                            label: 'In Progress',
                            data: inProgressDaily,
                            borderColor: '#0EA5E9',
                            backgroundColor: 'rgba(14, 165, 233, 0.06)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#0EA5E9'
                        },
                        {
                            label: 'Pending',
                            data: pendingDaily,
                            borderColor: '#F59E0B',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.35,
                            pointRadius: 2.5,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#F59E0B'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: tooltipBg,
                            titleColor: '#FFFFFF',
                            bodyColor: tooltipBody,
                            padding: 10,
                            cornerRadius: 8,
                            boxPadding: 4,
                            usePointStyle: true
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: tickColor,
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: tickColor,
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                }
                            },
                            grid: {
                                color: gridColor
                            }
                        }
                    }
                }
            });
        }

        // 4.2 Orders by Status (Doughnut Chart)
        var statusCanvas = document.getElementById('ordersStatusChart');
        if (statusCanvas) {
            var ctxStatus = statusCanvas.getContext('2d');

            var rawLabels = data.statusLabels || [];
            var rawValues = data.statusValues || [];

            // Humanize labels for the chart tooltip/legend
            var formattedLabels = rawLabels.map(function (item) {
                return item.replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
            });

            // If empty, supply default zeros
            if (!rawValues.length || rawValues.every(function (v) { return v === 0; })) {
                formattedLabels = ['No Active Orders'];
                rawValues = [1];
                var colorPalette = ['#E2E8F0'];
            } else {
                var colorPalette = data.statusColors && data.statusColors.length ? data.statusColors : [
                    '#F59E0B', // Pending
                    '#38BDF8', // Confirmed / Intake
                    '#0284C7', // Refilling
                    '#8B5CF6', // Ready / Out for Delivery
                    '#10B981', // Completed
                    '#EF4444'  // Cancelled / Failed
                ];
            }

            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: formattedLabels,
                    datasets: [
                        {
                            data: rawValues,
                            backgroundColor: colorPalette,
                            borderWidth: 2,
                            borderColor: '#13243A',
                            hoverOffset: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: {
                            display: false,
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                usePointStyle: true,
                                padding: 14,
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                },
                                color: legendColor
                            }
                        },
                        tooltip: {
                            backgroundColor: tooltipBg,
                            titleColor: '#FFFFFF',
                            bodyColor: tooltipBody,
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: function (context) {
                                    var label = context.label || '';
                                    var val = context.parsed || 0;
                                    return ' ' + label + ': ' + val + ' orders';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    if (window.requestIdleCallback) {
        requestIdleCallback(initCharts);
    } else {
        setTimeout(initCharts, 50);
    }

    // =========================================================================
    // 5. DASHBOARD ORDERS TABLE — no client-side filter/search in the compact
    //    side-by-side layout. Filtering is handled by the full Orders page.
    //    This function only handles the dynamic empty-row colspan correction.
    // =========================================================================
    function initTableFilters() {
        var table = document.getElementById('latestOrdersTable');
        if (!table) return;

        // The compact dashboard table has 6 columns.
        // Correct the empty-row colspan if the server rendered the fallback row.
        var emptyRow = table.querySelector('.table-empty-row');
        if (emptyRow) {
            emptyRow.setAttribute('colspan', '6');
        }
    }

    initTableFilters();
});
