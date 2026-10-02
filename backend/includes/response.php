<?php

function st_require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        st_json_error('Method not allowed.', 405);
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

function st_json_ok(array $data = []): void {
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => false, 'message' => $message], $extra));
    exit();
}
