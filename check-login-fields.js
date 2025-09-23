const puppeteer = require('puppeteer');

async function checkLoginFields() {
    console.log('🔍 Checking Login Fields Structure');
    console.log('==================================================');

    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-web-security']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1920, height: 1080 });

        // Check current system first
        console.log('📄 Checking CURRENT system login (vs.gvops.cl)...');
        await page.goto('https://vs.gvops.cl/login', {
            waitUntil: 'networkidle2',
            timeout: 10000
        });

        const currentSystemFields = await page.evaluate(() => {
            const inputs = Array.from(document.querySelectorAll('input'));
            const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]'));

            return {
                inputs: inputs.map(input => ({
                    type: input.type,
                    name: input.name,
                    id: input.id,
                    placeholder: input.placeholder,
                    className: input.className
                })),
                buttons: buttons.map(btn => ({
                    type: btn.type,
                    textContent: btn.textContent.trim(),
                    className: btn.className
                })),
                formAction: document.querySelector('form') ? document.querySelector('form').action : 'No form found'
            };
        });

        console.log('Current system fields:');
        console.log('Inputs:', JSON.stringify(currentSystemFields.inputs, null, 2));
        console.log('Buttons:', JSON.stringify(currentSystemFields.buttons, null, 2));
        console.log('Form action:', currentSystemFields.formAction);

        // Check original system
        console.log('\n📄 Checking ORIGINAL system login (sgv.costaneranorte.cl)...');
        await page.goto('https://sgv.costaneranorte.cl/login', {
            waitUntil: 'networkidle2',
            timeout: 10000
        });

        const originalSystemFields = await page.evaluate(() => {
            const inputs = Array.from(document.querySelectorAll('input'));
            const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]'));

            return {
                inputs: inputs.map(input => ({
                    type: input.type,
                    name: input.name,
                    id: input.id,
                    placeholder: input.placeholder,
                    className: input.className
                })),
                buttons: buttons.map(btn => ({
                    type: btn.type,
                    textContent: btn.textContent.trim(),
                    className: btn.className
                })),
                formAction: document.querySelector('form') ? document.querySelector('form').action : 'No form found'
            };
        });

        console.log('Original system fields:');
        console.log('Inputs:', JSON.stringify(originalSystemFields.inputs, null, 2));
        console.log('Buttons:', JSON.stringify(originalSystemFields.buttons, null, 2));
        console.log('Form action:', originalSystemFields.formAction);

        // Compare
        console.log('\n🔍 COMPARISON:');
        const currentInputNames = currentSystemFields.inputs.map(i => i.name);
        const originalInputNames = originalSystemFields.inputs.map(i => i.name);

        console.log('Current system input names:', currentInputNames);
        console.log('Original system input names:', originalInputNames);
        console.log('Fields match:', JSON.stringify(currentInputNames) === JSON.stringify(originalInputNames));

    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        await browser.close();
    }
}

checkLoginFields().catch(console.error);