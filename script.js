// ===== DOM CONTENT LOADED =====
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initNavbar();
    initHeroSlider();
    initMobileMenu();
    initSmoothScroll();
    initContactForm();
    initLiveSupport();
    initScrollAnimations();
    initMarketIndicators();
});

// ===== NAVBAR FUNCTIONALITY =====
function initNavbar() {
    const navbar = document.getElementById('navbar');
    
    // Add scroll effect to navbar
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// ===== HERO SLIDER =====
function initHeroSlider() {
    const slides = document.querySelectorAll('.slide');
    let currentSlide = 0;
    let slideInterval;
    let touchStartX = 0;
    let touchEndX = 0;

    // Function to show specific slide
    function showSlide(index) {
        // Remove active class from all slides
        slides.forEach(slide => slide.classList.remove('active'));
        
        // Add active class to current slide
        slides[index].classList.add('active');
        
        currentSlide = index;
    }

    // Auto-slide functionality
    function startAutoSlide() {
        slideInterval = setInterval(() => {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }, 5000); // Change slide every 5 seconds
    }

    // Stop auto-slide
    function stopAutoSlide() {
        clearInterval(slideInterval);
    }

    // Navigation functions
    function nextSlide() {
        currentSlide = (currentSlide + 1) % slides.length;
        showSlide(currentSlide);
        stopAutoSlide();
        setTimeout(startAutoSlide, 10000);
    }

    function prevSlide() {
        currentSlide = currentSlide === 0 ? slides.length - 1 : currentSlide - 1;
        showSlide(currentSlide);
        stopAutoSlide();
        setTimeout(startAutoSlide, 10000);
    }

    // Handle touch gestures
    function handleTouchStart(e) {
        touchStartX = e.touches[0].clientX;
    }

    function handleTouchMove(e) {
        touchEndX = e.touches[0].clientX;
    }

    function handleTouchEnd(e) {
        if (!touchStartX || !touchEndX) return;
        
        const touchDiff = touchStartX - touchEndX;
        const minSwipeDistance = 50;
        
        if (Math.abs(touchDiff) > minSwipeDistance) {
            if (touchDiff > 0) {
                // Swipe left - next slide
                nextSlide();
            } else {
                // Swipe right - previous slide
                prevSlide();
            }
        }
        
        // Reset touch values
        touchStartX = 0;
        touchEndX = 0;
    }

    // Pause on hover
    const sliderContainer = document.querySelector('.slider-container');
    sliderContainer.addEventListener('mouseenter', stopAutoSlide);
    sliderContainer.addEventListener('mouseleave', startAutoSlide);

    // Touch events for mobile swiping
    sliderContainer.addEventListener('touchstart', handleTouchStart, { passive: true });
    sliderContainer.addEventListener('touchmove', handleTouchMove, { passive: true });
    sliderContainer.addEventListener('touchend', handleTouchEnd, { passive: true });

    // Initialize auto-slide
    startAutoSlide();

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
            prevSlide();
        } else if (e.key === 'ArrowRight') {
            nextSlide();
        }
    });
}

// ===== MOBILE MENU =====
function initMobileMenu() {
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');

    // Toggle mobile menu
    hamburger.addEventListener('click', () => {
        navMenu.classList.toggle('active');
        hamburger.classList.toggle('active');
        
        // Animate hamburger
        const spans = hamburger.querySelectorAll('span');
        spans.forEach((span, index) => {
            if (hamburger.classList.contains('active')) {
                if (index === 0) span.style.transform = 'rotate(45deg) translate(5px, 5px)';
                if (index === 1) span.style.opacity = '0';
                if (index === 2) span.style.transform = 'rotate(-45deg) translate(7px, -6px)';
            } else {
                span.style.transform = 'none';
                span.style.opacity = '1';
            }
        });
    });

    // Close menu on link click
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            hamburger.classList.remove('active');
            
            const spans = hamburger.querySelectorAll('span');
            spans.forEach(span => {
                span.style.transform = 'none';
                span.style.opacity = '1';
            });
        });
    });

    // Close menu on outside click
    document.addEventListener('click', (e) => {
        if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
            navMenu.classList.remove('active');
            hamburger.classList.remove('active');
            
            const spans = hamburger.querySelectorAll('span');
            spans.forEach(span => {
                span.style.transform = 'none';
                span.style.opacity = '1';
            });
        }
    });
}

