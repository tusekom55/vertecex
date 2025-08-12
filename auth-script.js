// ===== AUTH SCRIPT - GlobalTradePro Authentication ===== //

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeAuth();
});

function initializeAuth() {
    setupNavigation();
    setupPasswordToggles();
    setupFormValidation();
    setupPasswordStrength();
    setupSocialLogin();
    setupAnimations();
}

// ===== NAVIGATION SETUP ===== //
function setupNavigation() {
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('nav-menu');
    
    if (hamburger && navMenu) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            }
        });
    }

    // Navbar scroll effect
    const navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
}

// ===== PASSWORD TOGGLES ===== //
function setupPasswordToggles() {
    const passwordToggles = document.querySelectorAll('.password-toggle');
    
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
}

// ===== FORM VALIDATION ===== //
function setupFormValidation() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (loginForm) {
        setupLoginValidation(loginForm);
    }
    
    if (registerForm) {
        setupRegisterValidation(registerForm);
    }
}

function setupLoginValidation(form) {
    const emailInput = form.querySelector('#email');
    const passwordInput = form.querySelector('#password');
    const submitBtn = form.querySelector('#loginBtn');
    
    // Real-time validation
    emailInput.addEventListener('input', function() {
        validateEmail(this);
    });
    
    passwordInput.addEventListener('input', function() {
        validatePassword(this, false); // false for login (less strict)
    });
    
    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const isEmailValid = validateEmail(emailInput);
        const isPasswordValid = validatePassword(passwordInput, false);
        
        if (isEmailValid && isPasswordValid) {
            handleLogin(form, submitBtn);
        }
    });
}

function setupRegisterValidation(form) {
    const firstNameInput = form.querySelector('#firstName');
    const lastNameInput = form.querySelector('#lastName');
    const emailInput = form.querySelector('#email');
    const passwordInput = form.querySelector('#password');
    const confirmPasswordInput = form.querySelector('#confirmPassword');
    const termsCheckbox = form.querySelector('#terms');
    const submitBtn = form.querySelector('#registerBtn');
    
    // Real-time validation
    firstNameInput.addEventListener('input', function() {
        validateName(this);
    });
    
    lastNameInput.addEventListener('input', function() {
        validateName(this);
    });
    
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            if (this.value.trim()) { // Sadece değer varsa validate et
                validateEmail(this);
            } else {
                showSuccess(this, document.getElementById(this.id + 'Error'));
            }
        });
    }
    
    passwordInput.addEventListener('input', function() {
        validatePassword(this, true); // true for register (strict)
        if (confirmPasswordInput.value) {
            validateConfirmPassword(confirmPasswordInput, passwordInput);
        }
    });
    
    confirmPasswordInput.addEventListener('input', function() {
        validateConfirmPassword(this, passwordInput);
    });
    
    termsCheckbox.addEventListener('change', function() {
        validateTerms(this);
    });
    
    // Form submission - email artık zorunlu değil
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const isFirstNameValid = validateName(firstNameInput);
        const isLastNameValid = validateName(lastNameInput);
        const isEmailValid = !emailInput.value.trim() || validateEmail(emailInput); // Email boşsa veya geçerliyse OK
        const isPasswordValid = validatePassword(passwordInput, true);
        const isConfirmPasswordValid = validateConfirmPassword(confirmPasswordInput, passwordInput);
        const isTermsValid = validateTerms(termsCheckbox);
        
        if (isFirstNameValid && isLastNameValid && isEmailValid && 
            isPasswordValid && isConfirmPasswordValid && isTermsValid) {
            handleRegister(form, submitBtn);
        }
    });
}

// ===== VALIDATION FUNCTIONS ===== //
function validateName(input) {
    const value = input.value.trim();
    const errorElement = document.getElementById(input.id + 'Error');
    
    if (value.length < 2) {
        showError(input, errorElement, 'En az 2 karakter olmalıdır');
        return false;
    } else if (value.length > 50) {
        showError(input, errorElement, 'En fazla 50 karakter olabilir');
        return false;
    } else if (!/^[a-zA-ZğüşıöçĞÜŞİÖÇ\s]+$/.test(value)) {
        showError(input, errorElement, 'Sadece harf kullanabilirsiniz');
        return false;
    } else {
        showSuccess(input, errorElement);
        return true;
    }
}

