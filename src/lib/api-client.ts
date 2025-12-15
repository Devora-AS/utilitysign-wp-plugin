/**
 * API Client for UtilitySign WordPress Plugin
 * Handles secure communication with the UtilitySign backend API
 * Implements Microsoft Entra ID JWT authentication, caching, rate limiting, and comprehensive error handling
 */

export interface APIResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
  correlationId?: string;
  timestamp?: string;
}

export interface Document {
  id: string;
  title: string;
  content: string;
  status: 'draft' | 'pending' | 'signed' | 'rejected';
  created_at: string;
  updated_at: string;
  signed_at?: string;
  signer_email?: string;
  signer_name?: string;
  pdf_url?: string;
  metadata: {
    template_id?: string;
    variables?: Record<string, any>;
  };
}

export interface SigningRequest {
  id: string;
  document_id: string;
  signer_email: string;
  signer_name: string;
  status: 'pending' | 'in_progress' | 'completed' | 'expired' | 'failed';
  created_at: string;
  expires_at: string;
  completed_at?: string;
  signing_url?: string; // Criipto signing URL (from backend response)
  bankid_session_id?: string;
  bankid_auth_url?: string;
  correlation_id?: string;
  retry_count?: number;
}

export interface UtilitySignConfig {
  backendApiUrl: string;
  wordpressApiUrl: string;
  wordpressNonce: string;
  environment: 'staging' | 'production';
  clientId: string;
  pluginKey: string;
  pluginKeyStatus: 'valid' | 'missing' | 'invalid';
  enableDebugMode: boolean;
  enableCaching: boolean;
  cacheTimeout: number;
  enableRateLimiting: boolean;
  maxRequestsPerMinute: number;
  enableRequestValidation: boolean;
  enableLogging: boolean;
  logLevel: 'error' | 'warn' | 'info' | 'debug';
}

export interface CacheEntry<T> {
  data: T;
  timestamp: number;
  expiresAt: number;
  hits: number;
}

export interface RateLimitInfo {
  remaining: number;
  resetTime: number;
  retryAfter?: number;
  limit: number;
}

export class APIError extends Error {
  statusCode?: number;
  correlationId?: string;
  retryable?: boolean;
  userMessage?: string;
  timestamp?: string;
  validationErrors?: string[];

  constructor(message: string, options?: {
    statusCode?: number;
    correlationId?: string;
    retryable?: boolean;
    userMessage?: string;
    timestamp?: string;
    validationErrors?: string[];
  }) {
    super(message);
    this.name = 'APIError';
    this.statusCode = options?.statusCode;
    this.correlationId = options?.correlationId;
    this.retryable = options?.retryable;
    this.userMessage = options?.userMessage;
    this.timestamp = options?.timestamp || new Date().toISOString();
    this.validationErrors = options?.validationErrors;
    
    // Maintain proper prototype chain for instanceof checks
    Object.setPrototypeOf(this, APIError.prototype);
  }
}

export interface SecurityHeaders {
  'Content-Type': string;
  'x-client-id': string;
  'X-Correlation-ID': string; // Backend allows both X-Correlation-ID and x-correlation-id
  'x-environment': string;
  'x-plugin-version': string;
  'x-request-source': string;
  'x-request-timestamp': string;
  'x-request-hash'?: string;
  'Authorization'?: string;
  'x-idempotency-key'?: string;
  'x-request-id'?: string;
  'X-Rate-Limit-Bypass'?: string;
}

export interface RequestMetrics {
  endpoint: string;
  method: string;
  statusCode: number;
  responseTime: number;
  timestamp: number;
  correlationId: string;
  cacheHit: boolean;
  retryCount: number;
}

class APIClient {
  private config: UtilitySignConfig;
  private baseUrl: string;
  private backendBaseUrl: string;
  private wordpressApiBase: string;
  private wordpressNonce: string;
  private retryAttempts: number = 3;
  private retryDelay: number = 1000;
  private cache: Map<string, CacheEntry<any>> = new Map();
  private rateLimitInfo: RateLimitInfo = { remaining: 60, resetTime: Date.now() + 60000, limit: 60 };
  // Request queue for future rate limiting implementation
  // @ts-expect-error - Reserved for future queue functionality
  private _requestQueue: Array<() => Promise<any>> = [];
  // @ts-expect-error - Reserved for future queue functionality
  private _isProcessingQueue: boolean = false;
  private metrics: RequestMetrics[] = [];
  private maxMetricsHistory: number = 1000;

  /**
   * Get the current environment configuration
   */
  static getEnvironmentConfig(): UtilitySignConfig {
    // Check both frontend and admin config objects
    const frontendConfig = (window as any).utilitySignFrontend || {};
    const adminConfig = (window as any).utilitySign || {};
    // Merge configs (frontend takes precedence if both exist)
    const config = { ...adminConfig, ...frontendConfig };
    
    const pluginKey = config.pluginKey || '';
    const pluginKeyStatus: UtilitySignConfig['pluginKeyStatus'] = config.pluginKeyStatus || (pluginKey ? 'valid' : 'missing');

    const wordpressApiUrl = config.apiUrl || '/wp-json/utilitysign/v1/';
    const backendApiUrl = config.backendApiUrl || 'https://api.utilitysign.devora.no';
    const isProduction = backendApiUrl.includes('api.utilitysign.devora.no') && !backendApiUrl.includes('staging');

    const queryDebug = typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    const enableDebugMode = config.debugMode ?? queryDebug ?? false;
    const wordpressNonce = config.restNonce || '';
    const clientId = config.clientId || 'utilitysign-wordpress-plugin';

    return {
      backendApiUrl,
      wordpressApiUrl,
      wordpressNonce,
      environment: isProduction ? 'production' : 'staging',
      clientId,
      pluginKey,
      pluginKeyStatus,
      enableDebugMode,
      enableCaching: true,
      cacheTimeout: 300000,
      enableRateLimiting: true,
      maxRequestsPerMinute: 60,
      enableRequestValidation: true,
      enableLogging: true,
      logLevel: 'info',
    };
  }

