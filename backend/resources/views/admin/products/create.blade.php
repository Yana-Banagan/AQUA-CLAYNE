@extends('admin.layouts.app')

@section('title', 'Add Refill Service')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">
            <a href="{{ route('admin.products.index') }}" style="color: var(--primary); text-decoration: none;">← Back to Products &amp; Services</a>
            &nbsp;·&nbsp; New Offering
        </p>
        <h1>Add Refill Service Offering</h1>
        <p>Configure a new water offering for station refill operations.</p>
    </div>
</div>

<div class="panel" style="max-width: 800px;">
    <form method="POST" action="{{ route('admin.products.store') }}">
        @csrf
        
        <div class="form-grid">
            <div class="field" style="grid-column: 1 / -1; padding: 12px 14px; background: #EAF7FF; border: 1px solid #BAE6FD; border-radius: 8px; color: #075985;">
                New refill services start with <strong>100 stock</strong>. The stock is automatically renewed to 100 each day.
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label for="name">Service / Offering Name *</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. 5-Gallon Purified Water Refill" required>
            </div>
            
            <div class="field">
                <label for="price">Refill Price (₱) *</label>
                <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', '50.00') }}" required>
                <small>Charge per customer-owned container refilled.</small>
            </div>
            
            <div class="field">
                <label>Status</label>
                <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Active &amp; Available for Ordering</span>
                </label>
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <label for="description">Service Description</label>
                <textarea id="description" name="description" rows="3" placeholder="e.g. Pure 16-stage reverse osmosis purified drinking water for customer 5-gallon containers.">{{ old('description') }}</textarea>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; border-top: 1px solid var(--border-light); padding-top: 18px;">
            <a class="secondary-button" href="{{ route('admin.products.index') }}">Cancel</a>
            <button class="primary-button" type="submit">Create Refill Offering</button>
        </div>
    </form>
</div>
@endsection
