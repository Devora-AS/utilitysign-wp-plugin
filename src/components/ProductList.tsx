/**
 * Product List Component
 * 
 * Displays all available electricity products.
 * Fetches products from WordPress AJAX endpoint.
 * Uses Devora Design System for consistent branding.
 */

import React, { useState, useEffect } from 'react';
import { ProductCard, Product } from './ProductCard';

interface ProductListProps {
  onProductSelect?: (productId: string) => void;
  showCategories?: boolean;
  maxProducts?: number;
}

// Declare global utilitySignData from WordPress
declare global {
  interface Window {
    utilitySignData: {
      ajaxUrl: string;
      nonce: string;
      apiUrl: string;
      backendApiUrl: string;
    };
  }
}

export const ProductList: React.FC<ProductListProps> = ({ 
  onProductSelect,
  showCategories = true,
  maxProducts
}) => {
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedProductId, setSelectedProductId] = useState<string | null>(null);
  const [categoryFilter, setCategoryFilter] = useState<string>('all');

  useEffect(() => {
    fetchProducts();
  }, []);

  const fetchProducts = async () => {
    try {
      setLoading(true);
      setError(null);

      const formData = new FormData();
      formData.append('action', 'utilitysign_get_products');
      formData.append('nonce', window.utilitySignData.nonce);

      const response = await fetch(window.utilitySignData.ajaxUrl, {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.data || 'Failed to load products');
      }

      setProducts(data.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  const handleProductSelect = (productId: string) => {
    setSelectedProductId(productId);
    
    if (onProductSelect) {
      onProductSelect(productId);
    } else {
      // Default behavior: redirect to order form with product ID
      const orderFormUrl = new URL(window.location.href);
      orderFormUrl.searchParams.set('product_id', productId);
      orderFormUrl.searchParams.set('step', 'order');
      window.location.href = orderFormUrl.toString();
    }
  };

  const filteredProducts = categoryFilter === 'all'
    ? products
    : products.filter(p => p.category === categoryFilter);

  const displayProducts = maxProducts
    ? filteredProducts.slice(0, maxProducts)
    : filteredProducts;

  // Loading state
  if (loading) {
    return (
      <div className="utilitysign-product-list-loading text-center py-12">
        <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-devora-primary mb-4"></div>
        <p className="text-devora-text-muted">Laster produkter...</p>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="utilitysign-product-list-error bg-red-50 border-2 border-red-200 text-red-700 px-6 py-4 rounded-lg">
        <strong>⚠️ Feil:</strong> {error}
        <button
          onClick={fetchProducts}
          className="ml-4 text-sm underline hover:no-underline"
        >
          Prøv igjen
        </button>
      </div>
    );
  }

  // Empty state
  if (products.length === 0) {
    return (
      <div className="utilitysign-product-list-empty text-center py-12">
        <p className="text-devora-text-muted text-lg mb-4">
          Ingen produkter tilgjengelig for øyeblikket.
        </p>
        <button
          onClick={fetchProducts}
          className="bg-devora-primary text-white py-2 px-6 rounded-devora-button font-ui font-bold hover:bg-devora-primary/90 transition-colors"
        >
          Last inn på nytt
        </button>
      </div>
    );
  }

  return (
    <div className="utilitysign-product-list">
      {/* Category Filter */}
      {showCategories && (
        <div className="mb-6 flex gap-2 flex-wrap">
          <button
            onClick={() => setCategoryFilter('all')}
            className={`py-2 px-4 rounded-devora-button font-ui font-bold text-sm transition-colors ${
              categoryFilter === 'all'
                ? 'bg-devora-primary text-white'
                : 'bg-devora-background-light text-devora-primary hover:bg-devora-primary-light/20'
            }`}
          >
            Alle produkter
          </button>
          <button
            onClick={() => setCategoryFilter('Spot')}
            className={`py-2 px-4 rounded-devora-button font-ui font-bold text-sm transition-colors ${
              categoryFilter === 'Spot'
                ? 'bg-devora-primary text-white'
                : 'bg-devora-background-light text-devora-primary hover:bg-devora-primary-light/20'
            }`}
          >
            Spotpris
          </button>
          <button
            onClick={() => setCategoryFilter('Fixed')}
            className={`py-2 px-4 rounded-devora-button font-ui font-bold text-sm transition-colors ${
              categoryFilter === 'Fixed'
                ? 'bg-devora-primary text-white'
                : 'bg-devora-background-light text-devora-primary hover:bg-devora-primary-light/20'
            }`}
          >
            Fast pris
          </button>
          <button
            onClick={() => setCategoryFilter('Variable')}
            className={`py-2 px-4 rounded-devora-button font-ui font-bold text-sm transition-colors ${
              categoryFilter === 'Variable'
                ? 'bg-devora-primary text-white'
                : 'bg-devora-background-light text-devora-primary hover:bg-devora-primary-light/20'
            }`}
          >
            Variabel pris
          </button>
        </div>
      )}
      
      {/* Product Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {displayProducts.map(product => (
          <ProductCard
            key={product.id}
            product={product}
            onSelect={handleProductSelect}
            selected={product.id === selectedProductId}
          />
        ))}
      </div>
      
      {/* Results count */}
      {filteredProducts.length > 0 && (
        <div className="mt-6 text-center text-sm text-devora-text-muted">
          Viser {displayProducts.length} av {filteredProducts.length} produkt{filteredProducts.length !== 1 ? 'er' : ''}
        </div>
      )}
    </div>
  );
};