  constructor(config: UtilitySignConfig) {
    // Merge config with defaults (config values take precedence)
    const defaults: Partial<UtilitySignConfig> = {
      enableCaching: true,
      cacheTimeout: 300000,
      enableRateLimiting: true,
      maxRequestsPerMinute: 60,
      enableDebugMode: false,
      enableRequestValidation: true,
      enableLogging: true,
      logLevel: 'info',
    };
    this.config = {
      ...defaults,
      ...config,
    } as UtilitySignConfig;
    this.backendBaseUrl = this.normalizeBaseUrl(this.config.backendApiUrl);
    this.wordpressApiBase = this.normalizeBaseUrl(this.config.wordpressApiUrl || '/wp-json/utilitysign/v1');
    this.wordpressNonce = this.config.wordpressNonce || '';
    this.baseUrl = this.backendBaseUrl;
    this.validateConfig();
    this.initializeSecurity();
  }

  private validateConfig(): void {
    if (!this.config.backendApiUrl) {
      throw new Error('Backend API URL is required');
    }
    if (!this.config.wordpressApiUrl) {
      throw new Error('WordPress REST API URL is required');
    }
    if (!this.wordpressNonce) {
      throw new Error('WordPress REST API nonce is missing');
    }
    // Plugin key validation is optional - allow admin pages to load without key
    // Components should check pluginKeyStatus and show appropriate UI
    if (!this.config.clientId) {
      throw new Error('Client identifier is required');
    }
  }

  private normalizeBaseUrl(url: string): string {
    if (!url) {
      return '';
    }
    return url.endsWith('/') ? url.slice(0, -1) : url;
  }

  private initializeSecurity(): void {
    // Clear expired cache entries periodically
    setInterval(() => this.clearExpiredCache(), 60000); // Every minute
    
    // Clean up old metrics
    setInterval(() => this.cleanupMetrics(), 300000); // Every 5 minutes
    
    // Log security initialization
    this.log('info', 'API Client security initialized', {
      environment: this.config.environment,
      caching: this.config.enableCaching,
      rateLimiting: this.config.enableRateLimiting
    });
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<APIResponse<T>> {
    const startTime = Date.now();
    const correlationId = this.generateCorrelationId();
    
    try {
      // Validate request
      if (this.config.enableRequestValidation) {
        this.validateRequest(endpoint, options);
      }

      // Check rate limiting
      if (this.config.enableRateLimiting && !this.checkRateLimit()) {
        throw new APIError('Rate limit exceeded', {
          statusCode: 429,
          correlationId,
          retryable: true,
          userMessage: 'Too many requests. Please try again later.',
          timestamp: new Date().toISOString()
        });
      }

      // Check if debug mode is enabled
      const debugMode = typeof window !== 'undefined' && 
        new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
      
      // Get authentication token
      const authToken = await this.getAuthToken();
      
      // Build security headers
      const headers = this.buildSecurityHeaders(correlationId, authToken, options);
      
      if (debugMode) {
        console.log('[APIClient] Making request', {
          endpoint,
          method: options.method || 'GET',
          url: `${this.baseUrl}${endpoint}`,
          headers: Object.keys(headers),
          hasBody: !!options.body,
          correlationId
        });
      }
      
      const response = await fetch(`${this.baseUrl}${endpoint}`, {
        ...options,
        mode: 'cors', // Explicitly enable CORS
        // Don't specify credentials - let browser default to 'same-origin'
        // This matches the standalone form behavior and works with backend AllowCredentials()
        headers: {
          ...headers,
          ...options.headers,
        },
      });
      
      if (debugMode) {
        console.log('[APIClient] Response received', {
          endpoint,
          status: response.status,
          statusText: response.statusText,
          ok: response.ok,
          correlationId
        });
      }

      const responseTime = Date.now() - startTime;

      // Update rate limit info from response headers
      this.updateRateLimitInfo(response);

      // Record metrics
      this.recordMetrics({
        endpoint,
        method: options.method || 'GET',
        statusCode: response.status,
        responseTime,
        timestamp: Date.now(),
        correlationId,
        cacheHit: false,
        retryCount: 0
      });

      if (!response.ok) {
        const errorData = await this.parseErrorResponse(response);
        
        // Check if this is a configuration error (non-retriable)
        const isConfigError = this.isConfigurationError(errorData);
        
        // Extract validation errors from ASP.NET Core ModelState format
        // ModelState format: { "field": ["error1", "error2"] }
        let validationErrors: string[] = [];
        if (response.status === 400 && errorData.errors) {
          // Flatten ModelState errors into a single array
          validationErrors = Object.values(errorData.errors).flat() as string[];
        }
        
        const errorMessage = validationErrors.length > 0
          ? validationErrors.join(', ')
          : (errorData.message || `HTTP error! status: ${response.status}`);
        
        throw new APIError(errorMessage, {
          statusCode: response.status,
          correlationId,
          retryable: isConfigError ? false : this.isRetryableStatusCode(response.status),
          userMessage: this.getUserFriendlyErrorMessage(response.status, errorMessage),
          timestamp: new Date().toISOString(),
          validationErrors: validationErrors.length > 0 ? validationErrors : undefined
        });
      }

      const data = await response.json();
      return {
        ...data,
        correlationId,
        timestamp: new Date().toISOString()
      };
    } catch (error) {
      const responseTime = Date.now() - startTime;
      
      // Check if debug mode is enabled
      const debugMode = typeof window !== 'undefined' && 
        new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
      
      // Enhanced error logging for CORS and network errors
      if (debugMode && error instanceof Error) {
        console.error('[APIClient] Request error details', {
          errorName: error.name,
          errorMessage: error.message,
          errorStack: error.stack,
          endpoint,
          method: options.method || 'GET',
          url: `${this.baseUrl}${endpoint}`,
          correlationId,
          responseTime
        });
        
        // Check if it's a CORS error
        if (error.message.includes('CORS') || error.message.includes('Access-Control')) {
          console.error('[APIClient] CORS error detected - this may be a browser cache issue. Try clearing browser cache or using a hard refresh (Ctrl+Shift+R / Cmd+Shift+R)');
        }
        
        // Check if it's a network error
        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
          console.error('[APIClient] Network error detected - check network connectivity and API availability');
        }
      }
      
      const apiError = error instanceof APIError ? error : new APIError(
        error instanceof Error ? error.message : 'Unknown error',
        { correlationId, retryable: true, timestamp: new Date().toISOString() }
      );
      
      // Record error metrics
      this.recordMetrics({
        endpoint,
        method: options.method || 'GET',
        statusCode: apiError.statusCode || 0,
        responseTime,
        timestamp: Date.now(),
        correlationId,
        cacheHit: false,
        retryCount: 0
      });
      
      this.logError('API request failed', apiError, { endpoint, options });
      
      return {
        success: false,
        error: apiError.userMessage || apiError.message,
        correlationId,
        timestamp: new Date().toISOString()
      };
    }
  }

