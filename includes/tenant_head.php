<?php
// includes/tenant_head.php
// Usage: include at top of every tenant page for consistent <head> + shared styles.
// $page_title should be set before including, e.g. $page_title = 'Payments | My Suite';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $page_title ?? "Resident Portal | Tobby's Suite" ?></title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,900&family=JetBrains+Mono:wght@500;700&display=swap');
    .font-serif-italic { font-family: 'Playfair Display', serif; font-style: italic; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
    body { padding-bottom: 4rem; } /* space for mobile bottom nav */
    @media (min-width: 1024px) { body { padding-bottom: 0; } }
  </style>
  <script>
    tailwind.config = { theme: { extend: { colors: { primary: "#1e293b", secondary: "#fbbf24" } } } }
  </script>
</head>
<body class="bg-[#f8fafc] min-h-screen">

<!-- Mobile Top Header -->
<header class="bg-slate-900 text-white px-4 lg:hidden flex justify-between items-center h-16 sticky top-0 z-40">
  <span class="font-black tracking-tight uppercase text-sm">Tobby's Suite</span>
  <div class="flex items-center gap-3">
    <span class="text-[10px] font-bold text-slate-400 uppercase">Unit <?= $tenant['unit_number'] ?? '' ?></span>
    <div class="w-8 h-8 rounded-xl bg-yellow-400 text-slate-900 flex items-center justify-center font-black text-sm">
      <?= substr($tenant['name'] ?? 'T', 0, 1) ?>
    </div>
  </div>
</header>