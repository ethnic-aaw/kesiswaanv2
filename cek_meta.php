<?php
require __DIR__.'/config/db.php';
$r=$pdo->query("SELECT * FROM dapodik_meta WHERE id=1")->fetch(PDO::FETCH_ASSOC);
var_export($r);
echo "\n---\n";
$rows=$pdo->query("SELECT sekolah,npsn,tahun_ajaran,tanggal_unduh,pengunduh,email_pengunduh, LENGTH(pengunduh) as len FROM dapodik_meta")->fetchAll(PDO::FETCH_ASSOC);
var_export($rows);
