@extends('admin.layouts.app')

@section('title', 'Notification Center')

@section('content')
<div class="notification-center" id="notificationCenter">
<div class="page-header">
    <div>
        <p class="eyebrow">Alerts &amp; Activity</p>
        <h1>Notification Center</h1>
        <p>Operational alerts, refill queue notifications, driver dispatch milestones, and system messages.</p>
    </div>
    <div class="header-actions">
        <form method="POST" action="{{ route('admin.notifications.read-all') }}">
            @csrf
            <button type="submit" class="secondary-button">
                <span>✓</span>
                <span>Mark All as Read</span>
            </button>
        </form>
    </div>
</div>

<article class="panel">
    <div class="tab-nav">
        <a href="{{ route('admin.notifications.index', ['filter' => 'all']) }}" class="tab-link {{ $filter === 'all' ? 'active' : '' }}">
            All Alerts ({{ $stats['total'] }})
        </a>
        <a href="{{ route('admin.notifications.index', ['filter' => 'unread']) }}" class="tab-link {{ $filter === 'unread' ? 'active' : '' }}">
            Unread Only ({{ $stats['unread'] }})
        </a>
    </div>

    <div style="display: flex; flex-direction: column; gap: 12px;">
        @forelse($notifications as $notif)
            <div style="display: flex; gap: 16px; align-items: flex-start; padding: 16px 18px; border-radius: var(--radius-md); background: {{ $notif->is_read ? 'var(--surface)' : 'var(--surface-soft)' }}; border: 1px solid {{ $notif->is_read ? 'var(--border-light)' : 'var(--border)' }};">
                <span class="notification-icon {{ $notif->is_read ? 'info' : 'warning' }}" style="margin-top: 2px;">
                    {{ $notif->is_read ? '✓' : '●' }}
                </span>
                
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                        <strong style="color: var(--text-primary); font-size: 14px;">{{ $notif->title }}</strong>
                        <small style="color: var(--text-secondary); white-space: nowrap;">
                            {{ $notif->created_at ? $notif->created_at->diffForHumans() : 'Recently' }}
                        </small>
                    </div>
                    
                    <p style="margin: 6px 0 0; color: var(--text-secondary); font-size: 13px; line-height: 1.5;">
                        {{ $notif->body }}
                    </p>
                </div>

                @if(!$notif->is_read)
                    <form method="POST" action="{{ route('admin.notifications.read', $notif) }}" style="margin-left: 8px;">
                        @csrf
                        <button type="submit" class="table-action" style="white-space: nowrap;">
                            Mark Read
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <div class="empty-state">
                <span class="empty-icon">🔔</span>
                <strong>No notifications</strong>
                <p>You are all caught up! New operational and system alerts will appear here.</p>
            </div>
        @endforelse
    </div>

    @if($notifications->hasPages())
        <div style="padding: 20px 0;">{{ $notifications->links() }}</div>
    @endif
</article>
</div>
@endsection

@push('scripts')
<script>
(() => {
    let timer = null;
    let inFlight = false;
    let pending = false;
    const refresh = async () => {
        if (document.visibilityState !== 'visible') { pending = true; return; }
        if (inFlight) { pending = true; return; }
        inFlight = true;
        try {
            const response = await fetch(window.location.href, {
                headers: { Accept: 'text/html' },
                cache: 'no-store'
            });
            if (!response.ok) return;
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const current = document.getElementById('notificationCenter');
            const updated = parsed.getElementById('notificationCenter');
            if (current && updated) current.replaceWith(updated);
        } catch (_) {
            // Leave the current notification list in place if refresh fails.
        } finally {
            inFlight = false;
            if (pending) { pending = false; schedule(); }
        }
    };
    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(refresh, 180);
    };
    const listener = (event) => {
        if (event.detail?.entity_type === 'notification') schedule();
    };
    window.addEventListener('aqua:realtime', listener);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && pending) { pending = false; schedule(); }
    });
    window.addEventListener('beforeunload', () => {
        window.clearTimeout(timer);
        window.removeEventListener('aqua:realtime', listener);
    }, { once: true });
})();
</script>
@endpush
