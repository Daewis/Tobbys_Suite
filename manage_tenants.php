<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$error = '';
$success = '';

// Handle addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tenant'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $apt_id = (int)$_POST['apartment_id'];

    $db->beginTransaction();
    try {
        // Create user account for tenant
        $pass = password_hash('tenant123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, 'tenant')");
        $stmt->execute([$email, $pass, $name]);
        $user_id = $db->lastInsertId();

        // Create tenant profile
        $stmt = $db->prepare("INSERT INTO tenants (user_id, apartment_id, name, phone, email, move_in_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
        $stmt->execute([$user_id, $apt_id, $name, $phone, $email]);

        // Update apartment status
        $stmt = $db->prepare("UPDATE apartments SET status = 'Occupied' WHERE id = ?");
        $stmt->execute([$apt_id]);

        logActivity("ONBOARD_TENANT", "Name: $name, Apt ID: $apt_id");
        $db->commit();
        $success = "Tenant $name added and assigned to unit!";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error adding tenant: " . $e->getMessage();
    }
}

// Fetch vacant apartments for assignment
$vacant_apts = $db->query("SELECT * FROM apartments WHERE status = 'Vacant'")->fetchAll();

// Fetch all active tenants
$tenants = $db->query("SELECT t.*, a.unit_number FROM tenants t LEFT JOIN apartments a ON t.apartment_id = a.id WHERE t.status = 'Active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Tenants | Tobby’s Suite</title>
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
      <h2 class="text-xl font-black text-slate-900">Tenant Management</h2>
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
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Tenant Name</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Contact</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Apartment</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Move In</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($tenants as $t): ?>
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="p-4 font-bold text-slate-900 text-sm"><?= htmlspecialchars($t['name']) ?></td>
                <td class="p-4">
                  <p class="text-xs text-slate-900 font-medium"><?= htmlspecialchars($t['phone']) ?></p>
                  <p class="text-[10px] text-slate-400 italic"><?= htmlspecialchars($t['email']) ?></p>
                </td>
                <td class="p-4 font-black text-slate-900 text-sm"><?= htmlspecialchars($t['unit_number']) ?></td>
                <td class="p-4 text-slate-500 text-xs"><?= $t['move_in_date'] ?></td>
                <td class="p-4">
                  <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-green-50 text-green-700">
                    <?= $t['status'] ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm h-fit space-y-6">
        <h3 class="font-black text-slate-900 border-b border-slate-100 pb-4">Onboard Tenant</h3>
        <form method="POST" class="space-y-4">
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Full Name</label>
            <input type="text" name="name" required placeholder="John Doe"
              class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Phone Number</label>
            <input type="text" name="phone" required placeholder="080 123 4567"
              class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
            <input type="email" name="email" required placeholder="john@email.com"
              class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900">
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Assign to Apartment</label>
            <select name="apartment_id" required
              class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:ring-2 focus:ring-slate-900 appearance-none cursor-pointer">
              <option disabled selected>Select Vacant Unit</option>
              <?php foreach ($vacant_apts as $apt): ?>
                <option value="<?= $apt['id'] ?>">Unit <?= htmlspecialchars($apt['unit_number']) ?> (₦<?= number_format($apt['rent_amount']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <p class="text-[10px] text-slate-400 mt-1 italic">Only vacant units are shown.</p>
          </div>
          <button type="submit" name="add_tenant"
            class="w-full bg-slate-900 text-white py-4 rounded-xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 hover:bg-slate-800 transition-all shadow-xl shadow-slate-100">
            Finalize Tenancy
            <span class="material-symbols-outlined text-sm">person_add</span>
          </button>
        </form>
      </div>

    </div>
  </main>
</body>
</html>
