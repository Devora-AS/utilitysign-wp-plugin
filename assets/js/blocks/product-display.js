/**
 * Product Display Block Frontend JavaScript
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Product Display Block Class
    class ProductDisplayBlock {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.initLazyLoading();
            this.initVariationSelectors();
        }

        bindEvents() {
            // Add to cart functionality
            $(document).on('click', '.utilitysign-product-display .add-to-cart-btn', this.handleAddToCart.bind(this));
            
            // Variation selector changes
            $(document).on('change', '.utilitysign-product-display .variation-select', this.handleVariationChange.bind(this));
            
            // Product image lazy loading
            $(document).on('scroll', this.handleScroll.bind(this));
        }

        handleAddToCart(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const $product = $btn.closest('.product-item');
            const productId = $product.data('product-id');
            const variationId = $product.find('.variation-select').val();
            const quantity = $product.find('.quantity-input').val() || 1;
            
            // Disable button and show loading
            $btn.prop('disabled', true).text('Adding...');
            
            // Get product data
            const productData = {
                product_id: productId,
                variation_id: variationId,
                quantity: quantity,
                action: 'utilitysign_add_to_cart'
            };
            
            // AJAX request
            $.ajax({
                url: utilitySignProductDisplay.ajaxUrl,
                type: 'POST',
                data: productData,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Product added to cart successfully!', 'success');
                        this.updateCartCount(response.data.cart_count);
                    } else {
                        this.showMessage(response.data.message || 'Failed to add product to cart', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('An error occurred. Please try again.', 'error');
                },
                complete: () => {
                    // Re-enable button
                    $btn.prop('disabled', false).text('Add to Cart');
                }
            });
        }

        handleVariationChange(e) {
            const $select = $(e.currentTarget);
            const $product = $select.closest('.product-item');
            const variationId = $select.val();
            
            if (variationId) {
                // Update product price if variation has different price
                this.updateProductPrice($product, variationId);
            }
        }

        updateProductPrice($product, variationId) {
            const productId = $product.data('product-id');
            
            $.ajax({
                url: utilitySignProductDisplay.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'utilitysign_get_variation_price',
                    product_id: productId,
                    variation_id: variationId
                },
                success: (response) => {
                    if (response.success) {
                        $product.find('.product-price').html(response.data.price_html);
                    }
                }
            });
        }

        initLazyLoading() {
            // Initialize lazy loading for product images
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

                document.querySelectorAll('.utilitysign-product-display img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        }

        initVariationSelectors() {
            // Initialize variation selectors with proper styling
            $('.utilitysign-product-display .variation-select').each(function() {
                const $select = $(this);
                const $product = $select.closest('.product-item');
                
                // Add change handler for price updates
                $select.on('change', function() {
                    const variationId = $(this).val();
                    if (variationId) {
                        // Update product price
                        const productId = $product.data('product-id');
                        // This would typically make an AJAX call to get the variation price
                        // For now, we'll just show a loading state
                        $product.find('.product-price').html('<span class="loading">Loading...</span>');
                    }
                });
            });
        }

        handleScroll() {
            // Handle scroll events for lazy loading and other scroll-based features
            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();
            
            // Check if any lazy images are in viewport
            $('.utilitysign-product-display img[data-src]').each(function() {
                const $img = $(this);
                const imgTop = $img.offset().top;
                
                if (imgTop < scrollTop + windowHeight) {
                    $img.attr('src', $img.data('src')).removeClass('lazy');
                }
            });
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
            
            // Insert message at the top of the product display
            $('.utilitysign-product-display').prepend($message);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        updateCartCount(count) {
            // Update cart count in header or other cart indicators
            $('.cart-count').text(count);
            $('.cart-icon').addClass('updated');
            
            setTimeout(() => {
                $('.cart-icon').removeClass('updated');
            }, 1000);
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new ProductDisplayBlock();
    });

    // Expose to global scope for external access
    window.UtilitySignProductDisplay = ProductDisplayBlock;

})(jQuery);