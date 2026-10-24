<?php
// pengguna.php - kelola akun (tabel pengguna + roles), khusus ADMIN.
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/koneksi.php';
require_role(ROLE_ADMIN);
$conn->set_charset('utf8mb4');
$saya = (int)$_SESSION['user_id'];
$roles = db_all($conn, 'SELECT id_role, nama_role FROM roles ORDER BY id_role');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!post_csrf_ok()) { flash_set('danger', 'Sesi formulir tidak valid. Muat ulang halaman.'); redirect('pengguna.php'); }
    $aksi = (string)($_POST['aksi'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($aksi === 'hapus' && $id > 0) {
            if ($id === $saya) { flash_set('danger', 'Anda tidak bisa menghapus akun yang sedang dipakai.'); redirect('pengguna.php'); }
            $s = $conn->prepare('DELETE FROM pengguna WHERE id_pengguna = ?');
            $s->bind_param('i', $id);
            $s->execute();
            flash_set('success', 'Pengguna dihapus.');
        } elseif ($aksi === 'simpan') {
            $nama  = mb_substr(trim((string)($_POST['nama_tampilan'] ?? '')), 0, 150);
            $user  = trim((string)($_POST['username'] ?? ''));
            $email = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 190);
            $email = $email === '' ? null : $email;
            $idRole = (int)($_POST['id_role'] ?? 0);
            $idDosen = (int)($_POST['id_dosen'] ?? 0);
            $idDosen = $idDosen > 0 ? $idDosen : null;
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            $pass  = (string)($_POST['password'] ?? '');
            $back  = 'pengguna.php?aksi=form' . ($id ? "&id=$id" : '');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $user)) { flash_set('danger', 'Username 3-100 karakter: huruf, angka, titik, garis bawah, atau strip.'); redirect($back); }
            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash_set('danger', 'Format email tidak valid.'); redirect($back); }
            if (!in_array($idRole, array_map('intval', array_column($roles, 'id_role')), true)) { flash_set('danger', 'Peran tidak valid.'); redirect($back); }
            if ($id === 0 && strlen($pass) < 8) { flash_set('danger', 'Password minimal 8 karakter.'); redirect($back); }
            if ($id > 0 && $pass !== '' && strlen($pass) < 8) { flash_set('danger', 'Password baru minimal 8 karakter.'); redirect($back); }
            if ($id === $saya) { // akun sendiri tidak boleh dinonaktifkan / diturunkan perannya
                $aktif = 1;
                $idRole = (int)db_all($conn, 'SELECT id_role FROM pengguna WHERE id_pengguna = ?', 'i', [$saya])[0]['id_role'];
            }
            $hash = $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : '';
            if ($id > 0 && $pass === '') {
                $s = $conn->prepare('UPDATE pengguna SET nama_tampilan=?, username=?, email=?, id_role=?, id_dosen=?, aktif=? WHERE id_pengguna=?');
                $s->bind_param('sssiiii', $nama, $user, $email, $idRole, $idDosen, $aktif, $id);
            } elseif ($id > 0) {
                $s = $conn->prepare('UPDATE pengguna SET nama_tampilan=?, username=?, email=?, id_role=?, id_dosen=?, aktif=?, password_hash=? WHERE id_pengguna=?');
                $s->bind_param('sssiiisi', $nama, $user, $email, $idRole, $idDosen, $aktif, $hash, $id);
            } else {
                $s = $conn->prepare('INSERT INTO pengguna (nama_tampilan, username, email, id_role, id_dosen, aktif, password_hash) VALUES (?,?,?,?,?,?,?)');
                $s->bind_param('sssiiis', $nama, $user, $email, $idRole, $idDosen, $aktif, $hash);
            }
            $s->execute();
            flash_set('success', 'Pengguna berhasil disimpan.');
        }
    } catch (mysqli_sql_exception $e) {
        error_log('pengguna.php: ' . $e->getMessage());
        flash_set('danger', (int)$e->getCode() === 1062 ? 'Username atau email sudah dipakai.' : 'Data gagal disimpan. Periksa isian.');
    }
    redirect('pengguna.php');
}

$form = ($_GET['aksi'] ?? '') === 'form';
$page_title = 'Pengguna';
require __DIR__ . '/includes/layout_header.php';

