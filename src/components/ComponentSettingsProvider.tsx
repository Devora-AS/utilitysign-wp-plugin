import React, { createContext, useContext, useEffect, useState, useMemo } from 'react';

export interface ComponentSettings {
  theme: 'light' | 'dark' | 'auto';
  primaryColor: string;
  secondaryColor: string;
  accentColor: string;
  borderRadius: 'none' | 'sm' | 'md' | 'lg' | 'xl' | 'devora';
  fontFamily: 'lato' | 'open-sans' | 'inter' | 'system';
  fontSize: 'sm' | 'base' | 'lg' | 'xl';
  buttonStyle: 'devora' | 'modern' | 'minimal';
  cardStyle: 'devora' | 'modern' | 'minimal';
  enableAnimations: boolean;
  enableShadows: boolean;
  enableGradients: boolean;
  customCSS: string;
  logoUrl: string;
  faviconUrl: string;
  enableCustomBranding: boolean;
}

const defaultSettings: ComponentSettings = {
  theme: 'light',
  primaryColor: '#3432A6',
  secondaryColor: '#968AB6',
  accentColor: '#FFFADE',
  borderRadius: 'devora',
  fontFamily: 'lato',
  fontSize: 'base',
  buttonStyle: 'devora',
  cardStyle: 'devora',
  enableAnimations: true,
  enableShadows: true,
  enableGradients: true,
  customCSS: '',
  logoUrl: '',
  faviconUrl: '',
  enableCustomBranding: false,
};

interface ComponentSettingsContextValue {
  settings: ComponentSettings;
  updateSettings: (newSettings: Partial<ComponentSettings>) => void;
}

const ComponentSettingsContext = createContext<ComponentSettingsContextValue>({
  settings: defaultSettings,
  updateSettings: () => {},
});

export const useComponentSettings = () => {
  const context = useContext(ComponentSettingsContext);
  if (!context) {
    throw new Error('useComponentSettings must be used within ComponentSettingsProvider');
  }
  return context;
};

interface ComponentSettingsProviderProps {
  children: React.ReactNode;
}

