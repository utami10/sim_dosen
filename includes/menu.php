<?php
// includes/menu.php - menu sidebar + hak akses per peran ('roles' kosong = semua peran login)
function app_menu(): array
{
    $tulis  = [ROLE_ADMIN, ROLE_OPERATOR];
    $lapor  = [ROLE_ADMIN, ROLE_OPERATOR, ROLE_FAKULTAS, ROLE_PIMPINAN];
    return [
        ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => 'dashboard.php'],
        ['label' => 'Data Dosen', 'icon' => 'bi-person-badge', 'href' => 'dosen.php'],
        ['heading' => 'Data Master', 'roles' => $tulis],
        ['label' => 'Jabatan Fungsional', 'icon' => 'bi-award', 'href' => 'master.php?t=jabatan', 'roles' => $tulis],
        ['label' => 'Kepangkatan', 'icon' => 'bi-bar-chart-steps', 'href' => 'master.php?t=kepangkatan', 'roles' => $tulis],
        ['label' => 'Pendidikan Terakhir', 'icon' => 'bi-journal-bookmark', 'href' => 'master.php?t=pendidikan', 'roles' => $tulis],
        ['label' => 'Rumpun Ilmu', 'icon' => 'bi-diagram-3', 'href' => 'master.php?t=rumpun', 'roles' => $tulis],
        ['label' => 'Status & Ikatan Kerja', 'icon' => 'bi-briefcase', 'href' => 'master.php?t=status_kepegawaian', 'roles' => $tulis],
        ['label' => 'Penempatan (Prodi)', 'icon' => 'bi-building', 'href' => 'master.php?t=penempatan', 'roles' => $tulis],
        ['heading' => 'Laporan', 'roles' => $lapor],
        ['label' => 'Daftar Dosen Aktif', 'icon' => 'bi-file-earmark-text', 'href' => 'laporan_dosen.php', 'roles' => $lapor],
        ['heading' => 'Sistem', 'roles' => $tulis],
        ['label' => 'Pengguna', 'icon' => 'bi-people', 'href' => 'pengguna.php', 'roles' => [ROLE_ADMIN]],
        ['label' => 'Dokumentasi API', 'icon' => 'bi-plug', 'href' => 'api_docs.php', 'roles' => $tulis],
    ];
}
