<?php
require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();

$journeyId = isset($_GET['journey_id']) ? (int)$_GET['journey_id'] : 0;

if ($journeyId <= 0) {
    $stmt = $db->prepare("
        SELECT id
        FROM journeys
        WHERE user_id = ?
        AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$currentUser['id']]);
    $activeJourney = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeJourney) {
        $journeyId = (int)$activeJourney['id'];
    }
}

$journey = null;

if ($journeyId > 0) {
    $stmt = $db->prepare("
        SELECT
            id,
            user_id,
            start_location,
            destination,
            start_lat,
            start_lng,
            destination_lat,
            destination_lng,
            current_lat,
            current_lng,
            distance_remaining_km,
            distance_travelled_km,
            status,
            started_at,
            ended_at
        FROM journeys
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $journeyId,
        $currentUser['id']
    ]);

    $journey = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$journey) {
    $pageError = 'No journey was found for your account.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SafariTrak | End Journey</title>

<link rel="stylesheet" href="dashboard.css">

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
.end-page {
    padding: 30px;
}

.end-container {
    max-width: 760px;
    margin: 0 auto;
}

.end-card {
    background: #fff;
    border-radius: 18px;
    padding: 35px;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
}

.end-header {
    text-align: center;
    margin-bottom: 30px;
}

.end-icon {
    width: 75px;
    height: 75px;
    border-radius: 50%;
    margin: 0 auto 18px;
    background: #e8f7f2;
    color: #087f6b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
}

.end-header h2 {
    margin: 0 0 8px;
    color: #1f2933;
}

.end-header p {
    margin: 0;
    color: #667085;
}

.journey-info {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin: 25px 0;
}

.info-box {
    border: 1px solid #e4e7ec;
    border-radius: 12px;
    padding: 17px;
    background: #fafafa;
}

.info-box small {
    display: block;
    color: #667085;
    margin-bottom: 6px;
}

.info-box strong {
    color: #1f2933;
}

.route-box {
    margin-top: 20px;
    padding: 20px;
    border: 1px solid #e4e7ec;
    border-radius: 14px;
}

.route-point {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 12px 0;
}

.route-point i {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.route-start i {
    background: #e8f7f2;
    color: #087f6b;
}

.route-end i {
    background: #fdeaea;
    color: #c0392b;
}

.route-line {
    width: 2px;
    height: 25px;
    background: #d0d5dd;
    margin-left: 15px;
}

.end-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 30px;
}

.btn-cancel,
.btn-end {
    border: 0;
    border-radius: 9px;
    padding: 13px 25px;
    font-weight: 600;
    cursor: pointer;
}

.btn-cancel {
    background: #f2f4f7;
    color: #344054;
}

.btn-end {
    background: #c0392b;
    color: #fff;
}

.btn-end:hover {
    background: #a93226;
}

.btn-end:disabled {
    opacity: .65;
    cursor: not-allowed;
}

.error-card {
    text-align: center;
    padding: 40px;
}

.error-card i {
    font-size: 45px;
    color: #c0392b;
    margin-bottom: 15px;
}

.tracking-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999;
    padding: 20px;
}

.tracking-modal {
    width: 100%;
    max-width: 430px;
    background: #fff;
    border-radius: 18px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}

.tracking-modal-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #e8f7f2;
    color: #087f6b;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 18px;
    font-size: 28px;
    font-weight: bold;
}

.tracking-modal-icon.warning {
    background: #fdeaea;
    color: #c0392b;
}

.tracking-modal h2 {
    margin: 0 0 12px;
    color: #1f2933;
}

.tracking-modal p {
    color: #667085;
    line-height: 1.6;
    margin: 0;
}

.modal-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 24px;
}

