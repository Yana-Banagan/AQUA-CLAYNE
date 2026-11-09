@extends('admin.layouts.app')

@section('title', 'Station Maintenance')

@section('content')
@if(session('success'))
    <div class="flash-message success" role="status">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="flash-message danger" role="alert">{{ session('error') }}</div>
@endif

<div class="page-header">
    <div>
        <p class="eyebrow">Equipment &amp; Facilities</p>
        <h1>Station Maintenance</h1>
        <p>Record equipment repairs, repair costs, current condition, and the full service history.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="secondary-button" onclick="openEquipmentModal()">Add Equipment</button>
    </div>
</div>

<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));">
    <article class="stat-card">
        <span class="stat-label">Registered Equipment</span>
        <strong class="stat-value">{{ $stats['equipment'] }}</strong>
        <small class="stat-meta">Items tracked by the station</small>
    </article>
    <article class="stat-card">
        <span class="stat-label">Under Repair</span>
        <strong class="stat-value">{{ $stats['underRepair'] }}</strong>
        <small class="stat-meta">Most recent record says work is ongoing</small>
    </article>
    <article class="stat-card">
        <span class="stat-label">Out of Service</span>
        <strong class="stat-value">{{ $stats['outOfService'] }}</strong>
        <small class="stat-meta">Equipment currently unavailable</small>
    </article>
    <article class="stat-card">
        <span class="stat-label">Recorded Repair Cost</span>
        <strong class="stat-value">₱{{ number_format((float) $stats['totalCost'], 2) }}</strong>
        <small class="stat-meta">Total across all maintenance records</small>
    </article>
</section>

<article class="panel">
    <div class="panel-head">
        <div>
            <h2>Equipment Status</h2>
            <small style="color: var(--text-secondary); font-size: 12px;">Status and cost below come from each equipment’s latest repair record.</small>
        </div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Equipment</th>
                    <th>Current Status</th>
                    <th>Last Repair</th>
                    <th>Last Repair Cost</th>
                    <th>Recorded By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($equipmentData as $item)
                    <tr>
                        <td><strong style="color: var(--text-primary);">{{ $item['name'] }}</strong></td>
                        <td>
                            @if($item['status'] === 'operational')
                                <span class="status-badge status-success">Operational</span>
                            @elseif($item['status'] === 'under_repair')
                                <span class="status-badge status-warning">Under Repair</span>
                            @elseif($item['status'] === 'out_of_service')
                                <span class="status-badge status-danger">Out of Service</span>
                            @else
                                <span class="status-badge status-info">No Record Yet</span>
                            @endif
                        </td>
                        <td>{{ $item['last_performed']?->format('M d, Y g:i A') ?? 'No repair recorded' }}</td>
                        <td>{{ $item['last_record'] ? '₱'.number_format((float) $item['last_record']->cost, 2) : '—' }}</td>
                        <td>{{ $item['last_record']?->performedBy?->name ?? '—' }}</td>
                        <td>
                            <button type="button" class="table-action" onclick="openMaintenanceModal(@js($item['id']), @js($item['name']))">Log Repair</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state"><strong>No equipment added</strong><p>Add an equipment item to start its maintenance history.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</article>