function validateEmail(input) {
    const value = input.value.trim();
    const errorElement = document.getElementById(input.id + 'Error');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!value) {
        showError(input, errorElement, 'Email adresi zorunludur');
        return false;
    } else if (!emailRegex.test(value)) {
        showError(input, errorElement, 'Geçerli bir email adresi girin');
        return false;
    } else {
        showSuccess(input, errorElement);
        return true;
    }
}

function validatePhone(input) {
    const value = input.value.trim();
    const errorElement = document.getElementById(input.id + 'Error');
    const phoneRegex = /^(\+90|0)?[5][0-9]{9}$/;
    
    if (!value) {
        showError(input, errorElement, 'Telefon numarası zorunludur');
        return false;
    } else if (!phoneRegex.test(value.replace(/\s/g, ''))) {
        showError(input, errorElement, 'Geçerli bir telefon numarası girin');
        return false;
    } else {
        showSuccess(input, errorElement);
        return true;
    }
}

function validatePassword(input, isStrict) {
    const value = input.value;
    const errorElement = document.getElementById(input.id + 'Error');
    
    if (!value) {
        showError(input, errorElement, 'Şifre zorunludur');
        return false;
    } else if (value.length < 8) {
        showError(input, errorElement, 'Şifre en az 8 karakter olmalıdır');
        return false;
    } else {
        showSuccess(input, errorElement);
        return true;
    }
}

function validateConfirmPassword(input, passwordInput) {
    const value = input.value;
    const passwordValue = passwordInput.value;
    const errorElement = document.getElementById(input.id + 'Error');
    
    if (!value) {
        showError(input, errorElement, 'Şifre tekrarı zorunludur');
        return false;
    } else if (value !== passwordValue) {
        showError(input, errorElement, 'Şifreler eşleşmiyor');
        return false;
    } else {
        showSuccess(input, errorElement);
        return true;
    }
}

function validateTerms(input) {
    const errorElement = document.getElementById(input.id + 'Error');
    
    if (!input.checked) {
        showError(input, errorElement, 'Kullanım şartlarını kabul etmelisiniz');
        return false;
    } else {
        showSuccess(input, errorElement);
        return true;
    }
}

function showError(input, errorElement, message) {
    input.style.borderColor = '#ef4444';
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}

function showSuccess(input, errorElement) {
    input.style.borderColor = '#22c55e';
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }
}

// ===== PASSWORD STRENGTH ===== //
function setupPasswordStrength() {
    const passwordInput = document.querySelector('#password');
    const strengthElement = document.querySelector('#passwordStrength');
    
    if (passwordInput && strengthElement) {
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value, strengthElement);
        });
    }
}

function updatePasswordStrength(password, strengthElement) {
    const strengthBar = strengthElement.querySelector('.strength-fill');
    const strengthText = strengthElement.querySelector('.strength-text');
    
    let strength = 0;
    let text = 'Çok Zayıf';
    let className = 'strength-weak';
    
    // Length check
    if (password.length >= 6) strength += 1;
    if (password.length >= 8) strength += 1;
    
    // Character variety checks
    if (/[a-z]/.test(password)) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/\d/.test(password)) strength += 1;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 1;
    
    // Determine strength level
    if (strength <= 2) {
        text = 'Zayıf';
        className = 'strength-weak';
    } else if (strength <= 3) {
        text = 'Orta';
        className = 'strength-fair';
    } else if (strength <= 4) {
        text = 'İyi';
        className = 'strength-good';
    } else {
        text = 'Güçlü';
        className = 'strength-strong';
    }
    
    // Update UI
    strengthElement.className = 'password-strength ' + className;
    strengthText.textContent = text;
}

