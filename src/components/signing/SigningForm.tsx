import React, { useState, startTransition } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../devora/Card';
import { Button } from '../devora/Button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Alert, AlertDescription } from '../ui/alert';
import { 
  Mail, 
  Shield, 
  CheckCircle, 
  AlertCircle, 
  Loader2
} from 'lucide-react';
import { cn } from '../../lib/utils';
import { SigningRequest, APIResponse, APIError } from '../../lib/api-client';
import { useAPIClient } from '../APIClientProvider';

export interface SigningFormProps {
  documentId: string;
  productId?: string;
  supplierId?: string;
  isApiProduct?: boolean;
  onSuccess?: (result: SigningResult) => void;
  onError?: (error: string) => void;
  className?: string;
  enableBankID?: boolean;
  enableEmailNotifications?: boolean;
}

export interface SigningResult {
  success: boolean;
  requestId: string;
  authUrl?: string;
  error?: string;
}

interface FormData {
  signerEmail: string;
  firstName: string;
  lastName: string;
  phone: string;
  address: string;
  city: string;
  zip: string;
  billingAddress: string;
  billingCity: string;
  billingZip: string;
  useSameAddressForBilling: boolean;
  takeoverDate: string;
  meterNumber: string; // MålepunktID (18 digits, prefix 7070575)
  serialNumber: string; // Målenummer (variable length, no validation)
  companyName: string;
  organizationNumber: string;
  sportsTeam: string; // Phase 3: For "Støtt ditt idrettslag" product
  marketingConsentEmail: boolean; // Phase 3: Marketing consent
  marketingConsentSms: boolean; // Phase 3: Marketing consent
}

// Product IDs for business customers (requires Firmanavn and Organisasjonsnummer)
const BUSINESS_PRODUCT_IDS = [
  'bef7a77c-8770-4c1f-9906-714a4b762d26', // Eiker Spotpris Bedrift
];

// Product ID for sports team support (requires sports team selection)
const SPORTS_TEAM_PRODUCT_ID = '36757e7a-3289-4922-92d7-ffccefb261a0'; // Støtt ditt idrettslag

// Sports team options for "Støtt ditt idrettslag" product
const SPORTS_TEAM_OPTIONS = [
  { value: 'VIF Spot', label: 'VIF Spot' },
  { value: 'HIL Spot', label: 'HIL Spot' },
  { value: 'IF Eiker Kvikk Spot', label: 'IF Eiker Kvikk Spot' },
  { value: 'Eiker Ski Spot', label: 'Eiker Ski Spot' },
];

