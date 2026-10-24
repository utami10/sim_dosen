<?php
// REST data master: /api/master.php?t=jabatan|kepangkatan|pendidikan|rumpun|status_kepegawaian|penempatan
//   tanpa id: GET (daftar), POST (tambah)   |   dengan &id=ID: GET, PUT/PATCH, DELETE
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/master_config.php';
$u = api_init();
if ($u['role'] === ROLE_DOSEN) api_err(403, 'Peran Anda tidak boleh mengakses data master.');
$m = $_SERVER['REQUEST_METHOD'];
$cfg = master_config();
$t = (string)($_GET['t'] ?? '');
if (!isset($cfg[$t])) api_err(404, 'Data master tidak dikenal. Pilihan: ' . implode(', ', array_keys($cfg)) . '.');
[$tbl, $pk, , $fields] = $cfg[$t];
$id = (int)($_GET['id'] ?? 0);

function find_row(mysqli $c, string $tbl, string $pk, int $id): ?array
{
    return db_all($c, "SELECT * FROM `$tbl` WHERE `$pk` = ?", 'i', [$id])[0] ?? null;
}

try {
    if ($m === 'GET') {
        if ($id > 0) { $r = find_row($conn, $tbl, $pk, $id); $r ? api_ok($r) : api_err(404, 'Data tidak ditemukan.'); }
        $rows = db_all($conn, "SELECT * FROM `$tbl` ORDER BY `$pk`");
        api_ok($rows, ['total' => count($rows)]);
    }
    if ($m === 'POST') {
        [$d, $ty, $err] = master_validate(api_body(), $fields, true);
        if ($err) api_err(422, 'Validasi gagal.', $err);
        $vals = array_values($d);
        $s = $conn->prepare("INSERT INTO `$tbl` (`" . implode('`, `', array_keys($d)) . '`) VALUES (' . implode(', ', array_fill(0, count($d), '?')) . ')');
        $s->bind_param($ty, ...$vals);
        $s->execute();
        api_ok(find_row($conn, $tbl, $pk, (int)$s->insert_id), [], 201);
    }
    if ($id <= 0) api_err(400, 'Parameter id wajib diisi.');
    if (!find_row($conn, $tbl, $pk, $id)) api_err(404, 'Data tidak ditemukan.');
    if ($m === 'DELETE') {
        $s = $conn->prepare("DELETE FROM `$tbl` WHERE `$pk` = ?");
        $s->bind_param('i', $id);
        $s->execute();
        api_ok([$pk => $id, 'dihapus' => true]);
    }
    [$d, $ty, $err] = master_validate(api_body(), $fields, false);
    if ($err) api_err(422, 'Validasi gagal.', $err);
    if (!$d) api_err(422, 'Tidak ada field yang diubah.');
    $vals = array_values($d); $vals[] = $id; $ty .= 'i';
    $s = $conn->prepare("UPDATE `$tbl` SET " . implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($d))) . " WHERE `$pk` = ?");
    $s->bind_param($ty, ...$vals);
    $s->execute();
    api_ok(find_row($conn, $tbl, $pk, $id));
} catch (mysqli_sql_exception $e) {
    api_db_error($e);
}
