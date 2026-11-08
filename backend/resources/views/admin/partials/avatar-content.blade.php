@php($avatarUser = $user ?? null)
@php($avatarName = $avatarUser?->name ?? ($fallbackName ?? 'User'))
@php($avatarUrl = $avatarUser?->avatar_url ?? ($avatarUrl ?? null))
@if($avatarUrl)
    <img src="{{ $avatarUrl }}" alt="" class="profile-avatar-image" loading="lazy" onerror="this.parentNode.classList.add('avatar-failed');this.remove()">
    <span class="profile-avatar-fallback" aria-hidden="true">{{ strtoupper(mb_substr($avatarName, 0, 1)) }}</span>
@else
    {{ strtoupper(mb_substr($avatarName, 0, 1)) }}
@endif
