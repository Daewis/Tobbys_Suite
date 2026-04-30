<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();

// Clear logs if requested (Admin only safety)
if (isset($_GET['clear']) && $_SESSION['role'] === 'admin') {
    $db->query("DELETE FROM activity_logs");
    logActivity("CLEAR_LOGS", "Audit trail was purged by admin.");
    header('Location: view_logs.php?success=cleared');
    exit;
}

// Pagination logic
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch total for pagination
$total_logs = $db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Fetch logs with user names
$logs = $db->prepare("
    SELECT l.*, u.name as staff_name, u.role as staff_role 
    FROM activity_logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT ? OFFSET ?
");
$logs->execute([$limit, $offset]);
$logs = $logs->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Activity Logs | Tobby’s Suite</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,900&family=JetBrains+Mono:wght@500;700&display=swap');
    .font-serif-italic { font-family: 'Playfair Display', serif; font-style: italic; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
  <script>
    tailwind.config={theme:{extend:{colors:{"primary":"#1e293b","secondary":"#fbbf24"}}}}
  </script>
</head>
<body class="bg-slate-50 min-h-screen flex text-slate-900">

  <?php include 'includes/sidebar.php'; ?>

  <main class="flex-1 flex flex-col h-screen overflow-y-auto">
    <header class="bg-white border-b border-slate-200 p-6 sticky top-0 z-10 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Strategic Audit Trail</h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Immutable Administrative Activity Log</p>
      </div>
      <div class="flex gap-3">
         <a href="?clear=1" onclick="return confirm('WARNING: This will permanently delete all audit logs. This action is irreversible. Proceed?')" class="bg-red-50 text-red-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest flex items-center gap-2 transition-all hover:bg-red-100">
           <span class="material-symbols-outlined text-sm">dangerous</span>
           Purge Logs
         </a>
      </div>
    </header>

    <div class="p-8 space-y-6">
      
      <!-- Feed UI -->
      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-50 bg-slate-50/30">
           <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Live Activity Feed</p>
        </div>
        <div class="divide-y divide-slate-100">
          <?php if (empty($logs)): ?>
          <div class="p-12 text-center text-slate-400 italic text-sm font-bold uppercase tracking-widest">No activity recorded.</div>
          <?php endif; ?>
          <?php foreach ($logs as $log): ?>
          <tr class="hover:bg-slate-50 transition-colors">
            <div class="p-4 md:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-50 transition-colors">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0 shadow-lg shadow-slate-200">
                        <span class="material-symbols-outlined text-sm">history_edu</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-black text-slate-900 uppercase italic"><?= htmlspecialchars($log['action']) ?></span>
                            <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-400 text-[8px] font-black uppercase tracking-widest"><?= $log['ip_address'] ?></span>
                        </div>
                        <p class="text-sm text-slate-600 font-medium"><?= htmlspecialchars($log['details']) ?></p>
                    </div>
                </div>
                <div class="md:text-right">
                    <p class="text-xs font-black text-slate-900 uppercase tracking-tighter italic"><?= htmlspecialchars($log['staff_name'] ?? 'System') ?></p>
                    <p class="text-[9px] text-slate-400 font-mono"><?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?></p>
                </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="flex items-center justify-center gap-2">
         <?php for($i=1; $i<=$total_pages; $i++): ?>
            <a href="?page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-black uppercase transition-all <?= $page == $i ? 'bg-slate-900 text-white shadow-lg' : 'bg-white text-slate-400 border border-slate-200 hover:border-slate-900 hover:text-slate-900' ?>">
               <?= $i ?>
            </a>
         <?php endfor; ?>
      </div>
      <?php endif; ?>

    </div>
  </main>
</body>
</html>