// ===== SMOOTH SCROLL =====
function initSmoothScroll() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            
            if (targetSection) {
                const offsetTop = targetSection.offsetTop - 80; // Account for navbar height
                
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// ===== CONTACT FORM =====
function initContactForm() {
    const form = document.getElementById('callbackForm');
    const inputs = form.querySelectorAll('input, select');
    
    // Form validation
    function validateForm() {
        let isValid = true;
        
        inputs.forEach(input => {
            if (input.hasAttribute('required') && !input.value.trim()) {
                input.style.borderColor = '#ff3f34';
                isValid = false;
            } else {
                input.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            }
            
            // Email validation
            if (input.type === 'email' && input.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(input.value)) {
                    input.style.borderColor = '#ff3f34';
                    isValid = false;
                }
            }
            
            // Phone validation
            if (input.type === 'tel' && input.value) {
                const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
                if (!phoneRegex.test(input.value)) {
                    input.style.borderColor = '#ff3f34';
                    isValid = false;
                }
            }
        });
        
        return isValid;
    }

    // Form submission
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        if (validateForm()) {
            const submitBtn = form.querySelector('.submit-btn');
            const originalText = submitBtn.textContent;
            
            // Show loading state
            submitBtn.textContent = 'Gönderiliyor...';
            submitBtn.disabled = true;
            
            // Simulate form submission
            setTimeout(() => {
                submitBtn.textContent = 'Başarıyla Gönderildi!';
                submitBtn.style.background = '#22c55e';
                
                // Reset form after 3 seconds
                setTimeout(() => {
                    form.reset();
                    submitBtn.textContent = originalText;
                    submitBtn.style.background = '#2563eb';
                    submitBtn.disabled = false;
                }, 3000);
            }, 2000);
        } else {
            // Show error message
            const submitBtn = form.querySelector('.submit-btn');
            const originalText = submitBtn.textContent;
            
            submitBtn.textContent = 'Lütfen Tüm Alanları Doldurun';
            submitBtn.style.background = '#ff3f34';
            
            setTimeout(() => {
                submitBtn.textContent = originalText;
                submitBtn.style.background = '#2563eb';
            }, 3000);
        }
    });

    // Real-time validation
    inputs.forEach(input => {
        input.addEventListener('blur', validateForm);
        input.addEventListener('focus', () => {
            input.style.borderColor = '#2563eb';
        });
    });
}

// ===== LIVE SUPPORT =====
function initLiveSupport() {
    const supportBtn = document.querySelector('.support-btn');
    
    supportBtn.addEventListener('click', () => {
        // Create support popup
        const popup = document.createElement('div');
        popup.className = 'support-popup';
        popup.innerHTML = `
            <div class="popup-content">
                <div class="popup-header">
                    <h3>Canlı Destek</h3>
                    <button class="close-popup">&times;</button>
                </div>
                <div class="popup-body">
                    <p>Merhaba! Size nasıl yardımcı olabiliriz?</p>
                    <div class="support-options">
                        <button class="support-option">Hesap Açma</button>
                        <button class="support-option">Platform Desteği</button>
                        <button class="support-option">Teknik Destek</button>
                        <button class="support-option">Genel Bilgi</button>
                    </div>
                    <div class="contact-methods">
                        <a href="tel:08501234567" class="contact-method">
                            <i class="fas fa-phone"></i>
                            0850 123 45 67
                        </a>
                        <a href="mailto:info@globaltradepro.com" class="contact-method">
                            <i class="fas fa-envelope"></i>
                            E-posta Gönder
                        </a>
                        <a href="#" class="contact-method">
                            <i class="fab fa-whatsapp"></i>
                            WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        `;
        
        // Add styles
        popup.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        
        const popupContent = popup.querySelector('.popup-content');
        popupContent.style.cssText = `
            background: #fff;
            border-radius: 15px;
            max-width: 400px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            transform: scale(0.8);
            transition: transform 0.3s ease;
        `;
        
        document.body.appendChild(popup);
        
        // Show popup
        setTimeout(() => {
            popup.style.opacity = '1';
            popupContent.style.transform = 'scale(1)';
        }, 10);
        
        // Close popup functionality
        const closeBtn = popup.querySelector('.close-popup');
        const closePopup = () => {
            popup.style.opacity = '0';
            popupContent.style.transform = 'scale(0.8)';
            setTimeout(() => {
                document.body.removeChild(popup);
            }, 300);
        };
        
        closeBtn.addEventListener('click', closePopup);
        popup.addEventListener('click', (e) => {
            if (e.target === popup) closePopup();
        });
        
        // Support option handlers
        const supportOptions = popup.querySelectorAll('.support-option');
        supportOptions.forEach(option => {
            option.addEventListener('click', () => {
                alert(`${option.textContent} konusunda size yardımcı olmak için temsilcimiz en kısa sürede iletişime geçecek.`);
                closePopup();
            });
        });
    });
}

