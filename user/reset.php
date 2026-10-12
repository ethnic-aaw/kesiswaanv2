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
$id=(int)($_POST['id']??0); $pw=(string)($_POST['pw']??'');
if(!$id) $fail('ID tidak valid');
if(strlen($pw)<8) $fail('Password min 8 karakter');
try{
  $st=$pdo->prepare("SELECT id FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1"); $st->execute([$id]);
  if(!$st->fetchColumn()) $fail('User tidak ditemukan',404);
  $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($pw,PASSWORD_BCRYPT),$id]);
  $pdo->prepare("DELETE FROM remember_tokens WHERE user_id=?")->execute([$id]); // sesi remember lama invalid
  echo json_encode(['success'=>true]);
}catch(Throwable $e){ error_log('user reset: '.$e->getMessage()); $fail('Gagal reset',500); }
