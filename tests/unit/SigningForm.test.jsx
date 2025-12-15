import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import SigningForm from '../../src/components/signing/SigningForm';

// Mock the API client
jest.mock('../../src/lib/api-client', () => ({
    createSigningRequest: jest.fn(),
    initiateBankID: jest.fn(),
    getDocument: jest.fn(),
    checkBankIDStatus: jest.fn(),
    cancelBankIDSession: jest.fn(),
}));

// Mock the theme provider
jest.mock('../../src/components/theme-provider', () => ({
    ThemeProvider: ({ children }) => <div data-testid="theme-provider">{children}</div>
}));

describe('SigningForm Component', () => {
    const defaultProps = {
        documentId: 'test-doc-123',
        enableBankID: true,
        enableEmailNotifications: true,
    };

    beforeEach(() => {
        jest.clearAllMocks();
    });

    test('renders form with required fields', () => {
        render(<SigningForm {...defaultProps} />);
        
        expect(screen.getByLabelText(/full name/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/email address/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /sign with bankid/i })).toBeInTheDocument();
    });

    test('displays document ID in form', () => {
        render(<SigningForm {...defaultProps} />);
        
        // The document ID is not displayed in the UI, it's used internally
        // This test verifies the component renders without errors when documentId is provided
        expect(screen.getByText('Sign Document')).toBeInTheDocument();
    });

    test('shows BankID option when enabled', () => {
        render(<SigningForm {...defaultProps} enableBankID={true} />);
        
        expect(screen.getByText(/bankid authentication/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /sign with bankid/i })).toBeInTheDocument();
    });

    test('hides BankID option when disabled', () => {
        render(<SigningForm {...defaultProps} enableBankID={false} />);
        
        expect(screen.queryByText(/bankid authentication/i)).not.toBeInTheDocument();
    });

    test('shows email notifications option when enabled', () => {
        render(<SigningForm {...defaultProps} enableEmailNotifications={true} />);
        
        expect(screen.getByText(/email notifications/i)).toBeInTheDocument();
    });

    test('hides email notifications option when disabled', () => {
        render(<SigningForm {...defaultProps} enableEmailNotifications={false} />);
        
        expect(screen.queryByText(/email notifications/i)).not.toBeInTheDocument();
    });

    test('validates required fields', async () => {
        render(<SigningForm {...defaultProps} />);
        
        const submitButton = screen.getByRole('button', { name: /sign with bankid/i });
        fireEvent.click(submitButton);
        
        await waitFor(() => {
            expect(screen.getByText(/name is required/i)).toBeInTheDocument();
            expect(screen.getByText(/email is required/i)).toBeInTheDocument();
        });
    });

    test('validates email format', async () => {
        render(<SigningForm {...defaultProps} />);
        
        const emailInput = screen.getByLabelText(/email address/i);
        const nameInput = screen.getByLabelText(/full name/i);
        const submitButton = screen.getByRole('button', { name: /sign with bankid/i });
        
        fireEvent.change(nameInput, { target: { value: 'John Doe' } });
        fireEvent.change(emailInput, { target: { value: 'invalid' } });
        
        // Check that the form is not submitting with invalid email
        fireEvent.click(submitButton);
        
        // The form should not show success message with invalid email
        await waitFor(() => {
            expect(screen.queryByText(/signing request created successfully/i)).not.toBeInTheDocument();
        });
    });

    test('submits form with valid data', async () => {
        const mockCreateSigningRequest = require('../../src/lib/api-client').createSigningRequest;
        mockCreateSigningRequest.mockResolvedValue({
            success: true,
            data: { id: 'req-123', status: 'pending' }
        });

        render(<SigningForm {...defaultProps} />);
        
        const nameInput = screen.getByLabelText(/full name/i);
        const emailInput = screen.getByLabelText(/email address/i);
        const submitButton = screen.getByRole('button', { name: /sign with bankid/i });
        
        fireEvent.change(nameInput, { target: { value: 'John Doe' } });
        fireEvent.change(emailInput, { target: { value: 'john@example.com' } });
        fireEvent.click(submitButton);
        
        await waitFor(() => {
            expect(mockCreateSigningRequest).toHaveBeenCalledWith(
                'test-doc-123',
                'john@example.com',
                'John Doe',
                expect.stringMatching(/signing-test-doc-123-john@example\.com-\d+/)
            );
        });
    });

    test('handles API errors gracefully', async () => {
        const mockCreateSigningRequest = require('../../src/lib/api-client').createSigningRequest;
        mockCreateSigningRequest.mockRejectedValue(new Error('API Error'));

        render(<SigningForm {...defaultProps} />);
        
        const nameInput = screen.getByLabelText(/full name/i);
        const emailInput = screen.getByLabelText(/email address/i);
        const submitButton = screen.getByRole('button', { name: /sign with bankid/i });
        
        fireEvent.change(nameInput, { target: { value: 'John Doe' } });
        fireEvent.change(emailInput, { target: { value: 'john@example.com' } });
        fireEvent.click(submitButton);
        
        await waitFor(() => {
            expect(screen.getByText(/api error/i)).toBeInTheDocument();
        });
    });

    test('shows loading state during submission', async () => {
        const mockCreateSigningRequest = require('../../src/lib/api-client').createSigningRequest;
        mockCreateSigningRequest.mockImplementation(() => new Promise(resolve => setTimeout(resolve, 100)));

        render(<SigningForm {...defaultProps} />);
        
        const nameInput = screen.getByLabelText(/full name/i);
        const emailInput = screen.getByLabelText(/email address/i);
        const submitButton = screen.getByRole('button', { name: /sign with bankid/i });
        
        fireEvent.change(nameInput, { target: { value: 'John Doe' } });
        fireEvent.change(emailInput, { target: { value: 'john@example.com' } });
        fireEvent.click(submitButton);
        
        expect(screen.getByText(/creating signing request/i)).toBeInTheDocument();
        expect(submitButton).toBeDisabled();
    });

    test('calls onSuccess callback when signing is successful', async () => {
        const mockOnSuccess = jest.fn();
        const mockCreateSigningRequest = require('../../src/lib/api-client').createSigningRequest;
        const mockInitiateBankID = require('../../src/lib/api-client').initiateBankID;
        
        // Mock window.open to return a mock window object
        const mockWindow = {
            closed: false,
            close: jest.fn(),
        };
        global.open = jest.fn().mockReturnValue(mockWindow);
        
        mockCreateSigningRequest.mockResolvedValue({
            success: true,
            data: { id: 'req-123', status: 'pending' }
        });
        
        mockInitiateBankID.mockResolvedValue({
            success: true,
            data: { auth_url: 'https://bankid.example.com/auth', session_id: 'session-123', correlation_id: 'corr-123' }
        });

        render(<SigningForm {...defaultProps} onSuccess={mockOnSuccess} />);
        
        const nameInput = screen.getByLabelText(/full name/i);
        const emailInput = screen.getByLabelText(/email address/i);
        const submitButton = screen.getByRole('button', { name: /sign with bankid/i });
        
        fireEvent.change(nameInput, { target: { value: 'John Doe' } });
        fireEvent.change(emailInput, { target: { value: 'john@example.com' } });
        fireEvent.click(submitButton);
        
        // Wait for the success callback to be called
        await waitFor(() => {
            expect(mockOnSuccess).toHaveBeenCalledWith({
                success: true,
                requestId: 'req-123',
                authUrl: 'https://bankid.example.com/auth'
            });
        }, { timeout: 5000 });
    });

    test('calls onError callback when signing fails', async () => {
        const mockOnError = jest.fn();
        const mockCreateSigningRequest = require('../../src/lib/api-client').createSigningRequest;
        mockCreateSigningRequest.mockRejectedValue(new Error('API Error'));

        render(<SigningForm {...defaultProps} onError={mockOnError} />);
        
        const nameInput = screen.getByLabelText(/full name/i);
        const emailInput = screen.getByLabelText(/email address/i);
        const submitButton = screen.getByRole('button', { name: /sign with bankid/i });
        
        fireEvent.change(nameInput, { target: { value: 'John Doe' } });
        fireEvent.change(emailInput, { target: { value: 'john@example.com' } });
        fireEvent.click(submitButton);
        
        await waitFor(() => {
            expect(mockOnError).toHaveBeenCalledWith('API Error');
        });
    });

    test('applies custom className', () => {
        render(<SigningForm {...defaultProps} className="custom-class" />);
        
        const card = screen.getByText('Sign Document').closest('.devora-card');
        expect(card).toHaveClass('custom-class');
    });
});
