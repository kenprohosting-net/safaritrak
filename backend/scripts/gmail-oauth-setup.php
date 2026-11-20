<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/env.php';

$clientId = trim(getenv('GOOGLE_CLIENT_ID') ?: '');
$clientSecret = trim(getenv('GOOGLE_CLIENT_SECRET') ?: '');
$redirectUri = trim(getenv('GOOGLE_REDIRECT_URI') ?: '');
$redirect = parse_url($redirectUri);

if ($clientId === '' || $clientSecret === ''
    || !is_array($redirect)
    || ($redirect['scheme'] ?? '') !== 'http'
    || strtolower((string) ($redirect['host'] ?? '')) !== '127.0.0.1'
    || !isset($redirect['port'])
    || !in_array($redirect['path'] ?? '', ['', '/'], true)
    || isset($redirect['user'])
    || isset($redirect['pass'])
    || isset($redirect['query'])
    || isset($redirect['fragment'])) {
    fwrite(STDERR, "Configure GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and a loopback GOOGLE_REDIRECT_URI such as http://127.0.0.1:53682/ in backend/config/env.local.php.\n");
    exit(1);
}
if (!function_exists('curl_init')) {
    fwrite(STDERR, "The PHP cURL extension is required for OAuth token exchange.\n");
    exit(1);
}

$port = (int) $redirect['port'];
$server = stream_socket_server('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage);
if ($server === false) {
    fwrite(STDERR, "Could not listen on the configured loopback redirect port. Check that the port is available.\n");
    exit(1);
}

$state = bin2hex(random_bytes(32));
$authorizeUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'https://www.googleapis.com/auth/gmail.send',
    'access_type' => 'offline',
    'prompt' => 'consent',
    'include_granted_scopes' => 'true',
    'state' => $state,
], '', '&', PHP_QUERY_RFC3986);

fwrite(STDOUT, "Open this Google authorization URL on this computer:\n{$authorizeUrl}\n\nWaiting up to 3 minutes for the local OAuth callback...\n");
$connection = stream_socket_accept($server, 180, $peerAddress);
fclose($server);
if ($connection === false) {
    fwrite(STDERR, "OAuth authorization timed out. Run this command again to retry.\n");
    exit(1);
}

$peerIp = is_string($peerAddress) ? (string) parse_url('tcp://' . $peerAddress, PHP_URL_HOST) : '';
$requestLine = fgets($connection, 8192);
$requestParts = is_string($requestLine) ? explode(' ', trim($requestLine), 3) : [];
$requestPath = $requestParts[1] ?? '';
$query = [];
if (($requestParts[0] ?? '') === 'GET' && str_starts_with($requestPath, '/')) {
    parse_str((string) parse_url($requestPath, PHP_URL_QUERY), $query);
}
while (($header = fgets($connection, 8192)) !== false && rtrim($header, "\r\n") !== '') {
}

$callbackValid = in_array($peerIp, ['127.0.0.1', '::1'], true)
    && ($requestParts[0] ?? '') === 'GET'
    && parse_url($requestPath, PHP_URL_PATH) === '/'
    && isset($query['state'])
    && is_string($query['state'])
    && hash_equals($state, $query['state']);
if (!$callbackValid || isset($query['error']) || !isset($query['code']) || !is_string($query['code'])) {
    gmail_oauth_setup_respond($connection, 400, 'Authorization was not completed. Close this page and check the setup command.');
    fclose($connection);
    fwrite(STDERR, "OAuth authorization was not completed or the local state check failed.\n");
    exit(1);
}

$tokenHandle = curl_init('https://oauth2.googleapis.com/token');
if ($tokenHandle === false) {
    gmail_oauth_setup_respond($connection, 500, 'SafariTrak could not complete authorization. Check the local setup command.');
    fclose($connection);
    fwrite(STDERR, "Could not initialize the OAuth token request.\n");
    exit(1);
}
curl_setopt_array($tokenHandle, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $query['code'],
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ], '', '&', PHP_QUERY_RFC3986),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$tokenBody = curl_exec($tokenHandle);
$tokenStatus = (int) curl_getinfo($tokenHandle, CURLINFO_RESPONSE_CODE);
curl_close($tokenHandle);
$tokenPayload = is_string($tokenBody) ? json_decode($tokenBody, true) : null;
if ($tokenStatus !== 200 || !is_array($tokenPayload)
    || !is_string($tokenPayload['refresh_token'] ?? null)
    || $tokenPayload['refresh_token'] === '') {
    gmail_oauth_setup_respond($connection, 500, 'SafariTrak could not complete authorization. Check the local setup command.');
    fclose($connection);
    $errorCode = is_array($tokenPayload) && is_string($tokenPayload['error'] ?? null)
        ? preg_replace('/[^a-zA-Z0-9_-]/', '', $tokenPayload['error'])
        : 'invalid_response';
    fwrite(STDERR, 'Google did not return a refresh token (HTTP ' . $tokenStatus . ', ' . ($errorCode ?: 'invalid_response') . "). Review the OAuth consent and retry.\n");
    exit(1);
}

$localConfigPath = __DIR__ . '/../config/env.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) {
    gmail_oauth_setup_respond($connection, 500, 'SafariTrak could not save its local authorization. Contact the administrator.');
    fclose($connection);
    fwrite(STDERR, "The existing backend/config/env.local.php must return an array.\n");
    exit(1);
}
$localConfig['GOOGLE_REFRESH_TOKEN'] = $tokenPayload['refresh_token'];
$configSource = "<?php\n\nreturn " . var_export($localConfig, true) . ";\n";
$bytesWritten = file_put_contents($localConfigPath, $configSource, LOCK_EX);
if ($bytesWritten !== strlen($configSource)) {
    gmail_oauth_setup_respond($connection, 500, 'SafariTrak could not save its local authorization. Contact the administrator.');
    fclose($connection);
    fwrite(STDERR, "Could not securely write the local environment configuration.\n");
    exit(1);
}
@chmod($localConfigPath, 0600);

gmail_oauth_setup_respond($connection, 200, 'SafariTrak Gmail authorization is complete. You may close this window.');
fclose($connection);
fwrite(STDOUT, "Gmail OAuth authorization completed. The refresh token was saved in the ignored backend/config/env.local.php file; it was not printed.\n");

function gmail_oauth_setup_respond($connection, int $status, string $message): void {
    $body = '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>SafariTrak Gmail setup</title><body style="font:16px Arial,sans-serif;max-width:38rem;margin:4rem auto;padding:0 1rem;color:#0F172A">'
        . '<h1>SafariTrak Gmail setup</h1><p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></body></html>';
    $reason = match ($status) {
        200 => 'OK',
        400 => 'Bad Request',
        default => 'Internal Server Error',
    };
    $response = "HTTP/1.1 {$status} {$reason}\r\n"
        . "Content-Type: text/html; charset=utf-8\r\n"
        . "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'\r\n"
        . "X-Content-Type-Options: nosniff\r\n"
        . "Cache-Control: no-store\r\n"
        . "Connection: close\r\n"
        . 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;
    fwrite($connection, $response);
}
