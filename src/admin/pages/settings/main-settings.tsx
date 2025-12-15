import React, { useState } from 'react';
import { Card, CardContent } from '@/components/devora/Card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle, AlertCircle, Loader2, Palette, Database, Settings as SettingsIcon } from 'lucide-react';
import APIConfig from './api-config';
import ComponentConfig from './component-config';
import GeneralSettings from './general-settings';
import { useUtilitySignAPI } from '@/hooks/useUtilitySignAPI';

interface UtilitySignConfig {
  environment: 'staging' | 'production';
  apiUrl: string;
  clientId: string;
  clientSecret: string;
  enableBankID: boolean;
  enableEmailNotifications: boolean;
  enableDebugMode: boolean;
  auth?: {
    authMethod: 'entra_id' | 'jwt' | 'api_key';
    entraIdTenantId: string;
    entraIdClientId: string;
    entraIdClientSecret: string;
    jwtSecret: string;
    jwtExpiration: number;
    apiKey: string;
    enableMFA: boolean;
    sessionTimeout: number;
    enableRememberMe: boolean;
    maxLoginAttempts: number;
    lockoutDuration: number;
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

interface PartialUtilitySignConfig extends Partial<UtilitySignConfig> {
  environment?: 'staging' | 'production';
  apiUrl?: string;
  clientId?: string;
  clientSecret?: string;
  enableBankID?: boolean;
  enableEmailNotifications?: boolean;
  enableDebugMode?: boolean;
}

interface MainSettingsProps {
  onSave?: (config: UtilitySignConfig) => void;
  onTest?: (config: UtilitySignConfig) => Promise<boolean>;
  onPreview?: (config: UtilitySignConfig) => void;
}

export const MainSettings: React.FC<MainSettingsProps> = ({ onSave, onTest, onPreview }) => {
  const { config, updateConfig, loading, error } = useUtilitySignAPI();
  const [activeTab, setActiveTab] = useState('general');
  const [isSaving, setIsSaving] = useState(false);
  const [saveResult, setSaveResult] = useState<{ success: boolean; message: string } | null>(null);

  // Handle API configuration save
  const handleAPISave = async (apiConfig: PartialUtilitySignConfig) => {
    setIsSaving(true);
    setSaveResult(null);
    
    try {
      if (onSave && config) {
        await onSave({ ...config, ...apiConfig } as UtilitySignConfig);
      } else if (updateConfig) {
        await updateConfig(apiConfig);
      }
      
      setSaveResult({
        success: true,
        message: 'API configuration saved successfully!'
      });
    } catch (err) {
      setSaveResult({
        success: false,
        message: `Save failed: ${err instanceof Error ? err.message : 'Unknown error'}`
      });
    } finally {
      setIsSaving(false);
    }
  };

  // Handle component configuration save
  const handleComponentSave = async (componentConfig: any) => {
    setIsSaving(true);
    setSaveResult(null);
    
    try {
      if (onSave && config) {
        const updatedConfig = { ...config, components: componentConfig };
        await onSave(updatedConfig as UtilitySignConfig);
      } else if (updateConfig) {
        await updateConfig({ components: componentConfig });
      }
      
      setSaveResult({
        success: true,
        message: 'Component configuration saved successfully!'
      });
    } catch (err) {
      setSaveResult({
        success: false,
        message: `Save failed: ${err instanceof Error ? err.message : 'Unknown error'}`
      });
    } finally {
      setIsSaving(false);
    }
  };

  // Handle API connection test
  const handleAPITest = async (apiConfig: any): Promise<boolean> => {
    try {
      if (onTest) {
        return await onTest(apiConfig);
      }
      
      // Default test implementation
      const response = await fetch(`${apiConfig.apiUrl}/api/health`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-Client-ID': apiConfig.clientId,
        },
      });
      
      return response.ok;
    } catch (err) {
      console.error('API test failed:', err);
      return false;
    }
  };

  // Handle component preview
  const handleComponentPreview = async (componentConfig: any) => {
    if (onPreview) {
      await onPreview(componentConfig);
    } else {
      // Default preview implementation
      console.log('Previewing component configuration:', componentConfig);
    }
  };

  // Handle general settings save
  const handleGeneralSave = async (generalSettings: any) => {
    setIsSaving(true);
    setSaveResult(null);
    
    try {
      if (onSave && config) {
        const updatedConfig = { ...config, general: generalSettings };
        await onSave(updatedConfig as UtilitySignConfig);
      } else if (updateConfig) {
        await updateConfig({ general: generalSettings });
      }
      
      setSaveResult({
        success: true,
        message: 'General settings saved successfully!'
      });
    } catch (err) {
      setSaveResult({
        success: false,
        message: `Save failed: ${err instanceof Error ? err.message : 'Unknown error'}`
      });
    } finally {
      setIsSaving(false);
    }
  };

  if (loading) {
    return (
      <Card variant="white">
        <CardContent className="flex items-center justify-center py-8">
          <Loader2 className="h-8 w-8 animate-spin text-devora-primary" />
          <span className="ml-2 text-devora-text-primary">Loading settings...</span>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="utilitysign-admin-settings space-y-6">
      {/* Header */}
      <div className="space-y-2">
        <h1 className="text-3xl font-heading font-black text-devora-primary">
          UtilitySign Settings
        </h1>
        <p className="text-devora-text-secondary">
          Configure your UtilitySign plugin settings, API connections, and customization options.
        </p>
      </div>

      {/* Global Save Result Alert */}
      {saveResult && (
        <Alert className={saveResult.success ? 'border-green-500 bg-green-50' : 'border-red-500 bg-red-50'}>
          {saveResult.success ? (
            <CheckCircle className="h-4 w-4 text-green-600" />
          ) : (
            <AlertCircle className="h-4 w-4 text-red-600" />
          )}
          <AlertDescription className={saveResult.success ? 'text-green-800' : 'text-red-800'}>
            {saveResult.message}
          </AlertDescription>
        </Alert>
      )}

      {/* Error Display */}
      {error && (
        <Alert className="border-red-500 bg-red-50">
          <AlertCircle className="h-4 w-4 text-red-600" />
          <AlertDescription className="text-red-800">
            {error}
          </AlertDescription>
        </Alert>
      )}

      {/* Settings Tabs */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
        <TabsList className="grid w-full grid-cols-3">
          <TabsTrigger value="general" className="flex items-center gap-2">
            <SettingsIcon className="h-4 w-4" />
            General Settings
          </TabsTrigger>
          <TabsTrigger value="api" className="flex items-center gap-2">
            <Database className="h-4 w-4" />
            API Configuration
          </TabsTrigger>
          <TabsTrigger value="components" className="flex items-center gap-2">
            <Palette className="h-4 w-4" />
            Components
          </TabsTrigger>
        </TabsList>

        <TabsContent value="general" className="space-y-6">
          <GeneralSettings
            onSave={handleGeneralSave}
          />
        </TabsContent>

        <TabsContent value="api" className="space-y-6">
          <APIConfig
            onSave={handleAPISave}
            onTest={handleAPITest}
          />
        </TabsContent>

        <TabsContent value="components" className="space-y-6">
          <ComponentConfig
            onSave={handleComponentSave}
            onPreview={handleComponentPreview}
          />
        </TabsContent>
      </Tabs>
    </div>
  );
};

export default MainSettings;