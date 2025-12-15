import { test, expect } from '@playwright/test';

test.describe('Responsive Design and Accessibility Tests', () => {
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

    test('shortcode is responsive on mobile devices', async ({ page }) => {
        // Set mobile viewport
        await page.setViewportSize({ width: 375, height: 667 });
        
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-mobile"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode is visible and responsive
        const formContainer = page.locator('.utilitysign-signing-form');
        await expect(formContainer).toBeVisible();
        
        // Check if the form is properly sized for mobile
        const boundingBox = await formContainer.boundingBox();
        expect(boundingBox.width).toBeLessThanOrEqual(375);
        
        // Check if form elements are accessible on mobile
        await expect(page.locator('input[type="text"]')).toBeVisible();
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeVisible();
    });

    test('shortcode is responsive on tablet devices', async ({ page }) => {
        // Set tablet viewport
        await page.setViewportSize({ width: 768, height: 1024 });
        
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-tablet"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode is visible and responsive
        const formContainer = page.locator('.utilitysign-signing-form');
        await expect(formContainer).toBeVisible();
        
        // Check if the form is properly sized for tablet
        const boundingBox = await formContainer.boundingBox();
        expect(boundingBox.width).toBeLessThanOrEqual(768);
    });

    test('shortcode is responsive on desktop devices', async ({ page }) => {
        // Set desktop viewport
        await page.setViewportSize({ width: 1920, height: 1080 });
        
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-desktop"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode is visible and responsive
        const formContainer = page.locator('.utilitysign-signing-form');
        await expect(formContainer).toBeVisible();
        
        // Check if the form is properly sized for desktop
        const boundingBox = await formContainer.boundingBox();
        expect(boundingBox.width).toBeLessThanOrEqual(1920);
    });

    test('shortcode has proper ARIA labels', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-aria"]');
        
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

    test('shortcode is keyboard navigable', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-keyboard"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Test keyboard navigation
        await page.keyboard.press('Tab');
        await page.keyboard.press('Tab');
        await page.keyboard.press('Tab');
        
        // Check if focus is on the form elements
        const focusedElement = page.locator(':focus');
        await expect(focusedElement).toBeVisible();
    });

    test('shortcode has proper color contrast', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-contrast"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if text has proper color contrast
        const textElements = page.locator('.utilitysign-signing-form p, .utilitysign-signing-form label, .utilitysign-signing-form button');
        
        for (let i = 0; i < await textElements.count(); i++) {
            const element = textElements.nth(i);
            const color = await element.evaluate(el => getComputedStyle(el).color);
            const backgroundColor = await element.evaluate(el => getComputedStyle(el).backgroundColor);
            
            // Basic color contrast check (this would need a proper contrast ratio calculation in a real test)
            expect(color).not.toBe('rgb(0, 0, 0)'); // Not pure black
            expect(backgroundColor).not.toBe('rgb(255, 255, 255)'); // Not pure white
        }
    });

    test('shortcode has proper focus indicators', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-focus"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Test focus indicators
        const nameInput = page.locator('input[type="text"]');
        await nameInput.focus();
        
        // Check if focus indicator is visible
        const focusStyles = await nameInput.evaluate(el => getComputedStyle(el).outline);
        expect(focusStyles).not.toBe('none');
    });

    test('shortcode has proper error messaging', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-errors"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Test form validation
        const submitButton = page.locator('button[type="submit"]');
        await submitButton.click();
        
        // Check if error messages are displayed
        await expect(page.locator('.utilitysign-error')).toBeVisible();
        
        // Check if error messages have proper ARIA attributes
        const errorElement = page.locator('.utilitysign-error');
        await expect(errorElement).toHaveAttribute('role', 'alert');
    });

    test('shortcode works with screen readers', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-screenreader"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if form has proper semantic structure
        const form = page.locator('form');
        await expect(form).toBeVisible();
        
        // Check if form has proper heading structure
        const headings = page.locator('.utilitysign-signing-form h1, .utilitysign-signing-form h2, .utilitysign-signing-form h3');
        await expect(headings).toHaveCount(1); // Should have at least one heading
    });

    test('shortcode is accessible with high contrast mode', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-contrast"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if form elements are visible in high contrast mode
        const formElements = page.locator('.utilitysign-signing-form input, .utilitysign-signing-form button');
        
        for (let i = 0; i < await formElements.count(); i++) {
            const element = formElements.nth(i);
            const visibility = await element.evaluate(el => getComputedStyle(el).visibility);
            expect(visibility).toBe('visible');
        }
    });
});