.modal-actions button {
    padding: 11px 22px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

.modal-cancel {
    border: 1px solid #d0d5dd;
    background: #fff;
    color: #344054;
}

.modal-confirm {
    border: 0;
    background: #c0392b;
    color: #fff;
}

.modal-ok {
    margin-top: 22px;
    padding: 11px 30px;
    border: 0;
    border-radius: 8px;
    background: #087f6b;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}

@media (max-width: 650px) {
    .end-page {
        padding: 18px;
    }

    .end-card {
        padding: 22px;
    }

    .journey-info {
        grid-template-columns: 1fr;
    }

    .end-actions {
        flex-direction: column;
    }

    .btn-cancel,
    .btn-end {
        width: 100%;
    }
}
</style>
</head>

<body>

<div class="app">

<aside class="sidebar" id="sidebar">

    <div class="brand">
        <div class="logo">
            <i class="fa-solid fa-route"></i>
        </div>

        <div>
            <b>SafariTrak</b>
            <small>Travel smarter</small>
        </div>
    </div>

    <nav>

        <a href="index.php">
            <i class="fa-solid fa-grid-2"></i>
            Dashboard
        </a>

        <a href="my-journeys.php">
            <i class="fa-solid fa-map-location-dot"></i>
            My Journeys
        </a>

        <a href="live-tracking.php">
            <i class="fa-solid fa-location-crosshairs"></i>
            Live Tracking
        </a>

        <a href="places.php">
            <i class="fa-solid fa-map-pin"></i>
            Places
        </a>

        <a href="messages.php">
            <i class="fa-regular fa-message"></i>
            Messages
        </a>

        <a href="trusted-contacts.php">
            <i class="fa-solid fa-user-group"></i>
            Trusted Contacts
        </a>

        <a href="safety.php">
            <i class="fa-solid fa-shield-halved"></i>
            Safety
        </a>

    </nav>

    <div class="bottom">

        <a href="settings.php">
            <i class="fa-solid fa-gear"></i>
            Settings
        </a>

        <a href="logout.php">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            Logout
        </a>

        <div class="account">
            <span>
                <?= st_avatar_inner($currentUser) ?>
            </span>

            <div>
                <b><?= htmlspecialchars($userName) ?></b>
                <small>Traveler</small>
            </div>
        </div>

    </div>

</aside>

<main>

<header>

    <button class="menu" id="menu">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div>
        <label>JOURNEY</label>
        <h1>End Journey</h1>
    </div>

    <div class="head-actions">

        <div class="avatar">
            <?= st_avatar_inner($currentUser) ?>
        </div>

    </div>

</header>

<div class="content end-page">

<div class="end-container">

<?php if (isset($pageError)): ?>

    <div class="end-card error-card">

        <i class="fa-solid fa-circle-exclamation"></i>

        <h2>Journey Not Found</h2>

        <p><?= htmlspecialchars($pageError) ?></p>

        <div class="end-actions">

            <button
                class="btn-cancel"
                onclick="window.location.href='my-journeys.php'">
                Back to My Journeys
            </button>

        </div>

    </div>

<?php else: ?>

    <div class="end-card">

        <div class="end-header">

            <div class="end-icon">
                <i class="fa-solid fa-flag-checkered"></i>
            </div>

            <h2>End Your Journey?</h2>

            <p>
                You are about to end your current journey.
                Your shared contacts will be notified.
            </p>

        </div>

        <div class="journey-info">

            <div class="info-box">

                <small>Journey ID</small>

                <strong>
                    #<?= (int)$journey['id'] ?>
                </strong>

            </div>

            <div class="info-box">

                <small>Status</small>

                <strong id="journeyStatus">
                    <?= htmlspecialchars(ucfirst($journey['status'])) ?>
                </strong>

            </div>

            <div class="info-box">

                <small>Distance Remaining</small>

                <strong>
                    <?php
                    if ($journey['distance_remaining_km'] !== null) {
                        echo number_format(
                            (float)$journey['distance_remaining_km'],
                            2
                        ) . ' km';
                    } else {
                        echo '--';
                    }
                    ?>
                </strong>

            </div>

            <div class="info-box">

                <small>Started</small>

                <strong>
                    <?= $journey['started_at']
                        ? htmlspecialchars($journey['started_at'])
                        : '--'
                    ?>
                </strong>

            </div>

        </div>

        <div class="route-box">

            <div class="route-point route-start">

                <i class="fa-solid fa-location-dot"></i>

                <div>

                    <small>Starting Point</small>

                    <strong>
                        <?= htmlspecialchars(
                            $journey['start_location'] ?? 'Starting point'
                        ) ?>
                    </strong>

                </div>

            </div>

            <div class="route-line"></div>

            <div class="route-point route-end">

                <i class="fa-solid fa-flag"></i>

                <div>

                    <small>Destination</small>

                    <strong>
                        <?= htmlspecialchars(
                            $journey['destination'] ?? 'Destination'
                        ) ?>
                    </strong>

                </div>

            </div>

        </div>

        <div class="end-actions">

            <button
                type="button"
                class="btn-cancel"
                id="cancelEndJourney">
                Cancel
            </button>

            <button
                type="button"
                class="btn-end"
                id="endJourneyBtn">
                <i class="fa-solid fa-stop"></i>
                End Journey
            </button>

        </div>

    </div>

<?php endif; ?>

</div>

</div>

<footer>
    &copy; <?= date('Y') ?> SafariTrak
    <span>Navigate. Track. Share. Connect. Stay Safe.</span>
</footer>

</main>

</div>

<input
    type="hidden"
    id="journeyId"
    value="<?= $journey ? (int)$journey['id'] : 0 ?>">

<script src="dashboard.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const endButton =
        document.getElementById('endJourneyBtn');

    const cancelButton =
        document.getElementById('cancelEndJourney');

    const journeyId =
        parseInt(
            document.getElementById('journeyId')?.value || 0,
            10
        );

    function createModal(
        title,
        message,
        type,
        showActions
    ) {

        const existing =
            document.getElementById('endJourneyModal');

        if (existing) {
            existing.remove();
        }

        const modal =
            document.createElement('div');

        modal.id =
            'endJourneyModal';

        modal.className =
            'tracking-modal-overlay';

        const icon =
            type === 'success'
                ? '✓'
                : type === 'warning'
                    ? '!'
                    : '×';

        const iconClass =
            type === 'warning'
                ? 'tracking-modal-icon warning'
                : 'tracking-modal-icon';

        modal.innerHTML = `

            <div class="tracking-modal">

                <div class="${iconClass}">
                    ${icon}
                </div>

                <h2>${escapeHtml(title)}</h2>

                <p>${escapeHtml(message)}</p>

                ${
                    showActions
                    ?
                    `
                    <div class="modal-actions">

                        <button
                            type="button"
                            class="modal-cancel"
                            id="modalCancel">
                            Cancel
                        </button>

                        <button
                            type="button"
                            class="modal-confirm"
                            id="modalConfirm">
                            End Journey
                        </button>

                    </div>
                    `
                    :
                    `
                    <button
                        type="button"
                        class="modal-ok"
                        id="modalOk">
                        OK
                    </button>
                    `
                }

            </div>
        `;

        document.body.appendChild(modal);

        if (showActions) {

            document
                .getElementById('modalCancel')
                .addEventListener('click', function () {
                    modal.remove();
                });

            document
                .getElementById('modalConfirm')
                .addEventListener('click', function () {

                    modal.remove();

                    processEndJourney();

                });

        } else {

            document
                .getElementById('modalOk')
                .addEventListener('click', function () {
                    modal.remove();
                });

        }

        return modal;
    }

    async function processEndJourney() {

        if (!journeyId) {

            createModal(
                'Unable to End Journey',
                'The journey ID could not be found.',
                'error',
                false
            );

            return;
        }

        if (endButton) {

            endButton.disabled = true;

            endButton.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Ending Journey...';

        }

        try {

            const response =
                await fetch(
                    'backend/api/journey/end.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json'
                        },

                        body: JSON.stringify({
                            journey_id:
                                journeyId
                        })
                    }
                );

            const text =
                await response.text();

            let data;

            try {

                data =
                    JSON.parse(text);

            } catch (error) {

                console.error(
                    'Invalid server response:',
                    text
                );

                throw new Error(
                    'The server returned an invalid response.'
                );
            }

            if (
                !response.ok ||
                !data.success
            ) {

                throw new Error(
                    data.message ||
                    'The journey could not be ended.'
                );
            }

            if (endButton) {

                endButton.innerHTML =
                    '<i class="fa-solid fa-check"></i> Journey Ended';

            }

            const status =
                document.getElementById(
                    'journeyStatus'
                );

            if (status) {
                status.textContent =
                    'Ended';
            }

            createModal(
                'Journey Ended',
                data.message ||
                'Your journey has been successfully ended.',
                'success',
                false
            );

            setTimeout(function () {

                window.location.href =
                    'my-journeys.php';

            }, 1800);

        } catch (error) {

            console.error(
                'End Journey Error:',
                error
            );

            if (endButton) {

                endButton.disabled =
                    false;

                endButton.innerHTML =
                    '<i class="fa-solid fa-stop"></i> End Journey';

            }

            createModal(
                'Unable to End Journey',
                error.message ||
                'Something went wrong while ending the journey.',
                'error',
                false
            );
        }
    }

    if (endButton) {

        endButton.addEventListener(
            'click',
            function () {

                createModal(
                    'End Journey',
                    'Are you sure you want to end this journey? People currently sharing your journey will be notified that the journey has ended.',
                    'warning',
                    true
                );

            }
        );

    }

    if (cancelButton) {

        cancelButton.addEventListener(
            'click',
            function () {

                window.history.back();

            }
        );

    }

    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value;

        return div.innerHTML;
    }

});
</script>

</body>
</html>