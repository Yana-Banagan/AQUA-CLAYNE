@extends('admin.layouts.app')

@section('title', 'Orders Management')
@section('header-title', 'Orders')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-orders.css') }}?v=20261006-17">
@endpush

@push('scripts')
    <script src="{{ asset('js/admin-orders.js') }}?v=20261006-6" defer></script>
@endpush

@section('content')

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer" aria-live="polite"></div>

    <!-- ===================================================================
         1. PAGE HEADER
    =================================================================== -->
    <div class="orders-header">
        <div>
            <div class="orders-title-row">
                <h1 class="orders-title">Orders</h1>
                <span class="service-model-badge">Customer-Owned Gallon Refill Service</span>
            </div>
            <p class="orders-subtitle">Manage customer refill orders, assign drivers, and track order progress.</p>
        </div>

        <div class="header-action-group">
            <a href="{{ route('admin.refill-queue.index') }}" class="btn-queue-link">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                <span>Station Refill Queue</span>
            </a>
        </div>
    </div>

    <!-- ===================================================================
         2. SUMMARY KPI CARDS (7 CARDS WITH CLICK-TO-FILTER)
    =================================================================== -->
    <section class="orders-stats" aria-label="Order Metrics Overview">
        
        <!-- Total Orders -->
        <a href="{{ route('admin.orders.index') }}" class="stat-card {{ empty($status) || $status === 'all' ? 'active-stat' : '' }}">
            <div class="stat-head">
                <span class="stat-label">TOTAL ORDERS</span>
            </div>
            <div class="stat-number" data-order-stat="total">{{ number_format($stats['total']) }}</div>
            <div class="stat-foot">All historical orders</div>
        </a>

        <!-- Pending -->
        <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="stat-card {{ $status === 'pending' ? 'active-stat' : '' }}">
            <div class="stat-head">
                <span class="stat-label">PENDING</span>
            </div>
            <div class="stat-number" data-order-stat="pending">{{ number_format($stats['pending']) }}</div>
            <div class="stat-foot">Requires review</div>
        </a>

        <!-- Confirmed -->
        <a href="{{ route('admin.orders.index', ['status' => 'confirmed']) }}" class="stat-card {{ $status === 'confirmed' ? 'active-stat' : '' }}">
            <div class="stat-head">
                <span class="stat-label">CONFIRMED</span>
            </div>
            <div class="stat-number" data-order-stat="confirmed">{{ number_format($stats['confirmed']) }}</div>
            <div class="stat-foot">Ready for driver assignment</div>
        </a>

        <!-- Assigned -->
        <a href="{{ route('admin.orders.index', ['status' => 'assigned']) }}" class="stat-card {{ $status === 'assigned' ? 'active-stat' : '' }}">
            <div class="stat-head">
                <span class="stat-label">ASSIGNED</span>
            </div>
            <div class="stat-number" data-order-stat="assigned">{{ number_format($stats['assigned']) }}</div>
            <div class="stat-foot">Driver assigned for pickup</div>
        </a>

        <!-- Out for Delivery -->
        <a href="{{ route('admin.orders.index', ['status' => 'out_for_delivery']) }}" class="stat-card {{ $status === 'out_for_delivery' ? 'active-stat' : '' }}">
            <div class="stat-head">
                <span class="stat-label">OUT FOR DELIVERY</span>
            </div>
            <div class="stat-number" data-order-stat="out_for_delivery">{{ number_format($stats['out_for_delivery']) }}</div>
            <div class="stat-foot">Refilled &amp; on the way</div>
        </a>

        <!-- Completed -->
        <a href="{{ route('admin.orders.index', ['status' => 'completed']) }}" class="stat-card {{ $status === 'completed' ? 'active-stat' : '' }}">
            <div class="stat-head">
                <span class="stat-label">COMPLETED</span>
            </div>
            <div class="stat-number" data-order-stat="completed">{{ number_format($stats['completed']) }}</div>
            <div class="stat-foot">Delivered &amp; settled</div>
        </a>

        <!-- Cancelled -->
        <a href="{{ route('admin.orders.index', ['status' => 'cancelled']) }}" class="stat-card {{ $status === 'cancelled' ? 'active-stat' : '' }}">
            <div class="stat-head">
                <span class="stat-label">CANCELLED</span>
            </div>
            <div class="stat-number" data-order-stat="cancelled">{{ number_format($stats['cancelled']) }}</div>
            <div class="stat-foot">Discontinued orders</div>
        </a>

    </section>

    <section class="orders-workspace" aria-label="Order search, filters, and results">

    <!-- ===================================================================
         3. ADVANCED SEARCH & FILTER BAR
    =================================================================== -->
    <article class="orders-card filter-card">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="orders-filters-form" id="ordersFilterForm">
            
            <!-- Search Field -->
            <div class="filter-field search-field">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="search" name="search" id="ordersSearchInput" class="filter-input" 
                       placeholder="Search order #, customer, phone, driver, address..." 
                       value="{{ $search }}">
            </div>

            <!-- Status Filter -->
            <div class="filter-field">
                <select name="status" class="filter-select" aria-label="Filter by order status">
                    <option value="" {{ empty($status) || $status === 'all' ? 'selected' : '' }}>All Orders</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="confirmed" {{ $status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="assigned" {{ $status === 'assigned' ? 'selected' : '' }}>Assigned</option>
                    <option value="picked_up" {{ $status === 'picked_up' ? 'selected' : '' }}>Picked Up</option>
                    <option value="received_at_station" {{ $status === 'received_at_station' ? 'selected' : '' }}>Received at Station</option>
                    <option value="waiting_in_queue" {{ $status === 'waiting_in_queue' ? 'selected' : '' }}>Waiting in Queue</option>
                    <option value="refilling" {{ $status === 'refilling' ? 'selected' : '' }}>Refilling</option>
                    <option value="ready_for_delivery" {{ $status === 'ready_for_delivery' ? 'selected' : '' }}>Ready for Delivery</option>
                    <option value="ready_for_pickup" {{ $status === 'ready_for_pickup' ? 'selected' : '' }}>Ready for Pickup</option>
                    <option value="out_for_delivery" {{ $status === 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                    <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                    <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="exceptions" {{ $status === 'exceptions' ? 'selected' : '' }}>Failed / Exceptions</option>
                </select>
            </div>

            <!-- Delivery Method Filter -->
            <div class="filter-field">
                <select name="delivery_method" class="filter-select" aria-label="Filter by delivery method">
                    <option value="">All Fulfillment</option>
                    <option value="home_delivery" {{ ($deliveryMethod ?? '') === 'home_delivery' ? 'selected' : '' }}>Home Delivery</option>
                    <option value="station_pickup" {{ ($deliveryMethod ?? '') === 'station_pickup' ? 'selected' : '' }}>Station Pickup</option>
                </select>
            </div>

            <!-- Driver Filter -->
            <div class="filter-field">
                <select name="driver_id" class="filter-select" aria-label="Filter by assigned driver">
                    <option value="">All Drivers</option>
                    @foreach($activeDrivers as $drv)
                        <option value="{{ $drv['id'] }}" {{ $driverId == $drv['id'] ? 'selected' : '' }}>
                            {{ $drv['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Payment Method Filter (Online, Cash, and Debt) -->
            <div class="filter-field">
                <select name="payment_method" class="filter-select" aria-label="Filter by payment method">
                    <option value="">All Payment Terms</option>
                    <option value="online" {{ $paymentMethod === 'online' ? 'selected' : '' }}>PayMongo Online</option>
                    <option value="cash" {{ $paymentMethod === 'cash' ? 'selected' : '' }}>Cash on Delivery</option>
                    <option value="credit" {{ $paymentMethod === 'credit' ? 'selected' : '' }}>Customer Debt</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="filter-buttons">
                <button type="submit" class="btn-filter-submit">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                    </svg>
                    <span>Filter</span>
                </button>

                @if($search || $status || $driverId || $paymentMethod)
                    <a href="{{ route('admin.orders.index') }}" class="btn-filter-reset" title="Clear all filters">
                        <span>Reset</span>
                    </a>
                @endif
            </div>

        </form>
    </article>

    <!-- ===================================================================
         5. ORDERS DATA TABLE
    =================================================================== -->
    <article class="orders-card table-card">
        
        <div class="orders-table-wrapper">
            <table class="orders-table" id="ordersMainTable">
                {{-- colgroup drives fixed-layout column widths (must match col.col-* in CSS) --}}
                <colgroup>
                    <col class="col-check">
                    <col class="col-order">
                    <col class="col-customer">
                    <col class="col-address">
                    <col class="col-status">
                    <col class="col-driver">
                    <col class="col-view">
                    <col class="col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col" class="th-select">Select</th>
                        <th scope="col">Order #</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Address</th>
                        <th scope="col">Status</th>
                        <th scope="col">Driver</th>
                        <th scope="col">View</th>
                        <th scope="col" style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $statusSlug = strtolower(str_replace('_', '-', $order->status));
                            $item = $order->items->first();
                            $serviceName = $item?->product?->name ?? '5-Gal Purified Refill';
                            $quantity = $order->items->sum('quantity') ?: ($order->expected_empty_gallons ?: 1);
                            $customerName = $order->customer?->user?->name ?? 'Walk-in Customer';
                            $customerPhone = $order->customer?->user?->phone ?? 'N/A';
                            $addressLine = $order->address?->address_line ?? 'Station Area';
                            $driver = $order->delivery?->driver;
                            $driverUser = $driver?->user;
                            $orderNum = $order->order_number ?: ('ORD-' . strtoupper(substr($order->id, 0, 8)));
                        @endphp
                        <tr id="order-row-{{ $order->id }}" data-order-id="{{ $order->id }}" data-status="{{ strtolower($order->status) }}">
                            
                            <td class="td-checkbox">
                                <input type="checkbox" class="order-select-checkbox" value="{{ $order->id }}" aria-label="Select order {{ $orderNum }}">
                            </td>

                            <!-- Order # -->
                            <td class="td-order-num">
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="order-link">
                                    {{ $orderNum }}
                                </a>
                            </td>

                            <!-- Customer -->
                            <td class="td-customer">
                                <div class="customer-cell">
                                    <div class="customer-avatar" aria-hidden="true">
                                        @include('admin.partials.avatar-content', ['user' => $order->customer?->user, 'fallbackName' => $customerName])
                                    </div>
                                    <div class="customer-info">
                                        <strong class="customer-name">{{ $customerName }}</strong>
                                        <small class="customer-phone">{{ $customerPhone }}</small>
                                    </div>
                                </div>
                            </td>

                            <!-- Address / Fulfillment -->
                            <td class="td-address">
                                @if($order->isStationPickup())
                                    <div style="display:flex;flex-direction:column;gap:3px;">
                                        <span class="badge" style="background:#E0F2FE;color:#0369A1;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;width:fit-content;">🏪 Station Pickup</span>
                                        <span class="address-snippet" style="color:#64748B;font-size:12px;">Aqua Clayne Station</span>
                                    </div>
                                @else
                                    <div style="display:flex;flex-direction:column;gap:3px;">
                                        <span class="badge" style="background:#F0FDF4;color:#15803D;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;width:fit-content;">🛵 Home Delivery</span>
                                        <span class="address-snippet" title="{{ $addressLine }}">{{ $addressLine }}</span>
                                    </div>
                                @endif
                            </td>

                            {{-- Container column removed — always "Customer Owned" in this system --}}

                            <!-- Status Badge -->
                            <td class="td-status">
                                <span class="status-badge status-{{ $statusSlug }}">
                                    <span class="status-dot" aria-hidden="true"></span>
                                    <span>{{ ucwords(str_replace('_', ' ', $order->status)) }}</span>
                                </span>
                            </td>

                            <!-- Driver Column -->
                            <td class="td-driver">
                                @if($order->isStationPickup())
                                    <div style="display:inline-flex;align-items:center;padding:4px 8px;background:#F8FAFC;border-radius:6px;border:1px solid #E2E8F0;">
                                        <span style="font-size:12px;color:#64748B;font-weight:600;">Pickup Order</span>
                                    </div>
                                @elseif($driverUser)
                                    <div class="driver-assigned-cell">
                                        <div class="driver-initials-badge">
                                            {{ strtoupper(substr($driverUser->name, 0, 2)) }}
                                        </div>
                                        <div class="driver-info-wrap">
                                            <span class="driver-name-text">{{ $driverUser->name }}</span>
                                            <small class="driver-plate">{{ $driver->vehicle_plate ?: 'Assigned' }}</small>
                                        </div>
                                    </div>
                                @else
                                    <div class="driver-unassigned-cell">
                                        <span class="awaiting-indicator" aria-hidden="true">●</span>
                                        <span class="unassigned-text">Unassigned</span>
                                    </div>
                                @endif
                            </td>

                            <!-- View / Review -->
                            <td class="td-view">
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="btn-table-action {{ strtolower($order->status) === 'pending' ? 'btn-review' : '' }}">
                                    <span>{{ strtolower($order->status) === 'pending' ? 'Review' : 'View' }}</span>
                                </a>
                            </td>

                            <!-- Actions -->
                            <td class="td-actions">
                                @if(strtolower($order->status) === 'pending')
                                    <form method="POST" action="{{ route('admin.orders.transition', $order->id) }}" class="inline-action-form">
                                        @csrf
                                        <input type="hidden" name="status" value="confirmed">
                                        <button type="submit" class="btn-workflow-action btn-confirm-order" onclick="return confirm('Confirm order {{ $orderNum }}?');">
                                            <span>Confirm Order</span>
                                        </button>
                                    </form>
                                @elseif(strtolower($order->status) === 'confirmed')
                                    @if($order->isStationPickup())
                                        <a href="{{ route('admin.orders.show', $order->id) }}" class="btn-workflow-action btn-confirm-order" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;">
                                            <span>Manage Intake</span>
                                        </a>
                                    @else
                                        <!-- PROMINENT ASSIGN DRIVER BUTTON -->
                                        <button type="button" class="btn-assign-driver" 
                                                data-order-id="{{ $order->id }}"
                                                data-order-num="{{ $orderNum }}"
                                                data-customer="{{ $customerName }}"
                                                data-address="{{ $addressLine }}"
                                                data-gallons="{{ $quantity }}"
                                                data-service="{{ $serviceName }}">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                                            </svg>
                                            <span>Assign Driver</span>
                                        </button>
                                    @endif
                                @elseif(strtolower($order->status) === 'picked_up')
                                    <form method="POST" action="{{ route('admin.refill-queue.start', $order->id) }}" class="inline-action-form" data-live-refill-action>
                                        @csrf
                                        <button type="submit" class="btn-workflow-action btn-refill">
                                        <span>Refill</span>
                                        </button>
                                    </form>
                                @elseif(strtolower($order->status) === 'received_at_station')
                                    <form method="POST" action="{{ route('admin.refill-queue.start', $order->id) }}" class="inline-action-form" data-live-refill-action>
                                        @csrf
                                        <button type="submit" class="btn-workflow-action btn-refill">
                                            <span>Refill</span>
                                        </button>
                                    </form>
                                @elseif(strtolower($order->status) === 'waiting_in_queue')
                                    <form method="POST" action="{{ route('admin.refill-queue.start', $order->id) }}" class="inline-action-form" data-live-refill-action>
                                        @csrf
                                        <button type="submit" class="btn-workflow-action btn-refill" onclick="return confirm('Start refilling order {{ $orderNum }}?');">
                                            <span>Refill</span>
                                        </button>
                                    </form>
                                @elseif(strtolower($order->status) === 'refilling')
                                    <form method="POST" action="{{ route('admin.refill-queue.complete', $order->id) }}" class="inline-action-form" data-live-refill-action>
                                        @csrf
                                        <button type="submit" class="btn-workflow-action btn-ready" onclick="return confirm('Mark order {{ $orderNum }} as ready?');">
                                            <span>Ready</span>
                                        </button>
                                    </form>
                                @elseif(strtolower($order->status) === 'ready_for_pickup')
                                    <form method="POST" action="{{ route('admin.orders.transition', $order->id) }}" class="inline-action-form">
                                        @csrf
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn-workflow-action btn-ready" style="background:#0284C7;color:#fff;" onclick="return confirm('Complete customer pickup for order {{ $orderNum }}?');">
                                            <span>Complete Pickup</span>
                                        </button>
                                    </form>
                                @elseif(strtolower($order->status) === 'ready_for_delivery')
                                    <span class="workflow-complete-label">Ready</span>
                                @else
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="btn-table-action">
                                        <span>View</span>
                                    </a>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="table-empty-row">
                                <div class="empty-state-box">
                                    <div class="empty-icon-wrap" aria-hidden="true">
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                            <line x1="3" y1="6" x2="21" y2="6"></line>
                                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                                        </svg>
                                    </div>
                                    <h3>No orders found</h3>
                                    <p>No customer refill orders match your selected search or filter parameters.</p>
                                    @if($search || $status || $driverId || $paymentMethod)
                                        <a href="{{ route('admin.orders.index') }}" class="btn-clear-empty">Clear All Filters</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Table Footer: Pagination -->
        @if($orders->hasPages() || $orders->total() > 0)
            <div class="table-pagination-footer">
                {{-- Showing X–Y of Z --}}
                <div class="pagination-info">
                    @if($orders->total() > 0)
                        Showing <strong>{{ $orders->firstItem() }}</strong>–<strong>{{ $orders->lastItem() }}</strong>
                        of <strong>{{ $orders->total() }}</strong> order{{ $orders->total() !== 1 ? 's' : '' }}
                    @else
                        No orders found
                    @endif
                </div>

                {{-- Page navigation --}}
                @if($orders->hasPages())
                    <nav class="orders-pagination" aria-label="Orders pages">
                        {{-- Previous --}}
                        @if($orders->onFirstPage())
                            <span class="page-btn page-btn--disabled" aria-disabled="true">‹ Prev</span>
                        @else
                            <a class="page-btn" href="{{ $orders->previousPageUrl() }}" aria-label="Previous page">‹ Prev</a>
                        @endif

                        {{-- Page numbers (show up to 7, with ellipsis) --}}
                        @php
                            $current = $orders->currentPage();
                            $last    = $orders->lastPage();
                            $window  = 2; // pages each side of current
                            $pages   = [];
                            for ($p = 1; $p <= $last; $p++) {
                                if ($p === 1 || $p === $last || ($p >= $current - $window && $p <= $current + $window)) {
                                    $pages[] = $p;
                                }
                            }
                        @endphp

                        @php $prev = null; @endphp
                        @foreach($pages as $page)
                            @if($prev !== null && $page - $prev > 1)
                                <span class="page-ellipsis">…</span>
                            @endif
                            @if($page === $current)
                                <span class="page-btn page-btn--active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="page-btn" href="{{ $orders->url($page) }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                            @endif
                            @php $prev = $page; @endphp
                        @endforeach

                        {{-- Next --}}
                        @if($orders->hasMorePages())
                            <a class="page-btn" href="{{ $orders->nextPageUrl() }}" aria-label="Next page">Next ›</a>
                        @else
                            <span class="page-btn page-btn--disabled" aria-disabled="true">Next ›</span>
                        @endif
                    </nav>
                @endif
            </div>
        @endif

    </article>

    {{-- Keep counter sales inside the Orders workspace. The admin shell uses a
        fixed-height scrolling region, so a sibling after this workspace can
        otherwise be clipped below the visible scroll area. --}}
    <section class="walk-in-orders-section" aria-labelledby="walkInOrdersTitle">
        <div class="walk-in-orders-head">
            <div>
                <p class="walk-in-orders-eyebrow">Counter sales</p>
                <h2 id="walkInOrdersTitle">Walk-in Orders</h2>
                <p>{{ number_format($walkInSummary['count']) }} completed &middot; ₱{{ number_format($walkInSummary['payments'], 2) }} collected &middot; Today: {{ number_format($walkInSummary['today_count']) }} / ₱{{ number_format($walkInSummary['today_payments'], 2) }}</p>
            </div>
            <button type="button" class="btn-walk-in-add" data-walk-in-open>
                <span aria-hidden="true">+</span> Add Order
            </button>
        </div>

        <div class="walk-in-orders-table-wrap">
            <table class="walk-in-orders-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact Number</th>
                        <th>Qty</th>
                        <th>Payment / Method</th>
                        <th>Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($walkInOrders as $walkInOrder)
                        <tr>
                            <td>{{ $walkInOrder->full_name }}</td>
                            <td>{{ $walkInOrder->phone ?: '—' }}</td>
                            <td>{{ number_format($walkInOrder->gallons) }}</td>
                            <td><span class="walk-in-payment-paid">₱{{ number_format((float) $walkInOrder->amount, 2) }} &middot; {{ match($walkInOrder->payment_method ?? 'cash') { 'gcash' => 'GCash', 'paymongo' => 'PayMongo', 'debt' => 'Debt', default => 'Cash' } }}</span></td>
                            <td>{{ $walkInOrder->created_at->format('M j, Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="walk-in-orders-empty">No walk-in orders have been completed yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    </section>{{-- /.orders-workspace --}}

    <!-- ===================================================================
         6. MODAL INCLUDE
    =================================================================== -->
    @include('admin.orders.partials.assign-driver-modal')

    <div class="modal-backdrop walk-in-order-backdrop" id="walkInOrderModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="walkInOrderTitle">
        <section class="modal walk-in-order-modal" role="document">
            <header class="walk-in-modal-header">
                <div class="walk-in-modal-heading">
                    <span class="walk-in-title-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 8v6m-3-3h6"/></svg>
                    </span>
                    <div>
                        <h3 id="walkInOrderTitle">Add Walk-in Order</h3>
                        <p>Create a new order for a walk-in customer</p>
                    </div>
                </div>
                <span class="walk-in-header-drop" aria-hidden="true"></span>
                <button type="button" class="walk-in-close-btn" data-walk-in-close aria-label="Close modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m18 6-12 12M6 6l12 12"/></svg>
                </button>
            </header>

            <form method="POST" action="{{ route('admin.orders.walk-in.store') }}" id="walkInOrderForm" novalidate>
                @csrf
                <div class="walk-in-modal-content">
                    <div class="walk-in-form-column" aria-label="Walk-in customer and order details">
                        <div class="walk-in-field {{ $errors->has('full_name') ? 'is-invalid' : '' }}" data-field="full_name">
                            <label for="walkInFullName">Full Name <span>*</span></label>
                            <div class="walk-in-input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
                                <input id="walkInFullName" name="full_name" type="text" value="{{ old('full_name') }}" maxlength="150" placeholder="Enter full name" autocomplete="name" required>
                            </div>
                            <small class="walk-in-inline-error" id="walkInFullNameError">@error('full_name'){{ $message }}@enderror</small>
                        </div>

                        <div class="walk-in-field {{ $errors->has('phone') ? 'is-invalid' : '' }}" data-field="phone">
                            <label for="walkInPhone">Contact Number <span>*</span></label>
                            <div class="walk-in-input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 11.19 18a19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.09 3.18 2 2 0 0 1 4.08 1h3a2 2 0 0 1 2 1.72l.45 3a2 2 0 0 1-.57 1.7L7.1 9.28a16 16 0 0 0 6 6l1.86-1.86a2 2 0 0 1 1.7-.57l3 .45a2 2 0 0 1 1.72 1.62z"/></svg>
                                <input id="walkInPhone" name="phone" type="tel" value="{{ old('phone') }}" inputmode="tel" maxlength="18" placeholder="e.g. 0917 123 4567" autocomplete="tel" required>
                            </div>
                            <small class="walk-in-inline-error" id="walkInPhoneError">@error('phone'){{ $message }}@enderror</small>
                        </div>

                        <div class="walk-in-field {{ $errors->has('gallons') ? 'is-invalid' : '' }}" data-field="gallons">
                            <label for="walkInGallons">How Many Gallons <span>*</span></label>
                            <div class="walk-in-input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h10l2 3v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6l2-3Z"/><path d="M5 8h14M9 3l1 5m5-5-1 5"/><path d="M9 14h6"/></svg>
                                <input id="walkInGallons" name="gallons" type="number" value="{{ old('gallons') }}" min="1" max="1000" step="1" placeholder="Enter number of gallons" required>
                            </div>
                            <small class="walk-in-inline-error" id="walkInGallonsError">@error('gallons'){{ $message }}@enderror</small>
                        </div>

                        <div class="walk-in-field {{ $errors->has('amount') ? 'is-invalid' : '' }}" data-field="amount">
                            <label for="walkInAmount">Amount <span>*</span></label>
                            <div class="walk-in-input-wrap walk-in-amount-wrap">
                                <span class="walk-in-currency" aria-hidden="true">₱</span>
                                <input id="walkInAmount" name="amount" type="text" value="₱0.00" readonly aria-readonly="true">
                            </div>
                            <small class="walk-in-rate-note">Rate: ₱35.00 per gallon · calculated automatically</small>
                        </div>

                        <div class="walk-in-field walk-in-payment-field {{ $errors->has('payment_method') ? 'is-invalid' : '' }}" data-field="payment_method">
                            <label for="walkInPaymentMethod">Payment Method <span>*</span></label>
                            <div class="walk-in-input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20m-15 5h4"/></svg>
                                <select id="walkInPaymentMethod" name="payment_method" required>
                                    <option value="cash" {{ old('payment_method', 'cash') === 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="gcash" {{ old('payment_method') === 'gcash' ? 'selected' : '' }}>GCash</option>
                                    <option value="paymongo" {{ old('payment_method') === 'paymongo' ? 'selected' : '' }}>PayMongo</option>
                                    <option value="debt" {{ old('payment_method') === 'debt' ? 'selected' : '' }}>Debt</option>
                                </select>
                            </div>
                            <small class="walk-in-inline-error" id="walkInPaymentMethodError">@error('payment_method'){{ $message }}@enderror</small>
                        </div>
                    </div>

                    <aside class="walk-in-summary-card" aria-labelledby="walkInSummaryTitle">
                        <div class="walk-in-summary-heading">
                            <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a8 8 0 0 0 8-8c0-4.4-8-12-8-12S4 9.6 4 14a8 8 0 0 0 8 8Z"/><path d="M8.5 14.5a3.5 3.5 0 0 0 3.5 3.5"/></svg></span>
                            <div><h4 id="walkInSummaryTitle">Order Summary</h4><p>Walk-in refill order</p></div>
                        </div>
                        <div class="walk-in-summary-rows">
                            <div><span>Gallons</span><strong class="walk-in-summary-value" id="walkInSummaryGallons">0</strong></div>
                            <div><span>Rate per gallon</span><strong class="walk-in-summary-value">₱35.00</strong></div>
                        </div>
                        <div class="walk-in-summary-total"><span>Total Amount</span><strong id="walkInSummaryTotal">₱0.00</strong></div>
                        <div class="walk-in-summary-example" id="walkInSummaryExample">Enter the gallon quantity to calculate the total.</div>
                        <div class="walk-in-info-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 11v5m0-8h.01"/></svg>
                            <div><strong>Walk-in Order</strong><p>This order is not linked to a customer account.</p></div>
                        </div>
                    </aside>
                </div>

                <footer class="walk-in-modal-footer">
                    <button type="button" class="walk-in-cancel-btn" data-walk-in-close>Cancel</button>
                    <button type="submit" class="walk-in-complete-btn" id="walkInCompleteButton">
                        <svg class="walk-in-complete-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                        <span>Complete Order</span>
                    </button>
                </footer>
            </form>
        </section>
    </div>

    <div class="modal-backdrop orders-intake-backdrop" id="ordersIntakeModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="ordersIntakeTitle">
        <div class="modal orders-intake-modal">
            <div class="modal-header">
                <h3 class="modal-title" id="ordersIntakeTitle">Physical Gallon Intake</h3>
                <button type="button" class="modal-close-btn" data-orders-intake-close aria-label="Close modal">&times;</button>
            </div>

            <form id="ordersIntakeForm">
                <input type="hidden" id="ordersIntakeOrderId">
                <div class="modal-body">
                    <div class="orders-intake-summary">
                        <div><span>Order Number:</span><strong id="ordersIntakeOrderNumber"></strong></div>
                        <div><span>Customer:</span><strong id="ordersIntakeCustomer"></strong></div>
                    </div>

                    <div class="orders-intake-grid">
                        <label>Expected Gallons
                            <input type="number" id="ordersIntakeExpected" readonly>
                        </label>
                        <label>Received Gallons <span>*</span>
                            <input type="number" id="ordersIntakeReceived" min="0" required>
                        </label>
                        <label>Accepted for Refill <span>*</span>
                            <input type="number" id="ordersIntakeAccepted" min="0" required>
                        </label>
                        <label>Damaged / Rejected
                            <input type="number" id="ordersIntakeDamaged" min="0" value="0">
                        </label>
                    </div>

                    <label class="orders-intake-notes">Condition Notes
                        <textarea id="ordersIntakeNotes" rows="2" placeholder="e.g. Slim 5-gallon blue containers received clean with caps."></textarea>
                    </label>
                    <p class="orders-intake-notice">Customer-owned empty gallons will enter the FIFO refill queue immediately after verification without a discrepancy.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-table-action" data-orders-intake-close>Cancel</button>
                    <button type="submit" class="btn-workflow-action btn-refill" id="ordersIntakeSubmit">Confirm Intake &amp; Enter Queue</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('walkInOrderModal');
    const open = document.querySelector('[data-walk-in-open]');
    const closeButtons = document.querySelectorAll('[data-walk-in-close]');
    const form = document.getElementById('walkInOrderForm');
    if (!modal || !open || !form) return;
    const gallonsInput = document.getElementById('walkInGallons');
    const amountInput = document.getElementById('walkInAmount');
    const nameInput = document.getElementById('walkInFullName');
    const phoneInput = document.getElementById('walkInPhone');
    const methodInput = document.getElementById('walkInPaymentMethod');
    const completeButton = document.getElementById('walkInCompleteButton');
    const refillRate = 35;
    const money = (amount) => `₱${Number(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const updateWalkInAmount = () => {
        const gallons = Number(gallonsInput?.value || 0);
        const total = gallons > 0 ? gallons * refillRate : 0;
        amountInput.value = money(total);
        document.getElementById('walkInSummaryGallons').textContent = String(gallons > 0 ? gallons : 0);
        document.getElementById('walkInSummaryTotal').textContent = money(total);
        document.getElementById('walkInSummaryExample').textContent = gallons > 0
            ? `${gallons} gallon${gallons === 1 ? '' : 's'} × ${money(refillRate)} = ${money(total)}`
            : 'Enter the gallon quantity to calculate the total.';
    };
    gallonsInput?.addEventListener('input', updateWalkInAmount);
    updateWalkInAmount();

    const setFieldError = (key, message) => {
        const field = form.querySelector(`[data-field="${key}"]`);
        const input = field?.querySelector('input, select');
        const error = document.getElementById(`walkIn${key.split('_').map(part => part[0].toUpperCase() + part.slice(1)).join('')}Error`);
        if (!field || !input || !error) return;
        field.classList.toggle('is-invalid', Boolean(message));
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        error.textContent = message || '';
    };
    const validateField = (key) => {
        if (key === 'full_name') return nameInput.value.trim() ? '' : 'Full name is required.';
        if (key === 'phone') {
            if (!phoneInput.value.trim()) return 'Contact number is required.';
            const normalized = phoneInput.value.trim().replace(/[\s()\-]/g, '');
            return /^(?:09\d{9}|\+639\d{9}|639\d{9})$/.test(normalized)
                ? '' : 'Enter a valid Philippine mobile number, such as 0917 123 4567.';
        }
        if (key === 'gallons') {
            const gallons = Number(gallonsInput.value);
            return Number.isInteger(gallons) && gallons >= 1 ? '' : 'Please enter at least 1 gallon.';
        }
        if (key === 'payment_method') return methodInput.value ? '' : 'Select a payment method.';
        return '';
    };
    const fieldKeys = ['full_name', 'phone', 'gallons', 'payment_method'];
    fieldKeys.forEach((key) => {
        const field = form.querySelector(`[data-field="${key}"] input, [data-field="${key}"] select`);
        field?.addEventListener('input', () => setFieldError(key, validateField(key)));
        field?.addEventListener('change', () => setFieldError(key, validateField(key)));
    });
    form.addEventListener('submit', (event) => {
        let firstInvalid = null;
        fieldKeys.forEach((key) => {
            const message = validateField(key);
            setFieldError(key, message);
            if (message && !firstInvalid) firstInvalid = form.querySelector(`[data-field="${key}"] input, [data-field="${key}"] select`);
        });
        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
            return;
        }
        completeButton.disabled = true;
        completeButton.classList.add('is-saving');
        completeButton.querySelector('span').textContent = 'Saving Order...';
    });

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    };
    open.addEventListener('click', () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        nameInput?.focus();
    });
    closeButtons.forEach((button) => button.addEventListener('click', closeModal));
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });
    @if($errors->has('full_name') || $errors->has('phone') || $errors->has('gallons') || $errors->has('payment_method'))
        open.click();
    @endif
    @if(session('success'))
        window.Toast?.show(@json(session('success')), 'success');
    @endif
});
</script>
@endpush
