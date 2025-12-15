import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/devora/Card';
import { Button } from '@/components/devora/Button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle, AlertCircle, Loader2 } from 'lucide-react';
import { useUtilitySignAPI } from '@/hooks/useUtilitySignAPI';

interface SecretInputState {
  mode: 'masked' | 'plain' | 'rotating';
  value: string;
  masked?: string;
  rotatedAt?: string | null;
}

const initialSecretState: SecretInputState = {
  mode: 'masked',
  value: '',
};

interface APIConfig {
  environment: 'staging' | 'production';
  apiUrl: string;
  clientId: string;
  clientSecret: string | SecretInputState;
  pluginKey: string | SecretInputState;
  pluginSecret: string | SecretInputState;
  enableBankID: boolean;
  enableEmailNotifications: boolean;
  enableDebugMode: boolean;
  criipto?: {
    clientId: string;
    clientSecret: string | SecretInputState;
    domain: string;
    environment: 'test' | 'production';
  };
}

interface APIConfigProps {
  onSave?: (config: APIConfig) => void;
  onTest?: (config: APIConfig) => Promise<boolean>;
}

export const APIConfig: React.FC<APIConfigProps> = ({ onSave, onTest }) => {
  const { config, updateConfig, loading, error } = useUtilitySignAPI();
  const [formData, setFormData] = useState<APIConfig>({
    environment: 'staging',
    apiUrl: '',
    clientId: '',
    clientSecret: initialSecretState,
    pluginKey: initialSecretState,
    pluginSecret: initialSecretState,
    enableBankID: true,
    enableEmailNotifications: true,
    enableDebugMode: false,
    criipto: {
      clientId: '',
      clientSecret: initialSecretState,
      domain: '',
      environment: 'test',
    },
  });
  const [isTesting, setIsTesting] = useState(false);
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  // Load existing configuration
  useEffect(() => {
    if (config) {
      setFormData({
        environment: config.environment || 'staging',
        apiUrl: config.apiUrl || '',
        clientId: config.clientId || '',
        clientSecret: formatSecret(config.clientSecret),
        pluginKey: formatSecret((config as any).pluginKey),
        pluginSecret: formatSecret((config as any).pluginSecret),
        enableBankID: config.enableBankID ?? true,
        enableEmailNotifications: config.enableEmailNotifications ?? true,
        enableDebugMode: config.enableDebugMode ?? false,
        criipto: {
          clientId: config.criipto?.clientId || '',
          clientSecret: formatSecret(config.criipto?.clientSecret),
          domain: config.criipto?.domain || '',
          environment: config.criipto?.environment || 'test',
        },
      });
    }
  }, [config]);

  // Update API URL based on environment
  useEffect(() => {
    if (formData.environment === 'production') {
      setFormData(prev => ({
        ...prev,
        apiUrl: 'https://api.utilitysign.devora.no'
      }));
    } else {
      setFormData(prev => ({
        ...prev,
        apiUrl: 'https://api-staging.utilitysign.devora.no'
      }));
    }
  }, [formData.environment]);

  const handleInputChange = (field: keyof APIConfig, value: string | boolean) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleSecretRotate = () => {
    setFormData(prev => ({
      ...prev,
      clientSecret: {
        mode: 'rotating',
        value: '',
      },
    }));
  };

  const handlePluginKeyRotate = () => {
    setFormData(prev => ({
      ...prev,
      pluginKey: {
        mode: 'rotating',
        value: '',
      },
    }));
  };

  const handlePluginKeyChange = (value: string) => {
    setFormData(prev => ({
      ...prev,
      pluginKey: {
        mode: prev.pluginKey.mode === 'masked' ? 'plain' : prev.pluginKey.mode,
        value,
        masked: prev.pluginKey.masked,
        rotatedAt: prev.pluginKey.rotatedAt,
      },
    }));
  };

  const handlePluginSecretRotate = () => {
    setFormData(prev => ({
      ...prev,
      pluginSecret: {
        mode: 'rotating',
        value: '',
      },
    }));
  };

  const handlePluginSecretChange = (value: string) => {
    setFormData(prev => ({
      ...prev,
      pluginSecret: {
        mode: prev.pluginSecret.mode === 'masked' ? 'plain' : prev.pluginSecret.mode,
        value,
        masked: prev.pluginSecret.masked,
        rotatedAt: prev.pluginSecret.rotatedAt,
      },
    }));
  };

  const handleSecretChange = (value: string) => {
    setFormData(prev => ({
      ...prev,
      clientSecret: {
        mode: prev.clientSecret.mode,
        value,
        masked: prev.clientSecret.masked,
        rotatedAt: prev.clientSecret.rotatedAt,
      },
    }));
  };

  const formatSecret = (secret: any): SecretInputState => {
    if (!secret) {
      return { ...initialSecretState };
    }

    if (typeof secret === 'string') {
      return {
        mode: 'plain',
        value: secret,
      };
    }

    return {
      mode: 'masked',
      value: '',
      masked: secret.masked,
      rotatedAt: secret.rotatedAt ?? null,
    };
  };

  const buildPayload = () => {
    const payload: any = {
      environment: formData.environment,
      apiUrl: formData.apiUrl,
      clientId: formData.clientId,
      enableBankID: formData.enableBankID,
      enableEmailNotifications: formData.enableEmailNotifications,
      enableDebugMode: formData.enableDebugMode,
      criipto: {
        clientId: formData.criipto?.clientId || '',
        domain: formData.criipto?.domain || '',
        environment: formData.criipto?.environment || 'test',
      },
    };

    // Handle UtilitySign API Client Secret
    if (formData.clientSecret.mode === 'rotating') {
      payload.clientSecret = {
        rotate: true,
        newValue: formData.clientSecret.value,
      };
    } else if (formData.clientSecret.mode === 'plain' && formData.clientSecret.value) {
      payload.clientSecret = formData.clientSecret.value;
    }

    // Handle plugin key
    if (formData.pluginKey.mode === 'rotating') {
      payload.pluginKey = {
        rotate: true,
        newValue: formData.pluginKey.value,
      };
    } else if (formData.pluginKey.mode === 'plain' && formData.pluginKey.value) {
      payload.pluginKey = formData.pluginKey.value;
    }

    // Handle plugin secret (required for backend API authentication)
    if (formData.pluginSecret.mode === 'rotating') {
      payload.pluginSecret = {
        rotate: true,
        newValue: formData.pluginSecret.value,
      };
    } else if (formData.pluginSecret.mode === 'plain' && formData.pluginSecret.value) {
      payload.pluginSecret = formData.pluginSecret.value;
    }

    // Handle Criipto Client Secret
    if (formData.criipto?.clientSecret) {
      const criiptoSecret = formData.criipto.clientSecret;
      if (typeof criiptoSecret === 'object') {
        if (criiptoSecret.mode === 'rotating' || criiptoSecret.mode === 'plain') {
          payload.criipto.clientSecret = {
            rotate: criiptoSecret.mode === 'rotating',
            newValue: criiptoSecret.value,
          };
        }
      } else if (typeof criiptoSecret === 'string' && criiptoSecret) {
        payload.criipto.clientSecret = criiptoSecret;
      }
    }

    return payload;
  };

  const handleTestConnection = async () => {
    if (!onTest) return;
    
    setIsTesting(true);
    setTestResult(null);
    
    try {
      const success = await onTest(formData as any);
      setTestResult({
        success,
        message: success 
          ? 'API connection successful!' 
          : 'API connection failed. Please check your configuration.'
      });
    } catch (err) {
      setTestResult({
        success: false,
        message: `Test failed: ${err instanceof Error ? err.message : 'Unknown error'}`
      });
    } finally {
      setIsTesting(false);
    }
  };

  const handleSave = async () => {
    setIsSaving(true);
    
    try {
      if (onSave) {
        await onSave(buildPayload());
      } else if (updateConfig) {
        await updateConfig(buildPayload());
      }
      
      setTestResult({
        success: true,
        message: 'Configuration saved successfully!'
      });
    } catch (err) {
      setTestResult({
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
          <span className="ml-2 text-devora-text-primary">Loading configuration...</span>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      {/* SECTION 1: UtilitySign API Configuration (Azure Backend) */}
      <Card variant="white">
        <CardHeader>
          <CardTitle className="text-devora-primary text-2xl">
            UtilitySign API Configuration
          </CardTitle>
          <CardDescription className="text-base">
            Configure your Azure backend API connection. These credentials connect your WordPress plugin to the UtilitySign cloud service for order processing and document management.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Environment Selection */}
          <div className="space-y-2">
            <Label htmlFor="environment" className="text-devora-text-primary font-ui font-bold">
              Environment
            </Label>
            <Select
              value={formData.environment}
              onValueChange={(value: 'staging' | 'production') => 
                handleInputChange('environment', value)
              }
            >
              <SelectTrigger className="devora-input">
                <SelectValue placeholder="Select environment" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="staging">Staging (Development)</SelectItem>
                <SelectItem value="production">Production</SelectItem>
              </SelectContent>
            </Select>
            <p className="text-sm text-devora-text-secondary">
              {formData.environment === 'staging' 
                ? 'Use staging environment for development and testing.'
                : 'Use production environment for live operations.'
              }
            </p>
          </div>

          <Separator />

          {/* API URL */}
          <div className="space-y-2">
            <Label htmlFor="apiUrl" className="text-devora-text-primary font-ui font-bold">
              API URL
            </Label>
            <Input
              id="apiUrl"
              type="url"
              value={formData.apiUrl}
              onChange={(e) => handleInputChange('apiUrl', e.target.value)}
              className="devora-input"
              placeholder="https://api.utilitysign.devora.no"
              required
            />
            <p className="text-sm text-devora-text-secondary">
              The base URL for the UtilitySign API endpoint.
            </p>
          </div>

          {/* UtilitySign API Credentials */}
          <div className="bg-devora-background-light p-4 rounded-lg space-y-4">
            <h4 className="font-heading font-black text-devora-primary-dark">
              Azure Backend API Credentials
            </h4>
            <p className="text-sm text-devora-text-secondary mb-4">
              These credentials are provided by Devora and connect to the UtilitySign cloud API for order processing, PDF generation, and document storage.
            </p>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="clientId" className="text-devora-text-primary font-ui font-bold">
                  API Client ID
                </Label>
                <Input
                  id="clientId"
                  type="text"
                  value={formData.clientId}
                  onChange={(e) => handleInputChange('clientId', e.target.value)}
                  className="devora-input"
                  placeholder="e.g., 7a8b9c1d-2e3f-4a5b-6c7d-8e9f0a1b2c3d"
                  required
                />
                <p className="text-xs text-devora-text-secondary">
                  Provided by Devora support
                </p>
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="clientSecret" className="text-devora-text-primary font-ui font-bold">
                  API Client Secret
                </Label>
                <div className="flex gap-2 items-center">
                  <Input
                    id="clientSecret"
                    type="password"
                    value={formData.clientSecret.value}
                    onChange={(e) => handleSecretChange(e.target.value)}
                    className="devora-input"
                    placeholder={
                      formData.clientSecret.mode === 'masked'
                        ? formData.clientSecret.masked || 'Secret hidden'
                        : 'Enter your API client secret'
                    }
                    disabled={formData.clientSecret.mode === 'masked'}
                    required={formData.clientSecret.mode !== 'masked'}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    onClick={handleSecretRotate}
                    title="Rotate secret"
                  >
                    Rotate
                  </Button>
                </div>
                {formData.clientSecret.rotatedAt && (
                  <p className="text-xs text-devora-text-secondary">
                    Last rotated: {new Date(formData.clientSecret.rotatedAt).toLocaleString()}
                  </p>
                )}
                <p className="text-xs text-devora-text-secondary">
                  Provided by Devora support
                </p>
              </div>

              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="pluginKey" className="text-devora-text-primary font-ui font-bold">
                  UtilitySign Plugin Key
                </Label>
                <div className="flex gap-2 items-center">
                  <Input
                    id="pluginKey"
                    type={formData.pluginKey.mode === 'masked' ? 'password' : 'text'}
                    value={formData.pluginKey.mode === 'masked' ? '' : formData.pluginKey.value}
                    onChange={(e) => handlePluginKeyChange(e.target.value)}
                    className="devora-input"
                    placeholder={
                      formData.pluginKey.mode === 'masked'
                        ? formData.pluginKey.masked || 'Plugin key hidden'
                        : 'wp_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'
                    }
                    disabled={formData.pluginKey.mode === 'masked'}
                    required={formData.pluginKey.mode !== 'masked'}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    onClick={handlePluginKeyRotate}
                    title="Update plugin key"
                  >
                    Update Key
                  </Button>
                </div>
                <p className="text-xs text-devora-text-secondary">
                  Plugin keys always start with <code>wp_</code> and are issued in the UtilitySign dashboard.
                </p>
              </div>

              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="pluginSecret" className="text-devora-text-primary font-ui font-bold">
                  UtilitySign Plugin Secret
                </Label>
                <div className="flex gap-2 items-center">
                  <Input
                    id="pluginSecret"
                    type="password"
                    value={formData.pluginSecret.value}
                    onChange={(e) => handlePluginSecretChange(e.target.value)}
                    className="devora-input"
                    placeholder={
                      formData.pluginSecret.mode === 'masked'
                        ? formData.pluginSecret.masked || 'Secret hidden'
                        : 'Enter your plugin secret'
                    }
                    disabled={formData.pluginSecret.mode === 'masked'}
                    required={formData.pluginSecret.mode !== 'masked'}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    onClick={handlePluginSecretRotate}
                    title="Rotate plugin secret"
                  >
                    Rotate
                  </Button>
                </div>
                {formData.pluginSecret.rotatedAt && (
                  <p className="text-xs text-devora-text-secondary">
                    Last rotated: {new Date(formData.pluginSecret.rotatedAt).toLocaleString()}
                  </p>
                )}
                <p className="text-xs text-devora-text-secondary">
                  Required for backend API authentication. Provided by Devora support along with the Plugin Key.
                </p>
              </div>
            </div>
          </div>

          <Separator />

          {/* Feature Toggles */}
          <div className="space-y-4">
            <h3 className="text-lg font-heading font-black text-devora-primary">
              Feature Settings
            </h3>
            
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="enableBankID" className="text-devora-text-primary font-ui font-bold">
                    Enable BankID Integration
                  </Label>
                  <p className="text-sm text-devora-text-secondary">
                    Allow users to sign documents using BankID authentication.
                  </p>
                </div>
                <Switch
                  id="enableBankID"
                  checked={formData.enableBankID}
                  onCheckedChange={(checked) => handleInputChange('enableBankID', checked)}
                />
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="enableEmailNotifications" className="text-devora-text-primary font-ui font-bold">
                    Enable Email Notifications
                  </Label>
                  <p className="text-sm text-devora-text-secondary">
                    Send email notifications for signing requests and completions.
                  </p>
                </div>
                <Switch
                  id="enableEmailNotifications"
                  checked={formData.enableEmailNotifications}
                  onCheckedChange={(checked) => handleInputChange('enableEmailNotifications', checked)}
                />
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="enableDebugMode" className="text-devora-text-primary font-ui font-bold">
                    Enable Debug Mode
                  </Label>
                  <p className="text-sm text-devora-text-secondary">
                    Enable detailed logging and debug information (development only).
                  </p>
                </div>
                <Switch
                  id="enableDebugMode"
                  checked={formData.enableDebugMode}
                  onCheckedChange={(checked) => handleInputChange('enableDebugMode', checked)}
                />
              </div>
            </div>
          </div>

          {/* Test Result Alert */}
          {testResult && (
            <Alert className={testResult.success ? 'border-green-500 bg-green-50' : 'border-red-500 bg-red-50'}>
              {testResult.success ? (
                <CheckCircle className="h-4 w-4 text-green-600" />
              ) : (
                <AlertCircle className="h-4 w-4 text-red-600" />
              )}
              <AlertDescription className={testResult.success ? 'text-green-800' : 'text-red-800'}>
                {testResult.message}
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

          {/* Action Buttons */}
          <div className="flex flex-col sm:flex-row gap-3 pt-4">
            <Button
              variant="primary"
              onClick={handleSave}
              disabled={isSaving || isTesting}
              className="flex-1 sm:flex-none"
            >
              {isSaving ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Saving...
                </>
              ) : (
                'Save Configuration'
              )}
            </Button>
            
            <Button
              variant="secondary"
              onClick={handleTestConnection}
              disabled={isSaving || isTesting || !formData.apiUrl || !formData.clientId}
              className="flex-1 sm:flex-none"
            >
              {isTesting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Testing...
                </>
              ) : (
                'Test Connection'
              )}
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* SECTION 2: Criipto BankID Configuration */}
      <Card variant="white">
        <CardHeader>
          <CardTitle className="text-devora-primary text-2xl">
            Criipto BankID Configuration
          </CardTitle>
          <CardDescription className="text-base">
            Configure Norwegian BankID integration for digital document signing. These credentials are obtained from your Criipto account at <a href="https://dashboard.criipto.com" target="_blank" rel="noopener noreferrer" className="text-devora-primary underline">dashboard.criipto.com</a>
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Criipto Environment */}
          <div className="space-y-2">
            <Label htmlFor="criiptoEnvironment" className="text-devora-text-primary font-ui font-bold">
              Criipto Environment
            </Label>
            <Select
              value={formData.criipto?.environment || 'test'}
              onValueChange={(value: 'test' | 'production') => 
                setFormData(prev => ({
                  ...prev,
                  criipto: { ...prev.criipto!, environment: value }
                }))
              }
            >
              <SelectTrigger className="devora-input">
                <SelectValue placeholder="Select Criipto environment" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="test">Test (Staging)</SelectItem>
                <SelectItem value="production">Production</SelectItem>
              </SelectContent>
            </Select>
            <p className="text-sm text-devora-text-secondary">
              {formData.criipto?.environment === 'test' 
                ? 'Use test environment for development with Criipto test users.'
                : 'Use production environment for real BankID signing.'
              }
            </p>
          </div>

          <Separator />

          {/* Criipto Credentials */}
          <div className="bg-amber-50 border border-amber-200 p-4 rounded-lg space-y-4">
            <h4 className="font-heading font-black text-devora-primary-dark flex items-center gap-2">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              BankID Application Credentials
            </h4>
            <p className="text-sm text-devora-text-secondary mb-4">
              Create a Criipto application at <strong>dashboard.criipto.com</strong> → Applications → Create Application. Choose "Norwegian BankID" as the authentication method. You'll find these credentials in your application's settings.
            </p>
            
            <div className="space-y-4">
              {/* Criipto Domain */}
              <div className="space-y-2">
                <Label htmlFor="criiptoDomain" className="text-devora-text-primary font-ui font-bold">
                  Criipto Domain
                </Label>
                <Input
                  id="criiptoDomain"
                  type="text"
                  value={formData.criipto?.domain || ''}
                  onChange={(e) => setFormData(prev => ({
                    ...prev,
                    criipto: { ...prev.criipto!, domain: e.target.value }
                  }))}
                  className="devora-input"
                  placeholder="e.g., your-company.criipto.id"
                  required
                />
                <p className="text-xs text-devora-text-secondary">
                  Your Criipto domain from dashboard → Settings → Domain
                </p>
              </div>

              {/* Criipto Client ID */}
              <div className="space-y-2">
                <Label htmlFor="criiptoClientId" className="text-devora-text-primary font-ui font-bold">
                  Criipto Client ID
                </Label>
                <Input
                  id="criiptoClientId"
                  type="text"
                  value={formData.criipto?.clientId || ''}
                  onChange={(e) => setFormData(prev => ({
                    ...prev,
                    criipto: { ...prev.criipto!, clientId: e.target.value }
                  }))}
                  className="devora-input"
                  placeholder="e.g., urn:my:application:identifier:1234"
                  required
                />
                <p className="text-xs text-devora-text-secondary">
                  Found in Criipto dashboard → Your Application → Settings → Client ID
                </p>
              </div>
              
              {/* Criipto Client Secret */}
              <div className="space-y-2">
                <Label htmlFor="criiptoClientSecret" className="text-devora-text-primary font-ui font-bold">
                  Criipto Client Secret
                </Label>
                <div className="flex gap-2 items-center">
                  <Input
                    id="criiptoClientSecret"
                    type="password"
                    value={typeof formData.criipto?.clientSecret === 'string' 
                      ? formData.criipto.clientSecret 
                      : (formData.criipto?.clientSecret as SecretInputState)?.value || ''
                    }
                    onChange={(e) => setFormData(prev => ({
                      ...prev,
                      criipto: { 
                        ...prev.criipto!, 
                        clientSecret: {
                          mode: 'plain',
                          value: e.target.value
                        }
                      }
                    }))}
                    className="devora-input"
                    placeholder={
                      typeof formData.criipto?.clientSecret === 'object' && formData.criipto.clientSecret.mode === 'masked'
                        ? (formData.criipto.clientSecret as SecretInputState).masked || 'Secret hidden'
                        : 'Enter your Criipto client secret'
                    }
                    disabled={typeof formData.criipto?.clientSecret === 'object' && formData.criipto.clientSecret.mode === 'masked'}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setFormData(prev => ({
                      ...prev,
                      criipto: { 
                        ...prev.criipto!, 
                        clientSecret: {
                          mode: 'rotating',
                          value: ''
                        }
                      }
                    }))}
                    title="Rotate Criipto secret"
                  >
                    Rotate
                  </Button>
                </div>
                <p className="text-xs text-devora-text-secondary">
                  Found in Criipto dashboard → Your Application → Settings → Client Secret
                </p>
              </div>
            </div>
          </div>

          {/* Test Result Alert */}
          {testResult && (
            <Alert className={testResult.success ? 'border-green-500 bg-green-50' : 'border-red-500 bg-red-50'}>
              {testResult.success ? (
                <CheckCircle className="h-4 w-4 text-green-600" />
              ) : (
                <AlertCircle className="h-4 w-4 text-red-600" />
              )}
              <AlertDescription className={testResult.success ? 'text-green-800' : 'text-red-800'}>
                {testResult.message}
              </AlertDescription>
            </Alert>
          )}

          {/* Save All Button */}
          <div className="flex flex-col sm:flex-row gap-3 pt-4">
            <Button
              variant="primary"
              onClick={handleSave}
              disabled={isSaving}
              className="flex-1 sm:flex-none"
            >
              {isSaving ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Saving...
                </>
              ) : (
                'Save All Changes'
              )}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default APIConfig;