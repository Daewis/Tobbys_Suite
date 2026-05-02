<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'tenant') {
    header('Location: login.php');
    exit;
}

$db       = getDB();
$user_id  = $_SESSION['user_id'];
$active_page = 'overview';

$success = '';
if (isset($_GET['success'])) {
    $success = $_GET['success'] === 'payment_received'
        ? 'Payment successful! Your rent record has been updated.'
        : $_GET['success'];
}

// Fetch tenant + apartment
$stmt = $db->prepare("SELECT t.*, a.unit_number, a.floor, a.type, a.rent_amount, a.rent_quarterly, a.rent_yearly
                       FROM tenants t
                       JOIN apartments a ON t.apartment_id = a.id
                       WHERE t.user_id = ? AND t.status = 'Active'");
$stmt->execute([$user_id]);
$tenant = $stmt->fetch();
if (!$tenant) die("Access Denied: You are not assigned to an active unit.");

// Quick stats
$stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE tenant_id = ? AND status = 'Paid'");
$stmt->execute([$tenant['id']]);
$total_paid_count = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT SUM(amount) FROM payments WHERE tenant_id = ?");
$stmt->execute([$tenant['id']]);
$total_paid_amount = $stmt->fetchColumn() ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) FROM bills WHERE tenant_id = ? AND status = 'Unpaid'");
$stmt->execute([$tenant['id']]);
$unpaid_bills_count = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM complaints WHERE tenant_id = ? AND status = 'Pending'");
$stmt->execute([$tenant['id']]);
$open_tickets = $stmt->fetchColumn();

// Last payment
$stmt = $db->prepare("SELECT * FROM payments WHERE tenant_id = ? ORDER BY payment_date DESC LIMIT 1");
$stmt->execute([$tenant['id']]);
$last_payment = $stmt->fetch();

$page_title = "Overview | Tobby's Suite";
include __DIR__ . '/includes/tenant_head.php';
?>

