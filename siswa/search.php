<?php
session_start(); header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ http_response_code(401); echo json_encode(['rows'=>[]]); exit; }
$q=trim($_GET['q']??''); if(strlen($q)<2){ echo json_encode(['rows'=>[]]); exit; }
$whereWali=''; if((current_user()['role']??'')==='Wali Kelas'){
  $ids=wali_ampu_ids($pdo,(int)($_SESSION['user']['id']??0));
  $whereWali = empty($ids) ? " AND 1=0" : " AND s.kelas_id IN (".implode(',',array_map('intval',$ids)).")";
}
// scan barcode QR (NPSN|NISN|nama) — cocokkan NISN via peserta_didik
$st=$pdo->prepare("SELECT DISTINCT s.id,s.nama,s.nipd,COALESCE(k.nama_kelas,'—') as kelas FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN peserta_didik pd ON pd.nipd=s.nipd OR pd.nisn=s.nipd WHERE s.deleted_at IS NULL $whereWali AND (s.nama LIKE ? OR s.nipd LIKE ? OR pd.nisn LIKE ? OR pd.nama LIKE ?) ORDER BY s.nama LIMIT 20");
$st->execute(["%$q%","%$q%","%$q%","%$q%"]); echo json_encode(['rows'=>$st->fetchAll()], JSON_UNESCAPED_UNICODE);
