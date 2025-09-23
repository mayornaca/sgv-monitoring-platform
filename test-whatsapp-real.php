<?php
// Test directo con cURL para WhatsApp

$accessToken = 'EAAR912L41MoBOx95sDl6qhXCcT9oqyvbl1ibxSmC7BG1YUHiU6BSyqtYL3fWFZC8CbVWPtToH2ara3ASrClorj3Grqg5FrkNyfyqZBSN5YPxAeToXFNCpUqerqZCueJuJC1i0t0E3BZA0gpVfrZCu7z4cuW77Up0cCG3ndAzGcopWuaQqUiG1CrhsMhwWqvBPZAwZDZD';
$phoneNumberId = '651420641396348';
$apiUrl = "https://graph.facebook.com/v22.0/{$phoneNumberId}/messages";

// Usar el formato exacto del template prometheus_alert_firing
$data = [
    'messaging_product' => 'whatsapp',
    'to' => '56972126016', // Sin el +
    'type' => 'template',
    'template' => [
        'name' => 'prometheus_alert_firing',
        'language' => [
            'code' => 'es'
        ],
        'components' => [
            [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => 'DiskSpaceAlert' // {{1}} - Nombre de alerta
                    ],
                    [
                        'type' => 'text',
                        'text' => 'critical' // {{2}} - Severidad
                    ],
                    [
                        'type' => 'text',
                        'text' => 'El disco /var está al 95% de capacidad' // {{3}} - Resumen
                    ],
                    [
                        'type' => 'text',
                        'text' => 'sgv-server-01' // {{4}} - Afectado
                    ]
                ]
            ]
        ]
    ]
];

$headers = [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
if ($error) {
    echo "Error: $error\n";
}

$result = json_decode($response, true);
if (isset($result['messages'][0]['id'])) {
    echo "\n✅ Mensaje enviado exitosamente!\n";
    echo "Message ID: " . $result['messages'][0]['id'] . "\n";
} else {
    echo "\n❌ Error al enviar mensaje\n";
    if (isset($result['error'])) {
        echo "Error: " . json_encode($result['error'], JSON_PRETTY_PRINT) . "\n";
    }
}