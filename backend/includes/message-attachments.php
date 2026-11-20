<?php

function st_store_message_photo(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That photo exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The photo upload was interrupted. Try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing its temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the photo.',
            UPLOAD_ERR_EXTENSION => 'A server extension blocked this photo.',
            default => 'Choose a photo before sending.',
        };
        st_json_error($message, 422);
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $size = is_file($temporaryPath) ? filesize($temporaryPath) : false;
    if (!is_uploaded_file($temporaryPath) || $size === false || $size < 1) {
        st_json_error('The uploaded photo could not be read.', 422);
    }
    if ($size > 6 * 1024 * 1024) {
        st_json_error('Choose a photo no larger than 6 MB.', 422);
    }

    $imageInfo = getimagesize($temporaryPath);
    if ($imageInfo === false) {
        st_json_error('That file is not a valid image.', 422);
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $imageMime = (string) ($imageInfo['mime'] ?? '');
    $detectedMime = $imageMime;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            st_json_error('The server could not validate the uploaded photo.', 500);
        }
        $detectedMime = (string) finfo_file($finfo, $temporaryPath);
        finfo_close($finfo);
    }
    if (!isset($allowed[$imageMime]) || $detectedMime !== $imageMime) {
        st_json_error('Upload a JPG, PNG or WEBP photo.', 422);
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000 || $width * $height > 40000000) {
        st_json_error('That photo has dimensions that are too large to share.', 422);
    }

    $directory = dirname(__DIR__, 2) . '/uploads/message-photos';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        st_json_error('The server could not prepare photo storage.', 500);
    }
    if (!is_writable($directory)) {
        st_json_error('The server cannot write to photo storage.', 500);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$imageMime];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        st_json_error('The photo could not be saved. Try again.', 500);
    }

    return [
        'path' => 'uploads/message-photos/' . $filename,
        'mime' => $imageMime,
    ];
}

function st_message_photo_file(string $relativePath): ?array {
    if (!preg_match('#\Auploads/message-photos/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $relativePath)) {
        return null;
    }

    $projectRoot = dirname(__DIR__, 2);
    $directory = realpath($projectRoot . '/uploads/message-photos');
    $filePath = realpath($projectRoot . '/' . $relativePath);
    if ($directory === false || $filePath === false || !is_file($filePath)) {
        return null;
    }

    $directoryPrefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($filePath, $directoryPrefix, strlen($directoryPrefix)) !== 0) {
        return null;
    }

    $mimeByExtension = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (!isset($mimeByExtension[$extension])) {
        return null;
    }

    return ['path' => $filePath, 'mime' => $mimeByExtension[$extension]];
}
