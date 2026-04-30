<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$error = '';
$success = '';

// Handle Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = (int)$_POST['complaint_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $db->prepare("UPDATE complaints SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$status, $id]);
        $success = "Request status updated to $status!";
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch all complaints with tenant and apartment info
$complaints = $db->query("SELECT c.*, t.name as tenant_name, a.unit_number 
                         FROM complaints c 
                         JOIN tenants t ON c.tenant_id = t.id 
                         JOIN apartments a ON c.apartment_id = a.id 
                         ORDER BY c.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maintenance Queue | Tobby’s Suite</title>
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
      <h2 class="text-xl font-black text-slate-900">Maintenance Queue</h2>
    </header>

    <div class="p-8 space-y-6">
      <?php if ($success): ?>
        <div class="p-4 bg-green-50 text-green-700 rounded-xl border border-green-100 flex items-center gap-3 text-sm font-bold">
          <span class="material-symbols-outlined">check_circle</span>
          <?= $success ?>
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($complaints as $c): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4 hover:shadow-md transition-shadow">
          <div class="flex justify-between items-start">
            <span class="px-2 py-1 rounded-full text-[10px] font-black uppercase <?= $c['status'] === 'Pending' ? 'bg-orange-50 text-orange-600' : ($c['status'] === 'In Progress' ? 'bg-blue-50 text-blue-600' : 'bg-green-50 text-green-600') ?>">
              <?= $c['status'] ?>
            </span>
            <p class="text-[10px] font-mono text-slate-400"><?= date('M d, H:i', strtotime($c['created_at'])) ?></p>
          </div>
          <div>
            <h3 class="font-black text-slate-900 text-base leading-tight"><?= htmlspecialchars($c['subject']) ?></h3>
            <p class="text-[10px] uppercase font-bold text-slate-400 mt-1">
              Unit <?= htmlspecialchars($c['unit_number']) ?> • <?= htmlspecialchars($c['tenant_name']) ?>
            </p>
          </div>
          <p class="text-xs text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-xl">
            <?= nl2br(htmlspecialchars($c['description'])) ?>
          </p>
          
          <form method="POST" class="flex gap-2">
            <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
            <select name="status" class="flex-1 p-2 bg-slate-50 border border-slate-100 rounded-xl text-[10px] font-black uppercase outline-none focus:ring-1 focus:ring-slate-900">
              <option value="Pending" <?= $c['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
              <option value="In Progress" <?= $c['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="Resolved" <?= $c['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
            <button type="submit" name="update_status" class="bg-slate-900 text-white p-2 rounded-xl hover:bg-slate-800 transition-all">
              <span class="material-symbols-outlined text-sm">save</span>
            </button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php if (empty($complaints)): ?>
          <div class="col-span-full p-12 text-center bg-white rounded-2xl border border-dashed border-slate-200">
            <span class="material-symbols-outlined text-4xl text-slate-200 mb-2">engineering</span>
            <p class="text-slate-400 text-sm italic">No maintenance requests yet.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
