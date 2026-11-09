@extends('admin.layouts.app')

@section('title', 'Customer Complaints')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Customer Support</p>
        <h1>Customer Complaints &amp; Tickets</h1>
        <p>Review customer feedback, quality concerns, delivery disputes, and station administrative responses.</p>
    </div>
</div>

<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <article class="stat-card">
        <span class="stat-label">Total Tickets</span>
        <strong class="stat-value">{{ $stats['total'] }}</strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Pending Attention</span>
        <strong class="stat-value">{{ $stats['pending'] }}</strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Resolved Tickets</span>
        <strong class="stat-value">{{ $stats['resolved'] }}</strong>
    </article>
</section>

<!-- Filter Bar -->
<article class="panel" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="{{ route('admin.complaints.index') }}" class="filter-bar" style="margin-bottom: 0;">
        <select name="status">
            <option value="">All Statuses</option>
            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="in_investigation" {{ $status === 'in_investigation' ? 'selected' : '' }}>In Investigation</option>
            <option value="resolved" {{ $status === 'resolved' ? 'selected' : '' }}>Resolved</option>
            <option value="dismissed" {{ $status === 'dismissed' ? 'selected' : '' }}>Dismissed</option>
        </select>
        <button type="submit" class="secondary-button" style="padding: 9px 16px;">Filter</button>
        @if($status)
            <a href="{{ route('admin.complaints.index') }}" class="table-action" style="padding: 9px 12px;">Clear</a>
        @endif
    </form>
</article>

<!-- Complaints Table -->
<article class="panel">
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Customer</th>
                    <th>Category</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Date Filed</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($complaints as $comp)
                    <tr>
                        <td><strong style="font-family: monospace;">#{{ strtoupper(substr($comp->id, 0, 8)) }}</strong></td>
                        <td class="customer-cell">
                            <strong>{{ $comp->customer?->user?->name ?? 'Customer' }}</strong>
                            <small>{{ $comp->customer?->user?->phone ?? 'N/A' }}</small>
                        </td>
                        <td><span class="ownership-badge">{{ $comp->category?->name ?? 'General' }}</span></td>
                        <td><strong>{{ $comp->subject }}</strong></td>
                        <td><span class="status-badge status-{{ $comp->status }}">{{ str_replace('_', ' ', $comp->status) }}</span></td>
                        <td><small style="color: var(--text-secondary);">{{ $comp->created_at?->format('M d, Y') }}</small></td>
                        <td>
                            <a href="{{ route('admin.complaints.show', $comp) }}" class="table-action">View &amp; Respond →</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7"><div class="empty-state"><p>No complaints filed matching current filter.</p></div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($complaints->hasPages())
        <div style="padding: 20px 0;">{{ $complaints->links() }}</div>
    @endif
</article>
@endsection
