<?php
session_start();
require_once __DIR__.'/config/db.php';
if(!empty($_SESSION['user']['id']) && isset($pdo) && $pdo){
  try{ $pdo->prepare("DELETE FROM remember_tokens WHERE user_id=?")->execute([(int)$_SESSION['user']['id']]); }catch(Throwable $e){ error_log('logout token: '.$e->getMessage()); }
  try{ $pdo->prepare("UPDATE users SET last_seen=NULL WHERE id=?")->execute([(int)$_SESSION['user']['id']]); }catch(Throwable $e){}
}
$_SESSION=[]; session_destroy();
setcookie(session_name(),'',time()-3600,'/', '', !empty($_SERVER['HTTPS']), true);
header('Location: login.php'); exit;
