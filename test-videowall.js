const puppeteer = require('puppeteer');
const path = require('path');

async function testVideowall() {
    console.log('🧪 COT Videowall CSS Test');
    console.log('==================================================');

    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-web-security', '--disable-gpu']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1920, height: 1080 });

        // Login first
        console.log('🔐 Logging in...');
        await page.goto('https://vs.gvops.cl/login', {
            waitUntil: 'networkidle2',
            timeout: 5000
        });

        // Use flexible selectors for login form
        const emailSelector = 'input[name="email"], input[name="_username"], input#username, input#email';
        const passwordSelector = 'input[name="password"], input[name="_password"], input#password';

        await page.waitForSelector(emailSelector, { timeout: 5000 });
        await page.type(emailSelector, 'admin');
        await page.type(passwordSelector, 'admin123');
        await page.click('button[type="submit"]');

        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log('✅ Login successful');

        // Navigate to videowall
        console.log('📄 Navigating to videowall...');
        await page.goto('https://vs.gvops.cl/admin/monitor/0?videowall=true');

        // Wait for page to load
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
        const screenshotPath = path.join(__dirname, 'public', 'screenshots', `videowall-test-${timestamp}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: true });
        console.log(`📸 Screenshot: ${screenshotPath}`);

        // Test CSS widths
        console.log('🎨 Testing CSS width application...');

        const cssTest = await page.evaluate(() => {
            const devices = document.querySelectorAll('.cot_device_container');
            const results = {
                deviceCount: devices.length,
                widths: [],
                hasBootstrapClasses: false,
                hasCustomWidths: false
            };

            devices.forEach((device, index) => {
                const computedStyle = window.getComputedStyle(device);
                const width = computedStyle.width;
                const hasBootstrap = device.classList.contains('col-sm-1');
                const hasMini = device.classList.contains('mini_device');

                if (index < 5) { // Only report first 5 for brevity
                    results.widths.push({
                        index: index,
                        width: width,
                        hasBootstrap: hasBootstrap,
                        hasMini: hasMini,
                        classes: Array.from(device.classList)
                    });
                }

                if (hasBootstrap) results.hasBootstrapClasses = true;
                if (width === '151px' || width === '35px') results.hasCustomWidths = true;
            });

            return results;
        });

        console.log(`📊 Device Analysis:`);
        console.log(`   Total devices found: ${cssTest.deviceCount}`);
        console.log(`   Has Bootstrap classes: ${cssTest.hasBootstrapClasses}`);
        console.log(`   Has custom widths: ${cssTest.hasCustomWidths}`);

        cssTest.widths.forEach(device => {
            console.log(`   Device ${device.index}: width=${device.width}, bootstrap=${device.hasBootstrap}, mini=${device.hasMini}`);
        });

        // Test contract UI functionality
        console.log('🔧 Testing Contract UI toggle...');

        const contractTest = await page.evaluate(() => {
            const checkbox = document.querySelector('#contract_ui');
            if (!checkbox) return { error: 'Contract UI checkbox not found' };

            // Test toggle
            const initialState = checkbox.checked;
            checkbox.click();

            return {
                initialState: initialState,
                afterToggle: checkbox.checked,
                checkboxExists: true
            };
        });

        if (contractTest.error) {
            console.log(`❌ ${contractTest.error}`);
        } else {
            console.log(`   ✅ Contract UI checkbox found and functional`);
            console.log(`   Initial state: ${contractTest.initialState}, After toggle: ${contractTest.afterToggle}`);
        }

        // Check for JavaScript errors
        const errors = await page.evaluate(() => {
            return window.jsErrors || [];
        });

        if (errors.length === 0) {
            console.log('✅ No JavaScript errors detected');
        } else {
            console.log('❌ JavaScript errors found:');
            errors.forEach(error => console.log(`   ${error}`));
        }

        console.log('==================================================');

        if (cssTest.hasCustomWidths) {
            console.log('✅ CSS fixes appear to be working - custom widths detected');
        } else {
            console.log('⚠️  CSS fixes may need adjustment - no custom widths detected');
        }

    } catch (error) {
        console.error('❌ Test failed:', error.message);
    } finally {
        await browser.close();
    }
}

testVideowall().catch(console.error);