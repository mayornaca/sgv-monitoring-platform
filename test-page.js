#!/usr/bin/env node

const puppeteer = require('puppeteer');
const fs = require('fs');

// Configuración global de pruebas - FMS Gesvial
const TEST_CONFIG = {
    username: 'jnacaratto@gesvial.cl',
    password: 'Pucara1004',
    baseUrl: 'https://vs.gvops.cl',
    screenshotDir: '/www/wwwroot/vs.gvops.cl/public/screenshots',
    logsDir: '/www/wwwroot/vs.gvops.cl/public/logs',
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

    // Capture ALL console logs (not just errors) for systematic analysis
    const consoleLogs = [];
    const allConsoleLogs = [];

    page.on('console', msg => {
        const logEntry = {
            type: msg.type(),
            text: msg.text(),
            timestamp: new Date().toISOString(),
            location: msg.location()
        };

        // Store all logs for JSON file
        allConsoleLogs.push(logEntry);

        // Keep errors for terminal output
        if (msg.type() === 'error') {
            consoleLogs.push(`CONSOLE ERROR: ${msg.text()}`);
        }
    });

    page.on('pageerror', error => {
        const errorEntry = {
            type: 'pageerror',
            text: error.message,
            stack: error.stack,
            timestamp: new Date().toISOString()
        };
        allConsoleLogs.push(errorEntry);
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

            // Verificar si el botón es AJAX (#btn-ajax-login) o submit tradicional
            const isAjaxLogin = await page.$('#btn-ajax-login');

            if (isAjaxLogin) {
                // Login AJAX - click y esperar respuesta + redirección
                await page.click('#btn-ajax-login');

                // Esperar a que desaparezca el botón de login (indica redirección exitosa)
                await page.waitForFunction(
                    () => !document.querySelector('#btn-ajax-login'),
                    { timeout: 10000 }
                );
            } else {
                // Login tradicional con form submit
                const submitButtonSelector = 'button[type="submit"]';
                await page.waitForSelector(submitButtonSelector, { timeout: 5000 });
                await page.click(submitButtonSelector);
                await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 10000 });
            }

            console.log('✅ Login successful');
        }
        
        // Navegar a la página objetivo
        // Detectar si path ya es una URL completa
        const targetUrl = path.startsWith('http://') || path.startsWith('https://')
            ? path
            : `${TEST_CONFIG.baseUrl}${path}`;

        console.log(`📄 Navigating to ${targetUrl}...`);
        const response = await page.goto(targetUrl, {
            waitUntil: 'networkidle2',
            timeout: 5000
        });
        
        const status = response.status();
        const finalUrl = page.url();
        
        console.log(`📊 Status: ${status}`);
        console.log(`🔗 Final URL: ${finalUrl}`);
        
        // Crear subfolder para esta sesión de test (mantener juntas screenshot + logs)
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const sessionDir = `${TEST_CONFIG.screenshotDir}/session-${timestamp}`;
        if (!fs.existsSync(sessionDir)) {
            fs.mkdirSync(sessionDir, { recursive: true });
        }

        // Capturar screenshot del viewport (no full page)
        const screenshotPath = `${sessionDir}/screenshot.png`;
        await page.screenshot({
            path: screenshotPath,
            fullPage: false  // Solo capturar viewport visible
        });
        console.log(`📸 Screenshot: ${screenshotPath}`);

        // Guardar todos los logs en archivo JSON para análisis sistemático
        const logsPath = `${sessionDir}/console.json`;
        const logsSummary = {
            timestamp: new Date().toISOString(),
            url: finalUrl,
            totalLogs: allConsoleLogs.length,
            errorCount: allConsoleLogs.filter(l => l.type === 'error' || l.type === 'pageerror').length,
            warningCount: allConsoleLogs.filter(l => l.type === 'warning').length,
            logCount: allConsoleLogs.filter(l => l.type === 'log').length,
            logs: allConsoleLogs
        };
        fs.writeFileSync(logsPath, JSON.stringify(logsSummary, null, 2));
        console.log(`📝 Console logs saved: ${logsPath} (${allConsoleLogs.length} entries)`);
        
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
            
            // Contar elementos importantes y verificar control panel
            const stats = await page.evaluate(() => {
                // Verificar control panel
                const controlPanel = document.querySelector('#modern-control-panel');
                const controlPanelVisible = controlPanel ?
                    window.getComputedStyle(controlPanel).display !== 'none' : false;

                // Verificar elementos específicos del control panel
                const deviceTypeSelect = document.querySelector('#device-type-select');
                const statusFilter = document.querySelector('#status-filter');
                const deviceSearch = document.querySelector('#device-search');

                // Test persistence features
                const localStorageKey = 'cot_panel_config';
                const storedConfig = localStorage.getItem(localStorageKey);
                const hasLocalStorage = !!storedConfig;
                let parsedConfig = null;
                if (hasLocalStorage) {
                    try {
                        parsedConfig = JSON.parse(storedConfig);
                    } catch (e) {
                        parsedConfig = null;
                    }
                }

                // Check URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                const urlConfig = {};
                for (let [key, value] of urlParams) {
                    urlConfig[key] = value;
                }

                // Check if Choices.js is loaded
                const choicesLoaded = typeof Choices !== 'undefined';

                // Check if ModernControlPanel class exists
                const controlPanelClass = typeof ModernControlPanel !== 'undefined';

                return {
                    forms: document.querySelectorAll('form').length,
                    tables: document.querySelectorAll('table').length,
                    links: document.querySelectorAll('a').length,
                    buttons: document.querySelectorAll('button').length,
                    controlPanel: controlPanelVisible,
                    controlPanelRoute: controlPanel?.dataset?.route || 'not-found',
                    deviceTypeSelect: !!deviceTypeSelect,
                    statusFilter: !!statusFilter,
                    deviceSearch: !!deviceSearch,
                    controlPanelHeight: controlPanel ? controlPanel.offsetHeight : 0,
                    // Persistence tests
                    hasLocalStorage: hasLocalStorage,
                    localStorageConfig: parsedConfig,
                    urlParameters: urlConfig,
                    choicesLoaded: choicesLoaded,
                    controlPanelClass: controlPanelClass,
                    videowallButton: !!document.querySelector('#copy-videowall-url')
                };
            });
            
            console.log('\n📊 Page Statistics:');
            console.log(`   Forms: ${stats.forms}`);
            console.log(`   Tables: ${stats.tables}`);
            console.log(`   Links: ${stats.links}`);
            console.log(`   Buttons: ${stats.buttons}`);
            console.log(`   Control Panel: ${stats.controlPanel ? '✅ VISIBLE' : '❌ NOT VISIBLE'}`);
            console.log(`   Panel Route: ${stats.controlPanelRoute}`);
            console.log(`   Panel Height: ${stats.controlPanelHeight}px`);
            console.log(`   Device Type Select: ${stats.deviceTypeSelect ? '✅' : '❌'}`);
            console.log(`   Status Filter: ${stats.statusFilter ? '✅' : '❌'}`);
            console.log(`   Device Search: ${stats.deviceSearch ? '✅' : '❌'}`);

            console.log('\n💾 Persistence Features:');
            console.log(`   Choices.js Loaded: ${stats.choicesLoaded ? '✅' : '❌'}`);
            console.log(`   ModernControlPanel Class: ${stats.controlPanelClass ? '✅' : '❌'}`);
            console.log(`   localStorage Active: ${stats.hasLocalStorage ? '✅' : '❌'}`);
            if (stats.localStorageConfig) {
                console.log(`   Stored Config: ${JSON.stringify(stats.localStorageConfig)}`);
            }
            console.log(`   URL Parameters: ${Object.keys(stats.urlParameters).length > 0 ? JSON.stringify(stats.urlParameters) : 'None'}`);
            console.log(`   Videowall Button: ${stats.videowallButton ? '✅' : '❌'}`)

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