<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . getRoleDashboard($_SESSION['role']));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($email, $password)) {
        header('Location: ' . getRoleDashboard($_SESSION['role']));
        exit;
    } else {
        $error = 'Invalid credentials. Please try again.';
    }
}
$urlError = $_GET['error'] ?? '';
if ($urlError === 'unauthorized') $error = 'You are not authorized to access that page.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | Tobby’s Suite</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}
    body{font-family:'Inter',sans-serif;}
  </style>
  <script>
    tailwind.config={theme:{extend:{colors:{
      "primary":"#1e293b","secondary":"#fbbf24","surface":"#f8fafc"
    }}}}
  </script>
</head>
<body class="min-h-screen bg-[#f8fafc] flex items-center justify-center px-4">

<div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-2 gap-0 rounded-2xl overflow-hidden shadow-2xl">

  <!-- Left Side: Branding & Visual -->
  <div class="bg-[#1e293b] p-12 flex flex-col justify-between text-white relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-64 h-64 bg-white/5 rounded-full"></div>
    <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-yellow-400/10 rounded-full"></div>
    
    <div class="relative z-10">
      <div class="flex items-center gap-3 mb-12">
        <div class="w-12 h-12 bg-yellow-400 rounded-xl flex items-center justify-center">
          <span class="material-symbols-outlined text-[#1e293b] text-2xl font-bold">domain</span>
        </div>
        <div>
          <h1 class="text-xl font-black tracking-tight leading-none uppercase">Tobby’s Suite</h1>
          <p class="text-[10px] uppercase tracking-widest opacity-60 mt-1">Management Portal</p>
        </div>
      </div>
      
      <h2 class="text-4xl font-black leading-tight mb-6">Master Your<br><span class="text-yellow-400 font-serif italic font-normal">Living Experience.</span></h2>
      <p class="text-slate-400 text-base leading-relaxed max-w-xs">
        The ultimate apartment management platform in Nigeria. Secure, seamless, and smart.
      </p>
    </div>

    <div class="relative z-10 mt-12 space-y-4">
      <div class="flex items-center gap-4 text-sm text-slate-300">
        <span class="material-symbols-outlined text-yellow-400">verified</span>
        <span>Secure Lease Management</span>
      </div>
      <div class="flex items-center gap-4 text-sm text-slate-300">
        <span class="material-symbols-outlined text-yellow-400">payments</span>
        <span>Instant Rent Tracking</span>
      </div>
      <div class="flex items-center gap-4 text-sm text-slate-300">
        <span class="material-symbols-outlined text-yellow-400">engineering</span>
        <span>24/7 Maintenance Requests</span>
      </div>
    </div>
  </div>

  <!-- Right Side: Login Form -->
  <div class="bg-white p-12 sm:p-20 flex flex-col justify-center">
    <div class="mb-10 text-center lg:text-left">
      <h3 class="text-3xl font-black text-slate-900 mb-2">Welcome Back</h3>
      <p class="text-slate-500 text-sm">Please enter your credentials to access your dashboard.</p>
    </div>

    <?php if ($error): ?>
    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100 flex items-center gap-3 text-red-700 text-sm font-medium">
      <span class="material-symbols-outlined text-base">error</span>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
      <div>
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Email Address</label>
        <div class="relative">
          <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg">mail</span>
          <input type="email" name="email" required autofocus
            class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-slate-900 focus:border-slate-900 transition-all text-sm outline-none"
            placeholder="name@email.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
      </div>

      <div>
        <div class="flex justify-between items-center mb-2 mx-1">
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Password</label>
          <a href="#" class="text-[10px] font-bold text-slate-400 hover:text-slate-900 uppercase tracking-widest">Forgot?</a>
        </div>
        <div class="relative">
          <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg">lock</span>
          <input type="password" name="password" required
            class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-slate-900 focus:border-slate-900 transition-all text-sm outline-none"
            placeholder="••••••••">
        </div>
      </div>

      <button type="submit"
        class="w-full bg-slate-900 text-white py-4 rounded-xl font-bold text-sm tracking-wide shadow-xl shadow-slate-200 hover:bg-slate-800 active:scale-[0.98] transition-all flex items-center justify-center gap-3 mt-4">
        Sign In to Portal
        <span class="material-symbols-outlined text-base">login</span>
      </button>

      <div class="pt-6 text-center">
        <p class="text-xs text-slate-400">
          Interested in renting? <a href="register.php" class="text-slate-900 font-bold hover:underline">Apply Here</a>
        </p>
      </div>
    </form>
  </div>
</div>

</body>
</html>
</div>

</body>
</html>
