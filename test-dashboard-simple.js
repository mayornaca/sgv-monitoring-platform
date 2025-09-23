const puppeteer = require('puppeteer');

async function testDashboard() {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();

        // Set cookies to simulate logged-in session
        await page.setCookie({
            name: 'PHPSESSID',
            value: 'test123456',
            domain: 'vs.gvops.cl',
            path: '/',
            httpOnly: true,
            secure: true
        });

        console.log('Navigating directly to dashboard...');
        const response = await page.goto('https://vs.gvops.cl/cot/dashboard', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        console.log('HTTP Status:', response.status());
        console.log('URL after navigation:', page.url());

        const pageContent = await page.content();

        // Check for MySQL error
        if (pageContent.includes('1045') || pageContent.includes('Access denied')) {
            console.log('❌ MySQL Error 1045 detected!');

            // Try to get the error details
            const errorText = await page.evaluate(() => {
                const pre = document.querySelector('pre');
                return pre ? pre.textContent.substring(0, 500) : null;
            });

            if (errorText) {
                console.log('Error details:', errorText);
            }
        } else if (pageContent.includes('login')) {
            console.log('⚠️ Redirected to login (expected without session)');
        } else if (pageContent.includes('Exception')) {
            console.log('❌ Exception found on page');

            const exceptionText = await page.evaluate(() => {
                const exception = document.querySelector('.exception-message');
                return exception ? exception.textContent : null;
            });

            if (exceptionText) {
                console.log('Exception:', exceptionText);
            }
        } else {
            console.log('✅ No MySQL error detected');

            if (pageContent.includes('vi_resumen_estado_dispositivos')) {
                console.log('✅ Dashboard query found in response');
            }
        }

        await page.screenshot({
            path: '/tmp/dashboard-direct-test.png'
        });
        console.log('Screenshot saved to /tmp/dashboard-direct-test.png');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

testDashboard();