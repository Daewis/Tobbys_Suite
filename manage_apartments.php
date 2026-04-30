<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_apartment'])) {
  $unit      = trim($_POST['unit_number']);
  $floor     = trim($_POST['floor']);
  $type      = trim($_POST['type']);
  $rent      = (float)$_POST['rent_amount'];
  
  $quarterly = !empty($_POST['rent_quarterly']) ? (float)$_POST['rent_quarterly'] : 0;
  $yearly    = !empty($_POST['rent_yearly']) ? (float)$_POST['rent_yearly'] : 0;

  try {
      // Updated SQL to include new columns
      $sql = "INSERT INTO apartments (unit_number, floor, type, rent_amount, rent_quarterly, rent_yearly, status) 
              VALUES (?, ?, ?, ?, ?, ?, 'Vacant')";   
      $stmt = $db->prepare($sql);
      $stmt->execute([$unit, $floor, $type, $rent, $quarterly, $yearly]);
      
      logActivity("ADD_APARTMENT", "Unit: $unit, Type: $type, Monthly: $rent, Yearly: $yearly");
      $success = "Apartment $unit added successfully!";
      
      // Optional: Redirect to clear post data and show success on dashboard
      // header("Location: apartments.php?success=1");
      // exit;
  } catch (PDOException $e) {
      // If there's a duplicate unit number, MySQL will throw an error
      if ($e->getCode() == 23000) {
          $error = "Error: Unit number $unit already exists.";
      } else {
          $error = "Error adding apartment: " . $e->getMessage();
      }
  }
}

// Fetch all for display
$apartments = $db->query("SELECT * FROM apartments ORDER BY unit_number")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Apartments | Tobby’s Suite</title>
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
      <h2 class="text-xl font-black text-slate-900">Apartment Management</h2>
      <a href="admin_dashboard.php" class="text-slate-400 hover:text-slate-900">
        <span class="material-symbols-outlined">close</span>
      </a>
    </header>

    <div class="p-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- List -->
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
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Unit</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Floor</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Type</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Rent (₦)</th>
                <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($apartments as $apt): ?>
              <tr>
                <td class="p-4 font-bold text-slate-900"><?= htmlspecialchars($apt['unit_number']) ?></td>
                <td class="p-4 text-slate-600 text-sm"><?= htmlspecialchars($apt['floor']) ?></td>
                <td class="p-4 text-slate-600 text-sm"><?= htmlspecialchars($apt['type']) ?></td>
                <td class="p-4 font-bold text-slate-900"><?= number_format($apt['rent_amount']) ?></td>
                <td class="p-4">
                  <span class="text-[10px] font-black uppercase <?= $apt['status'] === 'Vacant' ? 'text-green-600' : 'text-blue-600' ?>">
                    <?= $apt['status'] ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Form -->
      <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm h-fit space-y-6">
        <h3 class="font-black text-slate-900 border-b border-slate-100 pb-4">Add New Unit</h3>
        <form method="POST" class="space-y-4">
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Unit Number</label>
            <input type="text" name="unit_number" required placeholder="e.g. 101, 2B"
              class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900">
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Floor</label>
            <input type="text" name="floor" placeholder="e.g. Ground Floor, 2nd Floor"
              class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900">
          </div>
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Apartment Type</label>
            <select name="type" required
              class="w-full p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-slate-900">
              <option>Studio</option>
              <option>1-Bedroom</option>
              <option>2-Bedroom</option>
              <option>3-Bedroom</option>
              <option>Penthouse</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Monthly Rent (₦)</label>
            <input type="number" name="rent_amount" placeholder="50000" required class="w-full border-slate-100 rounded-xl bg-slate-50 text-sm">
          </div>
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Quarterly Rent (₦)</label>
            <input type="number" name="rent_quarterly" class="w-full border-slate-100 rounded-xl bg-slate-50 text-sm" placeholder="200000">
          </div>
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Yearly Rent (₦)</label>
            <input type="number" name="rent_yearly" class="w-full border-slate-100 rounded-xl bg-slate-50 text-sm" placeholder="800000">
          </div>
          <button type="submit" name="add_apartment"
            class="w-full bg-slate-900 text-white py-4 rounded-xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 hover:bg-slate-800 transition-all shadow-xl shadow-slate-100">
            Create Apartment
            <span class="material-symbols-outlined text-sm">add_circle</span>
          </button>
        </form>
      </div>

    </div>
  </main>
</body>
</html>