  private validateRequest(endpoint: string, options: RequestInit): void {
    // Validate endpoint
    if (!endpoint || typeof endpoint !== 'string') {
      throw new Error('Invalid endpoint');
    }
    
    // Validate method
    if (options.method && !['GET', 'POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method)) {
      throw new Error('Invalid HTTP method');
    }
    
    // Validate body for POST/PUT/PATCH
    if (['POST', 'PUT', 'PATCH'].includes(options.method || 'GET') && options.body) {
      try {
        if (typeof options.body === 'string') {
          JSON.parse(options.body);
        }
      } catch {
        throw new Error('Invalid JSON in request body');
      }
    }
  }

  private buildSecurityHeaders(
    correlationId: string, 
    authToken: string | null, 
    options: RequestInit
  ): SecurityHeaders {
    // CRITICAL FIX: Use lowercase header names to match backend CORS configuration
    // CORS preflight requests are case-sensitive - headers must match exactly
    const headers: SecurityHeaders = {
      'Content-Type': 'application/json',
      'x-client-id': this.config.clientId,
      'X-Correlation-ID': correlationId, // Backend allows both X-Correlation-ID and x-correlation-id
      'x-environment': this.config.environment,
      'x-plugin-version': '1.0.0',
      'x-request-source': 'wordpress-plugin',
      'x-request-timestamp': Date.now().toString(),
      // REMOVED: 'User-Agent' is not in backend's AllowedHeaders list
      // User-Agent is a standard browser header, but CORS requires explicit allow-list
    };

    if (authToken) {
      headers['Authorization'] = `Bearer ${authToken}`;
    }

    // Add idempotency key for POST/PUT/PATCH requests
    if (['POST', 'PUT', 'PATCH'].includes(options.method || 'GET')) {
      headers['x-idempotency-key'] = `${options.method}-${correlationId}`;
    }

    // Add request hash for integrity verification
    if (options.body) {
      headers['x-request-hash'] = this.generateRequestHash(options.body);
    }

    // Add cache-busting header to force fresh preflight requests
    // This prevents browser from using stale preflight cache that might not match current request
    headers['x-request-id'] = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

    return headers;
  }

  private generateRequestHash(body: BodyInit): string {
    // Simple hash for request integrity (in production, use proper crypto)
    const str = typeof body === 'string' ? body : JSON.stringify(body);
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32-bit integer
    }
    return Math.abs(hash).toString(36);
  }

  private async getAuthToken(): Promise<string | null> {
    return this.config.pluginKey || null;
  }

  private checkRateLimit(): boolean {
    if (!this.config.enableRateLimiting) return true;
    
    const now = Date.now();
    if (now > this.rateLimitInfo.resetTime) {
      this.rateLimitInfo.remaining = this.config.maxRequestsPerMinute;
      this.rateLimitInfo.resetTime = now + 60000;
      this.rateLimitInfo.limit = this.config.maxRequestsPerMinute;
    }
    
    if (this.rateLimitInfo.remaining <= 0) {
      this.rateLimitInfo.retryAfter = Math.ceil((this.rateLimitInfo.resetTime - now) / 1000);
      return false;
    }
    
    this.rateLimitInfo.remaining--;
    return true;
  }

  private updateRateLimitInfo(response: Response): void {
    const remaining = response.headers.get('X-RateLimit-Remaining');
    const resetTime = response.headers.get('X-RateLimit-Reset');
    const limit = response.headers.get('X-RateLimit-Limit');
    
    if (remaining) {
      this.rateLimitInfo.remaining = parseInt(remaining, 10);
    }
    
    if (resetTime) {
      this.rateLimitInfo.resetTime = parseInt(resetTime, 10) * 1000;
    }
    
    if (limit) {
      this.rateLimitInfo.limit = parseInt(limit, 10);
    }
  }

