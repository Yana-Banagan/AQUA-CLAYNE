<dialog class="driver-register-dialog" id="registerDriverModal" aria-labelledby="registerDriverTitle">
    <header class="driver-register-modal-head">
        <span class="driver-register-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="8" r="3.5"></circle>
                <path d="M3 20v-1.5A5.5 5.5 0 0 1 8.5 13h1a5.5 5.5 0 0 1 5.5 5.5V20"></path>
                <path d="M19 8v6M16 11h6"></path>
            </svg>
        </span>
        <div class="driver-register-heading">
            <p class="driver-register-eyebrow">Station Logistics</p>
            <h2 id="registerDriverTitle">Register New Driver</h2>
            <p>Create a driver account and set its initial password.</p>
        </div>
        <button type="button" class="driver-register-close" id="closeRegisterDriverModal" aria-label="Close registration form">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
    </header>

    <form method="POST" action="{{ route('admin.drivers.store') }}" class="driver-register-form">
        @csrf
        <div class="driver-register-grid">
            <div class="field">
                <label for="driver_name">Full Name <span>*</span></label>
                <div class="driver-register-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20v-1.5a7 7 0 0 1 14 0V20"/></svg>
                    <input id="driver_name" type="text" name="name" value="{{ old('name') }}" placeholder="Enter full name" autocomplete="name" required>
                </div>
                @error('name') <small class="field-error">{{ $message }}</small> @enderror
            </div>
            <div class="field">
                <label for="driver_email">Email Address <span>*</span></label>
                <div class="driver-register-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4.5 7 7.5 6 7.5-6"/></svg>
                    <input id="driver_email" type="email" name="email" value="{{ old('email') }}" placeholder="e.g. driver@aquaclayne.ph" autocomplete="email" required>
                </div>
                @error('email') <small class="field-error">{{ $message }}</small> @enderror
            </div>
            <div class="field">
                <label for="driver_phone">Contact Number <span>*</span></label>
                <div class="driver-register-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3.5h3l1.3 4-2 1.5a15 15 0 0 0 5.7 5.7l1.5-2 4 1.3v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 5 5.7 2 2 0 0 1 7 3.5Z"/></svg>
                    <input id="driver_phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="e.g. 0912 345 6789" autocomplete="tel" required>
                </div>
                @error('phone') <small class="field-error">{{ $message }}</small> @enderror
            </div>
            <div class="field">
                <label for="driver_license">Driver License Number</label>
                <div class="driver-register-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.8 16a3 3 0 0 1 5.4 0M13 10h4M13 14h4"/></svg>
                    <input id="driver_license" type="text" name="license_number" value="{{ old('license_number') }}" placeholder="e.g. L00-1234567" autocomplete="off">
                </div>
                @error('license_number') <small class="field-error">{{ $message }}</small> @enderror
            </div>
            <div class="field driver-register-wide">
                <label for="driver_vehicle">Vehicle Plate / Description</label>
                <div class="driver-register-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 16.5h14l-1.2-6a2 2 0 0 0-2-1.5H8.2a2 2 0 0 0-2 1.5L5 16.5Z"/><path d="M5 16.5v2h2M19 16.5v2h-2M7 13h10M8 11h.01M16 11h.01"/></svg>
                    <input id="driver_vehicle" type="text" name="vehicle_plate" value="{{ old('vehicle_plate') }}" placeholder="e.g. ABC 1234 / Hiace" maxlength="20" autocomplete="off">
                </div>
                @error('vehicle_plate') <small class="field-error">{{ $message }}</small> @enderror
            </div>
            <div class="field">
                <label for="driver_password">Password <span>*</span></label>
                <div class="driver-register-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2"/></svg>
                    <input id="driver_password" type="password" name="password" minlength="8" placeholder="Enter password" autocomplete="new-password" required>
                </div>
                @error('password') <small class="field-error">{{ $message }}</small> @enderror
            </div>
            <div class="field">
                <label for="driver_password_confirmation">Confirm Password <span>*</span></label>
                <div class="driver-register-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M9.5 15l1.7 1.7 3.5-3.5"/></svg>
                    <input id="driver_password_confirmation" type="password" name="password_confirmation" minlength="8" placeholder="Confirm password" autocomplete="new-password" required>
                </div>
            </div>
        </div>

        <div class="driver-register-note" role="note">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.25"/><path d="M10 9v4M10 6.5h.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <span>The driver role is automatically assigned by the system.</span>
        </div>

        <footer class="driver-register-actions">
            <button type="button" class="secondary-button" id="cancelRegisterDriverModal">Cancel</button>
            <button type="submit" class="primary-button">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 3v14M3 10h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                Create Driver Account
            </button>
        </footer>
    </form>
