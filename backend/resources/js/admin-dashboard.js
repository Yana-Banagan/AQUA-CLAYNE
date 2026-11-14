/**
 * AQUA CLAYNE Water Refilling Station Management System
 * Admin Dashboard & Reusable Administration Layout Scripts
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

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
    // 2. MOBILE OFF-CANVAS SIDEBAR DRAWER
    // =========================================================================
    function initMobileSidebar() {
        var menuBtn = document.getElementById('menuButton');
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebarBackdrop');

        if (!menuBtn || !sidebar || !backdrop) return;

        function openSidebar() {
            sidebar.classList.add('open');
            backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
            menuBtn.setAttribute('aria-expanded', 'true');
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            backdrop.classList.remove('active');
            document.body.style.overflow = '';
            menuBtn.setAttribute('aria-expanded', 'false');
        }

        menuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        backdrop.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        // Close sidebar when resizing to desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth > 992 && sidebar.classList.contains('open')) {
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
                            backgroundColor: '#0A2540',
                            titleColor: '#FFFFFF',
                            bodyColor: '#D1E5F7',
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
                                color: '#64748B',
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
                                color: '#64748B',
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                }
                            },
                            grid: {
                                color: '#E2EEF8'
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
                            borderColor: '#FFFFFF',
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
                                color: '#334155'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0A2540',
                            titleColor: '#FFFFFF',
                            bodyColor: '#D1E5F7',
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
    // 5. LATEST ORDERS TABLE: SEARCH & STATUS FILTERING
    // =========================================================================
    function initTableFilters() {
        var searchInput = document.getElementById('ordersSearchInput');
        var filterTabs = document.querySelectorAll('.filter-tab');
        var table = document.getElementById('latestOrdersTable');

        if (!table) return;

        var rows = table.querySelectorAll('tbody tr[data-status]');
        var activeFilter = 'all';
        var searchQuery = '';

        function applyFilters() {
            var visibleCount = 0;

            rows.forEach(function (row) {
                var rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
                var rowText = row.textContent.toLowerCase();

                // Status Matching
                var matchesStatus = false;
                if (activeFilter === 'all') {
                    matchesStatus = true;
                } else if (activeFilter === 'pending') {
                    matchesStatus = (rowStatus === 'pending');
                } else if (activeFilter === 'refilling') {
                    matchesStatus = (rowStatus === 'refilling');
                } else if (activeFilter === 'out_for_delivery') {
                    matchesStatus = (
                        rowStatus === 'out_for_delivery' ||
                        rowStatus === 'assigned_for_delivery' ||
                        rowStatus === 'refilled_ready_for_delivery'
                    );
                } else if (activeFilter === 'completed') {
                    matchesStatus = (rowStatus === 'completed');
                } else {
                    matchesStatus = (rowStatus === activeFilter);
                }

                // Search Matching
                var matchesSearch = true;
                if (searchQuery.trim() !== '') {
                    matchesSearch = rowText.indexOf(searchQuery) !== -1;
                }

                if (matchesStatus && matchesSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Handle dynamic empty state if all rows are hidden
            var existingEmpty = table.querySelector('.dynamic-empty-row');
            if (visibleCount === 0 && rows.length > 0) {
                if (!existingEmpty) {
                    var tbody = table.querySelector('tbody');
                    var tr = document.createElement('tr');
                    tr.className = 'dynamic-empty-row';
                    tr.innerHTML = '<td colspan="9" class="table-empty-row">No orders match the selected search or filter criteria.</td>';
                    tbody.appendChild(tr);
                }
            } else if (existingEmpty) {
                existingEmpty.remove();
            }
        }

        // Tab click listener
        filterTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                filterTabs.forEach(function (t) { t.classList.remove('active'); });
                this.classList.add('active');
                activeFilter = this.getAttribute('data-filter') || 'all';
                applyFilters();
            });
        });

        // Search input listener with 200ms debounce
        var searchDebounceTimer = null;
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var val = this.value.toLowerCase().trim();
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(function () {
                    searchQuery = val;
                    applyFilters();
                }, 200);
            });
        }
    }

    initTableFilters();
});
