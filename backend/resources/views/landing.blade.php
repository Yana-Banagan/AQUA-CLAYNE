<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Aqua Clayne Purified Drinking Water - Station Operations & Digital Refill Management System in Santa Cruz, Laguna. Clean Water, Healthier Lives.">

    <title>Aqua Clayne | Purified Drinking Water & Refill Station</title>

    <!-- Browser Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ time() }}">
    <link rel="apple-touch-icon" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ time() }}">
</head>
<body>

    <!-- ===================================================================
         1. NAVIGATION BAR (FLOATING HEADER)
    =================================================================== -->
    <header class="site-header" id="siteHeader">
        <div class="nav-wrapper">
            <nav class="nav-pill" aria-label="Main Navigation">
                <!-- Upper-left: Brand Logo & Title -->
                <a href="#home" class="brand-link" aria-label="Aqua Clayne Home">
                    <img src="{{ asset('images/aqua_clayne_logo_splash.png') }}" alt="Aqua Clayne Logo" class="brand-logo" width="42" height="42">
                    <div class="brand-text">
                        <span class="brand-title">AQUA CLAYNE</span>
                        <span class="brand-subtitle">PURIFIED DRINKING WATER</span>
                    </div>
                </a>

                <!-- Navigation Links (Center) -->
                <div class="nav-center-menu" id="navCenterMenu">
                    <ul class="nav-links" id="navLinksList">
                        <li><a href="#home" class="nav-item active" data-nav="home">HOME</a></li>
                        <li><a href="#about" class="nav-item" data-nav="about">ABOUT</a></li>
                        <li><a href="#services" class="nav-item" data-nav="services">SERVICES</a></li>
                        <li><a href="#download" class="nav-item" data-nav="download">DOWNLOAD</a></li>
                        <li><a href="#contact" class="nav-item" data-nav="contact">CONTACT</a></li>
                    </ul>
                </div>

                <!-- Right: Login Button & Mobile Hamburger Toggle -->
                <div class="nav-right-actions">
                    <a href="{{ route('admin.login') }}" class="btn-nav-login" id="desktopLoginBtn">
                        <svg class="nav-login-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                        <span>LOGIN</span>
                    </a>

                    <button type="button" class="mobile-toggle" id="mobileToggle" aria-expanded="false" aria-controls="mobileDrawer" aria-label="Toggle navigation menu">
                        <span class="hamburger-bar"></span>
                        <span class="hamburger-bar"></span>
                        <span class="hamburger-bar"></span>
                    </button>
                </div>
            </nav>
        </div>

        <!-- Mobile Drawer Navigation Overlay -->
        <div class="mobile-drawer" id="mobileDrawer" aria-hidden="true">
            <div class="mobile-drawer-content">
                <ul class="mobile-nav-links">
                    <li><a href="#home" class="mobile-nav-item active" data-nav="home">HOME</a></li>
                    <li><a href="#about" class="mobile-nav-item" data-nav="about">ABOUT</a></li>
                    <li><a href="#services" class="mobile-nav-item" data-nav="services">SERVICES</a></li>
                    <li><a href="#download" class="mobile-nav-item" data-nav="download">DOWNLOAD</a></li>
                    <li><a href="#contact" class="mobile-nav-item" data-nav="contact">CONTACT</a></li>
                    <li class="mobile-login-wrapper">
                        <a href="{{ route('admin.login') }}" class="btn-mobile-login">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10 17 15 12 10 7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                            <span>ACCOUNT LOGIN</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main id="mainContent">

        <!-- ===================================================================
             2. HERO SECTION
        =================================================================== -->
        <section class="hero-section" id="home" style="background-image: linear-gradient(135deg, rgba(6, 43, 80, 0.92) 0%, rgba(5, 30, 56, 0.84) 50%, rgba(3, 18, 35, 0.76) 100%), url('{{ asset('images/aqua-clayne-login-background.webp') }}');">
            <div class="hero-bg-overlay" aria-hidden="true"></div>

            <div class="container hero-container">
                <div class="hero-content">
                    <!-- Small Introductory Badge -->
                    <div class="hero-badge">
                        <span class="hero-badge-icon" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                            </svg>
                        </span>
                        <span class="hero-badge-text">EXPECT THE PUREST</span>
                    </div>

                    <!-- Main Heading -->
                    <h1 class="hero-title">AQUA CLAYNE</h1>

                    <!-- Subtitle -->
                    <div class="hero-subtitle">PURIFIED DRINKING WATER</div>

                    <!-- Main Tagline -->
                    <h2 class="hero-supporting-heading">Clean Water, Healthier Lives.</h2>

                    <!-- Description -->
                    <p class="hero-description">
                        Aqua Clayne provides purified drinking water and a digital management system designed to support customer orders, refill operations, delivery coordination, and water station management.
                    </p>

                    <!-- Hero Action Buttons (ONLY TWO BUTTONS) -->
                    <div class="hero-buttons">
                        <!-- 1. Order Water Refill -->
                        <a href="{{ route('customer.index') }}" class="btn-hero-primary" id="heroOrderBtn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                            </svg>
                            <span>ORDER WATER REFILL</span>
                        </a>

                        <!-- 2. Login Portal -->
                        <a href="{{ route('admin.login') }}" class="btn-hero-secondary" id="heroLoginBtn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10 17 15 12 10 7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                            <span>LOGIN PORTAL</span>
                        </a>
                    </div>

                    <!-- 3. Business Highlights Strip -->
                    <div class="hero-highlights-strip" aria-label="Station Key Highlights">
                        <div class="highlight-item">
                            <span class="highlight-dot"></span>
                            <span>Customer-Owned Gallons</span>
                        </div>
                        <div class="highlight-item">
                            <span class="highlight-dot"></span>
                            <span>Verified Station Intake</span>
                        </div>
                        <div class="highlight-item">
                            <span class="highlight-dot"></span>
                            <span>Santa Cruz Delivery</span>
                        </div>
                        <div class="highlight-item">
                            <span class="highlight-dot"></span>
                            <span>Multi-Stage Purification</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             3. ABOUT AQUA CLAYNE – SHORT INTRODUCTION
        =================================================================== -->
        <section class="section about-section" id="about">
            <div class="container">
                <!-- Section Header -->
                <div class="section-header">
                    <span class="section-tag">ABOUT OUR STATION</span>
                    <h2 class="section-title">About Aqua Clayne</h2>
                    <p class="section-lead">
                        Aqua Clayne is a local purified drinking water refilling station committed to providing accessible and convenient drinking water services. Through our digital management platform, customers can place orders, monitor deliveries, and manage their accounts while station personnel coordinate daily operations.
                    </p>
                </div>

                <!-- Three Small Information Cards -->
                <div class="about-pillars-grid" aria-label="Our Core Pillars">
                    <!-- Card 1: Our Mission -->
                    <div class="pillar-card">
                        <div class="pillar-icon-box">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 14 14"></polyline>
                            </svg>
                        </div>
                        <h3 class="pillar-title">Our Mission</h3>
                        <p class="pillar-desc">
                            To provide clean, safe, and affordable drinking water with convenient customer service.
                        </p>
                    </div>

                    <!-- Card 2: Our Vision -->
                    <div class="pillar-card">
                        <div class="pillar-icon-box">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </div>
                        <h3 class="pillar-title">Our Vision</h3>
                        <p class="pillar-desc">
                            To become a trusted local water refilling station recognized for quality and reliable service.
                        </p>
                    </div>

                    <!-- Card 3: Our Commitment -->
                    <div class="pillar-card">
                        <div class="pillar-icon-box">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                        </div>
                        <h3 class="pillar-title">Our Commitment</h3>
                        <p class="pillar-desc">
                            To deliver a convenient, transparent, and customer-focused ordering experience.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             4. SERVICES SECTION WITH PRODUCT CATALOG
        =================================================================== -->
        <section class="section services-section" id="services">
            <div class="container">
                <!-- Section Header -->
                <div class="section-header">
                    <span class="section-tag">OUR SERVICES &amp; SYSTEM FEATURES</span>
                    <h2 class="section-title">Our Services</h2>
                    <p class="section-lead">
                        Discover our water products and convenient services designed to meet your everyday drinking water needs.
                    </p>
                </div>

                <!-- A. Available Water Products Catalog -->
                <div class="catalog-section-wrap">
                    <div class="catalog-header-bar">
                        <div class="catalog-title-group">
                            <span class="catalog-subtag">FRESH INVENTORY</span>
                            <h3 class="catalog-title">Available Water Products</h3>
                            <p class="catalog-description">All water products are purified through our station's multi-stage filtration process and sealed under hygienic conditions.</p>
                        </div>
                    </div>

                    <!-- Dynamic Products Grid from Database -->
                    <div class="products-catalog-grid">
                        @forelse($products as $product)
                            <div class="product-card {{ !$product->is_active ? 'is-unavailable' : '' }}" id="product-card-{{ $product->id }}">
                                <div class="product-card-media">
                                    <img 
                                        src="{{ $product->image_url ?: asset('images/aqua_clayne_gallon.png') }}" 
                                        alt="{{ $product->name }}" 
                                        class="product-card-img" 
                                        loading="lazy"
                                    >
                                    @if($product->is_active)
                                        <span class="product-status-badge available" aria-label="Available for order">
                                            <span class="status-dot"></span> Available
                                        </span>
                                    @else
                                        <span class="product-status-badge unavailable" aria-label="Currently unavailable">
                                            <span class="status-dot"></span> Unavailable
                                        </span>
                                    @endif
                                </div>

                                <div class="product-card-body">
                                    <div class="product-title-row">
                                        <h4 class="product-card-title">{{ $product->name }}</h4>
                                        <div class="product-card-price">
                                            <span class="price-symbol">₱</span>
                                            <span class="price-val">{{ number_format($product->price, 2) }}</span>
                                            <span class="price-denom">/ gallon</span>
                                        </div>
                                    </div>

                                    <p class="product-card-desc">{{ $product->description }}</p>

                                    <div class="product-card-footer">
                                        <button 
                                            type="button" 
                                            class="btn-product-details btn-view-details" 
                                            data-id="{{ $product->id }}" 
                                            data-name="{{ $product->name }}" 
                                            data-price="₱{{ number_format($product->price, 2) }} / gallon" 
                                            data-raw-price="{{ $product->price }}" 
                                            data-desc="{{ $product->description }}" 
                                            data-active="{{ $product->is_active ? '1' : '0' }}" 
                                            data-type="{{ $product->product_type ?? 'refill' }}" 
                                            data-img="{{ $product->image_url ?: asset('images/aqua_clayne_gallon.png') }}"
                                            aria-haspopup="dialog"
                                            aria-label="View details for {{ $product->name }}"
                                        >
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <circle cx="11" cy="11" r="8"></circle>
                                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                                <line x1="11" y1="8" x2="11" y2="14"></line>
                                                <line x1="8" y1="11" x2="14" y2="11"></line>
                                            </svg>
                                            <span>View Details</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-catalog-box">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <p>No water products are currently listed in the station inventory. Please check back later or contact customer support.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- View All Products Button & Guest Ordering Notice -->
                    <div class="catalog-actions-box">
                        <a href="{{ route('customer.order') }}" class="btn-view-all-products" id="btnViewAllProducts">
                            <span>View All Products</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                        <p class="catalog-guest-notice">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <span>Guests will be directed to sign in before adding products to cart or completing orders.</span>
                        </p>
                    </div>
                </div>

                <!-- B & C: Fulfillment Methods (Home Delivery & Station Pickup) -->
                <div class="fulfillment-methods-wrapper">
                    <div class="fulfillment-header-row">
                        <span class="fulfillment-section-tag">ORDER FULFILLMENT</span>
                        <h3 class="fulfillment-section-title">Convenient Delivery &amp; Pickup</h3>
                        <p class="fulfillment-section-lead">Choose your preferred fulfillment method when placing your order online.</p>
                    </div>

                    <div class="fulfillment-cards-grid">
                        <!-- Card B: Home Delivery -->
                        <div class="fulfillment-card home-delivery-card">
                            <div class="fulfillment-card-top">
                                <div class="fulfillment-badge-icon delivery-badge-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="1" y="3" width="15" height="13"></rect>
                                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                    </svg>
                                </div>
                                <div class="fulfillment-header-text">
                                    <h4 class="fulfillment-card-heading">Home Delivery</h4>
                                    <span class="fulfillment-price-chip">₱5.00 Delivery Fee</span>
                                </div>
                            </div>

                            <p class="fulfillment-card-desc">
                                Enjoy the convenience of having your drinking water delivered directly to your doorstep within our supported delivery areas.
                            </p>

                            <ul class="fulfillment-details-list">
                                <li class="fulfillment-detail-item">
                                    <div class="detail-bullet">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </div>
                                    <span><strong>Delivery fee:</strong> ₱5.00</span>
                                </li>
                                <li class="fulfillment-detail-item">
                                    <div class="detail-bullet">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </div>
                                    <span><strong>Available areas:</strong> Gatid, Duhat, and Bubukal</span>
                                </li>
                                <li class="fulfillment-detail-item">
                                    <div class="detail-bullet">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </div>
                                    <span>Complete delivery address required</span>
                                </li>
                                <li class="fulfillment-detail-item">
                                    <div class="detail-bullet">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </div>
                                    <span>Online order tracking available</span>
                                </li>
                            </ul>

                            <div class="fulfillment-cta-wrap">
                                <a href="{{ route('customer.order') }}" class="btn-fulfillment btn-delivery-action">
                                    <span>Order for Delivery</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                            </div>
                        </div>

                        <!-- Card C: Station Pickup -->
                        <div class="fulfillment-card station-pickup-card">
                            <div class="fulfillment-card-top">
                                <div class="fulfillment-badge-icon pickup-badge-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                    </svg>
                                </div>
                                <div class="fulfillment-header-text">
                                    <h4 class="fulfillment-card-heading">Station Pickup</h4>
                                    <span class="fulfillment-price-chip free-chip">No Delivery Fee (₱0.00)</span>
                                </div>
                            </div>

                            <p class="fulfillment-card-desc">
                                Prefer to collect your order personally? Place your order online and pick it up directly at our station.
                            </p>

                            <ul class="fulfillment-details-list">
                                <li class="fulfillment-detail-item">
                                    <div class="detail-bullet">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </div>
                                    <span><strong>No delivery fee</strong></span>
                                </li>
                                <li class="fulfillment-detail-item">
                                    <div class="detail-bullet">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </div>
                                    <span>Online order confirmation</span>
                                </li>
                                <li class="fulfillment-detail-item">
                                    <div class="detail-bullet">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </div>
                                    <span>Convenient order preparation and pickup</span>
                                </li>
                                <li class="fulfillment-detail-item">
                                    <div class="detail-bullet">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </div>
                                    <span>Pickup queue ready notification via SMS/App</span>
                                </li>
                            </ul>

                            <div class="fulfillment-cta-wrap">
                                <a href="{{ route('customer.order') }}" class="btn-fulfillment btn-pickup-action">
                                    <span>Order for Pickup</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documented Station Services Subsection (Maintains 8 Services & Tests) -->
                <div class="documented-services-wrapper" style="margin-top: 60px;">
                    <div class="capabilities-header-row">
                        <div>
                            <h3 class="capabilities-heading">Our Services and System Features</h3>
                            <p class="capabilities-sublead">Comprehensive station workflows and technical features supporting safe water refilling and dependable operations.</p>
                        </div>
                        <span class="capabilities-subtag">Station Services</span>
                    </div>

                    <!-- Services Grid (Documented Station Services) -->
                    <div class="services-grid">

                        <!-- Service 1: Purified Water Refilling -->
                        <div class="service-card">
                            <div class="service-icon-box">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                                </svg>
                            </div>
                            <h3 class="service-title">1. Purified Water Refilling</h3>
                            <p class="service-desc">
                                The primary station service of refilling customer-owned empty gallons with premium, multi-stage purified drinking water that undergoes rigorous filtration and hygienic container sealing.
                            </p>
                            <div class="service-footer-badge">Core Refilling Service</div>
                        </div>

                        <!-- Service 2: Customer Ordering -->
                        <div class="service-card">
                            <div class="service-icon-box">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                            </div>
                            <h3 class="service-title">2. Customer Ordering</h3>
                            <p class="service-desc">
                                Seamless mobile ordering allowing customers to browse water offerings, configure gallon quantities, save and manage multiple delivery addresses, and review past order histories.
                            </p>
                            <div class="service-footer-badge">Mobile Experience</div>
                        </div>

                        <!-- Service 3: Delivery Management -->
                        <div class="service-card">
                            <div class="service-icon-box">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="1" y="3" width="15" height="13"></rect>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                </svg>
                            </div>
                            <h3 class="service-title">3. Delivery Management</h3>
                            <p class="service-desc">
                                Administrators assign orders to verified station drivers and oversee dispatch runs, while drivers access their assigned batches, navigate routes, and update real-time delivery milestones.
                            </p>
                            <div class="service-footer-badge">Doorstep Coordination</div>
                        </div>

                        <!-- Service 4: Station Refill Operations -->
                        <div class="service-card">
                            <div class="service-icon-box">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <line x1="8" y1="6" x2="21" y2="6"></line>
                                    <line x1="8" y1="12" x2="21" y2="12"></line>
                                    <line x1="8" y1="18" x2="21" y2="18"></line>
                                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                </svg>
                            </div>
                            <h3 class="service-title">4. Station Refill Operations</h3>
                            <p class="service-desc">
                                Customer-owned empty gallons are received and physically verified at the station before staff begin refilling and prepare completed orders for delivery.
                            </p>
                            <div class="service-footer-badge">Verified Station Intake</div>
                        </div>

                        <!-- Service 5: Order and Delivery Updates -->
                        <div class="service-card">
                            <div class="service-icon-box">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"></path>
                                    <path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"></path>
                                    <circle cx="12" cy="12" r="2"></circle>
                                    <path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"></path>
                                    <path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"></path>
                                </svg>
                            </div>
                            <h3 class="service-title">5. Order and Delivery Updates</h3>
                            <p class="service-desc">
                                Order and delivery status changes are communicated to the relevant parts of the system through its real-time event mechanism, keeping mobile clients and web dashboards synchronized.
                            </p>
                            <div class="service-footer-badge">Live System Signals</div>
                        </div>

                        <!-- Service 6: Payments & Credit Management -->
                        <div class="service-card">
                            <div class="service-icon-box">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                                    <circle cx="12" cy="12" r="2"></circle>
                                    <path d="M6 12h.01M18 12h.01"></path>
                                </svg>
                            </div>
                            <h3 class="service-title" title="6. Payments & Credit Management">6. Payments &amp; Credit Management</h3>
                            <p class="service-desc">
                                Structured payment workflows supporting Cash on Delivery (COD) and station cash settlements, alongside pre-approved customer credit accounts with established limits and ledger histories.
                            </p>
                            <div class="service-footer-badge">Cash &amp; Credit Workflows</div>
                        </div>

                        <!-- Service 7: Gallon and QR Tracking -->
                        <div class="service-card">
                            <div class="service-icon-box">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                            </div>
                            <h3 class="service-title">7. Gallon and QR Tracking</h3>
                            <p class="service-desc">
                                The existing QR-code-based scanning workflow used to simulate RFID-related gallon tracking, accurately associating physical containers with customer orders during intake and delivery handoffs.
                            </p>
                            <div class="service-footer-badge">QR-Simulated Tracking</div>
                        </div>

                        <!-- Service 8: Notifications and Customer Support -->
                        <div class="service-card">
                            <div class="service-icon-box">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </div>
                            <h3 class="service-title" title="8. Notifications & Support">8. Notifications &amp; Support</h3>
                            <p class="service-desc">
                                Documented multi-channel assistance encompassing mobile push notifications, organized complaint filing with administrative investigation, and an interactive FAQ knowledge center.
                            </p>
                            <div class="service-footer-badge">Customer Support</div>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             5. HOW TO ORDER (4-STEP VISUAL GUIDE)
        =================================================================== -->
        <section class="section how-to-order-section" id="how-to-order">
            <div class="container">
                <div class="section-header">
                    <span class="section-tag">EASY ORDERING</span>
                    <h2 class="section-title">How to Order</h2>
                    <p class="section-lead">
                        Getting clean, purified drinking water for your home or business is simple and convenient.
                    </p>
                </div>

                <div class="steps-flow-container">
                    <div class="steps-flow-grid">
                        <!-- Step 1 -->
                        <div class="step-card">
                            <div class="step-card-header">
                                <span class="step-badge-number">01</span>
                                <div class="step-icon-wrap">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="step-title">Create an Account</h3>
                            <p class="step-desc">Register or log in to your customer account.</p>
                        </div>

                        <!-- Step 2 -->
                        <div class="step-card">
                            <div class="step-card-header">
                                <span class="step-badge-number">02</span>
                                <div class="step-icon-wrap">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="step-title">Choose Your Products</h3>
                            <p class="step-desc">Browse available products and select quantities.</p>
                        </div>

                        <!-- Step 3 -->
                        <div class="step-card">
                            <div class="step-card-header">
                                <span class="step-badge-number">03</span>
                                <div class="step-icon-wrap">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="1" y="3" width="15" height="13"></rect>
                                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="step-title">Place Your Order</h3>
                            <p class="step-desc">Enter your information and select delivery or pickup.</p>
                        </div>

                        <!-- Step 4 -->
                        <div class="step-card">
                            <div class="step-card-header">
                                <span class="step-badge-number">04</span>
                                <div class="step-icon-wrap">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="step-title">Receive Your Water</h3>
                            <p class="step-desc">Track your order and receive your water.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             6. WHY CHOOSE AQUA CLAYNE? (4 COMPACT FEATURE CARDS)
        =================================================================== -->
        <section class="section why-choose-section" id="why-choose">
            <div class="container">
                <div class="section-header">
                    <span class="section-tag">STATION ADVANTAGES</span>
                    <h2 class="section-title">Why Choose Aqua Clayne?</h2>
                    <p class="section-lead">
                        Aqua Clayne brings together quality filtration, modern digital convenience, and customer-first service.
                    </p>
                </div>

                <div class="features-quad-grid">
                    <!-- Feature Card 1 -->
                    <div class="feature-quad-card">
                        <div class="feature-quad-icon-box">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                            </svg>
                        </div>
                        <h3 class="feature-quad-title">Quality Water</h3>
                        <p class="feature-quad-desc">
                            Drinking water processed through the station's purification procedures.
                        </p>
                    </div>

                    <!-- Feature Card 2 -->
                    <div class="feature-quad-card">
                        <div class="feature-quad-icon-box">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                        </div>
                        <h3 class="feature-quad-title">Easy Ordering</h3>
                        <p class="feature-quad-desc">
                            Convenient online product selection and checkout.
                        </p>
                    </div>

                    <!-- Feature Card 3 -->
                    <div class="feature-quad-card">
                        <div class="feature-quad-icon-box">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="1" y="3" width="15" height="13"></rect>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                <circle cx="18.5" cy="18.5" r="2.5"></circle>
                            </svg>
                        </div>
                        <h3 class="feature-quad-title">Reliable Delivery</h3>
                        <p class="feature-quad-desc">
                            Home delivery within supported locations.
                        </p>
                    </div>

                    <!-- Feature Card 4 -->
                    <div class="feature-quad-card">
                        <div class="feature-quad-icon-box">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                        </div>
                        <h3 class="feature-quad-title">Order Tracking</h3>
                        <p class="feature-quad-desc">
                            Monitor order progress through your customer account.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             6. DOWNLOAD SECTION (MOBILE APPLICATION)
        =================================================================== -->
        <section class="section download-section" id="download">
            <div class="container">
                <div class="download-card-wrapper">
                    <div class="download-grid">
                        <!-- Left: Info & Features & Download CTA -->
                        <div class="download-content">
                            <div class="download-badge">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                                    <line x1="12" y1="18" x2="12.01" y2="18"></line>
                                </svg>
                                <span>AQUA CLAYNE MOBILE APP</span>
                            </div>

                            <h2 class="download-title">Take Aqua Clayne Anywhere</h2>

                            <p class="download-desc">
                                Enjoy convenient water ordering, delivery tracking, and account management directly from your mobile device.
                            </p>

                            <!-- Mobile App Feature List -->
                            <div class="download-features-list">
                                <div class="download-feature-item">
                                    <div class="feature-check-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </div>
                                    <span class="feature-label">Convenient Water Ordering</span>
                                </div>

                                <div class="download-feature-item">
                                    <div class="feature-check-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </div>
                                    <span class="feature-label">Real-Time Delivery Tracking</span>
                                </div>

                                <div class="download-feature-item">
                                    <div class="feature-check-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </div>
                                    <span class="feature-label">Order History</span>
                                </div>

                                <div class="download-feature-item">
                                    <div class="feature-check-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </div>
                                    <span class="feature-label">Account Management</span>
                                </div>

                                <div class="download-feature-item">
                                    <div class="feature-check-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </div>
                                    <span class="feature-label">Delivery Notifications</span>
                                </div>
                            </div>

                            <!-- Download Action Buttons -->
                            <div class="download-buttons-group">
                                <!-- Android Button (Direct APK Download) -->
                                <a href="{{ route('app.download') }}" class="btn-download btn-download-android" id="btnDownloadAndroid" title="Download Aqua Clayne for Android (Direct APK)">
                                    <svg class="download-os-icon" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.551 0 .9993.4482.9993.9993.0001.5511-.4483.9997-.9993.9997m-11.046 0c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.5511 0 .9993.4482.9993.9993 0 .5511-.4482.9997-.9993.9997m11.4045-6.02l1.9973-3.4592a.416.416 0 0 0-.1521-.5676.416.416 0 0 0-.5676.1521l-2.0223 3.503C15.5802 8.4069 13.8532 8.125 12 8.125s-3.5802.2819-5.1368.8247L4.8409 5.4467a.4161.4161 0 0 0-.5677-.1521.4157.4157 0 0 0-.1521.5676l1.9973 3.4592C2.6889 11.1867.3432 14.6589 0 18.761h24c-.3432-4.1021-2.6889-7.5743-6.1185-9.4396"/>
                                    </svg>
                                    <div class="download-btn-text">
                                        <span class="btn-subtext">DOWNLOAD APK</span>
                                        <span class="btn-maintext">Android Package</span>
                                    </div>
                                    <span class="download-status-tag available-tag">Direct Install</span>
                                </a>

                                <!-- iOS Button (Coming Soon State) -->
                                <div class="btn-download btn-download-ios btn-disabled" id="btnDownloadIos" title="Aqua Clayne for iOS - Coming Soon">
                                    <svg class="download-os-icon" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.37c.62-.75 1.04-1.8 1.01-2.87-.89.04-2 .6-2.64 1.34-.56.65-1.06 1.7-1 2.76 1 .08 2.01-.48 2.63-1.23z"/>
                                    </svg>
                                    <div class="download-btn-text">
                                        <span class="btn-subtext">APPLE APP STORE</span>
                                        <span class="btn-maintext">iOS Application</span>
                                    </div>
                                    <span class="download-status-tag coming-soon-tag">Coming Soon</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Mobile Device Preview Mockup -->
                        <div class="download-mockup-wrapper">
                            <div class="device-glow-backdrop" aria-hidden="true"></div>

                            <div class="smartphone-frame" aria-label="Aqua Clayne Mobile App Mockup">
                                <!-- Notch & Speaker -->
                                <div class="phone-notch">
                                    <span class="phone-speaker"></span>
                                    <span class="phone-camera"></span>
                                </div>

                                <!-- Screen Mockup Content -->
                                <div class="phone-screen">
                                    <!-- App Top Bar -->
                                    <div class="app-mockup-header">
                                        <div class="app-mockup-brand">
                                            <img src="{{ asset('images/aqua_clayne_logo.png') }}" alt="" width="22" height="22">
                                            <span>Aqua Clayne</span>
                                        </div>
                                        <span class="app-status-indicator">Station Online</span>
                                    </div>

                                    <!-- Refill Status Card -->
                                    <div class="app-order-status-card">
                                        <div class="app-card-top">
                                            <span class="app-card-title">Active Order #AC-8492</span>
                                            <span class="app-station-pill">Refilling</span>
                                        </div>
                                        <div class="app-progress-bar">
                                            <div class="app-progress-fill" style="width: 75%;"></div>
                                        </div>
                                        <div class="app-status-steps">
                                            <span class="step-active">Intake</span>
                                            <span class="step-active">Sanitized</span>
                                            <span class="step-active">Filled</span>
                                            <span>Delivered</span>
                                        </div>
                                    </div>

                                    <!-- Quick Order Item -->
                                    <div class="app-product-card">
                                        <div class="app-product-img">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#008FE0" stroke-width="2">
                                                <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                                            </svg>
                                        </div>
                                        <div class="app-product-details">
                                            <span class="app-p-name">5-Gallon Purified Refill</span>
                                            <span class="app-p-price">₱35.00 • Santa Cruz Delivery</span>
                                        </div>
                                        <a href="{{ route('customer.index') }}" class="app-p-btn">Order</a>
                                    </div>

                                    <!-- App Bottom Nav Bar -->
                                    <div class="app-mockup-dock">
                                        <div class="dock-item active">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                                            <span>Home</span>
                                        </div>
                                        <div class="dock-item">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                                            <span>Orders</span>
                                        </div>
                                        <div class="dock-item">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 14.93V17a1 1 0 0 1-2 0v-.07A7.003 7.003 0 0 1 5.07 11H5a1 1 0 0 1 0-2h.07A7.003 7.003 0 0 1 11 3.07V3a1 1 0 0 1 2 0v.07A7.003 7.003 0 0 1 18.93 9H19a1 1 0 0 1 0 2h-.07A7.003 7.003 0 0 1 13 16.93z"/></svg>
                                            <span>Tracking</span>
                                        </div>
                                        <div class="dock-item">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                            <span>Profile</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             7. CONTACT SECTION
        =================================================================== -->
        <section class="section contact-section" id="contact">
            <div class="container">
                <!-- Section Header -->
                <div class="section-header">
                    <span class="section-tag">CONNECT WITH US</span>
                    <h2 class="section-title">Contact Aqua Clayne</h2>
                    <p class="section-lead">
                        Have an inquiry about our purified water refilling services, delivery availability, or customer credit accounts? Reach out to our station directly or send us an inquiry below.
                    </p>
                </div>

                <div class="contact-grid">
                    <!-- Left: Verified Station Information & Google Maps Embed -->
                    <div class="contact-info-panel">
                        <div class="station-meta-badge">
                            <span class="pulse-indicator"></span>
                            <span>OFFICIAL WATER REFILLING STATION</span>
                        </div>

                        <h3 class="station-display-name">{{ $station['name'] ?? 'Aqua Clayne Water Refilling Station' }}</h3>
                        <p class="station-display-motto">
                            Providing dependable purified water refilling and dedicated doorstep delivery service across Santa Cruz, Laguna.
                        </p>

                        <div class="contact-details-list">
                            <!-- Address -->
                            <div class="contact-detail-item">
                                <div class="detail-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">STATION ADDRESS</span>
                                    <address class="detail-value not-italic">{{ $station['address'] ?? 'Aqua Clayne Water Refilling Station, 007 Batisan St., Brgy. Gatid, Sta Cruz Laguna' }}</address>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="contact-detail-item">
                                <div class="detail-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">TELEPHONE / MOBILE</span>
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $station['phone'] ?? '+639175550199') }}" class="detail-link">{{ $station['phone'] ?? '+63 917 555 0199' }}</a>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="contact-detail-item">
                                <div class="detail-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">OFFICIAL EMAIL</span>
                                    <a href="mailto:{{ $station['email'] ?? 'support@aquaclayne.ph' }}" class="detail-link">{{ $station['email'] ?? 'support@aquaclayne.ph' }}</a>
                                </div>
                            </div>

                            <!-- Operating Hours -->
                            <div class="contact-detail-item">
                                <div class="detail-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">OPERATING HOURS</span>
                                    <span class="detail-value">{{ $station['operating_hours'] ?? 'Mon - Sat: 7:00 AM - 7:00 PM | Sun: 8:00 AM - 5:00 PM' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Embedded Google Map -->
                        <div class="contact-map-container">
                            <span class="map-label">STATION LOCATION MAP</span>
                            <iframe
                                class="contact-map"
                                title="Aqua Clayne Location Map"
                                src="https://www.google.com/maps?q=007+Batisan+St.,+Brgy.+Gatid,+Santa+Cruz,+Laguna&output=embed"
                                width="100%"
                                height="200"
                                style="border:0; border-radius:12px; display:block;"
                                allowfullscreen=""
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>

                        <!-- Direct Operator Portal Callout -->
                        <div class="portal-callout">
                            <div class="callout-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </div>
                            <div class="callout-text">
                                <strong>Station Operator Access</strong>
                                <span>Authorized staff and administrators can manage station operations directly via the <a href="{{ route('admin.login') }}" class="callout-link">Admin Web Portal</a>.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Verified Contact Inquiry Form -->
                    <div class="contact-form-panel">
                        <div class="form-panel-header">
                            <h3 class="form-title">Send Us a Message</h3>
                            <p class="form-subtitle">Fill in the form below and our station customer care team will respond promptly.</p>
                        </div>

                        <!-- Server-side Success Feedback -->
                        @if (session('contact_success'))
                            <div class="alert alert-success" role="alert" id="serverSuccessAlert">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                <span>{{ session('contact_success') }}</span>
                            </div>
                        @endif

                        <!-- Server-side Validation Errors -->
                        @if ($errors->any())
                            <div class="alert alert-error" role="alert" id="serverErrorAlert">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Client-side Dynamic Feedback Container -->
                        <div class="alert alert-success" id="clientSuccessAlert" style="display: none;" role="status">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span id="clientSuccessMessage">Your message has been sent successfully!</span>
                        </div>

                        <div class="alert alert-error" id="clientErrorAlert" style="display: none;" role="alert">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span id="clientErrorMessage">Please review the form errors and try again.</span>
                        </div>

                        <!-- Form -->
                        <form action="{{ route('contact.submit') }}" method="POST" class="contact-form" id="contactForm" novalidate>
                            @csrf

                            <!-- Full Name -->
                            <div class="form-group">
                                <label for="contact_name" class="form-label">Full Name <span class="required-star">*</span></label>
                                <input
                                    type="text"
                                    id="contact_name"
                                    name="name"
                                    class="form-input {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                    placeholder="e.g. Juan dela Cruz"
                                    value="{{ old('name') }}"
                                    required
                                    minlength="2"
                                    maxlength="100"
                                    autocomplete="name"
                                >
                                <span class="field-error-message" id="error_name"></span>
                            </div>

                            <!-- Email Address -->
                            <div class="form-group">
                                <label for="contact_email" class="form-label">Email Address <span class="required-star">*</span></label>
                                <input
                                    type="email"
                                    id="contact_email"
                                    name="email"
                                    class="form-input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                    placeholder="e.g. juan@example.com"
                                    value="{{ old('email') }}"
                                    required
                                    maxlength="150"
                                    autocomplete="email"
                                >
                                <span class="field-error-message" id="error_email"></span>
                            </div>

                            <!-- Subject -->
                            <div class="form-group">
                                <label for="contact_subject" class="form-label">Subject <span class="required-star">*</span></label>
                                <input
                                    type="text"
                                    id="contact_subject"
                                    name="subject"
                                    class="form-input {{ $errors->has('subject') ? 'is-invalid' : '' }}"
                                    placeholder="e.g. Refill inquiry or delivery schedule"
                                    value="{{ old('subject') }}"
                                    required
                                    minlength="3"
                                    maxlength="200"
                                >
                                <span class="field-error-message" id="error_subject"></span>
                            </div>

                            <!-- Message -->
                            <div class="form-group">
                                <label for="contact_message" class="form-label">Message <span class="required-star">*</span></label>
                                <textarea
                                    id="contact_message"
                                    name="message"
                                    class="form-textarea {{ $errors->has('message') ? 'is-invalid' : '' }}"
                                    rows="5"
                                    placeholder="How can our station team assist you today?"
                                    required
                                    minlength="10"
                                    maxlength="2000"
                                >{{ old('message') }}</textarea>
                                <span class="field-error-message" id="error_message"></span>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-action-wrap">
                                <button type="submit" class="btn-contact-submit" id="contactSubmitBtn">
                                    <span class="btn-text">Submit Message</span>
                                    <svg class="btn-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: none;" aria-hidden="true">
                                        <line x1="12" y1="2" x2="12" y2="6"></line>
                                        <line x1="12" y1="18" x2="12" y2="22"></line>
                                        <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line>
                                        <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
                                        <line x1="2" y1="12" x2="6" y2="12"></line>
                                        <line x1="18" y1="12" x2="22" y2="12"></line>
                                        <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line>
                                        <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
                                    </svg>
                                    <svg class="btn-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- ===================================================================
         8. FOOTER
    =================================================================== -->
    <footer class="site-footer">
        <div class="container footer-container">
            <div class="footer-top">
                <!-- Brand Info -->
                <div class="footer-brand">
                    <div class="footer-logo-row">
                        <img src="{{ asset('images/aqua_clayne_logo.png') }}" alt="Aqua Clayne Logo" class="footer-logo" width="36" height="36">
                        <div class="footer-brand-headings">
                            <span class="footer-brand-title">AQUA CLAYNE</span>
                            <span class="footer-brand-subtitle">PURIFIED DRINKING WATER</span>
                        </div>
                    </div>
                    <p class="footer-tagline">
                        Aqua Clayne provides purified drinking water and a digital management system designed to support customer orders, refill operations, delivery coordination, and water station management.
                    </p>
                    <p class="footer-address">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <span>{{ $station['address'] ?? 'Aqua Clayne Water Refilling Station, 007 Batisan St., Brgy. Gatid, Sta Cruz Laguna' }}</span>
                    </p>
                </div>

                <!-- Quick Navigation Links -->
                <div class="footer-nav">
                    <span class="footer-heading">NAVIGATION</span>
                    <ul class="footer-links">
                        <li><a href="#home">HOME</a></li>
                        <li><a href="#about">ABOUT</a></li>
                        <li><a href="#services">SERVICES</a></li>
                        <li><a href="#download">DOWNLOAD</a></li>
                        <li><a href="#contact">CONTACT</a></li>
                        <li><a href="{{ route('customer.index') }}" class="footer-highlight-link">ORDER WATER REFILL</a></li>
                        <li><a href="{{ route('admin.login') }}" class="footer-login-link">LOGIN PORTAL</a></li>
                    </ul>
                </div>

                <!-- Station Hours & Operations -->
                <div class="footer-operating">
                    <span class="footer-heading">STATION HOURS</span>
                    <p class="footer-hours">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span>{{ $station['operating_hours'] ?? 'Mon - Sat: 7:00 AM - 7:00 PM | Sun: 8:00 AM - 5:00 PM' }}</span>
                    </p>
                    <p class="footer-area-note">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                        </svg>
                        <span>Serving Santa Cruz, Laguna &amp; surrounding barangays with doorstep gallon delivery.</span>
                    </p>
                    <div class="footer-socials">
                        <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="social-circle" aria-label="Facebook">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                        </a>
                        <a href="https://messenger.com" target="_blank" rel="noopener noreferrer" class="social-circle" aria-label="Messenger">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.03 2 11c0 2.87 1.5 5.42 3.86 7.02V22l3.81-2.09c.75.21 1.53.32 2.33.32 5.52 0 10-4.03 10-9s-4.48-9-10-9zm1.06 12.15l-2.58-2.75-5.02 2.75 5.52-5.85 2.65 2.75 4.95-2.75-5.52 5.85z"/></svg>
                        </a>
                        <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="social-circle" aria-label="Instagram">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; {{ date('Y') }} Aqua Clayne Water Refilling Station. All rights reserved.</p>
                <div class="footer-back-to-top">
                    <a href="#home" class="btn-back-to-top" aria-label="Back to top of page">
                        <span>Back to top</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="19" x2="12" y2="5"></line>
                            <polyline points="5 12 12 5 19 12"></polyline>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ===================================================================
         PRODUCT DETAILS MODAL DIALOG
    =================================================================== -->
    <div class="product-modal-backdrop" id="productModalBackdrop" aria-hidden="true">
        <div class="product-modal-dialog" id="productModalDialog" role="dialog" aria-modal="true" aria-labelledby="modalProductTitle" tabindex="-1">
            <!-- Modal Header -->
            <div class="product-modal-header">
                <div class="modal-badge-wrapper">
                    <span class="modal-category-tag" id="modalProductTypeTag">Water Product</span>
                    <span class="modal-status-badge available" id="modalProductStatus">Available</span>
                </div>
                <button type="button" class="btn-modal-close" id="btnModalClose" aria-label="Close product details dialog">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="product-modal-body">
                <div class="modal-product-layout">
                    <!-- Left: Product Image Showcase -->
                    <div class="modal-product-media">
                        <div class="modal-img-container">
                            <img src="{{ asset('images/aqua_clayne_gallon.png') }}" alt="" id="modalProductImage" class="modal-img">
                        </div>
                    </div>

                    <!-- Right: Product Information & Specs -->
                    <div class="modal-product-info">
                        <h3 class="modal-product-title" id="modalProductTitle">New Gallon with Water</h3>
                        <div class="modal-price-box">
                            <span class="modal-price-val" id="modalProductPrice">₱200.00 / gallon</span>
                        </div>

                        <p class="modal-product-desc" id="modalProductDesc">
                            A brand-new 5-gallon container filled with purified drinking water.
                        </p>

                        <!-- Standardized Product Specs -->
                        <div class="modal-specs-box">
                            <h4 class="specs-box-heading">Product Specifications</h4>
                            <ul class="modal-specs-list">
                                <li>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span><strong>Standard Volume:</strong> 5-Gallon (18.9 Liters)</span>
                                </li>
                                <li>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span><strong>Filtration Process:</strong> 16-Stage Reverse Osmosis &amp; UV</span>
                                </li>
                                <li>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span><strong>Container Quality:</strong> Food-Grade, BPA-Free Polycarbonate</span>
                                </li>
                                <li>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span><strong>Safety Certification:</strong> Microbiologically Tested &amp; Certified</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Guest Order Callout Note -->
                        <div class="modal-guest-callout" id="modalGuestCallout">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <span>Please log in to your account to add items to your cart and complete checkout.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Actions -->
            <div class="product-modal-footer">
                <button type="button" class="btn-modal-secondary" id="btnModalCancel">Close</button>
                <a href="{{ route('customer.order') }}" class="btn-modal-primary" id="modalOrderBtn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                    </svg>
                    <span id="modalOrderBtnText">Order Water Refill</span>
                </a>
            </div>
        </div>
    </div>

    <!-- JavaScript Bundle -->
    <script src="{{ asset('js/landing.js') }}?v={{ time() }}" defer></script>
</body>
</html>
