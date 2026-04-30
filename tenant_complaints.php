<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'tenant') {
    header('Location: login.php');
    exit;
}

$db      = getDB();
$user_id = $_SESSION['user_id'];
$active_page = 'complaints';

$success = '';
$error   = '';

// Fetch tenant
$stmt = $db->prepare("SELECT t.*, a.unit_number, a.floor, a.type, a.rent_amount
                       FROM tenants t
                       JOIN apartments a ON t.apartment_id = a.id
                       WHERE t.user_id = ? AND t.status = 'Active'");
$stmt->execute([$user_id]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: tenant_dashboard.php'); exit; }

// Handle complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $subject = trim($_POST['subject']);
    $desc    = trim($_POST['description']);
    try {
        $stmt = $db->prepare("INSERT INTO complaints (tenant_id, apartment_id, subject, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tenant['id'], $tenant['apartment_id'], $subject, $desc]);
        $success = "Your request has been submitted successfully!";
    } catch (PDOException $e) {
        $error = "Error submitting request: " . $e->getMessage();
    }
}

// Fetch all complaints
$stmt = $db->prepare("SELECT * FROM complaints WHERE tenant_id = ? ORDER BY created_at DESC");
$stmt->execute([$tenant['id']]);
$complaints = $stmt->fetchAll();

$pending  = array_filter($complaints, fn($c) => $c['status'] === 'Pending');
$resolved = array_filter($complaints, fn($c) => $c['status'] !== 'Pending');

$page_title = "Maintenance | Tobby's Suite";
include __DIR__ . '/includes/tenant_head.php';
?>

<div class="flex">
  <?php include __DIR__ . '/includes/tenant_sidebar.php'; ?>

  <main class="flex-1 min-h-screen">
    <nav class="bg-white border-b border-slate-200 px-8 py-5 flex justify-between items-center sticky top-0 z-10">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Maintenance & Support</h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Unit <?= $tenant['unit_number'] ?> • Submit & Track Requests</p>
      </div>
      <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-black">
        <?= substr($tenant['name'], 0, 1) ?>
      </div>
    </nav>

    <div class="p-6 lg:p-8 space-y-8">

      <!-- Stats Row -->
      <div class="grid grid-cols-3 gap-4">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm text-center">
          <p class="text-2xl font-black text-slate-900"><?= count($complaints) ?></p>
          <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1">Total Tickets</p>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm text-center">
          <p class="text-2xl font-black text-orange-500"><?= count($pending) ?></p>
          <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1">Open</p>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm text-center">
          <p class="text-2xl font-black text-green-600"><?= count($resolved) ?></p>
          <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1">Resolved</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- Submit Form -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8 space-y-6">
          <div class="flex justify-between items-center">
            <h3 class="font-black text-slate-900 uppercase text-sm tracking-tighter italic">New Request</h3>
            <span class="material-symbols-outlined text-slate-300">add_circle</span>
          </div>

          <?php if ($success): ?>
            <div class="p-4 bg-green-50 text-green-700 rounded-2xl border border-green-100 text-xs font-bold flex items-center gap-2">
              <span class="material-symbols-outlined text-sm">check_circle</span>
              <?= htmlspecialchars($success) ?>
            </div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="p-4 bg-red-50 text-red-600 rounded-2xl border border-red-100 text-xs font-bold"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="POST" class="space-y-5">
            <div>
              <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Subject</label>
              <input type="text" name="subject" required placeholder="e.g. Broken AC, Leaking Tap"
                     class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl text-sm focus:ring-2 focus:ring-slate-900 focus:border-transparent outline-none transition-all">
            </div>
            <div>
              <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Describe the Issue</label>
              <textarea name="description" rows="5" required placeholder="Provide details — what's broken, when it started, urgency level..."
                        class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl text-sm focus:ring-2 focus:ring-slate-900 focus:border-transparent outline-none transition-all resize-none"></textarea>
            </div>
            <button type="submit" name="submit_complaint"
                    class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 hover:bg-slate-800 transition-all shadow-xl shadow-slate-100 active:scale-95">
              Submit Request
              <span class="material-symbols-outlined text-sm">send</span>
            </button>
          </form>
        </div>

        <!-- Ticket History -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
          <div class="p-8 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-black text-slate-900 uppercase text-sm tracking-tighter italic">My Tickets</h3>
            <span class="material-symbols-outlined text-slate-300">engineering</span>
          </div>

          <?php if (empty($complaints)): ?>
            <div class="p-12 text-center text-slate-300">
              <span class="material-symbols-outlined text-4xl mb-3 block">handyman</span>
              <p class="text-[10px] font-black uppercase tracking-widest italic">No tickets yet</p>
            </div>
          <?php else: ?>
            <div class="flex-1 overflow-y-auto divide-y divide-slate-50 max-h-[500px]">
              <?php foreach ($complaints as $c): ?>
                <?php
                  $status_style = match($c['status']) {
                    'Pending'     => 'bg-orange-50 text-orange-500',
                    'In Progress' => 'bg-blue-50 text-blue-600',
                    'Resolved'    => 'bg-green-50 text-green-600',
                    default       => 'bg-slate-50 text-slate-500'
                  };
                ?>
                <div class="p-6 hover:bg-slate-50 transition-colors">
                  <div class="flex justify-between items-start mb-2">
                    <p class="text-sm font-bold text-slate-900 pr-4"><?= htmlspecialchars($c['subject']) ?></p>
                    <span class="shrink-0 text-[8px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg <?= $status_style ?>">
                      <?= $c['status'] ?>
                    </span>
                  </div>
                  <p class="text-[10px] text-slate-500 leading-relaxed line-clamp-2"><?= htmlspecialchars($c['description']) ?></p>
                  <p class="text-[9px] text-slate-300 font-mono mt-2"><?= date('d M Y, g:ia', strtotime($c['created_at'])) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </main>
</div>
</body>
</html>