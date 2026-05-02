<?php
// includes/tenant_sidebar.php
// NOTE: No require_once here. Auth is already handled by the parent page.
// The including page must define $active_page before including, e.g:
//   $active_page = 'overview';
// $tenant must also be available in scope (fetched by the parent page).

$nav_items = [
    ['key' => 'overview',    'href' => 'tenant_dashboard.php',   'icon' => 'dashboard',             'label' => 'Overview'],
    ['key' => 'payments',    'href' => 'tenant_payments.php',    'icon' => 'payments',              'label' => 'Payments'],
    ['key' => 'documents',   'href' => 'tenant_documents.php',   'icon' => 'folder_shared',         'label' => 'Documents'],
    ['key' => 'complaints',  'href' => 'tenant_complaints.php',  'icon' => 'engineering',           'label' => 'Maintenance'],
    ['key' => 'notices',     'href' => 'tenant_notices.php',     'icon' => 'notifications_active',  'label' => 'Announcements'],
];
?>

<!-- ===== DESKTOP SIDEBAR ===== -->
<aside class="hidden lg:flex w-64 bg-slate-900 text-white flex-col shrink-0 h-screen sticky top-0">
  <div class="p-6 border-b border-white/10 flex items-center gap-3">
  <img src="assets/img/screen.png" 
       alt="Tobby's Suite Logo" 
       class="h-16 w-auto">
  </div>

  <nav class="flex-1 p-4 space-y-1">
    <?php foreach ($nav_items as $item): 
      $is_active = ($active_page === $item['key']);
    ?>
    <a href="<?= $item['href'] ?>"
       class="flex items-center gap-3 p-3 rounded-xl font-bold transition-all
              <?= $is_active
                  ? 'bg-white/10 text-yellow-400'
                  : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
      <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
      <span class="text-sm"><?= $item['label'] ?></span>
      <?php if ($is_active): ?>
        <span class="ml-auto w-1.5 h-1.5 rounded-full bg-yellow-400"></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="p-6 border-t border-white/10">
    <a href="logout.php"
       class="flex items-center gap-3 p-3 text-red-400 hover:bg-red-400/10 rounded-xl transition-all">
      <span class="material-symbols-outlined">logout</span>
      <span class="text-sm font-bold">Sign Out</span>
    </a>
  </div>
</aside>

<!-- ===== MOBILE BOTTOM NAV ===== -->
<nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-slate-900 border-t border-white/10 z-50 flex justify-around items-center h-16 px-2">
  <?php foreach ($nav_items as $item):
    $is_active = ($active_page === $item['key']);
  ?>
  <a href="<?= $item['href'] ?>"
     class="flex flex-col items-center gap-0.5 px-3 py-2 rounded-xl transition-all
            <?= $is_active ? 'text-yellow-400' : 'text-slate-500' ?>">
    <span class="material-symbols-outlined text-[22px]"><?= $item['icon'] ?></span>
    <span class="text-[8px] font-black uppercase tracking-widest"><?= $item['label'] ?></span>
  </a>
  <?php endforeach; ?>
</nav>