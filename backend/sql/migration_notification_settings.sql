ALTER TABLE users
    ADD COLUMN sos_alert_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_sos_on_silence,
    ADD COLUMN product_update_emails TINYINT(1) NOT NULL DEFAULT 0 AFTER sos_alert_notifications;


