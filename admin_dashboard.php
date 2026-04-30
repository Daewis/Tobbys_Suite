<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager', 'accountant', 'receptionist', 'maintenance' ])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

// Fetch stats
$total_apartments = $db->query("SELECT COUNT(*) FROM apartments")->fetchColumn();
$occupied_apartments = $db->query("SELECT COUNT(*) FROM apartments WHERE status = 'Occupied'")->fetchColumn();
$total_tenants = $db->query("SELECT COUNT(*) FROM tenants WHERE status = 'Active'")->fetchColumn();
$pending_complaints = $db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Pending'")->fetchColumn();
$daily_visitors = $db->query("SELECT COUNT(*) FROM visitors WHERE DATE(check_in) = CURDATE()")->fetchColumn();
$monthly_revenue = $db->query("SELECT SUM(amount) FROM payments WHERE DATE_FORMAT(payment_date, '%m-%Y') = DATE_FORMAT(CURRENT_DATE, '%m-%Y')")->fetchColumn() ?: 0;
$current_month = date('m-Y');
$total_debtors = $db->query("SELECT COUNT(*) FROM tenants t WHERE t.status = 'Active' AND t.id NOT IN (SELECT tenant_id FROM payments WHERE month_paid_for = '$current_month')")->fetchColumn();

// Fetch recent apartments
$stmt = $db->query("SELECT * FROM apartments ORDER BY unit_number LIMIT 5");
$apartments = $stmt->fetchAll();

// Fetch pending complaints
$stmt = $db->query("SELECT c.*, a.unit_number FROM complaints c JOIN apartments a ON c.apartment_id = a.id WHERE c.status = 'Pending' ORDER BY c.created_at DESC LIMIT 2");
$complaints_list = $stmt->fetchAll();

// Fetch latest notices
$stmt = $db->query("SELECT * FROM notices ORDER BY created_at DESC LIMIT 3");
$latest_notices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard | Tobby’s Suite</title>
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
<body class="bg-slate-50 min-h-screen flex">

  <!-- Sidebar -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 flex flex-col h-screen overflow-y-auto">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 p-6 flex justify-between items-center sticky top-0 z-10">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Good Day, <?= htmlspecialchars($_SESSION['name']) ?></h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Suite Intelligence Overview</p>
      </div>
      <div class="flex items-center gap-4">
        <button class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600">
          <span class="material-symbols-outlined">notifications</span>
        </button>
        <div class="w-10 h-10 rounded-full bg-yellow-400 flex items-center justify-center text-slate-900 font-bold">
          S
        </div>
      </div>
    </header>

    <div class="p-8 space-y-8">
      <!-- Stats Grid -->
      <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600">
            <span class="material-symbols-outlined">apartment</span>
          </div>
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Units</p>
            <p class="text-2xl font-black text-slate-900 font-mono"><?= $total_apartments ?></p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-600">
            <span class="material-symbols-outlined">how_to_reg</span>
          </div>
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Occupancy</p>
            <p class="text-2xl font-black text-slate-900 font-mono"><?= $total_apartments > 0 ? round(($occupied_apartments/$total_apartments)*100) : 0 ?>%</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center text-red-600">
            <span class="material-symbols-outlined">notification_important</span>
          </div>
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Rent Debtors</p>
            <p class="text-2xl font-black text-red-600 font-mono"><?= $total_debtors ?></p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-slate-900 rounded-xl flex items-center justify-center text-yellow-400">
            <span class="material-symbols-outlined">payments</span>
          </div>
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Revenue</p>
            <p class="text-2xl font-black text-slate-900 font-mono tracking-tighter">₦<?= number_format($monthly_revenue) ?></p>
          </div>
        </div>
      </section>

      <!-- Content Sections -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Apartments Table -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-black text-slate-900">Recent Apartment Units</h3>
            <a href="manage_apartments.php" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-800 transition-all flex items-center gap-2">
              <span class="material-symbols-outlined text-sm">add</span>
               Add New
            </a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Unit</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Type</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Status</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Rent</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($apartments)): ?>
                <tr>
                  <td colspan="5" class="p-8 text-center text-slate-400 italic text-sm">No apartments found. Get started by adding one!</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($apartments as $apt): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="p-4 font-bold text-slate-900 text-sm"><?= htmlspecialchars($apt['unit_number']) ?></td>
                  <td class="p-4 text-slate-600 text-sm"><?= htmlspecialchars($apt['type']) ?></td>
                  <td class="p-4">
                    <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase <?= $apt['status'] === 'Vacant' ? 'bg-green-50 text-green-700' : ($apt['status'] === 'Occupied' ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-700') ?>">
                      <?= $apt['status'] ?>
                    </span>
                  </td>
                  <td class="p-4 text-slate-900 font-bold text-sm">₦<?= number_format($apt['rent_amount']) ?></td>
                  <td class="p-4">
                    <button class="text-slate-400 hover:text-slate-900">
                      <span class="material-symbols-outlined text-lg">more_vert</span>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- System Notices -->
        <div class="space-y-8">
          <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <h3 class="font-black text-slate-900 uppercase text-xs tracking-tighter">Maintenance Queue</h3>
            <div class="space-y-4">
              <?php if (empty($complaints_list)): ?>
                <p class="text-xs text-slate-400 italic">No pending requests.</p>
              <?php endif; ?>
              <?php foreach ($complaints_list as $complaint): ?>
              <div class="p-4 bg-orange-50 rounded-xl border border-orange-100">
                <p class="text-xs font-bold text-orange-900"><?= htmlspecialchars($complaint['subject']) ?> - Unit <?= $complaint['unit_number'] ?></p>
                <p class="text-[10px] text-orange-700 mt-1 uppercase font-black">Urgent • Pending</p>
              </div>
              <?php endforeach; ?>
            </div>
            <a href="manage_complaints.php" class="block w-full text-center py-2 text-xs font-bold text-slate-900 hover:underline">View All Tickets</a>
          </div>

          <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <h3 class="font-black text-slate-900 uppercase text-xs tracking-tighter">Notice Board</h3>
            <div class="space-y-4">
              <?php foreach ($latest_notices as $notice): ?>
              <div class="border-l-2 border-yellow-400 pl-4 py-1">
                <p class="text-xs font-bold text-slate-900"><?= htmlspecialchars($notice['title']) ?></p>
                <p class="text-[10px] text-slate-400"><?= date('M d', strtotime($notice['created_at'])) ?></p>
              </div>
              <?php endforeach; ?>
            </div>
            <a href="manage_notices.php" class="block w-full text-center py-2 text-xs font-bold text-slate-900 hover:underline">Manage Notices</a>
          </div>
        </div>
      </div>
    </div>
  </main>

</body>
</html>
