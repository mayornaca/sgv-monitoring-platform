const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    
    try {
        // Test login page
        console.log('Testing login page...');
        await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle0' });
        
        // Take screenshot of login page
        await page.screenshot({ path: '/tmp/login-page.png' });
        console.log('Login page screenshot saved to /tmp/login-page.png');
        
        // Check if login form exists
        const loginForm = await page.$('form');
        if (loginForm) {
            console.log('✅ Login form found');
            
            // Fill in login credentials
            await page.type('#inputEmail', 'admin@example.com');
            await page.type('#inputPassword', 'Admin123!');
            
            // Take screenshot before submit
            await page.screenshot({ path: '/tmp/login-filled.png' });
            
            // Submit form
            await page.click('button[type="submit"]');
            await page.waitForNavigation({ waitUntil: 'networkidle0' });
            
            // Check current URL
            const currentUrl = page.url();
            console.log('After login, URL:', currentUrl);
            
            // Take screenshot after login
            await page.screenshot({ path: '/tmp/after-login.png' });
            
            if (currentUrl.includes('/login')) {
                // Still on login page, check for error
                const error = await page.$('.alert-danger');
                if (error) {
                    const errorText = await page.evaluate(el => el.textContent, error);
                    console.log('❌ Login error:', errorText);
                }
            } else {
                console.log('✅ Login successful! Redirected to:', currentUrl);
            }
        } else {
            console.log('❌ Login form not found');
        }
        
    } catch (error) {
        console.error('Error:', error);
        await page.screenshot({ path: '/tmp/error-page.png' });
    }
    
    await browser.close();
})();