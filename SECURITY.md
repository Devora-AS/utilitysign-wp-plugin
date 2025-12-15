# UtilitySign WordPress Plugin - Security Features

## Overview

The UtilitySign WordPress plugin implements comprehensive security features to ensure secure document signing workflows with BankID integration. This document outlines all security measures, configurations, and best practices.

## Security Architecture

### 1. TypeScript API Client Security

The frontend API client (`src/lib/api-client.ts`) implements:

- **Microsoft Entra ID JWT Authentication**: Secure authentication using Microsoft's identity platform
- **Environment-based Configuration**: Automatic detection of staging vs production environments
- **Comprehensive Error Handling**: User-friendly error messages with correlation IDs
- **Retry Logic**: Exponential backoff for failed requests
- **Rate Limiting**: Client-side request throttling
- **Caching**: Intelligent caching with TTL and invalidation
- **HTTPS Enforcement**: Mandatory HTTPS for all API communications
- **Data Validation**: Input sanitization and validation
- **Request Correlation**: Unique correlation IDs for request tracking

### 2. WordPress PHP Backend Security

The backend implements multiple security layers:

#### SecurityService
- **HTTPS Enforcement**: Forces HTTPS for all requests
- **Security Headers**: Comprehensive security headers (CSP, X-Frame-Options, etc.)
- **Rate Limiting**: Server-side rate limiting per IP address
- **CSRF Protection**: Cross-Site Request Forgery protection
- **XSS Protection**: Cross-Site Scripting prevention
- **SQL Injection Protection**: Database query sanitization
- **File Upload Validation**: Secure file upload handling
- **IP Whitelisting**: Optional IP address restrictions
- **Audit Logging**: Comprehensive security event logging

#### ApiAuthenticationService
- **Multiple Authentication Methods**: API Key, JWT, Microsoft Entra ID
- **Token Validation**: Secure token verification and refresh
- **Session Management**: Secure session handling
- **Failed Attempt Tracking**: Brute force protection
- **Audit Logging**: Authentication event logging

#### CacheService
- **Intelligent Caching**: Multi-level caching strategy
- **TTL Management**: Time-to-live for cached data
- **Cache Invalidation**: Smart cache clearing
- **Compression**: Data compression for storage efficiency
- **Statistics**: Cache performance monitoring

#### ErrorHandlingService
- **Comprehensive Error Handling**: PHP errors, exceptions, and fatal errors
- **User-friendly Messages**: Safe error messages for users
- **Correlation IDs**: Error tracking and debugging
- **Email Notifications**: Critical error alerts
- **Log Retention**: Configurable log retention policies

#### MultisiteService
- **WordPress Multisite Support**: Full multisite compatibility
- **Data Isolation**: Site-specific data separation
- **Network Administration**: Centralized network management
- **Cross-site Requests**: Secure inter-site communication
- **Site Switching**: Context-aware site switching

## Configuration

### Security Settings

Access security settings via **WordPress Admin > UtilitySign > Security**

#### General Security
- **HTTPS Enforcement**: Force HTTPS for all requests
- **Rate Limiting**: Configure request limits per minute
- **CSRF Protection**: Enable/disable CSRF protection
- **XSS Protection**: Enable/disable XSS protection
- **SQL Injection Protection**: Enable/disable SQL injection protection
- **File Upload Validation**: Configure file upload restrictions
- **IP Whitelist**: Restrict access to specific IP addresses

#### Authentication
- **Authentication Method**: Choose between API Key, JWT, or Microsoft Entra ID
- **API Key**: Configure API key authentication
- **Microsoft Entra ID**: Configure tenant ID, client ID, and client secret
- **JWT Secret**: Configure JWT signing secret
- **Token Cache Duration**: Set token caching time
- **Failed Attempt Limits**: Configure brute force protection

#### Caching
- **Enable Caching**: Toggle caching functionality
- **Default TTL**: Set default cache time-to-live
- **Max Cache Size**: Configure maximum cache size
- **Compression**: Enable/disable data compression
- **Statistics**: Enable/disable cache statistics

#### Error Handling
- **Enable Error Handling**: Toggle error handling
- **Logging Level**: Set minimum log level
- **User Feedback**: Enable/disable user-friendly error messages
- **Debug Mode**: Enable/disable debug mode
- **Email Notifications**: Configure error email alerts
- **Log Retention**: Set log retention period

## Database Tables

The plugin creates the following security-related database tables:

### utilitysign_security_log
Stores security events and audit logs
- `id`: Primary key
- `timestamp`: Event timestamp
- `event_type`: Type of security event
- `data`: Event data (JSON)
- `site_id`: WordPress site ID
- `ip_address`: Client IP address
- `user_agent`: Client user agent

