#!/usr/bin/env node

const puppeteer = require('puppeteer');
const { TEST_CONFIG } = require('./test-page.js');

async function captureConsoleErrors() {
    const browser = await puppeteer.launch({
        headless: TEST_CONFIG.headless,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport(TEST_CONFIG.viewport);

    // Capture all console messages
    const consoleMessages = [];
    const errors = [];

    page.on('console', msg => {
        consoleMessages.push({
            type: msg.type(),
            text: msg.text(),
            location: msg.location()
        });
    });

    page.on('pageerror', error => {
        errors.push({
            message: error.message,
            stack: error.stack
        });
    });

    try {
        // Login first
        console.log('🔐 Logging in...');
        await page.goto(`${TEST_CONFIG.baseUrl}/login`, {
            waitUntil: 'networkidle2',
            timeout: 10000
        });

        const emailSelector = 'input[name="email"], input[name="_username"], input#username, input#email';
        const passwordSelector = 'input[name="password"], input[name="_password"], input#password';

        await page.waitForSelector(emailSelector, { timeout: 5000 });
        await page.type(emailSelector, TEST_CONFIG.username);
        await page.type(passwordSelector, TEST_CONFIG.password);
        await page.click('button[type="submit"]');

        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log('✅ Login successful');

        // Navigate to VS spire history
        const vsUrl = '/admin/spire_history_vs_min?fechaInicio=16-09-2025%2015:00:00&fechaTermino=16-09-2025%2015:59:59';
        console.log(`📄 Navigating to ${vsUrl}...`);

        await page.goto(`${TEST_CONFIG.baseUrl}${vsUrl}`, {
            waitUntil: 'networkidle2',
            timeout: 10000
        });

        // Wait a bit for all JS to execute
        await page.waitForTimeout(3000);

        // Extract the actual JavaScript source that was generated
        const javascriptContent = await page.evaluate(() => {
            const scripts = Array.from(document.querySelectorAll('script'));
            const inlineScripts = scripts
                .filter(script => !script.src)
                .map(script => script.textContent);
            return inlineScripts.join('\n\n--- SCRIPT SEPARATOR ---\n\n');
        });

        // Take screenshot
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        await page.screenshot({
            path: `${TEST_CONFIG.screenshotDir}/console-debug-${timestamp}.png`,
            fullPage: true
        });

        console.log('\n🐛 CONSOLE ANALYSIS:');
        console.log('='.repeat(60));

        if (errors.length > 0) {
            console.log('\n❌ JavaScript Errors:');
            errors.forEach((error, index) => {
                console.log(`${index + 1}. ${error.message}`);
                if (error.stack) {
                    console.log(`   Stack: ${error.stack.split('\n')[0]}`);
                }
            });
        }

        if (consoleMessages.length > 0) {
            console.log('\n📋 Console Messages:');
            consoleMessages.forEach((msg, index) => {
                if (msg.type === 'error') {
                    console.log(`❌ ${index + 1}. [${msg.type.toUpperCase()}] ${msg.text}`);
                    if (msg.location) {
                        console.log(`    Location: line ${msg.location.lineNumber}, column ${msg.location.columnNumber}`);
                    }
                } else if (msg.type === 'warning') {
                    console.log(`⚠️  ${index + 1}. [${msg.type.toUpperCase()}] ${msg.text}`);
                } else {
                    console.log(`ℹ️  ${index + 1}. [${msg.type.toUpperCase()}] ${msg.text}`);
                }
            });
        }

        // Look for specific syntax error patterns in the JavaScript
        console.log('\n🔍 JavaScript Source Analysis:');
        console.log('='.repeat(60));

        const lines = javascriptContent.split('\n');
        lines.forEach((line, index) => {
            if (line.includes('data = ') || line.includes('JSON.parse') || line.includes('arr_reg_spires')) {
                console.log(`Line ${index + 1}: ${line.trim()}`);
            }
        });

        // Check for common syntax errors
        const syntaxIssues = [];
        if (javascriptContent.includes('data = "";')) {
            syntaxIssues.push('Empty data string detected');
        }
        if (javascriptContent.match(/data = ".*[^"]$/)) {
            syntaxIssues.push('Unclosed string detected in data assignment');
        }
        if (javascriptContent.match(/JSON\.parse\("[^"]*$/)) {
            syntaxIssues.push('Unclosed JSON.parse string detected');
        }

        if (syntaxIssues.length > 0) {
            console.log('\n⚠️  Potential Syntax Issues:');
            syntaxIssues.forEach((issue, index) => {
                console.log(`${index + 1}. ${issue}`);
            });
        }

    } catch (error) {
        console.error('❌ Test failed:', error.message);
    }

    await browser.close();
}

captureConsoleErrors();