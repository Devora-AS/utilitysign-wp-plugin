import React, { useState, useEffect } from 'react';

interface PricingData {
  product_id: number;
  quantity: number;
  base_price: number;
  unit_price: number;
  total_price: number;
  applied_discounts: Array<{
    type: string;
    name: string;
    amount: number;
    percentage?: number;
    new_unit_price?: number;
  }>;
  final_price: number;
  savings: number;
  savings_percentage: number;
  pricing_breakdown: any[];
}

interface PricingDisplayProps {
  productId: number;
  initialQuantity?: number;
  showQuantitySelector?: boolean;
  showPricingBreakdown?: boolean;
  className?: string;
}

export const PricingDisplay: React.FC<PricingDisplayProps> = ({
  productId,
  initialQuantity = 1,
  showQuantitySelector = true,
  showPricingBreakdown = true,
  className = ''
}) => {
  const [quantity, setQuantity] = useState(initialQuantity);
  const [pricingData, setPricingData] = useState<PricingData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchPricing = async (qty: number) => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(
        `/wp-json/utilitysign/v1/pricing/calculate?product_id=${productId}&quantity=${qty}`
      );
      
      if (!response.ok) {
        throw new Error('Failed to fetch pricing data');
      }

      const data = await response.json();
      setPricingData(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (productId) {
      fetchPricing(quantity);
    }
  }, [productId, quantity]);

  const handleQuantityChange = (newQuantity: number) => {
    if (newQuantity >= 1) {
      setQuantity(newQuantity);
    }
  };

  if (loading) {
    return (
      <div className={`utilitysign-pricing-display ${className}`}>
        <div className="pricing-loading">
          <span>Loading pricing...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`utilitysign-pricing-display ${className}`}>
        <div className="pricing-error">
          <span>Error: {error}</span>
        </div>
      </div>
    );
  }

  if (!pricingData) {
    return null;
  }

  return (
    <div className={`utilitysign-pricing-display ${className}`}>
      {showQuantitySelector && (
        <div className="quantity-selector">
          <label htmlFor="pricing-quantity">Quantity:</label>
          <input
            id="pricing-quantity"
            type="number"
            min="1"
            value={quantity}
            onChange={(e) => handleQuantityChange(parseInt(e.target.value) || 1)}
            className="quantity-input"
          />
        </div>
      )}

      <div className="pricing-summary">
        <div className="base-pricing">
          <span className="label">Base Price:</span>
          <span className="value">${pricingData.base_price.toFixed(2)} per unit</span>
        </div>
        
        <div className="final-pricing">
          <span className="label">Final Price:</span>
          <span className="value final-price">${pricingData.final_price.toFixed(2)}</span>
        </div>

        {pricingData.savings > 0 && (
          <div className="savings">
            <span className="label">You Save:</span>
            <span className="value savings-amount">
              ${pricingData.savings.toFixed(2)} ({pricingData.savings_percentage.toFixed(1)}%)
            </span>
          </div>
        )}
      </div>

      {showPricingBreakdown && pricingData.applied_discounts.length > 0 && (
        <div className="pricing-breakdown">
          <h4>Applied Discounts:</h4>
          <ul className="discounts-list">
            {pricingData.applied_discounts.map((discount, index) => (
              <li key={index} className="discount-item">
                <span className="discount-name">{discount.name}</span>
                <span className="discount-amount">
                  -${discount.amount.toFixed(2)}
                  {discount.percentage && ` (${discount.percentage}%)`}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="pricing-details">
        <div className="unit-price">
          <span className="label">Unit Price:</span>
          <span className="value">${pricingData.unit_price.toFixed(2)}</span>
        </div>
        <div className="total-quantity">
          <span className="label">Quantity:</span>
          <span className="value">{pricingData.quantity}</span>
        </div>
        <div className="total-price">
          <span className="label">Total:</span>
          <span className="value">${pricingData.total_price.toFixed(2)}</span>
        </div>
      </div>
    </div>
  );
};

export default PricingDisplay;
