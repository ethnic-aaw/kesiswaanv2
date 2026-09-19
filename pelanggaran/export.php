<?php
require __DIR__.'/../config/db.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
try{ $rows=$pdo->query("SELECT kode,nama,kategori,bobot_poin,deskripsi,konsekuensi FROM jenis_pelanggaran WHERE deleted_at IS NULL ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){ $rows=[]; }
require __DIR__.'/../vendor/autoload.php';
$ss=new \PhpOffice\PhpSpreadsheet\Spreadsheet(); $sh=$ss->getActiveSheet();
$hdr=['kode','nama','kategori','bobot_poin','deskripsi','konsekuensi'];
foreach($hdr as $i=>$h){ $c=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1).'1'; $sh->setCellValue($c,$h); $sh->getStyle($c)->getFont()->setBold(true); $sh->getStyle($c)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9'); }
$r=2; foreach($rows as $row){ $c=1; foreach($hdr as $k){ $sh->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$r, $row[$k]??''); $c++; } $r++; }
if(empty($rows)){ $sh->setCellValue('A2','PLG-057'); $sh->setCellValue('B2','Contoh — Terlambat apel pagi'); $sh->setCellValue('C2','Kedisiplinan'); $sh->setCellValue('D2',10); $sh->setCellValue('E2','Kehadiran — contoh'); $sh->setCellValue('F2','Teguran lisan'); }
foreach(range(1,6) as $c) $sh->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
$sh->freezePane('A2');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template_pelanggaran_'.date('Ymd').'.xlsx"');
$w=\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($ss,'Xlsx'); $w->save('php://output');