  private isRetryableStatusCode(statusCode: number): boolean {
    // Configuration errors (400) are not retriable
    // True service unavailability (503) is retriable
    // Rate limits (429) and timeouts (408) are retriable
    if (statusCode === 400) {
      return false; // Client errors are not retriable
    }
    return statusCode >= 500 || statusCode === 429 || statusCode === 408;
  }

  private isConfigurationError(errorData: any): boolean {
    // Check for configuration-related error codes
    const configErrorCodes = ['CRIIPTO_NOT_CONFIGURED', 'CRIIPTO_SUPPLIER_NOT_CONFIGURED', 'MISSING_PLUGIN_KEY'];
    if (errorData?.errorCode && configErrorCodes.includes(errorData.errorCode)) {
      return true;
    }
    // Also check backend_response.errorCode (from WordPress proxy)
    if (errorData?.backend_response?.errorCode && configErrorCodes.includes(errorData.backend_response.errorCode)) {
      return true;
    }
    return false;
  }

  private getUserFriendlyErrorMessage(statusCode: number, originalMessage?: string): string {
    switch (statusCode) {
      case 400:
        return originalMessage || 'Invalid request. Please check your input and try again.';
      case 401:
        // Check if it's a nonce/authentication issue
        if (originalMessage && (originalMessage.toLowerCase().includes('nonce') || 
            originalMessage.toLowerCase().includes('security check') ||
            originalMessage.toLowerCase().includes('oppdater siden'))) {
          return originalMessage;
        }
        return 'Autentisering mislyktes. Vennligst oppdater siden og prøv igjen.';
      case 403:
        // Check if it's a nonce/authentication issue
        if (originalMessage && (originalMessage.toLowerCase().includes('nonce') || 
            originalMessage.toLowerCase().includes('security check') ||
            originalMessage.toLowerCase().includes('oppdater siden'))) {
          return originalMessage;
        }
        return 'Tilgang nektet. Du har ikke tillatelse til å utføre denne handlingen.';
      case 404:
        return 'Den forespurte ressursen ble ikke funnet.';
      case 429:
        return 'For mange forespørsler. Vennligst vent et øyeblikk og prøv igjen.';
      case 500:
        return 'Serverfeil. Vennligst prøv igjen senere.';
      case 503:
        return 'Tjenesten er midlertidig utilgjengelig. Vennligst prøv igjen senere.';
      default:
        return originalMessage || 'En uventet feil oppstod. Vennligst prøv igjen.';
    }
  }

  private async parseErrorResponse(response: Response): Promise<{ message?: string; errors?: Record<string, string[]> }> {
    try {
      const data = await response.json();
      return data;
    } catch {
      return { message: response.statusText };
    }
  }

  private generateCorrelationId(): string {
    return 'wp-' + Math.random().toString(36).substr(2, 9) + '-' + Date.now();
  }

  private log(level: 'error' | 'warn' | 'info' | 'debug', message: string, data?: any): void {
    if (!this.config.enableLogging) return;
    
    const levels = { error: 0, warn: 1, info: 2, debug: 3 };
    const currentLevel = levels[this.config.logLevel];
    const messageLevel = levels[level];
    
    if (messageLevel <= currentLevel) {
      const logData = {
        timestamp: new Date().toISOString(),
        level,
        message,
        data: data || {},
        environment: this.config.environment,
        clientId: this.config.clientId
      };
      
      if (level === 'error') {
        // Stringify the logData to avoid [object Object] in console
        console.error('[UtilitySign API]', JSON.stringify(logData, null, 2));
      } else if (level === 'warn') {
        console.warn('[UtilitySign API]', JSON.stringify(logData, null, 2));
      } else {
        console.log('[UtilitySign API]', JSON.stringify(logData, null, 2));
      }
    }
  }

  private logError(message: string, error: any, context?: any): void {
    this.log('error', message, {
      error: error instanceof Error ? {
        name: error.name,
        message: error.message,
        stack: error.stack
      } : error,
      context
    });
  }

  private recordMetrics(metrics: RequestMetrics): void {
    this.metrics.push(metrics);
    
    // Keep only recent metrics
    if (this.metrics.length > this.maxMetricsHistory) {
      this.metrics = this.metrics.slice(-this.maxMetricsHistory);
    }
  }

  private cleanupMetrics(): void {
    const cutoff = Date.now() - 3600000; // 1 hour ago
    this.metrics = this.metrics.filter(m => m.timestamp > cutoff);
  }

  private async retryRequest<T>(
    requestFn: () => Promise<APIResponse<T>>,
    attempt: number = 1
  ): Promise<APIResponse<T>> {
    try {
      const result = await requestFn();
      if (result.success) {
        return result;
      }
      
      if (attempt < this.retryAttempts && this.isRetryableError(result.error)) {
        const delay = this.calculateRetryDelay(attempt);
        this.log('info', `Retrying request (attempt ${attempt + 1}/${this.retryAttempts})`, { delay });
        await this.delay(delay);
        return this.retryRequest(requestFn, attempt + 1);
      }
      
      return result;
    } catch (error) {
      // Handle null/undefined errors to prevent unhandled promise rejections
      if (error === null || error === undefined) {
        this.log('error', 'retryRequest received null/undefined error', { attempt });
        return {
          success: false,
          error: 'Request failed due to unexpected error',
          timestamp: new Date().toISOString()
        } as APIResponse<T>;
      }
      
      if (attempt < this.retryAttempts && this.isRetryableError(error)) {
        const delay = this.calculateRetryDelay(attempt);
        this.log('info', `Retrying request after error (attempt ${attempt + 1}/${this.retryAttempts})`, { delay, error: error instanceof Error ? error.message : 'Unknown error' });
        await this.delay(delay);
        return this.retryRequest(requestFn, attempt + 1);
      }
      throw error;
    }
  }

