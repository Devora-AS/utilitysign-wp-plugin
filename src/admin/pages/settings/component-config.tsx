import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/devora/Card';
import { Button } from '@/components/devora/Button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle, AlertCircle, Loader2, Palette, Layout, Eye } from 'lucide-react';
import { useUtilitySignAPI } from '@/hooks/useUtilitySignAPI';

interface ComponentConfig {
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
}

interface ComponentConfigProps {
  onSave?: (config: ComponentConfig) => void;
  onPreview?: (config: ComponentConfig) => void;
}

export const ComponentConfig: React.FC<ComponentConfigProps> = ({ onSave, onPreview }) => {
  const { config, updateConfig, loading, error } = useUtilitySignAPI();
  const [formData, setFormData] = useState<ComponentConfig>({
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
  });
  const [isSaving, setIsSaving] = useState(false);
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [saveResult, setSaveResult] = useState<{ success: boolean; message: string } | null>(null);

  // Load existing configuration
  useEffect(() => {
    if (config?.components) {
      setFormData(prev => ({
        ...prev,
        ...config.components
      }));
    }
  }, [config]);

  const handleInputChange = (field: keyof ComponentConfig, value: string | boolean) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleSave = async () => {
    setIsSaving(true);
    setSaveResult(null);
    
    try {
      if (onSave) {
        await onSave(formData);
      } else if (updateConfig) {
        await updateConfig({ components: formData });
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

  const handlePreview = async () => {
    if (!onPreview) return;
    
    setIsPreviewing(true);
    
    try {
      await onPreview(formData);
    } catch (err) {
      console.error('Preview failed:', err);
    } finally {
      setIsPreviewing(false);
    }
  };

  const resetToDefaults = () => {
    setFormData({
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
    });
  };

  if (loading) {
    return (
      <Card variant="white">
        <CardContent className="flex items-center justify-center py-8">
          <Loader2 className="h-8 w-8 animate-spin text-devora-primary" />
          <span className="ml-2 text-devora-text-primary">Loading component configuration...</span>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <Card variant="white">
        <CardHeader>
          <CardTitle className="text-devora-primary flex items-center gap-2">
            <Palette className="h-5 w-5" />
            Component Customization
          </CardTitle>
          <CardDescription>
            Customize the appearance and behavior of UtilitySign components to match your brand.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Theme Settings */}
          <div className="space-y-4">
            <h3 className="text-lg font-heading font-black text-devora-primary">
              Theme Settings
            </h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="theme" className="text-devora-text-primary font-ui font-bold">
                  Theme Mode
                </Label>
                <Select
                  value={formData.theme}
                  onValueChange={(value: 'light' | 'dark' | 'auto') => 
                    handleInputChange('theme', value)
                  }
                >
                  <SelectTrigger className="devora-input">
                    <SelectValue placeholder="Select theme" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="light">Light</SelectItem>
                    <SelectItem value="dark">Dark</SelectItem>
                    <SelectItem value="auto">Auto (System)</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="fontFamily" className="text-devora-text-primary font-ui font-bold">
                  Font Family
                </Label>
                <Select
                  value={formData.fontFamily}
                  onValueChange={(value: 'lato' | 'open-sans' | 'inter' | 'system') => 
                    handleInputChange('fontFamily', value)
                  }
                >
                  <SelectTrigger className="devora-input">
                    <SelectValue placeholder="Select font family" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="lato">Lato (Devora)</SelectItem>
                    <SelectItem value="open-sans">Open Sans</SelectItem>
                    <SelectItem value="inter">Inter</SelectItem>
                    <SelectItem value="system">System Default</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>

          <Separator />

          {/* Color Settings */}
          <div className="space-y-4">
            <h3 className="text-lg font-heading font-black text-devora-primary">
              Color Palette
            </h3>
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label htmlFor="primaryColor" className="text-devora-text-primary font-ui font-bold">
                  Primary Color
                </Label>
                <div className="flex gap-2">
                  <Input
                    id="primaryColor"
                    type="color"
                    value={formData.primaryColor}
                    onChange={(e) => handleInputChange('primaryColor', e.target.value)}
                    className="w-16 h-10 p-1 border rounded"
                  />
                  <Input
                    type="text"
                    value={formData.primaryColor}
                    onChange={(e) => handleInputChange('primaryColor', e.target.value)}
                    className="devora-input flex-1"
                    placeholder="#3432A6"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="secondaryColor" className="text-devora-text-primary font-ui font-bold">
                  Secondary Color
                </Label>
                <div className="flex gap-2">
                  <Input
                    id="secondaryColor"
                    type="color"
                    value={formData.secondaryColor}
                    onChange={(e) => handleInputChange('secondaryColor', e.target.value)}
                    className="w-16 h-10 p-1 border rounded"
                  />
                  <Input
                    type="text"
                    value={formData.secondaryColor}
                    onChange={(e) => handleInputChange('secondaryColor', e.target.value)}
                    className="devora-input flex-1"
                    placeholder="#968AB6"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="accentColor" className="text-devora-text-primary font-ui font-bold">
                  Accent Color
                </Label>
                <div className="flex gap-2">
                  <Input
                    id="accentColor"
                    type="color"
                    value={formData.accentColor}
                    onChange={(e) => handleInputChange('accentColor', e.target.value)}
                    className="w-16 h-10 p-1 border rounded"
                  />
                  <Input
                    type="text"
                    value={formData.accentColor}
                    onChange={(e) => handleInputChange('accentColor', e.target.value)}
                    className="devora-input flex-1"
                    placeholder="#FFFADE"
                  />
                </div>
              </div>
            </div>
          </div>

          <Separator />

          {/* Component Styles */}
          <div className="space-y-4">
            <h3 className="text-lg font-heading font-black text-devora-primary">
              Component Styles
            </h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="buttonStyle" className="text-devora-text-primary font-ui font-bold">
                  Button Style
                </Label>
                <Select
                  value={formData.buttonStyle}
                  onValueChange={(value: 'devora' | 'modern' | 'minimal') => 
                    handleInputChange('buttonStyle', value)
                  }
                >
                  <SelectTrigger className="devora-input">
                    <SelectValue placeholder="Select button style" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="devora">Devora Style</SelectItem>
                    <SelectItem value="modern">Modern</SelectItem>
                    <SelectItem value="minimal">Minimal</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="cardStyle" className="text-devora-text-primary font-ui font-bold">
                  Card Style
                </Label>
                <Select
                  value={formData.cardStyle}
                  onValueChange={(value: 'devora' | 'modern' | 'minimal') => 
                    handleInputChange('cardStyle', value)
                  }
                >
                  <SelectTrigger className="devora-input">
                    <SelectValue placeholder="Select card style" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="devora">Devora Style</SelectItem>
                    <SelectItem value="modern">Modern</SelectItem>
                    <SelectItem value="minimal">Minimal</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="borderRadius" className="text-devora-text-primary font-ui font-bold">
                Border Radius
              </Label>
              <Select
                value={formData.borderRadius}
                onValueChange={(value: 'none' | 'sm' | 'md' | 'lg' | 'xl' | 'devora') => 
                  handleInputChange('borderRadius', value)
                }
              >
                <SelectTrigger className="devora-input">
                  <SelectValue placeholder="Select border radius" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">None</SelectItem>
                  <SelectItem value="sm">Small</SelectItem>
                  <SelectItem value="md">Medium</SelectItem>
                  <SelectItem value="lg">Large</SelectItem>
                  <SelectItem value="xl">Extra Large</SelectItem>
                  <SelectItem value="devora">Devora (21.5px)</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <Separator />

          {/* Visual Effects */}
          <div className="space-y-4">
            <h3 className="text-lg font-heading font-black text-devora-primary">
              Visual Effects
            </h3>
            
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="enableAnimations" className="text-devora-text-primary font-ui font-bold">
                    Enable Animations
                  </Label>
                  <p className="text-sm text-devora-text-secondary">
                    Enable smooth transitions and animations for better user experience.
                  </p>
                </div>
                <Switch
                  id="enableAnimations"
                  checked={formData.enableAnimations}
                  onCheckedChange={(checked) => handleInputChange('enableAnimations', checked)}
                />
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="enableShadows" className="text-devora-text-primary font-ui font-bold">
                    Enable Shadows
                  </Label>
                  <p className="text-sm text-devora-text-secondary">
                    Add depth and visual hierarchy with subtle shadows.
                  </p>
                </div>
                <Switch
                  id="enableShadows"
                  checked={formData.enableShadows}
                  onCheckedChange={(checked) => handleInputChange('enableShadows', checked)}
                />
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="enableGradients" className="text-devora-text-primary font-ui font-bold">
                    Enable Gradients
                  </Label>
                  <p className="text-sm text-devora-text-secondary">
                    Use gradient backgrounds for enhanced visual appeal.
                  </p>
                </div>
                <Switch
                  id="enableGradients"
                  checked={formData.enableGradients}
                  onCheckedChange={(checked) => handleInputChange('enableGradients', checked)}
                />
              </div>
            </div>
          </div>

          <Separator />

          {/* Custom Branding */}
          <div className="space-y-4">
            <h3 className="text-lg font-heading font-black text-devora-primary">
              Custom Branding
            </h3>
            
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label htmlFor="enableCustomBranding" className="text-devora-text-primary font-ui font-bold">
                  Enable Custom Branding
                </Label>
                <p className="text-sm text-devora-text-secondary">
                  Override default Devora branding with your own assets.
                </p>
              </div>
              <Switch
                id="enableCustomBranding"
                checked={formData.enableCustomBranding}
                onCheckedChange={(checked) => handleInputChange('enableCustomBranding', checked)}
              />
            </div>

            {formData.enableCustomBranding && (
              <div className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="logoUrl" className="text-devora-text-primary font-ui font-bold">
                    Logo URL
                  </Label>
                  <Input
                    id="logoUrl"
                    type="url"
                    value={formData.logoUrl}
                    onChange={(e) => handleInputChange('logoUrl', e.target.value)}
                    className="devora-input"
                    placeholder="https://example.com/logo.png"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="faviconUrl" className="text-devora-text-primary font-ui font-bold">
                    Favicon URL
                  </Label>
                  <Input
                    id="faviconUrl"
                    type="url"
                    value={formData.faviconUrl}
                    onChange={(e) => handleInputChange('faviconUrl', e.target.value)}
                    className="devora-input"
                    placeholder="https://example.com/favicon.ico"
                  />
                </div>
              </div>
            )}
          </div>

          <Separator />

          {/* Custom CSS */}
          <div className="space-y-4">
            <h3 className="text-lg font-heading font-black text-devora-primary">
              Custom CSS
            </h3>
            
            <div className="space-y-2">
              <Label htmlFor="customCSS" className="text-devora-text-primary font-ui font-bold">
                Custom CSS Code
              </Label>
              <textarea
                id="customCSS"
                value={formData.customCSS}
                onChange={(e) => handleInputChange('customCSS', e.target.value)}
                className="devora-input min-h-[120px] font-mono text-sm"
                placeholder="/* Add your custom CSS here */&#10;.custom-class {&#10;  color: #3432A6;&#10;}"
              />
              <p className="text-sm text-devora-text-secondary">
                Add custom CSS to override default styles. Use with caution as it may affect component functionality.
              </p>
            </div>
          </div>

          {/* Save Result Alert */}
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

          {/* Action Buttons */}
          <div className="flex flex-col sm:flex-row gap-3 pt-4">
            <Button
              variant="primary"
              onClick={handleSave}
              disabled={isSaving || isPreviewing}
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
            
            {onPreview && (
              <Button
                variant="secondary"
                onClick={handlePreview}
                disabled={isSaving || isPreviewing}
                className="flex-1 sm:flex-none"
              >
                {isPreviewing ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Previewing...
                  </>
                ) : (
                  <>
                    <Eye className="mr-2 h-4 w-4" />
                    Preview Changes
                  </>
                )}
              </Button>
            )}

            <Button
              variant="outline"
              onClick={resetToDefaults}
              disabled={isSaving || isPreviewing}
              className="flex-1 sm:flex-none"
            >
              Reset to Defaults
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default ComponentConfig;