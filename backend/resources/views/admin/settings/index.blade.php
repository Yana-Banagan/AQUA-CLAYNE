@extends('admin.layouts.app')

@section('title', 'System Settings')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Administration &amp; Rules</p>
        <h1>System &amp; Station Settings</h1>
        <p>Manage station branding, operating parameters, business rules, and security policies.</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
    @csrf
    
    <div class="detail-grid">
        <div>
            <!-- Group 1: Station Information (Section 34) -->
            <article class="detail-section">
                <h3>Station Profile &amp; Branding</h3>

                <div class="admin-profile-photo-setting">
                    <img src="{{ $adminAvatarUrl ?: asset('images/aqua_clayne_logo.png') }}" alt="Current admin profile picture" class="admin-profile-photo-preview" id="adminProfilePhotoPreview">
                    <div class="admin-profile-photo-controls">
                        <label for="profile_photo">Administrator profile picture</label>
                        <input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" data-max-size="2048">
                        <small>JPG, PNG, or WebP. Maximum file size: 2 MB.</small>
                        <small id="profilePhotoClientError" class="profile-photo-error" role="alert" hidden></small>
                        @error('profile_photo') <small class="field-error">{{ $message }}</small> @enderror
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="field">
                        <label for="station_name">Station Name *</label>
                        <input id="station_name" type="text" name="station_name" value="{{ old('station_name', $settings['station_name']) }}" required>
                    </div>
                    
                    <div class="field">
                        <label for="tagline">Official Tagline</label>
                        <input id="tagline" type="text" name="tagline" value="{{ old('tagline', $settings['tagline']) }}" readonly style="background: var(--surface-soft);">
                    </div>
                    
                    <div class="field">
                        <label for="contact_phone">Contact Telephone / Mobile *</label>
                        <input id="contact_phone" type="text" name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone']) }}" required>
                    </div>
                    
                    <div class="field">
                        <label for="contact_email">Administrative Contact Email *</label>
                        <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $settings['contact_email']) }}" required>
                    </div>
                    
                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="station_address">Station Physical Address *</label>
                        <textarea id="station_address" name="station_address" rows="2" required>{{ old('station_address', $settings['station_address']) }}</textarea>
                    </div>
                    <div class="field" style="grid-column: 1 / -1;">
                        <label>Official delivery route origin coordinates</label>
                        <input type="text" value="{{ $settings['station_latitude'] }}, {{ $settings['station_longitude'] }}" readonly>
                        <small>Configured with STATION_LATITUDE and STATION_LONGITUDE on the server. This fixed Aqua Clayne station pin is used for every delivery route.</small>
                    </div>

                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="operating_hours">Operating Hours *</label>
                        <input id="operating_hours" type="text" name="operating_hours" value="{{ old('operating_hours', $settings['operating_hours']) }}" required>
                    </div>
                </div>
            </article>

            <!-- Group 2: Operational Refill Rules (Section 34) -->
            <article class="detail-section">
                <h3>Operational Refill &amp; Queue Parameters</h3>
                
                <div class="field">
                    <label>Active Business Workflow Model</label>
                    <input type="text" value="{{ $settings['business_model'] }}" readonly style="background: var(--surface-soft); font-weight: 700; color: var(--navy);">
                    <small>Enforces that customer-owned empty containers are received at station before queue entry.</small>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label>Daily Station Refill Capacity (Gallons)</label>
                        <input type="number" name="daily_refill_capacity" value="{{ $settings['daily_refill_capacity'] }}">
                    </div>
                    
                    <div class="field">
                        <label>Queue Dispatch Policy</label>
                        <input type="text" value="FIFO (First-In, First-Out)" readonly style="background: var(--surface-soft);">
                    </div>
                </div>
            </article>
        </div>

        <div>
            <!-- Group 3: Realtime & Notification Settings -->
            <article class="detail-section">
                <h3>Notification &amp; Signal Channels</h3>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" checked disabled>
                        <span>Supabase Realtime Signals</span>
                    </label>
                    <small style="margin-left: 24px; color: var(--text-secondary); display: block;">
                        Broadcasting non-sensitive events over <code>realtime_events</code> signal table.
                    </small>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" checked disabled>
                        <span>Firebase Cloud Messaging (FCM)</span>
                    </label>
                    <small style="margin-left: 24px; color: var(--text-secondary); display: block;">
                        Push notifications dispatched to mobile driver and customer devices.
                    </small>
                </div>
            </article>

            <!-- Group 4: Security & Environment Safeguards -->
            <article class="detail-section">
                <h3>System Security &amp; Safeguards</h3>
                
                <div class="detail-row">
                    <span class="label">Authentication Engine</span>
                    <span class="value">Laravel Session Auth</span>
                </div>
                <div class="detail-row">
                    <span class="label">Mobile Token API</span>
                    <span class="value">Laravel Sanctum</span>
                </div>
                <div class="detail-row">
                    <span class="label">Database Encryption</span>
                    <span class="value">PostgreSQL (Supabase)</span>
                </div>
                <div class="detail-row">
                    <span class="label">CSRF Protection</span>
                    <span class="value" style="color: var(--success);">✓ Active &amp; Enforced</span>
                </div>

                <div style="margin-top: 14px; background: var(--surface-soft); padding: 12px; border-radius: var(--radius-sm); font-size: 12px; color: var(--text-secondary);">
                    Server secrets (service role keys, DB passwords, FCM service account) remain isolated on the backend.
                </div>
            </article>

            <button type="submit" class="primary-button" style="width: 100%; height: 46px; font-size: 15px;">
                Save Station Settings
            </button>
        </div>
    </div>
</form>
@push('scripts')
<script>
    (function () {
        var input = document.getElementById('profile_photo');
        var preview = document.getElementById('adminProfilePhotoPreview');
        var error = document.getElementById('profilePhotoClientError');
        if (!input || !preview || !error) return;

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            error.hidden = true;
            error.textContent = '';
            if (!file) return;

            if (file.size > 2 * 1024 * 1024) {
                input.value = '';
                error.textContent = 'Choose an image that is 2 MB or smaller.';
                error.hidden = false;
                return;
            }

            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                input.value = '';
                error.textContent = 'Choose a JPG, PNG, or WebP image.';
                error.hidden = false;
                return;
            }

            preview.src = URL.createObjectURL(file);
        });
    }());
</script>
@endpush
@endsection
