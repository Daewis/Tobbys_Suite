<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }
requireRole(['admin']);           // Only admin can manage staff
requirePermission('manage_staff');

$db      = getDB();
$success = '';
$error   = '';

// ── APPOINT STAFF ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoint_staff'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $password = $_POST['password'];

    $allowed_roles = ['manager', 'accountant', 'receptionist', 'maintenance'];

    if (!in_array($role, $allowed_roles)) {
        $error = 'Invalid role selected.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Check email not already used
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'A user with that email already exists.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, is_verified) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$name, $email, $hash, $role]);
                logActivity('Staff Appointed', "$name appointed as $role");
                $success = "$name has been appointed as " . ucfirst($role) . " successfully.";
            } catch (PDOException $e) {
                $error = 'Error creating staff: ' . $e->getMessage();
            }
        }
    }
}

// ── CHANGE ROLE ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $uid      = (int) $_POST['user_id'];
    $new_role = $_POST['new_role'];
    $allowed  = ['manager', 'accountant', 'receptionist', 'maintenance'];

    if (in_array($new_role, $allowed) && $uid !== (int)$_SESSION['user_id']) {
        $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $uid]);
        logActivity('Role Changed', "User #$uid role changed to $new_role");
        $success = 'Role updated successfully.';
    } else {
        $error = 'Cannot change your own role or invalid role.';
    }
}

// ── REVOKE / DELETE ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_staff'])) {
    $uid = (int) $_POST['user_id'];
    if ($uid === (int)$_SESSION['user_id']) {
        $error = 'You cannot remove yourself.';
    } else {
        // Prevent removing other admins
        $check = $db->prepare("SELECT role FROM users WHERE id = ?");
        $check->execute([$uid]);
        $target = $check->fetch();
        if ($target && $target['role'] === 'admin') {
            $error = 'Cannot remove another admin account.';
        } else {
            $db->prepare("DELETE FROM users WHERE id = ? AND role != 'tenant'")->execute([$uid]);
            logActivity('Staff Revoked', "User #$uid removed from system");
            $success = 'Staff member removed successfully.';
        }
    }
}

// ── FETCH ALL STAFF (non-tenant) ─────────────────────────────────────────────
$staff = $db->query("SELECT id, name, email, role, created_at FROM users WHERE role != 'tenant' ORDER BY FIELD(role,'admin','manager','accountant','receptionist','maintenance'), name ASC")->fetchAll();

$staff_roles = ['manager', 'accountant', 'receptionist', 'maintenance'];

