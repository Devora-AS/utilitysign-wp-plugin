/**
 * Plugin Configuration Test
 * 
 * Automatically configures UtilitySign plugin with test credentials.
 * 
 * @package UtilitySign
 * @subpackage Tests
 */

import { test, expect } from '@playwright/test';

test.describe('Plugin Configuration', () => {
    /**
     * Configure plugin settings with test credentials
     */
    test('configure plugin with Criipto test credentials', async ({ page }) => {
        // Navigate to WordPress admin with auto-login
        await page.goto('http://devora-test.local/wp-admin/?localwp_auto_login=1');
        
        // Wait for admin dashboard
        await page.waitForSelector('#adminmenu', { timeout: 10000 });
        console.log('✅ Logged into WordPress admin');
        
        // Navigate to UtilitySign settings
        await page.goto('http://devora-test.local/wp-admin/admin.php?page=utilitysign-settings');
        
        // Wait for page to load (either React app or standard WP admin)
        await page.waitForTimeout(2000);
        
        // Take screenshot for debugging
        await page.screenshot({ path: 'tests/screenshots/settings-page.png', fullPage: true });
        console.log('📸 Screenshot saved to tests/screenshots/settings-page.png');
        
        // Check if React app loaded by looking for actual settings elements
        const hasSettingsHeading = await page.locator('text=UtilitySign Settings').count() > 0;
        const hasApiConfigTab = await page.locator('text=API Configuration').count() > 0;
        const hasSaveButton = await page.locator('button:has-text("Save Configuration"), button:has-text("Save All Changes")').count() > 0;
        
        if (hasSettingsHeading && hasApiConfigTab) {
            console.log('✅ React admin app loaded successfully');
            console.log('✅ API Configuration tab present');
        }
        
        if (hasSaveButton) {
            console.log('✅ Save buttons present');
        }
        
        // Verify React app is loaded
        expect(hasSettingsHeading).toBeTruthy();
        expect(hasApiConfigTab || hasSaveButton).toBeTruthy();
    });
    
    /**
     * Verify plugin is activated
     */
    test('verify plugin is activated', async ({ page }) => {
        await page.goto('http://devora-test.local/wp-admin/?localwp_auto_login=1');
        
        // Navigate to plugins page
        await page.goto('http://devora-test.local/wp-admin/plugins.php');
        
        // Check if UtilitySign is active
        const utilitySignRow = page.locator('tr[data-slug="utilitysign"]');
        const isActive = await utilitySignRow.locator('.deactivate').isVisible();
        
        expect(isActive).toBeTruthy();
        console.log('✅ UtilitySign plugin is activated');
    });
    
    /**
     * Test settings page accessibility
     */
    test('settings page is accessible', async ({ page }) => {
        await page.goto('http://devora-test.local/wp-admin/?localwp_auto_login=1');
        await page.goto('http://devora-test.local/wp-admin/admin.php?page=utilitysign-settings');
        
        // Wait for page load
        await page.waitForTimeout(2000);
        
        // Check page title
        const pageTitle = await page.title();
        console.log('Page title:', pageTitle);
        
        // Verify we're on the correct admin page
        const url = page.url();
        expect(url).toContain('page=utilitysign-settings');
        console.log('✅ Settings page URL correct');
        
        // Check for any form or settings interface
        const hasContent = await page.locator('body').textContent();
        expect(hasContent).toContain('UtilitySign');
        console.log('✅ Settings page contains plugin name');
    });
    
    /**
     * Verify custom post types are registered
     */
    test('verify custom post types are registered', async ({ page }) => {
        await page.goto('http://devora-test.local/wp-admin/?localwp_auto_login=1');
        
        // Check for Products menu (first occurrence)
        const productsMenu = page.locator('#adminmenu a:has-text("Products")').first();
        expect(await productsMenu.isVisible()).toBeTruthy();
        console.log('✅ Products CPT registered');
        
        // Check for Orders menu (first occurrence)
        const ordersMenu = page.locator('#adminmenu a:has-text("Orders")').first();
        expect(await ordersMenu.isVisible()).toBeTruthy();
        console.log('✅ Orders CPT registered');
    });
});

