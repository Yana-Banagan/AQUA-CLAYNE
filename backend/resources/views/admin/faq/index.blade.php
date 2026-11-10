@extends('admin.layouts.app')

@section('title', 'FAQ Management')

@section('content')
@if(session('success'))
    <div class="flash-message success" role="status">{{ session('success') }}</div>
@endif

<div class="page-header">
    <div>
        <p class="eyebrow">Customer Support &amp; Knowledge Base</p>
        <h1>Frequently Asked Questions (FAQ)</h1>
        <p>Manage customer self-service answers regarding refill procedures, container guidelines, and payment options.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="primary-button" onclick="openFaqModal('add-faq-modal')">
            <span>＋</span>
            <span>Add New FAQ</span>
        </button>
    </div>
</div>

<!-- FAQ Categories Overview -->
<section class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    @forelse($categories as $cat)
        <article class="stat-card">
            <span class="stat-label">{{ $cat->name }}</span>
            <strong class="stat-value">{{ $cat->faqs->count() }}</strong>
            <small class="stat-meta">Questions published</small>
        </article>
    @empty
        <article class="stat-card">
            <span class="stat-label">Categories</span>
            <strong class="stat-value">0</strong>
        </article>
    @endforelse
</section>

<!-- FAQs List -->
<article class="panel">
    <div class="panel-head">
        <h2>Published Questions &amp; Answers</h2>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Question</th>
                    <th>Answer Preview</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($faqs as $faq)
                    <tr>
                        <td>
                            <span class="ownership-badge">{{ $faq->category?->name ?? 'General' }}</span>
                        </td>
                        <td style="max-width: 280px;">
                            <strong style="color: var(--text-primary);">{{ $faq->question }}</strong>
                        </td>
                        <td style="max-width: 420px; color: var(--text-secondary); font-size: 13px;">
                            {{ Str::limit($faq->answer, 120) }}
                        </td>
                        <td>
                            <div class="row-actions">
                <button type="button" class="table-action" onclick="openEditFaqModal(@js($faq->id), @js($faq->category_id), @js($faq->question), @js($faq->answer))">
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('admin.faq.destroy', $faq) }}" data-confirm="Are you sure you want to delete this FAQ entry?" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="table-action danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <span class="empty-icon">?</span>
                                <strong>No FAQs created yet</strong>
                                <p>Create your first FAQ to help mobile app customers get instant answers.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($faqs->hasPages())
        <div style="padding: 20px 0;">{{ $faqs->links() }}</div>
    @endif
</article>

@push('modals')
<!-- Modal 1: Add FAQ Modal -->
<div class="modal-backdrop" id="add-faq-modal">
    <div class="modal-card">
        <form method="POST" action="{{ route('admin.faq.store') }}">
            @csrf
            <div class="modal-head">
                <h3>Add New FAQ Entry</h3>
                <button type="button" class="modal-close" onclick="closeFaqModal('add-faq-modal')" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label>Category *</label>
                    <select name="category_id" required style="width: 100%;">
                        <option value="">-- Choose Category --</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id') === $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Question *</label>
                    <input type="text" name="question" value="{{ old('question') }}" required placeholder="e.g. Can I provide my own 5-gallon slim container?" style="width: 100%;">
                </div>
                <div class="field">
                    <label>Answer *</label>
                    <textarea name="answer" rows="4" required placeholder="Provide a helpful and accurate answer..." style="width: 100%;">{{ old('answer') }}</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" onclick="closeFaqModal('add-faq-modal')" data-modal-close>Cancel</button>
                <button type="submit" class="primary-button">Publish FAQ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit FAQ Modal -->
<div class="modal-backdrop" id="edit-faq-modal">
    <div class="modal-card">
        <form method="POST" id="edit-faq-form" action="#">
            @csrf
            @method('PUT')
            <div class="modal-head">
                <h3>Edit FAQ Entry</h3>
                <button type="button" class="modal-close" onclick="closeFaqModal('edit-faq-modal')" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label>Category *</label>
                    <select name="category_id" id="edit-faq-category" required style="width: 100%;">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Question *</label>
                    <input type="text" name="question" id="edit-faq-question" required style="width: 100%;">
                </div>
                <div class="field">
                    <label>Answer *</label>
                    <textarea name="answer" id="edit-faq-answer" rows="4" required style="width: 100%;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" onclick="closeFaqModal('edit-faq-modal')" data-modal-close>Cancel</button>
                <button type="submit" class="primary-button">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
    (function () {
        function openFaqModal(id) {
            var modal = typeof id === 'string' ? document.getElementById(id) : id;
            if (!modal) return;
            modal.classList.add('is-active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            modal.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus();
        }

        function closeFaqModal(modalOrId) {
            var modal = typeof modalOrId === 'string' ? document.getElementById(modalOrId) : modalOrId;
            if (!modal) return;
            modal.classList.remove('is-active');
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        window.openFaqModal = openFaqModal;
        window.closeFaqModal = closeFaqModal;
        window.openEditFaqModal = function (id, categoryId, question, answer) {
            document.getElementById('edit-faq-category').value = categoryId || '';
            document.getElementById('edit-faq-question').value = question || '';
            document.getElementById('edit-faq-answer').value = answer || '';
            document.getElementById('edit-faq-form').action = '/admin/faq/' + encodeURIComponent(id);
            openFaqModal('edit-faq-modal');
        };

        document.querySelectorAll('#add-faq-modal, #edit-faq-modal').forEach(function (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.addEventListener('click', function (event) {
                if (event.target === modal || event.target.closest('[data-modal-close]')) closeFaqModal(modal);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('#add-faq-modal.is-active, #edit-faq-modal.is-active').forEach(closeFaqModal);
            }
        });

        @if($errors->any())
            openFaqModal('add-faq-modal');
        @endif
    }());
</script>
@endpush
@endsection
