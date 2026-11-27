<?php

$checks = 0;
require_once __DIR__ . '/../backend/includes/mailer.php';
require_once __DIR__ . '/../backend/includes/geo.php';

$expect = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }

    $checks++;
    echo "PASS {$message}\n";
};

$screens = require __DIR__ . '/../backend/config/planned-screens.php';
$requiredFields = ['title', 'description', 'phase', 'available_route', 'available_label'];

$expect(is_array($screens) && count($screens) >= 9, 'planned screen catalog is loaded');
$expect(isset($screens['groups'], $screens['group-chat'], $screens['globe'], $screens['android-permissions']), 'future product domains have explicit placeholder routes');

foreach ($screens as $screenId => $screen) {
    $expect((bool) preg_match('/^[a-z0-9-]+$/', $screenId), "screen id {$screenId} is URL-safe");

    foreach ($requiredFields as $field) {
        $expect(isset($screen[$field]) && is_string($screen[$field]) && $screen[$field] !== '', "screen {$screenId} has {$field}");
    }

    $routePath = strtok($screen['available_route'], '?');
    $expect(is_file(__DIR__ . '/../' . $routePath), "screen {$screenId} links to an existing PHP route");
}

foreach ([
    __DIR__ . '/../backend/views/app-shell-start.php',
    __DIR__ . '/../backend/views/app-shell-end.php',
    __DIR__ . '/../planned-screen.php',
    __DIR__ . '/../welcome.php',
] as $path) {
    $expect(is_file($path), basename($path) . ' exists');
}

