@extends('admin.layouts.app')

@section('title', 'Products')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">Catalog &amp; Offerings</p>
        <h1>Products</h1>
        <p>Manage water offerings and refill service pricing available to customers on mobile.</p>
    </div>
    <div class="header-actions">
        <a class="primary-button" href="{{ route('admin.products.create') }}">
            <span>＋</span>
            <span>Add Refill Service</span>
        </a>
    </div>
</div>

<!-- Refill Service Concept Banner (Section 26) -->
<article class="panel" style="margin-bottom: 24px; background: var(--surface-soft); border-left: 4px solid var(--primary); padding: 16px 20px;">
    <div style="display: flex; gap: 12px; align-items: center;">
        <span style="font-size: 22px;">💧</span>
        <div>
            <strong style="color: var(--navy); font-size: 13.5px;">Customer-Owned Container Refill Business Model</strong>
            <p style="margin: 3px 0 0; color: var(--text-secondary); font-size: 12.5px;">
                In the AQUA CLAYNE primary flow, catalog items represent refill services or water offerings. Order quantities represent gallons to refill into customer-owned containers, not physical plastic container sales.
            </p>
        </div>
    </div>
</article>

<!-- Services Table -->
<article class="panel">
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Offering Type</th>
                    <th>Refill Price</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td>
                            <strong style="font-size: 14px; color: var(--text-primary);">{{ $product->name }}</strong>
                        </td>
                        <td>
                            <span class="ownership-badge">Refill Service</span>
                        </td>
                        <td>
                            <strong style="font-size: 15px; color: var(--navy);">₱{{ number_format($product->price, 2) }}</strong>
                            <small style="color: var(--text-secondary); display: block;">per gallon refill</small>
                        </td>
                        <td style="max-width: 320px;">
                            <span style="color: var(--text-secondary); font-size: 13px;">{{ $product->description ?? 'Standard station refill service' }}</span>
                        </td>
                        <td>
                            <span class="status-badge {{ $product->is_active ? 'status-success' : 'status-danger' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.products.edit', $product) }}" class="table-action">
                                Edit Service
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <span class="empty-icon">◈</span>
                                <strong>No refill services configured</strong>
                                <p>Add a water offering such as "5-Gallon Purified Water Refill" to make it available for ordering.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($products->hasPages())
        <div style="padding: 20px 0;">
            {{ $products->links() }}
        </div>
    @endif
</article>
@endsection
