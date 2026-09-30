<?php
if(session_status()===PHP_SESSION_NONE) session_start();
function get_ta_aktif($pdo){
  if(!empty($_SESSION['ta_aktif'])) return $_SESSION['ta_aktif'];
  try{ $v=$pdo->query("SELECT value FROM settings WHERE key_name='tahun_ajaran_aktif' LIMIT 1")->fetchColumn(); if($v){ $_SESSION['ta_aktif']=$v; return $v; } }catch(Throwable $e){}
  try{ $v=$pdo->query("SELECT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran DESC LIMIT 1")->fetchColumn(); if($v){ $_SESSION['ta_aktif']=$v; return $v; } }catch(Throwable $e){}
  $def=date('Y').'/'.(date('Y')+1); $_SESSION['ta_aktif']=$def; return $def;
}
function set_ta_aktif($pdo,$ta){
  $ta=trim(preg_replace('/\s+Semester.*$/i','',$ta)); if($ta==='') return;
  $_SESSION['ta_aktif']=$ta;
  try{ $pdo->prepare("INSERT INTO settings(key_name,value) VALUES('tahun_ajaran_aktif',?) ON DUPLICATE KEY UPDATE value=VALUES(value)")->execute([$ta]); }catch(Throwable $e){}
}
function list_ta_options($pdo){
  try{
    $rows=$pdo->query("SELECT DISTINCT tahun_ajaran FROM kelas WHERE deleted_at IS NULL AND tahun_ajaran<>'' ORDER BY tahun_ajaran DESC")->fetchAll(PDO::FETCH_COLUMN);
    if($rows) return $rows;
  }catch(Throwable $e){}
  return [date('Y').'/'.(date('Y')+1)];
}
