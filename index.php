<?php
// index.php — Tobby's Suite Landing Page
// No auth required. Public-facing marketing page.
$current_year = date('Y');
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>Tobby's Suite</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "surface-container-high":       "#e6e8ea",
            "secondary":                    "#735c00",
            "surface-container":            "#eceef0",
            "error-container":              "#ffdad6",
            "surface-variant":              "#e0e3e5",
            "on-tertiary-fixed-variant":    "#38485d",
            "on-tertiary-container":        "#75859d",
            "on-primary-fixed-variant":     "#3a485a",
            "primary-container":            "#0e1c2d",
            "inverse-on-surface":           "#eff1f3",
            "outline":                      "#74777d",
            "surface":                      "#f7f9fb",
            "on-primary":                   "#ffffff",
            "on-primary-container":         "#778599",
            "on-background":                "#191c1e",
            "primary":                      "#000000",
            "error":                        "#ba1a1a",
            "inverse-surface":              "#2d3133",
            "on-secondary-fixed-variant":   "#574500",
            "on-secondary":                 "#ffffff",
            "secondary-container":          "#fed01b",
            "secondary-fixed-dim":          "#eec200",
            "inverse-primary":              "#b9c8de",
            "surface-container-lowest":     "#ffffff",
            "tertiary":                     "#000000",
            "background":                   "#f7f9fb",
            "surface-container-highest":    "#e0e3e5",
            "tertiary-container":           "#0b1c30",
            "on-secondary-fixed":           "#231b00",
            "on-tertiary":                  "#ffffff",
            "surface-bright":               "#f7f9fb",
            "on-tertiary-fixed":            "#0b1c30",
            "on-secondary-container":       "#6f5900",
            "on-error-container":           "#93000a",
            "surface-dim":                  "#d8dadc",
            "secondary-fixed":              "#ffe083",
            "on-error":                     "#ffffff",
            "primary-fixed-dim":            "#b9c8de",
            "surface-container-low":        "#f2f4f6",
            "outline-variant":              "#c4c6cd",
            "on-primary-fixed":             "#0e1c2d",
            "tertiary-fixed-dim":           "#b7c8e1",
            "primary-fixed":                "#d5e4fa",
            "tertiary-fixed":               "#d3e4fe",
            "on-surface":                   "#191c1e",
            "on-surface-variant":           "#44474c",
            "surface-tint":                 "#515f72"
          },
          borderRadius: {
            DEFAULT: "0.125rem", lg: "0.25rem", xl: "0.5rem", full: "0.75rem"
          },
          spacing: {
            gutter: "24px", base: "8px", margin: "32px",
            "section-gap": "120px", "container-max-width": "1280px", "content-gap": "48px"
          },
          fontFamily: {
            "display-xl": ["Manrope"], "body-lg": ["Manrope"], "label-sm": ["Manrope"],
            "label-bold": ["Manrope"], "headline-md": ["Manrope"],
            "body-md": ["Manrope"], "headline-lg": ["Manrope"]
          },
          fontSize: {
            "display-xl":  ["64px",  {"lineHeight":"1.1",  "letterSpacing":"-0.02em","fontWeight":"800"}],
            "body-lg":     ["18px",  {"lineHeight":"1.6",  "fontWeight":"400"}],
            "label-sm":    ["12px",  {"lineHeight":"1.2",  "fontWeight":"500"}],
            "label-bold":  ["14px",  {"lineHeight":"1.2",  "letterSpacing":"0.05em","fontWeight":"700"}],
            "headline-md": ["24px",  {"lineHeight":"1.4",  "fontWeight":"600"}],
            "body-md":     ["16px",  {"lineHeight":"1.6",  "fontWeight":"400"}],
            "headline-lg": ["40px",  {"lineHeight":"1.2",  "letterSpacing":"-0.01em","fontWeight":"700"}]
          }
        }
      }
    }
  </script>
  <style>
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    body { font-family: 'Manrope', sans-serif; background-color: #ffffff; }

    /* Smooth nav link underline */
    .nav-link { position: relative; }
    .nav-link::after {
      content: ''; position: absolute; bottom: -4px; left: 0;
      width: 0; height: 2px; background: #FACC15;
      transition: width 0.3s ease;
    }
    .nav-link:hover::after { width: 100%; }
    .nav-link.active::after { width: 100%; }
  </style>
</head>
<body class="bg-surface text-on-surface">

<!-- ═══════════════════════════════════════════
     TOP NAV
════════════════════════════════════════════ -->
<nav class="fixed top-0 w-full z-50 bg-white/80 backdrop-blur-md border-b border-slate-100 shadow-sm font-['Manrope'] antialiased">
  <div class="max-w-[1280px] mx-auto flex justify-between items-center px-8 h-20">

    <!-- Logo -->
    <img src="assets/img/screen.png" alt="Tobbys Suite Logo" href="index.php" class="w-18 h-12">
    

    <!-- Desktop Links -->
    <div class="hidden md:flex gap-8 items-center">
      <a href="#features"  class="nav-link text-slate-600 font-medium hover:text-slate-900 transition-colors">Features</a>
      <a href="#showcase"  class="nav-link text-slate-600 font-medium hover:text-slate-900 transition-colors">Product</a>
      <a href="#stats"     class="nav-link text-slate-600 font-medium hover:text-slate-900 transition-colors">Why Us</a>
      <a href="#contact"   class="nav-link text-slate-600 font-medium hover:text-slate-900 transition-colors">Contact</a>
    </div>

    <!-- CTA Buttons -->
    <div class="flex items-center gap-4">
      <a href="login.php"
         class="text-slate-900 font-bold hover:text-yellow-600 active:scale-95 transition-all">
        Login
      </a>
      <a href="register.php"
         class="bg-[#FACC15] text-primary px-6 py-2.5 rounded font-label-bold hover:shadow-lg hover:-translate-y-0.5 active:scale-95 transition-all">
        Get Started
      </a>
    </div>

  </div>
</nav>

<!-- ═══════════════════════════════════════════
     HERO
════════════════════════════════════════════ -->
<header id="hero" class="relative pt-20 overflow-hidden bg-white">
  <div class="max-w-[1280px] mx-auto flex flex-col md:flex-row items-center py-section-gap px-8 gap-content-gap">

    <!-- Left: Copy -->
    <div class="flex-1 z-10">
      <span class="inline-block mb-4 text-[11px] font-black uppercase tracking-widest text-secondary bg-secondary-fixed px-3 py-1 rounded-full">
        Nigeria's #1 Property Platform
      </span>
      <h1 class="font-display-xl text-display-xl text-primary-container mb-6">
        Master Your <br/>
        <span class="text-secondary-container">Living Experience</span>
      </h1>
      <p class="font-body-lg text-body-lg text-on-surface-variant max-w-xl mb-10">
        The intersection of Nigerian luxury real estate and Silicon Valley tech. Manage your properties with an editorial-grade interface designed for high-net-worth owners and modern tenants.
      </p>
      <div class="flex flex-wrap gap-4">
        <a href="register.php"
           class="bg-[#FACC15] text-primary px-10 py-4 rounded-lg font-label-bold text-lg hover:shadow-xl hover:-translate-y-1 transition-all">
          Get Started
        </a>
        <a href="#showcase"
           class="border-2 border-primary-container text-primary-container px-10 py-4 rounded-lg font-label-bold text-lg hover:bg-primary-container hover:text-white transition-all">
          View Demo
        </a>
      </div>
    </div>

    <!-- Right: Image -->
    <div class="flex-1 relative">
      <div class="rounded-2xl overflow-hidden shadow-2xl">
        <img class="w-full h-[600px] object-cover"
             src="https://lh3.googleusercontent.com/aida-public/AB6AXuBIfzZ39w53WcedTikVfY3NT7LP-Ar9rFmFuCNB0AxeLh-Sx8wwD___AzjFSroCNyEE3rqSsSXQFA7mMBK8zCx7j7cMefY6576vYt9OUFHK5ElhapKKfWX49H2dsh2VjlDu1Br9lhOTlvmjZdk2siAxdSWZ2YL5q6ox1Sh-CjTCK3phe-0bmZRoz7Z6Y1bX1Nv31GD7ohpH52QyVBZ_e1sNHDO1n3PMyahrf__IXAJ8ko4OpYeY9rglPtj2bAKLKIG6Zv1Uc0qY0pc8"
             alt="Luxury apartment interior"/>
      </div>
      <!-- Float Badge -->
      <div class="absolute -bottom-6 -left-6 bg-white p-6 shadow-xl rounded-xl border border-outline-variant max-w-[240px]">
        <div class="flex items-center gap-3 mb-2">
          <span class="material-symbols-outlined text-secondary-container" style="font-variation-settings:'FILL' 1;">star</span>
          <span class="font-label-bold text-primary-container">PREMIUM ACCESS</span>
        </div>
        <p class="text-xs text-on-surface-variant">Exclusive property management for Nigeria's elite real estate portfolio.</p>
      </div>
    </div>

  </div>
</header>

<!-- ═══════════════════════════════════════════
     STATS
════════════════════════════════════════════ -->
<section id="stats" class="bg-surface-container-low py-20 border-y border-outline-variant">
  <div class="max-w-[1280px] mx-auto px-8">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-12 text-center">
      <div>
        <p class="font-display-xl text-headline-lg text-primary-container">98%</p>
        <p class="font-label-bold text-on-surface-variant uppercase tracking-widest text-[10px]">Collection Rate</p>
      </div>
      <div>
        <p class="font-display-xl text-headline-lg text-primary-container">₦4.2B</p>
        <p class="font-label-bold text-on-surface-variant uppercase tracking-widest text-[10px]">Assets Managed</p>
      </div>
      <div>
        <p class="font-display-xl text-headline-lg text-primary-container">#1</p>
        <p class="font-label-bold text-on-surface-variant uppercase tracking-widest text-[10px]">Nigeria Tech Platform</p>
      </div>
      <div>
        <p class="font-display-xl text-headline-lg text-primary-container">24/7</p>
        <p class="font-label-bold text-on-surface-variant uppercase tracking-widest text-[10px]">Support Response</p>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     FEATURES
════════════════════════════════════════════ -->
<section id="features" class="py-section-gap bg-white">
  <div class="max-w-[1280px] mx-auto px-8">
    <div class="text-center mb-20">
      <h2 class="font-headline-lg text-headline-lg text-primary-container mb-4">Precision Engineering for Modern Living</h2>
      <div class="w-20 h-1.5 bg-[#FACC15] mx-auto"></div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

      <?php
      $features = [
        [
          'icon'  => 'security',
          'title' => 'Secure Lease Management',
          'desc'  => 'Automated contract generation with digital signatures and localized compliance protocols for the Nigerian market.',
          'link'  => 'manage_documents.php',
        ],
        [
          'icon'  => 'payments',
          'title' => 'Instant Rent Tracking',
          'desc'  => 'Real-time financial dashboard with multi-currency support and automated invoicing for high-value transactions.',
          'link'  => 'manage_payments.php',
        ],
        [
          'icon'  => 'engineering',
          'title' => '24/7 Maintenance',
          'desc'  => 'Sophisticated request system connecting tenants directly to certified luxury-grade service providers instantly.',
          'link'  => 'manage_complaints.php',
        ],
        [
          'icon'  => 'notifications_active',
          'title' => 'Smart Rent Reminders',
          'desc'  => 'Automated reminder workflows that reduce late payments and keep cash flow predictable month-over-month.',
          'link'  => 'manage_reminders.php',
        ],
        [
          'icon'  => 'analytics',
          'title' => 'Financial Analytics',
          'desc'  => 'Granular reporting across all units — occupancy rates, collection health, and yield projections at a glance.',
          'link'  => 'admin_reports.php',
        ],
        [
          'icon'  => 'person_pin_circle',
          'title' => 'Visitor Management',
          'desc'  => 'Digital visitor log with real-time entry tracking and automated host notification for premium security.',
          'link'  => 'manage_visitors.php',
        ],
      ];
      foreach ($features as $f):
      ?>
      <div class="p-10 bg-surface border border-outline-variant rounded-xl hover:shadow-2xl transition-all group">
        <div class="w-16 h-16 bg-primary-container rounded-lg flex items-center justify-center mb-8 group-hover:bg-secondary-container transition-colors">
          <span class="material-symbols-outlined text-white text-3xl"><?= $f['icon'] ?></span>
        </div>
        <h3 class="font-headline-md text-headline-md text-primary-container mb-4"><?= $f['title'] ?></h3>
        <p class="font-body-md text-on-surface-variant mb-6"><?= $f['desc'] ?></p>
        <a href="login.php" class="text-[11px] font-black uppercase tracking-widest text-secondary hover:text-secondary-container transition-colors flex items-center gap-1">
          Learn more <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </a>
      </div>
      <?php endforeach; ?>

    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     PRODUCT SHOWCASE
════════════════════════════════════════════ -->
<section id="showcase" class="py-section-gap bg-surface-container">
  <div class="max-w-[1280px] mx-auto px-8">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">

      <!-- Left copy -->
      <div class="md:col-span-5 pr-12">
        <span class="font-label-bold text-secondary tracking-widest uppercase mb-4 block">Product Showcase</span>
        <h2 class="font-headline-lg text-headline-lg text-primary-container mb-6 leading-tight">
          Sophistication Behind Every Screen
        </h2>
        <p class="font-body-lg text-on-surface-variant mb-8">
          Inspired by Silicon Valley's most efficient dashboards, our interface removes the clutter and focuses on what matters: your yield and your peace of mind.
        </p>
        <ul class="space-y-4 mb-10">
          <li class="flex items-center gap-3">
            <span class="material-symbols-outlined text-secondary-container" style="font-variation-settings:'FILL' 1;">check_circle</span>
            <span class="font-body-md text-primary-container">Predictive Analytics Dashboard</span>
          </li>
          <li class="flex items-center gap-3">
            <span class="material-symbols-outlined text-secondary-container" style="font-variation-settings:'FILL' 1;">check_circle</span>
            <span class="font-body-md text-primary-container">One-Click Tenant Onboarding</span>
          </li>
          <li class="flex items-center gap-3">
            <span class="material-symbols-outlined text-secondary-container" style="font-variation-settings:'FILL' 1;">check_circle</span>
            <span class="font-body-md text-primary-container">High-Fidelity Financial Reporting</span>
          </li>
        </ul>
        <div class="flex gap-4">
          <a href="login.php"
             class="bg-[#FACC15] text-primary px-8 py-3 rounded-lg font-label-bold hover:shadow-lg hover:-translate-y-0.5 transition-all">
            Access Dashboard
          </a>
          <a href="register.php"
             class="border border-primary-container text-primary-container px-8 py-3 rounded-lg font-label-bold hover:bg-primary-container hover:text-white transition-all">
            Register
          </a>
        </div>
      </div>

      <!-- Right: dashboard screenshot -->
      <div class="md:col-span-7">
        <div class="relative rounded-2xl overflow-hidden shadow-[0_32px_64px_-12px_rgba(0,0,0,0.15)] border border-outline-variant bg-white">
          <img class="w-full h-auto"
               src="https://lh3.googleusercontent.com/aida-public/AB6AXuDFD7WjFH-KH6KTGmcQyj7UC2t5lC_hmVydRAWjrf9L-g2t4ucvUm7QFbigIxNe8HN1DHcvBl3ZBRw3gw8xHphKK-ghMY1v3zQoViNEGCI08ce0qWHroEeGwx4qkwEoxCP7-EuJzPOKO9ELw_ikV0smziKTJmqjgWj7BFeNkta3UCo0BJ3V6Ncxo23qeZNxSlwQxwuBakfiL5g0QboI0j9IEasSt5ySTbGOASaR0mKiSklVqggQcyGSR-kHzFmBEjWd8euih_WZOIMS"
               alt="Tobby's Suite Dashboard"/>
          <!-- Live badge overlay -->
          <div class="absolute top-4 right-4 flex items-center gap-2 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-full shadow-md">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Live Platform</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     PORTAL QUICK ACCESS
════════════════════════════════════════════ -->
<section class="py-20 bg-white border-y border-outline-variant">
  <div class="max-w-[1280px] mx-auto px-8">
    <div class="text-center mb-12">
      <h2 class="font-headline-lg text-headline-lg text-primary-container mb-3">Access Your Portal</h2>
      <p class="text-on-surface-variant font-body-md">Choose your role to get started immediately.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl mx-auto">

      <!-- Tenant Portal -->
      <a href="login.php?role=tenant"
         class="group flex flex-col items-center gap-4 p-10 border-2 border-outline-variant rounded-xl hover:border-secondary-container hover:shadow-xl transition-all bg-surface">
        <div class="w-16 h-16 bg-primary-container rounded-xl flex items-center justify-center group-hover:bg-secondary-container transition-colors">
          <span class="material-symbols-outlined text-white text-3xl">person</span>
        </div>
        <div class="text-center">
          <h3 class="font-headline-md text-headline-md text-primary-container mb-1">Tenant Portal</h3>
          <p class="text-sm text-on-surface-variant">Pay rent, raise issues, view your lease.</p>
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-secondary flex items-center gap-1 group-hover:gap-2 transition-all">
          Sign In <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </span>
      </a>

      <!-- Admin / Staff Portal -->
      <a href="login.php?role=admin"
         class="group flex flex-col items-center gap-4 p-10 border-2 border-primary-container rounded-xl hover:bg-primary-container hover:shadow-xl transition-all bg-primary-container">
        <div class="w-16 h-16 bg-white/10 rounded-xl flex items-center justify-center group-hover:bg-secondary-container transition-colors">
          <span class="material-symbols-outlined text-white text-3xl">admin_panel_settings</span>
        </div>
        <div class="text-center">
          <h3 class="font-headline-md text-headline-md text-white mb-1">Admin / Staff</h3>
          <p class="text-sm text-on-primary-container">Manage units, tenants, finances & staff.</p>
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-secondary-container flex items-center gap-1 group-hover:gap-2 transition-all">
          Sign In <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </span>
      </a>

    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     FINAL CTA
════════════════════════════════════════════ -->
<section id="contact" class="py-section-gap bg-primary-container text-white relative overflow-hidden">
  <div class="absolute inset-0 opacity-10">
    <div class="absolute top-0 left-0 w-full h-full bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-[#FACC15] via-transparent to-transparent"></div>
  </div>
  <div class="max-w-[800px] mx-auto px-8 text-center relative z-10">
    <h2 class="font-display-xl text-headline-lg text-white mb-8">Transform Your Property Management Today</h2>
    <p class="font-body-lg text-on-primary-container mb-12">
      Join the elite network of property owners who have transitioned to a more intelligent, transparent, and luxury-focused management style.
    </p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
      <a href="register.php"
         class="bg-[#FACC15] text-primary px-12 py-5 rounded-lg font-label-bold text-xl hover:shadow-[0_0_30px_rgba(250,204,21,0.4)] transition-all">
        Start Your Free Trial
      </a>
      <a href="mailto:support@tobbyssuite.com"
         class="border border-white/30 backdrop-blur-sm text-white px-12 py-5 rounded-lg font-label-bold text-xl hover:bg-white/10 transition-all">
        Talk to Sales
      </a>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     FOOTER
════════════════════════════════════════════ -->
<footer class="bg-slate-50 border-t border-slate-200 font-['Manrope'] text-sm">
  <div class="max-w-[1280px] mx-auto grid grid-cols-1 md:grid-cols-4 gap-12 px-8 py-20">

    <!-- Brand -->
    <div class="col-span-1">
      <a href="index.php" class="text-lg font-bold text-slate-900 mb-6 block hover:text-yellow-600 transition-colors">
        Tobby's Suite
      </a>
      <p class="text-slate-500 leading-relaxed mb-6">
        Redefining luxury real estate technology in Nigeria through world-class engineering and editorial design.
      </p>
      <div class="flex gap-4">
        <a href="#" class="material-symbols-outlined text-slate-400 hover:text-yellow-500 cursor-pointer transition-colors">public</a>
        <a href="#" class="material-symbols-outlined text-slate-400 hover:text-yellow-500 cursor-pointer transition-colors">chat_bubble</a>
        <a href="mailto:support@tobbyssuite.com" class="material-symbols-outlined text-slate-400 hover:text-yellow-500 cursor-pointer transition-colors">alternate_email</a>
      </div>
    </div>

    <!-- Product Links -->
    <div>
      <h4 class="font-bold text-slate-900 mb-6">Product</h4>
      <ul class="space-y-4">
        <li><a href="#features"  class="text-slate-500 hover:text-slate-900 transition-colors">Features</a></li>
        <li><a href="#showcase"  class="text-slate-500 hover:text-slate-900 transition-colors">Product Demo</a></li>
        <li><a href="#stats"     class="text-slate-500 hover:text-slate-900 transition-colors">Why Tobby's Suite</a></li>
        <li><a href="login.php"  class="text-slate-500 hover:text-slate-900 transition-colors">Login</a></li>
        <li><a href="register.php" class="text-slate-500 hover:text-slate-900 transition-colors">Register</a></li>
      </ul>
    </div>

    <!-- Portals -->
    <div>
      <h4 class="font-bold text-slate-900 mb-6">Portals</h4>
      <ul class="space-y-4">
        <li><a href="tenant_dashboard.php"  class="text-slate-500 hover:text-slate-900 transition-colors">Tenant Portal</a></li>
        <li><a href="admin_dashboard.php"   class="text-slate-500 hover:text-slate-900 transition-colors">Admin Dashboard</a></li>
        <li><a href="manage_payments.php"   class="text-slate-500 hover:text-slate-900 transition-colors">Rent Payments</a></li>
        <li><a href="manage_complaints.php" class="text-slate-500 hover:text-slate-900 transition-colors">Maintenance</a></li>
        <li><a href="manage_notices.php"    class="text-slate-500 hover:text-slate-900 transition-colors">Notice Board</a></li>
      </ul>
    </div>

    <!-- Newsletter -->
    <div>
      <h4 class="font-bold text-slate-900 mb-6">Newsletter</h4>
      <p class="text-slate-500 mb-4">Stay updated with luxury living tech.</p>
      <form method="POST" action="#contact" class="flex gap-2">
        <input type="email" name="newsletter_email" placeholder="Email"
               class="bg-white border border-slate-200 px-4 py-2 rounded flex-1 focus:ring-1 focus:ring-yellow-400 outline-none text-sm"/>
        <button type="submit"
                class="bg-slate-900 text-white px-4 py-2 rounded font-bold hover:bg-slate-700 transition-colors">
          Join
        </button>
      </form>
      <div class="mt-8 pt-6 border-t border-slate-200">
        <h4 class="font-bold text-slate-900 mb-3 text-xs uppercase tracking-widest">Quick Access</h4>
        <div class="flex flex-col gap-2">
          <a href="login.php"
             class="flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-yellow-600 transition-colors">
            <span class="material-symbols-outlined text-sm">login</span> Staff Login
          </a>
          <a href="register.php"
             class="flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-yellow-600 transition-colors">
            <span class="material-symbols-outlined text-sm">person_add</span> Tenant Register
          </a>
        </div>
      </div>
    </div>

  </div>

  <!-- Bottom bar -->
  <div class="max-w-[1280px] mx-auto px-8 py-6 border-t border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4 text-slate-400 text-xs">
    <span>© <?= $current_year ?> Tobby's Suite. All rights reserved. Nigeria Luxury Real Estate Tech.</span>
    <div class="flex gap-6">
      <a href="#" class="hover:text-slate-700 transition-colors">Privacy Policy</a>
      <a href="#" class="hover:text-slate-700 transition-colors">Terms of Service</a>
      <a href="login.php" class="hover:text-slate-700 transition-colors">Staff Portal</a>
    </div>
  </div>
</footer>

<!-- Smooth scroll active nav highlighting -->
<script>
  const sections = document.querySelectorAll('section[id], header[id]');
  const navLinks = document.querySelectorAll('.nav-link');

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        navLinks.forEach(l => l.classList.remove('active'));
        const active = document.querySelector(`.nav-link[href="#${entry.target.id}"]`);
        if (active) active.classList.add('active');
      }
    });
  }, { threshold: 0.4 });

  sections.forEach(s => observer.observe(s));
</script>

</body>
</html>