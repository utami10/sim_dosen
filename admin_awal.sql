-- Akun admin awal. Username: admin  |  Password sementara: lihat balasan chat.
-- WAJIB diganti lewat menu Pengguna setelah login pertama.
INSERT INTO pengguna (id_role, username, password_hash, nama_tampilan, aktif)
SELECT id_role, 'admin', '$2y$10$iJY9Mxy3ntqwCqSRC8clm.q0ky.pbVJBRK/yj5WX4KysJVXk93DGG', 'Administrator', 1
FROM roles WHERE nama_role = 'ADMIN'
  AND NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'admin');
