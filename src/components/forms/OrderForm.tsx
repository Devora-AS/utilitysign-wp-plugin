import React, { useState, useEffect } from 'react';
import { Button } from '../ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/Card';
import { Input } from '../ui/input';
import { Label } from '../ui/label';

interface ProductVariation {
  name: string;
  description: string;
  price_modifier: number;
  sku: string;
  is_default: boolean;
}

interface Product {
  id: string;
  title: string;
  description: string;
  base_price: number;
  currency: string;
  billing_cycle: string;
  category: string;
  variations?: ProductVariation[];
  terms_content?: string;
  require_acceptance?: boolean;
}

interface OrderFormField {
  name: string;
  label: string;
  type: 'text' | 'email' | 'tel' | 'number' | 'select' | 'checkbox' | 'textarea';
  required: boolean;
  options?: string[];
  validation?: {
    pattern?: string;
    min?: number;
    max?: number;
    minLength?: number;
    maxLength?: number;
  };
}

interface OrderFormProps {
  product: Product;
  onSubmit: (data: Record<string, any>) => void;
  onCancel?: () => void;
  customFields?: OrderFormField[];
  className?: string;
}

export const OrderForm: React.FC<OrderFormProps> = ({
  product,
  onSubmit,
  onCancel,
  customFields = [],
  className = '',
}) => {
  const [formData, setFormData] = useState<Record<string, any>>({
    product_id: product.id,
    product_title: product.title,
    selected_variation: '',
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    terms_accepted: false,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [selectedVariation, setSelectedVariation] = useState<ProductVariation | null>(null);
  const [totalPrice, setTotalPrice] = useState<number>(product.base_price);

  // Set default variation on mount
  useEffect(() => {
    if (product.variations && product.variations.length > 0) {
      const defaultVariation = product.variations.find(v => v.is_default) || product.variations[0];
      if (defaultVariation) {
        setSelectedVariation(defaultVariation);
        setFormData(prev => ({ ...prev, selected_variation: defaultVariation.name }));
        setTotalPrice(product.base_price + defaultVariation.price_modifier);
      }
    }
  }, [product]);

  const handleInputChange = (name: string, value: any) => {
    setFormData(prev => ({ ...prev, [name]: value }));
    // Clear error for this field
    if (errors[name]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[name];
        return newErrors;
      });
    }
  };

  const handleVariationChange = (variationName: string) => {
    const variation = product.variations?.find(v => v.name === variationName);
    if (variation) {
      setSelectedVariation(variation);
      setFormData(prev => ({ ...prev, selected_variation: variationName }));
      setTotalPrice(product.base_price + variation.price_modifier);
    }
  };

  const validateField = (field: OrderFormField, value: any): string | null => {
    if (field.required && !value) {
      return `${field.label} is required`;
    }

    if (field.validation) {
      const { pattern, min, max, minLength, maxLength } = field.validation;

      if (pattern && typeof value === 'string' && !new RegExp(pattern).test(value)) {
        return `${field.label} format is invalid`;
      }

      if (min !== undefined && typeof value === 'number' && value < min) {
        return `${field.label} must be at least ${min}`;
      }

      if (max !== undefined && typeof value === 'number' && value > max) {
        return `${field.label} must be at most ${max}`;
      }

      if (minLength !== undefined && typeof value === 'string' && value.length < minLength) {
        return `${field.label} must be at least ${minLength} characters`;
      }

      if (maxLength !== undefined && typeof value === 'string' && value.length > maxLength) {
        return `${field.label} must be at most ${maxLength} characters`;
      }
    }

    return null;
  };

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};

    // Validate required fields
    if (!formData.customer_name) {
      newErrors.customer_name = 'Name is required';
    }

    if (!formData.customer_email) {
      newErrors.customer_email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.customer_email)) {
      newErrors.customer_email = 'Invalid email format';
    }

    if (!formData.customer_phone) {
      newErrors.customer_phone = 'Phone is required';
    }

    if (product.require_acceptance && !formData.terms_accepted) {
      newErrors.terms_accepted = 'You must accept the terms and conditions';
    }

    // Validate custom fields
    customFields.forEach(field => {
      const error = validateField(field, formData[field.name]);
      if (error) {
        newErrors[field.name] = error;
      }
    });

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (validateForm()) {
      onSubmit({
        ...formData,
        total_price: totalPrice,
        currency: product.currency,
        billing_cycle: product.billing_cycle,
      });
    }
  };

  const renderField = (field: OrderFormField) => {
    const value = formData[field.name] || '';
    const error = errors[field.name];

    switch (field.type) {
      case 'select':
        return (
          <div key={field.name} className="space-y-2">
            <Label htmlFor={field.name}>
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </Label>
            <select
              id={field.name}
              value={value}
              onChange={(e) => handleInputChange(field.name, e.target.value)}
              className="devora-input"
              required={field.required}
            >
              <option value="">Select {field.label}</option>
              {field.options?.map(option => (
                <option key={option} value={option}>{option}</option>
              ))}
            </select>
            {error && <p className="text-sm text-red-500">{error}</p>}
          </div>
        );

      case 'checkbox':
        return (
          <div key={field.name} className="flex items-center space-x-2">
            <input
              type="checkbox"
              id={field.name}
              checked={value === true}
              onChange={(e) => handleInputChange(field.name, e.target.checked)}
              className="h-4 w-4"
              required={field.required}
            />
            <Label htmlFor={field.name}>
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </Label>
            {error && <p className="text-sm text-red-500">{error}</p>}
          </div>
        );

      case 'textarea':
        return (
          <div key={field.name} className="space-y-2">
            <Label htmlFor={field.name}>
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </Label>
            <textarea
              id={field.name}
              value={value}
              onChange={(e) => handleInputChange(field.name, e.target.value)}
              className="devora-input min-h-[100px]"
              required={field.required}
              minLength={field.validation?.minLength}
              maxLength={field.validation?.maxLength}
            />
            {error && <p className="text-sm text-red-500">{error}</p>}
          </div>
        );

      default:
        return (
          <div key={field.name} className="space-y-2">
            <Label htmlFor={field.name}>
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </Label>
            <Input
              type={field.type}
              id={field.name}
              value={value}
              onChange={(e) => handleInputChange(field.name, e.target.value)}
              required={field.required}
              min={field.validation?.min}
              max={field.validation?.max}
              minLength={field.validation?.minLength}
              maxLength={field.validation?.maxLength}
              pattern={field.validation?.pattern}
            />
            {error && <p className="text-sm text-red-500">{error}</p>}
          </div>
        );
    }
  };

  return (
    <Card variant="white" className={className}>
      <CardHeader>
        <CardTitle className="text-2xl text-devora-primary">
          Order: {product.title}
        </CardTitle>
        <p className="text-devora-text-primary mt-2">{product.description}</p>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Product Variations */}
          {product.variations && product.variations.length > 0 && (
            <div className="space-y-2">
              <Label htmlFor="variation">
                Select Option
                <span className="text-red-500 ml-1">*</span>
              </Label>
              <select
                id="variation"
                value={formData.selected_variation}
                onChange={(e) => handleVariationChange(e.target.value)}
                className="devora-input"
                required
              >
                {product.variations.map(variation => (
                  <option key={variation.name} value={variation.name}>
                    {variation.name}
                    {variation.price_modifier !== 0 && 
                      ` (${variation.price_modifier > 0 ? '+' : ''}${variation.price_modifier} ${product.currency})`
                    }
                  </option>
                ))}
              </select>
              {selectedVariation && selectedVariation.description && (
                <p className="text-sm text-devora-text-primary">
                  {selectedVariation.description}
                </p>
              )}
            </div>
          )}

          {/* Customer Information */}
          <div className="space-y-4">
            <h3 className="font-heading text-lg font-black text-devora-primary">
              Your Information
            </h3>

            <div className="space-y-2">
              <Label htmlFor="customer_name">
                Full Name
                <span className="text-red-500 ml-1">*</span>
              </Label>
              <Input
                type="text"
                id="customer_name"
                value={formData.customer_name}
                onChange={(e) => handleInputChange('customer_name', e.target.value)}
                required
              />
              {errors.customer_name && (
                <p className="text-sm text-red-500">{errors.customer_name}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="customer_email">
                Email Address
                <span className="text-red-500 ml-1">*</span>
              </Label>
              <Input
                type="email"
                id="customer_email"
                value={formData.customer_email}
                onChange={(e) => handleInputChange('customer_email', e.target.value)}
                required
              />
              {errors.customer_email && (
                <p className="text-sm text-red-500">{errors.customer_email}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="customer_phone">
                Phone Number
                <span className="text-red-500 ml-1">*</span>
              </Label>
              <Input
                type="tel"
                id="customer_phone"
                value={formData.customer_phone}
                onChange={(e) => handleInputChange('customer_phone', e.target.value)}
                required
              />
              {errors.customer_phone && (
                <p className="text-sm text-red-500">{errors.customer_phone}</p>
              )}
            </div>
          </div>

          {/* Custom Fields */}
          {customFields.length > 0 && (
            <div className="space-y-4">
              <h3 className="font-heading text-lg font-black text-devora-primary">
                Additional Information
              </h3>
              {customFields.map(field => renderField(field))}
            </div>
          )}

          {/* Terms & Conditions */}
          {product.terms_content && (
            <div className="space-y-4">
              <h3 className="font-heading text-lg font-black text-devora-primary">
                Terms & Conditions
              </h3>
              <div 
                className="prose max-w-none p-4 bg-devora-background-light rounded-devora-button border border-devora-primary-light"
                dangerouslySetInnerHTML={{ __html: product.terms_content }}
              />
              {product.require_acceptance && (
                <div className="flex items-start space-x-2">
                  <input
                    type="checkbox"
                    id="terms_accepted"
                    checked={formData.terms_accepted}
                    onChange={(e) => handleInputChange('terms_accepted', e.target.checked)}
                    className="h-4 w-4 mt-1"
                    required
                  />
                  <Label htmlFor="terms_accepted" className="cursor-pointer">
                    I have read and accept the terms and conditions
                    <span className="text-red-500 ml-1">*</span>
                  </Label>
                </div>
              )}
              {errors.terms_accepted && (
                <p className="text-sm text-red-500">{errors.terms_accepted}</p>
              )}
            </div>
          )}

          {/* Price Summary */}
          <div className="bg-devora-background-light p-4 rounded-devora-button border border-devora-primary-light">
            <div className="flex justify-between items-center">
              <span className="font-ui font-bold text-devora-primary">Total Price:</span>
              <span className="font-heading text-2xl font-black text-devora-primary">
                {totalPrice.toFixed(2)} {product.currency}
              </span>
            </div>
            <p className="text-sm text-devora-text-primary mt-1">
              Billed {product.billing_cycle}
            </p>
          </div>

          {/* Form Actions */}
          <div className="flex gap-4">
            <Button type="submit" variant="primary" className="flex-1">
              Continue to Signing
            </Button>
            {onCancel && (
              <Button type="button" variant="secondary" onClick={onCancel}>
                Cancel
              </Button>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  );
};