if ($form):
    $editId = (int)($_GET['id'] ?? 0);
    $r = ['nama_tampilan' => '', 'username' => '', 'email' => '', 'id_role' => 0, 'id_dosen' => '', 'aktif' => 1];
    if ($editId > 0) { $f = db_all($conn, 'SELECT * FROM pengguna WHERE id_pengguna = ?', 'i', [$editId]); if ($f) $r = $f[0]; else $editId = 0; }
    $dosen = db_all($conn, 'SELECT id_dosen, nama FROM dosen ORDER BY nama'); ?>
<div class="card-box p-4" style="max-width:640px">
    <h2 class="h5 fw-bold mb-4"><?= $editId ? 'Ubah' : 'Tambah' ?> pengguna</h2>
    <form method="post" action="pengguna.php">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $editId ?>">
        <div class="mb-3"><label class="form-label fw-semibold" for="nama_tampilan">Nama tampilan</label>
            <input class="form-control" id="nama_tampilan" name="nama_tampilan" maxlength="150" value="<?= e((string)$r['nama_tampilan']) ?>"></div>
        <div class="mb-3"><label class="form-label fw-semibold" for="username">Username</label>
            <input class="form-control" id="username" name="username" maxlength="100" value="<?= e((string)$r['username']) ?>" autocomplete="off" required></div>
        <div class="mb-3"><label class="form-label fw-semibold" for="email">Email <span class="text-muted fw-normal">(opsional)</span></label>
            <input class="form-control" type="email" id="email" name="email" maxlength="190" value="<?= e((string)$r['email']) ?>"></div>
        <div class="mb-3"><label class="form-label fw-semibold" for="password">Password <?= $editId ? '<span class="text-muted fw-normal">(kosongkan jika tidak diubah)</span>' : '' ?></label>
            <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" <?= $editId ? '' : 'required' ?>></div>
        <div class="mb-3"><label class="form-label fw-semibold" for="id_role">Peran</label>
            <select class="form-select" id="id_role" name="id_role">
                <?php foreach ($roles as $ro): ?><option value="<?= (int)$ro['id_role'] ?>" <?= (int)$r['id_role'] === (int)$ro['id_role'] ? 'selected' : '' ?>><?= e($ro['nama_role']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="mb-3"><label class="form-label fw-semibold" for="id_dosen">Terhubung ke dosen <span class="text-muted fw-normal">(wajib untuk peran DOSEN)</span></label>
            <select class="form-select" id="id_dosen" name="id_dosen"><option value="">- Tidak ada -</option>
                <?php foreach ($dosen as $d): ?><option value="<?= (int)$d['id_dosen'] ?>" <?= (string)$r['id_dosen'] === (string)$d['id_dosen'] ? 'selected' : '' ?>><?= e($d['nama']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-check mb-4"><input class="form-check-input" type="checkbox" id="aktif" name="aktif" value="1" <?= (int)$r['aktif'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="aktif">Akun aktif</label></div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a class="btn btn-light" href="pengguna.php">Batal</a>
    </form>
</div>
<?php else:
    $rows = db_all($conn, 'SELECT p.id_pengguna, p.nama_tampilan, p.username, p.aktif, p.login_terakhir, r.nama_role FROM pengguna p JOIN roles r ON r.id_role = p.id_role ORDER BY p.username'); ?>
<div class="card-box p-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><span class="text-muted"><?= count($rows) ?> pengguna</span>
        <a class="btn btn-primary" href="pengguna.php?aksi=form"><i class="bi bi-plus-lg me-1"></i>Tambah pengguna</a></div>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>No</th><th>Nama</th><th>Username</th><th>Peran</th><th>Login terakhir</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
        <?php foreach ($rows as $i => $p): ?>
            <tr><td><?= $i + 1 ?></td><td><?= e((string)$p['nama_tampilan']) ?><?= (int)$p['id_pengguna'] === $saya ? ' <span class="badge text-bg-light">Anda</span>' : '' ?></td>
                <td><?= e($p['username']) ?></td><td><?= e($p['nama_role']) ?></td><td class="small"><?= e((string)($p['login_terakhir'] ?: '-')) ?></td>
                <td><span class="badge text-bg-<?= (int)$p['aktif'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$p['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="pengguna.php?aksi=form&id=<?= (int)$p['id_pengguna'] ?>"><i class="bi bi-pencil"></i> Ubah</a>
                <?php if ((int)$p['id_pengguna'] !== $saya): ?>
                    <form method="post" action="pengguna.php" class="d-inline" onsubmit="return confirm('Hapus pengguna ini?')">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$p['id_pengguna'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Hapus</button></form>
                <?php endif; ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
</div>
<?php endif;
require __DIR__ . '/includes/layout_footer.php';
