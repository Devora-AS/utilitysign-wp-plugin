# Document Signing Components

This directory contains React components for implementing document signing workflows with BankID integration, following the Devora Design System and integrating with the UtilitySign backend API.

## Components Overview

### Main Workflow Component

#### `DocumentSigningWorkflow`
The main orchestrator component that manages the complete document signing workflow.

**Features:**
- Step-by-step progress indicator
- State management for workflow steps
- Error handling and loading states
- Support for both new document uploads and existing documents
- Integration with all sub-components

**Props:**
```typescript
interface DocumentSigningWorkflowProps {
  documentId?: string;                    // Pre-existing document ID
  onSuccess?: (result: SigningResult) => void;
  onError?: (error: string) => void;
  className?: string;
  showProgress?: boolean;                 // Show step progress indicator
}
```

### Step Components

#### `DocumentUpload`
Handles PDF document upload with validation and preview.

**Features:**
- Drag & drop file upload
- PDF file validation
- File preview before upload
- Loading states and error handling
- Integration with Devora Design System

#### `DocumentPreview`
Displays document information and PDF preview.

**Features:**
- PDF iframe preview
- Document metadata display
- Status indicators (draft, pending, signed, rejected)
- Download functionality
- Responsive design

#### `SigningForm`
Collects signer information and initiates BankID authentication.

**Features:**
- Form validation for signer details
- BankID integration with Criipto
- Real-time authentication status
- Error handling and retry logic
- Support for email notifications

#### `SigningStatus`
Tracks and displays the current status of the signing process.

**Features:**
- Real-time status updates
- Auto-refresh functionality
- Status-specific UI states
- Download completed documents
- Error handling and recovery

### Supporting Components

#### `LoadingSpinner`
Reusable loading indicator with multiple sizes.

#### `ErrorAlert`
Error display component with dismiss functionality.

## API Integration

The components integrate with the UtilitySign backend API through the `api-client.ts` module:

### Authentication
- Microsoft Entra ID JWT authentication
- Automatic token refresh
- Environment-based configuration (staging/production)

### BankID Integration
- Criipto Signatures API integration
- Secure authentication flow
- Real-time status polling
- Error handling and retry logic

### API Endpoints Used
- `GET /api/v1/documents/{id}` - Fetch document details
- `POST /api/v1/signing-requests` - Create signing request
- `POST /api/v1/signing-requests/{id}/bankid/initiate` - Start BankID auth
- `GET /api/v1/bankid/status/{sessionId}` - Check auth status
- `GET /api/v1/health` - Health check

## Design System Integration

All components follow the Devora Design System:

### Colors
- Primary: `#3432A6` (bright purple)
- Primary Dark: `#242A56` (dark navy)
- Primary Light: `#968AB6` (secondary purple)
- Accent: `#FFFADE` (yellow)
- Backgrounds: `#FFFFFF`, `#FCF7FF`

### Typography
- Headings: Lato 900 weight
- Body: Open Sans 400 weight
- UI: Inter 700 weight

### Components
- Buttons with 21.5px border radius
- Cards with proper variants
- Form inputs with consistent styling
- Loading states and error displays

## Usage Examples

### Basic Usage
```tsx
import { DocumentSigningWorkflow } from './components/signing';

function MyComponent() {
  const handleSuccess = (result) => {
    console.log('Signing completed:', result);
  };

  const handleError = (error) => {
    console.error('Signing failed:', error);
  };

  return (
    <DocumentSigningWorkflow
      onSuccess={handleSuccess}
      onError={handleError}
      showProgress={true}
    />
  );
}
```

### With Existing Document
```tsx
<DocumentSigningWorkflow
  documentId="existing-doc-123"
  onSuccess={handleSuccess}
  onError={handleError}
/>
```

### Individual Components
```tsx
import { 
  DocumentUpload, 
  DocumentPreview, 
  SigningForm, 
  SigningStatus 
} from './components/signing';

// Use individual components as needed
<DocumentUpload 
  onDocumentUpload={handleUpload}
  onError={handleError}
/>
```

## Testing

The components include comprehensive test coverage:

- Unit tests for individual components
- Integration tests for workflow
- Mock API client for testing
- Error scenario testing
- User interaction testing

Run tests with:
```bash
npm test
```

## WordPress Integration

These components are designed to work within WordPress:

- Compatible with WordPress themes
- Responsive design for all devices
- Accessibility compliance (WCAG 2.1)
- Proper WordPress nonce security
- Multisite compatibility

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Dependencies

- React 18+
- TypeScript 4.9+
- Tailwind CSS 3.0+
- Lucide React (icons)
- class-variance-authority (component variants)

## Development

### Building
```bash
npm run build
```

### Development Server
```bash
npm run dev
```

### Code Formatting
```bash
npm run format:fix
```

## Security Considerations

- All API communications use HTTPS
- JWT tokens are securely stored
- Input validation on all forms
- XSS protection through React
- CSRF protection via WordPress nonces
- BankID integration follows security best practices

## Performance

- Lazy loading for large components
- Optimized bundle sizes
- Efficient state management
- Minimal re-renders
- Caching for API responses

## Accessibility

- ARIA labels and roles
- Keyboard navigation support
- Screen reader compatibility
- High contrast mode support
- Focus management
- Semantic HTML structure
