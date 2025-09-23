const puppeteer = require('puppeteer');

async function testDebugToolbar() {
    const browser = await puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--disable-gpu'
        ]
    });

    try {
        const page = await browser.newPage();

        console.log('🧪 Testing Symfony Debug Toolbar');
        console.log('==================================================');

        // Test 1: Without authentication
        console.log('📄 Test 1: Visiting /admin without login...');
        await page.goto('https://vs.gvops.cl/admin', { waitUntil: 'networkidle0' });

        const finalUrl1 = page.url();
        console.log(`🔗 Final URL: ${finalUrl1}`);

        // Check for debug toolbar elements
        const toolbarElements1 = await page.$$eval('[id*="sf-toolbar"], [class*="sf-toolbar"], [data-toggle="sf-dump"]',
            elements => elements.map(el => el.tagName + (el.id ? '#' + el.id : '') + (el.className ? '.' + el.className : ''))
        ).catch(() => []);

        console.log('🔍 Debug toolbar elements found:', toolbarElements1.length);
        if (toolbarElements1.length > 0) {
            console.log('   Elements:', toolbarElements1);
        }

        await page.screenshot({ path: '/tmp/test-no-auth.png' });

        // Test 2: With authentication
        console.log('\n📄 Test 2: Logging in and visiting /admin...');

        // Go to login page
        await page.goto('https://vs.gvops.cl/login', { waitUntil: 'networkidle0' });

        // Fill login form
        await page.type('input[name="_username"]', 'jnacaratto');
        await page.type('input[name="_password"]', 'Pampa1004');

        // Submit form
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle0' });

        console.log('✅ Login completed');

        // Navigate to admin
        await page.goto('https://vs.gvops.cl/admin', { waitUntil: 'networkidle0' });

        const finalUrl2 = page.url();
        console.log(`🔗 Final URL: ${finalUrl2}`);

        // Wait a bit for toolbar to load
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Check for debug toolbar elements (more comprehensive)
        const toolbarElements2 = await page.evaluate(() => {
            const selectors = [
                '[id*="sf-toolbar"]',
                '[class*="sf-toolbar"]',
                '[data-toggle="sf-dump"]',
                '.sf-toolbar',
                '#sfwdt',
                '.sf-profiler-toolbar',
                '[data-sfid]'
            ];

            let found = [];
            selectors.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    found.push({
                        selector: selector,
                        tag: el.tagName,
                        id: el.id || '',
                        class: el.className || '',
                        text: el.textContent ? el.textContent.substring(0, 50) : ''
                    });
                });
            });
            return found;
        });

        console.log('🔍 Debug toolbar elements found:', toolbarElements2.length);
        if (toolbarElements2.length > 0) {
            console.log('   Detailed elements:');
            toolbarElements2.forEach((el, i) => {
                console.log(`   ${i+1}. ${el.tag}${el.id ? '#' + el.id : ''}${el.class ? '.' + el.class.replace(/\s+/g, '.') : ''}`);
                if (el.text.trim()) {
                    console.log(`      Text: "${el.text.trim()}"`);
                }
            });
        }

        // Check for specific debug toolbar indicators in the page source
        const pageContent = await page.content();
        const hasProfilerScript = pageContent.includes('/_wdt/') || pageContent.includes('_profiler');
        const hasToolbarCss = pageContent.includes('sf-toolbar') || pageContent.includes('profiler');

        console.log('🔍 Page source analysis:');
        console.log(`   Contains profiler references: ${hasProfilerScript}`);
        console.log(`   Contains toolbar CSS: ${hasToolbarCss}`);

        await page.screenshot({ path: '/tmp/test-with-auth.png' });

        console.log('\n📸 Screenshots saved:');
        console.log('   Without auth: /tmp/test-no-auth.png');
        console.log('   With auth: /tmp/test-with-auth.png');

        console.log('\n📊 Summary:');
        console.log(`   Without login: ${toolbarElements1.length} toolbar elements`);
        console.log(`   With login: ${toolbarElements2.length} toolbar elements`);
        console.log(`   Expected: 0 without login, >0 with login`);

        if (toolbarElements1.length === 0 && toolbarElements2.length > 0) {
            console.log('✅ Debug toolbar working correctly!');
        } else if (toolbarElements1.length === 0 && toolbarElements2.length === 0) {
            console.log('⚠️  Debug toolbar not appearing even with authentication');
        } else if (toolbarElements1.length > 0) {
            console.log('❌ Debug toolbar appearing without authentication');
        }

    } catch (error) {
        console.error('❌ Error during test:', error.message);
    } finally {
        await browser.close();
    }
}

testDebugToolbar().catch(console.error);