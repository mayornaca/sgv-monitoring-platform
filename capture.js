const puppeteer = require('puppeteer');

async function capture(url) {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });
    
    try {
        const response = await page.goto(url, { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });
        
        console.log(`Status: ${response.status()}`);
        console.log(`URL: ${page.url()}`);
        
        // Capturar screenshot
        await page.screenshot({ 
            path: '/tmp/screenshot.png',
            fullPage: true 
        });
        console.log('Screenshot saved to /tmp/screenshot.png');
        
        // Obtener el HTML
        const html = await page.content();
        console.log('\n--- PAGE CONTENT ---');
        console.log(html.substring(0, 2000));
        
        // Buscar errores
        const errorText = await page.evaluate(() => {
            const error = document.querySelector('.exception-message');
            return error ? error.textContent : null;
        });
        
        if (errorText) {
            console.log('\n--- ERROR FOUND ---');
            console.log(errorText);
        }
        
    } catch (error) {
        console.error('Error:', error);
    }
    
    await browser.close();
}

// Usar el URL pasado como argumento o default
const url = process.argv[2] || 'https://vs.gvops.cl/app_dev.php/user/index';
capture(url);