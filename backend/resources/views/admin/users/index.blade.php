@extends('admin.layouts.app')

@section('title', 'Users & Roles')
@section('header-title', 'Users &amp; Roles')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Access Control &amp; Staff</p>
        <h1>Users &amp; Roles</h1>
        <p>Manage authorized station personnel, administrative privileges, and staff user access.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="primary-button" onclick="openUsersModal()">
            <span>＋</span>
            <span>Create Staff</span>
        </button>
    </div>
</div>

<article class="panel">
    <div class="panel-head">
        <h2>Authorized System Users</h2>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $usr)
                    <tr>
                        <td style="display: flex; align-items: center; gap: 10px;">
                            <span class="avatar">@include('admin.partials.avatar-content', ['user' => $usr, 'fallbackName' => 'User'])</span>
                            <strong>{{ $usr->name }}</strong>
                        </td>
                        <td>{{ $usr->email }}</td>
                        <td>
                            <span class="status-badge {{ $usr->role === 'admin' ? 'status-purple' : 'status-info' }}">
                                {{ ucfirst($usr->role) }}
                            </span>
                        </td>
                        <td>
                            <span class="status-badge {{ $usr->status === 'active' ? 'status-success' : 'status-danger' }}">
                                {{ $usr->status }}
                            </span>
                        </td>
                        <td><small style="color: var(--text-secondary);">{{ $usr->created_at?->format('M d, Y') }}</small></td>
                        <td>
                            @if($usr->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.toggle-status', $usr) }}" data-confirm="{{ $usr->status === 'active' ? 'Deactivate '.$usr->name.'?' : 'Reactivate '.$usr->name.'?' }}">
                                    @csrf
                                    <button type="submit" class="table-action {{ $usr->status === 'active' ? 'danger' : 'success' }}">
                                        {{ $usr->status === 'active' ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </form>
                            @else
                                <span style="font-size: 11px; color: var(--text-muted);">Current User</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"><div class="empty-state"><p>No users found.</p></div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div style="padding: 20px 0;">{{ $users->links() }}</div>
    @endif
</article>

@push('modals')
<!-- Modal: Create Staff -->
<div class="modal-backdrop" id="add-user-modal">
    <div class="modal-card">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="modal-head">
                <h3>Create Staff Account</h3>
                <button type="button" class="modal-close" onclick="closeUsersModal()" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field">
                        <label>Full Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Maria Santos">
                    </div>
                    <div class="field">
                        <label>Email Address *</label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="staff@aquaclayne.ph">
                    </div>
                    <div class="field">
                        <label>Role Privilege *</label>
                        <input type="hidden" name="role" value="staff">
                        <input type="text" value="Station Staff" readonly style="width: 100%; background: var(--surface-soft);">
                    </div>
                    <div class="field">
                        <label>Phone Contact</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" placeholder="09171234567">
                    </div>
                </div>

                <div class="field" style="margin-top: 14px;">
                    <label>Initial Password *</label>
                    <input type="password" name="password" minlength="8" required placeholder="Minimum 8 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" onclick="closeUsersModal()" data-modal-close>Cancel</button>
                <button type="submit" class="primary-button">Create Staff</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('add-user-modal');

        window.openUsersModal = function () {
            if (!modal) modal = document.getElementById('add-user-modal');
            if (!modal) return;
            modal.classList.add('is-active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            modal.querySelector('input[name="name"]')?.focus();
        };

        window.closeUsersModal = function () {
            if (!modal) modal = document.getElementById('add-user-modal');
            if (!modal) return;
            modal.classList.remove('is-active');
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.addEventListener('click', function (event) {
                if (event.target === modal || event.target.closest('[data-modal-close]')) window.closeUsersModal();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('is-active')) window.closeUsersModal();
            });
        }

        @if($errors->any())
            window.openUsersModal();
        @endif
    }());
</script>
@endpush
@endsection
