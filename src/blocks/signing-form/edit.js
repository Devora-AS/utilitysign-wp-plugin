import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl, TextareaControl } from '@wordpress/components';
import { Fragment } from '@wordpress/element';

export default function Edit({ attributes, setAttributes }) {
    const { documentId, enableBankID, enableEmailNotifications, className } = attributes;

    // Allow filtering of block attributes
    const filteredAttributes = wp.hooks.applyFilters('utilitysign.block.attributes', {
        documentId,
        enableBankID,
        enableEmailNotifications,
        className
    });

    const blockProps = useBlockProps({
        className: `utilitysign-signing-form-editor ${filteredAttributes.className}`.trim()
    });

    return (
        <Fragment>
            <InspectorControls>
                <PanelBody title={__('Signing Form Settings', 'utilitysign')} initialOpen={true}>
                    <TextControl
                        label={__('Document ID', 'utilitysign')}
                        value={filteredAttributes.documentId}
                        onChange={(value) => setAttributes({ documentId: value })}
                        help={__('Enter the ID of the document to be signed. This is required for the signing form to work.', 'utilitysign')}
                        placeholder={__('e.g., doc-12345', 'utilitysign')}
                    />
                    
                    <ToggleControl
                        label={__('Enable BankID', 'utilitysign')}
                        checked={filteredAttributes.enableBankID}
                        onChange={(value) => setAttributes({ enableBankID: value })}
                        help={__('Toggle to enable or disable BankID authentication for document signing.', 'utilitysign')}
                    />
                    
                    <ToggleControl
                        label={__('Enable Email Notifications', 'utilitysign')}
                        checked={filteredAttributes.enableEmailNotifications}
                        onChange={(value) => setAttributes({ enableEmailNotifications: value })}
                        help={__('Toggle to enable or disable email notifications for signing events.', 'utilitysign')}
                    />
                    
                    <TextControl
                        label={__('Additional CSS Class(es)', 'utilitysign')}
                        value={filteredAttributes.className}
                        onChange={(value) => setAttributes({ className: value })}
                        help={__('Add custom CSS classes to the block wrapper. Separate multiple classes with spaces.', 'utilitysign')}
                        placeholder={__('e.g., my-custom-class another-class', 'utilitysign')}
                    />
                </PanelBody>
            </InspectorControls>
            
            <div {...blockProps}>
                <div className="utilitysign-signing-form-preview">
                    <div className="utilitysign-signing-form-header">
                        <h3>{__('UtilitySign Signing Form', 'utilitysign')}</h3>
                        <div className="utilitysign-signing-form-status">
                            {documentId ? (
                                <span className="utilitysign-status-valid">
                                    ✓ {__('Document ID configured', 'utilitysign')}
                                </span>
                            ) : (
                                <span className="utilitysign-status-error">
                                    ⚠ {__('Document ID required', 'utilitysign')}
                                </span>
                            )}
                        </div>
                    </div>
                    
                    <div className="utilitysign-signing-form-details">
                        <div className="utilitysign-detail-item">
                            <strong>{__('Document ID:', 'utilitysign')}</strong>
                            <span>{filteredAttributes.documentId || __('Not set', 'utilitysign')}</span>
                        </div>
                        
                        <div className="utilitysign-detail-item">
                            <strong>{__('BankID Enabled:', 'utilitysign')}</strong>
                            <span>{filteredAttributes.enableBankID ? __('Yes', 'utilitysign') : __('No', 'utilitysign')}</span>
                        </div>
                        
                        <div className="utilitysign-detail-item">
                            <strong>{__('Email Notifications:', 'utilitysign')}</strong>
                            <span>{filteredAttributes.enableEmailNotifications ? __('Yes', 'utilitysign') : __('No', 'utilitysign')}</span>
                        </div>
                        
                        {filteredAttributes.className && (
                            <div className="utilitysign-detail-item">
                                <strong>{__('CSS Classes:', 'utilitysign')}</strong>
                                <span>{filteredAttributes.className}</span>
                            </div>
                        )}
                    </div>
                    
                    {!filteredAttributes.documentId && (
                        <div className="utilitysign-signing-form-warning">
                            <p>{__('Please configure the Document ID in the block settings to enable the signing form.', 'utilitysign')}</p>
                        </div>
                    )}
                </div>
            </div>
        </Fragment>
    );
}