  private isRetryableError(error: any): boolean {
    if (error instanceof APIError) {
      return error.retryable || false;
    }
    
    if (typeof error === 'string') {
      const errorLower = error.toLowerCase();
      
      // Non-retryable configuration errors (fail fast)
      const configurationErrors = [
        'not configured',
        'configuration error',
        'criipto_not_configured',
        'criipto_supplier_not_configured',
        'credentials are missing',
        'contact administrator',
        'configuration_missing',
        'invalid_product_or_supplier'
      ];
      
      if (configurationErrors.some(keyword => errorLower.includes(keyword))) {
        return false;
      }
      
      // Retryable transient errors
      const retryableErrors = [
        'timeout', 'network error', 'connection refused', 'temporary failure',
        'service unavailable', 'bad gateway', 'gateway timeout'
      ];
      return retryableErrors.some(keyword => 
        errorLower.includes(keyword)
      );
    }
    
    return false;
  }

  private calculateRetryDelay(attempt: number): number {
    // Exponential backoff with jitter
    const baseDelay = this.retryDelay * Math.pow(2, attempt - 1);
    const jitter = Math.random() * 0.1 * baseDelay;
    return Math.min(baseDelay + jitter, 30000); // Max 30 seconds
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  private getCacheKey(endpoint: string, options: RequestInit): string {
    const method = options.method || 'GET';
    const body = options.body ? JSON.stringify(options.body) : '';
    return `${method}:${endpoint}:${body}`;
  }

  private getFromCache<T>(key: string): T | null {
    if (!this.config.enableCaching) return null;
    
    const entry = this.cache.get(key);
    if (!entry) return null;
    
    if (Date.now() > entry.expiresAt) {
      this.cache.delete(key);
      return null;
    }
    
    entry.hits++;
    return entry.data;
  }

  private setCache<T>(key: string, data: T): void {
    if (!this.config.enableCaching) return;
    
    this.cache.set(key, {
      data,
      timestamp: Date.now(),
      expiresAt: Date.now() + this.config.cacheTimeout,
      hits: 0
    });
  }

  private clearExpiredCache(): void {
    const now = Date.now();
    let cleared = 0;
    
    for (const [cacheKey, entry] of this.cache.entries()) {
      if (now > entry.expiresAt) {
        this.cache.delete(cacheKey);
        cleared++;
      }
    }
    
    if (cleared > 0) {
      this.log('debug', `Cleared ${cleared} expired cache entries`);
    }
  }

  // Public API methods
  async getDocument(id: string): Promise<APIResponse<Document>> {
    const cacheKey = this.getCacheKey(`/api/v1.0/signing/${id}`, {});
    const cached = this.getFromCache<Document>(cacheKey);
    
    if (cached) {
      this.log('debug', 'Cache hit for getDocument', { id });
      return { success: true, data: cached };
    }

    const result = await this.retryRequest(() =>
      this.get<Document>(`/signing/${encodeURIComponent(id)}`)
    );

    if (result.success && result.data) {
      this.setCache(cacheKey, result.data);
    }

    return result;
  }

  async createSigningRequest(
    documentId: string,
    signerEmail: string,
    signerName: string,
    idempotencyKey?: string,
    additionalFields?: {
      productId?: string;
      supplierId?: string;
      phone?: string;
      dateOfBirth?: string;
      firstName?: string;
      lastName?: string;
      address?: string;
      city?: string;
      zip?: string;
      billingAddress?: string;
      billingCity?: string;
      billingZip?: string;
      takeoverDate?: string;
      meterNumber?: string; // MålepunktID (18 digits, prefix 7070575)
      serialNumber?: string; // Målenummer (variable length meter serial)
      companyName?: string;
      organizationNumber?: string;
      // Phase 3: Sports team and marketing consent fields
      sportsTeam?: string;
      marketingConsentEmail?: boolean;
      marketingConsentSms?: boolean;
    }
  ): Promise<APIResponse<SigningRequest>> {
    const correlationId = `signing-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    // Check if debug mode is enabled
    const debugMode = typeof window !== 'undefined' && 
      new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    
    if (debugMode) {
      console.log('[APIClient] createSigningRequest called', {
        documentId,
        signerEmail,
        signerName,
        idempotencyKey,
        correlationId
      });
    }
    
    // Validate and trim inputs before sending
    const trimmedSignerName = (signerName || '').trim();
    const trimmedSignerEmail = (signerEmail || '').trim();
    
    if (debugMode) {
      console.log('[APIClient] Inputs trimmed', {
        trimmedSignerName,
        trimmedSignerEmail,
        nameLength: trimmedSignerName.length
      });
    }
    
    // Validation
    if (!trimmedSignerName || trimmedSignerName.length < 2) {
      if (debugMode) {
        console.log('[APIClient] Validation failed: name too short or empty');
      }
      return {
        success: false,
        error: 'Signer name is required and must be at least 2 characters',
        correlationId,
        timestamp: new Date().toISOString()
      };
    }
    
    if (!trimmedSignerEmail) {
      if (debugMode) {
        console.log('[APIClient] Validation failed: email empty');
      }
      return {
        success: false,
        error: 'Signer email is required',
        correlationId,
        timestamp: new Date().toISOString()
      };
    }
    
    // Email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(trimmedSignerEmail)) {
      if (debugMode) {
        console.log('[APIClient] Validation failed: invalid email format');
      }
      return {
        success: false,
        error: 'Please enter a valid email address',
        correlationId,
        timestamp: new Date().toISOString()
      };
    }
    
    if (debugMode) {
      console.log('[APIClient] Validation passed, proceeding with API call');
    }
    
    // Generate title and ensure it doesn't exceed 200 characters
    let title = `Signing Request for ${trimmedSignerName}`;
    if (title.length > 200) {
      title = title.substring(0, 197) + '...';
    }
    
    const requestBody: any = {
      documentId: documentId || null, // Empty string becomes null
      title: title,
      description: `Document signing request initiated from WordPress plugin`,
      signerEmail: trimmedSignerEmail,
      signerName: trimmedSignerName,
      correlationId: correlationId,
      environment: this.config.environment,
    };
    
    // Add optional fields if provided
    if (additionalFields) {
      if (additionalFields.productId) {
        requestBody.productId = additionalFields.productId.trim();
      }
      if (additionalFields.supplierId) {
        requestBody.supplierId = additionalFields.supplierId.trim();
      }
      if (additionalFields.phone) {
        requestBody.phone = additionalFields.phone.trim();
      }
      if (additionalFields.dateOfBirth) {
        requestBody.dateOfBirth = additionalFields.dateOfBirth.trim();
      }
      if (additionalFields.firstName) {
        requestBody.firstName = additionalFields.firstName.trim();
      }
      if (additionalFields.lastName) {
        requestBody.lastName = additionalFields.lastName.trim();
      }
      if (additionalFields.address) {
        requestBody.address = additionalFields.address.trim();
      }
      if (additionalFields.city) {
        requestBody.city = additionalFields.city.trim();
      }
      if (additionalFields.zip) {
        requestBody.zip = additionalFields.zip.trim();
      }
      if (additionalFields.billingAddress) {
        requestBody.billingAddress = additionalFields.billingAddress.trim();
      }
      if (additionalFields.billingCity) {
        requestBody.billingCity = additionalFields.billingCity.trim();
      }
      if (additionalFields.billingZip) {
        requestBody.billingZip = additionalFields.billingZip.trim();
      }
      if (additionalFields.takeoverDate) {
        requestBody.takeoverDate = additionalFields.takeoverDate.trim();
      }
      if (additionalFields.meterNumber) {
        requestBody.meterNumber = additionalFields.meterNumber.trim();
      }
      if (additionalFields.serialNumber) {
        requestBody.serialNumber = additionalFields.serialNumber.trim();
      }
      if (additionalFields.companyName) {
        requestBody.companyName = additionalFields.companyName.trim();
      }
      if (additionalFields.organizationNumber) {
        requestBody.organizationNumber = additionalFields.organizationNumber.trim();
      }
      // Phase 3: Sports team and marketing consent fields
      if (additionalFields.sportsTeam) {
        requestBody.sportsTeam = additionalFields.sportsTeam.trim();
      }
      if (additionalFields.marketingConsentEmail !== undefined) {
        requestBody.marketingConsentEmail = additionalFields.marketingConsentEmail;
      }
      if (additionalFields.marketingConsentSms !== undefined) {
        requestBody.marketingConsentSms = additionalFields.marketingConsentSms;
      }
    }
    
    // Log billing address fields specifically for debugging
    const billingFields = {
      hasBillingAddress: !!(requestBody.billingAddress && requestBody.billingAddress.trim()),
      billingAddressValue: requestBody.billingAddress ? requestBody.billingAddress.substring(0, 30) : null,
      hasBillingCity: !!(requestBody.billingCity && requestBody.billingCity.trim()),
      hasBillingZip: !!(requestBody.billingZip && requestBody.billingZip.trim()),
    };
    console.log('[APIClient] Billing address fields in requestBody', billingFields);
    
    if (debugMode) {
      console.log('[APIClient] Request payload prepared', requestBody);
    }
    
    // Use WordPress-specific endpoint that accepts simplified format
    try {
      const result = await this.retryRequest(() =>
        this.post<SigningRequest>('/signing', {
          ...requestBody,
          idempotencyKey: idempotencyKey || correlationId,
        })
      );

      if (debugMode) {
        console.log('[APIClient] createSigningRequest result', {
          success: result.success,
          hasData: !!result.data,
          error: result.error
        });
      }
      
      return result;
    } catch (error) {
      if (debugMode) {
        console.error('[APIClient] createSigningRequest error', error);
      }
      throw error;
    }
  }

  async getSigningStatus(requestId: string): Promise<APIResponse<SigningRequest>> {
    return this.retryRequest(() =>
      this.get<SigningRequest>(`/signing/${encodeURIComponent(requestId)}`)
    );
  }

  async initiateBankID(requestId: string): Promise<APIResponse<{ auth_url: string; session_id: string; correlation_id: string }>> {
    const correlationId = `bankid-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    return this.retryRequest(() =>
      this.post<{ auth_url: string; session_id: string; correlation_id: string }>(`/signing/bankid/initiate`, {
        requestId,
        correlationId,
      })
    );
  }

  async checkBankIDStatus(sessionId: string): Promise<APIResponse<{ 
    status: 'pending' | 'completed' | 'failed' | 'cancelled' | 'expired';
    user_info?: {
      name?: string;
      personal_number?: string;
      bank?: string;
    };
    correlation_id?: string;
    error_message?: string;
  }>> {
    return this.retryRequest(() =>
      this.get<{ 
        status: 'pending' | 'completed' | 'failed' | 'cancelled' | 'expired';
        user_info?: {
          name?: string;
          personal_number?: string;
          bank?: string;
        };
        correlation_id?: string;
        error_message?: string;
      }>(`/signing/bankid/status/${encodeURIComponent(sessionId)}`)
    ).catch((error) => {
      // Ensure we never reject with null - return a failed response instead
      if (error === null || error === undefined) {
        this.log('error', 'checkBankIDStatus received null/undefined error', { sessionId });
        return {
          success: false,
          error: 'BankID status check failed',
          timestamp: new Date().toISOString()
        } as APIResponse<{ 
          status: 'pending' | 'completed' | 'failed' | 'cancelled' | 'expired';
          user_info?: {
            name?: string;
            personal_number?: string;
            bank?: string;
          };
          correlation_id?: string;
          error_message?: string;
        }>;
      }
      // Re-throw if it's a real error
      throw error;
    });
  }

  async cancelBankIDSession(sessionId: string): Promise<APIResponse<{ success: boolean }>> {
    return this.retryRequest(() =>
      this.post<{ success: boolean }>(`/signing/bankid/cancel`, {
        sessionId,
      })
    );
  }

  /**
   * Trigger the completion flow for a signing request.
   * This should be called after the user returns from Criipto signing
   * to ensure the completion email is sent even if webhooks are delayed.
   * 
   * @param requestId - The signing request ID
   * @returns Response indicating if completion was triggered successfully
   */
  async triggerSigningCompletion(requestId: string): Promise<APIResponse<{ message: string; correlationId: string }>> {
    const debugMode = 
      new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    
    if (debugMode) {
      console.log('[APIClient] triggerSigningCompletion called', { requestId });
    }
    
    try {
      const result = await this.retryRequest(() =>
        this.post<{ message: string; correlationId: string }>(`/signing/${encodeURIComponent(requestId)}/complete`, {})
      );
      
      if (debugMode) {
        console.log('[APIClient] triggerSigningCompletion result', {
          success: result.success,
          message: result.data?.message,
          correlationId: result.data?.correlationId,
          error: result.error
        });
      }
      
      return result;
    } catch (error) {
      if (debugMode) {
        console.error('[APIClient] triggerSigningCompletion error', error);
      }
      // Don't throw - return a failed response so the caller can handle it gracefully
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Failed to trigger signing completion',
        timestamp: new Date().toISOString()
      };
    }
  }

