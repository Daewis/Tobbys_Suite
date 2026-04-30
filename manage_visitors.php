<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager', 'security'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$error = '';
$success = '';

// Handle Visitor Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_visitor'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $purpose = trim($_POST['purpose']);
    $tenant_id = !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;

    try {
        $stmt = $db->prepare("INSERT INTO visitors (name, phone, purpose, tenant_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $purpose, $tenant_id]);
        logActivity("VISITOR_CHECKIN", "Visitor: $name, Purpose: $purpose");
        $success = "Visitor $name checked in successfully!";
    } catch (PDOException $e) {
        $error = "Check-in failed: " . $e->getMessage();
    }
}

// Handle Check-out
if (isset($_GET['check_out'])) {
    $visitor_id = (int)$_GET['check_out'];
    try {
        $stmt = $db->prepare("SELECT name FROM visitors WHERE id = ?");
        $stmt->execute([$visitor_id]);
        $v = $stmt->fetch();
        
        $stmt = $db->prepare("UPDATE visitors SET status = 'Checked Out', check_out = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$visitor_id]);
        logActivity("VISITOR_CHECKOUT", "Visitor: {$v['name']}");
        $success = "Visitor checked out.";
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch active tenants for the "Visiting" dropdown
$tenants = $db->query("SELECT t.id, t.name, a.unit_number FROM tenants t JOIN apartments a ON t.apartment_id = a.id WHERE t.status = 'Active'")->fetchAll();

// Fetch daily visitors
$visitors = $db->query("SELECT v.*, t.name as tenant_name, a.unit_number 
                        FROM visitors v 
                        LEFT JOIN tenants t ON v.tenant_id = t.id 
                        LEFT JOIN apartments a ON t.apartment_id = a.id 
                        ORDER BY v.check_in DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Visitor Log | Tobby’s Suite</title>
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
      <h2 class="text-xl font-black text-slate-900">Security & Visitors</h2>
    </header>

    <div class="p-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Visitor List -->
      <div class="lg:col-span-2 space-y-6">
        <?php if ($success): ?>
          <div class="p-4 bg-green-50 text-green-700 rounded-xl border border-green-100 flex items-center gap-3 text-sm font-bold">
            <span class="material-symbols-outlined text-sm">security</span>
            <?= $success ?>
          </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
              <tr>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Visitor</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Visiting</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">In/Out</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Status</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
              <?php foreach ($visitors as $v): ?>
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="p-4">
                  <p class="font-bold text-slate-900"><?= htmlspecialchars($v['name']) ?></p>
                  <p class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($v['phone']) ?></p>
                </td>
                <td class="p-4">
                  <?php if ($v['tenant_name']): ?>
                    <p class="font-medium text-slate-700"><?= htmlspecialchars($v['tenant_name']) ?></p>
                    <p class="text-[10px] text-yellow-600 font-black uppercase">Unit <?= $v['unit_number'] ?></p>
                  <?php else: ?>
                    <span class="text-slate-400 italic">General Visit</span>
                  <?php endif; ?>
                </td>
                <td class="p-4">
                  <p class="text-[10px] font-bold text-slate-900"><?= date('H:i', strtotime($v['check_in'])) ?></p>
                  <p class="text-[9px] text-slate-400"><?= $v['check_out'] ? date('H:i', strtotime($v['check_out'])) : '--:--' ?></p>
                </td>
                <td class="p-4">
                  <span class="text-[10px] font-black uppercase <?= $v['status'] === 'Checked In' ? 'text-green-600' : 'text-slate-400' ?>">
                    <?= $v['status'] ?>
                  </span>
                </td>
                <td class="p-4">
                  <?php if ($v['status'] === 'Checked In'): ?>
                    <a href="?check_out=<?= $v['id'] ?>" class="text-[10px] font-black text-red-600 hover:bg-red-50 px-2 py-1 rounded-lg border border-red-100 uppercase">Check Out</a>
                  <?php else: ?>
                    <span class="material-symbols-outlined text-slate-300 text-sm">done_all</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Visitor Form -->
      <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm h-fit space-y-6">
        <h3 class="font-black text-slate-900 border-b border-slate-100 pb-4 uppercase tracking-tighter">New Entry</h3>
        <form method="POST" class="space-y-4">
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Visitor Name</label>
            <input type="text" name="name" required placeholder="Guest Full Name" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900">
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Phone Number</label>
            <input type="text" name="phone" required placeholder="080 ..." class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900">
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Visiting Who?</label>
            <select name="tenant_id" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
              <option value="">General (Management/Repair)</option>
              <?php foreach ($tenants as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['unit_number'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Purpose of Visit</label>
            <textarea name="purpose" rows="2" placeholder="e.g. Delivery, Family Visit" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900"></textarea>
          </div>
          <button type="submit" name="log_visitor" class="w-full bg-slate-900 text-white py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-800 transition-all shadow-xl shadow-slate-100">
            Log Entry
          </button>
        </form>
      </div>

    </div>
  </main>
</body>
</html>
