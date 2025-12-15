import { test, expect } from '@playwright/test';

test.describe('UtilitySign WordPress Plugin', () => {
    test.beforeEach(async ({ page }) => {
        // Navigate to the WordPress site
        await page.goto('http://localhost:8080');
    });

    test('shortcode renders correctly on frontend', async ({ page }) => {
        // Create a test page with the shortcode
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed (you might need to adjust this based on your setup)
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with the shortcode
        await page.fill('#title', 'Test Signing Form Page');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-signing-form-page');
        
        // Check if the shortcode container is present
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-123"]')).toBeVisible();
    });

    test('shortcode shows error without document ID', async ({ page }) => {
        // Create a test page with invalid shortcode
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with invalid shortcode
        await page.fill('#title', 'Test Error Page');
        await page.fill('#content', '[utilitysign_signing_form]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-error-page');
        
        // Check if error message is shown
        await expect(page.locator('.utilitysign-error')).toBeVisible();
        await expect(page.locator('text=Document ID is required')).toBeVisible();
    });

    test('shortcode handles attributes correctly', async ({ page }) => {
        // Create a test page with shortcode attributes
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with shortcode attributes
        await page.fill('#title', 'Test Attributes Page');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-456" enable_bank_id="false" enable_email_notifications="true" class_name="custom-class"]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-attributes-page');
        
        // Check if attributes are applied correctly
        const formContainer = page.locator('.utilitysign-signing-form');
        await expect(formContainer).toBeVisible();
        await expect(formContainer).toHaveAttribute('data-document-id', 'test-doc-456');
        await expect(formContainer).toHaveAttribute('data-enable-bank-id', 'false');
        await expect(formContainer).toHaveAttribute('data-enable-email-notifications', 'true');
        await expect(formContainer).toHaveClass(/custom-class/);
    });

    test('gutenberg block appears in block inserter', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Click on the block inserter
        await page.click('[aria-label="Add block"]');
        
        // Search for the UtilitySign block
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        
        // Check if the block appears in search results
        await expect(page.locator('text=UtilitySign Signing Form')).toBeVisible();
    });

    test('gutenberg block can be added and configured', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Check if block is added
        await expect(page.locator('.wp-block-utilitysign-signing-form')).toBeVisible();
        
        // Open block settings
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Check if inspector controls are visible
        await expect(page.locator('.block-editor-inspector-controls')).toBeVisible();
        
        // Configure the block
        await page.fill('input[placeholder="e.g., doc-12345"]', 'test-doc-789');
        
        // Check if the block shows as configured
        await expect(page.locator('text=Configured')).toBeVisible();
        await expect(page.locator('text=test-doc-789')).toBeVisible();
    });

    test('gutenberg block shows warning without document ID', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Check if warning is shown
        await expect(page.locator('text=Not Configured')).toBeVisible();
        await expect(page.locator('text=Please configure the Document ID')).toBeVisible();
    });

    test('react component mounts correctly on frontend', async ({ page }) => {
        // Create a test page with the shortcode
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with the shortcode
        await page.fill('#title', 'Test React Component');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-react-component');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if React component is mounted
        await expect(page.locator('form')).toBeVisible();
        await expect(page.locator('input[type="text"]')).toBeVisible();
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('button')).toBeVisible();
    });

    test('react component handles form submission', async ({ page }) => {
        // Create a test page with the shortcode
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with the shortcode
        await page.fill('#title', 'Test Form Submission');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-form-submission');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Fill out the form
        await page.fill('input[type="text"]', 'John Doe');
        await page.fill('input[type="email"]', 'john@example.com');
        
        // Submit the form
        await page.click('button[type="submit"]');
        
        // Check if form validation or submission feedback is shown
        // (This depends on your API implementation)
        await expect(page.locator('.utilitysign-signing-form')).toBeVisible();
    });

    test('plugin admin interface loads correctly', async ({ page }) => {
        // Go to plugin admin page
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=utilitysign');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Check if admin interface loads
        await expect(page.locator('#utilitysign')).toBeVisible();
        
        // Check if React admin app is mounted
        await page.waitForSelector('#utilitysign', { timeout: 10000 });
    });

    test('plugin settings can be accessed', async ({ page }) => {
        // Go to plugin settings
        await page.goto('http://localhost:8080/wp-admin/admin.php?page=utilitysign');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Navigate to settings (if it's a separate page)
        // This depends on your admin interface implementation
        await page.waitForSelector('#utilitysign', { timeout: 10000 });
        
        // Check if settings components are visible
        // This depends on your admin interface implementation
    });
});
