@extends('admin.layouts.app')

@section('title', 'Edit User · ' . $user->name)

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">
            <a href="{{ route('admin.users.index', request()->query()) }}" style="color: var(--primary); text-decoration: none;">← Back to Users</a>
            &nbsp;·&nbsp; {{ ucfirst($user->role) }} Account
        </p>
        <h1>Edit {{ ucfirst($user->role) }} Account</h1>
        <p>Update account details and status. Password and role are not changed here.</p>
    </div>
</div>

<div class="panel" style="max-width: 800px;">
    <form method="POST" action="{{ route('admin.users.update', array_merge(['user' => $user], request()->query())) }}" data-disable-on-submit>
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="field">
                <label for="name">Full Name *</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="150" required>
                @error('name') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>

            <div class="field">
                <label for="email">Email Address *</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="150" required>
                @error('email') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>

            <div class="field">
                <label for="phone">Phone Number</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30">
                @error('phone') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>

            <div class="field">
                <label for="status">Account Status *</label>
                <select id="status" name="status" required>
                    <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
                </select>
                @error('status') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; border-top: 1px solid var(--border-light); padding-top: 18px;">
            <a class="secondary-button" href="{{ route('admin.users.index', request()->query()) }}">Cancel</a>
            <button class="primary-button" type="submit" data-loading-text="Saving...">Save Changes</button>
        </div>
    </form>
</div>
@endsection
