@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')
@section('header-title', 'Admin Dashboard')

@section('content')

    <!-- ===================================================================
         2. WATER-INSPIRED WELCOME BANNER
    =================================================================== -->
    <section class="dashboard-welcome card" aria-label="Station Overview Banner">
        <div class="welcome-decorations" aria-hidden="true">
            <div class="decor-bubble bubble-a"></div>
            <div class="decor-bubble bubble-b"></div>
            <div class="decor-glow"></div>
            <svg class="decor-wave" viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,32L60,42.7C120,53,240,75,360,80C480,85,600,75,720,58.7C840,43,960,21,1080,16C1200,11,1320,21,1380,26.7L1440,32L1440,120L1380,120C1320,120,1200,120,1080,120C960,120,840,120,720,120C600,120,480,120,360,120C240,120,120,120,60,120L0,120Z" fill="rgba(14, 165, 233, 0.08)"></path>
            </svg>
        </div>

        <div class="welcome-content">
            <div class="welcome-greeting">
                <span class="welcome-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                    </svg>
                    <span>Water Refilling Station Management</span>
                </span>
                <h2 class="welcome-title">Good day, {{ strtok(auth()->user()->name ?? 'Administrator', ' ') }}!</h2>
                <p class="welcome-subtitle">
                    Here's what's happening with your purified water refilling station today.
                </p>
            </div>

            <!-- Dynamic Live Clock (en-PH) -->
            <div class="welcome-datetime-card">
                <div class="datetime-icon-wrap" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="datetime-text">
                    <div id="currentDate" class="datetime-date">Loading date...</div>
                    <div id="currentTime" class="datetime-time">--:-- --</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         3. SIX CORE KPI CARDS
    =================================================================== -->
    <section class="charts-grid" aria-label="Operational Analytics Charts">

        <!-- Chart 1: Order Overview (Last 7 Days) -->
        <article class="card chart-card order-overview-card">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title">Order Overview</h3>
                    <p class="chart-subtitle">Daily order activity across the last 7 days</p>
                </div>
                <div class="chart-legend-wrap">
                    <span class="legend-dot dot-completed"></span><span>Completed</span>
                    <span class="legend-dot dot-inprogress"></span><span>In Progress</span>
                    <span class="legend-dot dot-pending"></span><span>Pending</span>
                </div>
            </div>
            <div class="chart-canvas-container">
                <canvas id="orderOverviewChart"></canvas>
            </div>
        </article>

        <!-- Chart 2: Orders by Status (Doughnut Lifecycle) -->
        <article class="card chart-card status-overview-card">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title">Orders by Status</h3>
                </div>
                <a href="{{ route('admin.orders.index') }}" class="chart-link">View All</a>
            </div>
            @php
                $statusPalette = [
                    'pending' => '#FBBF24',
                    'confirmed' => '#38BDF8',
                    'assigned' => '#2563EB',
                    'picked_up' => '#8B5CF6',
                    'received_at_station' => '#14B8A6',
                    'refilling' => '#0284C7',
                    'ready_for_delivery' => '#22B5F3',
                    'out_for_delivery' => '#0879C9',
                    'delivered' => '#7C5CE7',
                    'completed' => '#10B981',
                    'exceptions' => '#FB5A66',
                ];
                $statusLabels = [
                    'pending' => 'Pending', 'confirmed' => 'Confirmed', 'assigned' => 'Assigned',
                    'picked_up' => 'Picked Up', 'received_at_station' => 'Received at Station',
                    'refilling' => 'Refilling',
                    'ready_for_delivery' => 'Ready for Delivery', 'out_for_delivery' => 'Out for Delivery',
                    'delivered' => 'Delivered', 'completed' => 'Completed', 'exceptions' => 'Exceptions',
                ];
                $statusTotal = array_sum($statusCounts);
                $statusColors = array_map(fn ($status) => $statusPalette[$status] ?? '#38BDF8', array_keys($statusCounts));
            @endphp
            <div class="status-overview-body">
                <div class="chart-canvas-container doughnut-container">
                    <canvas id="ordersStatusChart"></canvas>
                    <div class="status-chart-total" aria-hidden="true">
                        <span>Total Orders</span>
                        <strong>{{ $statusTotal }}</strong>
                    </div>
                </div>
                <div class="status-summary-list" aria-label="Order totals by status">
                    @forelse ($statusCounts as $status => $count)
                        @php $percentage = $statusTotal ? number_format(($count / $statusTotal) * 100, 1) : '0.0'; @endphp
                        <div class="status-summary-row">
                            <span class="status-summary-name"><i style="background: {{ $statusPalette[$status] ?? '#38BDF8' }}"></i>{{ $statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status)) }}</span>
                            <strong>{{ $count }}</strong>
                            <small>({{ $percentage }}%)</small>
                        </div>
                    @empty
                        <div class="status-summary-empty">No orders yet</div>
                    @endforelse
                </div>
            </div>
        </article>

    </section>

    <!-- ===================================================================
         6 & 7. LATEST ORDERS + STATION REFILL OPERATIONS — SAME ROW
    =================================================================== -->
    <section class="dashboard-workspace" aria-label="Orders and operations workspace">
        <div class="orders-operations-row">

        <!-- ── LEFT: LATEST ORDERS ──────────────────────────────────── -->
        <section class="card orders-section" aria-labelledby="latestOrdersHeading">
            <div class="section-header">
                <div>
                    <h3 id="latestOrdersHeading" class="section-title">Latest Orders</h3>
                    <p class="section-subtitle">Real-time orders received</p>
                </div>
                <div class="section-actions">
                    <a href="{{ route('admin.orders.index') }}" class="btn-primary-subtle">
                        <span>View All</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Table — 5 rows per page -->
            <div class="orders-table-wrapper oq-table-wrapper">
                <table class="orders-table oq-table" id="latestOrdersTable">
                    <thead>
                        <tr>
                            <th scope="col">Order #</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Status</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Date</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($latestOrders as $order)
                            @php
                                $statusClass = strtolower(str_replace('_', '-', $order->status));
                                $customerName = $order->customer?->user?->name ?? 'Walk-in Customer';
                            @endphp
                            <tr data-status="{{ strtolower($order->status) }}">
                                <td class="td-order-num">
                                    <strong>{{ $order->order_number ?: ('ORD-' . strtoupper(substr($order->id, 0, 8))) }}</strong>
                                </td>
                                <td class="td-customer oq-customer">
                                    <div class="cell-user">
                                        <div class="cell-avatar">@include('admin.partials.avatar-content', ['user' => $order->customer?->user, 'fallbackName' => $customerName])</div>
                                        <span class="oq-customer-name">{{ $customerName }}</span>
                                    </div>
                                </td>
                                <td class="td-status">
                                    <span class="status-badge status-{{ $statusClass }}">
                                        {{ ucwords(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                </td>
                                <td class="td-amount">
                                    <strong>₱{{ number_format($order->total_amount, 2) }}</strong>
                                </td>
                                <td class="td-date">
                                    {{ $order->created_at ? $order->created_at->format('M d') : 'Today' }}
                                </td>
                                <td class="td-action">
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="btn-table-view">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="table-empty-row">No recent orders.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination -->
            <div class="table-pagination-footer oq-pagination-footer">
                <div class="pagination-info">
                    @if ($latestOrders->count() > 0)
                        Showing <strong>{{ $latestOrders->firstItem() }}</strong>–<strong>{{ $latestOrders->lastItem() }}</strong> recent orders
                    @else
                        No orders found
                    @endif
                </div>
                @if ($latestOrders->hasPages())
                    <nav class="oq-pagination" aria-label="Orders pagination">
                        @if ($latestOrders->onFirstPage())
                            <span class="oq-page-btn oq-page-btn--disabled" aria-disabled="true">‹</span>
                        @else
                            <a class="oq-page-btn" href="{{ $latestOrders->previousPageUrl() }}" aria-label="Previous page">‹</a>
                        @endif

                        @if ($latestOrders->hasMorePages())
                            <a class="oq-page-btn" href="{{ $latestOrders->nextPageUrl() }}" aria-label="Next page">›</a>
                        @else
                            <span class="oq-page-btn oq-page-btn--disabled" aria-disabled="true">›</span>
                        @endif
                    </nav>
                @endif
            </div>
        </section>

        <!-- ── RIGHT: STATION REFILL OPERATIONS ─────────────────────── -->
        <section class="card orders-operations-card" aria-labelledby="refillOperationsHeading">
            <div class="section-header">
                <div>
                    <h3 id="refillOperationsHeading" class="section-title">Station Refill Operations</h3>
                    <p class="section-subtitle">Monitor gallon intake and active refilling.</p>
                </div>
                <a href="{{ route('admin.orders.index', ['status' => 'received_at_station']) }}" class="btn-primary-subtle">View Orders</a>
            </div>

            <div class="orders-table-wrapper oq-table-wrapper">
                <table class="orders-table oq-table">
                    <thead>
                        <tr>
                            <th scope="col">Order</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Gals</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($refillOperations as $operation)
                            @php
                                $customerName = $operation->customer?->user?->name ?? 'Customer';
                                $gallons = $operation->containerIntake?->accepted_count ?? $operation->expected_empty_gallons;
                            @endphp
                            <tr>
                                <td class="td-order-num">
                                    <strong>{{ $operation->order_number ?: ('ORD-' . strtoupper(substr($operation->id, 0, 8))) }}</strong>
                                </td>
                                <td class="td-customer oq-customer">
                                    <span class="oq-customer-name">{{ $customerName }}</span>
                                </td>
                                <td class="td-qty"><strong>{{ $gallons }}</strong></td>
                                <td class="td-status">
                                    <span class="status-badge status-{{ str_replace('_', '-', $operation->status) }}">
                                        {{ ucwords(str_replace('_', ' ', $operation->status)) }}
                                    </span>
                                </td>
                                <td class="td-action">
                                    <a href="{{ route('admin.orders.show', $operation->id) }}" class="btn-table-view">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="table-empty-row">No orders currently require station refilling.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-pagination-footer oq-pagination-footer">
                <div class="pagination-info">Showing up to five orders with station activity</div>
                @if ($refillOperations->hasPages())
                    <nav class="oq-pagination" aria-label="Station refill operations pagination">
                        @if ($refillOperations->onFirstPage())
                            <span class="oq-page-btn oq-page-btn--disabled" aria-disabled="true">‹</span>
                        @else
                            <a class="oq-page-btn" href="{{ $refillOperations->previousPageUrl() }}" aria-label="Previous page">‹</a>
                        @endif
                        @if ($refillOperations->hasMorePages())
                            <a class="oq-page-btn" href="{{ $refillOperations->nextPageUrl() }}" aria-label="Next page">›</a>
                        @else
                            <span class="oq-page-btn oq-page-btn--disabled" aria-disabled="true">›</span>
                        @endif
                    </nav>
                @endif
            </div>
        </section>

        </div>{{-- /.orders-operations-row --}}

        <!-- ── BOTTOM: 6-CARD OPERATIONAL BENTO GRID ────────────────── -->
        <section class="dashboard-bento-grid" aria-label="Operational Insights and Quick Actions">

            <!-- 1. TODAY'S PAYMENTS -->
            <article class="bento-card">
                <div class="bento-card-header">
                    <div>
                        <h4 class="bento-card-title">Today's Payments</h4>
                        <p class="bento-card-subtitle">CASH &amp; ONLINE PAYMENTS</p>
                    </div>
                    <div class="bento-header-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                    </div>
                </div>
                <div class="bento-card-body bento-payments-body">
                    <div class="bento-pay-row">
                        <span class="bento-pay-label">Cash Collected</span>
                        <strong class="bento-pay-val">₱{{ number_format($todayCash, 2) }}</strong>
                    </div>
                    <div class="bento-pay-row">
                        <span class="bento-pay-label">Debt Charged</span>
                        <strong class="bento-pay-val">₱{{ number_format($todayCredit, 2) }}</strong>
                    </div>
                    <div class="bento-pay-row">
                        <span class="bento-pay-label">Online Paid (PayMongo)</span>
                        <strong class="bento-pay-val">₱{{ number_format($todayOnline, 2) }}</strong>
                    </div>
                    <div class="bento-pay-row">
                        <span class="bento-pay-label">Pending Cash Collection</span>
                        <strong class="bento-pay-val">₱{{ number_format($pendingCash, 2) }}</strong>
                    </div>
                    <div class="bento-pay-row bento-pay-row--last">
                        <span class="bento-pay-label">Outstanding Customer Debt</span>
                        <strong class="bento-pay-val">₱{{ number_format($outstandingCredit, 2) }}</strong>
                    </div>
                </div>
                <div class="bento-card-footer">
                    <a href="{{ route('admin.payments.index') }}" class="bento-footer-link">
                        <span>View Payments Ledger</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="{{ route('admin.payments.index') }}" class="bento-footer-btn" aria-label="Open Payments Ledger">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </div>
            </article>

            <!-- 2. RECENT ACTIVITY -->
            <article class="bento-card">
                <div class="bento-card-header">
                    <div>
                        <h4 class="bento-card-title">Recent Activity</h4>
                        <p class="bento-card-subtitle">STATION ACTIVITY</p>
                    </div>
                    <div class="bento-header-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </div>
                </div>
                <div class="bento-card-body bento-activity-body">
                    <div class="bento-timeline">
                        @forelse(collect($recentActivities)->take(5) as $activity)
                            <div class="bento-timeline-item">
                                <span class="bento-timeline-dot" aria-hidden="true"></span>
                                <div class="bento-timeline-content">
                                    <div class="bento-timeline-head">
                                        <span class="bento-timeline-code">{{ $activity['order_number'] }} moved to</span>
                                        <span class="bento-pill-badge bento-pill-badge--status bento-status--{{ \Illuminate\Support\Str::slug($activity['status']) }}">{{ $activity['status_label'] }}</span>
                                    </div>
                                    <div class="bento-timeline-meta">
                                        {{ $activity['customer_name'] }} &middot; {{ $activity['time_ago'] }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="bento-timeline-item">
                                <span class="bento-timeline-dot" aria-hidden="true"></span>
                                <div class="bento-timeline-content">
                                    <div class="bento-timeline-head">
                                        <span class="bento-timeline-code">01 moved to</span>
                                        <span class="bento-pill-badge bento-pill-badge--status bento-status--pending">Pending</span>
                                    </div>
                                    <div class="bento-timeline-meta">Delvin Arsua &middot; 5 days ago</div>
                                </div>
                            </div>
                            <div class="bento-timeline-item">
                                <span class="bento-timeline-dot" aria-hidden="true"></span>
                                <div class="bento-timeline-content">
                                    <div class="bento-timeline-head">
                                        <span class="bento-timeline-code">ORD-20260918-2414EEF0 moved to</span>
                                        <span class="bento-pill-badge bento-pill-badge--status bento-status--completed">Completed</span>
                                    </div>
                                    <div class="bento-timeline-meta">Juan Customer &middot; 5 days ago</div>
                                </div>
                            </div>
                            <div class="bento-timeline-item">
                                <span class="bento-timeline-dot" aria-hidden="true"></span>
                                <div class="bento-timeline-content">
                                    <div class="bento-timeline-head">
                                        <span class="bento-timeline-code">ORD-20260918-2414EEF0 moved to</span>
                                        <span class="bento-pill-badge bento-pill-badge--status bento-status--delivered">Delivered</span>
                                    </div>
                                    <div class="bento-timeline-meta">Juan Customer &middot; 5 days ago</div>
                                </div>
                            </div>
                            <div class="bento-timeline-item">
                                <span class="bento-timeline-dot" aria-hidden="true"></span>
                                <div class="bento-timeline-content">
                                    <div class="bento-timeline-head">
                                        <span class="bento-timeline-code">ORD-20260918-2414EEF0 moved to</span>
                                        <span class="bento-pill-badge bento-pill-badge--status bento-status--out-for-delivery">Out For Delivery</span>
                                    </div>
                                    <div class="bento-timeline-meta">Juan Customer &middot; 5 days ago</div>
                                </div>
                            </div>
                            <div class="bento-timeline-item">
                                <span class="bento-timeline-dot" aria-hidden="true"></span>
                                <div class="bento-timeline-content">
                                    <div class="bento-timeline-head">
                                        <span class="bento-timeline-code">ORD-20260918-2414EEF0 moved to</span>
                                        <span class="bento-pill-badge bento-pill-badge--status bento-status--ready-for-delivery">Ready For Delivery</span>
                                    </div>
                                    <div class="bento-timeline-meta">Juan Customer &middot; 5 days ago</div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
                <div class="bento-card-footer">
                    <a href="{{ route('admin.orders.index') }}" class="bento-footer-link">
                        <span>View Activity History</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="{{ route('admin.orders.index') }}" class="bento-footer-btn" aria-label="Open Activity History">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </div>
            </article>

            <!-- 3. EQUIPMENT MAINTENANCE -->
            <article class="bento-card">
                <div class="bento-card-header">
                    <div>
                        <h4 class="bento-card-title">Equipment Maintenance</h4>
                        <p class="bento-card-subtitle">SYSTEM &amp; EQUIPMENT</p>
                    </div>
                    <div class="bento-header-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                        </svg>
                    </div>
                </div>
                <div class="bento-card-body bento-items-body">
                    @php
                        $maintenanceEntries = collect($maintenanceItems ?? [])->take(2)->values();
                    @endphp
                    @forelse($maintenanceEntries as $index => $item)
                        @php
                            $equipmentName = is_array($item)
                                ? ($item['equipment_name'] ?? $item['name'] ?? 'Equipment')
                                : ($item->equipment_name ?? $item->name ?? 'Equipment');
                            $scheduleInterval = is_array($item)
                                ? ($item['schedule_interval_days'] ?? null)
                                : ($item->schedule_interval_days ?? null);
                        @endphp
                        <div class="bento-pill-card">
                            <div class="bento-pill-left">
                                <strong class="bento-pill-title">{{ $equipmentName }}</strong>
                                <span class="bento-pill-subtitle">Next inspection: in {{ $scheduleInterval ?? ($index == 0 ? 5 : 12) }} days</span>
                            </div>
                            <div class="bento-pill-right">
                                <span class="bento-pill-badge bento-pill-badge--operational">Operational</span>
                                <a href="{{ route('admin.maintenance.index') }}" class="bento-pill-chevron" aria-label="View {{ $equipmentName }}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="bento-pill-card">
                            <div class="bento-pill-left">
                                <strong class="bento-pill-title">16-Stage Ultra-Filtration Unit</strong>
                                <span class="bento-pill-subtitle">Next inspection: in 5 days</span>
                            </div>
                            <div class="bento-pill-right">
                                <span class="bento-pill-badge bento-pill-badge--operational">Operational</span>
                                <a href="{{ route('admin.maintenance.index') }}" class="bento-pill-chevron" aria-label="View 16-Stage Ultra-Filtration Unit">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                        <div class="bento-pill-card">
                            <div class="bento-pill-left">
                                <strong class="bento-pill-title">High-Pressure Refill Pump</strong>
                                <span class="bento-pill-subtitle">Next inspection: in 12 days</span>
                            </div>
                            <div class="bento-pill-right">
                                <span class="bento-pill-badge bento-pill-badge--operational">Operational</span>
                                <a href="{{ route('admin.maintenance.index') }}" class="bento-pill-chevron" aria-label="View High-Pressure Refill Pump">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                    @endforelse
                </div>
                <div class="bento-card-footer">
                    <a href="{{ route('admin.maintenance.index') }}" class="bento-footer-link">
                        <span>Open Maintenance Log</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="{{ route('admin.maintenance.index') }}" class="bento-footer-btn" aria-label="Open Maintenance Log">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </div>
            </article>

            <!-- 4. LOW STOCK ITEMS -->
            <article class="bento-card">
                <div class="bento-card-header">
                    <div>
                        <h4 class="bento-card-title">Low Stock Items</h4>
                        <p class="bento-card-subtitle">INVENTORY ALERTS</p>
                    </div>
                    <div class="bento-header-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                </div>
                <div class="bento-card-body bento-items-body">
                    @forelse(collect($lowStockItems)->take(2) as $inv)
                        @php
                            $invObj = is_array($inv) ? (object) $inv : (is_object($inv) ? $inv : null);
                            $productName = is_object($invObj?->product ?? null) ? ($invObj->product->name ?? 'Inventory Item') : ($invObj?->product_name ?? 'Inventory Item');
                            $qtyOnHand = $invObj?->quantity_on_hand ?? 0;
                            $reorderLvl = $invObj?->reorder_level ?? 0;
                        @endphp
                        @if($invObj)
                        <div class="bento-pill-card">
                            <div class="bento-pill-left">
                                <strong class="bento-pill-title">{{ $productName }}</strong>
                            </div>
                            <div class="bento-pill-right">
                                <div class="bento-stock-numbers">
                                    <strong class="bento-stock-qty">{{ $qtyOnHand }} {{ $qtyOnHand <= 15 ? 'left' : 'in stock' }}</strong>
                                    <span class="bento-stock-reorder">(Reorder: {{ $reorderLvl }})</span>
                                </div>
                                <a href="{{ route('admin.inventory.index') }}" class="bento-pill-chevron" aria-label="View {{ $productName }}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                        @endif
                    @empty
                        <div class="bento-pill-card">
                            <div class="bento-pill-left">
                                <strong class="bento-pill-title">Purified Water Concentrate</strong>
                            </div>
                            <div class="bento-pill-right">
                                <div class="bento-stock-numbers">
                                    <strong class="bento-stock-qty">120 in stock</strong>
                                    <span class="bento-stock-reorder">(Reorder: 50)</span>
                                </div>
                                <a href="{{ route('admin.inventory.index') }}" class="bento-pill-chevron" aria-label="View Purified Water Concentrate">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                        <div class="bento-pill-card">
                            <div class="bento-pill-left">
                                <strong class="bento-pill-title">Sanitizing Gallon Wash Solution</strong>
                            </div>
                            <div class="bento-pill-right">
                                <div class="bento-stock-numbers">
                                    <strong class="bento-stock-qty">15 left</strong>
                                    <span class="bento-stock-reorder">(Reorder: 20)</span>
                                </div>
                                <a href="{{ route('admin.inventory.index') }}" class="bento-pill-chevron" aria-label="View Sanitizing Gallon Wash Solution">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                    @endforelse
                </div>
                <div class="bento-card-footer">
                    <a href="{{ route('admin.inventory.index') }}" class="bento-footer-link">
                        <span>Manage Station Inventory</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="{{ route('admin.inventory.index') }}" class="bento-footer-btn" aria-label="Manage Station Inventory">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </div>
            </article>

            <!-- 5. STATION FLEET DISPATCH -->
            <article class="bento-card">
                <div class="bento-card-header">
                    <div>
                        <h4 class="bento-card-title">Station Fleet Dispatch</h4>
                        <p class="bento-card-subtitle">DELIVERY &amp; TRANSPORT</p>
                    </div>
                    <div class="bento-header-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                    </div>
                </div>
                <div class="bento-card-body bento-items-body">
                    @php
                        $firstDriver = collect($driverBoard)->first();
                    @endphp
                    @if($firstDriver)
                        @php
                            $dName = is_array($firstDriver) ? ($firstDriver['name'] ?? 'Juan Driver') : ($firstDriver->name ?? 'Juan Driver');
                            $dPlate = is_array($firstDriver) ? ($firstDriver['vehicle_plate'] ?? 'ABC-1234') : ($firstDriver->vehicle_plate ?? 'ABC-1234');
                            $dStatus = is_array($firstDriver) ? ($firstDriver['status'] ?? 'idle') : ($firstDriver->status ?? 'idle');
                            $dTask = is_array($firstDriver) ? ($firstDriver['current_task_code'] ?? null) : ($firstDriver->current_task_code ?? null);
                            $dSub = $dPlate . ' · ' . ($dStatus === 'idle' ? 'Idle at station' : ($dTask ?? 'Idle at station'));
                            $dBadge = strtoupper(str_replace('_', ' ', $dStatus ?: 'AVAILABLE'));
                            $dGal = (is_array($firstDriver) ? ($firstDriver['gallons_count'] ?? 0) : ($firstDriver->gallons_count ?? 0)) . '/30 Gal';
                        @endphp
                        <div class="bento-pill-card bento-pill-card--driver">
                            <div class="bento-pill-left">
                                <strong class="bento-pill-title">{{ $dName }}</strong>
                                <span class="bento-pill-subtitle">{{ $dSub }}</span>
                            </div>
                            <div class="bento-pill-right">
                                <div class="bento-driver-meta">
                                    <span class="bento-pill-badge bento-pill-badge--available">{{ $dBadge }}</span>
                                    <span class="bento-driver-gal">{{ $dGal }}</span>
                                </div>
                                <a href="{{ route('admin.deliveries.index') }}" class="bento-pill-chevron" aria-label="View Fleet Details">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="bento-pill-card bento-pill-card--driver">
                            <div class="bento-pill-left">
                                <strong class="bento-pill-title">Juan Driver</strong>
                                <span class="bento-pill-subtitle">ABC-1234 &middot; Idle at station</span>
                            </div>
                            <div class="bento-pill-right">
                                <div class="bento-driver-meta">
                                    <span class="bento-pill-badge bento-pill-badge--available">AVAILABLE</span>
                                    <span class="bento-driver-gal">0/30 Gal</span>
                                </div>
                                <a href="{{ route('admin.deliveries.index') }}" class="bento-pill-chevron" aria-label="View Fleet Details">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="bento-card-footer">
                    <a href="{{ route('admin.deliveries.index') }}" class="bento-footer-link">
                        <span>View Fleet Details</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="{{ route('admin.deliveries.index') }}" class="bento-footer-btn" aria-label="Open Fleet Details">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </div>
            </article>

            <!-- 6. QUICK ACTIONS -->
            <article class="bento-card">
                <div class="bento-card-header">
                    <div>
                        <h4 class="bento-card-title">Quick Actions</h4>
                        <p class="bento-card-subtitle">JUMP TO COMMON TASKS</p>
                    </div>
                    <div class="bento-header-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                    </div>
                </div>
                <div class="bento-card-body bento-quick-body">
                    <div class="bento-quick-grid">
                        <a href="{{ route('admin.orders.index') }}" class="bento-quick-tile">
                            <div class="bento-quick-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                            </div>
                            <span class="bento-quick-label">New Order</span>
                        </a>
                        <a href="{{ route('admin.customers.index') }}" class="bento-quick-tile">
                            <div class="bento-quick-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                            </div>
                            <span class="bento-quick-label">Add Customer</span>
                        </a>
                        <a href="{{ route('admin.orders.index', ['status' => 'received_at_station']) }}" class="bento-quick-tile">
                            <div class="bento-quick-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                                </svg>
                            </div>
                            <span class="bento-quick-label">Station Refilling</span>
                        </a>
                        <a href="{{ route('admin.reports.index') }}" class="bento-quick-tile">
                            <div class="bento-quick-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                            </div>
                            <span class="bento-quick-label">View Reports</span>
                        </a>
                        <a href="{{ route('admin.inventory.index') }}" class="bento-quick-tile">
                            <div class="bento-quick-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                </svg>
                            </div>
                            <span class="bento-quick-label">Manage Inventory</span>
                        </a>
                        <a href="{{ route('admin.settings.index') }}" class="bento-quick-tile">
                            <div class="bento-quick-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            </div>
                            <span class="bento-quick-label">System Settings</span>
                        </a>
                    </div>
                </div>
            </article>

        </section>
    </section>

    <!-- ===================================================================
         10. CHART DATA TRANSFER
    =================================================================== -->
    <script>
        window.AQUA_CLAYNE_DASHBOARD_DATA = {
            chartLabels: @json($chartLabels),
            completedDaily: @json($completedDaily),
            pendingDaily: @json($pendingDaily),
            inProgressDaily: @json($inProgressDaily),
            statusLabels: @json(array_keys($statusCounts)),
            statusValues: @json(array_values($statusCounts)),
            statusColors: @json($statusColors ?? [])
        };
    </script>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endpush
