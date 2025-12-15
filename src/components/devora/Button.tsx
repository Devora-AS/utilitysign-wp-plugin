import React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '../../lib/utils';
import { useComponentSettings } from '../ComponentSettingsProvider';

const buttonVariants = cva(
  "devora-button",
  {
    variants: {
      variant: {
        primary: "devora-button-primary",
        secondary: "devora-button-secondary", 
        accent: "devora-button-accent",
        ghost: "devora-button-ghost",
        destructive: "bg-red-500 text-white hover:bg-red-500/90",
        outline: "border border-devora-primary-light hover:bg-devora-primary-light/10 hover:text-devora-primary",
      },
      size: {
        default: "h-10 py-2 px-4",
        sm: "h-9 px-3 rounded-devora-button",
        lg: "h-11 px-8 rounded-devora-button",
        icon: "h-10 w-10",
      },
    },
    defaultVariants: {
      variant: "primary",
      size: "default",
    },
  }
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    // Get button style from component settings
    let buttonStyleClass = '';
    try {
      const { settings } = useComponentSettings();
      if (settings.buttonStyle === 'modern') {
        buttonStyleClass = 'devora-button-style-modern';
      } else if (settings.buttonStyle === 'minimal') {
        buttonStyleClass = 'devora-button-style-minimal';
      }
      // 'devora' style uses default classes, no additional class needed
    } catch {
      // ComponentSettingsProvider not available, use default
    }

    return (
      <button
        className={cn(buttonVariants({ variant, size }), buttonStyleClass, className)}
        ref={ref}
        {...props}
      />
    );
  }
);
Button.displayName = "Button";

export { Button, buttonVariants };
