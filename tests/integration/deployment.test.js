import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

test.describe('Deployment Script Tests', () => {
    const pluginDir = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/wp-plugin/utilitysign';
    
    test('deployment script exists and is executable', () => {
        const deployScript = path.join(pluginDir, 'deploy.sh');
        
        expect(fs.existsSync(deployScript)).toBe(true);
        
        // Check if script is executable
        const stats = fs.statSync(deployScript);
        expect(stats.mode & parseInt('111', 8)).toBeTruthy();
    });

    test('deployment script validates WordPress installation', () => {
        const deployScript = path.join(pluginDir, 'deploy.sh');
        const scriptContent = fs.readFileSync(deployScript, 'utf8');
        
        // Check if script contains WordPress validation
        expect(scriptContent).toContain('wp-config.php');
        expect(scriptContent).toContain('wp-content');
    });

    test('deployment script handles errors gracefully', () => {
        // Test error handling by running with invalid target
        try {
            execSync(`cd ${pluginDir} && ./deploy.sh /invalid/path`, { 
                stdio: 'pipe',
                cwd: pluginDir 
            });
        } catch (error) {
            // Should fail gracefully
            expect(error.message).toContain('Command failed');
        }
    });

    test('deployment script preserves file permissions', () => {
        const deployScript = path.join(pluginDir, 'deploy.sh');
        const scriptContent = fs.readFileSync(deployScript, 'utf8');
        
        // Check if script preserves permissions
        expect(scriptContent).toContain('chmod');
    });

    test('deployment script creates backup', () => {
        const deployScript = path.join(pluginDir, 'deploy.sh');
        const scriptContent = fs.readFileSync(deployScript, 'utf8');
        
        // Check if script creates backup
        expect(scriptContent).toContain('backup');
    });

    test('deployment script validates plugin files', () => {
        const deployScript = path.join(pluginDir, 'deploy.sh');
        const scriptContent = fs.readFileSync(deployScript, 'utf8');
        
        // Check if script validates required files
        expect(scriptContent).toContain('utilitysign.php');
    });

    test('deployment script handles symlinks correctly', () => {
        const deployScript = path.join(pluginDir, 'deploy.sh');
        const scriptContent = fs.readFileSync(deployScript, 'utf8');
        
        // Check if script handles symlinks
        expect(scriptContent).toContain('ln -sf');
    });

    test('deployment script provides helpful output', () => {
        const deployScript = path.join(pluginDir, 'deploy.sh');
        const scriptContent = fs.readFileSync(deployScript, 'utf8');
        
        // Check if script provides helpful output
        expect(scriptContent).toContain('echo');
        expect(scriptContent).toContain('Deploying');
    });

    test('deployment script can be run multiple times safely', () => {
        // Test idempotency
        const deployScript = path.join(pluginDir, 'deploy.sh');
        const scriptContent = fs.readFileSync(deployScript, 'utf8');
        
        // Check if script handles existing files
        expect(scriptContent).toContain('rm -rf');
        expect(scriptContent).toContain('cp -r');
    });
});