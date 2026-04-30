<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'tenant') {
    header('Location: login.php');
    exit;
}

$db      = getDB();
$user_id = $_SESSION['user_id'];
$active_page = 'notices';

// Fetch tenant
$stmt = $db->prepare("SELECT t.*, a.unit_number, a.floor, a.type
                       FROM tenants t
                       JOIN apartments a ON t.apartment_id = a.id
                       WHERE t.user_id = ? AND t.status = 'Active'");
$stmt->execute([$user_id]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: tenant_dashboard.php'); exit; }

// Fetch notices - all, sorted newest first
$notices = $db->query("SELECT * FROM notices ORDER BY created_at DESC")->fetchAll();

// Group by category
$grouped = [];
foreach ($notices as $notice) {
    $grouped[$notice['category']][] = $notice;
}

$category_colors = [
    'General'      => 'bg-slate-100 text-slate-600',
    'Maintenance'  => 'bg-orange-50 text-orange-600',
    'Finance'      => 'bg-green-50 text-green-700',
    'Security'     => 'bg-red-50 text-red-600',
    'Event'        => 'bg-purple-50 text-purple-600',
    'Utility'      => 'bg-blue-50 text-blue-600',
];

$page_title = "Announcements | Tobby's Suite";
include __DIR__ . '/includes/tenant_head.php';
?>

<div class="flex">
  <?php include __DIR__ . '/includes/tenant_sidebar.php'; ?>

  <main class="flex-1 min-h-screen">
    <nav class="bg-white border-b border-slate-200 px-8 py-5 flex justify-between items-center sticky top-0 z-10">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Announcements</h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Suite Notices & Updates</p>
      </div>
      <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-black">
        <?= substr($tenant['name'], 0, 1) ?>
      </div>
    </nav>

    <div class="p-6 lg:p-8 space-y-8">

      <?php if (empty($notices)): ?>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-16 text-center">
          <span class="material-symbols-outlined text-slate-200 text-5xl mb-4 block">notifications_off</span>
          <p class="text-sm font-black text-slate-400 uppercase tracking-widest">No announcements yet</p>
          <p class="text-xs text-slate-400 mt-2">Management notices and updates will appear here.</p>
        </div>

      <?php else: ?>

        <!-- Latest Notice Hero -->
        <?php $latest = $notices[0]; ?>
        <?php $cat_style = $category_colors[$latest['category']] ?? 'bg-slate-100 text-slate-600'; ?>
        <div class="bg-slate-900 text-white p-8 lg:p-10 rounded-3xl relative overflow-hidden">
          <!-- decorative -->
          <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-3 mb-4">
              <span class="text-[8px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg <?= $cat_style ?>">
                <?= htmlspecialchars($latest['category']) ?>
              </span>
              <span class="text-[9px] font-mono text-slate-400"><?= date('d M Y', strtotime($latest['created_at'])) ?></span>
              <span class="text-[8px] font-black text-yellow-400 bg-yellow-400/10 px-2 py-0.5 rounded-lg uppercase">Latest</span>
            </div>
            <h2 class="text-xl font-serif-italic text-white mb-3"><?= htmlspecialchars($latest['title']) ?></h2>
            <p class="text-sm text-slate-300 leading-relaxed"><?= htmlspecialchars($latest['content']) ?></p>
          </div>
        </div>

        <!-- All Notices by Category -->
        <?php if (count($notices) > 1): ?>
          <?php foreach ($grouped as $category => $cat_notices): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
              <div class="px-8 py-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <?php $cstyle = $category_colors[$category] ?? 'bg-slate-100 text-slate-600'; ?>
                <div class="flex items-center gap-3">
                  <span class="material-symbols-outlined text-slate-400 text-sm">label</span>
                  <h3 class="font-black text-slate-700 uppercase text-xs tracking-widest"><?= htmlspecialchars($category) ?></h3>
                </div>
                <span class="text-[8px] font-black px-2 py-0.5 rounded-lg <?= $cstyle ?>">
                  <?= count($cat_notices) ?> notice<?= count($cat_notices) != 1 ? 's' : '' ?>
                </span>
              </div>

              <div class="divide-y divide-slate-50">
                <?php foreach ($cat_notices as $notice): ?>
                  <div class="p-6 lg:p-8 hover:bg-slate-50 transition-colors">
                    <div class="flex justify-between items-start mb-2">
                      <h4 class="text-sm font-black text-slate-900 pr-4"><?= htmlspecialchars($notice['title']) ?></h4>
                      <span class="shrink-0 text-[9px] font-mono text-slate-400"><?= date('d M Y', strtotime($notice['created_at'])) ?></span>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed"><?= htmlspecialchars($notice['content']) ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>