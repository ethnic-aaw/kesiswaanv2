<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/csrf.php';
if(empty($_SESSION['user'])){ http_response_code(401); echo json_encode(['success'=>false,'error'=>'Login dulu']); exit; }
require_role(['Admin']);
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['success'=>false,'error'=>'POST only']); exit; }
$tok=$_POST['csrf_token']??$_SERVER['HTTP_X_CSRF_TOKEN']??'';
if(!csrf_verify($tok)){ http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF tidak valid']); exit; }
if(!$pdo){ http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB tidak konek']); exit; }
$confirm=trim($_POST['confirm']??'');
if($confirm!=='HAPUS'){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'Ketik HAPUS untuk konfirmasi']); exit; }
try{
  $cSiswa=(int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE deleted_at IS NULL")->fetchColumn();
  $cPd=(int)$pdo->query("SELECT COUNT(*) FROM peserta_didik")->fetchColumn();
  $pdo->exec("UPDATE siswa SET deleted_at=NOW() WHERE deleted_at IS NULL");
  $pdo->exec("DELETE FROM peserta_didik");
  try{ $pdo->exec("INSERT INTO dapodik_sync_log(jumlah_baru,jumlah_diperbarui,jumlah_gagal,dilakukan_oleh) VALUES(0,0,0,".((int)($_SESSION['user']['id']??'NULL')).")"); }catch(Throwable $e){}
  echo json_encode(['success'=>true,'data'=>['siswa_cleared'=>$cSiswa,'pd_cleared'=>$cPd]], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
