<?php
// includes/rbac.php - RBAC, flash message & helper query (disesuaikan dengan tabel roles/pengguna/dosen).
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // error SQL menjadi exception

// Nilai sama persis dengan roles.nama_role di database.
defined('ROLE_ADMIN')    || define('ROLE_ADMIN', 'ADMIN');
defined('ROLE_OPERATOR') || define('ROLE_OPERATOR', 'OPERATOR_PRODI');
defined('ROLE_FAKULTAS') || define('ROLE_FAKULTAS', 'FAKULTAS');
defined('ROLE_PIMPINAN') || define('ROLE_PIMPINAN', 'PIMPINAN');
defined('ROLE_DOSEN')    || define('ROLE_DOSEN', 'DOSEN');

function rbac_logged_in(): bool { return isset($_SESSION['user_id'], $_SESSION['role']); }
function require_login(): void { if (!rbac_logged_in()) redirect('login.php'); }
function has_role(string ...$roles): bool { return rbac_logged_in() && in_array($_SESSION['role'], $roles, true); }
function can_write(): bool { return has_role(ROLE_ADMIN, ROLE_OPERATOR); }

function require_role(string ...$roles): void
{
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        exit('403 - Anda tidak memiliki akses ke halaman ini.');
    }
}

function flash_set(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }

function flash_show(): string
{
    if (empty($_SESSION['flash'])) return '';
    [$t, $m] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert alert-' . e($t) . ' alert-dismissible fade show" role="alert">' . e($m)
         . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
}

function post_csrf_ok(): bool
{
    $t = $_POST['csrf_token'] ?? null;
    return csrf_validate(is_string($t) ? $t : null);
}

/** SELECT dengan prepared statement; mengembalikan array of assoc. */
function db_all(mysqli $c, string $sql, string $types = '', array $params = []): array
{
    $s = $c->prepare($sql);
    if ($types !== '') $s->bind_param($types, ...$params);
    $s->execute();
    $r = $s->get_result();
    $rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    $s->close();
    return $rows;
}

/** SELECT dasar data dosen + semua tabel referensinya (tanpa WHERE). */
function dosen_base_sql(): string
{
    return "SELECT d.*, j.nama_jabatan AS jabatan,
                   CONCAT_WS(' ', k.nama_pangkat, CONCAT_WS('/', k.golongan, k.ruang)) AS pangkat,
                   pd.jenjang AS pendidikan, r.nama_rumpun AS rumpun,
                   sk.status_pegawai, sk.ikatan_kerja, d.status_aktivitas AS aktivitas,
                   pn.universitas, pn.fakultas, pn.program_studi
            FROM dosen d
            LEFT JOIN jabatan_fungsional j ON j.id_jabatan = d.id_jabatan
            LEFT JOIN kepangkatan k ON k.id_kepangkatan = d.id_kepangkatan
            LEFT JOIN pendidikan pd ON pd.id_pendidikan = d.id_pendidikan
            LEFT JOIN rumpun_ilmu r ON r.id_rumpun_ilmu = d.id_rumpun_ilmu
            LEFT JOIN status_kepegawaian sk ON sk.id_status_kepegawaian = d.id_status_kepegawaian
            LEFT JOIN penempatan pn ON pn.id_penempatan = d.id_penempatan";
}
