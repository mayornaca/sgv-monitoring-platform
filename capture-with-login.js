const puppeteer = require('puppeteer');

async function captureWithLogin(username, password) {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });
    
    try {
        // Ir al login
        await page.goto('https://vs.gvops.cl/app_dev.php/login', { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });
        
        // Capturar screenshot antes de login
        await page.screenshot({ 
            path: '/tmp/before-login.png',
            fullPage: true 
        });
        console.log('Screenshot before login saved to /tmp/before-login.png');
        
        // Buscar el contenido HTML
        const pageContent = await page.content();
        if (pageContent.includes('exception') || pageContent.includes('error')) {
            console.log('Page contains error/exception');
            const errorText = await page.evaluate(() => {
                const error = document.querySelector('.exception-message');
                return error ? error.textContent : 'No specific error message found';
            });
            console.log('Error:', errorText);
            return;
        }
        
        // Hacer login - usar los nombres correctos de los campos
        await page.type('input[name="_username"]', username);
        await page.type('input[name="_password"]', password);
        await page.click('button[type="submit"]');
        
        // Esperar navegación
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        
        console.log('After login URL:', page.url());
        
        // Ahora ir a user/index
        await page.goto('https://vs.gvops.cl/app_dev.php/user/index', { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });
        
        console.log('User index URL:', page.url());
        console.log('Status:', page.url().includes('user/index') ? 'SUCCESS' : 'REDIRECTED');
        
        // Capturar screenshot
        await page.screenshot({ 
            path: '/tmp/user-index.png',
            fullPage: true 
        });
        console.log('Screenshot saved to /tmp/user-index.png');
        
        // Buscar errores
        const errorText = await page.evaluate(() => {
            const error = document.querySelector('.exception-message');
            return error ? error.textContent : null;
        });
        
        if (errorText) {
            console.log('\n--- ERROR FOUND ---');
            console.log(errorText);
        } else {
            console.log('\nNo errors found on page');
        }
        
    } catch (error) {
        console.error('Error:', error);
    }
    
    await browser.close();
}

// Necesitas proporcionar usuario y contraseña
const username = process.argv[2] || 'admin';
const password = process.argv[3] || 'admin';

console.log(`Attempting login with user: ${username}`);
captureWithLogin(username, password);