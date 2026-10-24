<?php
// REST: /api/dosen.php            GET (daftar), POST (tambah)
//       /api/dosen.php?id=ID      GET, PUT/PATCH (ubah field yang dikirim), DELETE
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/dosen_meta.php';
$u = api_init();
$m = $_SERVER['REQUEST_METHOD'];
$id = (int)($_GET['id'] ?? 0);
$hanyaSaya = $u['role'] === ROLE_DOSEN; // peran DOSEN hanya boleh membaca datanya sendiri

try {
    if ($m === 'GET') {
        if ($hanyaSaya) $id = $u['id_dosen'] ?: -1;
        if ($id !== 0) {
            $r = $id > 0 ? dosen_find($conn, $id) : null;
            $r ? api_ok($r) : api_err(404, 'Dosen tidak ditemukan.');
        }
        $w = []; $t = ''; $p = [];
        if (($q = trim((string)($_GET['q'] ?? ''))) !== '') { $w[] = '(d.nama LIKE ? OR d.nip LIKE ? OR d.nuptk LIKE ?)'; $t .= 'sss'; array_push($p, "%$q%", "%$q%", "%$q%"); }
        if ((int)($_GET['prodi'] ?? 0) > 0) { $w[] = 'd.id_penempatan = ?'; $t .= 'i'; $p[] = (int)$_GET['prodi']; }
        if (($_GET['aktif'] ?? '') === '1') $w[] = "d.status_aktivitas = 'Aktif'";
        $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
        $per = min(100, max(1, (int)($_GET['per'] ?? 20)));
        $total = (int)db_all($conn, 'SELECT COUNT(*) n FROM dosen d' . $where, $t, $p)[0]['n'];
        $pages = max(1, (int)ceil($total / $per));
        $page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
        $rows = db_all($conn, dosen_base_sql() . $where . " ORDER BY d.nama LIMIT $per OFFSET " . (($page - 1) * $per), $t, $p);
        api_ok($rows, ['total' => $total, 'page' => $page, 'per' => $per, 'pages' => $pages]);
    }

    if ($m === 'POST') {
        [$d, $t, $err] = dosen_validate(api_body(), true);
        if ($err) api_err(422, 'Validasi gagal.', $err);
        api_ok(dosen_find($conn, dosen_save($conn, $d, $t)), [], 201);
    }

    if ($id <= 0) api_err(400, 'Parameter id wajib diisi.');
    if (!dosen_find($conn, $id)) api_err(404, 'Dosen tidak ditemukan.');

    if ($m === 'DELETE') {
        $s = $conn->prepare('DELETE FROM dosen WHERE id_dosen = ?');
        $s->bind_param('i', $id);
        $s->execute();
        api_ok(['id_dosen' => $id, 'dihapus' => true]);
    }

    // PUT / PATCH
    [$d, $t, $err] = dosen_validate(api_body(), false);
    if ($err) api_err(422, 'Validasi gagal.', $err);
    if (!$d) api_err(422, 'Tidak ada field yang diubah.');
    dosen_save($conn, $d, $t, $id);
    api_ok(dosen_find($conn, $id));
} catch (mysqli_sql_exception $e) {
    api_db_error($e);
}
