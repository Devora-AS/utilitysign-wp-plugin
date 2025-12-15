const { test, expect } = require('@playwright/test');

test.describe('UtilitySign Shortcode and Block Tests', () => {
    test.beforeEach(async ({ page }) => {
        // Navigate to WordPress admin
        await page.goto('/wp-admin/');
        
        // Login if needed (assuming test user exists)
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Wait for admin dashboard to load
        await page.waitForSelector('#wpbody-content');
    });

    test('should display shortcode in post content', async ({ page }) => {
        // Create a new post
        await page.goto('/wp-admin/post-new.php');
        
        // Add shortcode to post content
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123" enable_bank_id="true" enable_email_notifications="false" class_name="test-shortcode"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode is rendered
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-123"]')).toBeVisible();
        await expect(page.locator('[data-enable-bank-id="true"]')).toBeVisible();
        await expect(page.locator('[data-enable-email-notifications="false"]')).toBeVisible();
        await expect(page.locator('.test-shortcode')).toBeVisible();
    });

    test('should display error for shortcode without document ID', async ({ page }) => {
        // Create a new post
        await page.goto('/wp-admin/post-new.php');
        
        // Add shortcode without document ID
        await page.fill('#content', '[utilitysign_signing_form enable_bank_id="true"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if error message is displayed
        await expect(page.locator('.utilitysign-error')).toBeVisible();
        await expect(page.locator('text=Document ID is required')).toBeVisible();
    });

    test('should add and configure Gutenberg block', async ({ page }) => {
        // Create a new post
        await page.goto('/wp-admin/post-new.php');
        
        // Add block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign Signing Form');
        await page.click('text=UtilitySign Signing Form');
        
        // Wait for block to be added
        await page.waitForSelector('.wp-block-utilitysign-signing-form');
        
        // Configure block settings
        await page.click('[aria-label="Block: UtilitySign Signing Form"]');
        await page.fill('[aria-label="Document ID"]', 'test-doc-456');
        await page.click('[aria-label="Enable BankID"]');
        await page.fill('[aria-label="Additional CSS Class(es)"]', 'test-block-class');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if block is rendered
        await expect(page.locator('.wp-block-utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-456"]')).toBeVisible();
        await expect(page.locator('[data-enable-bank-id="false"]')).toBeVisible();
        await expect(page.locator('.test-block-class')).toBeVisible();
    });

    test('should display block preview in editor', async ({ page }) => {
        // Create a new post
        await page.goto('/wp-admin/post-new.php');
        
        // Add block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign Signing Form');
        await page.click('text=UtilitySign Signing Form');
        
        // Wait for block to be added
        await page.waitForSelector('.wp-block-utilitysign-signing-form');
        
        // Check if block preview is displayed
        await expect(page.locator('.utilitysign-signing-form-preview')).toBeVisible();
        await expect(page.locator('text=UtilitySign Signing Form')).toBeVisible();
        await expect(page.locator('text=Document ID required')).toBeVisible();
        
        // Configure document ID
        await page.fill('[aria-label="Document ID"]', 'test-doc-789');
        
        // Check if preview updates
        await expect(page.locator('text=Document ID configured')).toBeVisible();
        await expect(page.locator('text=test-doc-789')).toBeVisible();
    });

    test('should handle block settings panel', async ({ page }) => {
        // Create a new post
        await page.goto('/wp-admin/post-new.php');
        
        // Add block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign Signing Form');
        await page.click('text=UtilitySign Signing Form');
        
        // Wait for block to be added
        await page.waitForSelector('.wp-block-utilitysign-signing-form');
        
        // Check if settings panel is visible
        await expect(page.locator('.block-editor-inspector-controls')).toBeVisible();
        await expect(page.locator('text=Signing Form Settings')).toBeVisible();
        
        // Check if all form controls are present
        await expect(page.locator('[aria-label="Document ID"]')).toBeVisible();
        await expect(page.locator('[aria-label="Enable BankID"]')).toBeVisible();
        await expect(page.locator('[aria-label="Enable Email Notifications"]')).toBeVisible();
        await expect(page.locator('[aria-label="Additional CSS Class(es)"]')).toBeVisible();
    });

    test('should sanitize shortcode attributes', async ({ page }) => {
        // Create a new post
        await page.goto('/wp-admin/post-new.php');
        
        // Add shortcode with potentially malicious attributes
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123<script>alert(\'xss\')</script>" class_name="test-class<script>alert(\'xss\')</script>"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if attributes are sanitized
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-123"]')).toBeVisible();
        await expect(page.locator('.test-class')).toBeVisible();
        
        // Ensure no script tags are present
        const content = await page.content();
        expect(content).not.toContain('<script>alert(\'xss\')</script>');
    });

    test('should work with different themes', async ({ page }) => {
        // Switch to a different theme (assuming Twenty Twenty-Three is available)
        await page.goto('/wp-admin/themes.php');
        await page.click('[data-slug="twentytwentythree"] .activate');
        await page.waitForSelector('.notice-success');
        
        // Create a new post
        await page.goto('/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-456"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode still works
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-456"]')).toBeVisible();
    });

    test('should be responsive on mobile devices', async ({ page }) => {
        // Set mobile viewport
        await page.setViewportSize({ width: 375, height: 667 });
        
        // Create a new post
        await page.goto('/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-mobile"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode is visible and responsive
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        
        // Check if the form is properly sized for mobile
        const formElement = page.locator('.utilitysign-signing-form');
        const boundingBox = await formElement.boundingBox();
        expect(boundingBox.width).toBeLessThanOrEqual(375);
    });
});
