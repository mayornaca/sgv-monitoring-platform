const puppeteer = require('puppeteer');

async function testDashboard() {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();

        // Set viewport
        await page.setViewport({ width: 1280, height: 720 });

        console.log('1. Navigating to login page...');
        await page.goto('https://vs.gvops.cl/login', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Login
        console.log('2. Logging in...');
        await page.type('#username', 'jnacaratto');
        await page.type('#password', 'Pampa1004');
        await page.click('button[type="submit"]');

        // Wait for navigation after login
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log('3. Login successful, navigating to dashboard...');

        // Go to COT Dashboard
        await page.goto('https://vs.gvops.cl/admin?routeName=cot_dashboard', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        console.log('4. Checking page content...');

        // Take screenshot
        await page.screenshot({
            path: '/tmp/cot-dashboard.png',
            fullPage: true
        });

        // Check for errors
        const pageTitle = await page.title();
        console.log('Page title:', pageTitle);

        // Check for Symfony error
        const errorExists = await page.$('.exception-message') !== null;
        if (errorExists) {
            const errorMessage = await page.$eval('.exception-message', el => el.textContent);
            console.error('❌ Symfony Exception Found:', errorMessage.trim());

            // Get more error details if available
            const traceExists = await page.$('.trace') !== null;
            if (traceExists) {
                const firstTrace = await page.$eval('.trace-line:first-child', el => el.textContent);
                console.error('Stack trace:', firstTrace.trim());
            }
        } else {
            console.log('✅ No Symfony exceptions detected');
        }

        // Check if dashboard loaded (look for Highcharts)
        const highchartsExists = await page.evaluate(() => {
            return typeof Highcharts !== 'undefined';
        });

        if (highchartsExists) {
            console.log('✅ Highcharts loaded successfully');

            // Count charts on page
            const chartCount = await page.evaluate(() => {
                return Highcharts.charts.filter(c => c).length;
            });
            console.log(`✅ Found ${chartCount} Highcharts on the page`);
        } else {
            console.log('⚠️ Highcharts not found on page');
        }

        // Check for dashboard elements
        const resumenExists = await page.$('#resumen') !== null;
        if (resumenExists) {
            console.log('✅ Dashboard resumen section found');

            // Count device status cards
            const deviceCards = await page.$$('.bs-callout');
            console.log(`✅ Found ${deviceCards.length} device status cards`);
        } else {
            console.log('⚠️ Dashboard resumen section not found');
        }

        // Check for timer control
        const timerExists = await page.$('#timer-range-ajax-update') !== null;
        if (timerExists) {
            console.log('✅ Timer control found');
            const timerValue = await page.$eval('#timer-range-ajax-update', el => el.value);
            console.log(`   Timer value: ${timerValue} seconds`);
        } else {
            console.log('⚠️ Timer control not found');
        }

        // Get page HTML to check for any issues
        const bodyHTML = await page.evaluate(() => document.body.innerHTML.substring(0, 500));

        // Check if we got redirected or access denied
        if (bodyHTML.includes('Access Denied') || bodyHTML.includes('403')) {
            console.error('❌ Access denied to dashboard');
        } else if (bodyHTML.includes('500 Internal Server Error')) {
            console.error('❌ Internal server error');
        } else if (bodyHTML.includes('Monitor de dispositivos')) {
            console.log('✅ Dashboard title found - page loaded correctly');
        }

        console.log('\n📸 Screenshot saved to: /tmp/cot-dashboard.png');

    } catch (error) {
        console.error('Error during test:', error.message);
        if (error.message.includes('net::ERR_')) {
            console.error('Network error - check if site is accessible');
        }
    } finally {
        await browser.close();
    }
}

testDashboard();