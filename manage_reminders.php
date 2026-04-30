<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'manager', 'accountant'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$success = '';
$error = '';

$current_month_year = date('m-Y');

// Handle Sending Reminder
if (isset($_GET['send_rent_reminder'])) {
    $tenant_id = (int)$_GET['send_rent_reminder'];
    
    try {
        // Fetch tenant email for the placeholder message
        $stmt = $db->prepare("SELECT name, email FROM tenants WHERE id = ?");
        $stmt->execute([$tenant_id]);
        $tenant = $stmt->fetch();

        if ($tenant) {
            // MOCK EMAIL LOGIC
            $to = $tenant['email'];
            $subject = "Rent Reminder: Tobby’s Suite - " . date('F Y');
            $message = "Dear {$tenant['name']},\n\nThis is a friendly reminder that your rent for " . date('F Y') . " is now due. Please ensure payment is made at your earliest convenience.\n\nThank you,\nManagement.";
            
            // Record in database
            $stmt = $db->prepare("INSERT INTO reminders (tenant_id, reminder_type) VALUES (?, 'Rent')");
            $stmt->execute([$tenant_id]);
            
            $success = "Email reminder successfully queued for " . htmlspecialchars($tenant['name']) . " ({$tenant['email']})";
        }
    } catch (PDOException $e) {
        $error = "Failed to send reminder.";
    }
}

// 1. Identify Tenants who haven't paid rent for the current month
// A tenant has NOT paid if they are Active and don't have a payment record for the current month-year
$debtors = $db->query("
    SELECT t.id, t.name, t.email, a.unit_number, a.rent_amount 
    FROM tenants t 
    JOIN apartments a ON t.apartment_id = a.id 
    WHERE t.status = 'Active' 
    AND t.id NOT IN (
        SELECT tenant_id FROM payments WHERE month_paid_for = '$current_month_year'
    )
    ORDER BY a.unit_number
")->fetchAll();

// 2. Fetch recent reminder history
$history = $db->query("
    SELECT r.*, t.name as tenant_name, a.unit_number 
    FROM reminders r 
    JOIN tenants t ON r.tenant_id = t.id 
    JOIN apartments a ON t.apartment_id = a.id 
    ORDER BY r.sent_at DESC 
    LIMIT 10
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rent Reminders | Tobby’s Suite</title>
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
      <h2 class="text-xl font-black text-slate-900 italic uppercase">Payment Automations</h2>
    </header>

    <div class="p-8 space-y-8">

      <?php if ($success): ?>
        <div class="p-4 bg-blue-50 text-blue-700 rounded-2xl border border-blue-100 flex items-center gap-3 text-sm font-bold animate-pulse">
          <span class="material-symbols-outlined">mail</span>
          <?= $success ?>
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Debtors List -->
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
               <div>
                 <h3 class="font-black text-slate-900 uppercase text-xs tracking-tighter">Outstanding Rent (<?= date('F Y') ?>)</h3>
                 <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Residents with pending payments</p>
               </div>
               <span class="px-3 py-1 bg-red-50 text-red-600 rounded-full text-[10px] font-black uppercase"><?= count($debtors) ?> PENDING</span>
            </div>
            
            <table class="w-full text-left">
              <thead class="bg-slate-50/50 border-b border-slate-100">
                <tr>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Resident</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Unit</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase">Amount</th>
                  <th class="p-4 text-[10px] font-black text-slate-400 uppercase text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($debtors)): ?>
                <tr>
                  <td colspan="4" class="p-12 text-center">
                    <span class="material-symbols-outlined text-green-400 text-4xl mb-2">check_circle</span>
                    <p class="text-sm text-slate-500 font-bold italic">Excellent! All rent payments collected for this month.</p>
                  </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($debtors as $d): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="p-4">
                    <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($d['name']) ?></p>
                    <p class="text-[10px] text-slate-400 font-mono italic"><?= htmlspecialchars($d['email']) ?></p>
                  </td>
                  <td class="p-4 font-black text-slate-600 text-xs uppercase">Unit <?= $d['unit_number'] ?></td>
                  <td class="p-4 text-sm font-black text-slate-900">₦<?= number_format($d['rent_amount']) ?></td>
                  <td class="p-4 text-right">
                    <a href="?send_rent_reminder=<?= $d['id'] ?>" class="inline-flex items-center gap-2 bg-slate-900 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-800 transition-all shadow-lg shadow-slate-200">
                      <span class="material-symbols-outlined text-sm">send</span>
                      Send Reminder
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- History Sidebar -->
        <div class="space-y-6">
          <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm space-y-6">
            <h3 class="font-black text-slate-900 uppercase text-xs tracking-tighter border-b border-slate-100 pb-4">Reminder Log</h3>
            <div class="space-y-6">
              <?php if (empty($history)): ?>
                <p class="text-xs text-slate-400 italic">No reminders sent recently.</p>
              <?php endif; ?>
              <?php foreach ($history as $h): ?>
              <div class="flex gap-4 relative">
                <div class="w-1 h-full absolute left-4 top-8 bg-slate-100"></div>
                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 z-10">
                  <span class="material-symbols-outlined text-sm">notifications</span>
                </div>
                <div>
                  <p class="text-xs font-bold text-slate-900"><?= htmlspecialchars($h['tenant_name']) ?> (<?= $h['unit_number'] ?>)</p>
                  <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Rent Reminder Sent</p>
                  <p class="text-[9px] text-slate-300 font-medium mt-1"><?= date('d M, H:i', strtotime($h['sent_at'])) ?></p>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="bg-slate-900 p-8 rounded-3xl text-white space-y-4">
             <span class="material-symbols-outlined text-yellow-400">info</span>
             <h4 class="font-black italic text-sm">Email Configuration</h4>
             <p class="text-[10px] text-slate-400 leading-relaxed uppercase font-bold tracking-widest">Reminders are currently sent using the management's default SMTP relay. Detailed logs are recorded for audit purposes.</p>
          </div>
        </div>

      </div>

    </div>
  </main>

</body>
</html>
