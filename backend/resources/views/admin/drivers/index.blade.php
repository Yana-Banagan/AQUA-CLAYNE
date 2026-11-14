@extends('admin.layouts.app')

@section('title', 'Drivers Management')

@section('content')
<div id="driverEditNotice" class="flash-message" role="status" aria-live="polite" hidden></div>
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
                    <tr data-driver-row="{{ $driver->id }}">
                        <td style="display: flex; align-items: center; gap: 12px;">
                            <span class="avatar">@include('admin.partials.avatar-content', ['user' => $driver->user, 'fallbackName' => 'Driver'])</span>
                            <div>
                                <strong data-driver-name style="color: var(--text-primary); font-size: 13.5px;">{{ $driver->user?->name ?? 'Driver' }}</strong>
                                <small data-driver-email style="display: block; color: var(--text-secondary);">{{ $driver->user?->email }}</small>
                            </div>
                        </td>
                        <td data-driver-phone>{{ $driver->user?->phone ?: '—' }}</td>
                        <td>
                            <strong data-driver-license>{{ $driver->license_number ?: 'No license listed' }}</strong>
                            <small data-driver-plate style="display: block; color: var(--text-secondary);">{{ $driver->vehicle_plate ?: 'No vehicle plate' }}</small>
                        </td>
                        <td>
                            <span data-driver-status class="status-badge {{ $driver->user?->status === 'active' ? 'status-success' : 'status-danger' }}">
                                {{ $driver->user?->status }}
                            </span>
                        </td>
                        <td>
                            <small style="color: var(--text-secondary);">{{ $driver->created_at?->format('M d, Y') }}</small>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button class="table-action" type="button" data-driver-edit="{{ route('admin.drivers.edit', $driver) }}">
                                    Edit Details
                                </button>

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

<div class="modal-backdrop" id="driverEditModal" aria-hidden="true">
    <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="driverEditTitle" style="padding: 24px;">
        <div class="panel-head">
            <div>
                <p class="eyebrow">Driver Profile</p>
                <h2 id="driverEditTitle">Edit Driver</h2>
            </div>
            <button type="button" class="icon-button" data-driver-modal-close aria-label="Close">&times;</button>
        </div>
        <form id="driverEditForm" method="POST">
            @csrf
            @method('PUT')
            <div id="driverEditError" class="flash-message danger" role="alert" hidden></div>
            <div class="form-grid">
                <div class="field"><label for="driverEditName">Full Name *</label><input id="driverEditName" name="name" maxlength="150" required></div>
                <div class="field"><label for="driverEditEmail">Email Address *</label><input id="driverEditEmail" name="email" type="email" maxlength="150" required></div>
                <div class="field"><label for="driverEditPhone">Contact Number</label><input id="driverEditPhone" name="phone" type="tel" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11"><small>Use the registered mobile format: 09xxxxxxxxx.</small></div>
                <div class="field" data-driver-restricted><label for="driverEditStatus">Account Status *</label><select id="driverEditStatus" name="status" required><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div class="field" data-driver-restricted><label for="driverEditLicense">License / ID Reference</label><input id="driverEditLicense" name="license_number" maxlength="50"></div>
                <div class="field" data-driver-restricted><label for="driverEditPlate">Vehicle Plate</label><input id="driverEditPlate" name="vehicle_plate" maxlength="20"></div>
            </div>
            <div class="form-actions">
                <button type="button" class="secondary-button" data-driver-modal-close>Cancel</button>
                <button type="submit" class="primary-button">Save Changes</button>
            </div>
        </form>
    </section>
</div>

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('driverEditModal');
    const form = document.getElementById('driverEditForm');
    const error = document.getElementById('driverEditError');
    const notice = document.getElementById('driverEditNotice');
    const fields = Object.fromEntries(Array.from(form.elements).map(element => [element.name, element]));

    function closeModal() {
        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function showError(message) {
        error.textContent = message;
        error.hidden = false;
    }

    document.querySelectorAll('[data-driver-edit]').forEach(button => button.addEventListener('click', async () => {
        error.hidden = true;
        form.reset();
        form.action = button.dataset.driverEdit;
        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        fields.name.focus();
        try {
            const response = await fetch(form.action, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Could not load this driver.');
            ['name', 'email', 'phone', 'status', 'license_number', 'vehicle_plate'].forEach(key => fields[key].value = data[key] || '');
            ['status', 'license_number', 'vehicle_plate'].forEach(key => fields[key].disabled = !data.can_manage_restricted_fields);
        } catch (requestError) {
            showError(requestError.message || 'Could not load this driver.');
        }
    }));

    modal.addEventListener('click', event => {
        if (event.target === modal || event.target.closest('[data-driver-modal-close]')) closeModal();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.classList.contains('is-active')) closeModal();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        error.hidden = true;
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'PUT',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.elements._token.value },
                body: new FormData(form)
            });
            const data = await response.json();
            if (!response.ok) {
                const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(validationMessage || data.message || 'Could not save this driver.');
            }
            const row = document.querySelector(`[data-driver-row="${CSS.escape(data.driver.id)}"]`);
            if (row) {
                row.querySelector('[data-driver-name]').textContent = data.driver.name || 'Driver';
                row.querySelector('[data-driver-email]').textContent = data.driver.email || '';
                row.querySelector('[data-driver-phone]').textContent = data.driver.phone || '—';
                row.querySelector('[data-driver-license]').textContent = data.driver.license_number || 'No license listed';
                row.querySelector('[data-driver-plate]').textContent = data.driver.vehicle_plate || 'No vehicle plate';
                const status = row.querySelector('[data-driver-status]');
                status.textContent = data.driver.status;
                status.classList.toggle('status-success', data.driver.status === 'active');
                status.classList.toggle('status-danger', data.driver.status !== 'active');
            }
            notice.textContent = data.message;
            notice.className = 'flash-message success';
            notice.hidden = false;
            closeModal();
        } catch (requestError) {
            showError(requestError.message || 'Could not save this driver.');
        } finally {
            submit.disabled = false;
        }
    });
})();
</script>
@endpush
@endsection
