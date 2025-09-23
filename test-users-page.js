#!/usr/bin/env node

const puppeteer = require('puppeteer');
const fs = require('fs');

// Configuración para el nuevo sistema Symfony 6.4
const TEST_CONFIG = {
    username: 'admin',
    password: 'admin123',
    baseUrl: 'https://vs.gvops.cl',
    screenshotDir: '/tmp',
    headless: 'new',
    viewport: { width: 1920, height: 1080 }
};

async function testUsersPage() {
    const browser = await puppeteer.launch({
        headless: TEST_CONFIG.headless,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    await page.setViewport(TEST_CONFIG.viewport);
    
    try {
        console.log('🔐 Logging in to Symfony 6.4...');
        await page.goto(`${TEST_CONFIG.baseUrl}/login`, { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });
        
        // Login page screenshot
        await page.screenshot({ 
            path: '/tmp/1-login-page.png',
            fullPage: true 
        });
        console.log('📸 Login page screenshot: /tmp/1-login-page.png');
        
        await page.type('input[name="_username"]', TEST_CONFIG.username);
        await page.type('input[name="_password"]', TEST_CONFIG.password);
        await page.click('button[type="submit"]');
        
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log('✅ Login successful');
        
        // Dashboard screenshot
        await page.screenshot({ 
            path: '/tmp/2-dashboard.png',
            fullPage: true 
        });
        console.log('📸 Dashboard screenshot: /tmp/2-dashboard.png');
        
        // Navigate to users page
        console.log('📄 Navigating to users page...');
        const response = await page.goto(`${TEST_CONFIG.baseUrl}/admin/users`, { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });
        
        const status = response.status();
        const finalUrl = page.url();
        
        console.log(`📊 Status: ${status}`);
        console.log(`🔗 Final URL: ${finalUrl}`);
        
        // Wait for Live Components to load
        await page.waitForTimeout(2000);
        
        // Main users page screenshot
        await page.screenshot({ 
            path: '/tmp/3-users-table-full.png',
            fullPage: true 
        });
        console.log('📸 Users table full page: /tmp/3-users-table-full.png');
        
        // Screenshot just the visible area
        await page.screenshot({ 
            path: '/tmp/4-users-table-viewport.png',
            fullPage: false 
        });
        console.log('📸 Users table viewport: /tmp/4-users-table-viewport.png');
        
        // Check for Symfony errors
        const symfonyError = await page.evaluate(() => {
            const exception = document.querySelector('.exception-message');
            const errorTitle = document.querySelector('h1.exception-message');
            const error500 = document.querySelector('.text-exception h1');
            
            if (exception) return exception.textContent.trim();
            if (errorTitle) return errorTitle.textContent.trim();
            if (error500) return error500.textContent.trim();
            
            return null;
        });
        
        if (symfonyError) {
            console.log('\n❌ ERROR FOUND:');
            console.log(symfonyError);
            return false;
        } else {
            console.log('\n✅ No Symfony errors detected');
            
            const pageTitle = await page.title();
            console.log(`📄 Page title: ${pageTitle}`);
            
            // Check for Live Component elements
            const liveComponents = await page.evaluate(() => {
                return {
                    liveComponents: document.querySelectorAll('[data-controller*="live"]').length,
                    forms: document.querySelectorAll('form').length,
                    tables: document.querySelectorAll('table').length,
                    buttons: document.querySelectorAll('button').length,
                    inputs: document.querySelectorAll('input').length,
                    badges: document.querySelectorAll('.badge').length,
                    dropdowns: document.querySelectorAll('.dropdown').length
                };
            });
            
            console.log('\n📊 Sistema de Monitoreo - Page Statistics:');
            console.log(`   Live Components: ${liveComponents.liveComponents}`);
            console.log(`   Forms: ${liveComponents.forms}`);
            console.log(`   Tables: ${liveComponents.tables}`);
            console.log(`   Buttons: ${liveComponents.buttons}`);
            console.log(`   Inputs: ${liveComponents.inputs}`);
            console.log(`   Badges: ${liveComponents.badges}`);
            console.log(`   Dropdowns: ${liveComponents.dropdowns}`);
            
            // Test filtering functionality
            console.log('\n🧪 Testing Live Component filters...');
            
            const searchInput = await page.$('input[data-model="search"]');
            if (searchInput) {
                await searchInput.type('admin');
                await page.waitForTimeout(1000);
                
                await page.screenshot({ 
                    path: '/tmp/5-users-search-filter.png',
                    fullPage: false 
                });
                console.log('📸 Search filter test: /tmp/5-users-search-filter.png');
            }
        }
        
        return true;
        
    } catch (error) {
        console.error('❌ Test failed:', error.message);
        
        // Emergency screenshot
        await page.screenshot({ 
            path: '/tmp/error-screenshot.png',
            fullPage: true 
        });
        console.log('📸 Error screenshot: /tmp/error-screenshot.png');
        
        return false;
    } finally {
        await browser.close();
    }
}

// Ejecutar
console.log('🧪 Sistema de Monitoreo - Users Table Test');
console.log('='.repeat(60));

testUsersPage().then(success => {
    console.log('='.repeat(60));
    if (success) {
        console.log('✅ Test completed successfully - Check screenshots in /tmp/');
        console.log('   📸 /tmp/1-login-page.png');
        console.log('   📸 /tmp/2-dashboard.png');
        console.log('   📸 /tmp/3-users-table-full.png');
        console.log('   📸 /tmp/4-users-table-viewport.png');
        console.log('   📸 /tmp/5-users-search-filter.png');
        process.exit(0);
    } else {
        console.log('❌ Test failed - Check /tmp/error-screenshot.png');
        process.exit(1);
    }
});