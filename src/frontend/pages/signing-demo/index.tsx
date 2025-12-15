import React, { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../../components/devora/Card';
import { Button } from '../../components/devora/Button';
import { DocumentSigningWorkflow } from '../../components/signing';
import { CheckCircle, AlertCircle, FileText } from 'lucide-react';

const SigningDemo: React.FC = () => {
  const [demoMode, setDemoMode] = useState<'upload' | 'existing'>('upload');
  const [workflowResult, setWorkflowResult] = useState<any>(null);
  const [workflowError, setWorkflowError] = useState<string | null>(null);

  const handleWorkflowSuccess = (result: any) => {
    setWorkflowResult(result);
    setWorkflowError(null);
  };

  const handleWorkflowError = (error: string) => {
    setWorkflowError(error);
    setWorkflowResult(null);
  };

  const resetDemo = () => {
    setWorkflowResult(null);
    setWorkflowError(null);
  };

  return (
    <div className="min-h-screen bg-devora-background-light py-8">
      <div className="container mx-auto px-4 max-w-4xl">
        <div className="text-center mb-8">
          <h1 className="devora-heading text-3xl font-black text-devora-primary-dark mb-4">
            Document Signing Workflow Demo
          </h1>
          <p className="devora-body text-lg text-devora-text-secondary">
            Experience the complete document signing process with BankID integration
          </p>
        </div>

        {/* Demo Mode Selection */}
        <Card className="mb-8">
          <CardHeader>
            <CardTitle className="devora-heading">Demo Mode</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex gap-4 mb-4">
              <Button
                variant={demoMode === 'upload' ? 'primary' : 'outline'}
                onClick={() => setDemoMode('upload')}
              >
                Upload New Document
              </Button>
              <Button
                variant={demoMode === 'existing' ? 'primary' : 'outline'}
                onClick={() => setDemoMode('existing')}
              >
                Use Existing Document
              </Button>
            </div>
            <p className="text-sm text-devora-text-secondary">
              {demoMode === 'upload' 
                ? 'Upload a PDF document and go through the complete signing workflow'
                : 'Use a pre-existing document ID to test the signing process'
              }
            </p>
          </CardContent>
        </Card>

        {/* Workflow Component */}
        <Card className="mb-8">
          <CardHeader>
            <CardTitle className="devora-heading flex items-center gap-2">
              <FileText className="h-5 w-5" />
              Document Signing Workflow
            </CardTitle>
          </CardHeader>
          <CardContent>
            <DocumentSigningWorkflow
              documentId={demoMode === 'existing' ? 'demo-doc-123' : undefined}
              onSuccess={handleWorkflowSuccess}
              onError={handleWorkflowError}
              showProgress={true}
            />
          </CardContent>
        </Card>

        {/* Results Display */}
        {workflowResult && (
          <Card className="mb-8 border-green-200 bg-green-50">
            <CardHeader>
              <CardTitle className="devora-heading flex items-center gap-2 text-green-800">
                <CheckCircle className="h-5 w-5" />
                Workflow Completed Successfully
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                <div>
                  <strong className="text-green-800">Document:</strong>
                  <p className="text-green-700">{workflowResult.document?.title || 'N/A'}</p>
                </div>
                <div>
                  <strong className="text-green-800">Signing Request ID:</strong>
                  <p className="text-green-700 font-mono text-sm">{workflowResult.signingRequest?.id || 'N/A'}</p>
                </div>
                <div>
                  <strong className="text-green-800">Status:</strong>
                  <p className="text-green-700">{workflowResult.signingRequest?.status || 'N/A'}</p>
                </div>
                {workflowResult.signingRequest?.completed_at && (
                  <div>
                    <strong className="text-green-800">Completed At:</strong>
                    <p className="text-green-700">
                      {new Date(workflowResult.signingRequest.completed_at).toLocaleString('no-NO')}
                    </p>
                  </div>
                )}
              </div>
              <div className="mt-4">
                <Button variant="outline" onClick={resetDemo}>
                  Start New Workflow
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {workflowError && (
          <Card className="mb-8 border-red-200 bg-red-50">
            <CardHeader>
              <CardTitle className="devora-heading flex items-center gap-2 text-red-800">
                <AlertCircle className="h-5 w-5" />
                Workflow Error
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-red-700 mb-4">{workflowError}</p>
              <Button variant="outline" onClick={resetDemo}>
                Try Again
              </Button>
            </CardContent>
          </Card>
        )}

        {/* Feature Overview */}
        <Card>
          <CardHeader>
            <CardTitle className="devora-heading">Features Demonstrated</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid md:grid-cols-2 gap-6">
              <div>
                <h3 className="font-ui font-semibold text-devora-primary-dark mb-3">
                  Document Management
                </h3>
                <ul className="space-y-2 text-sm text-devora-text-secondary">
                  <li>• PDF document upload with validation</li>
                  <li>• Document preview with iframe integration</li>
                  <li>• Document metadata display</li>
                  <li>• Download functionality</li>
                </ul>
              </div>
              <div>
                <h3 className="font-ui font-semibold text-devora-primary-dark mb-3">
                  Signing Process
                </h3>
                <ul className="space-y-2 text-sm text-devora-text-secondary">
                  <li>• Signer information collection</li>
                  <li>• BankID authentication integration</li>
                  <li>• Real-time status tracking</li>
                  <li>• Error handling and retry logic</li>
                </ul>
              </div>
              <div>
                <h3 className="font-ui font-semibold text-devora-primary-dark mb-3">
                  User Experience
                </h3>
                <ul className="space-y-2 text-sm text-devora-text-secondary">
                  <li>• Step-by-step progress indicator</li>
                  <li>• Loading states and feedback</li>
                  <li>• Responsive design</li>
                  <li>• Accessibility compliance</li>
                </ul>
              </div>
              <div>
                <h3 className="font-ui font-semibold text-devora-primary-dark mb-3">
                  Integration
                </h3>
                <ul className="space-y-2 text-sm text-devora-text-secondary">
                  <li>• UtilitySign API integration</li>
                  <li>• Microsoft Entra ID authentication</li>
                  <li>• Criipto BankID integration</li>
                  <li>• WordPress plugin compatibility</li>
                </ul>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};

export default SigningDemo;
