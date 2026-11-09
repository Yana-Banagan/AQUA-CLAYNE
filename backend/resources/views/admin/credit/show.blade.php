@extends('admin.layouts.app')

@section('title', 'Customer Debt · ' . ($customer->user?->name ?? 'Customer'))

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">
            <a href="{{ route('admin.debt.index') }}" style="color: var(--primary); text-decoration: none;">← Back to Debt</a>
            &nbsp;·&nbsp; Payment History
        </p>
        <h1>Debt: {{ $customer->user?->name ?? 'Customer' }}</h1>
        <p>Customer contact: {{ $customer->user?->phone ?? 'N/A' }} &nbsp;·&nbsp; {{ $customer->user?->email ?? 'N/A' }}</p>
    </div>
    <div class="header-actions">
        <span class="status-badge {{ $account->balance > 0 ? 'status-danger' : 'status-success' }}" style="font-size: 13px; padding: 6px 14px;">
            {{ $account->balance > 0 ? 'PAYMENT DUE' : 'SETTLED' }}
        </span>
    </div>
</div>

<!-- Outstanding debt -->
<section class="stat-grid" style="grid-template-columns: minmax(220px, 1fr);">
    <article class="stat-card">
        <span class="stat-label">Outstanding Debt</span>
        <strong class="stat-value">
            ₱{{ number_format($account->balance, 2) }}
        </strong>
        <small class="stat-meta">
            {{ $account->balance > 0 ? 'Payment required' : 'No balance due' }}
        </small>
    </article>

</section>

<!-- Transaction History Ledger -->
<article class="panel">
    <div class="panel-head">
        <div>
            <h2>Debt Payment History</h2>
            <small style="color: var(--text-secondary); font-size: 12px;">Charges and payments affecting this customer's outstanding debt</small>
        </div>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Note / Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td>
                            <span style="font-weight: 600;">{{ $tx->created_at?->format('M d, Y') }}</span>
                            <small style="display: block; color: var(--text-secondary);">{{ $tx->created_at?->format('g:i A') }}</small>
                        </td>
                        <td>
                            @if($tx->type === 'charge')
                                <span class="status-badge status-danger">Charge (+)</span>
                            @else
                                <span class="status-badge status-success">Payment (-)</span>
                            @endif
                        </td>
                        <td>
                            <strong style="font-family: monospace;">
                                {{ $tx->order_id ? '#'.strtoupper(substr($tx->order_id, 0, 8)) : ($tx->reference ?? 'Direct') }}
                            </strong>
                        </td>
                        <td>
                            <strong style="font-size: 14px; color: {{ $tx->type === 'charge' ? 'var(--danger)' : 'var(--success)' }};">
                                {{ $tx->type === 'charge' ? '+' : '-' }}₱{{ number_format($tx->amount, 2) }}
                            </strong>
                        </td>
                        <td>
                            <strong>₱{{ number_format($tx->balance_after ?? 0, 2) }}</strong>
                        </td>
                        <td>
                            <span style="color: var(--text-secondary); font-size: 12.5px;">{{ $tx->notes ?? 'Refill order transaction' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <span class="empty-icon">◌</span>
                                <strong>No ledger entries yet</strong>
                                <p>Debt charges and payments will appear here as they are recorded.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</article>
@endsection
