<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

$db = getDB();
$error = '';
$success = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Always pull email from session for Step 2, POST for Step 1
    $email = isset($_POST['email']) ? trim($_POST['email']) : ($_SESSION['reg_email'] ?? '');

    if (isset($_POST['send_otp'])) {
        if (empty($email)) {
            $error = "Email address is required.";
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'tenant'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "This email is not registered. Please contact management to be onboarded.";
            } elseif ($user['is_verified']) {
                $error = "Account already verified. Please login.";
            } else {
                require_once __DIR__ . '/includes/supabase.php';
                $supabase = new SupabaseAuth();
                $response = $supabase->signUp($email, 'tempPassword123!');

                if (!isset($response['error'])) {
                    $_SESSION['reg_email'] = $email;
                    $step = 2;
                    $success = "Verification code sent to " . htmlspecialchars($email);
                } else {
                    $error = "Auth Error: " . $response['error'];
                }
            }
        }
    } elseif (isset($_POST['verify_otp'])) {
        // Pull email exclusively from session — POST email field doesn't exist in Step 2
        $email = $_SESSION['reg_email'] ?? '';
        $otp = trim($_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($email)) {
            $error = "Session expired. Please start again.";
            $step = 1;
        } elseif (empty($otp) || !ctype_digit($otp) || strlen($otp) !== 6) {
            $error = "Please enter the 6-digit verification code.";
            $step = 2;
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
            $step = 2;
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = "Password must contain at least one uppercase letter.";
            $step = 2;
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = "Password must contain at least one number.";
            $step = 2;
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
            $step = 2;
        } else {
            require_once __DIR__ . '/includes/supabase.php';
            $supabase = new SupabaseAuth();
            $verify = $supabase->verifyOtp($email, $otp, 'signup');

            if (!isset($verify['error'])) {
                try {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ?, is_verified = 1 WHERE email = ?");
                    $stmt->execute([$hashed, $email]);

                    unset($_SESSION['reg_email']);
                    header('Location: login.php?verified=1');
                    exit;
                } catch (PDOException $e) {
                    $error = "Database Error: " . $e->getMessage();
                    $step = 2;
                }
            } else {
                $error = "Invalid or expired code. Please try again.";
                $step = 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Registration | Tobby's Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,900&family=JetBrains+Mono:wght@500;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .font-serif-italic { font-family: 'Playfair Display', serif; font-style: italic; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }

        /* OTP Input animation */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-4px); }
            40%, 80% { transform: translateX(4px); }
        }
        .shake { animation: shake 0.4s ease; }

        /* Password strength bar transitions */
        .strength-bar { transition: width 0.4s ease, background-color 0.4s ease; }

        /* Step transition */
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .step-enter { animation: fadeSlideIn 0.35s ease forwards; }

        /* Toggle password eye */
        .toggle-pw { cursor: pointer; user-select: none; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1e293b',
                        'secondary': '#fbbf24'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]">

    <div class="w-full max-w-md bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 overflow-hidden relative">
        <!-- Accent Line -->
        <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-slate-900 via-yellow-400 to-slate-900"></div>

        <div class="p-10">
            <!-- Header -->
            <div class="text-center mb-10">
                <div class="w-16 h-16 bg-slate-900 text-yellow-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl shadow-slate-200">
                    <span class="material-symbols-outlined text-4xl">domain</span>
                </div>
                <h1 class="text-3xl font-serif-italic text-slate-900 tracking-tighter">Tenant Onboarding</h1>
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mt-2">Claim Your Digital Resident Suite</p>

                <!-- Step Indicator -->
                <div class="flex items-center justify-center gap-3 mt-6">
                    <div class="flex items-center gap-1.5">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-black <?= $step === 1 ? 'bg-slate-900 text-yellow-400' : 'bg-green-500 text-white' ?>">
                            <?= $step === 1 ? '1' : '✓' ?>
                        </div>
                        <span class="text-[9px] font-black uppercase tracking-widest <?= $step === 1 ? 'text-slate-900' : 'text-green-500' ?>">Email</span>
                    </div>
                    <div class="w-8 h-px <?= $step === 2 ? 'bg-slate-900' : 'bg-slate-200' ?>"></div>
                    <div class="flex items-center gap-1.5">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-black <?= $step === 2 ? 'bg-slate-900 text-yellow-400' : 'bg-slate-200 text-slate-400' ?>">2</div>
                        <span class="text-[9px] font-black uppercase tracking-widest <?= $step === 2 ? 'text-slate-900' : 'text-slate-400' ?>">Verify &amp; Secure</span>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div id="alert-error" class="mb-6 p-4 bg-red-50 text-red-700 rounded-2xl border border-red-100 flex items-start gap-3 text-xs font-bold leading-tight">
                    <span class="material-symbols-outlined text-sm mt-0.5 shrink-0">security_update_warning</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-50 text-green-700 rounded-2xl border border-green-100 flex items-center gap-3 text-xs font-bold leading-tight">
                    <span class="material-symbols-outlined text-sm">verified</span>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- ───────── STEP 1: Email ───────── -->
            <?php if ($step === 1): ?>
            <form method="POST" class="space-y-6 step-enter">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Registered Email Address</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-slate-900 transition-colors">mail</span>
                        <input type="email" name="email" required placeholder="john@example.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold focus:ring-2 focus:ring-slate-900 focus:border-transparent outline-none transition-all placeholder:text-slate-300">
                    </div>
                    <p class="text-[9px] text-slate-400 mt-2 ml-1 italic font-medium">Use the email address you provided to management during onboarding.</p>
                </div>

                <button type="submit" name="send_otp"
                    class="w-full bg-slate-900 text-white py-5 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-slate-200 transition-all hover:bg-slate-800 active:scale-[0.98] flex items-center justify-center gap-3">
                    Request Verification Code
                    <span class="material-symbols-outlined text-sm">send</span>
                </button>

                <div class="text-center">
                    <a href="login.php" class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-900 transition-colors">Already registered? Login here</a>
                </div>
            </form>

            <!-- ───────── STEP 2: OTP + Password ───────── -->
            <?php else: ?>
            <form method="POST" class="space-y-5 step-enter" id="step2Form">

                <!-- Email badge -->
                <div class="bg-slate-50 px-4 py-3 rounded-2xl border border-slate-100 flex items-center justify-between">
                    <div>
                        <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Verifying for</p>
                        <p class="text-xs font-bold text-slate-900 mt-0.5"><?= htmlspecialchars($_SESSION['reg_email'] ?? '') ?></p>
                    </div>
                    <span class="material-symbols-outlined text-yellow-400 text-xl">mark_email_read</span>
                </div>

                <!-- OTP -->
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Verification Code (OTP)</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-slate-900 transition-colors">lock_open</span>
                        <input type="text" name="otp" id="otpInput" required placeholder="• • • • • •"
                            maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                            class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-mono font-bold tracking-[0.4em] focus:ring-2 focus:ring-slate-900 focus:border-transparent outline-none transition-all placeholder:text-slate-200 text-center">
                    </div>
                    <p class="text-[9px] text-slate-400 mt-2 ml-1 italic font-medium">Check your inbox — the code expires in 10 minutes.</p>
                </div>

                <!-- Divider -->
                <div class="flex items-center gap-3">
                    <div class="flex-1 h-px bg-slate-100"></div>
                    <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">Set Your Password</span>
                    <div class="flex-1 h-px bg-slate-100"></div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">New Password</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-slate-900 transition-colors">password</span>
                        <input type="password" name="password" id="passwordInput" required placeholder="••••••••"
                            class="w-full pl-12 pr-12 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold focus:ring-2 focus:ring-slate-900 focus:border-transparent outline-none transition-all"
                            oninput="checkStrength(this.value)">
                        <button type="button" class="toggle-pw absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-700 transition-colors" onclick="togglePw('passwordInput', this)">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </div>

                    <!-- Strength Meter -->
                    <div class="mt-2.5 space-y-1.5">
                        <div class="flex gap-1.5">
                            <div class="h-1 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                <div id="bar1" class="strength-bar h-full w-0 rounded-full"></div>
                            </div>
                            <div class="h-1 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                <div id="bar2" class="strength-bar h-full w-0 rounded-full"></div>
                            </div>
                            <div class="h-1 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                <div id="bar3" class="strength-bar h-full w-0 rounded-full"></div>
                            </div>
                            <div class="h-1 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                <div id="bar4" class="strength-bar h-full w-0 rounded-full"></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p id="strengthLabel" class="text-[9px] font-black uppercase tracking-widest text-slate-300">Enter a password</p>
                            <div id="strengthRules" class="flex gap-2">
                                <span id="rule-len"  class="text-[8px] font-bold text-slate-300 flex items-center gap-0.5"><span class="material-symbols-outlined text-[10px]">close</span>8+ chars</span>
                                <span id="rule-upper" class="text-[8px] font-bold text-slate-300 flex items-center gap-0.5"><span class="material-symbols-outlined text-[10px]">close</span>A-Z</span>
                                <span id="rule-num"  class="text-[8px] font-bold text-slate-300 flex items-center gap-0.5"><span class="material-symbols-outlined text-[10px]">close</span>0-9</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Confirm Password</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-slate-900 transition-colors">verified_user</span>
                        <input type="password" name="confirm_password" id="confirmInput" required placeholder="••••••••"
                            class="w-full pl-12 pr-12 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold focus:ring-2 focus:ring-slate-900 focus:border-transparent outline-none transition-all"
                            oninput="checkMatch()">
                        <button type="button" class="toggle-pw absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-700 transition-colors" onclick="togglePw('confirmInput', this)">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </div>
                    <p id="matchMsg" class="text-[9px] font-black mt-1.5 ml-1 hidden"></p>
                </div>

                <!-- Submit -->
                <button type="submit" name="verify_otp"
                    class="w-full bg-slate-900 text-white py-5 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-slate-200 transition-all hover:bg-slate-800 active:scale-[0.98] flex items-center justify-center gap-3 mt-2">
                    Finalise Registration
                    <span class="material-symbols-outlined text-sm">how_to_reg</span>
                </button>

                <!-- Resend (separate form so it doesn't interfere) -->
                <div class="text-center">
                    <form method="POST" class="inline">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['reg_email'] ?? '') ?>">
                        <button type="submit" name="send_otp"
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-900 transition-colors">
                            Didn't receive a code? Resend
                        </button>
                    </form>
                </div>

            </form>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="bg-slate-50 p-6 border-t border-slate-100 flex justify-center gap-6">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-300 text-lg">shield</span>
                <span class="text-[8px] font-black font-mono text-slate-400 uppercase tracking-widest">End-to-End SSL</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-300 text-lg">lock</span>
                <span class="text-[8px] font-black font-mono text-slate-400 uppercase tracking-widest">AES-256 Auth</span>
            </div>
        </div>
    </div>

