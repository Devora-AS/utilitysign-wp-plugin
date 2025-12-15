import React from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../../components/devora/Card';
import { Button } from '../../components/devora/Button';

const Dashboard: React.FC = () => {
  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="devora-heading text-devora-primary">UtilitySign Dashboard</h1>
        <Button variant="primary">New Document</Button>
      </div>
      
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card variant="white">
          <CardHeader>
            <CardTitle className="text-lg text-devora-primary">Total Documents</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="font-heading text-4xl font-black text-devora-primary-dark">
              0
            </p>
            <span className="devora-badge-primary mt-2">Ready to start</span>
          </CardContent>
        </Card>
        
        <Card variant="white">
          <CardHeader>
            <CardTitle className="text-lg text-devora-primary">Pending Signatures</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="font-heading text-4xl font-black text-devora-primary-dark">
              0
            </p>
            <span className="devora-badge-secondary mt-2">No pending</span>
          </CardContent>
        </Card>
        
        <Card variant="white">
          <CardHeader>
            <CardTitle className="text-lg text-devora-primary">Completed</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="font-heading text-4xl font-black text-devora-primary-dark">
              0
            </p>
            <span className="devora-badge-accent mt-2">All done</span>
          </CardContent>
        </Card>
      </div>
      
      <Card variant="white">
        <CardHeader>
          <CardTitle className="text-lg text-devora-primary">Recent Documents</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-devora-text-secondary">
            No documents yet. Create your first document to get started with UtilitySign.
          </p>
        </CardContent>
      </Card>
    </div>
  );
};

export default Dashboard;
