<?php
require_once __DIR__ . '/backend/includes/session.php';
require_once __DIR__ . '/backend/includes/helpers.php';

st_logout();

header('Location: login.php');
exit;