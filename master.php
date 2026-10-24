<?php
// master.php - CRUD generik untuk tabel master. Kolom dibaca otomatis dari database.
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/koneksi.php';
require_role(ROLE_ADMIN, ROLE_OPERATOR, ROLE_FAKULTAS, ROLE_PIMPINAN);
$conn->set_charset('utf8mb4');

// tabel => [judul, alias parameter ?t=]
$master = [
    'jabatan_fungsional' => ['Jabatan Fungsional',      ['jabatan', 'jabfung']],
    'kepangkatan'        => ['Kepangkatan',             ['pangkat', 'golongan']],
    'pendidikan'         => ['Pendidikan Terakhir',     ['pendidikan', 'jenjang']],
    'rumpun_ilmu'        => ['Rumpun Ilmu',             ['rumpun']],
    'status_kepegawaian' => ['Status & Ikatan Kerja',   ['status', 'ikatan', 'pegawai']],
    'penempatan'         => ['Penempatan (Prodi)',      ['penempatan', 'prodi', 'fakultas']],
];

$t = strtolower(trim((string)($_GET['t'] ?? $_POST['t'] ?? '')));
$table = null;
foreach ($master as $tbl => [$judul, $alias]) {
    if ($t === $tbl) { $table = $tbl; break; }
}
if ($table === null && $t !== '') {
    foreach ($master as $tbl => [$judul, $alias]) {
        foreach ($alias as $a) if (str_contains($t, $a)) { $table = $tbl; break 2; }
    }
}
if ($table === null) { flash_set('warning', 'Menu master tidak dikenali.'); redirect('dashboard.php'); }
$judul = $master[$table][0];
$tparam = e($_GET['t'] ?? $_POST['t'] ?? $table);

// ---- struktur tabel ----
$cols = $conn->query("SHOW COLUMNS FROM `$table`")->fetch_all(MYSQLI_ASSOC);
$pk = null;
foreach ($cols as $c) if ($c['Key'] === 'PRI') { $pk = $c['Field']; break; }
if ($pk === null) $pk = $cols[0]['Field'];
$edit = array_values(array_filter($cols, function ($c) {
    if (stripos($c['Extra'], 'auto_increment') !== false) return false;
    if (preg_match('/^(timestamp|datetime)/i', $c['Type']) && (stripos((string)$c['Default'], 'current_timestamp') !== false || stripos($c['Extra'], 'on update') !== false)) return false;
    return true;
}));
$self = 'master.php?t=' . rawurlencode($_GET['t'] ?? $_POST['t'] ?? $table);
function label(string $f): string { return ucwords(str_replace('_', ' ', preg_replace('/^id_/', '', $f))); }

// ---- aksi tulis ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_write()) { http_response_code(403); exit('403 - Hanya Admin/Operator yang boleh mengubah data.'); }
    if (!post_csrf_ok()) { flash_set('danger', 'Sesi tidak valid, coba lagi.'); redirect($self); }
    $aksi = $_POST['aksi'] ?? '';
    $id = (string)($_POST['id'] ?? '');

    if ($aksi === 'hapus') {
        try {
            $s = $conn->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
            $s->bind_param('s', $id); $s->execute(); $s->close();
            flash_set('success', 'Data berhasil dihapus.');
        } catch (Throwable $ex) {
            flash_set('danger', 'Data tidak bisa dihapus karena masih dipakai data lain.');
        }
        redirect($self);
    }

    if ($aksi === 'simpan') {
        $names = []; $vals = [];
        foreach ($edit as $c) {
            $f = $c['Field'];
            $v = trim((string)($_POST[$f] ?? ''));
            $numeric = (bool)preg_match('/int|decimal|float|double/i', $c['Type']);
            if ($v === '') {
                if ($c['Null'] === 'YES') $v = null;
                elseif ($c['Default'] !== null) $v = $c['Default'];
                else {
                    flash_set('danger', 'Kolom "' . label($f) . '" wajib diisi.');
                    redirect($self . '&aksi=form' . ($id !== '' ? '&id=' . urlencode($id) : ''));
                }
            }
            $names[] = $f; $vals[] = $v;
        }
        try {
            if ($id === '') {
                $sql = "INSERT INTO `$table` (`" . implode('`,`', $names) . "`) VALUES (" . implode(',', array_fill(0, count($names), '?')) . ")";
                $s = $conn->prepare($sql);
                $s->bind_param(str_repeat('s', count($vals)), ...$vals);
            } else {
                $sql = "UPDATE `$table` SET `" . implode('`=?, `', $names) . "`=? WHERE `$pk` = ?";
                $s = $conn->prepare($sql);
                $vals[] = $id;
                $s->bind_param(str_repeat('s', count($vals)), ...$vals);
            }
            $s->execute(); $s->close();
            flash_set('success', 'Data berhasil disimpan.');
        } catch (Throwable $ex) {
            error_log('master.php: ' . $ex->getMessage());
            flash_set('danger', 'Gagal menyimpan: ' . $ex->getMessage());
        }
        redirect($self);
    }
    redirect($self);
}

