@extends('admin.layouts.app')

@php
    $orderNum = $order->order_number ?: ('ORD-' . strtoupper(substr($order->id, 0, 8)));
    $statusSlug = strtolower(str_replace('_', '-', $order->status));
    $customerName = $order->customer?->user?->name ?? 'Customer';
    $customerPhone = $order->customer?->user?->phone ?? 'N/A';
    $customerEmail = $order->customer?->user?->email ?? 'N/A';
    $addressLine = $order->address?->address_line ?? 'Brgy. Salis, Station Service Area';
    $quantity = $order->items->sum('quantity') ?: ($order->expected_empty_gallons ?: 1);
    $serviceName = $order->items->first()?->product?->name ?? '5-Gallon Purified Water Refill';
    $driver = $order->delivery?->driver;
    $driverUser = $driver?->user;
@endphp

@section('title', 'Order ' . $orderNum)
@section('header-title', 'Order Details')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-orders.css') }}?v=20261006-15">
@endpush

@push('scripts')
    <script src="{{ asset('js/admin-orders.js') }}?v=20261006-4" defer></script>
@endpush

@section('content')

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer" aria-live="polite"></div>

    <!-- ===================================================================
         1. ORDER HEADER & STATUS ACTION BAR
    =================================================================== -->
    <div class="orders-show-header">
        <div class="show-header-left">
            <a href="{{ route('admin.orders.index') }}" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <span>Back to Orders</span>
            </a>

            <div class="show-title-wrap">
                <h1 class="show-order-title">{{ $orderNum }}</h1>
                <span class="status-badge status-{{ $statusSlug }}">
                    <span class="status-dot" aria-hidden="true"></span>
                    <span>{{ ucwords(str_replace('_', ' ', $order->status)) }}</span>
                </span>
                @if($order->isStationPickup())
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:600; padding:4px 10px; border-radius:9999px; font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                        🏪 Station Pickup
                    </span>
                @else
                    <span class="badge" style="background:#f0fdf4; color:#15803d; font-weight:600; padding:4px 10px; border-radius:9999px; font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                        🛵 Home Delivery
                    </span>
                @endif
            </div>

            <p class="show-subtitle">
                Placed on {{ $order->created_at ? $order->created_at->format('F d, Y \a\t g:i A') : 'Recently' }} &bull; Customer Refill Service
            </p>
        </div>

        <!-- Dynamic Action Buttons depending on canonical order status -->
        <div class="show-header-actions">
            
            @if(strtolower($order->status) === 'pending')
                <!-- CONFIRM ORDER ACTION -->
                <form method="POST" action="{{ route('admin.orders.transition', $order->id) }}" class="inline-action-form">
                    @csrf
                    <input type="hidden" name="status" value="confirmed">
                    <button type="submit" class="btn-action-primary" onclick="return confirm('Confirm order {{ $orderNum }} and verify customer refill request?');">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Confirm Order</span>
                    </button>
                </form>

                <!-- REJECT ORDER ACTION -->
                <form method="POST" action="{{ route('admin.orders.transition', $order->id) }}" class="inline-action-form">
                    @csrf
                    <input type="hidden" name="status" value="rejected">
                    <input type="hidden" name="reason" value="Rejected by administrator">
                    <button type="submit" class="btn-action-danger" onclick="return confirm('Are you sure you want to reject this order?');">
                        <span>Reject Order</span>
                    </button>
                </form>

            @elseif(strtolower($order->status) === 'confirmed')
                @if(!$order->isStationPickup())
                    <!-- ASSIGN DRIVER PROMINENT ACTION -->
                    <button type="button" class="btn-action-primary btn-assign-driver"
                            data-order-id="{{ $order->id }}"
                            data-order-num="{{ $orderNum }}"
                            data-customer="{{ $customerName }}"
                            data-address="{{ $addressLine }}"
                            data-gallons="{{ $quantity }}"
                            data-service="{{ $serviceName }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                        </svg>
                        <span>Assign Driver</span>
                    </button>
                @else
                    <!-- QUEUE FOR STATION REFILL ACTION -->
                    <a href="{{ route('admin.refill-queue.index') }}" class="btn-action-primary">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        <span>Station Refill Queue</span>
                    </a>
                @endif

            @elseif(strtolower($order->status) === 'ready_for_pickup')
                <!-- COMPLETE PICKUP ACTION -->
                <form method="POST" action="{{ route('admin.orders.transition', $order->id) }}" class="inline-action-form">
                    @csrf
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="btn-action-primary" style="background:#059669;" onclick="return confirm('Complete customer pickup for order {{ $orderNum }}? This will finalize settlement and mark order completed.');">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Complete Pickup</span>
                    </button>
                </form>

            @elseif(in_array(strtolower($order->status), ['assigned', 'picked_up', 'out_for_delivery', 'delivered']) && $order->delivery)
                <!-- VIEW DELIVERY MODULE -->
                <a href="{{ route('admin.deliveries.show', $order->delivery->id) }}" class="btn-action-secondary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                    <span>View Delivery Assignment</span>
                </a>

            @elseif(in_array(strtolower($order->status), ['received_at_station', 'waiting_in_queue', 'refilling']))
                <!-- VIEW STATION REFILL QUEUE -->
                <a href="{{ route('admin.refill-queue.index') }}" class="btn-action-secondary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    <span>Station Refill Queue</span>
                </a>
            @endif

        </div>
    </div>

    <!-- Intake Discrepancy Banner if active -->
    @if($order->containerIntake && ($order->containerIntake->expected_count != $order->containerIntake->received_count || $order->containerIntake->rejected_count > 0))
        <aside class="show-alert-banner banner-danger" role="alert">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <div>
                <strong>INTAKE DISCREPANCY DETECTED:</strong>
                <span>Expected {{ $order->containerIntake->expected_count }} gallons from customer, but received {{ $order->containerIntake->received_count }} gallons ({{ $order->containerIntake->damaged_count }} damaged / rejected). Please resolve with customer before station refill.</span>
            </div>
        </aside>
    @endif

    <!-- ===================================================================
         2. TWO-COLUMN DETAILS GRID
    =================================================================== -->
    <div class="show-details-grid">
        
        <!-- ===============================================================
             LEFT COLUMN: CUSTOMER, CONTAINER, INTAKE & PAYMENT
        =============================================================== -->
        <div class="show-col-left">

            <!-- Card 1: Customer Information -->
            <article class="orders-card show-card">
                <div class="show-card-head">
                    <h3 class="show-card-title">Customer Information</h3>
                    <span class="head-badge">Verified Customer</span>
                </div>

                <div class="customer-profile-box">
                    <div class="customer-avatar-large" aria-hidden="true">
                        @include('admin.partials.avatar-content', ['user' => $order->customer?->user, 'fallbackName' => $customerName])
                    </div>
                    <div class="customer-meta-wrap">
                        <strong class="profile-name">{{ $customerName }}</strong>
                        <span class="profile-contact">{{ $customerPhone }} &bull; {{ $customerEmail }}</span>
                    </div>
                </div>

                <div class="show-row-grid">
                    <div class="show-row">
                        <span class="show-label">{{ $order->isStationPickup() ? 'Pickup Location' : 'Delivery Address' }}</span>
                        <strong class="show-val">
                            @if($order->isStationPickup())
                                🏪 Aqua Clayne Water Refilling Station<br>
                                <span style="font-weight: normal; color: var(--text-secondary); font-size: 13px;">Batisan, Gatid, Santa Cruz, Laguna (14.265111, 121.405602)</span>
                            @else
                                {{ $addressLine }}
                            @endif
                        </strong>
                    </div>
                    <div class="show-row">
                        <span class="show-label">Customer ID</span>
                        <span class="show-val font-mono">{{ $order->customer_id }}</span>
                    </div>
                </div>
            </article>

            <!-- Card 2: Refill & Container Specifications -->
            <article class="orders-card show-card">
                <div class="show-card-head">
                    <h3 class="show-card-title">Refill &amp; Container Specifications</h3>
                    <span class="badge-customer-owned">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                        </svg>
                        <span>Customer Owned</span>
                    </span>
                </div>

                <div class="container-notice-box">
                    <strong>Customer-Owned Gallon Refill Service:</strong>
                    <p>This order is for purified drinking water refilling. Gallon containers belong to the customer and do not represent sales inventory.</p>
                </div>

                <div class="show-row-grid">
                    <div class="show-row">
                        <span class="show-label">Service Offering</span>
                        <strong class="show-val">{{ $serviceName }}</strong>
                    </div>
                    <div class="show-row">
                        <span class="show-label">Refill Quantity</span>
                        <strong class="show-val text-primary">{{ $quantity }} gallons</strong>
                    </div>
                    <div class="show-row">
                        <span class="show-label">Expected Empty Containers</span>
                        <span class="show-val">{{ $order->expected_empty_gallons ?: $quantity }} containers</span>
                    </div>
                    @if($order->notes)
                        <div class="show-row">
                            <span class="show-label">Customer Instructions</span>
                            <span class="show-val">{{ $order->notes }}</span>
                        </div>
                    @endif
                </div>
            </article>

            <!-- Card 3: Physical Station Intake Verification -->
            @if($order->containerIntake)
                <article class="orders-card show-card">
                    <div class="show-card-head">
                        <h3 class="show-card-title">Station Container Intake Verification</h3>
                        <span class="head-badge">Intake Recorded</span>
                    </div>

                    <div class="intake-counts-grid">
                        <div class="intake-count-box">
                            <span class="box-label">EXPECTED</span>
                            <strong>{{ $order->containerIntake->expected_count }}</strong>
                        </div>
                        <div class="intake-count-box">
                            <span class="box-label">RECEIVED</span>
                            <strong>{{ $order->containerIntake->received_count }}</strong>
                        </div>
                        <div class="intake-count-box is-success">
                            <span class="box-label">ACCEPTED</span>
                            <strong>{{ $order->containerIntake->accepted_count }}</strong>
                        </div>
                        <div class="intake-count-box {{ $order->containerIntake->damaged_count > 0 ? 'is-danger' : '' }}">
                            <span class="box-label">DAMAGED</span>
                            <strong>{{ $order->containerIntake->damaged_count }}</strong>
                        </div>
                    </div>

                    <div class="show-row-grid" style="margin-top: 1rem;">
                        <div class="show-row">
                            <span class="show-label">Received At Station</span>
                            <span class="show-val">{{ $order->containerIntake->received_at ? $order->containerIntake->received_at->format('M d, Y g:i A') : 'N/A' }}</span>
                        </div>
                        <div class="show-row">
                            <span class="show-label">Intake Verified By</span>
                            <span class="show-val">{{ $order->containerIntake->receivedBy?->name ?? 'Station Staff' }}</span>
                        </div>
                        @if($order->containerIntake->condition_notes)
                            <div class="show-row">
                                <span class="show-label">Condition Notes</span>
                                <span class="show-val">{{ $order->containerIntake->condition_notes }}</span>
                            </div>
                        @endif
                    </div>
                </article>
            @endif

            <!-- Card 4: Payment Information (Cash & Customer Debt only) -->
            <article class="orders-card show-card">
                <div class="show-card-head">
                    <h3 class="show-card-title">Payment Information</h3>
                    <span class="head-badge">{{ ucfirst($order->payment_method ?? 'cash') }}</span>
                </div>

                <div class="show-row-grid">
                    <div class="show-row">
                        <span class="show-label">Payment Method</span>
                        <strong class="show-val">
                            @if($order->payment_method === 'online')
                                <span style="color: #0EA5E9;">PayMongo Online Payment</span>
                            @elseif($order->payment_method === 'credit')
                                <span>Customer Debt Account</span>
                            @else
                                <span>{{ $order->isStationPickup() ? 'Cash on Pickup (COP)' : 'Cash on Delivery (COD)' }}</span>
                            @endif
                        </strong>
                    </div>
                    <div class="show-row">
                        <span class="show-label">Items Subtotal</span>
                        <span class="show-val">₱{{ number_format(max(0, $order->total_amount - ($order->delivery_fee ?? ($order->isStationPickup() ? 0 : 30))), 2) }}</span>
                    </div>
                    <div class="show-row">
                        <span class="show-label">Delivery Fee</span>
                        <span class="show-val">
                            @if($order->isStationPickup() || ($order->delivery_fee ?? 30) == 0)
                                <span style="color: #059669; font-weight: 600;">FREE (Station Pickup)</span>
                            @else
                                ₱{{ number_format($order->delivery_fee ?? 30, 2) }}
                            @endif
                        </span>
                    </div>
                    <div class="show-row">
                        <span class="show-label">Total Amount</span>
                        <strong class="show-val amount-val-large">₱{{ number_format($order->total_amount, 2) }}</strong>
                    </div>
                    <div class="show-row">
                        <span class="show-label">Settlement Status</span>
                        @if($order->payment && strtolower($order->payment->status) === 'paid')
                            <span class="payment-badge is-paid">Paid &bull; Settled</span>
                        @elseif($order->payment && strtolower($order->payment->status) === 'failed')
                            <span class="payment-badge is-danger">Payment Failed</span>
                        @else
                            <span class="payment-badge is-pending">Pending Settlement</span>
                        @endif
                    </div>
                    @if($order->payment?->reference_number)
                        <div class="show-row">
                            <span class="show-label">Transaction Ref</span>
                            <span class="show-val" style="font-family: monospace; font-size: 12px;">{{ $order->payment->reference_number }}</span>
                        </div>
                    @endif
                    @if($order->payment && $order->payment->paid_at)
                        <div class="show-row">
                            <span class="show-label">Paid At</span>
                            <span class="show-val">{{ $order->payment->paid_at->format('M d, Y g:i A') }}</span>
                        </div>
                    @endif
                </div>
            </article>

        </div>

        <!-- ===============================================================
             RIGHT COLUMN: DRIVER ASSIGNMENT & CHRONOLOGICAL TIMELINE
        =============================================================== -->
        <div class="show-col-right">

            @if($order->isStationPickup())
                <!-- Card 5 (Alternative): Station Pickup Fulfillment -->
                <article class="orders-card show-card">
                    <div class="show-card-head">
                        <h3 class="show-card-title">Station Pickup Fulfillment</h3>
                        <span class="head-badge" style="background:#e0f2fe; color:#0369a1;">Customer Walk-In</span>
                    </div>

                    <div style="background: var(--surface-soft, #f8fafc); border-radius: 8px; padding: 14px; margin-bottom: 1rem; border-left: 4px solid #0284c7;">
                        <strong style="color: var(--text-primary); font-size: 13.5px; display: block; margin-bottom: 4px;">
                            Aqua Clayne Refilling Station
                        </strong>
                        <p style="margin: 0; font-size: 12.5px; color: var(--text-secondary); line-height: 1.5;">
                            Batisan, Gatid, Santa Cruz, Laguna<br>
                            Station Coordinates: 14.265111, 121.405602<br>
                            Operating Hours: Mon - Sat: 7:00 AM - 6:00 PM | Sun: 8:00 AM - 12:00 PM
                        </p>
                    </div>

                    <div class="show-row-grid">
                        <div class="show-row">
                            <span class="show-label">Fulfillment Status</span>
                            @if(strtolower($order->status) === 'ready_for_pickup')
                                <span class="status-badge" style="background:#ecfdf5; color:#059669; font-weight:700;">
                                    Ready for Customer Pickup
                                </span>
                            @elseif(strtolower($order->status) === 'completed')
                                <span class="status-badge status-completed">Picked Up &bull; Completed</span>
                            @else
                                <span class="status-badge status-{{ $statusSlug }}">
                                    Station Processing ({{ ucwords(str_replace('_', ' ', $order->status)) }})
                                </span>
                            @endif
                        </div>
                        <div class="show-row">
                            <span class="show-label">Driver Routing</span>
                            <span class="show-val" style="color: var(--text-secondary);">Not required (Customer collects at station)</span>
                        </div>
                        @if(strtolower($order->status) === 'ready_for_pickup')
                            <div class="show-row" style="margin-top: 10px;">
                                <form method="POST" action="{{ route('admin.orders.transition', $order->id) }}" style="width: 100%;">
                                    @csrf
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn-action-primary" style="width: 100%; justify-content: center; background:#059669;" onclick="return confirm('Confirm customer order pickup and mark order completed?');">
                                        ✓ Complete Customer Pickup
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </article>
            @else
                <!-- Card 5: Driver & Delivery Assignment -->
                <article class="orders-card show-card">
                    <div class="show-card-head">
                        <h3 class="show-card-title">Driver Assignment</h3>
                        @if($driverUser)
                            <span class="head-badge is-assigned">Assigned</span>
                        @else
                            <span class="head-badge is-unassigned">Awaiting Driver</span>
                        @endif
                    </div>

                    @if($driverUser)
                        <div class="driver-assigned-profile">
                            <div class="driver-avatar-lg">
                                @include('admin.partials.avatar-content', ['user' => $driverUser, 'fallbackName' => $driverUser->name])
                            </div>
                            <div class="driver-meta-wrap">
                                <strong class="driver-assigned-name">{{ $driverUser->name }}</strong>
                                <span class="driver-assigned-phone">{{ $driverUser->phone ?? 'Active Driver' }}</span>
                                <span class="driver-plate-pill">Vehicle: {{ $driver->vehicle_plate ?: 'Tricycle' }}</span>
                            </div>
                        </div>

                        @if($order->delivery)
                            <div class="show-row-grid" style="margin-top: 1rem;">
                                <div class="show-row">
                                    <span class="show-label">Delivery Status</span>
                                    <span class="status-badge status-{{ strtolower(str_replace('_', '-', $order->delivery->status)) }}">
                                        {{ ucwords(str_replace('_', ' ', $order->delivery->status)) }}
                                    </span>
                                </div>
                                @if($order->delivery->delivered_at)
                                    <div class="show-row">
                                        <span class="show-label">Delivered At</span>
                                        <span class="show-val">{{ $order->delivery->delivered_at->format('M d, Y g:i A') }}</span>
                                    </div>
                                @endif
                                @if($order->delivery->proof_photo_url)
                                    <div class="show-row">
                                        <span class="show-label">Proof of Delivery</span>
                                        <a href="{{ $order->delivery->proof_photo_url }}" target="_blank" class="proof-link">View Proof Photo &rarr;</a>
                                    </div>
                                @endif
                            </div>
                        @endif

                    @else
                        <!-- Unassigned State with Prominent Call to Action if Confirmed -->
                        <div class="unassigned-state-box">
                            <div class="unassigned-icon" aria-hidden="true">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                                </svg>
                            </div>
                            <strong>No Driver Assigned</strong>
                            <p>Order has not yet been assigned to a delivery driver.</p>

                            @if(strtolower($order->status) === 'confirmed')
                                <button type="button" class="btn-assign-driver" style="margin-top: 0.85rem;"
                                        data-order-id="{{ $order->id }}"
                                        data-order-num="{{ $orderNum }}"
                                        data-customer="{{ $customerName }}"
                                        data-address="{{ $addressLine }}"
                                        data-gallons="{{ $quantity }}"
                                        data-service="{{ $serviceName }}">
                                    <span>Assign Driver Now</span>
                                </button>
                            @endif
                        </div>
                    @endif
                </article>
            @endif

            <!-- RFID gallon scan audit trail -->
            <article class="orders-card show-card">
                <div class="show-card-head">
                    <h3 class="show-card-title">RFID Gallon Scans</h3>
                    <span class="head-badge">{{ $order->rfidTransactions->count() }} recorded</span>
                </div>
                @forelse($order->rfidTransactions as $scan)
                    <div class="show-row-grid" style="margin-top: .65rem;">
                        <div class="show-row"><span class="show-label">Tag UID</span><span class="show-val">{{ $scan->rfidTag?->tag_uid ?? 'Unknown tag' }}</span></div>
                        <div class="show-row"><span class="show-label">Action</span><span class="show-val">{{ ucwords(str_replace('_', ' ', $scan->action)) }}</span></div>
                        <div class="show-row"><span class="show-label">Gallon</span><span class="show-val">{{ $scan->rfidTag?->gallon?->id ?? 'N/A' }} ({{ $scan->rfidTag?->gallon?->status ?? 'status unavailable' }})</span></div>
                        <div class="show-row"><span class="show-label">Scanned by</span><span class="show-val">{{ $scan->scannedBy?->name ?? 'Unknown user' }} · {{ $scan->created_at?->format('M d, Y g:i A') ?? 'Time unavailable' }}</span></div>
                    </div>
                @empty
                    <p class="show-subtitle">No RFID scans have been recorded for this order.</p>
                @endforelse
            </article>

            <!-- Card 6: Chronological Order Lifecycle Timeline -->
            <article class="orders-card show-card">
                <div class="show-card-head">
                    <h3 class="show-card-title">Order Lifecycle Timeline</h3>
                    <span class="head-badge">Real-Time Log</span>
                </div>

                <div class="order-timeline-wrap">
                    @forelse($order->statusHistory->sortBy('created_at') as $history)
                        <div class="timeline-step is-completed">
                            <div class="timeline-marker" aria-hidden="true">
                                <span class="marker-dot"></span>
                            </div>
                            <div class="timeline-content">
                                <strong class="timeline-status-title">
                                    {{ ucwords(str_replace('_', ' ', $history->to_status)) }}
                                </strong>
                                <span class="timeline-time">
                                    {{ $history->created_at ? $history->created_at->format('M d, Y \a\t g:i A') : 'Recorded' }}
                                </span>
                                @if($history->changedBy)
                                    <small class="timeline-actor">By: {{ $history->changedBy->name }} ({{ ucfirst($history->actor_role ?? 'Staff') }})</small>
                                @endif
                                @if($history->reason)
                                    <p class="timeline-reason">{{ $history->reason }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <!-- Default Placed entry if status history is empty -->
                        <div class="timeline-step is-completed">
                            <div class="timeline-marker" aria-hidden="true">
                                <span class="marker-dot"></span>
                            </div>
                            <div class="timeline-content">
                                <strong class="timeline-status-title">Order Placed</strong>
                                <span class="timeline-time">{{ $order->created_at ? $order->created_at->format('M d, Y \a\t g:i A') : 'Recently' }}</span>
                                <small class="timeline-actor">Customer Self-Service</small>
                            </div>
                        </div>
                    @endforelse
                </div>
            </article>

        </div>

    </div>

    <!-- Modal Include -->
    @include('admin.orders.partials.assign-driver-modal')

@endsection
