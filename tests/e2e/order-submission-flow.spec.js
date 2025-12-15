/**
 * Order Submission Flow E2E Tests
 * 
 * End-to-end tests for complete user journey from product selection to signing.
 * 
 * @package UtilitySign
 * @subpackage Tests
 */

import { test, expect } from '@playwright/test';

test.describe('Order Submission Flow', () => {
    test.beforeEach(async ({ page }) => {
        // Navigate to test page with UtilitySign plugin
        await page.goto('http://localhost:8888/test-order-form/');
        
        // Wait for React app to load
        await page.waitForSelector('[data-testid="signing-process"]', { timeout: 5000 });
    });
    
    /**
     * Test complete user journey from product selection to signing
     */
    test('completes full order submission and signing flow', async ({ page }) => {
        // Step 1: Select product
        await page.click('[data-testid="product-card-1"]');
        await expect(page.locator('[data-testid="order-form"]')).toBeVisible();
        
        // Step 2: Fill order form
        await page.fill('[name="customer_name"]', 'Test User');
        await page.fill('[name="customer_email"]', 'test@example.com');
        await page.fill('[name="customer_phone"]', '+47 12345678');
        
        // Accept terms
        await page.check('[name="terms_accepted"]');
        
        // Step 3: Submit order
        await page.click('button[type="submit"]');
        
        // Wait for loading indicator
        await expect(page.locator('text=Processing order')).toBeVisible();
        
        // Step 4: Confirm BankID redirect
        page.once('dialog', dialog => {
            expect(dialog.message()).toContain('BankID');
            dialog.accept();
        });
        
        // Wait for redirect (or mock it in test environment)
        await page.waitForURL(/signatures\.criipto\.io/, { timeout: 10000 });
        
        // Verify we're on Criipto signing page
        expect(page.url()).toContain('signatures.criipto.io');
    });
    
    /**
     * Test product selection displays correct details
     */
    test('displays product details after selection', async ({ page }) => {
        // Click on first product
        await page.click('[data-testid="product-card-1"]');
        
        // Verify product details are shown
        await expect(page.locator('[data-testid="product-title"]')).toBeVisible();
        await expect(page.locator('[data-testid="product-price"]')).toBeVisible();
        await expect(page.locator('[data-testid="product-description"]')).toBeVisible();
    });
    
    /**
     * Test order form validation
     */
    test('validates required fields before submission', async ({ page }) => {
        // Select product
        await page.click('[data-testid="product-card-1"]');
        
        // Try to submit without filling required fields
        await page.click('button[type="submit"]');
        
        // Verify validation errors
        await expect(page.locator('text=Email is required')).toBeVisible();
        await expect(page.locator('text=Name is required')).toBeVisible();
        
        // Fill only email
        await page.fill('[name="customer_email"]', 'invalid-email');
        await page.click('button[type="submit"]');
        
        // Verify email format validation
        await expect(page.locator('text=Invalid email format')).toBeVisible();
    });
    
    /**
     * Test BankID redirect confirmation
     */
    test('shows confirmation dialog before BankID redirect', async ({ page }) => {
        // Select product and fill form
        await page.click('[data-testid="product-card-1"]');
        await page.fill('[name="customer_name"]', 'Test User');
        await page.fill('[name="customer_email"]', 'test@example.com');
        await page.check('[name="terms_accepted"]');
        
        // Submit form
        await page.click('button[type="submit"]');
        
        // Wait for confirmation dialog
        const dialog = await page.waitForEvent('dialog');
        expect(dialog.message()).toContain('BankID');
        expect(dialog.message()).toContain('redirect');
        
        await dialog.dismiss(); // Cancel redirect
        
        // Verify we're still on the same page
        expect(page.url()).not.toContain('criipto.io');
    });
    
    /**
     * Test signing completion callback
     */
    test('handles signing completion webhook', async ({ page, request }) => {
        // Create order first
        await page.click('[data-testid="product-card-1"]');
        await page.fill('[name="customer_name"]', 'Test User');
        await page.fill('[name="customer_email"]', 'test@example.com');
        await page.check('[name="terms_accepted"]');
        await page.click('button[type="submit"]');
        
        // Get order ID from response
        const orderResponse = await page.waitForResponse(
            response => response.url().includes('admin-ajax.php') && response.status() === 200
        );
        const orderData = await orderResponse.json();
        const orderId = orderData.data.order_id;
        
        // Simulate webhook callback
        const webhookResponse = await request.post(
            'http://localhost:8888/wp-json/utilitysign/v1/webhooks/signing-complete',
            {
                data: {
                    wpOrderId: orderId,
                    status: 'signed',
                    documentUrl: 'https://storage.criipto.io/doc-signed.pdf',
                },
                headers: {
                    'Content-Type': 'application/json',
                    'X-Criipto-Signature': 'mock-signature',
                },
            }
        );
        
        expect(webhookResponse.ok()).toBeTruthy();
        
        // Verify order status updated in admin
        await page.goto(`http://localhost:8888/wp-admin/post.php?post=${orderId}&action=edit`);
        await expect(page.locator('text=signed')).toBeVisible();
    });
    
    /**
     * Test error recovery and retry
     */
    test('allows retry after submission error', async ({ page }) => {
        // Mock API to fail first time
        await page.route('**/admin-ajax.php', async (route, request) => {
            const postData = request.postData();
            
            if (postData && postData.includes('utilitysign_submit_order')) {
                // First request fails
                if (route.request().isNavigationRequest()) {
                    await route.fulfill({
                        status: 500,
                        contentType: 'application/json',
                        body: JSON.stringify({
                            success: false,
                            data: { message: 'Azure API error' },
                        }),
                    });
                } else {
                    // Second request succeeds
                    await route.continue();
                }
            } else {
                await route.continue();
            }
        });
        
        // Select product and submit
        await page.click('[data-testid="product-card-1"]');
        await page.fill('[name="customer_name"]', 'Test User');
        await page.fill('[name="customer_email"]', 'test@example.com');
        await page.check('[name="terms_accepted"]');
        await page.click('button[type="submit"]');
        
        // Verify error message
        await expect(page.locator('text=Azure API error')).toBeVisible();
        
        // Retry submission
        await page.click('button[type="submit"]');
        
        // Verify success (error cleared)
        await expect(page.locator('text=Azure API error')).not.toBeVisible();
        await expect(page.locator('text=Processing order')).toBeVisible();
    });
});

