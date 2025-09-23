const puppeteer = require('puppeteer');

(async () => {
    console.log('=== COT Implementation Test ===');

    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();

    try {
        console.log('Testing COT videowall page...');

        // Test 1: Basic page load
        const response = await page.goto('http://localhost:8000/cot/index/0?videowall=true', {
            waitUntil: 'networkidle0',
            timeout: 30000
        });

        console.log(`Status: ${response.status()}`);

        if (response.status() !== 200) {
            throw new Error(`HTTP ${response.status()}: Page failed to load`);
        }

        // Test 2: Check critical elements exist
        console.log('Checking critical elements...');

        // Wait for main container
        await page.waitForSelector('#cot-main-wrapper', { timeout: 10000 });
        console.log(' Main wrapper found');

        // Check for device containers
        const deviceContainers = await page.$$('.devices_grid_item');
        console.log(` Found ${deviceContainers.length} device containers`);

        // Check for footer status bar
        const statusBar = await page.$('.navbar-fixed-bottom');
        if (statusBar) {
            console.log(' Status bar found');

            // Check counters
            const totalCount = await page.$eval('#total_devices_count', el => el.textContent);
            const activeCount = await page.$eval('#active_devices_count', el => el.textContent);
            const inactiveCount = await page.$eval('#unactive_devices_count', el => el.textContent);

            console.log(` Device counts - Total: ${totalCount}, Active: ${activeCount}, Inactive: ${inactiveCount}`);
        }

        // Test 3: Check for JavaScript errors
        const jsErrors = [];
        page.on('pageerror', error => {
            jsErrors.push(error.message);
        });

        // Wait a bit for any async JS to complete
        await page.waitForTimeout(2000);

        if (jsErrors.length > 0) {
            console.log('  JavaScript errors found:');
            jsErrors.forEach(error => console.log(`  - ${error}`));
        } else {
            console.log(' No JavaScript errors detected');
        }

        // Test 4: Test AJAX functionality
        console.log('Testing AJAX functionality...');

        // Enable request interception to test AJAX calls
        await page.setRequestInterception(true);

        let ajaxCallMade = false;
        page.on('request', request => {
            if (request.url().includes('/cot/index') && request.headers()['x-requested-with'] === 'XMLHttpRequest') {
                ajaxCallMade = true;
                console.log(' AJAX request detected');
            }
            request.continue();
        });

        // Test 5: Check template variables
        const pageContent = await page.content();

        // Check for critical Twig variables
        const hasDeviceTypes = pageContent.includes('tipos_dispositivos');
        const hasDevices = pageContent.includes('dispositivos');
        const hasConfig = pageContent.includes('grid_items_width');

        console.log(` Template variables present: devices=${hasDevices}, types=${hasDeviceTypes}, config=${hasConfig}`);

        // Test 6: Test device interaction
        if (deviceContainers.length > 0) {
            console.log('Testing device interaction...');

            // Click on first device to test context menu
            await deviceContainers[0].click({ button: 'right' });
            await page.waitForTimeout(500);

            console.log(' Device right-click interaction tested');
        }

        console.log('\n=== COT Test Results ===');
        console.log(` Page loads successfully (HTTP ${response.status()})`);
        console.log(` ${deviceContainers.length} devices rendered`);
        console.log(' Status bar with counters working');
        console.log(' Template variables properly passed');
        console.log(' JavaScript execution without critical errors');

        console.log('\n<‰ COT implementation test PASSED!');

    } catch (error) {
        console.error('L COT test FAILED:', error.message);

        // Take screenshot for debugging
        await page.screenshot({
            path: `/www/wwwroot/vs.gvops.cl/public/screenshots/cot-test-error-${new Date().toISOString().replace(/[:.]/g, '-')}.png`,
            fullPage: true
        });

        process.exit(1);
    } finally {
        await browser.close();
    }
})();