$shellStart = file_get_contents(__DIR__ . '/../backend/views/app-shell-start.php');
$shellEnd = file_get_contents(__DIR__ . '/../backend/views/app-shell-end.php');
$navigation = file_get_contents(__DIR__ . '/../backend/views/app-navigation.php');
$authGuard = file_get_contents(__DIR__ . '/../backend/includes/auth-guard.php');
$organizationDashboard = file_get_contents(__DIR__ . '/../org-dashboard.php');
$adminUsersPage = file_get_contents(__DIR__ . '/../admin-users.php');
$adminUsersScript = file_get_contents(__DIR__ . '/../admin-users.js');
$adminDashboardPage = file_get_contents(__DIR__ . '/../admin-dashboard.php');
$platformClaimApi = file_get_contents(__DIR__ . '/../backend/api/platform/claim.php');
$platformOnboardingScript = file_get_contents(__DIR__ . '/../platform-onboarding.js');
$dashboardCss = file_get_contents(__DIR__ . '/../dashboard.css');
$themeCss = file_get_contents(__DIR__ . '/../theme.css');
$authTheme = file_get_contents(__DIR__ . '/../auth-theme.css');
$mapStyleScript = file_get_contents(__DIR__ . '/../map-style.js');
$dashboard = file_get_contents(__DIR__ . '/../index.php');
$dashboardScript = file_get_contents(__DIR__ . '/../dashboard.js');
$expect(
    is_string($navigation)
    && str_contains($navigation, 'class="sidebar app-navbar"')
    && str_contains($navigation, 'data-standard-navbar')
    && str_contains($navigation, 'aria-label="Primary navigation"')
    && str_contains($navigation, 'aria-label="Main navigation"'),
    'authenticated pages share the accessible floating icon navbar'
);
$expect(
    is_string($navigation)
    && str_contains($navigation, 'href="index.php" title="Home"')
    && str_contains($navigation, 'href="logout.php" class="profile-dropdown-item logout-link"')
    && str_contains($navigation, '<span>Sign out</span>')
    && str_contains($navigation, 'aria-current="page"'),
    'floating navigation keeps accessible Home and sign-out labels and active-page state'
);
$expect(
    is_string($navigation)
    && str_contains($navigation, '<details class="app-navbar-admin-menu"')
    && str_contains($navigation, '<div class="app-navbar-admin-submenu"')
    && str_contains($navigation, "open data-active=\"true\"")
    && substr_count($navigation, 'href="admin-dashboard.php"') === 1
    && substr_count($navigation, 'href="admin-users.php"') === 1
    && substr_count($navigation, 'href="admin-organizations.php"') === 1
    && substr_count($navigation, 'href="admin-admins.php"') === 1,
    'platform admin destinations are grouped in one accessible Admin menu'
);
$expect(
    is_string($navigation)
    && str_contains($navigation, 'data-sos-trigger')
    && str_contains($navigation, 'app-sos-dialog')
    && str_contains($navigation, 'backend/api/safety/trigger-sos.php') === false,
    'the shared navbar exposes one direct SOS control and keeps it separate from the admin menu'
);
$expect(
    is_string($shellStart)
    && str_contains($shellStart, 'id="toggleDashboardPlanner"')
    && str_contains($shellStart, 'id="dashboardPlannerContent"')
    && is_string($dashboard)
    && str_contains($dashboard, 'id="toggleExplorePanels"')
    && str_contains($dashboard, 'id="explorePanelsContent"')
    && is_string($dashboardScript)
    && str_contains($dashboardScript, 'dashboard-planner-collapsed')
    && str_contains($dashboardScript, 'explorePanels?.classList.toggle'),
    'Explore route and social panels expose independent accessible collapse controls'
);
$trackingPageSource = file_get_contents(__DIR__ . '/../tracking.php');
$viewJourneyPageSource = file_get_contents(__DIR__ . '/../view-journey.php');
$journeyPositionApi = file_get_contents(__DIR__ . '/../backend/api/journeys/update-position.php');
$journeyLocationApi = file_get_contents(__DIR__ . '/../backend/api/journeys/update-location.php');
$groupPositionApi = file_get_contents(__DIR__ . '/../backend/api/groups/update-position.php');
$expect(
    is_string($trackingPageSource)
    && str_contains($trackingPageSource, './backend/api/journeys/update-location.php')
    && str_contains($trackingPageSource, 'X-CSRF-Token')
    && str_contains($trackingPageSource, 'data.distance_km')
    && str_contains($trackingPageSource, 'data.covered_km')
    && substr_count($trackingPageSource, "'X-CSRF-Token'") === 2
    && !str_contains($trackingPageSource, './backend/api/journey/update-position.php')
    && str_contains($trackingPageSource, "backend/views/app-navigation.php")
    && str_contains($trackingPageSource, "dashboard.js?v=")
    && !str_contains($trackingPageSource, '<header class="topbar">'),
    'legacy tracking preserves its endpoint and CSRF handling while using the shared navigation'
);
$expect(
    is_string($viewJourneyPageSource)
    && substr_count($viewJourneyPageSource, "backend/views/app-navigation.php") === 2
    && str_contains($viewJourneyPageSource, 'class="app-navbar-safe-area"')
    && str_contains($viewJourneyPageSource, 'dashboard.js?v='),
    'both available and unavailable journey views retain the shared navigation'
);
$expect(
    is_string($trackingPageSource)
    && str_contains($trackingPageSource, 'dashboard.css?v=')
    && str_contains($trackingPageSource, 'tracking-page-content')
    && str_contains($trackingPageSource, 'env(safe-area-inset-bottom)'),
    'tracking reserves space for the desktop pill and mobile bottom navigation'
);
$expect(
    is_string($journeyPositionApi)
    && is_string($journeyLocationApi)
    && is_string($groupPositionApi)
    && str_contains($journeyPositionApi, 'st_parse_coordinate')
    && str_contains($journeyLocationApi, 'st_parse_coordinate')
    && str_contains($groupPositionApi, 'st_parse_coordinate')
    && str_contains($groupPositionApi, '$memberId = $stmt->fetchColumn();')
    && !str_contains($groupPositionApi, '$stmt->fetch();'),
    'personal and group position APIs validate coordinates and retain the selected group member ID'
);
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, 'body .app > .app-navbar nav a .nav-link-label')
    && str_contains($dashboardCss, 'max-width: 0 !important;')
    && str_contains($dashboardCss, 'body .app > .app-navbar nav a:is(:hover, :focus-visible) .nav-link-label')
    && str_contains($dashboardCss, 'max-width: 220px !important;'),
    'floating navigation hides every page label until that icon is hovered or focused'
);
$expect(
    is_string($navigation)
    && str_contains($navigation, '($currentUser[\'role\'] ?? \'\') === \'platform_admin\'')
    && str_contains($navigation, 'href="admin-users.php"')
    && str_contains($navigation, 'href="admin-admins.php"')
    && str_contains($navigation, 'class="app-navbar-brand-lockup"')
    && str_contains($dashboardCss, '.app-navbar')
    && str_contains($dashboardCss, 'left: 50% !important;')
    && str_contains($dashboardCss, 'width: max-content !important;')
    && str_contains($dashboardCss, 'transform: translateX(-50%);')
    && str_contains($dashboardCss, 'color-mix(in srgb, var(--accent-primary) 14%, var(--bg-panel))')
    && str_contains($dashboardCss, 'html[data-theme] body:not(.map-focus-mode) .app > .app-navbar')
    && str_contains($dashboardCss, 'backdrop-filter: blur(20px) saturate(160%) !important;')
    && str_contains($navigation, 'class="app-navbar-theme-toggle"')
    && str_contains($navigation, 'class="app-navbar-sos"')
    && str_contains($navigation, 'app-navbar-avatar"')
    && str_contains($navigation, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0')
    && str_contains($navigation, 'unreadNotificationCount'),
    'platform navigation is role-gated and styled with the active theme accent'
);
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, 'rgba(248, 250, 252, 0.88)')
    && str_contains($dashboardCss, 'html[data-theme] body .app.app--workspace ~ .mobile-bottom-nav')
    && str_contains($dashboardCss, 'rgba(22, 18, 28, 0.92)')
    && str_contains($dashboardScript, 'closeSidebar({ restoreFocus: true })')
    && str_contains($dashboardScript, 'hasActivePrimaryDestination'),
    'navigation supports the light glass theme, workspace mobile dock, and a togglable More drawer'
);
$expect(
    is_string($adminUsersPage)
    && str_contains($adminUsersPage, 'FROM platform_admins')
    && str_contains($adminUsersPage, 'FROM organization_admins')
    && str_contains($adminUsersPage, 'FROM school_verifications')
    && str_contains($adminUsersPage, 'id="userRoleFilter"')
    && str_contains($adminUsersPage, 'approved membership and verification workflow')
    && is_string($adminUsersScript)
    && str_contains($adminUsersScript, "row.getAttribute('data-roles')"),
    'platform account management reports scoped roles and filters accounts without changing verification workflows'
);
$expect(
    is_string($adminDashboardPage)
    && str_contains($adminDashboardPage, 'backend/includes/platform-guard.php')
    && str_contains($adminDashboardPage, 'SAFARITRAK_PLATFORM_ADMIN_BOOTSTRAP_TOKEN')
    && str_contains($adminDashboardPage, 'id="claimBootstrapToken"')
    && is_string($platformClaimApi)
    && str_contains($platformClaimApi, 'hash_equals($bootstrapToken, $providedToken)')
    && str_contains($platformClaimApi, 'GET_LOCK')
    && is_string($platformOnboardingScript)
    && str_contains($platformOnboardingScript, 'bootstrap_token: bootstrapToken'),
    'initial platform owner setup is reachable, token-gated, and serialized against concurrent claims'
);
$expect(
    is_string($adminDashboardPage)
    && str_contains($adminDashboardPage, '<html lang="en" data-theme="dark">')
    && str_contains($adminDashboardPage, 'backend/views/app-navigation.php'),
    'platform admin dashboard initializes the responsive shared navbar with a default theme'
);
$expect(is_string($dashboardScript) && str_contains($dashboardScript, 'syncSidebarAccessibility') && str_contains($dashboardScript, "toggleAttribute('inert'") && str_contains($dashboardScript, "e.key !== 'Tab'"), 'mobile navigation hides closed links and manages focus while open');
$expect(
    is_string($dashboardScript)
    && str_contains($dashboardScript, 'function addAccessibleTooltips')
    && str_contains($dashboardScript, "control.title = control.getAttribute('aria-label')")
    && str_contains($dashboardScript, 'new MutationObserver'),
    'icon controls receive accessible browser tooltips, including dynamically added controls'
);
$startJourneyPage = file_get_contents(__DIR__ . '/../start-journey.php');
$expect(
    is_string($startJourneyPage)
    && str_contains($startJourneyPage, 'app--journey-create')
    && str_contains($startJourneyPage, 'data-map-review')
    && str_contains($dashboardScript, "event.target.closest('[data-map-review]')")
    && str_contains($dashboardScript, "document.getElementById('appMapBackground')")
    && str_contains($dashboardCss, '.page-panel-focus .app--map-layout:not(.app--explore):not(.app--journeys) > main > .content > .is-page-panel-selected'),
    'map review controls focus their panel only on map pages while keeping journey planning available'
);
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, 'inset: 14px auto auto 50%;')
    && str_contains($dashboardCss, 'border-radius: 999px;')
    && str_contains($dashboardCss, 'max-width: 170px;')
    && str_contains($dashboardCss, 'inset: 160px 14px 14px 14px !important;')
    && str_contains($dashboardCss, 'transition: left .2s ease;')
    && str_contains($dashboardCss, 'padding-left: 44px;')
    && str_contains($dashboardCss, 'flex: 0 0 20px;')
    && preg_match('/@media \(max-width: 800px\) \{\s*\.app--map-layout:not\(\.app--explore\) > \.sidebar \{\s*z-index: 31;/s', $dashboardCss) === 1,
    'desktop navigation expansion stays aligned and the mobile drawer stacks above page panels'
);
$expect(
    is_string($themeCss)
    && str_contains($themeCss, '--bg-app: #EDF4FA;')
    && str_contains($themeCss, '--glass-opacity: 0.4;')
    && str_contains($themeCss, '--bg-panel: rgba(151, 196, 221, 0.4);')
    && str_contains($themeCss, '--bg-panel-inner: rgba(211, 231, 244, 0.72);')
    && str_contains($themeCss, '--glass-blur: var(--glass-surface-blur);')
    && str_contains($themeCss, '--border-glass: #B8CEDC;')
    && str_contains($themeCss, '--accent-primary: #007C95;')
    && str_contains($themeCss, '--accent-secondary: #8B35C9;')
    && str_contains($themeCss, '--accent-tertiary: #FF8A24;')
    && str_contains($themeCss, '--sos-red: #D9233A;')
    && str_contains($themeCss, '--route-stroke: #D7263D;')
    && str_contains($themeCss, '--route-completed-stroke: #D7263D;')
    && str_contains($themeCss, '--group-member-8: #52677A;')
    && str_contains($themeCss, '--bg-app: #050817;')
    && str_contains($themeCss, '--accent-primary: #00D9FF;')
    && str_contains($themeCss, '--accent-secondary: #FF24C8;')
    && str_contains($themeCss, '--success: #19FF79;')
    && str_contains($themeCss, '--route-stroke: #FF3B4D;')
    && str_contains($themeCss, '--route-completed-stroke: #FF3B4D;')
    && str_contains($themeCss, '--sos-red: #FF2638;')
    && str_contains($themeCss, '--map-background: #050817;')
    && str_contains($themeCss, '--map-urban: #10152D;')
    && str_contains($themeCss, '--map-building: #14768B;')
    && str_contains($themeCss, '--map-building-tall: #08B9A6;')
    && str_contains($themeCss, '--map-road-highway: #C97835;')
    && str_contains($themeCss, '--map-road-major: #FF8A24;')
    && str_contains($themeCss, '--map-road-secondary: #B14DFF;')
    && str_contains($themeCss, '--map-road-residential: #82AD98;')
    && str_contains($themeCss, '--map-road-service: #9685AF;')
    && str_contains($themeCss, '--map-trail: #8E85A7;')
    && str_contains($themeCss, '--map-building-highlight: #23A9BD;')
    && str_contains($themeCss, '--map-landuse-commercial: #D6A75F;')
    && str_contains($themeCss, '--map-landuse-industrial: #57B9C8;')
    && str_contains($themeCss, '--map-waterway: #00D9FF;')
    && str_contains($themeCss, '--map-label: #E8E8FF;')
    && str_contains($themeCss, '--map-boundary: #7654C9;')
    && str_contains($themeCss, '--map-marker-user: #A020F0;')
    && str_contains($themeCss, '--map-farmland: #A17A42;')
    && str_contains($dashboardCss, 'background-color: var(--bg-panel) !important;')
    && str_contains($dashboardCss, 'backdrop-filter: var(--glass-blur) !important;'),
    'shared light and dark themes define Cyber Aurora map colors and existing app surfaces'
);
$signupPage = file_get_contents(__DIR__ . '/../signup.php');
$expect(
    is_string($authTheme)
    && str_contains($authTheme, 'width: min(100%, 400px) !important;')
    && str_contains($authTheme, 'padding: 32px;')
    && str_contains($authTheme, 'border-radius: 20px;')
    && str_contains($authTheme, 'background: var(--auth-glass-surface) !important;')
    && str_contains($authTheme, 'backdrop-filter: var(--auth-glass-blur) !important;')
    && str_contains($authTheme, 'background: rgba(255, 255, 255, 0.08) !important;')
    && str_contains($authTheme, 'border-radius: 999px !important;')
    && str_contains($authTheme, 'backdrop-filter: blur(10px) saturate(155%) !important;')
    && str_contains($authTheme, 'backdrop-filter: blur(4px) !important;')
    && str_contains($authTheme, 'background: linear-gradient(110deg, #C2410C 0%, #0F766E 100%) !important;')
    && str_contains($authTheme, '@supports not ((backdrop-filter:')
    && str_contains($authTheme, '@media (prefers-reduced-transparency: reduce)')
    && is_string($signupPage)
    && str_contains($signupPage, '<h1 class="title">Create Account</h1>')
    && str_contains($signupPage, '<button type="submit" class="login-btn">Sign Up</button>'),
    'auth forms use theme-tinted glass controls, accessible labels and reduced-transparency fallbacks'
);
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, 'html[data-theme] body .app :is(')
    && str_contains($dashboardCss, '[class$="-panel"]')
    && str_contains($dashboardCss, '[class$="-card"]')
    && str_contains($dashboardCss, '@media (prefers-reduced-transparency: reduce)'),
    'application panels share clear glass styling with reduced-transparency fallbacks'
);
$expect(
    is_string($mapStyleScript)
    && str_contains($mapStyleScript, 'function getRouteColors()')
    && str_contains($mapStyleScript, "cssColor('--route-stroke'")
    && str_contains($mapStyleScript, 'getRouteColors,')
    && str_contains($mapStyleScript, 'positron-gl-style/style.json'),
    'shared map style exposes route colors from the global theme tokens'
);
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, '--accent-cyan: var(--accent-primary);')
    && str_contains($dashboardCss, '--accent-amber: var(--warning);')
    && str_contains($dashboardCss, '.dashboard-map-marker--current, .dashboard-map-marker--location')
    && str_contains($dashboardCss, '.app--journeys .trip-carousel-panel'),
    'navigation and map markers use shared theme and map tokens'
);
$termsPage = file_get_contents(__DIR__ . '/../terms.html');
$privacyPage = file_get_contents(__DIR__ . '/../privacy.html');
$legalCss = file_get_contents(__DIR__ . '/../legal.css');
$expect(
    is_string($termsPage)
    && is_string($privacyPage)
    && str_contains($termsPage, 'data-theme="dark"')
    && str_contains($privacyPage, 'data-theme="dark"')
    && str_contains($termsPage, 'auth-theme.js')
    && str_contains($privacyPage, 'auth-theme.js')
    && is_string($legalCss)
    && str_contains($legalCss, 'theme.css?v=10')
    && str_contains($legalCss, 'var(--bg-app)'),
    'standalone legal pages use the shared theme and persisted theme toggle'
);
$mailerSource = file_get_contents(__DIR__ . '/../backend/includes/mailer.php');
$expect(is_string($mailerSource) && str_contains($mailerSource, 'https://oauth2.googleapis.com/token') && str_contains($mailerSource, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'), 'transactional email uses Google OAuth refresh and the Gmail send API');
$expect(is_string($mailerSource) && str_contains($mailerSource, 'GOOGLE_CLIENT_SECRET') && str_contains($mailerSource, 'GOOGLE_REFRESH_TOKEN') && str_contains($mailerSource, 'GMAIL_SENDER_EMAIL') && str_contains($mailerSource, 'mail-recipient'), 'Gmail delivery uses server-only OAuth configuration and recipient rate limits');
$rawEmail = st_gmail_build_raw_message(
    'sender@example.test',
    'recipient@example.test',
    'SafariTrak Test',
    'Test subject',
    '<p>HTML body</p>',
    'Plain body'
);
$decodedEmail = base64_decode(strtr($rawEmail, '-_', '+/'), true);
$expect(is_string($decodedEmail) && str_contains($decodedEmail, 'Content-Type: multipart/alternative;') && str_contains($decodedEmail, 'Plain body') && str_contains($decodedEmail, '<p>HTML body</p>'), 'Gmail message builder emits base64url MIME with plain-text and HTML alternatives');
$expect(is_string($mailerSource) && str_contains($mailerSource, 'st_email_layout') && str_contains($mailerSource, 'st_send_password_reset_email') && str_contains($mailerSource, 'st_send_group_invitation_email') && str_contains($mailerSource, 'st_send_trusted_contact_invitation_email'), 'email templates cover account, group, and trusted-contact transactions');
$gmailSetup = file_get_contents(__DIR__ . '/../backend/scripts/gmail-oauth-setup.php');
$expect(is_string($gmailSetup) && str_contains($gmailSetup, 'hash_equals($state, $query[\'state\'])') && str_contains($gmailSetup, '127.0.0.1') && str_contains($gmailSetup, 'GOOGLE_REFRESH_TOKEN'), 'OAuth setup validates local callback state and stores refresh tokens server-side');
$expect(is_string($gmailSetup) && str_contains($gmailSetup, 'The refresh token was saved') && !str_contains($gmailSetup, 'echo $tokenPayload[\'refresh_token\']'), 'OAuth setup confirms completion without returning a refresh token');
$environmentConfig = file_get_contents(__DIR__ . '/../backend/config/env.php');
$exampleEnvironment = file_get_contents(__DIR__ . '/../.env.example');
$expect(is_string($environmentConfig) && str_contains($environmentConfig, "'GOOGLE_CLIENT_SECRET'") && str_contains($environmentConfig, "'GMAIL_SENDER_EMAIL'") && str_contains($environmentConfig, "'GOOGLE_MAPS_API_KEY'") && str_contains($environmentConfig, "'SAFARITRAK_GOOGLE_MAPS_API_KEY'"), 'backend environment loader accepts the Maps key alias and approved Google mail settings');
$expect(is_string($exampleEnvironment) && str_contains($exampleEnvironment, 'GOOGLE_CLIENT_ID=') && str_contains($exampleEnvironment, 'GOOGLE_REFRESH_TOKEN=') && str_contains($exampleEnvironment, 'GMAIL_SENDER_EMAIL='), 'environment example documents blank Gmail OAuth variable names');
$forgotPasswordApi = file_get_contents(__DIR__ . '/../backend/api/forgot-password.php');
$resetPasswordApi = file_get_contents(__DIR__ . '/../backend/api/reset-password.php');
$updatePasswordApi = file_get_contents(__DIR__ . '/../backend/api/update-password.php');
$contactInviteApi = file_get_contents(__DIR__ . '/../backend/api/contacts/invite.php');
$groupInviteApi = file_get_contents(__DIR__ . '/../backend/api/groups/persistent/invite.php');
$expect(is_string($forgotPasswordApi) && str_contains($forgotPasswordApi, 'st_send_password_reset_email') && str_contains($resetPasswordApi, 'st_send_password_changed_email') && str_contains($updatePasswordApi, 'st_send_password_changed_email'), 'password reset and account password changes send security email while preserving generic reset responses');
$expect(is_string($forgotPasswordApi) && str_contains($forgotPasswordApi, "gmdate('Y-m-d H:i:s'") && is_string($resetPasswordApi) && str_contains($resetPasswordApi, 'UTC_TIMESTAMP()'), 'password reset expiry is written and checked in UTC to avoid server timezone mismatches');
$expect(is_string($contactInviteApi) && str_contains($contactInviteApi, 'st_send_trusted_contact_invitation_email') && is_string($groupInviteApi) && str_contains($groupInviteApi, 'st_send_group_invitation_email') && str_contains($groupInviteApi, "'group-invite-user'"), 'trusted-contact and persistent-group invitations use shared templates and rate limits');
$authThemeScript = file_get_contents(__DIR__ . '/../auth-theme.js');
$welcomePage = file_get_contents(__DIR__ . '/../welcome.php');
$authBackgroundPhotos = [
    'safaritrak-auth-01.jpg',
    'safaritrak-auth-02.jpg',
    'safaritrak-auth-03.jpg',
    'safaritrak-auth-04.jpg',
    'safaritrak-auth-05.jpg',
];
$allAuthPhotosExist = true;
$allAuthPhotosIncluded = is_string($authThemeScript);
foreach ($authBackgroundPhotos as $photo) {
    $allAuthPhotosExist = $allAuthPhotosExist && is_file(__DIR__ . '/../assets/images/' . $photo);
    $allAuthPhotosIncluded = $allAuthPhotosIncluded && str_contains($authThemeScript, $photo);
}
$rotationIntervalMatch = [];
$photoImageRule = [];
$hasSlowAuthRotation = is_string($authThemeScript)
    && preg_match('/photoRotationIntervalMs = (\d+)/', $authThemeScript, $rotationIntervalMatch) === 1
    && (int) $rotationIntervalMatch[1] >= 120000;
$authPhotoRuleIsUnfiltered = is_string($authTheme)
    && preg_match('/\.auth-background-rotator img\s*\{([^}]*)\}/s', $authTheme, $photoImageRule) === 1
    && preg_match('/(?:^|;)\s*(?:-webkit-)?filter\s*:/', $photoImageRule[1]) !== 1;
$expect(is_string($authTheme) && str_contains($authTheme, '.auth-visually-hidden'), 'public account forms have a reusable visually-hidden label style');
$expect(
    is_string($authTheme)
    && str_contains($authTheme, 'assets/images/safaritrak-auth-01.jpg')
    && str_contains($authTheme, 'var(--auth-background-position) / cover no-repeat fixed')
    && str_contains($authTheme, '--auth-background-position: 60% center;')
    && str_contains($authTheme, '@media (max-width: 700px)')
    && str_contains($authTheme, '.welcome-page .welcome-visual')
    && str_contains($authTheme, '.auth-background-rotator img')
    && str_contains($authTheme, 'height: 100dvh;')
    && str_contains($authTheme, 'backdrop-filter: var(--auth-glass-blur) !important;')
    && str_contains($authTheme, 'object-fit: cover;')
    && is_string($welcomePage)
    && str_contains($welcomePage, 'auth-theme.js')
    && $allAuthPhotosIncluded
    && $hasSlowAuthRotation
    && $authPhotoRuleIsUnfiltered
    && str_contains($authThemeScript, 'prefers-reduced-motion: reduce')
    && $allAuthPhotosExist,
    'welcome and account pages rotate five slow-changing photos without filtering the images'
);
foreach ([
    'login.php' => ['loginUsername', 'loginPassword'],
    'signup.php' => ['fullName', 'signupUsername', 'email', 'signupPhone', 'signupPassword'],
    'forgot-password.php' => ['resetContact'],
    'reset-password.php' => ['newPassword', 'confirmPassword'],
    'org-signup.php' => ['orgName', 'fullName', 'orgUsername', 'orgEmail', 'orgPhone', 'orgPassword'],
] as $authRoute => $fieldIds) {
    $authSource = file_get_contents(__DIR__ . '/../' . $authRoute);
    foreach ($fieldIds as $fieldId) {
        $expect(
            is_string($authSource) && str_contains($authSource, 'for="' . $fieldId . '"'),
            "{$authRoute} provides an explicit label for {$fieldId}"
        );
    }
}
$expect(is_string($dashboardScript) && str_contains($dashboardScript, "localStorage.getItem('safaritrak-theme')") && str_contains($dashboardScript, "document.documentElement.dataset.theme = savedTheme"), 'shared page scripts restore the saved theme on legacy routes');
$fallbackDashboardMapScript = file_get_contents(__DIR__ . '/../dashboard-map.js');
$dashboardPlanner = file_get_contents(__DIR__ . '/../dashboard-planner.js');
$routeDirections = file_get_contents(__DIR__ . '/../route-directions.js');
$directionsApi = file_get_contents(__DIR__ . '/../backend/api/routes/directions.php');
$googleDashboardMapScript = file_get_contents(__DIR__ . '/../dashboard-map-google.js');
$mapLibreAppMapBackground = file_get_contents(__DIR__ . '/../app-map-background.js');
$mapStyle = file_get_contents(__DIR__ . '/../map-style.js');
$googleMapLoader = file_get_contents(__DIR__ . '/../google-maps-loader.js');
$googleLocationMarker = file_get_contents(__DIR__ . '/../google-location-marker.js');
$googleDarkMapStyle = file_get_contents(__DIR__ . '/../assets/google-maps-dark-style.json');
$mapViewState = file_get_contents(__DIR__ . '/../map-view-state.js');
$googleAppMapBackground = file_get_contents(__DIR__ . '/../app-map-background-google.js');
$appMapBackgroundView = file_get_contents(__DIR__ . '/../backend/views/app-map-background.php');
$sharedLocationsApi = file_get_contents(__DIR__ . '/../backend/api/journeys/active-shared-locations.php');
$locationAutocomplete = file_get_contents(__DIR__ . '/../location-autocomplete.js');
$locationSuggestionsApi = file_get_contents(__DIR__ . '/../backend/api/places/suggest.php');
$journeyStartScript = file_get_contents(__DIR__ . '/../start-journey.js');
$journeyStartApi = file_get_contents(__DIR__ . '/../backend/api/journeys/start.php');
$journeyActivationApi = file_get_contents(__DIR__ . '/../backend/api/journeys/activate.php');
$plannedJourneyMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0008_planned_journeys.php');
$contactSearchApi = file_get_contents(__DIR__ . '/../backend/api/contacts/search.php');
$contactRequestApi = file_get_contents(__DIR__ . '/../backend/api/contacts/add.php');
$contactInviteApi = file_get_contents(__DIR__ . '/../backend/api/contacts/invite.php');
$trustedContactsPage = file_get_contents(__DIR__ . '/../trusted-contacts.php');
$registerApi = file_get_contents(__DIR__ . '/../backend/api/register.php');
$liveShareApi = file_get_contents(__DIR__ . '/../backend/api/journeys/public-share.php');
$liveShareCreationApi = file_get_contents(__DIR__ . '/../backend/api/journeys/share-link.php');
$sharedLocationPage = file_get_contents(__DIR__ . '/../shared-location.php');
$appUrlHelper = file_get_contents(__DIR__ . '/../backend/includes/app-url.php');
$inviteMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0005_trusted_contact_invites.sql');
$liveShareMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0006_journey_share_links.sql');
$trustedContactsScript = file_get_contents(__DIR__ . '/../trusted-contacts.js');
$expect(is_string($shellStart) && str_contains($shellStart, 'id="startPoint"') && str_contains($shellStart, 'id="endPoint"'), 'Explore planner offers separate origin and destination fields');
$expect(is_string($shellEnd) && str_contains($shellEnd, 'dashboard-planner.js'), 'Explore loads the route planning and trip actions');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'SafariTrakRouteDirections.getDrivingRoute') && str_contains($dashboardPlanner, 'setPlannedRoute'), 'Explore previews a road route on the map');
$expect(is_string($directionsApi) && str_contains($directionsApi, 'st_require_login()') && str_contains($directionsApi, 'routing.openstreetmap.de') && str_contains($directionsApi, 'CURLOPT_TIMEOUT => 6'), 'Road directions use an authenticated, bounded server request with a provider fallback');
$expect(is_string($routeDirections) && str_contains($routeDirections, 'directions.php') && str_contains($routeDirections, 'router.project-osrm.org') && str_contains($routeDirections, 'Promise.any') && str_contains($routeDirections, 'Route search timed out'), 'Shared route client races browser-accessible directions against the authenticated fallback');
$expect(is_string($fallbackDashboardMapScript) && str_contains($fallbackDashboardMapScript, 'setPlannedRoute: setPlannedDashboardRoute') && str_contains($fallbackDashboardMapScript, 'routeColors.stroke') && str_contains($fallbackDashboardMapScript, 'safaritrak-planned-route-core'), 'Backup flat map draws a fitted active planned road route');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'routeAvailable') && str_contains($dashboardPlanner, 'retryDashboardRoute') && str_contains($fallbackDashboardMapScript, 'plannedRoute.approximate'), 'Explore keeps trip actions gated and draws an approximate route when directions fail');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'routeRequestId') && str_contains($dashboardPlanner, 'An approximate line is shown while directions load.'), 'Explore ignores stale route responses and shows the route while directions load');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, "fields.startLabel.addEventListener('safaritrak-location-selected'") && str_contains($dashboardPlanner, 'locationSelected(event, fields.endLat, fields.endLng)'), 'Selecting either Explore suggestion directly supplies coordinates and starts route lookup');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'select(options[activeIndex >= 0 ? activeIndex : 0])') && str_contains($locationAutocomplete, 'Choose a place from the suggestions before finding a route'), 'Explore location suggestions can be selected with Enter and explain the route prerequisite');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'fetchBrowserSuggestions') && str_contains($locationAutocomplete, 'Promise.any') && str_contains($locationAutocomplete, 'activeController?.abort()'), 'Autocomplete races Google against browser geocoders and cancels stale searches');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'timeoutMilliseconds = 12000') && str_contains($locationAutocomplete, 'Google Places search timed out.'), 'Autocomplete allows slow public geocoders to return while keeping searches bounded');
$expect(is_string($locationSuggestionsApi) && str_contains($locationSuggestionsApi, 'session_write_close()') && str_contains($locationSuggestionsApi, 'CURLOPT_TIMEOUT => 3'), 'Server geocoding releases the session lock and bounds provider delays');
$expect(is_string($directionsApi) && str_contains($directionsApi, 'session_write_close()'), 'Route provider requests do not hold the authenticated session lock');
$expect(
    is_string($trackingPageSource)
    && str_contains($trackingPageSource, 'route-directions.js')
    && str_contains($trackingPageSource, 'SafariTrakRouteDirections.getDrivingRoute')
    && str_contains($trackingPageSource, 'approximate direction, not a road route'),
    'Current individual tracking shows a road route or clearly marked approximate direction'
);
$expect(is_string($shellStart) && str_contains($shellStart, 'Type and select both locations from the suggestions to preview a route'), 'Explore explains that route locations must be selected from autocomplete');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'dashboard-route-spinner') && str_contains($dashboardCss, '.explore-shortcuts a:nth-child(3) i') && str_contains($dashboardCss, '.group-hub-tabs'), 'Explore and shared navigation use route loading feedback and a unified groups hub');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'planned_departure_at: plannedDeparture') && str_contains($dashboardPlanner, 'share-link.php') && is_string($shellStart) && str_contains($shellStart, 'Schedule trip'), 'Explore can schedule and share a saved trip');
$expect(is_string($shellStart) && str_contains($shellStart, 'id="useCurrentStartLocation"') && str_contains($shellStart, 'id="sharedJourneyUrl"') && str_contains($shellStart, 'View shared journey'), 'Explore offers current-location origin and pasted shared-journey controls');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'startUsesCurrentLocation') && str_contains($dashboardPlanner, 'getCurrentPosition') && str_contains($dashboardPlanner, 'action === \'start\' && !startUsesCurrentLocation'), 'Starting immediately requires an explicitly selected current device location');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'public-share.php') && str_contains($dashboardPlanner, 'shared-location.php') && str_contains($dashboardPlanner, 'setInterval') && str_contains($dashboardPlanner, 'showSharedJourney'), 'Pasted shared links are validated, refreshed, and displayed on the map');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'showSharedJourney') && str_contains($googleDashboardMapScript, 'strokeColor: routeColors.stroke') && str_contains($googleDashboardMapScript, 'publicJourneyLines'), 'Google map renders shared journey locations and an active route');
$expect(is_string($fallbackDashboardMapScript) && str_contains($fallbackDashboardMapScript, 'showSharedDashboardJourney') && str_contains($fallbackDashboardMapScript, 'routeColors.stroke') && str_contains($fallbackDashboardMapScript, 'public-shared-current'), 'Fallback map renders shared journey locations and an active route');
$expect(
    is_string($sharedLocationPage)
    && str_contains($sharedLocationPage, 'data-theme="dark"')
    && str_contains($sharedLocationPage, 'theme.css?v=')
    && str_contains($sharedLocationPage, 'planned-trip-route-glow')
    && str_contains($sharedLocationPage, 'routeColors.stroke'),
    'Public shared-trip map keeps the basemap and draws the active route'
);
$expect(is_string($shellEnd) && !str_contains($shellEnd, 'mobile-bottom-nav'), 'shared shell relies on the standard navbar instead of a duplicate mobile bar');
$expect(is_string($dashboard) && str_contains($dashboard, 'safariDashboardMapConfig'), 'Explore dashboard provides its map configuration');
$expect(is_string($dashboard) && str_contains($dashboard, 'id="dashboardMapCanvas"'), 'Explore keeps map controls outside the Google Maps rendering container');
$expect(is_string($fallbackDashboardMapScript) && str_contains($fallbackDashboardMapScript, "getElementById('dashboardMapCanvas')"), 'Explore initializes MapLibre on its dedicated canvas');
$expect(is_string($dashboard) && str_contains($dashboard, 'data-dashboard-projection="flat" aria-selected="true"'), 'Explore defaults to a flat map');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, "mapTypeId: 'roadmap'"), 'Explore starts in the shared road-map mode');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'window.SafariTrakMapView?.save'), 'Explore saves its Google map camera for other pages');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'styles: mapStyles()') && str_contains($googleDashboardMapScript, 'mapId ? { mapId }'), 'Explore uses the Cloud Map ID when configured and falls back to local styles');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'billingWarning') && str_contains($googleDashboardMapScript, 'Google Maps reports a configuration problem') && str_contains($googleDashboardMapScript, 'browser-key restrictions'), 'Explore explains the generic Google Maps configuration warning without misdiagnosing billing');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'bounds.contains(marker.getPosition())'), 'Explore recentres on the user marker when it is outside the visible map bounds');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.explore-map-heading.has-error') && str_contains($dashboardCss, 'max-width: min(340px'), 'Explore map billing errors stay in a compact overlay');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, "visibility: 'off'"), 'Explore hides point-of-interest icons and labels');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'SafariTrakGoogleLocationMarker.create'), 'Explore uses the pulsing HTML user-location marker');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'center: { lat: -1.286389, lng: 36.817223 }') && str_contains($googleDashboardMapScript, 'zoom: 15') && str_contains($googleDashboardMapScript, 'refreshData().then(() => locateOnMap(true))'), 'Google Explore uses Nairobi as its fallback and requests current location on startup');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'scrollwheel: false'), 'Explore disables wheel zoom');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'sharedMarkerListeners'), 'Explore does not accumulate shared-location marker listeners');
$expect(is_string($googleMapLoader) && str_contains($googleMapLoader, 'maps.googleapis.com/maps/api/js'), 'Google Maps JavaScript API loader is configured');
$expect(
    is_string($routeDirections)
    && str_contains($routeDirections, 'function focusMobileRoute')
    && str_contains($routeDirections, 'offset: [-canvas.clientWidth * 0.22, canvas.clientHeight * 0.25]')
    && str_contains($routeDirections, 'function focusMobileLeafletRoute')
    && str_contains($routeDirections, 'map.panBy([-size.x * 0.22, size.y * 0.25]')
    && str_contains($routeDirections, 'function focusMobileGoogleRoute')
    && str_contains($routeDirections, 'map.setHeading(')
    && str_contains($routeDirections, 'map.panBy(Math.round(canvas.clientWidth * 0.22), -Math.round(canvas.clientHeight * 0.25))')
    && str_contains($routeDirections, 'zoom: 16'),
    'mobile route framing keeps the origin low-left, follows route bearing, and stays close across map renderers'
);
$expect(
    is_string($shellStart)
    && str_contains($shellStart, "getenv('SAFARITRAK_GOOGLE_MAPS_API_KEY')")
    && str_contains($shellStart, 'name="google-maps-api-key"')
    && str_contains($shellStart, 'name="google-map-id"')
    && str_contains($shellStart, 'htmlspecialchars($googleMapsApiKey'),
    'Explore safely provides the configured restricted Maps key and optional Map ID to the browser loader'
);
$expect(is_string($shellStart) && str_contains($shellStart, 'maplibre-gl@5.6.2/dist/maplibre-gl.css') && str_contains($shellStart, 'map-style.js') && is_string($shellEnd) && str_contains($shellEnd, 'maplibre-gl@5.6.2/dist/maplibre-gl.js') && str_contains($shellEnd, 'dashboard-map.js'), 'Explore loads the shared styled MapLibre map before dashboard initialization');
$expect(is_string($googleMapLoader) && str_contains($googleMapLoader, 'libraries=places'), 'Google Places library loads with Maps');
$expect(is_string($googleMapLoader) && str_contains($googleMapLoader, '}, 30_000)') && str_contains($googleMapLoader, "importLibrary('maps')") && str_contains($googleMapLoader, "typeof window.google?.maps?.Map === 'function'"), 'Google Maps loader waits for its Maps library and tolerates slow API responses');
$googleStyleRules = json_decode((string) $googleDarkMapStyle, true, 512, JSON_THROW_ON_ERROR);
$expect(is_array($googleStyleRules) && count($googleStyleRules) >= 12, 'Google Maps dark style import is valid JSON');
$expect(is_string($googleDarkMapStyle) && str_contains($googleDarkMapStyle, '#050817') && str_contains($googleDarkMapStyle, '#7654C9') && str_contains($googleDarkMapStyle, '#C97835'), 'Google Maps style uses the Cyber Aurora land, boundary, and highway colors');
$expect(
    is_string($googleDashboardMapScript)
    && is_string($googleAppMapBackground)
    && str_contains($googleDashboardMapScript, "color('--map-road-major'")
    && str_contains($googleDashboardMapScript, "color('--map-waterway'")
    && str_contains($googleDashboardMapScript, "'#B14DFF'")
    && str_contains($googleDashboardMapScript, "'#FF8A24'")
    && str_contains($googleAppMapBackground, "color('--map-road-major'")
    && str_contains($googleAppMapBackground, "color('--map-waterway'")
    && str_contains($googleAppMapBackground, "'#B14DFF'")
    && str_contains($googleAppMapBackground, "'#FF8A24'"),
    'Google map fallbacks use the shared theme-aware road and river colors'
);
$expect(is_string($googleDarkMapStyle) && str_contains($googleDarkMapStyle, '"featureType": "poi"') && str_contains($googleDarkMapStyle, '"visibility": "off"'), 'Google Maps style hides point-of-interest icons and labels');
$expect(is_string($googleLocationMarker) && str_contains($googleLocationMarker, 'OverlayView') && str_contains($googleLocationMarker, 'google-user-location-marker__pulse'), 'Custom current-location marker is an accessible Google Maps HTML overlay');
$expect(is_string($mapViewState) && str_contains($mapViewState, 'safaritrak-map-view-v5') && str_contains($mapViewState, "mapProjection === 'globe' ? 'globe' : 'flat'"), 'Shared map camera state preserves the selected globe projection and flat-map default');
$expect(
    is_string($mapLibreAppMapBackground)
    && str_contains($mapLibreAppMapBackground, 'center: [36.817223, -1.286389]')
    && str_contains($mapLibreAppMapBackground, 'zoom: 15')
    && is_string($fallbackDashboardMapScript)
    && str_contains($fallbackDashboardMapScript, 'center: [36.817223, -1.286389]')
    && !str_contains($fallbackDashboardMapScript, 'locateOnDashboardMap(false)')
    && is_string($googleAppMapBackground)
    && str_contains($googleAppMapBackground, 'center: { lat: -1.286389, lng: 36.817223 }')
    && str_contains($googleAppMapBackground, 'zoom: 15'),
    'Shared maps and Explore retain Nairobi as a fallback while requesting current location on startup'
);
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'requestUserLocation(true);') && str_contains($googleAppMapBackground, "addEventListener('click', () => requestUserLocation(true))"), 'Shared Google maps request current location on startup and retain the manual location control');
$expect(
    is_string($mapLibreAppMapBackground)
    && str_contains($mapLibreAppMapBackground, 'requestUserLocation(true);')
    && is_string($fallbackDashboardMapScript)
    && str_contains($fallbackDashboardMapScript, 'locateOnDashboardMap();'),
    'MapLibre shared maps and Explore request current location on startup'
);
$expect(
    is_string($googleAppMapBackground)
    && str_contains($googleAppMapBackground, 'focusMobileGoogleRoute')
    && str_contains($googleAppMapBackground, "feature.routeColor === 'completed'")
    && is_string($googleDashboardMapScript)
    && str_contains($googleDashboardMapScript, 'focusMobileGoogleRoute')
    && str_contains($googleDashboardMapScript, 'routeColors.completedStroke'),
    'Google Maps fallbacks preserve mobile route focus and completed-route semantics'
);
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--explore .sidebar {'), 'Explore navigation floats as a glass panel');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.sidebar:is(:hover, :focus-within)') && str_contains($dashboardCss, 'width: 244px;') && str_contains($dashboardCss, 'font-size: 12px;'), 'Desktop side navigation expands on hover or keyboard focus to show page names');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--explore > main > .app-header') && str_contains($dashboardCss, 'background: transparent !important;') && str_contains($dashboardCss, '.app--explore:has(> .sidebar:is(:hover, :focus-within)) .app-search-row') && str_contains($dashboardCss, 'left: 274px;'), 'Explore removes the empty full-width header bar and shifts floating content clear of the expanded navigation');
$plannerHoverRules = [];
$plannerHoverRuleCount = is_string($dashboardCss)
    ? preg_match_all('/\.app--explore:has\(> \.sidebar:is\(:hover, :focus-within\)\) \.app-search-row\s*\{([^}]*)\}/', $dashboardCss, $plannerHoverRules)
    : false;
