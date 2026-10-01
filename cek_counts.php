<?php
require __DIR__.'/config/db.php';
echo "settings TA aktif: ".var_export($pdo->query("SELECT value FROM settings WHERE key_name='tahun_ajaran_aktif' LIMIT 1")->fetchColumn(),true)."\n";
echo "dapodik_meta TA: ".var_export($pdo->query("SELECT tahun_ajaran FROM dapodik_meta ORDER BY id DESC LIMIT 1")->fetchColumn(),true)."\n";
$rows=$pdo->query("SELECT tahun_ajaran, COUNT(*) c FROM kelas WHERE deleted_at IS NULL GROUP BY tahun_ajaran")->fetchAll(PDO::FETCH_ASSOC);
echo "kelas by TA:\n"; foreach($rows as $r) echo "  {$r['tahun_ajaran']} => {$r['c']}\n";
echo "siswa aktif total: ".$pdo->query("SELECT COUNT(*) FROM siswa WHERE deleted_at IS NULL AND status='Aktif'")->fetchColumn()."\n";
echo "siswa Aktif join any kelas: ".$pdo->query("SELECT COUNT(*) FROM siswa s JOIN kelas k ON k.id=s.kelas_id WHERE s.deleted_at IS NULL AND s.status='Aktif' AND k.deleted_at IS NULL")->fetchColumn()."\n";
// coba TA dari dashboard
$ta=$pdo->query("SELECT tahun_ajaran FROM dapodik_meta ORDER BY id DESC LIMIT 1")->fetchColumn();
if(!$ta) $ta=$pdo->query("SELECT value FROM settings WHERE key_name='tahun_ajaran_aktif' LIMIT 1")->fetchColumn();
if(!$ta) $ta=$pdo->query("SELECT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran DESC LIMIT 1")->fetchColumn();
echo "TA dashboard: $ta\n";
$st=$pdo->prepare("SELECT COUNT(*) FROM siswa s JOIN kelas k ON k.id=s.kelas_id WHERE s.deleted_at IS NULL AND s.status='Aktif' AND k.tahun_ajaran=? AND k.deleted_at IS NULL"); $st->execute([$ta]); echo "siswa with TA $ta: ".$st->fetchColumn()."\n";
$st=$pdo->prepare("SELECT COUNT(*) FROM kelas WHERE deleted_at IS NULL AND tahun_ajaran=?"); $st->execute([$ta]); echo "kelas with TA $ta: ".$st->fetchColumn()."\n";
echo "all kelas non-deleted: ".$pdo->query("SELECT COUNT(*) FROM kelas WHERE deleted_at IS NULL")->fetchColumn()."\n";
echo "all kelas raw: ".$pdo->query("SELECT COUNT(*) FROM kelas")->fetchColumn()."\n";
$taList=$pdo->query("SELECT DISTINCT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran")->fetchAll(PDO::FETCH_COLUMN);
echo "TA list: ".json_encode($taList)."\n";
