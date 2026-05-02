<?php
// includes/notification_bell.php
// Drop this anywhere inside a navbar/header.
// Requires: $db and $_SESSION['user_id'] to be available.
// Requires: includes/notifications.php already loaded.

$bell_user_id     = (int) $_SESSION['user_id'];
$bell_unread      = countUnread($db, $bell_user_id);
$bell_notifs      = getNotifications($db, $bell_user_id, 15);
?>

<div class="relative" id="bellWrapper">

  <!-- Bell Button -->
  <button id="bellBtn"
          onclick="toggleBell()"
          class="relative w-10 h-10 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200 transition-all">
    <span class="material-symbols-outlined text-[20px]">notifications</span>
    <?php if ($bell_unread > 0): ?>
      <span id="bellBadge"
            class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[9px] font-black rounded-full flex items-center justify-center leading-none">
        <?= $bell_unread > 9 ? '9+' : $bell_unread ?>
      </span>
    <?php else: ?>
      <span id="bellBadge" class="hidden absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[9px] font-black rounded-full flex items-center justify-center leading-none"></span>
    <?php endif; ?>
  </button>

  <!-- Dropdown Panel -->
  <div id="bellDropdown"
       class="hidden absolute right-0 top-14 w-80 bg-white rounded-3xl shadow-2xl border border-slate-100 z-50 overflow-hidden">

    <!-- Header -->
    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
      <div>
        <p class="text-sm font-black text-slate-900">Notifications</p>
        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
          <?= $bell_unread ?> unread
        </p>
      </div>
      <?php if ($bell_unread > 0): ?>
        <button onclick="markAllRead()"
                class="text-[9px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-900 transition-colors">
          Mark all read
        </button>
      <?php endif; ?>
    </div>

    <!-- List -->
    <div class="max-h-96 overflow-y-auto divide-y divide-slate-50">
      <?php if (empty($bell_notifs)): ?>
        <div class="p-10 text-center">
          <span class="material-symbols-outlined text-slate-200 text-4xl mb-2 block">notifications_off</span>
          <p class="text-xs text-slate-400 font-bold">No notifications yet</p>
        </div>
      <?php else: ?>
        <?php foreach ($bell_notifs as $notif):
          $meta = notificationMeta($notif['type']);
          $is_unread = !$notif['is_read'];
        ?>
          <div id="notif-<?= $notif['id'] ?>"
               onclick="handleNotifClick(<?= $notif['id'] ?>, '<?= addslashes($notif['link'] ?? '') ?>')"
               class="flex items-start gap-3 px-5 py-4 cursor-pointer transition-colors
                      <?= $is_unread ? 'bg-slate-50 hover:bg-slate-100' : 'hover:bg-slate-50' ?>">

            <!-- Icon -->
            <div class="w-9 h-9 rounded-xl <?= $meta['bg'] ?> <?= $meta['text'] ?> flex items-center justify-center shrink-0 mt-0.5">
              <span class="material-symbols-outlined text-[17px]"><?= $meta['icon'] ?></span>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <p class="text-xs font-black text-slate-900 leading-snug"><?= htmlspecialchars($notif['title']) ?></p>
              <p class="text-[10px] text-slate-500 leading-relaxed mt-0.5 line-clamp-2">
                <?= htmlspecialchars($notif['message']) ?>
              </p>
              <p class="text-[9px] text-slate-300 font-mono mt-1"><?= timeAgo($notif['created_at']) ?></p>
            </div>

            <!-- Unread dot -->
            <?php if ($is_unread): ?>
              <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0 mt-2"></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php if (!empty($bell_notifs)): ?>
      <div class="px-6 py-3 border-t border-slate-100 text-center">
        <a href="notifications.php"
           class="text-[9px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-900 transition-colors">
          View all notifications
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
// ── Bell toggle ───────────────────────────────────────────────────────────────
function toggleBell() {
  const d = document.getElementById('bellDropdown');
  d.classList.toggle('hidden');
}

// Close when clicking outside
document.addEventListener('click', function(e) {
  const wrapper = document.getElementById('bellWrapper');
  if (wrapper && !wrapper.contains(e.target)) {
    document.getElementById('bellDropdown')?.classList.add('hidden');
  }
});

// ── Mark single notification read + redirect ──────────────────────────────────
function handleNotifClick(id, link) {
  fetch('api/mark_notification_read.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id: id }),
  })
  .then(r => r.json())
  .then(data => {
    // Remove unread dot + background
    const el = document.getElementById('notif-' + id);
    if (el) {
      el.classList.remove('bg-slate-50', 'hover:bg-slate-100');
      el.classList.add('hover:bg-slate-50');
      const dot = el.querySelector('.bg-blue-500');
      if (dot) dot.remove();
    }
    // Update badge count
    updateBadge(data.unread ?? null);
    // Navigate if link provided
    if (link) window.location.href = link;
  })
  .catch(console.error);
}

// ── Mark ALL read ─────────────────────────────────────────────────────────────
function markAllRead() {
  fetch('api/mark_notification_read.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ all: true }),
  })
  .then(r => r.json())
  .then(() => {
    // Remove all unread styles
    document.querySelectorAll('[id^="notif-"]').forEach(el => {
      el.classList.remove('bg-slate-50', 'hover:bg-slate-100');
      el.classList.add('hover:bg-slate-50');
      const dot = el.querySelector('.bg-blue-500');
      if (dot) dot.remove();
    });
    updateBadge(0);
    // Hide "mark all read" button
    document.querySelector('[onclick="markAllRead()"]')?.remove();
    // Update unread count label
    const label = document.querySelector('#bellDropdown p.text-\\[9px\\]');
    if (label) label.textContent = '0 unread';
  })
  .catch(console.error);
}

// ── Update badge number ───────────────────────────────────────────────────────
function updateBadge(count) {
  const badge = document.getElementById('bellBadge');
  if (!badge) return;
  if (count === null || count === undefined) {
    // Decrement by 1
    const cur = parseInt(badge.textContent) || 0;
    count = Math.max(0, cur - 1);
  }
  if (count <= 0) {
    badge.classList.add('hidden');
    badge.textContent = '';
  } else {
    badge.classList.remove('hidden');
    badge.textContent = count > 9 ? '9+' : count;
  }
}
</script>