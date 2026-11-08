@extends('admin.layouts.app')

@section('title', 'Debt Management')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Financial Accounts</p>
        <h1>Customer Debt</h1>
        <p>Review unpaid customer debt and its payment history.</p>
    </div>
</div>

<!-- KPI Summary Cards (Section 23) -->
<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <article class="stat-card">
        <span class="stat-label">Total Outstanding Debt</span>
        <strong class="stat-value">₱{{ number_format($stats['totalOutstanding'], 2) }}</strong>
        <small class="stat-meta">Unpaid customer balances</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Customers With Debt</span>
        <strong class="stat-value">{{ $stats['customersWithDebt'] }}</strong>
        <small class="stat-meta">Accounts with unpaid balances</small>
    </article>
</section>

<!-- Filter Search Bar -->
<article class="panel" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="{{ route('admin.debt.index') }}" class="filter-bar" style="margin-bottom: 0;">
        <input type="search" name="search" placeholder="Search customer by name or phone..." value="{{ $search }}" style="min-width: 320px;">
        <button type="submit" class="secondary-button" style="padding: 9px 16px;">
            <span>⌕ Search</span>
        </button>
        @if($search)
            <a href="{{ route('admin.debt.index') }}" class="table-action" style="padding: 9px 12px;">Clear Search</a>
        @endif
    </form>
</article>

<!-- Accounts Table -->
<article class="panel">
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Outstanding Debt</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $acc)
                    <tr>
                        <td class="customer-cell">
                            <strong>{{ $acc->customer?->user?->name ?? 'Customer' }}</strong>
                            <small>{{ $acc->customer?->user?->email ?? 'No email' }}</small>
                        </td>
                        <td>{{ $acc->customer?->user?->phone ?? 'N/A' }}</td>
                        <td>
                            <strong style="color: {{ $acc->balance > 0 ? 'var(--danger)' : 'var(--success)' }}; font-size: 14px;">
                                ₱{{ number_format($acc->balance, 2) }}
                            </strong>
                        </td>
                        <td>
                            <a href="{{ route('admin.debt.show', $acc->customer_id) }}" class="table-action">
                                View Debt History →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <span class="empty-icon">◌</span>
                                <strong>No outstanding debt found</strong>
                                <p>Customers with unpaid balances will appear here.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($accounts->hasPages())
        <div style="padding: 20px 0;">
            {{ $accounts->links() }}
        </div>
    @endif
</article>
@endsection
