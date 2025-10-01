<?php

// Simple test to hit BambooHR time off API and store response
$subdomain = 'valsoftaspire'; // Replace with your BambooHR subdomain
$apiKey = 'e941d193a2240d26f2b1c03c0d93dfe64aeb4ecf';      // Replace with your BambooHR API key

// Get data for 2025-09-23 only
$dateRanges = [
    "?start=2025-09-23&end=2025-09-23",
];

$results = [];

foreach ($dateRanges as $index => $dateRange) {
    $url = "https://{$subdomain}.bamboohr.com/api/gateway.php/{$subdomain}/v1/time_off/whos_out" . $dateRange;
    echo "Testing date range " . ($index + 1) . ": {$dateRange}\n";

    $headers = [
        'Authorization: Basic ' . base64_encode($apiKey . ':x'),
        'Accept: application/json',
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $results[] = [
        'date_range' => $dateRange,
        'url' => $url,
        'http_code' => $httpCode,
        'error' => $error,
        'response_size' => strlen($response),
        'response' => $response ? json_decode($response, true) : null
    ];

    echo "HTTP Code: {$httpCode}\n";
    echo "Error: " . ($error ?: 'None') . "\n";
    echo "Response size: " . strlen($response) . " bytes\n";
    echo "---\n";
}

// Create final response data
$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'subdomain' => $subdomain,
    'results' => $results
];

// Save to JSON file
file_put_contents('api_response.json', json_encode($result, JSON_PRETTY_PRINT));

echo "Test completed. Response saved to api_response.json\n";