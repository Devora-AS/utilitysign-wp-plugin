import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/devora/Card';
import { Button } from '@/components/devora/Button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle, AlertCircle, Loader2, Settings as SettingsIcon, ExternalLink } from 'lucide-react';
import { useUtilitySignAPI } from '@/hooks/useUtilitySignAPI';

interface WordPressPage {
  id: number;
  title: {
    rendered: string;
  };
  link: string;
  status: 'publish' | 'draft' | 'pending';
}

interface GeneralSettings {
  signatoryRedirectPageId: number | null;
  signatoryRedirectUri: string;
}

interface GeneralSettingsProps {
  onSave?: (settings: GeneralSettings) => void;
}

export const GeneralSettings: React.FC<GeneralSettingsProps> = ({ onSave }) => {
  const { config, updateConfig, loading } = useUtilitySignAPI();
  const [formData, setFormData] = useState<GeneralSettings>({
    signatoryRedirectPageId: null,
    signatoryRedirectUri: '',
  });
  const [pages, setPages] = useState<WordPressPage[]>([]);
  const [loadingPages, setLoadingPages] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [saveResult, setSaveResult] = useState<{ success: boolean; message: string } | null>(null);

  // Load WordPress pages
  useEffect(() => {
    const fetchPages = async () => {
      try {
        setLoadingPages(true);
        
        // Fetch published pages via WordPress REST API
        const response = await fetch('/wp-json/wp/v2/pages?status=publish&per_page=100&orderby=title&order=asc', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
          },
        });
        
        if (!response.ok) {
          throw new Error(`Failed to fetch pages: ${response.statusText}`);
        }
        
        const pagesData: WordPressPage[] = await response.json();
        setPages(pagesData);
      } catch (err) {
        console.error('Error fetching WordPress pages:', err);
        setSaveResult({
          success: false,
          message: `Failed to load pages: ${err instanceof Error ? err.message : 'Unknown error'}`
        });
      } finally {
        setLoadingPages(false);
      }
    };
    
    fetchPages();
  }, []);

  // Load existing configuration
  useEffect(() => {
    const loadSettings = async () => {
      try {
        // Load from WordPress option
        const response = await fetch('/wp-json/utilitysign/v1/settings/general', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': (window as any).wpApiSettings?.nonce || '',
          },
        });
        
        if (response.ok) {
          const data = await response.json();
          if (data.signatoryRedirectPageId || data.signatoryRedirectUri) {
            setFormData({
              signatoryRedirectPageId: data.signatoryRedirectPageId || null,
              signatoryRedirectUri: data.signatoryRedirectUri || '',
            });
          }
        }
      } catch (err) {
        console.error('Error loading general settings:', err);
      }
    };
    
    loadSettings();
  }, [config]);

  const handlePageChange = (pageId: string) => {
    const id = pageId === 'none' ? null : parseInt(pageId, 10);
    const selectedPage = pages.find(p => p.id === id);
    
    setFormData(prev => ({
      ...prev,
      signatoryRedirectPageId: id,
      signatoryRedirectUri: selectedPage?.link || '',
    }));
  };

  const handleSave = async () => {
    setIsSaving(true);
    setSaveResult(null);
    
    try {
      // Save via WordPress REST API
      const response = await fetch('/wp-json/utilitysign/v1/settings/general', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': (window as any).wpApiSettings?.nonce || '',
        },
        body: JSON.stringify({
          signatoryRedirectPageId: formData.signatoryRedirectPageId,
          signatoryRedirectUri: formData.signatoryRedirectUri,
        }),
      });
      
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to save settings');
      }
      
      const result = await response.json();
      
      if (onSave) {
        await onSave(formData);
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

  const selectedPage = pages.find(p => p.id === formData.signatoryRedirectPageId);

  return (
    <Card variant="white" className="border-devora-border">
      <CardHeader className="border-b border-devora-border bg-devora-background-secondary">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-devora-primary/10">
            <SettingsIcon className="h-5 w-5 text-devora-primary" />
          </div>
          <div>
            <CardTitle className="text-devora-primary">General Settings</CardTitle>
            <CardDescription className="text-devora-text-secondary">
              Configure general plugin behavior and user experience options
            </CardDescription>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-6 pt-6">
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

        {/* Post-Sign Redirect Settings */}
        <div className="space-y-4">
          <div>
            <h3 className="text-lg font-semibold text-devora-primary mb-2">
              Post-Sign Redirect
            </h3>
            <p className="text-sm text-devora-text-secondary mb-4">
              Configure where users are redirected after successfully signing with BankID.
              If not set, users will remain on the Criipto confirmation page (current behavior).
            </p>
          </div>

          <div className="space-y-3">
            <Label htmlFor="redirect-page" className="text-devora-text-primary font-medium">
              Thank You / Confirmation Page
            </Label>
            
            {loadingPages ? (
              <div className="flex items-center gap-2 text-devora-text-secondary">
                <Loader2 className="h-4 w-4 animate-spin" />
                <span className="text-sm">Loading pages...</span>
              </div>
            ) : (
              <>
                <Select
                  value={formData.signatoryRedirectPageId?.toString() || 'none'}
                  onValueChange={handlePageChange}
                  disabled={isSaving}
                >
                  <SelectTrigger 
                    id="redirect-page" 
                    className="w-full border-devora-border focus:border-devora-primary focus:ring-devora-primary"
                  >
                    <SelectValue placeholder="No redirect (current behavior)" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">
                      <span className="text-devora-text-secondary italic">
                        No redirect (stay on Criipto page)
                      </span>
                    </SelectItem>
                    {pages.map(page => (
                      <SelectItem key={page.id} value={page.id.toString()}>
                        {page.title.rendered}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                {/* Preview Selected Page */}
                {selectedPage && (
                  <div className="flex items-center gap-2 text-sm text-devora-text-secondary">
                    <span>Preview:</span>
                    <a
                      href={selectedPage.link}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex items-center gap-1 text-devora-primary hover:text-devora-primary-hover hover:underline"
                    >
                      {selectedPage.link}
                      <ExternalLink className="h-3 w-3" />
                    </a>
                  </div>
                )}
                
                <p className="text-sm text-devora-text-secondary">
                  Select a page to redirect users to after successful BankID signing.
                  This provides a better user experience with branded confirmation and clear next steps.
                </p>
              </>
            )}
          </div>

          {/* Information Box */}
          <Alert className="border-devora-primary/20 bg-devora-primary/5">
            <AlertDescription className="text-sm text-devora-text-primary">
              <strong className="font-semibold">How it works:</strong>
              <ul className="mt-2 space-y-1 list-disc list-inside">
                <li>After signing with BankID, Criipto automatically redirects to the selected page</li>
                <li>Email confirmation is still sent as usual</li>
                <li>If no page is selected, current behavior is preserved (no redirect)</li>
                <li>Page must use HTTPS for security compliance</li>
              </ul>
            </AlertDescription>
          </Alert>

          {/* No Pages Warning */}
          {!loadingPages && pages.length === 0 && (
            <Alert className="border-yellow-500 bg-yellow-50">
              <AlertCircle className="h-4 w-4 text-yellow-600" />
              <AlertDescription className="text-yellow-800">
                <strong>No published pages found.</strong> Create a "Thank You" or "Order Confirmation" page first,
                then return here to select it as the redirect destination.
              </AlertDescription>
            </Alert>
          )}
        </div>

        {/* Save Button */}
        <div className="flex items-center justify-end gap-3 pt-4 border-t border-devora-border">
          <Button
            onClick={handleSave}
            disabled={isSaving || loadingPages}
            className="bg-devora-primary hover:bg-devora-primary-hover text-white"
          >
            {isSaving ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Saving...
              </>
            ) : (
              'Save General Settings'
            )}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
};

export default GeneralSettings;
