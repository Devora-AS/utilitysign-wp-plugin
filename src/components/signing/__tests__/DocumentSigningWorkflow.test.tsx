import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { DocumentSigningWorkflow } from '../DocumentSigningWorkflow';
import { Document } from '../../../lib/api-client';

// Mock the API client
jest.mock('../../../lib/api-client', () => ({
  __esModule: true,
  default: {
    getDocument: jest.fn(),
    createSigningRequest: jest.fn(),
    initiateBankID: jest.fn(),
    checkBankIDStatus: jest.fn(),
  },
}));

// Mock the child components
jest.mock('../DocumentUpload', () => ({
  DocumentUpload: ({ onDocumentUpload, onError }: any) => (
    <div data-testid="document-upload">
      <button 
        onClick={() => onDocumentUpload({
          id: 'test-doc-1',
          title: 'Test Document.pdf',
          content: 'Test content',
          status: 'draft',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
          metadata: {}
        })}
      >
        Upload Test Document
      </button>
      <button onClick={() => onError('Upload failed')}>
        Trigger Upload Error
      </button>
    </div>
  ),
}));

jest.mock('../DocumentPreview', () => ({
  DocumentPreview: ({ onProceedToSign, onBack }: any) => (
    <div data-testid="document-preview">
      <button onClick={onProceedToSign}>Proceed to Sign</button>
      <button onClick={onBack}>Back to Upload</button>
    </div>
  ),
}));

jest.mock('../SigningForm', () => ({
  SigningForm: ({ onSigningRequestCreated, onSigningCompleted, onBack }: any) => (
    <div data-testid="signing-form">
      <button 
        onClick={() => onSigningRequestCreated({
          id: 'test-request-1',
          document_id: 'test-doc-1',
          signer_email: 'test@example.com',
          signer_name: 'Test User',
          status: 'pending',
          created_at: new Date().toISOString(),
          expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
        })}
      >
        Create Signing Request
      </button>
      <button 
        onClick={() => onSigningCompleted({
          id: 'test-request-1',
          document_id: 'test-doc-1',
          signer_email: 'test@example.com',
          signer_name: 'Test User',
          status: 'completed',
          created_at: new Date().toISOString(),
          expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
          completed_at: new Date().toISOString(),
        })}
      >
        Complete Signing
      </button>
      <button onClick={onBack}>Back to Preview</button>
    </div>
  ),
}));

jest.mock('../SigningStatus', () => ({
  SigningStatus: ({ onBack }: any) => (
    <div data-testid="signing-status">
      <div>Signing Completed Successfully!</div>
      <button onClick={onBack}>Back to Signing</button>
    </div>
  ),
}));

jest.mock('../../ui/LoadingSpinner', () => ({
  LoadingSpinner: () => <div data-testid="loading-spinner">Loading...</div>,
}));

