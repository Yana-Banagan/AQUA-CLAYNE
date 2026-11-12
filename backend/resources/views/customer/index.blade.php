<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>Aqua Clayne Purified Water | Customer Portal</title>

    <!-- Browser Favicon & Touch Icons (Maximized Artwork Display) -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ time() }}">
    <link rel="apple-touch-icon" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Leaflet CSS for Live Driver Route Tracking -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

    <!-- Aqua Clayne Customer Portal Global Design System CSS -->
    <link rel="stylesheet" href="{{ asset('css/customer-app.css') }}?v={{ time() }}">
</head>
<body class="customer-body">

    <!-- =======================================================================
         1. GLOBAL NAVIGATION HEADER (Consistent Across All Pages)
         Upper-Left: Logo | Center: 5 Nav Links | Upper-Right: Bell & Account
    ======================================================================== -->
    <header class="global-navbar" id="globalNavbar">
        <div class="nav-container">
            <!-- Upper-Left Corner: Logo & Brand Name (aqua_clayne_logo_splash.png) -->
            <a href="javascript:void(0)" onclick="window.aquaApp.navigateTo('home')" class="nav-brand-link" title="Aqua Clayne Purified Drinking Water">
                <img src="{{ asset('images/aqua_clayne_logo_splash.png') }}" alt="Aqua Clayne Logo" class="nav-brand-logo" onerror="this.src='{{ asset('images/aqua_clayne_logo.png') }}'">
                <div class="nav-brand-text">
                    <span class="nav-brand-title">AQUA CLAYNE</span>
                    <span class="nav-brand-subtitle">PURIFIED DRINKING WATER</span>
                </div>
            </a>

            <!-- Center Navigation Menu (5 Primary Links) -->
            <nav class="nav-center-menu" id="navCenterMenu" aria-label="Main Navigation">
                <button type="button" class="nav-link-btn active" data-nav="home" onclick="window.aquaApp.navigateTo('home')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <span>Home</span>
                </button>
                <button type="button" class="nav-link-btn" data-nav="cart" onclick="window.aquaApp.navigateTo('cart')">
                    <div class="nav-cart-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <span class="nav-cart-badge cart-badge-count">0</span>
                    </div>
                    <span>Cart</span>
                </button>
                <button type="button" class="nav-link-btn" data-nav="tracking" onclick="window.aquaApp.navigateTo('tracking')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <span>Track Order</span>
                </button>
                <button type="button" class="nav-link-btn" data-nav="orders" onclick="window.aquaApp.navigateTo('orders')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>My Orders</span>
                </button>
                <button type="button" class="nav-link-btn" data-nav="profile" onclick="window.aquaApp.navigateTo('profile')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Profile</span>
                </button>
            </nav>

            <!-- Upper-Right Corner: 1. Bell Icon immediately before 2. Account Dropdown -->
            <div class="nav-right-actions">
                <!-- 1. Notification Bell Icon -->
                <div class="nav-notif-container" style="position:relative;">
                    <button type="button" class="nav-circle-btn" id="navNotifBtn" onclick="window.aquaApp.toggleNotifDropdown()" aria-label="Notifications" title="Notifications">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span class="nav-notif-indicator" id="navNotifIndicator"></span>
                    </button>
                    <!-- Notification Popover -->
                    <div class="notif-dropdown-popover" id="navNotifPopover">
                        <div class="notif-popover-header">
                            <span class="notif-popover-title">Notifications</span>
                            <button type="button" class="notif-mark-read-btn" onclick="window.aquaApp.markAllNotificationsRead()">Mark all as read</button>
                        </div>
                        <div class="notif-popover-list" id="notifPopoverList">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                </div>

                <!-- 2. Customer Profile / Account Dropdown -->
                <div class="nav-user-menu-wrap" id="userMenuWrap">
                    <button type="button" class="nav-user-btn" id="userProfileMenuBtn" aria-haspopup="true" aria-expanded="false">
                        <div class="nav-user-avatar" id="topUserAvatar">{{ substr($authenticatedUser->name, 0, 2) }}</div>
                        <span class="nav-user-name" id="topUserName">{{ $authenticatedUser->name }}</span>
                        <svg class="user-dropdown-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="user-profile-dropdown" id="userProfileDropdown">
                        <div class="dropdown-header">
                            <span class="dropdown-header-title">{{ $authenticatedUser->name }}</span>
                            <span class="station-chip">Verified Customer</span>
                        </div>
                        <button type="button" class="dropdown-link" onclick="window.aquaApp.navigateTo('profile')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            My Profile
                        </button>
                        <button type="button" class="dropdown-link" onclick="window.aquaApp.navigateTo('orders')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            My Orders
                        </button>
                        <button type="button" class="dropdown-link" onclick="window.aquaApp.navigateTo('tracking')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            Track Order
                        </button>
                        <button type="button" class="dropdown-link" onclick="window.aquaApp.navigateTo('cart')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            Shopping Cart
                        </button>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-link logout-link" onclick="window.aquaApp.confirmLogout()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Log Out
                        </button>
                    </div>
                </div>

                <!-- Mobile Hamburger Button -->
                <button type="button" class="nav-mobile-toggle" id="navMobileToggle" onclick="window.aquaApp.toggleMobileMenu()" aria-label="Toggle navigation menu">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Mobile Drawer Navigation (Collapsible on mobile) -->
        <div class="mobile-nav-drawer" id="mobileNavDrawer">
            <button type="button" class="mobile-drawer-link" data-nav="home" onclick="window.aquaApp.navigateTo('home')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Home</span>
            </button>
            <button type="button" class="mobile-drawer-link" data-nav="cart" onclick="window.aquaApp.navigateTo('cart')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span>Cart (<span class="cart-badge-count">0</span>)</span>
            </button>
            <button type="button" class="mobile-drawer-link" data-nav="tracking" onclick="window.aquaApp.navigateTo('tracking')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <span>Track Order</span>
            </button>
            <button type="button" class="mobile-drawer-link" data-nav="orders" onclick="window.aquaApp.navigateTo('orders')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span>My Orders</span>
            </button>
            <button type="button" class="mobile-drawer-link" data-nav="profile" onclick="window.aquaApp.navigateTo('profile')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Profile</span>
            </button>
            <div style="height:1px; background:rgba(255,255,255,0.12); margin:4px 0;"></div>
            <button type="button" class="mobile-drawer-link" onclick="window.aquaApp.confirmLogout()" style="color:#FCA5A5;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Log Out</span>
            </button>
        </div>
    </header>

    <!-- Main Content Container (Centered full-width application layout) -->
    <main class="customer-main-wrapper" id="customerMainContent">

        <!-- ===================================================================
             PAGE 1: HOME – WATER DELIVERY DASHBOARD (Reference Image 5)
        ==================================================================== -->
        <section id="view-home" class="view-section active-view">
            <!-- A. Hero Banner (Balanced Full-Width Water Background) -->
            <div class="water-hero-card" style="background-image: linear-gradient(135deg, rgba(8, 121, 209, 0.92) 0%, rgba(21, 153, 232, 0.82) 100%), url('{{ asset('images/banner_gallon_splash.png') }}');">
                <div class="hero-left-content">
                    <div class="hero-eyebrow-row">
                        <span class="hero-eyebrow-tag">AQUA CLAYNE</span>
                        <div class="pure-safe-badge">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>Pure &amp; Safe</span>
                        </div>
                    </div>
                    <h1 class="hero-main-heading">Clean Water, Healthier Lives.</h1>
                    <p class="hero-subtext">Enjoy pure and refreshing water delivered right to your doorstep in Gatid, Duhat, and Bubukal.</p>
                    <div class="hero-btn-row">
                        <button type="button" class="btn-hero-order-now" onclick="document.getElementById('servicesSection').scrollIntoView({behavior: 'smooth'})">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                            <span>Order Now</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                        </button>
                        <button type="button" class="btn-hero-track-delivery" onclick="window.aquaApp.navigateTo('tracking')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span>Track Delivery</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- B. Our Water Services Section -->
            <div class="home-services-layout" id="servicesSection">
                <div class="home-services-main">
                    <div class="services-header-row">
                        <div class="services-title-wrap">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="#0879D1"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                            <h2 class="services-heading">Our Water Services</h2>
                        </div>
                        <button type="button" class="btn-view-all-products" onclick="window.aquaApp.navigateTo('cart')">
                            <span>View All Products</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>

                    <!-- 3 Product Cards Grid -->
                    <div class="home-products-grid" id="homeProductsGrid">
                        <!-- Card 1: New Gallon with Water -->
                        <div class="product-service-card" data-product-id="prod_new_gallon">
                            <div class="product-card-img-wrap">
                                <img src="{{ asset('images/aqua_clayne_gallon.png') }}" alt="New Gallon with Water" class="product-card-img">
                                <span class="product-avail-badge"><span class="avail-dot"></span> Available</span>
                            </div>
                            <div class="product-card-info">
                                <h3 class="product-card-name">New Gallon with Water</h3>
                                <p class="product-card-desc">A brand-new 5-gallon container filled with purified water.</p>
                                <div class="product-card-price">₱200.00 <span class="unit">/ gallon</span></div>
                            </div>
                            <div class="product-card-bottom">
                                <div class="product-stepper">
                                    <button type="button" class="stepper-btn" onclick="window.aquaApp.adjustLocalQty('prod_new_gallon', -1)" aria-label="Decrease">&minus;</button>
                                    <span class="stepper-val" id="qty_prod_new_gallon">1</span>
                                    <button type="button" class="stepper-btn" onclick="window.aquaApp.adjustLocalQty('prod_new_gallon', 1)" aria-label="Increase">&plus;</button>
                                </div>
                                <button type="button" class="btn-card-add-cart" onclick="window.aquaApp.addConfiguredProductToCart('332afd4e-4ec7-4c78-87e2-5a697717d26f', 'New Gallon with Water', 200.00, 'new_gallon', 'qty_prod_new_gallon')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    <span>Add to Cart</span>
                                </button>
                            </div>
                        </div>

                        <!-- Card 2: Purified Water Refill -->
                        <div class="product-service-card" data-product-id="prod_purified_refill">
                            <div class="product-card-img-wrap">
                                <img src="{{ asset('images/aqua_clayne_gallon.png') }}" alt="Purified Water Refill" class="product-card-img">
                                <span class="product-avail-badge"><span class="avail-dot"></span> Available</span>
                            </div>
                            <div class="product-card-info">
                                <h3 class="product-card-name">Purified Water Refill</h3>
                                <p class="product-card-desc">Multi-stage purified water with reverse osmosis and UV filtration.</p>
                                <div class="product-card-price">₱35.00 <span class="unit">/ gallon</span></div>
                            </div>
                            <div class="product-card-bottom">
                                <div class="product-stepper">
                                    <button type="button" class="stepper-btn" onclick="window.aquaApp.adjustLocalQty('prod_purified_refill', -1)" aria-label="Decrease">&minus;</button>
                                    <span class="stepper-val" id="qty_prod_purified_refill">1</span>
                                    <button type="button" class="stepper-btn" onclick="window.aquaApp.adjustLocalQty('prod_purified_refill', 1)" aria-label="Increase">&plus;</button>
                                </div>
                                <button type="button" class="btn-card-add-cart" onclick="window.aquaApp.addConfiguredProductToCart('01a19621-bcdd-72aa-86cc-aa4ebb87e744', 'Purified Water Refill', 35.00, 'refill', 'qty_prod_purified_refill')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    <span>Add to Cart</span>
                                </button>
                            </div>
                        </div>

                        <!-- Card 3: Mineral Water Refill -->
                        <div class="product-service-card" data-product-id="prod_mineral_refill">
                            <div class="product-card-img-wrap">
                                <img src="{{ asset('images/aqua_clayne_gallon.png') }}" alt="Mineral Water Refill" class="product-card-img">
                                <span class="product-avail-badge"><span class="avail-dot"></span> Available</span>
                            </div>
                            <div class="product-card-info">
                                <h3 class="product-card-name">Mineral Water Refill</h3>
                                <p class="product-card-desc">Natural mineral-enriched drinking water replenishing.</p>
                                <div class="product-card-price">₱40.00 <span class="unit">/ gallon</span></div>
                            </div>
                            <div class="product-card-bottom">
                                <div class="product-stepper">
                                    <button type="button" class="stepper-btn" onclick="window.aquaApp.adjustLocalQty('prod_mineral_refill', -1)" aria-label="Decrease">&minus;</button>
                                    <span class="stepper-val" id="qty_prod_mineral_refill">1</span>
                                    <button type="button" class="stepper-btn" onclick="window.aquaApp.adjustLocalQty('prod_mineral_refill', 1)" aria-label="Increase">&plus;</button>
                                </div>
                                <button type="button" class="btn-card-add-cart" onclick="window.aquaApp.addConfiguredProductToCart('01a19621-bcef-72f5-89a3-6a9135c9a8ff', 'Mineral Water Refill', 40.00, 'refill', 'qty_prod_mineral_refill')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    <span>Add to Cart</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- C. Delivery Information Sidebar (Right Column) -->
                <aside class="home-services-sidebar" aria-label="Delivery Information">
                    <!-- Sidebar Card 1: Delivery Fee -->
                    <div class="info-sidebar-card">
                        <div class="sidebar-card-icon-box">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        </div>
                        <div class="sidebar-card-body">
                            <span class="sidebar-card-label">Delivery Fee</span>
                            <div class="sidebar-card-val">₱5.00</div>
                            <span class="sidebar-card-sub">for Home Delivery</span>
                        </div>
                    </div>

                    <!-- Sidebar Card 2: Delivery Area -->
                    <div class="info-sidebar-card">
                        <div class="sidebar-card-icon-box">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <div class="sidebar-card-body">
                            <span class="sidebar-card-label">Delivery Area</span>
                            <div class="sidebar-card-val-sm">Gatid, Duhat and Bubukal Only</div>
                        </div>
                    </div>

                    <!-- Sidebar Card 3: Safe & Secure -->
                    <div class="info-sidebar-card safe-secure-card" style="background-image: linear-gradient(135deg, rgba(224, 242, 254, 0.94) 0%, rgba(186, 230, 253, 0.9) 100%), url('{{ asset('images/banner_gallon_splash.png') }}');">
                        <div class="sidebar-card-icon-box shield-icon-box">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                        </div>
                        <div class="sidebar-card-body">
                            <span class="sidebar-card-label bold-dark">Safe &amp; Secure</span>
                            <p class="safe-secure-desc">Your orders and payments are protected.</p>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- D. Service Benefits (4 Cards at Bottom) -->
            <div class="home-benefits-row">
                <div class="benefit-card">
                    <div class="benefit-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    </div>
                    <div class="benefit-text">
                        <h4 class="benefit-title">Easy Ordering</h4>
                        <p class="benefit-desc">Order in just a few taps.</p>
                    </div>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div class="benefit-text">
                        <h4 class="benefit-title">Live Tracking</h4>
                        <p class="benefit-desc">Know your delivery status.</p>
                    </div>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                    </div>
                    <div class="benefit-text">
                        <h4 class="benefit-title">Customer Support</h4>
                        <p class="benefit-desc">We're here to help.</p>
                    </div>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    </div>
                    <div class="benefit-text">
                        <h4 class="benefit-title">Pure Quality</h4>
                        <p class="benefit-desc">Trusted and reliable.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             PAGE 2: SHOPPING CART (Reference Image 4)
        ==================================================================== -->
        <section id="view-cart" class="view-section">
            <!-- A. Page Banner -->
            <div class="water-hero-card cart-hero-banner" style="background-image: linear-gradient(135deg, rgba(8, 121, 209, 0.92) 0%, rgba(21, 153, 232, 0.82) 100%), url('{{ asset('images/banner_gallon_splash.png') }}');">
                <div class="hero-left-content">
                    <div class="hero-eyebrow-row">
                        <span class="hero-eyebrow-tag">AQUA CLAYNE</span>
                        <div class="pure-safe-badge">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>Pure &amp; Safe</span>
                        </div>
                    </div>
                    <h1 class="hero-main-heading">Shopping Cart</h1>
                    <p class="hero-subtext">Review your items before proceeding to checkout.</p>
                </div>
            </div>

            <!-- B. Cart Items & Summary Container -->
            <div class="cart-main-container">
                <div class="cart-header-title-row">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <h3 class="cart-container-heading" id="cartContainerHeading">Your Cart (0 Items)</h3>
                </div>

                <!-- Items List -->
                <div class="cart-items-list" id="cartItemsList">
                    <!-- Populated dynamically via customer-app.js renderCart() -->
                </div>

                <!-- Middle 2-Column: Special Instructions (Left) & Order Summary (Right) -->
                <div class="cart-bottom-grid" id="cartBottomGrid" style="display:none;">
                    <!-- Left: Special Instructions -->
                    <div class="special-instructions-card">
                        <div class="instruction-header-row">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            <label for="cartSpecialInstructions" class="instruction-label">Special Instructions (Optional)</label>
                        </div>
                        <textarea id="cartSpecialInstructions" class="instruction-textarea" rows="3" placeholder="e.g., Leave at the porch, ring doorbell, etc." oninput="window.aquaApp.saveCartInstructions(this.value)"></textarea>
                    </div>

                    <!-- Right: Order Summary -->
                    <div class="cart-summary-card">
                        <div class="summary-header-row">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <h4 class="summary-card-title">Order Summary</h4>
                        </div>
                        <div class="summary-line-row">
                            <span>Subtotal</span>
                            <span id="cartSummarySubtotal">₱0.00</span>
                        </div>
                        <div class="summary-line-row">
                            <span>Delivery Fee</span>
                            <span id="cartSummaryDeliveryFee">₱5.00</span>
                        </div>
                        <div class="summary-line-row total-highlight">
                            <span>Total Amount</span>
                            <span class="total-blue-val" id="cartSummaryTotal">₱0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Action Buttons -->
                <div class="cart-actions-row" id="cartActionsRow" style="display:none;">
                    <button type="button" class="btn-continue-shopping" onclick="window.aquaApp.navigateTo('home')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                        <span>Continue Shopping</span>
                    </button>
                    <button type="button" class="btn-proceed-checkout" onclick="window.aquaApp.openCheckoutModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                        <span>Proceed to Checkout</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             PAGE 3: TRACK ORDER (Reference Image 3)
        ==================================================================== -->
        <section id="view-tracking" class="view-section">
            <!-- A. Hero Banner -->
            <div class="water-hero-card tracking-hero-banner" style="background-image: linear-gradient(135deg, rgba(8, 121, 209, 0.92) 0%, rgba(21, 153, 232, 0.82) 100%), url('{{ asset('images/banner_gallon_splash.png') }}');">
                <div class="hero-left-content">
                    <div class="hero-eyebrow-row">
                        <span class="hero-eyebrow-tag">AQUA CLAYNE</span>
                        <div class="pure-safe-badge">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>Pure &amp; Safe</span>
                        </div>
                    </div>
                    <h1 class="hero-main-heading">Track Your Order</h1>
                    <p class="hero-subtext">Stay updated on your delivery in real-time.</p>
                </div>
            </div>

            <!-- B. Main Tracking 2-Column Layout -->
            <div id="orderDetailContainer" class="tracking-dashboard-layout">
                <!-- Dynamically rendered via customer-app.js loadTrackingView() -->
            </div>
        </section>

        <!-- ===================================================================
             PAGE 4: MY ORDERS (Reference Image 1)
        ==================================================================== -->
        <section id="view-orders" class="view-section">
            <!-- A. Hero Banner -->
            <div class="water-hero-card orders-hero-banner" style="background-image: linear-gradient(135deg, rgba(8, 121, 209, 0.92) 0%, rgba(21, 153, 232, 0.82) 100%), url('{{ asset('images/banner_gallon_splash.png') }}');">
                <div class="hero-left-content">
                    <span class="hero-eyebrow-tag">AQUA CLAYNE</span>
                    <h1 class="hero-main-heading">My Orders</h1>
                    <p class="hero-subtext">View and manage your orders anytime.</p>
                </div>
            </div>

            <!-- B. Order Filter Tabs -->
            <div class="orders-filter-bar">
                <button type="button" class="order-filter-pill active" data-filter="All" onclick="window.aquaApp.setOrderFilter('All')">All Orders</button>
                <button type="button" class="order-filter-pill" data-filter="Active" onclick="window.aquaApp.setOrderFilter('Active')">Active Deliveries</button>
                <button type="button" class="order-filter-pill" data-filter="Completed" onclick="window.aquaApp.setOrderFilter('Completed')">Completed</button>
                <button type="button" class="order-filter-pill" data-filter="Cancelled" onclick="window.aquaApp.setOrderFilter('Cancelled')">Cancelled</button>
            </div>

            <!-- C. Order History Cards Container -->
            <div class="orders-history-card-container">
                <div class="orders-list-inner" id="ordersListContainer">
                    <!-- Populated dynamically via customer-app.js renderOrdersList() -->
                </div>
            </div>
        </section>

        <!-- ===================================================================
             PAGE 5: PROFILE & ACCOUNT SETTINGS (Reference Image 2)
        ==================================================================== -->
        <section id="view-profile" class="view-section">
            <div class="profile-page-header">
                <span class="page-eyebrow-label">CUSTOMER ACCOUNT</span>
                <h1 class="page-main-heading">Profile &amp; Account Settings</h1>
                <p class="page-subtitle-text">Manage your personal credentials, contact info, and login password.</p>
            </div>

            <div class="profile-cards-stack" id="profileViewContainer">
                <!-- Dynamically populated via renderProfileView() -->
            </div>
        </section>

    </main>

    <!-- =======================================================================
         FUNCTIONAL MODALS (Profile Edit, Change Password, Report Issue, Checkout)
    ======================================================================== -->

    <!-- 1. EDIT PERSONAL INFORMATION MODAL (Reference Image 2 Modal) -->
    <div class="modal-backdrop-overlay" id="editPersonalModal">
        <div class="modal-box profile-edit-modal-box">
            <!-- Header -->
            <div class="modal-box-header">
                <div class="modal-header-title-group">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <h3 class="modal-heading-text">Edit Personal Information</h3>
                </div>
                <button type="button" class="modal-close-x" onclick="window.aquaApp.closeModal('editPersonalModal')" aria-label="Close modal">&times;</button>
            </div>

            <!-- Modal Form Body -->
            <form id="editPersonalForm" onsubmit="event.preventDefault(); window.aquaApp.handleSaveProfile(this);">
                <!-- Profile Photo -->
                <div class="modal-photo-row">
                    <div class="avatar-camera-wrapper">
                        <div class="modal-user-avatar" id="editModalAvatar">{{ substr($authenticatedUser->name, 0, 2) }}</div>
                        <div class="camera-badge-circle">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </div>
                    </div>
                    <div class="photo-info-col">
                        <label class="btn-change-photo" for="profilePhotoInput">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            <span>Change Photo</span>
                        </label>
                        <input type="file" id="profilePhotoInput" accept="image/jpeg,image/png" style="display:none;" onchange="window.aquaApp.previewProfilePhoto(this)">
                        <span class="photo-hint-text">JPG, PNG (Max 2MB)</span>
                    </div>
                </div>

                <!-- Form Inputs Grid -->
                <div class="modal-form-grid">
                    <div class="modal-input-group">
                        <label class="modal-label">Full Name <span class="req-star">*</span></label>
                        <input type="text" name="name" id="editModalName" class="modal-input-field" value="{{ $authenticatedUser->name }}" required>
                    </div>
                    <div class="modal-input-group">
                        <label class="modal-label">Email Address <span class="req-star">*</span></label>
                        <input type="email" name="email" id="editModalEmail" class="modal-input-field" value="{{ $authenticatedUser->email }}" required>
                    </div>
                    <div class="modal-input-group">
                        <label class="modal-label">Mobile Number <span class="req-star">*</span></label>
                        <input type="tel" name="phone" id="editModalPhone" class="modal-input-field" value="{{ $authenticatedUser->phone ?? '09171234567' }}" placeholder="09171234567" required>
                    </div>
                    <div class="modal-input-group">
                        <label class="modal-label">Birthdate <span class="req-star">*</span></label>
                        <div class="input-with-icon-wrap">
                            <svg class="field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <input type="date" name="birthdate" id="editModalBirthdate" class="modal-input-field with-icon" value="2006-11-08" required>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer Actions -->
                <div class="modal-footer-actions">
                    <button type="button" class="btn-modal-cancel" onclick="window.aquaApp.closeModal('editPersonalModal')">Cancel</button>
                    <button type="submit" class="btn-modal-save" id="btnSaveProfile">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. UNIVERSAL CONFIRMATION DIALOG MODAL (Logout, Cancel Order, Delete Address) -->
    <div class="modal-backdrop-overlay" id="confirmModal">
        <div class="modal-box confirm-modal-box">
            <button type="button" class="modal-close-x" onclick="window.aquaApp.closeModal('confirmModal')" aria-label="Close modal">&times;</button>
            <div class="confirm-modal-icon-wrap">
                <div class="confirm-icon-circle danger-icon-circle" id="confirmIconCircle">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" id="confirmIconSvg">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </div>
            </div>
            <h3 class="modal-heading-text confirm-modal-title" id="confirmModalTitle">Log Out of Aqua Clayne?</h3>
            <p class="confirm-modal-msg" id="confirmModalMsg">Are you sure you want to log out? You will need to sign in again to place orders and track deliveries.</p>
            <div class="modal-footer-actions confirm-modal-actions">
                <button type="button" class="btn-modal-cancel" id="confirmModalCancelBtn" onclick="window.aquaApp.closeModal('confirmModal')">Cancel</button>
                <button type="button" class="btn-modal-save danger-confirm-btn" id="confirmModalBtn">Log Out</button>
            </div>
        </div>
    </div>

    <!-- 2. CHANGE PASSWORD MODAL -->
    <div class="modal-backdrop-overlay" id="changePasswordModal">
        <div class="modal-box" style="max-width:480px;">
            <div class="modal-box-header">
                <div class="modal-header-title-group">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <h3 class="modal-heading-text">Change Login Password</h3>
                </div>
                <button type="button" class="modal-close-x" onclick="window.aquaApp.closeModal('changePasswordModal')">&times;</button>
            </div>
            <form onsubmit="event.preventDefault(); window.aquaApp.submitChangePasswordForm(this.current_password.value, this.password.value, this.password_confirmation.value);">
                <div class="modal-form-grid" style="grid-template-columns:1fr;">
                    <div class="modal-input-group">
                        <label class="modal-label">Current Password <span class="req-star">*</span></label>
                        <input type="password" name="current_password" class="modal-input-field" required>
                    </div>
                    <div class="modal-input-group">
                        <label class="modal-label">New Password <span class="req-star">*</span></label>
                        <input type="password" name="password" class="modal-input-field" required>
                    </div>
                    <div class="modal-input-group">
                        <label class="modal-label">Confirm New Password <span class="req-star">*</span></label>
                        <input type="password" name="password_confirmation" class="modal-input-field" required>
                    </div>
                </div>
                <div class="modal-footer-actions">
                    <button type="button" class="btn-modal-cancel" onclick="window.aquaApp.closeModal('changePasswordModal')">Cancel</button>
                    <button type="submit" class="btn-modal-save">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. UNIVERSAL CONFIRMATION DIALOG MODAL (Logout, Cancel Order, Delete Address) -->
    <div class="modal-backdrop-overlay" id="confirmModal">
        <div class="modal-box confirm-modal-box">
            <button type="button" class="modal-close-x" onclick="window.aquaApp.closeModal('confirmModal')" aria-label="Close modal">&times;</button>
            <div class="confirm-modal-icon-wrap">
                <div class="confirm-icon-circle danger-icon-circle" id="confirmIconCircle">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" id="confirmIconSvg">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </div>
            </div>
            <h3 class="modal-heading-text confirm-modal-title" id="confirmModalTitle">Log Out of Aqua Clayne?</h3>
            <p class="confirm-modal-msg" id="confirmModalMsg">Are you sure you want to log out? You will need to sign in again to place orders and track deliveries.</p>
            <div class="modal-footer-actions confirm-modal-actions">
                <button type="button" class="btn-modal-cancel" id="confirmModalCancelBtn" onclick="window.aquaApp.closeModal('confirmModal')">Cancel</button>
                <button type="button" class="btn-modal-save danger-confirm-btn" id="confirmModalBtn">Log Out</button>
            </div>
        </div>
    </div>

    <!-- 3. REPORT ISSUE MODAL -->
    <div class="modal-backdrop-overlay" id="reportIssueModal">
        <div class="modal-box" style="max-width:520px;">
            <div class="modal-box-header">
                <div class="modal-header-title-group">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <h3 class="modal-heading-text">Report Issue with this Order</h3>
                </div>
                <button type="button" class="modal-close-x" onclick="window.aquaApp.closeModal('reportIssueModal')">&times;</button>
            </div>
            <form onsubmit="event.preventDefault(); window.aquaApp.handleReportIssueSubmit(this);">
                <div class="modal-form-grid" style="grid-template-columns:1fr;">
                    <div class="modal-input-group">
                        <label class="modal-label">Order Number</label>
                        <input type="text" name="order_number" id="reportIssueOrderNum" class="modal-input-field" readonly>
                    </div>
                    <div class="modal-input-group">
                        <label class="modal-label">Issue Category <span class="req-star">*</span></label>
                        <select name="category" class="modal-input-field" required>
                            <option value="water_quality">Water Quality or Seal Condition</option>
                            <option value="delivery_delay">Delivery Delay or Late Arrival</option>
                            <option value="gallon_condition">Container Damage or Leak</option>
                            <option value="billing_payment">Payment or Billing Discrepancy</option>
                            <option value="driver_conduct">Driver Service or Route Inquiry</option>
                            <option value="other">Other Inquiry</option>
                        </select>
                    </div>
                    <div class="modal-input-group">
                        <label class="modal-label">Description of Issue <span class="req-star">*</span></label>
                        <textarea name="description" class="modal-input-field" rows="4" placeholder="Please provide specific details to help our team resolve your concern quickly." required></textarea>
                    </div>
                </div>
                <div class="modal-footer-actions">
                    <button type="button" class="btn-modal-cancel" onclick="window.aquaApp.closeModal('reportIssueModal')">Cancel</button>
                    <button type="submit" class="btn-modal-save">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. UNIVERSAL CONFIRMATION DIALOG MODAL (Logout, Cancel Order, Delete Address) -->
    <div class="modal-backdrop-overlay" id="confirmModal">
        <div class="modal-box confirm-modal-box">
            <button type="button" class="modal-close-x" onclick="window.aquaApp.closeModal('confirmModal')" aria-label="Close modal">&times;</button>
            <div class="confirm-modal-icon-wrap">
                <div class="confirm-icon-circle danger-icon-circle" id="confirmIconCircle">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" id="confirmIconSvg">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </div>
            </div>
            <h3 class="modal-heading-text confirm-modal-title" id="confirmModalTitle">Log Out of Aqua Clayne?</h3>
            <p class="confirm-modal-msg" id="confirmModalMsg">Are you sure you want to log out? You will need to sign in again to place orders and track deliveries.</p>
            <div class="modal-footer-actions confirm-modal-actions">
                <button type="button" class="btn-modal-cancel" id="confirmModalCancelBtn" onclick="window.aquaApp.closeModal('confirmModal')">Cancel</button>
                <button type="button" class="btn-modal-save danger-confirm-btn" id="confirmModalBtn">Log Out</button>
            </div>
        </div>
    </div>

    <!-- 4. CHECKOUT MODAL (Complete, Validated, & Address Managed) -->
    <div class="modal-backdrop-overlay" id="checkoutModal">
        <div class="modal-box checkout-modal-box">
            <div class="modal-box-header">
                <div class="modal-header-title-group">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                    <h3 class="modal-heading-text">Confirm Delivery &amp; Checkout</h3>
                </div>
                <button type="button" class="modal-close-x" onclick="window.aquaApp.closeModal('checkoutModal')">&times;</button>
            </div>
            
            <form id="checkoutForm" onsubmit="event.preventDefault(); window.aquaApp.handleCheckoutSubmit(this);" class="checkout-modal-form">
                <div class="checkout-modal-body">
                    
                    <!-- 1. Fulfillment Method -->
                    <div class="modal-input-group">
                        <label class="modal-label">Fulfillment Option <span class="req-star">*</span></label>
                        <div class="radio-pill-group">
                            <label class="radio-pill-label">
                                <input type="radio" name="fulfillment_method" value="home_delivery" checked onchange="window.aquaApp.updateCheckoutMethod('home_delivery')">
                                <span>Home Delivery (₱5.00)</span>
                            </label>
                            <label class="radio-pill-label">
                                <input type="radio" name="fulfillment_method" value="station_pickup" onchange="window.aquaApp.updateCheckoutMethod('station_pickup')">
                                <span>Station Pickup (FREE)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Station Pickup Information Banner (shown only when station_pickup is active) -->
                    <div id="checkoutStationNotice" class="station-pickup-notice-box" style="display:none;">
                        <div class="station-notice-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </div>
                        <div class="station-notice-text">
                            <strong>Station Pickup Point:</strong> Aqua Clayne Water Refilling Station<br>
                            <span class="text-muted">Gatid, Santa Cruz, Laguna &bull; Open Mon–Sat 8:00 AM – 5:00 PM</span><br>
                            <span class="text-free-pill">FREE (₱0.00 Delivery Fee) &bull; Claim your refilled containers directly at the station counter.</span>
                        </div>
                    </div>

                    <!-- 2. Delivery Destination Section (for Home Delivery) -->
                    <div id="checkoutDeliveryAddressSection" class="delivery-address-section">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <label class="modal-label" style="margin-bottom:0;">
                                Delivery Destination <span class="req-star">*</span>
                                <span class="badge-supported-area">Santa Cruz, Laguna</span>
                            </label>
                            <button type="button" id="btnToggleEditAddress" class="btn-edit-addr-link" onclick="window.aquaApp.toggleEditAddressForm()" style="display:none;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                <span>Edit Address</span>
                            </button>
                        </div>

                        <!-- Saved Address Display Card -->
                        <div id="checkoutSavedAddressCard" class="saved-address-card" style="display:none;">
                            <div class="saved-addr-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0879D1" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            </div>
                            <div class="saved-addr-content">
                                <div class="saved-addr-header-row">
                                    <span class="saved-addr-tag">Default Delivery Address</span>
                                    <span class="saved-addr-brgy-tag" id="savedAddressBrgyTag">Brgy. Gatid</span>
                                </div>
                                <p class="saved-addr-full-text" id="savedAddressFullText">007 Batisan St., Gatid, Santa Cruz, Laguna</p>
                                <p class="saved-addr-landmark" id="savedAddressLandmark" style="display:none;"></p>
                            </div>
                        </div>

                        <!-- Address Form Fields (Visible when no saved address, or in edit mode) -->
                        <div id="checkoutAddressFormFields" class="address-fields-grid" style="display:none;">
                            <!-- Province & Municipality (Readonly/Locked) -->
                            <div class="form-row-2col">
                                <div class="modal-input-subgroup">
                                    <label class="modal-sublabel">Province</label>
                                    <input type="text" id="checkoutProvince" class="modal-input-field modal-input-readonly" value="Laguna" readonly>
                                </div>
                                <div class="modal-input-subgroup">
                                    <label class="modal-sublabel">Municipality</label>
                                    <input type="text" id="checkoutMunicipality" class="modal-input-field modal-input-readonly" value="Santa Cruz" readonly>
                                </div>
                            </div>

                            <!-- Barangay Dropdown (Strictly 3 barangays) -->
                            <div class="modal-input-subgroup">
                                <label class="modal-sublabel">Barangay <span class="req-star">* (Service Area Restricted)</span></label>
                                <select id="checkoutBarangay" class="modal-input-field" required>
                                    <option value="Gatid">Gatid, Santa Cruz, Laguna</option>
                                    <option value="Duhat">Duhat, Santa Cruz, Laguna</option>
                                    <option value="Bubukal">Bubukal, Santa Cruz, Laguna</option>
                                </select>
                            </div>

                            <!-- Street & House Number -->
                            <div class="form-row-2col">
                                <div class="modal-input-subgroup">
                                    <label class="modal-sublabel">House / Building Number <span class="req-star">*</span></label>
                                    <input type="text" id="checkoutHouseNo" class="modal-input-field" placeholder="e.g., House No. 12" required>
                                </div>
                                <div class="modal-input-subgroup">
                                    <label class="modal-sublabel">Street / Road Name <span class="req-star">*</span></label>
                                    <input type="text" id="checkoutStreet" class="modal-input-field" placeholder="e.g., 007 Batisan Street" required>
                                </div>
                            </div>

                            <!-- Subdivision & Landmark -->
                            <div class="form-row-2col">
                                <div class="modal-input-subgroup">
                                    <label class="modal-sublabel">Subdivision / Purok / Sitio <span class="opt-label">(Optional)</span></label>
                                    <input type="text" id="checkoutSubdivision" class="modal-input-field" placeholder="e.g., Purok 3">
                                </div>
                                <div class="modal-input-subgroup">
                                    <label class="modal-sublabel">Landmark <span class="opt-label">(Optional)</span></label>
                                    <input type="text" id="checkoutLandmark" class="modal-input-field" placeholder="e.g., Near Barangay Hall / School">
                                </div>
                            </div>

                            <!-- Save / Cancel Address Buttons Row (visible only when editing existing address) -->
                            <div id="checkoutAddressSaveRow" class="address-save-action-row" style="display:none;">
                                <button type="button" class="btn-cancel-addr-edit" onclick="window.aquaApp.cancelEditAddress()">Cancel Edit</button>
                                <button type="button" class="btn-save-addr-edit" onclick="window.aquaApp.saveEditedAddress()">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span>Save Address</span>
                                </button>
                            </div>
                        </div>

                        <!-- Additional Delivery Instructions -->
                        <div class="modal-input-subgroup" style="margin-top:10px;">
                            <label class="modal-sublabel">Additional Delivery Instructions <span class="opt-label">(Optional)</span></label>
                            <textarea id="checkoutNotes" class="modal-input-field" rows="2" placeholder="e.g., Please leave the water gallons beside the main gate or call upon arrival."></textarea>
                        </div>
                    </div>

                    <!-- 3. Customer Mobile Number -->
                    <div class="modal-input-group">
                        <label class="modal-label">Customer Mobile Number <span class="req-star">*</span></label>
                        <input type="tel" id="checkoutPhone" class="modal-input-field" placeholder="09xxxxxxxxx" value="{{ $authenticatedUser->phone ?? '09171234567' }}" required>
                        <span class="input-helper-text">Our delivery rider will use this number to contact you when arriving.</span>
                    </div>

                    <!-- 4. Payment Method -->
                    <div class="modal-input-group">
                        <label class="modal-label">Payment Method <span class="req-star">*</span></label>
                        <div class="radio-pill-group">
                            <label class="radio-pill-label">
                                <input type="radio" name="payment_method" value="online" checked>
                                <span>Online (PayMongo)</span>
                            </label>
                            <label class="radio-pill-label">
                                <input type="radio" name="payment_method" value="cod">
                                <span>Cash on Delivery (COD)</span>
                            </label>
                        </div>
                        <select id="checkoutPaymentProvider" class="modal-input-field" style="margin-top:10px;">
                            <option value="qrph">QR Ph</option>
                            <option value="gcash">GCash</option>
                        </select>
                    </div>

                    <!-- 5. Order Summary Calculation Box -->
                    <div class="checkout-summary-box">
                        <div class="summary-line-row"><span>Items Subtotal:</span><span id="checkoutSubtotal">₱0.00</span></div>
                        <div class="summary-line-row"><span id="checkoutFeeLabel">Delivery Fee (Home Delivery):</span><span id="checkoutFee">₱5.00</span></div>
                        <div class="summary-line-row total-highlight"><span>Total Amount:</span><span class="total-blue-val" id="checkoutTotal">₱0.00</span></div>
                    </div>
                </div>

                <div class="modal-footer-actions checkout-modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="window.aquaApp.closeModal('checkoutModal')">Cancel</button>
                    <button type="submit" class="btn-modal-save" id="btnSubmitOrder">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Confirm &amp; Place Order</span>
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- 5. UNIVERSAL CONFIRMATION DIALOG MODAL (Logout, Cancel Order, Delete Address) -->
    <div class="modal-backdrop-overlay" id="confirmModal">
        <div class="modal-box confirm-modal-box">
            <button type="button" class="modal-close-x" onclick="window.aquaApp.closeModal('confirmModal')" aria-label="Close modal">&times;</button>
            <div class="confirm-modal-icon-wrap">
                <div class="confirm-icon-circle danger-icon-circle" id="confirmIconCircle">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" id="confirmIconSvg">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </div>
            </div>
            <h3 class="modal-heading-text confirm-modal-title" id="confirmModalTitle">Log Out of Aqua Clayne?</h3>
            <p class="confirm-modal-msg" id="confirmModalMsg">Are you sure you want to log out? You will need to sign in again to place orders and track deliveries.</p>
            <div class="modal-footer-actions confirm-modal-actions">
                <button type="button" class="btn-modal-cancel" id="confirmModalCancelBtn" onclick="window.aquaApp.closeModal('confirmModal')">Cancel</button>
                <button type="button" class="btn-modal-save danger-confirm-btn" id="confirmModalBtn">Log Out</button>
            </div>
        </div>
    </div>

    <!-- 5. UNIVERSAL CONFIRMATION DIALOG MODAL (Logout, Cancel Order, Delete Address) -->
    <div class="modal-backdrop-overlay" id="confirmModal">
        <div class="modal-box confirm-modal-box">
            <button type="button" class="modal-close-x" onclick="window.aquaApp.closeModal('confirmModal')" aria-label="Close modal">&times;</button>
            <div class="confirm-modal-icon-wrap">
                <div class="confirm-icon-circle danger-icon-circle" id="confirmIconCircle">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" id="confirmIconSvg">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </div>
            </div>
            <h3 class="modal-heading-text confirm-modal-title" id="confirmModalTitle">Log Out of Aqua Clayne?</h3>
            <p class="confirm-modal-msg" id="confirmModalMsg">Are you sure you want to log out? You will need to sign in again to place orders and track deliveries.</p>
            <div class="modal-footer-actions confirm-modal-actions">
                <button type="button" class="btn-modal-cancel" id="confirmModalCancelBtn" onclick="window.aquaApp.closeModal('confirmModal')">Cancel</button>
                <button type="button" class="btn-modal-save danger-confirm-btn" id="confirmModalBtn">Log Out</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Toast Container -->
    <div class="toast-notification-wrap" id="toastWrap"></div>

    <!-- Leaflet JS for Driver Route Mapping -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Preloaded App State from Laravel Backend -->
    <script>
        window.aquaCustomerToken = @json($customerToken);
        window.aquaCustomerUser = @json($authenticatedUser);
        window.aquaCustomerData = @json($customer);
        window.aquaPreloadedProducts = @json($preloadedProducts);
        window.aquaStationConfig = @json($station);
        window.aquaRealtimeConfig = @json($realtime);
        window.aquaInitialView = @json($initialView);
        window.aquaInitialId = @json($initialId);
        window.aquaCustomerStats = @json($customerStats);
        window.aquaCustomerGallons = @json($customerGallons);
    </script>

    <!-- Customer Application Logic -->
    <script src="{{ asset('js/customer-app.js') }}?v={{ time() }}"></script>
</body>
</html>
