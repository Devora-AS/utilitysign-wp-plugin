import { useState, useEffect, useCallback } from 'react';
import { useAPIClient } from '@/components/APIClientProvider';
import { APIClient } from '@/lib/api-client';

interface MaskedSecret {
  masked: string;
  rotatedAt?: string | null;
  rotatedBy?: number | null;
}

interface UtilitySignConfig {
  environment: 'staging' | 'production';
  apiUrl: string;
  clientId: string;
  clientSecret: string | MaskedSecret | null;
  pluginKey: string | MaskedSecret | null;
  enableBankID: boolean;
  enableEmailNotifications: boolean;
  enableDebugMode: boolean;
  auth?: {
    authMethod: 'entra_id' | 'jwt' | 'api_key';
    entraIdTenantId: string;
    entraIdClientId: string;
    entraIdClientSecret: string | MaskedSecret | null;
    jwtSecret: string | MaskedSecret | null;
    jwtExpiration: number;
    apiKey: string | MaskedSecret | null;
    enableMFA: boolean;
    sessionTimeout: number;
    enableRememberMe: boolean;
    maxLoginAttempts: number;
    lockoutDuration: number;
  };
  criipto?: {
    clientId: string;
    clientSecret: string | MaskedSecret | null;
    domain: string;
    environment: 'test' | 'production';
    webhookSecret: string | MaskedSecret | null;
    enableWebhooks: boolean;
    redirectUri: string;
    acrValues: string;
    uiLocales: string;
    loginHint: string;
  };
  components?: {
    theme: 'light' | 'dark' | 'auto';
    primaryColor: string;
    secondaryColor: string;
    accentColor: string;
    borderRadius: 'none' | 'sm' | 'md' | 'lg' | 'xl' | 'devora';
    fontFamily: 'lato' | 'open-sans' | 'inter' | 'system';
    fontSize: 'sm' | 'base' | 'lg' | 'xl';
    buttonStyle: 'devora' | 'modern' | 'minimal';
    cardStyle: 'devora' | 'modern' | 'minimal';
    enableAnimations: boolean;
    enableShadows: boolean;
    enableGradients: boolean;
    customCSS: string;
    logoUrl: string;
    faviconUrl: string;
    enableCustomBranding: boolean;
  };
}

interface UseUtilitySignAPIResult {
  config: UtilitySignConfig | null;
  loading: boolean;
  error: string | null;
  updateConfig: (newConfig: Partial<UtilitySignConfig>) => Promise<void>;
  testConnection: (config: UtilitySignConfig) => Promise<boolean>;
  saveSettings: (settings: Partial<UtilitySignConfig>) => Promise<void>;
  loadSettings: () => Promise<void>;
  apiClient: APIClient;
}

