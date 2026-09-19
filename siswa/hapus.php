<?php
session_start();
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: index.php'); exit; }
if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF tidak valid'); }
$id=(int)($_POST['id']??0);
if($id && isset($pdo) && $pdo){ try{ $pdo->prepare("UPDATE siswa SET deleted_at=NOW() WHERE id=?")->execute([$id]); }catch(Throwable $e){} }
header('Location: index.php?msg='.urlencode('Dihapus (soft-delete)')); exit;
