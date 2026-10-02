<?php

function st_initials(string $name): string {
    $parts = array_filter(preg_split('/\s+/', trim($name)));
    if (empty($parts)) {
        return '?';
    }
    $parts = array_values($parts);
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function st_avatar_inner(array $user): string {
    if (!empty($user['avatar_path'])) {
        $v = file_exists(__DIR__ . '/../../' . $user['avatar_path']) 
            ? filemtime(__DIR__ . '/../../' . $user['avatar_path']) 
            : time();
        $src = htmlspecialchars($user['avatar_path']) . '?v=' . $v;
        return '<img src="' . $src . '" class="avatar-img" alt="">';
    }
    return htmlspecialchars(st_initials($user['full_name'] ?? ''));
}

function st_notif_icon(string $type): string {
    $icons = [
        'journey_started'   => 'fa-route',
        'journey_completed' => 'fa-flag-checkered',
        'arrival'           => 'fa-flag-checkered',
        'route_deviation'   => 'fa-triangle-exclamation',
        'new_message'       => 'fa-regular fa-message',
        'location_share'    => 'fa-location-arrow',
        'sos_alert'         => 'fa-triangle-exclamation',
        'contact_request'   => 'fa-user-plus',
        'group_invite'      => 'fa-user-group',
    ];
    return $icons[$type] ?? 'fa-bell';
}

function st_notif_category(string $type): string {
    if (in_array($type, ['sos_alert', 'route_deviation'], true)) {
        return 'safety';
    }
    if ($type === 'new_message') {
        return 'messages';
    }
    return 'journey';
}

function st_notif_link(string $type, ?int $relatedJourneyId, ?int $relatedUserId): ?string {
    switch ($type) {
        case 'location_share':
        case 'journey_started':
            return $relatedJourneyId ? 'watch-journey.php?id=' . $relatedJourneyId : null;
        case 'journey_completed':
        case 'arrival':
        case 'route_deviation':
            return $relatedJourneyId ? 'watch-journey.php?id=' . $relatedJourneyId : 'my-journeys.php';
        case 'new_message':
            return $relatedUserId ? 'messages.php?to=' . $relatedUserId : 'messages.php';
        case 'contact_request':
            return 'trusted-contacts.php';
        case 'group_invite':
            return 'group-travel.php';
        case 'sos_alert':
            return 'safety.php';
        default:
            return null;
    }
}

function st_greeting(): string {
    $hour = (int) date('G');

    if ($hour < 12) {
        return 'Good morning';
    }
    if ($hour < 17) {
        return 'Good afternoon';
    }
    return 'Good evening';
}

function st_clean_phone(string $phone): string {
    return preg_replace('/\D/', '', $phone) ?? '';
}

function st_valid_kenyan_phone(string $digits): bool {
    if (preg_match('/^(07|01)\d{8}$/', $digits)) {
        return true;
    }
    if (preg_match('/^254(7|1)\d{8}$/', $digits)) {
        return true;
    }
    return false;
}

function st_normalize_phone(string $digits): string {
    if (preg_match('/^(07|01)\d{8}$/', $digits)) {
        return '254' . substr($digits, 1);
    }
    return $digits;
}

function st_display_phone(?string $digits): string {
    if (!$digits) {
        return '';
    }
    if (preg_match('/^254(\d{9})$/', $digits, $m)) {
        $local = '0' . $m[1];
        return substr($local, 0, 4) . ' ' . substr($local, 4, 3) . ' ' . substr($local, 7);
    }
    return $digits;
}

function st_logout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        st_start_session();
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}