<script>
/* ── Password strength meter ── */
function checkStrength(val) {
    const hasLen   = val.length >= 8;
    const hasUpper = /[A-Z]/.test(val);
    const hasNum   = /[0-9]/.test(val);
    const hasSpec  = /[^A-Za-z0-9]/.test(val);

    // Update rule chips
    setRule('rule-len',   hasLen);
    setRule('rule-upper', hasUpper);
    setRule('rule-num',   hasNum);

    const score = [hasLen, hasUpper, hasNum, hasSpec].filter(Boolean).length;

    const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

    for (let i = 1; i <= 4; i++) {
        const bar = document.getElementById('bar' + i);
        bar.style.width  = i <= score ? '100%' : '0%';
        bar.style.backgroundColor = i <= score ? colors[score] : '';
    }

    const lbl = document.getElementById('strengthLabel');
    lbl.textContent  = val.length === 0 ? 'Enter a password' : labels[score];
    lbl.style.color  = val.length === 0 ? '#cbd5e1' : colors[score];

    checkMatch();
}

function setRule(id, passed) {
    const el   = document.getElementById(id);
    const icon = el.querySelector('.material-symbols-outlined');
    if (passed) {
        el.classList.remove('text-slate-300');
        el.classList.add('text-green-500');
        icon.textContent = 'check';
    } else {
        el.classList.remove('text-green-500');
        el.classList.add('text-slate-300');
        icon.textContent = 'close';
    }
}

/* ── Confirm password match ── */
function checkMatch() {
    const pw  = document.getElementById('passwordInput').value;
    const cfm = document.getElementById('confirmInput').value;
    const msg = document.getElementById('matchMsg');
    if (!cfm) { msg.classList.add('hidden'); return; }
    msg.classList.remove('hidden');
    if (pw === cfm) {
        msg.textContent  = '✓ Passwords match';
        msg.className    = 'text-[9px] font-black mt-1.5 ml-1 text-green-500';
    } else {
        msg.textContent  = '✗ Passwords do not match';
        msg.className    = 'text-[9px] font-black mt-1.5 ml-1 text-red-500';
    }
}

/* ── Toggle password visibility ── */
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('.material-symbols-outlined');
    if (input.type === 'password') {
        input.type    = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type    = 'password';
        icon.textContent = 'visibility';
    }
}

/* ── OTP: digits only ── */
const otpInput = document.getElementById('otpInput');
if (otpInput) {
    otpInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });
}
</script>
</body>
</html>