<div class="flex">
  <?php include __DIR__ . '/includes/tenant_sidebar.php'; ?>

  <main class="flex-1 min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white border-b border-slate-200 px-8 py-5 flex justify-between items-center sticky top-0 lg:top-0 z-10">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Welcome Home, <?= explode(' ', $tenant['name'])[0] ?></h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Unit <?= $tenant['unit_number'] ?> • <?= $tenant['type'] ?></p>
      </div>
      <div class="flex items-center gap-4">
        <div class="hidden sm:block text-right">
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Lease Status</p>
          <p class="text-xs font-bold text-green-600">Active</p>
        </div>
        <button class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600">
          <span class="material-symbols-outlined">notifications</span>
        </button>
        <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-black">
          <?= substr($tenant['name'], 0, 1) ?>
        </div>
        <div class="flex items-center gap-4">
      </div>
    </nav>

    <div class="p-6 lg:p-8 space-y-8">

      <?php if ($success): ?>
        <div class="p-4 bg-green-50 text-green-700 rounded-2xl border border-green-100 text-xs font-bold flex items-center gap-2">
          <span class="material-symbols-outlined text-green-500 text-sm">check_circle</span>
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <!-- 3 Main Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- My Apartment -->
        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <span class="material-symbols-outlined p-3 bg-blue-50 text-blue-600 rounded-2xl mb-4 block w-fit">apartment</span>
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">My Apartment</h3>
            <p class="text-2xl font-black text-slate-900">Unit <?= $tenant['unit_number'] ?></p>
          </div>
          <p class="text-xs text-slate-500 mt-4"><?= $tenant['floor'] ?> • <?= $tenant['type'] ?></p>
        </div>

        <!-- Rent Options -->
        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <span class="material-symbols-outlined p-3 bg-green-50 text-green-600 rounded-2xl mb-4 block w-fit">payments</span>
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Rent Options</h3>
            <p class="text-2xl font-black text-slate-900 font-mono tracking-tighter">
              ₦<?= number_format($tenant['rent_amount']) ?>
              <span class="text-[10px] font-black uppercase text-slate-400">/ mo</span>
            </p>
            <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
              <?php if (!empty($tenant['rent_quarterly'])): ?>
                <div class="flex justify-between items-center">
                  <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Quarterly</span>
                  <span class="text-xs font-bold text-slate-600">₦<?= number_format($tenant['rent_quarterly']) ?></span>
                </div>
              <?php endif; ?>
              <?php if (!empty($tenant['rent_yearly'])): ?>
                <div class="flex justify-between items-center">
                  <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Yearly</span>
                  <span class="text-xs font-bold text-slate-900">₦<?= number_format($tenant['rent_yearly']) ?></span>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <p class="text-xs text-slate-500 mt-4">Due on the 1st of every month</p>
        </div>

        <!-- Next Payment / Pay -->
        <div class="bg-slate-900 p-8 rounded-3xl border-none shadow-sm flex flex-col justify-between group transition-all hover:bg-slate-800">
          <div>
            <span class="material-symbols-outlined p-3 bg-white/10 text-yellow-400 rounded-2xl mb-4 block w-fit">verified</span>
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Next Payment</h3>
            <p class="text-2xl font-serif-italic text-white"><?= date('F t', strtotime('now')) ?></p>
          </div>
          <button onclick="payWithPaystack()"
                  class="w-full text-center mt-6 bg-yellow-400 text-slate-900 py-3 rounded-2xl font-black text-xs uppercase tracking-widest transition-all hover:scale-[1.02] active:scale-95 shadow-xl shadow-yellow-900/20">
            Pay Online
          </button>
        </div>
      </div>

      <!-- Quick Stats Row -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
          <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Payments Made</p>
          <p class="text-2xl font-black text-slate-900"><?= $total_paid_count ?></p>
          <p class="text-[9px] text-slate-400 mt-1">All time</p>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
          <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Paid</p>
          <p class="text-xl font-black text-slate-900 font-mono">₦<?= number_format($total_paid_amount) ?></p>
          <p class="text-[9px] text-slate-400 mt-1">Lifetime</p>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
          <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Unpaid Bills</p>
          <p class="text-2xl font-black <?= $unpaid_bills_count > 0 ? 'text-orange-500' : 'text-green-600' ?>">
            <?= $unpaid_bills_count ?>
          </p>
          <p class="text-[9px] text-slate-400 mt-1"><?= $unpaid_bills_count > 0 ? 'Needs attention' : 'All clear' ?></p>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
          <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Open Tickets</p>
          <p class="text-2xl font-black <?= $open_tickets > 0 ? 'text-orange-500' : 'text-slate-900' ?>">
            <?= $open_tickets ?>
          </p>
          <p class="text-[9px] text-slate-400 mt-1">Maintenance</p>
        </div>
      </div>

      <!-- Last Payment + Quick Links -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Last Payment -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8">
          <div class="flex justify-between items-center mb-6">
            <h3 class="font-black text-slate-900 uppercase text-sm tracking-tighter italic">Last Payment</h3>
            <a href="tenant_payments.php" class="text-[9px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-900 transition-colors flex items-center gap-1">
              View All <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </a>
          </div>
          <?php if ($last_payment): ?>
            <div class="flex items-center justify-between p-5 bg-slate-50 rounded-2xl border border-slate-100">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center">
                  <span class="material-symbols-outlined">receipt</span>
                </div>
                <div>
                  <p class="text-sm font-black text-slate-900"><?= $last_payment['month_paid_for'] ?></p>
                  <p class="text-[9px] font-mono text-slate-400 uppercase"><?= $last_payment['receipt_no'] ?></p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-black text-slate-900">₦<?= number_format($last_payment['amount']) ?></p>
                <p class="text-[9px] font-black text-green-600 uppercase tracking-widest">Paid</p>
              </div>
            </div>
          <?php else: ?>
            <p class="text-xs text-slate-400 italic text-center py-6">No payment records yet.</p>
          <?php endif; ?>
        </div>

        <!-- Quick Links -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8">
          <h3 class="font-black text-slate-900 uppercase text-sm tracking-tighter italic mb-6">Quick Actions</h3>
          <div class="grid grid-cols-2 gap-3">
            <a href="tenant_payments.php"
               class="flex flex-col items-center gap-2 p-4 bg-slate-50 rounded-2xl border border-slate-100 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition-all group">
              <span class="material-symbols-outlined text-slate-400 group-hover:text-yellow-400 transition-colors">payments</span>
              <span class="text-[9px] font-black uppercase tracking-widest text-slate-600 group-hover:text-white transition-colors">Payments</span>
            </a>
            <a href="tenant_documents.php"
               class="flex flex-col items-center gap-2 p-4 bg-slate-50 rounded-2xl border border-slate-100 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition-all group">
              <span class="material-symbols-outlined text-slate-400 group-hover:text-yellow-400 transition-colors">folder_shared</span>
              <span class="text-[9px] font-black uppercase tracking-widest text-slate-600 group-hover:text-white transition-colors">Documents</span>
            </a>
            <a href="tenant_complaints.php"
               class="flex flex-col items-center gap-2 p-4 bg-slate-50 rounded-2xl border border-slate-100 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition-all group">
              <span class="material-symbols-outlined text-slate-400 group-hover:text-yellow-400 transition-colors">engineering</span>
              <span class="text-[9px] font-black uppercase tracking-widest text-slate-600 group-hover:text-white transition-colors">Maintenance</span>
            </a>
            <a href="tenant_notices.php"
               class="flex flex-col items-center gap-2 p-4 bg-slate-50 rounded-2xl border border-slate-100 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition-all group">
              <span class="material-symbols-outlined text-slate-400 group-hover:text-yellow-400 transition-colors">notifications_active</span>
              <span class="text-[9px] font-black uppercase tracking-widest text-slate-600 group-hover:text-white transition-colors">Notices</span>
            </a>
          </div>
        </div>
      </div>

    </div><!-- /p-8 -->
  </main>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
  function payWithPaystack() {
    let handler = PaystackPop.setup({
      key: '<?= PAYSTACK_PUBLIC_KEY ?>',
      email: '<?= $tenant["email"] ?>',
      amount: <?= $tenant["rent_amount"] * 100 ?>,
      currency: "NGN",
      ref: 'TS-' + Math.floor((Math.random() * 1000000000) + 1),
      onClose: function() { console.log('Closed.'); },
      callback: function(response) {
        window.location.href = "api/paystack_verify.php?reference=" + response.reference + "&amount=<?= $tenant['rent_amount'] * 100 ?>";
      }
    });
    handler.openIframe();
  }
</script>
</body>
</html>