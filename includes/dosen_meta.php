<?php
// includes/dosen_meta.php - definisi, validasi, dan penyimpanan data dosen (dipakai web & API).
const D_SERDOS    = ['Sudah', 'Belum', 'Tidak Berlaku', 'Belum Diketahui'];
const D_AKTIVITAS = ['Aktif', 'Tidak Aktif', 'Cuti', 'Pensiun', 'Belum Diketahui'];
const D_TEKS      = ['nomor_registrasi' => ['Nomor registrasi', 50], 'nuptk' => ['NUPTK', 30], 'nip' => ['NIP', 30]];

/** kolom FK di dosen => [tabel, kolom id, ekspresi label SQL, label form] */
function dosen_ref(): array
{
    return [
        'id_jabatan'            => ['jabatan_fungsional', 'id_jabatan', 'nama_jabatan', 'Jabatan fungsional'],
        'id_kepangkatan'        => ['kepangkatan', 'id_kepangkatan', "CONCAT_WS(' ', nama_pangkat, CONCAT_WS('/', golongan, ruang))", 'Kepangkatan'],
        'id_pendidikan'         => ['pendidikan', 'id_pendidikan', 'jenjang', 'Pendidikan terakhir'],
        'id_rumpun_ilmu'        => ['rumpun_ilmu', 'id_rumpun_ilmu', 'nama_rumpun', 'Rumpun ilmu'],
        'id_status_kepegawaian' => ['status_kepegawaian', 'id_status_kepegawaian', "CONCAT(status_pegawai, ' - ', ikatan_kerja)", 'Status pegawai & ikatan kerja'],
        'id_penempatan'         => ['penempatan', 'id_penempatan', "CONCAT(fakultas, ' - ', program_studi)", 'Penempatan (fakultas - prodi)'],
    ];
}

/** Validasi input. Hanya kunci yang dikirim diproses; saat $create, nama wajib. Mengembalikan [kolom=>nilai, tipe, galat]. */
function dosen_validate(array $b, bool $create): array
{
    $d = []; $t = ''; $err = [];
    if (array_key_exists('nama', $b)) {
        $v = trim((string)$b['nama']);
        if ($v === '') $err['nama'] = 'Nama wajib diisi.'; else { $d['nama'] = mb_substr($v, 0, 150); $t .= 's'; }
    } elseif ($create) $err['nama'] = 'Nama wajib diisi.';

    foreach (D_TEKS as $k => [$lbl, $max]) if (array_key_exists($k, $b)) {
        $v = trim((string)$b[$k]); $d[$k] = $v === '' ? null : mb_substr($v, 0, $max); $t .= 's';
    }
    foreach (['status_serdos' => D_SERDOS, 'status_aktivitas' => D_AKTIVITAS] as $k => $list) if (array_key_exists($k, $b)) {
        if (!in_array($b[$k], $list, true)) $err[$k] = 'Nilai harus salah satu dari: ' . implode(', ', $list);
        else { $d[$k] = $b[$k]; $t .= 's'; }
    }
    if (array_key_exists('keterangan', $b)) {
        $v = trim((string)$b['keterangan']); $d['keterangan'] = $v === '' ? null : mb_substr($v, 0, 2000); $t .= 's';
    }
    foreach (array_keys(dosen_ref()) as $k) if (array_key_exists($k, $b)) {
        $v = $b[$k];
        if ($v === null || $v === '') $d[$k] = null;
        elseif (is_numeric($v) && (int)$v > 0) $d[$k] = (int)$v;
        else { $err[$k] = 'harus berupa id angka positif atau kosong'; continue; }
        $t .= 'i';
    }
    return [$d, $t, $err];
}

/** INSERT (id=0) atau UPDATE. Mengembalikan id dosen. */
function dosen_save(mysqli $c, array $d, string $t, int $id = 0): int
{
    $vals = array_values($d);
    if ($id > 0) {
        $s = $c->prepare('UPDATE dosen SET ' . implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($d))) . ' WHERE id_dosen = ?');
        $vals[] = $id; $t .= 'i';
    } else {
        $s = $c->prepare('INSERT INTO dosen (`' . implode('`, `', array_keys($d)) . '`) VALUES (' . implode(', ', array_fill(0, count($d), '?')) . ')');
    }
    $s->bind_param($t, ...$vals);
    $s->execute();
    return $id > 0 ? $id : (int)$s->insert_id;
}

function dosen_find(mysqli $c, int $id): ?array
{
    return db_all($c, dosen_base_sql() . ' WHERE d.id_dosen = ?', 'i', [$id])[0] ?? null;
}
