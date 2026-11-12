@extends('admin.layouts.app')

@section('title', 'Payments Management')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Finance &amp; Collections</p>
        <h1>Station Payments</h1>
        <p>Monitor cash collections and customer debt billings across all refill orders.</p>
    </div>
</div>

<!-- Payment KPI Cards -->
<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <article class="stat-card">
        <span class="stat-label">Total Collected</span>
        <strong class="stat-value">₱{{ number_format($stats['totalPaid'], 2) }}</strong>
        <small class="stat-meta">Verified revenue</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Pending Collection</span>
        <strong class="stat-value">₱{{ number_format($stats['totalPending'], 2) }}</strong>
        <small class="stat-meta">Awaiting delivery / collection</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Cash Transactions</span>
        <strong class="stat-value">{{ $stats['cashCount'] }}</strong>
        <small class="stat-meta">Cash on Delivery</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Debt Transactions</span>
        <strong class="stat-value">{{ $stats['debtCount'] }}</strong>
        <small class="stat-meta">Customer debt ledger</small>
    </article>
</section>

<!-- Gateway Notice (Section 22) -->
<article class="panel" style="margin-bottom: 24px; background: var(--surface-soft); border-left: 4px solid var(--info); padding: 16px 20px;">
    <div style="display: flex; gap: 12px; align-items: center;">
        <span style="font-size: 22px;">ℹ</span>
        <div>
            <strong style="color: var(--text-primary); font-size: 13.5px;">Active Payment Methods: Cash on Delivery &amp; Customer Debt</strong>
            <p style="margin: 3px 0 0; color: var(--text-secondary); font-size: 12.5px;">
                PayMongo Hosted Checkout supports QR Ph and GCash. Cash collections require driver or station verification. Customer debt remains recorded against authorized accounts.
            </p>
        </div>
    </div>
</article>

<!-- Filter Bar -->
<article class="panel" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="{{ route('admin.payments.index') }}" class="filter-bar" style="margin-bottom: 0;">
        <select name="status">
            <option value="">All Payment Statuses</option>
            <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Paid</option>
            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
        </select>

        <select name="method">
            <option value="">All Payment Methods</option>
            <option value="cash" {{ $method === 'cash' ? 'selected' : '' }}>Cash</option>
            <option value="credit" {{ $method === 'credit' ? 'selected' : '' }}>Debt</option>
            <option value="online" {{ $method === 'online' ? 'selected' : '' }}>PayMongo online</option>
        </select>

        <button type="submit" class="secondary-button" style="padding: 9px 16px;">
            <span>⌕ Filter</span>
        </button>

        @if($status || $method)
            <a href="{{ route('admin.payments.index') }}" class="table-action" style="padding: 9px 12px;">Clear Filters</a>
        @endif
    </form>
</article>

<!-- Payments Table -->
<article class="panel">
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Payment #</th>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>PayMongo Reference</th>
                    <th>Paid At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>
                            <strong style="font-family: monospace;">#{{ strtoupper(substr($payment->id, 0, 8)) }}</strong>
                        </td>
                        <td class="order-cell">
                            <strong>#{{ strtoupper(substr($payment->order_id, 0, 8)) }}</strong>
                            @if($payment->order?->isStationPickup())
                                <span style="display:inline-block; font-size:10px; font-weight:700; color:#0369a1; background:#e0f2fe; padding:1px 6px; border-radius:9999px; margin-left: 2px;">🏪 Pickup</span>
                            @elseif($payment->order)
                                <span style="display:inline-block; font-size:10px; font-weight:700; color:#15803d; background:#f0fdf4; padding:1px 6px; border-radius:9999px; margin-left: 2px;">🛵 Delivery</span>
                            @endif
                            <br>
                            <a href="{{ route('admin.orders.show', $payment->order_id) }}" style="font-size: 11px; color: var(--primary); text-decoration: none;">View Order</a>
                        </td>
                        <td class="customer-cell">
                            <strong>{{ $payment->order?->customer?->user?->name ?? 'Customer' }}</strong>
                            <small>{{ $payment->order?->customer?->user?->phone ?? 'N/A' }}</small>
                        </td>
                        <td>
                            <strong style="font-size: 14px; color: var(--navy);">₱{{ number_format($payment->amount, 2) }}</strong>
                        </td>
                        <td>
                            <span style="font-weight: 700; text-transform: uppercase; font-size: 12px; color: var(--text-primary);">
                                @if($payment->method === 'cash')
                                    {{ $payment->order?->isStationPickup() ? 'Cash (Pickup)' : 'COD' }}
                                @elseif($payment->method === 'credit')
                                    Debt
                                @elseif($payment->method === 'online')
                                    PayMongo {{ strtoupper($payment->gateway_payment_method ?? 'online') }}
                                @else
                                    {{ ucfirst($payment->method) }}
                                @endif
                            </span>
                        </td>
                        <td>
                            <span class="status-badge {{ $payment->status === 'paid' ? 'status-success' : 'status-warning' }}">
                                {{ $payment->status }}
                            </span>
                        </td>
                        <td>
                            <small style="color: var(--text-secondary);">
                                {{ $payment->gateway_payment_id ?: $payment->gateway_checkout_session_id ?: '—' }}
                            </small>
                        </td>
                        <td>
                            <small style="color: var(--text-secondary);">
                                {{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('M d, g:i A') : 'Pending' }}
                            </small>
                        </td>
                        <td>
                            @if($payment->status === 'pending' && $payment->method === 'cash')
                                <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" data-confirm="Confirm receipt of ₱{{ number_format($payment->amount, 2) }} cash payment for Order #{{ strtoupper(substr($payment->order_id, 0, 8)) }}?">
                                    @csrf
                                    <button type="submit" class="table-action success">
                                        ✓ Mark Paid
                                    </button>
                                </form>
                            @else
                                <span style="font-size: 11.5px; color: var(--text-muted);">Verified</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <span class="empty-icon">₱</span>
                                <strong>No payment records found</strong>
                                <p>Recorded payments will appear here as orders are placed and processed.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="oq-pagination-footer" style="padding: 16px 20px;">
        <div class="pagination-info">
            Showing <strong>{{ $payments->firstItem() ?? 0 }}–{{ $payments->lastItem() ?? 0 }}</strong>
            of <strong>{{ $payments->total() }}</strong> payments
        </div>
        <nav class="oq-pagination" aria-label="Payments pagination">
            @if($payments->onFirstPage())
                <span class="oq-page-btn oq-page-btn--disabled" aria-disabled="true">‹</span>
            @else
                <a class="oq-page-btn" href="{{ $payments->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
            @endif

            <span class="oq-page-btn oq-page-btn--active" aria-current="page" aria-label="Page {{ $payments->currentPage() }} of {{ $payments->lastPage() }}">{{ $payments->currentPage() }}</span>

            @if($payments->hasMorePages())
                <a class="oq-page-btn" href="{{ $payments->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
            @else
                <span class="oq-page-btn oq-page-btn--disabled" aria-disabled="true">›</span>
            @endif
            <span class="pagination-info">Page {{ $payments->currentPage() }} of {{ $payments->lastPage() }}</span>
        </nav>
    </div>
</article>
@endsection
