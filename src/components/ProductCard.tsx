/**
 * Product Card Component
 * 
 * Displays a single electricity product with selection button.
 * Uses Devora Design System for consistent branding.
 */

import React from 'react';

export interface Product {
  id: string;
  name: string;
  description: string;
  price: number;
  formatted_price: string;
  category: string;
  sku?: string;
  isActive: boolean;
}

interface ProductCardProps {
  product: Product;
  onSelect: (productId: string) => void;
  selected?: boolean;
}

export const ProductCard: React.FC<ProductCardProps> = ({ product, onSelect, selected = false }) => {
  return (
    <div className={`devora-card ${selected ? 'ring-2 ring-devora-primary' : ''} hover:shadow-lg transition-shadow`}>
      {/* Category Badge */}
      <div className="mb-3">
        <span className="devora-badge-primary text-xs">
          {product.category}
        </span>
      </div>
      
      {/* Product Name */}
      <h3 className="font-heading text-xl font-black text-devora-primary mb-2">
        {product.name}
      </h3>
      
      {/* Product Description */}
      <p className="text-devora-text-primary text-sm mb-4 line-clamp-3">
        {product.description}
      </p>
      
      {/* Price */}
      <div className="mb-4 pb-4 border-b border-devora-primary-light">
        <div className="flex items-baseline gap-2">
          <span className="font-heading text-3xl font-black text-devora-primary">
            {product.formatted_price}
          </span>
          <span className="text-devora-text-muted text-sm">
            / måned
          </span>
        </div>
      </div>
      
      {/* Features (placeholder for now) */}
      <ul className="space-y-2 mb-6 text-sm text-devora-text-primary">
        <li className="flex items-start">
          <span className="text-devora-primary mr-2">✓</span>
          <span>Ingen binding</span>
        </li>
        <li className="flex items-start">
          <span className="text-devora-primary mr-2">✓</span>
          <span>Gratis bytting</span>
        </li>
        <li className="flex items-start">
          <span className="text-devora-primary mr-2">✓</span>
          <span>Digital signering med BankID</span>
        </li>
      </ul>
      
      {/* Select Button */}
      <button
        onClick={() => onSelect(product.id)}
        className={`w-full py-3 px-4 rounded-devora-button font-ui font-bold text-sm transition-colors ${
          selected
            ? 'bg-devora-accent text-devora-primary hover:bg-devora-accent/90'
            : 'bg-devora-primary text-white hover:bg-devora-primary/90'
        }`}
      >
        {selected ? '✓ Valgt' : 'Velg dette produktet'}
      </button>
      
      {/* SKU (if available) */}
      {product.sku && (
        <div className="mt-3 text-xs text-devora-text-muted text-center">
          Produktkode: {product.sku}
        </div>
      )}
    </div>
  );
};

