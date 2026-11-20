<?php

return static function (PDO $db): void {
    $db->exec('ALTER TABLE saved_locations ADD COLUMN note VARCHAR(500) NULL AFTER address');
};
