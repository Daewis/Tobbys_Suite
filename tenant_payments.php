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
    $success = 'Payment successful! Your record has been updated.';
}

// Fetch tenant + apartment
$stmt = $db->prepare("SELECT t.*, a.unit_number, a.floor, a.type,
                             a.rent_amount, a.rent_quarterly, a.rent_yearly
                       FROM tenants t
                       JOIN apartments a ON t.apartment_id = a.id
                       WHERE t.user_id = ? AND t.status = 'Active'");
$stmt->execute([$user_id]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: tenant_dashboard.php'); exit; }

// ── Rent period lock checks ───────────────────────────────────────────────────
$current_month = date('Y-m');
$current_year  = date('Y');

$stmt = $db->prepare("SELECT COUNT(*) FROM payments
                       WHERE tenant_id = ? AND status = 'Paid' AND payment_type = 'Monthly'
                       AND DATE_FORMAT(payment_date, '%Y-%m') = ?");
$stmt->execute([$tenant['id'], $current_month]);
$rent_paid_monthly = (bool) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM payments
                       WHERE tenant_id = ? AND status = 'Paid' AND payment_type = 'Quarterly'
                       AND YEAR(payment_date) = ? AND QUARTER(payment_date) = QUARTER(NOW())");
$stmt->execute([$tenant['id'], $current_year]);
$rent_paid_quarterly = (bool) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM payments
                       WHERE tenant_id = ? AND status = 'Paid' AND payment_type = 'Yearly'
                       AND YEAR(payment_date) = ?");
$stmt->execute([$tenant['id'], $current_year]);
$rent_paid_yearly = (bool) $stmt->fetchColumn();

// ── Fetch all payments ────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM payments WHERE tenant_id = ? ORDER BY payment_date DESC");
$stmt->execute([$tenant['id']]);
$payments = $stmt->fetchAll();

// ── Fetch bills ───────────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM bills WHERE tenant_id = ? ORDER BY status ASC, due_date ASC");
$stmt->execute([$tenant['id']]);
$all_bills    = $stmt->fetchAll();
$unpaid_bills = array_values(array_filter($all_bills, fn($b) => $b['status'] === 'Unpaid'));
$paid_bills   = array_values(array_filter($all_bills, fn($b) => $b['status'] !== 'Unpaid'));
$unpaid_total = array_sum(array_column($unpaid_bills, 'amount'));

// ── Summary stats ─────────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*), SUM(amount) FROM payments WHERE tenant_id = ? AND status = 'Paid'");
$stmt->execute([$tenant['id']]);
[$paid_count, $paid_total] = $stmt->fetch(\PDO::FETCH_NUM);

$bill_icons = [
    'Electricity'      => 'bolt',
    'Water'            => 'water_drop',
    'Service Charge'   => 'build',
    'Waste Management' => 'delete',
    'Security'         => 'security',
];

$page_title = "Payments | Tobby's Suite";
include __DIR__ . '/includes/tenant_head.php';
?>

<div class="flex">
  <?php include __DIR__ . '/includes/tenant_sidebar.php'; ?>

  <main class="flex-1 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white border-b border-slate-200 px-8 py-5 flex justify-between items-center sticky top-0 z-10">
      <div>
        <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Payments</h2>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Unit <?= $tenant['unit_number'] ?> • History & Bills</p>
      </div>
      <div class="flex items-center gap-4">
        <button onclick="openRentModal()"
                class="bg-yellow-400 text-slate-900 px-5 py-2.5 rounded-2xl font-black text-xs uppercase tracking-widest hover:scale-[1.02] active:scale-95 transition-all shadow-lg shadow-yellow-900/10 flex items-center gap-2">
          <span class="material-symbols-outlined text-sm">bolt</span>
          Pay Rent
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

        <!-- Rent Options with paid badges -->
        <div class="bg-slate-900 text-white p-8 rounded-3xl flex flex-col justify-between">
          <div>
            <span class="material-symbols-outlined p-3 bg-white/10 text-yellow-400 rounded-2xl mb-4 block w-fit">payments</span>
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Rent Options</h3>
            <p class="text-2xl font-black text-white font-mono tracking-tighter">
              ₦<?= number_format($tenant['rent_amount']) ?>
              <span class="text-[10px] text-slate-400 uppercase">/ mo</span>
            </p>
          </div>
          <div class="mt-4 space-y-2.5 border-t border-white/10 pt-4">
            <div class="flex justify-between items-center">
              <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Monthly</span>
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-white">₦<?= number_format($tenant['rent_amount']) ?></span>
                <?php if ($rent_paid_monthly): ?>
                  <span class="text-[8px] font-black bg-green-500/20 text-green-400 px-1.5 py-0.5 rounded-lg">PAID</span>
                <?php endif; ?>
              </div>
            </div>
            <?php if (!empty($tenant['rent_quarterly'])): ?>
              <div class="flex justify-between items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Quarterly</span>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-300">₦<?= number_format($tenant['rent_quarterly']) ?></span>
                  <?php if ($rent_paid_quarterly): ?>
                    <span class="text-[8px] font-black bg-green-500/20 text-green-400 px-1.5 py-0.5 rounded-lg">PAID</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
            <?php if (!empty($tenant['rent_yearly'])): ?>
              <div class="flex justify-between items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Yearly</span>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-white">₦<?= number_format($tenant['rent_yearly']) ?></span>
                  <?php if ($rent_paid_yearly): ?>
                    <span class="text-[8px] font-black bg-green-500/20 text-green-400 px-1.5 py-0.5 rounded-lg">PAID</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Total Paid -->
        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <span class="material-symbols-outlined p-3 bg-green-50 text-green-600 rounded-2xl mb-4 block w-fit">check_circle</span>
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Paid</h3>
            <p class="text-2xl font-black text-slate-900 font-mono tracking-tighter">₦<?= number_format($paid_total ?? 0) ?></p>
          </div>
          <p class="text-xs text-slate-500 mt-4"><?= $paid_count ?> successful payment<?= $paid_count != 1 ? 's' : '' ?></p>
        </div>

        <!-- Outstanding Bills -->
        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <span class="material-symbols-outlined p-3 <?= count($unpaid_bills) > 0 ? 'bg-orange-50 text-orange-500' : 'bg-green-50 text-green-600' ?> rounded-2xl mb-4 block w-fit">
              <?= count($unpaid_bills) > 0 ? 'receipt_long' : 'check_circle' ?>
            </span>
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Outstanding Bills</h3>
            <p class="text-2xl font-black <?= count($unpaid_bills) > 0 ? 'text-orange-500' : 'text-green-600' ?> font-mono tracking-tighter">
              ₦<?= number_format($unpaid_total) ?>
            </p>
          </div>
          <p class="text-xs text-slate-500 mt-4">
            <?= count($unpaid_bills) ?> unpaid bill<?= count($unpaid_bills) != 1 ? 's' : '' ?> pending
          </p>
        </div>
      </div>

      <!-- Bills -->
      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center">
          <h3 class="font-black text-slate-900 uppercase text-sm tracking-tighter italic">Utility & Bills</h3>
          <span class="material-symbols-outlined text-slate-300">receipt_long</span>
        </div>

        <?php if (empty($all_bills)): ?>
          <div class="p-12 text-center bg-slate-50">
            <span class="material-symbols-outlined text-green-500 text-3xl mb-2 block">check_circle</span>
            <p class="text-xs text-slate-500 font-bold">No bills generated yet.</p>
          </div>
        <?php else: ?>
          <div class="divide-y divide-slate-50">
            <?php if (!empty($unpaid_bills)): ?>
              <div class="px-8 py-3 bg-orange-50">
                <p class="text-[9px] font-black text-orange-500 uppercase tracking-widest">
                  Unpaid — <?= count($unpaid_bills) ?> outstanding
                </p>
              </div>
              <?php foreach ($unpaid_bills as $bill): ?>
                <div class="p-6 flex items-center justify-between hover:bg-slate-50 transition-colors">
                  <div class="flex items-center gap-4">
                    <div class="w-11 h-11 rounded-2xl bg-orange-50 text-orange-500 flex items-center justify-center shrink-0">
                      <span class="material-symbols-outlined text-sm"><?= $bill_icons[$bill['bill_type']] ?? 'receipt' ?></span>
                    </div>
                    <div>
                      <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($bill['bill_type']) ?></p>
                      <p class="text-[9px] text-slate-400 font-bold tracking-widest uppercase">
                        Due <?= date('M d, Y', strtotime($bill['due_date'])) ?>
                        <?php if (strtotime($bill['due_date']) < time()): ?>
                          <span class="text-red-500 ml-1">• Overdue</span>
                        <?php endif; ?>
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center gap-4">
                    <div class="text-right">
                      <p class="text-sm font-black text-slate-900">₦<?= number_format($bill['amount']) ?></p>
                      <p class="text-[8px] font-black text-orange-500 uppercase tracking-widest">Unpaid</p>
                    </div>
                    <button onclick="payBill(<?= $bill['id'] ?>, '<?= htmlspecialchars($bill['bill_type']) ?>', <?= $bill['amount'] ?>)"
                            class="px-4 py-2 bg-slate-900 text-white rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-slate-700 active:scale-95 transition-all flex items-center gap-1.5">
                      <span class="material-symbols-outlined text-sm">bolt</span> Pay
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($paid_bills)): ?>
              <div class="px-8 py-3 bg-slate-50">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">
                  Settled — <?= count($paid_bills) ?> paid
                </p>
              </div>
              <?php foreach ($paid_bills as $bill): ?>
                <div class="p-6 flex items-center justify-between opacity-50">
                  <div class="flex items-center gap-4">
                    <div class="w-11 h-11 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                      <span class="material-symbols-outlined text-sm"><?= $bill_icons[$bill['bill_type']] ?? 'receipt' ?></span>
                    </div>
                    <div>
                      <p class="text-sm font-black text-slate-700"><?= htmlspecialchars($bill['bill_type']) ?></p>
                      <p class="text-[9px] text-slate-400 font-bold tracking-widest uppercase">Settled</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-black text-slate-700">₦<?= number_format($bill['amount']) ?></p>
                    <p class="text-[8px] font-black text-green-600 uppercase tracking-widest">Paid</p>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Payment History -->
      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center">
          <h3 class="font-black text-slate-900 uppercase text-sm tracking-tighter italic">Payment History</h3>
          <span class="material-symbols-outlined text-slate-300">receipt</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
              <tr>
                <th class="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Period / Item</th>
                <th class="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Type</th>
                <th class="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Receipt</th>
                <th class="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
                <th class="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php if (empty($payments)): ?>
                <tr><td colspan="5" class="p-12 text-center text-slate-400 text-sm italic">No payment records yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($payments as $p): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="px-6 py-5">
                    <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($p['month_paid_for']) ?></p>
                    <p class="text-[9px] text-slate-400"><?= date('d M Y', strtotime($p['payment_date'])) ?></p>
                  </td>
                  <td class="px-6 py-5">
                    <?php
                      $type_style = match($p['payment_type'] ?? 'Monthly') {
                          'Quarterly' => 'bg-blue-50 text-blue-600',
                          'Yearly'    => 'bg-purple-50 text-purple-600',
                          'Bill'      => 'bg-orange-50 text-orange-500',
                          default     => 'bg-slate-100 text-slate-600',
                      };
                    ?>
                    <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-lg <?= $type_style ?>">
                      <?= htmlspecialchars($p['payment_type'] ?? 'Monthly') ?>
                    </span>
                  </td>
                  <td class="px-6 py-5">
                    <p class="text-[9px] font-mono text-slate-400 uppercase"><?= $p['receipt_no'] ?></p>
                  </td>
                  <td class="px-6 py-5 text-right font-mono">
                    <p class="text-sm font-black text-slate-900">₦<?= number_format($p['amount']) ?></p>
                  </td>
                  <td class="px-6 py-5 text-right">
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
  </main>
</div>

<!-- ══════════ RENT MODAL ══════════ -->
<div id="rentModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md">
    <div class="p-8 border-b border-slate-100 flex justify-between items-center">
      <div>
        <h3 class="font-serif-italic text-slate-900 text-lg tracking-tighter">Pay Rent</h3>
        <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold">Select a payment period</p>
      </div>
      <button onclick="closeRentModal()" class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 hover:bg-slate-200 transition-all">
        <span class="material-symbols-outlined text-sm">close</span>
      </button>
    </div>

    <div class="p-8 space-y-3">

      <!-- Monthly -->
      <button onclick="selectRentType('Monthly', <?= $tenant['rent_amount'] ?>)"
              id="btn-monthly"
              <?= $rent_paid_monthly ? 'disabled' : '' ?>
              class="rent-option w-full flex items-center justify-between p-5 rounded-2xl border-2 border-slate-100 transition-all
                     <?= $rent_paid_monthly ? 'opacity-50 cursor-not-allowed bg-slate-50' : 'hover:border-slate-900 cursor-pointer' ?>">
        <div class="text-left">
          <p class="text-sm font-black text-slate-900">Monthly</p>
          <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">
            <?= $rent_paid_monthly ? '✓ Paid for ' . date('F Y') : 'Due ' . date('F t, Y') ?>
          </p>
        </div>
        <div class="text-right flex flex-col items-end gap-1">
          <p class="text-base font-black text-slate-900 font-mono">₦<?= number_format($tenant['rent_amount']) ?></p>
          <?php if ($rent_paid_monthly): ?>
            <span class="text-[8px] font-black text-green-600 bg-green-50 px-2 py-0.5 rounded-lg">PAID</span>
          <?php else: ?>
            <span class="text-[8px] font-black text-slate-400">1 Month</span>
          <?php endif; ?>
        </div>
      </button>

      <?php if (!empty($tenant['rent_quarterly'])): ?>
      <!-- Quarterly -->
      <button onclick="selectRentType('Quarterly', <?= $tenant['rent_quarterly'] ?>)"
              id="btn-quarterly"
              <?= $rent_paid_quarterly ? 'disabled' : '' ?>
              class="rent-option w-full flex items-center justify-between p-5 rounded-2xl border-2 border-slate-100 transition-all
                     <?= $rent_paid_quarterly ? 'opacity-50 cursor-not-allowed bg-slate-50' : 'hover:border-slate-900 cursor-pointer' ?>">
        <div class="text-left">
          <p class="text-sm font-black text-slate-900">Quarterly</p>
          <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">
            <?= $rent_paid_quarterly ? '✓ Paid this quarter' : 'Covers 3 months at once' ?>
          </p>
        </div>
        <div class="text-right flex flex-col items-end gap-1">
          <p class="text-base font-black text-slate-900 font-mono">₦<?= number_format($tenant['rent_quarterly']) ?></p>
          <?php if ($rent_paid_quarterly): ?>
            <span class="text-[8px] font-black text-green-600 bg-green-50 px-2 py-0.5 rounded-lg">PAID</span>
          <?php else: ?>
            <span class="text-[8px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded-lg">3 MONTHS</span>
          <?php endif; ?>
        </div>
      </button>
      <?php endif; ?>

      <?php if (!empty($tenant['rent_yearly'])): ?>
      <!-- Yearly -->
      <button onclick="selectRentType('Yearly', <?= $tenant['rent_yearly'] ?>)"
              id="btn-yearly"
              <?= $rent_paid_yearly ? 'disabled' : '' ?>
              class="rent-option w-full flex items-center justify-between p-5 rounded-2xl border-2 border-slate-100 transition-all
                     <?= $rent_paid_yearly ? 'opacity-50 cursor-not-allowed bg-slate-50' : 'hover:border-slate-900 cursor-pointer' ?>">
        <div class="text-left">
          <p class="text-sm font-black text-slate-900">Yearly</p>
          <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">
            <?= $rent_paid_yearly ? '✓ Paid for ' . date('Y') : 'Best value — full year upfront' ?>
          </p>
        </div>
        <div class="text-right flex flex-col items-end gap-1">
          <p class="text-base font-black text-slate-900 font-mono">₦<?= number_format($tenant['rent_yearly']) ?></p>
          <?php if ($rent_paid_yearly): ?>
            <span class="text-[8px] font-black text-green-600 bg-green-50 px-2 py-0.5 rounded-lg">PAID</span>
          <?php else: ?>
            <span class="text-[8px] font-black text-purple-600 bg-purple-50 px-2 py-0.5 rounded-lg">12 MONTHS</span>
          <?php endif; ?>
        </div>
      </button>
      <?php endif; ?>

      <!-- Confirm section — appears after selection -->
      <div id="confirmPaySection" class="hidden pt-3 border-t border-slate-100">
        <div class="p-4 bg-slate-50 rounded-2xl mb-4 flex justify-between items-center">
          <div>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Paying for</p>
            <p id="selectedLabel" class="text-sm font-black text-slate-900 mt-0.5"></p>
          </div>
          <p id="selectedAmount" class="text-xl font-black text-slate-900 font-mono"></p>
        </div>
        <button onclick="confirmRentPayment()"
                class="w-full bg-yellow-400 text-slate-900 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:scale-[1.01] active:scale-95 transition-all shadow-xl shadow-yellow-900/20 flex items-center justify-center gap-2">
          <span class="material-symbols-outlined text-sm">lock</span>
          Confirm & Pay via Paystack
        </button>
      </div>

    </div>
  </div>
</div>

<!-- ══════════ BILL MODAL ══════════ -->
<div id="billModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm">
    <div class="p-8 border-b border-slate-100 flex justify-between items-center">
      <div>
        <h3 class="font-serif-italic text-slate-900 text-lg tracking-tighter">Pay Bill</h3>
        <p id="billModalLabel" class="text-[10px] text-slate-400 uppercase tracking-widest font-bold"></p>
      </div>
      <button onclick="closeBillModal()" class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 hover:bg-slate-200 transition-all">
        <span class="material-symbols-outlined text-sm">close</span>
      </button>
    </div>
    <div class="p-8 space-y-5">
      <div class="p-5 bg-orange-50 rounded-2xl border border-orange-100 flex justify-between items-center">
        <div>
          <p class="text-[9px] font-black text-orange-500 uppercase tracking-widest mb-1">Amount Due</p>
          <p id="billAmountDisplay" class="text-2xl font-black text-slate-900 font-mono"></p>
        </div>
        <span class="material-symbols-outlined text-orange-400 text-4xl">receipt_long</span>
      </div>
      <button onclick="confirmBillPayment()"
              class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-800 active:scale-95 transition-all flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-sm">bolt</span>
        Pay Now via Paystack
      </button>
    </div>
  </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
// ── Rent Modal ────────────────────────────────────────────────────────────────
let selectedRentType = null, selectedRentAmount = 0;

function openRentModal() {
  document.getElementById('rentModal').classList.remove('hidden');
  resetRentSelection();
}
function closeRentModal() {
  document.getElementById('rentModal').classList.add('hidden');
}
function resetRentSelection() {
  selectedRentType = null; selectedRentAmount = 0;
  document.querySelectorAll('.rent-option:not([disabled])').forEach(b => {
    b.classList.remove('border-slate-900', 'bg-slate-50');
    b.classList.add('border-slate-100');
  });
  document.getElementById('confirmPaySection').classList.add('hidden');
}

function selectRentType(type, amount) {
  selectedRentType   = type;
  selectedRentAmount = amount;

  document.querySelectorAll('.rent-option:not([disabled])').forEach(b => {
    b.classList.remove('border-slate-900', 'bg-yellow-50');
    b.classList.add('border-slate-100');
  });
  const btn = document.getElementById('btn-' + type.toLowerCase());
  if (btn) { btn.classList.add('border-slate-900', 'bg-yellow-50'); btn.classList.remove('border-slate-100'); }

  document.getElementById('selectedLabel').textContent  = type + ' Rent';
  document.getElementById('selectedAmount').textContent = '₦' + amount.toLocaleString('en-NG');
  document.getElementById('confirmPaySection').classList.remove('hidden');
}

function confirmRentPayment() {
  if (!selectedRentType) return;
  PaystackPop.setup({
    key:      '<?= PAYSTACK_PUBLIC_KEY ?>',
    email:    '<?= addslashes($tenant["email"]) ?>',
    amount:   selectedRentAmount * 100,
    currency: 'NGN',
    ref:      'TS-RENT-' + Date.now(),
    metadata: { payment_type: selectedRentType },
    onClose:  () => {},
    callback: (response) => {
      window.location.href = 'api/paystack_verify.php?reference=' + response.reference
        + '&amount=' + (selectedRentAmount * 100)
        + '&payment_type=' + encodeURIComponent(selectedRentType);
    }
  }).openIframe();
}

// ── Bill Modal ────────────────────────────────────────────────────────────────
let currentBillId = null, currentBillAmount = 0, currentBillType = '';

function payBill(billId, billType, amount) {
  currentBillId     = billId;
  currentBillAmount = amount;
  currentBillType   = billType;
  document.getElementById('billModalLabel').textContent    = billType + ' Bill';
  document.getElementById('billAmountDisplay').textContent = '₦' + amount.toLocaleString('en-NG');
  document.getElementById('billModal').classList.remove('hidden');
}
function closeBillModal() {
  document.getElementById('billModal').classList.add('hidden');
}

function confirmBillPayment() {
  PaystackPop.setup({
    key:      '<?= PAYSTACK_PUBLIC_KEY ?>',
    email:    '<?= addslashes($tenant["email"]) ?>',
    amount:   currentBillAmount * 100,
    currency: 'NGN',
    ref:      'TS-BILL-' + Date.now(),
    metadata: { bill_id: currentBillId, payment_type: 'Bill' },
    onClose:  () => {},
    callback: (response) => {
      window.location.href = 'api/paystack_verify.php?reference=' + response.reference
        + '&amount=' + (currentBillAmount * 100)
        + '&payment_type=Bill'
        + '&bill_id=' + currentBillId;
    }
  }).openIframe();
}

// Close modals on backdrop click
['rentModal', 'billModal'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
  });
});
</script>
</body>
</html>