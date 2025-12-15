import React, { useEffect, useState } from 'react';

interface SupplierAnalytics {
    supplier_id: number;
    supplier_name: string;
    period: {
        from: string;
        to: string;
    };
    overview: {
        total_products: number;
        total_orders: number;
        total_revenue: number;
        average_order_value: number;
        active_products: number;
    };
    products: {
        products: Array<{
            product_id: number;
            product_name: string;
            orders_count: number;
            revenue: number;
            average_order_value: number;
            status: string;
            base_price: number;
        }>;
        summary: {
            total_products: number;
            total_orders: number;
            total_revenue: number;
            top_product: any;
        };
    };
    orders: {
        total_orders: number;
        total_revenue: number;
        average_order_value: number;
        status_breakdown: Record<string, number>;
        daily_orders: Record<string, number>;
        conversion_rate: number;
    };
    performance: {
        supplier_info: any;
        response_time: number;
        order_fulfillment_rate: number;
        customer_satisfaction: number;
        revenue_growth: number;
        product_performance: Array<{
            product_id: number;
            product_name: string;
            orders_count: number;
            revenue: number;
            performance_score: number;
        }>;
    };
    trends: {
        revenue_trend: {
            direction: string;
            percentage_change: number;
            confidence: number;
        };
        order_trend: {
            direction: string;
            percentage_change: number;
            confidence: number;
        };
        product_trend: {
            direction: string;
            percentage_change: number;
            confidence: number;
        };
        seasonal_patterns: {
            has_seasonal_pattern: boolean;
            peak_months: string[];
            low_months: string[];
            seasonal_variance: number;
        };
    };
}

interface SupplierAnalyticsDashboardProps {
    supplierId: number;
    dateFrom?: string;
    dateTo?: string;
    onExport?: (format: 'csv' | 'json' | 'pdf') => void;
}

