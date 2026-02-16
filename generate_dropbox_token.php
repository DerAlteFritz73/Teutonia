<?php

echo "=== Dropbox Token Generator ===\n\n";
echo "This will help you generate a Dropbox access token and refresh token.\n\n";

echo "Step 1: Go to your Dropbox App settings\n";
echo "URL: https://www.dropbox.com/developers/apps\n\n";

echo "Step 2: Copy your App Key and App Secret\n";
echo "They are in the Settings tab under 'App key' and 'App secret'\n\n";

echo "Enter your App Key: ";
$appKey = trim(fgets(STDIN));

echo "Enter your App Secret: ";
$appSecret = trim(fgets(STDIN));

if (empty($appKey) || empty($appSecret)) {
    echo "\n❌ App Key and App Secret are required!\n";
    exit(1);
}

echo "\n\n=== OAuth Authorization URL ===\n";
$authUrl = "https://www.dropbox.com/oauth2/authorize?client_id={$appKey}&token_access_type=offline&response_type=code";
echo "\n1. Open this URL in your browser:\n";
echo "{$authUrl}\n\n";
echo "2. Click 'Allow' to authorize the app\n";
echo "3. Copy the authorization code from the URL\n\n";

echo "Enter the authorization code: ";
$authCode = trim(fgets(STDIN));

if (empty($authCode)) {
    echo "\n❌ Authorization code is required!\n";
    exit(1);
}

echo "\n\nExchanging code for access token...\n";

$ch = curl_init('https://api.dropboxapi.com/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'code' => $authCode,
    'grant_type' => 'authorization_code',
    'client_id' => $appKey,
    'client_secret' => $appSecret,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "\n❌ Error getting token:\n";
    echo $response . "\n";
    exit(1);
}

$data = json_decode($response, true);

if (isset($data['access_token']) && isset($data['refresh_token'])) {
    echo "\n✅ SUCCESS! Your tokens have been generated:\n\n";

    echo "========================================\n";
    echo "Add these to your .env.local file:\n";
    echo "========================================\n\n";

    echo "# Dropbox App Credentials\n";
    echo "DROPBOX_APP_KEY={$appKey}\n";
    echo "DROPBOX_APP_SECRET={$appSecret}\n\n";

    echo "# Dropbox Tokens\n";
    echo "DROPBOX_ACCESS_TOKEN={$data['access_token']}\n";
    echo "DROPBOX_REFRESH_TOKEN={$data['refresh_token']}\n\n";

    echo "========================================\n";
    echo "Token Details:\n";
    echo "========================================\n";
    echo "Access Token expires in: " . ($data['expires_in'] ?? 14400) . " seconds (4 hours)\n";
    echo "Refresh Token: Never expires (use it to get new access tokens)\n\n";

    echo "⚠️  IMPORTANT:\n";
    echo "   - The access token expires after 4 hours\n";
    echo "   - The refresh token is used automatically to get new access tokens\n";
    echo "   - Keep all 4 values in your .env.local file\n";
    echo "   - Never commit these tokens to git!\n\n";
} else {
    echo "\n❌ Error: Response missing access_token or refresh_token\n";
    echo "Make sure you used the correct authorization URL with token_access_type=offline\n";
    echo "\nFull response:\n";
    echo $response . "\n";
}
