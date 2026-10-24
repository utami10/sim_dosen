<?php
// includes/api.php - fondasi REST API: autentikasi, respons JSON, penanganan error.
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/../koneksi.php';
$conn->set_charset('utf8mb4');

function api_out(int $code, array $body)
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}
function api_ok($data, array $meta = [], int $code = 200)
{
    $b = ['status' => 'success', 'data' => $data];
    if ($meta) $b['meta'] = $meta;
    api_out($code, $b);
}
function api_err(int $code, string $msg, array $errors = [])
{
    $b = ['status' => 'error', 'message' => $msg];
    if ($errors) $b['errors'] = $errors;
    api_out($code, $b);
}

/** Pengguna dari token API (Bearer / X-API-Key) atau, jika tidak ada, dari sesi login. */
function api_user(mysqli $c): ?array
{
    $tok = null;
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', $h, $m)) $tok = $m[1];
    elseif (!empty($_SERVER['HTTP_X_API_KEY']) && preg_match('/^[A-Fa-f0-9]{64}$/', $_SERVER['HTTP_X_API_KEY'])) $tok = $_SERVER['HTTP_X_API_KEY'];

    if ($tok !== null) {
        $r = db_all($c, 'SELECT p.id_pengguna, p.id_dosen, p.username, r.nama_role FROM api_token t
                         JOIN pengguna p ON p.id_pengguna = t.id_pengguna
                         JOIN roles r ON r.id_role = p.id_role
                         WHERE t.token_hash = ? AND p.aktif = 1 LIMIT 1', 's', [hash('sha256', $tok)]);
        return $r ? ['id' => (int)$r[0]['id_pengguna'], 'id_dosen' => (int)$r[0]['id_dosen'], 'nama' => $r[0]['username'], 'role' => $r[0]['nama_role'], 'via' => 'token'] : null;
    }
    if (isset($_SESSION['user_id'], $_SESSION['role'])) {
        return ['id' => (int)$_SESSION['user_id'], 'id_dosen' => (int)($_SESSION['id_dosen'] ?? 0), 'nama' => $_SESSION['nama_lengkap'] ?? '', 'role' => $_SESSION['role'], 'via' => 'session'];
    }
    return null;
}

/**
 * Siapkan endpoint: header JSON, validasi metode, autentikasi, dan hak akses.
 * GET boleh lewat sesi atau token; metode tulis wajib token + peran admin/operator
 * (sesi cookie tidak dipakai untuk menulis agar aman dari CSRF).
 */
function api_init(): array
{
    global $conn;
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    set_exception_handler(function (Throwable $e) {
        error_log('API: ' . $e->getMessage());
        api_err(500, 'Terjadi kesalahan pada server.');
    });
    $m = $_SERVER['REQUEST_METHOD'];
    if (!in_array($m, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) api_err(405, 'Metode tidak didukung.');
    $u = api_user($conn);
    if (!$u) api_err(401, 'Tidak terautentikasi. Kirim header Authorization: Bearer <token>.');
    if ($m !== 'GET') {
        if ($u['via'] !== 'token') api_err(403, 'Metode tulis hanya bisa memakai token API.');
        if (!in_array($u['role'], [ROLE_ADMIN, ROLE_OPERATOR], true)) api_err(403, 'Peran Anda tidak boleh mengubah data.');
    }
    return $u;
}

function api_body(): array
{
    $b = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($b)) api_err(400, 'Body harus berupa JSON object yang valid.');
    foreach ($b as $v) if (!is_scalar($v) && $v !== null) api_err(422, 'Semua nilai harus berupa teks, angka, atau null.');
    return $b;
}

function api_db_error(mysqli_sql_exception $e)
{
    error_log('API DB: ' . $e->getMessage());
    switch ((int)$e->getCode()) {
        case 1062: api_err(409, 'Data duplikat: nilai tersebut sudah dipakai.');
        case 1451: api_err(409, 'Data masih dipakai oleh data lain sehingga tidak bisa dihapus.');
        case 1452: api_err(422, 'Referensi tidak valid: id pada salah satu field master tidak ditemukan.');
        default:   api_err(500, 'Terjadi kesalahan pada server.');
    }
}
