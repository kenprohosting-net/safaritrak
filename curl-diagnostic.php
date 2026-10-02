<?php
// Standalone diagnostic — has nothing to do with the SafariTrak app itself.
// Drop this in C:\xampp\htdocs\safaritrak\curl-diagnostic.php and open
// http://localhost/safaritrak/curl-diagnostic.php in your browser.
//
// It just checks whether PHP-cURL on this machine can reach the outside
// world at all, and if not, exactly why — so we know whether the 502 is
// a network/firewall issue, a Windows XAMPP SSL cert issue (the common
// one), or something else. Delete this file once you're done with it.

header('Content-Type: text/plain');

function test(string $label, string $url): void {
    echo "=== $label ===\n";
    echo "URL: $url\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['User-Agent: SafariTrak-Diagnostic/1.0'],
        CURLOPT_VERBOSE => false,
    ]);

    $start = microtime(true);
    $response = curl_exec($ch);
    $elapsed = round((microtime(true) - $start) * 1000);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        echo "RESULT: FAILED\n";
        echo "curl_errno: $errno\n";
        echo "curl_error: $error\n";
        if ($errno === 60 || stripos($error, 'certificate') !== false) {
            echo "\n>>> This is the classic Windows XAMPP SSL problem.\n";
            echo ">>> Fix: download https://curl.se/ca/cacert.pem, save it\n";
            echo ">>> somewhere like C:\\xampp\\php\\cacert.pem, then in\n";
            echo ">>> C:\\xampp\\php\\php.ini set:\n";
            echo ">>>   curl.cainfo = \"C:\\xampp\\php\\cacert.pem\"\n";
            echo ">>>   openssl.cafile = \"C:\\xampp\\php\\cacert.pem\"\n";
            echo ">>> then restart Apache in the XAMPP control panel.\n";
        }
        if ($errno === 6) {
            echo "\n>>> Could not resolve host — DNS or no internet access\n";
            echo ">>> from this machine/network.\n";
        }
        if ($errno === 7 || $errno === 28) {
            echo "\n>>> Could not connect / timed out — likely a firewall,\n";
            echo ">>> proxy, or antivirus blocking outbound PHP requests.\n";
        }
    } else {
        echo "RESULT: OK (HTTP $httpCode, {$elapsed}ms)\n";
        echo "First 200 chars of response:\n";
        echo substr($response, 0, 200) . "\n";
    }

    echo "\n";
}

echo "PHP version: " . PHP_VERSION . "\n";
echo "curl extension loaded: " . (extension_loaded('curl') ? 'yes' : 'NO - THIS IS THE PROBLEM') . "\n";
if (extension_loaded('curl')) {
    $v = curl_version();
    echo "curl version: " . $v['version'] . "\n";
    echo "SSL version: " . $v['ssl_version'] . "\n";
}
echo "\n";

test('Overpass (primary)', 'https://overpass-api.de/api/interpreter?data=' . urlencode('[out:json][timeout:5];node(1);out;'));
test('Overpass (mirror)', 'https://overpass.kumi.systems/api/interpreter?data=' . urlencode('[out:json][timeout:5];node(1);out;'));
test('Nominatim', 'https://nominatim.openstreetmap.org/search?format=jsonv2&q=Nairobi&limit=1');
test('Plain HTTPS sanity check', 'https://example.com');