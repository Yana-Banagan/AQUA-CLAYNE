@extends('admin.layouts.app')

@section('title', 'RFID & Gallon Management')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-rfid.css') }}">
@endpush

@section('content')
<div class="page-header rfid-hub-header">
    <div>
        <p class="eyebrow">IoT Hardware &amp; Smart Tracking</p>
        <h1>RFID &amp; Gallon Management</h1>
        <p>ESP32 RFID reader integration, customer container identification, station intake validation, and hardware device health.</p>
    </div>
    <div class="rfid-actions">
        <button type="button" class="btn-rfid-secondary" onclick="openAddContainerModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Container
        </button>
        <button type="button" class="btn-rfid-secondary" onclick="openAddDeviceModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line></svg>
            Add Scanner
        </button>
        <button type="button" class="btn-rfid-primary" onclick="openRegisterRfidModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
            Register New RFID Tag
        </button>
    </div>
</div>

<section id="latestRfidScan" aria-live="polite" aria-atomic="true" style="display:flex; align-items:flex-start; gap:14px; padding:16px 18px; margin-bottom:22px; background:#F8FAFC; border:1px solid #CBD5E1; border-radius:12px;">
    <span id="latestRfidScanIndicator" aria-hidden="true" style="width:10px; height:10px; margin-top:5px; border-radius:50%; background:#94A3B8; flex:none;"></span>
    <div>
        <strong id="latestRfidScanStatus" style="display:block; color:#334155;">Waiting for RFID scan</strong>
        <span id="latestRfidScanMessage" style="display:block; margin-top:4px; color:#64748B;">Scanner ONLINE status reflects its heartbeat, not whether a tag was read.</span>
        <div id="latestRfidScanCustomerDetails" style="display:none; margin-top:6px; color:#334155;"></div>
        <time id="latestRfidScanTime" style="display:block; margin-top:4px; color:#64748B; font-size:12px;"></time>
    </div>
</section>

<!-- Section A: Live Operational Statistics Cards -->
<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 24px;">
    <article class="stat-card">
        <span class="stat-label">Total Registered Tags</span>
        <strong class="stat-value">{{ number_format($stats['totalTags']) }}</strong>
        <small class="stat-meta">Active &amp; historical RFID tags</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Active RFID Tags</span>
        <strong class="stat-value" style="color: #059669;">{{ number_format($stats['activeTags']) }}</strong>
        <small class="stat-meta">Ready for station intake &amp; refill</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Inactive / Replaced Tags</span>
        <strong class="stat-value" style="color: #64748B;">{{ number_format($stats['inactiveTags']) }}</strong>
        <small class="stat-meta">Deactivated or superseded</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Untagged Containers</span>
        <strong class="stat-value" style="color: #D97706;">{{ number_format($stats['unassignedContainers']) }}</strong>
        <small class="stat-meta">Customer gallons awaiting RFID</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">ESP32 Scanners Online</span>
        <strong class="stat-value" style="color: #0284C7;">
            <span class="pulsing-dot online" style="margin-right: 6px;"></span>{{ $stats['devicesOnline'] }}
        </strong>
        <small class="stat-meta">{{ $stats['devicesOffline'] }} offline or idle</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Gallons at Station</span>
        <strong class="stat-value">{{ number_format($stats['gallonsAtStation']) }}</strong>
        <small class="stat-meta">In queue or refilling bay</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Gallons in Delivery</span>
        <strong class="stat-value">{{ number_format($stats['gallonsInDelivery']) }}</strong>
        <small class="stat-meta">Out for customer delivery</small>
    </article>
</section>

<!-- Tab Navigation -->
<nav class="rfid-tabs" aria-label="RFID Management Views">
    <a href="{{ route('admin.rfid.index', ['tab' => 'tags']) }}" class="rfid-tab-btn {{ $activeTab === 'tags' ? 'active' : '' }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
        RFID Tags Registry
        <span class="rfid-tab-badge">{{ number_format($stats['activeTags']) }}</span>
    </a>
    <a href="{{ route('admin.rfid.index', ['tab' => 'containers']) }}" class="rfid-tab-btn {{ $activeTab === 'containers' ? 'active' : '' }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
        Customer Containers
        <span class="rfid-tab-badge">{{ number_format($containerCount ?? $containers->count()) }}</span>
    </a>
    <a href="{{ route('admin.rfid.index', ['tab' => 'devices']) }}" class="rfid-tab-btn {{ $activeTab === 'devices' ? 'active' : '' }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect></svg>
        Hardware Scanners
        <span class="rfid-tab-badge">{{ number_format($deviceCount ?? $devices->count()) }}</span>
    </a>
    <a href="{{ route('admin.rfid.index', ['tab' => 'transactions']) }}" class="rfid-tab-btn {{ $activeTab === 'transactions' ? 'active' : '' }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
        Scan History &amp; Audit
        <span class="rfid-tab-badge">{{ number_format($transactionCount ?? $transactions->count()) }}</span>
    </a>
</nav>

