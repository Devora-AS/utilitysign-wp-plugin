import React, { useState } from 'react';
import { ProductSelection } from '../product/ProductSelection';
import { OrderForm } from '../forms/OrderForm';
import { SigningForm } from './SigningForm';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/Card';
import { Button } from '../ui/Button';
import { LoadingSpinner } from '../ui/LoadingSpinner';

interface Product {
  id: string;
  title: string;
  description: string;
  base_price: number;
  currency: string;
  billing_cycle: string;
  category: string;
  variations?: any[];
  terms_content?: string;
  require_acceptance?: boolean;
}

interface OrderData {
  id: string;
  order_id?: string; // Backward compatibility
  azure_id?: string;
  product_id: string;
  product_title: string;
  customer_name: string;
  customer_email: string;
  total_price: number;
  currency: string;
  status: string;
  signing_url?: string;
}

interface SigningProcessProps {
  supplierId?: string;
  category?: string;
  productId?: string;
  onComplete?: (result: any) => void;
  className?: string;
}

type ProcessStep = 'product-selection' | 'order-form' | 'signing';

export const SigningProcess: React.FC<SigningProcessProps> = ({
  supplierId,
  category,
  productId,
  onComplete,
  className = '',
}) => {
  const [currentStep, setCurrentStep] = useState<ProcessStep>(
    productId ? 'order-form' : 'product-selection'
  );
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [orderData, setOrderData] = useState<OrderData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(false);

  // Handle product selection
  const handleProductSelect = async (product: Product) => {
    try {
      setError(null);

      // Fetch full product data including variations and terms
      const response = await fetch(`/wp-json/utilitysign/v1/products/${product.id}`);
      
      if (!response.ok) {
        throw new Error('Failed to fetch product details');
      }

      const data = await response.json();
      const fullProduct = data.data;

      setSelectedProduct(fullProduct);
      setCurrentStep('order-form');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load product');
    }
  };

  // Handle order form submission
  const handleOrderSubmit = async (formData: Record<string, any>) => {
    try {
      setError(null);
      setIsLoading(true);

      // Submit order to backend
      const response = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'utilitysign_submit_order',
          nonce: (window as any).utilitySign?.nonce || '',
          ...formData,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to submit order');
      }

      const result = await response.json();

      if (!result.success) {
        // Handle validation errors from backend
        if (result.data?.errors) {
          const errorMessages = Object.values(result.data.errors).join(', ');
          throw new Error(errorMessages);
        }
        throw new Error(result.data?.message || 'Failed to create order');
      }

      // Store order data
      const orderData = result.data.order_data;
      setOrderData(orderData);

      // If signing URL is provided, redirect to Criipto signing
      if (result.data.signing_url) {
        // Show confirmation before redirecting
        const shouldRedirect = confirm(
          'Order created successfully! You will now be redirected to BankID for signing. Continue?'
        );
        
        if (shouldRedirect) {
          window.location.href = result.data.signing_url;
        } else {
          setCurrentStep('signing');
        }
      } else {
        // No signing URL, proceed to signing step (manual flow)
        setCurrentStep('signing');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to submit order');
    } finally {
      setIsLoading(false);
    }
  };

  // Handle signing completion
  const handleSigningComplete = (result: any) => {
    if (onComplete) {
      onComplete({
        ...result,
        order: orderData,
        product: selectedProduct,
      });
    }
  };

  // Handle back navigation
  const handleBack = () => {
    if (currentStep === 'order-form') {
      setCurrentStep('product-selection');
      setSelectedProduct(null);
    } else if (currentStep === 'signing') {
      setCurrentStep('order-form');
      setOrderData(null);
    }
  };

  // Render progress indicator
  const renderProgress = () => {
    const steps = [
      { id: 'product-selection', label: 'Select Product', completed: currentStep !== 'product-selection' },
      { id: 'order-form', label: 'Order Details', completed: currentStep === 'signing' },
      { id: 'signing', label: 'Sign Document', completed: false },
    ];

    return (
      <div className="mb-8">
        <div className="flex items-center justify-between">
          {steps.map((step, index) => (
            <React.Fragment key={step.id}>
              <div className="flex flex-col items-center flex-1">
                <div
                  className={`w-10 h-10 rounded-full flex items-center justify-center font-bold ${
                    step.completed
                      ? 'bg-devora-primary text-white'
                      : currentStep === step.id
                      ? 'bg-devora-accent text-devora-primary'
                      : 'bg-gray-200 text-gray-500'
                  }`}
                >
                  {step.completed ? '✓' : index + 1}
                </div>
                <span
                  className={`mt-2 text-sm font-ui font-bold ${
                    currentStep === step.id ? 'text-devora-primary' : 'text-gray-500'
                  }`}
                >
                  {step.label}
                </span>
              </div>
              {index < steps.length - 1 && (
                <div
                  className={`flex-1 h-1 mx-2 ${
                    step.completed ? 'bg-devora-primary' : 'bg-gray-200'
                  }`}
                />
              )}
            </React.Fragment>
          ))}
        </div>
      </div>
    );
  };

  return (
    <div className={className}>
      {isLoading && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white p-6 rounded-devora-button shadow-devora-medium">
            <LoadingSpinner />
            <p className="mt-4 text-devora-primary font-ui font-bold">Processing order...</p>
          </div>
        </div>
      )}

      {renderProgress()}

      {error && (
        <Card variant="white" className="mb-6">
          <CardContent>
            <div className="bg-red-50 border border-red-200 rounded-devora-button p-4">
              <p className="text-red-600">{error}</p>
            </div>
          </CardContent>
        </Card>
      )}

      {currentStep === 'product-selection' && (
        <ProductSelection
          onProductSelect={handleProductSelect}
          supplierId={supplierId}
          category={category}
        />
      )}

      {currentStep === 'order-form' && selectedProduct && (
        <OrderForm
          product={selectedProduct}
          onSubmit={handleOrderSubmit}
          onCancel={handleBack}
        />
      )}

      {currentStep === 'signing' && orderData && (
        <div className="space-y-6">
          {/* Order Summary */}
          <Card variant="light">
            <CardHeader>
              <CardTitle className="text-lg text-devora-primary">
                Order Summary
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-devora-text-primary">Product:</span>
                  <span className="font-bold text-devora-primary">
                    {orderData.product_title}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-devora-text-primary">Customer:</span>
                  <span className="font-bold text-devora-primary">
                    {orderData.customer_name}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-devora-text-primary">Total:</span>
                  <span className="font-heading text-xl font-black text-devora-primary">
                    {orderData.total_price.toFixed(2)} {orderData.currency}
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Signing Form */}
          <SigningForm
            documentId={orderData.order_id}
            onSuccess={handleSigningComplete}
            onError={(error) => setError(error)}
          />

          {/* Back Button */}
          <div className="flex justify-center">
            <Button variant="ghost" onClick={handleBack}>
              ← Back to Order Details
            </Button>
          </div>
        </div>
      )}
    </div>
  );
};

