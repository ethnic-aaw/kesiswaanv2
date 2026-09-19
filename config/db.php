<?php
$host='127.0.0.1'; $db='kesiswaan'; $user='root'; $pass='';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false];
$pdo=null;
try {
  $pdo=new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4",$user,$pass,$opts);
} catch(PDOException $e){
  if(str_contains($e->getMessage(),'Unknown database')){
    try{
      $tmp=new PDO("mysql:host=$host;charset=utf8mb4",$user,$pass,$opts);
      $tmp->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
      $pdo=new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4",$user,$pass,$opts);
    }catch(Throwable $e2){ $pdo=null; }
  }
}
if($pdo){
  // patch: tambah kolom yang hilang di DB lama (sebelum ada deleted_at di schema)
  foreach(['users'=>'DATETIME NULL','kelas'=>'DATETIME NULL','siswa'=>'DATETIME NULL','jenis_pelanggaran'=>'DATETIME NULL'] as $tbl=>$def){
    try{ $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN deleted_at $def"); }catch(Throwable $ignored){}
  }
  // opsional: pastikan kolom lain yang mungkin belum ada
  try{ $pdo->exec("ALTER TABLE `siswa` ADD COLUMN foto VARCHAR(255) NULL"); }catch(Throwable $ignored){}
  try{ $pdo->exec("ALTER TABLE `users` ADD COLUMN nip VARCHAR(50) NULL"); }catch(Throwable $ignored){}
  try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS peserta_didik (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }catch(Throwable $ignored){}
  try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS dapodik_meta (
      id INT AUTO_INCREMENT PRIMARY KEY,
      sekolah VARCHAR(150), npsn VARCHAR(20), tahun_ajaran VARCHAR(20),
      tanggal_unduh VARCHAR(30), pengunduh VARCHAR(150), email_pengunduh VARCHAR(150),
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dapodik_sync_log (
      id INT AUTO_INCREMENT PRIMARY KEY,
      jumlah_baru INT, jumlah_diperbarui INT, jumlah_gagal INT,
      dilakukan_oleh INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }catch(Throwable $ignored){}
  try{
    $has=$pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if(!$has){
      $sqlFile=__DIR__.'/../sql/kesiswaan.sql';
      if(!file_exists($sqlFile)) $sqlFile=__DIR__.'/../api-go/migrations/001_init.sql';
      if(file_exists($sqlFile)){
        $sql=file_get_contents($sqlFile);
        $sql=preg_replace('/CREATE DATABASE.*?[;]/is','',$sql);
        $sql=preg_replace('/USE\s+`?kesiswaan`?/i','',$sql);
        $stmts=array_filter(array_map('trim', explode(';',$sql)));
        foreach($stmts as $s){ if($s===''||str_starts_with($s,'--')) continue; try{ $pdo->exec($s); }catch(Throwable $ignored){} }
      }
    }
    $cnt=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='admin'")->fetchColumn();
    if($cnt===0){
      $hash=password_hash('admin123', PASSWORD_BCRYPT);
      $pdo->prepare("INSERT INTO users(nama,username,password_hash,role,status) VALUES(?,?,?,?,?)")->execute(['Admin TU','admin',$hash,'Admin','Aktif']);
    }
    $cnt2=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='guru@belajar.id'")->fetchColumn();
    if($cnt2===0){
      $hash=password_hash('guru12345', PASSWORD_BCRYPT);
      $pdo->prepare("INSERT INTO users(nama,username,password_hash,role,nip,status) VALUES(?,?,?,?,?,?)")->execute(['Guru BK','guru@belajar.id',$hash,'Guru BK','1987654321','Aktif']);
    }
    try{
      $c=(int)$pdo->query("SELECT COUNT(*) FROM jenis_pelanggaran WHERE deleted_at IS NULL")->fetchColumn();
      if($c < 56){
        $seedFile=__DIR__.'/../sql/seed_pelanggaran_leuwimunding.sql';
        if(file_exists($seedFile)){
          foreach(file($seedFile) as $line){
            $line=trim($line);
            if($line===''||str_starts_with($line,'--')) continue;
            try{ $pdo->exec($line); }catch(Throwable $ignored){}
          }
        }
      }
    }catch(Throwable $ignored){}
  }catch(Throwable $ignored){}
}