{{-- ========================================================================= --}}
{{-- TAB 1: RFID TAGS REGISTRY --}}
{{-- ========================================================================= --}}
@if($activeTab === 'tags')
<article class="panel">
    <div class="panel-head" style="flex-wrap: wrap; gap: 14px; margin-bottom: 16px;">
        <div>
            <h2>Registered RFID Tags</h2>
            <small style="color: var(--text-secondary); font-size: 12.5px;">Active, replaced, and historical RFID tags associated with physical customer gallons</small>
        </div>
        <form method="GET" action="{{ route('admin.rfid.index') }}" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="tags">
            <input type="text" name="tag_search" value="{{ $tagSearch }}" placeholder="Search UID, customer, or container..." style="padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; min-width: 240px;">
            <select name="tag_status" style="padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;" onchange="this.form.submit()">
                <option value="all" {{ $tagStatus === 'all' || empty($tagStatus) ? 'selected' : '' }}>All Statuses</option>
                <option value="active" {{ $tagStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="replaced" {{ $tagStatus === 'replaced' ? 'selected' : '' }}>Replaced</option>
                <option value="inactive" {{ $tagStatus === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="btn-rfid-secondary" style="padding: 8px 14px;">Filter</button>
            @if($tagSearch || ($tagStatus && $tagStatus !== 'all'))
                <a href="{{ route('admin.rfid.index', ['tab' => 'tags']) }}" class="btn-rfid-secondary" style="padding: 8px 14px; color: #64748B;">Clear</a>
            @endif
        </form>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>RFID UID</th>
                    <th>Container Code</th>
                    <th>Customer Name</th>
                    <th>Customer Contact</th>
                    <th>Container Type</th>
                    <th>Registered At</th>
                    <th>Tag Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tags as $tag)
                    <tr>
                        <td>
                            <span class="rfid-uid-pill">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path></svg>
                                {{ $tag->uid }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ $tag->container?->container_code ?? '—' }}</strong>
                        </td>
                        <td>
                            <span>{{ $tag->container?->customer?->user?->name ?? 'Unassigned' }}</span>
                        </td>
                        <td>
                            <span style="font-size: 12.5px; color: #64748B;">{{ $tag->container?->customer?->user?->phone ?? '—' }}</span>
                        </td>
                        <td>
                            <span style="font-size: 12.5px;">{{ $tag->container?->container_type ?? '5-Gallon Standard' }}</span>
                        </td>
                        <td>
                            <span style="font-size: 12.5px; color: #475569;">{{ $tag->registered_at ? $tag->registered_at->format('M d, Y g:i A') : '—' }}</span>
                        </td>
                        <td>
                            @if($tag->status === 'active')
                                <span class="badge-rfid-active"><span class="pulsing-dot online"></span> Active</span>
                            @elseif($tag->status === 'replaced')
                                <span class="badge-rfid-replaced">Replaced</span>
                            @else
                                <span class="badge-rfid-inactive">Inactive</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 6px;">
                                @if($tag->status === 'active')
                                    <button type="button" class="btn-rfid-secondary" style="padding: 5px 10px; font-size: 12px;" onclick="openReplaceTagModal('{{ $tag->id }}', '{{ $tag->uid }}', '{{ $tag->container?->container_code }}')">
                                        Replace
                                    </button>
                                    <button type="button" class="btn-rfid-secondary" style="padding: 5px 10px; font-size: 12px; color: #EF4444; border-color: #FECACA;" onclick="openDeactivateTagModal('{{ $tag->id }}', '{{ $tag->uid }}')">
                                        Deactivate
                                    </button>
                                @endif
                                <a href="{{ route('admin.rfid.index', ['tab' => 'transactions', 'tx_search' => $tag->uid]) }}" class="btn-rfid-secondary" style="padding: 5px 10px; font-size: 12px;">
                                    History
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 36px 20px;">
                            <div style="max-width: 380px; margin: 0 auto; color: #64748B;">
                                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8" style="margin-bottom: 10px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                <strong style="display: block; font-size: 15px; color: #1E293B; margin-bottom: 4px;">No RFID tags found</strong>
                                <p style="font-size: 13px; margin: 0 0 16px;">There are no registered RFID tags matching the selected filters.</p>
                                <button type="button" class="btn-rfid-primary" onclick="openRegisterRfidModal()">Register First RFID Tag</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tags->hasPages())
        <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #E2E8F0;">
            {{ $tags->links() }}
        </div>
    @endif
</article>
@endif

{{-- ========================================================================= --}}
{{-- TAB 2: CUSTOMER CONTAINERS --}}
{{-- ========================================================================= --}}
@if($activeTab === 'containers')
<article class="panel">
    <div class="panel-head" style="flex-wrap: wrap; gap: 14px; margin-bottom: 16px;">
        <div>
            <h2>Customer Containers (Gallons)</h2>
            <small style="color: var(--text-secondary); font-size: 12.5px;">Customer-owned physical containers enrolled in Aqua Clayne refill operations</small>
        </div>
        <form method="GET" action="{{ route('admin.rfid.index') }}" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="containers">
            <input type="text" name="container_search" value="{{ $containerSearch }}" placeholder="Search code, customer, or type..." style="padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; min-width: 240px;">
            <select name="container_status" style="padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;" onchange="this.form.submit()">
                <option value="all" {{ $containerStatus === 'all' || empty($containerStatus) ? 'selected' : '' }}>All Statuses</option>
                <option value="active" {{ $containerStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="inactive" {{ $containerStatus === 'inactive' ? 'selected' : '' }}>Inactive</option>
                <option value="damaged" {{ $containerStatus === 'damaged' ? 'selected' : '' }}>Damaged</option>
            </select>
            <button type="submit" class="btn-rfid-secondary" style="padding: 8px 14px;">Filter</button>
            @if($containerSearch || ($containerStatus && $containerStatus !== 'all'))
                <a href="{{ route('admin.rfid.index', ['tab' => 'containers']) }}" class="btn-rfid-secondary" style="padding: 8px 14px; color: #64748B;">Clear</a>
            @endif
        </form>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Container Code</th>
                    <th>Customer Name</th>
                    <th>Customer Contact</th>
                    <th>Container Type</th>
                    <th>Capacity</th>
                    <th>Linked RFID Tag</th>
                    <th>Container Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($containers as $c)
                    <tr>
                        <td>
                            <strong>{{ $c->container_code }}</strong>
                        </td>
                        <td>
                            <span>{{ $c->customer?->user?->name ?? 'Unassigned' }}</span>
                        </td>
                        <td>
                            <span style="font-size: 12.5px; color: #64748B;">{{ $c->customer?->user?->phone ?? '—' }}</span>
                        </td>
                        <td>
                            <span style="font-size: 12.5px;">{{ $c->container_type }}</span>
                        </td>
                        <td>
                            <span style="font-size: 12.5px;">{{ number_format($c->capacity, 1) }} gal</span>
                        </td>
                        <td>
                            @if($c->activeTag)
                                <span class="rfid-uid-pill">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path></svg>
                                    {{ $c->activeTag->uid }}
                                </span>
                            @else
                                <span class="rfid-uid-pill unlinked">Not Tagged</span>
                            @endif
                        </td>
                        <td>
                            @if($c->status === 'active')
                                <span class="badge-rfid-active">Active</span>
                            @elseif($c->status === 'damaged')
                                <span class="badge-rfid-inactive" style="color: #EF4444; background: #FEF2F2;">Damaged</span>
                            @else
                                <span class="badge-rfid-inactive">{{ ucfirst($c->status) }}</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 6px;">
                                @if(!$c->activeTag && $c->status === 'active')
                                    <button type="button" class="btn-rfid-primary" style="padding: 5px 12px; font-size: 12px;" onclick="startRegistrationForContainer('{{ $c->id }}', '{{ $c->container_code }}', '{{ $c->customer?->id }}', '{{ addslashes($c->customer?->user?->name ?? '') }}')">
                                        Attach Tag
                                    </button>
                                @endif
                                <a href="{{ route('admin.rfid.index', ['tab' => 'transactions', 'tx_search' => $c->container_code]) }}" class="btn-rfid-secondary" style="padding: 5px 10px; font-size: 12px;">
                                    Scans
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 36px 20px;">
                            <p style="color: #64748B; margin-bottom: 12px;">No customer containers found.</p>
                            <button type="button" class="btn-rfid-primary" onclick="openAddContainerModal()">Register New Customer Container</button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($containers->hasPages())
        <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #E2E8F0;">
            {{ $containers->links() }}
        </div>
    @endif
</article>
@endif

{{-- ========================================================================= --}}
{{-- TAB 3: HARDWARE SCANNERS (ESP32) --}}
{{-- ========================================================================= --}}
@if($activeTab === 'devices')
<section class="device-cards-grid">
    @forelse($devices as $dev)
        <article class="device-card">
            <div>
                <div class="device-card-head">
                    <div>
                        <h3 class="device-card-title">{{ $dev->device_name }}</h3>
                        <p class="device-card-meta">{{ $dev->location }}</p>
                    </div>
                    @if($dev->isOnline())
                        <span class="badge-rfid-active"><span class="pulsing-dot online"></span> ONLINE</span>
                    @elseif($dev->status === 'maintenance')
                        <span class="badge-rfid-inactive" style="color: #D97706; background: #FEF3C7;"><span class="pulsing-dot maintenance"></span> MAINTENANCE</span>
                    @else
                        <span class="badge-rfid-inactive"><span class="pulsing-dot offline"></span> OFFLINE</span>
                    @endif
                </div>

                <div class="device-details-list">
                    <div class="device-detail-item">
                        <span>Device Code:</span>
                        <strong style="font-family: monospace;">{{ $dev->device_code }}</strong>
                    </div>
                    <div class="device-detail-item">
                        <span>Last Heartbeat:</span>
                        <strong>{{ $dev->last_heartbeat_at ? $dev->last_heartbeat_at->diffForHumans() : 'Never' }}</strong>
                    </div>
                    <div class="device-detail-item">
                        <span>IP Address:</span>
                        <strong>{{ $dev->last_ip_address ?? 'Not recorded' }}</strong>
                    </div>
                    <div class="device-detail-item">
                        <span>Firmware:</span>
                        <strong>v{{ $dev->firmware_version ?? '1.0.0' }}</strong>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 8px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #F1F5F9;">
                <button type="button" class="btn-rfid-secondary" style="flex: 1; padding: 7px 10px; font-size: 12px;" onclick="rotateDeviceToken('{{ $dev->id }}', '{{ $dev->device_code }}')">
                    Rotate Secret Token
                </button>
                <button type="button" class="btn-rfid-secondary" style="padding: 7px 10px; font-size: 12px;" onclick="toggleDeviceStatus('{{ $dev->id }}')">
                    {{ $dev->status === 'online' ? 'Set Maintenance' : 'Set Online' }}
                </button>
            </div>
        </article>
    @empty
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; background: #ffffff; border-radius: 12px; border: 1px solid #E2E8F0;">
            <p style="color: #64748B; margin-bottom: 12px;">No RFID scanner devices enrolled yet.</p>
            <button type="button" class="btn-rfid-primary" onclick="openAddDeviceModal()">Register First ESP32 Device</button>
        </div>
    @endforelse
</section>
@endif

{{-- ========================================================================= --}}
{{-- TAB 4: SCAN HISTORY & AUDIT TRAIL --}}
{{-- ========================================================================= --}}
@if($activeTab === 'transactions')
<article class="panel">
    <div class="panel-head" style="flex-wrap: wrap; gap: 14px; margin-bottom: 16px;">
        <div>
            <h2>RFID Scan Transactions</h2>
            <small style="color: var(--text-secondary); font-size: 12.5px;">Complete verifiable log of every RFID read, intake verification, and registration</small>
        </div>
        <form method="GET" action="{{ route('admin.rfid.index') }}" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="transactions">
            <input type="text" name="tx_search" value="{{ $txSearch }}" placeholder="Search keyword or UID..." style="padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 12.5px; width: 180px;">
            <select name="tx_type" style="padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 12.5px;" onchange="this.form.submit()">
                <option value="all">All Operations</option>
                <option value="REGISTRATION" {{ $txType === 'REGISTRATION' ? 'selected' : '' }}>Registration</option>
                <option value="STATION_INTAKE" {{ $txType === 'STATION_INTAKE' ? 'selected' : '' }}>Station Intake</option>
                <option value="REFILL_START" {{ $txType === 'REFILL_START' ? 'selected' : '' }}>Refill Start</option>
                <option value="REFILL_COMPLETE" {{ $txType === 'REFILL_COMPLETE' ? 'selected' : '' }}>Refill Complete</option>
                <option value="DRIVER_PICKUP" {{ $txType === 'DRIVER_PICKUP' ? 'selected' : '' }}>Driver Pickup</option>
                <option value="TAG_REPLACEMENT" {{ $txType === 'TAG_REPLACEMENT' ? 'selected' : '' }}>Tag Replacement</option>
            </select>
            <select name="tx_result" style="padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 12.5px;" onchange="this.form.submit()">
                <option value="all">All Results</option>
                <option value="accepted" {{ $txResult === 'accepted' ? 'selected' : '' }}>Accepted Only</option>
                <option value="rejected" {{ $txResult === 'rejected' ? 'selected' : '' }}>Rejected / Blocked</option>
            </select>
            <button type="submit" class="btn-rfid-secondary" style="padding: 7px 12px;">Filter</button>
            @if($txSearch || ($txType && $txType !== 'all') || ($txResult && $txResult !== 'all'))
                <a href="{{ route('admin.rfid.index', ['tab' => 'transactions']) }}" class="btn-rfid-secondary" style="padding: 7px 12px; color: #64748B;">Reset</a>
            @endif
        </form>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Scan Type</th>
                    <th>Tag UID</th>
                    <th>Container</th>
                    <th>Customer</th>
                    <th>Scanner / Actor</th>
                    <th>Result</th>
                    <th>Audit Message</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td>
                            <span style="font-size: 12px; color: #475569;">{{ $tx->scanned_at ? $tx->scanned_at->format('M d, Y g:i A') : ($tx->created_at ? $tx->created_at->format('M d, Y g:i A') : '—') }}</span>
                        </td>
                        <td>
                            <span style="font-weight: 700; font-size: 11.5px; background: #F1F5F9; color: #334155; padding: 3px 8px; border-radius: 5px;">
                                {{ str_replace('_', ' ', $tx->transaction_type ?? $tx->action) }}
                            </span>
                        </td>
                        <td>
                            @php
                                $dispUid = $tx->customerContainerTag?->uid ?? $tx->rfidTag?->tag_uid ?? ($tx->metadata['raw_uid'] ?? '—');
                            @endphp
                            <span class="rfid-uid-pill" style="font-size: 11.5px; padding: 2px 7px;">
                                {{ $dispUid }}
                            </span>
                        </td>
                        <td>
                            <strong style="font-size: 12.5px;">{{ $tx->customerContainer?->container_code ?? ($tx->rfidTag?->gallon_id ? '#' . substr($tx->rfidTag->gallon_id, 0, 8) : '—') }}</strong>
                        </td>
                        <td>
                            <span style="font-size: 12.5px;">{{ $tx->customerContainer?->customer?->user?->name ?? '—' }}</span>
                        </td>
                        <td>
                            <span style="font-size: 12px; color: #64748B;">{{ $tx->device?->device_name ?? ($tx->scannedBy?->name ?? 'System Staff') }}</span>
                        </td>
                        <td>
                            @if($tx->scan_result === 'accepted')
                                <span class="badge-rfid-active" style="padding: 2px 7px; font-size: 11px;">Accepted</span>
                            @else
                                <span class="badge-rfid-inactive" style="color: #EF4444; background: #FEF2F2; border-color: #FECACA; padding: 2px 7px; font-size: 11px;">Rejected</span>
                            @endif
                        </td>
                        <td>
                            <span style="font-size: 12px; color: #475569;">{{ $tx->message ?? '—' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #64748B;">
                            No scan transactions recorded matching the filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($transactions->hasPages())
        <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #E2E8F0;">
            {{ $transactions->links() }}
        </div>
    @endif
</article>
@endif

{{-- ========================================================================= --}}
{{-- MODAL 1: REGISTER NEW RFID TAG (CORE REQUIREMENT) --}}
{{-- ========================================================================= --}}
<div class="modal-backdrop" id="registerRfidModal" style="display: none; position: fixed; inset: 0; background: rgba(3, 27, 55, 0.7); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-card" style="background: #ffffff; width: 100%; max-width: 540px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); overflow: hidden; margin: 20px;">
        <div style="padding: 18px 22px; background: linear-gradient(135deg, #041a33, #0284c7); color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Register New RFID Tag</h3>
                <small style="color: #BAE6FD; font-size: 12px;">Link a physical RFID tag to a customer gallon via ESP32 reader</small>
            </div>
            <button type="button" onclick="closeRegisterRfidModal()" style="background: transparent; border: none; color: #ffffff; font-size: 22px; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <div style="padding: 22px;">
            <!-- Step 1: Selection Form -->
            <div id="rfidSetupStep">
                <!-- Customer Search -->
                <div style="margin-bottom: 16px; position: relative;">
                    <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">1. Select Customer</label>
                    <input type="text" id="regCustomerSearch" placeholder="Search customer name, mobile, or email..." style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box;" autocomplete="off" aria-controls="customerSearchResults" aria-expanded="false" oninput="searchCustomers(this.value)">
                    <div id="customerSearchResults" class="customer-search-dropdown" role="listbox" aria-live="polite"></div>
                    <input type="hidden" id="regSelectedCustomerId">
                </div>

                <!-- Selected Customer Card -->
                <div id="selectedCustomerCard" style="display: none; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between;">
                        <strong id="displayCustomerName" style="color: #0369A1;"></strong>
                        <span id="displayCustomerPhone" style="color: #64748B;"></span>
                    </div>
                </div>

                <!-- Container Selection -->
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="font-weight: 600; font-size: 13px; color: #1E293B;">2. Select Customer Container</label>
                        <a href="javascript:void(0)" onclick="toggleQuickAddContainer()" style="font-size: 12px; color: #0284C7; font-weight: 600;">+ New Container</a>
                    </div>
                    <select id="regContainerSelect" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box;" disabled>
                        <option value="">Loading eligible containers...</option>
                    </select>
                </div>

                <!-- Quick Add Container Inline Form -->
                <div id="quickAddContainerWrap" style="display: none; background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                    <label style="font-size: 12px; font-weight: 700; color: #0369A1; display: block; margin-bottom: 4px;">Quick Create Container</label>
                    <div style="display: flex; gap: 8px;">
                        <select id="quickContainerType" style="flex: 2; padding: 7px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12.5px;">
                            <option value="5-Gallon Standard Slim">5-Gallon Standard Slim</option>
                            <option value="5-Gallon Round">5-Gallon Round</option>
                            <option value="5-Gallon Dispenser Bottle">5-Gallon Dispenser Bottle</option>
                        </select>
                        <button type="button" class="btn-rfid-primary" style="flex: 1; padding: 7px; font-size: 12px;" onclick="submitQuickContainer()">Create</button>
                    </div>
                </div>

                <!-- Target Scanner Device -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">3. RFID Scanner Hardware</label>
                    <select id="regDeviceSelect" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box;">
                        <option value="">Select an online scanner</option>
                        @foreach($devices as $dev)
                            <option value="{{ $dev->id }}" @disabled(!$dev->isOnline())>
                                {{ $dev->device_name }} ({{ $dev->device_code }}) — {{ $dev->isOnline() ? 'ONLINE' : ($dev->status === 'maintenance' ? 'MAINTENANCE' : 'OFFLINE') }}
                            </option>
                        @endforeach
                    </select>
                    <small style="display: block; margin-top: 5px; color: #64748B;">Only scanners with a recent authenticated heartbeat can start registration.</small>
                </div>

                <div id="regErrorBanner" style="display: none; background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 14px;"></div>

                <button type="button" id="btnStartRegistration" class="btn-rfid-primary" style="width: 100%; justify-content: center; padding: 12px;" onclick="startRegistrationSession()">
                    Start RFID Registration
                </button>
            </div>

            <!-- Step 2: Waiting for Hardware Scan -->
            <div id="rfidScanningStep" style="display: none;">
                <div class="rfid-scanner-anim">
                    <div class="rfid-radar">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                    </div>
                    <div id="registrationConnectionIndicator" style="font-size: 12px; font-weight: 700; color: #0369A1; margin: 8px 0;">Checking ESP32 connection...</div>
                    <h4 class="rfid-anim-title" id="scanningAnimTitle">Waiting for ESP32 connection</h4>
                    <p class="rfid-anim-sub" id="scanningAnimSub">Please tap the physical RFID tag on the ESP32 RFID reader.</p>
                    <div class="rfid-countdown" id="sessionCountdownBadge">Session expires in 180s</div>
                </div>

                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span style="color: #64748B;">Target Container:</span>
                        <strong id="scanSummaryContainer">—</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748B;">Customer:</span>
                        <strong id="scanSummaryCustomer">—</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                        <span style="color: #64748B;">ESP32 scanner:</span>
                        <strong id="scanSummaryDevice">—</strong>
                    </div>
                </div>

                <div id="registrationFailureMessage" role="alert" style="display: none; background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 14px;"></div>
                <button type="button" id="btnRetryRegistration" class="btn-rfid-secondary" style="display: none; width: 100%; justify-content: center; padding: 10px; margin-bottom: 8px;" onclick="returnToRegistrationSetup()">Start New Registration</button>
                <button type="button" class="btn-rfid-secondary" style="width: 100%; justify-content: center; padding: 10px; color: #EF4444; border-color: #FECACA;" onclick="cancelRegistrationSession()">
                    Cancel Registration
                </button>
            </div>

            <!-- Step 3: Success Result -->
            <div id="rfidSuccessStep" style="display: none;">
                <div class="rfid-success-card">
                    <div class="rfid-success-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <h4 class="rfid-success-title">RFID Tag Successfully Registered!</h4>
                    <p style="margin: 0; font-size: 13px; color: #065F46;">The physical RFID tag is now permanently assigned to this customer gallon.</p>

                    <div class="rfid-success-details">
                        <div>Actual RFID UID: <span id="successUidDisplay" class="rfid-uid-pill" style="background:#ffffff;">—</span></div>
                        <div>Container ID: <strong id="successContainerIdDisplay">—</strong></div>
                        <div>Container: <strong id="successContainerDisplay">—</strong></div>
                        <div>Customer: <strong id="successCustomerDisplay">—</strong></div>
                        <div>ESP32 device: <strong id="successDeviceDisplay">—</strong></div>
                        <div>Registered: <strong id="successRegisteredAtDisplay">—</strong></div>
                    </div>
                    <p id="registryRefreshNotice" role="status" style="margin: 12px 0 0; font-size: 12px; color: #047857;"></p>
                </div>

                <button type="button" class="btn-rfid-primary" style="width: 100%; justify-content: center; padding: 12px;" onclick="closeRegisterRfidModal()">
                    Done
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 2: ADD CUSTOMER CONTAINER --}}
{{-- ========================================================================= --}}
<div class="modal-backdrop" id="addContainerModal" style="display: none; position: fixed; inset: 0; background: rgba(3, 27, 55, 0.7); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-card" style="background: #ffffff; width: 100%; max-width: 480px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); overflow: hidden; margin: 20px;">
        <div style="padding: 16px 20px; background: #041a33; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Enroll Customer Container</h3>
            <button type="button" onclick="closeAddContainerModal()" style="background: transparent; border: none; color: #ffffff; font-size: 22px; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 20px;">
            <div style="margin-bottom: 14px; position: relative;">
                <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">Customer</label>
                <input type="text" id="addConCustomerSearch" placeholder="Search customer name or phone..." style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box;" autocomplete="off" oninput="searchCustomersForAddCon(this.value)">
                <div id="addConCustomerDropdown" class="customer-search-dropdown"></div>
                <input type="hidden" id="addConSelectedCustomerId">
                <div id="addConCustomerSelectedBadge" style="display: none; margin-top: 6px; font-size: 12.5px; color: #0284C7; font-weight: 600;"></div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">Container Type</label>
                <select id="addConType" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
                    <option value="5-Gallon Standard Slim">5-Gallon Standard Slim (Blue)</option>
                    <option value="5-Gallon Round">5-Gallon Round</option>
                    <option value="5-Gallon Dispenser Bottle">5-Gallon Dispenser Bottle</option>
                    <option value="2.5-Gallon Compact">2.5-Gallon Compact</option>
                </select>
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">Condition / Notes</label>
                <input type="text" id="addConNotes" placeholder="Optional notes (e.g. slight scratch on handle)" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <div id="addConError" style="display: none; background: #FEF2F2; color: #991B1B; padding: 8px 12px; border-radius: 6px; font-size: 12.5px; margin-bottom: 12px;"></div>

            <button type="button" class="btn-rfid-primary" style="width: 100%; justify-content: center; padding: 11px;" onclick="submitAddContainer()">
                Save Container Record
            </button>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 3: REPLACE RFID TAG --}}
{{-- ========================================================================= --}}
<div class="modal-backdrop" id="replaceTagModal" style="display: none; position: fixed; inset: 0; background: rgba(3, 27, 55, 0.7); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-card" style="background: #ffffff; width: 100%; max-width: 460px; border-radius: 16px; overflow: hidden; margin: 20px;">
        <div style="padding: 16px 20px; background: #0369A1; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Replace RFID Tag</h3>
            <button type="button" onclick="closeReplaceTagModal()" style="background: transparent; border: none; color: #ffffff; font-size: 22px; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 20px;">
            <div style="background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 13px;">
                <div>Current Tag UID: <strong id="repOldUid" style="font-family: monospace;"></strong></div>
                <div>Container: <strong id="repContainerCode"></strong></div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">New Physical Tag UID</label>
                <input type="text" id="repNewUid" placeholder="Scan or enter new UID..." style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box; font-family: monospace;">
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">Reason for Replacement</label>
                <select id="repReason" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
                    <option value="Physical RFID tag damaged or peeling">Physical RFID tag damaged or peeling</option>
                    <option value="RFID chip unresponsive / failed read">RFID chip unresponsive / failed read</option>
                    <option value="Tag lost during customer transit">Tag lost during customer transit</option>
                    <option value="Administrative correction">Administrative correction</option>
                </select>
            </div>

            <input type="hidden" id="repHiddenTagId">
            <div id="repError" style="display: none; background: #FEF2F2; color: #991B1B; padding: 8px 12px; border-radius: 6px; font-size: 12.5px; margin-bottom: 12px;"></div>

            <button type="button" class="btn-rfid-primary" style="width: 100%; justify-content: center; padding: 11px;" onclick="submitReplaceTag()">
                Confirm Tag Replacement
            </button>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 4: DEACTIVATE RFID TAG --}}
{{-- ========================================================================= --}}
<div class="modal-backdrop" id="deactivateTagModal" style="display: none; position: fixed; inset: 0; background: rgba(3, 27, 55, 0.7); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-card" style="background: #ffffff; width: 100%; max-width: 440px; border-radius: 16px; overflow: hidden; margin: 20px;">
        <div style="padding: 16px 20px; background: #991B1B; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Deactivate RFID Tag</h3>
            <button type="button" onclick="closeDeactivateTagModal()" style="background: transparent; border: none; color: #ffffff; font-size: 22px; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 20px;">
            <p style="margin: 0 0 14px; font-size: 13.5px; color: #475569;">
                Are you sure you want to deactivate tag <strong id="deactUid" style="font-family: monospace;"></strong>? This tag will be rejected during all future station scans.
            </p>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">Reason for Deactivation</label>
                <input type="text" id="deactReason" placeholder="e.g. Gallon retired / damaged beyond use" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <input type="hidden" id="deactHiddenTagId">

            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-rfid-secondary" style="flex: 1; padding: 10px;" onclick="closeDeactivateTagModal()">Cancel</button>
                <button type="button" class="btn-rfid-primary" style="flex: 1; padding: 10px; background: #DC2626; border-color: #DC2626;" onclick="submitDeactivateTag()">Deactivate</button>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 5: ADD SCANNER HARDWARE DEVICE --}}
{{-- ========================================================================= --}}
<div class="modal-backdrop" id="addDeviceModal" style="display: none; position: fixed; inset: 0; background: rgba(3, 27, 55, 0.7); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-card" style="background: #ffffff; width: 100%; max-width: 480px; border-radius: 16px; overflow: hidden; margin: 20px;">
        <div style="padding: 16px 20px; background: #041a33; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Enroll ESP32 RFID Scanner</h3>
            <button type="button" onclick="closeAddDeviceModal()" style="background: transparent; border: none; color: #ffffff; font-size: 22px; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 20px;" id="addDeviceBody">
            <div style="margin-bottom: 14px;">
                <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">Device Code (Identifier)</label>
                <input type="text" id="devCode" placeholder="e.g. ESP32-STA-02" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box; text-transform: uppercase;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">Device Name</label>
                <input type="text" id="devName" placeholder="e.g. Station Intake Bay 2" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-weight: 600; font-size: 13px; color: #1E293B; margin-bottom: 6px;">Physical Location</label>
                <input type="text" id="devLocation" placeholder="e.g. Refill Station Intake Counter" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <div id="addDevError" style="display: none; background: #FEF2F2; color: #991B1B; padding: 8px 12px; border-radius: 6px; font-size: 12.5px; margin-bottom: 12px;"></div>

            <button type="button" class="btn-rfid-primary" style="width: 100%; justify-content: center; padding: 11px;" onclick="submitAddDevice()">
                Generate Device &amp; Secret Token
            </button>
        </div>

        <div style="padding: 20px; display: none;" id="addDeviceTokenSuccess">
            <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
                <strong style="color: #065F46; font-size: 14px; display: block; margin-bottom: 6px;">Device Enrolled Successfully!</strong>
                <p style="margin: 0 0 10px; font-size: 12.5px; color: #047857;">Copy this secret token now. It will not be shown again.</p>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="displayNewToken" readonly style="flex: 1; padding: 8px; font-family: monospace; font-size: 12.5px; background: #ffffff; border: 1px solid #A7F3D0; border-radius: 6px;">
                    <button type="button" class="btn-rfid-secondary" style="padding: 8px 12px;" onclick="copyToClipboard('displayNewToken')">Copy</button>
                </div>
            </div>
            <button type="button" class="btn-rfid-primary" style="width: 100%; justify-content: center; padding: 10px;" onclick="window.location.reload()">
                Done
            </button>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 6: TOKEN ROTATED RESULT --}}
{{-- ========================================================================= --}}
<div class="modal-backdrop" id="tokenRotatedModal" style="display: none; position: fixed; inset: 0; background: rgba(3, 27, 55, 0.7); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-card" style="background: #ffffff; width: 100%; max-width: 460px; border-radius: 16px; overflow: hidden; margin: 20px; padding: 22px;">
        <h3 style="margin: 0 0 8px; font-size: 16px; color: #0F172A;">Security Token Rotated</h3>
        <p style="margin: 0 0 14px; font-size: 13px; color: #475569;">The previous token is now permanently invalid. Flash the new token to your ESP32 device.</p>
        <div style="display: flex; gap: 8px; margin-bottom: 18px;">
            <input type="text" id="rotatedTokenDisplay" readonly style="flex: 1; padding: 9px; font-family: monospace; font-size: 12.5px; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px;">
            <button type="button" class="btn-rfid-secondary" style="padding: 9px 12px;" onclick="copyToClipboard('rotatedTokenDisplay')">Copy</button>
        </div>
        <button type="button" class="btn-rfid-primary" style="width: 100%; justify-content: center; padding: 10px;" onclick="document.getElementById('tokenRotatedModal').style.display='none'">Close</button>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    let activeSessionId = null;
    let pollInterval = null;
    let countdownInterval = null;
    let countdownRemaining = 180;
    let customerSearchTimeout = null;
    let customerSearchSequence = 0;
    let selectedRegistrationCustomerId = null;
    let lastDisplayedScanId = null;
    const scanResultUrlTemplate = @json(route('admin.rfid.scans.result', ['transaction' => 'SCAN_ID_PLACEHOLDER']));

    window.addEventListener('aqua:realtime', async event => {
        const signal = event.detail || {};
        if (signal.entity_type !== 'rfid' || signal.event !== 'RFID_SCAN_RESULT' || !signal.entity_id) return;
        if (signal.entity_id === lastDisplayedScanId) return;

        try {
            const resultUrl = scanResultUrlTemplate.replace('SCAN_ID_PLACEHOLDER', encodeURIComponent(signal.entity_id));
            const response = await fetch(resultUrl, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('Could not load the latest RFID scan result.');
            const result = await response.json();

            lastDisplayedScanId = result.scan_id;
            const succeeded = result.status === 'SUCCESS';
            document.getElementById('latestRfidScanIndicator').style.background = succeeded ? '#059669' : '#DC2626';
            document.getElementById('latestRfidScanStatus').textContent = succeeded ? 'SUCCESS' : 'FAILED';
            document.getElementById('latestRfidScanStatus').style.color = succeeded ? '#047857' : '#B91C1C';
            document.getElementById('latestRfidScanMessage').textContent = result.message || 'RFID scan rejected.';

            const customerDetails = document.getElementById('latestRfidScanCustomerDetails');
            if (succeeded) {
                const count = Number(result.gallon_count) || 0;
                customerDetails.textContent = `Customer: ${result.customer_name} · Gallons: ${count}`;
                customerDetails.style.display = 'block';
            } else {
                customerDetails.textContent = '';
                customerDetails.style.display = 'none';
            }
            document.getElementById('latestRfidScanTime').textContent = result.scanned_at
                ? new Date(result.scanned_at).toLocaleString()
                : '';
        } catch (error) {
            document.getElementById('latestRfidScanStatus').textContent = 'SCAN RESULT UNAVAILABLE';
            document.getElementById('latestRfidScanStatus').style.color = '#B91C1C';
            document.getElementById('latestRfidScanMessage').textContent = error.message || 'Could not load the RFID scan result.';
        }
    });

    // Helper: Copy text to clipboard
    function copyToClipboard(elementId) {
        const input = document.getElementById(elementId);
        if (!input) return;
        input.select();
        navigator.clipboard.writeText(input.value).then(() => {
            alert('Copied token to clipboard!');
        });
    }

    // Modal Control: Register RFID
    function openRegisterRfidModal() {
        clearInterval(pollInterval);
        clearInterval(countdownInterval);
        activeSessionId = null;
        selectedRegistrationCustomerId = null;
        document.getElementById('rfidSetupStep').style.display = 'block';
        document.getElementById('rfidScanningStep').style.display = 'none';
        document.getElementById('rfidSuccessStep').style.display = 'none';
        document.getElementById('regErrorBanner').style.display = 'none';
        document.getElementById('registrationFailureMessage').style.display = 'none';
        document.getElementById('regCustomerSearch').value = '';
        document.getElementById('regSelectedCustomerId').value = '';
        document.getElementById('selectedCustomerCard').style.display = 'none';
        document.getElementById('quickAddContainerWrap').style.display = 'none';
        document.getElementById('regContainerSelect').innerHTML = '<option value="">Select customer first...</option>';
        document.getElementById('regContainerSelect').disabled = true;
        document.getElementById('regDeviceSelect').value = '';
        document.getElementById('btnStartRegistration').disabled = false;
        document.getElementById('btnStartRegistration').textContent = 'Start RFID Registration';
        document.getElementById('registerRfidModal').style.display = 'flex';
        loadEligibleCustomers('');
    }

    async function closeRegisterRfidModal() {
        if (activeSessionId) {
            const cancelled = await cancelRegistrationSession();
            if (!cancelled) return;
        }
        document.getElementById('registerRfidModal').style.display = 'none';
        clearInterval(pollInterval);
        clearInterval(countdownInterval);
    }

    function renderCustomerDropdownMessage(message, isError = false) {
        const dropdown = document.getElementById('customerSearchResults');
        dropdown.replaceChildren();
        const item = document.createElement('div');
        item.className = 'customer-search-item';
        item.textContent = message;
        if (isError) item.style.color = '#B91C1C';
        dropdown.appendChild(item);
        dropdown.style.display = 'block';
        document.getElementById('regCustomerSearch').setAttribute('aria-expanded', 'true');
    }

    async function loadEligibleCustomers(query = '') {
        const dropdown = document.getElementById('customerSearchResults');
        const requestSequence = ++customerSearchSequence;
        renderCustomerDropdownMessage('Loading eligible customers...');

        try {
            const response = await fetch(`{{ route('admin.rfid.customers.search') }}?q=${encodeURIComponent(query)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) throw new Error('Unable to load eligible customers.');
            const data = await response.json();
            if (requestSequence !== customerSearchSequence) return;

            dropdown.replaceChildren();
            const seenIds = new Set();
            const customers = (data.customers || []).filter(customer => {
                if (!customer.id || seenIds.has(customer.id)) return false;
                seenIds.add(customer.id);
                return true;
            });

            if (customers.length === 0) {
                renderCustomerDropdownMessage(query ? 'No eligible customers match your search.' : 'No customers have an unregistered active container.');
                return;
            }

            customers.forEach(customer => {
                const item = document.createElement('div');
                item.className = 'customer-search-item';
                item.setAttribute('role', 'option');
                item.tabIndex = 0;
                const name = document.createElement('strong');
                name.textContent = customer.name || 'Unknown customer';
                const details = document.createElement('span');
                details.style.cssText = 'color:#64748B; font-size:12px; display:block;';
                details.textContent = [customer.phone, customer.email].filter(Boolean).join(' · ');
                item.append(name, details);
                item.onclick = () => selectCustomer(customer);
                item.onkeydown = event => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        selectCustomer(customer);
                    }
                };
                dropdown.appendChild(item);
            });
            dropdown.style.display = 'block';
            document.getElementById('regCustomerSearch').setAttribute('aria-expanded', 'true');
        } catch (error) {
            if (requestSequence !== customerSearchSequence) return;
            renderCustomerDropdownMessage(error.message || 'Could not load eligible customers. Try again.', true);
        }
    }

    function searchCustomers(query) {
        clearTimeout(customerSearchTimeout);
        customerSearchSequence++;
        selectedRegistrationCustomerId = null;
        document.getElementById('regSelectedCustomerId').value = '';
        document.getElementById('selectedCustomerCard').style.display = 'none';
        document.getElementById('regContainerSelect').disabled = true;
        document.getElementById('regContainerSelect').innerHTML = '<option value="">Select a customer first...</option>';
        customerSearchTimeout = setTimeout(() => loadEligibleCustomers(query.trim()), 250);
    }

    function selectCustomer(customer, selectContainerId = null) {
        clearTimeout(customerSearchTimeout);
        customerSearchSequence++;
        selectedRegistrationCustomerId = customer.id;
        document.getElementById('regCustomerSearch').value = customer.name;
        document.getElementById('regSelectedCustomerId').value = customer.id;
        document.getElementById('customerSearchResults').replaceChildren();
        document.getElementById('customerSearchResults').style.display = 'none';
        document.getElementById('regCustomerSearch').setAttribute('aria-expanded', 'false');
        document.getElementById('selectedCustomerCard').style.display = 'block';
        document.getElementById('displayCustomerName').textContent = customer.name;
        document.getElementById('displayCustomerPhone').textContent = [customer.phone, customer.email].filter(Boolean).join(' · ');

        // Load customer containers
        loadCustomerContainers(customer.id, selectContainerId);
    }

    async function loadCustomerContainers(customerId, selectContainerId = null) {
        const select = document.getElementById('regContainerSelect');
        const requestCustomerId = customerId;
        select.disabled = true;
        select.innerHTML = '<option value="">Loading containers...</option>';

        try {
            const response = await fetch(`/admin/rfid/customers/${customerId}/containers`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) throw new Error('Could not load this customer’s containers.');
            const data = await response.json();
            if (requestCustomerId !== selectedRegistrationCustomerId) return;
            select.replaceChildren();

            if (data.containers && data.containers.length > 0) {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select an unregistered container...';
                select.appendChild(placeholder);
                data.containers.forEach(container => {
                    const option = document.createElement('option');
                    option.value = container.id;
                    option.textContent = `${container.container_code} (${container.container_type}) · ${container.capacity} gal · ${container.status}`;
                    option.selected = selectContainerId === container.id;
                    select.appendChild(option);
                });
                select.disabled = false;
            } else {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'No eligible containers. Create a new container below.';
                select.appendChild(placeholder);
                document.getElementById('quickAddContainerWrap').style.display = 'block';
            }
        } catch (error) {
            if (requestCustomerId !== selectedRegistrationCustomerId) return;
            select.innerHTML = '<option value="">Unable to load containers. Please try selecting the customer again.</option>';
            select.disabled = true;
            showRegistrationError(error.message || 'Could not load customer containers.');
        }
    }

    function toggleQuickAddContainer() {
        const wrap = document.getElementById('quickAddContainerWrap');
        wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
    }

    async function submitQuickContainer() {
        const customerId = document.getElementById('regSelectedCustomerId').value;
        if (!customerId) {
            showRegistrationError('Please select a customer first.');
            return;
        }

        const type = document.getElementById('quickContainerType').value;
        try {
            const response = await fetch('{{ route('admin.rfid.containers.store') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ customer_id: customerId, container_type: type, capacity: 5.00 })
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Error creating container.');
            document.getElementById('quickAddContainerWrap').style.display = 'none';
            await loadCustomerContainers(customerId, data.container.id);
        } catch (error) {
            showRegistrationError(error.message || 'Could not create the container.');
        }
    }

    function showRegistrationError(message) {
        const banner = document.getElementById('regErrorBanner');
        banner.textContent = message;
        banner.style.display = 'block';
    }

    function responseError(data) {
        if (data.message && data.message !== 'The given data was invalid.') return data.message;
        const firstValidationError = data.errors ? Object.values(data.errors).flat()[0] : null;
        return firstValidationError || 'The registration request could not be completed.';
    }

    async function startRegistrationSession() {
        const customerId = document.getElementById('regSelectedCustomerId').value;
        const containerId = document.getElementById('regContainerSelect').value;
        const deviceId = document.getElementById('regDeviceSelect').value;
        const errBanner = document.getElementById('regErrorBanner');
        errBanner.style.display = 'none';

        if (!customerId || customerId !== selectedRegistrationCustomerId) {
            showRegistrationError('Please select an eligible customer.');
            return;
        }
        if (!containerId) {
            showRegistrationError('Please select an eligible, unregistered customer container.');
            return;
        }
        if (!deviceId) {
            showRegistrationError('Please select an online ESP32 RFID scanner.');
            return;
        }

        const btn = document.getElementById('btnStartRegistration');
        btn.disabled = true;
        btn.textContent = 'Starting Session...';

        try {
            const response = await fetch('{{ route('admin.rfid.sessions.create') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({
                    customer_id: customerId,
                    customer_container_id: containerId,
                    station_device_id: deviceId
                })
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(responseError(data));
            btn.disabled = false;
            btn.textContent = 'Start RFID Registration';

            activeSessionId = data.session.id;
            document.getElementById('scanSummaryContainer').textContent = data.session.container_code;
            document.getElementById('scanSummaryCustomer').textContent = data.session.customer_name;
            document.getElementById('scanSummaryDevice').textContent = data.session.device_name;
            document.getElementById('registrationFailureMessage').style.display = 'none';
            document.getElementById('btnRetryRegistration').style.display = 'none';
            document.getElementById('registrationConnectionIndicator').textContent = 'Waiting for selected ESP32 scanner';

            // Transition to Step 2
            document.getElementById('rfidSetupStep').style.display = 'none';
            document.getElementById('rfidScanningStep').style.display = 'block';

            countdownRemaining = data.session.expires_in_seconds || 180;
            updateCountdownBadge();
            countdownInterval = setInterval(() => {
                countdownRemaining = Math.max(0, countdownRemaining - 1);
                updateCountdownBadge();
            }, 1000);

            await checkSessionProgress();
            if (activeSessionId) {
                pollInterval = setInterval(checkSessionProgress, 1500);
            }
        } catch (error) {
            btn.disabled = false;
            btn.textContent = 'Start RFID Registration';
            showRegistrationError(error.message || 'Could not communicate with the backend to start registration.');
        }
    }

    function updateCountdownBadge() {
        const badge = document.getElementById('sessionCountdownBadge');
        if (badge) {
            badge.textContent = `Session expires in ${countdownRemaining}s`;
        }
    }

    async function checkSessionProgress() {
        if (!activeSessionId) return;

        try {
            const response = await fetch(`/admin/rfid/registration-sessions/${activeSessionId}/status`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (!response.ok) throw new Error(responseError(data));

            if (data.status === 'completed') {
                clearInterval(pollInterval);
                clearInterval(countdownInterval);
                document.getElementById('rfidScanningStep').style.display = 'none';
                document.getElementById('rfidSuccessStep').style.display = 'block';
                document.getElementById('successUidDisplay').textContent = data.scanned_uid || '—';
                document.getElementById('successContainerIdDisplay').textContent = data.container.id || '—';
                document.getElementById('successContainerDisplay').textContent = `${data.container.code || '—'} (${data.container.customer_name || '—'})`;
                document.getElementById('successCustomerDisplay').textContent = data.container.customer_name || '—';
                document.getElementById('successDeviceDisplay').textContent = data.device.name || '—';
                document.getElementById('successRegisteredAtDisplay').textContent = data.completed_at ? new Date(data.completed_at).toLocaleString() : '—';
                activeSessionId = null;
                await Promise.all([
                    loadEligibleCustomers(''),
                    loadCustomerContainers(selectedRegistrationCustomerId)
                ]);
                const refreshed = await refreshRfidRegistry();
                document.getElementById('registryRefreshNotice').textContent = refreshed
                    ? 'RFID registry and eligible containers have been refreshed.'
                    : 'Tag registration succeeded. Reload the page if the registry list does not update.';
                return;
            }

            if (data.progress === 'waiting_for_device') {
                document.getElementById('registrationConnectionIndicator').textContent = 'Waiting for ESP32 connection';
                document.getElementById('scanningAnimTitle').textContent = 'Waiting for ESP32 connection';
            } else if (data.progress === 'device_disconnected') {
                document.getElementById('registrationConnectionIndicator').textContent = 'ESP32 disconnected';
                document.getElementById('scanningAnimTitle').textContent = 'Waiting for ESP32 reconnection';
            } else if (data.progress === 'waiting_for_tag') {
                document.getElementById('registrationConnectionIndicator').textContent = 'ESP32 connected · registration command sent';
                document.getElementById('scanningAnimTitle').textContent = 'WAITING FOR RFID TAG';
            } else if (data.progress === 'retry_scan') {
                document.getElementById('registrationConnectionIndicator').textContent = 'RFID REGISTRATION FAILED — PLEASE TAP ANOTHER RFID TAG';
                document.getElementById('scanningAnimTitle').textContent = 'RFID REGISTRATION FAILED';
                document.getElementById('registrationFailureMessage').textContent = data.progress_message;
                document.getElementById('registrationFailureMessage').style.display = 'block';
            } else if (data.status === 'pending') {
                document.getElementById('registrationConnectionIndicator').textContent = 'RFID tag detected · validating UID';
                document.getElementById('scanningAnimTitle').textContent = 'Validating RFID UID';
            }
            document.getElementById('scanningAnimSub').textContent = data.progress_message || 'Please tap the physical RFID tag on the ESP32 RFID reader.';
            if (data.status === 'pending' && data.progress !== 'retry_scan') {
                document.getElementById('registrationFailureMessage').style.display = 'none';
            }

            if (data.status === 'failed' || data.status === 'expired' || data.status === 'cancelled') {
                clearInterval(pollInterval);
                clearInterval(countdownInterval);
                activeSessionId = null;
                document.getElementById('registrationFailureMessage').textContent = data.failure_message || data.progress_message || 'Registration failed.';
                document.getElementById('registrationFailureMessage').style.display = 'block';
                document.getElementById('btnRetryRegistration').style.display = 'block';
                document.getElementById('registrationConnectionIndicator').textContent = data.status === 'expired' ? 'Registration session expired' : 'Registration ended';
            }
        } catch (error) {
            document.getElementById('registrationConnectionIndicator').textContent = 'Registration status unavailable';
            document.getElementById('scanningAnimSub').textContent = error.message || 'Could not reach the backend. Retrying shortly.';
        }
    }

    async function refreshRfidRegistry() {
        try {
            const response = await fetch(window.location.href, { headers: { 'Accept': 'text/html' } });
            if (!response.ok) return false;
            const html = await response.text();
            const refreshedPage = new DOMParser().parseFromString(html, 'text/html');
            ['.stat-grid', '.data-table-wrap'].forEach(selector => {
                const current = document.querySelector(selector);
                const refreshed = refreshedPage.querySelector(selector);
                if (current && refreshed) current.replaceWith(refreshed);
            });
            const currentBadges = document.querySelectorAll('.rfid-tab-badge');
            const refreshedBadges = refreshedPage.querySelectorAll('.rfid-tab-badge');
            currentBadges.forEach((badge, index) => {
                if (refreshedBadges[index]) badge.textContent = refreshedBadges[index].textContent;
            });
            return true;
        } catch (error) {
            return false;
        }
    }

    async function cancelRegistrationSession() {
        if (activeSessionId) {
            const sessionId = activeSessionId;
            try {
                const response = await fetch(`/admin/rfid/registration-sessions/${sessionId}/cancel`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                if (!response.ok) {
                    throw new Error('The backend could not cancel this session.');
                }
            } catch (error) {
                const message = error.message || 'The backend could not cancel this session. Please try again.';
                document.getElementById('registrationFailureMessage').textContent = message;
                document.getElementById('registrationFailureMessage').style.display = 'block';
                return false;
            }
        }
        clearInterval(pollInterval);
        clearInterval(countdownInterval);
        activeSessionId = null;
        document.getElementById('rfidScanningStep').style.display = 'none';
        document.getElementById('rfidSetupStep').style.display = 'block';
        return true;
    }

    function returnToRegistrationSetup() {
        activeSessionId = null;
        document.getElementById('registrationFailureMessage').style.display = 'none';
        document.getElementById('btnRetryRegistration').style.display = 'none';
        document.getElementById('rfidScanningStep').style.display = 'none';
        document.getElementById('rfidSetupStep').style.display = 'block';
        document.getElementById('regErrorBanner').style.display = 'none';
        document.getElementById('btnStartRegistration').disabled = false;
        document.getElementById('btnStartRegistration').textContent = 'Start RFID Registration';
    }

    function startRegistrationForContainer(containerId, containerCode, customerId, customerName) {
        openRegisterRfidModal();
        selectCustomer({ id: customerId, name: customerName, phone: '' }, containerId);
    }

    // Modal 2: Add Container
    function openAddContainerModal() {
        document.getElementById('addContainerModal').style.display = 'flex';
    }
    function closeAddContainerModal() {
        document.getElementById('addContainerModal').style.display = 'none';
    }
    function searchCustomersForAddCon(q) {
        const dropdown = document.getElementById('addConCustomerDropdown');
        if (q.trim().length < 2) { dropdown.style.display = 'none'; return; }
        fetch(`{{ route('admin.rfid.customers.search') }}?include_all=1&q=${encodeURIComponent(q)}`)
            .then(res => res.json())
            .then(data => {
                dropdown.innerHTML = '';
                if (data.customers && data.customers.length > 0) {
                    data.customers.forEach(c => {
                        const item = document.createElement('div');
                        item.className = 'customer-search-item';
                        item.innerHTML = `<strong>${c.name}</strong> (${c.phone})`;
                        item.onclick = () => {
                            document.getElementById('addConCustomerSearch').value = c.name;
                            document.getElementById('addConSelectedCustomerId').value = c.id;
                            document.getElementById('addConCustomerSelectedBadge').textContent = `Selected: ${c.name} (${c.phone})`;
                            document.getElementById('addConCustomerSelectedBadge').style.display = 'block';
                            dropdown.style.display = 'none';
                        };
                        dropdown.appendChild(item);
                    });
                    dropdown.style.display = 'block';
                }
            });
    }
    function submitAddContainer() {
        const customerId = document.getElementById('addConSelectedCustomerId').value;
        const type = document.getElementById('addConType').value;
        const notes = document.getElementById('addConNotes').value;
        const err = document.getElementById('addConError');
        err.style.display = 'none';

        if (!customerId) {
            err.textContent = 'Please select a customer first.';
            err.style.display = 'block';
            return;
        }

        fetch('{{ route('admin.rfid.containers.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ customer_id: customerId, container_type: type, notes: notes, capacity: 5.00 })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = "{{ route('admin.rfid.index', ['tab' => 'containers']) }}";
            } else {
                err.textContent = data.message || 'Error saving container.';
                err.style.display = 'block';
            }
        });
    }

    // Modal 3: Replace Tag
    function openReplaceTagModal(tagId, uid, containerCode) {
        document.getElementById('repHiddenTagId').value = tagId;
        document.getElementById('repOldUid').textContent = uid;
        document.getElementById('repContainerCode').textContent = containerCode || '—';
        document.getElementById('repNewUid').value = '';
        document.getElementById('repError').style.display = 'none';
        document.getElementById('replaceTagModal').style.display = 'flex';
    }
    function closeReplaceTagModal() {
        document.getElementById('replaceTagModal').style.display = 'none';
    }
    function submitReplaceTag() {
        const tagId = document.getElementById('repHiddenTagId').value;
        const newUid = document.getElementById('repNewUid').value.trim();
        const reason = document.getElementById('repReason').value;
        const err = document.getElementById('repError');
        err.style.display = 'none';

        if (!newUid) {
            err.textContent = 'Please input or scan the new RFID UID.';
            err.style.display = 'block';
            return;
        }

        fetch(`/admin/rfid/tags/${tagId}/replace`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ new_uid: newUid, reason: reason })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('RFID tag replaced successfully!');
                window.location.reload();
            } else {
                err.textContent = data.message || 'Error replacing tag.';
                err.style.display = 'block';
            }
        });
    }

    // Modal 4: Deactivate Tag
    function openDeactivateTagModal(tagId, uid) {
        document.getElementById('deactHiddenTagId').value = tagId;
        document.getElementById('deactUid').textContent = uid;
        document.getElementById('deactivateTagModal').style.display = 'flex';
    }
    function closeDeactivateTagModal() {
        document.getElementById('deactivateTagModal').style.display = 'none';
    }
    function submitDeactivateTag() {
        const tagId = document.getElementById('deactHiddenTagId').value;
        const reason = document.getElementById('deactReason').value.trim() || 'Manual admin deactivation';

        fetch(`/admin/rfid/tags/${tagId}/deactivate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ reason: reason })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('RFID tag deactivated successfully.');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to deactivate tag.');
            }
        });
    }

    // Modal 5: Add Device
    function openAddDeviceModal() {
        document.getElementById('addDeviceBody').style.display = 'block';
        document.getElementById('addDeviceTokenSuccess').style.display = 'none';
        document.getElementById('addDevError').style.display = 'none';
        document.getElementById('addDeviceModal').style.display = 'flex';
    }
    function closeAddDeviceModal() {
        document.getElementById('addDeviceModal').style.display = 'none';
    }
    function submitAddDevice() {
        const code = document.getElementById('devCode').value.trim();
        const name = document.getElementById('devName').value.trim();
        const loc = document.getElementById('devLocation').value.trim();
        const err = document.getElementById('addDevError');
        err.style.display = 'none';

        if (!code || !name || !loc) {
            err.textContent = 'Please fill out all required device fields.';
            err.style.display = 'block';
            return;
        }

        fetch('{{ route('admin.rfid.devices.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ device_code: code, device_name: name, location: loc })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('addDeviceBody').style.display = 'none';
                document.getElementById('displayNewToken').value = data.plain_token;
                document.getElementById('addDeviceTokenSuccess').style.display = 'block';
            } else {
                err.textContent = data.message || 'Error enrolling device.';
                err.style.display = 'block';
            }
        });
    }

    // Rotate Device Token
    function rotateDeviceToken(deviceId, deviceCode) {
        if (!confirm(`Are you sure you want to rotate the security credential for ${deviceCode}? The device will be disconnected until updated with the new token.`)) {
            return;
        }

        fetch(`/admin/rfid/devices/${deviceId}/rotate-token`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('rotatedTokenDisplay').value = data.plain_token;
                document.getElementById('tokenRotatedModal').style.display = 'flex';
            } else {
                alert(data.message || 'Error rotating token.');
            }
        });
    }

    // Toggle Device Online/Maintenance
    function toggleDeviceStatus(deviceId) {
        fetch(`/admin/rfid/devices/${deviceId}/toggle-status`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
</script>
@endpush

