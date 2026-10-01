<?php
require __DIR__.'/config/db.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS dapodik_meta (id INT AUTO_INCREMENT PRIMARY KEY, sekolah VARCHAR(150), npsn VARCHAR(20), tahun_ajaran VARCHAR(20), tanggal_unduh VARCHAR(30), pengunduh VARCHAR(150), email_pengunduh VARCHAR(150), updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// restore tanggal yang kehapus tadi — pengunduh biar kosong dulu, nanti keisi full saat import ulang
$cnt=(int)$pdo->query("SELECT COUNT(*) FROM dapodik_meta")->fetchColumn();
if($cnt===0){
  $pdo->prepare("INSERT INTO dapodik_meta(sekolah,npsn,tahun_ajaran,tanggal_unduh,pengunduh,email_pengunduh) VALUES(?,?,?,?,?,?)")
      ->execute([null,null,null,'2026-09-30 12:09:37',null,null]);
  echo "restored tanggal\n";
} else {
  $pdo->exec("UPDATE dapodik_meta SET tanggal_unduh='2026-09-30 12:09:37' WHERE id=1");
  echo "updated tanggal\n";
}
$r=$pdo->query("SELECT * FROM dapodik_meta LIMIT 1")->fetch(PDO::FETCH_ASSOC);
var_export($r);
echo "\n";
