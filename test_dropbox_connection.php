<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

echo "=== Testing Dropbox Connection ===\n\n";

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');
if (file_exists(__DIR__ . '/.env.dev')) {
    $dotenv->load(__DIR__ . '/.env.dev');
}

// Check if all required env vars are set
$required = ['DROPBOX_ACCESS_TOKEN', 'DROPBOX_REFRESH_TOKEN', 'DROPBOX_APP_KEY', 'DROPBOX_APP_SECRET'];
$missing = [];

foreach ($required as $var) {
    if (empty($_ENV[$var])) {
        $missing[] = $var;
    }
}

if (!empty($missing)) {
    echo "❌ Missing required environment variables:\n";
    foreach ($missing as $var) {
        echo "   - $var\n";
    }
    exit(1);
}

echo "✓ All 4 environment variables are set\n\n";

// Test token refresh
echo "Testing token refresh...\n";

$ch = curl_init('https://api.dropbox.com/oauth2/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $_ENV['DROPBOX_REFRESH_TOKEN'],
        'client_id' => $_ENV['DROPBOX_APP_KEY'],
        'client_secret' => $_ENV['DROPBOX_APP_SECRET']
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Token refresh failed with HTTP $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$data = json_decode($response, true);

if (!isset($data['access_token'])) {
    echo "❌ Invalid response from Dropbox\n";
    echo "Response: $response\n";
    exit(1);
}

echo "✅ Token refresh successful!\n";
echo "   - New access token received (length: " . strlen($data['access_token']) . ")\n";
echo "   - Expires in: " . ($data['expires_in'] ?? 'unknown') . " seconds\n\n";

// Test API call with new token
echo "Testing Dropbox API with refreshed token...\n";

$ch = curl_init('https://api.dropboxapi.com/2/users/get_current_account');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $data['access_token'],
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => 'null'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ API call failed with HTTP $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$account = json_decode($response, true);

echo "✅ Dropbox API connection successful!\n";
echo "   - Account: " . ($account['name']['display_name'] ?? 'Unknown') . "\n";
echo "   - Email: " . ($account['email'] ?? 'Unknown') . "\n\n";

echo "========================================\n";
echo "🎉 All tests passed!\n";
echo "========================================\n";
echo "Your Dropbox integration is properly configured.\n";
echo "The application will automatically refresh tokens when needed.\n";
