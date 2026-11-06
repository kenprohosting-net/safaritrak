<?php

require_once __DIR__ . '/../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    st_json_error('Method not allowed.', 405);
}

st_json_ok(['csrf_token' => st_csrf_token()]);
