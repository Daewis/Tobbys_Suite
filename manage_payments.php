<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager', 'accountant'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$error = '';
$success = '';

// Handle Rent Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $tenant_id = (int)$_POST['tenant_id'];
    $amount = (float)$_POST['amount'];
    $method = trim($_POST['method']);
    $month = trim($_POST['month_paid_for']);
    $receipt = 'REC-' . strtoupper(substr(md5(time().$tenant_id), 0, 8));

    try {
        // Get tenant name for logging
        $stmt = $db->prepare("SELECT name FROM tenants WHERE id = ?");
        $stmt->execute([$tenant_id]);
        $t_name = $stmt->fetchColumn();

        $stmt = $db->prepare("INSERT INTO payments (tenant_id, amount, method, month_paid_for, receipt_no) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $amount, $method, $month, $receipt]);
        logActivity("RECORD_PAYMENT", "Tenant: $t_name, Amount: ₦$amount, Month: $month");
        $success = "Payment of ₦" . number_format($amount) . " recorded successfully! Receipt: $receipt";
    } catch (PDOException $e) {
        $error = "Error recording payment: " . $e->getMessage();
    }
}

// Fetch all tenants with their units
$tenants_list = $db->query("SELECT t.id, t.name, a.unit_number, a.rent_amount 
                           FROM tenants t 
                           JOIN apartments a ON t.apartment_id = a.id 
                           WHERE t.status = 'Active' 
                           ORDER BY a.unit_number")->fetchAll();

// Fetch all payments
$payments = $db->query("SELECT p.*, t.name as tenant_name, a.unit_number 
                       FROM payments p 
                       JOIN tenants t ON p.tenant_id = t.id 
                       JOIN apartments a ON t.apartment_id = a.id 
                       ORDER BY p.payment_date DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rent Payments | Tobby’s Suite</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script>
    tailwind.config={theme:{extend:{colors:{"primary":"#1e293b","secondary":"#fbbf24"}}}}
  </script>
</head>
<body class="bg-slate-50 min-h-screen flex">

  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 flex flex-col h-screen overflow-y-auto">
    <header class="bg-white border-b border-slate-200 p-6 flex justify-between items-center sticky top-0 z-10">
      <h2 class="text-xl font-black text-slate-900">Rent & Financials</h2>
    </header>

    <div class="p-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Payments List -->
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
          <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-black text-slate-900">Recent Transactions</h3>
            <span class="text-[10px] font-black text-slate-400 uppercase">Live Records</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Receipt</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Tenant & Unit</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Amount</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Method</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Month</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($payments)): ?>
                <tr>
                  <td colspan="5" class="p-12 text-center text-slate-400 italic text-sm">No payment history found.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($payments as $p): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="p-4">
                    <p class="font-mono text-xs font-bold text-slate-900"><?= $p['receipt_no'] ?></p>
                    <p class="text-[9px] text-slate-400 font-medium"><?= date('M d, Y', strtotime($p['payment_date'])) ?></p>
                  </td>
                  <td class="p-4">
                    <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($p['tenant_name']) ?></p>
                    <p class="text-[10px] font-black text-yellow-600 uppercase">Unit <?= htmlspecialchars($p['unit_number']) ?></p>
                  </td>
                  <td class="p-4 font-black text-slate-900 text-sm">₦<?= number_format($p['amount']) ?></td>
                  <td class="p-4 text-slate-600 text-xs font-medium"><?= htmlspecialchars($p['method']) ?></td>
                  <td class="p-4 text-slate-400 text-xs font-bold uppercase tracking-widest"><?= htmlspecialchars($p['month_paid_for']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Add Payment Form -->
      <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm h-fit space-y-6">
        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
          <span class="material-symbols-outlined text-green-600">add_card</span>
          <h3 class="font-black text-slate-900">Record Rent</h3>
        </div>
        <form method="POST" class="space-y-4">
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Select Tenant</label>
            <select name="tenant_id" required onchange="updateSuggestedAmount(this)"
              class="w-full p-4 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900 appearance-none cursor-pointer">
              <option disabled selected>-- Select Resident --</option>
              <?php foreach ($tenants_list as $t): ?>
                <option value="<?= $t['id'] ?>" data-rent="<?= $t['rent_amount'] ?>">
                  <?= htmlspecialchars($t['name']) ?> (Unit <?= htmlspecialchars($t['unit_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Amount Paid (₦)</label>
            <input type="number" name="amount" id="amount_input" required placeholder="0.00"
              class="w-full p-4 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Method</label>
              <select name="method" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
                <option>Bank Transfer</option>
                <option>Cash</option>
                <option>POS</option>
                <option>Cheque</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Month</label>
              <select name="month_paid_for" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
                <?php
                $months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                foreach ($months as $m) {
                    $selected = ($m == date('F')) ? 'selected' : '';
                    echo "<option $selected>$m " . date('Y') . "</option>";
                }
                ?>
              </select>
            </div>
          </div>
          <button type="submit" name="record_payment"
            class="w-full bg-slate-900 text-white py-4 rounded-xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 hover:bg-slate-800 transition-all shadow-xl shadow-slate-100 mt-2">
            Generate Receipt
            <span class="material-symbols-outlined text-sm">receipt_long</span>
          </button>
        </form>
      </div>

    </div>
  </main>

  <script>
    function updateSuggestedAmount(select) {
      const option = select.options[select.selectedIndex];
      const rent = option.getAttribute('data-rent');
      document.getElementById('amount_input').value = rent;
    }
  </script>
</body>
</html>
