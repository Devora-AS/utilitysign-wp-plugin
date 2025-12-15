import React from 'react';
import ReactDOM from 'react-dom/client';
import SigningForm from '@/components/signing/SigningForm';
import { ThemeProvider } from "@/components/theme-provider";
import { APIClientProvider } from "@/components/APIClientProvider";
import { ComponentSettingsProvider, useComponentTheme } from "@/components/ComponentSettingsProvider";

/**
 * Mount the SigningForm React component into a DOM element
 * 
 * @param {HTMLElement} element - The DOM element to mount the component into
 * @param {Object} props - Props to pass to the SigningForm component
 */
export function mountSigningForm(element, props) {
    const debugMode = typeof window !== 'undefined' && 
        new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    
    if (!element) {
        if (debugMode) {
            console.error('UtilitySign: No element provided for mounting SigningForm');
        }
        return;
    }

    try {
        const root = ReactDOM.createRoot(element);
        
        // Inner component to access ComponentSettingsProvider context
        const AppContent = () => {
            const componentTheme = useComponentTheme();
            return (
                <ThemeProvider defaultTheme="light" storageKey="vite-ui-theme" componentTheme={componentTheme}>
                    <APIClientProvider>
                        <React.StrictMode>
                            <SigningForm {...props} />
                        </React.StrictMode>
                    </APIClientProvider>
                </ThemeProvider>
            );
        };
        
        root.render(
            <ComponentSettingsProvider>
                <AppContent />
            </ComponentSettingsProvider>
        );
    } catch (error) {
        if (debugMode) {
            console.error('UtilitySign: Error mounting SigningForm component:', error);
        }
        element.innerHTML = `
            <div class="utilitysign-error" style="color: #dc3545; padding: 15px; border: 1px solid #dc3545; border-radius: 4px; background-color: #f8d7da; text-align: center;">
                <strong>Error:</strong> Failed to load the signing form component. Please refresh the page and try again.
            </div>
        `;
    }
}