// ===== SCROLL ANIMATIONS =====
function initScrollAnimations() {
    const animateElements = document.querySelectorAll('.service-card, .education-card, .indicator-item, .promo-card, .animate-on-scroll');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                entry.target.classList.add('animate');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    animateElements.forEach(element => {
        // Don't hide promo cards initially, they should be visible
        if (!element.classList.contains('promo-card')) {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';
            element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        }
        observer.observe(element);
    });
}



// ===== MARKET INDICATORS ANIMATION =====
function initMarketIndicators() {
    const indicators = document.querySelectorAll('.indicator-item');
    
    // Animate price changes
    function animatePriceChange() {
        indicators.forEach(indicator => {
            const priceElement = indicator.querySelector('.price');
            const changeElement = indicator.querySelector('.change');
            
            // Random price simulation
            if (Math.random() > 0.7) { // 30% chance of change
                const currentPrice = parseFloat(priceElement.textContent.replace(',', ''));
                const changePercent = (Math.random() - 0.5) * 0.02; // ±1% change
                const newPrice = currentPrice * (1 + changePercent);
                
                // Update price with animation
                priceElement.style.transform = 'scale(1.05)';
                priceElement.style.color = changePercent > 0 ? '#22c55e' : '#ef4444';
                
                setTimeout(() => {
                    priceElement.textContent = newPrice.toFixed(indicator.querySelector('.pair').textContent.includes('/') ? 4 : 2);
                    
                    setTimeout(() => {
                        priceElement.style.transform = 'scale(1)';
                        priceElement.style.color = '#fff';
                    }, 300);
                }, 150);
                
                // Update change indicator
                const changeValue = changePercent * currentPrice;
                changeElement.textContent = (changePercent > 0 ? '+' : '') + changeValue.toFixed(changePercent > 0 ? 4 : 4);
                changeElement.className = 'change ' + (changePercent > 0 ? 'positive' : 'negative');
            }
        });
    }
    
    // Run price animation every 3-8 seconds
    setInterval(animatePriceChange, 3000 + Math.random() * 5000);
}

// ===== UTILITY FUNCTIONS =====

// Smooth scroll to top
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Add scroll to top functionality
window.addEventListener('scroll', () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    if (scrollTop > 300) {
        if (!document.querySelector('.scroll-to-top')) {
            const scrollBtn = document.createElement('button');
            scrollBtn.className = 'scroll-to-top';
            scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
            scrollBtn.style.cssText = `
                position: fixed;
                bottom: 100px;
                right: 30px;
                background: #2563eb;
                color: #fff;
                border: none;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 1.2rem;
                z-index: 999;
                transition: all 0.3s ease;
                opacity: 0.8;
            `;
            
            scrollBtn.addEventListener('click', scrollToTop);
            scrollBtn.addEventListener('mouseenter', () => {
                scrollBtn.style.background = '#1d4ed8';
                scrollBtn.style.transform = 'translateY(-3px)';
            });
            scrollBtn.addEventListener('mouseleave', () => {
                scrollBtn.style.background = '#2563eb';
                scrollBtn.style.transform = 'translateY(0)';
            });
            
            document.body.appendChild(scrollBtn);
        }
    } else {
        const scrollBtn = document.querySelector('.scroll-to-top');
        if (scrollBtn) {
            document.body.removeChild(scrollBtn);
        }
    }
});

