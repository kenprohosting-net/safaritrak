<?php
require __DIR__ . '/backend/includes/auth-guard.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Messages</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout">
<?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>STAY CONNECTED</label><h1>Messages</h1></div>
  <div class="head-actions">
    <div class="notif-wrap">
      <button type="button" class="notif-bell" id="notifBell"><i class="fa-regular fa-bell"></i><span class="notif-dot" id="notifDot"></span></button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-head"><b>Notifications</b><a href="notifications.php">View all</a></div>
        <div class="notif-list" id="notifDropdownList">
          <p class="notif-empty">Loading...</p>
        </div>
      </div>
    </div>
    <div class="avatar"><?= st_avatar_inner($currentUser) ?></div>
  </div>
</header>

<div class="content">
<div class="card">
  <div class="chat-shell">

    <div class="chat-list" id="chatList">
      <div class="chat-search"><label class="visually-hidden" for="chatSearch">Search conversations</label><input type="search" id="chatSearch" placeholder="Search conversations..."></div>
      <ul id="chatListItems" class="chat-list-items" aria-label="Conversations" aria-busy="true">
        <li><p class="notif-empty" role="status" aria-live="polite">Loading conversations…</p></li>
      </ul>
      <p id="chatSearchStatus" class="visually-hidden" role="status" aria-live="polite"></p>
    </div>

    <div class="chat-panel">
      <div class="chat-head" id="chatHead" aria-live="polite">
        <p class="notif-empty" style="padding:0">Select a conversation</p>
      </div>

      <div class="chat-messages" id="chatMessages" role="log" aria-label="Conversation messages" aria-live="polite" aria-relevant="additions text"></div>
      <div id="chatNotice" class="chat-notice"></div>

      <form class="chat-input" id="chatForm" style="display:none">
        <label class="visually-hidden" for="chatInput">Type a message</label>
        <input type="text" id="chatInput" placeholder="Type a message..." autocomplete="off">
        <button type="submit" aria-label="Send message"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
      </form>
    </div>

  </div>
</div>
</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="notifications-widget.js?v=<?= hash_file('sha256', __DIR__ . '/notifications-widget.js') ?>"></script>
<script src="messages.js?v=<?= hash_file('sha256', __DIR__ . '/messages.js') ?>"></script>
</body>
</html>