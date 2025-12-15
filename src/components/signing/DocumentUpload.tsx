import React, { useState, useRef, useCallback } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../devora/Card';
import { Button } from '../devora/Button';
import { Upload, FileText, X, AlertCircle, CheckCircle } from 'lucide-react';
import { cn } from '../../lib/utils';

export interface DocumentUploadProps {
  onFileSelect: (file: File) => void;
  onError: (error: string) => void;
  maxSize?: number; // in MB
  acceptedTypes?: string[];
  className?: string;
  disabled?: boolean;
}

export interface UploadedFile {
  file: File;
  id: string;
  status: 'uploading' | 'success' | 'error';
  error?: string;
}

const DocumentUpload: React.FC<DocumentUploadProps> = ({
  onFileSelect,
  onError,
  maxSize = 10, // 10MB default
  acceptedTypes = ['.pdf', '.doc', '.docx'],
  className,
  disabled = false,
}) => {
  const [isDragOver, setIsDragOver] = useState(false);
  const [uploadedFiles, setUploadedFiles] = useState<UploadedFile[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const validateFile = useCallback((file: File): string | null => {
    // Check file size
    if (file.size > maxSize * 1024 * 1024) {
      return `File size must be less than ${maxSize}MB`;
    }

    // Check file type
    const fileExtension = '.' + file.name.split('.').pop()?.toLowerCase();
    if (!acceptedTypes.includes(fileExtension)) {
      return `File type must be one of: ${acceptedTypes.join(', ')}`;
    }

    return null;
  }, [maxSize, acceptedTypes]);

  const handleFileSelect = useCallback((file: File) => {
    const validationError = validateFile(file);
    if (validationError) {
      onError(validationError);
      return;
    }

    const fileId = Math.random().toString(36).substr(2, 9);
    const newFile: UploadedFile = {
      file,
      id: fileId,
      status: 'uploading',
    };

    setUploadedFiles(prev => [...prev, newFile]);
    
    // Simulate upload process
    setTimeout(() => {
      setUploadedFiles(prev => 
        prev.map(f => 
          f.id === fileId 
            ? { ...f, status: 'success' }
            : f
        )
      );
      onFileSelect(file);
    }, 1000);
  }, [validateFile, onFileSelect]);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    if (!disabled) {
      setIsDragOver(true);
    }
  }, [disabled]);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
  }, []);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    
    if (disabled) return;

    const files = Array.from(e.dataTransfer.files);
    if (files.length > 0) {
      handleFileSelect(files[0]);
    }
  }, [disabled, handleFileSelect]);

  const handleFileInputChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      handleFileSelect(files[0]);
    }
  }, [handleFileSelect]);

  const handleRemoveFile = useCallback((fileId: string) => {
    setUploadedFiles(prev => prev.filter(f => f.id !== fileId));
  }, []);

  const handleClick = useCallback(() => {
    if (!disabled && fileInputRef.current) {
      fileInputRef.current.click();
    }
  }, [disabled]);

  return (
    <Card variant="white" className={cn("w-full", className)}>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-devora-primary">
          <FileText className="h-5 w-5" />
          Upload Document
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Upload Area */}
        <div
          className={cn(
            "border-2 border-dashed rounded-lg p-8 text-center transition-colors",
            isDragOver && !disabled
              ? "border-devora-primary bg-devora-primary-light/10"
              : "border-devora-primary-light hover:border-devora-primary",
            disabled && "opacity-50 cursor-not-allowed"
          )}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          onClick={handleClick}
        >
          <Upload className="h-12 w-12 mx-auto mb-4 text-devora-primary-light" />
          <h3 className="text-lg font-semibold text-devora-primary-dark mb-2">
            {isDragOver ? 'Drop your document here' : 'Drag & drop your document here'}
          </h3>
          <p className="text-devora-text-secondary mb-4">
            or click to browse files
          </p>
          <Button
            variant="outline"
            size="sm"
            disabled={disabled}
            className="devora-button-outline"
          >
            Choose File
          </Button>
          <p className="text-sm text-devora-text-secondary mt-2">
            Accepted formats: {acceptedTypes.join(', ')} (max {maxSize}MB)
          </p>
        </div>

        {/* Hidden file input */}
        <input
          ref={fileInputRef}
          type="file"
          accept={acceptedTypes.join(',')}
          onChange={handleFileInputChange}
          className="hidden"
          disabled={disabled}
        />

        {/* Uploaded Files List */}
        {uploadedFiles.length > 0 && (
          <div className="space-y-2">
            <h4 className="text-sm font-medium text-devora-primary-dark">
              Uploaded Files
            </h4>
            {uploadedFiles.map((uploadedFile) => (
              <div
                key={uploadedFile.id}
                className="flex items-center justify-between p-3 bg-devora-background-light rounded-lg"
              >
                <div className="flex items-center gap-3">
                  <FileText className="h-4 w-4 text-devora-primary" />
                  <div>
                    <p className="text-sm font-medium text-devora-primary-dark">
                      {uploadedFile.file.name}
                    </p>
                    <p className="text-xs text-devora-text-secondary">
                      {(uploadedFile.file.size / 1024 / 1024).toFixed(2)} MB
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  {uploadedFile.status === 'uploading' && (
                    <div className="animate-spin h-4 w-4 border-2 border-devora-primary border-t-transparent rounded-full" />
                  )}
                  {uploadedFile.status === 'success' && (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  )}
                  {uploadedFile.status === 'error' && (
                    <AlertCircle className="h-4 w-4 text-red-500" />
                  )}
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleRemoveFile(uploadedFile.id)}
                    className="h-8 w-8 p-0 text-devora-text-secondary hover:text-devora-primary"
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
};

export default DocumentUpload;