### utilitysign_auth_log
Stores authentication events
- `id`: Primary key
- `timestamp`: Event timestamp
- `event`: Authentication event type
- `method`: Authentication method used
- `reason`: Failure reason (if applicable)
- `data`: Event data (JSON)
- `site_id`: WordPress site ID
- `ip_address`: Client IP address

### utilitysign_cache
Stores cached data
- `id`: Primary key
- `cache_key`: Unique cache key
- `data`: Cached data
- `expires_at`: Cache expiration time
- `site_id`: WordPress site ID

### utilitysign_error_log
Stores error logs
- `id`: Primary key
- `type`: Error type
- `message`: Error message
- `file`: File where error occurred
- `line`: Line number where error occurred
- `severity`: Error severity level
- `correlation_id`: Unique correlation ID
- `stack_trace`: Stack trace (if enabled)
- `site_id`: WordPress site ID

## API Endpoints

### Authentication Endpoints
- `POST /wp-json/utilitysign/v1/auth/login` - User login
- `POST /wp-json/utilitysign/v1/auth/logout` - User logout
- `POST /wp-json/utilitysign/v1/auth/refresh` - Token refresh

### Document Endpoints
- `GET /wp-json/utilitysign/v1/documents/{id}` - Get document
- `POST /wp-json/utilitysign/v1/documents` - Create document
- `PUT /wp-json/utilitysign/v1/documents/{id}` - Update document
- `DELETE /wp-json/utilitysign/v1/documents/{id}` - Delete document

### Signing Endpoints
- `POST /wp-json/utilitysign/v1/signing/request` - Create signing request
- `GET /wp-json/utilitysign/v1/signing/{id}` - Get signing status
- `POST /wp-json/utilitysign/v1/signing/{id}/initiate` - Initiate BankID
- `GET /wp-json/utilitysign/v1/signing/{id}/status` - Check BankID status
- `POST /wp-json/utilitysign/v1/signing/{id}/cancel` - Cancel BankID

### System Endpoints
- `GET /wp-json/utilitysign/v1/system/health` - Health check
- `GET /wp-json/utilitysign/v1/system/config` - Get configuration
- `POST /wp-json/utilitysign/v1/system/config` - Update configuration

## Security Best Practices

### 1. Environment Configuration
- Use different API keys for staging and production
- Enable HTTPS in production
- Configure proper CORS settings
- Use secure cookie settings

### 2. Authentication
- Use strong API keys (32+ characters)
- Enable token validation
- Configure appropriate token expiration
- Monitor failed authentication attempts

### 3. Rate Limiting
- Set appropriate rate limits based on usage
- Monitor rate limit violations
- Implement progressive penalties for violations

### 4. Logging
- Enable comprehensive logging
- Monitor security events regularly
- Set up alerts for critical events
- Implement log rotation

### 5. File Uploads
- Restrict file types to necessary formats
- Set appropriate file size limits
- Scan uploaded files for malware
- Store files outside web root when possible

### 6. Database Security
- Use prepared statements for all queries
- Implement proper input validation
- Regular security updates
- Monitor database access

## Monitoring and Alerting

### Security Events
The plugin logs the following security events:
- Authentication attempts (success/failure)
- Rate limit violations
- CSRF token failures
- XSS attempts
- SQL injection attempts
- File upload violations
- IP whitelist violations

### Error Monitoring
- PHP errors and exceptions
- Fatal errors
- API errors
- Database errors
- Cache errors

### Performance Monitoring
- Cache hit/miss ratios
- API response times
- Memory usage
- Database query performance

## Troubleshooting

### Common Issues

#### 1. Authentication Failures
- Check API key configuration
- Verify Microsoft Entra ID settings
- Check token expiration
- Review authentication logs

#### 2. Rate Limiting Issues
- Adjust rate limit settings
- Check for legitimate high traffic
- Review IP whitelist settings
- Monitor rate limit logs

#### 3. Cache Issues
- Clear cache manually
- Check cache configuration
- Review cache statistics
- Verify cache permissions

#### 4. Error Handling Issues
- Check error log configuration
- Verify email notification settings
- Review error message templates
- Check correlation ID generation

### Debug Mode
Enable debug mode in error handling settings to get detailed error information. This should only be enabled in development environments.

### Log Analysis
Use the security statistics dashboard to analyze:
- Security event trends
- Authentication patterns
- Error frequencies
- Performance metrics

## Compliance

### GDPR Compliance
- Data encryption in transit and at rest
- User consent management
- Data retention policies
- Right to be forgotten implementation

### Security Standards
- OWASP Top 10 compliance
- WordPress security best practices
- Industry-standard encryption
- Regular security audits

## Support

For security-related issues or questions:
1. Check the security logs
2. Review the configuration settings
3. Run the deployment verification script
4. Contact the development team

## Changelog

### Version 1.0.0
- Initial security implementation
- Microsoft Entra ID integration
- Comprehensive error handling
- WordPress multisite support
- Advanced caching system
- Rate limiting and throttling
- Security monitoring and alerting
