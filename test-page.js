#!/usr/bin/env node

const puppeteer = require('puppeteer');
const fs = require('fs');

// Configuración global de pruebas - Symfony 6.4
const TEST_CONFIG = {
    username: 'jnacaratto@gesvial.cl',
    password: 'Pampa1004',
    baseUrl: 'https://vs.gvops.cl',
    screenshotDir: '/www/wwwroot/vs.gvops.cl/public/screenshots',
    headless: 'new',
    viewport: { width: 1920, height: 1080 }
};

async function testPage(path = '/admin/users', requiresLogin = true) {
    const browser = await puppeteer.launch({
        headless: TEST_CONFIG.headless,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    await page.setViewport(TEST_CONFIG.viewport);

    // Capture console errors
    const consoleLogs = [];
    page.on('console', msg => {
        if (msg.type() === 'error') {
            consoleLogs.push(`CONSOLE ERROR: ${msg.text()}`);
        }
    });

    page.on('pageerror', error => {
        consoleLogs.push(`PAGE ERROR: ${error.message}`);
    });

    try {
        if (requiresLogin) {
            // Login primero
            console.log('🔐 Logging in...');
            await page.goto(`${TEST_CONFIG.baseUrl}/login`, { 
                waitUntil: 'networkidle2',
                timeout: 5000 
            });
            
            // Buscar campos por id o name según el formulario actual
            const emailSelector = 'input[name="email"], input[name="_username"], input#username, input#email';
            const passwordSelector = 'input[name="password"], input[name="_password"], input#password';
            
            await page.waitForSelector(emailSelector, { timeout: 5000 });
            await page.type(emailSelector, TEST_CONFIG.username);
            await page.type(passwordSelector, TEST_CONFIG.password);
            await page.click('button[type="submit"]');
            
            await page.waitForNavigation({ waitUntil: 'networkidle2' });
            console.log('✅ Login successful');
        }
        
        // Navegar a la página objetivo
        console.log(`📄 Navigating to ${path}...`);
        const response = await page.goto(`${TEST_CONFIG.baseUrl}${path}`, { 
            waitUntil: 'networkidle2',
            timeout: 5000 
        });
        
        const status = response.status();
        const finalUrl = page.url();
        
        console.log(`📊 Status: ${status}`);
        console.log(`🔗 Final URL: ${finalUrl}`);
        
        // Capturar screenshot
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const screenshotPath = `${TEST_CONFIG.screenshotDir}/test-${timestamp}.png`;
        await page.screenshot({ 
            path: screenshotPath,
            fullPage: true 
        });
        console.log(`📸 Screenshot: ${screenshotPath}`);
        
        // Wait a bit for JavaScript to execute
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Buscar errores de Symfony
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
            
            // Obtener stack trace si existe
            const stackTrace = await page.evaluate(() => {
                const trace = document.querySelector('.traces-text');
                if (trace) {
                    return trace.textContent.substring(0, 500);
                }
                return null;
            });
            
            if (stackTrace) {
                console.log('\n📋 Stack trace (first 500 chars):');
                console.log(stackTrace);
            }
        } else {
            console.log('\n✅ No Symfony errors detected');
            
            // Verificar si la página se cargó correctamente
            const pageTitle = await page.title();
            console.log(`📄 Page title: ${pageTitle}`);
            
            // Contar elementos importantes
            const stats = await page.evaluate(() => {
                return {
                    forms: document.querySelectorAll('form').length,
                    tables: document.querySelectorAll('table').length,
                    links: document.querySelectorAll('a').length,
                    buttons: document.querySelectorAll('button').length
                };
            });
            
            console.log('\n📊 Page Statistics:');
            console.log(`   Forms: ${stats.forms}`);
            console.log(`   Tables: ${stats.tables}`);
            console.log(`   Links: ${stats.links}`);
            console.log(`   Buttons: ${stats.buttons}`);

            // Show console errors if any
            if (consoleLogs.length > 0) {
                console.log('\n🐛 Console Errors:');
                consoleLogs.forEach((log, index) => {
                    console.log(`   ${index + 1}. ${log}`);
                });
            } else {
                console.log('\n✅ No console errors detected');
            }
        }
        
        return !symfonyError;
        
    } catch (error) {
        console.error('❌ Test failed:', error.message);
        return false;
    } finally {
        await browser.close();
    }
}

// Ejecutar si se llama directamente
if (require.main === module) {
    const path = process.argv[2] || '/admin/users';
    const requiresLogin = process.argv[3] !== 'false';
    
    console.log('🧪 Symfony Page Tester');
    console.log('=' .repeat(50));
    
    testPage(path, requiresLogin).then(success => {
        console.log('=' .repeat(50));
        if (success) {
            console.log('✅ Test completed successfully');
            process.exit(0);
        } else {
            console.log('❌ Test failed');
            process.exit(1);
        }
    });
}

module.exports = { testPage, TEST_CONFIG };