  async getPluginConfig(): Promise<APIResponse<UtilitySignConfig>> {
    const cacheKey = this.getCacheKey('/api/v1.0/system-settings', {});
    const cached = this.getFromCache<UtilitySignConfig>(cacheKey);
    
    if (cached) {
      this.log('debug', 'Cache hit for getPluginConfig');
      return { success: true, data: cached };
    }

    const result = await this.retryRequest(() =>
      this.request<UtilitySignConfig>('/api/v1.0/system-settings')
    );

    if (result.success && result.data) {
      this.setCache(cacheKey, result.data);
    }

    return result;
  }

  async healthCheck(): Promise<APIResponse<{ status: string; version: string }>> {
    return this.request<{ status: string; version: string }>('/api/health');
  }

  // Cache management
  clearCache(): void {
    this.cache.clear();
    this.log('info', 'Cache cleared manually');
  }

  getCacheStats(): { size: number; entries: Array<{ key: string; hits: number; age: number }> } {
    const now = Date.now();
    return {
      size: this.cache.size,
      entries: Array.from(this.cache.entries()).map(([key, entry]) => ({
        key,
        hits: entry.hits,
        age: now - entry.timestamp
      }))
    };
  }

  // Rate limiting info
  getRateLimitInfo(): RateLimitInfo {
    return { ...this.rateLimitInfo };
  }

