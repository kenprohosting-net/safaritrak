<?php
require __DIR__ . '/backend/includes/auth-guard.php';
$appMapPage = 'messages';
$showAppMapFocusControl = false;
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Messages</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="messages.css?v=<?= hash_file('sha256', __DIR__ . '/messages.css') ?>">
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@5.6.2/dist/maplibre-gl.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script>
  try {
    const savedTheme = localStorage.getItem('safaritrak-theme');
    if (savedTheme === 'light' || savedTheme === 'dark') document.documentElement.dataset.theme = savedTheme;
  } catch (error) {
    document.documentElement.dataset.theme = 'dark';
  }
</script>
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout app--workspace app--messages">
<?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

<main>
<div class="content messages-content">
  <section class="chat-shell messages-shell" aria-label="Messages">
    <aside class="chat-list messages-conversations" id="chatList" aria-label="Conversations">
      <div class="messages-list-header">
        <div class="messages-list-title">
          <h1>Chats</h1>
        </div>
        <a class="messages-new-chat" href="trusted-contacts.php" aria-label="Start a new conversation" title="Start a new conversation"><i class="fa-solid fa-plus" aria-hidden="true"></i></a>
      </div>
      <div class="chat-search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <label class="visually-hidden" for="chatSearch">Search conversations</label>
        <input type="search" id="chatSearch" placeholder="Search or start a new chat" autocomplete="off">
      </div>
      <div class="messages-filter-row" role="group" aria-label="Filter conversations">
        <button type="button" class="messages-filter active" data-message-filter="all" aria-pressed="true">All</button>
        <button type="button" class="messages-filter" data-message-filter="unread" aria-pressed="false">Unread <span id="unreadConversationCount"></span></button>
        <a class="messages-contact-link" href="trusted-contacts.php">Trusted people</a>
      </div>
      <ul id="chatListItems" class="chat-list-items" aria-label="Conversations" aria-busy="true">
        <li><p class="notif-empty" role="status" aria-live="polite">Loading conversations…</p></li>
      </ul>
      <p id="chatSearchStatus" class="visually-hidden" role="status" aria-live="polite"></p>
    </aside>

    <section class="chat-panel messages-thread" aria-label="Selected conversation">
      <div class="chat-head" id="chatHead" aria-live="polite">
        <p class="notif-empty" style="padding:0">Select a conversation</p>
      </div>
      <div class="chat-messages" id="chatMessages" role="log" aria-label="Conversation messages" aria-live="polite" aria-relevant="additions text"></div>
      <div id="chatNotice" class="chat-notice"></div>
      <article class="messages-location-card" id="chatLocationCard" hidden>
        <div class="messages-location-card-heading">
          <strong>Shared Location</strong>
          <small id="sharedLocationSummary">No locations shared in this conversation yet.</small>
        </div>
        <button type="button" class="messages-share-location" id="shareCurrentLocation">
          <i class="fa-solid fa-location-dot" aria-hidden="true"></i>Share current location
        </button>
        <button type="button" class="messages-location-map" id="openSharedLocationMap" aria-label="Show shared locations on the map">
          <span class="messages-map-roads" aria-hidden="true"></span>
          <span class="messages-map-pin messages-map-pin--contact" id="contactMapPin" hidden><i class="fa-solid fa-location-dot"></i></span>
          <span class="messages-map-pin messages-map-pin--self" id="selfMapPin" hidden><i class="fa-solid fa-location-dot"></i></span>
          <span class="messages-map-empty" id="sharedLocationEmpty">Location pins appear here when shared.</span>
        </button>
      </article>
      <div class="messages-photo-details" id="chatPhotoDetails" hidden>
        <img id="chatPhotoPreview" alt="Photo preview" hidden>
        <div class="messages-photo-fields">
          <label for="chatPhotoTripLabel">Trip label<input id="chatPhotoTripLabel" maxlength="120" required placeholder="e.g. Amboseli weekend"></label>
          <label for="chatPhotoCaption">Caption <span>(optional)</span><input id="chatPhotoCaption" maxlength="2000" placeholder="Add a note"></label>
        </div>
        <div class="messages-photo-actions">
          <button type="button" id="cancelChatPhoto">Cancel</button>
          <button type="button" id="sendChatPhoto">Share photo</button>
        </div>
      </div>
      <input type="file" id="chatPhotoFile" accept="image/jpeg,image/png,image/webp" hidden>
      <form class="chat-input" id="chatForm" style="display:none">
        <button type="button" id="attachChatPhoto" aria-label="Attach a trip photo" title="Attach a trip photo"><i class="fa-regular fa-image" aria-hidden="true"></i></button>
        <label class="visually-hidden" for="chatInput">Type a message</label>
        <input type="text" id="chatInput" placeholder="Type a message..." autocomplete="off">
        <button type="submit" aria-label="Send message"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
      </form>
    </section>
  </section>
</div>
</main>
</div>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="messages.js?v=<?= hash_file('sha256', __DIR__ . '/messages.js') ?>"></script>
</body>
</html>