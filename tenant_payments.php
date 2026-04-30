<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'tenant') {
    header('Location: login.php');
    exit;
}

$db      = getDB();
$user_id = $_SESSION['user_id'];
$active_page = 'payments';

$success = '';
if (isset($_GET['success']) && $_GET['success'] === 'payment_received') {
    $success = 'Payment successful! Your rent record has been updated.';
}

// Fetch tenant + apartment
$stmt = $db->prepare("SELECT t.*, a.unit_number, a.floor, a.type, a.rent_amount, a.rent_quarterly, a.rent_yearly
                       FROM tenants t
                       JOIN apartments a ON t.apartment_id = a.id
                       WHERE t.user_id = ? AND t.status = 'Active'");
$stmt->execute([$user_id]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: tenant_dashboard.php'); exit; }

// Fetch all payments
$stmt = $db->prepare("SELECT * FROM payments WHERE tenant_id = ? ORDER BY payment_date DESC");
$stmt->execute([$tenant['id']]);
$payments = $stmt->fetchAll();

// Fetch unpaid bills
$stmt = $db->prepare("SELECT * FROM bills WHERE tenant_id = ? AND status = 'Unpaid' ORDER BY due_date ASC");
$stmt->execute([$tenant['id']]);
$unpaid_bills = $stmt->fetchAll();

// Payment summary stats
$stmt = $db->prepare("SELECT COUNT(*), SUM(amount) FROM payments WHERE tenant_id = ? AND status = 'Paid'");
$stmt->execute([$tenant['id']]);
[$paid_count, $paid_total] = $stmt->fetch(\PDO::FETCH_NUM);

$page_title = "Payments | Tobby's Suite";
include __DIR__ . '/includes/tenant_head.php';
?>

<div class="flex">
  <?php include __DIR__ . '/includes/tenant_sidebar.php'; ?>

  <main class="flex-1 min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white border-b border-slate-200 px-8 py-5 flex justify-between items-center sticky top-0 lg:top-0 z-10">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Payments</h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Unit <?= $tenant['unit_number'] ?> • History & Bills</p>
      </div>
      <div class="flex items-center gap-4">
        <button onclick="payWithPaystack()"
                class="bg-yellow-400 text-slate-900 px-5 py-2.5 rounded-2xl font-black text-xs uppercase tracking-widest hover:scale-[1.02] active:scale-95 transition-all shadow-lg shadow-yellow-900/10 flex items-center gap-2">
          <span class="material-symbols-outlined text-sm">bolt</span>
          Pay Now
        </button>
        <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-black">
          <?= substr($tenant['name'], 0, 1) ?>
        </div>
      </div>
    </nav>

    <div class="p-6 lg:p-8 space-y-8">

      <?php if ($success): ?>
        <div class="p-4 bg-green-50 text-green-700 rounded-2xl border border-green-100 text-xs font-bold flex items-center gap-2">
          <span class="material-symbols-outlined text-green-500 text-sm">check_circle</span>
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Monthly Rent -->
        <div class="bg-slate-900 text-white p-8 rounded-3xl flex flex-col justify-between">
          <div>
            <span class="material-symbols-outlined p-3 bg-white/10 text-yellow-400 rounded-2xl mb-4 block w-fit">payments</span>
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Rent Options</h3>
            <p class="text-2xl font-black text-white font-mono tracking-tighter">
              ₦<?= number_format($tenant['rent_amount']) ?>
              <span class="text-[10px] text-slate-400 uppercase">/ mo</span>
            </p>
          </div>
          <div class="mt-4 space-y-2 border-t border-white/10 pt-4">
            <?php if (!empty($tenant['rent_quarterly'])): ?>
              <div class="flex justify-between items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Quarterly</span>
                <span class="text-xs font-bold text-slate-300">₦<?= number_format($tenant['rent_quarterly']) ?></span>
              </div>
            <?php endif; ?>
            <?php if (!empty($tenant['rent_yearly'])): ?>
              <div class="flex justify-between items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Yearly</span>
                <span class="text-xs font-bold text-white">₦<?= number_format($tenant['rent_yearly']) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Payments Made -->
        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <span class="material-symbols-outlined p-3 bg-green-50 text-green-600 rounded-2xl mb-4 block w-fit">check_circle</span>
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Paid</h3>
            <p class="text-2xl font-black text-slate-900 font-mono tracking-tighter">₦<?= number_format($paid_total ?? 0) ?></p>
          </div>
          <p class="text-xs text-slate-500 mt-4"><?= $paid_count ?> successful payment<?= $paid_count != 1 ? 's' : '' ?></p>
        </div>

        <!-- Next Due -->
        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <span class="material-symbols-outlined p-3 bg-orange-50 text-orange-500 rounded-2xl mb-4 block w-fit">event</span>
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Next Due Date</h3>
            <p class="text-2xl font-serif-italic text-slate-900"><?= date('F t') ?></p>
          </div>
          <p class="text-xs text-slate-500 mt-4">Due on the 1st of every month</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- Unpaid Bills -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8 space-y-6">
          <div class="flex justify-between items-center">
            <h3 class="font-black text-slate-900 uppercase text-sm tracking-tighter italic">Utility & Bills</h3>
            <span class="material-symbols-outlined text-slate-300">receipt_long</span>
          </div>
          <?php if (empty($unpaid_bills)): ?>
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-100">
              <span class="material-symbols-outlined text-green-500 text-2xl mb-2 block">check_circle</span>
              <p class="text-xs text-slate-500 font-bold">All utilities are paid up!</p>
            </div>
          <?php else: ?>
            <div class="space-y-3">
              <?php foreach ($unpaid_bills as $bill): ?>
                <div class="p-4 bg-white border border-slate-100 rounded-2xl flex items-center justify-between shadow-sm">
                  <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                      <span class="material-symbols-outlined text-sm">bolt</span>
                    </div>
                    <div>
                      <p class="text-xs font-black text-slate-900"><?= htmlspecialchars($bill['bill_type']) ?></p>
                      <p class="text-[9px] text-slate-400 font-bold tracking-widest uppercase">Due <?= date('M d', strtotime($bill['due_date'])) ?></p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-xs font-black text-slate-900">₦<?= number_format($bill['amount']) ?></p>
                    <p class="text-[8px] font-black text-orange-500 uppercase tracking-widest">Unpaid</p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Full Payment History -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
          <div class="p-8 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-black text-slate-900 uppercase text-sm tracking-tighter italic">Rent History</h3>
            <span class="material-symbols-outlined text-slate-300">receipt</span>
          </div>
          <div class="flex-1 overflow-y-auto max-h-96">
            <table class="w-full text-left">
              <tbody class="divide-y divide-slate-50">
                <?php if (empty($payments)): ?>
                  <tr><td class="p-12 text-center text-slate-400 text-sm italic" colspan="2">No payment records yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($payments as $p): ?>
                  <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-5">
                      <p class="text-sm font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($p['month_paid_for']) ?></p>
                      <p class="text-[9px] font-mono text-slate-400 uppercase"><?= $p['receipt_no'] ?></p>
                      <p class="text-[9px] text-slate-400"><?= date('d M Y', strtotime($p['payment_date'])) ?></p>
                    </td>
                    <td class="p-5 text-right">
                      <p class="text-sm font-black text-slate-900 tracking-tight">₦<?= number_format($p['amount']) ?></p>
                      <span class="inline-block text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-lg
                        <?= $p['status'] === 'Paid' ? 'bg-green-50 text-green-600' : 'bg-orange-50 text-orange-500' ?>">
                        <?= $p['status'] ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
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
      onClose: function() {},
      callback: function(response) {
        window.location.href = "api/paystack_verify.php?reference=" + response.reference + "&amount=<?= $tenant['rent_amount'] * 100 ?>";
      }
    });
    handler.openIframe();
  }
</script>
</body>
</html>