const SupplierAnalyticsDashboard: React.FC<SupplierAnalyticsDashboardProps> = ({
    supplierId,
    dateFrom = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    dateTo = new Date().toISOString().split('T')[0],
    onExport
}) => {
    const [analytics, setAnalytics] = useState<SupplierAnalytics | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    const [activeTab, setActiveTab] = useState<'overview' | 'products' | 'orders' | 'performance' | 'trends'>('overview');

    useEffect(() => {
        fetchAnalytics();
    }, [supplierId, dateFrom, dateTo]);

    const fetchAnalytics = async () => {
        setLoading(true);
        setError(null);
        
        try {
            const response = await fetch(`/wp-json/utilitysign/v1/supplier-analytics/${supplierId}?date_from=${dateFrom}&date_to=${dateTo}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to fetch analytics.');
            }

            const data = await response.json();
            setAnalytics(data);
        } catch (err: any) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const handleExport = async (format: 'csv' | 'json' | 'pdf') => {
        try {
            const response = await fetch(`/wp-json/utilitysign/v1/supplier-analytics/${supplierId}/export?format=${format}&date_from=${dateFrom}&date_to=${dateTo}`, {
                method: 'GET',
            });

            if (format === 'csv') {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `supplier-analytics-${analytics?.supplier_name}-${supplierId}.csv`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else if (format === 'json') {
                const data = await response.json();
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `supplier-analytics-${analytics?.supplier_name}-${supplierId}.json`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else if (format === 'pdf') {
                // PDF export would be handled differently
                const data = await response.json();
                console.log('PDF export data:', data);
            }

            if (onExport) {
                onExport(format);
            }
        } catch (err: any) {
            console.error('Export failed:', err.message);
        }
    };

    if (loading) {
        return (
            <div className="utilitysign-analytics-loading">
                <p>Loading analytics...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="utilitysign-analytics-error">
                <p style={{ color: 'red' }}>Error: {error}</p>
                <button onClick={fetchAnalytics} className="button">Retry</button>
            </div>
        );
    }

    if (!analytics) {
        return (
            <div className="utilitysign-analytics-no-data">
                <p>No analytics data available.</p>
            </div>
        );
    }

    return (
        <div className="utilitysign-supplier-analytics-dashboard">
            <div className="analytics-header">
                <h2>Analytics Dashboard - {analytics.supplier_name}</h2>
                <div className="analytics-controls">
                    <div className="date-range">
                        <label>From:</label>
                        <input 
                            type="date" 
                            value={dateFrom} 
                            onChange={(e) => {
                                const newDateFrom = e.target.value;
                                if (newDateFrom <= dateTo) {
                                    window.location.href = `?date_from=${newDateFrom}&date_to=${dateTo}`;
                                }
                            }}
                        />
                        <label>To:</label>
                        <input 
                            type="date" 
                            value={dateTo} 
                            onChange={(e) => {
                                const newDateTo = e.target.value;
                                if (newDateTo >= dateFrom) {
                                    window.location.href = `?date_from=${dateFrom}&date_to=${newDateTo}`;
                                }
                            }}
                        />
                    </div>
                    <div className="export-controls">
                        <button onClick={() => handleExport('csv')} className="button">Export CSV</button>
                        <button onClick={() => handleExport('json')} className="button">Export JSON</button>
                        <button onClick={() => handleExport('pdf')} className="button">Export PDF</button>
                    </div>
                </div>
            </div>

            <div className="analytics-tabs">
                <button 
                    className={activeTab === 'overview' ? 'active' : ''} 
                    onClick={() => setActiveTab('overview')}
                >
                    Overview
                </button>
                <button 
                    className={activeTab === 'products' ? 'active' : ''} 
                    onClick={() => setActiveTab('products')}
                >
                    Products
                </button>
                <button 
                    className={activeTab === 'orders' ? 'active' : ''} 
                    onClick={() => setActiveTab('orders')}
                >
                    Orders
                </button>
                <button 
                    className={activeTab === 'performance' ? 'active' : ''} 
                    onClick={() => setActiveTab('performance')}
                >
                    Performance
                </button>
                <button 
                    className={activeTab === 'trends' ? 'active' : ''} 
                    onClick={() => setActiveTab('trends')}
                >
                    Trends
                </button>
            </div>

            <div className="analytics-content">
                {activeTab === 'overview' && (
                    <div className="overview-tab">
                        <div className="metrics-grid">
                            <div className="metric-card">
                                <h3>Total Products</h3>
                                <p className="metric-value">{analytics.overview.total_products}</p>
                            </div>
                            <div className="metric-card">
                                <h3>Total Orders</h3>
                                <p className="metric-value">{analytics.overview.total_orders}</p>
                            </div>
                            <div className="metric-card">
                                <h3>Total Revenue</h3>
                                <p className="metric-value">{analytics.overview.total_revenue.toFixed(2)} NOK</p>
                            </div>
                            <div className="metric-card">
                                <h3>Average Order Value</h3>
                                <p className="metric-value">{analytics.overview.average_order_value.toFixed(2)} NOK</p>
                            </div>
                            <div className="metric-card">
                                <h3>Active Products</h3>
                                <p className="metric-value">{analytics.overview.active_products}</p>
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'products' && (
                    <div className="products-tab">
                        <h3>Product Performance</h3>
                        <div className="products-summary">
                            <p>Total Products: {analytics.products.summary.total_products}</p>
                            <p>Total Orders: {analytics.products.summary.total_orders}</p>
                            <p>Total Revenue: {analytics.products.summary.total_revenue.toFixed(2)} NOK</p>
                        </div>
                        <table className="products-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Orders</th>
                                    <th>Revenue</th>
                                    <th>Avg Order Value</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {analytics.products.products.map((product) => (
                                    <tr key={product.product_id}>
                                        <td>{product.product_name}</td>
                                        <td>{product.orders_count}</td>
                                        <td>{product.revenue.toFixed(2)} NOK</td>
                                        <td>{product.average_order_value.toFixed(2)} NOK</td>
                                        <td>
                                            <span className={`status-badge status-${product.status}`}>
                                                {product.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {activeTab === 'orders' && (
                    <div className="orders-tab">
                        <h3>Order Analytics</h3>
                        <div className="orders-summary">
                            <p>Total Orders: {analytics.orders.total_orders}</p>
                            <p>Total Revenue: {analytics.orders.total_revenue.toFixed(2)} NOK</p>
                            <p>Average Order Value: {analytics.orders.average_order_value.toFixed(2)} NOK</p>
                            <p>Conversion Rate: {analytics.orders.conversion_rate.toFixed(2)}%</p>
                        </div>
                        
                        <div className="status-breakdown">
                            <h4>Order Status Breakdown</h4>
                            <div className="status-chart">
                                {Object.entries(analytics.orders.status_breakdown).map(([status, count]) => (
                                    <div key={status} className="status-bar">
                                        <span className="status-label">{status}</span>
                                        <div className="status-progress">
                                            <div 
                                                className="status-fill" 
                                                style={{ 
                                                    width: `${(count / analytics.orders.total_orders) * 100}%` 
                                                }}
                                            ></div>
                                        </div>
                                        <span className="status-count">{count}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'performance' && (
                    <div className="performance-tab">
                        <h3>Performance Metrics</h3>
                        <div className="performance-metrics">
                            <div className="metric-card">
                                <h4>Response Time</h4>
                                <p>{analytics.performance.response_time} hours</p>
                            </div>
                            <div className="metric-card">
                                <h4>Fulfillment Rate</h4>
                                <p>{analytics.performance.order_fulfillment_rate.toFixed(2)}%</p>
                            </div>
                            <div className="metric-card">
                                <h4>Customer Satisfaction</h4>
                                <p>{analytics.performance.customer_satisfaction}/5</p>
                            </div>
                            <div className="metric-card">
                                <h4>Revenue Growth</h4>
                                <p>{analytics.performance.revenue_growth.toFixed(2)}%</p>
                            </div>
                        </div>
                        
                        <div className="product-performance">
                            <h4>Product Performance</h4>
                            <table className="performance-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                        <th>Performance Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {analytics.performance.product_performance.map((product) => (
                                        <tr key={product.product_id}>
                                            <td>{product.product_name}</td>
                                            <td>{product.orders_count}</td>
                                            <td>{product.revenue.toFixed(2)} NOK</td>
                                            <td>{product.performance_score}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {activeTab === 'trends' && (
                    <div className="trends-tab">
                        <h3>Trends Analysis</h3>
                        <div className="trends-grid">
                            <div className="trend-card">
                                <h4>Revenue Trend</h4>
                                <p>Direction: {analytics.trends.revenue_trend.direction}</p>
                                <p>Change: {analytics.trends.revenue_trend.percentage_change.toFixed(2)}%</p>
                                <p>Confidence: {(analytics.trends.revenue_trend.confidence * 100).toFixed(1)}%</p>
                            </div>
                            <div className="trend-card">
                                <h4>Order Trend</h4>
                                <p>Direction: {analytics.trends.order_trend.direction}</p>
                                <p>Change: {analytics.trends.order_trend.percentage_change.toFixed(2)}%</p>
                                <p>Confidence: {(analytics.trends.order_trend.confidence * 100).toFixed(1)}%</p>
                            </div>
                            <div className="trend-card">
                                <h4>Product Trend</h4>
                                <p>Direction: {analytics.trends.product_trend.direction}</p>
                                <p>Change: {analytics.trends.product_trend.percentage_change.toFixed(2)}%</p>
                                <p>Confidence: {(analytics.trends.product_trend.confidence * 100).toFixed(1)}%</p>
                            </div>
                        </div>
                        
                        <div className="seasonal-patterns">
                            <h4>Seasonal Patterns</h4>
                            {analytics.trends.seasonal_patterns.has_seasonal_pattern ? (
                                <div>
                                    <p>Peak Months: {analytics.trends.seasonal_patterns.peak_months.join(', ')}</p>
                                    <p>Low Months: {analytics.trends.seasonal_patterns.low_months.join(', ')}</p>
                                    <p>Seasonal Variance: {(analytics.trends.seasonal_patterns.seasonal_variance * 100).toFixed(1)}%</p>
                                </div>
                            ) : (
                                <p>No significant seasonal patterns detected.</p>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default SupplierAnalyticsDashboard;
