@extends('admin.layouts.app')

@section('title', 'Deliveries · AQUA CLAYNE')
@section('header-title', 'Deliveries')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-deliveries.css') }}?v=20261021-1">
    <link rel="stylesheet" href="{{ asset('css/admin-orders.css') }}?v=20261006-15">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@push('scripts')
    {{-- Page config injected from Laravel — never hardcoded in JS --}}
    <script>
        window.deliveryConfig = {
            listUrl:        "{{ route('admin.deliveries.index') }}",
            statusUrl:      "{{ route('admin.deliveries.status') }}",
            assignUrl:      "{{ route('admin.deliveries.assign.store') }}",
            detailUrl:      "{{ url('admin/deliveries') }}/:id",
            csrfToken:      "{{ csrf_token() }}",
            supabaseUrl:    "{{ config('services.supabase.url') }}",
            supabaseAnonKey:"{{ config('services.supabase.anon_key') }}",
            mapStatusUrl: "{{ route('admin.deliveries.status') }}",
            routeUrl: "{{ url('/admin/deliveries') }}",
            stationCoordinates: {
                latitude: {{ (float) config('services.station.latitude') }},
                longitude: {{ (float) config('services.station.longitude') }},
                address: @json(config('services.station.address'))
            }
        };
        window.deliveryMapInitial = @json($mapLocations);
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
    <script src="{{ asset('js/delivery-map.js') }}?v=20261021-1" defer></script>
    <script src="{{ asset('js/admin-deliveries.js') }}?v=20261006-4" defer></script>
@endpush

@section('content')

{{-- Toast Notification Container --}}
<div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="false"></div>