</dialog>

@push('styles')
<style>
.driver-register-dialog {
    position: fixed; inset: 0; margin: auto; width: min(800px, calc(100vw - 32px));
    max-height: min(88vh, 720px); padding: 0; overflow: hidden;
    border: 1px solid #BBD8EB; border-radius: 20px; color: #19324C;
    background: #F8FAFC; box-shadow: 0 28px 90px rgba(3, 17, 38, .38);
}
.driver-register-dialog::backdrop { background: rgba(4, 18, 37, .72); backdrop-filter: blur(8px); }
.driver-register-modal-head { display: flex; align-items: center; gap: 15px; padding: 23px 27px 20px; border-bottom: 1px solid #DDE9F2; }
.driver-register-mark { display: grid; flex: 0 0 46px; width: 46px; height: 46px; place-items: center; border: 1px solid rgba(32,199,245,.25); border-radius: 13px; color: #087FCF; background: #E8F6FD; }
.driver-register-mark svg { width: 25px; height: 25px; }
.driver-register-heading { min-width: 0; }
.driver-register-eyebrow { margin: 0 0 3px; color: #087FCF; font-size: 10px; font-weight: 800; letter-spacing: .13em; line-height: 1.3; text-transform: uppercase; }
.driver-register-modal-head h2 { margin: 0; color: #0B2445; font-size: 25px; font-weight: 750; line-height: 1.25; }
.driver-register-heading > p:last-child { margin: 4px 0 0; color: #687F95; font-size: 12.5px; line-height: 1.4; }
.driver-register-close { display: grid; flex: 0 0 36px; width: 36px; height: 36px; place-items: center; margin-left: auto; border: 1px solid #D7E4EE; border-radius: 50%; color: #49647D; background: #F0F6FA; cursor: pointer; transition: background .16s ease, color .16s ease, border-color .16s ease; }
.driver-register-close:hover { border-color: #9ACBE8; color: #0969D7; background: #E4F3FB; }
.driver-register-close svg { width: 18px; height: 18px; }
.driver-register-form { max-height: calc(88vh - 91px); overflow-y: auto; padding: 20px 27px 23px; }
.driver-register-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px 18px; }
.driver-register-grid .field { min-width: 0; margin: 0; }
.driver-register-grid .field label { display: block; margin: 0 0 7px; color: #18334E; font-size: 12px; font-weight: 700; line-height: 1.25; }
.driver-register-grid .field label span { color: #0969D7; }
.driver-register-input-wrap { position: relative; }
.driver-register-input-wrap > svg { position: absolute; z-index: 1; top: 50%; left: 13px; width: 17px; height: 17px; transform: translateY(-50%); fill: none; stroke: #7190A8; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; pointer-events: none; }
.driver-register-grid .field input { display: block; width: 100%; height: 50px; padding: 0 13px 0 40px; border: 1px solid #C8D8E5; border-radius: 11px; outline: none; color: #19324C; background: #FFF; font: inherit; font-size: 13px; transition: border-color .16s ease, box-shadow .16s ease; }
.driver-register-grid .field input::placeholder { color: #8A9DAE; opacity: 1; }
.driver-register-grid .field input:focus { border-color: #1688FF; box-shadow: 0 0 0 3px rgba(22,136,255,.13); }
.driver-register-grid .field .field-error { display: block; margin-top: 5px; color: #B4233A; font-size: 11px; font-weight: 600; }
.driver-register-wide { grid-column: 1 / -1; }
.driver-register-note { display: flex; min-height: 44px; align-items: center; gap: 9px; margin-top: 17px; padding: 8px 12px; border: 1px solid #CFE7F4; border-radius: 10px; color: #416985; background: #EAF6FC; font-size: 12px; line-height: 1.4; }
.driver-register-note svg { flex: 0 0 18px; width: 18px; height: 18px; color: #1688D4; stroke: currentColor; }
.driver-register-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 17px; padding-top: 15px; border-top: 1px solid #DDE9F2; }
.driver-register-actions button { display: inline-flex; min-height: 48px; align-items: center; justify-content: center; gap: 8px; padding: 0 17px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; }
.driver-register-actions .secondary-button { border: 1px solid #C6D6E3; color: #35516B; background: #FFF; }
.driver-register-actions .secondary-button:hover { background: #F1F6FA; }
.driver-register-actions .primary-button { border: 1px solid #0969D7; color: #FFF; background: #0969D7; box-shadow: 0 5px 12px rgba(9,105,215,.18); }
.driver-register-actions .primary-button:hover { border-color: #0758B8; background: #075FC4; }
.driver-register-actions .primary-button svg { width: 16px; height: 16px; }
html[data-theme="dark"] .driver-register-dialog { border-color: #285178; color: #D8E7F4; background: #0B1E34; }
html[data-theme="dark"] .driver-register-modal-head, html[data-theme="dark"] .driver-register-actions { border-color: #203D59; }
html[data-theme="dark"] .driver-register-mark { border-color: rgba(32,199,245,.22); color: #42CFF4; background: #123453; }
html[data-theme="dark"] .driver-register-modal-head h2, html[data-theme="dark"] .driver-register-grid .field label { color: #F2F7FB; }
html[data-theme="dark"] .driver-register-heading > p:last-child { color: #91A4BD; }
html[data-theme="dark"] .driver-register-close { border-color: #2A4763; color: #C7D8E8; background: #142B43; }
html[data-theme="dark"] .driver-register-close:hover { border-color: #2876AB; color: #FFF; background: #1A3B5C; }
html[data-theme="dark"] .driver-register-grid .field input { border-color: #2B4965; color: #F2F7FB; background: #071A2D; }
html[data-theme="dark"] .driver-register-grid .field input::placeholder { color: #8198AD; }
html[data-theme="dark"] .driver-register-input-wrap > svg { stroke: #84A4BC; }
html[data-theme="dark"] .driver-register-note { border-color: #214B6D; color: #B5D5EA; background: #102C45; }
html[data-theme="dark"] .driver-register-note svg { color: #49B9E9; }
html[data-theme="dark"] .driver-register-actions .secondary-button { border-color: #35536E; color: #DAE8F3; background: #10263D; }
html[data-theme="dark"] .driver-register-actions .secondary-button:hover { background: #18344F; }
@media (max-width: 620px) {
    .driver-register-dialog { width: calc(100vw - 20px); max-height: 92dvh; border-radius: 16px; }
    .driver-register-modal-head { gap: 11px; padding: 18px 17px 15px; }
    .driver-register-mark { flex-basis: 40px; width: 40px; height: 40px; }
    .driver-register-modal-head h2 { font-size: 21px; }
    .driver-register-heading > p:last-child { font-size: 11.5px; }
    .driver-register-form { max-height: calc(92dvh - 75px); padding: 16px 17px 18px; }
    .driver-register-grid { grid-template-columns: 1fr; gap: 13px; }
    .driver-register-wide { grid-column: auto; }
    .driver-register-actions button { flex: 1; padding: 0 10px; }
}
@media (max-height: 680px) and (min-width: 621px) {
    .driver-register-dialog { max-height: 94dvh; }
    .driver-register-modal-head { padding-top: 16px; padding-bottom: 14px; }
    .driver-register-form { max-height: calc(94dvh - 76px); padding-top: 14px; padding-bottom: 16px; }
    .driver-register-grid { gap: 11px 16px; }
    .driver-register-grid .field input { height: 46px; }
}
</style>
@endpush

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('registerDriverModal');
    if (!modal) return;
    const closeModal = () => modal.close();
    document.getElementById('openRegisterDriverModal')?.addEventListener('click', () => modal.showModal());
    document.getElementById('closeRegisterDriverModal')?.addEventListener('click', closeModal);
    document.getElementById('cancelRegisterDriverModal')?.addEventListener('click', closeModal);
    modal.addEventListener('click', event => {
        if (event.target === modal) closeModal();
    });
    @if($errors->any()) modal.showModal(); @endif
})();
</script>
@endpush
