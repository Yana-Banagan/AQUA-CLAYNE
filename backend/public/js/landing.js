/**
 * AQUA CLAYNE - Public Landing Page Interactive Script
 * Handles sticky header effects, scroll spy for active navigation,
 * mobile drawer toggle, and AJAX contact form validation.
 */

document.addEventListener('DOMContentLoaded', () => {
    // =========================================================================
    // 1. STICKY HEADER SCROLL EFFECT
    // =========================================================================
    const siteHeader = document.getElementById('siteHeader');

    const handleHeaderScroll = () => {
        if (!siteHeader) return;
        if (window.scrollY > 30) {
            siteHeader.classList.add('is-scrolled');
        } else {
            siteHeader.classList.remove('is-scrolled');
        }
    };

    window.addEventListener('scroll', handleHeaderScroll, { passive: true });
    handleHeaderScroll();

    // =========================================================================
    // 2. MOBILE DRAWER TOGGLE & AUTO-CLOSE
    // =========================================================================
    const mobileToggle = document.getElementById('mobileToggle');
    const mobileDrawer = document.getElementById('mobileDrawer');

    const toggleMobileMenu = (forceState) => {
        if (!mobileToggle || !mobileDrawer) return;

        const isCurrentlyOpen = mobileDrawer.classList.contains('is-open');
        const willOpen = typeof forceState === 'boolean' ? forceState : !isCurrentlyOpen;

        if (willOpen) {
            mobileDrawer.classList.add('is-open');
            mobileDrawer.setAttribute('aria-hidden', 'false');
            mobileToggle.setAttribute('aria-expanded', 'true');
        } else {
            mobileDrawer.classList.remove('is-open');
            mobileDrawer.setAttribute('aria-hidden', 'true');
            mobileToggle.setAttribute('aria-expanded', 'false');
        }
    };

    if (mobileToggle) {
        mobileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMobileMenu();
        });
    }

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!mobileDrawer || !mobileDrawer.classList.contains('is-open')) return;
        if (!mobileDrawer.contains(e.target) && !mobileToggle.contains(e.target)) {
            toggleMobileMenu(false);
        }
    });

    // Close menu on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileDrawer && mobileDrawer.classList.contains('is-open')) {
            toggleMobileMenu(false);
        }
    });

    // =========================================================================
    // 3. NAVIGATION LINK HANDLING & ACTIVE SCROLL SPY
    // =========================================================================
    const navItems = document.querySelectorAll('.nav-item, .mobile-nav-item');
    const sections = document.querySelectorAll('section[id]');

    // Function to update active class across desktop & mobile navs
    const setActiveNav = (targetId) => {
        navItems.forEach((link) => {
            const navId = link.getAttribute('data-nav');
            if (navId === targetId) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    };

    // Smooth scroll and auto-close mobile drawer on link click
    navItems.forEach((link) => {
        link.addEventListener('click', (e) => {
            const href = link.getAttribute('href');
            if (href && href.startsWith('#')) {
                const targetElement = document.querySelector(href);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                    setActiveNav(href.substring(1));
                    toggleMobileMenu(false);

                    // Update URL history without jumping
                    if (history.pushState) {
                        history.pushState(null, '', href);
                    }
                }
            }
        });
    });

    // IntersectionObserver for reliable, performant scroll spy
    if ('IntersectionObserver' in window && sections.length > 0) {
        const observerOptions = {
            root: null,
            rootMargin: '-25% 0px -65% 0px',
            threshold: 0,
        };

        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const sectionId = entry.target.getAttribute('id');
                    if (sectionId) {
                        setActiveNav(sectionId);
                    }
                }
            });
        }, observerOptions);

        sections.forEach((section) => {
            sectionObserver.observe(section);
        });
    }

    // =========================================================================
    // 4. CONTACT FORM SUBMISSION WITH VALIDATION & FEEDBACK
    // =========================================================================
    const contactForm = document.getElementById('contactForm');
    const submitBtn = document.getElementById('contactSubmitBtn');
    const btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
    const btnSpinner = submitBtn ? submitBtn.querySelector('.btn-spinner') : null;
    const btnIcon = submitBtn ? submitBtn.querySelector('.btn-icon') : null;

    const clientSuccessAlert = document.getElementById('clientSuccessAlert');
    const clientSuccessMessage = document.getElementById('clientSuccessMessage');
    const clientErrorAlert = document.getElementById('clientErrorAlert');
    const clientErrorMessage = document.getElementById('clientErrorMessage');

    const clearFormErrors = () => {
        document.querySelectorAll('.field-error-message').forEach((el) => {
            el.textContent = '';
        });
        document.querySelectorAll('.form-input, .form-textarea').forEach((el) => {
            el.classList.remove('is-invalid');
        });
        if (clientErrorAlert) clientErrorAlert.style.display = 'none';
        if (clientSuccessAlert) clientSuccessAlert.style.display = 'none';

        const serverSuccess = document.getElementById('serverSuccessAlert');
        if (serverSuccess) serverSuccess.style.display = 'none';
        const serverError = document.getElementById('serverErrorAlert');
        if (serverError) serverError.style.display = 'none';
    };

    if (contactForm) {
        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearFormErrors();

            // Client-side quick check
            let hasError = false;
            const nameInput = document.getElementById('contact_name');
            const emailInput = document.getElementById('contact_email');
            const subjectInput = document.getElementById('contact_subject');
            const messageInput = document.getElementById('contact_message');

            if (!nameInput.value.trim() || nameInput.value.trim().length < 2) {
                nameInput.classList.add('is-invalid');
                const errSpan = document.getElementById('error_name');
                if (errSpan) errSpan.textContent = 'Please enter your full name (at least 2 characters).';
                hasError = true;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailInput.value.trim() || !emailRegex.test(emailInput.value.trim())) {
                emailInput.classList.add('is-invalid');
                const errSpan = document.getElementById('error_email');
                if (errSpan) errSpan.textContent = 'Please provide a valid email address.';
                hasError = true;
            }

            if (!subjectInput.value.trim() || subjectInput.value.trim().length < 3) {
                subjectInput.classList.add('is-invalid');
                const errSpan = document.getElementById('error_subject');
                if (errSpan) errSpan.textContent = 'Please specify a subject for your inquiry.';
                hasError = true;
            }

            if (!messageInput.value.trim() || messageInput.value.trim().length < 10) {
                messageInput.classList.add('is-invalid');
                const errSpan = document.getElementById('error_message');
                if (errSpan) errSpan.textContent = 'Your message must be at least 10 characters long.';
                hasError = true;
            }

            if (hasError) {
                if (clientErrorAlert) {
                    clientErrorMessage.textContent = 'Please resolve the highlighted field errors before submitting.';
                    clientErrorAlert.style.display = 'flex';
                }
                return;
            }

            // Set loading state
            if (submitBtn) {
                submitBtn.disabled = true;
                if (btnText) btnText.textContent = 'Submitting...';
                if (btnSpinner) btnSpinner.style.display = 'inline-block';
                if (btnIcon) btnIcon.style.display = 'none';
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData(contactForm);

            try {
                const response = await fetch(contactForm.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    if (clientSuccessAlert) {
                        clientSuccessMessage.textContent = data.message || 'Thank you! Your message has been received.';
                        clientSuccessAlert.style.display = 'flex';
                    }
                    contactForm.reset();
                } else if (response.status === 422 && data.errors) {
                    // Validation errors from Laravel
                    for (const [field, messages] of Object.entries(data.errors)) {
                        const inputEl = document.getElementById(`contact_${field}`);
                        if (inputEl) inputEl.classList.add('is-invalid');
                        const errorSpan = document.getElementById(`error_${field}`);
                        if (errorSpan) errorSpan.textContent = messages[0];
                    }
                    if (clientErrorAlert) {
                        clientErrorMessage.textContent = 'Please correct the indicated errors in the form.';
                        clientErrorAlert.style.display = 'flex';
                    }
                } else {
                    if (clientErrorAlert) {
                        clientErrorMessage.textContent = data.message || 'An error occurred while sending your message. Please try again.';
                        clientErrorAlert.style.display = 'flex';
                    }
                }
            } catch (err) {
                console.error('Contact form submission error:', err);
                if (clientErrorAlert) {
                    clientErrorMessage.textContent = 'Could not deliver message. Please check your network connection and try again.';
                    clientErrorAlert.style.display = 'flex';
                }
            } finally {
                // Restore button state
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (btnText) btnText.textContent = 'Submit Message';
                    if (btnSpinner) btnSpinner.style.display = 'none';
                    if (btnIcon) btnIcon.style.display = 'inline-block';
                }
            }
        });
    }

    // =========================================================================
    // 5. PRODUCT DETAILS MODAL DIALOG
    // =========================================================================
    const productModalBackdrop = document.getElementById('productModalBackdrop');
    const productModalDialog = document.getElementById('productModalDialog');
    const btnModalClose = document.getElementById('btnModalClose');
    const btnModalCancel = document.getElementById('btnModalCancel');
    const modalProductTitle = document.getElementById('modalProductTitle');
    const modalProductPrice = document.getElementById('modalProductPrice');
    const modalProductDesc = document.getElementById('modalProductDesc');
    const modalProductImage = document.getElementById('modalProductImage');
    const modalProductStatus = document.getElementById('modalProductStatus');
    const modalProductTypeTag = document.getElementById('modalProductTypeTag');
    const modalOrderBtn = document.getElementById('modalOrderBtn');
    const modalOrderBtnText = document.getElementById('modalOrderBtnText');

    let lastFocusedElement = null;

    const openProductModal = (data) => {
        if (!productModalBackdrop) return;

        lastFocusedElement = document.activeElement;

        // Populate modal data
        if (modalProductTitle) modalProductTitle.textContent = data.name || 'Aqua Clayne Water Product';
        if (modalProductPrice) modalProductPrice.textContent = data.price || '₱0.00 / gallon';
        if (modalProductDesc) modalProductDesc.textContent = data.desc || 'Purified drinking water processed to rigorous standards.';
        if (modalProductImage) {
            modalProductImage.src = data.img || '/images/aqua_clayne_gallon.png';
            modalProductImage.alt = data.name || 'Aqua Clayne Water Product';
        }

        // Category Tag
        if (modalProductTypeTag) {
            if (data.type === 'new_gallon') {
                modalProductTypeTag.textContent = 'New Container with Water';
            } else if (data.type === 'refill') {
                modalProductTypeTag.textContent = 'Refill Service';
            } else {
                modalProductTypeTag.textContent = 'Water Product';
            }
        }

        // Status badge & Order button state
        const isActive = data.active === '1' || data.active === true || data.active === 1;
        if (modalProductStatus) {
            if (isActive) {
                modalProductStatus.textContent = 'Available';
                modalProductStatus.className = 'modal-status-badge available';
            } else {
                modalProductStatus.textContent = 'Currently Unavailable';
                modalProductStatus.className = 'modal-status-badge unavailable';
            }
        }

        if (modalOrderBtn) {
            if (isActive) {
                modalOrderBtn.removeAttribute('aria-disabled');
                modalOrderBtn.classList.remove('btn-disabled');
                modalOrderBtn.style.pointerEvents = 'auto';
                modalOrderBtn.style.opacity = '1';
                if (modalOrderBtnText) modalOrderBtnText.textContent = 'Order Water Refill';
            } else {
                modalOrderBtn.setAttribute('aria-disabled', 'true');
                modalOrderBtn.classList.add('btn-disabled');
                modalOrderBtn.style.pointerEvents = 'none';
                modalOrderBtn.style.opacity = '0.55';
                if (modalOrderBtnText) modalOrderBtnText.textContent = 'Currently Unavailable';
            }
        }

        // Display modal
        productModalBackdrop.classList.add('is-open');
        productModalBackdrop.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        // Focus close button for accessibility
        if (btnModalClose) {
            setTimeout(() => btnModalClose.focus(), 60);
        }
    };

    const closeProductModal = () => {
        if (!productModalBackdrop) return;
        productModalBackdrop.classList.remove('is-open');
        productModalBackdrop.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');

        // Restore focus
        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
        }
    };

    // Attach click listeners to all "View Details" buttons
    document.querySelectorAll('.btn-view-details').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const data = {
                id: btn.getAttribute('data-id'),
                name: btn.getAttribute('data-name'),
                price: btn.getAttribute('data-price'),
                rawPrice: btn.getAttribute('data-raw-price'),
                desc: btn.getAttribute('data-desc'),
                active: btn.getAttribute('data-active'),
                type: btn.getAttribute('data-type'),
                img: btn.getAttribute('data-img'),
            };
            openProductModal(data);
        });
    });

    if (btnModalClose) {
        btnModalClose.addEventListener('click', closeProductModal);
    }

    if (btnModalCancel) {
        btnModalCancel.addEventListener('click', closeProductModal);
    }

    // Close when clicking on backdrop outside modal dialog
    if (productModalBackdrop) {
        productModalBackdrop.addEventListener('click', (e) => {
            if (e.target === productModalBackdrop) {
                closeProductModal();
            }
        });
    }

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && productModalBackdrop && productModalBackdrop.classList.contains('is-open')) {
            closeProductModal();
        }
    });
});

