const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    try {
        const page = await browser.newPage();
        
        // Primero hacer login
        console.log('Navegando a página de login...');
        await page.goto('https://vs.gvops.cl/login', { waitUntil: 'networkidle2' });
        
        // Login
        await page.type('#username', 'jnacaratto');
        await page.type('#password', 'Pampa1004');
        await page.click('button[type="submit"]');
        
        // Esperar a que se complete el login
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log('Login exitoso');
        
        // Ahora navegar a la página de spire_history
        console.log('Navegando a /cot/spire_history...');
        await page.goto('https://vs.gvops.cl/cot/spire_history', { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });
        
        // Tomar screenshot
        await page.screenshot({ path: '/tmp/spire-history.png', fullPage: true });
        console.log('Screenshot guardado en /tmp/spire-history.png');
        
        // Obtener título de la página
        const title = await page.title();
        console.log('Título de la página:', title);
        
        // Verificar si hay errores
        const errorElement = await page.$('.exception-message');
        if (errorElement) {
            const errorText = await page.evaluate(el => el.textContent, errorElement);
            console.error('ERROR EN LA PÁGINA:', errorText);
        } else {
            console.log('✓ Página cargada sin errores de Symfony');
        }
        
        // Verificar si existe el formulario de filtros
        const filterForm = await page.$('#report_params');
        if (filterForm) {
            console.log('✓ Formulario de filtros encontrado');
        } else {
            console.log('✗ Formulario de filtros NO encontrado');
        }
        
        // Verificar si existe el área del timeline
        const timeline = await page.$('#timeline1');
        if (timeline) {
            console.log('✓ Área de timeline encontrada');
        } else {
            console.log('✗ Área de timeline NO encontrada');
        }
        
    } catch (error) {
        console.error('Error durante la prueba:', error);
    } finally {
        await browser.close();
    }
})();