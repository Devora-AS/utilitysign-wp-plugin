import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../devora/Card';
import { Button } from '../devora/Button';
import { DocumentUpload } from './DocumentUpload';
import { DocumentPreview } from './DocumentPreview';
import { SigningForm } from './SigningForm';
import { SigningStatus } from './SigningStatus';
import { LoadingSpinner } from '../ui/LoadingSpinner';
import { ErrorAlert } from '../ui/ErrorAlert';
import { Document, SigningRequest, APIResponse } from '../../lib/api-client';
import { useAPIClient } from '../APIClientProvider';
import { FileText, Upload, CheckCircle, AlertCircle } from 'lucide-react';

export interface DocumentSigningWorkflowProps {
  documentId?: string;
  onSuccess?: (result: SigningResult) => void;
  onError?: (error: string) => void;
  className?: string;
  showProgress?: boolean;
}

export interface SigningResult {
  success: boolean;
  documentId: string;
  signedDocumentUrl?: string;
  error?: string;
}

export type WorkflowStep = 'upload' | 'preview' | 'signing' | 'status' | 'completed';

export const DocumentSigningWorkflow: React.FC<DocumentSigningWorkflowProps> = ({
  documentId,
  onSuccess,
  onError,
  className = '',
  showProgress = true,
}) => {
  const apiClient = useAPIClient();
  const [currentStep, setCurrentStep] = useState<WorkflowStep>('upload');
  const [document, setDocument] = useState<Document | null>(null);
  const [signingRequest, setSigningRequest] = useState<SigningRequest | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [uploadedFile, setUploadedFile] = useState<File | null>(null);

  // Load existing document if documentId is provided
  useEffect(() => {
    if (documentId) {
      loadDocument(documentId);
    }
  }, [documentId]);

  const loadDocument = async (id: string) => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await apiClient.getDocument(id);
      
      if (response.success && response.data) {
        setDocument(response.data);
        setCurrentStep('preview');
      } else {
        throw new Error(response.error || 'Failed to load document');
      }
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Unknown error occurred';
      setError(errorMessage);
      onError?.(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleDocumentUploaded = (file: File, documentData: Document) => {
    setUploadedFile(file);
    setDocument(documentData);
    setCurrentStep('preview');
    setError(null);
  };

  const handlePreviewConfirmed = () => {
    setCurrentStep('signing');
  };

  const handleSigningInitiated = (request: SigningRequest) => {
    setSigningRequest(request);
    setCurrentStep('status');
  };

  const handleSigningCompleted = (result: SigningResult) => {
    setCurrentStep('completed');
    onSuccess?.(result);
  };

  const handleError = (errorMessage: string) => {
    setError(errorMessage);
    onError?.(errorMessage);
  };

  const handleRetry = () => {
    setError(null);
    setCurrentStep('upload');
  };

  const getStepIcon = (step: WorkflowStep) => {
    switch (step) {
      case 'upload':
        return <Upload className="w-5 h-5" />;
      case 'preview':
        return <FileText className="w-5 h-5" />;
      case 'signing':
        return <FileText className="w-5 h-5" />;
      case 'status':
        return <CheckCircle className="w-5 h-5" />;
      case 'completed':
        return <CheckCircle className="w-5 h-5" />;
      default:
        return <FileText className="w-5 h-5" />;
    }
  };

  const getStepTitle = (step: WorkflowStep) => {
    switch (step) {
      case 'upload':
        return 'Upload Document';
      case 'preview':
        return 'Preview Document';
      case 'signing':
        return 'Sign Document';
      case 'status':
        return 'Signing Status';
      case 'completed':
        return 'Signing Completed';
      default:
        return 'Document Signing';
    }
  };

  const renderCurrentStep = () => {
    if (loading) {
      return (
        <div className="flex flex-col items-center justify-center py-12">
          <LoadingSpinner size="lg" />
          <p className="mt-4 text-devora-text-secondary">Loading document...</p>
        </div>
      );
    }

    if (error) {
      return (
        <div className="space-y-4">
          <ErrorAlert 
            title="Error occurred"
            message={error}
            onRetry={handleRetry}
          />
        </div>
      );
    }

    switch (currentStep) {
      case 'upload':
        return (
          <DocumentUpload
            onDocumentUploaded={handleDocumentUploaded}
            onError={handleError}
            loading={loading}
          />
        );
      
      case 'preview':
        return (
          <DocumentPreview
            document={document!}
            file={uploadedFile}
            onConfirm={handlePreviewConfirmed}
            onError={handleError}
            loading={loading}
          />
        );
      
      case 'signing':
        return (
          <SigningForm
            document={document!}
            onSigningInitiated={handleSigningInitiated}
            onError={handleError}
            loading={loading}
          />
        );
      
      case 'status':
        return (
          <SigningStatus
            signingRequest={signingRequest!}
            onSigningCompleted={handleSigningCompleted}
            onError={handleError}
            loading={loading}
          />
        );
      
      case 'completed':
        return (
          <div className="text-center py-12">
            <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
            <h3 className="text-xl font-heading font-black text-devora-primary mb-2">
              Document Signed Successfully!
            </h3>
            <p className="text-devora-text-secondary mb-6">
              Your document has been signed and is ready for download.
            </p>
            {document?.pdf_url && (
              <Button
                variant="primary"
                onClick={() => window.open(document.pdf_url, '_blank')}
                className="mr-4"
              >
                Download Signed Document
              </Button>
            )}
            <Button
              variant="secondary"
              onClick={() => {
                setCurrentStep('upload');
                setDocument(null);
                setSigningRequest(null);
                setUploadedFile(null);
              }}
            >
              Sign Another Document
            </Button>
          </div>
        );
      
      default:
        return null;
    }
  };

  return (
    <div className={`devora-signing-workflow ${className}`}>
      {showProgress && (
        <div className="mb-8">
          <div className="flex items-center justify-between">
            {(['upload', 'preview', 'signing', 'status', 'completed'] as WorkflowStep[]).map((step, index) => (
              <div key={step} className="flex items-center">
                <div className={`
                  flex items-center justify-center w-10 h-10 rounded-full border-2 transition-colors
                  ${currentStep === step 
                    ? 'border-devora-primary bg-devora-primary text-white' 
                    : index < (['upload', 'preview', 'signing', 'status', 'completed'] as WorkflowStep[]).indexOf(currentStep)
                    ? 'border-devora-primary bg-devora-primary text-white'
                    : 'border-devora-primary-light bg-white text-devora-primary-light'
                  }
                `}>
                  {getStepIcon(step)}
                </div>
                {index < 4 && (
                  <div className={`
                    w-16 h-0.5 mx-2 transition-colors
                    ${index < (['upload', 'preview', 'signing', 'status', 'completed'] as WorkflowStep[]).indexOf(currentStep)
                      ? 'bg-devora-primary'
                      : 'bg-devora-primary-light'
                    }
                  `} />
                )}
              </div>
            ))}
          </div>
          <div className="mt-4 text-center">
            <h2 className="text-lg font-heading font-black text-devora-primary">
              {getStepTitle(currentStep)}
            </h2>
          </div>
        </div>
      )}

      <Card variant="white">
        <CardContent className="p-6">
          {renderCurrentStep()}
        </CardContent>
      </Card>
    </div>
  );
};

export default DocumentSigningWorkflow;
