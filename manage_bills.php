<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager', 'accountant'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$error = '';
$success = '';

// Handle Bill Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_bill'])) {
    $tenant_id = (int)$_POST['tenant_id'];
    $bill_type = trim($_POST['bill_type']);
    $amount = (float)$_POST['amount'];
    $month_year = trim($_POST['month_year']);
    $due_date = trim($_POST['due_date']);

    try {
        $stmt = $db->prepare("INSERT INTO bills (tenant_id, bill_type, amount, month_year, due_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $bill_type, $amount, $month_year, $due_date]);
        $success = "Bill generated successfully for tenant!";
    } catch (PDOException $e) {
        $error = "Error generating bill: " . $e->getMessage();
    }
}

// Handle Mark as Paid
if (isset($_GET['mark_paid'])) {
    $bill_id = (int)$_GET['mark_paid'];
    try {
        $stmt = $db->prepare("UPDATE bills SET status = 'Paid' WHERE id = ?");
        $stmt->execute([$bill_id]);
        $success = "Bill marked as paid.";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch all active tenants for selection
$tenants_list = $db->query("SELECT t.id, t.name, a.unit_number 
                           FROM tenants t 
                           JOIN apartments a ON t.apartment_id = a.id 
                           WHERE t.status = 'Active' 
                           ORDER BY a.unit_number")->fetchAll();

// Fetch all bills
$bills = $db->query("SELECT b.*, t.name as tenant_name, a.unit_number 
                    FROM bills b 
                    JOIN tenants t ON b.tenant_id = t.id 
                    JOIN apartments a ON t.apartment_id = a.id 
                    ORDER BY b.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Bills | Tobby’s Suite</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script>
    tailwind.config={theme:{extend:{colors:{"primary":"#1e293b","secondary":"#fbbf24"}}}}
  </script>
</head>
<body class="bg-slate-50 min-h-screen flex">

  <?php include 'includes/sidebar.php'; ?>

  <main class="flex-1 flex flex-col h-screen overflow-y-auto">
    <header class="bg-white border-b border-slate-200 p-6 sticky top-0 z-10 flex justify-between items-center">
      <h2 class="text-xl font-black text-slate-900">Utility Billing</h2>
    </header>

    <div class="p-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <div class="lg:col-span-2 space-y-6">
        <?php if ($success): ?>
          <div class="p-4 bg-green-50 text-green-700 rounded-xl border border-green-100 flex items-center gap-3 text-sm font-bold">
            <span class="material-symbols-outlined">check_circle</span>
            <?= $success ?>
          </div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="p-4 bg-red-50 text-red-700 rounded-xl border border-red-100 flex items-center gap-3 text-sm font-bold">
            <span class="material-symbols-outlined">error</span>
            <?= $error ?>
          </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
              <tr>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Tenant/Unit</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Type</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Amount</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Month</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Status</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
              <?php foreach ($bills as $b): ?>
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="p-4">
                  <p class="font-bold text-slate-900"><?= htmlspecialchars($b['tenant_name']) ?></p>
                  <p class="text-[10px] text-slate-400 uppercase font-black tracking-tighter">Unit <?= htmlspecialchars($b['unit_number']) ?></p>
                </td>
                <td class="p-4">
                  <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-bold"><?= $b['bill_type'] ?></span>
                </td>
                <td class="p-4 font-black">₦<?= number_format($b['amount']) ?></td>
                <td class="p-4 text-slate-500"><?= $b['month_year'] ?></td>
                <td class="p-4">
                  <span class="text-[10px] font-black uppercase <?= $b['status'] === 'Paid' ? 'text-green-600' : 'text-orange-600' ?>">
                    <?= $b['status'] ?>
                  </span>
                </td>
                <td class="p-4">
                  <?php if($b['status'] === 'Unpaid'): ?>
                  <a href="?mark_paid=<?= $b['id'] ?>" class="text-[10px] font-black text-blue-600 hover:underline uppercase">Mark Paid</a>
                  <?php else: ?>
                  <span class="material-symbols-outlined text-green-600 text-sm">check_circle</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm h-fit space-y-6">
        <h3 class="font-black text-slate-900 border-b border-slate-100 pb-4">Generate Bill</h3>
        <form method="POST" class="space-y-4">
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Resident</label>
            <select name="tenant_id" required class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
              <option disabled selected>Select Resident</option>
              <?php foreach ($tenants_list as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['unit_number'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Utility Type</label>
            <select name="bill_type" required class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
              <option>Electricity</option>
              <option>Water</option>
              <option>Service Charge</option>
              <option>Waste Management</option>
              <option>Security</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Amount (₦)</label>
              <input type="number" name="amount" required step="0.01" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900">
            </div>
            <div>
              <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Period</label>
              <input type="text" name="month_year" required placeholder="April 2026" value="<?= date('F Y') ?>" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900">
            </div>
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Due Date</label>
            <input type="date" name="due_date" required class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900">
          </div>
          <button type="submit" name="generate_bill" class="w-full bg-slate-900 text-white py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-800 transition-all shadow-xl shadow-slate-100">
            Generate & Notify
          </button>
        </form>
      </div>

    </div>
  </main>
</body>
</html>
