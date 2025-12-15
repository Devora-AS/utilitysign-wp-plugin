/**
 * Supplier Selection Block
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

(function() {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { createElement } = wp.element;
    const { __ } = wp.i18n;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, ToggleControl, TextControl } = wp.components;
    const { useSelect } = wp.data;

    registerBlockType('utilitysign/supplier-selection', {
        title: __('Supplier Selection', 'utilitysign'),
        description: __('Display a list of suppliers for selection', 'utilitysign'),
        icon: 'groups',
        category: 'utilitysign',
        keywords: [
            __('supplier', 'utilitysign'),
            __('selection', 'utilitysign'),
            __('vendor', 'utilitysign')
        ],
        attributes: {
            supplierId: {
                type: 'number',
                default: 0
            },
            showSupplierInfo: {
                type: 'boolean',
                default: true
            },
            showSupplierLogo: {
                type: 'boolean',
                default: true
            },
            showSupplierDescription: {
                type: 'boolean',
                default: true
            },
            showContactInfo: {
                type: 'boolean',
                default: true
            },
            displayStyle: {
                type: 'string',
                'default': 'dropdown'
            },
            buttonText: {
                type: 'string',
                'default': __('Select Supplier', 'utilitysign')
            },
            placeholder: {
                type: 'string',
                'default': __('Choose a supplier', 'utilitysign')
            }
        },
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { 
                supplierId, 
                showSupplierInfo, 
                showSupplierLogo, 
                showSupplierDescription, 
                showContactInfo, 
                displayStyle, 
                buttonText, 
                placeholder 
            } = attributes;

            // Get suppliers for select control
            const suppliers = useSelect((select) => {
                return select('core').getEntityRecords('postType', 'utilitysign_supplier', {
                    per_page: 100,
                    status: 'publish'
                });
            }, []);

            const supplierOptions = [
                { value: 0, label: __('Select a supplier', 'utilitysign') }
            ].concat((suppliers || []).map(supplier => ({
                value: supplier.id,
                label: supplier.title.rendered
            })));

            return createElement('div', { className: 'utilitysign-supplier-selection-block-editor' },
                createElement(InspectorControls, null,
                    createElement(PanelBody, { title: __('Supplier Settings', 'utilitysign'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Pre-selected Supplier', 'utilitysign'),
                            value: supplierId,
                            options: supplierOptions,
                            onChange: (value) => setAttributes({ supplierId: parseInt(value) })
                        }),
                        createElement(SelectControl, {
                            label: __('Display Style', 'utilitysign'),
                            value: displayStyle,
                            options: [
                                { value: 'dropdown', label: __('Dropdown', 'utilitysign') },
                                { value: 'cards', label: __('Cards', 'utilitysign') },
                                { value: 'list', label: __('List', 'utilitysign') }
                            ],
                            onChange: (value) => setAttributes({ displayStyle: value })
                        })
                    ),
                    createElement(PanelBody, { title: __('Display Options', 'utilitysign'), initialOpen: true },
                        createElement(ToggleControl, {
                            label: __('Show Supplier Info', 'utilitysign'),
                            checked: showSupplierInfo,
                            onChange: (value) => setAttributes({ showSupplierInfo: value })
                        }),
                        createElement(ToggleControl, {
                            label: __('Show Supplier Logo', 'utilitysign'),
                            checked: showSupplierLogo,
                            onChange: (value) => setAttributes({ showSupplierLogo: value })
                        }),
                        createElement(ToggleControl, {
                            label: __('Show Description', 'utilitysign'),
                            checked: showSupplierDescription,
                            onChange: (value) => setAttributes({ showSupplierDescription: value })
                        }),
                        createElement(ToggleControl, {
                            label: __('Show Contact Info', 'utilitysign'),
                            checked: showContactInfo,
                            onChange: (value) => setAttributes({ showContactInfo: value })
                        })
                    ),
                    createElement(PanelBody, { title: __('Text Settings', 'utilitysign'), initialOpen: false },
                        createElement(TextControl, {
                            label: __('Button Text', 'utilitysign'),
                            value: buttonText,
                            onChange: (value) => setAttributes({ buttonText: value })
                        }),
                        createElement(TextControl, {
                            label: __('Placeholder Text', 'utilitysign'),
                            value: placeholder,
                            onChange: (value) => setAttributes({ placeholder: value })
                        })
                    )
                ),
                createElement('div', { className: 'supplier-selection-preview' },
                    createElement('h3', null, __('Supplier Selection Preview', 'utilitysign')),
                    createElement('div', { className: `supplier-preview ${displayStyle}` },
                        displayStyle === 'dropdown' && createElement('div', { className: 'supplier-dropdown' },
                            createElement('label', null, __('Select Supplier', 'utilitysign')),
                            createElement('select', null,
                                createElement('option', null, placeholder)
                            )
                        ),
                        displayStyle === 'cards' && createElement('div', { className: 'supplier-cards' },
                            createElement('h4', null, __('Choose a Supplier', 'utilitysign')),
                            createElement('div', { className: 'supplier-grid' },
                                createElement('div', { className: 'supplier-card' },
                                    showSupplierLogo && createElement('div', { className: 'supplier-logo' },
                                        createElement('div', { className: 'logo-placeholder' }, __('Logo', 'utilitysign'))
                                    ),
                                    createElement('div', { className: 'supplier-info' },
                                        createElement('h4', null, __('Supplier Name', 'utilitysign')),
                                        showSupplierDescription && createElement('p', null, __('Supplier description...', 'utilitysign')),
                                        showContactInfo && createElement('div', { className: 'supplier-contact' },
                                            createElement('p', null, __('Email: supplier@example.com', 'utilitysign')),
                                            createElement('p', null, __('Phone: +47 123 45 678', 'utilitysign'))
                                        )
                                    ),
                                    createElement('button', { className: 'select-supplier-btn' }, buttonText)
                                )
                            )
                        ),
                        displayStyle === 'list' && createElement('div', { className: 'supplier-list' },
                            createElement('h4', null, __('Available Suppliers', 'utilitysign')),
                            createElement('ul', { className: 'supplier-list-items' },
                                createElement('li', { className: 'supplier-list-item' },
                                    createElement('div', { className: 'supplier-list-content' },
                                        showSupplierLogo && createElement('div', { className: 'supplier-list-logo' },
                                            createElement('div', { className: 'logo-placeholder' }, __('Logo', 'utilitysign'))
                                        ),
                                        createElement('div', { className: 'supplier-list-info' },
                                            createElement('h4', null, __('Supplier Name', 'utilitysign')),
                                            showSupplierDescription && createElement('p', null, __('Supplier description...', 'utilitysign')),
                                            showContactInfo && createElement('div', { className: 'supplier-list-contact' },
                                                createElement('span', null, __('supplier@example.com', 'utilitysign')),
                                                createElement('span', null, __('+47 123 45 678', 'utilitysign'))
                                            )
                                        ),
                                        createElement('button', { className: 'select-supplier-btn' }, buttonText)
                                    )
                                )
                            )
                        )
                    )
                )
            );
        },
        save: function() {
            return null; // This block is rendered server-side
        }
    });
})();