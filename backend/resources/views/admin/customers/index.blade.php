@extends('admin.layouts.app')

@section('title', 'Customers Directory')

@section('content')
<div id="customerEditNotice" class="flash-message" role="status" aria-live="polite" hidden></div>
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
                    <tr data-customer-row="{{ $cust->id }}">
                        <td style="display: flex; align-items: center; gap: 10px;">
                            <span class="avatar" style="width: 32px; height: 32px; font-size: 12px;">
                                @include('admin.partials.avatar-content', ['user' => $cust->user, 'fallbackName' => 'Customer'])
                            </span>
                            <div>
                                <strong data-customer-name style="font-size: 13px; color: var(--text-primary);">{{ $cust->user?->name ?? 'Customer' }}</strong>
                                <small data-customer-email style="color: var(--text-secondary); display: block;">{{ $cust->user?->email ?? 'No email' }}</small>
                            </div>
                        </td>
                        <td data-customer-phone>{{ $cust->user?->phone ?? 'N/A' }}</td>
                        <td style="max-width: 250px;">
                            <span data-customer-address style="font-size: 12.5px; color: var(--text-primary);">
                                {{ $cust->addresses->first()?->address_line ? collect([$cust->addresses->first()->address_line, $cust->addresses->first()->barangay])->filter()->implode(', ') : 'None listed' }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ $cust->orders_count ?? $cust->orders->count() }}</strong>
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
                            <span data-customer-status class="status-badge {{ $cust->user?->status === 'active' ? 'status-success' : 'status-danger' }}">
                                {{ $cust->user?->status ?? 'active' }}
                            </span>
                        </td>
                        <td>
                            <small style="color: var(--text-secondary);">{{ $cust->created_at?->format('M d, Y') }}</small>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.customers.show', array_merge(['customer' => $cust], request()->query())) }}" class="table-action">
                                    View Profile
                                </a>
                                <button type="button" class="table-action" data-customer-edit="{{ route('admin.customers.edit', $cust) }}">
                                    Edit
                                </button>
                            </div>
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

<div class="modal-backdrop" id="customerEditModal" aria-hidden="true">
    <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="customerEditTitle" style="padding: 24px;">
        <div class="panel-head">
            <div>
                <p class="eyebrow">Customer Profile</p>
                <h2 id="customerEditTitle">Edit Customer</h2>
            </div>
            <button type="button" class="icon-button" data-customer-modal-close aria-label="Close">&times;</button>
        </div>
        <form id="customerEditForm" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="address_id">
            <div id="customerEditError" class="flash-message danger" role="alert" hidden></div>
            <div class="form-grid">
                <div class="field"><label for="customerEditName">Full Name *</label><input id="customerEditName" name="name" maxlength="150" required></div>
                <div class="field"><label for="customerEditEmail">Email Address *</label><input id="customerEditEmail" name="email" type="email" maxlength="150" required></div>
                <div class="field"><label for="customerEditPhone">Contact Number</label><input id="customerEditPhone" name="phone" type="tel" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11"><small>Use the registered mobile format: 09xxxxxxxxx.</small></div>
                <div class="field"><label for="customerEditBirthdate">Birthdate</label><input id="customerEditBirthdate" name="birthdate" type="date" max="{{ now()->toDateString() }}"></div>
                <div class="field"><label for="customerEditAddress">Address</label><input id="customerEditAddress" name="address_line" maxlength="500"></div>
                <div class="field"><label for="customerEditBarangay">Barangay</label><input id="customerEditBarangay" name="barangay" maxlength="100"></div>
                <div class="field"><label for="customerEditCity">City / Municipality</label><input id="customerEditCity" name="city" maxlength="100"></div>
                <div class="field"><label for="customerEditProvince">Province</label><input id="customerEditProvince" name="province" maxlength="100"></div>
                <div class="field"><label for="customerEditZip">ZIP Code</label><input id="customerEditZip" name="zip_code" maxlength="20"></div>
                <div class="field"><label for="customerEditStatus">Account Status *</label><select id="customerEditStatus" name="status" required><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            </div>
            <div class="form-actions">
                <button type="button" class="secondary-button" data-customer-modal-close>Cancel</button>
                <button type="submit" class="primary-button">Save Changes</button>
            </div>
        </form>
    </section>
</div>

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('customerEditModal');
    const form = document.getElementById('customerEditForm');
    const error = document.getElementById('customerEditError');
    const notice = document.getElementById('customerEditNotice');
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

    document.querySelectorAll('[data-customer-edit]').forEach(button => button.addEventListener('click', async () => {
        error.hidden = true;
        form.reset();
        form.action = button.dataset.customerEdit;
        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        fields.name.focus();
        try {
            const response = await fetch(form.action, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Could not load this customer.');
            ['name', 'email', 'phone', 'status', 'birthdate'].forEach(key => fields[key].value = data[key] || '');
            const address = data.address || {};
            fields.address_id.value = address.id || '';
            ['address_line', 'barangay', 'city', 'province', 'zip_code'].forEach(key => fields[key].value = address[key] || '');
        } catch (requestError) {
            showError(requestError.message || 'Could not load this customer.');
        }
    }));

    modal.addEventListener('click', event => {
        if (event.target === modal || event.target.closest('[data-customer-modal-close]')) closeModal();
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
                throw new Error(validationMessage || data.message || 'Could not save this customer.');
            }
            const row = document.querySelector(`[data-customer-row="${CSS.escape(data.customer.id)}"]`);
            if (row) {
                row.querySelector('[data-customer-name]').textContent = data.customer.name || 'Customer';
                row.querySelector('[data-customer-email]').textContent = data.customer.email || 'No email';
                row.querySelector('[data-customer-phone]').textContent = data.customer.phone || 'N/A';
                row.querySelector('[data-customer-address]').textContent = data.customer.address || 'None listed';
                const status = row.querySelector('[data-customer-status]');
                status.textContent = data.customer.status;
                status.classList.toggle('status-success', data.customer.status === 'active');
                status.classList.toggle('status-danger', data.customer.status !== 'active');
            }
            notice.textContent = data.message;
            notice.className = 'flash-message success';
            notice.hidden = false;
            closeModal();
        } catch (requestError) {
            showError(requestError.message || 'Could not save this customer.');
        } finally {
            submit.disabled = false;
        }
    });
})();
</script>
@endpush
@endsection
