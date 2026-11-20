<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$customerId = st_require_login();
$input = st_input();
$bookingId = filter_var($input['booking_id'] ?? null, FILTER_VALIDATE_INT);
$rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT);
$reviewText = trim(st_input_string($input, 'review_text'));
if (!$bookingId || !$rating || $rating < 1 || $rating > 5 || mb_strlen($reviewText) > 1000) {
    st_json_error('Choose a rating from 1 to 5 and keep your review under 1000 characters.', 422);
}

$db = safaritrak_db();
$bookingStmt = $db->prepare(
    'SELECT b.id, b.vehicle_id
     FROM rental_bookings b
     WHERE b.id = ? AND b.customer_id = ? AND b.status = "completed"'
);
$bookingStmt->execute([$bookingId, $customerId]);
$booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
if (!$booking) {
    st_json_error('Only a completed rental can be reviewed.', 403);
}

$db->prepare(
    'INSERT INTO rental_reviews (booking_id, vehicle_id, customer_id, rating, review_text)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), status = "published"'
)->execute([
    (int) $booking['id'],
    (int) $booking['vehicle_id'],
    $customerId,
    $rating,
    $reviewText === '' ? null : $reviewText,
]);

st_json_ok(['message' => 'Your review has been saved.']);
