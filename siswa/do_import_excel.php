<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/csrf.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
if(empty($_SESSION['user'])){ http_response_code(401); echo json_encode(['success'=>false,'error'=>'Login dulu']); exit; }
$tok=$_POST['csrf_token']??$_GET['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??'');
if(!csrf_verify($tok)){ http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF token tidak valid']); exit; }
if(!$pdo){ http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB tidak konek']); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['success'=>false,'error'=>'POST only']); exit; }
if(empty($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'file wajib (field name=file)']); exit; }
$dryRun=isset($_GET['dry_run'])&&$_GET['dry_run']==='1';
$autoKelas=isset($_GET['auto_kelas'])&&$_GET['auto_kelas']==='1';
$upsert=isset($_GET['upsert'])&&$_GET['upsert']==='1';
$tmp=$_FILES['file']['tmp_name']; $fname=strtolower($_FILES['file']['name']??'');
if(filesize($tmp)>10*1024*1024){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'max 10MB']); exit; }
function nkey($s){ return preg_replace('/[^a-z0-9]/','',strtolower(trim((string)$s))); }
function ntext($s){ return trim(preg_replace('/\s+/',' ',(string)$s)); }
$COLUMN_MAP=[
  'no'=>'no_urut','nama'=>'nama','nipd'=>'nipd','jk'=>'jenis_kelamin','nisn'=>'nisn',
  'tempatlahir'=>'tempat_lahir','tanggallahir'=>'tanggal_lahir','nik'=>'nik','agama'=>'agama',
  'alamat'=>'alamat','rt'=>'rt','rw'=>'rw','dusun'=>'dusun','kelurahan'=>'kelurahan',
  'kecamatan'=>'kecamatan','kodepos'=>'kode_pos','jenistinggal'=>'jenis_tinggal',
  'alattransportasi'=>'alat_transportasi','telepon'=>'telepon','hp'=>'hp','email'=>'email',
  'skhun'=>'skhun','penerimakps'=>'penerima_kps','nokps'=>'no_kps',
  'dataayahnama'=>'ayah_nama','dataayahtahunlahir'=>'ayah_tahun_lahir',
  'dataayahjenjangpendidikan'=>'ayah_pendidikan','dataayahpekerjaan'=>'ayah_pekerjaan',
  'dataayahpenghasilan'=>'ayah_penghasilan','dataayahnik'=>'ayah_nik',
  'dataibunama'=>'ibu_nama','dataibutahunlahir'=>'ibu_tahun_lahir',
  'dataibujenjangpendidikan'=>'ibu_pendidikan','dataibupekerjaan'=>'ibu_pekerjaan',
  'dataibupenghasilan'=>'ibu_penghasilan','dataibunik'=>'ibu_nik',
  'datawalinama'=>'wali_nama','datawalitahunlahir'=>'wali_tahun_lahir',
  'datawalijenjangpendidikan'=>'wali_pendidikan','datawalipekerjaan'=>'wali_pekerjaan',
  'datawalipenghasilan'=>'wali_penghasilan','datawalinik'=>'wali_nik',
  'rombelsaatini'=>'rombel','nopesertaujiannasional'=>'no_peserta_ujian',
  'noseriijazah'=>'no_seri_ijazah','penerimakip'=>'penerima_kip','nomorkip'=>'nomor_kip',
  'namadikip'=>'nama_kip','nomorkks'=>'nomor_kks','noregistrasiaktalahir'=>'no_registrasi_akta_lahir',
  'bank'=>'bank','nomorrekeningbank'=>'no_rekening_bank','rekeningatasnama'=>'rekening_atas_nama',
  'layakpipusulandarisekolah'=>'layak_pip','alasanlayakpip'=>'alasan_layak_pip',
  'kebutuhankhusus'=>'kebutuhan_khusus','sekolahasal'=>'sekolah_asal','anakkeberapa'=>'anak_ke',
  'lintang'=>'lintang','bujur'=>'bujur','nokk'=>'no_kk','beratbadan'=>'berat_badan',
  'tinggibadan'=>'tinggi_badan','lingkarkepala'=>'lingkar_kepala',
  'jmlsaudarakandung'=>'jml_saudara_kandung','jarakrumahkesekolahkm'=>'jarak_ke_sekolah_km',
];
function cleanVal($v){
  if($v===null) return null;
  if($v instanceof DateTime) return $v->format('Y-m-d');
  if(is_float($v)) return $v==(int)$v ? (string)(int)$v : trim((string)$v);
  if(is_int($v)) return (string)$v;
  $s=trim(str_replace(["\r","\n"],' ',(string)$v)); return $s===''?null:$s;
}
function fixDate($s){
  if(!$s) return $s; $s=trim((string)$s);
  foreach(['Y-m-d H:i:s','Y-m-d','d-m-Y','d/m/Y','d-m-y'] as $fmt){
    $d=DateTime::createFromFormat($fmt,$s); if($d && $d->format($fmt)===$s) return $d->format('Y-m-d');
  }
  if(preg_match('/^(\d{4}-\d{2}-\d{2})/',$s)) return substr($s,0,10);
  if(preg_match('/(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})/',$s,$m)) return sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]);
  return $s;
}
function tingkatPHP($nm){ $u=strtoupper(trim($nm)); if(preg_match('/^12\b/',$u)) return 'XII'; if(preg_match('/^11\b/',$u)) return 'XI'; if(preg_match('/^10\b/',$u)) return 'X'; if(str_starts_with($u,'XII')) return 'XII'; if(str_starts_with($u,'XI')) return 'XI'; if(str_starts_with($u,'IX')) return 'IX'; if(str_starts_with($u,'VIII')) return 'VIII'; if(str_starts_with($u,'VII')) return 'VII'; if($u==='X'||str_starts_with($u,'X ')) return 'X'; return 'X'; }
function normJK($s){ $s=strtolower(trim((string)$s)); return in_array($s,['p','perempuan','pr','female','f'])?'P':'L'; }
$rows=[]; $err='';
try{
  $spread=IOFactory::load($tmp);
  $sheet=$spread->getActiveSheet();
  $maxRow=$sheet->getHighestDataRow(); $maxCol=$sheet->getHighestDataColumn();
  $maxColIdx=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);
  for($r=1;$r<=$maxRow;$r++){
    $row=[];
    for($c=1;$c<=$maxColIdx;$c++){
      $cell=$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$r);
      $v=$cell->getFormattedValue();
      // for dates, try formatted; also check isDate
      if(\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && is_numeric($cell->getValue())){
        try{ $dt=\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cell->getValue()); $v=$dt->format('Y-m-d'); }catch(Throwable $e){}
      }
      $row[]=cleanVal($v);
    }
    // skip fully empty
    $emp=true; foreach($row as $x) if($x!==null && $x!==''){ $emp=false; break; }
    if(!$emp) $rows[]=$row;
  }
}catch(Throwable $e){
  // fallback: try CSV/HTML parse if PhpSpreadsheet fails (e.g. HTML-disguised)
  $buf=file_get_contents($tmp);
  $isHTML=stripos(substr($buf,0,2048),'<table')!==false || stripos(substr($buf,0,2048),'<html')!==false;
  if($isHTML){
    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is',$buf,$trs);
    foreach($trs[1] as $inner){
      preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is',$inner,$tds);
      if(empty($tds[1])) continue;
      $row=[]; foreach($tds[1] as $c){ $c=preg_replace('/<[^>]*>/',' ',$c); $c=html_entity_decode($c,ENT_QUOTES|ENT_HTML5,'UTF-8'); $row[]=cleanVal(preg_replace('/\s+/',' ',trim($c))); }
      $rows[]=$row;
    }
  } else {
    $buf2=preg_replace('/^\xEF\xBB\xBF/','',$buf);
    $sample=substr($buf2,0,4096); $delim=(substr_count($sample,';')>substr_count($sample,','))?';':',';
    $fh=fopen('php://temp','r+'); fwrite($fh,$buf2); rewind($fh);
    while(($r=fgetcsv($fh,0,$delim,'"',chr(92)))!==false){
      $empty=true; foreach($r as $c) if(trim((string)$c)!==''){ $empty=false; break; }
      if($empty) continue; $rows[]=array_map(fn($x)=>cleanVal($x),$r);
    } fclose($fh);
  }
  if(empty($rows)){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'Gagal parse: '.$e->getMessage()]); exit; }
}
if(empty($rows)){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'file kosong']); exit; }
// parse meta dapodik dari baris atas (sebelum header): sekolah, npsn, tahun, tgl unduh, pengunduh
$dapodikMeta=['sekolah'=>null,'npsn'=>null,'tahun_ajaran'=>null,'tanggal_unduh'=>null,'pengunduh'=>null,'email'=>null];
for($i=0;$i<min(12,count($rows));$i++){
  $line=implode(' ',array_filter(array_map(fn($v)=>trim((string)($v??'')),$rows[$i])));
  if(preg_match('/sekolah\s*[:\-]\s*(.+)/i',$line,$m)) $dapodikMeta['sekolah']=trim($m[1]);
  if(preg_match('/npsn\s*[:\-]\s*([0-9]+)/i',$line,$m)) $dapodikMeta['npsn']=trim($m[1]);
  if(preg_match('/tahun\s*ajaran\s*[:\-]?\s*([0-9]{4}\s*\/\s*[0-9]{4}[^\\n]*)/i',$line,$m)) $dapodikMeta['tahun_ajaran']=trim($m[1]);
  if(preg_match('/tahun\s*[:\-]?\s*([0-9]{4}\s*\/\s*[0-9]{4})/i',$line,$m) && !$dapodikMeta['tahun_ajaran']) $dapodikMeta['tahun_ajaran']=trim($m[1]);
  if(preg_match('/tanggal\s*unduh\s*[:\-]?\s*([0-9]{4}-[0-9]{2}-[0-9]{2}[ 0-9:]*)/i',$line,$m)) $dapodikMeta['tanggal_unduh']=trim($m[1]);
  if(preg_match('/pengunduh\s*[:\-]?\s*(.+?)(?:\s*\(([^)]+)\))?/i',$line,$m)){ $dapodikMeta['pengunduh']=trim($m[1]); if(!empty($m[2])) $dapodikMeta['email']=trim($m[2]); }
}
// fallback: scan all rows for tgl/pengunduh pattern if not found above
if(!$dapodikMeta['tanggal_unduh']||!$dapodikMeta['pengunduh']){
  for($i=0;$i<min(40,count($rows));$i++){
    $line=implode(' ',array_filter(array_map(fn($v)=>trim((string)($v??'')),$rows[$i])));
    if(!$dapodikMeta['tanggal_unduh'] && preg_match('/\b20\d{2}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\b/',$line,$m)) $dapodikMeta['tanggal_unduh']=$m[0];
    if(!$dapodikMeta['pengunduh'] && preg_match('/\b([A-Z ]+)\s*\(([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})\)/i',$line,$m)){ $dapodikMeta['pengunduh']=trim($m[1]); $dapodikMeta['email']=trim($m[2]); }
  }
}
if($dapodikMeta['sekolah']||$dapodikMeta['tanggal_unduh']){
  try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS dapodik_meta (id INT AUTO_INCREMENT PRIMARY KEY, sekolah VARCHAR(150), npsn VARCHAR(20), tahun_ajaran VARCHAR(20), tanggal_unduh VARCHAR(30), pengunduh VARCHAR(150), email_pengunduh VARCHAR(150), updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ex=(int)$pdo->query("SELECT COUNT(*) FROM dapodik_meta")->fetchColumn();
    if($ex===0) $pdo->prepare("INSERT INTO dapodik_meta(sekolah,npsn,tahun_ajaran,tanggal_unduh,pengunduh,email_pengunduh) VALUES(?,?,?,?,?,?)")->execute([$dapodikMeta['sekolah'],$dapodikMeta['npsn'],$dapodikMeta['tahun_ajaran'],$dapodikMeta['tanggal_unduh'],$dapodikMeta['pengunduh'],$dapodikMeta['email']]);
    else $pdo->prepare("UPDATE dapodik_meta SET sekolah=COALESCE(?,sekolah), npsn=COALESCE(?,npsn), tahun_ajaran=COALESCE(?,tahun_ajaran), tanggal_unduh=COALESCE(?,tanggal_unduh), pengunduh=COALESCE(?,pengunduh), email_pengunduh=COALESCE(?,email_pengunduh) WHERE id=1")->execute([$dapodikMeta['sekolah'],$dapodikMeta['npsn'],$dapodikMeta['tahun_ajaran'],$dapodikMeta['tanggal_unduh'],$dapodikMeta['pengunduh'],$dapodikMeta['email']]);
  }catch(Throwable $e){}
}
// cari header: baris mengandung no+nama+nipd
$hdr=-1;
for($i=0;$i<min(30,count($rows));$i++){
  $keys=array_map(fn($v)=>nkey($v??''),$rows[$i]);
  $set=array_flip(array_filter($keys));
  if(isset($set['no'])&&isset($set['nama'])&&isset($set['nipd'])){ $hdr=$i; break; }
}
if($hdr===-1){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'Header tidak ditemukan (butuh No, Nama, NIPD). File tarikan Dapodik asli?']); exit; }
// deteksi header 2 baris (Data Ayah/Ibu/Wali merged)
$subKeys=array_map(fn($v)=>nkey($v??''),$rows[$hdr+1]??[]);
$twoRows=(bool)(array_intersect(['tahunlahir','jenjangpendidikan','penghasilan'],$subKeys));
if($twoRows){
  // ffill grup (merged cell): fill empty from left
  $grp=$rows[$hdr]; $sub=$rows[$hdr+1];
  $cur=''; for($c=0;$c<count($grp);$c++){ if(ntext($grp[$c]??'')!=='') $cur=$grp[$c]; else $grp[$c]=$cur; }
  $names=[];
  for($c=0;$c<max(count($grp),count($sub));$c++){
    $g=ntext($grp[$c]??''); $s=ntext($sub[$c]??'');
    if($g!==''&&$s!==''&&strtolower($g)!==strtolower($s)) $names[]="$g - $s";
    elseif($s!=='') $names[]=$s;
    elseif($g!=='') $names[]=$g;
    else $names[]='_kosong';
  }
  $data=array_slice($rows,$hdr+2);
} else {
  $names=array_map(fn($v)=>ntext($v??''),$rows[$hdr]);
  $data=array_slice($rows,$hdr+1);
}
// map names -> db col via COLUMN_MAP
$dbCols=[]; $colIdxMap=[];
foreach($names as $i=>$nm){
  $k=nkey($nm); if($k===''||$k==='_kosong') continue;
  if(isset($COLUMN_MAP[$k])){ $dbCols[$COLUMN_MAP[$k]]=$i; }
}
if(!isset($dbCols['nama'])||!isset($dbCols['nipd'])){
  http_response_code(400); echo json_encode(['success'=>false,'error'=>'Header tidak dikenali — butuh Nama & NIPD/NISN']); exit;
}
// bersihkan data rows
$cleaned=[];
foreach($data as $r){
  $empty=true; foreach($r as $v) if($v!==null&&$v!==''){ $empty=false; break; }
  if($empty) continue;
  $cleaned[]=$r;
}
// skip sub-header row if present
if(!empty($cleaned)){
  $firstKeys=array_map(fn($v)=>nkey($v??''),$cleaned[0]);
  if(in_array('tahunlahir',$firstKeys)||in_array('penghasilan',$firstKeys)) $cleaned=array_slice($cleaned,1);
}
$kelasMap=[];
try{
  $st=$pdo->query("SELECT id,nama_kelas FROM kelas WHERE deleted_at IS NULL");
  foreach($st as $r) $kelasMap[strtolower(trim($r['nama_kelas']))]=$r['id'];
}catch(Throwable $e){}
$pdCols=array_keys($COLUMN_MAP); // not needed; we use $dbCols keys
$seen=[]; $results=[]; $ok=0; $fail=0; $skippedUpdate=0;
// preload existing peserta_didik by nisn/nipd for upsert check
$existingPd=[];
try{
  $st=$pdo->query("SELECT nisn,nipd FROM peserta_didik");
  foreach($st as $r){ if($r['nisn']) $existingPd['nisn:'.trim($r['nisn'])]=true; if($r['nipd']) $existingPd['nipd:'.trim($r['nipd'])]=true; }
}catch(Throwable $e){}
foreach($cleaned as $idx=>$rec){
  $line=$hdr+($twoRows?3:2)+$idx;
  $get=function($col) use($rec,$dbCols){ $i=$dbCols[$col]??null; if($i===null||!isset($rec[$i])) return null; $v=trim((string)$rec[$i]); return $v===''?null:$v; };
  $nipd=$get('nipd'); $nama=$get('nama'); $nisn=$get('nisn'); $jkRaw=$get('jenis_kelamin')??''; $rombel=$get('rombel'); $tglRaw=$get('tanggal_lahir');
  $jk=normJK($jkRaw);
  $tgl=$tglRaw?fixDate($tglRaw):null;
  $r=['line'=>$line,'nipd'=>$nipd??'','nama'=>$nama??'','kelas'=>$rombel??'','jk'=>$jk];
  if(!$nama){ $r['status']='gagal'; $r['error']='Nama kosong'; $results[]=$r; $fail++; continue; }
  if(!$nipd && !$nisn){ $r['status']='gagal'; $r['error']='NIPD/NISN kosong (salah satu wajib)'; $results[]=$r; $fail++; continue; }
  if($nipd && strlen($nipd)>25){ $r['status']='gagal'; $r['error']='NIPD max 25'; $results[]=$r; $fail++; continue; }
  $dupKey=null;
  if($nisn) $dupKey='nisn:'.trim($nisn); elseif($nipd) $dupKey='nipd:'.trim($nipd);
  if($dupKey && isset($seen[$dupKey])){ $r['status']='gagal'; $r['error']='Duplikat di file'; $results[]=$r; $fail++; continue; }
  if($dupKey) $seen[$dupKey]=$line;
  // check peserta_didik duplikat (for upsert decision)
  $isExisting=false;
  if($nisn && isset($existingPd['nisn:'.trim($nisn)])) $isExisting=true;
  elseif($nipd && isset($existingPd['nipd:'.trim($nipd)])) $isExisting=true;
  if($isExisting && !$upsert && !$dryRun){ $r['status']='gagal'; $r['error']='NISN/NIPD sudah ada di DB (aktifkan UPSERT untuk update)'; $results[]=$r; $fail++; continue; }
  // rombel -> kelas mapping (only for preview/save to siswa; peserta_didik always saves rombel string)
  $kelasId=null;
  if($rombel){
    $key=strtolower(trim($rombel));
    if(isset($kelasMap[$key])) $kelasId=$kelasMap[$key];
    else{
      if($autoKelas){
        $ting=tingkatPHP($rombel);
        try{ $ins=$pdo->prepare("INSERT INTO kelas(nama_kelas,tingkat) VALUES(?,?)"); $ins->execute([$rombel,$ting]); $kelasId=(int)$pdo->lastInsertId(); $kelasMap[$key]=$kelasId; }catch(Throwable $e){}
      }
      if($kelasId===null && !$dryRun){ /* peserta_didik tetap disimpan, siswa sync ditolak nanti */ }
    }
  }
  if($dryRun){
    $r['status']=$isExisting?'update':'ok'; $r['mode']=$isExisting?'upsert':'insert'; $results[]=$r; $ok++; continue;
  }
  // build peserta_didik row (all 60 cols)
  $pdRow=[];
  foreach($dbCols as $col=>$idx2){
    $v=$rec[$idx2]??null; $v=$v===null?null:trim((string)$v); if($v==='') $v=null;
    if($col==='tanggal_lahir' && $v) $v=fixDate($v);
    if($col==='jenis_kelamin' && $v) $v=normJK($v);
    $pdRow[$col]=$v;
  }
  // ensure jenis_kelamin normalized
  if(isset($pdRow['jenis_kelamin'])) $pdRow['jenis_kelamin']=normJK($pdRow['jenis_kelamin']);
  if(isset($pdRow['tanggal_lahir'])) $pdRow['tanggal_lahir']=fixDate($pdRow['tanggal_lahir']);
  try{
    if($isExisting && $upsert){
      // UPDATE peserta_didik by nisn or nipd
      $where = $nisn ? "nisn=?" : "nipd=?";
      $whereVal = $nisn ? $nisn : $nipd;
      $sets=[]; $vals=[];
      foreach($pdRow as $k=>$v){ $sets[]="`$k`=?"; $vals[]=$v; }
      if($sets){ $vals[]=$whereVal; $pdo->prepare("UPDATE peserta_didik SET ".implode(',',$sets)." WHERE $where LIMIT 1")->execute($vals); }
      $skippedUpdate++;
    } else {
      $cols=array_keys($pdRow); $ph=array_fill(0,count($cols),'?');
      $pdo->prepare("INSERT INTO peserta_didik(`".implode('`,`',$cols)."`) VALUES(".implode(',',$ph).")")->execute(array_values($pdRow));
      if($dupKey) $existingPd[$dupKey]=true;
    }
  }catch(Throwable $e){
    // if unique violation and upsert off, mark fail
    if(str_contains($e->getMessage(),'Duplicate') && !$upsert){ $r['status']='gagal'; $r['error']='Duplikat DB: '.substr($e->getMessage(),0,80); $results[]=$r; $fail++; continue; }
    // else ignore and continue to siswa sync? peserta_didik is master, so count fail if insert fails
    if(!$isExisting || !$upsert){ $r['status']='gagal'; $r['error']='DB peserta_didik: '.substr($e->getMessage(),0,120); $results[]=$r; $fail++; continue; }
  }
  // sync to siswa (for kesiswaan poin) — key by nipd (fallback nisn if nipd empty)
  $siswaNipd=$nipd?:$nisn;
  if($siswaNipd){
    $siswaNipd=trim($siswaNipd);
    $namaOrtu=$pdRow['ayah_nama']??($pdRow['ibu_nama']??($pdRow['wali_nama']??null));
    $hpOrtu=$pdRow['hp']??($pdRow['telepon']??null);
    $alamatFull=$pdRow['alamat']??null;
    // check siswa exists
    try{
      // check existing — include soft-deleted to restore (unique still holds); allow restore without UPSERT
      $chk=$pdo->prepare("SELECT id,deleted_at FROM siswa WHERE nipd=? LIMIT 1");
      $chk->execute([$siswaNipd]); $existingSiswa=$chk->fetch();
      $sid=$existingSiswa['id']??null; $isDeleted=!empty($existingSiswa['deleted_at']);
      if($sid){
        // restore soft-deleted even without UPSERT; update if upsert else just restore
        if($isDeleted){
          $pdo->prepare("UPDATE siswa SET deleted_at=NULL, nama=?,jenis_kelamin=?,kelas_id=COALESCE(?,kelas_id),tempat_lahir=COALESCE(?,tempat_lahir),tanggal_lahir=COALESCE(?,tanggal_lahir),nama_ortu=COALESCE(?,nama_ortu),hp_ortu=COALESCE(?,hp_ortu),alamat=COALESCE(?,alamat),status='Aktif' WHERE id=?")
              ->execute([$nama,$jk,$kelasId,$pdRow['tempat_lahir']??null,$tgl,$namaOrtu,$hpOrtu,$alamatFull,$sid]);
        } elseif($upsert){
          $pdo->prepare("UPDATE siswa SET nama=?,jenis_kelamin=?,kelas_id=COALESCE(?,kelas_id),tempat_lahir=COALESCE(?,tempat_lahir),tanggal_lahir=COALESCE(?,tanggal_lahir),nama_ortu=COALESCE(?,nama_ortu),hp_ortu=COALESCE(?,hp_ortu),alamat=COALESCE(?,alamat) WHERE id=?")
              ->execute([$nama,$jk,$kelasId,$pdRow['tempat_lahir']??null,$tgl,$namaOrtu,$hpOrtu,$alamatFull,$sid]);
        }
      } else {
        $nipdSiswa=substr($siswaNipd,0,20);
        $pdo->prepare("INSERT INTO siswa(nipd,nama,jenis_kelamin,kelas_id,tempat_lahir,tanggal_lahir,nama_ortu,hp_ortu,alamat,status) VALUES(?,?,?,?,?,?,?,?,?,?)")
            ->execute([$nipdSiswa,$nama,$jk,$kelasId,$pdRow['tempat_lahir']??null,$tgl,$namaOrtu,$hpOrtu,$alamatFull,'Aktif']);
      }
    }catch(Throwable $e){
      // siswa sync fail does not fail peserta_didik import; log to rincian as warning
      $r['warning']='siswa sync: '.substr($e->getMessage(),0,80);
    }
  }
  $r['status']=$isExisting && $upsert?'updated':'ok'; $results[]=$r; $ok++;
}
// log to dapodik_sync_log if not dryRun
if(!$dryRun && ($ok>0||$fail>0)){
  try{ $pdo->prepare("INSERT INTO dapodik_sync_log(jumlah_baru,jumlah_diperbarui,jumlah_gagal,dilakukan_oleh) VALUES(?,?,?,?)")->execute([$ok,$skippedUpdate,$fail,$_SESSION['user']['id']??null]); }catch(Throwable $e){}
}
echo json_encode(['success'=>true,'data'=>['berhasil'=>$ok,'gagal'=>$fail,'diperbarui'=>$skippedUpdate,'rincian'=>$results,'dry_run'=>$dryRun,'upsert'=>$upsert]], JSON_UNESCAPED_UNICODE);