test.describe('Accessibility Tests', () => {
    /**
     * Test keyboard navigation
     */
    test('supports keyboard navigation', async ({ page }) => {
        await page.goto('http://localhost:8888/test-order-form/');
        
        // Tab through form elements
        await page.keyboard.press('Tab'); // Product card
        await page.keyboard.press('Enter'); // Select product
        
        await page.keyboard.press('Tab'); // Name field
        await page.keyboard.type('Test User');
        
        await page.keyboard.press('Tab'); // Email field
        await page.keyboard.type('test@example.com');
        
        await page.keyboard.press('Tab'); // Terms checkbox
        await page.keyboard.press('Space'); // Check terms
        
        await page.keyboard.press('Tab'); // Submit button
        await page.keyboard.press('Enter'); // Submit
        
        // Verify submission started
        await expect(page.locator('text=Processing order')).toBeVisible();
    });
    
    /**
     * Test screen reader labels
     */
    test('has proper ARIA labels', async ({ page }) => {
        await page.goto('http://localhost:8888/test-order-form/');
        
        // Check for proper labels
        const nameInput = page.locator('[name="customer_name"]');
        await expect(nameInput).toHaveAttribute('aria-label', /name/i);
        
        const emailInput = page.locator('[name="customer_email"]');
        await expect(emailInput).toHaveAttribute('aria-label', /email/i);
        
        const submitButton = page.locator('button[type="submit"]');
        await expect(submitButton).toHaveAttribute('aria-label', /submit|send/i);
    });
});

