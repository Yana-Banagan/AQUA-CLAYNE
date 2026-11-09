<div class="pickup-batches-grid">
    <section class="pickup-batch-panel">
        <div class="suggested-section-title">
            <span>Suggested Pickup Batches ({{ count($suggestedPickupBatches) }})</span>
            <small>Grouped by area · Max 30 gallons per vehicle</small>
        </div>

        <div class="active-mission-table-wrap pickup-table-wrap">
            <table class="delivery-table pickup-batches-table" aria-label="Suggested Pickup Batches">
                <thead>
                    <tr>
                        <th scope="col">Batch / Area</th>
                        <th scope="col">Stops / Gallons</th>
                        <th scope="col">Assign Driver</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suggestedPickupBatchesPage as $batch)
                        <tr>
                            <td>
                                <strong style="display:block;color:#0066CC;">{{ $batch['suggested_code'] }}</strong>
                                <span class="suggested-card-tag">{{ $batch['area'] }}</span>
                            </td>
                            <td>
                                <strong>{{ $batch['total_gallons'] }} / 30 gal</strong>
                                <small style="display:block;color:#64748B;">{{ $batch['stops_count'] }} {{ $batch['stops_count'] === 1 ? 'stop' : 'stops' }}</small>
                            </td>
                            <td colspan="2">
                                <form method="POST" action="{{ route('admin.deliveries.batches.store') }}" class="pickup-dispatch-form">
                                    @csrf
                                    <input type="hidden" name="area" value="{{ $batch['area'] }}">
                                    @foreach($batch['orders'] as $order)
                                        <input type="hidden" name="order_ids[]" value="{{ $order['id'] }}">
                                    @endforeach
                                    <select name="driver_id" class="suggested-driver-select" required aria-label="Assign driver for {{ $batch['suggested_code'] }}">
                                        <option value="">Choose driver</option>
                                        @foreach($activeDrivers as $driver)
                                            <option value="{{ $driver->id }}" {{ $batch['suggested_driver_id'] == $driver->id ? 'selected' : '' }}>
                                                {{ $driver->user?->name ?? 'Driver' }} ({{ $driver->vehicle_plate ?? 'N/A' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-dispatch-action">Dispatch</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="pickup-empty-cell">No pending pickup batches.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav class="pickup-pagination" aria-label="Suggested pickup batch pages">
            @if($suggestedPickupBatchesPage->onFirstPage())
                <span class="pickup-page-control is-disabled" aria-disabled="true">Previous</span>
            @else
                <a class="pickup-page-control" href="{{ $suggestedPickupBatchesPage->previousPageUrl() }}" rel="prev">Previous</a>
            @endif
            <span class="pickup-page-count">Page {{ $suggestedPickupBatchesPage->currentPage() }} of {{ max(1, $suggestedPickupBatchesPage->lastPage()) }} · {{ count($suggestedPickupBatches) }} batches</span>
            @if($suggestedPickupBatchesPage->hasMorePages())
                <a class="pickup-page-control" href="{{ $suggestedPickupBatchesPage->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="pickup-page-control is-disabled" aria-disabled="true">Next</span>
            @endif
        </nav>
    </section>

    <section class="pickup-batch-panel">
        <div class="suggested-section-title">
            <span>Active Pickup Batches in Transit ({{ $activePickupBatches->total() }})</span>
            <small>Driver collection progress</small>
        </div>

        <div class="active-mission-table-wrap pickup-table-wrap">
            <table class="delivery-table pickup-batches-table" aria-label="Active Pickup Batches in Transit">
                <thead>
                    <tr>
                        <th scope="col">Batch / Area</th>
                        <th scope="col">Driver</th>
                        <th scope="col">Gallons</th>
                        <th scope="col">Progress</th>
                        <th scope="col">Status / Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activePickupBatches as $batch)
                        @php
                            $doneStops = $batch->items->whereIn('status', ['picked_up', 'failed'])->count();
                            $totalStops = $batch->items->count();
                            $pct = $totalStops > 0 ? round(($doneStops / $totalStops) * 100) : 0;
                        @endphp
                        <tr>
                            <td>
                                <strong style="display:block;color:#0066CC;">{{ $batch->batch_number }}</strong>
                                <span class="suggested-card-tag">{{ $batch->area }}</span>
                            </td>
                            <td>{{ $batch->driver?->user?->name ?? 'Unassigned' }}<small style="display:block;color:#64748B;">{{ $batch->driver?->vehicle_plate ?? 'N/A' }}</small></td>
                            <td>{{ $batch->total_collected_gallons }} / {{ $batch->total_expected_gallons }} gal</td>
                            <td>
                                <div class="pickup-progress-cell">
                                    <div class="mission-progress-bar"><div class="mission-progress-fill" style="width: {{ $pct }}%;"></div></div>
                                    <small>{{ $doneStops }}/{{ $totalStops }}</small>
                                </div>
                            </td>
                            <td>
                                <span class="delivery-status status-{{ $batch->status === 'in_progress' ? 'en_route' : 'assigned' }}">{{ ucwords(str_replace('_', ' ', $batch->status)) }}</span>
                                <div class="cell-actions pickup-row-actions">
                                    <form method="POST" action="{{ route('admin.deliveries.batches.status', $batch->id) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="delivery-action" onclick="return confirm('Complete batch and confirm gallons arrived at station for intake?');">Complete</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.deliveries.batches.status', $batch->id) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="delivery-action secondary" onclick="return confirm('Cancel this pickup batch?');">Cancel</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="pickup-empty-cell">No active pickup batches in transit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav class="pickup-pagination" aria-label="Active pickup batch pages">
            @if($activePickupBatches->onFirstPage())
                <span class="pickup-page-control is-disabled" aria-disabled="true">Previous</span>
            @else
                <a class="pickup-page-control" href="{{ $activePickupBatches->previousPageUrl() }}" rel="prev">Previous</a>
            @endif
            <span class="pickup-page-count">Page {{ $activePickupBatches->currentPage() }} of {{ max(1, $activePickupBatches->lastPage()) }} · {{ $activePickupBatches->total() }} batches</span>
            @if($activePickupBatches->hasMorePages())
                <a class="pickup-page-control" href="{{ $activePickupBatches->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="pickup-page-control is-disabled" aria-disabled="true">Next</span>
            @endif
        </nav>
    </section>
</div>
