<?php
// dosen.php - Data Dosen (daftar, cari, tambah, ubah, hapus). Kolom dibaca otomatis dari tabel `dosen`.
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/koneksi.php';
require_login();
$conn->set_charset('utf8mb4');

$cols = $conn->query("SHOW COLUMNS FROM `dosen`")->fetch_all(MYSQLI_ASSOC);
$pk = 'id_dosen';
$edit = array_values(array_filter($cols, function ($c) {
    if (stripos($c['Extra'], 'auto_increment') !== false) return false;
    if (preg_match('/^(timestamp|datetime)/i', $c['Type']) && (stripos((string)$c['Default'], 'current_timestamp') !== false || stripos($c['Extra'], 'on update') !== false)) return false;
    return true;
}));

// kolom relasi => [tabel, kolom id, ekspresi label]
$ref = [
    'id_jabatan'            => ['jabatan_fungsional', 'id_jabatan', 'nama_jabatan'],
    'id_kepangkatan'        => ['kepangkatan', 'id_kepangkatan', "CONCAT_WS(' ', nama_pangkat, CONCAT_WS('/', golongan, ruang))"],
    'id_pendidikan'         => ['pendidikan', 'id_pendidikan', 'jenjang'],
    'id_rumpun_ilmu'        => ['rumpun_ilmu', 'id_rumpun_ilmu', 'nama_rumpun'],
    'id_status_kepegawaian' => ['status_kepegawaian', 'id_status_kepegawaian', "CONCAT_WS(' - ', status_pegawai, ikatan_kerja)"],
    'id_penempatan'         => ['penempatan', 'id_penempatan', "CONCAT_WS(' / ', fakultas, program_studi)"],
];
function label(string $f): string { return ucwords(str_replace('_', ' ', preg_replace('/^id_/', '', $f))); }

// Dosen hanya melihat datanya sendiri
$batasi = has_role(ROLE_DOSEN);
$batasSql = ''; $batasT = ''; $batasP = [];
if ($batasi) {
    if (!empty($_SESSION['id_dosen'])) { $batasSql = ' d.id_dosen = ? '; $batasT = 's'; $batasP = [(string)$_SESSION['id_dosen']]; }
    else { $batasSql = ' d.nama = ? '; $batasT = 's'; $batasP = [(string)($_SESSION['nama_lengkap'] ?? '')]; }
}

$self = 'dosen.php';

// ---- aksi tulis ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_write()) { http_response_code(403); exit('403 - Hanya Admin/Operator yang boleh mengubah data.'); }
    if (!post_csrf_ok()) { flash_set('danger', 'Sesi tidak valid, coba lagi.'); redirect($self); }
    $aksi = $_POST['aksi'] ?? '';
    $id = (string)($_POST['id'] ?? '');

    if ($aksi === 'hapus') {
        try {
            $s = $conn->prepare("DELETE FROM dosen WHERE id_dosen = ?");
            $s->bind_param('s', $id); $s->execute(); $s->close();
            flash_set('success', 'Data dosen berhasil dihapus.');
        } catch (Throwable $ex) {
            flash_set('danger', 'Data dosen tidak bisa dihapus: ' . $ex->getMessage());
        }
        redirect($self);
    }

    if ($aksi === 'simpan') {
        $names = []; $vals = [];
        foreach ($edit as $c) {
            $f = $c['Field'];
            $v = trim((string)($_POST[$f] ?? ''));
            if ($v === '') {
                if ($c['Null'] === 'YES') $v = null;
                elseif ($c['Default'] !== null) $v = $c['Default'];
                else {
                    flash_set('danger', 'Kolom "' . label($f) . '" wajib diisi.');
                    redirect($self . '?aksi=form' . ($id !== '' ? '&id=' . urlencode($id) : ''));
                }
            }
            $names[] = $f; $vals[] = $v;
        }
        try {
            if ($id === '') {
                $s = $conn->prepare("INSERT INTO dosen (`" . implode('`,`', $names) . "`) VALUES (" . implode(',', array_fill(0, count($names), '?')) . ")");
            } else {
                $s = $conn->prepare("UPDATE dosen SET `" . implode('`=?, `', $names) . "`=? WHERE id_dosen = ?");
                $vals[] = $id;
            }
            $s->bind_param(str_repeat('s', count($vals)), ...$vals);
            $s->execute(); $s->close();
            flash_set('success', 'Data dosen berhasil disimpan.');
        } catch (Throwable $ex) {
            error_log('dosen.php: ' . $ex->getMessage());
            flash_set('danger', 'Gagal menyimpan: ' . $ex->getMessage());
        }
        redirect($self);
    }
    redirect($self);
}

$page_title = 'Data Dosen';
require __DIR__ . '/includes/layout_header.php';

if (($_GET['aksi'] ?? '') === 'form' && can_write()):
    $id = (string)($_GET['id'] ?? '');
    $row = $id !== '' ? (db_all($conn, "SELECT * FROM dosen WHERE id_dosen = ?", 's', [$id])[0] ?? []) : [];
    $opsi = [];
    foreach ($ref as $f => [$tbl, $idc, $lbl]) {
        try { $opsi[$f] = db_all($conn, "SELECT `$idc` AS v, $lbl AS l FROM `$tbl` ORDER BY l"); } catch (Throwable $ex) { $opsi[$f] = []; }
    }
