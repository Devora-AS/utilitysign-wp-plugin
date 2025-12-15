import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../devora/Card';
import { Button } from '../devora/Button';
import { 
  CheckCircle, 
  Clock, 
  XCircle, 
  AlertCircle, 
  RefreshCw,
  ExternalLink,
  Download,
  Mail
} from 'lucide-react';
import { cn } from '../../lib/utils';
import { SigningRequest, APIResponse } from '../../lib/api-client';
import { useAPIClient } from '../APIClientProvider';

export interface SigningStatusProps {
  requestId: string;
  onStatusChange?: (status: SigningRequest['status']) => void;
  onError?: (error: string) => void;
  className?: string;
  autoRefresh?: boolean;
  refreshInterval?: number; // in milliseconds
}

const SigningStatus: React.FC<SigningStatusProps> = ({
  requestId,
  onStatusChange,
  onError,
  className,
  autoRefresh = true,
  refreshInterval = 5000, // 5 seconds
}) => {
  const apiClient = useAPIClient();
  const [request, setRequest] = useState<SigningRequest | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

  const fetchStatus = async () => {
    try {
      setIsLoading(true);
      const response: APIResponse<SigningRequest> = await apiClient.getSigningStatus(requestId);
      
      if (!response.success || !response.data) {
        throw new Error(response.error || 'Failed to fetch signing status');
      }

      setRequest(response.data);
      setError(null);
      setLastUpdated(new Date());
      onStatusChange?.(response.data.status);
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'An error occurred';
      setError(errorMessage);
      onError?.(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchStatus();
  }, [requestId]);

  useEffect(() => {
    if (!autoRefresh || !request || request.status === 'completed' || request.status === 'expired') {
      return;
    }

    const interval = setInterval(fetchStatus, refreshInterval);
    return () => clearInterval(interval);
  }, [autoRefresh, request, refreshInterval]);

  const getStatusIcon = (status: SigningRequest['status']) => {
    switch (status) {
      case 'completed':
        return <CheckCircle className="h-5 w-5 text-green-600" />;
      case 'in_progress':
        return <RefreshCw className="h-5 w-5 text-blue-600 animate-spin" />;
      case 'pending':
        return <Clock className="h-5 w-5 text-yellow-600" />;
      case 'expired':
        return <XCircle className="h-5 w-5 text-red-600" />;
      default:
        return <AlertCircle className="h-5 w-5 text-gray-600" />;
    }
  };

  const getStatusColor = (status: SigningRequest['status']) => {
    switch (status) {
      case 'completed':
        return 'text-green-600 bg-green-50 border-green-200';
      case 'in_progress':
        return 'text-blue-600 bg-blue-50 border-blue-200';
      case 'pending':
        return 'text-yellow-600 bg-yellow-50 border-yellow-200';
      case 'expired':
        return 'text-red-600 bg-red-50 border-red-200';
      default:
        return 'text-gray-600 bg-gray-50 border-gray-200';
    }
  };

  const getStatusMessage = (status: SigningRequest['status']) => {
    switch (status) {
      case 'completed':
        return 'Document has been successfully signed';
      case 'in_progress':
        return 'Document is being processed';
      case 'pending':
        return 'Waiting for signature';
      case 'expired':
        return 'Signing request has expired';
      default:
        return 'Unknown status';
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString();
  };

  const getTimeRemaining = (expiresAt: string) => {
    const now = new Date();
    const expires = new Date(expiresAt);
    const diff = expires.getTime() - now.getTime();
    
    if (diff <= 0) {
      return 'Expired';
    }

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (hours > 0) {
      return `${hours}h ${minutes}m remaining`;
    } else {
      return `${minutes}m remaining`;
    }
  };

  if (isLoading && !request) {
    return (
      <Card variant="white" className={cn("w-full", className)}>
        <CardContent className="flex items-center justify-center py-8">
          <RefreshCw className="h-6 w-6 animate-spin text-devora-primary mr-2" />
          <span className="text-devora-text-secondary">Loading status...</span>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card variant="white" className={cn("w-full", className)}>
        <CardContent>
          <div className="flex items-center gap-2 text-red-600">
            <AlertCircle className="h-5 w-5" />
            <span className="font-medium">Error loading status</span>
          </div>
          <p className="text-sm text-devora-text-secondary mt-1">{error}</p>
          <Button
            variant="outline"
            size="sm"
            onClick={fetchStatus}
            className="mt-3 devora-button-outline"
          >
            <RefreshCw className="h-4 w-4 mr-2" />
            Retry
          </Button>
        </CardContent>
      </Card>
    );
  }

  if (!request) {
    return null;
  }

  return (
    <Card variant="white" className={cn("w-full", className)}>
      <CardHeader>
        <CardTitle className="flex items-center justify-between">
          <span className="flex items-center gap-2 text-devora-primary">
            <CheckCircle className="h-5 w-5" />
            Signing Status
          </span>
          <Button
            variant="ghost"
            size="sm"
            onClick={fetchStatus}
            disabled={isLoading}
            className="h-8 w-8 p-0"
          >
            <RefreshCw className={cn("h-4 w-4", isLoading && "animate-spin")} />
          </Button>
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Status Badge */}
        <div className={cn(
          "inline-flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium",
          getStatusColor(request.status)
        )}>
          {getStatusIcon(request.status)}
          {getStatusMessage(request.status)}
        </div>

        {/* Request Details */}
        <div className="space-y-3">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <span className="text-devora-text-secondary">Request ID:</span>
              <p className="font-mono text-devora-primary-dark">{request.id}</p>
            </div>
            <div>
              <span className="text-devora-text-secondary">Document ID:</span>
              <p className="font-mono text-devora-primary-dark">{request.document_id}</p>
            </div>
            <div>
              <span className="text-devora-text-secondary">Signer:</span>
              <p className="font-medium text-devora-primary-dark">{request.signer_name}</p>
            </div>
            <div>
              <span className="text-devora-text-secondary">Email:</span>
              <p className="font-medium text-devora-primary-dark">{request.signer_email}</p>
            </div>
            <div>
              <span className="text-devora-text-secondary">Created:</span>
              <p className="text-devora-primary-dark">{formatDate(request.created_at)}</p>
            </div>
            <div>
              <span className="text-devora-text-secondary">Expires:</span>
              <p className="text-devora-primary-dark">
                {formatDate(request.expires_at)}
                {request.status === 'pending' && (
                  <span className="ml-2 text-xs text-devora-text-secondary">
                    ({getTimeRemaining(request.expires_at)})
                  </span>
                )}
              </p>
            </div>
            {request.completed_at && (
              <div>
                <span className="text-devora-text-secondary">Completed:</span>
                <p className="text-devora-primary-dark">{formatDate(request.completed_at)}</p>
              </div>
            )}
            {request.bankid_session_id && (
              <div>
                <span className="text-devora-text-secondary">BankID Session:</span>
                <p className="font-mono text-xs text-devora-primary-dark">
                  {request.bankid_session_id}
                </p>
              </div>
            )}
          </div>
        </div>

        {/* Actions */}
        {request.status === 'completed' && (
          <div className="pt-4 border-t border-devora-primary-light">
            <div className="flex flex-wrap gap-2">
              <Button
                variant="primary"
                size="sm"
                className="devora-button-primary"
              >
                <Download className="h-4 w-4 mr-2" />
                Download Signed Document
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="devora-button-outline"
              >
                <Mail className="h-4 w-4 mr-2" />
                Send Email Copy
              </Button>
            </div>
          </div>
        )}

        {request.status === 'pending' && (
          <div className="pt-4 border-t border-devora-primary-light">
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <Clock className="h-5 w-5 text-yellow-600 mt-0.5" />
                <div>
                  <h4 className="font-medium text-yellow-800">
                    Waiting for Signature
                  </h4>
                  <p className="text-sm text-yellow-700 mt-1">
                    The signing request is pending. Please complete the signing process 
                    to proceed. You will receive email notifications about the status.
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}

        {request.status === 'expired' && (
          <div className="pt-4 border-t border-devora-primary-light">
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <XCircle className="h-5 w-5 text-red-600 mt-0.5" />
                <div>
                  <h4 className="font-medium text-red-800">
                    Signing Request Expired
                  </h4>
                  <p className="text-sm text-red-700 mt-1">
                    This signing request has expired. Please contact the document owner 
                    to create a new signing request.
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Last Updated */}
        {lastUpdated && (
          <div className="pt-2 text-xs text-devora-text-secondary text-center">
            Last updated: {lastUpdated.toLocaleTimeString()}
          </div>
        )}
      </CardContent>
    </Card>
  );
};

export default SigningStatus;
