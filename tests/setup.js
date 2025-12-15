// Test setup file for Jest
import '@testing-library/jest-dom';

// Mock WordPress globals
global.wp = {
  i18n: {
    __: (text) => text,
    _x: (text) => text,
    _n: (text) => text,
    _nx: (text) => text,
  },
  element: {
    createElement: (tag, props, ...children) => {
      const element = document.createElement(tag);
      if (props) {
        Object.assign(element, props);
      }
      children.forEach(child => {
        if (typeof child === 'string') {
          element.appendChild(document.createTextNode(child));
        } else if (child) {
          element.appendChild(child);
        }
      });
      return element;
    },
    Fragment: ({ children }) => {
      const fragment = document.createDocumentFragment();
      if (children) {
        if (Array.isArray(children)) {
          children.forEach(child => fragment.appendChild(child));
        } else {
          fragment.appendChild(children);
        }
      }
      return fragment;
    },
  },
  blocks: {
    registerBlockType: jest.fn(),
  },
      blockEditor: {
        InspectorControls: ({ children }) => children,
        PanelBody: ({ children }) => children,
        useBlockProps: {
          save: jest.fn((props) => ({ ...props, 'data-testid': 'block-props' })),
        },
      },
  components: {
    TextControl: ({ label, value, onChange }) => {
      const input = document.createElement('input');
      input.type = 'text';
      input.value = value || '';
      input.placeholder = label;
      if (onChange) {
        input.addEventListener('change', (e) => onChange(e.target.value));
      }
      return input;
    },
    ToggleControl: ({ label, checked, onChange }) => {
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = checked || false;
      if (onChange) {
        input.addEventListener('change', (e) => onChange(e.target.checked));
      }
      return input;
    },
  },
  hooks: {
    applyFilters: (hook, value) => value,
    addFilter: () => {},
    removeFilter: () => {},
    addAction: () => {},
    removeAction: () => {},
  },
};

// Mock WordPress REST API
global.wpApiSettings = {
  root: 'http://localhost/wp-json/',
  nonce: 'test-nonce',
};

// Mock utilitySign global
global.utilitySign = {
  apiUrl: 'http://localhost/wp-json/utilitysign/v1',
  nonce: 'test-nonce',
  isAdmin: true,
};

// Mock fetch for API calls
global.fetch = jest.fn();

// Mock window.open for BankID popup testing
global.open = jest.fn();

// Mock console methods to reduce noise in tests
global.console = {
  ...console,
  log: jest.fn(),
  debug: jest.fn(),
  info: jest.fn(),
  warn: jest.fn(),
  error: jest.fn(),
};