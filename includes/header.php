<?php
// includes/header.php
require_once __DIR__ . '/config.php';
$cfg         = getStripeKeys();
$businessName = $cfg['business_name'] ?? APP_NAME;
$currentPage  = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($pageTitle ?? 'Dashboard') ?> — <?= h($businessName) ?></title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Sohne:wght@400;500;600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

  <!-- Stripe.js -->
  <script src="https://js.stripe.com/v3/"></script>

  <style>
    /* ═══════════════════════════════════════════════
       STRIPE-LIKE DESIGN SYSTEM
    ═══════════════════════════════════════════════ */
    :root {
      --stripe-purple:   #635bff;
      --stripe-purple-d: #4b44cc;
      --stripe-purple-l: #ede9fe;
      --bg-sidebar:      #0a2540;
      --bg-sidebar-h:    #1a3d5c;
      --bg-main:         #f6f9fc;
      --bg-card:         #ffffff;
      --text-primary:    #0a2540;
      --text-secondary:  #4f566b;
      --text-muted:      #8792a2;
      --border:          #e0e6ed;
      --success:         #1ea672;
      --success-bg:      #e3f5ed;
      --danger:          #df1b41;
      --danger-bg:       #fde8ec;
      --warning:         #c9970b;
      --warning-bg:      #fef9e7;
      --info:            #0573e4;
      --info-bg:         #e8f3fd;
      --radius-sm:       6px;
      --radius-md:       8px;
      --radius-lg:       12px;
      --shadow-sm:       0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
      --shadow-md:       0 4px 12px rgba(0,0,0,.10);
      --shadow-lg:       0 10px 40px rgba(0,0,0,.14);
      --font-main:       'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      --sidebar-w:       230px;
      --topbar-h:        58px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: var(--font-main);
      font-size: 14px;
      color: var(--text-primary);
      background: var(--bg-main);
      line-height: 1.5;
      display: flex;
      min-height: 100vh;
    }

    /* ── Sidebar ─────────────────────────────────────────── */
    .sidebar {
      width: var(--sidebar-w);
      background: var(--bg-sidebar);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      z-index: 100;
      overflow-y: auto;
    }

    .sidebar-logo {
      padding: 20px 18px 14px;
      display: flex;
      align-items: center;
      gap: 10px;
      border-bottom: 1px solid rgba(255,255,255,.08);
      text-decoration: none;
    }

    .sidebar-logo-icon {
      width: 32px; height: 32px;
      background: var(--stripe-purple);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; font-weight: 700; color: #fff;
      letter-spacing: -1px;
      flex-shrink: 0;
    }

    .sidebar-logo-text { color: #fff; font-weight: 600; font-size: 15px; }
    .sidebar-logo-sub  { color: rgba(255,255,255,.45); font-size: 11px; margin-top: 1px; }

    .sidebar-section { padding: 18px 0 4px; }
    .sidebar-section-label {
      font-size: 10px; font-weight: 600; letter-spacing: .9px;
      color: rgba(255,255,255,.3); text-transform: uppercase;
      padding: 0 18px 8px;
    }

    .sidebar-link {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 18px;
      color: rgba(255,255,255,.65);
      text-decoration: none;
      border-radius: 0;
      font-size: 13.5px; font-weight: 450;
      transition: background .15s, color .15s;
      position: relative;
    }
    .sidebar-link:hover { background: rgba(255,255,255,.07); color: #fff; }
    .sidebar-link.active {
      background: rgba(99,91,255,.25);
      color: #fff;
    }
    .sidebar-link.active::before {
      content: '';
      position: absolute; left: 0; top: 0; bottom: 0;
      width: 3px; background: var(--stripe-purple);
      border-radius: 0 3px 3px 0;
    }

    .sidebar-link .icon {
      width: 18px; height: 18px; flex-shrink: 0; opacity: .8;
    }
    .sidebar-link.active .icon { opacity: 1; }

    .sidebar-badge {
      margin-left: auto;
      background: var(--stripe-purple);
      color: #fff; font-size: 10px; font-weight: 600;
      padding: 1px 7px; border-radius: 20px;
    }

    .sidebar-bottom {
      margin-top: auto;
      border-top: 1px solid rgba(255,255,255,.08);
      padding: 14px 18px;
    }
    .sidebar-user {
      display: flex; align-items: center; gap: 10px;
      color: rgba(255,255,255,.7); font-size: 13px;
    }
    .sidebar-avatar {
      width: 30px; height: 30px; border-radius: 50%;
      background: var(--stripe-purple);
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 12px; font-weight: 600;
      flex-shrink: 0;
    }

    /* ── Main content ────────────────────────────────────── */
    .main-wrap {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .topbar {
      height: var(--topbar-h);
      background: #fff;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      padding: 0 28px;
      position: sticky; top: 0; z-index: 90;
      gap: 16px;
    }

    .topbar-title { font-size: 15px; font-weight: 600; color: var(--text-primary); }
    .topbar-breadcrumb { font-size: 13px; color: var(--text-muted); }
    .topbar-spacer { flex: 1; }

    .btn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 8px 16px; border-radius: var(--radius-md);
      font-size: 13.5px; font-weight: 500; cursor: pointer;
      border: none; text-decoration: none;
      transition: all .18s;
    }
    .btn-primary {
      background: var(--stripe-purple); color: #fff;
    }
    .btn-primary:hover { background: var(--stripe-purple-d); }
    .btn-outline {
      background: transparent;
      border: 1.5px solid var(--border);
      color: var(--text-secondary);
    }
    .btn-outline:hover { border-color: #b0b8c5; background: var(--bg-main); }
    .btn-danger { background: var(--danger); color: #fff; }
    .btn-danger:hover { background: #b5142f; }
    .btn-success { background: var(--success); color: #fff; }
    .btn-sm { padding: 5px 12px; font-size: 12.5px; }

    .page-content { padding: 28px; flex: 1; }

    /* ── Cards ───────────────────────────────────────────── */
    .card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
    }
    .card-header {
      padding: 18px 22px 14px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .card-title { font-size: 14.5px; font-weight: 600; color: var(--text-primary); }
    .card-body { padding: 22px; }

    /* ── Stat grid ───────────────────────────────────────── */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }

    .stat-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 20px 22px;
      box-shadow: var(--shadow-sm);
    }
    .stat-label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
    .stat-value { font-size: 26px; font-weight: 700; color: var(--text-primary); line-height: 1; }
    .stat-change {
      font-size: 12px; margin-top: 6px;
      display: flex; align-items: center; gap: 4px;
    }
    .stat-change.up { color: var(--success); }
    .stat-change.down { color: var(--danger); }
    .stat-icon {
      float: right; width: 38px; height: 38px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; margin-top: -4px;
    }

    /* ── Table ───────────────────────────────────────────── */
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th {
      background: #f6f9fc;
      text-align: left; padding: 11px 16px;
      font-size: 11.5px; font-weight: 600; color: var(--text-muted);
      text-transform: uppercase; letter-spacing: .5px;
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    .data-table td {
      padding: 13px 16px; border-bottom: 1px solid var(--border);
      vertical-align: middle; font-size: 13.5px; color: var(--text-primary);
    }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: #f9fbfe; }

    /* ── Badges ──────────────────────────────────────────── */
    .badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 3px 9px; border-radius: 20px;
      font-size: 11.5px; font-weight: 600;
    }
    .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
    .badge-success { background: var(--success-bg); color: var(--success); }
    .badge-success::before { background: var(--success); }
    .badge-danger  { background: var(--danger-bg);  color: var(--danger);  }
    .badge-danger::before  { background: var(--danger);  }
    .badge-warning { background: var(--warning-bg); color: var(--warning); }
    .badge-warning::before { background: var(--warning); }
    .badge-info    { background: var(--info-bg);    color: var(--info);    }
    .badge-info::before    { background: var(--info);    }
    .badge-muted   { background: #f0f2f5; color: var(--text-muted); }
    .badge-muted::before   { background: var(--text-muted); }

    /* ── Form elements ───────────────────────────────────── */
    .form-group { margin-bottom: 18px; }
    .form-label { display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px; }
    .form-control {
      width: 100%; padding: 9px 13px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md); font-size: 14px;
      font-family: var(--font-main); color: var(--text-primary);
      background: #fff; outline: none;
      transition: border-color .18s, box-shadow .18s;
    }
    .form-control:focus {
      border-color: var(--stripe-purple);
      box-shadow: 0 0 0 3px var(--stripe-purple-l);
    }
    .form-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    /* ── Stripe card element wrapper ─────────────────────── */
    .stripe-element-wrap {
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      padding: 10px 13px;
      background: #fff;
      transition: border-color .18s, box-shadow .18s;
    }
    .stripe-element-wrap.focused {
      border-color: var(--stripe-purple);
      box-shadow: 0 0 0 3px var(--stripe-purple-l);
    }

    /* ── Alert / toast ───────────────────────────────────── */
    .alert {
      padding: 12px 16px; border-radius: var(--radius-md);
      font-size: 13.5px; margin-bottom: 18px;
      display: flex; align-items: flex-start; gap: 10px;
    }
    .alert-success { background: var(--success-bg); color: #0d6b49; border: 1px solid #a7dfc7; }
    .alert-danger  { background: var(--danger-bg);  color: #9b1231; border: 1px solid #f9b4c0; }
    .alert-info    { background: var(--info-bg);    color: #064a9f; border: 1px solid #a3cef5; }

    /* ── Spinner ─────────────────────────────────────────── */
    .spinner {
      width: 20px; height: 20px; border: 2.5px solid rgba(255,255,255,.4);
      border-top-color: #fff; border-radius: 50%;
      animation: spin .7s linear infinite; display: inline-block;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Payment card visual ─────────────────────────────── */
    .card-visual {
      width: 340px; height: 200px; border-radius: 18px;
      background: linear-gradient(135deg, #635bff 0%, #0573e4 100%);
      padding: 24px; color: #fff; position: relative; overflow: hidden;
      box-shadow: 0 12px 40px rgba(99,91,255,.4);
    }
    .card-visual::before {
      content: ''; position: absolute;
      width: 200px; height: 200px; border-radius: 50%;
      background: rgba(255,255,255,.08);
      top: -60px; right: -40px;
    }
    .card-visual-chip { font-size: 22px; margin-bottom: 24px; }
    .card-visual-number { font-size: 16px; letter-spacing: 3px; font-weight: 500; margin-bottom: 18px; font-family: 'Courier New', monospace; }
    .card-visual-meta { display: flex; justify-content: space-between; font-size: 12px; }
    .card-visual-label { opacity: .65; font-size: 10px; margin-bottom: 3px; }

    /* ── Pagination ──────────────────────────────────────── */
    .pagination { display: flex; gap: 6px; align-items: center; }
    .page-btn {
      min-width: 34px; height: 34px; border-radius: var(--radius-sm);
      display: flex; align-items: center; justify-content: center;
      border: 1.5px solid var(--border); color: var(--text-secondary);
      font-size: 13px; cursor: pointer; text-decoration: none;
      transition: all .15s;
    }
    .page-btn:hover, .page-btn.active { border-color: var(--stripe-purple); color: var(--stripe-purple); background: var(--stripe-purple-l); }

    /* ── Modal ───────────────────────────────────────────── */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(10,37,64,.45);
      display: flex; align-items: center; justify-content: center;
      z-index: 1000; padding: 20px;
      opacity: 0; pointer-events: none; transition: opacity .2s;
    }
    .modal-overlay.open { opacity: 1; pointer-events: auto; }
    .modal {
      background: #fff; border-radius: var(--radius-lg);
      width: 100%; max-width: 500px; box-shadow: var(--shadow-lg);
      transform: translateY(12px); transition: transform .2s;
    }
    .modal-overlay.open .modal { transform: translateY(0); }
    .modal-header { padding: 20px 24px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 16px; font-weight: 600; }
    .modal-close { background: none; border: none; cursor: pointer; font-size: 20px; color: var(--text-muted); line-height: 1; }
    .modal-body { padding: 24px; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }

    /* ── Search bar ──────────────────────────────────────── */
    .search-wrap { position: relative; }
    .search-wrap input { padding-left: 36px; }
    .search-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }

    /* ── Empty state ─────────────────────────────────────── */
    .empty-state { text-align: center; padding: 48px 24px; }
    .empty-state-icon { font-size: 48px; margin-bottom: 16px; opacity: .4; }
    .empty-state h3 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
    .empty-state p { color: var(--text-muted); font-size: 13.5px; }

    /* ── Responsive ──────────────────────────────────────── */
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); transition: transform .25s; }
      .sidebar.open { transform: translateX(0); }
      .main-wrap { margin-left: 0; }
      .form-row { grid-template-columns: 1fr; }
      .stat-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- ═══════════════ SIDEBAR ════════════════ -->
<aside class="sidebar" id="sidebar">
  <a href="index.php" class="sidebar-logo">
    <div class="sidebar-logo-icon">S</div>
    <div>
      <div class="sidebar-logo-text"><?= h($businessName) ?></div>
      <div class="sidebar-logo-sub">Payments Platform</div>
    </div>
  </a>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Overview</div>
    <a href="index.php"         class="sidebar-link <?= $currentPage === 'index'     ? 'active' : '' ?>">
      <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Payments</div>
    <a href="payments.php"      class="sidebar-link <?= $currentPage === 'payments'  ? 'active' : '' ?>">
      <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      All Payments
    </a>
    <a href="charge.php"        class="sidebar-link <?= $currentPage === 'charge'    ? 'active' : '' ?>">
      <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      New Charge
    </a>
    <a href="refunds.php"       class="sidebar-link <?= $currentPage === 'refunds'   ? 'active' : '' ?>">
      <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.51"/></svg>
      Refunds
    </a>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Data</div>
    <a href="customers.php"     class="sidebar-link <?= $currentPage === 'customers' ? 'active' : '' ?>">
      <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Customers
    </a>
    <a href="webhook.php"       class="sidebar-link <?= $currentPage === 'webhook'   ? 'active' : '' ?>">
      <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
      Webhook Logs
    </a>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Configure</div>
    <a href="settings.php"      class="sidebar-link <?= $currentPage === 'settings'  ? 'active' : '' ?>">
      <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
      API Settings
    </a>
  </div>

  <div class="sidebar-bottom">
    <div class="sidebar-user">
      <div class="sidebar-avatar">A</div>
      <div>
        <div style="font-size:13px;color:#fff">Admin</div>
        <div style="font-size:11px;color:rgba(255,255,255,.4)">Test Mode</div>
      </div>
    </div>
  </div>
</aside>

<!-- ═══════════════ MAIN WRAP ════════════════ -->
<div class="main-wrap">

  <!-- Topbar -->
  <header class="topbar">
    <button onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none;background:none;border:none;cursor:pointer;color:var(--text-secondary);" id="hamburger">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div>
      <div class="topbar-title"><?= h($pageTitle ?? 'Dashboard') ?></div>
    </div>
    <div class="topbar-spacer"></div>
    <span class="badge badge-warning" style="font-size:11px;padding:4px 10px;">Test Mode</span>
    <a href="charge.php" class="btn btn-primary btn-sm">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Payment
    </a>
  </header>

  <!-- Page body renders here -->
  <div class="page-content">
