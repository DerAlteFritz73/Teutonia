<?php

/**
 * One-off helper to re-authorize the Dropbox app and mint a fresh refresh token.
 *
 * Needed after enabling a new scope (e.g. files.content.write) in the Dropbox
 * App Console, because Dropbox grants scopes at authorization time — an existing
 * refresh token never gains a newly added scope. You must re-run the OAuth flow
 * and replace DROPBOX_REFRESH_TOKEN in .env.local with the value this prints.
 *
 * Usage (run on the environment whose .env.local holds the app credentials):
 *
 *   1) php bin/dropbox-reauth.php url
 *        → prints the authorize URL. Open it, approve, copy the code shown.
 *
 *   2) php bin/dropbox-reauth.php exchange <CODE>
 *        → exchanges the code and prints the new refresh + access tokens.
 *
 * The app key/secret are read from .env.local; nothing is written automatically —
 * you copy the new DROPBOX_REFRESH_TOKEN into .env.local yourself.
 */

$root = dirname(__DIR__);
$envFile = $root . '/.env.local';

if (!is_readable($envFile)) {
    fwrite(STDERR, "Cannot read $envFile\n");
    exit(1);
}

/**
 * Minimal .env parser for the two keys we need (KEY=value, value optionally quoted).
 */
function envValue(string $file, string $key): ?string
{
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = ltrim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) {
            return trim(trim($v), "\"'");
        }
    }
    return null;
}

$appKey = envValue($envFile, 'DROPBOX_APP_KEY');
$appSecret = envValue($envFile, 'DROPBOX_APP_SECRET');

if (!$appKey || !$appSecret) {
    fwrite(STDERR, "DROPBOX_APP_KEY / DROPBOX_APP_SECRET not found in $envFile\n");
    exit(1);
}

$command = $argv[1] ?? 'url';

if ($command === 'url') {
    $url = 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
        'client_id'         => $appKey,
        'response_type'     => 'code',
        'token_access_type' => 'offline', // required to receive a refresh token
    ]);

    echo "Open this URL, approve access, then copy the authorization code:\n\n";
    echo "  $url\n\n";
    echo "Then run:\n\n";
    echo "  php bin/dropbox-reauth.php exchange <CODE>\n";
    exit(0);
}

if ($command === 'exchange') {
    $code = $argv[2] ?? '';
    if ($code === '') {
        fwrite(STDERR, "Usage: php bin/dropbox-reauth.php exchange <CODE>\n");
        exit(1);
    }

    $ch = curl_init('https://api.dropbox.com/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $appKey,
            'client_secret' => $appSecret,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        fwrite(STDERR, "Request failed: $curlErr\n");
        exit(1);
    }

    if ($httpCode !== 200) {
        fwrite(STDERR, "Token exchange failed (HTTP $httpCode):\n$response\n");
        fwrite(STDERR, "\nNote: authorization codes are single-use and short-lived — if it expired, get a fresh one with `url`.\n");
        exit(1);
    }

    $data = json_decode($response, true);
    if (!isset($data['refresh_token'])) {
        fwrite(STDERR, "No refresh_token in response (did you use token_access_type=offline?):\n$response\n");
        exit(1);
    }

    $scopes = $data['scope'] ?? '(not reported)';

    echo "Success. Update .env.local with the new refresh token:\n\n";
    echo "  DROPBOX_REFRESH_TOKEN=" . $data['refresh_token'] . "\n";
    if (isset($data['access_token'])) {
        echo "  DROPBOX_ACCESS_TOKEN=" . $data['access_token'] . "\n";
    }
    echo "\nGranted scopes: $scopes\n";
    echo "Confirm 'files.content.write' is listed above; if not, the scope wasn't enabled in the App Console before authorizing.\n";
    exit(0);
}

fwrite(STDERR, "Unknown command '$command'. Use 'url' or 'exchange <CODE>'.\n");
exit(1);