  // Metrics
  getMetrics(): RequestMetrics[] {
    return [...this.metrics];
  }

  getMetricsSummary(): {
    totalRequests: number;
    averageResponseTime: number;
    errorRate: number;
    cacheHitRate: number;
  } {
    const total = this.metrics.length;
    if (total === 0) {
      return { totalRequests: 0, averageResponseTime: 0, errorRate: 0, cacheHitRate: 0 };
    }

    const totalResponseTime = this.metrics.reduce((sum, m) => sum + m.responseTime, 0);
    const errors = this.metrics.filter(m => m.statusCode >= 400).length;
    const cacheHits = this.metrics.filter(m => m.cacheHit).length;

    return {
      totalRequests: total,
      averageResponseTime: Math.round(totalResponseTime / total),
      errorRate: Math.round((errors / total) * 100),
      cacheHitRate: Math.round((cacheHits / total) * 100)
    };
  }

  // Configuration management
  updateConfig(newConfig: Partial<UtilitySignConfig>): void {
    this.config = { ...this.config, ...newConfig };
    this.validateConfig();
    this.log('info', 'Configuration updated', newConfig);
  }

  getConfig(): UtilitySignConfig {
    return { ...this.config };
  }

  // WordPress REST API methods for products
  async get<T>(endpoint: string): Promise<APIResponse<T>> {
    const url = `${this.wordpressApiBase}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': this.wordpressNonce,
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      return {
        success: true,
        data,
        correlationId: this.generateCorrelationId(),
        timestamp: new Date().toISOString()
      };
    } catch (error) {
      this.logError('WordPress API request failed', error, { endpoint: url });
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Unknown error',
        correlationId: this.generateCorrelationId(),
        timestamp: new Date().toISOString()
      };
    }
  }

  async post<T>(endpoint: string, data: any): Promise<APIResponse<T>> {
    const url = `${this.wordpressApiBase}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
    const debugMode = this.config.debugMode || new URLSearchParams(window.location.search).get('utilitysign_debug') === '1';
    
    try {
      if (debugMode) {
        console.log('[APIClient] POST request', { 
          url, 
          data, 
          nonce: this.wordpressNonce ? `present (${this.wordpressNonce.substring(0, 10)}...)` : 'missing',
          nonceLength: this.wordpressNonce ? this.wordpressNonce.length : 0,
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': this.wordpressNonce ? `${this.wordpressNonce.substring(0, 10)}...` : 'missing'
          }
        });
      }
      
      // Validate nonce before sending
      if (!this.wordpressNonce || this.wordpressNonce.trim() === '') {
        // Try to get nonce from window object as fallback
        const windowConfig = (window as any).utilitySignFrontend || (window as any).utilitySign || {};
        const fallbackNonce = windowConfig.restNonce || '';
        
        if (fallbackNonce && fallbackNonce.trim() !== '') {
          if (debugMode) {
            console.warn('[APIClient] Using fallback nonce from window object');
          }
          this.wordpressNonce = fallbackNonce;
        } else {
          const errorMsg = 'WordPress REST API nonce is missing. Please refresh the page to get a new security token.';
          if (debugMode) {
            console.error('[APIClient] Nonce validation failed:', errorMsg, {
              hasWordpressNonce: !!this.wordpressNonce,
              hasWindowUtilitySign: !!(window as any).utilitySign,
              hasWindowUtilitySignFrontend: !!(window as any).utilitySignFrontend,
              windowUtilitySignKeys: (window as any).utilitySign ? Object.keys((window as any).utilitySign) : [],
              windowUtilitySignFrontendKeys: (window as any).utilitySignFrontend ? Object.keys((window as any).utilitySignFrontend) : []
            });
          }
          throw new Error(errorMsg);
        }
      }
      
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': this.wordpressNonce,
        },
        credentials: 'same-origin',
        body: JSON.stringify(data),
      });

      if (debugMode) {
        console.log('[APIClient] POST response', { 
          status: response.status, 
          statusText: response.statusText, 
          ok: response.ok,
          headers: Object.fromEntries(response.headers.entries())
        });
      }

      if (!response.ok) {
        // Read error response body
        let errorMessage = `HTTP error! status: ${response.status}`;
        let errorData: any = null;
        
        try {
          const responseText = await response.text();
          if (debugMode) {
            console.log('[APIClient] Error response body', responseText);
          }
          
          if (responseText) {
            try {
              errorData = JSON.parse(responseText);
              // WordPress REST API error format: { code: 'error_code', message: 'Error message', data: { status: 400 } }
              if (errorData.message) {
                errorMessage = errorData.message;
              } else if (errorData.error) {
                errorMessage = errorData.error;
              }
              
              // Check for nonce-related errors (401/403 from WordPress REST API)
              if ((response.status === 401 || response.status === 403) && 
                  (errorData.code === 'rest_cookie_invalid_nonce' || 
                   errorData.code === 'invalid_nonce' ||
                   errorMessage.toLowerCase().includes('nonce') ||
                   errorMessage.toLowerCase().includes('security check'))) {
                errorMessage = 'Sikkerhetskontroll mislyktes. Vennligst oppdater siden og prøv igjen.';
              }
            } catch (e) {
              // Not JSON, use raw text
              errorMessage = responseText.substring(0, 200);
            }
          }
        } catch (e) {
          if (debugMode) {
            console.error('[APIClient] Failed to read error response', e);
          }
        }
        
        // For 401/403 errors, provide more helpful message
        if ((response.status === 401 || response.status === 403) && 
            !errorMessage.includes('oppdater siden')) {
          errorMessage = 'Autentisering mislyktes. Vennligst oppdater siden og prøv igjen.';
        }
        
        throw new Error(errorMessage);
      }

      const responseData = await response.json();
      return {
        success: true,
        data: responseData,
        correlationId: this.generateCorrelationId(),
        timestamp: new Date().toISOString()
      };
    } catch (error) {
      this.logError('WordPress API request failed', error, { endpoint: url, data });
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Unknown error',
        correlationId: this.generateCorrelationId(),
        timestamp: new Date().toISOString()
      };
    }
  }

  // Cleanup
  destroy(): void {
    this.cache.clear();
    this.metrics = [];
    this.log('info', 'API Client destroyed');
  }
}

