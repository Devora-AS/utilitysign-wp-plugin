import { test, expect } from '@playwright/test';

test.describe('Shortcode Tests', () => {
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

    test('shortcode renders correctly with valid document ID', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode is rendered
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-123"]')).toBeVisible();
    });

    test('shortcode shows error without document ID', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode without document ID
        await page.fill('#content', '[utilitysign_signing_form]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if error message is shown
        await expect(page.locator('.utilitysign-error')).toBeVisible();
        await expect(page.locator('text=Document ID is required')).toBeVisible();
    });

    test('shortcode handles all attributes correctly', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode with all attributes
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-456" enable_bank_id="false" enable_email_notifications="true" class_name="custom-shortcode-class"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if all attributes are applied
        const formContainer = page.locator('.utilitysign-signing-form');
        await expect(formContainer).toBeVisible();
        await expect(formContainer).toHaveAttribute('data-document-id', 'test-doc-456');
        await expect(formContainer).toHaveAttribute('data-enable-bank-id', 'false');
        await expect(formContainer).toHaveAttribute('data-enable-email-notifications', 'true');
        await expect(formContainer).toHaveClass(/custom-shortcode-class/);
    });

    test('shortcode handles boolean attributes correctly', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Test with different boolean values
        const testCases = [
            { enable_bank_id: 'true', enable_email_notifications: 'false' },
            { enable_bank_id: '1', enable_email_notifications: '0' },
            { enable_bank_id: 'yes', enable_email_notifications: 'no' },
            { enable_bank_id: 'false', enable_email_notifications: 'true' },
            { enable_bank_id: '0', enable_email_notifications: '1' },
            { enable_bank_id: 'no', enable_email_notifications: 'yes' }
        ];
        
        for (const testCase of testCases) {
            // Add shortcode with test case
            await page.fill('#content', `[utilitysign_signing_form document_id="test-doc-${Math.random().toString(36).substr(2, 9)}" enable_bank_id="${testCase.enable_bank_id}" enable_email_notifications="${testCase.enable_email_notifications}"]`);
            
            // Publish the post
            await page.click('#publish');
            await page.waitForSelector('.notice-success');
            
            // View the post
            await page.click('.post-publish-panel__post-view');
            
            // Check if boolean attributes are handled correctly
            const formContainer = page.locator('.utilitysign-signing-form');
            await expect(formContainer).toBeVisible();
            
            // Check if attributes are converted to proper boolean strings
            const enableBankId = testCase.enable_bank_id.toLowerCase();
            const enableEmailNotifications = testCase.enable_email_notifications.toLowerCase();
            
            if (['true', '1', 'yes'].includes(enableBankId)) {
                await expect(formContainer).toHaveAttribute('data-enable-bank-id', 'true');
            } else {
                await expect(formContainer).toHaveAttribute('data-enable-bank-id', 'false');
            }
            
            if (['true', '1', 'yes'].includes(enableEmailNotifications)) {
                await expect(formContainer).toHaveAttribute('data-enable-email-notifications', 'true');
            } else {
                await expect(formContainer).toHaveAttribute('data-enable-email-notifications', 'false');
            }
            
            // Go back to edit the post for next test case
            await page.goto('http://localhost:8080/wp-admin/post-new.php');
        }
    });

    test('shortcode sanitizes input attributes', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode with potentially malicious attributes
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123<script>alert(\'xss\')</script>" class_name="test-class<img src=x onerror=alert(1)>"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if attributes are sanitized
        const formContainer = page.locator('.utilitysign-signing-form');
        await expect(formContainer).toBeVisible();
        
        // Should not contain script tags
        const content = await page.content();
        expect(content).not.toContain('<script>alert(\'xss\')</script>');
        expect(content).not.toContain('onerror=alert(1)');
        
        // Should contain sanitized values
        await expect(formContainer).toHaveAttribute('data-document-id', 'test-doc-123');
        await expect(formContainer).toHaveClass(/test-class/);
    });

    test('shortcode works with multiple instances on same page', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add multiple shortcodes
        await page.fill('#content', `
            <p>First form:</p>
            [utilitysign_signing_form document_id="test-doc-1" class_name="first-form"]
            
            <p>Second form:</p>
            [utilitysign_signing_form document_id="test-doc-2" class_name="second-form"]
            
            <p>Third form:</p>
            [utilitysign_signing_form document_id="test-doc-3" class_name="third-form"]
        `);
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if all shortcodes are rendered
        await expect(page.locator('.utilitysign-signing-form')).toHaveCount(3);
        await expect(page.locator('[data-document-id="test-doc-1"]')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-2"]')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-3"]')).toBeVisible();
        
        // Check if each has unique ID
        const forms = page.locator('.utilitysign-signing-form');
        const ids = await forms.evaluateAll(forms => forms.map(form => form.id));
        const uniqueIds = [...new Set(ids)];
        expect(uniqueIds).toHaveLength(3);
    });

    test('shortcode works in different post types', async ({ page }) => {
        // Test with post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-post"]');
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        await page.click('.post-publish-panel__post-view');
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        
        // Test with page
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-page"]');
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        await page.click('.post-publish-panel__post-view');
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
    });

    test('shortcode works in widgets', async ({ page }) => {
        // Go to widgets page
        await page.goto('http://localhost:8080/wp-admin/widgets.php');
        
        // Add a text widget
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'Text');
        await page.click('text=Text');
        
        // Add shortcode to widget
        await page.fill('.wp-block-paragraph', '[utilitysign_signing_form document_id="test-doc-widget"]');
        
        // Save widgets
        await page.click('#save-widgets');
        
        // View the site
        await page.goto('http://localhost:8080');
        
        // Check if shortcode is rendered in widget
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-widget"]')).toBeVisible();
    });

    test('shortcode works in custom post types', async ({ page }) => {
        // This test assumes a custom post type exists
        // You might need to create one or adjust the test
        
        // Go to custom post type editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=custom_post_type');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-custom"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode is rendered
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-custom"]')).toBeVisible();
    });

    test('shortcode handles empty attributes gracefully', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode with empty attributes
        await page.fill('#content', '[utilitysign_signing_form document_id="" enable_bank_id="" enable_email_notifications="" class_name=""]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if error is shown for empty document ID
        await expect(page.locator('.utilitysign-error')).toBeVisible();
        await expect(page.locator('text=Document ID is required')).toBeVisible();
    });

    test('shortcode handles malformed attributes gracefully', async ({ page }) => {
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode with malformed attributes
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123" enable_bank_id="invalid" enable_email_notifications="invalid" class_name="invalid class name with spaces"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode is rendered with default values
        const formContainer = page.locator('.utilitysign-signing-form');
        await expect(formContainer).toBeVisible();
        await expect(formContainer).toHaveAttribute('data-document-id', 'test-doc-123');
        // Boolean attributes should default to true for invalid values
        await expect(formContainer).toHaveAttribute('data-enable-bank-id', 'true');
        await expect(formContainer).toHaveAttribute('data-enable-email-notifications', 'true');
    });

    test('shortcode works with different themes', async ({ page }) => {
        // Switch to a different theme (assuming Twenty Twenty-Three is available)
        await page.goto('http://localhost:8080/wp-admin/themes.php');
        await page.click('[data-slug="twentytwentythree"] .activate');
        await page.waitForSelector('.notice-success');
        
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add shortcode
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-theme"]');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if shortcode still works
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-theme"]')).toBeVisible();
    });
});
