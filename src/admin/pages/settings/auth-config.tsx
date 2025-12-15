import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/devora/Card';
import { Button } from '@/components/devora/Button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle, AlertCircle, Loader2, Shield, Key } from 'lucide-react';
import { useUtilitySignAPI } from '@/hooks/useUtilitySignAPI';

interface SecretInputState {
  mode: 'masked' | 'plain' | 'rotating';
  value: string;
  masked?: string;
  rotatedAt?: string | null;
}

const createSecretState = (secret: any): SecretInputState => {
  if (!secret) {
    return {
      mode: 'plain',
      value: '',
    };
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

interface AuthConfig {
  authMethod: 'entra_id' | 'jwt' | 'api_key';
  entraIdTenantId: string;
  entraIdClientId: string;
  entraIdClientSecret: SecretInputState;
  jwtSecret: SecretInputState;
  jwtExpiration: number;
  apiKey: SecretInputState;
  enableMFA: boolean;
  sessionTimeout: number;
  enableRememberMe: boolean;
  maxLoginAttempts: number;
  lockoutDuration: number;
}

interface AuthConfigProps {
  onSave?: (config: AuthConfig) => void;
  onTest?: (config: AuthConfig) => Promise<boolean>;
}

export const AuthConfig: React.FC<AuthConfigProps> = ({ onSave, onTest }) => {
  const { config, updateConfig, loading, error } = useUtilitySignAPI();
  const [formData, setFormData] = useState<AuthConfig>({
    authMethod: 'entra_id',
    entraIdTenantId: '',
    entraIdClientId: '',
    entraIdClientSecret: createSecretState(null),
    jwtSecret: createSecretState(null),
    jwtExpiration: 3600,
    apiKey: createSecretState(null),
    enableMFA: true,
    sessionTimeout: 1800,
    enableRememberMe: true,
    maxLoginAttempts: 5,
    lockoutDuration: 900,
  });
  const [isTesting, setIsTesting] = useState(false);
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  // Load existing configuration
  useEffect(() => {
    if (config?.auth) {
      setFormData(prev => ({
        ...prev,
        authMethod: config.auth.authMethod || 'entra_id',
        entraIdTenantId: config.auth.entraIdTenantId || '',
        entraIdClientId: config.auth.entraIdClientId || '',
        entraIdClientSecret: createSecretState(config.auth.entraIdClientSecret),
        jwtSecret: createSecretState(config.auth.jwtSecret),
        jwtExpiration: config.auth.jwtExpiration ?? 3600,
        apiKey: createSecretState(config.auth.apiKey),
        enableMFA: config.auth.enableMFA ?? true,
        sessionTimeout: config.auth.sessionTimeout ?? 1800,
        enableRememberMe: config.auth.enableRememberMe ?? true,
        maxLoginAttempts: config.auth.maxLoginAttempts ?? 5,
        lockoutDuration: config.auth.lockoutDuration ?? 900,
      }));
    }
  }, [config]);

  const handleInputChange = (field: keyof AuthConfig, value: string | number | boolean) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleSecretRotate = (field: keyof Pick<AuthConfig, 'entraIdClientSecret' | 'jwtSecret' | 'apiKey'>) => {
    setFormData(prev => ({
      ...prev,
      [field]: {
        mode: 'rotating',
        value: '',
      },
    }));
  };

  const handleSecretChange = (
    field: keyof Pick<AuthConfig, 'entraIdClientSecret' | 'jwtSecret' | 'apiKey'>,
    value: string
  ) => {
    setFormData(prev => ({
      ...prev,
      [field]: {
        mode: prev[field].mode,
        value,
        masked: prev[field].masked,
        rotatedAt: prev[field].rotatedAt,
      },
    }));
  };

  const buildPayload = () => {
    const payload: any = {
      authMethod: formData.authMethod,
      entraIdTenantId: formData.entraIdTenantId,
      entraIdClientId: formData.entraIdClientId,
      enableMFA: formData.enableMFA,
      sessionTimeout: formData.sessionTimeout,
      enableRememberMe: formData.enableRememberMe,
      maxLoginAttempts: formData.maxLoginAttempts,
      lockoutDuration: formData.lockoutDuration,
      jwtExpiration: formData.jwtExpiration,
    };

    const handleSecret = (key: 'entraIdClientSecret' | 'jwtSecret' | 'apiKey') => {
      const secret = formData[key];
      if (secret.mode === 'rotating') {
        payload[key] = {
          rotate: true,
          newValue: secret.value,
        };
      } else if (secret.mode === 'plain' && secret.value) {
        payload[key] = secret.value;
      }
    };

    handleSecret('entraIdClientSecret');
    handleSecret('jwtSecret');
    handleSecret('apiKey');

    return payload;
  };

  const handleTestAuth = async () => {
    if (!onTest) return;
    
    setIsTesting(true);
    setTestResult(null);
    
    try {
      const success = await onTest(formData);
      setTestResult({
        success,
        message: success 
          ? 'Authentication configuration test successful!' 
          : 'Authentication test failed. Please check your configuration.'
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
        await onSave(buildPayload() as any);
      } else if (updateConfig) {
        await updateConfig({ auth: buildPayload() as any });
      }
      
      setTestResult({
        success: true,
        message: 'Authentication configuration saved successfully!'
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

  const generateJWTSecret = () => {
    const secret = generateRandomSecret();
    handleSecretChange('jwtSecret', secret);
  };

  const generateAPIKey = () => {
    const key = generateRandomSecret();
    handleSecretChange('apiKey', key);
  };

  const generateRandomSecret = () => {
    const bytes = new Uint8Array(32);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes)
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');
  };

  if (loading) {
    return (
      <Card variant="white">
        <CardContent className="flex items-center justify-center py-8">
          <Loader2 className="h-8 w-8 animate-spin text-devora-primary" />
          <span className="ml-2 text-devora-text-primary">Loading authentication configuration...</span>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <Card variant="white">
        <CardHeader>
          <CardTitle className="text-devora-primary flex items-center gap-2">
            <Shield className="h-5 w-5" />
            Authentication Configuration
          </CardTitle>
          <CardDescription>
            Configure authentication methods and security settings for the UtilitySign plugin.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Authentication Method */}
          <div className="space-y-2">
            <Label htmlFor="authMethod" className="text-devora-text-primary font-ui font-bold">
              Authentication Method
            </Label>
            <Select
              value={formData.authMethod}
              onValueChange={(value: 'entra_id' | 'jwt' | 'api_key') => 
                handleInputChange('authMethod', value)
              }
            >
              <SelectTrigger className="devora-input">
                <SelectValue placeholder="Select authentication method" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="entra_id">Microsoft Entra ID (Recommended)</SelectItem>
                <SelectItem value="jwt">JWT Tokens</SelectItem>
                <SelectItem value="api_key">API Key</SelectItem>
              </SelectContent>
            </Select>
            <p className="text-sm text-devora-text-secondary">
              Choose the primary authentication method for admin access.
            </p>
          </div>

          <Separator />

          {/* Microsoft Entra ID Configuration */}
          {formData.authMethod === 'entra_id' && (
            <div className="space-y-4">
              <h3 className="text-lg font-heading font-black text-devora-primary">
                Microsoft Entra ID Settings
              </h3>
              
              <div className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="entraIdTenantId" className="text-devora-text-primary font-ui font-bold">
                    Tenant ID
                  </Label>
                  <Input
                    id="entraIdTenantId"
                    type="text"
                    value={formData.entraIdTenantId}
                    onChange={(e) => handleInputChange('entraIdTenantId', e.target.value)}
                    className="devora-input"
                    placeholder="3abba735-9db1-48c0-a5a8-2e4171bb5739"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="entraIdClientId" className="text-devora-text-primary font-ui font-bold">
                    Client ID
                  </Label>
                  <Input
                    id="entraIdClientId"
                    type="text"
                    value={formData.entraIdClientId}
                    onChange={(e) => handleInputChange('entraIdClientId', e.target.value)}
                    className="devora-input"
                    placeholder="7795ce22-88a1-4a62-99b9-54f125f5782a"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="entraIdClientSecret" className="text-devora-text-primary font-ui font-bold">
                    Client Secret
                  </Label>
                  <div className="flex gap-2">
                    <Input
                      id="entraIdClientSecret"
                      type="password"
                      value={formData.entraIdClientSecret.value}
                      onChange={(e) => handleSecretChange('entraIdClientSecret', e.target.value)}
                      className="devora-input"
                      placeholder={
                        formData.entraIdClientSecret.mode === 'masked'
                          ? formData.entraIdClientSecret.masked || 'Secret hidden'
                          : 'Enter your client secret'
                      }
                      disabled={formData.entraIdClientSecret.mode === 'masked'}
                      required={formData.entraIdClientSecret.mode !== 'masked'}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => handleSecretRotate('entraIdClientSecret')}
                      className="px-3"
                    >
                      Rotate
                    </Button>
                  </div>
                  {formData.entraIdClientSecret.rotatedAt && (
                    <p className="text-xs text-devora-text-secondary">
                      Last rotated: {new Date(formData.entraIdClientSecret.rotatedAt).toLocaleString()}
                    </p>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* JWT Configuration */}
          {formData.authMethod === 'jwt' && (
            <div className="space-y-4">
              <h3 className="text-lg font-heading font-black text-devora-primary">
                JWT Token Settings
              </h3>
              
              <div className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="jwtSecret" className="text-devora-text-primary font-ui font-bold">
                    JWT Secret
                  </Label>
                  <div className="flex gap-2">
                    <Input
                      id="jwtSecret"
                      type="password"
                      value={formData.jwtSecret.value}
                      onChange={(e) => handleSecretChange('jwtSecret', e.target.value)}
                      className="devora-input flex-1"
                      placeholder={
                        formData.jwtSecret.mode === 'masked'
                          ? formData.jwtSecret.masked || 'Secret hidden'
                          : 'Enter JWT secret key'
                      }
                      disabled={formData.jwtSecret.mode === 'masked'}
                      required={formData.jwtSecret.mode !== 'masked'}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      onClick={generateJWTSecret}
                      className="px-3"
                    >
                      <Key className="h-4 w-4" />
                    </Button>
                    {formData.jwtSecret.mode === 'masked' && (
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleSecretRotate('jwtSecret')}
                        className="px-3"
                      >
                        Rotate
                      </Button>
                    )}
                  </div>
                  {formData.jwtSecret.rotatedAt && (
                    <p className="text-xs text-devora-text-secondary">
                      Last rotated: {new Date(formData.jwtSecret.rotatedAt).toLocaleString()}
                    </p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="jwtExpiration" className="text-devora-text-primary font-ui font-bold">
                    Token Expiration (seconds)
                  </Label>
                  <Input
                    id="jwtExpiration"
                    type="number"
                    value={formData.jwtExpiration}
                    onChange={(e) => handleInputChange('jwtExpiration', parseInt(e.target.value) || 3600)}
                    className="devora-input"
                    min="300"
                    max="86400"
                    required
                  />
                </div>
              </div>
            </div>
          )}

          {/* API Key Configuration */}
          {formData.authMethod === 'api_key' && (
            <div className="space-y-4">
              <h3 className="text-lg font-heading font-black text-devora-primary">
                API Key Settings
              </h3>
              
              <div className="space-y-2">
                <Label htmlFor="apiKey" className="text-devora-text-primary font-ui font-bold">
                  API Key
                </Label>
                <div className="flex gap-2">
                  <Input
                    id="apiKey"
                    type="password"
                    value={formData.apiKey.value}
                    onChange={(e) => handleSecretChange('apiKey', e.target.value)}
                    className="devora-input flex-1"
                    placeholder={
                      formData.apiKey.mode === 'masked'
                        ? formData.apiKey.masked || 'API key hidden'
                        : 'Enter API key'
                    }
                    disabled={formData.apiKey.mode === 'masked'}
                    required={formData.apiKey.mode !== 'masked'}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    onClick={generateAPIKey}
                    className="px-3"
                  >
                    <Key className="h-4 w-4" />
                  </Button>
                  {formData.apiKey.mode === 'masked' && (
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => handleSecretRotate('apiKey')}
                      className="px-3"
                    >
                      Rotate
                    </Button>
                  )}
                </div>
                {formData.apiKey.rotatedAt && (
                  <p className="text-xs text-devora-text-secondary">
                    Last rotated: {new Date(formData.apiKey.rotatedAt).toLocaleString()}
                  </p>
                )}
              </div>
            </div>
          )}

          <Separator />

          {/* Security Settings */}
          <div className="space-y-4">
            <h3 className="text-lg font-heading font-black text-devora-primary">
              Security Settings
            </h3>
            
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="enableMFA" className="text-devora-text-primary font-ui font-bold">
                    Enable Multi-Factor Authentication
                  </Label>
                  <p className="text-sm text-devora-text-secondary">
                    Require MFA for admin access (recommended for production).
                  </p>
                </div>
                <Switch
                  id="enableMFA"
                  checked={formData.enableMFA}
                  onCheckedChange={(checked) => handleInputChange('enableMFA', checked)}
                />
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="enableRememberMe" className="text-devora-text-primary font-ui font-bold">
                    Enable Remember Me
                  </Label>
                  <p className="text-sm text-devora-text-secondary">
                    Allow users to stay logged in for extended periods.
                  </p>
                </div>
                <Switch
                  id="enableRememberMe"
                  checked={formData.enableRememberMe}
                  onCheckedChange={(checked) => handleInputChange('enableRememberMe', checked)}
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="sessionTimeout" className="text-devora-text-primary font-ui font-bold">
                    Session Timeout (seconds)
                  </Label>
                  <Input
                    id="sessionTimeout"
                    type="number"
                    value={formData.sessionTimeout}
                    onChange={(e) => handleInputChange('sessionTimeout', parseInt(e.target.value) || 1800)}
                    className="devora-input"
                    min="300"
                    max="86400"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="maxLoginAttempts" className="text-devora-text-primary font-ui font-bold">
                    Max Login Attempts
                  </Label>
                  <Input
                    id="maxLoginAttempts"
                    type="number"
                    value={formData.maxLoginAttempts}
                    onChange={(e) => handleInputChange('maxLoginAttempts', parseInt(e.target.value) || 5)}
                    className="devora-input"
                    min="3"
                    max="10"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="lockoutDuration" className="text-devora-text-primary font-ui font-bold">
                  Lockout Duration (seconds)
                </Label>
                <Input
                  id="lockoutDuration"
                  type="number"
                  value={formData.lockoutDuration}
                  onChange={(e) => handleInputChange('lockoutDuration', parseInt(e.target.value) || 900)}
                  className="devora-input"
                  min="60"
                  max="3600"
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
              onClick={handleTestAuth}
              disabled={isSaving || isTesting}
              className="flex-1 sm:flex-none"
            >
              {isTesting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Testing...
                </>
              ) : (
                'Test Authentication'
              )}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default AuthConfig;