jest.mock('../../ui/ErrorAlert', () => ({
  ErrorAlert: ({ message, onClose }: any) => (
    <div data-testid="error-alert">
      <div>{message}</div>
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));

describe('DocumentSigningWorkflow', () => {
  const mockOnSuccess = jest.fn();
  const mockOnError = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders upload step by default', () => {
    render(
      <DocumentSigningWorkflow
        onSuccess={mockOnSuccess}
        onError={mockOnError}
      />
    );

    expect(screen.getByTestId('document-upload')).toBeInTheDocument();
    expect(screen.getByText('Upload Test Document')).toBeInTheDocument();
  });

  it('shows progress steps correctly', () => {
    render(
      <DocumentSigningWorkflow
        onSuccess={mockOnSuccess}
        onError={mockOnError}
        showProgress={true}
      />
    );

    expect(screen.getByText('Upload')).toBeInTheDocument();
    expect(screen.getByText('Preview')).toBeInTheDocument();
    expect(screen.getByText('Sign')).toBeInTheDocument();
    expect(screen.getByText('Status')).toBeInTheDocument();
  });

  it('hides progress steps when showProgress is false', () => {
    render(
      <DocumentSigningWorkflow
        onSuccess={mockOnSuccess}
        onError={mockOnError}
        showProgress={false}
      />
    );

    expect(screen.queryByText('Upload')).not.toBeInTheDocument();
    expect(screen.queryByText('Preview')).not.toBeInTheDocument();
    expect(screen.queryByText('Sign')).not.toBeInTheDocument();
    expect(screen.queryByText('Status')).not.toBeInTheDocument();
  });

  it('transitions from upload to preview when document is uploaded', async () => {
    render(
      <DocumentSigningWorkflow
        onSuccess={mockOnSuccess}
        onError={mockOnError}
      />
    );

    // Start at upload step
    expect(screen.getByTestId('document-upload')).toBeInTheDocument();

    // Upload a document
    fireEvent.click(screen.getByText('Upload Test Document'));

    await waitFor(() => {
      expect(screen.getByTestId('document-preview')).toBeInTheDocument();
    });
  });

  it('transitions from preview to sign when proceed is clicked', async () => {
    render(
      <DocumentSigningWorkflow
        onSuccess={mockOnSuccess}
        onError={mockOnError}
      />
    );

    // Upload document first
    fireEvent.click(screen.getByText('Upload Test Document'));

    await waitFor(() => {
      expect(screen.getByTestId('document-preview')).toBeInTheDocument();
    });

    // Proceed to sign
    fireEvent.click(screen.getByText('Proceed to Sign'));

    await waitFor(() => {
      expect(screen.getByTestId('signing-form')).toBeInTheDocument();
    });
  });

  it('transitions from sign to status when signing is completed', async () => {
    render(
      <DocumentSigningWorkflow
        onSuccess={mockOnSuccess}
        onError={mockOnError}
      />
    );

    // Go through the workflow
    fireEvent.click(screen.getByText('Upload Test Document'));

    await waitFor(() => {
      fireEvent.click(screen.getByText('Proceed to Sign'));
    });

    await waitFor(() => {
      fireEvent.click(screen.getByText('Create Signing Request'));
    });

    await waitFor(() => {
      fireEvent.click(screen.getByText('Complete Signing'));
    });

    await waitFor(() => {
      expect(screen.getByTestId('signing-status')).toBeInTheDocument();
    });

    expect(mockOnSuccess).toHaveBeenCalledWith({
      success: true,
      document: expect.any(Object),
      signingRequest: expect.any(Object),
    });
  });

  it('handles back navigation correctly', async () => {
    render(
      <DocumentSigningWorkflow
        onSuccess={mockOnSuccess}
        onError={mockOnError}
      />
    );

    // Upload document
    fireEvent.click(screen.getByText('Upload Test Document'));

    await waitFor(() => {
      expect(screen.getByTestId('document-preview')).toBeInTheDocument();
    });

    // Go back to upload
    fireEvent.click(screen.getByText('Back to Upload'));

    await waitFor(() => {
      expect(screen.getByTestId('document-upload')).toBeInTheDocument();
    });
  });

  it('handles errors from child components', async () => {
    render(
      <DocumentSigningWorkflow
        onSuccess={mockOnSuccess}
        onError={mockOnError}
      />
    );

    // Trigger an error
    fireEvent.click(screen.getByText('Trigger Upload Error'));

    await waitFor(() => {
      expect(screen.getByTestId('error-alert')).toBeInTheDocument();
    });

    expect(mockOnError).toHaveBeenCalledWith('Upload failed');
  });

  it('loads existing document when documentId is provided', async () => {
    const mockGetDocument = require('../../../lib/api-client').default.getDocument;
    mockGetDocument.mockResolvedValue({
      success: true,
      data: {
        id: 'existing-doc-1',
        title: 'Existing Document.pdf',
        content: 'Existing content',
        status: 'draft',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        metadata: {}
      }
    });

    render(
      <DocumentSigningWorkflow
        documentId="existing-doc-1"
        onSuccess={mockOnSuccess}
        onError={mockOnError}
      />
    );

    await waitFor(() => {
      expect(mockGetDocument).toHaveBeenCalledWith('existing-doc-1');
    });

    await waitFor(() => {
      expect(screen.getByTestId('document-preview')).toBeInTheDocument();
    });
  });

  it('handles document loading errors', async () => {
    const mockGetDocument = require('../../../lib/api-client').default.getDocument;
    mockGetDocument.mockResolvedValue({
      success: false,
      error: 'Document not found'
    });

    render(
      <DocumentSigningWorkflow
        documentId="non-existent-doc"
        onSuccess={mockOnSuccess}
        onError={mockOnError}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('error-alert')).toBeInTheDocument();
    });

    expect(mockOnError).toHaveBeenCalledWith('Document not found');
  });
});
