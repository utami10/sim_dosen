<?php
// profil.php - Ubah foto profil (disimpan di uploads/avatar/user_{id}.ext, tanpa ubah database)
// Letakkan di /htdocs/sim_dosen1/ (sejajar dengan login.php)
require_once __DIR__ . '/includes/rbac.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$dir    = __DIR__ . '/uploads/avatar';
$error  = '';
$ok     = '';

function hapus_foto_lama(string $dir, int $id): void {
    foreach (glob($dir . '/user_' . $id . '.*') ?: [] as $f) { @unlink($f); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!post_csrf_ok()) {
        $error = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
    } elseif (isset($_POST['hapus'])) {
        hapus_foto_lama($dir, $userId);
        $ok = 'Foto profil dihapus.';
    } else {
        $f = $_FILES['foto'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Pilih foto terlebih dahulu.';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload gagal (kode ' . (int)$f['error'] . '). Coba foto yang lebih kecil.';
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            $error = 'Ukuran foto maksimal 2 MB.';
        } else {
            $info = @getimagesize($f['tmp_name']);
            $map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!$info || !isset($map[$info['mime']])) {
                $error = 'File harus berupa gambar JPG, PNG, atau WEBP.';
            } else {
                if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                    $error = 'Folder uploads/avatar tidak bisa dibuat. Buat manual lewat File Manager.';
                } else {
                    hapus_foto_lama($dir, $userId);
                    $tujuan = $dir . '/user_' . $userId . '.' . $map[$info['mime']];
                    if (move_uploaded_file($f['tmp_name'], $tujuan)) {
                        @chmod($tujuan, 0644);
                        $ok = 'Foto profil berhasil diperbarui.';
                    } else {
                        $error = 'Foto gagal disimpan di server.';
                    }
                }
            }
        }
    }
}

$files   = glob($dir . '/user_' . $userId . '.*') ?: [];
$fotoUrl = $files ? 'uploads/avatar/' . basename($files[0]) . '?v=' . filemtime($files[0]) : '';
$inisialProfil = strtoupper(mb_substr($_SESSION['nama_lengkap'] ?? 'U', 0, 1));

$page_title = 'Foto Profil';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="card-box p-4" style="max-width:460px">
    <?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

    <div class="text-center mb-4">
        <div id="previewBox" style="width:140px;height:140px;border-radius:50%;overflow:hidden;margin:0 auto;background:#1458b8;color:#fff;display:grid;place-items:center;font-size:3.5rem;font-weight:700">
            <?php if ($fotoUrl): ?>
                <img id="previewImg" src="<?= e($fotoUrl) ?>" alt="Foto profil" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
                <span id="previewInisial"><?= e($inisialProfil) ?></span>
            <?php endif; ?>
        </div>
        <div class="fw-semibold mt-3"><?= e($_SESSION['nama_lengkap'] ?? '') ?></div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="form-label fw-semibold">Pilih Foto Baru</label>
        <input type="file" name="foto" id="foto" class="form-control" accept="image/jpeg,image/png,image/webp">
        <div class="form-text mb-3">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Simpan Foto</button>
        <?php if ($fotoUrl): ?>
            <button type="submit" name="hapus" value="1" class="btn btn-outline-danger ms-1" onclick="return confirm('Hapus foto profil?')"><i class="bi bi-trash me-1"></i>Hapus Foto</button>
        <?php endif; ?>
    </form>
</div>

<script>
document.getElementById('foto').addEventListener('change', function () {
    var file = this.files[0];
    if (!file) return;
    var box = document.getElementById('previewBox');
    box.innerHTML = '<img src="' + URL.createObjectURL(file) + '" style="width:100%;height:100%;object-fit:cover" alt="">';
});
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>