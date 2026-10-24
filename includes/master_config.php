<?php
// Konfigurasi tabel master (web & API).
// Format: kunci => [tabel, kolom_pk, judul, [kolom => [label, maks, wajib, tipe('s'|'i')]]]
function master_config(): array
{
    return [
        'jabatan' => ['jabatan_fungsional', 'id_jabatan', 'Jabatan Fungsional', [
            'nama_jabatan' => ['Nama jabatan', 100, true, 's']]],
        'kepangkatan' => ['kepangkatan', 'id_kepangkatan', 'Kepangkatan', [
            'nama_pangkat' => ['Nama pangkat', 100, true, 's'], 'golongan' => ['Golongan', 20, false, 's'], 'ruang' => ['Ruang', 10, false, 's']]],
        'pendidikan' => ['pendidikan', 'id_pendidikan', 'Pendidikan Terakhir', [
            'jenjang' => ['Jenjang', 30, true, 's'], 'urutan' => ['Urutan', 255, true, 'i']]],
        'rumpun' => ['rumpun_ilmu', 'id_rumpun_ilmu', 'Rumpun Ilmu', [
            'nama_rumpun' => ['Rumpun ilmu', 150, true, 's']]],
        'status_kepegawaian' => ['status_kepegawaian', 'id_status_kepegawaian', 'Status & Ikatan Kerja', [
            'status_pegawai' => ['Status pegawai', 100, true, 's'], 'ikatan_kerja' => ['Ikatan kerja', 100, true, 's']]],
        'penempatan' => ['penempatan', 'id_penempatan', 'Penempatan (Program Studi)', [
            'universitas' => ['Universitas', 200, true, 's'], 'fakultas' => ['Fakultas', 200, true, 's'], 'program_studi' => ['Program studi', 200, true, 's']]],
    ];
}

/** Validasi input master. Mengembalikan [kolom=>nilai, tipe, galat]. Kolom opsional kosong menjadi NULL. */
function master_validate(array $in, array $fields, bool $create): array
{
    $d = []; $t = ''; $err = [];
    foreach ($fields as $k => [$label, $max, $req, $tp]) {
        if (!array_key_exists($k, $in)) { if ($create && $req) $err[$k] = "$label wajib diisi."; continue; }
        $v = trim((string)$in[$k]);
        if ($v === '') {
            if ($req) $err[$k] = "$label wajib diisi."; else { $d[$k] = null; $t .= $tp; }
            continue;
        }
        if ($tp === 'i') {
            if (!ctype_digit($v) || (int)$v > $max) { $err[$k] = "$label harus berupa angka 0-$max."; continue; }
            $d[$k] = (int)$v;
        } else $d[$k] = mb_substr($v, 0, $max);
        $t .= $tp;
    }
    return [$d, $t, $err];
}
