<?php
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/koneksi.php';
require_login();
$conn->set_charset('utf8mb4');

/** Jalankan query agregat; jika tabel belum ada, kembalikan nilai kosong agar dashboard tetap tampil. */
function aman(mysqli $c, string $sql): array
{
    try { return db_all($c, $sql); } catch (Throwable $e) { error_log('Dashboard: ' . $e->getMessage()); return []; }
}

$tot   = (int)(aman($conn, "SELECT COUNT(*) n FROM dosen")[0]['n'] ?? 0);
$aktif = (int)(aman($conn, "SELECT COUNT(*) n FROM dosen WHERE status_aktivitas = 'Aktif'")[0]['n'] ?? 0);
$serdos= (int)(aman($conn, "SELECT COUNT(*) n FROM dosen WHERE status_serdos = 'Sudah'")[0]['n'] ?? 0);
$prodi = (int)(aman($conn, "SELECT COUNT(*) n FROM penempatan")[0]['n'] ?? 0);

$perProdi = aman($conn, "SELECT pn.program_studi nama, COUNT(d.id_dosen) n FROM penempatan pn LEFT JOIN dosen d ON d.id_penempatan = pn.id_penempatan GROUP BY pn.id_penempatan, pn.program_studi ORDER BY n DESC");
$perJabatan = aman($conn, "SELECT COALESCE(j.nama_jabatan, 'Belum diisi') nama, COUNT(*) n FROM dosen d LEFT JOIN jabatan_fungsional j ON j.id_jabatan = d.id_jabatan GROUP BY j.id_jabatan, j.nama_jabatan ORDER BY n DESC");
$terbaru = aman($conn, "SELECT d.nama, d.nip, pn.program_studi FROM dosen d LEFT JOIN penempatan pn ON pn.id_penempatan = d.id_penempatan ORDER BY d.id_dosen DESC LIMIT 5");

$stat = [
    ['Total dosen', $tot, 'bi-person-badge', '#e8f0fd', '#1458b8'],
    ['Dosen aktif', $aktif, 'bi-person-check', '#e6f6ee', '#198754'],
    ['Sudah sertifikasi', $serdos, 'bi-patch-check', '#fff4e0', '#d98200'],
    ['Program studi', $prodi, 'bi-building', '#f1eafd', '#6f42c1'],
];

function bar_list(array $rows): void {
    $max = max(array_merge([1], array_column($rows, 'n')));
    if (!$rows) { echo '<p class="text-muted small mb-0">Belum ada data.</p>'; return; }
    foreach ($rows as $r) {
        $w = round($r['n'] / $max * 100);
        echo '<div class="mb-3"><div class="d-flex justify-content-between small mb-1"><span>' . e($r['nama']) . '</span><strong>' . (int)$r['n'] . '</strong></div>'
           . '<div class="progress" style="height:8px"><div class="progress-bar" style="width:' . $w . '%;background:#1458b8"></div></div></div>';
    }
}

$page_title = 'Dashboard';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="card-box p-4 mb-4" style="background:linear-gradient(135deg,#0b2d59,#1458b8);color:#fff;border:0">
    <h2 class="h4 fw-bold mb-1">Selamat datang, <?= e($_SESSION['nama_lengkap']) ?></h2>
    <p class="mb-0 text-white-50">Kelola data dosen Universitas Muhammadiyah Bengkulu dari satu tempat.</p>
</div>

<?php if (has_role(ROLE_DOSEN)): ?>
<div class="card-box p-4"><p class="mb-3">Anda masuk sebagai dosen. Anda dapat melihat data kepegawaian Anda sendiri.</p>
<a class="btn btn-primary" href="dosen.php"><i class="bi bi-person-badge me-2"></i>Lihat data saya</a></div>
<?php require __DIR__ . '/includes/layout_footer.php'; return; endif; ?>
<div class="row g-3 mb-4">
<?php foreach ($stat as [$label, $jumlah, $ikon, $bg, $fg]): ?>
    <div class="col-sm-6 col-xl-3">
        <div class="card-box stat">
            <div class="ico" style="background:<?= $bg ?>;color:<?= $fg ?>"><i class="bi <?= $ikon ?>"></i></div>
            <div><div class="num"><?= number_format($jumlah, 0, ',', '.') ?></div><div class="text-muted small"><?= e($label) ?></div></div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-4"><div class="card-box p-4 h-100"><h3 class="h6 fw-bold mb-3">Dosen per program studi</h3><?php bar_list($perProdi); ?></div></div>
    <div class="col-lg-4"><div class="card-box p-4 h-100"><h3 class="h6 fw-bold mb-3">Dosen per jabatan fungsional</h3><?php bar_list($perJabatan); ?></div></div>
    <div class="col-lg-4">
        <div class="card-box p-4 h-100">
            <h3 class="h6 fw-bold mb-3">Dosen terbaru</h3>
            <?php if (!$terbaru): ?>
                <p class="text-muted small">Belum ada data dosen.</p>
            <?php else: foreach ($terbaru as $d): ?>
                <div class="mb-3"><div class="fw-semibold"><?= e($d['nama']) ?></div>
                <div class="text-muted small"><?= e($d['nip'] ?: '-') ?> &middot; <?= e($d['program_studi'] ?: 'Belum ditempatkan') ?></div></div>
            <?php endforeach; endif; ?>
            <?php if (can_write()): ?>
                <a class="btn btn-primary btn-sm" href="dosen.php?aksi=form"><i class="bi bi-plus-lg me-1"></i>Tambah dosen</a>
            <?php endif; ?>
            <?php if (has_role(ROLE_ADMIN, ROLE_OPERATOR, ROLE_FAKULTAS, ROLE_PIMPINAN)): ?><a class="btn btn-outline-primary btn-sm" href="laporan_dosen.php">Lihat laporan</a><?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