const SigningForm: React.FC<SigningFormProps> = ({
  documentId,
  productId,
  supplierId,
  isApiProduct: _isApiProduct,
  onSuccess,
  onError,
  className,
  enableBankID = true,
  enableEmailNotifications = true,
}) => {
  const apiClient = useAPIClient();
  const [formData, setFormData] = useState<FormData>({
    signerEmail: '',
    firstName: '',
    lastName: '',
    phone: '',
    address: '',
    city: '',
    zip: '',
    billingAddress: '',
    billingCity: '',
    billingZip: '',
    useSameAddressForBilling: true,
    takeoverDate: '',
    meterNumber: '',
    serialNumber: '',
    companyName: '',
    organizationNumber: '',
    sportsTeam: '',
    marketingConsentEmail: false,
    marketingConsentSms: false,
  });
  
  // Check if this is a business product that requires company fields
  const isBusinessProduct = productId ? BUSINESS_PRODUCT_IDS.includes(productId) : false;
  // Check if this is the sports team product that requires team selection
  const isSportsTeamProduct = productId === SPORTS_TEAM_PRODUCT_ID;
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isBankIDLoading, setIsBankIDLoading] = useState(false);
  const [errors, setErrors] = useState<Partial<FormData>>({});
  const [success, setSuccess] = useState<string | null>(null);
  const [currentRequest, setCurrentRequest] = useState<SigningRequest | null>(null);
  const [currentRequestId, setCurrentRequestId] = useState<string | null>(null);
  const [productName, setProductName] = useState<string | null>(null);
  const [isLoadingProduct, setIsLoadingProduct] = useState(false);

  // Email validation regex
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  
  // Fetch product name when productId is available
  React.useEffect(() => {
    if (productId && productId.trim() !== '') {
      setIsLoadingProduct(true);
      
      // Fetch product name from WordPress REST API (works for both UUID and WordPress post ID)
      // Note: apiClient.get() expects endpoint without leading slash (it adds it automatically)
      // Use startTransition to mark product name update as non-urgent for better perceived performance
      apiClient.get<{ success?: boolean; data?: { name?: string; title?: string } }>(
        `products/${productId}`
      ).then((response) => {
        // DEBUG: Log the full response structure
        const debugMode = new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
        if (debugMode) {
          console.log('[SigningForm] Product API response:', response);
          console.log('[SigningForm] response.data:', response.data);
          console.log('[SigningForm] response.data type:', typeof response.data);
        }
        
        if (response.success && response.data) {
          // WordPress REST API returns { success: true, data: { name: "...", title: "..." } }
          // apiClient.get() wraps this, so response.data is the WordPress response object
          // We need to access response.data.data to get the actual product data
          const productData = (response.data as any)?.data || response.data;
          const name = productData?.name ?? productData?.title ?? null;
          
          if (debugMode) {
            console.log('[SigningForm] productData:', productData);
            console.log('[SigningForm] extracted name:', name);
          }
          
          // Use startTransition to mark this update as non-urgent
          // This keeps the UI responsive and improves perceived performance
          startTransition(() => {
            setProductName(name);
            setIsLoadingProduct(false);
          });
        } else {
          console.warn('[SigningForm] Product fetch failed:', response.error);
          setIsLoadingProduct(false);
        }
      }).catch((error) => {
        console.warn('[SigningForm] Failed to fetch product name:', error);
        setIsLoadingProduct(false);
      });
    }
  }, [productId, apiClient]);

  const validateFormWithData = (data: FormData): boolean => {
    const debugMode = new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    
    if (debugMode) {
      console.log('[SigningForm] validateFormWithData called', { data });
    }
    
    const newErrors: Partial<FormData> = {};
    
    // Use provided data (from DOM or state)
    const currentFirstName = (data.firstName || '').trim();
    const currentLastName = (data.lastName || '').trim();
    const currentEmail = (data.signerEmail || '').trim();
    const currentPhone = (data.phone || '').trim();
    const currentAddress = (data.address || '').trim();
    const currentCity = (data.city || '').trim();
    const currentZip = (data.zip || '').trim();
    const currentBillingAddress = (data.billingAddress || '').trim();
    const currentBillingCity = (data.billingCity || '').trim();
    const currentBillingZip = (data.billingZip || '').trim();
    const useSameAddressForBilling = data.useSameAddressForBilling !== false;
    const currentMeterNumber = (data.meterNumber || '').trim();
    const currentSerialNumber = (data.serialNumber || '').trim();

    // Validate firstName (required)
    if (!currentFirstName) {
      newErrors.firstName = 'Fornavn er påkrevd';
    } else if (currentFirstName.length < 2) {
      newErrors.firstName = 'Fornavn må være minst 2 tegn';
    }

    // Validate lastName (required)
    if (!currentLastName) {
      newErrors.lastName = 'Etternavn er påkrevd';
    } else if (currentLastName.length < 2) {
      newErrors.lastName = 'Etternavn må være minst 2 tegn';
    }

    // Validate email (required)
    if (!currentEmail) {
      newErrors.signerEmail = 'E-postadresse er påkrevd';
    } else if (!emailRegex.test(currentEmail)) {
      newErrors.signerEmail = 'Vennligst skriv inn en gyldig e-postadresse';
    }

    // Validate phone (required)
    if (!currentPhone) {
      newErrors.phone = 'Telefonnummer er påkrevd';
    }

    // Validate address (required)
    if (!currentAddress) {
      newErrors.address = 'Gate er påkrevd';
    }

    // Validate city (required)
    if (!currentCity) {
      newErrors.city = 'Sted er påkrevd';
    }

    // Validate zip (required)
    if (!currentZip) {
      newErrors.zip = 'Postnummer er påkrevd';
    }

    // Validate billing address fields (required only when user has different billing address)
    if (!useSameAddressForBilling) {
      if (!currentBillingAddress) {
        newErrors.billingAddress = 'Fakturaadresse er påkrevd';
      }
      if (!currentBillingZip) {
        newErrors.billingZip = 'Faktura postnummer er påkrevd';
      }
      if (!currentBillingCity) {
        newErrors.billingCity = 'Faktura sted er påkrevd';
      }
    }

    // Validate meterNumber (optional, but if provided must be valid GS1 MålepunktID format)
    // GS1 MålepunktID format: 18 digits, starts with 70 (country code) + 70575 (industry code)
    // Example: 707057500088553215
    if (currentMeterNumber) {
      // Remove any whitespace
      const cleanedMeterNumber = currentMeterNumber.replace(/\s/g, '');
      
      // Must be exactly 18 digits
      if (!/^\d{18}$/.test(cleanedMeterNumber)) {
        newErrors.meterNumber = 'MålepunktID må være nøyaktig 18 siffer';
      }
      // Must start with country code 70 + industry code 70575 (7070575)
      else if (!cleanedMeterNumber.startsWith('7070575')) {
        newErrors.meterNumber = 'MålepunktID må starte med 7070575 (norsk landkode + bransjekode)';
      }
    }
    
    // Validate business fields (only required for business products)
    if (isBusinessProduct) {
      const currentCompanyName = (data.companyName || '').trim();
      const currentOrgNumber = (data.organizationNumber || '').trim();
      
      if (!currentCompanyName) {
        newErrors.companyName = 'Firmanavn er påkrevd';
      } else if (currentCompanyName.length < 2) {
        newErrors.companyName = 'Firmanavn må være minst 2 tegn';
      }
      
      if (!currentOrgNumber) {
        newErrors.organizationNumber = 'Organisasjonsnummer er påkrevd';
      } else if (!/^\d{9}$/.test(currentOrgNumber)) {
        newErrors.organizationNumber = 'Organisasjonsnummer må være 9 siffer';
      }
    }
    
    // Phase 3: Validate sports team selection (only required for sports team product)
    if (isSportsTeamProduct) {
      const currentSportsTeam = (data.sportsTeam || '').trim();
      
      if (!currentSportsTeam) {
        newErrors.sportsTeam = 'Vennligst velg idrettslag du vil støtte';
      }
    }

    if (debugMode) {
      console.log('[SigningForm] Validation result', { 
        errors: newErrors, 
        isValid: Object.keys(newErrors).length === 0,
        currentFirstName,
        currentLastName,
        currentEmail,
        currentPhone,
        currentAddress,
        currentCity,
        currentZip,
        currentMeterNumber,
        currentSerialNumber
      });
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleInputChange = (field: keyof FormData, value: string | boolean) => {
    const debugMode = new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    
    if (debugMode) {
      console.log('[SigningForm] Input change', { field, value, timestamp: new Date().toISOString() });
    }
    
    setFormData(prev => {
      const updated = { ...prev, [field]: value } as FormData;
      // Safety: when user chooses "same address for billing", clear any previously entered billing address
      if (field === 'useSameAddressForBilling' && value === true) {
        updated.billingAddress = '';
        updated.billingCity = '';
        updated.billingZip = '';
      }
      if (debugMode) {
        console.log('[SigningForm] Form state updated', { previous: prev, updated });
      }
      return updated;
    });
    
    // Clear error when user starts typing
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: undefined }));
    }
  };

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    
    // Check if debug mode is enabled
    const debugMode = new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    
    // Read values directly from form inputs to avoid React state timing issues
    const form = e.currentTarget;
    const formDataFromDOM = new FormData(form);
    const firstName = (formDataFromDOM.get('firstName') as string) || formData.firstName || '';
    const lastName = (formDataFromDOM.get('lastName') as string) || formData.lastName || '';
    const signerEmail = (formDataFromDOM.get('signerEmail') as string) || formData.signerEmail || '';
    const phone = (formDataFromDOM.get('phone') as string) || formData.phone || '';
    const address = (formDataFromDOM.get('address') as string) || formData.address || '';
    const city = (formDataFromDOM.get('city') as string) || formData.city || '';
    const zip = (formDataFromDOM.get('zip') as string) || formData.zip || '';
    const useSameAddressForBilling =
      formDataFromDOM.get('useSameAddressForBilling') === 'on' ||
      formData.useSameAddressForBilling;
    const billingAddress = (formDataFromDOM.get('billingAddress') as string) || formData.billingAddress || '';
    const billingCity = (formDataFromDOM.get('billingCity') as string) || formData.billingCity || '';
    const billingZip = (formDataFromDOM.get('billingZip') as string) || formData.billingZip || '';
    const takeoverDate = (formDataFromDOM.get('takeoverDate') as string) || formData.takeoverDate || '';
    const meterNumber = (formDataFromDOM.get('meterNumber') as string) || formData.meterNumber || '';
    const serialNumber = (formDataFromDOM.get('serialNumber') as string) || formData.serialNumber || '';
    // Business customer fields
    const companyName = (formDataFromDOM.get('companyName') as string) || formData.companyName || '';
    const organizationNumber = (formDataFromDOM.get('organizationNumber') as string) || formData.organizationNumber || '';
    // Phase 3: Sports team and marketing consent fields
    const sportsTeam = (formDataFromDOM.get('sportsTeam') as string) || formData.sportsTeam || '';
    const marketingConsentEmail = formDataFromDOM.get('marketingConsentEmail') === 'on' || formData.marketingConsentEmail;
    const marketingConsentSms = formDataFromDOM.get('marketingConsentSms') === 'on' || formData.marketingConsentSms;
    
    // Construct full name from firstName + lastName for API
    const signerName = `${firstName} ${lastName}`.trim();
    
    // Update state if DOM values differ (handles race conditions)
    const updatedFormData: FormData = {
      signerEmail,
      firstName,
      lastName,
      phone,
      address,
      city,
      zip,
      useSameAddressForBilling,
      billingAddress: useSameAddressForBilling ? '' : billingAddress,
      billingCity: useSameAddressForBilling ? '' : billingCity,
      billingZip: useSameAddressForBilling ? '' : billingZip,
      takeoverDate,
      meterNumber,
      serialNumber,
      companyName,
      organizationNumber,
      sportsTeam,
      marketingConsentEmail,
      marketingConsentSms,
    };
    
    if (JSON.stringify(updatedFormData) !== JSON.stringify(formData)) {
      if (debugMode) {
        console.log('[SigningForm] State sync needed', { 
          state: formData, 
          dom: updatedFormData
        });
      }
      setFormData(updatedFormData);
    }
    
    if (debugMode) {
      console.log('[SigningForm] handleSubmit called', { 
        formData, 
        domValues: { signerName, signerEmail },
        documentId, 
        enableBankID,
        timestamp: new Date().toISOString()
      });
    }
    
    // Validate with current values (from DOM or state, whichever is more recent)
    const currentFormData = updatedFormData;
    const isValid = validateFormWithData(currentFormData);
    if (debugMode) {
      console.log('[SigningForm] Validation result', { isValid, errors, validatedData: currentFormData });
    }
    
    if (!isValid) {
      if (debugMode) {
        console.log('[SigningForm] Validation failed, returning early');
      }
      return;
    }
    
    // Use the validated data for submission
    const finalFormData = currentFormData;

    setIsSubmitting(true);
    setSuccess(null);

    try {
      // Create signing request with idempotency key using validated data
      const idempotencyKey = `signing-${documentId}-${finalFormData.signerEmail}-${Date.now()}`;
      
      // Construct signerName from firstName + lastName
      const signerName = `${finalFormData.firstName} ${finalFormData.lastName}`.trim();
      
      if (debugMode) {
        console.log('[SigningForm] Calling createSigningRequest', {
          documentId,
          signerEmail: finalFormData.signerEmail,
          signerName: signerName,
          firstName: finalFormData.firstName,
          lastName: finalFormData.lastName,
          idempotencyKey
        });
      }
      
      // Log billing address fields before sending
      const billingFieldsToSend = {
        useSameAddressForBilling: finalFormData.useSameAddressForBilling,
        billingAddress: finalFormData.billingAddress,
        billingCity: finalFormData.billingCity,
        billingZip: finalFormData.billingZip,
        willSendBillingAddress: !finalFormData.useSameAddressForBilling && !!finalFormData.billingAddress,
        willSendBillingCity: !finalFormData.useSameAddressForBilling && !!finalFormData.billingCity,
        willSendBillingZip: !finalFormData.useSameAddressForBilling && !!finalFormData.billingZip,
      };
      console.log('[SigningForm] Billing address fields before API call', billingFieldsToSend);
      
      const response: APIResponse<SigningRequest> = await apiClient.createSigningRequest(
        documentId,
        finalFormData.signerEmail,
        signerName,
        idempotencyKey,
        {
          productId: productId || undefined,
          supplierId: supplierId || undefined,
          phone: finalFormData.phone || undefined,
          firstName: finalFormData.firstName || undefined,
          lastName: finalFormData.lastName || undefined,
          address: finalFormData.address || undefined,
          city: finalFormData.city || undefined,
          zip: finalFormData.zip || undefined,
          billingAddress: finalFormData.useSameAddressForBilling ? undefined : (finalFormData.billingAddress || undefined),
          billingCity: finalFormData.useSameAddressForBilling ? undefined : (finalFormData.billingCity || undefined),
          billingZip: finalFormData.useSameAddressForBilling ? undefined : (finalFormData.billingZip || undefined),
          takeoverDate: finalFormData.takeoverDate || undefined,
          meterNumber: finalFormData.meterNumber || undefined,
          serialNumber: finalFormData.serialNumber || undefined,
          // Business customer fields (only included if non-empty)
          companyName: finalFormData.companyName || undefined,
          organizationNumber: finalFormData.organizationNumber || undefined,
          // Phase 3: Sports team and marketing consent fields
          sportsTeam: finalFormData.sportsTeam || undefined,
          marketingConsentEmail: finalFormData.marketingConsentEmail,
          marketingConsentSms: finalFormData.marketingConsentSms,
        }
      );
      
      if (debugMode) {
        console.log('[SigningForm] createSigningRequest response', { 
          success: response.success, 
          hasData: !!response.data,
          error: response.error 
        });
      }

      if (!response.success || !response.data) {
        throw new Error(response.error || 'Failed to create signing request');
      }

      setCurrentRequest(response.data);
      setCurrentRequestId(response.data.id);
      setSuccess('Signeringsforespørsel opprettet!');

      // If BankID is enabled, check if SigningUrl is available in response
      if (enableBankID) {
        // Check for SigningUrl in various formats (PascalCase, camelCase, snake_case)
        const signingUrl = response.data.signing_url || 
                          (response.data as any)?.signingUrl || 
                          (response.data as any)?.SigningUrl;
        
        if (debugMode) {
          console.log('[SigningForm] BankID enabled, checking for SigningUrl', { 
            hasSigningUrl: !!signingUrl,
            signingUrl: signingUrl,
            checkedFormats: {
              signing_url: !!response.data.signing_url,
              signingUrl: !!(response.data as any)?.signingUrl,
              SigningUrl: !!(response.data as any)?.SigningUrl
            },
            responseDataKeys: Object.keys(response.data || {})
          });
        }
        
        // If SigningUrl is available directly from the response, use it
        if (signingUrl) {
          if (debugMode) {
            console.log('[SigningForm] Using SigningUrl from response directly', { 
              signingUrl,
              foundIn: response.data.signing_url ? 'signing_url' :
                      (response.data as any)?.signingUrl ? 'signingUrl' :
                      (response.data as any)?.SigningUrl ? 'SigningUrl' : 'unknown'
            });
          }
          
          // Try to open Criipto signing page in new window (preferred UX)
          const authWindow = window.open(
            signingUrl,
            'bankid-auth',
            'width=600,height=700,scrollbars=yes,resizable=yes'
          );

          if (!authWindow) {
            // Popup blocked - fallback to same-window redirect with user confirmation
            if (debugMode) {
              console.warn('[SigningForm] Popup blocked, falling back to same-window redirect');
            }
            
            const shouldRedirect = window.confirm(
              'Popup blokkert. Vil du bli omdirigert til signeringssiden i dette vinduet? (Du kan returnere til denne siden etter signering)'
            );
            
            if (shouldRedirect) {
              window.location.href = signingUrl;
              // Don't call onSuccess here as we're redirecting
              return;
      } else {
        // User cancelled - show error but don't throw (non-blocking)
        setErrors({ signerEmail: 'Vennligst tillat popups eller klikk "OK" for å omdirigere til signeringssiden.' });
        onError?.('Vennligst tillat popups eller klikk "OK" for å omdirigere til signeringssiden.');
        return;
      }
          }

          onSuccess?.({
            success: true,
            requestId: response.data.id,
            authUrl: signingUrl,
          });
        } else {
          // Fallback: Try to initiate BankID if SigningUrl is not available
          if (debugMode) {
            console.log('[SigningForm] SigningUrl not in response, calling initiateBankID');
          }
          await initiateBankID(response.data.id);
        }
      } else {
        if (debugMode) {
          console.log('[SigningForm] BankID disabled, showing success only');
        }
        // If BankID is disabled, just show success
        onSuccess?.({
          success: true,
          requestId: response.data.id,
        });
      }
    } catch (error) {
      let errorMessage = 'En feil oppstod under behandling av forespørselen. Vennligst prøv igjen.';
      
      // Log error for debugging
      console.error('[SigningForm] Submission error:', error);
      
      if (error instanceof APIError) {
        // Use validation errors if available, otherwise use userMessage or message
        if (error.validationErrors && error.validationErrors.length > 0) {
          errorMessage = error.validationErrors.join(', ');
          // Set errors for specific fields if possible
          const fieldErrors: Partial<FormData> = {};
          error.validationErrors.forEach(err => {
            if (err.toLowerCase().includes('email')) {
              fieldErrors.signerEmail = err;
            } else if (err.toLowerCase().includes('firstname') || err.toLowerCase().includes('fornavn')) {
              fieldErrors.firstName = err;
            } else if (err.toLowerCase().includes('lastname') || err.toLowerCase().includes('etternavn')) {
              fieldErrors.lastName = err;
            }
          });
          if (Object.keys(fieldErrors).length > 0) {
            setErrors(fieldErrors);
          } else {
            setErrors({ signerEmail: errorMessage });
          }
        } else {
          errorMessage = error.userMessage || error.message || errorMessage;
          setErrors({ signerEmail: errorMessage });
        }
      } else if (error instanceof Error) {
        errorMessage = error.message || errorMessage;
        setErrors({ signerEmail: errorMessage });
      } else if (error && typeof error === 'object' && 'message' in error) {
        errorMessage = String((error as any).message) || errorMessage;
        setErrors({ signerEmail: errorMessage });
      } else {
        // Handle null/undefined errors
        setErrors({ signerEmail: errorMessage });
      }
      
      onError?.(errorMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  const initiateBankID = async (requestId: string) => {
    const debugMode = new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    
    if (debugMode) {
      console.log('[SigningForm] initiateBankID called', { requestId });
    }
    
    setIsBankIDLoading(true);

    try {
      const response: APIResponse<{ auth_url: string; session_id: string; correlation_id: string }> = 
        await apiClient.initiateBankID(requestId);
      
      if (debugMode) {
        console.log('[SigningForm] initiateBankID response', { 
          success: response.success, 
          hasData: !!response.data,
          error: response.error 
        });
      }

      if (!response.success || !response.data) {
        throw new Error(response.error || 'Failed to initiate BankID authentication');
      }

      // Store session info for potential cancellation
      const sessionId = response.data.session_id;

      // Try to open BankID authentication in new window (preferred UX)
      const authWindow = window.open(
        response.data.auth_url,
        'bankid-auth',
        'width=600,height=700,scrollbars=yes,resizable=yes'
      );

      if (!authWindow) {
        // Popup blocked - fallback to same-window redirect with user confirmation
        if (debugMode) {
          console.warn('[SigningForm] Popup blocked in initiateBankID, falling back to same-window redirect');
        }
        
        const shouldRedirect = window.confirm(
          'Popup blokkert. Vil du bli omdirigert til signeringssiden i dette vinduet? (Du kan returnere til denne siden etter signering)'
        );
        
        if (shouldRedirect) {
          window.location.href = response.data.auth_url;
          // Don't continue with monitoring as we're redirecting
          return;
        } else {
          // User cancelled - show error but don't throw (non-blocking)
          setErrors({ signerEmail: 'Vennligst tillat popups eller klikk "OK" for å omdirigere til signeringssiden.' });
        onError?.('Vennligst tillat popups eller klikk "OK" for å omdirigere til signeringssiden.');
          setIsBankIDLoading(false);
          return;
        }
      }

      // Monitor window closure
      const checkClosed = setInterval(() => {
        if (authWindow.closed) {
          clearInterval(checkClosed);
          // User closed window - cancel session
          apiClient.cancelBankIDSession(sessionId).catch((error) => {
            // Silently handle cancellation error - user intentionally closed window
            if (new URLSearchParams(window.location.search).get('utilitysign_debug') === '1') {
              console.error('[SigningForm] Error canceling BankID session:', error);
            }
          });
        }
      }, 1000);

      // Poll for authentication completion (fire-and-forget, no await needed)
      // Wrap in promise to catch any unhandled rejections
      pollBankIDStatus(sessionId).catch((error) => {
        // Silently handle any unhandled promise rejections from polling
        if (debugMode) {
          console.error('[SigningForm] Unhandled error in pollBankIDStatus:', error);
        }
      });

      onSuccess?.({
        success: true,
        requestId,
        authUrl: response.data.auth_url,
      });
    } catch (error) {
      let errorMessage = 'BankID authentication failed';
      
      if (error instanceof APIError) {
        errorMessage = error.userMessage || error.message;
      } else if (error instanceof Error) {
        errorMessage = error.message;
      }
      
      onError?.(errorMessage);
    } finally {
      setIsBankIDLoading(false);
    }
  };

  const pollBankIDStatus = (sessionId: string): Promise<void> => {
    // Check if debug mode is enabled
    const debugMode = new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    
    if (debugMode) {
      console.log('[SigningForm] Starting BankID status polling', { sessionId });
    }

    const pollInterval = setInterval(async () => {
      try {
        const response: APIResponse<{ status: string; user_info?: any }> = 
          await apiClient.checkBankIDStatus(sessionId).catch((error) => {
            // Ensure we never reject with null
            const errorMessage = error instanceof Error ? error.message : 'Unknown error';
            if (debugMode) {
              console.error('[SigningForm] checkBankIDStatus rejected:', errorMessage, error);
            }
            // Return a failed response instead of rejecting
            return {
              success: false,
              error: errorMessage || 'BankID status check failed',
              timestamp: new Date().toISOString()
            } as APIResponse<{ status: string; user_info?: any }>;
          });

        if (debugMode) {
          console.log('[SigningForm] BankID status poll response', { status: response.data?.status, success: response.success });
        }

        if (response.success && response.data) {
          if (response.data.status === 'completed') {
            clearInterval(pollInterval);
            setSuccess('Dokument signert med BankID!');
            // Close the auth window if it's still open
            const authWindow = window.open('', 'bankid-auth');
            if (authWindow) {
              authWindow.close();
            }
            
            // Trigger completion flow to ensure post-signing email is sent
            // This is a fire-and-forget call - we don't wait for it
            if (currentRequestId) {
              apiClient.triggerSigningCompletion(currentRequestId).then((completionResult) => {
                if (debugMode) {
                  console.log('[SigningForm] Completion trigger result:', completionResult);
                }
              }).catch((completionError) => {
                if (debugMode) {
                  console.error('[SigningForm] Error triggering completion:', completionError);
                }
                // Don't show error to user - this is a background operation
              });
            }
          } else if (response.data.status === 'failed' || response.data.status === 'cancelled') {
            clearInterval(pollInterval);
            onError?.('BankID authentication was cancelled or failed');
          }
        }
      } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Unknown error';
        console.error('[SigningForm] Error polling BankID status:', errorMessage, error);
        // Don't clear interval on error - continue polling
      }
    }, 2000); // Poll every 2 seconds

    // Stop polling after 5 minutes
    setTimeout(() => {
      clearInterval(pollInterval);
      if (debugMode) {
        console.log('[SigningForm] BankID polling stopped after 5 minutes');
      }
    }, 300000);
    
    // Return a promise that resolves when polling stops (for error handling)
    return new Promise<void>((resolve) => {
      setTimeout(() => resolve(), 300000);
    });
  };

  return (
    <Card variant="white" className={cn("w-full", className)}>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-devora-primary">
          {isLoadingProduct ? (
            'Bli kunde og bestill strømavtale'
          ) : productName ? (
            `Bli kunde og bestill ${productName} strømavtale`
          ) : (
            'Bli kunde og bestill strømavtale'
          )}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Success Message */}
          {success && (
            <Alert className="border-green-200 bg-green-50">
              <CheckCircle className="h-4 w-4 text-green-600" />
              <AlertDescription className="text-green-800">
                {success}
              </AlertDescription>
            </Alert>
          )}

          {/* Signer Information */}
          <div className="space-y-4">
            {/* Business Customer Fields - Only shown for business products */}
            {isBusinessProduct && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="companyName" className="text-devora-primary-dark font-medium">
                    Firmanavn *
                  </Label>
                  <div className="relative mt-1">
                    <Input
                      id="companyName"
                      name="companyName"
                      type="text"
                      value={formData.companyName}
                      onChange={(e) => handleInputChange('companyName', e.target.value)}
                      className={cn(
                        "devora-input",
                        errors.companyName && "border-red-500 focus:border-red-500"
                      )}
                      placeholder="Skriv inn firmanavn"
                      disabled={isSubmitting || isBankIDLoading}
                    />
                  </div>
                  {errors.companyName && (
                    <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                      <AlertCircle className="h-4 w-4" />
                      {errors.companyName}
                    </p>
                  )}
                </div>

                <div>
                  <Label htmlFor="organizationNumber" className="text-devora-primary-dark font-medium">
                    Organisasjonsnummer *
                  </Label>
                  <div className="relative mt-1">
                    <Input
                      id="organizationNumber"
                      name="organizationNumber"
                      type="text"
                      value={formData.organizationNumber}
                      onChange={(e) => handleInputChange('organizationNumber', e.target.value)}
                      className={cn(
                        "devora-input",
                        errors.organizationNumber && "border-red-500 focus:border-red-500"
                      )}
                      placeholder="9 siffer (f.eks. 123456789)"
                      maxLength={9}
                      disabled={isSubmitting || isBankIDLoading}
                    />
                  </div>
                  {errors.organizationNumber && (
                    <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                      <AlertCircle className="h-4 w-4" />
                      {errors.organizationNumber}
                    </p>
                  )}
                </div>
              </div>
            )}

            {/* 1. Fornavn + Etternavn */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="firstName" className="text-devora-primary-dark font-medium">
                  Fornavn *
                </Label>
                <div className="relative mt-1">
                  <Input
                    id="firstName"
                    name="firstName"
                    type="text"
                    value={formData.firstName}
                    onChange={(e) => handleInputChange('firstName', e.target.value)}
                    className={cn(
                      "devora-input",
                      errors.firstName && "border-red-500 focus:border-red-500"
                    )}
                    placeholder="Fornavn"
                    disabled={isSubmitting || isBankIDLoading}
                  />
                </div>
                {errors.firstName && (
                  <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                    <AlertCircle className="h-4 w-4" />
                    {errors.firstName}
                  </p>
                )}
              </div>

              <div>
                <Label htmlFor="lastName" className="text-devora-primary-dark font-medium">
                  Etternavn *
                </Label>
                <div className="relative mt-1">
                  <Input
                    id="lastName"
                    name="lastName"
                    type="text"
                    value={formData.lastName}
                    onChange={(e) => handleInputChange('lastName', e.target.value)}
                    className={cn(
                      "devora-input",
                      errors.lastName && "border-red-500 focus:border-red-500"
                    )}
                    placeholder="Etternavn"
                    disabled={isSubmitting || isBankIDLoading}
                  />
                </div>
                {errors.lastName && (
                  <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                    <AlertCircle className="h-4 w-4" />
                    {errors.lastName}
                  </p>
                )}
              </div>
            </div>

            {/* 2. E-postadresse */}
            <div>
              <Label htmlFor="signerEmail" className="text-devora-primary-dark font-medium">
                E-postadresse *
              </Label>
              <div className="relative mt-1">
                <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-devora-text-secondary z-10 pointer-events-none" />
                <Input
                  id="signerEmail"
                  name="signerEmail"
                  type="email"
                  value={formData.signerEmail}
                  onChange={(e) => handleInputChange('signerEmail', e.target.value)}
                  className={cn(
                    "devora-input !pl-10 pr-3",
                    errors.signerEmail && "border-red-500 focus:border-red-500"
                  )}
                  placeholder="Skriv inn din e-postadresse"
                  disabled={isSubmitting || isBankIDLoading}
                />
              </div>
              {errors.signerEmail && (
                <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                  <AlertCircle className="h-4 w-4" />
                  {errors.signerEmail}
                </p>
              )}
            </div>

            {/* 3. Telefonnummer (required) */}
            <div>
              <Label htmlFor="phone" className="text-devora-primary-dark font-medium">
                Telefonnummer *
              </Label>
              <div className="relative mt-1">
                <Input
                  id="phone"
                  name="phone"
                  type="tel"
                  value={formData.phone}
                  onChange={(e) => handleInputChange('phone', e.target.value)}
                  className={cn(
                    "devora-input",
                    errors.phone && "border-red-500 focus:border-red-500"
                  )}
                  placeholder="Skriv inn telefonnummer"
                  disabled={isSubmitting || isBankIDLoading}
                />
              </div>
              {errors.phone && (
                <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                  <AlertCircle className="h-4 w-4" />
                  {errors.phone}
                </p>
              )}
            </div>

            {/* Delivery address section title */}
            <div>
              <Label className="text-devora-primary-dark font-semibold">
                Hvilken adresse bestiller du strøm til?
              </Label>
            </div>

            {/* 4. Gate (required) */}
            <div>
              <Label htmlFor="address" className="text-devora-primary-dark font-medium">
                Gate *
              </Label>
              <div className="relative mt-1">
                <Input
                  id="address"
                  name="address"
                  type="text"
                  value={formData.address}
                  onChange={(e) => handleInputChange('address', e.target.value)}
                  className={cn(
                    "devora-input",
                    errors.address && "border-red-500 focus:border-red-500"
                  )}
                  placeholder="Skriv inn gateadresse"
                  disabled={isSubmitting || isBankIDLoading}
                />
              </div>
              {errors.address && (
                <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                  <AlertCircle className="h-4 w-4" />
                  {errors.address}
                </p>
              )}
            </div>

            {/* 5. Postnummer + Sted (both required) */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="zip" className="text-devora-primary-dark font-medium">
                  Postnummer *
                </Label>
                <div className="relative mt-1">
                  <Input
                    id="zip"
                    name="zip"
                    type="text"
                    value={formData.zip}
                    onChange={(e) => handleInputChange('zip', e.target.value)}
                    className={cn(
                      "devora-input",
                      errors.zip && "border-red-500 focus:border-red-500"
                    )}
                    placeholder="Postnummer"
                    disabled={isSubmitting || isBankIDLoading}
                  />
                </div>
                {errors.zip && (
                  <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                    <AlertCircle className="h-4 w-4" />
                    {errors.zip}
                  </p>
                )}
              </div>

              <div>
                <Label htmlFor="city" className="text-devora-primary-dark font-medium">
                  Sted *
                </Label>
                <div className="relative mt-1">
                  <Input
                    id="city"
                    name="city"
                    type="text"
                    value={formData.city}
                    onChange={(e) => handleInputChange('city', e.target.value)}
                    className={cn(
                      "devora-input",
                      errors.city && "border-red-500 focus:border-red-500"
                    )}
                    placeholder="Poststed"
                    disabled={isSubmitting || isBankIDLoading}
                  />
                </div>
                {errors.city && (
                  <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                    <AlertCircle className="h-4 w-4" />
                    {errors.city}
                  </p>
                )}
              </div>
            </div>

            {/* Billing address checkbox */}
            <div className="flex items-center gap-2 pt-2">
              <input
                id="useSameAddressForBilling"
                name="useSameAddressForBilling"
                type="checkbox"
                checked={formData.useSameAddressForBilling}
                onChange={(e) => handleInputChange('useSameAddressForBilling', e.target.checked)}
                className="h-4 w-4"
                disabled={isSubmitting || isBankIDLoading}
              />
              <Label htmlFor="useSameAddressForBilling" className="text-devora-primary-dark font-medium">
                Dette er også min fakturaaddresse
              </Label>
            </div>

            {/* Conditional billing address section */}
            {!formData.useSameAddressForBilling && (
              <div className="space-y-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                <div>
                  <h3 className="text-lg font-semibold text-devora-primary-dark mb-2">
                    Fakturaaddresse
                  </h3>
                </div>

                <div>
                  <Label htmlFor="billingAddress" className="text-devora-primary-dark font-medium">
                    Adresse *
                  </Label>
                  <div className="relative mt-1">
                    <Input
                      id="billingAddress"
                      name="billingAddress"
                      type="text"
                      value={formData.billingAddress}
                      onChange={(e) => handleInputChange('billingAddress', e.target.value)}
                      className={cn(
                        "devora-input",
                        errors.billingAddress && "border-red-500 focus:border-red-500"
                      )}
                      placeholder="Skriv inn fakturaadresse"
                      disabled={isSubmitting || isBankIDLoading}
                      required
                    />
                  </div>
                  {errors.billingAddress && (
                    <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                      <AlertCircle className="h-4 w-4" />
                      {errors.billingAddress}
                    </p>
                  )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="billingZip" className="text-devora-primary-dark font-medium">
                      Postnummer *
                    </Label>
                    <div className="relative mt-1">
                      <Input
                        id="billingZip"
                        name="billingZip"
                        type="text"
                        value={formData.billingZip}
                        onChange={(e) => handleInputChange('billingZip', e.target.value)}
                        className={cn(
                          "devora-input",
                          errors.billingZip && "border-red-500 focus:border-red-500"
                        )}
                        placeholder="Postnummer"
                        disabled={isSubmitting || isBankIDLoading}
                        required
                      />
                    </div>
                    {errors.billingZip && (
                      <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                        <AlertCircle className="h-4 w-4" />
                        {errors.billingZip}
                      </p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="billingCity" className="text-devora-primary-dark font-medium">
                      Sted *
                    </Label>
                    <div className="relative mt-1">
                      <Input
                        id="billingCity"
                        name="billingCity"
                        type="text"
                        value={formData.billingCity}
                        onChange={(e) => handleInputChange('billingCity', e.target.value)}
                        className={cn(
                          "devora-input",
                          errors.billingCity && "border-red-500 focus:border-red-500"
                        )}
                        placeholder="Poststed"
                        disabled={isSubmitting || isBankIDLoading}
                        required
                      />
                    </div>
                    {errors.billingCity && (
                      <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                        <AlertCircle className="h-4 w-4" />
                        {errors.billingCity}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* 6. Overtakelses-/startdato */}
            <div>
              <Label htmlFor="takeoverDate" className="text-devora-primary-dark font-medium">
                Overtakelses-/startdato
              </Label>
              <div className="relative mt-1">
                <Input
                  id="takeoverDate"
                  name="takeoverDate"
                  type="date"
                  value={formData.takeoverDate}
                  onChange={(e) => handleInputChange('takeoverDate', e.target.value)}
                  className={cn(
                    "devora-input",
                    errors.takeoverDate && "border-red-500 focus:border-red-500"
                  )}
                  disabled={isSubmitting || isBankIDLoading}
                />
              </div>
              {errors.takeoverDate && (
                <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                  <AlertCircle className="h-4 w-4" />
                  {errors.takeoverDate}
                </p>
              )}
            </div>

            {/* 7. MålepunktID og Målenummer Section */}
            <div className="space-y-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
              {/* Section Header */}
              <div>
                <h3 className="text-lg font-semibold text-devora-primary-dark mb-2">
                  MålepunktID og Målenummer
                </h3>
                <p className="text-sm text-gray-600">
                  Disse tallene finner du på din siste strømfaktura eller ved å logge inn på elhub.no. 
                  Dersom du ikke har dette tilgjengelig, trenger du ikke å skrive noe. Vi hjelper deg etter fullført bestilling.
                </p>
              </div>

              {/* 7a. MålepunktID Field (optional, 18 digits, prefix 7070575) */}
              <div>
                <Label htmlFor="meterNumber" className="text-devora-primary-dark font-medium">
                  MålepunktID
                </Label>
                <div className="relative mt-1">
                  <Input
                    id="meterNumber"
                    name="meterNumber"
                    type="text"
                    value={formData.meterNumber}
                    onChange={(e) => {
                      // Only allow numeric input
                      const numericValue = e.target.value.replace(/\D/g, '');
                      // Limit to 18 digits
                      const limitedValue = numericValue.slice(0, 18);
                      handleInputChange('meterNumber', limitedValue);
                    }}
                    className={cn(
                      "devora-input",
                      errors.meterNumber && "border-red-500 focus:border-red-500"
                    )}
                    placeholder="Skriv inn målepunktID (18 siffer)"
                    maxLength={18}
                    disabled={isSubmitting || isBankIDLoading}
                  />
                </div>
                {errors.meterNumber && (
                  <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                    <AlertCircle className="h-4 w-4" />
                    {errors.meterNumber}
                  </p>
                )}
              </div>

              {/* 7b. Målenummer Field (optional, no validation) */}
              <div>
                <Label htmlFor="serialNumber" className="text-devora-primary-dark font-medium">
                  Målenummer
                </Label>
                <div className="relative mt-1">
                  <Input
                    id="serialNumber"
                    name="serialNumber"
                    type="text"
                    value={formData.serialNumber}
                    onChange={(e) => {
                      // Only allow numeric input
                      const numericValue = e.target.value.replace(/\D/g, '');
                      // Limit to 18 digits (max practical length)
                      const limitedValue = numericValue.slice(0, 18);
                      handleInputChange('serialNumber', limitedValue);
                    }}
                    className="devora-input"
                    placeholder="Skriv inn målenummer"
                    maxLength={18}
                    disabled={isSubmitting || isBankIDLoading}
                  />
                </div>
              </div>
            </div>
            
            {/* Phase 3: Sports Team Selection (only for "Støtt ditt idrettslag" product) */}
            {isSportsTeamProduct && (
              <div>
                <Label htmlFor="sportsTeam" className="text-devora-primary-dark font-medium">
                  Velg idrettslag du vil støtte *
                </Label>
                <div className="relative mt-1">
                  <select
                    id="sportsTeam"
                    name="sportsTeam"
                    value={formData.sportsTeam}
                    onChange={(e) => handleInputChange('sportsTeam', e.target.value)}
                    className={cn(
                      "devora-input w-full",
                      errors.sportsTeam && "border-red-500 focus:border-red-500"
                    )}
                    disabled={isSubmitting || isBankIDLoading}
                  >
                    <option value="">Velg idrettslag...</option>
                    {SPORTS_TEAM_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
                {errors.sportsTeam && (
                  <p className="mt-1 text-sm text-red-600 flex items-center gap-1">
                    <AlertCircle className="h-4 w-4" />
                    {errors.sportsTeam}
                  </p>
                )}
              </div>
            )}
          </div>
          
          {/* Phase 3: Marketing Consent Checkboxes (all products) */}
          <div className="space-y-3 bg-devora-background-light p-4 rounded-lg border border-devora-primary-light">
            <h4 className="font-medium text-devora-primary-dark break-words overflow-wrap-anywhere">Markedsføringssamtykke (valgfritt)</h4>
            <p className="text-sm text-devora-text-secondary">
              Jeg samtykker til å motta markedsføring fra {productId ? 'leverandøren' : 'UtilitySign'}:
            </p>
            <div className="flex items-center gap-2">
              <input
                id="marketingConsentEmail"
                name="marketingConsentEmail"
                type="checkbox"
                checked={formData.marketingConsentEmail}
                onChange={(e) => handleInputChange('marketingConsentEmail', e.target.checked)}
                className="h-4 w-4 text-devora-primary border-gray-300 rounded focus:ring-devora-primary"
                disabled={isSubmitting || isBankIDLoading}
              />
              <Label htmlFor="marketingConsentEmail" className="text-sm cursor-pointer">
                Ja, jeg ønsker å motta markedsføring via e-post
              </Label>
            </div>
            <div className="flex items-center gap-2">
              <input
                id="marketingConsentSms"
                name="marketingConsentSms"
                type="checkbox"
                checked={formData.marketingConsentSms}
                onChange={(e) => handleInputChange('marketingConsentSms', e.target.checked)}
                className="h-4 w-4 text-devora-primary border-gray-300 rounded focus:ring-devora-primary"
                disabled={isSubmitting || isBankIDLoading}
              />
              <Label htmlFor="marketingConsentSms" className="text-sm cursor-pointer">
                Ja, jeg ønsker å motta markedsføring via SMS
              </Label>
            </div>
          </div>

          {/* BankID Information */}
          {enableBankID && (
            <div className="bg-devora-background-light p-4 rounded-lg border border-devora-primary-light">
              <div className="flex items-start gap-3">
                <Shield className="h-5 w-5 text-devora-primary mt-0.5" />
                <div>
                  <h4 className="font-medium text-devora-primary-dark">
                    BankID-autentisering
                  </h4>
                  <p className="text-sm text-devora-text-secondary mt-1">
                    Du vil bli omdirigert til BankID for sikker autentisering. 
                    Dette sikrer at din identitet er verifisert før du signerer dokumentet.
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Email Notifications */}
          {enableEmailNotifications && (
            <div className="bg-devora-background-light p-4 rounded-lg border border-devora-primary-light">
              <div className="flex items-start gap-3">
                <Mail className="h-5 w-5 text-devora-primary mt-0.5" />
                <div>
                  <h4 className="font-medium text-devora-primary-dark">
                    E-postvarsler
                  </h4>
                  <p className="text-sm text-devora-text-secondary mt-1">
                    Du vil motta e-postoppdateringer om signeringsprosessen og 
                    en kopi av det signerte dokumentet.
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Submit Button */}
          <div className="pt-4">
            <Button
              type="submit"
              variant="primary"
              size="lg"
              disabled={isSubmitting || isBankIDLoading}
              className="w-full devora-button-primary"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                  Oppretter signeringsforespørsel...
                </>
              ) : isBankIDLoading ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                  Starter BankID...
                </>
              ) : enableBankID ? (
                <>
                  Bli kunde med BankID
                </>
              ) : (
                <>
                  <CheckCircle className="h-4 w-4 mr-2" />
                  Opprett signeringsforespørsel
                </>
              )}
            </Button>
            
            {/* Footer */}
            <div className="mt-3 text-center">
              <p className="text-xs text-devora-text-secondary">
                Digital signeringsløsning levert av{' '}
                <a
                  href="https://utilitysign.no"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-devora-primary hover:text-devora-primary-dark underline"
                >
                  UtilitySign
                </a>
                {' '}fra{' '}
                <a
                  href="https://devora.no"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-devora-primary hover:text-devora-primary-dark underline"
                >
                  Devora
                </a>
              </p>
            </div>
          </div>

          {/* Current Request Status */}
          {currentRequest && (
            <div className="pt-4 border-t border-devora-primary-light">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-devora-primary-dark">
                    Forespørsel-ID: {currentRequest.id}
                  </p>
                  <p className="text-xs text-devora-text-secondary">
                    Status: {currentRequest.status.toUpperCase()}
                  </p>
                </div>
                {currentRequest.status === 'in_progress' && (
                  <div className="flex items-center gap-2 text-sm text-devora-primary">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Behandler...
                  </div>
                )}
              </div>
            </div>
          )}
        </form>
      </CardContent>
    </Card>
  );
};

export default SigningForm;
