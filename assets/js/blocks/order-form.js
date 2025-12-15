/**
 * Order Form Block Frontend JavaScript
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Order Form Block Class
    class OrderFormBlock {
        constructor() {
            this.formData = {};
            this.currentStep = 1;
            this.totalSteps = 4;
            this.init();
        }

        init() {
            this.bindEvents();
            this.initFormValidation();
            this.initStepNavigation();
        }

        bindEvents() {
            // Form submission
            $(document).on('submit', '.utilitysign-order-form form', this.handleFormSubmit.bind(this));
            
            // Supplier selection
            $(document).on('change', '.utilitysign-order-form .supplier-select', this.handleSupplierChange.bind(this));
            
            // Product selection
            $(document).on('change', '.utilitysign-order-form .product-select', this.handleProductChange.bind(this));
            
            // Quantity changes
            $(document).on('click', '.utilitysign-order-form .quantity-btn', this.handleQuantityChange.bind(this));
            $(document).on('input', '.utilitysign-order-form .quantity-input', this.handleQuantityInput.bind(this));
            
            // Step navigation
            $(document).on('click', '.utilitysign-order-form .step-btn', this.handleStepNavigation.bind(this));
            
            // Form field changes
            $(document).on('change input', '.utilitysign-order-form .form-control', this.handleFieldChange.bind(this));
        }

        handleFormSubmit(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $submitBtn = $form.find('.submit-order-btn');
            
            // Validate form
            if (!this.validateForm($form)) {
                return;
            }
            
            // Disable submit button and show loading
            $submitBtn.prop('disabled', true).text('Processing...');
            $form.addClass('form-loading');
            
            // Collect form data
            const formData = this.collectFormData($form);
            
            // Submit order
            $.ajax({
                url: utilitySignOrderForm.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'utilitysign_submit_order',
                    nonce: utilitySignOrderForm.nonce,
                    ...formData
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccessMessage(response.data.message);
                        this.resetForm($form);
                    } else {
                        this.showErrorMessage(response.data.message || 'Failed to submit order');
                    }
                },
                error: (xhr, status, error) => {
                    this.showErrorMessage('An error occurred. Please try again.');
                },
                complete: () => {
                    // Re-enable submit button
                    $submitBtn.prop('disabled', false).text('Submit Order');
                    $form.removeClass('form-loading');
                }
            });
        }

        handleSupplierChange(e) {
            const supplierId = $(e.currentTarget).val();
            const $form = $(e.currentTarget).closest('.utilitysign-order-form');
            
            if (supplierId) {
                // Load products for selected supplier
                this.loadSupplierProducts($form, supplierId);
                
                // Update form data
                this.formData.supplier_id = supplierId;
            } else {
                // Clear product selection
                this.clearProductSelection($form);
            }
        }

        handleProductChange(e) {
            const productId = $(e.currentTarget).val();
            const $form = $(e.currentTarget).closest('.utilitysign-order-form');
            
            if (productId) {
                // Load product details
                this.loadProductDetails($form, productId);
                
                // Update form data
                this.formData.product_id = productId;
            } else {
                // Clear product details
                this.clearProductDetails($form);
            }
        }

        handleQuantityChange(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const $input = $btn.siblings('.quantity-input');
            const currentValue = parseInt($input.val()) || 1;
            const newValue = $btn.hasClass('quantity-plus') ? currentValue + 1 : Math.max(1, currentValue - 1);
            
            $input.val(newValue);
            this.updateQuantityDisplay($input);
        }

        handleQuantityInput(e) {
            const $input = $(e.currentTarget);
            let value = parseInt($input.val()) || 1;
            
            // Ensure minimum value of 1
            if (value < 1) {
                value = 1;
                $input.val(1);
            }
            
            this.updateQuantityDisplay($input);
        }

        handleStepNavigation(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const direction = $btn.data('direction');
            const $form = $btn.closest('.utilitysign-order-form');
            
            if (direction === 'next') {
                if (this.validateCurrentStep($form)) {
                    this.nextStep($form);
                }
            } else if (direction === 'prev') {
                this.prevStep($form);
            }
        }

        handleFieldChange(e) {
            const $field = $(e.currentTarget);
            const fieldName = $field.attr('name');
            const fieldValue = $field.val();
            
            // Update form data
            this.formData[fieldName] = fieldValue;
            
            // Real-time validation
            this.validateField($field);
        }

        loadSupplierProducts($form, supplierId) {
            const $productSelect = $form.find('.product-select');
            
            // Show loading state
            $productSelect.html('<option value="">Loading products...</option>').prop('disabled', true);
            
            $.ajax({
                url: utilitySignOrderForm.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'utilitysign_get_supplier_products',
                    supplier_id: supplierId
                },
                success: (response) => {
                    if (response.success) {
                        let options = '<option value="">Select a product...</option>';
                        
                        response.data.products.forEach(product => {
                            options += `<option value="${product.id}">${product.name} - ${product.price}</option>`;
                        });
                        
                        $productSelect.html(options).prop('disabled', false);
                    } else {
                        $productSelect.html('<option value="">No products available</option>').prop('disabled', false);
                    }
                },
                error: () => {
                    $productSelect.html('<option value="">Error loading products</option>').prop('disabled', false);
                }
            });
        }

        loadProductDetails($form, productId) {
            const $productDetails = $form.find('.product-details');
            
            // Show loading state
            $productDetails.html('<div class="loading">Loading product details...</div>');
            
            $.ajax({
                url: utilitySignOrderForm.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'utilitysign_get_product_details',
                    product_id: productId
                },
                success: (response) => {
                    if (response.success) {
                        const product = response.data.product;
                        $productDetails.html(`
                            <div class="product-info">
                                <h4>${product.name}</h4>
                                <p class="product-description">${product.description}</p>
                                <p class="product-price">Price: ${product.price}</p>
                                ${product.variations ? this.renderVariations(product.variations) : ''}
                            </div>
                        `);
                    } else {
                        $productDetails.html('<div class="error">Failed to load product details</div>');
                    }
                },
                error: () => {
                    $productDetails.html('<div class="error">Error loading product details</div>');
                }
            });
        }

        renderVariations(variations) {
            let html = '<div class="product-variations"><label>Variations:</label>';
            
            variations.forEach(variation => {
                html += `
                    <div class="variation-option">
                        <input type="radio" name="product_variation" value="${variation.id}" id="variation-${variation.id}">
                        <label for="variation-${variation.id}">${variation.name} - ${variation.price}</label>
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        }

        updateQuantityDisplay($input) {
            const $controls = $input.siblings('.quantity-controls');
            const value = parseInt($input.val()) || 1;
            
            // Enable/disable minus button
            $controls.find('.quantity-minus').prop('disabled', value <= 1);
        }

        nextStep($form) {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.updateStepDisplay($form);
            }
        }

        prevStep($form) {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.updateStepDisplay($form);
            }
        }

        updateStepDisplay($form) {
            // Update step indicators
            $form.find('.step-indicator .step').each(function(index) {
                const stepNumber = index + 1;
                const $step = $(this);
                
                if (stepNumber < this.currentStep) {
                    $step.addClass('completed');
                } else if (stepNumber === this.currentStep) {
                    $step.addClass('active');
                } else {
                    $step.removeClass('active completed');
                }
            });
            
            // Show/hide step content
            $form.find('.form-section').hide();
            $form.find(`.form-section[data-step="${this.currentStep}"]`).show();
            
            // Update navigation buttons
            $form.find('.step-btn[data-direction="prev"]').toggle(this.currentStep > 1);
            $form.find('.step-btn[data-direction="next"]').toggle(this.currentStep < this.totalSteps);
            $form.find('.submit-order-btn').toggle(this.currentStep === this.totalSteps);
        }

        validateForm($form) {
            let isValid = true;
            
            // Clear previous errors
            $form.find('.form-error').remove();
            $form.find('.form-control').removeClass('error');
            
            // Validate required fields
            $form.find('.form-control[required]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    $field.addClass('error');
                    $field.after(`<div class="form-error">This field is required</div>`);
                }
            });
            
            // Validate email
            const $email = $form.find('input[type="email"]');
            if ($email.length && $email.val()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test($email.val())) {
                    isValid = false;
                    $email.addClass('error');
                    $email.after(`<div class="form-error">Please enter a valid email address</div>`);
                }
            }
            
            // Validate terms acceptance
            const $terms = $form.find('input[name="terms_accepted"]');
            if ($terms.length && !$terms.is(':checked')) {
                isValid = false;
                $terms.addClass('error');
                $terms.closest('.terms-checkbox').after(`<div class="form-error">You must accept the terms and conditions</div>`);
            }
            
            return isValid;
        }

        validateCurrentStep($form) {
            const $currentSection = $form.find(`.form-section[data-step="${this.currentStep}"]`);
            let isValid = true;
            
            // Clear previous errors
            $currentSection.find('.form-error').remove();
            $currentSection.find('.form-control').removeClass('error');
            
            // Validate required fields in current step
            $currentSection.find('.form-control[required]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    $field.addClass('error');
                    $field.after(`<div class="form-error">This field is required</div>`);
                }
            });
            
            return isValid;
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
            
            return true;
        }

        collectFormData($form) {
            const formData = {};
            
            // Collect all form fields
            $form.find('.form-control').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const type = $field.attr('type');
                
                if (name) {
                    if (type === 'checkbox') {
                        formData[name] = $field.is(':checked');
                    } else if (type === 'radio') {
                        if ($field.is(':checked')) {
                            formData[name] = $field.val();
                        }
                    } else {
                        formData[name] = $field.val();
                    }
                }
            });
            
            return formData;
        }

        resetForm($form) {
            $form[0].reset();
            this.formData = {};
            this.currentStep = 1;
            this.updateStepDisplay($form);
        }

        clearProductSelection($form) {
            $form.find('.product-select').html('<option value="">Select a product...</option>').prop('disabled', true);
            this.clearProductDetails($form);
        }

        clearProductDetails($form) {
            $form.find('.product-details').html('');
        }

        showSuccessMessage(message) {
            this.showMessage(message, 'success');
        }

        showErrorMessage(message) {
            this.showMessage(message, 'error');
        }

        showMessage(message, type = 'info') {
            // Remove existing messages
            $('.utilitysign-message').remove();
            
            // Create new message
            const $message = $(`
                <div class="utilitysign-message alert alert-${type}">
                    ${message}
                </div>
            `);
            
            // Insert message at the top of the form
            $('.utilitysign-order-form').prepend($message);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new OrderFormBlock();
    });

    // Expose to global scope for external access
    window.UtilitySignOrderForm = OrderFormBlock;

})(jQuery);