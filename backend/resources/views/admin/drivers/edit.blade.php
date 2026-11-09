@extends('admin.layouts.app')

@section('title', 'Edit Driver · ' . ($driver->user?->name ?? 'Driver'))

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">
            <a href="{{ route('admin.drivers.index') }}" style="color: var(--primary); text-decoration: none;">← Back to Drivers</a>
            &nbsp;·&nbsp; Driver Profile
        </p>
        <div style="display: flex; align-items: center; gap: 12px;">
            <span class="avatar" style="width: 44px; height: 44px; font-size: 15px; flex: 0 0 44px;">
                @include('admin.partials.avatar-content', ['user' => $driver->user, 'fallbackName' => 'Driver'])
            </span>
            <h1>Edit Driver: {{ $driver->user?->name ?? 'Driver' }}</h1>
        </div>
        <p>Update vehicle details and license information.</p>
    </div>
</div>

<div class="panel" style="max-width: 800px;">
    <form method="POST" action="{{ route('admin.drivers.update', $driver) }}">
        @csrf
        @method('PUT')
        
        <div class="form-grid">
            <div class="field">
                <label>Driver Full Name</label>
                <input value="{{ $driver->user?->name }}" disabled style="background: var(--surface-soft); color: var(--text-muted);">
            </div>
            
            <div class="field">
                <label>Email Address</label>
                <input value="{{ $driver->user?->email }}" disabled style="background: var(--surface-soft); color: var(--text-muted);">
            </div>
            
            <div class="field">
                <label for="license_number">License / ID Reference *</label>
                <input id="license_number" type="text" name="license_number" value="{{ old('license_number', $driver->license_number) }}" required>
            </div>
            
            <div class="field">
                <label for="vehicle_plate">Vehicle Plate</label>
                <input id="vehicle_plate" type="text" name="vehicle_plate" value="{{ old('vehicle_plate', $driver->vehicle_plate) }}">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; border-top: 1px solid var(--border-light); padding-top: 18px;">
            <a class="secondary-button" href="{{ route('admin.drivers.index') }}">Cancel</a>
            <button class="primary-button" type="submit">Save Changes</button>
        </div>
    </form>
</div>
@endsection
