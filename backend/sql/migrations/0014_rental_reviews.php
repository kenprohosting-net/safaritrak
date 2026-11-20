<?php

return static function (PDO $db): void {
    $db->exec(
        'CREATE TABLE IF NOT EXISTS rental_reviews (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT UNSIGNED NOT NULL,
            vehicle_id BIGINT UNSIGNED NOT NULL,
            customer_id INT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            review_text VARCHAR(1000) NULL,
            status ENUM("published", "hidden") NOT NULL DEFAULT "published",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES rental_bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (vehicle_id) REFERENCES rental_vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_rental_review_booking (booking_id),
            INDEX idx_rental_reviews_vehicle (vehicle_id, status, created_at)
        ) ENGINE=InnoDB'
    );
};
