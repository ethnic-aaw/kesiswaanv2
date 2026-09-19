<?php
session_start(); header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/../config/db.php';
if(empty($_SESSION['user'])){ http_response_code(401); echo json_encode(['rows'=>[]]); exit; }
$q=trim($_GET['q']??''); if(strlen($q)<2){ echo json_encode(['rows'=>[]]); exit; }
$st=$pdo->prepare("SELECT s.id,s.nama,s.nipd,COALESCE(k.nama_kelas,'—') as kelas FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.deleted_at IS NULL AND (s.nama LIKE ? OR s.nipd LIKE ?) ORDER BY s.nama LIMIT 20");
$st->execute(["%$q%","%$q%"]); echo json_encode(['rows'=>$st->fetchAll()], JSON_UNESCAPED_UNICODE);
