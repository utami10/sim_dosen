<?php
// api_docs.php - dokumentasi API + pembuatan token pribadi.
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/koneksi.php';
require_role(ROLE_ADMIN, ROLE_OPERATOR);
$conn->set_charset('utf8mb4');
$saya = (int)$_SESSION['user_id'];
$tokenBaru = null;
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!post_csrf_ok()) { flash_set('danger', 'Sesi formulir tidak valid. Muat ulang halaman.'); redirect('api_docs.php'); }
    try {
        if (($_POST['aksi'] ?? '') === 'buat') {
            $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 100);
            $n = (int)db_all($conn, 'SELECT COUNT(*) n FROM api_token WHERE id_pengguna = ?', 'i', [$saya])[0]['n'];
            if ($label === '') $galat = 'Label token wajib diisi.';
            elseif ($n >= 5) $galat = 'Maksimal 5 token aktif. Cabut token lama terlebih dahulu.';
            else {
                $tokenBaru = bin2hex(random_bytes(32)); // 64 karakter hex; hanya hash-nya yang disimpan
                $h = hash('sha256', $tokenBaru);
                $s = $conn->prepare('INSERT INTO api_token (id_pengguna, label, token_hash) VALUES (?,?,?)');
                $s->bind_param('iss', $saya, $label, $h);
                $s->execute();
            }
        } elseif (($_POST['aksi'] ?? '') === 'cabut') {
            $s = $conn->prepare('DELETE FROM api_token WHERE id_token = ? AND id_pengguna = ?');
            $idt = (int)($_POST['id'] ?? 0);
            $s->bind_param('ii', $idt, $saya);
            $s->execute();
            flash_set('success', 'Token dicabut.');
            redirect('api_docs.php');
        }
    } catch (mysqli_sql_exception $e) {
        error_log('api_docs.php: ' . $e->getMessage());
        $galat = 'Tabel token belum ada. Impor sql/skema_api.sql terlebih dahulu.';
        $tokenBaru = null;
    }
}

$tokens = [];
try { $tokens = db_all($conn, 'SELECT id_token, label, dibuat_pada FROM api_token WHERE id_pengguna = ? ORDER BY id_token DESC', 'i', [$saya]); }
catch (Throwable $e) { $galat = $galat ?: 'Tabel token belum ada. Impor sql/skema_api.sql terlebih dahulu.'; }

$skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $skema . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api';

$endpoint = [
    ['GET', 'dosen.php', 'Daftar dosen. Parameter: q, prodi, aktif=1, page, per (maks 100).'],
    ['GET', 'dosen.php?id=1', 'Detail satu dosen.'],
    ['POST', 'dosen.php', 'Tambah dosen. Wajib: nama.'],
    ['PUT / PATCH', 'dosen.php?id=1', 'Ubah field yang dikirim saja.'],
    ['DELETE', 'dosen.php?id=1', 'Hapus dosen.'],
    ['GET', 'master.php?t=jabatan', 'Daftar data master. Nilai t: jabatan, kepangkatan, pendidikan, rumpun, status_kepegawaian, penempatan.'],
    ['POST / PUT / DELETE', 'master.php?t=jabatan[&id=1]', 'Tambah, ubah, atau hapus data master.'],
];
$page_title = 'Dokumentasi API';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="row g-3">
<div class="col-lg-5">
    <div class="card-box p-4 mb-3">
        <h2 class="h6 fw-bold mb-3">Token API pribadi</h2>
        <?php if ($galat): ?><div class="alert alert-danger"><?= e($galat) ?></div><?php endif; ?>
        <?php if ($tokenBaru): ?>
            <div class="alert alert-success"><div class="fw-semibold mb-1">Token baru &mdash; salin sekarang, tidak akan ditampilkan lagi.</div>
            <code class="user-select-all text-break"><?= e($tokenBaru) ?></code></div>
        <?php endif; ?>
        <form method="post" action="api_docs.php" class="d-flex gap-2 mb-3">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="buat">
            <input class="form-control" name="label" maxlength="100" placeholder="Label, mis. Postman" required>
            <button class="btn btn-primary text-nowrap" type="submit">Buat token</button>
        </form>
        <?php foreach ($tokens as $t): ?>
            <div class="d-flex justify-content-between align-items-center border-top py-2">
                <div><div class="fw-semibold"><?= e($t['label']) ?></div><div class="text-muted small">Dibuat <?= e($t['dibuat_pada']) ?></div></div>
                <form method="post" action="api_docs.php" onsubmit="return confirm('Cabut token ini?')">
                    <?= csrf_field() ?><input type="hidden" name="aksi" value="cabut"><input type="hidden" name="id" value="<?= (int)$t['id_token'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Cabut</button></form>
            </div>
        <?php endforeach; if (!$tokens && !$galat): ?><p class="text-muted small mb-0">Belum ada token.</p><?php endif; ?>
    </div>
    <div class="card-box p-4">
        <h2 class="h6 fw-bold mb-3">Aturan umum</h2>
        <ul class="small mb-0 ps-3">
            <li>Kirim token di header <code>Authorization: Bearer &lt;token&gt;</code> atau <code>X-API-Key</code>.</li>
            <li>GET juga bisa dibuka di browser saat Anda sedang login.</li>
            <li>POST, PUT, PATCH, DELETE hanya dengan token dan peran admin/operator.</li>
            <li>Body berformat JSON. Respons: <code>status</code>, <code>data</code>, <code>meta</code>, atau <code>message</code> bila gagal.</li>
            <li>Kode status: 200, 201, 400, 401, 403, 404, 409, 422, 500.</li>
        </ul>
    </div>
</div>
<div class="col-lg-7">
    <div class="card-box p-4 mb-3">
        <h2 class="h6 fw-bold mb-3">Endpoint</h2>
        <div class="small text-muted mb-2">Base URL: <code><?= e($base) ?></code></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Metode</th><th>Path</th><th>Keterangan</th></tr></thead><tbody>
            <?php foreach ($endpoint as [$mt, $pt, $ket]): ?><tr><td class="text-nowrap"><span class="badge text-bg-primary"><?= e($mt) ?></span></td><td><code><?= e($pt) ?></code></td><td class="small"><?= e($ket) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
    </div>
    <div class="card-box p-4">
        <h2 class="h6 fw-bold mb-3">Contoh (curl)</h2>
<pre class="bg-light rounded p-3 small mb-0" style="white-space:pre-wrap"><?= e(
"# Daftar dosen aktif di prodi id 1
curl -H \"Authorization: Bearer TOKEN\" \"$base/dosen.php?aktif=1&prodi=1\"

# Tambah dosen
curl -X POST -H \"Authorization: Bearer TOKEN\" -H \"Content-Type: application/json\" \\
  -d '{\"nama\":\"Budi Santoso, M.Kom\",\"nip\":\"198001012005011001\",\"status_serdos\":\"Sudah\",\"id_jabatan\":3,\"status_aktivitas\":\"Aktif\"}' \\
  \"$base/dosen.php\"

# Ubah status serdos
curl -X PATCH -H \"Authorization: Bearer TOKEN\" -H \"Content-Type: application/json\" \\
  -d '{\"status_serdos\":\"Belum\"}' \"$base/dosen.php?id=1\"

# Hapus
curl -X DELETE -H \"Authorization: Bearer TOKEN\" \"$base/dosen.php?id=1\"") ?></pre>
    </div>
</div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php';
