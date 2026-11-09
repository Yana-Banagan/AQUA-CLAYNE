@extends('admin.layouts.app')

@section('title', 'Refill Queue')
@section('header-title', 'Refill Queue')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-refill-queue.css') }}">
@endpush

@push('scripts')
    <script>
        window.refillQueueConfig = {
            statusUrl: @json(route('admin.refill-queue.status')),
            supabaseUrl: @json(config('services.supabase.url')),
            supabaseAnonKey: @json(config('services.supabase.anon_key'))
        };
    </script>
    <script src="{{ asset('js/admin-refill-queue.js') }}?v=20261006-1" defer></script>
@endpush

@section('content')

    <!-- Dynamic Toast Notification Container -->
    <div class="toast-container" id="toastContainer" aria-live="polite"></div>

    <div class="refill-queue-page">

        <!-- ===================================================================
             1. PAGE HEADER
        =================================================================== -->
        <div class="refill-queue-header">
            <div>
                <div class="refill-queue-title">
                    <span>Refill Queue</span>
                    <span class="station-badge-live">
                        <span class="pulse-dot-live" aria-hidden="true"></span>
                        <span>Station Live • FIFO Refill</span>
                    </span>
                </div>
                <p class="refill-queue-subtitle">Manage verified refill orders, station processing, and refill priority.</p>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="{{ route('admin.orders.index') }}" class="queue-action-btn view-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <span>View All Orders</span>
                </a>
                <a href="{{ route('admin.deliveries.index') }}" class="queue-action-btn view-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                    <span>Deliveries</span>
                </a>
            </div>
        </div>

        <!-- ===================================================================
             2. SUMMARY KPI CARDS (4 PRIMARY METRICS)
        =================================================================== -->
        <section class="refill-stats" aria-label="Refill Queue Key Metrics">
            
            <!-- Card 1: Waiting in Queue -->
            <article class="refill-card kpi-card">
                <div class="kpi-head">
                    <span class="kpi-label">WAITING IN QUEUE</span>
                </div>
                <div class="kpi-value">{{ $stats['waiting'] }}</div>
                <div class="kpi-meta">
                    <span>orders waiting for refill</span>
                </div>
            </article>

            @if(false) {{-- Removed from the streamlined dashboard. --}}
            <!-- Card 2: Gallons Waiting -->
            <article class="refill-card kpi-card">
                <div class="kpi-head">
                    <span class="kpi-label">GALLONS WAITING</span>
                </div>
                <div class="kpi-value">{{ $stats['totalGallons'] }}</div>
                <div class="kpi-meta">
                    <span>customer-owned gallons</span>
                </div>
            </article>

            @endif

            <!-- Card 3: Currently Refilling -->
            <article class="refill-card kpi-card">
                <div class="kpi-head">
                    <span class="kpi-label">CURRENTLY REFILLING</span>
                </div>
                <div class="kpi-value">{{ $stats['refilling'] }}</div>
                <div class="kpi-meta">
                    <span>{{ $stats['refilling'] === 1 ? '1 order in progress' : $stats['refilling'] . ' orders in progress' }}</span>
                </div>
            </article>

            @if(false) {{-- Ready orders are tracked in delivery operations. --}}
            <!-- Card 4: Ready for Delivery -->
            <article class="refill-card kpi-card">
                <div class="kpi-head">
                    <span class="kpi-label">READY FOR DELIVERY</span>
                </div>
                <div class="kpi-value">{{ $stats['ready'] }}</div>
                <div class="kpi-meta">
                    <span>orders sealed &amp; staged</span>
                </div>
            </article>

            @endif
        </section>

        {{-- Intake, filters, and waiting-order controls live on the Orders dashboard. --}}
        @if(false)
        <!-- Secondary Metrics Bar -->
        <div class="secondary-metrics-bar">
            <div class="secondary-metrics-data">
                <div class="sec-metric-item">
                    <span class="sec-metric-label">Avg. Wait Time:</span>
                    <strong class="sec-metric-value">{{ $stats['avgWaitMinutes'] > 0 ? $stats['avgWaitMinutes'] . ' min' : '< 5 min' }}</strong>
                </div>
                <span class="sec-separator" aria-hidden="true">•</span>
                <div class="sec-metric-item">
                    <span class="sec-metric-label">Completed Today:</span>
                    <strong class="sec-metric-value">{{ $stats['completedToday'] }} orders</strong>
                </div>
                <span class="sec-separator" aria-hidden="true">•</span>
                <div class="sec-metric-item">
                    <span class="sec-metric-label">Awaiting Intake:</span>
                    <strong class="sec-metric-value">{{ $stats['awaitingIntake'] }} orders</strong>
                </div>
                @if($stats['discrepancies'] > 0)
                    <span class="sec-separator" aria-hidden="true">•</span>
                    <div class="sec-metric-item sec-metric-item--alert">
                        <span>Discrepancies Needing Attention:</span>
                        <strong class="sec-metric-value">{{ $stats['discrepancies'] }}</strong>
                    </div>
                @endif
            </div>
            <div class="sec-metric-item sec-metric-policy">
                <span class="sec-metric-label">Policy:</span>
                <strong>Strict Prioritized FIFO (1 Active Refill)</strong>
            </div>
        </div>

        <!-- ===================================================================
             3. INTAKE DISCREPANCIES (CRITICAL RECONCILIATION NOTICE)
        =================================================================== -->
        @if($intakeDiscrepancies->count() > 0)
            @foreach($intakeDiscrepancies as $discOrder)
                @php
                    $intake = $discOrder->containerIntake;
                    $expected = $intake ? $intake->expected_count : $discOrder->expected_empty_gallons;
                    $received = $intake ? $intake->received_count : 0;
                    $accepted = $intake ? $intake->accepted_count : 0;
                    $diff = abs($expected - $received);
                @endphp
                <aside class="discrepancy-banner" role="alert">
                    <div class="banner-content">
                        <span class="banner-icon">⚠</span>
                        <div>
                            <div class="banner-title">
                                Intake Discrepancy Detected — Order #{{ $discOrder->order_number ?? strtoupper(substr($discOrder->id, 0, 8)) }}
                            </div>
                            <div class="banner-desc">
                                Expected: <strong>{{ $expected }} gallons</strong> &bull;
                                Received: <strong>{{ $received }} gallons</strong> &bull;
                                Accepted: <strong>{{ $accepted }} gallons</strong> &bull;
                                Difference: <strong>{{ $diff }} gallon(s)</strong>
                                (Customer: {{ $discOrder->customer?->user?->name ?? 'Customer' }}).
                                <em>This order will NOT enter the Refill Queue until reconciled.</em>
                            </div>
                        </div>
                    </div>
                    <div>
                        <button type="button" class="queue-action-btn start-refill-btn"
                                data-action="resolve-intake"
                                data-order-id="{{ $discOrder->id }}"
                                data-order-num="{{ $discOrder->order_number ?? strtoupper(substr($discOrder->id, 0, 8)) }}"
                                data-customer="{{ $discOrder->customer?->user?->name ?? 'Customer' }}"
                                data-expected="{{ $expected }}"
                                data-received="{{ $received }}"
                                data-accepted="{{ $accepted }}"
                                data-notes="{{ $intake?->condition_notes ?? '' }}">
                            Resolve Intake →
                        </button>
                    </div>
                </aside>
            @endforeach
        @endif

        <!-- ===================================================================
             4. PICKED UP GALLONS READY FOR REFILL
        =================================================================== -->
        @if($awaitingIntakeOrders->count() > 0)
            <div class="intake-banner">
                <div class="banner-content">
                    <span class="banner-icon">♜</span>
                    <div>
                        <div class="banner-title">
                            Gallons Arrived at Station — Ready for Refill ({{ $awaitingIntakeOrders->count() }})
                        </div>
                        <div class="banner-desc">
                            Start Refill to record the expected gallon count and enter the FIFO queue. Queue order and single-active-refill rules still apply.
                        </div>
                    </div>
                </div>
                <div>
                    <span class="status-badge" style="background: #FEF3C7; color: #92400E; font-weight: 700;">
                        {{ $awaitingIntakeOrders->count() }} Orders Arrived
                    </span>
                </div>
            </div>

            <!-- Arrived Orders Table -->
            <div class="refill-card rq-table-card" style="margin-bottom: 14px;">
                <div class="rq-table-head">
                    <strong class="rq-table-title">Orders Arrived at Station (Driver Collected)</strong>
                    <span class="rq-table-count">Expected customer-owned gallons</span>
                </div>
                <table class="refill-table" style="table-layout:fixed; width:100%;">
                    <colgroup>
                        <col style="width:14%">
                        <col style="width:20%">
                        <col style="width:13%">
                        <col style="width:17%">
                        <col style="width:13%">
                        <col style="width:11%">
                        <col style="width:12%">
                    </colgroup>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Expected Gallons</th>
                                <th>Collected By Driver</th>
                                <th>Arrived Time</th>
                                <th>Container Ownership</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($awaitingIntakeOrders as $intakeOrder)
                                <tr>
                                    <td>
                                        <strong style="color: var(--ac-primary);">#{{ $intakeOrder->order_number ?? strtoupper(substr($intakeOrder->id, 0, 8)) }}</strong>
                                    </td>
                                    <td>
                                        <strong>{{ $intakeOrder->customer?->user?->name ?? 'Customer' }}</strong>
                                        <small style="display: block; color: var(--ac-muted); font-size: 11.5px;">{{ $intakeOrder->customer?->user?->phone ?? '' }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $intakeOrder->expected_empty_gallons ?: ($intakeOrder->items->sum('quantity') ?: 1) }} gallons</strong>
                                    </td>
                                    <td>
                                        <span>{{ $intakeOrder->delivery?->driver?->user?->name ?? 'Assigned Driver' }}</span>
                                    </td>
                                    <td>
                                        <span style="color: var(--ac-muted);">{{ $intakeOrder->updated_at?->diffForHumans() ?? 'Recently' }}</span>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-normal">CUSTOMER OWNED</span>
                                    </td>
                                    <td style="text-align: right;">
                                        <button type="button" class="queue-action-btn start-refill-btn"
                                                data-action="start-refill"
                                                data-order-id="{{ $intakeOrder->id }}"
                                                data-order-num="{{ $intakeOrder->order_number ?? strtoupper(substr($intakeOrder->id, 0, 8)) }}"
                                                data-customer="{{ $intakeOrder->customer?->user?->name ?? 'Customer' }}"
                                                data-quantity="{{ $intakeOrder->expected_empty_gallons ?: ($intakeOrder->items->sum('quantity') ?: 1) }}">
                                            Start Refill →
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
            </div>{{-- /.rq-table-card arrived orders --}}
        @endif

        <!-- ===================================================================
             5. TABS & FILTER CONTROLS
        =================================================================== -->
        <nav class="queue-tabs" aria-label="Queue Status Tabs">
            <a href="{{ route('admin.refill-queue.index', array_merge(request()->query(), ['status' => 'active'])) }}" 
               class="queue-tab-btn {{ $statusFilter === 'active' ? 'active' : '' }}">
                <span>Active Queue</span>
                <span class="tab-badge">{{ $stats['waiting'] + $stats['refilling'] }}</span>
            </a>
            <a href="{{ route('admin.refill-queue.index', array_merge(request()->query(), ['status' => 'waiting'])) }}" 
               class="queue-tab-btn {{ $statusFilter === 'waiting' ? 'active' : '' }}">
                <span>Waiting</span>
                <span class="tab-badge">{{ $stats['waiting'] }}</span>
            </a>
            <a href="{{ route('admin.refill-queue.index', array_merge(request()->query(), ['status' => 'in_progress'])) }}" 
               class="queue-tab-btn {{ $statusFilter === 'in_progress' ? 'active' : '' }}">
                <span>Currently Refilling</span>
                <span class="tab-badge">{{ $stats['refilling'] }}</span>
            </a>
            <a href="{{ route('admin.refill-queue.index', array_merge(request()->query(), ['status' => 'completed'])) }}" 
               class="queue-tab-btn {{ $statusFilter === 'completed' ? 'active' : '' }}">
                <span>Completed</span>
                <span class="tab-badge">{{ $stats['completedToday'] }}</span>
            </a>
            <a href="{{ route('admin.refill-queue.index', array_merge(request()->query(), ['status' => 'all'])) }}" 
               class="queue-tab-btn {{ $statusFilter === 'all' ? 'active' : '' }}">
                <span>All Records</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.refill-queue.index') }}" class="refill-card queue-filters" id="queueFilterForm">
            <input type="hidden" name="status" value="{{ $statusFilter }}">

            <!-- Search input -->
            <div class="filter-input-wrap">
                <span class="filter-icon-left">🔍</span>
                <input type="search" name="search" id="queueSearchInput" class="filter-control has-icon"
                       placeholder="Search by order #, customer name, or phone..."
                       value="{{ $search }}">
            </div>

            <!-- Priority filter -->
            <div>
                <select name="priority" class="filter-control">
                    <option value="all" {{ $priorityFilter === 'all' ? 'selected' : '' }}>All Priorities</option>
                    <option value="high" {{ $priorityFilter === 'high' ? 'selected' : '' }}>High Priority Only</option>
                    <option value="normal" {{ $priorityFilter === 'normal' ? 'selected' : '' }}>Normal Priority (0)</option>
                    <option value="low" {{ $priorityFilter === 'low' ? 'selected' : '' }}>Low Priority</option>
                </select>
            </div>

            <!-- Date filter -->
            <div>
                <input type="date" name="date" class="filter-control" value="{{ $dateFilter }}">
            </div>

            <!-- Actions -->
            <div class="filter-actions">
                <button type="submit" class="btn-filter">Filter</button>
                <a href="{{ route('admin.refill-queue.index') }}" class="btn-reset">Reset</a>
            </div>
        </form>

        <!-- ===================================================================
             6. VERIFIED REFILL QUEUE — FULL WIDTH
        =================================================================== -->
        @endif

        <section class="refill-operations-grid" aria-label="Active refill queue">
        <div class="refill-main-content">

            <div class="refill-card rq-table-card simplified-refill-table">
                <div class="rq-table-head">
                    <div>
                        <strong class="rq-table-title">Verified Refill Queue</strong>
                        <small class="rq-table-sub">Verified gallons currently being refilled</small>
                    </div>
                    <span class="rq-table-count">{{ $queueEntries->total() }} refilling</span>
                </div>
                <div class="refill-table-wrapper">
                    <table class="refill-table">
                        <thead><tr><th>Queue</th><th>Order</th><th>Customer</th><th style="text-align: right;">Action</th></tr></thead>
                        <tbody>
                            @forelse($queueEntries as $entry)
                                @php
                                    $gallons = $entry->order?->containerIntake?->accepted_count ?? $entry->order?->expected_empty_gallons ?? 1;
                                    $orderNumber = $entry->order?->order_number ?? strtoupper(substr($entry->order_id, 0, 8));
                                    $customer = $entry->order?->customer?->user?->name ?? 'Customer';
                                @endphp
                                <tr>
                                    <td><span class="queue-number">#{{ sprintf('%03d', $entry->queue_number) }}</span></td>
                                    <td><strong style="color: var(--ac-dark-blue);">#{{ $orderNumber }}</strong></td>
                                    <td><strong>{{ $customer }}</strong></td>
                                    <td style="text-align: right;">
                                        <button type="button" class="queue-action-btn complete-refill-btn"
                                                data-action="complete-refill"
                                                data-order-id="{{ $entry->order_id }}"
                                                data-order-num="{{ $orderNumber }}"
                                                data-customer="{{ $customer }}"
                                                data-quantity="{{ $gallons }}">Ready</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align: center; color: var(--ac-muted); padding: 30px;">No gallons are currently refilling.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($queueEntries->total() > 0)
                    <div class="rq-pagination-footer">
                        <span class="rq-pagination-info">
                            Showing <strong>{{ $queueEntries->firstItem() }}</strong>–<strong>{{ $queueEntries->lastItem() }}</strong>
                            of <strong>{{ $queueEntries->total() }}</strong> record{{ $queueEntries->total() !== 1 ? 's' : '' }}
                        </span>
                        @if($queueEntries->hasPages())
                            <nav class="rq-pagination" aria-label="Verified refill queue pages">
                                @if($queueEntries->onFirstPage())
                                    <span class="rq-page-btn rq-page-btn--disabled" aria-disabled="true">‹</span>
                                @else
                                    <a class="rq-page-btn" href="{{ $queueEntries->previousPageUrl() }}" aria-label="Previous page">‹</a>
                                @endif
                                @foreach($queueEntries->getUrlRange(1, $queueEntries->lastPage()) as $page => $url)
                                    @if($page === $queueEntries->currentPage())
                                        <span class="rq-page-btn rq-page-btn--active" aria-current="page">{{ $page }}</span>
                                    @else
                                        <a class="rq-page-btn" href="{{ $url }}">{{ $page }}</a>
                                    @endif
                                @endforeach
                                @if($queueEntries->hasMorePages())
                                    <a class="rq-page-btn" href="{{ $queueEntries->nextPageUrl() }}" aria-label="Next page">›</a>
                                @else
                                    <span class="rq-page-btn rq-page-btn--disabled" aria-disabled="true">›</span>
                                @endif
                            </nav>
                        @endif
                    </div>
                @endif
            </div>

            @if(false) {{-- Replaced by the streamlined verified-refill table above. --}}
            <!-- ACTIVE REFILL QUEUE TABLE — full width -->
            <div class="refill-card rq-table-card">
                <div class="rq-table-head">
                    <div>
                        <strong class="rq-table-title">Verified Refill Queue</strong>
                        <small class="rq-table-sub">FIFO order processing of accepted customer-owned containers</small>
                    </div>
                    <span class="rq-table-count">
                        {{ $queueEntries->total() }} records
                    </span>
                </div>

                {{-- NO overflow wrapper — table must fit naturally --}}
                <div class="refill-table-wrapper rq-main-table-wrapper">
                <table class="refill-table rq-main-table" id="queueMainTable">
                    <colgroup>
                        <col class="rq-col-num">
                        <col class="rq-col-order">
                        <col class="rq-col-customer">
                        <col class="rq-col-qty">
                        <col class="rq-col-recv">
                        <col class="rq-col-wait">
                        <col class="rq-col-priority">
                        <col class="rq-col-status">
                        <col class="rq-col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Queue #</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Gals</th>
                            <th>Recv'd</th>
                            <th>Wait</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                            @forelse($queueEntries as $entry)
                                @php
                                    $isNextToRefill = ($entry->id === $nextEligibleId && $entry->status === 'waiting' && !$hasActiveRefill);
                                    $acceptedGallons = $entry->order?->containerIntake?->accepted_count 
                                        ?? $entry->order?->expected_empty_gallons 
                                        ?? 1;
                                    $receivedTime = $entry->order?->containerIntake?->received_at 
                                        ?? $entry->queued_at;
                                @endphp
                                <tr class="{{ $isNextToRefill ? 'next-to-refill' : '' }}">
                                    <!-- Queue # -->
                                    <td>
                                        <span class="queue-number">
                                            #{{ sprintf('%03d', $entry->queue_number) }}
                                        </span>
                                        @if($isNextToRefill)
                                            <div class="next-to-refill-pill">NEXT TO REFILL</div>
                                        @endif
                                    </td>

                                    <!-- Order # -->
                                    <td>
                                        <strong style="color: var(--ac-dark-blue); font-size: 13.5px;">
                                            #{{ $entry->order?->order_number ?? strtoupper(substr($entry->order_id, 0, 8)) }}
                                        </strong>
                                        <a href="{{ route('admin.orders.show', $entry->order_id) }}" 
                                           style="display: block; font-size: 11.5px; color: var(--ac-primary); text-decoration: none; margin-top: 2px;">
                                            View Order Details &rarr;
                                        </a>
                                    </td>

                                    <!-- Customer -->
                                    <td>
                                        <strong style="color: var(--ac-text);">
                                            {{ $entry->order?->customer?->user?->name ?? 'Customer' }}
                                        </strong>
                                        <small style="display: block; color: var(--ac-muted); font-size: 11.5px;">
                                            {{ $entry->order?->customer?->user?->phone ?? 'N/A' }}
                                        </small>
                                    </td>

                                    <!-- Quantity (Customer Owned) -->
                                    <td>
                                        <strong style="color: var(--ac-dark-blue);">{{ $acceptedGallons }} gal</strong>
                                        <small style="display: block; color: #0284C7; font-size: 11px; font-weight: 700;">Customer-Owned</small>
                                    </td>

                                    <!-- Received At -->
                                    <td>
                                        <span style="color: #475569; font-size: 13px;">
                                            {{ $receivedTime ? $receivedTime->format('g:i A') : 'N/A' }}
                                        </span>
                                        <small style="display: block; color: var(--ac-muted); font-size: 11px;">
                                            {{ $receivedTime ? $receivedTime->format('M d') : '' }}
                                        </small>
                                    </td>

                                    <!-- Realtime Waiting Time -->
                                    <td>
                                        @if($entry->status === 'waiting')
                                            <strong style="color: var(--ac-warning); font-size: 13px;"
                                                    data-queued-at="{{ $entry->queued_at?->toISOString() }}">
                                                {{ $entry->queued_at ? $entry->queued_at->diffForHumans(null, true) : '0m' }}
                                            </strong>
                                            <small style="display: block; color: var(--ac-muted); font-size: 11px;">in queue</small>
                                        @elseif($entry->status === 'in_progress')
                                            <strong style="color: var(--ac-purple); font-size: 13px;">
                                                Refilling
                                            </strong>
                                            <small style="display: block; color: var(--ac-muted); font-size: 11px;">
                                                started {{ $entry->started_at ? $entry->started_at->diffForHumans(null, true) : 'now' }}
                                            </small>
                                        @else
                                            <span style="color: var(--ac-success); font-weight: 700;">Done</span>
                                            <small style="display: block; color: var(--ac-muted); font-size: 11px;">completed</small>
                                        @endif
                                    </td>

                                    <!-- Priority -->
                                    <td>
                                        @if($entry->priority > 15)
                                            <span class="priority-badge priority-urgent" title="{{ $entry->manual_priority_reason ?? 'Urgent priority' }}">
                                                URGENT (+{{ $entry->priority }})
                                            </span>
                                        @elseif($entry->priority > 0)
                                            <span class="priority-badge priority-high" title="{{ $entry->manual_priority_reason ?? 'High priority' }}">
                                                HIGH (+{{ $entry->priority }})
                                            </span>
                                        @elseif($entry->priority < 0)
                                            <span class="priority-badge priority-normal">
                                                LOW ({{ $entry->priority }})
                                            </span>
                                        @else
                                            <span class="priority-badge priority-normal">NORMAL</span>
                                        @endif
                                    </td>

                                    <!-- Queue Status -->
                                    <td>
                                        @if($entry->status === 'waiting')
                                            <span class="queue-status queue-status-waiting">
                                                <span class="pulse-dot-live" style="background: #F59E0B;" aria-hidden="true"></span>
                                                WAITING
                                            </span>
                                        @elseif($entry->status === 'in_progress')
                                            <span class="queue-status queue-status-progress">
                                                <span class="pulse-dot-live" style="background: #8B5CF6;" aria-hidden="true"></span>
                                                REFILLING
                                            </span>
                                        @elseif($entry->status === 'completed')
                                            <span class="queue-status queue-status-completed">✓ READY</span>
                                        @else
                                            <span class="queue-status queue-status-removed">REMOVED</span>
                                        @endif
                                    </td>

                                    <!-- Actions -->
                                    <td style="text-align: right;">
                                        <div class="queue-actions-wrap" style="justify-content: flex-end;">
                                            @if($entry->status === 'waiting')
                                                @if($isNextToRefill)
                                                    <button type="button" class="queue-action-btn start-refill-btn"
                                                            data-action="start-refill"
                                                            data-order-id="{{ $entry->order_id }}"
                                                            data-order-num="{{ $entry->order?->order_number ?? strtoupper(substr($entry->order_id, 0, 8)) }}"
                                                            data-customer="{{ $entry->order?->customer?->user?->name ?? 'Customer' }}"
                                                            data-quantity="{{ $acceptedGallons }}"
                                                            data-queue-pos="#{{ sprintf('%03d', $entry->queue_number) }}"
                                                            data-received="{{ $receivedTime ? $receivedTime->format('g:i A') : 'N/A' }}">
                                                        <span>▶</span>
                                                        <span>Start Refill</span>
                                                    </button>
                                                @endif

                                                <button type="button" class="queue-action-btn priority-btn"
                                                        data-action="change-priority"
                                                        data-order-id="{{ $entry->order_id }}"
                                                        data-order-num="{{ $entry->order?->order_number ?? strtoupper(substr($entry->order_id, 0, 8)) }}"
                                                        data-queue-pos="#{{ sprintf('%03d', $entry->queue_number) }}"
                                                        data-current-priority="{{ $entry->priority }}">
                                                    <span>⇅ Priority</span>
                                                </button>
                                            @elseif($entry->status === 'in_progress')
                                                <button type="button" class="queue-action-btn complete-refill-btn"
                                                        data-action="complete-refill"
                                                        data-order-id="{{ $entry->order_id }}"
                                                        data-order-num="{{ $entry->order?->order_number ?? strtoupper(substr($entry->order_id, 0, 8)) }}"
                                                        data-customer="{{ $entry->order?->customer?->user?->name ?? 'Customer' }}"
                                                        data-quantity="{{ $acceptedGallons }}">
                                                    <span>✓</span>
                                                    <span>Mark Refill Ready</span>
                                                </button>
                                            @else
                                                <a href="{{ route('admin.orders.show', $entry->order_id) }}" class="queue-action-btn view-btn">
                                                    <span>View</span>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="queue-empty-box">
                                            <div class="empty-water-drop">💧</div>
                                            <div class="queue-empty-title">Queue is currently empty</div>
                                            <div class="queue-empty-desc">
                                                No verified refill orders are currently waiting for processing.
                                                Orders enter the queue once empty gallons arrive at station and pass intake verification.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                </table>
                </div>

                {{-- Custom pagination footer --}}
                @if($queueEntries->total() > 0)
                    <div class="rq-pagination-footer">
                        {{-- Showing X–Y of Z --}}
                        <span class="rq-pagination-info">
                            @if($queueEntries->total() > 0)
                                Showing <strong>{{ $queueEntries->firstItem() }}</strong>–<strong>{{ $queueEntries->lastItem() }}</strong>
                                of <strong>{{ $queueEntries->total() }}</strong> record{{ $queueEntries->total() !== 1 ? 's' : '' }}
                            @endif
                        </span>

                        @if($queueEntries->hasPages())
                            <nav class="rq-pagination" aria-label="Queue pages">
                                {{-- Previous --}}
                                @if($queueEntries->onFirstPage())
                                    <span class="rq-page-btn rq-page-btn--disabled">‹</span>
                                @else
                                    <a class="rq-page-btn" href="{{ $queueEntries->previousPageUrl() }}" aria-label="Previous">‹</a>
                                @endif

                                {{-- Page numbers with ellipsis window --}}
                                @php
                                    $cur  = $queueEntries->currentPage();
                                    $last = $queueEntries->lastPage();
                                    $win  = 2;
                                    $pages = [];
                                    for ($p = 1; $p <= $last; $p++) {
                                        if ($p === 1 || $p === $last || ($p >= $cur - $win && $p <= $cur + $win)) {
                                            $pages[] = $p;
                                        }
                                    }
                                @endphp
                                @php $prev = null; @endphp
                                @foreach($pages as $page)
                                    @if($prev !== null && $page - $prev > 1)
                                        <span class="rq-page-ellipsis">…</span>
                                    @endif
                                    @if($page === $cur)
                                        <span class="rq-page-btn rq-page-btn--active" aria-current="page">{{ $page }}</span>
                                    @else
                                        <a class="rq-page-btn" href="{{ $queueEntries->url($page) }}">{{ $page }}</a>
                                    @endif
                                    @php $prev = $page; @endphp
                                @endforeach

                                {{-- Next --}}
                                @if($queueEntries->hasMorePages())
                                    <a class="rq-page-btn" href="{{ $queueEntries->nextPageUrl() }}" aria-label="Next">›</a>
                                @else
                                    <span class="rq-page-btn rq-page-btn--disabled">›</span>
                                @endif
                            </nav>
                        @endif
                    </div>
                @endif
            </div>{{-- /.rq-table-card --}}
            @endif

        </div>{{-- /.refill-main-content --}}

        </section>{{-- /.refill-operations-grid --}}

    </div>

