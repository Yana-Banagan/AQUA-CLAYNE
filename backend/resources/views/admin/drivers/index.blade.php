@extends('admin.layouts.app')

@section('title', 'Drivers Management')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Station Logistics</p>
        <h1>Station Drivers</h1>
        <p>Manage authorized station personnel responsible for customer gallon pickup and return delivery.</p>
    </div>
    @if(auth()->user()->role === 'admin')
        <div class="header-actions">
            <button class="primary-button" type="button" id="openRegisterDriverModal">
                <span>＋</span>
                <span>Register New Driver</span>
            </button>
        </div>
    @endif
</div>

<!-- KPI Cards -->
<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <article class="stat-card">
        <span class="stat-label">Total Registered</span>
        <strong class="stat-value">{{ $drivers->total() }}</strong>
        <small class="stat-meta">Authorized drivers</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Active Drivers</span>
        <strong class="stat-value">{{ $drivers->where('user.status', 'active')->count() }}</strong>
        <small class="stat-meta">Available for assignment</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Deactivated</span>
        <strong class="stat-value">{{ $drivers->where('user.status', 'inactive')->count() }}</strong>
        <small class="stat-meta">Historical preserved</small>
    </article>
</section>

@if(session('success'))
    <div class="flash-message success" role="status">{{ session('success') }}</div>
@endif

<!-- Drivers Directory Table -->
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Driver Directory</h2>
            <small style="color: var(--text-secondary); font-size: 12px;">Accounts are created by Admin. Deactivation preserves delivery records.</small>
        </div>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Driver</th>
                    <th>Phone Contact</th>
                    <th>License / Plate</th>
                    <th>Status</th>
                    <th>Account Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drivers as $driver)
                    <tr>
                        <td style="display: flex; align-items: center; gap: 12px;">
                            <span class="avatar">@include('admin.partials.avatar-content', ['user' => $driver->user, 'fallbackName' => 'Driver'])</span>
                            <div>
                                <strong style="color: var(--text-primary); font-size: 13.5px;">{{ $driver->user?->name ?? 'Driver' }}</strong>
                                <small style="display: block; color: var(--text-secondary);">{{ $driver->user?->email }}</small>
                            </div>
                        </td>
                        <td>{{ $driver->user?->phone ?: '—' }}</td>
                        <td>
                            <strong>{{ $driver->license_number ?: 'No license listed' }}</strong>
                            <small style="display: block; color: var(--text-secondary);">{{ $driver->vehicle_plate ?: 'No vehicle plate' }}</small>
                        </td>
                        <td>
                            <span class="status-badge {{ $driver->user?->status === 'active' ? 'status-success' : 'status-danger' }}">
                                {{ $driver->user?->status }}
                            </span>
                        </td>
                        <td>
                            <small style="color: var(--text-secondary);">{{ $driver->created_at?->format('M d, Y') }}</small>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a class="table-action" href="{{ route('admin.drivers.edit', $driver) }}">
                                    Edit Details
                                </a>

                                <form method="POST" action="{{ route('admin.drivers.toggle-status', $driver) }}" data-disable-on-submit data-confirm="{{ $driver->user?->status === 'active' ? 'Deactivate driver '.$driver->user?->name.'? Historical deliveries will be kept intact.' : 'Reactivate driver '.$driver->user?->name.'?' }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="table-action {{ $driver->user?->status === 'active' ? 'danger' : 'success' }}">
                                        {{ $driver->user?->status === 'active' ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <span class="empty-icon">♜</span>
                                <strong>No drivers registered yet</strong>
                                <p>Create a driver account to begin dispatching empty gallon pickups and return deliveries.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($drivers->hasPages())
        <div style="padding: 20px 0;">
            {{ $drivers->links() }}
        </div>
    @endif
</section>
@if(auth()->user()->role === 'admin')
    @include('admin.drivers.partials.register-modal')
@endif
@endsection
