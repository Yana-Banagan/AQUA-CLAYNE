@extends('admin.layouts.app')

@section('title', 'Customer · ' . ($customer->user?->name ?? 'Customer'))

@section('content')
@if(session('success'))
    <div class="flash-message success" role="status">{{ session('success') }}</div>
@endif

<div class="page-header">
    <div>
        <p class="eyebrow">
            <a href="{{ route('admin.customers.index', request()->query()) }}" style="color: var(--primary); text-decoration: none;">← Back to Customers</a>
            &nbsp;·&nbsp; Profile Overview
        </p>
        <div style="display: flex; align-items: center; gap: 12px;">
            <span class="avatar" style="width: 44px; height: 44px; font-size: 15px; flex: 0 0 44px;">
                @include('admin.partials.avatar-content', ['user' => $customer->user, 'fallbackName' => 'Customer'])
            </span>
            <h1>{{ $customer->user?->name ?? 'Customer Profile' }}</h1>
        </div>
        <p>Customer since {{ $customer->created_at?->format('F d, Y') }}</p>
    </div>
    <div class="header-actions">
        <a class="primary-button" href="{{ route('admin.customers.edit', array_merge(['customer' => $customer], request()->query())) }}">Edit Customer</a>
        <span class="status-badge {{ $customer->user?->status === 'active' ? 'status-success' : 'status-danger' }}" style="font-size: 13px; padding: 6px 14px;">
            {{ strtoupper($customer->user?->status ?? 'ACTIVE') }}
        </span>
    </div>
</div>

<!-- Tabs Navigation (Section 25) -->
<div class="tab-nav">
    <a href="javascript:void(0);" class="tab-link active" onclick="switchTab('profile', this)">Profile &amp; Addresses</a>
    <a href="javascript:void(0);" class="tab-link" onclick="switchTab('orders', this)">Orders History ({{ $customer->orders->count() }})</a>
    <a href="javascript:void(0);" class="tab-link" onclick="switchTab('credit', this)">Debt</a>
    <a href="javascript:void(0);" class="tab-link" onclick="switchTab('complaints', this)">Complaints ({{ $customer->complaints->count() }})</a>
</div>

<!-- Tab 1: Profile & Addresses -->
<div id="tab-profile" class="tab-pane">
    <div class="detail-grid">
        <article class="detail-section">
            <h3>Contact Information</h3>
            <div class="detail-row">
                <span class="label">Full Name</span>
                <span class="value">{{ $customer->user?->name }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Email Address</span>
                <span class="value">{{ $customer->user?->email }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Phone Number</span>
                <span class="value">{{ $customer->user?->phone ?? 'Not provided' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Account Status</span>
                <span class="value">{{ ucfirst($customer->user?->status ?? 'active') }}</span>
            </div>
        </article>

        <article class="detail-section">
            <h3>Registered Delivery Addresses</h3>
            @forelse($customer->addresses as $addr)
                <div style="padding: 12px 14px; background: var(--surface-soft); border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: 10px;">
                    <strong style="color: var(--navy); font-size: 13px; display: block;">{{ $addr->label ?? 'Home / Office' }}</strong>
                    <p style="margin: 4px 0 0; font-size: 12.5px; color: var(--text-primary);">{{ $addr->address_line }}</p>
                    @if($addr->notes)
                        <small style="color: var(--text-secondary); display: block; margin-top: 2px;">Note: {{ $addr->notes }}</small>
                    @endif
                </div>
            @empty
                <p style="color: var(--text-secondary); font-size: 13px;">No delivery addresses registered yet.</p>
            @endforelse
        </article>
    </div>
</div>

<!-- Tab 2: Orders History -->
<div id="tab-orders" class="tab-pane" style="display: none;">
    <article class="panel">
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Service</th>
                        <th>Containers</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customer->orders as $order)
                        <tr>
                            <td class="order-cell">
                                <strong>#{{ strtoupper(substr($order->id, 0, 8)) }}</strong>
                            </td>
                            <td>{{ $order->items->first()?->product?->name ?? 'Water Refill' }}</td>
                            <td>
                                <span class="ownership-badge">CUSTOMER OWNED</span>
                            </td>
                            <td>
                                <strong>₱{{ number_format($order->total_amount, 2) }}</strong>
                            </td>
                            <td>
                                <span style="text-transform: uppercase; font-size: 11.5px; font-weight: 600;">{{ $order->payment_method ?? 'Cash' }}</span>
                            </td>
                            <td>
                                <span class="status-badge status-{{ $order->status }}">{{ str_replace('_', ' ', $order->status) }}</span>
                            </td>
                            <td>
                                <small style="color: var(--text-secondary);">{{ $order->created_at?->format('M d, Y') }}</small>
                            </td>
                            <td>
                                <a href="{{ route('admin.orders.show', $order) }}" class="table-action">View Order</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <p>No orders placed by this customer yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>

<!-- Tab 3: Debt -->
<div id="tab-credit" class="tab-pane" style="display: none;">
    @if($customer->creditAccount)
        <section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
            <article class="stat-card">
                <span class="stat-label">Outstanding Debt</span>
                <strong class="stat-value" style="color: {{ $customer->creditAccount->balance > 0 ? 'var(--danger)' : 'var(--success)' }};">
                    ₱{{ number_format($customer->creditAccount->balance, 2) }}
                </strong>
            </article>
        </section>

        <a href="{{ route('admin.debt.show', $customer) }}" class="primary-button">
            View Debt Ledger &amp; Transactions →
        </a>
    @else
        <article class="panel">
            <div class="empty-state">
                <p>This customer has no outstanding debt.</p>
            </div>
        </article>
    @endif
</div>

<!-- Tab 4: Complaints -->
<div id="tab-complaints" class="tab-pane" style="display: none;">
    <article class="panel">
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date Filed</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customer->complaints as $comp)
                        <tr>
                            <td>#{{ strtoupper(substr($comp->id, 0, 8)) }}</td>
                            <td><strong>{{ $comp->subject }}</strong></td>
                            <td><span class="status-badge status-{{ $comp->status }}">{{ $comp->status }}</span></td>
                            <td>{{ $comp->created_at?->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('admin.complaints.show', $comp) }}" class="table-action">View Ticket</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <p>No complaints filed by this customer.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>

@push('scripts')
@endpush
@endsection
