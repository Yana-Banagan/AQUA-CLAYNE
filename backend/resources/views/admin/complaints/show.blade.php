@extends('admin.layouts.app')

@section('title', 'Ticket #' . strtoupper(substr($complaint->id, 0, 8)))

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">
            <a href="{{ route('admin.complaints.index') }}" style="color: var(--primary); text-decoration: none;">← Back to Complaints</a>
            &nbsp;·&nbsp; Ticket Details
        </p>
        <h1>{{ $complaint->subject }}</h1>
        <p>Ticket #{{ strtoupper(substr($complaint->id, 0, 8)) }} &nbsp;·&nbsp; Filed by {{ $complaint->customer?->user?->name ?? 'Customer' }}</p>
    </div>
    <div class="header-actions">
        <span class="status-badge status-{{ $complaint->status }}" style="font-size: 13px; padding: 6px 14px;">
            {{ str_replace('_', ' ', $complaint->status) }}
        </span>
    </div>
</div>

<div class="detail-grid">
    <!-- Left Column: Complaint Details & Thread -->
    <div>
        <article class="detail-section">
            <h3>Complaint Information</h3>
            <div class="detail-row">
                <span class="label">Category</span>
                <span class="value">{{ $complaint->category?->name ?? 'General Concern' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Current Status</span>
                <span class="value">
                    <span class="status-badge status-{{ $complaint->status }}">{{ str_replace('_', ' ', $complaint->status) }}</span>
                </span>
            </div>
            <div style="margin-top: 16px;">
                <strong style="color: var(--text-primary); font-size: 13px;">Customer Description:</strong>
                <div style="margin-top: 8px; padding: 14px; background: var(--surface-soft); border-radius: var(--radius-sm); border: 1px solid var(--border); font-size: 13.5px; line-height: 1.6;">
                    {{ $complaint->description }}
                </div>
            </div>
        </article>

        <!-- Response Conversation History -->
        <article class="detail-section">
            <h3>Investigation &amp; Response History</h3>
            <div class="timeline" style="margin-top: 16px;">
                @forelse($complaint->history as $hist)
                    <div class="timeline-step completed">
                        <div class="timeline-dot"></div>
                        <div class="timeline-title">Response by {{ $hist->changedBy?->name ?? 'Admin Staff' }}</div>
                        <div class="timeline-time">{{ $hist->created_at?->format('M d, Y h:i A') }}</div>
                        <div style="margin-top: 8px; padding: 12px 14px; background: var(--primary-light); color: var(--navy-dark); border-radius: var(--radius-sm); font-size: 13px; border: 1px solid #c8e3fd;">
                            {{ $hist->note }}
                        </div>
                    </div>
                @empty
                    <div class="timeline-step">
                        <div class="timeline-dot"></div>
                        <div class="timeline-title">No administrative responses recorded yet</div>
                    </div>
                @endforelse
            </div>
        </article>
    </div>

    <!-- Right Column: Admin Response & Customer Info -->
    <div>
        <!-- Admin Response Action Form (Section 30) -->
        <article class="detail-section" style="border-top: 4px solid var(--primary);">
            <h3>Submit Official Response</h3>
            
            <form method="POST" action="{{ route('admin.complaints.respond', $complaint) }}">
                @csrf
                <div class="field">
                    <label>Update Complaint Status *</label>
                    <select name="status" required style="width: 100%;">
                        <option value="in_investigation" {{ $complaint->status === 'in_investigation' ? 'selected' : '' }}>In Investigation</option>
                        <option value="resolved" {{ $complaint->status === 'resolved' ? 'selected' : '' }}>Mark as Resolved</option>
                        <option value="dismissed" {{ $complaint->status === 'dismissed' ? 'selected' : '' }}>Dismiss Ticket</option>
                        <option value="pending" {{ $complaint->status === 'pending' ? 'selected' : '' }}>Keep Pending</option>
                    </select>
                </div>

                <div class="field">
                    <label>Administrative Response Note *</label>
                    <textarea name="note" rows="5" required placeholder="Type the investigation resolution or reply to the customer..." style="width: 100%;"></textarea>
                </div>

                <button type="submit" class="primary-button" style="width: 100%;">
                    Record Response &amp; Notify Customer
                </button>
            </form>
        </article>

        <!-- Customer Summary -->
        <article class="detail-section">
            <h3>Customer Contact</h3>
            <div class="detail-row">
                <span class="label">Name</span>
                <span class="value">{{ $complaint->customer?->user?->name ?? 'Customer' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Phone</span>
                <span class="value">{{ $complaint->customer?->user?->phone ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Email</span>
                <span class="value">{{ $complaint->customer?->user?->email ?? 'N/A' }}</span>
            </div>
        </article>
    </div>
</div>
@endsection
