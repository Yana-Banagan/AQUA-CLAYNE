@extends('admin.layouts.app')

@section('title', 'Edit ' . $product->name)

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">
            <a href="{{ route('admin.products.index') }}" style="color: var(--primary); text-decoration: none;">← Back to Products &amp; Services</a>
            &nbsp;·&nbsp; Service Details
        </p>
        <h1>Edit: {{ $product->name }}</h1>
        <p>Update pricing, availability status, or description.</p>
    </div>
</div>

<div class="panel" style="max-width: 800px;">
    <form method="POST" action="{{ route('admin.products.update', $product) }}">
        @csrf
        @method('PUT')
        
        <div class="form-grid">
            <div class="field" style="grid-column: 1 / -1;">
                <label for="name">Service / Offering Name *</label>
                <input id="name" type="text" name="name" value="{{ old('name', $product->name) }}" required>
            </div>
            
            <div class="field">
                <label for="price">Refill Price (₱) *</label>
                <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" required>
            </div>
            
            <div class="field">
                <label>Status</label>
                <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }}>
                    <span>Active &amp; Available for Ordering</span>
                </label>
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <label for="description">Service Description</label>
                <textarea id="description" name="description" rows="3">{{ old('description', $product->description) }}</textarea>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; border-top: 1px solid var(--border-light); padding-top: 18px;">
            <a class="secondary-button" href="{{ route('admin.products.index') }}">Cancel</a>
            <button class="primary-button" type="submit">Save Changes</button>
        </div>
    </form>
</div>
@endsection
