<?php
// --- Diagnostic-safe JSON guard -------------------------------------
// Buffers everything this script outputs. If a PHP warning/notice/
// deprecation prints alongside our JSON, this strips it so the browser
// only ever gets valid JSON. It also logs what was stripped so you can
// find and fix the real cause in php-error.log.
ob_start();
register_shutdown_function(function () {
    $buffer = ob_get_clean();
    if (preg_match('/\{.*\}\s*$/s', $buffer, $m)) {
        if (trim($buffer) !== trim($m[0])) {
            error_log('[SafariTrak upload-avatar] stripped extra output: ' . trim(str_replace($m[0], '', $buffer)));
        }
        header('Content-Type: application/json');
        echo $m[0];
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        error_log('[SafariTrak upload-avatar] fatal with no JSON produced: ' . trim($buffer));
        echo json_encode(['success' => false, 'message' => 'Unexpected server error. Check php-error.log.']);
    }
});
// ---------------------------------------------------------------------

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

if (!empty($_POST['remove'])) {
    $pdo = safaritrak_db();
    $existing = $pdo->prepare('SELECT avatar_path FROM users WHERE id = ?');
    $existing->execute([$userId]);
    $oldPath = $existing->fetchColumn();

    $pdo->prepare('UPDATE users SET avatar_path = NULL WHERE id = ?')->execute([$userId]);

    if ($oldPath && strpos($oldPath, 'uploads/avatars/') === 0) {
        $oldFile = __DIR__ . '/../../' . $oldPath;
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    st_json_ok(['avatar_path' => null]);
    exit;
}

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
    st_json_error('Choose a photo to upload.');
    exit;
}

$file = $_FILES['avatar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE  => 'That photo is larger than this server allows. Please choose a smaller image.',
        UPLOAD_ERR_FORM_SIZE => 'That photo is larger than this server allows. Please choose a smaller image.',
        UPLOAD_ERR_PARTIAL   => 'The upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder for uploads.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A server extension blocked this upload.',
    ];
    st_json_error($uploadErrors[$file['error']] ?? 'That upload did not go through. Please try again.');
    exit;
}

$maxBytes = 4 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    st_json_error('Please choose an image smaller than 4 MB.');
    exit;
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

$mime = null;
if (function_exists('finfo_open')) {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
}

$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    st_json_error('That file does not look like a valid image.');
    exit;
}

if (!$mime) {
    $mime = $imageInfo['mime'] ?? null;
}

if (!isset($allowed[$mime])) {
    st_json_error('Please upload a JPG, PNG or WEBP image.');
    exit;
}

$extension = $allowed[$mime];
$uploadsDir = __DIR__ . '/../../uploads/avatars';

if (!is_dir($uploadsDir)) {
    if (!mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
        st_json_error('Server could not prepare the uploads folder.', 500);
        exit;
    }
}

if (!is_writable($uploadsDir)) {
    st_json_error('Server does not have permission to save photos. Please contact support.', 500);
    exit;
}

$pdo = safaritrak_db();

$existing = $pdo->prepare('SELECT avatar_path FROM users WHERE id = ?');
$existing->execute([$userId]);
$oldPath = $existing->fetchColumn();

$filename = 'user-' . $userId . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
$destination = $uploadsDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    st_json_error('We could not save that photo. Please try again.', 500);
    exit;
}

$publicPath = 'uploads/avatars/' . $filename;

$update = $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?');
$update->execute([$publicPath, $userId]);

if ($oldPath && strpos($oldPath, 'uploads/avatars/') === 0) {
    $oldFile = __DIR__ . '/../../' . $oldPath;
    if (is_file($oldFile)) {
        @unlink($oldFile);
    }
}

st_json_ok(['avatar_path' => $publicPath]);
exit;