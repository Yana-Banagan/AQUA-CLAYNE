@extends('admin.layouts.app')

@section('title', 'Operational Reports')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Analytics &amp; Performance</p>
        <h1>Operational Reports</h1>
        <p>Read-only executive summaries of station revenue, refill volume, customer debt, and driver performance.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="secondary-button" onclick="window.print();">
            <span>⎙</span>
            <span>Print Report</span>
        </button>
    </div>
</div>

<!-- Report Controls & Filters (Section 28) -->
<article class="panel" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="{{ route('admin.reports.index') }}" class="filter-bar" style="margin-bottom: 0;">
        <select name="range">
            <option value="all" {{ $range === 'all' ? 'selected' : '' }}>All Time</option>
            <option value="today" {{ $range === 'today' ? 'selected' : '' }}>Today</option>
            <option value="week" {{ $range === 'week' ? 'selected' : '' }}>Past 7 Days</option>
            <option value="month" {{ $range === 'month' ? 'selected' : '' }}>Past 30 Days</option>
        </select>

        <select name="method">
            <option value="">All Payment Methods</option>
            <option value="cash" {{ $method === 'cash' ? 'selected' : '' }}>Cash Only</option>
            <option value="credit" {{ $method === 'credit' ? 'selected' : '' }}>Debt Only</option>
        </select>

        <button type="submit" class="primary-button" style="padding: 9px 18px;">
            <span>Apply Filters</span>
        </button>

        @if($range !== 'all' || $method)
            <a href="{{ route('admin.reports.index') }}" class="table-action" style="padding: 9px 12px;">Reset</a>
        @endif
    </form>
</article>

<!-- Financial Highlights -->
<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <article class="stat-card">
        <span class="stat-label">Verified Revenue</span>
        <strong class="stat-value">₱{{ number_format($totalRevenue, 2) }}</strong>
        <small class="stat-meta">Delivered refill services</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Cash Collections</span>
        <strong class="stat-value">₱{{ number_format($cashRevenue, 2) }}</strong>
        <small class="stat-meta">Cash on Delivery</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Debt Charges</span>
        <strong class="stat-value">₱{{ number_format($creditRevenue, 2) }}</strong>
        <small class="stat-meta">Added to customer debt</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Outstanding Debt</span>
        <strong class="stat-value">₱{{ number_format($totalOutstandingCredit, 2) }}</strong>
        <small class="stat-meta">{{ $activeCreditAccounts }} customer accounts with balance</small>
    </article>
</section>

<!-- Report Breakdown Grid -->
<div class="dashboard-grid">
    <!-- Area 1: Orders by Status -->
    <article class="panel">
        <div class="panel-head">
            <div>
                <h2>Orders by Status Breakdown</h2>
                <small style="color: var(--text-secondary); font-size: 12px;">Fulfillment volume in selected period</small>
            </div>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Status Category</th>
                        <th>Order Count</th>
                        <th>Status Indicator</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Completed &amp; Delivered</strong></td>
                        <td><strong>{{ $ordersByStatus['completed'] }}</strong></td>
                        <td><span class="status-badge status-success">Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>Currently In-Progress (Station Workflow / Transit)</strong></td>
                        <td><strong>{{ $ordersByStatus['in_progress'] }}</strong></td>
                        <td><span class="status-badge status-purple">Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>Pending Administrative Review</strong></td>
                        <td><strong>{{ $ordersByStatus['pending'] }}</strong></td>
                        <td><span class="status-badge status-warning">Pending</span></td>
                    </tr>
                    <tr>
                        <td><strong>Cancelled / Rejected</strong></td>
                        <td><strong>{{ $ordersByStatus['cancelled'] }}</strong></td>
                        <td><span class="status-badge status-danger">Cancelled</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </article>

    <!-- Area 2: Top Refill Services -->
    <article class="panel">
        <div class="panel-head">
            <div>
                <h2>Top Water Refill Offerings</h2>
                <small style="color: var(--text-secondary); font-size: 12px;">Customer preferred offerings</small>
            </div>
        </div>

        <div class="data-table-wrap">
            <table class="data-table" style="min-width: 100%;">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Price</th>
                        <th>Orders</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topServices as $svc)
                        <tr>
                            <td><strong>{{ $svc->name }}</strong></td>
                            <td>₱{{ number_format($svc->price, 2) }}</td>
                            <td><span style="font-weight: 700; color: var(--navy);">{{ $svc->total_orders ?? 0 }} orders</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3"><div class="empty-state"><p>No service sales data yet.</p></div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>

<!-- Area 3: Active Drivers Performance -->
<article class="panel" style="margin-top: 24px;">
    <div class="panel-head">
        <div>
            <h2>Driver Performance Metrics</h2>
            <small style="color: var(--text-secondary); font-size: 12px;">Logistical throughput and completed customer deliveries</small>
        </div>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Driver Personnel</th>
                    <th>Contact Phone</th>
                    <th>License Reference</th>
                    <th>Vehicle Details</th>
                    <th>Completed Deliveries</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($driverPerformances as $driver)
                    <tr>
                        <td style="display: flex; align-items: center; gap: 10px;">
                            <span class="avatar">@include('admin.partials.avatar-content', ['user' => $driver->user, 'fallbackName' => 'Driver'])</span>
                            <strong>{{ $driver->user?->name ?? 'Driver' }}</strong>
                        </td>
                        <td>{{ $driver->user?->phone ?? 'N/A' }}</td>
                        <td>{{ $driver->license_number ?? 'N/A' }}</td>
                        <td>{{ $driver->vehicle_plate ?: 'Motorized Tricycle' }}</td>
                        <td>
                            <strong style="font-size: 14px; color: var(--success);">
                                {{ $driver->deliveries->count() }} fulfilled
                            </strong>
                        </td>
                        <td>
                            <span class="status-badge {{ $driver->user?->status === 'active' ? 'status-success' : 'status-danger' }}">
                                {{ $driver->user?->status ?? 'active' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"><div class="empty-state"><p>No driver activity records.</p></div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</article>
@endsection
