@extends('admin.layouts.app')

@section('title', 'Assign Deliveries')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">
            <a href="{{ route('admin.deliveries.index') }}" style="color: var(--primary); text-decoration: none;">← Back to Deliveries</a>
            &nbsp;·&nbsp; Driver Assignment
        </p>
        <h1>Assign Drivers for Empty Container Pickup</h1>
        <p>Assign confirmed customer refill orders to active drivers to collect customer-owned empty containers.</p>
    </div>
    <div class="header-actions">
        <a class="secondary-button" href="{{ route('admin.deliveries.index') }}">All Deliveries</a>
    </div>
</div>

@if($activeDrivers->isEmpty())
    <div class="flash-message danger">
        <span>!</span>
        <span>No active drivers are available. Create or activate a driver before assigning pickup tasks.</span>
    </div>
@endif

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Confirmed Orders Awaiting Driver Assignment</h2>
            <small style="color: var(--text-secondary); font-size: 12px;">These orders require a driver to visit the customer and collect empty gallons.</small>
        </div>
        <span class="status-badge status-warning">{{ $pendingOrders->count() }} awaiting assignment</span>
    </div>

    @forelse($pendingOrders as $order)
        <article class="stat-card" style="margin-bottom: 16px; border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; margin-bottom: 14px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                        <strong style="font-size: 16px; color: var(--navy);">Order #{{ strtoupper(substr($order->id, 0, 8)) }}</strong>
                        <span class="ownership-badge">CUSTOMER OWNED</span>
                    </div>
                    <p style="margin: 0; color: var(--text-secondary); font-size: 13px;">
                        <strong>{{ $order->customer?->user?->name ?? 'Customer' }}</strong> &nbsp;·&nbsp; 
                        {{ $order->customer?->user?->phone ?? 'No phone' }} &nbsp;·&nbsp; 
                        <strong>₱{{ number_format($order->total_amount, 2) }}</strong> ({{ strtoupper($order->payment_method ?? 'Cash') }})
                    </p>
                </div>
                <span class="status-badge status-{{ $order->status }}">
                    {{ str_replace('_', ' ', $order->status) }}
                </span>
            </div>

            @if($activeDrivers->isNotEmpty())
                <form method="POST" action="{{ route('admin.deliveries.assign.store') }}" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; background: var(--surface-soft); padding: 12px 16px; border-radius: var(--radius-sm);">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->id }}">
                    <label style="font-size: 13px; font-weight: 700; color: var(--text-primary); white-space: nowrap;">Assign To Driver:</label>
                    <select name="driver_id" required style="flex: 1; min-width: 240px; height: 38px;">
                        <option value="">-- Select Active Driver --</option>
                        @foreach($activeDrivers as $driver)
                            <option value="{{ $driver->id }}">
                                {{ $driver->user?->name ?? 'Driver' }} ({{ $driver->user?->phone ?? 'N/A' }}) {{ $driver->vehicle_plate ? '· Plate: '.$driver->vehicle_plate : '' }}
                            </option>
                        @endforeach
                    </select>
                    <button class="primary-button" type="submit" style="padding: 8px 16px; font-size: 13px;">
                        Assign Driver for Pickup →
                    </button>
                </form>
            @endif
        </article>
    @empty
        <div class="empty-state">
            <span class="empty-icon">✓</span>
            <strong>All confirmed orders have been assigned!</strong>
            <p>New orders requiring empty gallon collection will appear here once confirmed.</p>
        </div>
    @endforelse
</section>
@endsection
