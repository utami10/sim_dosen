-- Tabel token API (hanya menyimpan hash SHA-256 token)
CREATE TABLE IF NOT EXISTS api_token (
  id_token INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pengguna BIGINT UNSIGNED NOT NULL,
  label VARCHAR(100) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_token_pengguna (id_pengguna),
  CONSTRAINT fk_token_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
