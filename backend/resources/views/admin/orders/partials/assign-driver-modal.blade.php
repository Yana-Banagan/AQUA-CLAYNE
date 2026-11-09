<!-- Assign driver dispatch modal -->
<div class="modal-backdrop assign-driver-backdrop" id="assignDriverModal" aria-hidden="true" style="display: none;">
    <section class="modal assign-driver-modal" role="dialog" aria-modal="true" aria-labelledby="assignModalTitle">
        <header class="assign-modal-header">
            <div class="assign-modal-heading">
                <span class="assign-title-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v11H3zM14 11h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/><path d="M5 10h6m-6 3h6"/></svg>
                </span>
                <div>
                    <h3 id="assignModalTitle">Assign Driver</h3>
                    <p>Aqua Clayne purified water delivery and container pickup</p>
                </div>
            </div>
            <button type="button" class="assign-close-btn" id="closeAssignModalBtn" aria-label="Close modal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m18 6-12 12M6 6l12 12"/></svg>
            </button>
        </header>

        <form id="assignDriverForm" class="assign-driver-form" method="POST" action="{{ route('admin.deliveries.assign.store') }}">
            @csrf
            <input type="hidden" name="order_id" id="modalOrderId" value="">

            <div class="assign-modal-content">
                <div class="modal-error-banner assign-error-banner" id="modalErrorMessage" style="display: none;" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                    <span id="modalErrorText">Error message goes here</span>
                </div>

                <section class="assign-panel" aria-labelledby="assignOrderPanelTitle">
                    <div class="assign-panel-heading">
                        <span class="assign-panel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>
                        </span>
                        <h4 id="assignOrderPanelTitle">Order Awaiting Assignment</h4>
                    </div>
                    <label class="assign-sr-only" for="assignOrderSelect">Select a confirmed order</label>
                    <select id="assignOrderSelect" class="driver-select assign-driver-select assign-order-select" {{ isset($assignableOrders) && $assignableOrders->isNotEmpty() ? '' : 'disabled' }}>
                        <option value="">— Select a confirmed order —</option>
                        @if(isset($assignableOrders))
                            @foreach($assignableOrders as $assignableOrder)
                                @php
                                    $assignOrderNumber = $assignableOrder->order_number ?: ('ORD-' . strtoupper(substr($assignableOrder->id, 0, 8)));
                                    $assignCustomerName = $assignableOrder->customer?->user?->name ?? 'Customer';
                                    $assignAddress = $assignableOrder->address?->address_line ?? 'Station Service Area';
                                    $assignGallons = $assignableOrder->items->sum('quantity') ?: ($assignableOrder->expected_empty_gallons ?: 1);
                                    $assignService = $assignableOrder->items->first()?->product?->name ?? 'Purified Water Refill';
                                    $rfidTag = $assignableOrder->rfidTransactions->first()?->rfidTag;
                                    $rfidUid = $rfidTag?->tag_uid;
                                    $rfidGallonStatus = $rfidTag?->gallon?->status;
                                @endphp
                                <option value="{{ $assignableOrder->id }}"
                                        data-order-num="{{ $assignOrderNumber }}"
                                        data-customer="{{ $assignCustomerName }}"
                                        data-address="{{ $assignAddress }}"
                                        data-gallons="{{ $assignGallons }}"
                                        data-service="{{ $assignService }}"
                                        data-rfid-uid="{{ $rfidUid }}"
                                        data-gallon-status="{{ $rfidGallonStatus }}">
                                    {{ $assignOrderNumber }} · {{ $assignCustomerName }} · {{ $assignGallons }} gallons
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <p class="assign-order-empty" id="assignOrderEmptyMessage" role="status" {{ isset($assignableOrders) && $assignableOrders->isNotEmpty() ? 'hidden' : '' }}>ⓘ No confirmed orders currently awaiting assignment.</p>
                    <div class="assign-order-details">
                        <div class="assign-detail-cell">
                            <span class="assign-detail-label">Order number</span>
                            <strong id="modalOrderNumber">ORD-00000000</strong>
                        </div>
                        <div class="assign-detail-cell">
                            <span class="assign-detail-label">Customer</span>
                            <strong id="modalCustomerName">Customer Name</strong>
                        </div>
                        <div class="assign-detail-cell">
                            <span class="assign-detail-label">Gallon quantity</span>
                            <strong class="assign-detail-accent" id="modalGallons">0 gallons to refill</strong>
                        </div>
                        <div class="assign-detail-cell">
                            <span class="assign-detail-label">Service</span>
                            <strong id="modalService">5-Gallon Purified Water Refill</strong>
                        </div>
                        <div class="assign-detail-cell assign-address-cell">
                            <span class="assign-detail-label">Pickup &amp; delivery address</span>
                            <strong class="address-text" id="modalAddress">Address location...</strong>
                        </div>
                    </div>
                    <div class="assign-rfid-card" id="assignRfidCard" hidden>
                        <span class="assign-rfid-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7a7 7 0 0 0 0 10M10 9.5a3.5 3.5 0 0 0 0 5M4 4a11 11 0 0 0 0 16M13 12h.01"/><path d="M16 5v14h5V5z"/></svg>
                        </span>
                        <div class="assign-rfid-copy">
                            <strong>RFID Gallon</strong>
                            <span>UID: <b id="assignRfidUid">—</b></span>
                            <span>Status: <b id="assignRfidStatus">—</b></span>
                        </div>
                        <span class="assign-rfid-customer">Customer: <b id="assignRfidCustomer">—</b></span>
                    </div>
                </section>

                <section class="assign-panel assign-driver-panel" aria-labelledby="assignDriverPanelTitle">
                    <div class="assign-panel-heading assign-driver-heading">
                        <span class="assign-panel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
                        </span>
                        <h4 id="assignDriverPanelTitle">Select Active Driver</h4>
                        <span class="assign-required-badge">Required</span>
                    </div>
                    <label class="assign-sr-only" for="driverSelect">Choose an available driver</label>
                    <select name="driver_id" id="driverSelect" class="driver-select assign-driver-select" required {{ isset($assignableDrivers) && $assignableDrivers->isNotEmpty() ? '' : 'disabled' }}>
                        <option value="" disabled selected>— Choose an available driver —</option>
                        @if(isset($assignableDrivers) && $assignableDrivers->isNotEmpty())
                            @foreach($assignableDrivers as $driver)
                                <option value="{{ $driver['id'] }}"
                                        data-name="{{ $driver['name'] }}"
                                        data-avatar="{{ $driver['avatar_url'] ?? '' }}"
                                        data-phone="{{ $driver['phone'] ?? 'N/A' }}"
                                        data-plate="{{ $driver['vehicle_plate'] }}"
                                        data-deliveries="{{ $driver['active_deliveries'] }}"
                                        data-status="{{ $driver['status_text'] }}"
                                        data-available="{{ $driver['is_available'] ? 'true' : 'false' }}">
                                    {{ $driver['name'] }} · {{ $driver['status_text'] }} ({{ $driver['active_deliveries'] }} active deliveries) · {{ $driver['vehicle_plate'] }}
                                </option>
                            @endforeach
                        @else
                            <option value="" disabled>No active drivers currently registered in station</option>
                        @endif
                    </select>
                    <p class="assign-helper-text">Only active and available Aqua Clayne station drivers are eligible for dispatch.</p>

                    <div class="driver-info-card assign-driver-info" id="driverInfoCard" style="display: none;">
                        <div class="driver-card-head">
                            <div class="driver-card-avatar" id="driverCardAvatar">DR</div>
                            <div class="driver-card-meta">
                                <strong class="driver-card-name" id="driverCardName">Driver Name</strong>
                                <span class="driver-card-phone" id="driverCardPhone">+63 900 000 0000</span>
                            </div>
                            <span class="driver-workload-badge" id="driverWorkloadBadge">Available</span>
                        </div>
                        <div class="driver-card-details">
                            <div class="detail-pill"><span class="pill-label">Vehicle</span><strong id="driverCardVehicle">Tricycle</strong></div>
                            <div class="detail-pill"><span class="pill-label">Current workload</span><strong id="driverCardDeliveries">0 deliveries</strong></div>
                        </div>
                    </div>
                </section>

                <ol class="assign-workflow" aria-label="Aqua Clayne delivery workflow">
                    <li><span>1</span><b>Confirmed Order</b></li>
                    <li class="is-current"><span>2</span><b>Driver Assigned</b></li>
                    <li><span>3</span><b>Gallon Pickup</b></li>
                    <li><span>4</span><b>Out for Delivery</b></li>
                    <li><span>5</span><b>Delivered</b></li>
                </ol>

                <div class="confirm-box assign-confirm-box" id="confirmAssignBox" style="display: none;">
                    <div class="confirm-box-content">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                        <span>Ready to assign order <strong id="confirmOrderRef">ORD-0000</strong> to <strong id="confirmDriverTarget">Driver</strong>? This will notify the driver and transition status to <strong>ASSIGNED</strong>.</span>
                    </div>
                </div>
            </div>

            <footer class="assign-modal-footer">
                <button type="button" class="btn-secondary assign-cancel-btn" id="cancelAssignBtn">Cancel</button>
                <button type="submit" class="btn-primary assign-submit-btn" id="submitAssignBtn" disabled>
                    <span class="spinner" id="assignSpinner" style="display: none;" aria-hidden="true"></span>
                    <svg class="assign-submit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/></svg>
                    <span id="assignBtnText">Assign Driver</span>
                </button>
            </footer>
        </form>
    </section>
</div>
