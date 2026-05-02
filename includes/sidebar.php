<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? 'Staff';

// Role badge styles
$role_styles = [
    'admin'        => ['label' => 'Admin',        'text' => 'text-red-400'],
    'manager'      => ['label' => 'Manager',      'text' => 'text-blue-400'],
    'accountant'   => ['label' => 'Accountant',   'text' => 'text-green-400'],
    'receptionist' => ['label' => 'Receptionist', 'text' => 'text-purple-400'],
    'maintenance'  => ['label' => 'Maintenance',  'text' => 'text-orange-400'],
];
$ri = $role_styles[$role] ?? ['label' => ucfirst($role), 'text' => 'text-slate-400'];

function nav_link($file, $icon, $label, $current) {
    $active = $current === $file
        ? 'bg-white/10 text-yellow-400 font-bold'
        : 'hover:bg-white/5 transition-all';
    echo "<a href=\"{$file}\" class=\"flex items-center gap-3 p-3 rounded-xl {$active}\">
      <span class=\"material-symbols-outlined\">{$icon}</span>
      <span class=\"text-sm\">{$label}</span>
    </a>";
}
?>

<aside class="w-64 bg-slate-900 text-white flex flex-col shrink-0 h-screen sticky top-0">

  <!-- Logo + User Badge -->
  <div class="p-6 border-b border-white/10">
    <div class="flex items-center gap-3 mb-4">
    <img src="assets/img/screen.png" 
       alt="Tobby's Suite Logo" 
       class="h-16 w-auto">
    </div>
    <!-- Logged-in user pill -->
    <div class="flex items-center gap-3 bg-white/5 rounded-2xl px-3 py-2.5">
      <div class="w-8 h-8 rounded-xl bg-slate-700 flex items-center justify-center font-black text-sm text-white shrink-0">
        <?= strtoupper(substr($name, 0, 1)) ?>
      </div>
      <div class="min-w-0">
        <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars(explode(' ', $name)[0]) ?></p>
        <p class="text-[9px] font-black uppercase tracking-widest <?= $ri['text'] ?>"><?= $ri['label'] ?></p>
      </div>
    </div>
  </div>

  <nav class="flex-1 p-4 space-y-1 overflow-y-auto">

    <!-- All staff: Dashboard -->
    <?php nav_link('admin_dashboard.php', 'dashboard', 'Dashboard', $current_page); ?>

    <!-- Admin + Manager: Apartments & Tenants (full access) -->
    <?php if (in_array($role, ['admin', 'manager'])): ?>
      <?php nav_link('manage_apartments.php', 'apartment', 'Apartments', $current_page); ?>
      <?php nav_link('manage_tenants.php',    'group',     'Tenants',    $current_page); ?>
    <?php endif; ?>

    <!-- Receptionist: Tenants read-only -->
    <?php if ($role === 'receptionist'): ?>
      <?php nav_link('manage_tenants.php', 'group', 'Tenants', $current_page); ?>
    <?php endif; ?>

    <!-- Finance: Admin, Manager, Accountant -->
    <?php if (in_array($role, ['admin', 'manager', 'accountant'])): ?>
      <?php nav_link('manage_payments.php',  'payments',               'Rent Payments',  $current_page); ?>
      <?php nav_link('manage_reminders.php', 'notification_important', 'Rent Reminders', $current_page); ?>
      <?php nav_link('manage_bills.php',     'receipt_long',           'Utility Billing',$current_page); ?>
    <?php endif; ?>

    <!-- Docs: Admin, Manager, Accountant -->
    <?php if (in_array($role, ['admin', 'manager', 'accountant'])): ?>
      <?php nav_link('manage_documents.php', 'description', 'Lease & Docs', $current_page); ?>
    <?php endif; ?>

    <!-- Maintenance page: Admin + Manager (full control) -->
    <?php if (in_array($role, ['admin', 'manager'])): ?>
      <?php nav_link('manage_complaints.php', 'engineering', 'Maintenance', $current_page); ?>
    <?php endif; ?>

    <!-- Maintenance staff: own tickets only -->
    <?php if ($role === 'maintenance'): ?>
      <?php nav_link('manage_complaints.php', 'engineering', 'My Tickets', $current_page); ?>
    <?php endif; ?>

    <!-- Receptionist: can submit maintenance on behalf of tenants -->
    <?php if ($role === 'receptionist'): ?>
      <?php nav_link('manage_complaints.php', 'engineering', 'Maintenance', $current_page); ?>
    <?php endif; ?>

    <!-- Admin only: Staff Management + Activity Logs -->
    <?php if ($role === 'admin'): ?>
      <?php nav_link('manage_staff.php', 'badge',   'Staff Management', $current_page); ?>
      <?php nav_link('view_logs.php',    'history', 'Activity Logs',    $current_page); ?>
    <?php endif; ?>

    <!-- Reports: Admin, Manager, Accountant -->
    <?php if (in_array($role, ['admin', 'manager', 'accountant'])): ?>
      <?php nav_link('admin_reports.php', 'analytics', 'Reports & Analytics', $current_page); ?>
    <?php endif; ?>

    <!-- Visitor Log + Notices: Admin, Manager, Receptionist -->
    <?php if (in_array($role, ['admin', 'manager', 'receptionist'])): ?>
      <?php nav_link('manage_visitors.php', 'person_pin_circle', 'Visitor Log',  $current_page); ?>
      <?php nav_link('manage_notices.php',  'campaign',          'Notice Board', $current_page); ?>
    <?php endif; ?>

  </nav>

  <div class="p-4 border-t border-white/10">
    <a href="logout.php" class="flex items-center gap-3 p-3 text-red-400 hover:bg-red-400/10 rounded-xl transition-all">
      <span class="material-symbols-outlined">logout</span>
      <span class="text-sm font-bold">Logout</span>
    </a>
  </div>

</aside>