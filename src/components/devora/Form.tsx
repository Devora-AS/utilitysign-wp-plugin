import React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const formVariants = cva(
  "devora-form",
  {
    variants: {
      variant: {
        default: "devora-form-default",
        card: "devora-form-card",
        inline: "devora-form-inline",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
);

export interface FormProps
  extends React.FormHTMLAttributes<HTMLFormElement>,
    VariantProps<typeof formVariants> {
  children: React.ReactNode;
}

const Form = React.forwardRef<HTMLFormElement, FormProps>(
  ({ className, variant, children, ...props }, ref) => {
    return (
      <form
        className={cn(formVariants({ variant, className }))}
        ref={ref}
        {...props}
      >
        {children}
      </form>
    );
  }
);
Form.displayName = "Form";

// Form Field Component
export interface FormFieldProps {
  children: React.ReactNode;
  className?: string;
}

const FormField = React.forwardRef<HTMLDivElement, FormFieldProps>(
  ({ className, children, ...props }, ref) => {
    return (
      <div
        className={cn("devora-form-field", className)}
        ref={ref}
        {...props}
      >
        {children}
      </div>
    );
  }
);
FormField.displayName = "FormField";

// Form Label Component
export interface FormLabelProps
  extends React.LabelHTMLAttributes<HTMLLabelElement> {
  required?: boolean;
}

const FormLabel = React.forwardRef<HTMLLabelElement, FormLabelProps>(
  ({ className, required, children, ...props }, ref) => {
    return (
      <label
        className={cn("devora-form-label", className)}
        ref={ref}
        {...props}
      >
        {children}
        {required && <span className="text-red-500 ml-1">*</span>}
      </label>
    );
  }
);
FormLabel.displayName = "FormLabel";

// Form Description Component
export interface FormDescriptionProps
  extends React.HTMLAttributes<HTMLParagraphElement> {}

const FormDescription = React.forwardRef<HTMLParagraphElement, FormDescriptionProps>(
  ({ className, ...props }, ref) => {
    return (
      <p
        className={cn("devora-form-description", className)}
        ref={ref}
        {...props}
      />
    );
  }
);
FormDescription.displayName = "FormDescription";

// Form Error Component
export interface FormErrorProps
  extends React.HTMLAttributes<HTMLParagraphElement> {}

const FormError = React.forwardRef<HTMLParagraphElement, FormErrorProps>(
  ({ className, ...props }, ref) => {
    return (
      <p
        className={cn("devora-form-error", className)}
        ref={ref}
        {...props}
      />
    );
  }
);
FormError.displayName = "FormError";

export { 
  Form, 
  FormField, 
  FormLabel, 
  FormDescription, 
  FormError,
  formVariants 
};