// Export class for APIClientProvider usage
export { APIClient };

// Fallback: Lazy initialization for non-React usage
let apiClientSingleton: APIClient | null = null;

export function getAPIClientSingleton(): APIClient {
  if (!apiClientSingleton) {
    const frontendConfig = typeof window !== 'undefined' ? (window as any).utilitySignFrontend : null;
    const adminConfig = typeof window !== 'undefined' ? (window as any).utilitySign : null;
    if (!frontendConfig && !adminConfig) {
      throw new Error('UtilitySign configuration not loaded yet. Ensure window.utilitySignFrontend or window.utilitySign is available.');
    }
    apiClientSingleton = new APIClient(APIClient.getEnvironmentConfig());
  }
  return apiClientSingleton;
}

// Default export: Lazy getter that creates instance on first access
// This prevents immediate initialization errors when config isn't available yet
const apiClient = new Proxy({} as APIClient, {
  get(target, prop) {
    const instance = getAPIClientSingleton();
    return (instance as any)[prop];
  }
});

export default apiClient;

// Global type declaration for WordPress configuration
declare global {
  interface Window {
    utilitySignFrontend?: {
      pluginKey?: string;
      pluginKeyStatus?: 'valid' | 'missing' | 'invalid';
      backendApiUrl?: string;
      apiUrl?: string;
      restNonce?: string;
      clientId?: string;
      debugMode?: boolean;
    };
  }
}