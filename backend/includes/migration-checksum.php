<?php

function st_migration_file_checksum(string $file): string {
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException('Unable to read migration file for checksum verification.');
    }

    return hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents));
}

function st_migration_file_checksum_matches(string $storedChecksum, string $file): bool {
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException('Unable to read migration file for checksum verification.');
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $contents);
    $lfChecksum = hash('sha256', $normalized);
    $crlfChecksum = hash('sha256', str_replace("\n", "\r\n", $normalized));

    return hash_equals($storedChecksum, $lfChecksum)
        || hash_equals($storedChecksum, $crlfChecksum);
}