// ---- tampilan ----
$page_title = $judul;
require __DIR__ . '/includes/layout_header.php';

if (($_GET['aksi'] ?? '') === 'form' && can_write()):
    $row = [];
    $id = (string)($_GET['id'] ?? '');
    if ($id !== '') $row = db_all($conn, "SELECT * FROM `$table` WHERE `$pk` = ?", 's', [$id])[0] ?? [];
?>
<div class="card-box p-4" style="max-width:720px">
    <h2 class="h5 fw-bold mb-3"><?= $id !== '' ? 'Ubah' : 'Tambah' ?> <?= e($judul) ?></h2>
    <form method="post" action="<?= e($self) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= e($id) ?>">
        <?php foreach ($edit as $c):
            $f = $c['Field']; $val = $row[$f] ?? ($id === '' ? ($c['Default'] ?? '') : '');
            $req = $c['Null'] === 'NO' && $c['Default'] === null ? 'required' : '';
            echo '<div class="mb-3"><label class="form-label fw-semibold">' . e(label($f)) . ($req ? ' <span class="text-danger">*</span>' : '') . '</label>';
            if (preg_match("/^enum\((.*)\)$/i", $c['Type'], $m)) {
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
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a class="btn btn-outline-secondary" href="<?= e($self) ?>">Batal</a>
    </form>
</div>
<?php
else:
    $q = trim((string)($_GET['q'] ?? ''));
    $names = array_map(fn($c) => '`' . $c['Field'] . '`', $cols);
    if ($q !== '') $rows = db_all($conn, "SELECT * FROM `$table` WHERE CONCAT_WS(' ', " . implode(',', $names) . ") LIKE ? ORDER BY `$pk`", 's', ['%' . $q . '%']);
    else $rows = db_all($conn, "SELECT * FROM `$table` ORDER BY `$pk`");
?>
<div class="card-box p-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3 no-print">
        <form class="d-flex gap-2" method="get">
            <input type="hidden" name="t" value="<?= $tparam ?>">
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari...">
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <?php if (can_write()): ?><a class="btn btn-primary" href="<?= e($self) ?>&aksi=form"><i class="bi bi-plus-lg me-1"></i>Tambah</a><?php endif; ?>
    </div>
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th>
            <?php foreach ($cols as $c) if ($c['Field'] !== $pk) echo '<th>' . e(label($c['Field'])) . '</th>'; ?>
            <?php if (can_write()): ?><th class="text-end no-print">Aksi</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="<?= count($cols) + 1 ?>" class="text-center text-muted py-4">Belum ada data.</td></tr><?php endif; ?>
        <?php foreach ($rows as $i => $r): ?>
            <tr><td><?= $i + 1 ?></td>
                <?php foreach ($cols as $c) if ($c['Field'] !== $pk) echo '<td>' . e(mb_strimwidth((string)($r[$c['Field']] ?? '-'), 0, 80, '...')) . '</td>'; ?>
                <?php if (can_write()): ?>
                <td class="text-end text-nowrap no-print">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e($self) ?>&aksi=form&id=<?= urlencode((string)$r[$pk]) ?>"><i class="bi bi-pencil"></i></a>
                    <form method="post" action="<?= e($self) ?>" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= e($r[$pk]) ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif;
require __DIR__ . '/includes/layout_footer.php'; ?>
