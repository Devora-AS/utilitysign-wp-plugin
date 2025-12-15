import React from "react";
import ReactDOM from "react-dom/client";
import "./index.css";
import "../styles/devora-design-system.css";
import { RouterProvider } from "react-router-dom";
import { router } from "./routes";
import { ThemeProvider } from "@/components/theme-provider";
import { APIClientProvider } from "@/components/APIClientProvider";
import { ComponentSettingsProvider, useComponentTheme } from "@/components/ComponentSettingsProvider";
import { mountSigningForm } from "./components/SigningFormMount";

// Mount the main React app
const el = document.getElementById("utilitysign-frontend");

if (el) {
  // Inner component to access ComponentSettingsProvider context
  const AppContent = () => {
    const componentTheme = useComponentTheme();
    return (
      <ThemeProvider defaultTheme="light" storageKey="vite-ui-theme" componentTheme={componentTheme}>
        <APIClientProvider>
          <React.StrictMode>
            <RouterProvider router={router} />
          </React.StrictMode>
        </APIClientProvider>
      </ThemeProvider>
    );
  };
  
  ReactDOM.createRoot(el).render(
    <ComponentSettingsProvider>
      <AppContent />
    </ComponentSettingsProvider>
  );
}

// Make the signing form mounting function globally available
if (typeof window !== 'undefined') {
  window.utilitySignMountSigningForm = mountSigningForm;
}

// Auto-initialize all signing form and order form shortcodes on the page
function initializeSigningForms() {
  // Look for both signing forms and order forms
  const formContainers = document.querySelectorAll('.utilitysign-signing-form, .utilitysign-order-form');
  
  if (formContainers.length > 0) {
    console.log(`[UtilitySign] Found ${formContainers.length} form(s) to initialize`);
    
    formContainers.forEach((container) => {
      // Check if this is an order form (API product) or signing form
      const isOrderForm = container.classList.contains('utilitysign-order-form');
      const isApiProduct = container.dataset.isApiProduct === 'true';
      
      // Apply defaults: if attribute is not present (undefined), default to true
      // If attribute is explicitly 'false', use false
      const enableBankIdAttr = container.dataset.enableBankId;
      const enableBankId = enableBankIdAttr !== undefined
        ? enableBankIdAttr === 'true'
        : true; // Default to true if attribute not present
      
      const enableEmailNotificationsAttr = container.dataset.enableEmailNotifications;
      const enableEmailNotifications = enableEmailNotificationsAttr !== undefined
        ? enableEmailNotificationsAttr === 'true'
        : true; // Default to true if attribute not present
      
      const props = {
        documentId: container.dataset.documentId || '',
        productId: container.dataset.productId || '',
        supplierId: container.dataset.supplierId || '',
        isApiProduct: isApiProduct,
        enableBankId: enableBankId,
        enableEmailNotifications: enableEmailNotifications,
      };
      
      const formType = isOrderForm ? 'order form' : 'signing form';
      console.log(`[UtilitySign] Mounting ${formType} with props:`, props);
      mountSigningForm(container, props);
    });
  }
}

// Global error handlers
if (typeof window !== 'undefined') {
  const debugMode = new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
  
  // Always handle unhandled promise rejections (not just in debug mode)
  window.addEventListener('unhandledrejection', (event) => {
    // Always log the error details (not just in debug mode) to help identify issues
    const errorDetails = {
      reason: event.reason,
      reasonType: typeof event.reason,
      reasonString: event.reason?.toString?.() || String(event.reason),
      reasonMessage: event.reason?.message || (event.reason instanceof Error ? event.reason.message : undefined),
      reasonName: event.reason?.name || (event.reason instanceof Error ? event.reason.name : undefined),
      stack: event.reason?.stack,
      promise: event.promise,
      timestamp: new Date().toISOString()
    };
    
    if (debugMode) {
      console.error('[UtilitySign] Unhandled promise rejection:', errorDetails);
      // Also log the full error object for inspection
      console.error('[UtilitySign] Full error object:', event.reason);
    } else {
      // In production, still log a summary but don't expose full details
      console.warn('[UtilitySign] Unhandled promise rejection:', errorDetails.reasonString || errorDetails.reasonMessage || 'Unknown error');
    }
    
    // Prevent the default behavior to stop console error
    // The error is already logged above
    event.preventDefault();
  });
  
  // Handle general errors
  window.addEventListener('error', (event) => {
    if (debugMode) {
      console.error('[UtilitySign] Global error:', {
        message: event.message,
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        error: event.error,
        timestamp: new Date().toISOString()
      });
    }
  });
  
  if (debugMode) {
    console.log('[UtilitySign] Debug mode enabled - verbose logging active');
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeSigningForms);
} else {
  // DOM is already loaded
  initializeSigningForms();
}
