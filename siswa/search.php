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
$st=$pdo->prepare("SELECT s.id,s.nama,s.nipd,COALESCE(k.nama_kelas,'—') as kelas FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.deleted_at IS NULL $whereWali AND (s.nama LIKE ? OR s.nipd LIKE ?) ORDER BY s.nama LIMIT 20");
$st->execute(["%$q%","%$q%"]); echo json_encode(['rows'=>$st->fetchAll()], JSON_UNESCAPED_UNICODE);
