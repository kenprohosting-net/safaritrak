<?php

$token = $_GET['token'] ?? '';
if (!is_string($token) || $token === '') {
    header('Location: welcome.php', true, 302);
    exit;
}

header('Location: shared-location.php?' . http_build_query(['token' => $token]), true, 302);
exit;