// ===== CTA BUTTON EFFECTS =====
document.addEventListener('DOMContentLoaded', () => {
    const ctaButtons = document.querySelectorAll('.btn-cta, .card-btn, .submit-btn');
    
    ctaButtons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.3);
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            `;
            
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = '50%';
            ripple.style.top = '50%';
            ripple.style.marginLeft = -size / 2 + 'px';
            ripple.style.marginTop = -size / 2 + 'px';
            
            button.style.position = 'relative';
            button.style.overflow = 'hidden';
            button.appendChild(ripple);
            
            setTimeout(() => {
                try {
                    button.removeChild(ripple);
                } catch (e) {
                    // Ripple already removed
                }
            }, 600);
        });
    });
    
    // Add ripple animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});

// ===== PERFORMANCE OPTIMIZATION =====
// Debounce function for scroll events
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Optimized scroll handler
const optimizedScrollHandler = debounce(() => {
    const navbar = document.getElementById('navbar');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
}, 10);

window.addEventListener('scroll', optimizedScrollHandler);

// ===== PROMO CAROUSEL FUNCTIONALITY =====
class PromoCarousel {
    constructor() {
        this.track = document.getElementById('promoTrack');
        this.prevBtn = document.getElementById('prevBtn');
        this.nextBtn = document.getElementById('nextBtn');
        this.dots = document.querySelectorAll('.dot');
        this.currentSlide = 0;
        this.totalSlides = 4;
        this.autoPlayInterval = null;
        this.isPlaying = true;
        
        this.init();
    }
    
    init() {
        // Navigation buttons
        this.prevBtn.addEventListener('click', () => this.previousSlide());
        this.nextBtn.addEventListener('click', () => this.nextSlide());
        
        // Dots navigation
        this.dots.forEach((dot, index) => {
            dot.addEventListener('click', () => this.goToSlide(index));
        });
        
        // Touch/swipe support
        this.addTouchSupport();
        
        // Auto-play
        this.startAutoPlay();
        
        // Pause on hover
        this.track.addEventListener('mouseenter', () => this.pauseAutoPlay());
        this.track.addEventListener('mouseleave', () => this.resumeAutoPlay());
    }
    
    goToSlide(slideIndex) {
        this.currentSlide = slideIndex;
        const translateX = -slideIndex * 25; // Each slide is 25% (100% / 4 slides)
        this.track.style.transform = `translateX(${translateX}%)`;
        this.updateDots();
    }
    
    nextSlide() {
        this.currentSlide = (this.currentSlide + 1) % this.totalSlides;
        this.goToSlide(this.currentSlide);
    }
    
    previousSlide() {
        this.currentSlide = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
        this.goToSlide(this.currentSlide);
    }
    
    updateDots() {
        this.dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === this.currentSlide);
        });
    }
    
    startAutoPlay() {
        this.autoPlayInterval = setInterval(() => {
            if (this.isPlaying) {
                this.nextSlide();
            }
        }, 5000); // 5 seconds
    }
    
    pauseAutoPlay() {
        this.isPlaying = false;
    }
    
    resumeAutoPlay() {
        this.isPlaying = true;
    }
    
    addTouchSupport() {
        let startX = 0;
        let startY = 0;
        let distX = 0;
        let distY = 0;
        
        this.track.addEventListener('touchstart', (e) => {
            const touch = e.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
        });
        
        this.track.addEventListener('touchmove', (e) => {
            e.preventDefault();
        });
        
        this.track.addEventListener('touchend', (e) => {
            const touch = e.changedTouches[0];
            distX = touch.clientX - startX;
            distY = touch.clientY - startY;
            
            // Check if horizontal swipe
            if (Math.abs(distX) > Math.abs(distY) && Math.abs(distX) > 50) {
                if (distX > 0) {
                    this.previousSlide();
                } else {
                    this.nextSlide();
                }
            }
        });
    }
}

// ===== SCROLL ANIMATIONS =====
class ScrollAnimations {
    constructor() {
        this.animateElements = document.querySelectorAll('.animate-on-scroll');
        this.init();
    }
    
    init() {
        // Initial check for elements in viewport
        this.checkElements();
        
        // Create optimized scroll handler
        let ticking = false;
        const scrollHandler = () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    this.checkElements();
                    ticking = false;
                });
                ticking = true;
            }
        };
        
        window.addEventListener('scroll', scrollHandler);
        window.addEventListener('resize', scrollHandler);
    }
    
    checkElements() {
        this.animateElements.forEach((element, index) => {
            if (this.isElementInViewport(element)) {
                // Add stagger delay for multiple elements
                element.style.setProperty('--delay', `${index * 0.1}s`);
                element.classList.add('animate', 'stagger-animation');
            }
        });
    }
    
    isElementInViewport(element) {
        const rect = element.getBoundingClientRect();
        const windowHeight = window.innerHeight || document.documentElement.clientHeight;
        
        return (
            rect.top >= 0 &&
            rect.top <= windowHeight * 0.8 // Trigger when 80% visible
        );
    }
}

// ===== ENHANCED CARD ANIMATIONS =====
function initCardAnimations() {
    const promoCards = document.querySelectorAll('.promo-card');
    
    promoCards.forEach(card => {
        // Tilt effect on mouse move
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 10;
            const rotateY = (centerX - x) / 10;
            
            card.style.transform = `
                translateY(-8px) 
                scale(1.02) 
                perspective(1000px) 
                rotateX(${rotateX}deg) 
                rotateY(${rotateY}deg)
            `;
        });
        
        // Reset on mouse leave
        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
        });
        
        // Click ripple effect
        card.addEventListener('click', (e) => {
            const ripple = document.createElement('span');
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.3);
                transform: scale(0);
                animation: ripple 0.6s linear;
                left: ${x - 10}px;
                top: ${y - 10}px;
                width: 20px;
                height: 20px;
                pointer-events: none;
            `;
            
            card.style.position = 'relative';
            card.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // Add ripple animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// ===== ENHANCED HERO SLIDER =====
class EnhancedHeroSlider {
    constructor() {
        this.slides = document.querySelectorAll('.slide');
        this.progressBar = document.getElementById('sliderProgress');
        this.currentSlide = 0;
        this.totalSlides = this.slides.length;
        this.autoPlayInterval = null;
        this.progressInterval = null;
        this.isPlaying = true;
        this.autoPlayDuration = 6000; // 6 seconds
        
        this.init();
    }
    
    init() {
        // Touch/swipe support
        this.addTouchSupport();
        
        // Pause on hover
        const slider = document.querySelector('.hero-slider');
        slider.addEventListener('mouseenter', () => this.pauseAutoPlay());
        slider.addEventListener('mouseleave', () => this.resumeAutoPlay());
        
        // Start auto-play
        this.startAutoPlay();
    }
    
    goToSlide(slideIndex) {
        // Remove active class from current slide
        this.slides[this.currentSlide].classList.remove('active');
        
        // Set new current slide
        this.currentSlide = slideIndex;
        
        // Add active class to new slide
        this.slides[this.currentSlide].classList.add('active');
        
        // Restart progress bar
        this.resetProgress();
    }
    
    nextSlide() {
        const nextIndex = (this.currentSlide + 1) % this.totalSlides;
        this.goToSlide(nextIndex);
    }
    
    previousSlide() {
        const prevIndex = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
        this.goToSlide(prevIndex);
    }
    
    startAutoPlay() {
        this.autoPlayInterval = setInterval(() => {
            if (this.isPlaying) {
                this.nextSlide();
            }
        }, this.autoPlayDuration);
        
        this.startProgress();
    }
    
    startProgress() {
        let progress = 0;
        const increment = 100 / (this.autoPlayDuration / 100);
        
        this.progressInterval = setInterval(() => {
            if (this.isPlaying) {
                progress += increment;
                this.progressBar.style.width = `${progress}%`;
                
                if (progress >= 100) {
                    progress = 0;
                }
            }
        }, 100);
    }
    
    resetProgress() {
        clearInterval(this.progressInterval);
        this.progressBar.style.width = '0%';
        this.startProgress();
    }
    
    pauseAutoPlay() {
        this.isPlaying = false;
    }
    
    resumeAutoPlay() {
        this.isPlaying = true;
    }
    
    addTouchSupport() {
        let startX = 0;
        let startY = 0;
        
        const slider = document.querySelector('.hero-slider');
        
        slider.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        });
        
        slider.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const distX = endX - startX;
            const distY = endY - startY;
            
            // Check if horizontal swipe and minimum distance
            if (Math.abs(distX) > Math.abs(distY) && Math.abs(distX) > 50) {
                if (distX > 0) {
                    this.previousSlide();
                } else {
                    this.nextSlide();
                }
            }
        });
    }
}

