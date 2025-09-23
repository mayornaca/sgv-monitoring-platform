#!/usr/bin/env node

const puppeteer = require('puppeteer');

const TEST_CONFIG = {
    username: 'jnacaratto@gesvial.cl',
    password: 'Pampa1004',
    baseUrl: 'https://vs.gvops.cl',
    screenshotDir: '/www/wwwroot/vs.gvops.cl/public/screenshots',
    headless: 'new',
    viewport: { width: 1920, height: 1080 }
};

async function testDropdown() {
    const browser = await puppeteer.launch({
        headless: TEST_CONFIG.headless,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    await page.setViewport(TEST_CONFIG.viewport);
    
    try {
        // Login
        console.log('🔐 Logging in...');
        await page.goto(`${TEST_CONFIG.baseUrl}/login`, { 
            waitUntil: 'networkidle2',
            timeout: 10000 
        });
        
        await page.type('input[name="email"]', TEST_CONFIG.username);
        await page.type('input[name="password"]', TEST_CONFIG.password);
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        
        // Go to users page
        console.log('📄 Navigating to users page...');
        await page.goto(`${TEST_CONFIG.baseUrl}/admin/users`, { 
            waitUntil: 'networkidle2',
            timeout: 10000 
        });
        
        // Wait for page to load completely
        await page.waitForSelector('table', { timeout: 5000 });
        
        // Find and click first dropdown button
        console.log('🔍 Testing dropdown functionality...');
        const dropdownButton = await page.$('[data-controller="dropdown"] button[data-action="dropdown#toggle"]');
        
        if (!dropdownButton) {
            console.log('❌ Dropdown button not found');
            return;
        }
        
        // Take screenshot before click
        const timestamp = new Date().toISOString().replace(/:/g, '-');
        await page.screenshot({ 
            path: `${TEST_CONFIG.screenshotDir}/dropdown-before-${timestamp}.png`,
            fullPage: true 
        });
        
        console.log('🖱️ Clicking dropdown button...');
        await dropdownButton.click();
        
        // Wait a moment for dropdown to appear
        await page.waitForTimeout(500);
        
        // Take screenshot after click
        await page.screenshot({ 
            path: `${TEST_CONFIG.screenshotDir}/dropdown-after-${timestamp}.png`,
            fullPage: true 
        });
        
        // Check if dropdown menu is visible
        const dropdownMenu = await page.$('[data-dropdown-target="menu"]:not(.hidden)');
        
        if (dropdownMenu) {
            console.log('✅ Dropdown opened successfully!');
            
            // Try to click an action
            const editButton = await page.$('[data-dropdown-target="menu"] a[href*="edit"]');
            if (editButton) {
                console.log('📝 Edit button found in dropdown');
            }
        } else {
            console.log('❌ Dropdown did not open');
        }
        
        console.log(`📸 Screenshots saved: dropdown-before-${timestamp}.png and dropdown-after-${timestamp}.png`);
        
    } catch (error) {
        console.error('Error testing dropdown:', error.message);
    } finally {
        await browser.close();
    }
}

testDropdown();