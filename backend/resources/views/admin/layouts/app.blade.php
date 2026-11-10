<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="description" content="AQUA CLAYNE Water Refilling Station Management System - Administration Workspace">

    <title>@yield('title', 'Admin Dashboard') · AQUA CLAYNE</title>

    <!-- Browser Favicon & Touch Icons (Maximized Artwork Display) -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ time() }}">
    <link rel="apple-touch-icon" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Stylesheet -->
    {{-- Legacy page primitives are retained while all admin pages share this shell. --}}
    <link rel="stylesheet" href="{{ asset('admin.css') }}?v=20261005-2">
    <link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}?v=20261005-4">
    <link rel="stylesheet" href="{{ asset('css/admin-page-shell.css') }}?v=20261007-5">

    <!-- Theme initialisation: runs immediately to prevent flash of wrong theme -->
    <script>
        (function () {
            var saved = localStorage.getItem('aquaclayneTheme');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = saved ? saved : (prefersDark ? 'dark' : 'light');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        }());
    </script>
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-dark-mode.css') }}?v=20261006-4">
    <link rel="stylesheet" href="{{ asset('css/admin-responsive.css') }}?v=20261007-5">
</head>
<body class="dashboard-body">

    <!-- Mobile Sidebar Dark Backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

    <div class="dashboard-layout">

        <!-- ===================================================================
             SIDEBAR NAVIGATION (250px Fixed / Off-Canvas on Mobile)
        =================================================================== -->
        <aside class="sidebar" id="sidebar" aria-label="Main station navigation">

            <!-- Brand Header -->
            <div class="sidebar-brand">
                <a href="{{ route('admin.dashboard') }}" class="brand-link">
                    <div class="brand-logo-icon" aria-hidden="true">
                        <img src="{{ asset('images/aqua_clayne_logo.png') }}" alt="" class="brand-logo-image">
                    </div>
                    <div class="brand-text-wrap">
                        <span class="brand-company">AQUA CLAYNE</span>
                        <span class="brand-sub">PURIFIED DRINKING WATER</span>
                    </div>
                </a>
            </div>

            <!-- Navigation Links -->
            <nav class="sidebar-nav">

                <!-- MAIN -->
                <div class="nav-group">
                    <div class="nav-group-title">MAIN</div>
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                        </span>
                        <span class="nav-label">Dashboard</span>
                    </a>
                </div>

                <!-- OPERATIONS -->
                <div class="nav-group">
                    <div class="nav-group-title">OPERATIONS</div>

                    <!-- Orders (Prominent with alert badge) -->
                    <a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <path d="M16 10a4 4 0 0 1-8 0"></path>
                            </svg>
                        </span>
                        <span class="nav-label">Orders</span>
                        @if (isset($sidebarCounts['pendingOrders']) && $sidebarCounts['pendingOrders'] > 0)
                            <span class="nav-badge badge-warning" title="{{ $sidebarCounts['pendingOrders'] }} pending orders">
                                {{ $sidebarCounts['pendingOrders'] }}
                            </span>
                        @endif
                    </a>

                    <!-- Refill Queue (Prominent FIFO queue) -->
                    <a href="{{ route('admin.refill-queue.index') }}" class="nav-link {{ request()->routeIs('admin.refill-queue.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <polyline points="1 20 1 14 7 14"></polyline>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                            </svg>
                        </span>
                        <span class="nav-label">Refill Queue</span>
                        @if (isset($sidebarCounts['waitingInQueue']) && $sidebarCounts['waitingInQueue'] > 0)
                            <span class="nav-badge badge-info" title="{{ $sidebarCounts['waitingInQueue'] }} waiting in queue">
                                {{ $sidebarCounts['waitingInQueue'] }}
                            </span>
                        @endif
                    </a>

                    <a href="{{ route('admin.deliveries.index') }}" class="nav-link {{ request()->routeIs('admin.deliveries.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="1" y="3" width="15" height="13"></rect>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                <circle cx="18.5" cy="18.5" r="2.5"></circle>
                            </svg>
                        </span>
                        <span class="nav-label">Deliveries</span>
                        @if (isset($sidebarCounts['activeDeliveries']) && $sidebarCounts['activeDeliveries'] > 0)
                            <span class="nav-badge badge-info" title="{{ $sidebarCounts['activeDeliveries'] }} active deliveries">
                                {{ $sidebarCounts['activeDeliveries'] }}
                            </span>
                        @endif
                    </a>

                    <a href="{{ route('admin.payments.index') }}" class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                <line x1="2" y1="10" x2="22" y2="10"></line>
                            </svg>
                        </span>
                        <span class="nav-label">Payments</span>
                    </a>

                    <a href="{{ route('admin.debt.index') }}" class="nav-link {{ request()->routeIs('admin.debt.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </span>
                        <span class="nav-label">Debt</span>
                    </a>
                </div>

                <!-- MANAGEMENT -->
                <div class="nav-group">
                    <div class="nav-group-title">MANAGEMENT</div>

                    <a href="{{ route('admin.customers.index') }}" class="nav-link {{ request()->routeIs('admin.customers.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </span>
                        <span class="nav-label">Customers</span>
                    </a>

                    <a href="{{ route('admin.drivers.index') }}" class="nav-link {{ request()->routeIs('admin.drivers.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                            </svg>
                        </span>
                        <span class="nav-label">Drivers</span>
                    </a>

                    <a href="{{ route('admin.products.index') }}" class="nav-link {{ request()->routeIs('admin.products.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </span>
                        <span class="nav-label">Products & Services</span>
                    </a>

                    <a href="{{ route('admin.inventory.index') }}" class="nav-link {{ request()->routeIs('admin.inventory.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </span>
                        <span class="nav-label">Inventory</span>
                        @if (isset($sidebarCounts['lowStock']) && $sidebarCounts['lowStock'] > 0)
                            <span class="nav-badge badge-danger" title="{{ $sidebarCounts['lowStock'] }} items need restocking">
                                {{ $sidebarCounts['lowStock'] }}
                            </span>
                        @endif
                    </a>
                </div>

                <!-- COMMUNICATION -->
                <div class="nav-group">
                    <div class="nav-group-title">COMMUNICATION</div>

                    <a href="{{ route('admin.chat.index') }}" class="nav-link {{ request()->routeIs('admin.chat.*') ? 'active-nav' : '' }}" id="chatNavLink">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5a8.5 8.5 0 0 1 4.7-7.6A8.38 8.38 0 0 1 12.5 3h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        </span>
                        <span class="nav-label">Chat</span>
                        <span id="chatUnreadBadge" class="nav-badge badge-danger" {{ empty($sidebarCounts['unreadChats']) ? 'hidden' : '' }}>{{ ($sidebarCounts['unreadChats'] ?? 0) > 99 ? '99+' : ($sidebarCounts['unreadChats'] ?? '') }}</span>
                    </a>

                    <a href="{{ route('admin.notifications.index') }}" class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                        </span>
                        <span class="nav-label">Notifications</span>
                        @if (isset($sidebarCounts['unreadNotifications']) && $sidebarCounts['unreadNotifications'] > 0)
                            <span class="nav-badge badge-accent">
                                {{ $sidebarCounts['unreadNotifications'] }}
                            </span>
                        @endif
                    </a>

                    <a href="{{ route('admin.complaints.index') }}" class="nav-link {{ request()->routeIs('admin.complaints.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </span>
                        <span class="nav-label">Complaints</span>
                    </a>

                    <a href="{{ route('admin.faq.index') }}" class="nav-link {{ request()->routeIs('admin.faq.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                        </span>
                        <span class="nav-label">FAQ</span>
                    </a>
                </div>

                <!-- ANALYTICS -->
                <div class="nav-group">
                    <div class="nav-group-title">ANALYTICS</div>
                    <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="20" x2="18" y2="10"></line>
                                <line x1="12" y1="20" x2="12" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="14"></line>
                            </svg>
                        </span>
                        <span class="nav-label">Reports</span>
                    </a>
                </div>

                <!-- SYSTEM -->
                <div class="nav-group">
                    <div class="nav-group-title">SYSTEM</div>

                    <a href="{{ route('admin.maintenance.index') }}" class="nav-link {{ request()->routeIs('admin.maintenance.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                            </svg>
                        </span>
                        <span class="nav-label">Maintenance</span>
                    </a>

                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <polyline points="17 11 19 13 23 9"></polyline>
                            </svg>
                        </span>
                        <span class="nav-label">Users &amp; Roles</span>
                    </a>

                    <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active-nav' : '' }}">
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </span>
                        <span class="nav-label">Settings</span>
                    </a>
                </div>

            </nav>

            <!-- User Footer & Logout -->
            <div class="sidebar-footer">
                <div class="sidebar-user-card">
                    <div class="user-avatar-circle">
                        @if($adminAvatarUrl)
                            <img src="{{ $adminAvatarUrl }}" alt="{{ auth()->user()->name }} profile picture">
                        @else
                            <img src="{{ asset('images/aqua_clayne_logo.png') }}" alt="Aqua Clayne logo">
                        @endif
                    </div>
                    <div class="user-info">
                        <span class="user-name">{{ auth()->user()->name ?? 'Administrator' }}</span>
                        <span class="user-role">{{ ucfirst(auth()->user()->role ?? 'Admin') }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.logout') }}" class="logout-form">
                    @csrf
                    <button type="submit" class="logout-btn" title="Sign out of administration workspace">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>

        </aside>

        <!-- ===================================================================
             MAIN CONTENT WORKSPACE
        =================================================================== -->
        <div class="dashboard-main">

            <!-- Sticky Topbar Header (64px) -->
            <header class="dashboard-header">
                <div class="header-left">
                    <button type="button" class="menu-toggle-btn" id="menuButton" aria-label="Toggle navigation menu">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>

                    <h1 class="header-page-title">{!! trim($__env->yieldContent('header-title', $__env->yieldContent('title', 'Admin Dashboard'))) !!}</h1>
                </div>

                <div class="header-right">

                    <!-- Dark / Light Mode Toggle -->
                    <button
                        type="button"
                        class="theme-toggle-btn"
                        id="themeToggleBtn"
                        title="Toggle dark / light mode"
                        aria-label="Toggle dark or light theme">
                        <!-- Moon icon: shown in light mode, click → go dark -->
                        <span class="icon-moon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                            </svg>
                        </span>
                        <!-- Sun icon: shown in dark mode, click → go light -->
                        <span class="icon-sun" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                        </span>
                    </button>

                    <!-- Pending Orders: takes the administrator straight to
                         the filtered Orders workspace. -->
                    <a
                        href="{{ route('admin.orders.index', ['status' => 'pending']) }}"
                        class="header-icon-btn header-orders-btn"
                        id="pendingOrdersButton"
                        aria-label="View pending orders"
                        title="Pending orders">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <path d="M3 6h18"></path>
                            <path d="M9 10h6"></path>
                            <path d="M9 14h6"></path>
                        </svg>
                        <span
                            class="notif-count-badge pending-orders-badge"
                            id="pendingOrdersBadge"
                            {{ empty($sidebarCounts['pendingOrders']) ? 'hidden' : '' }}>
                            {{ ($sidebarCounts['pendingOrders'] ?? 0) > 99 ? '99+' : ($sidebarCounts['pendingOrders'] ?? '') }}
                        </span>
                    </a>

                    <!-- Notification Bell Dropdown -->
                    <div class="header-dropdown-wrap">
                        <button type="button" class="header-icon-btn" id="notifButton" aria-label="View notifications" aria-expanded="false">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span class="notif-count-badge" id="adminUnreadNotificationsBadge" {{ empty($sidebarCounts['unreadNotifications']) ? 'hidden' : '' }}>{{ ($sidebarCounts['unreadNotifications'] ?? 0) > 99 ? '99+' : ($sidebarCounts['unreadNotifications'] ?? '') }}</span>
                        </button>

                        <div class="dropdown-menu notif-dropdown" id="notifDropdown">
                            <div class="dropdown-head">
                                <strong>Station Notifications</strong>
                                <a href="{{ route('admin.notifications.index') }}" class="dropdown-link">View All</a>
                            </div>
                            <div class="dropdown-body" id="adminNotificationList">
                                @if(!empty($headerNotifications))
                                    @foreach($headerNotifications as $notif)
                                        <div class="notif-item {{ !$notif['is_read'] ? 'unread' : '' }}">
                                            <div class="notif-item-title">{{ $notif['title'] }}</div>
                                            <div class="notif-item-body">{{ $notif['body'] }}</div>
                                            <div class="notif-item-time">{{ $notif['time_ago'] }}</div>
                                            @if(!$notif['is_read'])
                                                <form method="POST" action="{{ route('admin.notifications.read', $notif['id']) }}" class="notif-mark-read-form">
                                                    @csrf
                                                    <button type="submit" class="notif-mark-read-btn">Mark as read</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <div class="dropdown-empty">No new notifications</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Administrator Profile Dropdown -->
                    <div class="header-dropdown-wrap">
                        <button type="button" class="header-user-btn" id="profileButton" aria-label="User account menu" aria-expanded="false">
                            <div class="header-user-avatar">
                                @if($adminAvatarUrl)
                                    <img src="{{ $adminAvatarUrl }}" alt="{{ auth()->user()->name }} profile picture">
                                @else
                                    <img src="{{ asset('images/aqua_clayne_logo.png') }}" alt="Aqua Clayne logo">
                                @endif
                            </div>
                            <div class="header-user-text">
                                <span class="header-user-name">{{ auth()->user()->name ?? 'Admin' }}</span>
                                <span class="header-user-sub">Administrator</span>
                            </div>
                            <svg class="dropdown-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>

                        <div class="dropdown-menu profile-dropdown" id="profileDropdown">
                            <div class="profile-dropdown-info">
                                <strong>{{ auth()->user()->name ?? 'Administrator' }}</strong>
                                <small>{{ auth()->user()->email ?? 'admin@aquaclayne.ph' }}</small>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="{{ route('admin.settings.index') }}" class="dropdown-menu-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                                <span>Station Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('admin.logout') }}" class="dropdown-logout-form">
                                @csrf
                                <button type="submit" class="dropdown-logout-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                    <span>Sign Out</span>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </header>

            <!-- Main Scrollable Dashboard Content -->
            <main class="dashboard-content">
                @yield('content')
            </main>

        </div>

    </div>

    <!-- Kept above every dashboard while switching sections, so the operator
         never sees a blank white browser-loading page. -->
    {{-- Navigation overlay removed. <div class="admin-navigation-overlay" id="adminNavigationOverlay" aria-live="polite" aria-hidden="true" hidden>
        <div class="admin-navigation-panel">
            <div class="admin-navigation-mark" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                </svg>
            </div>
            <div>
                <strong id="adminNavigationTitle">Opening dashboard</strong>
                <span id="adminNavigationSubtitle">Preparing your workspace…</span>
            </div>
            <i class="admin-navigation-progress" aria-hidden="true"></i>
        </div>
    </div> --}}

    <!-- Modals -->
    @stack('modals')

    <!-- Shared admin scripts. Page-specific libraries load from their page view. -->
    <script src="{{ asset('js/admin-dashboard.js') }}?v=20261005-9" defer></script>
    <script src="{{ asset('js/admin-action-confirmations.js') }}?v=20261005-1"></script>
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.min.js"></script>
    <script>
        // The support inbox is intentionally polled lightly so a station
        // operator sees incoming mobile messages without a manual reload.
        (function () {
            var url = @json(route('admin.status-counts'));
            var chatBadge = document.getElementById('chatUnreadBadge');
            var ordersBadge = document.getElementById('pendingOrdersBadge');
            var notificationBadge = document.getElementById('adminUnreadNotificationsBadge');
            var notificationList = document.getElementById('adminNotificationList');
            var button = document.getElementById('pendingOrdersButton');
            var lastChatCount = chatBadge && !chatBadge.hidden ? Number(chatBadge.textContent) || 0 : 0;
            var inFlight = false;
            var fallbackTimer = null;
            function refreshAdminCounts() {
                if (document.visibilityState !== 'visible' || inFlight) return;
                inFlight = true;
                var controller = new AbortController();
                var timeout = window.setTimeout(function () { controller.abort(); }, 8000);
                fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store', signal: controller.signal})
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) {
                        if (!data) return;
                        var chatCount = Number(data.unread_chats || 0);
                        if (chatBadge) {
                            chatBadge.hidden = chatCount === 0;
                            chatBadge.textContent = chatCount > 99 ? '99+' : chatCount;
                        }
                        if (chatCount > lastChatCount && location.pathname.indexOf('/admin/chat') !== -1) {
                            window.dispatchEvent(new CustomEvent('aqua:chat-incoming'));
                        }
                        lastChatCount = chatCount;

                        var notificationCount = Number(data.unread_notifications || 0);
                        if (notificationBadge) {
                            notificationBadge.hidden = notificationCount === 0;
                            notificationBadge.textContent = notificationCount > 99 ? '99+' : notificationCount;
                        }
                        if (notificationList && Array.isArray(data.notifications)) {
                            notificationList.replaceChildren();
                            if (data.notifications.length === 0) {
                                var empty = document.createElement('div');
                                empty.className = 'dropdown-empty';
                                empty.textContent = 'No new notifications';
                                notificationList.appendChild(empty);
                            } else {
                                data.notifications.forEach(function (item) {
                                    var row = document.createElement('div');
                                    row.className = 'notif-item' + (item.is_read ? '' : ' unread');
                                    var title = document.createElement('div');
                                    title.className = 'notif-item-title';
                                    title.textContent = item.title || 'Station update';
                                    var body = document.createElement('div');
                                    body.className = 'notif-item-body';
                                    body.textContent = item.body || '';
                                    var time = document.createElement('div');
                                    time.className = 'notif-item-time';
                                    time.textContent = item.time_ago || 'Recently';
                                    row.append(title, body, time);
                                    if (!item.is_read) {
                                        var form = document.createElement('form');
                                        form.method = 'POST';
                                        form.action = @json(url('/admin/notifications')) + '/' + encodeURIComponent(item.id) + '/read';
                                        form.className = 'notif-mark-read-form';
                                        var token = document.createElement('input');
                                        token.type = 'hidden';
                                        token.name = '_token';
                                        token.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
                                        var mark = document.createElement('button');
                                        mark.type = 'submit';
                                        mark.className = 'notif-mark-read-btn';
                                        mark.textContent = 'Mark as read';
                                        form.append(token, mark);
                                        row.appendChild(form);
                                    }
                                    notificationList.appendChild(row);
                                });
                            }
                        }

                        var pendingCount = Number(data.pending_orders || 0);
                        document.querySelectorAll('[data-admin-pending-orders]').forEach(function (element) {
                            element.textContent = pendingCount.toLocaleString();
                        });
                        if (ordersBadge && button) {
                            ordersBadge.hidden = pendingCount === 0;
                            ordersBadge.textContent = pendingCount > 99 ? '99+' : pendingCount;
                            button.setAttribute('aria-label', pendingCount ? 'View ' + pendingCount + ' pending orders' : 'View pending orders');
                            button.title = pendingCount ? pendingCount + ' pending order' + (pendingCount === 1 ? '' : 's') : 'No pending orders';
                        }
                    })
                    .catch(function () {})
                    .finally(function () { window.clearTimeout(timeout); inFlight = false; });
            }
            // Initial counts are already server-rendered by Blade.
            // Do not fire a redundant immediate fetch on page load;
            // refresh only via realtime events or fallback polling.
            function startFallback() {
                if (!fallbackTimer) fallbackTimer = window.setInterval(refreshAdminCounts, 30000);
            }
            function stopFallback() {
                if (fallbackTimer) window.clearInterval(fallbackTimer);
                fallbackTimer = null;
            }
            document.addEventListener('visibilitychange', refreshAdminCounts);

            var realtimeConfig = {
                url: @json(config('services.supabase.url')),
                key: @json(config('services.supabase.anon_key'))
            };
            if (realtimeConfig.url && realtimeConfig.key && window.supabase?.createClient) {
                try {
                    window.aquaRealtimeClient = window.aquaRealtimeClient ||
                        window.supabase.createClient(realtimeConfig.url, realtimeConfig.key);
                    window.supabaseClient = window.aquaRealtimeClient;
                    window.aquaAdminRealtimeChannel = window.aquaRealtimeClient
                        .channel('admin-global-signals')
                        .on('postgres_changes', {
                            event: 'INSERT', schema: 'public', table: 'realtime_events'
                        }, function (payload) {
                            var signal = payload.new || {};
                            window.dispatchEvent(new CustomEvent('aqua:realtime', { detail: signal }));
                            if (['order', 'delivery', 'refill_queue', 'notification', 'payment', 'chat'].includes(signal.entity_type)) {
                                // Initial counts are already server-rendered by Blade.
            // Do not fire a redundant immediate fetch on page load;
            // refresh only via realtime events or fallback polling.
                            }
                        })
                        .subscribe(function (status) {
                            window.aquaRealtimeConnected = status === 'SUBSCRIBED';
                            window.dispatchEvent(new CustomEvent('aqua:realtime-status', {
                                detail: { connected: window.aquaRealtimeConnected }
                            }));
                            if (window.aquaRealtimeConnected) stopFallback();
                            else startFallback();
                        });
                } catch (error) {
                    console.warn('[Admin Realtime] Unable to subscribe; using status polling.', error);
                    startFallback();
                }
            } else {
                startFallback();
            }
            window.addEventListener('beforeunload', function () {
                stopFallback();
                if (window.aquaRealtimeClient && window.aquaAdminRealtimeChannel) {
                    window.aquaRealtimeClient.removeChannel(window.aquaAdminRealtimeChannel);
                }
            }, { once: true });
        }());
    </script>
    @stack('scripts')

</body>
</html>
