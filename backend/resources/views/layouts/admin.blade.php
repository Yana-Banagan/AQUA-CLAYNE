<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · AQUA CLAYNE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('admin.css') }}"> @stack('styles')
</head>
<body class="admin-body"><div class="admin-shell" id="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Primary navigation">
        <a class="brand" href="{{ route('admin.dashboard') }}"><span class="brand-mark">A</span><span><strong>AQUA CLAYNE</strong><small>Water Refilling System</small></span></a>
        <nav class="sidebar-nav">
            <p class="nav-label">Overview</p><a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}"><span>⌂</span> Dashboard</a>
            <p class="nav-label">Operations</p><a class="nav-link" href="#"><span>♙</span> Customers</a><a class="nav-link {{ request()->routeIs('admin.drivers.*') ? 'is-active' : '' }}" href="{{ route('admin.drivers.index') }}"><span>♜</span> Drivers</a><a class="nav-link" href="#"><span>◈</span> Products &amp; Inventory</a><a class="nav-link" href="#"><span>▣</span> Orders <b id="order-badge" class="nav-count" hidden>0</b></a><a class="nav-link" href="#"><span>₱</span> Payments</a><a class="nav-link" href="#"><span>◌</span> Credit / Debt</a><a class="nav-link {{ request()->routeIs('admin.deliveries.*') ? 'is-active' : '' }}" href="{{ route('admin.deliveries.assign') }}"><span>⌁</span> Deliveries</a><a class="nav-link" href="#"><span>◉</span> RFID &amp; Gallons</a>
            <p class="nav-label">Customer service</p><a class="nav-link" href="#"><span>!</span> Complaints</a><a class="nav-link" href="#"><span>?</span> FAQ Chatbot</a><a class="nav-link" href="#"><span>♢</span> Notifications</a>
            <p class="nav-label">Analytics</p><a class="nav-link" href="#"><span>↗</span> Sales &amp; Reports</a>
            <p class="nav-label">System</p><a class="nav-link" href="#"><span>⌘</span> Maintenance</a><a class="nav-link" href="#"><span>☷</span> Audit Trail</a><a class="nav-link" href="#"><span>⚙</span> Settings</a>
        </nav>
        <div class="sidebar-user"><span class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span><span><strong>{{ auth()->user()->name ?? 'Administrator' }}</strong><small>{{ ucfirst(auth()->user()->role ?? 'admin') }}</small></span></div>
    </aside>
    <section class="admin-main"><header class="topbar">
        <button class="icon-button sidebar-toggle" type="button" aria-label="Toggle navigation" data-sidebar-toggle>☰</button><div class="global-search"><span>⌕</span><input type="search" aria-label="Global search" placeholder="Search customers, orders, drivers, gallons..."></div>
        <div class="topbar-actions"><a href="{{ route('admin.drivers.create') }}" class="quick-create">＋ <span>Quick create</span></a><button class="icon-button" type="button" aria-label="Toggle theme" data-theme-toggle>◐</button>
            <div class="dropdown-wrap"><button class="icon-button has-indicator" type="button" aria-label="Notifications" data-dropdown="notifications">♢</button><div class="dropdown notification-panel" id="notifications"><div class="dropdown-head"><strong>Notifications</strong><a href="#">Mark all read</a></div><div class="notification-item"><span class="notification-icon info">▣</span><span><strong>Order activity</strong><small>New orders will appear here.</small></span></div><div class="notification-item"><span class="notification-icon warning">!</span><span><strong>Stay on top of tasks</strong><small>Payment and delivery alerts are shown here.</small></span></div><a class="dropdown-footer" href="#">View all notifications</a></div></div>
            <div class="dropdown-wrap profile-wrap"><button class="profile-button" type="button" data-dropdown="profile-menu"><span class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span><span class="profile-name"><strong>{{ auth()->user()->name ?? 'Administrator' }}</strong><small>Administrator</small></span><span>⌄</span></button><div class="dropdown profile-menu" id="profile-menu"><div class="profile-card"><span class="avatar large">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span><span><strong>{{ auth()->user()->name ?? 'Administrator' }}</strong><small>{{ auth()->user()->email ?? '' }}</small></span></div><a href="#">My Profile</a><a href="#">Change Password</a><a href="#">Activity Log</a><a href="#">Settings</a><form method="POST" action="{{ route('admin.logout') }}" data-confirm="Sign out of AQUA CLAYNE?"><button class="danger-link" type="submit">Sign out</button>@csrf</form></div></div>
        </div>
    </header><main class="page-content">@if (session('success'))<div class="flash-message success">✓ {{ session('success') }}</div>@endif @if ($errors->any())<div class="flash-message danger">{{ $errors->first() }}</div>@endif @yield('content')</main></section>
</div><div id="admin-toast" class="toast" role="status" aria-live="polite"></div><script src="{{ asset('admin.js') }}"></script>@stack('scripts')</body></html>
