<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'tenant') {
    header('Location: login.php');
    exit;
}

$db      = getDB();
$user_id = $_SESSION['user_id'];
$active_page = 'documents';

// Fetch tenant
$stmt = $db->prepare("SELECT t.*, a.unit_number, a.floor, a.type, a.rent_amount
                       FROM tenants t
                       JOIN apartments a ON t.apartment_id = a.id
                       WHERE t.user_id = ? AND t.status = 'Active'");
$stmt->execute([$user_id]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: tenant_dashboard.php'); exit; }

// Fetch documents
$stmt = $db->prepare("SELECT * FROM documents WHERE tenant_id = ? OR (apartment_id = ? AND tenant_id IS NULL) ORDER BY uploaded_at DESC");
$stmt->execute([$tenant['id'], $tenant['apartment_id']]);
$my_documents = $stmt->fetchAll();

// Group by category
$grouped = [];
foreach ($my_documents as $doc) {
    $grouped[$doc['category']][] = $doc;
}

$page_title = "Documents | Tobby's Suite";
include __DIR__ . '/includes/tenant_head.php';
?>

<div class="flex">
  <?php include __DIR__ . '/includes/tenant_sidebar.php'; ?>

  <main class="flex-1 min-h-screen">
    <nav class="bg-white border-b border-slate-200 px-8 py-5 flex justify-between items-center sticky top-0 z-10">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">My Documents</h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Unit <?= $tenant['unit_number'] ?> • Lease & Shared Files</p>
      </div>
      <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-black">
        <?= substr($tenant['name'], 0, 1) ?>
      </div>
    </nav>

    <div class="p-6 lg:p-8 space-y-8">

      <?php if (empty($my_documents)): ?>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-16 text-center">
          <span class="material-symbols-outlined text-slate-200 text-5xl mb-4 block">folder_open</span>
          <p class="text-sm font-black text-slate-400 uppercase tracking-widest">No documents shared yet</p>
          <p class="text-xs text-slate-400 mt-2">Your lease agreement and other files will appear here once uploaded by management.</p>
        </div>

      <?php else: ?>

        <!-- Document count banner -->
        <div class="flex items-center gap-4 p-5 bg-slate-900 text-white rounded-2xl">
          <span class="material-symbols-outlined text-yellow-400 p-2 bg-white/10 rounded-xl">folder_shared</span>
          <div>
            <p class="text-sm font-black"><?= count($my_documents) ?> document<?= count($my_documents) != 1 ? 's' : '' ?> available</p>
            <p class="text-[9px] text-slate-400 uppercase tracking-widest"><?= count($grouped) ?> categor<?= count($grouped) != 1 ? 'ies' : 'y' ?></p>
          </div>
        </div>

        <!-- Grouped Documents -->
        <?php foreach ($grouped as $category => $docs): ?>
          <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-slate-400 text-sm">label</span>
                <h3 class="font-black text-slate-700 uppercase text-xs tracking-widest"><?= htmlspecialchars($category) ?></h3>
              </div>
              <span class="text-[9px] font-black text-slate-400 bg-white border border-slate-200 px-2 py-0.5 rounded-lg uppercase">
                <?= count($docs) ?> file<?= count($docs) != 1 ? 's' : '' ?>
              </span>
            </div>
            <div class="divide-y divide-slate-50">
              <?php foreach ($docs as $doc): ?>
                <?php
                  $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                  $icon_map = ['pdf' => 'picture_as_pdf', 'doc' => 'description', 'docx' => 'description', 'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image'];
                  $icon = $icon_map[$ext] ?? 'insert_drive_file';
                  $color_map = ['pdf' => 'bg-red-50 text-red-500', 'doc' => 'bg-blue-50 text-blue-500', 'docx' => 'bg-blue-50 text-blue-500', 'jpg' => 'bg-purple-50 text-purple-500', 'jpeg' => 'bg-purple-50 text-purple-500', 'png' => 'bg-purple-50 text-purple-500'];
                  $color = $color_map[$ext] ?? 'bg-slate-50 text-slate-500';
                ?>
                <div class="p-6 flex items-center justify-between hover:bg-slate-50 transition-colors">
                  <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl <?= $color ?> flex items-center justify-center">
                      <span class="material-symbols-outlined"><?= $icon ?></span>
                    </div>
                    <div>
                      <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($doc['title']) ?></p>
                      <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[9px] font-mono text-slate-400 uppercase">.<?= $ext ?></span>
                        <span class="text-slate-200">•</span>
                        <span class="text-[9px] text-slate-400"><?= date('d M Y', strtotime($doc['uploaded_at'])) ?></span>
                      </div>
                    </div>
                  </div>
                  <a href="<?= htmlspecialchars($doc['file_path']) ?>" download
                     class="flex items-center gap-2 px-4 py-2.5 bg-slate-100 text-slate-600 rounded-2xl text-[9px] font-black uppercase tracking-widest hover:bg-slate-900 hover:text-white transition-all">
                    <span class="material-symbols-outlined text-sm">download</span>
                    Download
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>