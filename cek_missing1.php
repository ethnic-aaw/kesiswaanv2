<?php
require __DIR__.'/config/db.php';
echo "peserta_didik: ".$pdo->query("SELECT COUNT(*) FROM peserta_didik")->fetchColumn()."\n";
echo "siswa aktif: ".$pdo->query("SELECT COUNT(*) FROM siswa WHERE deleted_at IS NULL")->fetchColumn()."\n";
echo "siswa soft-deleted: ".$pdo->query("SELECT COUNT(*) FROM siswa WHERE deleted_at IS NOT NULL")->fetchColumn()."\n";
echo "siswa status Aktif: ".$pdo->query("SELECT COUNT(*) FROM siswa WHERE deleted_at IS NULL AND status='Aktif'")->fetchColumn()."\n";
echo "kelas aktif: ".$pdo->query("SELECT COUNT(*) FROM kelas WHERE deleted_at IS NULL")->fetchColumn()."\n";
echo "\n-- sync log terakhir --\n";
foreach($pdo->query("SELECT id,jumlah_baru,jumlah_diperbarui,jumlah_gagal,created_at FROM dapodik_sync_log ORDER BY id DESC LIMIT 5") as $r) print_r($r);
echo "\n-- cek duplikat nipd di peserta_didik --\n";
foreach($pdo->query("SELECT nipd, COUNT(*) c FROM peserta_didik GROUP BY nipd HAVING c>1") as $r) print_r($r);
echo "\n-- cek peserta_didik tanpa siswa --\n";
$cnt=$pdo->query("SELECT COUNT(*) FROM peserta_didik pd LEFT JOIN siswa s ON s.nipd=pd.nipd COLLATE utf8mb4_unicode_ci WHERE s.id IS NULL")->fetchColumn();
echo "pd tanpa siswa: $cnt\n";
foreach($pdo->query("SELECT pd.nipd,pd.nama,pd.nisn,pd.rombel FROM peserta_didik pd LEFT JOIN siswa s ON s.nipd=pd.nipd COLLATE utf8mb4_unicode_ci WHERE s.id IS NULL LIMIT 5") as $r) print_r($r);
echo "\n-- cek siswa tanpa pd --\n";
foreach($pdo->query("SELECT s.nipd,s.nama FROM siswa s LEFT JOIN peserta_didik pd ON pd.nipd=s.nipd COLLATE utf8mb4_unicode_ci WHERE pd.id IS NULL AND s.deleted_at IS NULL LIMIT 5") as $r) print_r($r);
echo "\n-- sample 3 baris terakhir peserta_didik --\n";
foreach($pdo->query("SELECT nipd,nama,rombel FROM peserta_didik ORDER BY id DESC LIMIT 5") as $r) print_r($r);