// ===== FORM HANDLERS ===== //
function handleLogin(form, submitBtn) {
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    // Show loading state
    btnText.style.display = 'none';
    btnLoading.style.display = 'flex';
    submitBtn.disabled = true;
    
    // Get form data
    const formData = new FormData(form);
    const loginData = {
        email: formData.get('email'),
        password: formData.get('password'),
        remember: formData.get('remember') === 'on'
    };
    
    // Simulate API call (replace with actual backend call)
    setTimeout(() => {
        console.log('Login attempt:', loginData);
        
        // For demo purposes - replace with real authentication
        if (loginData.email && loginData.password) {
            // Success
            showNotification('Giriş başarılı! Yönlendiriliyorsunuz...', 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.html'; // or wherever you want to redirect
            }, 1500);
        } else {
            // Error
            showNotification('Giriş başarısız. Lütfen bilgilerinizi kontrol edin.', 'error');
            resetButton(btnText, btnLoading, submitBtn);
        }
    }, 2000);
}

function handleRegister(form, submitBtn) {
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    // Show loading state
    btnText.style.display = 'none';
    btnLoading.style.display = 'flex';
    submitBtn.disabled = true;
    
    // Get form data
    const formData = new FormData(form);
    const registerData = {
        firstName: formData.get('firstName'),
        lastName: formData.get('lastName'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        password: formData.get('password'),
        newsletter: formData.get('newsletter') === 'on'
    };
    
    // Simulate API call (replace with actual backend call)
    setTimeout(() => {
        console.log('Registration attempt:', registerData);
        
        // For demo purposes - replace with real registration
        showNotification('Hesabınız oluşturuldu! Email adresinizi doğrulayın.', 'success');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 2000);
    }, 2500);
}

function resetButton(btnText, btnLoading, submitBtn) {
    btnText.style.display = 'block';
    btnLoading.style.display = 'none';
    submitBtn.disabled = false;
}

// ===== SOCIAL LOGIN ===== //
function setupSocialLogin() {
    const googleBtn = document.querySelector('.google-btn');
    const facebookBtn = document.querySelector('.facebook-btn');
    
    if (googleBtn) {
        googleBtn.addEventListener('click', function() {
            showNotification('Google girişi yakında aktif olacak...', 'info');
        });
    }
    
    if (facebookBtn) {
        facebookBtn.addEventListener('click', function() {
            showNotification('Facebook girişi yakında aktif olacak...', 'info');
        });
    }
}

// ===== ANIMATIONS ===== //
function setupAnimations() {
    // Smooth reveal animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe animated elements
    const animatedElements = document.querySelectorAll('.feature-item, .stat-item, .auth-form');
    animatedElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
    
    // Stagger animation for feature items
    const featureItems = document.querySelectorAll('.feature-item');
    featureItems.forEach((item, index) => {
        setTimeout(() => {
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 200);
    });
}

// ===== NOTIFICATION SYSTEM ===== //
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.auth-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `auth-notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 300px;
        max-width: 400px;
        transform: translateX(420px);
        transition: transform 0.3s ease;
    `;
    
    // Add to body
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Close button
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => removeNotification(notification));
    
    // Auto remove
    setTimeout(() => {
        removeNotification(notification);
    }, 5000);
}

function getNotificationIcon(type) {
    switch (type) {
        case 'success': return 'fa-check-circle';
        case 'error': return 'fa-exclamation-circle';
        case 'warning': return 'fa-exclamation-triangle';
        default: return 'fa-info-circle';
    }
}

function getNotificationColor(type) {
    switch (type) {
        case 'success': return '#22c55e';
        case 'error': return '#ef4444';
        case 'warning': return '#f59e0b';
        default: return '#2563eb';
    }
}

function removeNotification(notification) {
    notification.style.transform = 'translateX(420px)';
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

// ===== PHONE NUMBER FORMATTING ===== //
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.startsWith('90')) {
        value = value.substring(2);
    }
    
    if (value.startsWith('0')) {
        value = value.substring(1);
    }
    
    if (value.length >= 10) {
        value = value.substring(0, 10);
        const formatted = `+90 ${value.substring(0, 3)} ${value.substring(3, 6)} ${value.substring(6, 8)} ${value.substring(8, 10)}`;
        input.value = formatted;
    }
} 