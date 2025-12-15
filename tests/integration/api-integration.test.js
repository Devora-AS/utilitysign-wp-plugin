import { test, expect } from '@playwright/test';

test.describe('UtilitySign API Integration', () => {
    test.beforeEach(async ({ page }) => {
        // Mock the API responses
        await page.route('**/api/v1/health', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    message: 'API is healthy',
                    timestamp: new Date().toISOString()
                })
            });
        });

        await page.route('**/api/v1/documents/*', async route => {
            const url = new URL(route.request().url());
            const documentId = url.pathname.split('/').pop();
            
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        id: documentId,
                        title: 'Test Document',
                        content: 'This is a test document for signing.',
                        status: 'draft',
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString()
                    }
                })
            });
        });

        await page.route('**/api/v1/signing-requests', async route => {
            if (route.request().method() === 'POST') {
                await route.fulfill({
                    status: 201,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        success: true,
                        data: {
                            id: 'req-123',
                            document_id: 'test-doc-123',
                            signer_email: 'john@example.com',
                            signer_name: 'John Doe',
                            status: 'pending',
                            created_at: new Date().toISOString(),
                            expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString()
                        }
                    })
                });
            }
        });

        await page.route('**/api/v1/bankid/initiate', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        session_id: 'bankid-session-123',
                        redirect_url: 'https://bankid.no/sign?session=bankid-session-123'
                    }
                })
            });
        });

        await page.route('**/api/v1/bankid/status/*', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        session_id: 'bankid-session-123',
                        status: 'completed',
                        completed_at: new Date().toISOString()
                    }
                })
            });
        });
    });

    test('can load document from API', async ({ page }) => {
        await page.goto('http://localhost:8080');
        
        // Create a test page with the shortcode
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with the shortcode
        await page.fill('#title', 'Test API Integration');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-api-integration');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if document ID is displayed (indicating API call was made)
        await expect(page.locator('text=test-doc-123')).toBeVisible();
    });

    test('can create signing request via API', async ({ page }) => {
        await page.goto('http://localhost:8080');
        
        // Create a test page with the shortcode
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with the shortcode
        await page.fill('#title', 'Test Signing Request');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-signing-request');
        
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

    test('can initiate BankID authentication via API', async ({ page }) => {
        await page.goto('http://localhost:8080');
        
        // Create a test page with the shortcode
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with the shortcode
        await page.fill('#title', 'Test BankID Integration');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123" enable_bank_id="true"]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-bankid-integration');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Fill out the form
        await page.fill('input[type="text"]', 'John Doe');
        await page.fill('input[type="email"]', 'john@example.com');
        
        // Enable BankID
        await page.check('input[type="checkbox"][name="enableBankID"]');
        
        // Submit the form
        await page.click('button[type="submit"]');
        
        // Wait for API call to complete
        await page.waitForTimeout(2000);
        
        // Check if BankID redirect URL is shown
        await expect(page.locator('text=bankid.no')).toBeVisible();
    });

    test('handles API errors gracefully', async ({ page }) => {
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

        await page.goto('http://localhost:8080');
        
        // Create a test page with the shortcode
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with the shortcode
        await page.fill('#title', 'Test API Error Handling');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-api-error-handling');
        
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
        await expect(page.locator('text=Error: Internal server error')).toBeVisible();
    });

    test('validates API configuration', async ({ page }) => {
        // Mock invalid API configuration
        await page.route('**/api/v1/health', async route => {
            await route.fulfill({
                status: 401,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: false,
                    error: 'Unauthorized'
                })
            });
        });

        await page.goto('http://localhost:8080');
        
        // Create a test page with the shortcode
        await page.goto('http://localhost:8080/wp-admin/post-new.php?post_type=page');
        
        // Login if needed
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        
        // Create a new page with the shortcode
        await page.fill('#title', 'Test API Configuration');
        await page.fill('#content', '[utilitysign_signing_form document_id="test-doc-123"]');
        await page.click('#publish');
        
        // View the page
        await page.goto('http://localhost:8080/test-api-configuration');
        
        // Wait for React to mount
        await page.waitForSelector('.utilitysign-signing-form', { timeout: 10000 });
        
        // Check if configuration error is shown
        await expect(page.locator('text=API configuration error')).toBeVisible();
    });
});