// ===== ENHANCED SCROLL ANIMATIONS =====
class EnhancedScrollAnimations {
    constructor() {
        this.animateElements = document.querySelectorAll('.animate-on-scroll');
        this.init();
    }
    
    init() {
        // Initial check
        this.checkElements();
        
        // Optimized scroll handler
        let ticking = false;
        const scrollHandler = () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    this.checkElements();
                    ticking = false;
                });
                ticking = true;
            }
        };
        
        window.addEventListener('scroll', scrollHandler);
        window.addEventListener('resize', scrollHandler);
    }
    
    checkElements() {
        this.animateElements.forEach((element) => {
            if (this.isElementInViewport(element) && !element.classList.contains('animate')) {
                // Get delay from data attribute
                const delay = element.getAttribute('data-delay') || '0';
                element.style.setProperty('--delay', `${delay}s`);
                
                // Add animate class with delay
                setTimeout(() => {
                    element.classList.add('animate');
                }, parseFloat(delay) * 1000);
            }
        });
    }
    
    isElementInViewport(element) {
        const rect = element.getBoundingClientRect();
        const windowHeight = window.innerHeight;
        
        return (
            rect.top < windowHeight * 0.8 &&
            rect.bottom > 0
        );
    }
}

// Initialize all enhanced functionality
document.addEventListener('DOMContentLoaded', () => {
    // Initialize enhanced hero slider
    new EnhancedHeroSlider();
    
    // Initialize enhanced scroll animations
    new EnhancedScrollAnimations();
    
    // Initialize enhanced card animations
    initCardAnimations();
}); 