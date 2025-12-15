/**
 * Admin Interface Test Suite
 * 
 * This file contains basic tests to verify the WordPress admin interface functionality.
 * Run these tests in the browser console when on the UtilitySign admin page.
 */

class UtilitySignAdminTests {
  constructor() {
    this.tests = [];
    this.results = [];
  }

  // Test 1: Check if React app is mounted
  testReactAppMounted() {
    const appElement = document.getElementById('utilitysign');
    const hasApp = appElement && appElement.children.length > 0;
    
    this.addTest('React App Mounted', hasApp, 
      hasApp ? 'React app successfully mounted' : 'React app not found or empty'
    );
  }

  // Test 2: Check if settings components are rendered
  testSettingsComponents() {
    const settingsElement = document.querySelector('.utilitysign-admin-settings');
    const hasSettings = !!settingsElement;
    
    this.addTest('Settings Components Rendered', hasSettings,
      hasSettings ? 'Settings components found' : 'Settings components not found'
    );
  }

  // Test 3: Check if tabs are working
  testTabsFunctionality() {
    const tabsList = document.querySelector('[role="tablist"]');
    const tabTriggers = document.querySelectorAll('[role="tab"]');
    const tabContents = document.querySelectorAll('[role="tabpanel"]');
    
    const hasTabs = tabsList && tabTriggers.length > 0 && tabContents.length > 0;
    
    this.addTest('Tabs Functionality', hasTabs,
      hasTabs ? `Found ${tabTriggers.length} tabs and ${tabContents.length} tab panels` : 'Tabs not found'
    );
  }

  // Test 4: Check if forms are present
  testFormElements() {
    const inputs = document.querySelectorAll('input, select, textarea');
    const buttons = document.querySelectorAll('button');
    
    const hasForms = inputs.length > 0 && buttons.length > 0;
    
    this.addTest('Form Elements Present', hasForms,
      hasForms ? `Found ${inputs.length} form inputs and ${buttons.length} buttons` : 'Form elements not found'
    );
  }

  // Test 5: Check if Devora design system classes are applied
  testDevoraDesignSystem() {
    const devoraButtons = document.querySelectorAll('.devora-button');
    const devoraCards = document.querySelectorAll('.devora-card');
    const devoraInputs = document.querySelectorAll('.devora-input');
    
    const hasDevoraStyles = devoraButtons.length > 0 || devoraCards.length > 0 || devoraInputs.length > 0;
    
    this.addTest('Devora Design System Applied', hasDevoraStyles,
      hasDevoraStyles ? `Found ${devoraButtons.length} buttons, ${devoraCards.length} cards, ${devoraInputs.length} inputs` : 'Devora design system classes not found'
    );
  }

  // Test 6: Check if WordPress AJAX is available
  testWordPressAJAX() {
    const hasUtilitySign = typeof window.utilitySign !== 'undefined';
    const hasAjaxUrl = hasUtilitySign && window.utilitySign.ajaxUrl;
    const hasNonce = hasUtilitySign && window.utilitySign.nonce;
    
    this.addTest('WordPress AJAX Available', hasAjaxUrl && hasNonce,
      hasAjaxUrl && hasNonce ? 'WordPress AJAX URL and nonce available' : 'WordPress AJAX not properly configured'
    );
  }

  // Test 7: Check if API configuration form is working
  testAPIConfiguration() {
    const environmentSelect = document.querySelector('select[value*="staging"], select[value*="production"]');
    const apiUrlInput = document.querySelector('input[type="url"]');
    const clientIdInput = document.querySelector('input[placeholder*="client"], input[placeholder*="Client"]');
    
    const hasAPIConfig = environmentSelect || apiUrlInput || clientIdInput;
    
    this.addTest('API Configuration Form', hasAPIConfig,
      hasAPIConfig ? 'API configuration form elements found' : 'API configuration form not found'
    );
  }

  // Test 8: Check if authentication configuration is present
  testAuthConfiguration() {
    const authMethodSelect = document.querySelector('select option[value*="entra"], select option[value*="jwt"]');
    const tenantIdInput = document.querySelector('input[placeholder*="tenant"], input[placeholder*="Tenant"]');
    
    const hasAuthConfig = authMethodSelect || tenantIdInput;
    
    this.addTest('Authentication Configuration', hasAuthConfig,
      hasAuthConfig ? 'Authentication configuration form found' : 'Authentication configuration form not found'
    );
  }

  // Test 9: Check if component customization is present
  testComponentCustomization() {
    const colorInputs = document.querySelectorAll('input[type="color"]');
    const themeSelect = document.querySelector('select option[value*="light"], select option[value*="dark"]');
    const customCSS = document.querySelector('textarea[placeholder*="CSS"], textarea[placeholder*="css"]');
    
    const hasComponentConfig = colorInputs.length > 0 || themeSelect || customCSS;
    
    this.addTest('Component Customization', hasComponentConfig,
      hasComponentConfig ? 'Component customization form found' : 'Component customization form not found'
    );
  }

  // Test 10: Check if error handling is working
  testErrorHandling() {
    const alertElements = document.querySelectorAll('[role="alert"], .alert');
    const errorElements = document.querySelectorAll('.text-red-600, .text-red-800');
    
    const hasErrorHandling = alertElements.length > 0 || errorElements.length > 0;
    
    this.addTest('Error Handling Elements', hasErrorHandling,
      hasErrorHandling ? 'Error handling elements found' : 'Error handling elements not found'
    );
  }

  addTest(name, passed, message) {
    this.tests.push({ name, passed, message });
    this.results.push({
      test: name,
      status: passed ? 'PASS' : 'FAIL',
      message: message
    });
  }

  runAllTests() {
    console.log('🧪 Running UtilitySign Admin Interface Tests...\n');
    
    this.testReactAppMounted();
    this.testSettingsComponents();
    this.testTabsFunctionality();
    this.testFormElements();
    this.testDevoraDesignSystem();
    this.testWordPressAJAX();
    this.testAPIConfiguration();
    this.testAuthConfiguration();
    this.testComponentCustomization();
    this.testErrorHandling();
    
    this.displayResults();
  }

  displayResults() {
    const passed = this.results.filter(r => r.status === 'PASS').length;
    const failed = this.results.filter(r => r.status === 'FAIL').length;
    const total = this.results.length;
    
    console.log(`\n📊 Test Results: ${passed}/${total} passed, ${failed} failed\n`);
    
    this.results.forEach(result => {
      const icon = result.status === 'PASS' ? '✅' : '❌';
      console.log(`${icon} ${result.test}: ${result.message}`);
    });
    
    if (failed === 0) {
      console.log('\n🎉 All tests passed! The admin interface is working correctly.');
    } else {
      console.log(`\n⚠️  ${failed} test(s) failed. Please check the implementation.`);
    }
  }
}

// Auto-run tests when script is loaded
if (typeof window !== 'undefined') {
  window.UtilitySignAdminTests = UtilitySignAdminTests;
  
  // Run tests after a short delay to allow React to render
  setTimeout(() => {
    const tester = new UtilitySignAdminTests();
    tester.runAllTests();
  }, 2000);
}

// Export for manual testing
if (typeof module !== 'undefined' && module.exports) {
  module.exports = UtilitySignAdminTests;
}
