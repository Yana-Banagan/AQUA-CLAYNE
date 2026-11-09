@extends('admin.layouts.app')

@section('title', 'Customers Directory')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Customer Management</p>
        <h1>Customers Directory</h1>
        <p>Registered customer accounts, delivery addresses, order histories, and outstanding debt.</p>
    </div>
</div>

<!-- Customer KPI Cards -->
<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <article class="stat-card">
        <span class="stat-label">Total Customers</span>
        <strong class="stat-value">{{ number_format($stats['total']) }}</strong>
        <small class="stat-meta">Registered accounts</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Active Customers</span>
        <strong class="stat-value">{{ number_format($stats['active']) }}</strong>
        <small class="stat-meta">Active profile status</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">With Outstanding Debt</span>
        <strong class="stat-value">{{ number_format($stats['withCredit']) }}</strong>
        <small class="stat-meta">Customers with unpaid debt</small>
    </article>
</section>

<!-- Filter Search Bar -->
<article class="panel" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="{{ route('admin.customers.index') }}" class="filter-bar" style="margin-bottom: 0;">
        <input type="search" name="search" placeholder="Search customer by name, email, or phone..." value="{{ $search }}" style="min-width: 320px;">
        <button type="submit" class="secondary-button" style="padding: 9px 16px;">
            <span>⌕ Search</span>
        </button>
        @if($search)
            <a href="{{ route('admin.customers.index') }}" class="table-action" style="padding: 9px 12px;">Clear Search</a>
        @endif
    </form>
</article>

<!-- Customers Table -->
<article class="panel">
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Primary Address</th>
                    <th>Total Orders</th>
                    <th>Outstanding Debt</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $cust)
                    <tr>
                        <td style="display: flex; align-items: center; gap: 10px;">
                            <span class="avatar" style="width: 32px; height: 32px; font-size: 12px;">
                                @include('admin.partials.avatar-content', ['user' => $cust->user, 'fallbackName' => 'Customer'])
                            </span>
                            <div>
                                <strong style="font-size: 13px; color: var(--text-primary);">{{ $cust->user?->name ?? 'Customer' }}</strong>
                                <small style="color: var(--text-secondary); display: block;">{{ $cust->user?->email ?? 'No email' }}</small>
                            </div>
                        </td>
                        <td>{{ $cust->user?->phone ?? 'N/A' }}</td>
                        <td style="max-width: 250px;">
                            <span style="font-size: 12.5px; color: var(--text-primary);">
                                {{ $cust->addresses->first()?->address_line ?? 'None listed' }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ $cust->orders->count() }}</strong>
                        </td>
                        <td>
                            @if($cust->creditAccount)
                                <span style="font-weight: 700; color: {{ $cust->creditAccount->balance > 0 ? 'var(--danger)' : 'var(--success)' }};">
                                    ₱{{ number_format($cust->creditAccount->balance, 2) }}
                                </span>
                            @else
                                <span style="color: var(--text-muted);">No debt</span>
                            @endif
                        </td>
                        <td>
                            <span class="status-badge {{ $cust->user?->status === 'active' ? 'status-success' : 'status-danger' }}">
                                {{ $cust->user?->status ?? 'active' }}
                            </span>
                        </td>
                        <td>
                            <small style="color: var(--text-secondary);">{{ $cust->created_at?->format('M d, Y') }}</small>
                        </td>
                        <td>
                            <a href="{{ route('admin.customers.show', $cust) }}" class="table-action">
                                View Profile →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <span class="empty-icon">♙</span>
                                <strong>No customers found</strong>
                                <p>Customers registering through the mobile application will appear here.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($customers->hasPages())
        <div style="padding: 20px 0;">
            {{ $customers->links() }}
        </div>
    @endif
</article>
@endsection
