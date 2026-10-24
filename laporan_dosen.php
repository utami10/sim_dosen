<?php
// laporan_dosen.php - Daftar Dosen Aktif (sesuai contoh laporan), bisa dicetak & diunduh CSV.
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/koneksi.php';
require_role(ROLE_ADMIN, ROLE_OPERATOR, ROLE_FAKULTAS, ROLE_PIMPINAN);
$conn->set_charset('utf8mb4');

$idPen = (int)($_GET['prodi'] ?? 0);
$semua = isset($_GET['semua']); // tampilkan semua status, bukan hanya Aktif
$where = []; $types = ''; $par = [];
if (!$semua) $where[] = "d.status_aktivitas = 'Aktif'";
if ($idPen > 0) { $where[] = 'd.id_penempatan = ?'; $types = 'i'; $par = [$idPen]; }
$rows = db_all($conn, dosen_base_sql() . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY d.nama', $types, $par);
$pen  = db_all($conn, 'SELECT * FROM penempatan ORDER BY fakultas, program_studi');
$sel  = null; foreach ($pen as $p) if ((int)$p['id_penempatan'] === $idPen) $sel = $p;

$head = ['No', 'Nomor Registrasi', 'NUPTK', 'Nama', 'NIP', 'Jabatan Fungsional', 'Kepangkatan', 'Pendidikan Terakhir', 'Rumpun Ilmu',
         'Status Serdos', 'Status Pegawai', 'Ikatan Kerja', 'Status Aktivitas', 'Penempatan'];
$kolom = ['nomor_registrasi', 'nuptk', 'nama', 'nip', 'jabatan', 'pangkat', 'pendidikan', 'rumpun', 'status_serdos', 'status_pegawai', 'ikatan_kerja', 'aktivitas', 'program_studi'];

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="daftar_dosen_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($o, $head);
    foreach ($rows as $i => $r) fputcsv($o, array_merge([$i + 1], array_map(fn($k) => (string)($r[$k] ?? ''), $kolom)));
    fclose($o);
    exit;
}

$page_title = 'Laporan Daftar Dosen';
require __DIR__ . '/includes/layout_header.php';
$qs = http_build_query(array_filter(['prodi' => $idPen ?: null, 'semua' => $semua ? 1 : null]));
?>
<form class="card-box p-3 mb-3 d-flex flex-wrap gap-2 align-items-center no-print" method="get" action="laporan_dosen.php">
    <select class="form-select w-auto" name="prodi" aria-label="Program studi">
        <option value="">Semua program studi</option>
        <?php foreach ($pen as $p): ?><option value="<?= (int)$p['id_penempatan'] ?>" <?= (int)$p['id_penempatan'] === $idPen ? 'selected' : '' ?>><?= e($p['fakultas'] . ' - ' . $p['program_studi']) ?></option><?php endforeach; ?>
    </select>
    <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="semua" id="semua" value="1" <?= $semua ? 'checked' : '' ?>><label class="form-check-label" for="semua">Termasuk dosen tidak aktif</label></div>
    <button class="btn btn-primary" type="submit">Tampilkan</button>
    <span class="ms-auto d-flex gap-2">
        <a class="btn btn-outline-success" href="laporan_dosen.php?<?= e($qs) ?>&export=csv"><i class="bi bi-filetype-csv me-1"></i>Unduh CSV</a>
        <button class="btn btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
    </span>
</form>

<div class="card-box p-4">
    <h2 class="h5 fw-bold">Daftar Dosen <?= $semua ? '' : 'Aktif ' ?>Universitas</h2>
    <div class="small mb-3">
        Universitas : <?= e($sel['universitas'] ?? 'Universitas Muhammadiyah Bengkulu') ?><br>
        Fakultas : <?= e($sel['fakultas'] ?? 'Semua fakultas') ?><br>
        Program Studi : <?= e($sel['program_studi'] ?? 'Semua program studi') ?>
    </div>
    <div class="table-responsive"><table class="table table-bordered table-sm align-middle">
        <thead><tr><?php foreach ($head as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="14" class="text-center text-muted py-4">Tidak ada data dosen untuk filter ini.</td></tr><?php endif;
        foreach ($rows as $i => $r): ?>
            <tr><td><?= $i + 1 ?></td><?php foreach ($kolom as $k): ?><td><?= e((string)($r[$k] ?? '')) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <div class="text-muted small">Dicetak <?= date('d/m/Y H:i') ?> &middot; <?= count($rows) ?> dosen</div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php';
