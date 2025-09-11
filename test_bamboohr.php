<?php

/**
 * Simple test script for BambooHR integration
 * Run this from the command line: php test_bamboohr.php
 */

require_once 'vendor/autoload.php';

use App\Services\BambooHRService;

echo "=== BambooHR Integration Test ===\n\n";

try {
    // Test service instantiation
    echo "1. Testing service instantiation...\n";
    $service = new BambooHRService();
    echo "✓ Service created successfully\n\n";

    // Test connection
    echo "2. Testing BambooHR connection...\n";
    $connectionResult = $service->testConnection();
    
    if ($connectionResult['success']) {
        echo "✓ Connection successful\n";
        echo "  Status: " . $connectionResult['data']['status'] . "\n";
        echo "  Timestamp: " . $connectionResult['data']['timestamp'] . "\n";
    } else {
        echo "✗ Connection failed\n";
        echo "  Message: " . $connectionResult['message'] . "\n";
        if (isset($connectionResult['data']['error'])) {
            echo "  Error: " . $connectionResult['data']['error'] . "\n";
        }
    }
    echo "\n";

    // Test status
    echo "3. Testing status retrieval...\n";
    $statusResult = $service->getStatus();
    
    if ($statusResult['status'] === 'success') {
        echo "✓ Status retrieved successfully\n";
        echo "  Employees: " . $statusResult['data']['stats']['employees'] . "\n";
        echo "  Departments: " . $statusResult['data']['stats']['departments'] . "\n";
        echo "  Job Titles: " . $statusResult['data']['stats']['job_titles'] . "\n";
        echo "  Time Off: " . $statusResult['data']['stats']['time_off'] . "\n";
    } else {
        echo "✗ Status retrieval failed\n";
        echo "  Message: " . $statusResult['message'] . "\n";
    }
    echo "\n";

    echo "=== Test Complete ===\n";

} catch (Exception $e) {
    echo "✗ Test failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
