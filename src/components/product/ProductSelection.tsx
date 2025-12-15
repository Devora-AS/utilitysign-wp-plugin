import React, { useState, useEffect } from 'react';
import { Button } from '../ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/Card';
import { Input } from '../ui/input';
import { Label } from '../ui/label';

interface Product {
  id: string;
  title: string;
  description: string;
  base_price: number;
  currency: string;
  billing_cycle: string;
  category: string;
  status: string;
  supplier_name?: string;
}

interface ProductSelectionProps {
  onProductSelect: (product: Product) => void;
  supplierId?: string;
  category?: string;
  className?: string;
}

export const ProductSelection: React.FC<ProductSelectionProps> = ({
  onProductSelect,
  supplierId,
  category,
  className = '',
}) => {
  const [products, setProducts] = useState<Product[]>([]);
  const [filteredProducts, setFilteredProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState(category || 'all');
  const [categories, setCategories] = useState<string[]>([]);

  // Fetch products on mount
  useEffect(() => {
    fetchProducts();
  }, [supplierId]);

  // Filter products when search term or category changes
  useEffect(() => {
    filterProducts();
  }, [searchTerm, selectedCategory, products]);

  const fetchProducts = async () => {
    try {
      setLoading(true);
      setError(null);

      const params = new URLSearchParams();
      if (supplierId) {
        params.append('supplier', supplierId);
      }
      params.append('status', 'active');

      const response = await fetch(
        `/wp-json/utilitysign/v1/products?${params.toString()}`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch products');
      }

      const data = await response.json();
      const productList = data.data || [];

      setProducts(productList);

      // Extract unique categories
      const uniqueCategories = [...new Set(productList.map((p: Product) => p.category).filter(Boolean))];
      setCategories(uniqueCategories);

      setLoading(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load products');
      setLoading(false);
    }
  };

  const filterProducts = () => {
    let filtered = [...products];

    // Filter by search term
    if (searchTerm) {
      filtered = filtered.filter(
        (product) =>
          product.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
          product.description.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }

    // Filter by category
    if (selectedCategory && selectedCategory !== 'all') {
      filtered = filtered.filter((product) => product.category === selectedCategory);
    }

    setFilteredProducts(filtered);
  };

  const handleProductSelect = (product: Product) => {
    onProductSelect(product);
  };

  if (loading) {
    return (
      <Card variant="white" className={className}>
        <CardContent>
          <div className="flex items-center justify-center py-12">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-devora-primary"></div>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card variant="white" className={className}>
        <CardContent>
          <div className="text-center py-12">
            <p className="text-red-500 mb-4">{error}</p>
            <Button variant="primary" onClick={fetchProducts}>
              Try Again
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card variant="white" className={className}>
      <CardHeader>
        <CardTitle className="text-2xl text-devora-primary">
          Select a Product
        </CardTitle>
        <p className="text-devora-text-primary mt-2">
          Choose the product you would like to order
        </p>
      </CardHeader>
      <CardContent>
        {/* Search and Filter */}
        <div className="space-y-4 mb-6">
          <div className="space-y-2">
            <Label htmlFor="search">Search Products</Label>
            <Input
              id="search"
              type="text"
              placeholder="Search by name or description..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>

          {categories.length > 0 && (
            <div className="space-y-2">
              <Label htmlFor="category">Category</Label>
              <select
                id="category"
                value={selectedCategory}
                onChange={(e) => setSelectedCategory(e.target.value)}
                className="devora-input"
              >
                <option value="all">All Categories</option>
                {categories.map((cat) => (
                  <option key={cat} value={cat}>
                    {cat.charAt(0).toUpperCase() + cat.slice(1)}
                  </option>
                ))}
              </select>
            </div>
          )}
        </div>

        {/* Product List */}
        {filteredProducts.length === 0 ? (
          <div className="text-center py-12">
            <p className="text-devora-text-primary">No products found</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredProducts.map((product) => (
              <Card
                key={product.id}
                variant="light"
                className="hover:border-devora-primary transition-colors cursor-pointer"
                onClick={() => handleProductSelect(product)}
              >
                <CardHeader>
                  <CardTitle className="text-lg text-devora-primary">
                    {product.title}
                  </CardTitle>
                  {product.supplier_name && (
                    <p className="text-sm text-devora-text-primary">
                      by {product.supplier_name}
                    </p>
                  )}
                </CardHeader>
                <CardContent>
                  <p className="text-devora-text-primary text-sm mb-4 line-clamp-3">
                    {product.description}
                  </p>

                  <div className="flex items-baseline justify-between">
                    <div>
                      <span className="font-heading text-2xl font-black text-devora-primary">
                        {product.base_price.toFixed(2)}
                      </span>
                      <span className="text-devora-text-primary ml-1">
                        {product.currency}
                      </span>
                    </div>
                    <span className="text-sm text-devora-text-primary">
                      /{product.billing_cycle}
                    </span>
                  </div>

                  {product.category && (
                    <div className="mt-4">
                      <span className="devora-badge-secondary text-xs">
                        {product.category}
                      </span>
                    </div>
                  )}

                  <Button variant="primary" className="w-full mt-4">
                    Select Product
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
};

