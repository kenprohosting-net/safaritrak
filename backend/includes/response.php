<?php

require_once __DIR__ . '/session.php';

set_exception_handler(static function (Throwable $exception): void {
    $requestId = bin2hex(random_bytes(8));
    error_log(
        '[SafariTrak] unhandled request error (' . $requestId . '): '
        . get_class($exception) . ': ' . $exception->getMessage()
    );

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode([
        'success' => false,
        'message' => 'The request could not be completed. Contact support with reference ' . $requestId . '.',
    ]);
});

function st_require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        st_json_error('Method not allowed.', 405);
    }

    if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        st_require_csrf();
    }
}

/**
 * Reads either a JSON body or a regular form POST, whichever was sent.
 */
function st_input(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return $_POST;
}

function st_input_string(array $input, string $key): string {
    if (!array_key_exists($key, $input) || $input[$key] === null) {
        return '';
    }

    if (!is_string($input[$key]) && !is_int($input[$key]) && !is_float($input[$key])) {
        st_json_error('Invalid request data.', 422, ['errors' => [$key => 'Enter a valid value.']]);
    }

    return (string) $input[$key];
}

function st_json_ok(array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

/**
 * $extra can carry ['errors' => ['field_name' => 'message', ...]] so the
 * front end can highlight the exact field that failed, not just show a
 * generic banner.
 */
function st_json_error(string $message, int $httpCode = 400, array $extra = []): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(array_merge(['success' => false, 'message' => $message], $extra));
    exit();
}
