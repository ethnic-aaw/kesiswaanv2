<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
require __DIR__.'/../includes/auth.php';
$fail=function(string $m,int $c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; };
if(empty($_SESSION['user'])) $fail('Belum login',401);
if(!role_can('mutate_user')) $fail('Forbidden',403);
if($_SERVER['REQUEST_METHOD']!=='POST') $fail('Method tidak diizinkan',405);
if(!csrf_verify($_POST['csrf_token']??'')) $fail('CSRF',403);
$id=(int)($_POST['id']??0);
if(!$id) $fail('ID tidak valid');
if($id===(int)($_SESSION['user']['id']??0)) $fail('Tidak bisa menghapus akun sendiri');
try{
  $st=$pdo->prepare("SELECT role FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1"); $st->execute([$id]); $role=$st->fetchColumn();
  if(!$role) $fail('User tidak ditemukan',404);
  if($role==='Admin'){
    $n=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='Admin' AND status='Aktif' AND deleted_at IS NULL")->fetchColumn();
    if($n<=1) $fail('Minimal harus ada 1 Admin aktif');
  }
  $pdo->prepare("UPDATE kelas SET wali_kelas_id=NULL WHERE wali_kelas_id=?")->execute([$id]);
  $pdo->prepare("DELETE FROM remember_tokens WHERE user_id=?")->execute([$id]);
  $pdo->prepare("UPDATE users SET deleted_at=NOW(),status='Nonaktif' WHERE id=?")->execute([$id]);
  echo json_encode(['success'=>true]);
}catch(Throwable $e){ error_log('user hapus: '.$e->getMessage()); $fail('Gagal hapus',500); }
