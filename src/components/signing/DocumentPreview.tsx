import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../devora/Card';
import { Button } from '../devora/Button';
import { 
  FileText, 
  Download, 
  Eye, 
  CheckCircle, 
  AlertCircle,
  Loader2
} from 'lucide-react';
import { cn } from '../../lib/utils';
import { Document } from '../../lib/api-client';

export interface DocumentPreviewProps {
  document: Document;
  onProceedToSign: () => void;
  onBack?: () => void;
  onError?: (error: string) => void;
  className?: string;
  showDownloadButton?: boolean;
}

const DocumentPreview: React.FC<DocumentPreviewProps> = ({
  document,
  onProceedToSign,
  onBack,
  onError,
  className,
  showDownloadButton = true,
}) => {
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const [pdfUrl, setPdfUrl] = useState<string | null>(null);

  useEffect(() => {
    // Set up PDF preview URL
    if (document.pdf_url) {
      setPdfUrl(document.pdf_url);
      setIsLoading(false);
    } else {
      // If no PDF URL, we might need to generate one or show an error
      setPreviewError('No preview available for this document');
      setIsLoading(false);
    }
  }, [document]);

  const handleDownload = () => {
    if (document.pdf_url) {
      const link = document.createElement('a');
      link.href = document.pdf_url;
      link.download = document.title || 'document.pdf';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  };

  const getStatusIcon = (status: Document['status']) => {
    switch (status) {
      case 'draft':
        return <FileText className="h-5 w-5 text-devora-text-secondary" />;
      case 'pending':
        return <AlertCircle className="h-5 w-5 text-yellow-500" />;
      case 'signed':
        return <CheckCircle className="h-5 w-5 text-green-500" />;
      case 'rejected':
        return <AlertCircle className="h-5 w-5 text-red-500" />;
      default:
        return <FileText className="h-5 w-5 text-devora-text-secondary" />;
    }
  };

  const getStatusText = (status: Document['status']) => {
    switch (status) {
      case 'draft':
        return 'Draft';
      case 'pending':
        return 'Pending Signature';
      case 'signed':
        return 'Signed';
      case 'rejected':
        return 'Rejected';
      default:
        return 'Unknown';
    }
  };

  const getStatusColor = (status: Document['status']) => {
    switch (status) {
      case 'draft':
        return 'text-devora-text-secondary';
      case 'pending':
        return 'text-yellow-600';
      case 'signed':
        return 'text-green-600';
      case 'rejected':
        return 'text-red-600';
      default:
        return 'text-devora-text-secondary';
    }
  };

  if (isLoading) {
    return (
      <Card className={className}>
        <CardContent className="flex items-center justify-center py-12">
          <div className="flex flex-col items-center gap-3">
            <Loader2 className="h-8 w-8 animate-spin text-devora-primary" />
            <p className="text-sm text-devora-text-secondary">Loading document preview...</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (previewError) {
    return (
      <Card className={className}>
        <CardHeader>
          <CardTitle className="devora-heading flex items-center gap-2">
            <AlertCircle className="h-5 w-5 text-red-500" />
            Preview Error
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8">
            <AlertCircle className="h-12 w-12 text-red-500 mx-auto mb-4" />
            <p className="text-devora-text-secondary mb-4">{previewError}</p>
            <Button variant="outline" onClick={onBack}>
              Go Back
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className={className}>
      <CardHeader>
        <CardTitle className="devora-heading flex items-center gap-2">
          <FileText className="h-5 w-5" />
          Document Preview
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* Document Info */}
        <div className="bg-devora-background-light p-4 rounded-lg">
          <div className="flex items-start justify-between mb-3">
            <div className="flex-1">
              <h3 className="font-ui font-semibold text-devora-primary-dark mb-1">
                {document.title}
              </h3>
              <p className="text-sm text-devora-text-secondary">
                Created: {new Date(document.created_at).toLocaleDateString('no-NO')}
              </p>
              {document.updated_at !== document.created_at && (
                <p className="text-sm text-devora-text-secondary">
                  Updated: {new Date(document.updated_at).toLocaleDateString('no-NO')}
                </p>
              )}
            </div>
            <div className="flex items-center gap-2">
              {getStatusIcon(document.status)}
              <span className={cn("text-sm font-medium", getStatusColor(document.status))}>
                {getStatusText(document.status)}
              </span>
            </div>
          </div>
          
          {document.signer_email && (
            <div className="text-sm text-devora-text-secondary">
              <strong>Signer:</strong> {document.signer_name || 'Unknown'} ({document.signer_email})
            </div>
          )}
          
          {document.signed_at && (
            <div className="text-sm text-devora-text-secondary">
              <strong>Signed:</strong> {new Date(document.signed_at).toLocaleString('no-NO')}
            </div>
          )}
        </div>

        {/* PDF Preview */}
        {pdfUrl && (
          <div className="border border-devora-primary-light rounded-lg overflow-hidden">
            <div className="bg-devora-background-light px-4 py-2 border-b border-devora-primary-light">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-devora-primary-dark">
                  Document Preview
                </span>
                {showDownloadButton && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleDownload}
                    className="flex items-center gap-1"
                  >
                    <Download className="h-4 w-4" />
                    Download
                  </Button>
                )}
              </div>
            </div>
            <div className="h-96 bg-gray-50">
              <iframe
                src={pdfUrl}
                className="w-full h-full border-0"
                title="Document Preview"
                onLoad={() => setIsLoading(false)}
                onError={() => {
                  setPreviewError('Failed to load document preview');
                  setIsLoading(false);
                }}
              />
            </div>
          </div>
        )}

        {/* Action Buttons */}
        <div className="flex gap-3 justify-end">
          {onBack && (
            <Button variant="outline" onClick={onBack}>
              Back
            </Button>
          )}
          <Button 
            onClick={onProceedToSign}
            disabled={document.status === 'signed'}
            className="flex items-center gap-2"
          >
            <Eye className="h-4 w-4" />
            {document.status === 'signed' ? 'Already Signed' : 'Proceed to Sign'}
          </Button>
        </div>

        {/* Additional Info */}
        {document.metadata && Object.keys(document.metadata).length > 0 && (
          <div className="bg-devora-background-light p-4 rounded-lg">
            <h4 className="text-sm font-medium text-devora-primary-dark mb-2">
              Document Information
            </h4>
            <div className="space-y-1 text-sm text-devora-text-secondary">
              {document.metadata.template_id && (
                <div><strong>Template ID:</strong> {document.metadata.template_id}</div>
              )}
              {document.metadata.variables && Object.keys(document.metadata.variables).length > 0 && (
                <div>
                  <strong>Variables:</strong>
                  <pre className="mt-1 text-xs bg-white p-2 rounded border">
                    {JSON.stringify(document.metadata.variables, null, 2)}
                  </pre>
                </div>
              )}
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
};

export default DocumentPreview;