?>
<div class="card-box p-4">
    <h2 class="h5 fw-bold mb-3"><?= $id !== '' ? 'Ubah' : 'Tambah' ?> Data Dosen</h2>
    <form method="post" action="<?= $self ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= e($id) ?>">
        <div class="row g-3">
        <?php foreach ($edit as $c):
            $f = $c['Field']; $val = $row[$f] ?? ($id === '' ? ($c['Default'] ?? '') : '');
            $req = $c['Null'] === 'NO' && $c['Default'] === null ? 'required' : '';
            $wide = preg_match('/text/i', $c['Type']) ? 'col-12' : 'col-md-6';
            echo '<div class="' . $wide . '"><label class="form-label fw-semibold">' . e(label($f)) . ($req ? ' <span class="text-danger">*</span>' : '') . '</label>';
            if (isset($ref[$f])) {
                echo '<select class="form-select" name="' . e($f) . '" ' . $req . '><option value="">- pilih -</option>';
                foreach ($opsi[$f] as $o) echo '<option value="' . e($o['v']) . '" ' . ((string)$o['v'] === (string)$val ? 'selected' : '') . '>' . e($o['l']) . '</option>';
                echo '</select>';
            } elseif (preg_match("/^enum\((.*)\)$/i", $c['Type'], $m)) {
                echo '<select class="form-select" name="' . e($f) . '" ' . $req . '><option value="">- pilih -</option>';
                foreach (str_getcsv($m[1], ',', "'") as $o) echo '<option ' . ($o === (string)$val ? 'selected' : '') . '>' . e($o) . '</option>';
                echo '</select>';
            } elseif (preg_match('/text/i', $c['Type'])) {
                echo '<textarea class="form-control" rows="3" name="' . e($f) . '" ' . $req . '>' . e($val) . '</textarea>';
            } else {
                $type = preg_match('/^date$/i', $c['Type']) ? 'date' : (preg_match('/int|decimal|float|double/i', $c['Type']) ? 'number" step="any' : 'text');
                echo '<input class="form-control" type="' . $type . '" name="' . e($f) . '" value="' . e($val) . '" ' . $req . '>';
            }
            echo '</div>';
        endforeach; ?>
        </div>
        <div class="mt-4">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
            <a class="btn btn-outline-secondary" href="<?= $self ?>">Batal</a>
        </div>
    </form>
</div>
<?php
else:
    $q = trim((string)($_GET['q'] ?? ''));
    $hal = max(1, (int)($_GET['hal'] ?? 1)); $per = 20;
    $where = []; $types = ''; $params = [];
    if ($batasSql) { $where[] = $batasSql; $types .= $batasT; $params = array_merge($params, $batasP); }
    if ($q !== '') { $where[] = "(d.nama LIKE ? OR d.nip LIKE ?)"; $types .= 'ss'; $params[] = "%$q%"; $params[] = "%$q%"; }
    $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int)(db_all($conn, "SELECT COUNT(*) n FROM dosen d$w", $types, $params)[0]['n'] ?? 0);
    $hal = min($hal, max(1, (int)ceil($total / $per)));
    $off = ($hal - 1) * $per;
    $rows = db_all($conn, dosen_base_sql() . "$w ORDER BY d.nama LIMIT $per OFFSET $off", $types, $params);
    $hal_total = max(1, (int)ceil($total / $per));
?>
<div class="card-box p-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3 no-print">
        <form class="d-flex gap-2" method="get">
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari nama / NIP...">
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <?php if (can_write()): ?><a class="btn btn-primary" href="dosen.php?aksi=form"><i class="bi bi-plus-lg me-1"></i>Tambah dosen</a><?php endif; ?>
    </div>
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Nama</th><th>NIP</th><th>Jabatan</th><th>Pangkat</th><th>Program Studi</th><th>Status</th><?php if (can_write()): ?><th class="text-end no-print">Aksi</th><?php endif; ?></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada data dosen.</td></tr><?php endif; ?>
        <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td><?= $off + $i + 1 ?></td>
                <td class="fw-semibold"><?= e($r['nama'] ?? '-') ?></td>
                <td><?= e($r['nip'] ?? '-') ?></td>
                <td><?= e($r['jabatan'] ?? '-') ?></td>
                <td><?= e($r['pangkat'] ?? '-') ?></td>
                <td><?= e($r['program_studi'] ?? '-') ?></td>
                <td><?= e($r['aktivitas'] ?? '-') ?></td>
                <?php if (can_write()): ?>
                <td class="text-end text-nowrap no-print">
                    <a class="btn btn-sm btn-outline-primary" href="dosen.php?aksi=form&id=<?= urlencode((string)$r['id_dosen']) ?>"><i class="bi bi-pencil"></i></a>
                    <form method="post" action="dosen.php" class="d-inline" onsubmit="return confirm('Hapus dosen ini?')">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= e($r['id_dosen']) ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($hal_total > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3 no-print">
        <span class="text-muted small"><?= $total ?> dosen &middot; halaman <?= $hal ?> dari <?= $hal_total ?></span>
        <div>
            <?php if ($hal > 1): ?><a class="btn btn-sm btn-outline-secondary" href="?q=<?= urlencode($q) ?>&hal=<?= $hal - 1 ?>">&laquo; Sebelumnya</a><?php endif; ?>
            <?php if ($hal < $hal_total): ?><a class="btn btn-sm btn-outline-secondary" href="?q=<?= urlencode($q) ?>&hal=<?= $hal + 1 ?>">Berikutnya &raquo;</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif;
require __DIR__ . '/includes/layout_footer.php'; ?>