$plannerHoverRulesAreCompact = $plannerHoverRuleCount > 0;
foreach ($plannerHoverRules[1] ?? [] as $plannerHoverRule) {
    $plannerHoverRulesAreCompact = $plannerHoverRulesAreCompact
        && str_contains($plannerHoverRule, 'width: min(320px, calc(100vw - 32px));');
}
$expect($plannerHoverRulesAreCompact, 'Explore keeps the compact route planner width when navigation is hovered or focused');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '--p: var(--accent-primary)') && str_contains($dashboardCss, '--panel: var(--bg-panel)') && str_contains($dashboardCss, '--bg: var(--bg-app)'), 'Signed-in pages resolve legacy aliases to shared theme tokens');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'journey-filter-bar::after') && str_contains($dashboardCss, '.content .card:has(.journey-list)'), 'Map page filters and repeated records render as separate floating tiles');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.explore-map-heading {'), 'Explore map status uses a glass container');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '):is(:hover, :focus-within, .is-engaged)::after'), 'animated glass outline is limited to the interacted panel');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '@media (prefers-reduced-motion: reduce)'), 'floating outline respects reduced-motion preference');
$authThemeCss = file_get_contents(__DIR__ . '/../auth-theme.css');
$expect(is_string($authThemeCss) && str_contains($authThemeCss, '.login-card:is(:hover, :focus-within, .is-engaged)::after'), 'auth panel outline activates only during interaction');
$expect(is_string($authThemeCss) && !str_contains($authThemeCss, 'body:has(#loginForm, #signupForm, .login-card) .auth-background-rotator'), 'auth theme keeps the rotating background slideshow visible');
$expect(is_string($authThemeCss) && str_contains($authThemeCss, '--auth-panel: var(--bg-panel)') && str_contains($authThemeCss, '--auth-glass-surface: var(--app-glass-surface)') && str_contains($authThemeCss, '--auth-glass-blur: var(--glass-surface-blur)'), 'auth panels use shared translucent surfaces and blur tokens');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '--glass-surface: var(--bg-panel)') && str_contains($dashboardCss, '--glass-control: var(--bg-input)') && str_contains($dashboardCss, '--glass-blur: var(--glass-surface-blur)'), 'dashboard glass aliases resolve to shared theme tokens');
$expect(
    is_string($themeCss)
    && str_contains($themeCss, '--bg-panel: rgba(151, 196, 221, 0.4);')
    && str_contains($themeCss, '--bg-panel: rgba(16, 21, 45, 0.4);')
    && str_contains($themeCss, '--glass-surface-blur: blur(8px);'),
    'light and dark themes use map-tinted 40%-opaque surfaces and an 8px blur'
);
$expect(
    is_string($themeCss)
    && substr_count($themeCss, '--glass-control-blur: blur(12px);') >= 2
    && str_contains($dashboardCss, 'button:not(.app-sos-confirm):not(.app-sos-cancel):not(.app-navbar-sos):not(.sos-button)')
    && str_contains($dashboardCss, 'background-color: color-mix(in srgb, var(--accent-primary) 10%, var(--bg-panel-inner)) !important;'),
    'shared secondary controls use stronger glass blur and theme-accent surfaces'
);
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, '@media (min-width: 768px) and (max-width: 1100px)')
    && str_contains($dashboardCss, '.app--explore .explore-panels')
    && str_contains($dashboardCss, 'width: min(320px, calc(50vw - 24px)) !important;')
    && str_contains($dashboardCss, 'visibility: visible !important;'),
    'tablet widths use a visible compact navigation and side-by-side Explore panels'
);
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, 'background: var(--app-glass-surface) !important;')
    && str_contains($dashboardCss, 'backdrop-filter: var(--glass-surface-blur) !important;')
    && is_string($authThemeCss)
    && str_contains($authThemeCss, 'backdrop-filter: var(--glass-surface-blur) !important;'),
    'application and authentication panels use the shared glass treatment'
);
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '[class$="-panel"]') && str_contains($dashboardCss, '[class$="-card"]') && str_contains($dashboardCss, '.fleet-vehicle,') && str_contains($dashboardCss, 'html :is(.dashboard-planner-field, .dashboard-planner-departure)') && str_contains($dashboardCss, 'background: var(--bg-panel) !important;') && str_contains($dashboardCss, 'input:not([type="checkbox"])'), 'all app panels, forms, and text controls share the glass treatment');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.content [class$="-panel"]') && str_contains($dashboardCss, 'border-radius: var(--glass-radius) !important;') && str_contains($dashboardCss, 'theme.css?v=10'), 'nested app panels use the updated global theme styling');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'html[data-theme] .app--map-layout:not(.app--explore)') && str_contains($dashboardCss, 'background: transparent !important;'), 'map-backed pages leave the shared map visible beneath their panels');
$expect(is_string($authThemeCss) && str_contains($authThemeCss, '--auth-glass-surface: var(--app-glass-surface)') && str_contains($authThemeCss, '--auth-glass-control: var(--bg-input)') && str_contains($authThemeCss, '--auth-glass-blur: var(--glass-surface-blur)'), 'authentication panels and fields share the matching dark and light glass treatment');
$expect(is_string($authThemeCss) && str_contains($authThemeCss, 'body:has(.login-card)') && str_contains($authThemeCss, 'overflow: visible'), 'auth sheets scroll with the page instead of showing an inner side rail');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'google-user-location-marker__pulse'), 'current-location marker has a neon pulse treatment');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--map-layout:not(.app--explore) > main > header') && str_contains($dashboardCss, 'width: max-content'), 'signed-in page header is a compact corner control group');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--explore .content--explore {'), 'Explore map fills the page beneath floating controls');
$expect(is_string($appMapBackgroundView) && str_contains($appMapBackgroundView, 'app-map-background.js') && !str_contains($appMapBackgroundView, 'app-map-background-google.js') && str_contains($appMapBackgroundView, '$showAppMapBackground = $showAppMapBackground ?? true'), 'Authenticated pages use the shared MapLibre background by default');
$expect(is_string($shellStart) && str_contains($shellStart, '$showAppMapBackground = $showAppMapBackground ?? !$isExploreDashboard'), 'Shared app shell enables the background map on non-Explore pages');
$expect(
    is_string($mapLibreAppMapBackground)
    && str_contains($mapLibreAppMapBackground, "projection: { type: 'mercator' }")
    && str_contains($mapLibreAppMapBackground, 'map.resize()'),
    'shared map starts in a consistent projection and resizes after style load'
);
$expect(
    is_string($mapStyle)
    && str_contains($mapStyle, 'dark-matter-gl-style/style.json')
    && str_contains($mapStyle, 'positron-gl-style/style.json')
    && str_contains($mapStyle, "cssColor('--map-road-highway'")
    && str_contains($mapStyle, "cssColor('--map-road-secondary'")
    && str_contains($mapStyle, "cssColor('--map-road-major'")
    && str_contains($mapStyle, "cssColor('--map-road-service'")
    && str_contains($mapStyle, "cssColor('--map-waterway'")
    && str_contains($mapStyle, "['to-number', ['get', 'height'], 0]")
    && str_contains($mapStyle, 'palette.buildingTall')
    && str_contains($mapStyle, "cssColor('--route-stroke'")
    && str_contains($mapStyle, "id.startsWith('road_pri')")
    && str_contains($mapStyle, 'palette.roadMajor')
    && str_contains($mapStyle, "['tertiary', 'tertiary_link']")
    && str_contains($mapStyle, 'palette.landuseRetail')
    && str_contains($mapStyle, "id.startsWith('road_service')")
    && str_contains($mapStyle, "cssColor('--map-farmland'")
    && str_contains($mapStyle, "cssColor('--route-completed-stroke'")
    && str_contains($mapStyle, '`--group-member-${index}`')
    && str_contains($mapStyle, "cssColor('--map-marker-sos'")
    && str_contains($mapLibreAppMapBackground, 'SafariTrakMapStyle.apply(map)')
    && str_contains($fallbackDashboardMapScript, 'SafariTrakMapStyle.apply(dashboardMapState.map)'),
    'Explore and shared maps use the shared Cyber Aurora CARTO palette'
);
$legacyMapScripts = [
    file_get_contents(__DIR__ . '/../group-travel.js'),
    file_get_contents(__DIR__ . '/../tracking.js'),
    file_get_contents(__DIR__ . '/../watch-journey.js'),
    file_get_contents(__DIR__ . '/../view-shared.js'),
    file_get_contents(__DIR__ . '/../view-journey.js'),
    file_get_contents(__DIR__ . '/../tracking.php'),
    file_get_contents(__DIR__ . '/../watch-journey.php'),
];
$expect(
    is_string($authGuard)
    && str_contains($authGuard, 'EXISTS (SELECT 1 FROM platform_admins')
    && str_contains($authGuard, 'SELECT oa.organization_id FROM organization_admins')
    && str_contains($authGuard, 'st_destroy_current_session($db)')
    && !str_contains($authGuard, 'SELECT * FROM users'),
    'authenticated pages load only required user fields, resolve roles in one query, and revoke invalid sessions'
);
$expect(
    is_string($organizationDashboard)
    && str_contains($organizationDashboard, '$isSchool = false;')
    && str_contains($organizationDashboard, 'SELECT o.id, o.name, o.is_suspended')
    && !str_contains($organizationDashboard, 'SELECT o.*, p.account_type'),
    'organization onboarding and review states render without undefined account-type state'
);
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, 'top: 142px !important;'),
    'shared map controls clear standard page headers at phone widths'
);
$expect(
    !str_contains(implode("\n", array_filter($legacyMapScripts, 'is_string')), 'tile.openstreetmap.org')
    && !str_contains(implode("\n", array_filter($legacyMapScripts, 'is_string')), 'basemaps.cartocdn.com/dark_all/')
    && substr_count(implode("\n", array_filter($legacyMapScripts, 'is_string')), 'SafariTrakMapStyle.attachLeafletBase(') >= 7,
    'legacy Leaflet map views use the shared multicolor MapLibre basemap'
);
$expect(
    is_string($shellEnd)
    && str_contains($shellEnd, 'dashboard-map.js')
    && !str_contains($shellEnd, 'dashboard-map-google.js')
    && is_string($mapViewState)
    && str_contains($mapViewState, "mapProjection === 'globe' ? 'globe' : 'flat'"),
    'Explore uses the shared map renderer without satellite imagery'
);
$expect(is_string($appMapBackgroundView) && str_contains($appMapBackgroundView, 'appMapLocate'), 'Map-backed app pages provide an explicit location control');
$expect(is_string($dashboardCss) && preg_match('/#appMapBackground\s*\{\s*display:\s*block;/s', $dashboardCss) === 1, 'Shared map container is visible immediately without waiting for user interaction');
$expect(is_string($appMapBackgroundView) && str_contains($appMapBackgroundView, 'appMapFocus'), 'Map-backed pages provide an accessible map focus toggle');
$expect(is_string($appMapBackgroundView) && str_contains($appMapBackgroundView, 'aria-label="Back"'), 'Map-backed pages provide a Back control to restore hidden panels');
$expect(is_string($dashboardScript) && str_contains($dashboardScript, 'page-panel-focus') && str_contains($dashboardScript, 'is-page-panel-selected'), 'Selecting a page panel focuses it over the map across map-backed screens');
$expect(
    is_string($dashboardScript)
    && str_contains($dashboardScript, "event.target.closest('[data-map-review]')")
    && str_contains($dashboardScript, "document.getElementById('appMapBackground')")
    && str_contains($dashboardScript, "content.addEventListener('focusin', focusMapReviewPanel)"),
    'Only map-review controls on pages with a map trigger panel focus'
);
$expect(
    is_string($dashboardCss)
    && !str_contains($dashboardCss, '.page-panel-focus .app--map-layout:not(.app--explore):not(.app--journeys) > main > header')
    && !str_contains($dashboardCss, '.page-panel-focus .app--map-layout:not(.app--explore):not(.app--journeys) .sidebar')
    && preg_match('/\.page-panel-focus \.app--map-layout:not\(\.app--explore\):not\(\.app--journeys\) \.mobile-bottom-nav \{\s*visibility: visible;/s', $dashboardCss) === 1,
    'The top navigation remains visible while a map-review panel is focused'
);
$expect(is_string($dashboardCss) && str_contains($dashboardCss, ':has(.is-page-panel-selected)') && str_contains($dashboardCss, '.page-panel-focus .app--map-layout'), 'Map-backed pages hide competing panels while keeping the selected panel available');
$expect(
    is_string($mapLibreAppMapBackground)
    && str_contains($mapLibreAppMapBackground, 'function activateMap()')
    && str_contains($mapLibreAppMapBackground, 'mapLoadStarted = false')
    && preg_match('/\n\s+activateMap\(\);\s*\}\)\(\);\s*$/', $mapLibreAppMapBackground) === 1,
    'Shared page maps begin loading as soon as the page map script runs'
);
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'map-focus-mode'), 'Map gestures hide floating panels and can restore them');
$navigation = file_get_contents(__DIR__ . '/../backend/views/app-navigation.php');
$expect(is_string($navigation) && str_contains($navigation, 'href="index.php"') && str_contains($navigation, 'href="places.php"'), 'Authenticated pages share the Explore navigation');
$expect(!is_string($navigation) || !str_contains($navigation, 'safety.php'), 'Standalone Safety navigation is removed');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'center: { lat: -1.286389, lng: 36.817223 }'), 'Shared Google map retains Nairobi as its location-unavailable fallback');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'gestureHandling: \'greedy\''), 'Shared Google map preserves map panning');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'scrollwheel: false'), 'Shared Google map disables wheel zoom');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'focusFeatures') && str_contains($googleAppMapBackground, 'map.fitBounds(bounds, padding)'), 'Shared Google map fits bounds only when a page explicitly focuses selected features');
$expect(is_string($dashboardCss) && (bool) preg_match('/#appMapBackground\s*\{\s*position:\s*fixed;/', $dashboardCss), 'Shared map background is positioned on desktop as well as mobile');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, "addEventListener('click', () => requestUserLocation(true))"), 'Shared Google map retains its manual current-location control');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'google.maps.Polyline'), 'Shared Google map renders supplied recorded routes');
$expect(is_string($mapLibreAppMapBackground) && str_contains($mapLibreAppMapBackground, 'function focusFeatures') && str_contains($mapLibreAppMapBackground, 'map.fitBounds('), 'Backup page map focuses and fits the selected trip route');
$expect(is_string($myJourneysSource = file_get_contents(__DIR__ . '/../my-journeys.php')) && str_contains($myJourneysSource, "\$appMapPage = 'journeys'") && str_contains($appMapBackgroundView, 'data-map-page='), 'My Trips identifies itself before the shared map script initializes');
$expect(is_string($mapLibreAppMapBackground) && str_contains($mapLibreAppMapBackground, "mapElement.dataset.mapPage === 'journeys'"), 'My Trips map loads only the selected journey instead of every saved trip');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, "mapElement.dataset.mapPage === 'journeys'"), 'My Trips map keeps its route and avoids recentering on device location');
$expect(
    str_contains($mapLibreAppMapBackground, '(isJourneyPage && journeyFeatures.length)')
    && str_contains($googleAppMapBackground, '(isJourneyPage && journeyFeatures.length)'),
    'My Trips keeps the selected route in view when restoring the saved device location'
);
$expect(
    str_contains($dashboardCss, '.app--journeys.journey-route-focus .journey-overview-panel {')
    && str_contains($dashboardCss, 'display: flex !important;')
    && str_contains($mapLibreAppMapBackground, 'left: 480,')
    && str_contains($googleAppMapBackground, 'left: 480,'),
    'trip filters stay visible during route focus and both map providers use the requested desktop fit padding'
);
$journeysSource = file_get_contents(__DIR__ . '/../my-journeys.php');
$journeysScript = file_get_contents(__DIR__ . '/../journeys.js');
$endJourneyPage = file_get_contents(__DIR__ . '/../end-journey.php');
$endJourneyApi = file_get_contents(__DIR__ . '/../backend/api/journeys/end.php');
$expect(is_string($journeysSource) && str_contains($journeysSource, 'journey_positions'), 'Journey history provides recorded route geometry to the map');
$expect(is_string($journeysSource) && str_contains($journeysSource, 'tripCarouselTitle') && str_contains($journeysSource, 'data-tab="planned"'), 'My trips shows selected route details in the trip carousel and includes scheduled journeys');
$expect(is_string($journeysSource) && str_contains($journeysSource, 'id="journeyCarouselData"') && str_contains($journeysSource, "'startLat'") && str_contains($journeysSource, "'endLat'") && str_contains($journeysScript, 'tripCarouselRouteStatus') && str_contains($journeysScript, 'setJourneyFeatures(features, { focus: true })'), 'My trips fits the selected road route and updates carousel details');
$expect(
    is_string($journeysSource)
    && strpos($journeysSource, 'journey-filter-bar') < strpos($journeysSource, 'trip-carousel-panel')
    && str_contains($journeysSource, '$showAppMapPanelsToggle = false')
    && str_contains($journeysSource, '$showAppMapFocusControl = false')
    && str_contains($journeysSource, 'journeys.js?v=')
    && str_contains($journeysScript, 'moveCarousel(-1)')
    && str_contains($journeysScript, 'moveCarousel(1)')
    && str_contains($journeysScript, 'SafariTrakRouteDirections.getDrivingRoute')
    && str_contains($journeysScript, 'routeStatus.textContent = hasRecordedRoute')
    && str_contains($journeysScript, 'routeFeature.route = [[startLng, startLat], [endLng, endLat]]')
    && str_contains($journeysScript, 'approximate direction, not a road route'),
    'My Trips filters and cycles trips and clearly marks approximate fallback directions'
);
$expect(
    is_string($journeysScript)
    && str_contains($journeysScript, "routeFeature.routeColor = ['ended', 'completed'].includes")
    && str_contains($journeysScript, "? 'completed'")
    && str_contains($journeysScript, ": 'planned'")
    && str_contains($journeysScript, 'setJourneyFeatures(features, { focus: true })')
    && str_contains($mapLibreAppMapBackground, "feature.routeColor === 'planned'")
    && str_contains($mapLibreAppMapBackground, "feature.routeColor === 'completed'")
    && !str_contains($mapLibreAppMapBackground, 'plannedRoute ?')
    && str_contains($mapLibreAppMapBackground, 'safaritrak-selected-journey-${mapElement.dataset.mapUserId}')
    && str_contains($mapLibreAppMapBackground, 'sessionStorage.setItem')
    && str_contains($mapLibreAppMapBackground, 'getRouteColors()')
    && str_contains($mapLibreAppMapBackground, 'routeColors.casing')
    && str_contains($mapLibreAppMapBackground, "feature.routeApproximate ? { 'line-dasharray': [2, 2] } : {}")
    && str_contains($googleDashboardMapScript, 'safaritrak-selected-journey-')
    && str_contains($fallbackDashboardMapScript, 'safaritrak-selected-journey-')
    && str_contains($googleAppMapBackground, 'approximateDash')
    && str_contains($mapLibreAppMapBackground, 'getRouteColors()')
    && str_contains($mapLibreAppMapBackground, "'line-width': isPlannedRoute ? 4 : 5")
    && str_contains($mapLibreAppMapBackground, "'line-width': 8")
    && str_contains($mapLibreAppMapBackground, "'line-opacity': isPlannedRoute ? 0.28 : 0.22")
    && str_contains($googleAppMapBackground, 'getRouteColors()')
    && str_contains($googleAppMapBackground, 'strokeWeight: isPlannedRoute ? 4 : 5')
    && !str_contains($mapLibreAppMapBackground, "routeColor: 'planned'"),
    'Selected journeys preserve per-user route state and use purple active or turquoise completed styling'
);
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'width: 72px') && str_contains($dashboardCss, '.app--explore .sidebar') && str_contains($dashboardCss, 'content: attr(title)'), 'Desktop navigation is an icon rail with accessible hover labels across pages');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'visibility: hidden') && str_contains($dashboardCss, '.sidebar-close'), 'Mobile navigation is visually hidden when closed and exposes its close control when open');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--explore .explore-shortcuts') && str_contains($dashboardCss, '.app--journeys .trip-carousel-panel'), 'Explore removes duplicate shaded shortcuts and My Trips uses its carousel panel');
$expect(is_string($journeyActivationApi) && str_contains($journeyActivationApi, 'status = "planned"') && is_string($plannedJourneyMigration) && str_contains($plannedJourneyMigration, "ENUM('planned', 'active', 'completed', 'cancelled')"), 'Scheduled trips can be explicitly activated after their schema migration');
$expect(is_string($contactSearchApi) && str_contains($contactSearchApi, 'contact-search-user') && str_contains($contactSearchApi, 'LEFT(LOWER(u.username)') && !str_contains($contactSearchApi, 'LOWER(u.full_name)'), 'Trusted-contact lookup searches verified usernames only');
$expect(is_string($contactRequestApi) && str_contains($contactRequestApi, 'contact_request') && str_contains($contactRequestApi, 'status)') && !str_contains($contactRequestApi, 'Direct contact creation is disabled'), 'Social contact requests create pending friend connections');
$placesScript = file_get_contents(__DIR__ . '/../places.js');
$placesPage = file_get_contents(__DIR__ . '/../places.php');
$placesWorkspaceCss = file_get_contents(__DIR__ . '/../places-workspace.css');
$savedLocationsApi = file_get_contents(__DIR__ . '/../backend/api/places/saved.php');
$savedLocationNoteMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0018_saved_location_notes.php');
$dashboardPlanner = file_get_contents(__DIR__ . '/../dashboard-planner.js');
$expect(is_string($placesScript) && str_contains($placesScript, 'focusFeatures(placeMapFeatures'), 'Nearby search results are drawn as map markers');
$expect(
    is_string($placesPage)
    && str_contains($placesPage, 'app--workspace app--places')
    && str_contains($placesPage, 'places-category-grid')
    && str_contains($placesPage, 'places-saved-panel')
    && is_string($placesWorkspaceCss)
    && str_contains($placesWorkspaceCss, 'width: 380px;')
    && str_contains($placesWorkspaceCss, 'right: 360px;')
    && is_string($placesScript)
    && str_contains($placesScript, 'No nearby stops loaded right now')
    && !str_contains($placesPage, 'data-map-review')
    && str_contains($placesScript, 'View on map')
    && str_contains($placesScript, 'Start trip')
    && str_contains($placesScript, 'tripPlannerUrl(place)')
    && is_string($mapLibreAppMapBackground)
    && str_contains($mapLibreAppMapBackground, "mapElement.dataset.mapPage === 'places'")
    && str_contains($mapLibreAppMapBackground, '{ left: 420, right: 360, top: 100, bottom: 60 }'),
    'Places Nearby uses split glass sidebars, map actions, and a friendly retry state'
);
$expect(
    is_string($placesPage)
    && str_contains($placesPage, 'id="placeSaveNote"')
    && is_string($placesScript)
    && str_contains($placesScript, "activeCategory === 'all' ? place.category : activeCategory")
    && str_contains($placesScript, "note: document.getElementById('placeSaveNote').value.trim()")
    && is_string($savedLocationsApi)
    && str_contains($savedLocationsApi, 'SELECT id, name, category, address, note, lat, lng, created_at')
    && str_contains($savedLocationsApi, 'mb_strlen($note) > 500')
    && is_string($savedLocationNoteMigration)
    && str_contains($savedLocationNoteMigration, 'ADD COLUMN note VARCHAR(500) NULL'),
    'Places save the active type and optional note with length validation'
);
$expect(
    is_string($placesPage)
    && str_contains($placesPage, 'id="addPlaceButton"')
    && str_contains($placesPage, 'id="customPlaceType"')
    && str_contains($placesPage, 'id="customPlaceSearch"')
    && str_contains($placesPage, 'id="pickCustomPlaceOnMap"')
    && str_contains($placesPage, 'id="saveCustomPlaceButton"')
    && is_string($placesScript)
    && str_contains($placesScript, "fetch('backend/api/places/saved.php'")
    && str_contains($placesScript, "safaritrak:places-map-click")
    && is_string($mapLibreAppMapBackground)
    && str_contains($mapLibreAppMapBackground, "safaritrak:places-map-click"),
    'Places can save a typed custom location from search or direct map selection'
);
$expect(
    is_string($placesScript)
    && str_contains($placesScript, 'destination_lat')
    && str_contains($placesScript, "params.set('saved_location_id'")
    && is_string($dashboardPlanner)
    && str_contains($dashboardPlanner, 'requestedDestination.get(\'destination_lat\')')
    && str_contains($dashboardPlanner, 'requestedSavedLocationId')
    && str_contains($dashboardPlanner, 'location.note ? ` — ${location.note}` : \'\''),
    'Saved place trip links prefill the Explore trip planner destination and show saved notes'
);
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'setTimeout(() => fetchSuggestions(query), 250)'), 'Location suggestions debounce network requests while typing');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'aria-activedescendant'), 'Location suggestions support keyboard navigation');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'AutocompleteSuggestion.fetchAutocompleteSuggestions'), 'Location suggestions use Google Places typeahead');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'fetchServerSuggestions(query, signal)'), 'Location suggestions retain the authenticated server fallback');
$expect(is_string($locationSuggestionsApi) && str_contains($locationSuggestionsApi, 'geocoding-api.open-meteo.com') && str_contains($locationSuggestionsApi, 'photon.komoot.io'), 'Location suggestions fall back from Open-Meteo to Photon when providers fail');
$expect(is_string($locationSuggestionsApi) && str_contains($locationSuggestionsApi, 'Location suggestions are temporarily unavailable'), 'Suggestion failures do not imply un-geocoded manual trip locations are valid');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'location-suggestion-status'), 'Location search announces suggestion and configuration errors');
$expect(is_string($placesScript) && str_contains($placesScript, 'Place.searchNearby'), 'Nearby place search uses Google Places');
$expect(is_string($journeyStartScript) && str_contains($journeyStartScript, 'SafariTrakRouteDirections.getDrivingRoute') && str_contains($journeyStartScript, 'routePreviewAvailable'), 'Trip planner requests a real road route and waits for its result');
$expect(is_string($journeyStartScript) && str_contains($journeyStartScript, "routeColor: 'planned'"), 'Planned route previews use theme-aware selected-route styling');
$expect(is_string($journeyStartScript) && str_contains($journeyStartScript, 'end_lat:'), 'Trip creation submits selected destination coordinates');
$expect(is_string($journeyStartScript) && str_contains($journeyStartScript, 'validPoint(startLatVal, startLngVal)') && str_contains($journeyStartScript, 'Choose both locations from the suggestions'), 'Trip submission requires valid coordinates from both selected map locations');
$expect(is_string($journeyStartApi) && str_contains($journeyStartApi, 'Choose both coordinates'), 'Journey API rejects partial coordinate pairs');
$expect(is_string($contactInviteApi) && str_contains($contactInviteApi, 'random_bytes(32)') && str_contains($contactInviteApi, "'SafariTrak friend'"), 'Trusted-contact sign-up invitations need no personal details and use high-entropy random tokens');
$expect(is_string($registerApi) && str_contains($registerApi, 'contactInviteToken') && !str_contains($registerApi, 'WHERE invite_phone = ? AND contact_user_id IS NULL'), 'Phone matches alone no longer auto-link trusted contacts');
$expect(is_string($liveShareCreationApi) && str_contains($liveShareCreationApi, 'time() + 3600') && str_contains($liveShareCreationApi, '$departureTimestamp + 3600'), 'Public trip links expire one hour after creation or scheduled departure');
$expect(is_string($liveShareCreationApi) && str_contains($liveShareCreationApi, "includes/app-url.php") && is_string($appUrlHelper) && str_contains($appUrlHelper, 'function st_app_url()'), 'Live location links load the shared application URL helper');
$expect(is_string($liveShareApi) && str_contains($liveShareApi, 'j.status IN ("active", "planned")') && str_contains($liveShareApi, '$journey[\'status\'] === \'active\''), 'Public trip links expose active location or planned route data only');
$expect(is_string($inviteMigration) && str_contains($inviteMigration, 'trusted_contact_invites'), 'Trusted-contact invites have an additive migration');
$expect(is_string($liveShareMigration) && str_contains($liveShareMigration, 'journey_share_links'), 'One-hour live-share links have an additive migration');
$expect(
    is_string($trustedContactsScript)
    && str_contains($trustedContactsScript, 'shareLiveShareBtn')
    && str_contains($trustedContactsScript, 'createSignupInviteBtn')
    && str_contains($trustedContactsScript, 'copySignupInviteBtn')
    && is_string($trustedContactsPage)
    && str_contains($trustedContactsPage, 'Copy journey link')
    && str_contains($trustedContactsPage, 'Copy sign-up link'),
    'Trusted contacts provides separate copyable temporary-journey and trusted-contact sign-up links'
);
$expect(is_file(__DIR__ . '/../assets/auth-map-landscape.svg') && is_file(__DIR__ . '/../assets/auth-map-portrait.svg'), 'Original landscape and portrait auth visuals are present');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'styles: mapStyles()') && str_contains($googleAppMapBackground, 'mapId ? { mapId }'), 'Shared Google map uses the Cloud Map ID when configured and falls back to local styles');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'billingWarning') && str_contains($googleAppMapBackground, 'Google Maps reports a configuration problem') && str_contains($googleAppMapBackground, 'browser-key restrictions'), 'Shared map backgrounds explain generic Google Maps configuration warnings');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'bounds.contains(position)'), 'Shared map recentres on the user marker when it is outside the visible map bounds');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, "visibility: 'off'"), 'Shared Google map hides point-of-interest icons and labels');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'SafariTrakGoogleLocationMarker.create'), 'Shared Google map uses the pulsing HTML user-location marker');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, "mapType === 'hybrid'"), 'Explore retains Satellite mode when saving its camera');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, "mapType === 'hybrid'"), 'Shared map retains Satellite mode when saving its camera');
$expect(is_string($shellStart) && str_contains($shellStart, "if (!\$isExploreDashboard)") && str_contains($shellStart, 'app--map-layout'), 'Shared application shell uses the map layout outside Explore');
$mapBackedScreens = [
    'admin-admins.php',
    'admin-dashboard.php',
    'admin-organizations.php',
    'admin-users.php',
    'group-travel.php',
    'my-journeys.php',
    'notifications.php',
    'org-dashboard.php',
    'org-groups.php',
    'org-reports.php',
    'org-travelers.php',
    'places.php',
    'settings.php',
    'start-journey.php',
    'trusted-contacts.php',
    'view-journey.php',
    'watch-journey.php',
];
foreach ($mapBackedScreens as $screen) {
    $screenSource = file_get_contents(__DIR__ . '/../' . $screen);
    $expect(is_string($screenSource) && str_contains($screenSource, 'app-map-background.php') && str_contains($screenSource, 'app--map-layout'), "{$screen} uses the shared glass map layout");
}
$navigationScreens = [
    'admin-admins.php', 'admin-dashboard.php', 'admin-organizations.php', 'admin-users.php',
    'end-journey.php',
    'group-travel.php', 'messages.php', 'my-journeys.php',
    'notifications.php', 'org-dashboard.php', 'org-groups.php', 'org-reports.php',
    'org-travelers.php', 'places.php', 'settings.php', 'start-journey.php',
    'trusted-contacts.php', 'view-journey.php', 'watch-journey.php',
];
foreach ($navigationScreens as $screen) {
    $source = file_get_contents(__DIR__ . '/../' . $screen);
    $expect(is_string($source) && str_contains($source, 'app-navigation.php'), "{$screen} uses the shared app navbar");
}
$liveTrackingCompatibilityRoute = file_get_contents(__DIR__ . '/../live-tracking.php');
$expect(is_string($liveTrackingCompatibilityRoute) && str_contains($liveTrackingCompatibilityRoute, "header('Location: my-journeys.php?selected=") && str_contains($liveTrackingCompatibilityRoute, "header('Location: watch-journey.php?id=") && !str_contains($liveTrackingCompatibilityRoute, '<!doctype html>'), 'Live map is removed while owner and shared active-trip links keep safe destinations');
$groupsHub = file_get_contents(__DIR__ . '/../groups.php');
$groupTripsPage = file_get_contents(__DIR__ . '/../group-travel.php');
$expect(is_string($groupsHub) && str_contains($groupsHub, 'Groups sections') && str_contains($groupsHub, "require __DIR__ . '/group-travel.php'") && str_contains($groupsHub, 'href="groups.php?tab=trips"') && is_string($groupTripsPage) && str_contains($groupTripsPage, "header('Location: groups.php?tab=trips')") && str_contains($groupTripsPage, "require_once __DIR__ . '/backend/includes/auth-guard.php'"), 'Persistent groups and group trips share one Groups destination without reloading the auth guard');
$demoCarHireSeeder = file_get_contents(__DIR__ . '/../backend/scripts/seed-demo-car-hire.php');
$expect(is_string($demoCarHireSeeder) && str_contains($demoCarHireSeeder, "PHP_SAPI !== 'cli'") && str_contains($demoCarHireSeeder, "random_bytes(16)") && str_contains($demoCarHireSeeder, 'Existing password is not changed or displayed'), 'Demo car-hire seeding is local CLI-only, additive, and does not reset existing passwords');
$expect(!is_file(__DIR__ . '/../safety.php') && !is_file(__DIR__ . '/../admin-safety.php'), 'standalone traveler and admin safety pages are removed');
foreach (['login.php', 'signup.php', 'forgot-password.php', 'reset-password.php', 'org-signup.php', 'verify-email.php', 'welcome.php', 'contact-invite.php'] as $authScreen) {
    $source = file_get_contents(__DIR__ . '/../' . $authScreen);
    $expect(is_string($source) && str_contains($source, 'auth-theme.css') && str_contains($source, 'auth-theme.js'), "{$authScreen} follows the application theme");
}
foreach (['login.php', 'signup.php', 'forgot-password.php', 'reset-password.php', 'welcome.php'] as $publicScreen) {
    $screenSource = file_get_contents(__DIR__ . '/../' . $publicScreen);
    $expect(is_string($screenSource) && !str_contains($screenSource, 'app-map-background.php'), "{$publicScreen} remains outside the authenticated map layout");
}
$expect(is_string($sharedLocationsApi) && str_contains($sharedLocationsApi, 'st_active_shared_journey_locations'), 'Explore uses the permission-filtered shared-location API');
$expect(is_string($dashboardScript) && str_contains($dashboardScript, 'more.dataset.mobileMore'), 'legacy app pages receive the responsive More navigation control');
$journeyFilters = file_get_contents(__DIR__ . '/../my-journeys.php');
$expect(is_string($journeyFilters) && str_contains($journeyFilters, 'journey-filter-bar'), 'My Journeys uses a compact labeled filter bar');
$expect(is_string($journeyFilters) && str_contains($journeyFilters, 'id="startSelectedJourney"') && str_contains($journeyFilters, 'Start now') && str_contains($journeysScript, 'startButton.dataset.startPlanned = String(journeyId)') && str_contains($journeysScript, 'backend/api/journeys/activate.php'), 'Scheduled trips can be started directly from the selected carousel card');
$expect(
    str_contains($journeyFilters, 'id="endSelectedJourney"')
    && str_contains($journeysScript, "if (journey.status === 'active')")
    && str_contains($journeysScript, 'end-journey.php?journey_id=')
    && str_contains($dashboard, 'end-journey.php?journey_id='),
    'Only active personal trips expose an end-trip action on Explore and My Trips'
);
$expect(
    is_string($endJourneyPage)
    && str_contains($endJourneyPage, "backend/api/journeys/end.php")
    && !str_contains($endJourneyPage, "backend/api/journey/end.php")
    && str_contains($endJourneyPage, 'start_label AS start_location')
    && str_contains($endJourneyPage, 'end_label AS destination')
    && str_contains($endJourneyPage, 'distance_km')
    && !str_contains($endJourneyPage, 'distance_remaining_km')
    && str_contains($endJourneyPage, "if (\$journey['status'] === 'active')")
    && is_string($endJourneyApi)
    && str_contains($endJourneyApi, "\$journey['status'] !== 'active'")
    && str_contains($endJourneyApi, 'WHERE id = ? AND user_id = ?'),
    'Ending a trip uses the owner-checked active-trip API and stale links cannot end completed trips'
);
$expect(is_string($journeyFilters) && str_contains($journeyFilters, 'data-theme="dark"') && str_contains($journeyFilters, "localStorage.getItem('safaritrak-theme')") && !str_contains($journeyFilters, 'data-theme-toggle'), 'My Journeys restores the shared theme without adding another panel control');
$journeyOverviewStart = is_string($journeyFilters) ? strpos($journeyFilters, '<section class="card journey-overview-panel"') : false;
$journeyFilterStart = is_string($journeyFilters) ? strpos($journeyFilters, 'class="tabs journey-filter-bar"') : false;
$journeyActionsStart = is_string($journeyFilters) ? strpos($journeyFilters, 'class="journey-action-row"') : false;
$journeyOverviewEnd = $journeyActionsStart !== false ? strpos($journeyFilters, '</section>', $journeyActionsStart) : false;
$journeyCarouselStart = is_string($journeyFilters) ? strpos($journeyFilters, '<section class="card trip-carousel-panel"') : false;
$expect(
    $journeyOverviewStart !== false
    && $journeyFilterStart > $journeyOverviewStart
    && $journeyActionsStart > $journeyFilterStart
    && $journeyOverviewEnd > $journeyActionsStart
    && $journeyCarouselStart > $journeyOverviewEnd
    && !str_contains($journeyFilters, 'journeyPickerPanel')
    && !str_contains($journeyFilters, 'journeyList')
    && !str_contains($journeyFilters, 'journeyMapDetail')
    && str_contains((string) $dashboardCss, '.app--journeys .sidebar nav {')
    && str_contains((string) $journeyFilters, 'id="previousJourney"')
    && str_contains((string) $journeyFilters, 'id="nextJourney"'),
    'My Trips has only the filter/actions panel and navigable trip carousel beneath centered desktop navigation'
);
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.journey-filter-options .tab'), 'My Journeys status filters use compact controls');
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, '.app--map-layout.app--journeys > main > .content {')
    && str_contains($dashboardCss, 'inset: 146px auto 14px 28px;')
    && str_contains($dashboardCss, 'width: min(720px, calc(50vw - 28px));')
    && str_contains($dashboardCss, 'max-width: 720px;')
    && str_contains($dashboardCss, 'not(.app--explore):not(.app--journeys) > main > .content')
    && str_contains($dashboardCss, '.app--journeys .sidebar {')
    && str_contains($dashboardCss, '.journey-overview-panel')
    && str_contains($dashboardCss, 'width: min(480px, calc(37.5vw - 21px));')
    && str_contains($dashboardCss, 'max-width: 480px;')
    && str_contains($dashboardCss, 'background-color: var(--bg-panel) !important;')
    && str_contains($dashboardCss, 'border-radius: 16px;')
    && str_contains($dashboardCss, 'backdrop-filter: var(--glass-blur) !important;')
    && str_contains($journeysSource, 'id="journeyPanelsToggle"')
    && str_contains($journeysSource, 'id="journeyOverviewPanel"')
    && str_contains($journeysScript, 'setJourneyRouteFocus(hasCoordinates, false)')
    && str_contains($journeysScript, 'setJourneyFeatures(selectedMapFeatures, { focus: true })')
    && str_contains($dashboardCss, '.app--journeys.journey-route-focus .journey-overview-panel')
    && str_contains($dashboardCss, '.app--map-layout.app--journeys:has(> .sidebar:is(:hover, :focus-within)) > main > .content')
    && str_contains($dashboardCss, 'transition: none !important;')
    && str_contains($mapLibreAppMapBackground, "'.app--journeys .journey-overview-panel, .app--journeys .trip-carousel-panel'")
    && str_contains($googleAppMapBackground, "'.app--journeys .journey-overview-panel, .app--journeys .trip-carousel-panel'")
    && str_contains($mapLibreAppMapBackground, 'maxTotalPadding')
    && str_contains($googleAppMapBackground, 'maxTotalPadding')
    && str_contains($mapLibreAppMapBackground, 'left: 480,')
    && str_contains($googleAppMapBackground, 'left: 480,')
    && str_contains($mapLibreAppMapBackground, 'if (isJourneyPage)')
    && str_contains($mapLibreAppMapBackground, "map.setProjection({ type: 'mercator' })")
    && str_contains($journeysScript, 'setJourneyFeatures(features, { focus: true })'),
    'My Trips keeps its panels visible during route focus and uses the requested desktop map padding'
);
$exportApi = file_get_contents(__DIR__ . '/../backend/api/export-data.php');
$expect(is_string($exportApi) && str_contains($exportApi, 'safaritrak-account-export'), 'User data export API is implemented');
$expect(is_string($exportApi) && str_contains($exportApi, 'gm.last_lat, gm.last_lng'), 'Account export includes the user own group location');
$consentMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0004_privacy_consent_events.sql');
$expect(is_string($consentMigration) && str_contains($consentMigration, 'privacy_consents'), 'Versioned consent events have a schema migration');
$liveJourneyMap = file_get_contents(__DIR__ . '/../safaritrak-map.js');
$expect(is_string($liveJourneyMap) && !str_contains($liveJourneyMap, 'NavigationControl'), 'Live journey map has no zoom control');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'touchZoomRotate: false'), 'Live journey map disables zoom gestures');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'window.SafariTrakMapView?.read()'), 'Live journey map restores the shared camera');
$expect(
    is_string($liveJourneyMap)
    && str_contains($liveJourneyMap, 'const initialPoint = savedView || { lat: -1.286389, lng: 36.817223 };')
    && str_contains($liveJourneyMap, "zoom: savedView?.zoom ?? (state.selectedProjection === 'globe' ? 1.8 : 15)"),
    'Live journey map uses the shared Nairobi reference camera when no user view is saved'
);
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'hasUserMovedMap') && str_contains($liveJourneyMap, 'state.hasFittedJourney = false'), 'Live journey prioritizes fitting its route over a stale saved camera until the user moves the map');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, "'line-color': routeColors.stroke") && str_contains($liveJourneyMap, 'safaritrak-route-core') && str_contains($liveJourneyMap, 'directEstimate'), 'Live journey keeps the active route visible while road routing loads or fails');
$routeCoordinatesAssignment = strpos($liveJourneyMap, 'state.routeCoordinates = coordinates;');
$routeStyleReadyGuard = strpos($liveJourneyMap, 'if (!state.styleReady) return;');
$expect(is_string($liveJourneyMap) && $routeCoordinatesAssignment !== false && $routeStyleReadyGuard !== false && $routeCoordinatesAssignment < $routeStyleReadyGuard, 'Live journey retains route geometry until the map style can render it');
$expect(is_string($liveJourneyMap) && !str_contains($liveJourneyMap, 'animateRouteDash') && str_contains($liveJourneyMap, 'else scheduleRotation()'), 'Live map motion preference changes do not call the removed route animation');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'function startLocationWatch(followLocation = false)') && str_contains($liveJourneyMap, 'if (followLocation && state.map)'), 'GPS sharing does not pan away from the planned route unless the user resumes map following');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'html[data-theme] .app--map-layout.app--live-map-layout:not(.app--explore) > main > .content > .map-full') && str_contains($dashboardCss, 'height: 100dvh;'), 'Active live tracking map fills the viewport without its glass card constraining it');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'zoom: Math.max(state.map.getZoom(), 16)'), 'Live journey map follows device location at neighborhood zoom');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'height: calc(100dvh - 222px - env(safe-area-inset-bottom))') && str_contains($dashboardCss, 'grid-template-rows: minmax(0, .7fr) minmax(0, 1.3fr)'), 'Groups panels fit above mobile navigation and remain internally scrollable');
$expect(is_string($mapViewState) && str_contains($mapViewState, 'scopedViewKey'), 'Map camera storage is isolated by signed-in user');
$expect(is_string($mapViewState) && str_contains($mapViewState, "typeof result === 'function'"), 'Map camera supports Google Maps coordinate accessors');
$sharedUiStatus = file_get_contents(__DIR__ . '/../dashboard.js');
$expect(is_string($sharedUiStatus) && str_contains($sharedUiStatus, 'window.SafariTrakUI.setStatus'), 'Shared app UI provides accessible status states');
$expect(is_string($sharedUiStatus) && str_contains($sharedUiStatus, 'aria-live'), 'Shared status states announce loading and errors to assistive technology');
$messagesPage = file_get_contents(__DIR__ . '/../messages.php');
$messagesScript = file_get_contents(__DIR__ . '/../messages.js');
$messagesStyles = file_get_contents(__DIR__ . '/../messages.css');
$messagesSendApi = file_get_contents(__DIR__ . '/../backend/api/messages/send.php');
$messagesThreadApi = file_get_contents(__DIR__ . '/../backend/api/messages/thread.php');
$expect(
    is_string($messagesPage)
    && str_contains($messagesPage, 'app-map-background.php')
    && str_contains($messagesPage, "\$appMapPage = 'messages'")
    && str_contains($messagesPage, 'app--messages')
    && str_contains($messagesPage, 'messages.css')
    && is_string($messagesStyles)
    && str_contains($messagesStyles, 'left: 20px;')
    && str_contains($messagesStyles, 'right: 20px;')
    && str_contains($messagesStyles, 'chat-thread-open')
    && str_contains($messagesStyles, 'messages-share-location'),
    'Messages uses the full-screen map workspace with responsive sidebars and location sharing'
);
$expect(is_string($messagesPage) && str_contains($messagesPage, 'role="log"') && str_contains($messagesPage, 'aria-label="Conversation messages"'), 'Messages provide a labeled live conversation log');
$expect(is_string($messagesPage) && str_contains($messagesPage, 'for="chatSearch"') && str_contains($messagesPage, 'aria-label="Send message"'), 'Message search and send controls have accessible labels');
$expect(is_string($messagesScript) && str_contains($messagesScript, "button.className = 'chat-item'") && str_contains($messagesScript, "button.type = 'button'"), 'Conversation selection uses keyboard-operable buttons');
$expect(is_string($messagesScript) && str_contains($messagesScript, 'button.dataset.name = conversation.full_name'), 'Conversation names are available to the search filter');
$expect(is_string($messagesScript) && str_contains($messagesScript, "setAttribute('aria-pressed'"), 'Selected conversations expose their pressed state');
$expect(is_string($messagesScript) && str_contains($messagesScript, 'No conversations match your search.'), 'Conversation filtering announces an empty search result');
$expect(is_string($messagesScript) && str_contains($messagesScript, 'data-message-filter') && str_contains($messagesScript, "conversationFilter === 'unread'"), 'Messages supports all and unread conversation filters');
$expect(
    is_string($messagesScript)
    && str_contains($messagesScript, 'active-shared-locations.php')
    && str_contains($messagesScript, 'focusFeatures(conversationMapFeatures)')
    && str_contains($messagesScript, 'type: \'location\'')
    && is_string($messagesSendApi)
    && str_contains($messagesSendApi, '__safaritrak_location__:')
    && str_contains($messagesSendApi, '$lat < -90 || $lat > 90 || $lng < -180 || $lng > 180')
    && is_string($messagesThreadApi)
    && str_contains($messagesThreadApi, '\'message_type\'')
    && str_contains($sharedLocationsApi, '\'owner_id\' => (int) $location[\'owner_id\']'),
    'Private conversations share validated locations and display only authorized live trip pins'
);
$expect(
    is_string($mapLibreAppMapBackground)
    && str_contains($mapLibreAppMapBackground, 'isMessagesPage')
    && str_contains($mapLibreAppMapBackground, 'workspacePadding')
    && str_contains($googleAppMapBackground, 'isMessagesPage')
    && str_contains($googleAppMapBackground, '360')
    && str_contains($appMapBackgroundView, '\'messages\''),
    'Messages map fits shared pins into the clear center canvas using workspace padding'
);
$notificationsWidget = file_get_contents(__DIR__ . '/../notifications-widget.js');
$expect(is_string($notificationsWidget) && str_contains($notificationsWidget, 'You are offline'), 'Notification loading exposes a distinct offline state');
$expect(is_string($notificationsWidget) && str_contains($notificationsWidget, 'window.SafariTrakUI?.setStatus'), 'Notification loading uses shared status states');

$persistentGroupsMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0007_accounts_groups_and_rentals.php');
$groupReadMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0015_persistent_group_reads.php');
$groupsPage = file_get_contents(__DIR__ . '/../groups.php');
$groupsNavigation = file_get_contents(__DIR__ . '/../backend/views/app-navigation.php');
$settingsSource = file_get_contents(__DIR__ . '/../settings.php');
$settingsScript = file_get_contents(__DIR__ . '/../settings.js');
$accountWorkspaceCss = file_get_contents(__DIR__ . '/../account-workspace.css');
$expect(
    is_string($settingsSource)
    && str_contains($settingsSource, 'account-profile-card')
    && str_contains($settingsSource, 'account-profile-hero')
    && str_contains($settingsSource, 'accountProfileName')
    && str_contains($settingsSource, 'account-profile-stats')
    && str_contains($settingsSource, 'app--workspace app--account-workspace')
    && str_contains($settingsSource, '$showAppMapControls = false')
    && str_contains($settingsSource, 'Emergency Contacts')
    && str_contains($settingsSource, 'Live Location Share Settings')
    && str_contains($settingsSource, 'Profile Privacy')
    && str_contains($settingsSource, 'Recent Journeys')
    && str_contains($settingsSource, 'Payment Methods')
    && str_contains($settingsSource, 'Help &amp; Feedback')
    && !str_contains($settingsSource, 'account-profile-location-note')
    && str_contains($settingsSource, 'data-open-modal="editProfileModal"')
    && str_contains($settingsScript, 'accountProfileName')
    && str_contains($settingsScript, 'accountProfileAvatar')
    && str_contains($settingsScript, 'data-settings-tab')
    && str_contains($settingsScript, 'accountSupportNote')
    && str_contains($appMapBackgroundView, "'profile'")
    && str_contains($appMapBackgroundView, '$showAppMapControls ?? true')
    && is_string($accountWorkspaceCss)
    && str_contains($accountWorkspaceCss, 'width: min(820px, 92vw)')
    && str_contains($accountWorkspaceCss, 'background: var(--glass-surface) !important;')
    && str_contains($accountWorkspaceCss, 'backdrop-filter: var(--glass-surface-blur) !important;')
    && str_contains($accountWorkspaceCss, 'grid-template-columns: repeat(2, minmax(0, 1fr))')
    && str_contains($mapLibreAppMapBackground, "mapElement.dataset.mapPage === 'profile'")
    && str_contains($mapLibreAppMapBackground, '!isProfilePage')
    && str_contains($mapLibreAppMapBackground, 'center: [36.817223, -1.286389]'),
    'Account page uses the centered glass workspace, keeps profile controls, and hides map widgets'
);
$appShellStart = file_get_contents(__DIR__ . '/../backend/views/app-shell-start.php');
$groupClient = file_get_contents(__DIR__ . '/../groups.js');
$groupInvitePage = file_get_contents(__DIR__ . '/../group-invite.php');
$groupInviteAcceptApi = file_get_contents(__DIR__ . '/../backend/api/groups/persistent/accept.php');
$groupLocationsApi = file_get_contents(__DIR__ . '/../backend/api/groups/persistent/locations.php');
$groupSharingMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0019_persistent_group_sharing.php');
$signupPage = file_get_contents(__DIR__ . '/../signup.php');
$authScript = file_get_contents(__DIR__ . '/../auth.js');
$verificationMailer = file_get_contents(__DIR__ . '/../backend/includes/mailer.php');
$verifyEmailPage = file_get_contents(__DIR__ . '/../verify-email.php');
$groupMessagesApi = file_get_contents(__DIR__ . '/../backend/api/groups/persistent/messages.php');
$groupTripApi = file_get_contents(__DIR__ . '/../backend/api/groups/create.php');
$groupTripClient = file_get_contents(__DIR__ . '/../group-travel.js');
$trackingClient = file_get_contents(__DIR__ . '/../tracking.js');
$groupTripPage = file_get_contents(__DIR__ . '/../group-travel.php');
$journeysRouteClient = file_get_contents(__DIR__ . '/../journeys.js');
$watchJourneyPage = file_get_contents(__DIR__ . '/../watch-journey.php');
$watchJourneyClient = file_get_contents(__DIR__ . '/../watch-journey.js');
$sharedJourneyClient = file_get_contents(__DIR__ . '/../view-journey.js');
$orgSignupApi = file_get_contents(__DIR__ . '/../backend/api/org/signup.php');
$orgReviewApi = file_get_contents(__DIR__ . '/../backend/api/platform/org-status.php');
$schoolInviteApi = file_get_contents(__DIR__ . '/../backend/api/org/invite.php');
$orgStatusRoute = file_get_contents(__DIR__ . '/../org-status.php');
$orgGuardRoute = file_get_contents(__DIR__ . '/../org-guard.php');
$sharedJourneyRoute = file_get_contents(__DIR__ . '/../shared-journey.php');
$testRoute = file_get_contents(__DIR__ . '/../test.php');
$dbRoute = file_get_contents(__DIR__ . '/../db.php');
$schoolTravelersPage = file_get_contents(__DIR__ . '/../org-travelers.php');
$schoolRequestApi = file_get_contents(__DIR__ . '/../backend/api/org/request-membership.php');
$schoolReviewApi = file_get_contents(__DIR__ . '/../backend/api/org/review-membership.php');
$schoolInvitePage = file_get_contents(__DIR__ . '/../organization-invite.php');
$schoolChildAccessMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0013_school_child_trip_access.php');
$schoolChildInviteApi = file_get_contents(__DIR__ . '/../backend/api/org/invite-child-viewer.php');
$schoolChildResponseApi = file_get_contents(__DIR__ . '/../backend/api/org/respond-child-viewer.php');
$schoolTransportPage = file_get_contents(__DIR__ . '/../school.php');
$schoolTransportAccess = file_get_contents(__DIR__ . '/../backend/includes/school-transport.php');
$schoolTransportScript = file_get_contents(__DIR__ . '/../school-transport.js');
$schoolTransportStateApi = file_get_contents(__DIR__ . '/../backend/api/school-transport/state.php');
$schoolParentDropoffApi = file_get_contents(__DIR__ . '/../backend/api/school-transport/parent-dropoff.php');
$schoolTransportMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0017_school_transport.php');
$schoolTripsPage = file_get_contents(__DIR__ . '/../school-trips.php');
$schoolTripsScript = file_get_contents(__DIR__ . '/../school-trips.js');
$schoolNavigation = file_get_contents(__DIR__ . '/../backend/views/app-navigation.php');
$rentalBookApi = file_get_contents(__DIR__ . '/../backend/api/rentals/book.php');
$rentalCatalogApi = file_get_contents(__DIR__ . '/../backend/api/rentals/catalog.php');
$fleetPage = file_get_contents(__DIR__ . '/../fleet.php');
$fleetScript = file_get_contents(__DIR__ . '/../fleet.js');
$rentalPage = file_get_contents(__DIR__ . '/../car-hire.php');
$rentalWorkspaceCss = file_get_contents(__DIR__ . '/../rental-workspace.css');
$rentalPickupMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0016_rental_vehicle_pickup_coordinates.php');
$rentalReviewMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0014_rental_reviews.php');
$rentalReviewApi = file_get_contents(__DIR__ . '/../backend/api/rentals/review.php');
$fleetActionApi = file_get_contents(__DIR__ . '/../backend/api/rentals/fleet-action.php');
$carHireScript = file_get_contents(__DIR__ . '/../car-hire.js');
$expect(is_string($persistentGroupsMigration) && str_contains($persistentGroupsMigration, 'CREATE TABLE IF NOT EXISTS persistent_group_messages') && str_contains($persistentGroupsMigration, 'CREATE TABLE IF NOT EXISTS rental_bookings'), 'migration 0007 additively creates group chat and rental booking tables');
$expect(is_string($groupReadMigration) && str_contains($groupReadMigration, 'last_read_message_id') && str_contains($groupReadMigration, 'ALTER TABLE persistent_group_members'), 'migration 0015 adds persistent group read tracking for unread badges');
$expect(is_string($persistentGroupsMigration) && str_contains($persistentGroupsMigration, '"school", "approved"'), 'migration 0007 preserves existing organization access as approved school accounts');
$expect(is_string($groupsNavigation) && str_contains($groupsNavigation, 'href="groups.php"') && is_string($groupsPage), 'persistent groups have a real route in shared navigation');
$expect(is_string($settingsSource) && str_contains($settingsSource, 'settings-account-shortcut') && str_contains($settingsSource, 'trusted-contacts.php') && is_string($appShellStart) && !str_contains($appShellStart, 'href="trusted-contacts.php"'), 'Trusted people is grouped under account access instead of a separate profile-menu item');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--explore .explore-panels') && !str_contains($dashboardCss, '.dashboard-route-focus'), 'Explore keeps panels in place while fitting the selected route beside them');
$expect(
    is_string($dashboardCss)
    && str_contains($dashboardCss, '--accent-amber: var(--badge-warning-text)')
    && !str_contains($dashboardCss, 'accent-violet')
    && str_contains($dashboardCss, '.dashboard-map-marker--destination')
    && str_contains($dashboardCss, 'background: #FF2D2D;'),
    'shared UI accents use theme tokens while multicolor map markers stay unchanged'
);
$expect(is_string($groupsPage) && str_contains($groupsPage, 'persistent_group_messages') && str_contains($groupsPage, 'groupInviteForm'), 'groups page renders private chat and member invitation controls');
$expect(
    is_string($groupsPage)
    && str_contains($groupsPage, 'data-group-search-item')
    && str_contains($groupsPage, 'groups-avatar-stack')
    && str_contains($groupsPage, 'groups-unread-badge')
    && str_contains($groupsPage, 'groups-view-trips')
    && str_contains($groupsPage, 'shareGroupLocation')
    && str_contains($dashboardCss, '.app--groups .group-hub-tabs')
    && str_contains($mapLibreAppMapBackground, 'left: 360, right: 380, top: 100, bottom: 60')
    && str_contains($googleAppMapBackground, 'top: 100, right: 380, bottom: 60, left: 360'),
    'Groups uses the full-screen map workspace with directory search, unread badges, and centered navigation'
);
$expect(is_string($groupClient) && str_contains($groupClient, 'backend/api/groups/persistent/messages.php') && str_contains($groupClient, 'backend/api/groups/persistent/create.php'), 'persistent group UI is wired to the group APIs');
$expect(is_string($groupMessagesApi) && str_contains($groupMessagesApi, 'persistent_group_members') && str_contains($groupMessagesApi, 'organization_travelers'), 'group chat enforces membership and current school approval');
$expect(is_string($groupMessagesApi) && str_contains($groupMessagesApi, '__safaritrak_location__:') && str_contains($groupMessagesApi, 'message_type') && str_contains($groupMessagesApi, 'last_read_message_id') && str_contains($groupMessagesApi, 'is_finite'), 'Group chat safely supports shared locations and marks delivered messages read');
$expect(is_string($groupInvitePage) && str_contains($groupInvitePage, 'data-token') && str_contains($groupInvitePage, 'backend/api/groups/persistent/accept.php'), 'group invitations can be opened and accepted through a public link');
$expect(
    is_string($groupInviteApi)
    && str_contains($groupInviteApi, '$username')
    && str_contains($groupInviteApi, 'invited_user_id')
    && is_string($groupInviteAcceptApi)
    && str_contains($groupInviteAcceptApi, '$invite[\'invited_user_id\'] !== null')
    && !str_contains($groupInviteAcceptApi, 'AND i.accepted_at IS NULL'),
    'group invites support username-targeted acceptance while reusable share links are not consumed by a join'
);
$expect(
    is_string($registerApi)
    && str_contains($registerApi, 'group_invite_token')
    && str_contains($registerApi, 'invited_user_id')
    && str_contains($registerApi, 'expires_at > UTC_TIMESTAMP()'),
    'registration validates reusable group links before creating accounts and keeps username-only invites restricted'
);
$expect(
    is_string($groupSharingMigration)
    && str_contains($groupSharingMigration, 'sharing_location')
    && str_contains($groupSharingMigration, 'invited_user_id')
    && is_string($groupLocationsApi)
    && str_contains($groupLocationsApi, 'UTC_TIMESTAMP() - INTERVAL 2 MINUTE')
    && str_contains($groupLocationsApi, 'st_parse_coordinate'),
    'persistent group location sharing uses an additive migration and returns only recent validated coordinates'
);
$expect(
    is_string($groupClient)
    && str_contains($groupClient, 'navigator.geolocation.watchPosition')
    && str_contains($groupClient, 'setSharedLocationFeatures')
    && str_contains($groupClient, "action: 'stop'")
    && is_string($mapLibreAppMapBackground)
    && str_contains($mapLibreAppMapBackground, 'function setSharedLocationFeatures'),
    'group members can opt into live location sharing, stop it, and display labeled markers on the map'
);
$expect(is_string($groupInvitePage) && str_contains($groupInvitePage, 'signup.php?group_invite=') && is_string($schoolInvitePage) && str_contains($schoolInvitePage, 'signup.php?org_invite='), 'group and school invitation pages preserve invite links during account creation');
$expect(is_string($signupPage) && str_contains($signupPage, 'groupInviteToken') && str_contains($signupPage, 'organizationInviteToken') && str_contains($signupPage, 'signupInviteError') && is_string($authScript) && str_contains($authScript, 'group_invite_token') && str_contains($authScript, 'org_invite_token') && str_contains($authScript, "group_invite_token: 'signupInviteError'"), 'individual signup submits invitation tokens and displays invitation errors');
$expect(is_string($registerApi) && str_contains($registerApi, 'group_invite_token') && str_contains($registerApi, 'org_invite_token') && is_string($verificationMailer) && str_contains($verificationMailer, 'group_invite') && str_contains($verificationMailer, 'org_invite'), 'email verification retains an allow-listed invitation continuation');
$expect(is_string($verificationMailer) && str_contains($verificationMailer, 'SMTP username and password are not configured') && str_contains($verificationMailer, 'ENCRYPTION_SMTPS'), 'mail delivery reports missing SMTP configuration and supports implicit TLS');
$rateLimitSource = file_get_contents(__DIR__ . '/../backend/includes/rate-limit.php');
$expect(is_string($rateLimitSource) && str_contains($rateLimitSource, 'st_local_development_rate_limit_maximum') && str_contains($rateLimitSource, "['127.0.0.1', '::1']"), 'higher signup limits are restricted to loopback development requests');
$expect(is_string($registerApi) && str_contains($registerApi, 'st_local_development_rate_limit_maximum(5)') && is_string($orgSignupApi) && str_contains($orgSignupApi, 'st_local_development_rate_limit_maximum(5)'), 'individual and organization registration use the loopback-only development limit');
$expect(is_string($registerApi) && str_contains($registerApi, 'Local development mode is enabled') && str_contains($registerApi, 'could not send the verification email') && is_string($orgSignupApi) && str_contains($orgSignupApi, 'could not send the verification email'), 'personal and organization signup report delivery failures and provide development verification links');
$resendVerificationApi = file_get_contents(__DIR__ . '/../backend/api/resend-verification.php');
$expect(is_string($resendVerificationApi) && str_contains($resendVerificationApi, 'dev_verification_link') && str_contains($resendVerificationApi, 'st_is_local_development_request()') && str_contains($resendVerificationApi, 'new verification link has been requested'), 'resend verification exposes local links only to loopback development requests while preserving generic responses');
$expect(is_string($verifyEmailPage) && str_contains($verifyEmailPage, 'group-invite.php?token=') && str_contains($verifyEmailPage, 'organization-invite.php?token='), 'verified invitees return to the correct invitation without an open redirect');
$expect(is_string($groupTripClient) && str_contains($groupTripClient, 'backend/api/groups/create.php') && str_contains($groupTripClient, 'persistent_group_ids') && str_contains($groupTripClient, 'memberRouteColor') && str_contains($groupTripClient, 'getTrackingColors()') && str_contains($groupTripClient, 'focusMobileLeafletRoute') && str_contains($groupTripClient, 'completedStroke'), 'group journeys use distinct member colors, completed-route styling, and mobile route focus');
$expect(
    is_string($trackingClient)
    && str_contains($trackingClient, 'if (!hasCenteredOnCurrentLocation)')
    && str_contains($trackingClient, 'reportLocationUnavailable')
    && is_string($groupTripClient)
    && str_contains($groupTripClient, 'navigator.geolocation.getCurrentPosition')
    && is_string($groupTripPage)
    && str_contains($groupTripPage, 'id="groupDeviceLocationStatus"')
    && is_string($watchJourneyClient)
    && str_contains($watchJourneyClient, 'hasCenteredOnDeviceLocation = true')
    && is_string($watchJourneyPage)
    && str_contains($watchJourneyPage, 'id="deviceLocationStatus"')
    && is_string($sharedJourneyClient)
    && str_contains($sharedJourneyClient, 'centerOnDeviceLocation'),
    'standalone tracking, group, and shared-journey maps center on device location when available'
);
$expect(is_string($groupTripApi) && str_contains($groupTripApi, 'persistent_group_members') && str_contains($groupTripApi, 'One or more selected groups'), 'group-trip API verifies group membership before inviting members');
$expect(
    is_string($groupTripPage)
    && str_contains($groupTripPage, 'id="startPoint" data-location-autocomplete')
    && str_contains($groupTripPage, 'id="endPoint" data-location-autocomplete')
    && str_contains($groupTripPage, 'ACTIVE_GROUP_START_LAT')
    && str_contains($groupTripPage, 'route-directions.js'),
    'Group trip planning saves selectable route endpoints and loads route directions'
);
$expect(
    is_string($groupTripApi)
    && str_contains($groupTripApi, 'meeting_point_label, meeting_point_lat, meeting_point_lng')
    && str_contains($groupTripApi, "'start_location'")
    && str_contains($groupTripClient, 'SafariTrakRouteDirections.getDrivingRoute')
    && str_contains($groupTripClient, 'group-planned-route'),
    'Group journeys persist an origin and draw a road route for scheduled, active, and ended trips'
);
$expect(
    is_string($journeysRouteClient)
    && str_contains($journeysRouteClient, 'backend/api/journeys/position.php?group_journey_id=')
    && str_contains($journeysRouteClient, 'group_journey_id: Number(journey.id)')
    && str_contains($journeysRouteClient, 'SafariTrakRouteDirections.getDrivingRoute'),
    'Selecting a group journey loads its authorized tracks and road route on My Trips'
);
$expect(
    is_string($watchJourneyPage)
    && str_contains($watchJourneyPage, 'journeyRoadRouteStatus')
    && str_contains($watchJourneyPage, 'route-directions.js')
    && is_string($watchJourneyClient)
    && str_contains($watchJourneyClient, 'SafariTrakRouteDirections.getDrivingRoute')
    && is_string($sharedJourneyClient)
    && str_contains($sharedJourneyClient, 'SafariTrakRouteDirections.getDrivingRoute'),
    'Shared current and ended individual journeys render their road route with recorded positions'
);
$expect(is_string($orgSignupApi) && str_contains($orgSignupApi, 'account_type') && str_contains($orgSignupApi, '"pending"'), 'new school and car-hire accounts wait for platform review');
$expect(is_string($orgReviewApi) && str_contains($orgReviewApi, "'approve', 'reject'") && str_contains($orgReviewApi, 'reviewed_by'), 'platform admins can approve or reject organization applications');
$expect(is_string($schoolInviteApi) && str_contains($schoolInviteApi, 'account_type') && str_contains($schoolInviteApi, 'organization_invites'), 'school admins can create expiring enrollment links');
$expect(is_string($orgStatusRoute) && str_contains($orgStatusRoute, "'/backend/config/database.php'") && str_contains($orgStatusRoute, "'/backend/includes/session.php'"), 'root organization-status endpoint resolves backend includes from its actual location');
$expect(is_string($orgGuardRoute) && str_contains($orgGuardRoute, "'/backend/includes/org-guard.php'") && str_contains($orgGuardRoute, "header('Location: org-dashboard.php')"), 'root organization-guard route delegates to the shared guard and returns to the organization page');
$expect(is_string($sharedJourneyRoute) && str_contains($sharedJourneyRoute, 'shared-location.php?') && str_contains($testRoute, "header('Location: welcome.php'") && str_contains($dbRoute, "header('Location: welcome.php'"), 'legacy empty and utility routes now redirect to supported user-facing pages');
$expect(is_string($schoolTravelersPage) && str_contains($schoolTravelersPage, 'Search school members by name...') && str_contains($schoolTravelersPage, 'Search travelers by name or phone...'), 'school roster search label matches its privacy-limited name-only data');
$expect(is_string($schoolRequestApi) && str_contains($schoolRequestApi, 'organization_membership_requests') && !str_contains($schoolRequestApi, 'INSERT INTO organization_travelers'), 'school invite acceptance creates a request rather than immediate traveler access');
$expect(is_string($schoolReviewApi) && str_contains($schoolReviewApi, 'status = "pending"') && str_contains($schoolReviewApi, 'INSERT INTO organization_travelers'), 'school traveler visibility begins only after admin approval');
$expect(is_string($schoolInvitePage) && str_contains($schoolInvitePage, 'request-membership.php'), 'school enrollment links provide a request-to-join flow');
$expect(is_string($schoolChildAccessMigration) && str_contains($schoolChildAccessMigration, 'organization_child_access') && str_contains($schoolChildAccessMigration, '"school_trip_access"'), 'school trip sharing is separately permissioned and can notify guardians');
$expect(
    is_string($schoolTransportPage)
    && str_contains($schoolTransportPage, 'if (!$schoolAccess)')
    && str_contains($schoolTransportPage, "header('Location: index.php')")
    && is_string($schoolTransportAccess)
    && str_contains($schoolTransportAccess, 'sv.verification_status = "verified"')
    && is_string($schoolNavigation)
    && str_contains($schoolNavigation, 'if ($schoolTransportVerification)'),
    'School transport is hidden and directly gated by current verified school access'
);
$expect(
    is_string($schoolTransportScript)
    && str_contains($schoolTransportScript, "if (!destination.route_service_consent)")
    && str_contains($schoolTransportScript, 'route_service_consent: Boolean(byId(\'routeServiceConsent\')?.checked)')
    && str_contains($schoolTransportScript, 'student.home_dropoff_lat')
    && is_string($schoolParentDropoffApi)
    && str_contains($schoolParentDropoffApi, 'route_service_consent = ?')
    && is_string($schoolTransportStateApi)
    && str_contains($schoolTransportStateApi, '$parent[\'route_service_consent\']')
    && is_string($schoolTransportMigration)
    && str_contains($schoolTransportMigration, 'route_service_consent TINYINT(1)'),
    'Home coordinates reach road-route and ETA calculations only after explicit persisted consent'
);
$expect(is_string($schoolChildInviteApi) && str_contains($schoolChildInviteApi, 'member_type = "student"') && str_contains($schoolChildInviteApi, 'member_type = "guardian"') && str_contains($schoolChildInviteApi, 'status = "pending"'), 'school administrators can invite an enrolled guardian to a specific student trip');
$expect(is_string($schoolChildResponseApi) && str_contains($schoolChildResponseApi, 'guardian_user_id = ?') && str_contains($schoolChildResponseApi, 'status = "active"') && is_string($schoolTripsScript) && str_contains($schoolTripsScript, 'respond-child-viewer.php'), 'guardians must accept student-specific trip access before it activates');
$expect(is_string($schoolTripsPage) && str_contains($schoolTripsPage, 'journey_positions') && str_contains($schoolTripsPage, 'organization_child_access') && is_string($schoolNavigation) && str_contains($schoolNavigation, 'school-trips.php?child='), 'authorized guardians get a named child trip page and navigation link');
$expect(is_string($rentalCatalogApi) && str_contains($rentalCatalogApi, 'review_status = "approved"') && is_string($rentalPage), 'car-hire catalog exposes only approved providers');
$expect(is_string($schoolChildAccessMigration) && str_contains($schoolChildAccessMigration, 'CREATE TABLE IF NOT EXISTS organization_child_access'), 'school child-trip permissions have an additive database migration');
$contactsAuthorization = file_get_contents(__DIR__ . '/../backend/includes/contacts-check.php');
$expect(is_string($contactsAuthorization) && str_contains($contactsAuthorization, 'rental_bookings') && str_contains($contactsAuthorization, '"responded"'), 'rental enquiry participants can message the company in-app');
$expect(is_string($rentalReviewMigration) && str_contains($rentalReviewMigration, 'uniq_rental_review_booking') && is_string($rentalReviewApi) && str_contains($rentalReviewApi, 'b.status = "completed"'), 'rental ratings are limited to completed customer enquiries');
$expect(is_string($fleetActionApi) && str_contains($fleetActionApi, "\$status === 'completed'") && is_string($fleetPage) && str_contains($fleetPage, 'Mark rental completed') && is_string($carHireScript) && str_contains($carHireScript, 'rental-review-form'), 'providers can mark offline rentals complete and customers can rate them');
$expect(is_string($rentalBookApi) && str_contains($rentalBookApi, 'FOR UPDATE') && str_contains($rentalBookApi, 'status = "approved"') && str_contains($rentalBookApi, 'quoted_daily_rate'), 'rental booking requests validate vehicle dates and preserve quoted rates');
$expect(is_string($fleetPage) && str_contains($fleetPage, 'rental_vehicles') && is_string($fleetScript) && str_contains($fleetScript, 'fleet-action.php'), 'approved car-hire accounts can manage fleet and booking requests');
$expect(
    is_string($rentalPage)
    && str_contains($rentalPage, 'rental-marketplace-panel')
    && str_contains($rentalPage, 'rentalPickupDate')
    && str_contains($rentalPage, 'data-rental-filter="automatic"')
    && str_contains($rentalPage, 'rentalPreview')
    && is_string($rentalWorkspaceCss)
    && str_contains($rentalWorkspaceCss, 'width: 420px;')
    && str_contains($rentalWorkspaceCss, 'rgba(12, 16, 28, .74)')
    && is_string($carHireScript)
    && str_contains($carHireScript, 'safaritrak:rental-feature-click')
    && str_contains($carHireScript, "latitude === null")
    && str_contains($carHireScript, "longitude === null"),
    'Car Hire opens as a map marketplace with glass filters, vehicle preview, and clickable price pins'
);
$expect(
    is_string($fleetPage)
    && str_contains($fleetPage, 'fleet-kpi-grid')
    && str_contains($fleetPage, 'fleet-activity-sidebar')
    && str_contains($fleetPage, 'data-status="approved"')
    && str_contains($fleetPage, 'vehicleManagementDialog')
    && is_string($fleetActionApi)
    && str_contains($fleetActionApi, "'update_vehicle'")
    && str_contains($fleetActionApi, "['approved', 'responded', 'rejected']")
    && str_contains($fleetActionApi, 'overlaps an approved rental'),
    'Fleet portal summarizes listings, updates pricing, and safely approves non-overlapping requests'
);
$expect(
    is_string($rentalPickupMigration)
    && str_contains($rentalPickupMigration, 'pickup_lat')
    && str_contains($rentalPickupMigration, 'pickup_lng')
    && is_string($rentalCatalogApi)
    && str_contains($rentalCatalogApi, 'v.pickup_lat, v.pickup_lng')
    && is_string($fleetActionApi)
    && str_contains($fleetActionApi, 'pickup_lat, pickup_lng'),
    'Vehicle pickup coordinates are validated, stored, and returned to the rental map'
);
$exploreChatScript = file_get_contents(__DIR__ . '/../explore-chat.js');
$mapFallbackScript = file_get_contents(__DIR__ . '/../google-maps-fallback.js');
$expect(is_string($exploreChatScript) && str_contains($exploreChatScript, 'backend/api/groups/persistent/messages.php') && str_contains($exploreChatScript, 'textContent = body'), 'Explore group chat sends safely through the member-authorized chat API');
$expect(is_string($dashboard) && str_contains($dashboard, 'explore-chat-panel') && str_contains($dashboard, 'dashboardGroupMessages') && str_contains($dashboard, 'notifications.php'), 'Explore dashboard shows real group chat and location, trip, contact, and alert shortcuts');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, "mapTypeId: 'roadmap'") && is_string($dashboardCss) && str_contains($dashboardCss, '.explore-map-canvas') && str_contains($dashboardCss, 'border-radius: 0'), 'Explore preserves the full-width flat map presentation');
$expect(is_string($mapFallbackScript) && str_contains($mapFallbackScript, 'maplibregl') && str_contains($mapFallbackScript, 'backup map library could not be reached') && is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'fallbackToMapLibre') && str_contains($googleDashboardMapScript, 'backup flat map'), 'Google Maps failures fall back to an interactive flat map with visible failure states');

echo "Completed {$checks} SafariTrak smoke checks.\n";
