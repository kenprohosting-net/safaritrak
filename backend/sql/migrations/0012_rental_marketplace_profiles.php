<?php

return static function (PDO $db): void {
    $db->exec(
        'CREATE TABLE IF NOT EXISTS rental_company_profiles (
            organization_id INT UNSIGNED NOT NULL PRIMARY KEY,
            logo_url VARCHAR(500) NULL,
            cover_photo_url VARCHAR(500) NULL,
            description VARCHAR(2000) NULL,
            established_year SMALLINT UNSIGNED NULL,
            business_email VARCHAR(160) NULL,
            official_phone VARCHAR(32) NULL,
            whatsapp VARCHAR(32) NULL,
            website VARCHAR(500) NULL,
            social_links TEXT NULL,
            office_location VARCHAR(500) NULL,
            town VARCHAR(120) NULL,
            regions_served TEXT NULL,
            operating_hours VARCHAR(500) NULL,
            office_lat DECIMAL(10, 7) NULL,
            office_lng DECIMAL(10, 7) NULL,
            coverage_description VARCHAR(1000) NULL,
            pickup_locations TEXT NULL,
            vehicle_delivery TINYINT(1) NOT NULL DEFAULT 0,
            airport_pickup TINYINT(1) NOT NULL DEFAULT 0,
            services TEXT NULL,
            minimum_driver_age TINYINT UNSIGNED NULL,
            valid_licence_required TINYINT(1) NOT NULL DEFAULT 1,
            minimum_driving_experience TINYINT UNSIGNED NULL,
            identification_requirements VARCHAR(500) NULL,
            booking_notice VARCHAR(200) NULL,
            minimum_rental_days SMALLINT UNSIGNED NULL,
            maximum_rental_days SMALLINT UNSIGNED NULL,
            fuel_return_policy VARCHAR(500) NULL,
            mileage_restrictions VARCHAR(500) NULL,
            late_return_policy VARCHAR(500) NULL,
            cancellation_policy VARCHAR(1000) NULL,
            refund_policy VARCHAR(1000) NULL,
            cross_border_available TINYINT(1) NOT NULL DEFAULT 0,
            gps_tracking_available TINYINT(1) NOT NULL DEFAULT 0,
            customer_trip_sharing TINYINT(1) NOT NULL DEFAULT 0,
            roadside_assistance TINYINT(1) NOT NULL DEFAULT 0,
            emergency_number VARCHAR(32) NULL,
            breakdown_assistance TINYINT(1) NOT NULL DEFAULT 0,
            registered_drivers TINYINT(1) NOT NULL DEFAULT 0,
            live_trip_monitoring TINYINT(1) NOT NULL DEFAULT 0,
            emergency_arrangements VARCHAR(1000) NULL,
            response_information VARCHAR(500) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS rental_vehicle_details (
            vehicle_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            transmission ENUM("automatic", "manual") NULL,
            fuel_type ENUM("petrol", "diesel", "hybrid", "electric") NULL,
            air_conditioning TINYINT(1) NULL,
            luggage_capacity TINYINT UNSIGNED NULL,
            weekly_rate DECIMAL(10, 2) NULL,
            monthly_rate DECIMAL(10, 2) NULL,
            chauffeur_rate DECIMAL(10, 2) NULL,
            self_drive_rate DECIMAL(10, 2) NULL,
            security_deposit DECIMAL(10, 2) NULL,
            extra_mileage_charge DECIMAL(10, 2) NULL,
            fuel_policy VARCHAR(500) NULL,
            additional_charges VARCHAR(1000) NULL,
            negotiable TINYINT(1) NOT NULL DEFAULT 0,
            quotation_required TINYINT(1) NOT NULL DEFAULT 0,
            driver_option ENUM("self_drive", "chauffeur", "both") NOT NULL DEFAULT "both",
            services TEXT NULL,
            minimum_driver_age TINYINT UNSIGNED NULL,
            valid_licence_required TINYINT(1) NOT NULL DEFAULT 1,
            minimum_driving_experience TINYINT UNSIGNED NULL,
            identification_requirements VARCHAR(500) NULL,
            booking_notice VARCHAR(200) NULL,
            minimum_rental_days SMALLINT UNSIGNED NULL,
            maximum_rental_days SMALLINT UNSIGNED NULL,
            fuel_return_policy VARCHAR(500) NULL,
            mileage_restrictions VARCHAR(500) NULL,
            late_return_policy VARCHAR(500) NULL,
            cancellation_policy VARCHAR(1000) NULL,
            refund_policy VARCHAR(1000) NULL,
            cross_border_available TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (vehicle_id) REFERENCES rental_vehicles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS rental_vehicle_photos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            vehicle_id BIGINT UNSIGNED NOT NULL,
            photo_type ENUM("exterior", "interior") NOT NULL,
            photo_url VARCHAR(1000) NOT NULL,
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES rental_vehicles(id) ON DELETE CASCADE,
            INDEX idx_rental_vehicle_photos (vehicle_id, photo_type, sort_order)
        ) ENGINE=InnoDB'
    );
    $db->exec('ALTER TABLE rental_vehicles MODIFY daily_rate DECIMAL(10, 2) NULL');
    $db->exec('ALTER TABLE rental_bookings MODIFY quoted_daily_rate DECIMAL(10, 2) NULL');
    $db->exec(
        'ALTER TABLE rental_bookings
         MODIFY status ENUM("requested", "approved", "responded", "rejected", "cancelled", "completed")
         NOT NULL DEFAULT "requested"'
    );
};
