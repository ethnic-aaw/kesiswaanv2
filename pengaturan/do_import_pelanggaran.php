<?php
session_start(); header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/../config/db.php'; require __DIR__.'/../includes/csrf.php'; require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ http_response_code(401); echo json_encode(['success'=>false,'error'=>'Login dulu']); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['success'=>false,'error'=>'POST only']); exit; }
$tok=$_POST['csrf_token']??$_SERVER['HTTP_X_CSRF_TOKEN']??''; if(!csrf_verify($tok)){ http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF token tidak valid']); exit; }
if(empty($_FILES['csv_file'])||$_FILES['csv_file']['error']!==UPLOAD_ERR_OK){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'file wajib (field name=csv_file) — .xlsx/.xls']); exit; }
$dry=isset($_GET['dry'])&&$_GET['dry']==='1'; $syncFull=isset($_POST['sync_full'])&&$_POST['sync_full']==='1';
$tmp=$_FILES['csv_file']['tmp_name']; $fname=strtolower($_FILES['csv_file']['name']??'');
if(filesize($tmp)>10*1024*1024){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'max 10MB']); exit; }
$rows=[]; $isXlsx=str_ends_with($fname,'.xlsx')||str_ends_with($fname,'.xls');
try{
  if($isXlsx){
    if(!file_exists(__DIR__.'/../vendor/autoload.php')) throw new Exception('PhpSpreadsheet belum install');
    require_once __DIR__.'/../vendor/autoload.php';
    $ss=\PhpOffice\PhpSpreadsheet\IOFactory::load($tmp); $sh=$ss->getActiveSheet();
    $maxRow=$sh->getHighestDataRow(); $maxCol=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sh->getHighestDataColumn());
    for($r=1;$r<=$maxRow;$r++){ $row=[]; for($c=1;$c<=$maxCol;$c++){ $v=$sh->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$r)->getFormattedValue(); $row[]=trim((string)$v); } $empty=true; foreach($row as $x) if($x!==''){ $empty=false; break; } if(!$empty) $rows[]=$row; }
  } else {
    $buf=file_get_contents($tmp); $buf=preg_replace('/^\xEF\xBB\xBF/','',$buf);
    $fh=fopen('php://temp','r+'); fwrite($fh,$buf); rewind($fh);
    $delim=(substr_count(substr($buf,0,4096),';')>substr_count(substr($buf,0,4096),','))?';':',';
    while(($r=fgetcsv($fh,0,$delim))!==false){ $empty=true; foreach($r as $x) if(trim((string)$x)!==''){ $empty=false; break; } if(!$empty) $rows[]=$r; }
    fclose($fh);
  }
}catch(Throwable $e){ error_log('import pelanggaran parse: '.$e->getMessage()); http_response_code(400); echo json_encode(['success'=>false,'error'=>'Gagal parse file']); exit; }
if(empty($rows)){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'File kosong']); exit; }
$hdr=array_map(fn($x)=>strtolower(trim(preg_replace('/[^a-z_]/','',strtolower((string)$x)))), $rows[0]);
$hasHdr=in_array('kode',$hdr)&&in_array('nama',$hdr);
$data=$hasHdr?array_slice($rows,1):$rows; $colIdx=array_flip($hdr);
$validKat=['Kedisiplinan','Tata Krama','Kekerasan','Narkoba','Lainnya'];
$ok=0; $fail=0; $log=[]; $seen=[]; $importKodes=[]; $results=[];
$lc=$hasHdr?1:0;
foreach($data as $r){
  $lc++;
  if($hasHdr){
    $get=function($k) use($r,$colIdx){ $i=$colIdx[$k]??null; return $i===null?'':trim((string)($r[$i]??'')); };
    $kode=$get('kode'); $nama=$get('nama'); $kategori=$get('kategori')?:'Lainnya'; $bobotStr=$get('bobot_poin'); $desk=$get('deskripsi'); $kons=$get('konsekuensi');
  } else { $kode=trim($r[0]??''); $nama=trim($r[1]??''); $kategori=trim($r[2]??'Lainnya'); $bobotStr=trim($r[3]??''); $desk=trim($r[4]??''); $kons=trim($r[5]??''); }
  if($bobotStr==='' && $kategori!=='' && is_numeric($kategori)){ $bobotStr=$kategori; $kategori='Lainnya'; }
  $bobot=(int)$bobotStr; if($kategori!=='' && !in_array($kategori,$validKat,true)) $kategori='Lainnya';
  $rr=['line'=>$lc,'kode'=>$kode,'nama'=>$nama,'kategori'=>$kategori,'bobot'=>$bobotStr];
  if($kode===''||$nama===''){ $rr['status']='gagal'; $rr['error']='kode/nama kosong'; $results[]=$rr; $fail++; continue; }
  if($bobot<1||$bobot>100){ $rr['status']='gagal'; $rr['error']='bobot 1-100 (dapat '.$bobotStr.')'; $results[]=$rr; $fail++; continue; }
  if(isset($seen[strtolower($kode)])){ $rr['status']='gagal'; $rr['error']='duplikat di file'; $results[]=$rr; $fail++; continue; }
  $seen[strtolower($kode)]=true; $importKodes[]=$kode;
  if($dry){
    $st=$pdo->prepare("SELECT id FROM jenis_pelanggaran WHERE kode=? LIMIT 1"); $st->execute([$kode]); $exists=(bool)$st->fetchColumn();
    $rr['status']=$exists?'updated':'ok'; $rr['mode']=$exists?'update':'insert'; $results[]=$rr; $ok++; continue;
  }
  try{
    $pdo->prepare("INSERT INTO jenis_pelanggaran(kode,nama,kategori,bobot_poin,deskripsi,konsekuensi) VALUES(?,?,?,?,?,?)")->execute([$kode,$nama,$kategori,$bobot,$desk?:null,$kons?:null]);
    $rr['status']='ok'; $results[]=$rr; $ok++;
  }catch(Throwable $e){
    if(str_contains($e->getMessage(),'Duplicate')||str_contains($e->getMessage(),'UNIQUE')){
      try{ $pdo->prepare("UPDATE jenis_pelanggaran SET nama=?,kategori=?,bobot_poin=?,deskripsi=?,konsekuensi=?,deleted_at=NULL WHERE kode=?")->execute([$nama,$kategori,$bobot,$desk?:null,$kons?:null,$kode]); $rr['status']='updated'; $results[]=$rr; $ok++; }catch(Throwable $e2){ error_log('import pelanggaran update: '.$e2->getMessage()); $rr['status']='gagal'; $rr['error']='Gagal update'; $results[]=$rr; $fail++; }
    } else { error_log('import pelanggaran: '.$e->getMessage()); $rr['status']='gagal'; $rr['error']='Gagal import baris ini'; $results[]=$rr; $fail++; }
  }
}
$deleted=0;
if(!$dry && $syncFull && $importKodes){
  $ph=implode(',',array_fill(0,count($importKodes),'?'));
  $st=$pdo->prepare("SELECT kode FROM jenis_pelanggaran WHERE deleted_at IS NULL AND kode NOT IN ($ph)"); $st->execute($importKodes); $toDel=$st->fetchAll(PDO::FETCH_COLUMN);
  foreach($toDel as $kd){
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM pelanggaran_siswa ps JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id WHERE jp.kode=?"); $cnt->execute([$kd]); if((int)$cnt->fetchColumn()>0) continue;
    $pdo->prepare("UPDATE jenis_pelanggaran SET deleted_at=NOW() WHERE kode=?")->execute([$kd]); $deleted++;
  }
}
echo json_encode(['success'=>true,'data'=>['berhasil'=>$ok,'gagal'=>$fail,'dihapus'=>$deleted,'rincian'=>$results,'dry_run'=>$dry]], JSON_UNESCAPED_UNICODE);
