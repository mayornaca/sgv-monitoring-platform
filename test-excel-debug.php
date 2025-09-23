<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();

// First, get login page and CSRF token
$response = $client->request('GET', 'https://vs.gvops.cl/login');
$content = $response->getContent();

// Extract CSRF token
preg_match('/<input[^>]+name="_csrf_token"[^>]+value="([^"]+)"/', $content, $matches);
$csrfToken = $matches[1] ?? '';

if (!$csrfToken) {
    die("Could not extract CSRF token\n");
}

// Login
$response = $client->request('POST', 'https://vs.gvops.cl/login', [
    'body' => [
        '_username' => 'jnacaratto',
        '_password' => 'Pampa1004',
        '_csrf_token' => $csrfToken,
    ]
]);

// Get cookies
$cookies = [];
foreach ($response->getHeaders()['set-cookie'] ?? [] as $cookie) {
    if (strpos($cookie, 'PHPSESSID=') === 0) {
        $cookies[] = explode(';', $cookie)[0];
    }
}

$cookieHeader = implode('; ', $cookies);

// Test Excel endpoint
$response = $client->request('GET', 'https://vs.gvops.cl/admin/lista_llamadas_sos_export_excel', [
    'headers' => [
        'Cookie' => $cookieHeader,
    ]
]);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Headers:\n";
foreach ($response->getHeaders() as $name => $values) {
    echo "  $name: " . implode(', ', $values) . "\n";
}

$content = $response->getContent();
echo "\nContent Length: " . strlen($content) . " bytes\n";

// Check if it's Excel
if (strpos($content, 'PK') === 0) {
    echo "✅ This is a valid Excel file (ZIP format)\n";

    // Save it to test
    $filename = '/tmp/test_export_' . time() . '.xlsx';
    file_put_contents($filename, $content);
    echo "Saved to: $filename\n";
} else {
    echo "Content preview (first 500 chars):\n";
    echo substr($content, 0, 500) . "\n";
}