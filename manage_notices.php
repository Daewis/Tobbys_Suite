<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager', 'security', 'receptionist'])) {
    header('Location: login.php');
    exit;
}

$db      = getDB();
$error   = '';
$success = '';

// Handle Posting Notice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_notice'])) {
    $title    = trim($_POST['title']    ?? '');
    $content  = trim($_POST['content']  ?? '');
    $category = trim($_POST['category'] ?? 'General');

    if (!$title || !$content) {
        $error = 'Title and content are required.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO notices (title, content, category) VALUES (?, ?, ?)");
            $stmt->execute([$title, $content, $category]);
            logActivity('POST_NOTICE', "Title: $title, Category: $category");
            $success = "Announcement published successfully!";

            // Notify ALL active tenants
            notifyAllTenants(
                $db,
                'notice',
                'New Notice: ' . $title,
                strlen($content) > 100 ? substr($content, 0, 100) . '...' : $content,
                'tenant_notices.php',
                $_SESSION['user_id']
            );

        } catch (PDOException $e) {
            $error = "Publishing failed: " . $e->getMessage();
        }
    }
}

// Handle Deletion
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        $stmt = $db->prepare("SELECT title FROM notices WHERE id = ?");
        $stmt->execute([$id]);
        $n_title = $stmt->fetchColumn();

        $stmt = $db->prepare("DELETE FROM notices WHERE id = ?");
        $stmt->execute([$id]);
        logActivity('DELETE_NOTICE', "Title: $n_title");
        $success = "Notice removed.";
    } catch (PDOException $e) {
        $error = "Deletion failed.";
    }
}

// Fetch all notices
$notices = $db->query("SELECT * FROM notices ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notice Board | Tobby's Suite</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script>tailwind.config={theme:{extend:{colors:{"primary":"#1e293b","secondary":"#fbbf24"}}}}</script>
</head>
<body class="bg-slate-50 min-h-screen flex">

  <?php include 'includes/sidebar.php'; ?>

  <main class="flex-1 flex flex-col h-screen overflow-y-auto">
    <header class="bg-white border-b border-slate-200 p-6 sticky top-0 z-10 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-black text-slate-900">Internal Announcements</h2>
        <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mt-0.5">Publishing notifies all active tenants</p>
      </div>
    </header>

    <div class="p-8 grid grid-cols-1 lg:grid-cols-3 gap-8">

      <!-- Notice List -->
      <div class="lg:col-span-2 space-y-6">
        <?php if ($success): ?>
          <div class="p-4 bg-green-50 text-green-700 rounded-xl border border-green-100 flex items-center gap-3 text-sm font-bold">
            <span class="material-symbols-outlined">check_circle</span>
            <?= htmlspecialchars($success) ?>
          </div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="p-4 bg-red-50 text-red-700 rounded-xl border border-red-100 flex items-center gap-3 text-sm font-bold">
            <span class="material-symbols-outlined">error</span>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <div class="space-y-4">
          <?php if (empty($notices)): ?>
            <div class="p-12 text-center bg-white rounded-2xl border border-dashed border-slate-200 text-slate-400 italic">
              No notices published yet.
            </div>
          <?php endif; ?>
          <?php foreach ($notices as $n): ?>
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm relative group">
              <div class="flex justify-between items-start mb-2">
                <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-black uppercase">
                  <?= htmlspecialchars($n['category']) ?>
                </span>
                <p class="text-[10px] font-mono text-slate-400"><?= date('M d, Y', strtotime($n['created_at'])) ?></p>
              </div>
              <h3 class="font-black text-slate-900 text-lg mb-2"><?= htmlspecialchars($n['title']) ?></h3>
              <p class="text-sm text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars($n['content'])) ?></p>

              <!-- Tenants notified badge -->
              <div class="mt-3 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[13px] text-slate-300">notifications_active</span>
                <span class="text-[9px] text-slate-300 font-bold uppercase tracking-widest">Tenants notified</span>
              </div>

              <a href="?delete=<?= $n['id'] ?>"
                 onclick="return confirm('Delete this notice?')"
                 class="absolute top-4 right-4 text-red-100 group-hover:text-red-400 transition-colors">
                <span class="material-symbols-outlined text-lg">delete</span>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Post Form -->
      <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm h-fit space-y-6">
        <div class="border-b border-slate-100 pb-4">
          <h3 class="font-black text-slate-900 italic tracking-tighter">New Announcement</h3>
          <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold mt-1">
            Will notify all active tenants
          </p>
        </div>
        <form method="POST" class="space-y-4">
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Title</label>
            <input type="text" name="title" required placeholder="e.g. Water Maintenance Tomorrow"
                   class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900">
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Category</label>
            <select name="category" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
              <option>General</option>
              <option>Maintenance</option>
              <option>Billing</option>
              <option>Security</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Content</label>
            <textarea name="content" rows="5" required placeholder="Enter the detailed message here..."
                      class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900"></textarea>
          </div>
          <button type="submit" name="post_notice"
                  class="w-full bg-slate-900 text-white py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-800 transition-all shadow-xl shadow-slate-100 flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-sm">campaign</span>
            Publish to Portal
          </button>
        </form>
      </div>

    </div>
  </main>
</body>
</html>