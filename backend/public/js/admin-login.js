/**
 * AQUA CLAYNE - Unified Portal Login & Password Reset Interactive Logic
 * Handles accessible password visibility toggle, loading states, error clearing,
 * and the complete multi-step OTP-based password reset modal workflow.
 */
document.addEventListener('DOMContentLoaded', () => {
    // --------------------------------------------------------------------------
    // 1. LOGIN FORM ELEMENTS
    // --------------------------------------------------------------------------
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    const buttonText = loginButton ? loginButton.querySelector('.button-text') : null;
    const buttonSpinner = loginButton ? loginButton.querySelector('.button-spinner') : null;

    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const eyeIcon = document.getElementById('eyeIcon');
    const eyeOffIcon = document.getElementById('eyeOffIcon');

    const identifierInput = document.getElementById('login_identifier') || document.getElementById('email');
    const alertBox = document.querySelector('.alert-error');
    const loginSuccessAlert = document.getElementById('loginSuccessAlert');
    const loginSuccessMessage = document.getElementById('loginSuccessMessage');

    // Password show / hide toggle for login form
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            togglePasswordBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            if (eyeIcon && eyeOffIcon) {
                eyeIcon.style.display = isPassword ? 'none' : 'block';
                eyeOffIcon.style.display = isPassword ? 'block' : 'none';
            }
            passwordInput.focus();
        });
    }

    // Login submit button loading state
    if (loginForm && loginButton) {
        loginForm.addEventListener('submit', () => {
            if (!loginForm.checkValidity()) return;
            loginButton.disabled = true;
            loginButton.classList.add('loading');
            if (buttonText) buttonText.textContent = 'Signing in...';
            if (buttonSpinner) buttonSpinner.classList.remove('hidden');
        });
    }

    // Error highlight removal
    const removeErrorHighlights = () => {
        document.querySelectorAll('.input-wrapper.has-error').forEach(wrap => wrap.classList.remove('has-error'));
        if (alertBox) {
            alertBox.style.opacity = '0.7';
        }
    };
    if (identifierInput) identifierInput.addEventListener('input', removeErrorHighlights);
    if (passwordInput) passwordInput.addEventListener('input', removeErrorHighlights);

    // --------------------------------------------------------------------------
    // 2. FORGOT PASSWORD MULTI-STEP MODAL WORKFLOW
    // --------------------------------------------------------------------------
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const forgotModalBackdrop = document.getElementById('forgotModalBackdrop');
    const forgotModalCloseBtn = document.getElementById('forgotModalCloseBtn');
    const forgotModalAlert = document.getElementById('forgotModalAlert');

    // Step panels
    const stepPanels = {
        1: document.getElementById('forgotStep1'),
        2: document.getElementById('forgotStep2'),
        3: document.getElementById('forgotStep3'),
        4: document.getElementById('forgotStep4')
    };

    // Step 1 elements
    const forgotStep1Form = document.getElementById('forgotStep1Form');
    const forgotEmailInput = document.getElementById('forgotEmailInput');
    const btnSendOtp = document.getElementById('btnSendOtp');

    // Step 2 elements
    const forgotDisplayEmail = document.getElementById('forgotDisplayEmail');
    const forgotStep2Form = document.getElementById('forgotStep2Form');
    const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
    const otpTimerText = document.getElementById('otpTimerText');
    const otpCountdown = document.getElementById('otpCountdown');
    const btnResendOtp = document.getElementById('btnResendOtp');
    const btnVerifyOtp = document.getElementById('btnVerifyOtp');
    const btnBackToStep1 = document.getElementById('btnBackToStep1');

    // Step 3 elements
    const forgotStep3Form = document.getElementById('forgotStep3Form');
    const newPasswordInput = document.getElementById('newPasswordInput');
    const confirmPasswordInput = document.getElementById('confirmPasswordInput');
    const toggleNewPw = document.getElementById('toggleNewPw');
    const toggleConfirmPw = document.getElementById('toggleConfirmPw');
    const btnSavePassword = document.getElementById('btnSavePassword');

    // Step 4 elements
    const btnProceedToLogin = document.getElementById('btnProceedToLogin');

    // State variables
    let verifiedEmail = '';
    let currentResetToken = '';
    let countdownInterval = null;

    const getCsrfToken = () => {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    };

    // Show/hide alerts inside modal
    function showModalAlert(message, type = 'error') {
        if (!forgotModalAlert) return;
        forgotModalAlert.className = `forgot-alert alert-${type}`;
        forgotModalAlert.textContent = message;
        forgotModalAlert.style.display = 'block';
    }

    function hideModalAlert() {
        if (!forgotModalAlert) return;
        forgotModalAlert.style.display = 'none';
        forgotModalAlert.textContent = '';
    }

    // Switch visible step panel
    function setStep(stepNum) {
        hideModalAlert();
        Object.keys(stepPanels).forEach(key => {
            const panel = stepPanels[key];
            if (panel) {
                panel.style.display = String(key) === String(stepNum) ? 'block' : 'none';
            }
        });

        if (stepNum === 1) {
            setTimeout(() => forgotEmailInput?.focus(), 80);
        } else if (stepNum === 2) {
            otpBoxes.forEach(b => {
                b.value = '';
                b.classList.remove('has-value');
            });
            startCountdown(60);
            setTimeout(() => otpBoxes[0]?.focus(), 80);
        } else if (stepNum === 3) {
            if (newPasswordInput) newPasswordInput.value = '';
            if (confirmPasswordInput) confirmPasswordInput.value = '';
            setTimeout(() => newPasswordInput?.focus(), 80);
        }
    }

    // Modal open / close
    function openForgotModal() {
        if (!forgotModalBackdrop) return;
        const currentLoginVal = (identifierInput?.value || '').trim();
        if (forgotEmailInput) {
            forgotEmailInput.value = currentLoginVal;
        }

        forgotModalBackdrop.style.display = 'grid';
        forgotModalBackdrop.classList.add('is-active');
        forgotModalBackdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        setStep(1);
    }

    function closeForgotModal() {
        if (!forgotModalBackdrop) return;
        forgotModalBackdrop.classList.remove('is-active');
        forgotModalBackdrop.style.display = 'none';
        forgotModalBackdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', (e) => {
            e.preventDefault();
            openForgotModal();
        });
    }

    if (forgotModalCloseBtn) {
        forgotModalCloseBtn.addEventListener('click', closeForgotModal);
    }

    if (forgotModalBackdrop) {
        forgotModalBackdrop.addEventListener('click', (e) => {
            if (e.target === forgotModalBackdrop) {
                closeForgotModal();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && forgotModalBackdrop && forgotModalBackdrop.classList.contains('is-active')) {
            closeForgotModal();
        }
    });

    // --------------------------------------------------------------------------
    // 3. STEP 1: SEND OTP
    // --------------------------------------------------------------------------
    async function handleSendOtp() {
        const emailOrUser = (forgotEmailInput?.value || '').trim();
        if (!emailOrUser) {
            showModalAlert('Please enter your email address or username.', 'error');
            forgotEmailInput?.focus();
            return;
        }

        hideModalAlert();
        if (btnSendOtp) {
            btnSendOtp.disabled = true;
            btnSendOtp.querySelector('.btn-text').textContent = 'Sending Code...';
        }

        try {
            const response = await fetch('/auth/password/otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    email: emailOrUser,
                    digits: 6
                })
            });

            const data = await response.json();

            if (!response.ok) {
                const errorMsg = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Unable to send OTP. Please try again.');
                throw new Error(errorMsg);
            }

            verifiedEmail = data.data?.email || emailOrUser;
            if (forgotDisplayEmail) {
                forgotDisplayEmail.textContent = verifiedEmail;
            }

            setStep(2);
        } catch (err) {
            showModalAlert(err.message || 'Unable to send OTP. Please check your network and try again.', 'error');
        } finally {
            if (btnSendOtp) {
                btnSendOtp.disabled = false;
                btnSendOtp.querySelector('.btn-text').textContent = 'Send OTP';
            }
        }
    }

    if (btnSendOtp) btnSendOtp.addEventListener('click', handleSendOtp);
    if (forgotStep1Form) forgotStep1Form.addEventListener('submit', handleSendOtp);

    // --------------------------------------------------------------------------
    // 4. STEP 2: 6-DIGIT OTP BOXES & 1-MINUTE TIMER
    // --------------------------------------------------------------------------
    function startCountdown(seconds = 60) {
        if (countdownInterval) clearInterval(countdownInterval);
        let remaining = seconds;

        if (otpTimerText) otpTimerText.style.display = 'inline';
        if (btnResendOtp) btnResendOtp.style.display = 'none';

        const updateTimer = () => {
            const mins = String(Math.floor(remaining / 60)).padStart(2, '0');
            const secs = String(remaining % 60).padStart(2, '0');
            if (otpCountdown) otpCountdown.textContent = `${mins}:${secs}`;
        };
        updateTimer();

        countdownInterval = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(countdownInterval);
                countdownInterval = null;
                if (otpTimerText) otpTimerText.style.display = 'none';
                if (btnResendOtp) btnResendOtp.style.display = 'inline-block';
            } else {
                updateTimer();
            }
        }, 1000);
    }

    // OTP Box input handling
    otpBoxes.forEach((box, index) => {
        box.addEventListener('input', (e) => {
            const clean = box.value.replace(/[^0-9]/g, '');
            box.value = clean ? clean[0] : '';
            box.classList.toggle('has-value', Boolean(box.value));

            if (box.value && index < otpBoxes.length - 1) {
                otpBoxes[index + 1].focus();
                otpBoxes[index + 1].select();
            }

            const currentCode = otpBoxes.map(b => b.value).join('');
            if (currentCode.length === 6) {
                // Auto trigger verification when all 6 digits are typed
                handleVerifyOtp();
            }
        });

        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace') {
                if (!box.value && index > 0) {
                    otpBoxes[index - 1].focus();
                    otpBoxes[index - 1].value = '';
                    otpBoxes[index - 1].classList.remove('has-value');
                }
            } else if (e.key === 'ArrowLeft' && index > 0) {
                otpBoxes[index - 1].focus();
            } else if (e.key === 'ArrowRight' && index < otpBoxes.length - 1) {
                otpBoxes[index + 1].focus();
            }
        });

        box.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text') || '';
            const digits = pasted.replace(/[^0-9]/g, '').slice(0, 6);
            if (!digits) return;

            digits.split('').forEach((d, i) => {
                if (otpBoxes[i]) {
                    otpBoxes[i].value = d;
                    otpBoxes[i].classList.add('has-value');
                }
            });

            const nextIndex = Math.min(digits.length, otpBoxes.length - 1);
            otpBoxes[nextIndex].focus();

            if (digits.length === 6) {
                handleVerifyOtp();
            }
        });
    });

    // Resend OTP
    if (btnResendOtp) {
        btnResendOtp.addEventListener('click', async () => {
            btnResendOtp.disabled = true;
            btnResendOtp.textContent = 'Resending...';
            hideModalAlert();

            try {
                const response = await fetch('/auth/password/otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ email: verifiedEmail, digits: 6 })
                });

                const data = await response.json();
                if (!response.ok) {
                    const errorMsg = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Could not resend OTP.');
                    throw new Error(errorMsg);
                }

                showModalAlert('A new 6-digit verification code has been sent to your email.', 'success');
                otpBoxes.forEach(b => {
                    b.value = '';
                    b.classList.remove('has-value');
                });
                startCountdown(60);
                setTimeout(() => otpBoxes[0]?.focus(), 100);
            } catch (err) {
                showModalAlert(err.message, 'error');
            } finally {
                btnResendOtp.disabled = false;
                btnResendOtp.innerHTML = 'Didn\'t receive code? <strong>Resend OTP</strong>';
            }
        });
    }

    // Back to Step 1
    if (btnBackToStep1) {
        btnBackToStep1.addEventListener('click', () => {
            setStep(1);
        });
    }

    // Verify OTP
    async function handleVerifyOtp() {
        const fullCode = otpBoxes.map(b => b.value.trim()).join('');
        if (fullCode.length !== 6) {
            showModalAlert('Please enter the complete 6-digit verification code.', 'error');
            const emptyBox = otpBoxes.find(b => !b.value);
            if (emptyBox) emptyBox.focus();
            return;
        }

        hideModalAlert();
        if (btnVerifyOtp) {
            btnVerifyOtp.disabled = true;
            btnVerifyOtp.querySelector('.btn-text').textContent = 'Verifying Code...';
        }

        try {
            const response = await fetch('/auth/password/verify-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    email: verifiedEmail,
                    otp: fullCode
                })
            });

            const data = await response.json();
            if (!response.ok) {
                const errorMsg = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Verification failed.');
                throw new Error(errorMsg);
            }

            currentResetToken = data.data?.reset_token || '';
            setStep(3);
        } catch (err) {
            showModalAlert(err.message || 'Invalid or expired verification code.', 'error');
            otpBoxes.forEach(b => {
                b.value = '';
                b.classList.remove('has-value');
            });
            otpBoxes[0]?.focus();
        } finally {
            if (btnVerifyOtp) {
                btnVerifyOtp.disabled = false;
                btnVerifyOtp.querySelector('.btn-text').textContent = 'Verify Code';
            }
        }
    }

    if (btnVerifyOtp) btnVerifyOtp.addEventListener('click', handleVerifyOtp);
    if (forgotStep2Form) forgotStep2Form.addEventListener('submit', handleVerifyOtp);

    // --------------------------------------------------------------------------
    // 5. STEP 3: CREATE NEW PASSWORD
    // --------------------------------------------------------------------------
    const setupPasswordToggle = (btn, input) => {
        if (!btn || !input) return;
        btn.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const eyeOn = btn.querySelector('.eye-on');
            const eyeOff = btn.querySelector('.eye-off');
            if (eyeOn && eyeOff) {
                eyeOn.style.display = isPassword ? 'none' : 'block';
                eyeOff.style.display = isPassword ? 'block' : 'none';
            }
            input.focus();
        });
    };
    setupPasswordToggle(toggleNewPw, newPasswordInput);
    setupPasswordToggle(toggleConfirmPw, confirmPasswordInput);

    async function handleSaveNewPassword() {
        const password = (newPasswordInput?.value || '').trim();
        const confirmation = (confirmPasswordInput?.value || '').trim();

        if (!password) {
            showModalAlert('Please enter your new password.', 'error');
            newPasswordInput?.focus();
            return;
        }

        if (password.length < 8) {
            showModalAlert('Password must be at least 8 characters long.', 'error');
            newPasswordInput?.focus();
            return;
        }

        if (password !== confirmation) {
            showModalAlert('Password confirmation does not match.', 'error');
            confirmPasswordInput?.focus();
            return;
        }

        hideModalAlert();
        if (btnSavePassword) {
            btnSavePassword.disabled = true;
            btnSavePassword.querySelector('.btn-text').textContent = 'Saving New Password...';
        }

        try {
            const response = await fetch('/auth/password/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    email: verifiedEmail,
                    reset_token: currentResetToken,
                    password: password,
                    password_confirmation: confirmation
                })
            });

            const data = await response.json();
            if (!response.ok) {
                const errorMsg = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Could not reset password.');
                throw new Error(errorMsg);
            }

            setStep(4);
        } catch (err) {
            showModalAlert(err.message || 'Could not save new password. Please try again.', 'error');
        } finally {
            if (btnSavePassword) {
                btnSavePassword.disabled = false;
                btnSavePassword.querySelector('.btn-text').textContent = 'Save New Password';
            }
        }
    }

    if (btnSavePassword) btnSavePassword.addEventListener('click', handleSaveNewPassword);
    if (forgotStep3Form) forgotStep3Form.addEventListener('submit', handleSaveNewPassword);

    // --------------------------------------------------------------------------
    // 6. STEP 4: PROCEED TO LOGIN
    // --------------------------------------------------------------------------
    if (btnProceedToLogin) {
        btnProceedToLogin.addEventListener('click', () => {
            closeForgotModal();

            // Populate login input with updated email
            if (identifierInput && verifiedEmail) {
                identifierInput.value = verifiedEmail;
            }
            if (passwordInput) {
                passwordInput.value = '';
                passwordInput.focus();
            }

            // Display success notification above the login card
            if (loginSuccessAlert) {
                if (loginSuccessMessage) {
                    loginSuccessMessage.textContent = 'Password reset successful! Please log in with your new password.';
                }
                loginSuccessAlert.style.display = 'flex';
                loginSuccessAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
});