@endsection

@push('modals')

    <!-- ===================================================================
         MODAL 1: START REFILL CONFIRMATION
    =================================================================== -->
    <div class="modal-backdrop" id="startRefillModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="startRefillTitle">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="startRefillTitle">Start Refill Process?</h3>
                <button type="button" class="modal-close-btn" data-modal-close aria-label="Close modal">&times;</button>
            </div>

            <form id="startRefillForm" action="#">
                <input type="hidden" id="startRefillOrderId" value="">

                <div class="modal-body">
                    <div class="modal-order-summary">
                        <div class="modal-summary-row">
                            <span>Order Number:</span>
                            <strong id="startOrderNumText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Customer:</span>
                            <strong id="startCustomerText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Gallon Quantity:</span>
                            <strong id="startQuantityText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Queue Position:</span>
                            <strong id="startPositionText" style="color: var(--ac-primary);"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Intake Received:</span>
                            <strong id="startReceivedText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Current Status:</span>
                            <strong style="color: var(--ac-warning);">WAITING IN QUEUE</strong>
                        </div>
                    </div>

                    <p style="font-size: 13.5px; color: #475569; line-height: 1.5; margin: 0;">
                        This will start the physical water refill process for this order at the station. The order status will transition to <strong>REFILLING</strong> and the queue status will move to <strong>IN_PROGRESS</strong>.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="queue-action-btn priority-btn" data-modal-close>Cancel</button>
                    <button type="submit" class="queue-action-btn start-refill-btn">
                        <span>▶ Start Refill</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================
         MODAL 2: COMPLETE REFILL CONFIRMATION (MARK READY FOR DELIVERY)
    =================================================================== -->
    <div class="modal-backdrop" id="completeRefillModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="completeRefillTitle">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="completeRefillTitle">Complete Refill Process</h3>
                <button type="button" class="modal-close-btn" data-modal-close aria-label="Close modal">&times;</button>
            </div>

            <form id="completeRefillForm" action="#">
                <input type="hidden" id="completeRefillOrderId" value="">

                <div class="modal-body">
                    <div class="modal-order-summary">
                        <div class="modal-summary-row">
                            <span>Order Number:</span>
                            <strong id="completeOrderNumText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Customer:</span>
                            <strong id="completeCustomerText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Gallon Quantity:</span>
                            <strong id="completeQuantityText"></strong>
                        </div>
                    </div>

                    <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 10px; padding: 14px; color: #065F46; font-size: 13px; line-height: 1.5; margin-bottom: 12px;">
                        ✓ <strong>Quality Confirmation:</strong> Confirm that all customer gallons have been refilled with purified water, thoroughly sealed with safety seals, and are staged ready for driver dispatch.
                    </div>

                    <p style="font-size: 13px; color: var(--ac-muted); margin: 0;">
                        Order status will update to <strong>READY_FOR_DELIVERY</strong>.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="queue-action-btn priority-btn" data-modal-close>Cancel</button>
                    <button type="submit" class="queue-action-btn complete-refill-btn">
                        <span>✓ Mark Refill Ready</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================
         MODAL 3: CHANGE QUEUE PRIORITY (CONTROLLED AUDIT TRAIL)
    =================================================================== -->
    <div class="modal-backdrop" id="priorityModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="priorityTitle">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="priorityTitle">Change Queue Priority</h3>
                <button type="button" class="modal-close-btn" data-modal-close aria-label="Close modal">&times;</button>
            </div>

            <form id="priorityChangeForm" action="#">
                <input type="hidden" id="priorityOrderId" value="">

                <div class="modal-body">
                    <div class="modal-order-summary">
                        <div class="modal-summary-row">
                            <span>Order:</span>
                            <strong id="priorityOrderNumText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Current Position:</span>
                            <strong id="priorityCurrentPosText" style="color: var(--ac-primary);"></strong>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prioritySelect">
                            Adjusted Priority Level <span class="required">*</span>
                        </label>
                        <select id="prioritySelect" class="form-control" required>
                            <option value="20">Urgent Priority (+20) — Senior, PWD, or Medical Supply</option>
                            <option value="10">High Priority (+10) — Previous Station Delay / Expedite</option>
                            <option value="0">Normal Priority (0) — Standard Station FIFO</option>
                            <option value="-10">Low Priority (-10) — Customer Requested Later Processing</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="priorityReasonSelect">
                            Operational Reason <span class="required">*</span>
                        </label>
                        <select id="priorityReasonSelect" class="form-control" required>
                            <option value="">Select operational reason...</option>
                            <option value="Senior / PWD / Medical Priority">Senior / PWD / Medical Priority</option>
                            <option value="Correction of Intake Mistake">Correction of Intake Mistake</option>
                            <option value="Urgent Customer Service Condition">Urgent Customer Service Condition</option>
                            <option value="Previous Station Service Failure">Previous Station Service Failure</option>
                            <option value="Station Operational Supervisor Decision">Station Operational Supervisor Decision</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="priorityExplanation">
                            Detailed Explanation
                        </label>
                        <textarea id="priorityExplanation" class="form-control" rows="3"
                                  placeholder="Enter specific operational context for the permanent audit log..."></textarea>
                        <small style="color: var(--ac-muted); font-size: 11.5px; margin-top: 4px; display: block;">
                            Every priority modification creates an unalterable system audit record.
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="queue-action-btn priority-btn" data-modal-close>Cancel</button>
                    <button type="submit" class="queue-action-btn start-refill-btn">
                        <span>Confirm Priority Change</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================
         MODAL 4: PHYSICAL CONTAINER INTAKE CONFIRMATION
    =================================================================== -->
    <div class="modal-backdrop" id="intakeModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="intakeTitle">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="intakeTitle">Physical Gallon Intake</h3>
                <button type="button" class="modal-close-btn" data-modal-close aria-label="Close modal">&times;</button>
            </div>

            <form id="stationIntakeForm" action="#">
                <input type="hidden" id="intakeOrderId" value="">

                <div class="modal-body">
                    <div class="modal-order-summary">
                        <div class="modal-summary-row">
                            <span>Order Number:</span>
                            <strong id="intakeOrderNumText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Customer:</span>
                            <strong id="intakeCustomerText"></strong>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;" class="form-group">
                        <div>
                            <label class="form-label" for="intakeExpectedCount">Expected Gallons</label>
                            <input type="number" id="intakeExpectedCount" class="form-control" readonly style="background: #F1F5F9; font-weight: 700;">
                        </div>
                        <div>
                            <label class="form-label" for="intakeReceivedCount">Received Gallons <span class="required">*</span></label>
                            <input type="number" id="intakeReceivedCount" class="form-control" min="0" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;" class="form-group">
                        <div>
                            <label class="form-label" for="intakeAcceptedCount">Accepted for Refill <span class="required">*</span></label>
                            <input type="number" id="intakeAcceptedCount" class="form-control" min="0" required>
                        </div>
                        <div>
                            <label class="form-label" for="intakeDamagedCount">Damaged / Rejected</label>
                            <input type="number" id="intakeDamagedCount" class="form-control" min="0" value="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="intakeNotes">Condition Notes</label>
                        <textarea id="intakeNotes" class="form-control" rows="2" placeholder="e.g. Slim 5-gallon blue containers received clean with caps."></textarea>
                    </div>

                    <div style="background: #E0F2FE; border: 1px solid #BAE6FD; border-radius: 10px; padding: 10px 14px; font-size: 12px; color: #0369A1;">
                        ℹ Customer-owned empty gallons will enter the FIFO Refill Queue immediately upon verification without discrepancy.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="queue-action-btn priority-btn" data-modal-close>Cancel</button>
                    <button type="submit" class="queue-action-btn start-refill-btn">
                        <span>Start Refill &amp; Enter Queue</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================
         MODAL 5: RESOLVE INTAKE DISCREPANCY
    =================================================================== -->
    <div class="modal-backdrop" id="resolveModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="resolveTitle">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="resolveTitle" style="color: #991B1B;">Resolve Intake Discrepancy</h3>
                <button type="button" class="modal-close-btn" data-modal-close aria-label="Close modal">&times;</button>
            </div>

            <form id="resolveIntakeForm" action="#">
                <input type="hidden" id="resolveOrderId" value="">

                <div class="modal-body">
                    <div class="modal-order-summary" style="border-left: 4px solid #DC2626;">
                        <div class="modal-summary-row">
                            <span>Order Number:</span>
                            <strong id="resolveOrderNumText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Customer:</span>
                            <strong id="resolveCustomerText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Original Expected:</span>
                            <strong id="resolveExpectedText"></strong>
                        </div>
                        <div class="modal-summary-row">
                            <span>Physically Received:</span>
                            <strong id="resolveReceivedText" style="color: #DC2626;"></strong>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="resolveAcceptedCount">
                            Final Reconciled Gallons to Refill <span class="required">*</span>
                        </label>
                        <input type="number" id="resolveAcceptedCount" class="form-control" min="1" required>
                        <small style="color: var(--ac-muted); font-size: 11.5px;">
                            Only accepted undamaged customer gallons will enter the station refill queue.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="resolveReason">
                            Resolution Explanation <span class="required">*</span>
                        </label>
                        <input type="text" id="resolveReason" class="form-control" required
                               placeholder="e.g. Customer agreed to refill 2 gallons instead of 3; 1 gallon had hairline crack.">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="resolveNotes">Additional Inspection Notes</label>
                        <textarea id="resolveNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="queue-action-btn priority-btn" data-modal-close>Cancel</button>
                    <button type="submit" class="queue-action-btn start-refill-btn">
                        <span>Resolve &amp; Enter Queue</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

@endpush