<article class="panel" style="margin-top: 24px;">
    <div class="panel-head">
        <div>
            <h2>Past Maintenance Records</h2>
            <small style="color: var(--text-secondary); font-size: 12px;">A dated record of repair notes, cost, condition, and who logged the work.</small>
        </div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Equipment</th>
                    <th>Work / Notes</th>
                    <th>Repair Cost</th>
                    <th>Status After Work</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $record)
                    <tr>
                        <td>{{ $record->performed_at?->format('M d, Y g:i A') ?? '—' }}</td>
                        <td><strong>{{ $record->maintenance?->equipment_name ?? 'Equipment removed' }}</strong></td>
                        <td style="max-width: 360px; white-space: normal;">{{ $record->notes ?: 'No notes entered' }}</td>
                        <td>₱{{ number_format((float) $record->cost, 2) }}</td>
                        <td>
                            @if($record->equipment_status === 'operational')
                                <span class="status-badge status-success">Operational</span>
                            @elseif($record->equipment_status === 'under_repair')
                                <span class="status-badge status-warning">Under Repair</span>
                            @elseif($record->equipment_status === 'out_of_service')
                                <span class="status-badge status-danger">Out of Service</span>
                            @else
                                <span class="status-badge status-info">Not recorded</span>
                            @endif
                        </td>
                        <td>{{ $record->performedBy?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state"><strong>No past records yet</strong><p>Each repair you log will appear here for future reference.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($history->hasPages())
        <div style="padding: 20px 0;">{{ $history->links() }}</div>
    @endif
</article>

@push('modals')
<div class="modal-backdrop" id="add-equipment-modal" aria-hidden="true">
    <div class="modal-card">
        <form method="POST" action="{{ route('admin.maintenance.equipment.store') }}">
            @csrf
            <div class="modal-head"><h3>Add Equipment</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <div class="field">
                    <label for="equipment-name">Equipment Name *</label>
                    <input id="equipment-name" type="text" name="equipment_name" value="{{ old('equipment_name') }}" maxlength="100" required placeholder="e.g. Reverse osmosis pump">
                    @error('equipment_name') <small class="field-error">{{ $message }}</small> @enderror
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="secondary-button" data-modal-close>Cancel</button><button type="submit" class="primary-button">Add Equipment</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="log-maintenance-modal" aria-hidden="true">
    <div class="modal-card">
        <form method="POST" action="{{ route('admin.maintenance.store') }}">
            @csrf
            <input type="hidden" name="maintenance_id" id="modal-maintenance-id" value="{{ old('maintenance_id') }}">
            <div class="modal-head"><h3>Log Equipment Repair</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <div style="background: var(--surface-soft); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; border: 1px solid var(--border);">
                    <span style="color: var(--text-secondary); font-size: 12px;">Equipment:</span>
                    <strong id="modal-equipment-name" style="display: block; color: var(--navy); font-size: 14px; margin-top: 2px;"></strong>
                </div>
                <div class="field">
                    <label for="repair-notes">What was repaired? *</label>
                    <textarea id="repair-notes" name="notes" rows="4" maxlength="1000" required placeholder="Describe the issue and the repair work completed...">{{ old('notes') }}</textarea>
                    @error('notes') <small class="field-error">{{ $message }}</small> @enderror
                </div>
                <div class="form-grid" style="margin-top: 14px;">
                    <div class="field">
                        <label for="repair-cost">Repair Cost (₱) *</label>
                        <input id="repair-cost" type="number" name="cost" min="0" max="99999999.99" step="0.01" value="{{ old('cost', '0.00') }}" required>
                        @error('cost') <small class="field-error">{{ $message }}</small> @enderror
                    </div>
                    <div class="field">
                        <label for="equipment-status">Equipment Status *</label>
                        <select id="equipment-status" name="equipment_status" required>
                            <option value="operational" @selected(old('equipment_status', 'operational') === 'operational')>Operational</option>
                            <option value="under_repair" @selected(old('equipment_status') === 'under_repair')>Under Repair</option>
                            <option value="out_of_service" @selected(old('equipment_status') === 'out_of_service')>Out of Service</option>
                        </select>
                        @error('equipment_status') <small class="field-error">{{ $message }}</small> @enderror
                    </div>
                </div>
                @error('maintenance_id') <small class="field-error">{{ $message }}</small> @enderror
            </div>
            <div class="modal-footer"><button type="button" class="secondary-button" data-modal-close>Cancel</button><button type="submit" class="primary-button">Save Repair Record</button></div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
    (function () {
        var equipmentModal = document.getElementById('add-equipment-modal');
        var repairModal = document.getElementById('log-maintenance-modal');
        var equipmentNames = @json($equipmentData->pluck('name', 'id'));

        function openModal(modal, focusSelector) {
            if (!modal) return;
            modal.classList.add('is-active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            modal.querySelector(focusSelector)?.focus();
        }
        function closeModal(modal) {
            modal.classList.remove('is-active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        window.openEquipmentModal = function () { openModal(equipmentModal, '#equipment-name'); };
        window.openMaintenanceModal = function (id, name) {
            document.getElementById('modal-maintenance-id').value = id;
            document.getElementById('modal-equipment-name').textContent = name;
            openModal(repairModal, '#repair-notes');
        };

        [equipmentModal, repairModal].forEach(function (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.addEventListener('click', function (event) {
                if (event.target === modal || event.target.closest('[data-modal-close]')) closeModal(modal);
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                [equipmentModal, repairModal].forEach(function (modal) {
                    if (modal.classList.contains('is-active')) closeModal(modal);
                });
            }
        });

        @if($errors->has('equipment_name'))
            window.openEquipmentModal();
        @elseif($errors->any() && old('maintenance_id'))
            window.openMaintenanceModal(@js(old('maintenance_id')), equipmentNames[@js(old('maintenance_id'))] || 'Selected equipment');
        @endif
    }());
</script>
@endpush
@endsection