export const ComponentSettingsProvider: React.FC<ComponentSettingsProviderProps> = ({ children }) => {
  const [settings, setSettings] = useState<ComponentSettings>(defaultSettings);
  const [styleElement, setStyleElement] = useState<HTMLStyleElement | null>(null);
  const [customStyleElement, setCustomStyleElement] = useState<HTMLStyleElement | null>(null);

  // Load settings from window.utilitySignFrontend or window.utilitySign
  useEffect(() => {
    const frontendConfig = typeof window !== 'undefined' ? (window as any).utilitySignFrontend : null;
    const adminConfig = typeof window !== 'undefined' ? (window as any).utilitySign : null;
    const config = frontendConfig || adminConfig;

    // CRITICAL FIX: Always load settings, even if components object is empty or missing
    // This ensures defaults are used when settings haven't been saved yet
    const components = config?.components || {};
    
    const loadedSettings: ComponentSettings = {
      theme: components.theme || defaultSettings.theme,
      primaryColor: components.primaryColor || defaultSettings.primaryColor,
      secondaryColor: components.secondaryColor || defaultSettings.secondaryColor,
      accentColor: components.accentColor || defaultSettings.accentColor,
      borderRadius: components.borderRadius || defaultSettings.borderRadius,
      fontFamily: components.fontFamily || defaultSettings.fontFamily,
      fontSize: components.fontSize || defaultSettings.fontSize,
      buttonStyle: components.buttonStyle || defaultSettings.buttonStyle,
      cardStyle: components.cardStyle || defaultSettings.cardStyle,
      enableAnimations: components.enableAnimations !== undefined ? components.enableAnimations : defaultSettings.enableAnimations,
      enableShadows: components.enableShadows !== undefined ? components.enableShadows : defaultSettings.enableShadows,
      enableGradients: components.enableGradients !== undefined ? components.enableGradients : defaultSettings.enableGradients,
      customCSS: components.customCSS || defaultSettings.customCSS,
      logoUrl: components.logoUrl || defaultSettings.logoUrl,
      faviconUrl: components.faviconUrl || defaultSettings.faviconUrl,
      enableCustomBranding: components.enableCustomBranding !== undefined ? components.enableCustomBranding : defaultSettings.enableCustomBranding,
    };
    
    setSettings(loadedSettings);
    
    // Debug logging in development
    if (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('utilitysign_debug') === '1') {
      console.log('[ComponentSettingsProvider] Loaded settings:', loadedSettings);
      console.log('[ComponentSettingsProvider] Source config:', config);
    }
  }, []);

  // Apply CSS variables and visual effects
  useEffect(() => {
    // Create or update style element for CSS variables
    let style = styleElement;
    if (!style) {
      style = document.createElement('style');
      style.id = 'utilitysign-component-settings';
      document.head.appendChild(style);
      setStyleElement(style);
    }

    // Map border radius values to CSS values
    const borderRadiusMap: Record<string, string> = {
      none: '0',
      sm: '0.25rem',
      md: '0.5rem',
      lg: '0.75rem',
      xl: '1rem',
      devora: '21.5px',
    };

    const borderRadiusValue = borderRadiusMap[settings.borderRadius] || borderRadiusMap.devora;

    // Build CSS with variables and visual effects
    let css = `
      :root {
        --devora-primary: ${settings.primaryColor};
        --devora-secondary: ${settings.secondaryColor};
        --devora-accent: ${settings.accentColor};
        --devora-button-radius: ${borderRadiusValue};
        --devora-card-radius: ${borderRadiusValue};
        --devora-input-radius: ${borderRadiusValue};
      }
      
      .utilitysign-component-wrapper {
        /* Apply CSS variables to wrapper for component inheritance */
        --devora-primary: ${settings.primaryColor};
        --devora-secondary: ${settings.secondaryColor};
        --devora-accent: ${settings.accentColor};
        --devora-button-radius: ${borderRadiusValue};
        --devora-card-radius: ${borderRadiusValue};
        --devora-input-radius: ${borderRadiusValue};
        /* Visual effects */
        ${settings.enableAnimations ? 'transition: all 0.3s ease;' : ''}
        ${settings.enableShadows ? 'box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);' : ''}
        ${settings.enableGradients ? `background: linear-gradient(135deg, ${settings.primaryColor} 0%, ${settings.secondaryColor} 100%);` : ''}
      }
      
      /* Apply border radius to components */
      .devora-button,
      .devora-card,
      .devora-input,
      .devora-input[type="date"],
      input[type="date"].devora-input {
        border-radius: var(--devora-input-radius, var(--devora-button-radius));
      }
      
      /* Button style variants - scoped to utilitysign-component-wrapper */
      .utilitysign-component-wrapper .devora-button-style-modern {
        border-radius: 8px !important;
        font-weight: 600;
        letter-spacing: 0.5px;
        padding: 0.625rem 1.5rem;
      }
      
      .utilitysign-component-wrapper .devora-button-style-minimal {
        border-radius: 4px !important;
        border-width: 1px !important;
        background: transparent !important;
        border-color: var(--devora-primary);
        color: var(--devora-primary);
      }
      
      .utilitysign-component-wrapper .devora-button-style-minimal:hover {
        background: var(--devora-primary) !important;
        color: white !important;
      }
      
      /* Card style variants - scoped to utilitysign-component-wrapper */
      .utilitysign-component-wrapper .devora-card-style-modern {
        border-radius: 12px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
      }
      
      .utilitysign-component-wrapper .devora-card-style-minimal {
        border-radius: 4px !important;
        border-width: 1px !important;
        box-shadow: none !important;
        background: transparent !important;
      }
      
      /* Visual effects classes - scoped to utilitysign-component-wrapper */
      ${settings.enableAnimations ? `
        .utilitysign-component-wrapper .devora-button,
        .utilitysign-component-wrapper .devora-card,
        .utilitysign-component-wrapper .devora-input {
          transition: all 0.3s ease;
        }
        .utilitysign-component-wrapper .devora-button:hover {
          transform: translateY(-2px);
        }
      ` : ''}
      
      ${settings.enableShadows ? `
        .utilitysign-component-wrapper .devora-card {
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .utilitysign-component-wrapper .devora-button {
          box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
      ` : ''}
      
      ${settings.enableGradients ? `
        .utilitysign-component-wrapper .devora-button-primary {
          background: linear-gradient(135deg, ${settings.primaryColor} 0%, ${settings.secondaryColor} 100%);
        }
      ` : ''}
    `;

    style.textContent = css;

    // Cleanup on unmount
    return () => {
      if (style && style.parentNode) {
        style.parentNode.removeChild(style);
      }
    };
  }, [settings, styleElement]);

  // Inject custom CSS if enabled
  useEffect(() => {
    if (settings.enableCustomBranding && settings.customCSS) {
      let customStyle = customStyleElement;
      if (!customStyle) {
        customStyle = document.createElement('style');
        customStyle.id = 'utilitysign-custom-css';
        document.head.appendChild(customStyle);
        setCustomStyleElement(customStyle);
      }
      customStyle.textContent = settings.customCSS;
    } else if (customStyleElement && customStyleElement.parentNode) {
      customStyleElement.parentNode.removeChild(customStyleElement);
      setCustomStyleElement(null);
    }

    // Cleanup on unmount
    return () => {
      if (customStyleElement && customStyleElement.parentNode) {
        customStyleElement.parentNode.removeChild(customStyleElement);
      }
    };
  }, [settings.enableCustomBranding, settings.customCSS, customStyleElement]);

  const updateSettings = (newSettings: Partial<ComponentSettings>) => {
    setSettings(prev => ({ ...prev, ...newSettings }));
  };

  const value = useMemo(() => ({
    settings,
    updateSettings,
  }), [settings]);

  return (
    <ComponentSettingsContext.Provider value={value}>
      <div className="utilitysign-component-wrapper">
        {children}
      </div>
    </ComponentSettingsContext.Provider>
  );
};

// Export a hook to get theme for ThemeProvider
export const useComponentTheme = (): 'dark' | 'light' | 'system' => {
  const { settings } = useComponentSettings();
  return settings.theme === 'auto' ? 'system' : (settings.theme as 'dark' | 'light' | 'system');
};

