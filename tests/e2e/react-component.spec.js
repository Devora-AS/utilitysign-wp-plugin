import { test, expect } from '@playwright/test';

test.describe('React Component Integration Tests', () => {
    test.beforeEach(async ({ page }) => {
        // Navigate to WordPress admin
        await page.goto('http://localhost:8080/wp-admin/');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Wait for admin dashboard to load
        await page.waitForSelector('#wpbody-content');
    });

    test('React component mounts correctly on shortcode', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if React component is mounted
        await expect(page.locator('form')).toBeVisible();
        await expect(page.locator('input[type="text"]')).toBeVisible();
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeVisible();
    });

    test('React component mounts correctly on Gutenberg block', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Configure block
        await page.fill('input[placeholder="e.g., doc-12345"]', 'test-doc-456');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if React component is mounted
        await expect(page.locator('form')).toBeVisible();
        await expect(page.locator('input[type="text"]')).toBeVisible();
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeVisible();
    });

    test('React component handles form submission', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Fill out the form
        await page.fill('input[type="text"]', 'John Doe');
        await page.fill('input[type="email"]', 'john@example.com');
        
        // Submit the form
        await page.click('button[type="submit"]');
        
        // Check if form validation or submission feedback is shown
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
    });

    test('React component shows validation errors', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Try to submit form without filling required fields
        await page.click('button[type="submit"]');
        
        // Check if validation errors are shown
        await expect(page.locator('.utilitysign-error')).toBeVisible();
    });

    test('React component handles BankID option', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode with BankID enabled
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123" enable_bank_id="true"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if BankID option is shown
        await expect(page.locator('input[type="checkbox"][name="enableBankID"]')).toBeVisible();
        await expect(page.locator('input[type="checkbox"][name="enableBankID"]')).toBeChecked();
    });

    test('React component handles email notifications option', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode with email notifications enabled
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123" enable_email_notifications="true"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if email notifications option is shown
        await expect(page.locator('input[type="checkbox"][name="enableEmailNotifications"]')).toBeVisible();
        await expect(page.locator('input[type="checkbox"][name="enableEmailNotifications"]')).toBeChecked();
    });

    test('React component applies custom CSS classes', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode with custom class
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123" class_name="custom-react-class"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if custom class is applied
        await expect(page.locator('.utilitysign-signing-form.custom-react-class')).toBeVisible();
    });

    test('React component handles multiple instances', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add multiple shortcodes
        await page.fill('#content', `
            <p>First form:</p>
            [utilitysign_signing_form document_id="test-doc-1" class_name="first-form"]
            
            <p>Second form:</p>
            [utilitysign_signing_form document_id="test-doc-2" class_name="second-form"]
        `);
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if both forms are mounted
        await expect(page.locator('.utilitysign-signing-form')).toHaveCount(2);
        await expect(page.locator('.first-form')).toBeVisible();
        await expect(page.locator('.second-form')).toBeVisible();
        
        // Check if both forms have working inputs
        await expect(page.locator('.first-form input[type="text"]')).toBeVisible();
        await expect(page.locator('.second-form input[type="text"]')).toBeVisible();
    });

    test('React component handles API errors gracefully', async ({ page }) => {
        // Mock API error
        await page.route('**/api/v1/signing-requests', async route => {
            await route.fulfill({
                status: 500,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: false,
                    error: 'Internal server error'
                })
            });
        });

        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Fill out the form
        await page.fill('input[type="text"]', 'John Doe');
        await page.fill('input[type="email"]', 'john@example.com');
        
        // Submit the form
        await page.click('button[type="submit"]');
        
        // Wait for API call to complete
        await page.waitForTimeout(2000);
        
        // Check if error message is shown
        await expect(page.locator('.utilitysign-error')).toBeVisible();
    });

    test('React component shows loading state', async ({ page }) => {
        // Mock slow API response
        await page.route('**/api/v1/signing-requests', async route => {
            await new Promise(resolve => setTimeout(resolve, 2000));
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { requestId: 'req-123' }
                })
            });
        });

        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Fill out the form
        await page.fill('input[type="text"]', 'John Doe');
        await page.fill('input[type="email"]', 'john@example.com');
        
        // Submit the form
        await page.click('button[type="submit"]');
        
        // Check if loading state is shown
        await expect(page.locator('text=Processing')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeDisabled();
    });

    test('React component handles success callback', async ({ page }) => {
        // Mock successful API response
        await page.route('**/api/v1/signing-requests', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { requestId: 'req-123' }
                })
            });
        });

        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Fill out the form
        await page.fill('input[type="text"]', 'John Doe');
        await page.fill('input[type="email"]', 'john@example.com');
        
        // Submit the form
        await page.click('button[type="submit"]');
        
        // Wait for API call to complete
        await page.waitForTimeout(2000);
        
        // Check if success message is shown
        await expect(page.locator('text=Signing request created successfully')).toBeVisible();
    });

    test('React component is accessible', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if form has proper ARIA labels
        const form = page.locator('form');
        await expect(form).toHaveAttribute('role', 'form');
        
        // Check if inputs have proper labels
        const nameInput = page.locator('input[type="text"]');
        await expect(nameInput).toHaveAttribute('aria-label');
        
        const emailInput = page.locator('input[type="email"]');
        await expect(emailInput).toHaveAttribute('aria-label');
        
        // Check if submit button has proper label
        const submitButton = page.locator('button[type="submit"]');
        await expect(submitButton).toHaveAttribute('aria-label');
    });

    test('React component is responsive', async ({ page }) => {
        // Test on mobile viewport
        await page.setViewportSize({ width: 375, height: 667 });
        
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if form is responsive
        const formContainer = page.locator('.utilitysign-signing-form');
        await expect(formContainer).toBeVisible();
        
        const boundingBox = await formContainer.boundingBox();
        expect(boundingBox.width).toBeLessThanOrEqual(375);
    });
});
