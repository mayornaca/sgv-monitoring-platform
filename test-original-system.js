const puppeteer = require('puppeteer');
const path = require('path');

async function testOriginalSystem() {
    console.log('🧪 Original COT System Comparison Test');
    console.log('==================================================');

    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-web-security', '--disable-gpu']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1920, height: 1080 });

        // Login to original system
        console.log('🔐 Logging into original system...');
        await page.goto('https://sgv.costaneranorte.cl/login', {
            waitUntil: 'domcontentloaded',
            timeout: 8000
        });

        // Use specific selectors based on curl analysis
        const usernameSelector = 'input[name="_username"]#username';
        const passwordSelector = 'input[name="_password"]#password';
        const submitSelector = '#btn-ajax-login'; // AJAX login button, not form submit

        await page.waitForSelector(usernameSelector, { timeout: 3000 });
        await page.type(usernameSelector, 'jnacaratto@gesvial.cl');
        await page.type(passwordSelector, 'Pampa1004');
        await page.click(submitSelector);

        // Wait for AJAX login response and subsequent JavaScript redirect
        try {
            // Wait for either navigation or AJAX completion
            await Promise.race([
                page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 8000 }),
                page.waitForFunction(() => window.location.href !== 'https://sgv.costaneranorte.cl/login', { timeout: 8000 })
            ]);
        } catch (navError) {
            console.log('⚠️  Login may have failed or timed out');
        }
        console.log('✅ Login successful to original system');

        // Navigate to COT page with specific parameters
        console.log('📄 Navigating to original COT system...');
        await page.goto('https://sgv.costaneranorte.cl/cot/index/0?videowall=false&device_status=all&contract_ui=true&masonry=true&grid_items_width=2&input_device_finder=', {
            waitUntil: 'domcontentloaded',
            timeout: 8000
        });

        // Wait for page to load completely
        await new Promise(resolve => setTimeout(resolve, 3000));

        const response = await page.evaluate(() => {
            return {
                status: 'loaded',
                url: window.location.href,
                title: document.title
            }
        });

        console.log(`📊 Status: Page loaded`);
        console.log(`🔗 Final URL: ${response.url}`);
        console.log(`📄 Page title: ${response.title}`);

        // Take screenshot
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5) + 'Z';
        const screenshotPath = path.join(__dirname, 'public', 'screenshots', `original-system-${timestamp}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: true });
        console.log(`📸 Original System Screenshot: ${screenshotPath}`);

        // Analyze device groups and details
        console.log('🔍 Analyzing device groups and structure...');

        const deviceAnalysis = await page.evaluate(() => {
            const results = {
                totalDeviceGroups: 0,
                deviceGroups: [],
                totalDevices: 0,
                deviceTypes: {},
                sensorsFound: 0,
                hasControlPanel: false,
                hasFilters: false,
                gridItems: []
            };

            // Check for control panel
            const controlPanel = document.querySelector('#control-panel-container, .control-panel, .filtros');
            results.hasControlPanel = !!controlPanel;

            // Check for filters
            const filters = document.querySelectorAll('.filter-button, .btn-filter, [data-filter]');
            results.hasFilters = filters.length > 0;

            // Analyze grid items (device groups)
            const gridItems = document.querySelectorAll('.grid-item, .grupo-dispositivos, .device-group');
            results.totalDeviceGroups = gridItems.length;

            gridItems.forEach((item, index) => {
                const title = item.querySelector('h1, h2, h3, h4, .title, .grupo-titulo');
                const devices = item.querySelectorAll('.cot_device_container, .opc_device_container, .device');
                const sensors = item.querySelectorAll('.sensor, .opc_var_device_container, [class*="sensor"]');

                if (index < 10) { // Limit to first 10 for brevity
                    results.gridItems.push({
                        index: index,
                        title: title ? title.textContent.trim() : 'Sin título',
                        deviceCount: devices.length,
                        sensorCount: sensors.length,
                        classes: Array.from(item.classList)
                    });
                }

                results.totalDevices += devices.length;
                results.sensorsFound += sensors.length;
            });

            // Analyze device types
            const allDevices = document.querySelectorAll('.cot_device_container, .opc_device_container');
            allDevices.forEach(device => {
                const typeClasses = Array.from(device.classList).filter(cls => cls.startsWith('type_'));
                typeClasses.forEach(typeClass => {
                    if (!results.deviceTypes[typeClass]) {
                        results.deviceTypes[typeClass] = 0;
                    }
                    results.deviceTypes[typeClass]++;
                });
            });

            return results;
        });

        console.log(`📊 Original System Analysis:`);
        console.log(`   Total device groups: ${deviceAnalysis.totalDeviceGroups}`);
        console.log(`   Total devices: ${deviceAnalysis.totalDevices}`);
        console.log(`   Total sensors: ${deviceAnalysis.sensorsFound}`);
        console.log(`   Has control panel: ${deviceAnalysis.hasControlPanel}`);
        console.log(`   Has filters: ${deviceAnalysis.hasFilters}`);

        console.log(`📋 Device Groups (first 10):`);
        deviceAnalysis.gridItems.forEach(group => {
            console.log(`   ${group.index}: "${group.title}" - ${group.deviceCount} devices, ${group.sensorCount} sensors`);
        });

        console.log(`🎯 Device Types Found:`);
        Object.entries(deviceAnalysis.deviceTypes).forEach(([type, count]) => {
            console.log(`   ${type}: ${count} devices`);
        });

        // Check for JavaScript errors
        const errors = await page.evaluate(() => {
            return window.jsErrors || [];
        });

        if (errors.length === 0) {
            console.log('✅ No JavaScript errors detected in original system');
        } else {
            console.log('❌ JavaScript errors found in original system:');
            errors.forEach(error => console.log(`   ${error}`));
        }

        console.log('==================================================');
        console.log('✅ Original system analysis completed successfully');

        return {
            screenshotPath,
            deviceAnalysis,
            finalUrl: response.url
        };

    } catch (error) {
        console.error('❌ Test failed:', error.message);
        return null;
    } finally {
        await browser.close();
    }
}

// Run if called directly
if (require.main === module) {
    testOriginalSystem().catch(console.error);
}

module.exports = testOriginalSystem;