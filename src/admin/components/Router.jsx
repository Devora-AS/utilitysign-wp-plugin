import React from 'react';
import Settings from '../pages/settings';

const Router = () => {
  // Simple hash-based routing for WordPress admin
  const getCurrentRoute = () => {
    const hash = window.location.hash;
    if (hash.includes('#/settings')) {
      return 'settings';
    }
    return 'dashboard';
  };

  const renderCurrentRoute = () => {
    const route = getCurrentRoute();
    
    switch (route) {
      case 'settings':
        return <Settings />;
      default:
        return <Dashboard />;
    }
  };

  return (
    <div className="utilitysign-admin">
      {renderCurrentRoute()}
    </div>
  );
};

// Simple dashboard component
const Dashboard = () => {
  return (
    <div className="p-6">
      <div className="mb-8">
        <h1 className="text-3xl font-heading font-black text-devora-primary-dark mb-2">
          UtilitySign Admin
        </h1>
        <p className="text-devora-text-secondary">
          Manage your document signing workflows and settings
        </p>
      </div>
      
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div className="devora-card-white p-6">
          <h3 className="text-lg font-ui font-bold text-devora-primary mb-2">
            Quick Actions
          </h3>
          <p className="text-devora-text-secondary mb-4">
            Access common administrative tasks
          </p>
          <a 
            href="#/settings" 
            className="devora-button devora-button-primary inline-block"
          >
            Go to Settings
          </a>
        </div>
        
        <div className="devora-card-white p-6">
          <h3 className="text-lg font-ui font-bold text-devora-primary mb-2">
            System Status
          </h3>
          <p className="text-devora-text-secondary">
            All systems operational
          </p>
        </div>
        
        <div className="devora-card-white p-6">
          <h3 className="text-lg font-ui font-bold text-devora-primary mb-2">
            Recent Activity
          </h3>
          <p className="text-devora-text-secondary">
            No recent activity
          </p>
        </div>
      </div>
    </div>
  );
};

export default Router;