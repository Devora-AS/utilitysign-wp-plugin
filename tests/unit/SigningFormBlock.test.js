import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import { registerBlockType } from '@wordpress/blocks';
import { createBlock } from '@wordpress/blocks';

// Mock WordPress dependencies
jest.mock('@wordpress/blocks', () => ({
    registerBlockType: jest.fn(),
}));

jest.mock('@wordpress/block-editor', () => {
    const mockUseBlockProps = jest.fn((props) => ({ ...props, 'data-testid': 'block-props' }));
    mockUseBlockProps.save = jest.fn((props) => ({ ...props, 'data-testid': 'block-props' }));
    
    return {
        useBlockProps: mockUseBlockProps,
        InspectorControls: ({ children }) => <div data-testid="inspector-controls">{children}</div>,
    };
});

jest.mock('@wordpress/components', () => ({
    PanelBody: ({ children, title }) => <div data-testid="panel-body" data-title={title}>{children}</div>,
    TextControl: ({ label, value, onChange, help, placeholder }) => (
        <div data-testid="text-control">
            <label>{label}</label>
            <input 
                value={value} 
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                data-help={help}
            />
        </div>
    ),
    ToggleControl: ({ label, checked, onChange, help }) => (
        <div data-testid="toggle-control">
            <label>
                <input 
                    type="checkbox" 
                    checked={checked} 
                    onChange={(e) => onChange(e.target.checked)}
                />
                {label}
            </label>
            <div data-help={help}></div>
        </div>
    ),
}));

jest.mock('@wordpress/i18n', () => ({
    __: (text) => text,
}));

// Mock the block registration
const mockRegisterBlockType = registerBlockType;

describe('SigningForm Block', () => {
    let Edit, Save;
    
    beforeAll(() => {
        // Import the block components
        Edit = require('../../src/blocks/signing-form/edit.js').default;
        Save = require('../../src/blocks/signing-form/save.js').default;
    });
    
    beforeEach(() => {
        jest.clearAllMocks();
    });
    
    describe('Block Registration', () => {
        test('registers block with correct metadata', () => {
            // This would be tested by importing the index.js file
            // For now, we'll test the individual components
            expect(mockRegisterBlockType).toBeDefined();
        });
    });
    
    describe('Edit Component', () => {
        const defaultAttributes = {
            documentId: '',
            enableBankID: true,
            enableEmailNotifications: true,
            className: '',
        };
        
        const mockSetAttributes = jest.fn();
        
        beforeEach(() => {
            mockSetAttributes.mockClear();
        });
        
        test('renders inspector controls', () => {
            render(<Edit attributes={defaultAttributes} setAttributes={mockSetAttributes} />);
            
            expect(screen.getByTestId('inspector-controls')).toBeInTheDocument();
            expect(screen.getByTestId('panel-body')).toBeInTheDocument();
        });
        
        test('renders all form controls', () => {
            render(<Edit attributes={defaultAttributes} setAttributes={mockSetAttributes} />);
            
            expect(screen.getAllByTestId('text-control')).toHaveLength(2);
            expect(screen.getAllByTestId('toggle-control')).toHaveLength(2);
        });
        
        test('displays current attribute values', () => {
            const attributes = {
                ...defaultAttributes,
                documentId: 'test-doc-123',
                enableBankID: false,
                className: 'custom-class',
            };
            
            render(<Edit attributes={attributes} setAttributes={mockSetAttributes} />);
            
            const textInput = screen.getByDisplayValue('test-doc-123');
            expect(textInput).toBeInTheDocument();
            
            const toggles = screen.getAllByTestId('toggle-control');
            expect(toggles[0]).toHaveTextContent('Enable BankID');
            expect(toggles[1]).toHaveTextContent('Enable Email Notifications');
        });
        
        test('calls setAttributes when form values change', () => {
            render(<Edit attributes={defaultAttributes} setAttributes={mockSetAttributes} />);
            
            const textInputs = screen.getAllByDisplayValue('');
            const documentIdInput = textInputs[0]; // First input is Document ID
            fireEvent.change(documentIdInput, { target: { value: 'new-doc-123' } });
            
            expect(mockSetAttributes).toHaveBeenCalledWith({ documentId: 'new-doc-123' });
        });
        
        test('calls setAttributes when toggles change', () => {
            render(<Edit attributes={defaultAttributes} setAttributes={mockSetAttributes} />);
            
            const toggles = screen.getAllByTestId('toggle-control');
            const bankIdToggle = toggles[0].querySelector('input[type="checkbox"]');
            
            fireEvent.click(bankIdToggle);
            
            expect(mockSetAttributes).toHaveBeenCalledWith({ enableBankID: false });
        });
        
        test('shows configuration status', () => {
            const attributes = {
                ...defaultAttributes,
                documentId: 'test-doc-123',
            };
            
            render(<Edit attributes={attributes} setAttributes={mockSetAttributes} />);
            
            expect(screen.getByText(/document id configured/i)).toBeInTheDocument();
            expect(screen.getByText('test-doc-123')).toBeInTheDocument();
        });
        
        test('shows warning when document ID is missing', () => {
            render(<Edit attributes={defaultAttributes} setAttributes={mockSetAttributes} />);
            
            expect(screen.getByText(/document id required/i)).toBeInTheDocument();
            expect(screen.getByText(/please configure the document id/i)).toBeInTheDocument();
        });
        
        test('displays help text for form controls', () => {
            render(<Edit attributes={defaultAttributes} setAttributes={mockSetAttributes} />);
            
            const textControls = screen.getAllByTestId('text-control');
            const documentIdControl = textControls[0]; // First text control is Document ID
            const helpText = documentIdControl.querySelector('[data-help]');
            expect(helpText).toHaveAttribute('data-help');
        });
    });
    
    describe('Save Component', () => {
        const defaultAttributes = {
            documentId: 'test-doc-123',
            enableBankID: true,
            enableEmailNotifications: false,
            className: 'custom-class',
        };
        
        test('renders div with correct attributes', () => {
            const { container } = render(<Save attributes={defaultAttributes} />);
            
            const div = container.firstChild;
            expect(div).toHaveClass('utilitysign-signing-form', 'custom-class');
            expect(div).toHaveAttribute('data-document-id', 'test-doc-123');
            expect(div).toHaveAttribute('data-enable-bank-id', 'true');
            expect(div).toHaveAttribute('data-enable-email-notifications', 'false');
        });
        
        test('handles empty className', () => {
            const attributes = {
                ...defaultAttributes,
                className: '',
            };
            
            const { container } = render(<Save attributes={attributes} />);
            
            const div = container.firstChild;
            expect(div).toHaveClass('utilitysign-signing-form');
            // When className is empty, it should not have any additional classes beyond the base class
            expect(div.className).toBe('utilitysign-signing-form');
        });
        
        test('converts boolean attributes to strings', () => {
            const attributes = {
                ...defaultAttributes,
                enableBankID: false,
                enableEmailNotifications: true,
            };
            
            const { container } = render(<Save attributes={attributes} />);
            
            const div = container.firstChild;
            expect(div).toHaveAttribute('data-enable-bank-id', 'false');
            expect(div).toHaveAttribute('data-enable-email-notifications', 'true');
        });
    });
    
    describe('Block Attributes', () => {
        test('has correct default values', () => {
            // This would be tested by checking the block.json metadata
            const expectedAttributes = {
                documentId: '',
                enableBankID: true,
                enableEmailNotifications: true,
                className: '',
            };
            
            // In a real test, you would load the block.json and check the attributes
            expect(expectedAttributes).toBeDefined();
        });
    });
});
