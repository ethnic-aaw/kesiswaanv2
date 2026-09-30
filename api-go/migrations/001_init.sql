-- Kesiswaan v1.4 — schema PRD §5.2
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(100) NOT NULL UNIQUE,
  value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Admin','Guru BK','Wali Kelas','Siswa') NOT NULL DEFAULT 'Admin',
  nip VARCHAR(50) NULL,
  status ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_username (username),
  INDEX idx_users_deleted (deleted_at)
);

CREATE TABLE IF NOT EXISTS remember_tokens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS kelas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nama_kelas VARCHAR(100) NOT NULL,
  tingkat ENUM('X','XI','XII','VII','VIII','IX') NOT NULL DEFAULT 'X',
  wali_kelas_id BIGINT NULL,
  tahun_ajaran VARCHAR(20) NOT NULL DEFAULT '2024/2025',
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (wali_kelas_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS siswa (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nipd VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL DEFAULT 'L',
  kelas_id BIGINT NULL,
  tempat_lahir VARCHAR(100) NULL,
  tanggal_lahir DATE NULL,
  nama_ortu VARCHAR(100) NULL,
  hp_ortu VARCHAR(20) NULL,
  foto VARCHAR(255) NULL,
  alamat TEXT NULL,
  status ENUM('Aktif','Tidak Aktif','Pindah','Lulus') NOT NULL DEFAULT 'Aktif',
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL,
  INDEX idx_siswa_kelas (kelas_id),
  INDEX idx_siswa_status (status),
  INDEX idx_siswa_nipd (nipd)
);

CREATE TABLE IF NOT EXISTS jenis_pelanggaran (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  kategori ENUM('Kedisiplinan','Tata Krama','Kekerasan','Narkoba','Lainnya') NOT NULL DEFAULT 'Lainnya',
  bobot_poin INT NOT NULL CHECK (bobot_poin BETWEEN 1 AND 100),
  deskripsi TEXT NULL,
  konsekuensi TEXT NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pelanggaran_siswa (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  siswa_id BIGINT NOT NULL,
  jenis_pelanggaran_id BIGINT NOT NULL,
  poin_final INT NOT NULL,
  tahun_ajaran VARCHAR(20) NOT NULL,
  tanggal DATE NOT NULL,
  lokasi VARCHAR(255) NULL,
  keterangan TEXT NULL,
  tindakan TEXT NULL,
  pelapor_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (siswa_id) REFERENCES siswa(id),
  FOREIGN KEY (jenis_pelanggaran_id) REFERENCES jenis_pelanggaran(id),
  FOREIGN KEY (pelapor_id) REFERENCES users(id),
  INDEX idx_pelanggaran_siswa (siswa_id),
  INDEX idx_pelanggaran_tanggal (tanggal),
  INDEX idx_pelanggaran_ta (tahun_ajaran)
);

CREATE TABLE IF NOT EXISTS audit_poin (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  pelanggaran_siswa_id BIGINT NOT NULL,
  poin_default INT NOT NULL,
  poin_final INT NOT NULL,
  alasan TEXT NOT NULL,
  changed_by BIGINT NOT NULL,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pelanggaran_siswa_id) REFERENCES pelanggaran_siswa(id),
  FOREIGN KEY (changed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS dapodik_sync_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  jumlah_baru INT NOT NULL DEFAULT 0,
  jumlah_diperbarui INT NOT NULL DEFAULT 0,
  jumlah_gagal INT NOT NULL DEFAULT 0,
  dilakukan_oleh BIGINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dilakukan_oleh) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS peserta_didik (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_urut VARCHAR(10), nama VARCHAR(150), nipd VARCHAR(25), jenis_kelamin VARCHAR(10),
  nisn VARCHAR(15), tempat_lahir VARCHAR(100), tanggal_lahir VARCHAR(20), nik VARCHAR(25),
  agama VARCHAR(30), alamat VARCHAR(255), rt VARCHAR(10), rw VARCHAR(10),
  dusun VARCHAR(100), kelurahan VARCHAR(100), kecamatan VARCHAR(100), kode_pos VARCHAR(10),
  jenis_tinggal VARCHAR(50), alat_transportasi VARCHAR(100), telepon VARCHAR(20),
  hp VARCHAR(20), email VARCHAR(100), skhun VARCHAR(30), penerima_kps VARCHAR(10),
  no_kps VARCHAR(30),
  ayah_nama VARCHAR(150), ayah_tahun_lahir VARCHAR(10), ayah_pendidikan VARCHAR(50),
  ayah_pekerjaan VARCHAR(100), ayah_penghasilan VARCHAR(50), ayah_nik VARCHAR(25),
  ibu_nama VARCHAR(150), ibu_tahun_lahir VARCHAR(10), ibu_pendidikan VARCHAR(50),
  ibu_pekerjaan VARCHAR(100), ibu_penghasilan VARCHAR(50), ibu_nik VARCHAR(25),
  wali_nama VARCHAR(150), wali_tahun_lahir VARCHAR(10), wali_pendidikan VARCHAR(50),
  wali_pekerjaan VARCHAR(100), wali_penghasilan VARCHAR(50), wali_nik VARCHAR(25),
  rombel VARCHAR(50), no_peserta_ujian VARCHAR(30), no_seri_ijazah VARCHAR(50),
  penerima_kip VARCHAR(10), nomor_kip VARCHAR(30), nama_kip VARCHAR(150),
  nomor_kks VARCHAR(30), no_registrasi_akta_lahir VARCHAR(50), bank VARCHAR(50),
  no_rekening_bank VARCHAR(50), rekening_atas_nama VARCHAR(150), layak_pip VARCHAR(50),
  alasan_layak_pip VARCHAR(100), kebutuhan_khusus VARCHAR(100), sekolah_asal VARCHAR(150),
  anak_ke VARCHAR(10), lintang VARCHAR(20), bujur VARCHAR(20), no_kk VARCHAR(25),
  berat_badan VARCHAR(10), tinggi_badan VARCHAR(10), lingkar_kepala VARCHAR(10),
  jml_saudara_kandung VARCHAR(10), jarak_ke_sekolah_km VARCHAR(15),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pd_nisn (nisn), INDEX idx_pd_nipd (nipd), INDEX idx_pd_rombel (rombel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS siswa_kesehatan (
  siswa_id BIGINT NOT NULL PRIMARY KEY, tinggi_badan VARCHAR(10) NULL, berat_badan VARCHAR(10) NULL, golongan_darah VARCHAR(5) NULL,
  cacat_tubuh ENUM('Ya','Tidak') NOT NULL DEFAULT 'Tidak', cacat_keterangan VARCHAR(255) NULL,
  pakai_kacamata ENUM('Ya','Tidak') NOT NULL DEFAULT 'Tidak', kacamata_minus VARCHAR(20) NULL, kacamata_silinder VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS siswa_sakit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, siswa_id BIGINT NOT NULL, jenis_penyakit VARCHAR(150) NOT NULL, usia_saat_sakit VARCHAR(20) NULL, opname ENUM('Ya','Tidak') NOT NULL DEFAULT 'Tidak', rumah_sakit VARCHAR(150) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE, INDEX idx_sakit_siswa (siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bimbingan_konseling (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, siswa_id BIGINT NOT NULL, tanggal DATE NOT NULL, permasalahan TEXT NOT NULL, tindakan TEXT NULL, konselor_id BIGINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE, FOREIGN KEY (konselor_id) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_bk_siswa (siswa_id), INDEX idx_bk_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- seed
INSERT IGNORE INTO settings(key_name,value) VALUES('threshold_poin_kritis','76');

-- admin: password admin123 bcrypt
INSERT IGNORE INTO users(id,nama,username,password_hash,role,status) VALUES(1,'Admin TU','admin','$2a$10$xm8v1Yl8xXzQwQwQwQwQwOeKqKqKqKqKqKqKqKqKqKqKqKqKqKqKq','Admin','Aktif');
-- proper hash will be generated by seed command; fallback insert admin if not exists handled in main.go

INSERT IGNORE INTO kelas(id,nama_kelas,tingkat,tahun_ajaran) VALUES(1,'X IPA 2','X','2024/2025'),(2,'XI IPA 1','XI','2024/2025'),(3,'XII IPA 3','XII','2024/2025');

INSERT IGNORE INTO jenis_pelanggaran(kode,nama,kategori,bobot_poin,konsekuensi) VALUES
('PLG-001','Terlambat masuk','Kedisiplinan',10,'Teguran lisan'),
('PLG-002','Seragam tidak lengkap','Kedisiplinan',5,'Teguran'),
('PLG-003','Membolos','Kedisiplinan',25,'Panggilan ortu'),
('PLG-004','Merusak fasilitas','Kekerasan',50,'Ganti rugi + skors'),
('PLG-005','Kekerasan fisik','Kekerasan',100,'Skors + BK intensif');