$role_descriptions = [
    'manager'     => 'Full operations access. Approves maintenance, views reports, manages tenants & notices.',
    'accountant'  => 'Finance-only access. Records payments, manages bills, generates reports.',
    'receptionist'=> 'Front desk access. Logs visitors, submits maintenance, manages notice board.',
    'maintenance' => 'Ticket access only. Views and updates assigned maintenance tickets.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff Management | Tobby's Suite</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,900&family=JetBrains+Mono:wght@500;700&display=swap');
    .font-serif-italic { font-family: 'Playfair Display', serif; font-style: italic; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
  <script>tailwind.config={theme:{extend:{colors:{primary:"#1e293b",secondary:"#fbbf24"}}}}</script>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col h-screen overflow-y-auto">

  <!-- Header -->
  <header class="bg-white border-b border-slate-200 px-8 py-6 sticky top-0 z-10 flex justify-between items-center">
    <div>
      <h2 class="text-xl font-serif-italic text-slate-900 tracking-tighter">Staff Command Center</h2>
      <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Manage Privileges & Personnel</p>
    </div>
    <button onclick="document.getElementById('appointModal').classList.remove('hidden')"
            class="flex items-center gap-2 bg-slate-900 text-white px-5 py-2.5 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-700 transition-all shadow-lg shadow-slate-900/20">
      <span class="material-symbols-outlined text-sm">person_add</span>
      Appoint Staff
    </button>
  </header>

  <div class="p-8 space-y-8">

    <!-- Alerts -->
    <?php if ($success): ?>
      <div class="p-4 bg-green-50 border border-green-100 text-green-700 rounded-2xl text-xs font-bold flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">check_circle</span> <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="p-4 bg-red-50 border border-red-100 text-red-600 rounded-2xl text-xs font-bold flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">error</span> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Role Reference Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <?php foreach ($role_descriptions as $r => $desc):
        $ri = roleInfo($r);
        $count = count(array_filter($staff, fn($s) => $s['role'] === $r));
      ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-lg <?= $ri['bg'] . ' ' . $ri['text'] ?>">
              <?= $ri['label'] ?>
            </span>
            <span class="text-lg font-black text-slate-900"><?= $count ?></span>
          </div>
          <p class="text-[10px] text-slate-500 leading-relaxed"><?= $desc ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Staff Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center">
        <h3 class="font-black text-slate-900 uppercase text-xs tracking-widest">All Personnel (<?= count($staff) ?>)</h3>
        <span class="material-symbols-outlined text-slate-300">badge</span>
      </div>
      <table class="w-full text-left">
        <thead class="bg-slate-50 border-b border-slate-100">
          <tr>
            <th class="px-8 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Name & Identity</th>
            <th class="px-8 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Role</th>
            <th class="px-8 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Since</th>
            <th class="px-8 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($staff as $s):
            $ri = roleInfo($s['role']);
            $is_self = ($s['id'] === (int)$_SESSION['user_id']);
          ?>
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-8 py-5">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black text-sm shrink-0">
                  <?= strtoupper(substr($s['name'], 0, 1)) ?>
                </div>
                <div>
                  <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($s['name']) ?></p>
                  <p class="text-[9px] text-slate-400 font-mono"><?= htmlspecialchars($s['email']) ?></p>
                </div>
              </div>
            </td>
            <td class="px-8 py-5">
              <span class="text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg <?= $ri['bg'] . ' ' . $ri['text'] ?>">
                <?= $ri['label'] ?>
              </span>
            </td>
            <td class="px-8 py-5 text-xs text-slate-400 font-mono">
              <?= date('d M Y', strtotime($s['created_at'])) ?>
            </td>
            <td class="px-8 py-5">
              <?php if ($is_self): ?>
                <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">Current User</span>
              <?php elseif ($s['role'] === 'admin'): ?>
                <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">Protected</span>
              <?php else: ?>
                <div class="flex items-center gap-2">
                  <!-- Change Role -->
                  <button onclick="openEditModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name']) ?>', '<?= $s['role'] ?>')"
                          class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-200 transition-all flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px]">edit</span> Edit
                  </button>
                  <!-- Revoke -->
                  <form method="POST" onsubmit="return confirm('Remove <?= htmlspecialchars($s['name']) ?> from the system?')">
                    <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                    <button type="submit" name="revoke_staff"
                            class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest bg-red-50 text-red-500 rounded-xl hover:bg-red-100 transition-all flex items-center gap-1">
                      <span class="material-symbols-outlined text-[13px]">person_remove</span> Revoke
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /p-8 -->
</main>

<!-- ══════════════ APPOINT MODAL ══════════════ -->
<div id="appointModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md">
    <div class="p-8 border-b border-slate-100 flex justify-between items-center">
      <div>
        <h3 class="font-serif-italic text-slate-900 text-lg tracking-tighter">Appoint Staff</h3>
        <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold">Create a new staff account</p>
      </div>
      <button onclick="document.getElementById('appointModal').classList.add('hidden')"
              class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 hover:bg-slate-200 transition-all">
        <span class="material-symbols-outlined text-sm">close</span>
      </button>
    </div>
    <form method="POST" class="p-8 space-y-5">
      <div>
        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Full Name</label>
        <input type="text" name="name" required placeholder="e.g. Funmi Adeyemi"
               class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl text-sm focus:ring-2 focus:ring-slate-900 outline-none transition-all">
      </div>
      <div>
        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
        <input type="email" name="email" required placeholder="staff@email.com"
               class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl text-sm focus:ring-2 focus:ring-slate-900 outline-none transition-all">
      </div>
      <div>
        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Role</label>
        <select name="role" required id="roleSelect" onchange="updateRoleDesc(this.value)"
                class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl text-sm focus:ring-2 focus:ring-slate-900 outline-none transition-all">
          <option value="">— Select a role —</option>
          <option value="manager">Manager</option>
          <option value="accountant">Accountant</option>
          <option value="receptionist">Receptionist</option>
          <option value="maintenance">Maintenance Staff</option>
        </select>
        <p id="roleDesc" class="text-[10px] text-slate-400 mt-2 italic min-h-[2rem] leading-relaxed"></p>
      </div>
      <div>
        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Temporary Password</label>
        <input type="password" name="password" required placeholder="Min. 8 characters"
               class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl text-sm focus:ring-2 focus:ring-slate-900 outline-none transition-all">
        <p class="text-[10px] text-slate-400 mt-1">Staff can change this after first login.</p>
      </div>
      <button type="submit" name="appoint_staff"
              class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 hover:bg-slate-800 transition-all shadow-xl shadow-slate-900/10 active:scale-95">
        <span class="material-symbols-outlined text-sm">person_add</span>
        Appoint Staff Member
      </button>
    </form>
  </div>
</div>

<!-- ══════════════ EDIT ROLE MODAL ══════════════ -->
<div id="editModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm">
    <div class="p-8 border-b border-slate-100 flex justify-between items-center">
      <div>
        <h3 class="font-serif-italic text-slate-900 text-lg tracking-tighter">Change Role</h3>
        <p id="editStaffName" class="text-[10px] text-slate-400 uppercase tracking-widest font-bold"></p>
      </div>
      <button onclick="document.getElementById('editModal').classList.add('hidden')"
              class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 hover:bg-slate-200 transition-all">
        <span class="material-symbols-outlined text-sm">close</span>
      </button>
    </div>
    <form method="POST" class="p-8 space-y-5">
      <input type="hidden" name="user_id" id="editUserId">
      <div>
        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">New Role</label>
        <select name="new_role" id="editRoleSelect" required
                class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl text-sm focus:ring-2 focus:ring-slate-900 outline-none transition-all">
          <option value="manager">Manager</option>
          <option value="accountant">Accountant</option>
          <option value="receptionist">Receptionist</option>
          <option value="maintenance">Maintenance Staff</option>
        </select>
      </div>
      <button type="submit" name="change_role"
              class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-800 transition-all active:scale-95">
        Update Role
      </button>
    </form>
  </div>
</div>

<script>
const roleDescs = {
  manager:      'Full operations access. Approves maintenance, views reports, manages tenants & notices.',
  accountant:   'Finance-only access. Records payments, manages utility bills, generates financial reports.',
  receptionist: 'Front desk access. Logs visitors, submits maintenance tickets, manages the notice board.',
  maintenance:  'Ticket access only. Views and updates status of assigned maintenance tickets.',
};

function updateRoleDesc(role) {
  document.getElementById('roleDesc').textContent = roleDescs[role] || '';
}

function openEditModal(id, name, currentRole) {
  document.getElementById('editUserId').value      = id;
  document.getElementById('editStaffName').textContent = name;
  document.getElementById('editRoleSelect').value  = currentRole;
  document.getElementById('editModal').classList.remove('hidden');
}

// Close modals on backdrop click
['appointModal','editModal'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
  });
});

// Auto-open appoint modal if there was a validation error
<?php if ($error && isset($_POST['appoint_staff'])): ?>
  document.getElementById('appointModal').classList.remove('hidden');
<?php endif; ?>
</script>

</body>
</html>