<div class="deliveries-page">

    {{-- ======================================================================
         1. PAGE HEADER
    ====================================================================== --}}
    <div class="deliveries-header">
        <div class="deliveries-title-wrap">
            <h1 class="deliveries-title">
                <span class="deliveries-title-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                </span>
                Deliveries
            </h1>
            <p class="deliveries-subtitle">Track and manage water deliveries in real-time.</p>
        </div>

        <div class="deliveries-header-actions">
            {{-- Refresh trigger --}}
            <button type="button" class="btn-refresh-deliveries" id="btnRefreshDeliveries"
                    title="Refresh deliveries data" aria-label="Refresh deliveries">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
            </button>

            {{-- Open Assign Driver modal (no specific order pre-selected) --}}
            <button type="button" class="btn-assign-driver btn-open-assign-modal"
                    aria-label="Assign driver to an order">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                </svg>
                <span>Assign Driver</span>
            </button>
        </div>
    </div>

    {{-- ======================================================================
         2. KPI CARDS — 4 live database-backed summary cards
    ====================================================================== --}}
    <section class="delivery-stats" aria-label="Delivery statistics overview">

        {{-- Total Deliveries --}}
        <article class="delivery-stat-card">
            <div class="stat-header">
                <span class="stat-title">Total Deliveries</span>
            </div>
            <div class="stat-number" id="kpiTotalDeliveries">{{ number_format($stats['total']) }}</div>
            <div class="stat-subtitle">All logged deliveries</div>
        </article>

        {{-- Pending / Unassigned --}}
        <article class="delivery-stat-card">
            <div class="stat-header">
                <span class="stat-title">Pending / Unassigned</span>
            </div>
            <div class="stat-number" id="kpiPendingDeliveries">{{ number_format($stats['pending']) }}</div>
            <div class="stat-subtitle">Awaiting driver assignment</div>
        </article>

        {{-- On Delivery --}}
        <article class="delivery-stat-card">
            <div class="stat-header">
                <span class="stat-title">On Delivery</span>
            </div>
            <div class="stat-number" id="kpiOnDelivery">{{ number_format($stats['onDelivery']) }}</div>
            <div class="stat-subtitle">Currently en route</div>
        </article>

        {{-- Completed Today --}}
        <article class="delivery-stat-card">
            <div class="stat-header">
                <span class="stat-title">Completed Today</span>
            </div>
            <div class="stat-number" id="kpiCompletedToday">{{ number_format($stats['completedToday']) }}</div>
            <div class="stat-subtitle">Successfully delivered today</div>
        </article>

    </section>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="dispatch-banner-callout callout-teal" style="margin-bottom: 20px;">
            <div class="dispatch-callout-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div class="dispatch-callout-text">
                <h4>Success</h4>
                <p>{{ session('success') }}</p>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="dispatch-banner-callout callout-amber" style="margin-bottom: 20px;">
            <div class="dispatch-callout-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <div class="dispatch-callout-text">
                <h4>Notice</h4>
                <p>{{ session('error') }}</p>
            </div>
        </div>
    @endif

    {{-- ======================================================================
         3. DISPATCH TABS NAVIGATION
    ====================================================================== --}}
    <nav class="dispatch-nav-tabs" aria-label="Dispatch Operations Tabs">
        <button type="button" class="dispatch-tab-btn active" data-tab="tabPickupDispatch">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 12 20 22 4 22 4 12"></polyline>
                <rect x="2" y="7" width="20" height="5"></rect>
                <line x1="12" y1="22" x2="12" y2="7"></line>
                <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
                <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
            </svg>
            <span>Pickup Dispatch</span>
            <span class="dispatch-tab-badge {{ count($suggestedPickupBatches) > 0 ? 'badge-highlight' : '' }}">
                {{ count($suggestedPickupBatches) + $activePickupBatches->count() }}
            </span>
        </button>

        <button type="button" class="dispatch-tab-btn" data-tab="tabDeliveryRoutes">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13"></rect>
                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                <circle cx="18.5" cy="18.5" r="2.5"></circle>
            </svg>
            <span>Delivery Routes</span>
            <span class="dispatch-tab-badge {{ count($suggestedDeliveryRoutes) > 0 ? 'badge-highlight' : '' }}">
                {{ count($suggestedDeliveryRoutes) + $activeDeliveryRoutes->total() }}
            </span>
        </button>

        <button type="button" class="dispatch-tab-btn" data-tab="tabDriverBoard">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Driver Board</span>
            <span class="dispatch-tab-badge">{{ count($driverBoard) }}</span>
        </button>

        <button type="button" class="dispatch-tab-btn" data-tab="tabAllDeliveries">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <line x1="8" y1="6" x2="21" y2="6"></line>
                <line x1="8" y1="12" x2="21" y2="12"></line>
                <line x1="8" y1="18" x2="21" y2="18"></line>
                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
            <span>All Deliveries</span>
            <span class="dispatch-tab-badge">{{ $stats['total'] }}</span>
        </button>

        <button type="button" class="dispatch-tab-btn" data-tab="tabDeliveryHistory">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>History</span>
            <span class="dispatch-tab-badge">{{ $deliveries->total() + $archivedDeliveries->total() }}</span>
        </button>
    </nav>

    {{-- ======================================================================
         TAB 1: PICKUP DISPATCH
    ====================================================================== --}}
    <div class="dispatch-tab-pane active" id="tabPickupDispatch">
        {{-- Intake Reminder Banner --}}
        <div class="dispatch-banner-callout callout-amber">
            <div class="dispatch-callout-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <div class="dispatch-callout-text">
                <h4>Physical Station Intake Mandatory</h4>
                <p>Drivers collect customer-owned empty gallons in batches (max 30 gal capacity per vehicle). Once brought to the station, staff complete physical intake inspection before refilling begins.</p>
            </div>
        </div>

        @include('admin.deliveries.partials.pickup-batches')
    </div>

    {{-- ======================================================================
         TAB 2: DELIVERY ROUTES
    ====================================================================== --}}
    <div class="dispatch-tab-pane" id="tabDeliveryRoutes">
        {{-- Delivery Routes Notice Banner --}}
        <div class="dispatch-banner-callout callout-teal">
            <div class="dispatch-callout-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            </div>
            <div class="dispatch-callout-text">
                <h4>Multi-Stop Delivery Dispatch</h4>
                <p>Refilled gallons ready for dispatch are clustered into sequenced routes for Driver 1 and Driver 2. Drivers verify payment (Paid Online / Customer Debt / Cash to collect) and upload photo proof upon delivery.</p>
            </div>
        </div>

        {{-- Suggested Delivery Routes --}}
        <div class="suggested-section-title">
            <span>Suggested Delivery Routes ({{ count($suggestedDeliveryRoutes) }})</span>
            <small>Grouped by area · Max 30 gallons vehicle capacity</small>
        </div>

        <div class="suggested-grid">
            @forelse($suggestedDeliveryRoutes as $route)
                <div class="suggested-card">
                    <div class="suggested-card-head">
                        <div>
                            <span class="suggested-card-tag">{{ $route['area'] }}</span>
                            <span style="margin-left: 8px; font-weight: 700; color: #64748B;">{{ $route['suggested_code'] }}</span>
                        </div>
                        <span class="suggested-capacity-badge {{ $route['total_gallons'] >= 28 ? 'near-max' : 'optimal' }}">
                            {{ $route['total_gallons'] }} / 30 Gal ({{ $route['stops_count'] }} Stops)
                        </span>
                    </div>

                    <div class="suggested-stops-list">
                        @foreach($route['orders'] as $idx => $o)
                            <div class="suggested-stop-item">
                                <span class="stop-seq-badge">{{ $idx + 1 }}</span>
                                <div class="stop-customer-details">
                                    <span class="stop-cust-name">{{ $o['customer_name'] }}</span>
                                    <span class="stop-cust-addr">{{ $o['address'] }}</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span class="stop-gallons-pill">{{ $o['gallons'] }} gal</span>
                                    @if($o['payment_status'] === 'paid')
                                        <span class="stop-payment-pill stop-payment-paid">PAID</span>
                                    @elseif($o['payment_method'] === 'credit')
                                        <span class="stop-payment-pill stop-payment-credit">DEBT</span>
                                    @else
                                        <span class="stop-payment-pill stop-payment-cash">CASH</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('admin.deliveries.routes.store') }}" class="suggested-dispatch-form">
                        @csrf
                        <input type="hidden" name="area" value="{{ $route['area'] }}">
                        @foreach($route['orders'] as $o)
                            <input type="hidden" name="order_ids[]" value="{{ $o['id'] }}">
                        @endforeach

                        <select name="driver_id" class="suggested-driver-select" required aria-label="Select Driver">
                            <option value="">— Assign Driver —</option>
                            @foreach($activeDrivers as $drv)
                                <option value="{{ $drv->id }}" {{ $route['suggested_driver_id'] == $drv->id ? 'selected' : '' }}>
                                    {{ $drv->user?->name ?? 'Driver' }} ({{ $drv->vehicle_plate ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>

                        <button type="submit" class="btn-dispatch-action">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            Dispatch Route
                        </button>
                    </form>
                </div>
            @empty
                <div class="delivery-card" style="grid-column: 1 / -1; text-align: center; padding: 36px 20px;">
                    <div style="color: #64748B; font-weight: 600;">No pending delivery routes.</div>
                    <p style="margin: 6px 0 0; font-size: 13px; color: #94A3B8;">Completed station refills will appear here for delivery assignment.</p>
                </div>
            @endforelse
        </div>

        {{-- Active Delivery Routes in Transit --}}
        <div class="suggested-section-title" style="margin-top: 20px;">
            <span>Active Delivery Routes in Transit ({{ $activeDeliveryRoutes->total() }})</span>
        </div>

        @if($activeDeliveryRoutes->isNotEmpty())
            <div class="active-mission-table-wrap">
                <table class="delivery-table" aria-label="Active Delivery Routes">
                    <thead>
                        <tr>
                            <th scope="col">Route #</th>
                            <th scope="col">Driver</th>
                            <th scope="col">Area</th>
                            <th scope="col">Gallons</th>
                            <th scope="col">Delivery Progress</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activeDeliveryRoutes as $dr)
                            @php
                                $deliveredStops = (int) $dr->delivered_stops_count;
                                $totalStops = (int) $dr->stops_count;
                                $pct = $totalStops > 0 ? round(($deliveredStops / $totalStops) * 100) : 0;
                            @endphp
                            <tr>
                                <td><strong style="color: #0066CC;">{{ $dr->route_number }}</strong></td>
                                <td>{{ $dr->driver?->user?->name ?? 'Unassigned' }} ({{ $dr->driver?->vehicle_plate ?? 'N/A' }})</td>
                                <td><span class="suggested-card-tag">{{ $dr->area }}</span></td>
                                <td>{{ $dr->total_gallons }} gal</td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div class="mission-progress-bar" style="width: 90px; height: 6px;">
                                            <div class="mission-progress-fill" style="width: {{ $pct }}%;"></div>
                                        </div>
                                        <small style="font-weight: 700;">{{ $deliveredStops }}/{{ $totalStops }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="delivery-status status-{{ $dr->status === 'in_progress' ? 'en_route' : 'assigned' }}">
                                        {{ ucwords(str_replace('_', ' ', $dr->status)) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <form method="POST" action="{{ route('admin.deliveries.routes.status', $dr->id) }}" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="delivery-action" onclick="return confirm('Complete route and confirm all stops delivered?');">Complete</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.deliveries.routes.status', $dr->id) }}" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="delivery-action secondary" onclick="return confirm('Cancel this delivery route?');">Cancel</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="delivery-card" style="text-align: center; padding: 36px 20px;">
                <div style="color: #64748B; font-weight: 600;">No active delivery routes in transit.</div>
                <p style="margin: 6px 0 0; font-size: 13px; color: #94A3B8;">Completed station refills will appear here for delivery assignment.</p>
            </div>
        @endif
        {{ $activeDeliveryRoutes->links() }}
    </div>

    {{-- ======================================================================
         TAB 3: DRIVER BOARD (TWO DRIVERS)
    ====================================================================== --}}
    <div class="dispatch-tab-pane" id="tabDriverBoard">
        <div class="dispatch-banner-callout">
            <div class="dispatch-callout-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
            </div>
            <div class="dispatch-callout-text">
                <h4>Two-Driver Fleet Management</h4>
                <p>AQUA CLAYNE operations are powered by two dedicated station drivers. Each vehicle has a strict 30-gallon capacity limit per mission. Track their live missions, current stop progression, and vehicle capacity below.</p>
            </div>
        </div>

        <div class="driver-board-grid">
            @forelse($driverBoard as $drv)
                @php
                    $statusClass = strtolower($drv['status']);
                    $pillClass = 'pill-' . $statusClass;
                    $loadPct = min(100, round(($drv['gallons_count'] / 30) * 100));
                @endphp
                <div class="driver-status-card status-{{ $statusClass }}">
                    <div class="driver-card-top">
                        <div class="driver-profile-info">
                            <div class="driver-large-avatar">
                                {{ strtoupper(mb_substr($drv['name'] ?? 'D', 0, 1)) }}
                            </div>
                            <div class="driver-meta-block">
                                <h3>{{ $drv['name'] }}</h3>
                                <div class="driver-meta-details">
                                    <span class="driver-meta-plate">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                        {{ $drv['vehicle_plate'] }}
                                    </span>
                                    <span>{{ $drv['phone'] }}</span>
                                </div>
                            </div>
                        </div>
                        <span class="driver-live-pill {{ $pillClass }}">
                            <span class="pulse-dot"></span>
                            {{ str_replace('_', ' ', $drv['status']) }}
                        </span>
                    </div>

                    {{-- Current Mission Box --}}
                    <div class="driver-mission-box">
                        <div class="driver-mission-header">
                            <span>Current Mission</span>
                            <span>{{ $drv['current_task_type'] ? strtoupper($drv['current_task_type']) : 'IDLE' }}</span>
                        </div>

                        @if($drv['current_task_code'])
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span class="driver-mission-code">{{ $drv['current_task_code'] }}</span>
                                <span class="suggested-card-tag">{{ $drv['area'] }}</span>
                            </div>

                            @php
                                $progPct = $drv['stops_count'] > 0 ? round(($drv['completed_stops'] / $drv['stops_count']) * 100) : 0;
                            @endphp
                            <div class="mission-progress-bar">
                                <div class="mission-progress-fill" style="width: {{ $progPct }}%;"></div>
                            </div>

                            <div class="driver-capacity-stat">
                                <span>Stops Progress: <strong>{{ $drv['completed_stops'] }} / {{ $drv['stops_count'] }} completed</strong></span>
                                <span>Vehicle Load: <strong>{{ $drv['gallons_count'] }} / 30 Gal</strong></span>
                            </div>
                        @else
                            <div style="color: #64748B; font-size: 13px; padding: 6px 0;">
                                No active batch or route currently assigned. Ready for dispatch.
                            </div>
                        @endif
                    </div>

                    {{-- Quick Dispatch Navigation --}}
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="delivery-action" style="flex: 1; justify-content: center;" onclick="window.dispatchTabs.switch('tabPickupDispatch')">
                            Assign Pickup
                        </button>
                        <button type="button" class="delivery-action secondary" style="flex: 1; justify-content: center;" onclick="window.dispatchTabs.switch('tabDeliveryRoutes')">
                            Assign Route
                        </button>
                    </div>
                </div>
            @empty
                <div class="delivery-card" style="grid-column: 1 / -1; text-align: center; padding: 36px 20px;">
                    <div style="color: #64748B; font-weight: 600;">No drivers registered in the station fleet.</div>
                    <p style="margin: 6px 0 0; font-size: 13px; color: #94A3B8;"><a href="{{ route('admin.drivers.index') }}">Click here to manage fleet drivers →</a></p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ======================================================================
         TAB 4: ALL DELIVERIES & HISTORY
    ====================================================================== --}}
    <div class="dispatch-tab-pane" id="tabAllDeliveries">
        <div class="delivery-content-grid">

            {{-- ===================== LEFT COLUMN ===================== --}}
            <div>

                {{-- ── Live Deliveries Panel ── --}}
                <section class="delivery-card" aria-label="Live active deliveries">
                    <div class="delivery-card-header">
                        <h2 class="delivery-card-title">
                            <span class="card-title-dot live-dot" aria-hidden="true"></span>
                            Live Deliveries
                        </h2>
                        <span class="delivery-card-meta">
                            Active Transit ({{ $liveDeliveries->count() }})
                        </span>
                    </div>

                    <div class="live-deliveries-container" id="liveDeliveriesContainer">
                        @forelse($liveDeliveries as $ld)
                            <div class="live-delivery-item">
                                <div class="live-item-top">
                                    <div class="live-ids">
                                        <span class="live-delivery-id">#{{ strtoupper(substr($ld->id, 0, 8)) }}</span>
                                        <span class="live-order-id">ORD&nbsp;#{{ $ld->order?->order_number ?? strtoupper(substr($ld->order_id, 0, 8)) }}</span>
                                    </div>
                                    <span class="delivery-status status-{{ $ld->status }}">
                                        {{ ucwords(str_replace('_', ' ', $ld->status)) }}
                                    </span>
                                </div>

                                <div class="live-customer-info">
                                    <span class="live-customer-name">{{ $ld->order?->customer?->user?->name ?? 'Customer' }}</span>
                                    <span class="live-driver-info">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                        {{ $ld->driver?->user?->name ?? 'Unassigned' }}
                                    </span>
                                    <span class="live-address">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        {{ $ld->order?->address
                                            ? trim($ld->order->address->address_line1 . ', ' . $ld->order->address->barangay)
                                            : 'Station Pickup' }}
                                    </span>
                                </div>

                                <div class="live-item-footer">
                                    <div class="live-eta">
                                        <small>ETA</small>
                                        {{ $ld->eta ? \Illuminate\Support\Carbon::parse($ld->eta)->format('g:i A') : 'Scheduled' }}
                                    </div>
                                    <button type="button" class="delivery-action btn-view-delivery"
                                            data-delivery-id="{{ $ld->id }}"
                                            aria-label="View delivery details for #{{ strtoupper(substr($ld->id, 0, 8)) }}">
                                        View Details
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="live-empty-state">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="1" y="3" width="15" height="13"></rect>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                </svg>
                                <strong>No active deliveries on route</strong>
                                <p>Deliveries in transit will appear here automatically.</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                {{-- ── Live Delivery Locations Panel (Schematic) ── --}}
                <section class="delivery-card delivery-location-panel" aria-label="Live delivery map">
    <div class="delivery-card-header">
        <h2 class="delivery-card-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            Live Delivery Locations
        </h2>
        <span class="delivery-card-meta">OpenStreetMap · Live GPS</span>
    </div>

    <div class="location-map-canvas" id="liveDeliveryMap" role="application" aria-label="Interactive map with current driver and delivery locations">
        <div class="map-loading-message" id="deliveryMapLoading">Loading live map…</div>
    </div>

    <div class="map-notice-bar">
        <span id="deliveryMapStatus" role="status">Driver locations update automatically while sharing is enabled.</span>
        <strong><a href="https://www.openstreetmap.org/fixthemap" target="_blank" rel="noopener">Report a map issue</a></strong>
    </div>
</section>

                



            </div>
            {{-- ===================== END LEFT COLUMN ===================== --}}

            {{-- ===================== RIGHT COLUMN ===================== --}}
            <div>

                {{-- ── Delivery Status Breakdown ── --}}
                <section class="delivery-card" aria-label="Delivery status breakdown">
                    <div class="delivery-card-header">
                        <h2 class="delivery-card-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                            Delivery Status
                        </h2>
                    </div>

                    <div class="status-breakdown-list">
                        <div class="status-breakdown-item">
                            <span>Pending / Unassigned</span>
                            <span class="status-breakdown-count pending" id="sbCountPending">{{ $statusCounts['pending'] }}</span>
                        </div>
                        <div class="status-breakdown-item">
                            <span>Assigned</span>
                            <span class="status-breakdown-count assigned" id="sbCountAssigned">{{ $statusCounts['assigned'] }}</span>
                        </div>
                        <div class="status-breakdown-item">
                            <span>En Route / In Transit</span>
                            <span class="status-breakdown-count en-route" id="sbCountEnRoute">{{ $statusCounts['en_route'] }}</span>
                        </div>
                        <div class="status-breakdown-item">
                            <span>Delivered</span>
                            <span class="status-breakdown-count delivered" id="sbCountDelivered">{{ $statusCounts['delivered'] }}</span>
                        </div>
                        <div class="status-breakdown-item">
                            <span>Failed / Exceptions</span>
                            <span class="status-breakdown-count failed" id="sbCountFailed">{{ $statusCounts['failed'] }}</span>
                        </div>
                    </div>

                    <div class="status-breakdown-filters">
                        <a href="{{ route('admin.deliveries.index', ['status' => 'pending']) }}" class="status-filter-link">View pending →</a>
                        <a href="{{ route('admin.deliveries.index', ['status' => 'en_route']) }}" class="status-filter-link">View en route →</a>
                    </div>
                </section>

                {{-- ── Active Fleet Overview ── --}}
                <section class="delivery-card" aria-label="Active driver fleet">
                    <div class="delivery-card-header">
                        <h2 class="delivery-card-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                            Active Fleet
                        </h2>
                        <span class="delivery-card-meta">{{ $activeDrivers->count() }} Available</span>
                    </div>

                    <p class="fleet-intro">Active drivers eligible for dispatch and gallon transport.</p>

                    <div class="fleet-driver-list">
                        @forelse($activeDrivers as $drv)
                            <div class="fleet-driver-item">
                                <div class="fleet-driver-left">
                                    <span class="cell-driver-avatar" aria-hidden="true">
                                        {{ strtoupper(mb_substr($drv->user?->name ?? 'D', 0, 1)) }}
                                    </span>
                                    <div>
                                        <strong class="fleet-driver-name">{{ $drv->user?->name ?? 'Driver' }}</strong>
                                        <span class="fleet-driver-plate">{{ $drv->vehicle_plate ?? 'N/A' }}</span>
                                    </div>
                                </div>
                                <span class="delivery-status status-delivered fleet-active-badge">Active</span>
                            </div>
                        @empty
                            <div class="fleet-empty">No active drivers registered.</div>
                        @endforelse
                    </div>

                    <div class="fleet-manage-link">
                        <a href="{{ route('admin.drivers.index') }}" class="fleet-manage-anchor">
                            Manage Fleet Drivers
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                    </div>
                </section>

                {{-- ── Refill & Delivery Workflow Guide ── --}}
                <section class="delivery-card delivery-flow-guide" aria-label="Refill logistics workflow guide">
                    <div class="delivery-card-header">
                        <h2 class="delivery-card-title guide-title">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            Refill Logistics Guide
                        </h2>
                    </div>
                    <ol class="flow-guide-list">
                        <li><strong>Pickup Batch:</strong> Two drivers collect empty gallons in batches.</li>
                        <li><strong>Intake:</strong> Station inspects and accepts customer gallons.</li>
                        <li><strong>Refill:</strong> Station staff process accepted gallons before preparing completed orders for delivery.</li>
                        <li><strong>Multi-Stop Route:</strong> Sequenced delivery with proof photo &amp; payment.</li>
                        <li><strong>Completion:</strong> Reconciled as paid online, recorded as debt, or cash collected.</li>
                    </ol>
                </section>

            </div>
            {{-- ===================== END RIGHT COLUMN ===================== --}}

        </div>
    </div>

    {{-- TAB 5: DELIVERY HISTORY --}}
    <div class="dispatch-tab-pane" id="tabDeliveryHistory">
        <div class="delivery-content-grid"><div>
<section class="delivery-card" aria-label="Recent deliveries table">
                    <div class="delivery-card-header">
                        <h2 class="delivery-card-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            Recent Deliveries
                        </h2>
                        <span class="delivery-card-meta">{{ number_format($deliveries->total()) }} Records</span>
                    </div>

                    {{-- Filter Bar --}}
                    <form method="GET" action="{{ route('admin.deliveries.index') }}#tabDeliveryHistory"
                          id="deliveryFilterForm" class="delivery-filters" role="search"
                          aria-label="Filter deliveries">

                        <div class="filter-search-wrap">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="filter-search-icon" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" name="search" id="deliverySearchInput"
                                   class="filter-input filter-search"
                                   placeholder="Search Delivery #, Order #, Customer, Driver…"
                                   value="{{ $search }}"
                                   autocomplete="off"
                                   aria-label="Search deliveries">
                        </div>

                        <select name="status" class="filter-input" aria-label="Filter by status">
                            <option value="">All Statuses</option>
                            <option value="pending"   {{ $status === 'pending'   ? 'selected' : '' }}>Pending / Unassigned</option>
                            <option value="assigned"  {{ $status === 'assigned'  ? 'selected' : '' }}>Assigned</option>
                            <option value="en_route"  {{ $status === 'en_route'  ? 'selected' : '' }}>En Route / In Transit</option>
                            <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                            <option value="failed"    {{ $status === 'failed'    ? 'selected' : '' }}>Failed</option>
                        </select>

                        <select name="driver_id" class="filter-input" aria-label="Filter by driver">
                            <option value="">All Drivers</option>
                            @foreach($allDrivers as $drv)
                                <option value="{{ $drv->id }}" {{ $driverId == $drv->id ? 'selected' : '' }}>
                                    {{ $drv->user?->name ?? 'Driver' }}
                                </option>
                            @endforeach
                        </select>

                        <input type="date" name="date" class="filter-input"
                               value="{{ $date }}" title="Filter by date"
                               aria-label="Filter by date">

                        <button type="submit" class="filter-btn-submit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                            Filter
                        </button>

                        @if($search || $status || $driverId || $date)
                            <a href="{{ route('admin.deliveries.index') }}#tabDeliveryHistory" class="filter-btn-reset"
                               title="Reset all filters" aria-label="Reset filters">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                Reset
                            </a>
                        @endif
                    </form>

                    {{-- Deliveries Table --}}
                    <div class="delivery-table-wrapper">
                        <table class="delivery-table" aria-label="Deliveries list">
                            <thead>
                                <tr>
                                    <th scope="col">Delivery #</th>
                                    <th scope="col">Order #</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Driver</th>
                                    <th scope="col">Address</th>
                                    <th scope="col">ETA</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Delivered At</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($deliveries as $deliv)
                                    <tr>
                                        <td>
                                            <span class="cell-delivery-id">#{{ strtoupper(substr($deliv->id, 0, 8)) }}</span>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.orders.show', $deliv->order_id) }}"
                                               class="cell-order-link"
                                               title="View order details">
                                                ORD&nbsp;#{{ $deliv->order?->order_number ?? strtoupper(substr($deliv->order_id, 0, 8)) }}
                                            </a>
                                        </td>
                                        <td>
                                            <div class="cell-user-wrap">
                                                <span class="cell-customer-avatar" aria-hidden="true">{{ strtoupper(mb_substr($deliv->order?->customer?->user?->name ?? 'C', 0, 1)) }}</span>
                                                <span class="cell-user-meta">
                                                    <span class="cell-user-name">{{ $deliv->order?->customer?->user?->name ?? '—' }}</span>
                                                    <span class="cell-user-sub">{{ $deliv->order?->customer?->user?->phone ?? '' }}</span>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            @if($deliv->driver)
                                                <div class="cell-driver-wrap">
                                                    <span class="cell-driver-avatar" aria-hidden="true">
                                                        {{ strtoupper(mb_substr($deliv->driver->user?->name ?? 'D', 0, 1)) }}
                                                    </span>
                                                    <div>
                                                        <strong class="cell-driver-name">{{ $deliv->driver->user?->name ?? 'Driver' }}</strong>
                                                        <small class="cell-driver-plate">{{ $deliv->driver->vehicle_plate ?? '' }}</small>
                                                    </div>
                                                </div>
                                            @else
                                                <button type="button"
                                                        class="delivery-action secondary btn-open-assign-modal"
                                                        data-order-id="{{ $deliv->order_id }}"
                                                        data-delivery-id="{{ $deliv->id }}"
                                                        data-order-number="{{ $deliv->order?->order_number }}"
                                                        data-customer-name="{{ $deliv->order?->customer?->user?->name }}"
                                                        data-address="{{ $deliv->order?->address ? trim($deliv->order->address->address_line1 . ', ' . $deliv->order->address->barangay) : '' }}"
                                                        aria-label="Assign driver to this delivery">
                                                    + Assign Driver
                                                </button>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="cell-address">
                                                {{ $deliv->order?->address
                                                    ? trim($deliv->order->address->address_line1 . ', ' . $deliv->order->address->barangay)
                                                    : 'Station Pickup' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="cell-eta">
                                                {{ $deliv->eta
                                                    ? \Illuminate\Support\Carbon::parse($deliv->eta)->format('g:i A')
                                                    : '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="delivery-status status-{{ $deliv->status }}">
                                                {{ ucwords(str_replace('_', ' ', $deliv->status)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="cell-delivered-at">
                                                {{ $deliv->delivered_at
                                                    ? \Illuminate\Support\Carbon::parse($deliv->delivered_at)->format('M d, g:i A')
                                                    : '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="cell-actions">
                                                <button type="button"
                                                        class="delivery-action btn-view-delivery"
                                                        data-delivery-id="{{ $deliv->id }}"
                                                        aria-label="View delivery #{{ strtoupper(substr($deliv->id, 0, 8)) }}">
                                                    View
                                                </button>
                                                <a href="{{ route('admin.deliveries.show', $deliv->id) }}"
                                                   class="delivery-action secondary"
                                                   title="Open full delivery page">
                                                    ↗
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9">
                                            <div class="table-empty-state">
                                                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <rect x="1" y="3" width="15" height="13"></rect>
                                                    <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                                                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                                </svg>
                                                <strong>No deliveries found</strong>
                                                <p>There are currently no deliveries matching your filters.</p>
                                                @if($search || $status || $driverId || $date)
                                                    <a href="{{ route('admin.deliveries.index') }}" class="delivery-action secondary" style="margin-top:8px;">Clear Filters</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($deliveries->hasPages())
                        <div class="delivery-pagination">
                            {{ $deliveries->links() }}
                        </div>
                    @endif

                </section>

            @foreach([
                ['title' => 'Archive Deliveries', 'items' => $archivedDeliveries],
            ] as $historySection)
                <section class="delivery-card" style="margin-bottom:22px;" aria-label="{{ $historySection['title'] }}">
                    <div class="delivery-card-header">
                        <h2 class="delivery-card-title">{{ $historySection['title'] }}</h2>
                        <span class="delivery-card-meta">{{ number_format($historySection['items']->total()) }} records · 5 per page</span>
                    </div>
                    <div class="delivery-table-wrapper">
                        <table class="delivery-table">
                            <thead><tr><th>Delivery #</th><th>Order #</th><th>Customer</th><th>Driver</th><th>Address</th><th>Completed At</th><th>Actions</th></tr></thead>
                            <tbody>
                                @forelse($historySection['items'] as $deliv)
                                    <tr>
                                        <td><span class="cell-delivery-id">#{{ strtoupper(substr($deliv->id, 0, 8)) }}</span></td>
                                        <td><a href="{{ route('admin.orders.show', $deliv->order_id) }}" class="cell-order-link">ORD&nbsp;#{{ $deliv->order?->order_number ?? strtoupper(substr($deliv->order_id, 0, 8)) }}</a></td>
                                        <td><div class="cell-user-wrap"><span class="cell-user-name">{{ $deliv->order?->customer?->user?->name ?? '—' }}</span><span class="cell-user-sub">{{ $deliv->order?->customer?->user?->phone ?? '' }}</span></div></td>
                                        <td>{{ $deliv->driver?->user?->name ?? 'Unassigned' }}</td>
                                        <td><span class="cell-address">{{ $deliv->order?->address ? trim($deliv->order->address->address_line1 . ', ' . $deliv->order->address->barangay) : 'Station Pickup' }}</span></td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($deliv->delivered_at ?? $deliv->created_at)->format('M d, Y g:i A') }}</td>
                                        <td><button type="button" class="delivery-action btn-view-delivery" data-delivery-id="{{ $deliv->id }}">View</button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7"><div class="table-empty-state"><strong>No {{ strtolower($historySection['title']) }} yet</strong><p>Completed deliveries will appear here automatically.</p></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($historySection['items']->hasPages())
                        <div class="delivery-pagination">{{ $historySection['items']->links() }}</div>
                    @endif
                </section>
            @endforeach
        </div></div>
                {{-- ── Completed Batches & Routes History Logs ── --}}
                @if($historyBatches->isNotEmpty() || $historyRoutes->isNotEmpty())
                    <section class="delivery-card" style="margin-top: 24px;" aria-label="Dispatch History">
                        <div class="delivery-card-header">
                            <h2 class="delivery-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                                Completed Batch &amp; Route History
                            </h2>
                        </div>
                        <div class="delivery-table-wrapper">
                            <table class="delivery-table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Code</th>
                                        <th>Driver</th>
                                        <th>Area</th>
                                        <th>Gallons</th>
                                        <th>Status</th>
                                        <th>Completed At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($historyBatches as $hb)
                                        <tr>
                                            <td><span class="suggested-card-tag" style="background:#FEF3C7;color:#92400E;">Pickup Batch</span></td>
                                            <td><strong>{{ $hb->batch_number }}</strong></td>
                                            <td>{{ $hb->driver?->user?->name ?? 'Driver' }}</td>
                                            <td>{{ $hb->area }}</td>
                                            <td>{{ $hb->total_collected_gallons }} gal</td>
                                            <td><span class="delivery-status status-delivered">{{ ucfirst($hb->status) }}</span></td>
                                            <td>{{ $hb->completed_at ? \Illuminate\Support\Carbon::parse($hb->completed_at)->format('M d, g:i A') : '—' }}</td>
                                        </tr>
                                    @endforeach
                                    @foreach($historyRoutes as $hr)
                                        <tr>
                                            <td><span class="suggested-card-tag" style="background:#EDE9FE;color:#5B21B6;">Delivery Route</span></td>
                                            <td><strong>{{ $hr->route_number }}</strong></td>
                                            <td>{{ $hr->driver?->user?->name ?? 'Driver' }}</td>
                                            <td>{{ $hr->area }}</td>
                                            <td>{{ $hr->total_gallons }} gal</td>
                                            <td><span class="delivery-status status-delivered">{{ ucfirst($hr->status) }}</span></td>
                                            <td>{{ $hr->completed_at ? \Illuminate\Support\Carbon::parse($hr->completed_at)->format('M d, g:i A') : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

    </div>

</div>

<script>
    // Tab switching handler with hash support
    window.dispatchTabs = {
        switch: function(targetTabId) {
            document.querySelectorAll('.dispatch-tab-btn').forEach(btn => {
                if (btn.dataset.tab === targetTabId) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            document.querySelectorAll('.dispatch-tab-pane').forEach(pane => {
                if (pane.id === targetTabId) {
                    pane.classList.add('active');
                } else {
                    pane.classList.remove('active');
                }
            });
            if (history.replaceState) {
                history.replaceState(null, null, '#' + targetTabId);
            }
            window.dispatchEvent(new Event('delivery-tab-change'));
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        const tabBtns = document.querySelectorAll('.dispatch-tab-btn');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                window.dispatchTabs.switch(this.dataset.tab);
            });
        });

        const currentHash = window.location.hash ? window.location.hash.substring(1) : null;
        if (currentHash && document.getElementById(currentHash)) {
            window.dispatchTabs.switch(currentHash);
        }
    });
</script>

{{-- ============================================================
     MODALS
============================================================ --}}

{{-- ── Assign Driver Modal ── --}}
@php($availableAssignDrivers = $activeDrivers->filter(fn ($driver) => (int) $driver->active_deliveries_count < 4)->values())
<div class="delivery-modal-backdrop assign-driver-backdrop modal-backdrop" id="assignDriverModal"
     role="dialog" aria-modal="true" aria-labelledby="assignDriverModalTitle">
    <div class="delivery-modal modal assign-driver-modal">

        <div class="modal-header assign-modal-header">
            <div class="modal-title-block assign-modal-heading">
                <span class="assign-title-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v11H3zM14 11h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/><path d="M5 10h6m-6 3h6"/></svg>
                </span>
                <div>
                    <h3 class="modal-title" id="assignDriverModalTitle">Assign Driver</h3>
                    <p class="modal-subtitle">Aqua Clayne purified water delivery and gallon pickup</p>
                </div>
            </div>
            <button type="button" class="modal-close assign-close-btn" aria-label="Close assign driver modal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form id="assignDriverForm" class="assign-driver-form" novalidate>
            @csrf
            <input type="hidden" name="order_id"    id="assignModalOrderId"    value="">
            <input type="hidden" name="delivery_id" id="assignModalDeliveryId" value="">

            <div class="modal-body assign-modal-content">

                {{-- Error Banner --}}
                <div class="modal-error-banner assign-error-banner" id="assignModalError" style="display:none;" role="alert">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span id="assignModalErrorText"></span>
                </div>

                {{-- Order selector (when opened without a specific order context) --}}
                <div class="form-group assign-panel" id="assignModalOrderSelectWrap">
                    <div class="assign-panel-heading">
                        <span class="assign-panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></span>
                        <h4>Order Awaiting Assignment</h4>
                    </div>
                    <label class="assign-sr-only" for="assignModalOrderSelect">Select a confirmed order</label>
                    <select id="assignModalOrderSelect" class="form-control assign-driver-select assign-order-select" {{ $assignableOrders->isEmpty() ? 'disabled' : '' }}
                            aria-label="Select order to assign">
                        <option value="">— Select a confirmed order —</option>
                        @foreach($assignableOrders as $ao)
                            <option value="{{ $ao->id }}"
                                    data-delivery-id="{{ $ao->delivery?->id ?? '' }}"
                                    data-order-number="{{ $ao->order_number }}"
                                    data-customer="{{ $ao->customer?->user?->name ?? 'Customer' }}"
                                    data-address="{{ $ao->address?->address_line ?? 'Station Service Area' }}"
                                    data-gallons="{{ $ao->expected_empty_gallons ?: 1 }}">
                                #{{ $ao->order_number }} · {{ $ao->customer?->user?->name ?? 'Customer' }}
                                ({{ ucwords(str_replace('_', ' ', $ao->status)) }})
                            </option>
                        @endforeach
                    </select>
                    @if($assignableOrders->isEmpty())
                        <small class="form-hint hint-warn assign-order-empty">ⓘ No confirmed orders currently awaiting driver assignment.</small>
                    @else
                        <small class="form-hint assign-helper-text">Select a confirmed customer water refill order.</small>
                    @endif
                </div>

                {{-- Order info display (when opened for specific delivery) --}}
                <div class="modal-info-box assign-panel assign-delivery-order-info" id="assignModalOrderInfoWrap" style="display:none;">
                    <div class="assign-panel-heading"><span class="assign-panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.7 18 8.8a8 8 0 1 1-12 0L12 2.7Z"/></svg></span><h4>Selected Water Refill Order</h4></div>
                    <div id="assignModalOrderDisplay" class="modal-info-order-num"></div>
                    <div id="assignModalCustomerDisplay" class="modal-info-customer"></div>
                    <div id="assignModalAddressDisplay" class="modal-info-address"></div>
                    <div class="assign-delivery-order-meta"><span><small>Gallon quantity</small><b id="assignModalGallonsDisplay">—</b></span><span><small>Service</small><b id="assignModalServiceDisplay">—</b></span></div>
                    <div class="assign-rfid-card" id="assignModalRfidCard" hidden>
                        <span class="assign-rfid-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7a7 7 0 0 0 0 10M10 9.5a3.5 3.5 0 0 0 0 5M4 4a11 11 0 0 0 0 16M13 12h.01"/><path d="M16 5v14h5V5z"/></svg></span>
                        <div class="assign-rfid-copy"><strong>RFID Gallon</strong><span>UID: <b id="assignModalRfidUid"></b></span><span>Status: <b id="assignModalRfidStatus"></b></span></div>
                        <span class="assign-rfid-customer">Customer: <b id="assignModalRfidCustomer"></b></span>
                    </div>
                </div>

                {{-- Driver selector — active drivers only --}}
                <div class="form-group assign-panel assign-driver-panel">
                    <div class="assign-panel-heading assign-driver-heading">
                        <span class="assign-panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg></span>
                        <h4>Select Active Driver</h4>
                        <span class="form-label-required assign-required-badge">Required</span>
                    </div>
                    <label class="assign-sr-only" for="assignModalDriverSelect">Choose an active and available Aqua Clayne driver</label>
                    <select name="driver_id" id="assignModalDriverSelect"
                            class="form-control assign-driver-select" required {{ $availableAssignDrivers->isEmpty() ? 'disabled' : '' }}
                            aria-label="Select active driver for assignment">
                        <option value="">— Choose an available driver —</option>
                        @foreach($availableAssignDrivers as $drv)
                            <option value="{{ $drv->id }}"
                                    data-name="{{ $drv->user?->name ?? 'Driver' }}"
                                    data-avatar=""
                                    data-plate="{{ $drv->vehicle_plate ?? 'N/A' }}"
                                    data-phone="{{ $drv->user?->phone ?? 'N/A' }}"
                                    data-deliveries="{{ $drv->deliveries?->count() ?? 0 }}">
                                {{ $drv->user?->name ?? 'Driver' }}
                                · Plate: {{ $drv->vehicle_plate ?? 'N/A' }}
                                ({{ $drv->deliveries?->count() ?? 0 }} active deliveries)
                            </option>
                        @endforeach
                    </select>
                    @if($availableAssignDrivers->isEmpty())
                        <small class="form-hint hint-warn assign-order-empty">No active and available Aqua Clayne station drivers right now.</small>
                    @else
                        <small class="form-hint assign-helper-text">Only active and available Aqua Clayne station drivers are eligible for dispatch.</small>
                    @endif
                </div>

                {{-- Driver workload preview card --}}
                <div class="driver-preview-card assign-driver-info" id="driverPreviewCard" style="display:none;" aria-live="polite">
                    <div class="driver-preview-head">
                        <span class="driver-preview-avatar" id="driverPreviewAvatar" aria-hidden="true">DR</span>
                        <div class="driver-preview-info">
                            <strong id="driverPreviewName">Driver Name</strong>
                            <span id="driverPreviewPhone" class="driver-preview-phone"></span>
                        </div>
                        <span class="driver-preview-badge" id="driverPreviewBadge">Available</span>
                    </div>
                    <div class="driver-preview-details">
                        <span class="driver-preview-detail">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            Plate: <strong id="driverPreviewPlate"></strong>
                        </span>
                        <span class="driver-preview-detail">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line></svg>
                            <strong id="driverPreviewDeliveries"></strong> active deliveries
                        </span>
                    </div>
                </div>

                {{-- ETA input --}}
                <div class="form-group assign-panel assign-eta-panel">
                    <div class="assign-panel-heading"><span class="assign-panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><h4>Estimated Delivery Time (ETA)</h4></div>
                    <div class="assign-eta-input-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <input type="text" name="eta" id="assignModalEtaInput"
                           class="form-control"
                           placeholder="e.g. 10:45 AM or In 30 mins"
                           aria-describedby="etaHint"></div>
                    <small class="form-hint assign-helper-text" id="etaHint">Optional — driver can update the water delivery ETA from the mobile app.</small>
                </div>

                <ol class="assign-workflow" aria-label="Aqua Clayne delivery workflow">
                    <li><span>1</span><b>Confirmed Order</b></li>
                    <li class="is-current"><span>2</span><b>Driver Assigned</b></li>
                    <li><span>3</span><b>Gallon Pickup</b></li>
                    <li><span>4</span><b>Out for Delivery</b></li>
                    <li><span>5</span><b>Delivered</b></li>
                </ol>
            </div>

            <div class="modal-footer assign-modal-footer">
                <button type="button" class="delivery-action secondary assign-cancel-btn btn-cancel-modal"
                        id="assignModalCancelBtn">Cancel</button>
                <button type="submit" class="delivery-action assign-submit-btn" id="assignModalSubmitBtn" disabled>
                    <svg class="assign-submit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/></svg>
                    <span class="btn-spinner" id="assignSpinner" aria-hidden="true"></span>
                    <span id="assignBtnText">Assign Driver</span>
                </button>
            </div>
        </form>

    </div>
</div>

{{-- ── Delivery Details Modal ── --}}
<div class="delivery-modal-backdrop" id="deliveryDetailsModal"
     role="dialog" aria-modal="true" aria-labelledby="deliveryDetailsModalTitle">
    <div class="delivery-modal modal-large">

        <div class="modal-header">
            <div class="modal-title-block">
                <h3 class="modal-title" id="deliveryDetailsModalTitle">Delivery Details</h3>
                <p class="modal-subtitle">Full delivery lifecycle &amp; verification</p>
            </div>
            <button type="button" class="modal-close" aria-label="Close delivery details modal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        {{-- Content dynamically injected by DeliveriesDashboard.viewDelivery() --}}
        <div class="modal-body" id="deliveryDetailsBody">
            <div class="modal-loading-state">
                <div class="modal-spinner" aria-label="Loading delivery details"></div>
                <span>Loading delivery details…</span>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="delivery-action secondary btn-cancel-modal">Close</button>
            <a href="#" class="delivery-action" id="deliveryDetailsFullPageBtn" target="_blank"
               style="display:none;">Open Full Page ↗</a>
        </div>
    </div>
</div>

@endsection
