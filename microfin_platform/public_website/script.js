document.addEventListener('DOMContentLoaded', () => {

    // --- Smooth Scrolling for Navigation Links ---
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if(targetId === '#') return;
            
            e.preventDefault();
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // --- Demo Form Date Validation & OTP Logic ---
    const demoForm = document.getElementById('demo-form');
    
    if (demoForm) {
        // OTP Elements
        const btnSendOtp = document.getElementById('btn-send-otp');
        const btnVerifyOtp = document.getElementById('btn-verify-otp');
        const otpGroup = document.getElementById('otp-group');
        const emailInput = document.getElementById('work_email');
        const otpInput = document.getElementById('otp_code');
        const otpMsg = document.getElementById('otp-status-msg');
        const btnFinalSubmit = document.getElementById('btn-final-submit');
        const formBlockNote = document.getElementById('form-block-note');
        const isOtpVerified = document.getElementById('is_otp_verified');

        if (btnSendOtp) {
            btnSendOtp.addEventListener('click', () => {
                const email = emailInput.value.trim();
                if (!email) {
                    alert("Please enter a valid business email first.");
                    return;
                }
                
                // Show loading state on button
                btnSendOtp.disabled = true;
                btnSendOtp.innerHTML = 'Sending...';

                const formData = new FormData();
                formData.append('action', 'send_otp');
                formData.append('email', email);

                fetch('api/api_demo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        otpGroup.style.display = 'block';
                        btnSendOtp.innerHTML = 'OTP Sent';
                        btnSendOtp.classList.remove('btn-outline');
                        btnSendOtp.style.backgroundColor = '#10b981';
                        btnSendOtp.style.color = '#fff';
                        btnSendOtp.style.borderColor = '#10b981';
                        otpMsg.style.color = '#10b981';
                        
                        let msg = data.message;
                        if(data.dev_otp) {
                            msg += ' (DEV MOCK CODE: ' + data.dev_otp + ')';
                            console.log("MOCK OTP IS:", data.dev_otp);
                        }
                        otpMsg.innerText = msg;
                    } else {
                        btnSendOtp.disabled = false;
                        btnSendOtp.innerHTML = 'Send OTP';
                        alert(data.message);
                    }
                })
                .catch(err => {
                    btnSendOtp.disabled = false;
                    btnSendOtp.innerHTML = 'Send OTP';
                    console.error(err);
                });
            });
        }

        if (btnVerifyOtp) {
            btnVerifyOtp.addEventListener('click', () => {
                const email = emailInput.value.trim();
                const code = otpInput.value.trim();
                
                if (code.length !== 6) {
                    otpMsg.style.color = '#ef4444';
                    otpMsg.innerText = 'Please enter a valid 6-digit OTP.';
                    return;
                }

                btnVerifyOtp.disabled = true;
                btnVerifyOtp.innerHTML = 'Verifying...';

                const formData = new FormData();
                formData.append('action', 'verify_otp');
                formData.append('email', email);
                formData.append('otp_code', code);

                fetch('api/api_demo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btnVerifyOtp.innerHTML = 'Verified';
                        btnVerifyOtp.style.backgroundColor = '#10b981';
                        btnVerifyOtp.style.borderColor = '#10b981';
                        otpMsg.style.color = '#10b981';
                        otpMsg.innerText = data.message;
                        
                        emailInput.readOnly = true;
                        otpInput.readOnly = true;
                        isOtpVerified.value = '1';

                        // Final Submission Unlocked
                        btnFinalSubmit.style.opacity = '1';
                        btnFinalSubmit.style.pointerEvents = 'auto';
                        formBlockNote.style.color = '#10b981';
                        formBlockNote.innerText = 'You may now submit your request.';
                    } else {
                        btnVerifyOtp.disabled = false;
                        btnVerifyOtp.innerHTML = 'Verify';
                        otpMsg.style.color = '#ef4444';
                        otpMsg.innerText = data.message;
                    }
                })
                .catch(err => {
                    btnVerifyOtp.disabled = false;
                    btnVerifyOtp.innerHTML = 'Verify';
                    console.error(err);
                });
            });
        }

        demoForm.addEventListener('submit', (e) => {
            if (isOtpVerified.value === '0') {
                e.preventDefault();
                alert("Please verify your email with the OTP before submitting.");
                return;
            }

            const submitBtn = demoForm.querySelector('button[type="submit"]');
            
            // Loading state
            submitBtn.innerHTML = '<span class="material-symbols-rounded" style="animation: spin 1s linear infinite; font-size: 18px; margin-right: 8px; vertical-align: middle;">sync</span> Submitting...';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.8';
            
            // Add keyframes if not exists
            if (!document.getElementById('spin-keyframes')) {
                const style = document.createElement('style');
                style.id = 'spin-keyframes';
                style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } }`;
                document.head.appendChild(style);
            }
        });
    }

    // --- Navbar Scroll Effect ---
    const navbar = document.querySelector('.navbar');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 10) {
            navbar.style.boxShadow = '0 4px 6px -1px rgba(0,0,0,0.1)';
        } else {
            navbar.style.boxShadow = 'none';
        }
    });

});
