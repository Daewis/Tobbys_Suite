<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager', 'accountant'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();


$total_revenue = $db->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?: 0;
$this_month_revenue = $db->query("SELECT SUM(amount) FROM payments 
    WHERE DATE_FORMAT(payment_date, '%m-%Y') = DATE_FORMAT(CURRENT_DATE, '%m-%Y')")->fetchColumn() ?: 0;

$occupied_rent_raw = $db->query("SELECT SUM(rent_amount) FROM apartments WHERE status = 'Occupied'")->fetchColumn() ?: 0;
$collection_rate = $occupied_rent_raw > 0 ? ($this_month_revenue / $occupied_rent_raw) * 100 : 0;
$total_unpaid_bills = $db->query("SELECT SUM(amount) FROM bills WHERE status = 'Unpaid'")->fetchColumn() ?: 0;

$monthly_stats = $db->query("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total 
    FROM payments 
    GROUP BY month 
    ORDER BY month DESC 
    LIMIT 6
")->fetchAll();


$total_units = $db->query("SELECT COUNT(*) FROM apartments")->fetchColumn() ?: 0;
$occupied_units = $db->query("SELECT COUNT(*) FROM apartments WHERE status = 'Occupied'")->fetchColumn() ?: 0;
$vacant_units = $total_units - $occupied_units;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Financial Reports | Tobby’s Suite</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,900&family=JetBrains+Mono:wght@500;700&display=swap');
    .font-serif-italic { font-family: 'Playfair Display', serif; font-style: italic; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
  <!-- D3 for mini charts -->
  <script src="https://d3js.org/d3.v7.min.js"></script>
  <script>
    tailwind.config={theme:{extend:{colors:{"primary":"#1e293b","secondary":"#fbbf24"}}}}
  </script>
</head>
<body class="bg-slate-50 min-h-screen flex">

  <?php include 'includes/sidebar.php'; ?>

  <main class="flex-1 flex flex-col h-screen overflow-y-auto">
    <header class="bg-white border-b border-slate-200 p-6 sticky top-0 z-10 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Financial Insights</h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Real-time Suite Analytics</p>
      </div>
      <button onclick="window.print()" class="flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition-all">
        <span class="material-symbols-outlined text-sm">print</span>
        Export PDF
      </button>
    </header>

    <div class="p-8 space-y-8">
      
      <!-- Top Level Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-slate-900 rounded-3xl p-8 text-white relative overflow-hidden">
          <div class="relative z-10">
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Total Gross Revenue</p>
            <h3 class="text-3xl font-black italic tracking-tighter mb-4 text-white font-serif-italic">₦<?= number_format($total_revenue) ?></h3>
            <div class="flex items-center gap-2">
              <span class="px-2 py-0.5 rounded-lg bg-white/10 text-yellow-400 text-[10px] font-black uppercase tracking-widest">+12% vs last year</span>
            </div>
          </div>
          <span class="material-symbols-outlined absolute -bottom-4 -right-4 text-9xl opacity-5">payments</span>
        </div>

        <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Unpaid Utility Debt</p>
            <h3 class="text-3xl font-black italic tracking-tighter text-red-600 font-serif-italic">₦<?= number_format($total_unpaid_bills) ?></h3>
          </div>
          <p class="text-xs text-slate-500 font-medium">Pending resident settlement</p>
        </div>

        <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Collection Health</p>
            <div class="flex items-end gap-2 mb-2">
               <h3 class="text-3xl font-black italic tracking-tighter text-slate-900 font-serif-italic"><?= round($collection_rate) ?>%</h3>
               <p class="text-[10px] text-slate-400 mb-2 font-bold">(Monthly Rent)</p>
            </div>
            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
              <div class="bg-green-500 h-full" style="width: <?= $collection_rate ?>%"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Occupancy & History -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Monthly Revenue Chart (SVG/D3 placeholder) -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8 flex flex-col">
          <div class="flex justify-between items-center mb-8">
            <h3 class="font-black text-slate-900 uppercase text-xs tracking-tighter">Revenue Trend (Last 6 Months)</h3>
            <span class="material-symbols-outlined text-slate-300">insights</span>
          </div>
          <div class="flex-1 flex items-end gap-4 h-48">
            <?php 
            $max_m = count($monthly_stats) > 0 ? max(array_column($monthly_stats, 'total')) : 1;
            foreach(array_reverse($monthly_stats) as $stat): 
              $h = ($stat['total'] / $max_m) * 100;
            ?>
              <div class="flex-1 flex flex-col items-center gap-2">
                <div class="w-full bg-slate-900 rounded-lg group relative cursor-help" style="height: <?= $h ?>%">
                  <div class="absolute -top-10 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-2 py-1 rounded text-[10px] whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                    ₦<?= number_format($stat['total']) ?>
                  </div>
                </div>
                <p class="text-[8px] font-black uppercase text-slate-400"><?= date('M', strtotime($stat['month'] . '-01')) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Occupancy Breakdown -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8 flex flex-col">
          <div class="flex justify-between items-center mb-8">
            <h3 class="font-black text-slate-900 uppercase text-xs tracking-tighter">Occupancy Snapshot</h3>
            <span class="material-symbols-outlined text-slate-300">pie_chart</span>
          </div>
          <div class="flex-1 flex items-center justify-center gap-12">
            <div class="relative w-32 h-32">
               <!-- Simple SVG Pie -->
               <svg viewBox="0 0 32 32" class="w-full h-full -rotate-90">
                 <?php
                 $p = $total_units > 0 ? ($occupied_units / $total_units) * 100 : 0;
                 $dash = ($p / 100) * 100; // Circumference is 100 in simple circle
                 ?>
                 <circle r="16" cx="16" cy="16" fill="#f1f5f9" />
                 <circle r="16" cx="16" cy="16" fill="transparent" stroke="#1e293b" stroke-width="32" stroke-dasharray="<?= $dash ?> 100" />
               </svg>
            </div>
            <div class="space-y-4">
              <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-slate-900"></div>
                <div>
                  <p class="text-[10px] font-black text-slate-400 uppercase">Occupied</p>
                  <p class="text-xl font-black text-slate-900"><?= $occupied_units ?> Units</p>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-slate-200"></div>
                <div>
                  <p class="text-[10px] font-black text-slate-400 uppercase">Vacant</p>
                  <p class="text-xl font-black text-slate-900"><?= $vacant_units ?> Units</p>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Detailed Revenue Table -->
      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-8 border-b border-slate-100">
           <h3 class="font-black text-slate-900 uppercase text-xs tracking-tighter">Recent Payment Audit</h3>
        </div>
        <table class="w-full text-left">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="p-6 text-[10px] font-black text-slate-400 uppercase">Date</th>
              <th class="p-6 text-[10px] font-black text-slate-400 uppercase">Tenant</th>
              <th class="p-6 text-[10px] font-black text-slate-400 uppercase">Source</th>
              <th class="p-6 text-[10px] font-black text-slate-400 uppercase">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php
            $audit = $db->query("SELECT p.*, t.name as tenant_name FROM payments p JOIN tenants t ON p.tenant_id = t.id ORDER BY p.payment_date DESC LIMIT 10")->fetchAll();
            foreach($audit as $item):
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
              <td class="p-6 text-xs text-slate-500"><?= date('d M Y', strtotime($item['payment_date'])) ?></td>
              <td class="p-6 text-sm font-bold text-slate-900"><?= htmlspecialchars($item['tenant_name']) ?></td>
              <td class="p-6 text-xs text-slate-400 uppercase font-black tracking-widest"><?= $item['method'] ?></td>
              <td class="p-6 text-sm font-black text-slate-900 italic font-mono tracking-tighter">₦<?= number_format($item['amount']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>

</body>
</html>
