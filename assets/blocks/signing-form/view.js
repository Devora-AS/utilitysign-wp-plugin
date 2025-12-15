/**
 * View script for the UtilitySign Signing Form block
 * 
 * This script runs on the frontend and mounts the React SigningForm component
 * into the block's container element.
 */

document.addEventListener('DOMContentLoaded', () => {
    const blocks = document.querySelectorAll('.wp-block-utilitysign-signing-form');
    
    blocks.forEach(block => {
        const documentId = block.dataset.documentId;
        const enableBankID = block.dataset.enableBankId === 'true';
        const enableEmailNotifications = block.dataset.enableEmailNotifications === 'true';
        const className = block.dataset.className || '';

        // Validate required attributes
        if (!documentId) {
            block.innerHTML = `
                <div class="utilitysign-error" style="color: #dc3545; padding: 15px; border: 1px solid #dc3545; border-radius: 4px; background-color: #f8d7da; text-align: center;">
                    <strong>Error:</strong> Document ID is required for the UtilitySign signing form.
                </div>
            `;
            return;
        }

        // Check if the mounting function is available
        if (typeof window.utilitySignMountSigningForm === 'function') {
            try {
                window.utilitySignMountSigningForm(block, {
                    documentId,
                    enableBankID,
                    enableEmailNotifications,
                    className
                });
            } catch (error) {
                console.error('UtilitySign: Error mounting signing form:', error);
                block.innerHTML = `
                    <div class="utilitysign-error" style="color: #dc3545; padding: 15px; border: 1px solid #dc3545; border-radius: 4px; background-color: #f8d7da; text-align: center;">
                        <strong>Error:</strong> Failed to load the signing form. Please refresh the page and try again.
                    </div>
                `;
            }
        } else {
            console.error('UtilitySign: mountSigningForm function not found. Ensure the frontend React app is loaded.');
            block.innerHTML = `
                <div class="utilitysign-error" style="color: #dc3545; padding: 15px; border: 1px solid #dc3545; border-radius: 4px; background-color: #f8d7da; text-align: center;">
                    <strong>Error:</strong> Signing form component not available. Please ensure the UtilitySign plugin is properly configured.
                </div>
            `;
        }
    });
});
