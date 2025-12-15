import React, { createContext, useContext, useEffect, useState } from 'react';
import { APIClient } from '../lib/api-client';

// Create Context for the APIClient instance
const APIClientContext = createContext<APIClient | null>(null);

// Polling configuration
const POLL_INTERVAL_MS = 100; // Check every 100ms
const MAX_POLL_ATTEMPTS = 10; // 10 attempts = 1 second total
const TIMEOUT_MS = MAX_POLL_ATTEMPTS * POLL_INTERVAL_MS;

interface APIClientProviderProps {
  children: React.ReactNode;
}

/**
 * APIClientProvider component
 * 
 * Initializes the APIClient instance after WordPress has loaded the configuration
 * (window.utilitySignFrontend). Uses polling/retry logic to handle race conditions
 * where the config might not be available immediately.
 */
export const APIClientProvider: React.FC<APIClientProviderProps> = ({ children }) => {
  const [client, setClient] = useState<APIClient | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isInitializing, setIsInitializing] = useState(true);

  useEffect(() => {
    let pollAttempts = 0;
    let pollTimeout: NodeJS.Timeout | null = null;

    const checkAndInitialize = () => {
      // Check if window.utilitySignFrontend (frontend) or window.utilitySign (admin) is available
      const frontendConfig = typeof window !== 'undefined' ? (window as any).utilitySignFrontend : null;
      const adminConfig = typeof window !== 'undefined' ? (window as any).utilitySign : null;
      const hasConfig = frontendConfig || adminConfig;
      
      if (hasConfig) {
        try {
          const instance = new APIClient(APIClient.getEnvironmentConfig());
          setClient(instance);
          setError(null);
          setIsInitializing(false);
          
          // Log successful initialization (only in debug mode)
          const debugMode = new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
          if (debugMode) {
            console.log('[APIClientProvider] APIClient initialized successfully');
          }
          
          // Clear any pending poll timeout
          if (pollTimeout) {
            clearTimeout(pollTimeout);
          }
          return true; // Success
        } catch (err) {
          const errorMessage = err instanceof Error ? err.message : 'Failed to initialize APIClient';
          console.error('[APIClientProvider] Error initializing APIClient:', errorMessage);
          setError(errorMessage);
          setIsInitializing(false);
          return false; // Failed
        }
      }

      // Config not available yet, continue polling
      pollAttempts++;
      
      if (pollAttempts >= MAX_POLL_ATTEMPTS) {
        // Timeout reached
        const errorMessage = `UtilitySign configuration not loaded after ${TIMEOUT_MS}ms. Please reload the page or contact support.`;
        console.error('[APIClientProvider]', errorMessage);
        setError(errorMessage);
        setIsInitializing(false);
        return false; // Failed
      }

      // Schedule next poll attempt
      pollTimeout = setTimeout(checkAndInitialize, POLL_INTERVAL_MS);
      return false; // Still polling
    };

    // Start checking immediately
    checkAndInitialize();

    // Cleanup function
    return () => {
      if (pollTimeout) {
        clearTimeout(pollTimeout);
      }
    };
  }, []); // Run only once on mount

  // Loading state: show simple loading message
  if (isInitializing) {
    return (
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '20px',
        color: '#666',
        fontSize: '14px'
      }}>
        <span>Loading UtilitySign...</span>
      </div>
    );
  }

  // Error state: show friendly error message
  if (error || !client) {
    return (
      <div style={{
        padding: '20px',
        margin: '20px',
        backgroundColor: '#f8d7da',
        border: '1px solid #dc3545',
        borderRadius: '4px',
        color: '#721c24'
      }}>
        <strong>Error:</strong> {error || 'Failed to initialize UtilitySign. Please reload the page or contact support.'}
      </div>
    );
  }

  // Success: render children with context
  return (
    <APIClientContext.Provider value={client}>
      {children}
    </APIClientContext.Provider>
  );
};

/**
 * Hook to use the APIClient instance in components
 * 
 * @throws {Error} If used outside of APIClientProvider
 * @returns {APIClient} The APIClient instance
 */
export const useAPIClient = (): APIClient => {
  const client = useContext(APIClientContext);
  
  if (!client) {
    throw new Error('useAPIClient must be used within an APIClientProvider. Make sure your component is wrapped with <APIClientProvider>.');
  }
  
  return client;
};

