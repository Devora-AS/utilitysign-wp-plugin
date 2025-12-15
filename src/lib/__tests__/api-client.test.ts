import apiClient from '../api-client';

// Mock fetch
global.fetch = jest.fn();

// Mock window.utilitySign
const mockUtilitySign = {
  apiUrl: 'http://localhost/wp-json/utilitysign/v1',
  nonce: 'test-nonce',
  backendApiUrl: 'https://api-staging.utilitysign.devora.no',
};

beforeEach(() => {
  (global as any).window = {
    utilitySign: mockUtilitySign,
  };
  (fetch as jest.Mock).mockClear();
});

describe('APIClient', () => {
  describe('WordPress API methods', () => {
    it('should make GET request to WordPress API', async () => {
      const mockResponse = { success: true, data: { id: 1, title: 'Test Product' } };
      (fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      const result = await apiClient.get('/products/get');

      expect(fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/utilitysign/v1/products/get',
        {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': 'test-nonce',
          },
        }
      );

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockResponse);
      expect(result.correlationId).toBeDefined();
      expect(result.timestamp).toBeDefined();
    });

    it('should make POST request to WordPress API', async () => {
      const mockData = { title: 'New Product', supplier: 'Test Supplier' };
      const mockResponse = { success: true, data: { id: 2, ...mockData } };
      (fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      const result = await apiClient.post('/products/create', mockData);

      expect(fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/utilitysign/v1/products/create',
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': 'test-nonce',
          },
          body: JSON.stringify(mockData),
        }
      );

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockResponse);
    });

    it('should handle HTTP errors in WordPress API', async () => {
      (fetch as jest.Mock).mockResolvedValueOnce({
        ok: false,
        status: 404,
      });

      const result = await apiClient.get('/products/get/999');

      expect(result.success).toBe(false);
      expect(result.error).toBe('HTTP error! status: 404');
    });

    it('should handle network errors in WordPress API', async () => {
      (fetch as jest.Mock).mockRejectedValueOnce(new Error('Network error'));

      const result = await apiClient.get('/products/get');

      expect(result.success).toBe(false);
      expect(result.error).toBe('Network error');
    });

    it('should use fallback values when window.utilitySign is not available', async () => {
      (global as any).window = {};

      const mockResponse = { success: true, data: [] };
      (fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      const result = await apiClient.get('/products/get');

      expect(fetch).toHaveBeenCalledWith(
        '/wp-json/utilitysign/v1/products/get',
        {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': '',
          },
        }
      );

      expect(result.success).toBe(true);
    });
  });

  describe('Backend API methods', () => {
    it('should make authenticated request to backend API', async () => {
      const mockToken = 'test-jwt-token';
      const mockResponse = { success: true, data: { orders: [] } };
      (fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      const result = await apiClient.getBackendData('/orders', mockToken);

      expect(fetch).toHaveBeenCalledWith(
        'https://api-staging.utilitysign.devora.no/orders',
        {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${mockToken}`,
          },
        }
      );

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockResponse);
    });

    it('should handle backend API errors', async () => {
      (fetch as jest.Mock).mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      const result = await apiClient.getBackendData('/orders', 'invalid-token');

      expect(result.success).toBe(false);
      expect(result.error).toBe('HTTP error! status: 401');
    });
  });

  describe('Utility methods', () => {
    it('should generate unique correlation IDs', () => {
      const id1 = apiClient.generateCorrelationId();
      const id2 = apiClient.generateCorrelationId();

      expect(id1).toBeDefined();
      expect(id2).toBeDefined();
      expect(id1).not.toBe(id2);
      expect(typeof id1).toBe('string');
      expect(id1.length).toBeGreaterThan(0);
    });

    it('should log errors with correlation ID', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const error = new Error('Test error');
      const context = { endpoint: '/test' };

      apiClient.logError('Test operation failed', error, context);

      expect(consoleSpy).toHaveBeenCalledWith(
        'UtilitySign API Error:',
        'Test operation failed',
        error,
        expect.objectContaining({
          correlationId: expect.any(String),
          timestamp: expect.any(String),
          context,
        })
      );

      consoleSpy.mockRestore();
    });
  });

  describe('Caching', () => {
    it('should cache GET requests', async () => {
      const mockResponse = { success: true, data: { id: 1 } };
      (fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      // First request
      const result1 = await apiClient.get('/products/get');
      expect(fetch).toHaveBeenCalledTimes(1);

      // Second request should use cache
      const result2 = await apiClient.get('/products/get');
      expect(fetch).toHaveBeenCalledTimes(1); // Still 1, not 2
      expect(result1).toEqual(result2);
    });

    it('should not cache POST requests', async () => {
      const mockResponse = { success: true, data: { id: 1 } };
      (fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      await apiClient.post('/products/create', { title: 'Test' });
      await apiClient.post('/products/create', { title: 'Test' });

      expect(fetch).toHaveBeenCalledTimes(2);
    });
  });

  describe('Rate limiting', () => {
    it('should respect rate limits', async () => {
      const mockResponse = { success: true, data: [] };
      (fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      });

      // Make multiple requests quickly
      const promises = Array(10).fill(null).map(() => apiClient.get('/products/get'));
      await Promise.all(promises);

      // Should not exceed rate limit
      expect(fetch).toHaveBeenCalledTimes(10);
    });
  });
});
