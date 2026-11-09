/**
 * AQUA CLAYNE - Admin Login Interactive Logic
 * Handles accessible password visibility toggle, loading state on submit,
 * input error clearing, and administrator contact notice.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Form elements
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    const buttonText = loginButton ? loginButton.querySelector('.button-text') : null;
    const buttonSpinner = loginButton ? loginButton.querySelector('.button-spinner') : null;

    // Password visibility toggle elements
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const eyeIcon = document.getElementById('eyeIcon');
    const eyeOffIcon = document.getElementById('eyeOffIcon');

    // Input fields for error handling
    const identifierInput = document.getElementById('login_identifier') || document.getElementById('email');
    const alertBox = document.querySelector('.alert-error');

    // Forgot password elements
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const forgotNotice = document.getElementById('forgotNotice');
    const closeNoticeBtn = document.getElementById('closeNoticeBtn');

    /* --------------------------------------------------------------------------
       1. PASSWORD SHOW / HIDE TOGGLE WITH ACCESSIBILITY
       -------------------------------------------------------------------------- */
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', () => {
            const isCurrentlyPassword = passwordInput.type === 'password';

            if (isCurrentlyPassword) {
                passwordInput.type = 'text';
                togglePasswordBtn.setAttribute('aria-label', 'Hide password');
                if (eyeIcon && eyeOffIcon) {
                    eyeIcon.style.display = 'none';
                    eyeOffIcon.style.display = 'block';
                }
            } else {
                passwordInput.type = 'password';
                togglePasswordBtn.setAttribute('aria-label', 'Show password');
                if (eyeIcon && eyeOffIcon) {
                    eyeIcon.style.display = 'block';
                    eyeOffIcon.style.display = 'none';
                }
            }

            // Maintain focus on the password input field for convenience
            passwordInput.focus();
        });
    }

    /* --------------------------------------------------------------------------
       2. SUBMIT FORM LOADING STATE (REAL LARAVEL SESSION SUBMISSION)
       -------------------------------------------------------------------------- */
    if (loginForm && loginButton) {
        loginForm.addEventListener('submit', function (e) {
            // Trigger native HTML5 validation
            if (!loginForm.checkValidity()) {
                return; // Let native browser validation message show
            }

            // Disable submit button to prevent double submissions
            loginButton.disabled = true;
            loginButton.classList.add('loading');

            if (buttonText) {
                buttonText.textContent = 'Signing in...';
            }

            if (buttonSpinner) {
                buttonSpinner.classList.remove('hidden');
            }

            // Normal browser submission continues to Laravel backend
        });
    }

    /* --------------------------------------------------------------------------
       3. REMOVE VALIDATION ERROR STATE ON USER INPUT
       -------------------------------------------------------------------------- */
    const removeErrorHighlights = () => {
        const errorWrappers = document.querySelectorAll('.input-wrapper.has-error');
        errorWrappers.forEach(wrap => wrap.classList.remove('has-error'));

        if (alertBox) {
            alertBox.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            alertBox.style.opacity = '0.7';
        }
    };

    if (identifierInput) {
        identifierInput.addEventListener('input', removeErrorHighlights);
    }
    if (passwordInput) {
        passwordInput.addEventListener('input', removeErrorHighlights);
    }

    /* --------------------------------------------------------------------------
       4. FORGOT PASSWORD NOTICE HANDLING
       -------------------------------------------------------------------------- */
    if (forgotPasswordLink && forgotNotice) {
        forgotPasswordLink.addEventListener('click', (e) => {
            e.preventDefault();
            forgotNotice.classList.add('visible');
            forgotNotice.focus();
        });
    }

    if (closeNoticeBtn && forgotNotice) {
        closeNoticeBtn.addEventListener('click', () => {
            forgotNotice.classList.remove('visible');
        });
    }
});
