@extends('admin.layouts.app')

@section('title', 'Edit Driver · ' . ($driver->user?->name ?? 'Driver'))

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">
            <a href="{{ route('admin.drivers.index', request()->query()) }}" style="color: var(--primary); text-decoration: none;">← Back to Drivers</a>
            &nbsp;·&nbsp; Driver Profile
        </p>
        <div style="display: flex; align-items: center; gap: 12px;">
            <span class="avatar" style="width: 44px; height: 44px; font-size: 15px; flex: 0 0 44px;">
                @include('admin.partials.avatar-content', ['user' => $driver->user, 'fallbackName' => 'Driver'])
            </span>
            <h1>Edit Driver: {{ $driver->user?->name ?? 'Driver' }}</h1>
        </div>
        <p>Update driver contact, account status, vehicle, and license information.</p>
    </div>
</div>

<div class="panel" style="max-width: 800px;">
    <form method="POST" action="{{ route('admin.drivers.update', array_merge(['driver' => $driver], request()->query())) }}" data-disable-on-submit>
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="field">
                <label for="name">Driver Full Name *</label>
                <input id="name" name="name" value="{{ old('name', $driver->user?->name) }}" maxlength="150" required>
                @error('name') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>

            <div class="field">
                <label for="email">Email Address *</label>
                <input id="email" type="email" name="email" value="{{ old('email', $driver->user?->email) }}" maxlength="150" required>
                @error('email') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>

            <div class="field">
                <label for="phone">Phone Number</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $driver->user?->phone) }}" maxlength="30">
                @error('phone') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>

            <div class="field">
                <label for="status">Account Status *</label>
                <select id="status" name="status" required>
                    <option value="active" @selected(old('status', $driver->user?->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $driver->user?->status) === 'inactive')>Inactive</option>
                </select>
                @error('status') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>

            <div class="field">
                <label for="license_number">License / ID Reference</label>
                <input id="license_number" type="text" name="license_number" value="{{ old('license_number', $driver->license_number) }}" maxlength="50">
                @error('license_number') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>

            <div class="field">
                <label for="vehicle_plate">Vehicle Plate</label>
                <input id="vehicle_plate" type="text" name="vehicle_plate" value="{{ old('vehicle_plate', $driver->vehicle_plate) }}" maxlength="20">
                @error('vehicle_plate') <small class="field-error" role="alert">{{ $message }}</small> @enderror
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; border-top: 1px solid var(--border-light); padding-top: 18px;">
            <a class="secondary-button" href="{{ route('admin.drivers.index', request()->query()) }}">Cancel</a>
            <button class="primary-button" type="submit" data-loading-text="Saving...">Save Changes</button>
        </div>
    </form>
</div>
@endsection
