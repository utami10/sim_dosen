<?php
// Variabel dari halaman pemanggil: $page_title
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/menu.php';
require_login();

$page_title = $page_title ?? 'Dashboard';
$current    = basename($_SERVER['SCRIPT_NAME']) . (isset($_GET['t']) && basename($_SERVER['SCRIPT_NAME']) === 'master.php' ? '?t=' . $_GET['t'] : '');
$nama       = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$peran      = $_SESSION['role'] ?? '';
$inisial    = strtoupper(mb_substr($nama, 0, 1));

// Foto profil (jika ada) di uploads/avatar/user_{id}.ext
$fotoUrl   = '';
$fotoFiles = glob(__DIR__ . '/../uploads/avatar/user_' . (int)($_SESSION['user_id'] ?? 0) . '.*') ?: [];
if ($fotoFiles) {
    $fotoUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/')
             . '/uploads/avatar/' . basename($fotoFiles[0]) . '?v=' . filemtime($fotoFiles[0]);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> | SIM Dosen UMB</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#1458b8;--navy:#0b2d59;--side:260px}
body{background:#f3f6fb;color:#1b2b43;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}
.sidebar{position:fixed;inset:0 auto 0 0;width:var(--side);background:linear-gradient(180deg,#0b2d59,#0e3f80);color:#fff;overflow-y:auto;z-index:1040;transition:transform .2s}
.sb-brand{display:flex;gap:12px;align-items:center;padding:22px 20px;border-bottom:1px solid rgba(255,255,255,.1)}
.sb-brand .ico{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.16);font-size:1.3rem}
.sb-brand small{display:block;color:rgba(255,255,255,.6);font-size:.72rem}
.sb-heading{padding:18px 22px 6px;font-size:.78rem;font-weight:700;color:rgba(255,255,255,.5)}
.sb-link{display:flex;align-items:center;gap:12px;margin:2px 12px;padding:.6rem .85rem;border-radius:10px;color:rgba(255,255,255,.82);text-decoration:none;font-size:.92rem}
.sb-link:hover{background:rgba(255,255,255,.09);color:#fff}
.sb-link.active{background:#fff;color:var(--navy);font-weight:700}
.main{margin-left:var(--side);min-height:100vh}
.topbar{position:sticky;top:0;z-index:1030;display:flex;align-items:center;gap:12px;padding:14px 28px;background:#fff;border-bottom:1px solid #e3eaf4}
.avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:var(--blue);color:#fff;font-weight:700;flex-shrink:0;overflow:hidden}
.avatar img{width:100%;height:100%;object-fit:cover}
.account-actions{display:flex;align-items:center;gap:14px}
.account-profile{display:flex;align-items:center;gap:10px}
a.account-profile{padding:4px 8px;border-radius:10px;transition:background .2s;cursor:pointer}
a.account-profile:hover{background:#f1f5fb}
.logout-direct{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:9px 15px;border:1px solid #fecdd3;border-radius:9px;background:#fff1f2;color:#dc2626;font-size:.9rem;font-weight:600;white-space:nowrap;transition:background .2s,color .2s}
.logout-direct:hover,.logout-direct:focus-visible{background:#dc2626;color:#fff}
.content{padding:28px}
.card-box{background:#fff;border:1px solid #e3eaf4;border-radius:16px}
.stat{padding:20px;display:flex;gap:16px;align-items:center}
.stat .ico{display:grid;place-items:center;width:52px;height:52px;border-radius:14px;font-size:1.5rem}
.stat .num{font-size:1.9rem;font-weight:750;line-height:1}
.btn-primary{background:var(--blue);border-color:var(--blue)}
.table thead th{font-size:.85rem;white-space:nowrap;background:#f6f9fe}
@media(max-width:991px){.sidebar{transform:translateX(-100%)}.sidebar.show{transform:none}.main{margin-left:0}.topbar,.content{padding-inline:16px}}
@media(max-width:575px){.account-actions{gap:8px}.logout-direct{padding:9px 11px}.logout-direct .logout-label{display:none}}
@media print{.sidebar,.topbar,.no-print{display:none!important}.main{margin:0}.content{padding:0}body{background:#fff}}
</style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <div class="ico"><i class="bi bi-mortarboard-fill"></i></div>
        <div><strong>SIM Dosen</strong><small>Universitas Muhammadiyah Bengkulu</small></div>
    </div>
    <nav class="pb-4">
    <?php foreach (app_menu() as $m):
        if (!empty($m['roles']) && !has_role(...$m['roles'])) continue;
        if (isset($m['heading'])): ?>
            <div class="sb-heading"><?= e($m['heading']) ?></div>
        <?php else: ?>
            <a class="sb-link <?= $current === $m['href'] ? 'active' : '' ?>" href="<?= e($m['href']) ?>">
                <i class="bi <?= e($m['icon']) ?>"></i><span><?= e($m['label']) ?></span>
            </a>
        <?php endif;
    endforeach; ?>
    </nav>
</aside>

<div class="main">
    <header class="topbar">
        <button class="btn btn-light d-lg-none" type="button" onclick="document.getElementById('sidebar').classList.toggle('show')" aria-label="Buka menu"><i class="bi bi-list"></i></button>
        <h1 class="h5 fw-bold mb-0 me-auto"><?= e($page_title) ?></h1>
        <div class="account-actions">
            <a href="profil.php" class="account-profile text-decoration-none text-reset" title="Ubah foto profil">
                <span class="avatar"><?php if ($fotoUrl): ?><img src="<?= e($fotoUrl) ?>" alt=""><?php else: ?><?= e($inisial) ?><?php endif; ?></span>
                <span class="text-start d-none d-sm-block lh-sm">
                    <span class="d-block fw-semibold small"><?= e($nama) ?></span>
                    <span class="text-muted" style="font-size:.78rem"><?= e(ucwords(strtolower(str_replace('_', ' ', $peran)))) ?></span>
                </span>
            </a>
            <form method="post" action="logout.php" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="logout-direct" aria-label="Keluar dari sistem" onclick="return confirm('Yakin ingin keluar dari sistem?')">
                    <i class="bi bi-box-arrow-right"></i><span class="logout-label">Keluar</span>
                </button>
            </form>
        </div>
    </header>
    <main class="content">
    <?= flash_show() ?>