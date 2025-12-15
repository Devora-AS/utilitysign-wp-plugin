import { useBlockProps } from '@wordpress/block-editor';

export default function save({ attributes }) {
    const { documentId, enableBankID, enableEmailNotifications, className } = attributes;
    
    const blockProps = useBlockProps.save({
        className: `utilitysign-signing-form ${className}`.trim(),
        'data-document-id': documentId,
        'data-enable-bank-id': enableBankID ? 'true' : 'false',
        'data-enable-email-notifications': enableEmailNotifications ? 'true' : 'false'
    });

    return (
        <div {...blockProps}>
            {/* The React component will be mounted here by the view script */}
        </div>
    );
}