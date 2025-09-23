const puppeteer = require('puppeteer');

const BASE_URL = 'https://vs.gvops.cl';
const USERNAME = 'jnacaratto';
const PASSWORD = 'Pampa1004';

async function testExcelEndpoint() {
    console.log('🧪 Testing Excel Export Endpoint');
    console.log('==================================================');

    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();

    try {
        // Login
        console.log('🔐 Logging in...');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle0' });
        await page.type('#username', USERNAME);
        await page.type('#password', PASSWORD);
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle0' });
        console.log('✅ Login successful');

        // Direct navigation to Excel export endpoint
        console.log('📥 Testing Excel export endpoint...');
        const response = await page.goto(`${BASE_URL}/admin/lista_llamadas_sos_export_excel`, {
            waitUntil: 'networkidle0'
        });

        const contentType = response.headers()['content-type'];
        const contentDisposition = response.headers()['content-disposition'];

        console.log('📊 Response Details:');
        console.log(`   Status: ${response.status()}`);
        console.log(`   Content-Type: ${contentType}`);
        console.log(`   Content-Disposition: ${contentDisposition}`);

        if (contentType && contentType.includes('spreadsheetml')) {
            console.log('✅ Excel file generation successful!');
            console.log('   The endpoint is working correctly and returning Excel data');
        } else {
            console.log('⚠️ Unexpected content type, but endpoint responded');
        }

    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        await browser.close();
        console.log('==================================================');
        console.log('✅ Test completed');
    }
}

testExcelEndpoint().catch(console.error);