@extends('admin.layouts.app')

@section('title', 'Station Inventory & Assets')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Stock &amp; Asset Management</p>
        <h1>Station Inventory &amp; Assets</h1>
        <p>Distinguish station water supplies, consumables, and company-owned reusable gallon containers.</p>
    </div>
</div>

<!-- Business Rule Distinction Notice (Section 27) -->
<article class="panel" style="margin-bottom: 24px; background: var(--surface-soft); border-left: 4px solid var(--primary); padding: 18px 22px;">
    <div style="display: flex; gap: 14px; align-items: flex-start;">
        <span style="font-size: 24px; line-height: 1;">ℹ</span>
        <div>
            <strong style="color: var(--navy); font-size: 14px;">Asset Distinction Architecture Rule</strong>
            <p style="margin: 4px 0 0; color: var(--text-secondary); font-size: 13px; line-height: 1.5;">
                <strong>Customer-owned containers</strong> in primary refill orders are tracked via Order Intake verification and do <em>not</em> appear in or decrement station inventory.<br>
                This inventory screen tracks two separate things: <strong>(1) Station consumable supplies</strong> (water capacity, seals, caps) and <strong>(2) Station-owned reusable assets</strong> (company loaner gallons with RFID tracking).
            </p>
        </div>
    </div>
</article>

<!-- Section 1: Station-Owned Gallon Assets & RFID (Section 27) -->
<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <article class="stat-card">
        <span class="stat-label">Station-Owned Gallons</span>
        <strong class="stat-value">{{ $stats['stationGallonsTotal'] }}</strong>
        <small class="stat-meta">Company capital assets</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">In Station Reserve</span>
        <strong class="stat-value">{{ $stats['stationGallonsInStation'] }}</strong>
        <small class="stat-meta">Ready for station use/loan</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Currently Issued</span>
        <strong class="stat-value">{{ $stats['stationGallonsIssued'] }}</strong>
        <small class="stat-meta">Loaned to accounts</small>
    </article>
</section>

<!-- Station Asset Table -->
<article class="panel">
    <div class="panel-head">
        <div>
            <h2>Station-Owned Gallon Asset Registry</h2>
            <small style="color: var(--text-secondary); font-size: 12px;">Track company-owned physical containers and assigned QR/RFID identification tags</small>
        </div>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Gallon Asset ID</th>
                    <th>Container Type</th>
                    <th>Asset Status</th>
                    <th>RFID / QR Tag</th>
                    <th>Ownership Class</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stationGallons as $gallon)
                    <tr>
                        <td>
                            <strong style="font-family: monospace;">#{{ strtoupper(substr($gallon->id, 0, 8)) }}</strong>
                        </td>
                        <td>
                            <span>{{ ucfirst($gallon->type ?? '5-Gallon Standard Slim') }}</span>
                        </td>
                        <td>
                            <span class="status-badge {{ $gallon->status === 'in_station' ? 'status-success' : 'status-info' }}">
                                {{ str_replace('_', ' ', $gallon->status ?? 'in_station') }}
                            </span>
                        </td>
                        <td>
                            @if($gallon->rfidTag)
                                <span style="font-family: monospace; font-weight: 700; color: var(--primary);">
                                    {{ $gallon->rfidTag->tag_uid ?? 'RFID-TAGGED' }}
                                </span>
                            @else
                                <span style="color: var(--text-muted);">Unlinked</span>
                            @endif
                        </td>
                        <td>
                            <span style="font-weight: 700; color: var(--navy); font-size: 11.5px; background: #e9f2fd; padding: 3px 8px; border-radius: 6px;">
                                STATION ASSET
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <span class="empty-icon">◉</span>
                                <strong>No station-owned assets registered</strong>
                                <p>Company containers with RFID tracking tags will appear here.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($stationGallons->hasPages())
        <div style="padding: 20px 0;">
            {{ $stationGallons->links() }}
        </div>
    @endif
</article>
@endsection
