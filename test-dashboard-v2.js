const puppeteer = require('puppeteer');

async function testDashboard() {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 720 });

        console.log('1. Navigating to login page...');
        await page.goto('https://vs.gvops.cl/login', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Clear fields first and then type
        console.log('2. Filling login form...');
        // Wait for form fields to be available
        await page.waitForSelector('input[name="_username"]');
        await page.waitForSelector('input[name="_password"]');

        // Clear and type in correct fields
        await page.evaluate(() => {
            document.querySelector('input[name="_username"]').value = '';
            document.querySelector('input[name="_password"]').value = '';
        });

        await page.type('input[name="_username"]', 'jnacaratto');
        await page.type('input[name="_password"]', 'Pampa1004');

        // Take screenshot of login form filled
        await page.screenshot({
            path: '/tmp/login-filled.png'
        });
        console.log('   Login form filled, screenshot saved to /tmp/login-filled.png');

        // Click submit
        console.log('3. Submitting login...');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]')
        ]);

        // Check where we are after login
        const currentUrl = page.url();
        console.log('4. After login URL:', currentUrl);

        if (currentUrl.includes('login')) {
            console.log('❌ Still on login page - authentication may have failed');

            // Check for error messages
            const errorMessage = await page.$('.alert-danger');
            if (errorMessage) {
                const errorText = await page.$eval('.alert-danger', el => el.textContent);
                console.log('   Error message:', errorText.trim());
            }
        } else {
            console.log('✅ Login successful');
        }

        // Try to navigate directly to dashboard
        console.log('5. Navigating to COT Dashboard...');
        const response = await page.goto('https://vs.gvops.cl/cot/dashboard', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        const statusCode = response.status();
        console.log('   HTTP Status:', statusCode);

        // Take screenshot
        await page.screenshot({
            path: '/tmp/cot-dashboard-final.png',
            fullPage: true
        });
        console.log('6. Screenshot saved to /tmp/cot-dashboard-final.png');

        // Check page content
        const pageContent = await page.content();

        // Check for specific indicators
        if (pageContent.includes('Exception')) {
            console.log('❌ Exception found on page');

            // Try to get exception message
            const exceptionMsg = await page.$('.exception-message');
            if (exceptionMsg) {
                const msg = await page.$eval('.exception-message', el => el.textContent);
                console.log('   Exception:', msg.trim().substring(0, 200));
            }
        }

        if (pageContent.includes('vi_resumen_estado_dispositivos')) {
            console.log('✅ Dashboard query found in page');
        }

        if (pageContent.includes('Highcharts')) {
            console.log('✅ Highcharts library loaded');
        }

        if (pageContent.includes('Monitor de dispositivos') || pageContent.includes('Resumen del monitor')) {
            console.log('✅ Dashboard title found');
        }

        // Check for database error
        if (pageContent.includes('1045') || pageContent.includes('Access denied')) {
            console.log('❌ Database access error detected');
        }

        // Try to evaluate Highcharts presence
        const hasHighcharts = await page.evaluate(() => {
            return typeof window.Highcharts !== 'undefined';
        });

        if (hasHighcharts) {
            const chartCount = await page.evaluate(() => {
                if (window.Highcharts && window.Highcharts.charts) {
                    return window.Highcharts.charts.filter(c => c).length;
                }
                return 0;
            });
            console.log(`✅ Highcharts active with ${chartCount} charts`);
        }

        // Check for dashboard elements
        const hasResumen = await page.$('#resumen');
        if (hasResumen) {
            console.log('✅ Dashboard #resumen section exists');

            const cards = await page.$$('.bs-callout');
            console.log(`   Found ${cards.length} device cards`);
        }

        // Check final page title
        const pageTitle = await page.title();
        console.log('7. Final page title:', pageTitle);

    } catch (error) {
        console.error('Error during test:', error.message);
    } finally {
        await browser.close();
    }
}

testDashboard();