export const useUtilitySignAPI = (): UseUtilitySignAPIResult => {
  const apiClient = useAPIClient();
  const [config, setConfig] = useState<UtilitySignConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Load configuration from WordPress
  const loadSettings = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      // Get WordPress AJAX URL and nonce
      const ajaxUrl = (window as any).utilitySign?.ajaxUrl || '/wp-admin/admin-ajax.php';
      const nonce = (window as any).utilitySign?.nonce || '';

      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'utilitysign_get_settings',
          nonce: nonce,
        }),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        setConfig(data.data);
      } else {
        throw new Error(data.data || 'Failed to load settings');
      }
    } catch (err) {
      console.error('Failed to load settings:', err);
      setError(err instanceof Error ? err.message : 'Failed to load settings');
      
      // Set default configuration if loading fails
      const defaultConfig = (window as any).utilitySign?.defaultConfig || {
        environment: 'staging',
        apiUrl: 'https://api-staging.utilitysign.devora.no',
        clientId: 'default-client-id',
        clientSecret: null,
        pluginKey: null,
        enableBankID: true,
        enableEmailNotifications: true,
        enableDebugMode: false,
        auth: {
          authMethod: 'entra_id',
          entraIdTenantId: 'default-tenant-id',
          entraIdClientId: 'default-client-id',
          entraIdClientSecret: null,
          jwtSecret: null,
          jwtExpiration: 3600,
          apiKey: null,
          enableMFA: true,
          sessionTimeout: 1800,
          enableRememberMe: true,
          maxLoginAttempts: 5,
          lockoutDuration: 900,
        },
        criipto: {
          clientId: '',
          clientSecret: null,
          domain: '',
          environment: 'test',
          webhookSecret: null,
          enableWebhooks: true,
          redirectUri: '',
          acrValues: 'urn:grn:authn:no:bankid',
          uiLocales: 'no',
          loginHint: '',
        },
        components: {
          theme: 'light',
          primaryColor: '#3432A6',
          secondaryColor: '#968AB6',
          accentColor: '#FFFADE',
          borderRadius: 'devora',
          fontFamily: 'lato',
          fontSize: 'base',
          buttonStyle: 'devora',
          cardStyle: 'devora',
          enableAnimations: true,
          enableShadows: true,
          enableGradients: true,
          customCSS: '',
          logoUrl: '',
          faviconUrl: '',
          enableCustomBranding: false,
        },
      };
      setConfig(defaultConfig);
    } finally {
      setLoading(false);
    }
  }, []);

  // Update configuration
  const updateConfig = useCallback(async (newConfig: Partial<UtilitySignConfig>) => {
    try {
      setError(null);

      // Get WordPress AJAX URL and nonce
      const ajaxUrl = (window as any).utilitySign?.ajaxUrl || '/wp-admin/admin-ajax.php';
      const nonce = (window as any).utilitySign?.nonce || '';

      const payload = JSON.stringify(preparePayload(newConfig));

      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'utilitysign_update_settings',
          nonce: nonce,
          settings: payload,
        }),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        // CRITICAL FIX: Reload settings from backend after successful update
        // This ensures the state always matches what's stored in WordPress
        // and prevents issues with merging encrypted secrets
        await loadSettings();
      } else {
        throw new Error(data.data || 'Failed to update settings');
      }
    } catch (err) {
      console.error('Failed to update settings:', err);
      setError(err instanceof Error ? err.message : 'Failed to update settings');
      throw err;
    }
  }, [loadSettings]);

  // Test API connection
  const testConnection = useCallback(async (config: UtilitySignConfig): Promise<boolean> => {
    try {
      const response = await fetch(`${config.apiUrl}/api/health`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-Client-ID': config.clientId,
        },
      });

      return response.ok;
    } catch (err) {
      console.error('API connection test failed:', err);
      return false;
    }
  }, []);

  // Save settings (alias for updateConfig)
  const saveSettings = useCallback(async (settings: Partial<UtilitySignConfig>) => {
    return updateConfig(settings);
  }, [updateConfig]);

  // Load settings on mount
  useEffect(() => {
    // Set default config immediately to prevent "Client ID is required" error
    const defaultConfig = (window as any).utilitySign?.defaultConfig || {
      environment: 'staging',
      apiUrl: 'https://api-staging.utilitysign.devora.no',
      clientId: 'default-client-id',
      clientSecret: null,
      pluginKey: null,
      enableBankID: true,
      enableEmailNotifications: true,
      enableDebugMode: false,
      auth: {
        authMethod: 'entra_id',
        entraIdTenantId: 'default-tenant-id',
        entraIdClientId: 'default-client-id',
        entraIdClientSecret: null,
        jwtSecret: null,
        jwtExpiration: 3600,
        apiKey: null,
        enableMFA: true,
        sessionTimeout: 1800,
        enableRememberMe: true,
        maxLoginAttempts: 5,
        lockoutDuration: 900,
      },
      criipto: {
        clientId: '',
        clientSecret: null,
        domain: '',
        environment: 'test',
        webhookSecret: null,
        enableWebhooks: true,
        redirectUri: '',
        acrValues: 'urn:grn:authn:no:bankid',
        uiLocales: 'no',
        loginHint: '',
      },
      components: {
        theme: 'light',
        primaryColor: '#3432A6',
        secondaryColor: '#968AB6',
        accentColor: '#FFFADE',
        borderRadius: 'devora',
        fontFamily: 'lato',
        fontSize: 'base',
        buttonStyle: 'devora',
        cardStyle: 'devora',
        enableAnimations: true,
        enableShadows: true,
        enableGradients: true,
        customCSS: '',
        logoUrl: '',
        faviconUrl: '',
        enableCustomBranding: false,
      },
    };
    setConfig(defaultConfig);
    setLoading(false);
    
    // Then try to load real settings
    loadSettings();
  }, [loadSettings]);

  const preparePayload = (settings: Partial<UtilitySignConfig>) => {
    const clone: any = JSON.parse(JSON.stringify(settings));

    const handleSecret = (obj: any, key: string) => {
      if (!obj || typeof obj !== 'object' || !(key in obj)) {
        return;
      }

      const value = obj[key];

      if (value === null || value === undefined) {
        delete obj[key];
        return;
      }

      if (typeof value === 'string' && value.length > 0) {
        obj[key] = value;
        return;
      }

      if (typeof value === 'object' && 'rotate' in value) {
        obj[key] = {
          rotate: true,
          newValue: value.newValue || '',
        };
        return;
      }

      delete obj[key];
    };

    handleSecret(clone, 'clientSecret');
    handleSecret(clone, 'pluginKey');

    if (clone.auth) {
      handleSecret(clone.auth, 'entraIdClientSecret');
      handleSecret(clone.auth, 'jwtSecret');
      handleSecret(clone.auth, 'apiKey');
    }

    if (clone.criipto) {
      handleSecret(clone.criipto, 'clientSecret');
      handleSecret(clone.criipto, 'webhookSecret');
    }

    return clone;
  };

  const mergeConfig = (prev: UtilitySignConfig | null, update: Partial<UtilitySignConfig>) => {
    if (!prev) {
      return update as UtilitySignConfig;
    }

    const merged: any = { ...prev };

    const deepMerge = (target: any, source: any) => {
      if (!source) {
        return;
      }

      Object.keys(source).forEach(key => {
        const value = source[key];

        if (value === null || value === undefined) {
          return;
        }

        // CRITICAL FIX: Always replace instead of merge when source value is non-object
        // This prevents trying to merge string secrets with object secrets
        if (typeof value !== 'object' || Array.isArray(value)) {
          target[key] = value;
          return;
        }

        // For objects, check if target is also an object before deep merge
        if (typeof target[key] !== 'object' || target[key] === null || Array.isArray(target[key])) {
          target[key] = value;
        } else {
          deepMerge(target[key], value);
        }
      });
    };

    deepMerge(merged, update);
    return merged as UtilitySignConfig;
  };

  return {
    config,
    loading,
    error,
    updateConfig,
    testConnection,
    saveSettings,
    loadSettings,
    apiClient,
  };
};

export default useUtilitySignAPI;