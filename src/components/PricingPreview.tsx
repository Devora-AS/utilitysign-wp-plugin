import React, { useState, useEffect } from 'react';

interface PricingPreviewData {
  quantity: number;
  unit_price: number;
  total_price: number;
  savings: number;
  savings_percentage: number;
  applied_discounts: Array<{
    type: string;
    name: string;
    amount: number;
    percentage?: number;
  }>;
}

interface PricingPreviewProps {
  productId: number;
  quantityRange?: number[];
  showBestValue?: boolean;
  className?: string;
}

export const PricingPreview: React.FC<PricingPreviewProps> = ({
  productId,
  quantityRange = [1, 5, 10, 25, 50, 100],
  showBestValue = true,
  className = ''
}) => {
  const [previewData, setPreviewData] = useState<PricingPreviewData[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchPricingPreview = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(
        `/wp-json/utilitysign/v1/pricing/preview/${productId}?quantity_range=${JSON.stringify(quantityRange)}`
      );
      
      if (!response.ok) {
        throw new Error('Failed to fetch pricing preview');
      }

      const data = await response.json();
      setPreviewData(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (productId) {
      fetchPricingPreview();
    }
  }, [productId, quantityRange]);

  const getBestValue = () => {
    if (previewData.length === 0) return null;
    
    return previewData.reduce((best, current) => {
      const currentValue = current.savings_percentage;
      const bestValue = best.savings_percentage;
      return currentValue > bestValue ? current : best;
    });
  };

  if (loading) {
    return (
      <div className={`utilitysign-pricing-preview ${className}`}>
        <div className="preview-loading">
          <span>Loading pricing preview...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`utilitysign-pricing-preview ${className}`}>
        <div className="preview-error">
          <span>Error: {error}</span>
        </div>
      </div>
    );
  }

  if (previewData.length === 0) {
    return null;
  }

  const bestValue = showBestValue ? getBestValue() : null;

  return (
    <div className={`utilitysign-pricing-preview ${className}`}>
      <h3>Pricing Preview</h3>
      
      {showBestValue && bestValue && (
        <div className="best-value-banner">
          <span className="best-value-label">Best Value:</span>
          <span className="best-value-quantity">{bestValue.quantity} units</span>
          <span className="best-value-savings">
            Save {bestValue.savings_percentage.toFixed(1)}%
          </span>
        </div>
      )}

      <div className="pricing-table">
        <div className="table-header">
          <div className="col-quantity">Quantity</div>
          <div className="col-unit-price">Unit Price</div>
          <div className="col-total-price">Total Price</div>
          <div className="col-savings">Savings</div>
        </div>
        
        {previewData.map((item, index) => (
          <div 
            key={index} 
            className={`table-row ${bestValue && item.quantity === bestValue.quantity ? 'best-value' : ''}`}
          >
            <div className="col-quantity">
              {item.quantity}
              {bestValue && item.quantity === bestValue.quantity && (
                <span className="best-value-indicator">★</span>
              )}
            </div>
            <div className="col-unit-price">
              ${item.unit_price.toFixed(2)}
            </div>
            <div className="col-total-price">
              ${item.total_price.toFixed(2)}
            </div>
            <div className="col-savings">
              {item.savings > 0 ? (
                <span className="savings-positive">
                  ${item.savings.toFixed(2)} ({item.savings_percentage.toFixed(1)}%)
                </span>
              ) : (
                <span className="savings-none">No savings</span>
              )}
            </div>
          </div>
        ))}
      </div>

      {previewData.some(item => item.applied_discounts.length > 0) && (
        <div className="discounts-summary">
          <h4>Available Discounts:</h4>
          <ul className="discounts-list">
            {previewData
              .flatMap(item => item.applied_discounts)
              .filter((discount, index, array) => 
                array.findIndex(d => d.name === discount.name) === index
              )
              .map((discount, index) => (
                <li key={index} className="discount-summary-item">
                  <span className="discount-name">{discount.name}</span>
                  {discount.percentage && (
                    <span className="discount-percentage">({discount.percentage}% off)</span>
                  )}
                </li>
              ))}
          </ul>
        </div>
      )}
    </div>
  );
};

export default PricingPreview;
