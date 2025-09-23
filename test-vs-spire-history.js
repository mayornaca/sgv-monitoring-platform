const puppeteer = require('puppeteer');

async function testVSSpireHistory(username, password) {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });

    try {
        console.log('=== Testing VS Spire History Page ===');

        // Go to login page
        await page.goto('https://vs.gvops.cl/app_dev.php/login', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        console.log('1. Login page loaded');

        // Check for errors on login page
        const loginContent = await page.content();
        if (loginContent.includes('exception') || loginContent.includes('error')) {
            console.log('ERROR: Login page has errors');
            await page.screenshot({ path: '/tmp/vs-login-error.png' });
            return;
        }

        // Perform login
        await page.type('input[name="_username"]', username);
        await page.type('input[name="_password"]', password);
        await page.click('button[type="submit"]');

        // Wait for navigation
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log('2. Login successful, current URL:', page.url());

        // Go to VS spire history page with September 16 data
        const vsSpireUrl = 'https://vs.gvops.cl/admin?routeName=cot_spire_history_vs_min&fechaInicio=16-09-2025 15:00:00&fechaTermino=16-09-2025 15:59:59';
        await page.goto(vsSpireUrl, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        console.log('3. VS Spire History page loaded:', page.url());

        // Take initial screenshot
        await page.screenshot({
            path: `/tmp/vs-spire-initial-${new Date().toISOString().replace(/[:.]/g, '-')}.png`,
            fullPage: true
        });
        console.log('4. Initial screenshot captured');

        // Check for page content and errors
        const content = await page.content();

        // Look for specific elements
        const pageAnalysis = await page.evaluate(() => {
            const results = {
                hasTimeline: !!document.querySelector('#timeline1'),
                timelineContent: '',
                hasFilters: !!document.querySelector('.card-header'),
                hasData: false,
                errors: [],
                consoleLogs: []
            };

            const timeline = document.querySelector('#timeline1');
            if (timeline) {
                results.timelineContent = timeline.innerHTML.substring(0, 200);
                results.hasData = !timeline.innerHTML.includes('No hay datos') &&
                                !timeline.innerHTML.includes('Cargando...');
            }

            // Check for visible errors
            const errorElements = document.querySelectorAll('.alert-danger, .exception-message');
            errorElements.forEach(el => {
                results.errors.push(el.textContent.trim());
            });

            return results;
        });

        console.log('5. Page Analysis Results:');
        console.log('   - Has timeline element:', pageAnalysis.hasTimeline);
        console.log('   - Timeline content preview:', pageAnalysis.timelineContent);
        console.log('   - Has filters:', pageAnalysis.hasFilters);
        console.log('   - Has data rendered:', pageAnalysis.hasData);
        console.log('   - Errors found:', pageAnalysis.errors.length);

        if (pageAnalysis.errors.length > 0) {
            console.log('   - Error details:', pageAnalysis.errors);
        }

        // Wait a bit longer for any async data loading
        console.log('6. Waiting for potential async data loading...');
        await page.waitForTimeout(5000);

        // Check console errors
        const logs = [];
        page.on('console', msg => logs.push(`${msg.type()}: ${msg.text()}`));
        page.on('pageerror', error => logs.push(`PAGE ERROR: ${error.message}`));

        // Click "Consultar" button to test form submission
        try {
            const consultarBtn = await page.$('button[type="submit"]:contains("Consultar")');
            if (consultarBtn) {
                console.log('7. Testing "Consultar" button...');
                await consultarBtn.click();
                await page.waitForTimeout(3000);

                // Check if we were redirected (which was the reported issue)
                const currentUrl = page.url();
                if (currentUrl.includes('spire_history_vs_min')) {
                    console.log('   - SUCCESS: Stayed on VS page after Consultar');
                } else {
                    console.log('   - ISSUE: Redirected to:', currentUrl);
                }
            }
        } catch (e) {
            console.log('7. Could not find or click Consultar button:', e.message);
        }

        // Take final screenshot
        await page.screenshot({
            path: `/tmp/vs-spire-final-${new Date().toISOString().replace(/[:.]/g, '-')}.png`,
            fullPage: true
        });
        console.log('8. Final screenshot captured');

        // Final analysis
        const finalAnalysis = await page.evaluate(() => {
            const timeline = document.querySelector('#timeline1');
            return {
                finalUrl: window.location.href,
                timelineHtml: timeline ? timeline.innerHTML : 'NO TIMELINE',
                isDataRendered: timeline && !timeline.innerHTML.includes('No hay datos') &&
                               !timeline.innerHTML.includes('Cargando...') &&
                               timeline.innerHTML.includes('svg')
            };
        });

        console.log('9. Final Results:');
        console.log('   - Final URL:', finalAnalysis.finalUrl);
        console.log('   - Data successfully rendered:', finalAnalysis.isDataRendered);
        console.log('   - Console logs:', logs.slice(0, 5)); // Show first 5 logs

        return {
            success: finalAnalysis.isDataRendered,
            url: finalAnalysis.finalUrl,
            errors: pageAnalysis.errors,
            logs: logs
        };

    } catch (error) {
        console.error('ERROR during test:', error);
        await page.screenshot({
            path: `/tmp/vs-spire-error-${new Date().toISOString().replace(/[:.]/g, '-')}.png`
        });
        return { success: false, error: error.message };
    }

    await browser.close();
}

// Get credentials from command line or use defaults
const username = process.argv[2] || 'admin';
const password = process.argv[3] || 'admin';

console.log(`Testing VS Spire History with user: ${username}`);
testVSSpireHistory(username, password)
    .then(result => {
        console.log('\n=== TEST COMPLETE ===');
        console.log('Success:', result.success);
        if (!result.success) {
            console.log('Issues found. Check screenshots in /tmp/ directory');
        }
    })
    .catch(console.error);