/**
 * UtilitySign Plugin Main JavaScript
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main UtilitySign Class
    class UtilitySign {
        constructor() {
            this.config = window.utilitySignConfig || {};
            this.init();
        }

        init() {
            this.bindEvents();
            this.initComponents();
            this.initPerformanceOptimizations();
        }

        bindEvents() {
            // Document ready
            $(document).ready(() => {
                this.onDocumentReady();
            });

            // Window load
            $(window).on('load', () => {
                this.onWindowLoad();
            });

            // Window resize
            $(window).on('resize', this.debounce(() => {
                this.onWindowResize();
            }, 250));

            // Window scroll
            $(window).on('scroll', this.throttle(() => {
                this.onWindowScroll();
            }, 100));
        }

        initComponents() {
            // Initialize all components
            this.initLazyLoading();
            this.initFormValidation();
            this.initTooltips();
            this.initModals();
            this.initTabs();
            this.initAccordions();
            this.initCarousels();
        }

        initPerformanceOptimizations() {
            // Preload critical resources
            this.preloadCriticalResources();
            
            // Initialize intersection observer for lazy loading
            this.initIntersectionObserver();
            
            // Optimize images
            this.optimizeImages();
        }

        onDocumentReady() {
            // Initialize components that need DOM to be ready
            this.initDatePickers();
            this.initSelect2();
            this.initCharts();
        }

        onWindowLoad() {
            // Initialize components that need all resources to be loaded
            this.initAnimations();
            this.initCounters();
        }

        onWindowResize() {
            // Handle responsive changes
            this.handleResponsiveChanges();
        }

        onWindowScroll() {
            // Handle scroll-based features
            this.handleScrollFeatures();
        }

        // Lazy Loading
        initLazyLoading() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    });
                });

                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        }

        // Form Validation
        initFormValidation() {
            // Real-time validation
            $(document).on('blur', '.utilitysign .form-control', (e) => {
                this.validateField($(e.currentTarget));
            });

            // Form submission validation
            $(document).on('submit', '.utilitysign form', (e) => {
                if (!this.validateForm($(e.currentTarget))) {
                    e.preventDefault();
                }
            });
        }

        validateField($field) {
            const value = $field.val().trim();
            const fieldType = $field.attr('type');
            const isRequired = $field.prop('required');
            
            // Clear previous errors
            $field.removeClass('error');
            $field.siblings('.form-error').remove();
            
            // Required field validation
            if (isRequired && !value) {
                $field.addClass('error');
                $field.after(`<div class="form-error">This field is required</div>`);
                return false;
            }
            
            // Email validation
            if (fieldType === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    $field.addClass('error');
                    $field.after(`<div class="form-error">Please enter a valid email address</div>`);
                    return false;
                }
            }
            
            // Phone validation
            if (fieldType === 'tel' && value) {
                const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
                if (!phoneRegex.test(value.replace(/\s/g, ''))) {
                    $field.addClass('error');
                    $field.after(`<div class="form-error">Please enter a valid phone number</div>`);
                    return false;
                }
            }
            
            return true;
        }

        validateForm($form) {
            let isValid = true;
            
            // Clear previous errors
            $form.find('.form-error').remove();
            $form.find('.form-control').removeClass('error');
            
            // Validate all required fields
            $form.find('.form-control[required]').each(function() {
                if (!this.validateField($(this))) {
                    isValid = false;
                }
            });
            
            return isValid;
        }

        // Tooltips
        initTooltips() {
            // Initialize tooltips for elements with data-tooltip attribute
            $(document).on('mouseenter', '[data-tooltip]', function() {
                const $element = $(this);
                const tooltipText = $element.data('tooltip');
                
                if (!tooltipText) return;
                
                const $tooltip = $(`
                    <div class="utilitysign-tooltip">
                        ${tooltipText}
                    </div>
                `);
                
                $element.addClass('tooltip-active');
                $('body').append($tooltip);
                
                // Position tooltip
                const elementRect = this.getBoundingClientRect();
                const tooltipRect = $tooltip[0].getBoundingClientRect();
                
                $tooltip.css({
                    position: 'absolute',
                    top: elementRect.top - tooltipRect.height - 10,
                    left: elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2),
                    zIndex: 9999
                });
            });
            
            $(document).on('mouseleave', '[data-tooltip]', function() {
                $(this).removeClass('tooltip-active');
                $('.utilitysign-tooltip').remove();
            });
        }

        // Modals
        initModals() {
            // Open modal
            $(document).on('click', '[data-modal]', function(e) {
                e.preventDefault();
                const modalId = $(this).data('modal');
                $(`#${modalId}`).addClass('active');
                $('body').addClass('modal-open');
            });
            
            // Close modal
            $(document).on('click', '.utilitysign-modal .modal-close, .utilitysign-modal .modal-overlay', function() {
                $(this).closest('.utilitysign-modal').removeClass('active');
                $('body').removeClass('modal-open');
            });
            
            // Close modal with Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.utilitysign-modal.active').removeClass('active');
                    $('body').removeClass('modal-open');
                }
            });
        }

        // Tabs
        initTabs() {
            $(document).on('click', '.utilitysign-tabs .tab-nav a', function(e) {
                e.preventDefault();
                
                const $tab = $(this);
                const targetId = $tab.attr('href');
                const $tabContent = $(targetId);
                const $tabs = $tab.closest('.utilitysign-tabs');
                
                // Update active tab
                $tabs.find('.tab-nav a').removeClass('active');
                $tab.addClass('active');
                
                // Show target content
                $tabs.find('.tab-content').removeClass('active');
                $tabContent.addClass('active');
            });
        }

        // Accordions
        initAccordions() {
            $(document).on('click', '.utilitysign-accordion .accordion-header', function() {
                const $header = $(this);
                const $content = $header.next('.accordion-content');
                const $accordion = $header.closest('.utilitysign-accordion');
                
                // Toggle current item
                $content.slideToggle(300);
                $header.toggleClass('active');
                
                // Close other items if single open
                if ($accordion.hasClass('single-open')) {
                    $accordion.find('.accordion-header').not($header).removeClass('active');
                    $accordion.find('.accordion-content').not($content).slideUp(300);
                }
            });
        }

        // Carousels
        initCarousels() {
            $('.utilitysign-carousel').each(function() {
                const $carousel = $(this);
                const $slides = $carousel.find('.carousel-slide');
                const $indicators = $carousel.find('.carousel-indicators');
                const $prev = $carousel.find('.carousel-prev');
                const $next = $carousel.find('.carousel-next');
                
                let currentSlide = 0;
                const totalSlides = $slides.length;
                
                // Create indicators
                if ($indicators.length && totalSlides > 1) {
                    for (let i = 0; i < totalSlides; i++) {
                        $indicators.append(`<button class="indicator ${i === 0 ? 'active' : ''}" data-slide="${i}"></button>`);
                    }
                }
                
                // Show slide
                const showSlide = (index) => {
                    $slides.removeClass('active');
                    $slides.eq(index).addClass('active');
                    
                    $indicators.find('.indicator').removeClass('active');
                    $indicators.find(`[data-slide="${index}"]`).addClass('active');
                    
                    currentSlide = index;
                };
                
                // Navigation
                $prev.on('click', () => {
                    const prevSlide = currentSlide === 0 ? totalSlides - 1 : currentSlide - 1;
                    showSlide(prevSlide);
                });
                
                $next.on('click', () => {
                    const nextSlide = currentSlide === totalSlides - 1 ? 0 : currentSlide + 1;
                    showSlide(nextSlide);
                });
                
                // Indicator clicks
                $indicators.on('click', '.indicator', function() {
                    const slideIndex = parseInt($(this).data('slide'));
                    showSlide(slideIndex);
                });
                
                // Auto-play
                if ($carousel.data('autoplay')) {
                    setInterval(() => {
                        const nextSlide = currentSlide === totalSlides - 1 ? 0 : currentSlide + 1;
                        showSlide(nextSlide);
                    }, 5000);
                }
            });
        }

        // Date Pickers
        initDatePickers() {
            $('.utilitysign .datepicker').each(function() {
                const $input = $(this);
                const options = $input.data('datepicker-options') || {};
                
                // Initialize datepicker (assuming you have a datepicker library)
                if (typeof $.fn.datepicker !== 'undefined') {
                    $input.datepicker(options);
                }
            });
        }

        // Select2
        initSelect2() {
            $('.utilitysign .select2').each(function() {
                const $select = $(this);
                const options = $select.data('select2-options') || {};
                
                // Initialize Select2
                if (typeof $.fn.select2 !== 'undefined') {
                    $select.select2(options);
                }
            });
        }

        // Charts
        initCharts() {
            $('.utilitysign-chart').each(function() {
                const $chart = $(this);
                const chartType = $chart.data('chart-type');
                const chartData = $chart.data('chart-data');
                
                // Initialize chart (assuming you have a charting library)
                if (typeof Chart !== 'undefined' && chartData) {
                    new Chart($chart[0], {
                        type: chartType,
                        data: chartData
                    });
                }
            });
        }

        // Animations
        initAnimations() {
            // Animate elements on scroll
            if ('IntersectionObserver' in window) {
                const animationObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate-in');
                        }
                    });
                });

                document.querySelectorAll('.animate-on-scroll').forEach(el => {
                    animationObserver.observe(el);
                });
            }
        }

        // Counters
        initCounters() {
            $('.utilitysign-counter').each(function() {
                const $counter = $(this);
                const target = parseInt($counter.data('target'));
                const duration = parseInt($counter.data('duration')) || 2000;
                
                $({ count: 0 }).animate({ count: target }, {
                    duration: duration,
                    easing: 'swing',
                    step: function() {
                        $counter.text(Math.floor(this.count));
                    }
                });
            });
        }

        // Performance Optimizations
        preloadCriticalResources() {
            // Preload critical fonts
            const criticalFonts = [
                'https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap',
                'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap'
            ];
            
            criticalFonts.forEach(fontUrl => {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.href = fontUrl;
                link.as = 'style';
                document.head.appendChild(link);
            });
        }

        initIntersectionObserver() {
            if ('IntersectionObserver' in window) {
                // Lazy load images
                const imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });

                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        }

        optimizeImages() {
            // Add loading="lazy" to images that don't have it
            document.querySelectorAll('img:not([loading])').forEach(img => {
                img.loading = 'lazy';
            });
        }

        handleResponsiveChanges() {
            // Handle responsive changes
            const windowWidth = $(window).width();
            
            // Update carousel settings based on screen size
            $('.utilitysign-carousel').each(function() {
                const $carousel = $(this);
                const slidesToShow = windowWidth < 768 ? 1 : windowWidth < 1024 ? 2 : 3;
                $carousel.attr('data-slides-to-show', slidesToShow);
            });
        }

        handleScrollFeatures() {
            // Handle scroll-based features
            const scrollTop = $(window).scrollTop();
            
            // Sticky elements
            $('.utilitysign-sticky').each(function() {
                const $element = $(this);
                const offset = $element.data('sticky-offset') || 0;
                
                if (scrollTop > offset) {
                    $element.addClass('sticky-active');
                } else {
                    $element.removeClass('sticky-active');
                }
            });
        }

        // Utility Functions
        debounce(func, wait) {
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

        throttle(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }

        // Public API
        showMessage(message, type = 'info') {
            // Remove existing messages
            $('.utilitysign-message').remove();
            
            // Create new message
            const $message = $(`
                <div class="utilitysign-message alert alert-${type}">
                    ${message}
                </div>
            `);
            
            // Insert message
            $('body').prepend($message);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        showLoading(element) {
            $(element).addClass('loading');
        }

        hideLoading(element) {
            $(element).removeClass('loading');
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        window.UtilitySign = new UtilitySign();
    });

    // Expose to global scope
    window.UtilitySign = UtilitySign;

})(jQuery);
