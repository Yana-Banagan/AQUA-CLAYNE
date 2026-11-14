@extends('admin.layouts.app')

@section('title', 'Delivery #' . strtoupper(substr($delivery->id, 0, 8)) . ' · AQUA CLAYNE')
@section('header-title', 'Delivery Details')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="{{ asset('css/admin-deliveries.css') }}">
@endpush

@section('content')
<div class="deliveries-page">

    {{-- Back navigation --}}
    <div class="show-breadcrumb">
        <a href="{{ route('admin.deliveries.index') }}" class="show-back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Deliveries Dashboard
        </a>
    </div>

    {{-- Page header --}}
    <div class="deliveries-header" style="margin-bottom: 20px;">
        <div>
            <div class="show-page-label">Dispatch Tracking &amp; Verification</div>
            <h1 class="deliveries-title" style="font-size: 24px; margin-top: 4px;">
                Delivery #{{ strtoupper(substr($delivery->id, 0, 8)) }}
            </h1>
            <p class="deliveries-subtitle">
                Associated with Order
                <a href="{{ route('admin.orders.show', $delivery->order_id) }}" class="cell-order-link">
                    #{{ $delivery->order?->order_number ?? strtoupper(substr($delivery->order_id, 0, 8)) }}
                </a>
            </p>
        </div>
        <div class="deliveries-header-actions">
            <span class="delivery-status status-{{ $delivery->status }}" style="font-size: 14px; padding: 7px 18px;">
                {{ ucwords(str_replace('_', ' ', $delivery->status)) }}
            </span>
        </div>
    </div>

    {{-- 2-column show grid --}}
    <div class="show-grid">

        {{-- ===================== LEFT COLUMN ===================== --}}
        <div>

            {{-- Refill Delivery Lifecycle (Step Matrix) --}}
            <article class="delivery-card">
                <div class="delivery-card-header">
                    <h2 class="delivery-card-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                        Refill Delivery Lifecycle
                    </h2>
                </div>

                @php
                    $statusWeights = [
                        'assigned'             => 1,
                        'en_route_for_pickup'  => 2,
                        'picked_up'            => 3,
                        'at_station'           => 4,
                        'received_at_station'  => 4,
                        'refilling'            => 4,
                        'ready_for_delivery'   => 5,
                        'out_for_delivery'     => 6,
                        'delivered'            => 7,
                        'completed'            => 8,
                        'failed'               => -1,
                        'pickup_failed'        => -1,
                        'delivery_failed'      => -1,
                    ];
                    $currentOrder   = $delivery->order;
                    $orderWeight    = $statusWeights[$currentOrder?->status] ?? 0;
                    $deliveryWeight = $statusWeights[$delivery->status] ?? 0;
                    $weight         = max($orderWeight, $deliveryWeight);
                    $isFailed       = in_array($delivery->status, ['failed', 'pickup_failed', 'delivery_failed']);
                @endphp

                <div class="delivery-timeline">

                    @php
                    $steps = [
                        [1, 'Driver Assigned for Empty Gallon Pickup',
                            $delivery->created_at?->format('M d, Y · g:i A') ?? ''],
                        [2, 'Driver En Route to Customer',
                            $weight >= 2 ? 'Driver heading to customer for gallon collection' : 'Awaiting dispatch'],
                        [3, 'Empty Gallons Collected',
                            $weight >= 3 ? 'Customer empty gallons picked up by driver' : 'Awaiting collection'],
                        [4, 'Station Intake &amp; Inspection',
                            $currentOrder?->containerIntake
                                ? $currentOrder->containerIntake->accepted_count . ' gallons accepted for refill'
                                : ($weight >= 4 ? 'Gallons received at station' : 'Awaiting station arrival')],
                        [5, 'Refill Completed &amp; Ready for Delivery',
                            $weight >= 5 ? 'Purified water refilling completed' : 'Pending station refilling'],
                        [6, 'Out for Return Delivery',
                            $weight >= 6 ? 'Driver dispatched with refilled gallons' : 'Awaiting dispatch'],
                        [7, 'Delivered to Customer',
                            $delivery->delivered_at
                                ? \Illuminate\Support\Carbon::parse($delivery->delivered_at)->format('M d, Y · g:i A')
                                : ($weight >= 7 ? 'Delivered' : 'Pending delivery')],
                        [8, 'Order Completed',
                            $weight >= 8 ? 'Proof verified &amp; payment reconciled' : 'Pending completion'],
                    ];
                    @endphp

                    @foreach($steps as [$stepWeight, $title, $meta])
                        @php
                            $isDone   = !$isFailed && $weight >= $stepWeight;
                            $isActive = !$isFailed && $weight === $stepWeight - 1;
                            $class    = $isDone ? 'done' : ($isActive ? 'active' : '');
                        @endphp
                        <div class="delivery-timeline-item {{ $class }}">
                            <div class="timeline-step-title">{!! $title !!}</div>
                            <div class="timeline-step-meta">{!! $meta !!}</div>
                        </div>
                    @endforeach

                    @if($isFailed)
                        <div class="delivery-timeline-item failed">
                            <div class="timeline-step-title">⚠ Delivery Failed / Exception</div>
                            <div class="timeline-step-meta">
                                {{ $delivery->failure_reason ?? 'Delivery could not be completed.' }}
                            </div>
                        </div>
                    @endif
                </div>
            </article>

            {{-- Recorded Audit Timeline (DeliveryStatusHistory) --}}
            <article class="delivery-card">
                <div class="delivery-card-header">
                    <h2 class="delivery-card-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        Recorded Audit Log Timeline
                    </h2>
                </div>

                <div class="delivery-timeline">
                    @forelse($delivery->statusHistory as $hist)
                        <div class="delivery-timeline-item done">
                            <div class="timeline-step-title">{{ ucwords(str_replace('_', ' ', $hist->status)) }}</div>
                            <div class="timeline-step-meta">
                                {{ $hist->created_at?->format('M d, Y · g:i A') }}
                                @if($hist->note)
                                    — {{ $hist->note }}
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="timeline-empty">No recorded status transitions yet.</p>
                    @endforelse
                </div>
            </article>

            {{-- Proof of Delivery & Signature --}}
            <article class="delivery-card">
                <div class="delivery-card-header">
                    <h2 class="delivery-card-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        Proof of Delivery &amp; Signature
                    </h2>
                </div>

                <div class="proof-media-grid">
                    <div class="proof-media-card">
                        <strong class="proof-media-label">Photo Proof</strong>
                        @if($proofPhotoSignedUrl)
                            <a href="{{ $proofPhotoSignedUrl }}" target="_blank"
                               title="Open full-size proof photo" rel="noopener noreferrer">
                                <img src="{{ $proofPhotoSignedUrl }}" alt="Delivery proof photograph" />
                            </a>
                            <span class="proof-verified-badge">✓ Captured by Driver</span>
                        @else
                            <div class="proof-missing">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                @if($delivery->proof_status === 'skipped')
                                    Proof not provided (driver skipped)
                                @else
                                    No proof photo submitted
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="proof-media-card">
                        <strong class="proof-media-label">Customer Signature</strong>
                        @if($signatureSignedUrl)
                            <a href="{{ $signatureSignedUrl }}" target="_blank"
                               title="Open full-size signature" rel="noopener noreferrer">
                                <img src="{{ $signatureSignedUrl }}" alt="Customer delivery signature" />
                            </a>
                            <span class="proof-verified-badge">✓ Verified Signature</span>
                        @else
                            <div class="proof-missing">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 20H4v-4l9.29-9.29a1 1 0 0 1 1.42 0l3.58 3.58a1 1 0 0 1 0 1.42L9 20"></path><line x1="17" y1="7" x2="3" y2="21"></line><path d="M15 5l4 4"></path></svg>
                                No signature recorded yet
                            </div>
                        @endif
                    </div>
                </div>
            </article>

            {{-- RFID scans recorded by the assigned driver --}}
            <article class="delivery-card">
                <div class="delivery-card-header">
                    <h2 class="delivery-card-title">RFID Gallon Scans</h2>
                    <span class="head-badge">{{ $delivery->order?->rfidTransactions?->count() ?? 0 }} recorded</span>
                </div>
                @forelse($delivery->order?->rfidTransactions ?? collect() as $scan)
                    <div class="detail-rows">
                        <div class="detail-row"><span class="detail-label">Tag UID</span><span class="detail-val">{{ $scan->rfidTag?->tag_uid ?? 'Unknown tag' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Action</span><span class="detail-val">{{ ucfirst($scan->action) }}</span></div>
                        <div class="detail-row"><span class="detail-label">Gallon status</span><span class="detail-val">{{ $scan->rfidTag?->gallon?->status ?? 'Unavailable' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Scanned by</span><span class="detail-val">{{ $scan->scannedBy?->name ?? 'Unknown user' }} · {{ $scan->created_at?->format('M d, Y g:i A') ?? 'Time unavailable' }}</span></div>
                    </div>
                @empty
                    <div class="proof-missing">No RFID scans recorded for this delivery.</div>
                @endforelse
            </article>

        </div>
        {{-- ===================== END LEFT COLUMN ===================== --}}

        {{-- ===================== RIGHT COLUMN ===================== --}}
        <div>

            {{-- Assigned Driver Card --}}
            <article class="delivery-card">
                <div class="delivery-card-header">
                    <h2 class="delivery-card-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                        Assigned Driver
                    </h2>
                </div>

                @if($delivery->driver)
                    <div class="show-driver-profile">
                        <span class="show-driver-avatar" aria-hidden="true">
                            {{ strtoupper(mb_substr($delivery->driver->user?->name ?? 'D', 0, 1)) }}
                        </span>
                        <div>
                            <strong class="show-driver-name">{{ $delivery->driver->user?->name ?? 'Driver' }}</strong>
                            <span class="show-driver-license">License: {{ $delivery->driver->license_number ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="detail-rows">
                        <div class="detail-row">
                            <span class="detail-label">Vehicle Plate</span>
                            <span class="detail-val">{{ $delivery->driver->vehicle_plate ?? 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Phone</span>
                            <span class="detail-val">{{ $delivery->driver->user?->phone ?? 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Driver Status</span>
                            <span class="detail-val">
                                <span class="delivery-status status-{{ $delivery->driver->user?->status === 'active' ? 'delivered' : 'failed' }}" style="font-size:11px;">
                                    {{ ucfirst($delivery->driver->user?->status ?? 'active') }}
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">ETA</span>
                            <span class="detail-val">
                                {{ $delivery->eta
                                    ? \Illuminate\Support\Carbon::parse($delivery->eta)->format('M d, Y · g:i A')
                                    : 'Scheduled' }}
                            </span>
                        </div>
                    </div>
                @else
                    <div class="show-no-driver">
                        No driver assigned to this delivery yet.
                    </div>
                @endif
            </article>

            {{-- Customer & Delivery Destination --}}
            <article class="delivery-card">
                <div class="delivery-card-header">
                    <h2 class="delivery-card-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        Customer &amp; Destination
                    </h2>
                </div>

                <div class="detail-rows">
                    <div class="detail-row">
                        <span class="detail-label">Customer</span>
                        <span class="detail-val">{{ $delivery->order?->customer?->user?->name ?? '—' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone</span>
                        <span class="detail-val">{{ $delivery->order?->customer?->user?->phone ?? '—' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Delivery Address</span>
                        <span class="detail-val">
                            {{ $delivery->order?->address
                                ? trim(
                                    $delivery->order->address->address_line1 . ', ' .
                                    $delivery->order->address->barangay . ', ' .
                                    $delivery->order->address->city
                                  )
                                : 'Station Pickup' }}
                        </span>
                    </div>
                    @if($delivery->order?->address?->delivery_notes)
                        <div class="detail-row">
                            <span class="detail-label">Delivery Notes</span>
                            <span class="detail-val detail-notes">{{ $delivery->order->address->delivery_notes }}</span>
                        </div>
                    @endif
                </div>
            </article>

            {{-- Order & Payment Summary --}}
            <article class="delivery-card">
                <div class="delivery-card-header">
                    <h2 class="delivery-card-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                        Order &amp; Payment
                    </h2>
                </div>

                <div class="detail-rows">
                    <div class="detail-row">
                        <span class="detail-label">Order #</span>
                        <span class="detail-val">
                            <a href="{{ route('admin.orders.show', $delivery->order_id) }}"
                               class="cell-order-link">
                                #{{ $delivery->order?->order_number ?? strtoupper(substr($delivery->order_id, 0, 8)) }}
                            </a>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Container Type</span>
                        <span class="detail-val">
                            <span class="badge-ownership">
                                {{ ucwords(str_replace('_', ' ', $delivery->order?->container_ownership ?? 'customer_owned')) }}
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Gallons Expected</span>
                        <span class="detail-val">{{ $delivery->order?->expected_empty_gallons ?? '—' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Gallons Accepted</span>
                        <span class="detail-val">{{ $delivery->order?->containerIntake?->accepted_count ?? '—' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method</span>
                        <span class="detail-val uppercase-val">
                            {{ strtoupper($delivery->order?->payment?->method ?? $delivery->order?->payment_method ?? 'Cash') }}
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Status</span>
                        <span class="detail-val">
                            @php
                                $payStatus = $delivery->order?->payment?->status ?? 'pending';
                            @endphp
                            <span class="delivery-status status-{{ $payStatus === 'paid' ? 'delivered' : 'pending' }}"
                                  style="font-size:11px; padding: 3px 10px;">
                                {{ strtoupper($payStatus) }}
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Amount</span>
                        <span class="detail-val detail-amount">
                            ₱{{ number_format((float)($delivery->order?->payment?->amount ?? $delivery->order?->total_amount ?? 0), 2) }}
                        </span>
                    </div>
                    @if($delivery->delivered_at)
                        <div class="detail-row">
                            <span class="detail-label">Delivered At</span>
                            <span class="detail-val">
                                {{ \Illuminate\Support\Carbon::parse($delivery->delivered_at)->format('M d, Y · g:i A') }}
                            </span>
                        </div>
                    @endif
                    @if($delivery->failure_reason)
                        <div class="detail-row">
                            <span class="detail-label">Failure Reason</span>
                            <span class="detail-val detail-notes">{{ $delivery->failure_reason }}</span>
                        </div>
                    @endif
                </div>
            </article>

            {{-- Container Intake Summary (if recorded) --}}
            @if($delivery->order?->containerIntake)
                <article class="delivery-card">
                    <div class="delivery-card-header">
                        <h2 class="delivery-card-title">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>
                            Container Intake
                        </h2>
                    </div>
                    @php $intake = $delivery->order->containerIntake; @endphp
                    <div class="detail-rows">
                        <div class="detail-row">
                            <span class="detail-label">Accepted Gallons</span>
                            <span class="detail-val">{{ $intake->accepted_count }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Rejected Gallons</span>
                            <span class="detail-val">{{ $intake->rejected_count ?? 0 }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Returned / Refilled</span>
                            <span class="detail-val">{{ $intake->returned_count ?? 0 }}</span>
                        </div>
                    </div>
                </article>
            @endif

            {{-- Delivery Route & Navigation (Interactive Map & Turn-by-Turn) --}}
            @php
                $orderAddress = $delivery->order?->address;
                $destLat = $orderAddress?->latitude;
                $destLng = $orderAddress?->longitude;
                $hasValidCoords = is_numeric($destLat) && is_numeric($destLng) && (float)$destLat >= 14.22 && (float)$destLat <= 14.34 && (float)$destLng >= 121.37 && (float)$destLng <= 121.47 && !((float)$destLat === 0.0 && (float)$destLng === 0.0);
                $stationLat = (float) config('services.station.latitude', 14.265111);
                $stationLng = (float) config('services.station.longitude', 121.405602);
                $stationAddress = config('services.station.address', 'Aqua Clayne Water Refilling Station, Batisan, Gatid, Santa Cruz, Laguna');
            @endphp
            <article class="delivery-card" style="border: 1px solid #BCE3FD;">
                <div class="delivery-card-header" style="background:#F0F7FC;">
                    <div>
                        <h2 class="delivery-card-title" style="color: #0878C9;">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
                            Delivery Route &amp; Navigation
                        </h2>
                        <span style="font-size: 11.5px; color: #52718B; display:block; margin-top:2px;">
                            Official Starting Point: <strong>Aqua Clayne Station, Batisan, Gatid</strong>
                        </span>
                    </div>
                    <span class="delivery-status status-{{ $hasValidCoords ? 'delivered' : 'failed' }}" style="font-size: 11px; padding: 4px 10px;" id="badgeCoordsStatus">
                        {{ $hasValidCoords ? '✓ Valid Destination Pin' : '⚠️ Missing / Needs Pin Correction' }}
                    </span>
                </div>

                {{-- Map canvas --}}
                <div style="padding: 14px 16px;">
                    <div id="deliveryDetailMap" style="height: 320px; width: 100%; border-radius: 12px; border: 1px solid #D5EAF8; background: #EAF3F9; position: relative;">
                        <div id="routeMapLoading" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(240, 247, 252, 0.85); color: #0878C9; font-weight: 600; font-size: 13px; z-index: 500;">
                            Calculating road route from Aqua Clayne Station…
                        </div>
                    </div>

                    {{-- Origin & Destination summary --}}
                    <div style="margin-top: 14px; display: grid; grid-template-columns: 1fr; gap: 8px;">
                        <div style="display: flex; align-items: flex-start; gap: 10px; font-size: 12.5px; background: #F8FAFC; padding: 10px 12px; border-radius: 8px; border: 1px solid #E2E8F0;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#059669; margin-top:4px; flex-shrink:0;"></span>
                            <div>
                                <strong style="color: #0F172A;">Starting Origin (Fixed):</strong>
                                <span style="color: #334155;">{{ $stationAddress }} ({{ $stationLat }}, {{ $stationLng }})</span>
                            </div>
                        </div>

                        <div style="display: flex; align-items: flex-start; gap: 10px; font-size: 12.5px; background: #F8FAFC; padding: 10px 12px; border-radius: 8px; border: 1px solid #E2E8F0;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#DC2626; margin-top:4px; flex-shrink:0;"></span>
                            <div>
                                <strong style="color: #0F172A;">Delivery Destination:</strong>
                                <span id="textDestinationSummary" style="color: #334155;">
                                    {{ $orderAddress ? collect([$orderAddress->address_line ?? $orderAddress->address_line1, $orderAddress->barangay, $orderAddress->city])->filter()->implode(', ') : 'No delivery address recorded' }}
                                    @if($hasValidCoords) ({{ $destLat }}, {{ $destLng }}) @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Route Metrics & Estimate Warning --}}
                    <div style="margin-top: 12px; padding: 12px 14px; background: #EAF7FF; border: 1px solid #BCE3FD; border-radius: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #0878C9; letter-spacing: 0.5px;">Estimated Distance</span>
                                <div id="valRouteDistance" style="font-size: 18px; font-weight: 800; color: #12344D;">-- km</div>
                            </div>
                            <div>
                                <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #0878C9; letter-spacing: 0.5px;">Estimated Travel Time</span>
                                <div id="valRouteDuration" style="font-size: 18px; font-weight: 800; color: #059669;">-- mins</div>
                            </div>
                        </div>
                        <div style="margin-top: 8px; font-size: 11px; color: #52718B; line-height: 1.4;">
                            ℹ️ <em>Travel time is strictly an estimate calculated from Aqua Clayne Station based on current routing and Santa Cruz road conditions. It is not a guaranteed arrival time.</em>
                        </div>
                    </div>

                    {{-- Action buttons row --}}
                    <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" id="btnRecalculateRoute" class="btn" style="flex: 1; min-width: 130px; background: #F1F5F9; border: 1px solid #CBD5E1; color: #334155; padding: 9px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                            Recalculate Route
                        </button>

                        <button type="button" id="btnOpenAddressModal" class="btn" style="flex: 1; min-width: 140px; background: #0878C9; border: 1px solid #0878C9; color: #FFFFFF; padding: 9px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                            Correct Address / Pin
                        </button>

                        <a id="btnExternalMapsLink" href="https://www.google.com/maps/dir/?api=1&origin={{ $stationLat }},{{ $stationLng }}&destination={{ $hasValidCoords ? $destLat.','.$destLng : urlencode($orderAddress->address_line ?? '') }}&travelmode=driving" target="_blank" rel="noopener noreferrer" style="flex: 1; min-width: 140px; background: #FFFFFF; border: 1px solid #0878C9; color: #0878C9; padding: 9px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            Open in Google Maps
                        </a>
                    </div>

                    {{-- Turn-by-turn Navigation instructions --}}
                    <div style="margin-top: 14px; border-top: 1px solid #E2E8F0; padding-top: 12px;">
                        <button type="button" id="btnToggleSteps" style="background: none; border: none; padding: 0; color: #0878C9; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <svg id="iconStepsArrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            <span>Turn-by-Turn Navigation Instructions (<span id="stepsCount">0</span> steps)</span>
                        </button>
                        <div id="containerStepsList" style="display: none; margin-top: 10px; max-height: 220px; overflow-y: auto; font-size: 12px; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px 12px; background: #F8FAFC;">
                            <ol id="stepsList" style="margin: 0; padding-left: 20px; color: #334155; line-height: 1.6;">
                                <li>Loading driving steps…</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </article>

        </div>
        {{-- ===================== END RIGHT COLUMN ===================== --}}

    </div>

</div>

{{-- ======================================================================
     ADDRESS / DESTINATION PIN CORRECTION MODAL
====================================================================== --}}
<div id="modalCorrectAddress" style="display: none; position: fixed; inset: 0; background: rgba(18, 52, 77, 0.65); z-index: 99999; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: #FFFFFF; border-radius: 18px; max-width: 520px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.25); overflow: hidden; animation: popIn 0.2s ease-out;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid #E2E8F0; background: #F0F7FC;">
            <h3 style="font-size: 16px; font-weight: 800; color: #12344D; margin: 0; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0878C9" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                Correct Delivery Destination &amp; Map Pin
            </h3>
            <button type="button" id="btnCloseAddressModal" style="background: none; border: none; font-size: 24px; color: #64748B; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <form id="formCorrectAddress" style="padding: 20px 22px;">
            @csrf
            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 11px; font-weight: 800; color: #475569; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Address Line / Street / Landmark</label>
                <input type="text" name="address_line" id="inputAddressLine" required value="{{ $orderAddress?->address_line ?? $orderAddress?->address_line1 ?? '' }}" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box;" placeholder="House number, Street, Landmark" />
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #475569; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Barangay</label>
                    <input type="text" name="barangay" id="inputBarangay" value="{{ $orderAddress?->barangay ?? '' }}" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box;" placeholder="e.g. Gatid, Callios" />
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #475569; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">City</label>
                    <input type="text" name="city" id="inputCity" value="{{ $orderAddress?->city ?? 'Santa Cruz' }}" readonly style="width: 100%; padding: 10px 12px; border: 1px solid #E2E8F0; background: #F8FAFC; border-radius: 8px; font-size: 13.5px; box-sizing: border-box; color: #64748B;" />
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #475569; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Latitude (14.22 – 14.34)</label>
                    <input type="number" step="0.000001" min="14.22" max="14.34" name="latitude" id="inputLatitude" required value="{{ $hasValidCoords ? $destLat : '14.281400' }}" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box;" />
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #475569; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Longitude (121.37 – 121.47)</label>
                    <input type="number" step="0.000001" min="121.37" max="121.47" name="longitude" id="inputLongitude" required value="{{ $hasValidCoords ? $destLng : '121.416100' }}" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box;" />
                </div>
            </div>

            <div style="background: #EBF5FB; border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; font-size: 11.5px; color: #0878C9; line-height: 1.45;">
                <strong>Santa Cruz Bounds:</strong> Official delivery routes always start at Aqua Clayne Station ({{ $stationLat }}, {{ $stationLng }}) and route along accessible Santa Cruz roads to this destination pin.
            </div>

            <div id="addressErrorAlert" style="display: none; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 8px; padding: 8px 12px; font-size: 12px; margin-bottom: 12px; font-weight: 600;"></div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="btnCancelAddressModal" style="padding: 9px 16px; border: 1px solid #CBD5E1; background: #FFFFFF; border-radius: 8px; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer;">Cancel</button>
                <button type="submit" id="btnSubmitAddressCorrection" style="padding: 9px 20px; border: none; background: #0878C9; border-radius: 8px; font-size: 13px; font-weight: 700; color: #FFFFFF; cursor: pointer;">Save &amp; Generate Route</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var deliveryId = "{{ $delivery->id }}";
    var stationPoint = [{{ $stationLat }}, {{ $stationLng }}];
    var destPoint = @if($hasValidCoords) [{{ $destLat }}, {{ $destLng }}] @else null @endif;
    var driverPoint = @if(is_numeric($delivery->driver_latitude) && is_numeric($delivery->driver_longitude)) [{{ $delivery->driver_latitude }}, {{ $delivery->driver_longitude }}] @else null @endif;
    var routeEndpoint = "{{ url('/admin/deliveries/' . $delivery->id . '/route') }}";
    var addressUpdateEndpoint = "{{ route('admin.deliveries.address.update', $delivery->id) }}";

    var map = null;
    var routeLayer = null;
    var markersLayer = null;

    function markerIcon(kind, glyph) {
        return L.divIcon({
            className: 'delivery-map-marker delivery-map-marker--' + kind,
            html: '<span><i>' + glyph + '</i></span>',
            iconSize: [34, 40],
            iconAnchor: [17, 37],
            popupAnchor: [0, -34],
        });
    }

    function initMap() {
        var container = document.getElementById('deliveryDetailMap');
        if (!container || typeof L === 'undefined') {
            setTimeout(initMap, 150);
            return;
        }

        map = L.map(container, {
            scrollWheelZoom: false,
            minZoom: 12,
            maxBounds: L.latLngBounds([[14.20, 121.33], [14.36, 121.50]]),
        });

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        markersLayer = L.layerGroup().addTo(map);
        routeLayer = L.layerGroup().addTo(map);

        renderMarkers();
        loadRoute();
    }

    function renderMarkers() {
        if (!markersLayer) return;
        markersLayer.clearLayers();

        // 1. Station Origin Marker (ALWAYS Official Aqua Clayne Origin)
        L.marker(stationPoint, { icon: markerIcon('station', '🏢') })
            .bindPopup('<strong>Aqua Clayne Water Refilling Station</strong><br><span>Official Delivery Origin</span><br><small>Batisan, Gatid, Santa Cruz, Laguna</small>')
            .addTo(markersLayer);

        // 2. Customer Destination Marker
        if (destPoint) {
            L.marker(destPoint, { icon: markerIcon('destination', '📍') })
                .bindPopup('<strong>Delivery Destination</strong><br><span>Customer Registered Address</span>')
                .addTo(markersLayer);
        }

        // 3. Driver Live Marker (if available)
        if (driverPoint) {
            L.marker(driverPoint, { icon: markerIcon('driver', '🚚') })
                .bindPopup('<strong>Assigned Driver Location</strong>')
                .addTo(markersLayer);
        }

        if (destPoint) {
            map.fitBounds(L.latLngBounds([stationPoint, destPoint]).pad(0.2));
        } else {
            map.setView(stationPoint, 15);
        }
    }

    function loadRoute() {
        var loadingEl = document.getElementById('routeMapLoading');
        if (loadingEl) loadingEl.style.display = 'flex';

        fetch(routeEndpoint, {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Route HTTP ' + res.status);
            return res.json();
        })
        .then(function (payload) {
            var data = payload.data || {};
            var coords = data.coordinates;
            var dist = data.distance;
            var dur = data.duration;
            var steps = data.steps || [];

            if (routeLayer) routeLayer.clearLayers();

            if (Array.isArray(coords) && coords.length >= 2) {
                var latLngs = coords.map(function (c) { return [c[1], c[0]]; });
                var poly = L.polyline(latLngs, {
                    color: '#0878C9',
                    weight: 5,
                    opacity: 0.85
                }).addTo(routeLayer);

                if (dist != null && dur != null) {
                    poly.bindTooltip((dist / 1000).toFixed(1) + ' km · ' + Math.ceil(dur / 60) + ' min estimated travel time');
                }
                map.fitBounds(poly.getBounds().pad(0.18));
            }

            // Update UI metrics
            var distEl = document.getElementById('valRouteDistance');
            var durEl = document.getElementById('valRouteDuration');
            if (distEl && dist != null) distEl.textContent = (dist / 1000).toFixed(1) + ' km';
            if (durEl && dur != null) durEl.textContent = '~' + Math.ceil(dur / 60) + ' mins (Estimated)';

            // Populate turn-by-turn steps
            var stepsCountEl = document.getElementById('stepsCount');
            var stepsListEl = document.getElementById('stepsList');
            if (stepsCountEl) stepsCountEl.textContent = steps.length;
            if (stepsListEl) {
                if (steps.length === 0) {
                    stepsListEl.innerHTML = '<li>Direct route from Aqua Clayne Station to customer destination.</li>';
                } else {
                    stepsListEl.innerHTML = steps.map(function (s) {
                        var d = s.distance != null ? ' (' + (s.distance > 1000 ? (s.distance/1000).toFixed(1) + ' km' : Math.round(s.distance) + ' m') + ')' : '';
                        return '<li><strong>' + (s.instruction || 'Proceed') + '</strong>' + d + '</li>';
                    }).join('');
                }
            }
        })
        .catch(function (err) {
            console.warn('[DeliveryRoute] Route lookup note:', err);
            var distEl = document.getElementById('valRouteDistance');
            var durEl = document.getElementById('valRouteDuration');
            if (distEl) distEl.textContent = 'Unavailable';
            if (durEl) durEl.textContent = 'Awaiting pin';
        })
        .finally(function () {
            if (loadingEl) loadingEl.style.display = 'none';
        });
    }

    // Modal elements
    var modal = document.getElementById('modalCorrectAddress');
    var btnOpen = document.getElementById('btnOpenAddressModal');
    var btnClose = document.getElementById('btnCloseAddressModal');
    var btnCancel = document.getElementById('btnCancelAddressModal');
    var form = document.getElementById('formCorrectAddress');
    var errorAlert = document.getElementById('addressErrorAlert');

    function openModal() {
        if (modal) modal.style.display = 'flex';
        if (errorAlert) errorAlert.style.display = 'none';
    }
    function closeModal() {
        if (modal) modal.style.display = 'none';
    }

    if (btnOpen) btnOpen.addEventListener('click', openModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);

    // Handle address correction submission
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(form);
            var submitBtn = document.getElementById('btnSubmitAddressCorrection');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }
            if (errorAlert) errorAlert.style.display = 'none';

            fetch(addressUpdateEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Validation error');
                closeModal();

                // Update local destination coordinates
                var newLat = Number(formData.get('latitude'));
                var newLng = Number(formData.get('longitude'));
                destPoint = [newLat, newLng];

                // Update badge and text
                var badge = document.getElementById('badgeCoordsStatus');
                if (badge) {
                    badge.className = 'delivery-status status-delivered';
                    badge.textContent = '✓ Valid Destination Pin';
                }
                var textDest = document.getElementById('textDestinationSummary');
                if (textDest && data.address) {
                    textDest.textContent = data.address.full + ' (' + newLat + ', ' + newLng + ')';
                }

                // Update External Google Maps link
                var gmaps = document.getElementById('btnExternalMapsLink');
                if (gmaps) {
                    gmaps.href = 'https://www.google.com/maps/dir/?api=1&origin=' + stationPoint[0] + ',' + stationPoint[1] + '&destination=' + newLat + ',' + newLng + '&travelmode=driving';
                }

                renderMarkers();
                loadRoute();
            })
            .catch(function (err) {
                if (errorAlert) {
                    errorAlert.textContent = err.message || 'Failed to update address. Please verify coordinates within Santa Cruz.';
                    errorAlert.style.display = 'block';
                }
            })
            .finally(function () {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save & Generate Route'; }
            });
        });
    }

    // Recalculate button
    var btnRecalc = document.getElementById('btnRecalculateRoute');
    if (btnRecalc) btnRecalc.addEventListener('click', loadRoute);

    // Toggle turn-by-turn accordion
    var btnToggle = document.getElementById('btnToggleSteps');
    var containerSteps = document.getElementById('containerStepsList');
    var arrow = document.getElementById('iconStepsArrow');
    if (btnToggle && containerSteps) {
        btnToggle.addEventListener('click', function () {
            var isHidden = containerSteps.style.display === 'none';
            containerSteps.style.display = isHidden ? 'block' : 'none';
            if (arrow) arrow.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
        });
    }

    initMap();
});
</script>
@endpush

