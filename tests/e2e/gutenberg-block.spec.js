import { test, expect } from '@playwright/test';

test.describe('Gutenberg Block Tests', () => {
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

    test('block appears in block inserter', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Click on the block inserter
        await page.click('[aria-label="Add block"]');
        
        // Search for the UtilitySign block
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        
        // Check if the block appears in search results
        await expect(page.locator('text=UtilitySign Signing Form')).toBeVisible();
    });

    test('block can be added to post', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Check if block is added
        await expect(page.locator('.wp-block-utilitysign-signing-form')).toBeVisible();
    });

    test('block shows preview in editor', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Check if block preview is shown
        await expect(page.locator('.utilitysign-signing-form-preview')).toBeVisible();
        await expect(page.locator('text=UtilitySign Signing Form')).toBeVisible();
    });

    test('block shows warning without document ID', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Check if warning is shown
        await expect(page.locator('text=Not Configured')).toBeVisible();
        await expect(page.locator('text=Please configure the Document ID')).toBeVisible();
    });

    test('block inspector controls are visible', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Check if inspector controls are visible
        await expect(page.locator('.block-editor-inspector-controls')).toBeVisible();
        await expect(page.locator('text=Signing Form Settings')).toBeVisible();
    });

    test('block has all required form controls', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Check if all form controls are present
        await expect(page.locator('input[placeholder="e.g., doc-12345"]')).toBeVisible();
        await expect(page.locator('input[type="checkbox"][name="enableBankID"]')).toBeVisible();
        await expect(page.locator('input[type="checkbox"][name="enableEmailNotifications"]')).toBeVisible();
        await expect(page.locator('input[placeholder="e.g., my-custom-class another-class"]')).toBeVisible();
    });

    test('block can be configured with document ID', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Configure the block
        await page.fill('input[placeholder="e.g., doc-12345"]', 'test-doc-123');
        
        // Check if the block shows as configured
        await expect(page.locator('text=Configured')).toBeVisible();
        await expect(page.locator('text=test-doc-123')).toBeVisible();
    });

    test('block can be configured with BankID settings', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Configure the block
        await page.fill('input[placeholder="e.g., doc-12345"]', 'test-doc-123');
        await page.check('input[type="checkbox"][name="enableBankID"]');
        
        // Check if the block shows updated settings
        await expect(page.locator('text=BankID Enabled: Yes')).toBeVisible();
    });

    test('block can be configured with email notifications', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Configure the block
        await page.fill('input[placeholder="e.g., doc-12345"]', 'test-doc-123');
        await page.uncheck('input[type="checkbox"][name="enableEmailNotifications"]');
        
        // Check if the block shows updated settings
        await expect(page.locator('text=Email Notifications: No')).toBeVisible();
    });

    test('block can be configured with custom CSS classes', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Configure the block
        await page.fill('input[placeholder="e.g., doc-12345"]', 'test-doc-123');
        await page.fill('input[placeholder="e.g., my-custom-class another-class"]', 'custom-class another-class');
        
        // Check if the block shows custom classes
        await expect(page.locator('text=CSS Classes: custom-class another-class')).toBeVisible();
    });

    test('block can be saved and published', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Configure the block
        await page.fill('input[placeholder="e.g., doc-12345"]', 'test-doc-123');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if block is rendered on frontend
        await expect(page.locator('.wp-block-utilitysign-signing-form')).toBeVisible();
        await expect(page.locator('[data-document-id="test-doc-123"]')).toBeVisible();
    });

    test('block can be edited after publishing', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Configure the block
        await page.fill('input[placeholder="e.g., doc-12345"]', 'test-doc-123');
        
        // Publish the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // Edit the post
        await page.click('.post-publish-panel__post-edit');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Update the block
        await page.fill('input[placeholder="e.g., doc-12345"]', 'updated-doc-456');
        
        // Update the post
        await page.click('#publish');
        await page.waitForSelector('.notice-success');
        
        // View the post
        await page.click('.post-publish-panel__post-view');
        
        // Check if block is updated on frontend
        await expect(page.locator('[data-document-id="updated-doc-456"]')).toBeVisible();
    });

    test('block can be deleted', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Delete the block
        await page.keyboard.press('Delete');
        
        // Check if block is removed
        await expect(page.locator('.wp-block-utilitysign-signing-form')).not.toBeVisible();
    });

    test('block can be moved up and down', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add some text
        await page.fill('#content', 'Some text before the block');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Add some text after
        await page.keyboard.press('Enter');
        await page.keyboard.type('Some text after the block');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Move block up
        await page.keyboard.press('ArrowUp');
        
        // Move block down
        await page.keyboard.press('ArrowDown');
        
        // Check if block is still visible
        await expect(page.locator('.wp-block-utilitysign-signing-form')).toBeVisible();
    });

    test('block can be duplicated', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Duplicate the block
        await page.keyboard.press('Control+c');
        await page.keyboard.press('Control+v');
        
        // Check if block is duplicated
        await expect(page.locator('.wp-block-utilitysign-signing-form')).toHaveCount(2);
    });

    test('block can be copied and pasted', async ({ page }) => {
        // Go to post editor
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Add the block
        await page.click('[aria-label="Add block"]');
        await page.fill('[placeholder="Search for a block"]', 'UtilitySign');
        await page.click('text=UtilitySign Signing Form');
        
        // Click on the block to select it
        await page.click('.wp-block-utilitysign-signing-form');
        
        // Copy the block
        await page.keyboard.press('Control+c');
        
        // Create a new post
        await page.goto('http://localhost:8080/wp-admin/post-new.php');
        
        // Paste the block
        await page.keyboard.press('Control+v');
        
        // Check if block is pasted
        await expect(page.locator('.wp-block-utilitysign-signing-form')).toBeVisible();
    });
});
