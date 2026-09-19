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
if(!csrf_verify($tok)){ http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF token tidak valid']); exit; }
if(!$pdo){ http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB tidak konek']); exit; }
$confirm=trim($_POST['confirm']??'');
if($confirm!=='HAPUS'){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'Ketik HAPUS untuk konfirmasi']); exit; }
try{
  $rows=$pdo->query("SELECT id FROM kelas WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
  $cleared=0; $skipped=0;
  foreach($rows as $id){
    $cnt=(int)$pdo->prepare("SELECT COUNT(*) FROM siswa WHERE kelas_id=? AND deleted_at IS NULL");
    // prepare+execute for count
    $st=$pdo->prepare("SELECT COUNT(*) FROM siswa WHERE kelas_id=? AND deleted_at IS NULL"); $st->execute([$id]); $cnt=(int)$st->fetchColumn();
    if($cnt>0){ $skipped++; continue; }
    $pdo->prepare("UPDATE kelas SET deleted_at=NOW() WHERE id=?")->execute([$id]); $cleared++;
  }
  echo json_encode(['success'=>true,'data'=>['kelas_cleared'=>$cleared,'kelas_skipped'=>$skipped]], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
