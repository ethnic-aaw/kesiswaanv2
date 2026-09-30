<?php $active='siswa'; $title='Edit Siswa';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='GET') require_can('view_siswa');
if($_SERVER['REQUEST_METHOD']==='POST') require_can('mutate_siswa');
$id=(int)($_GET['id']??0); $err='';
if(!$id){ header('Location: index.php'); exit; }
$row=null; try{ $st=$pdo->prepare("SELECT * FROM siswa WHERE id=? AND deleted_at IS NULL LIMIT 1"); $st->execute([$id]); $row=$st->fetch(); }catch(Throwable $e){}
if(!$row){ header('Location: index.php?err='.urlencode('Siswa tidak ditemukan')); exit; }
$isWaliEdit = (current_user()['role']??'')==='Wali Kelas';
$isAdminEdit = (current_user()['role']??'')==='Admin';
// Wali Kelas: hanya boleh edit foto kelas ampu — selain itu 403
if($isWaliEdit && !can_change_foto($pdo,(int)$row['id'])){
  $ampu = wali_ampu_names($pdo,(int)($_SESSION['user']['id']??0));
  http_response_code(403);
  exit('Forbidden: hanya bisa edit foto siswa kelas ampu '.htmlspecialchars(implode(', ',$ampu)?:'—'));
}
// Admin/Wali Kelas foto-only notice — full edit tetap via POST tapi UI foto-only untuk Wali Kelas
$kelasOpts=[]; try{
  if($isWaliEdit){
    $ids=wali_ampu_ids($pdo,(int)($_SESSION['user']['id']??0));
    if($ids){
      $in=implode(',',array_map('intval',$ids));
      foreach($pdo->query("SELECT id,nama_kelas FROM kelas WHERE id IN ($in) AND deleted_at IS NULL ORDER BY nama_kelas") as $r) $kelasOpts[]=$r;
      // pastikan kelas siswa saat ini tetap muncul walau beda TA
      if($row['kelas_id'] && !in_array((int)$row['kelas_id'],array_column($kelasOpts,'id'))){
        $st=$pdo->prepare("SELECT id,nama_kelas FROM kelas WHERE id=? LIMIT 1"); $st->execute([(int)$row['kelas_id']]); if($k=$st->fetch()) $kelasOpts[]=$k;
      }
    }
  } else {
    foreach($pdo->query("SELECT id,nama_kelas FROM kelas WHERE deleted_at IS NULL ORDER BY nama_kelas") as $r) $kelasOpts[]=$r;
  }
}catch(Throwable $e){}
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_verify($_POST['csrf_token']??'')){ $err='CSRF token tidak valid'; }
  else{
    // Wali Kelas: foto-only — ignore field lain
    if($isWaliEdit){
      if(!isset($_FILES['foto']) || $_FILES['foto']['error']===4){ $err='Pilih foto dulu (JPG/PNG ≤500KB)'; }
      elseif($_FILES['foto']['error']!==0){ $err='Upload gagal'; }
      elseif($_FILES['foto']['size']>500*1024){ $err='Foto maks 500KB'; }
      else{
        $tmp=$_FILES['foto']['tmp_name']; $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$tmp); finfo_close($finfo);
        if(!in_array($mime,['image/jpeg','image/png'])) $err='Foto hanya JPG/PNG';
        else{
          $ext=$mime==='image/png'?'.png':'.jpg'; $fotoVal=bin2hex(random_bytes(8)).$ext;
          $dir=__DIR__.'/../assets/uploads/foto_siswa'; @mkdir($dir,0755,true); $dest="$dir/$fotoVal";
          try{ $src=$mime==='image/png'?imagecreatefrompng($tmp):imagecreatefromjpeg($tmp); if($src){ $dst=imagecreatetruecolor(150,150); if($mime==='image/png'){ imagealphablending($dst,false); imagesavealpha($dst,true); } $w=imagesx($src);$h=imagesy($src); imagecopyresampled($dst,$src,0,0,0,0,150,150,$w,$h); if($mime==='image/png') imagepng($dst,$dest); else imagejpeg($dst,$dest,85); imagedestroy($src);imagedestroy($dst);} else move_uploaded_file($tmp,$dest);}catch(Throwable $e){ move_uploaded_file($tmp,$dest); }
          if(!$err){
            try{
              $old=$row['foto']??null;
              $pdo->prepare("UPDATE siswa SET foto=? WHERE id=?")->execute([$fotoVal,$id]);
              if($old){ $op=__DIR__.'/../assets/uploads/foto_siswa/'.$old; if(is_file($op)) @unlink($op); }
              header('Location: index.php?msg='.urlencode('Foto '.htmlspecialchars($row['nama']).' diperbarui')); exit;
            }catch(Throwable $e){ $err=$e->getMessage(); }
          }
        }
      }
    } else {
    $nipd=trim($_POST['nipd']??$row['nipd']); $nama=trim($_POST['nama']??$row['nama']); $kelasId=$_POST['kelas_id']??$row['kelas_id']; $status=$_POST['status']??$row['status']; $hp=trim($_POST['hp']??''); $alamat=trim($_POST['alamat']??'');
    if($nipd===''||$nama==='') $err='NIPD dan Nama wajib';
    else{
      $hpClean=null; if($hp!==''){ $hpClean=preg_replace('/[^0-9+]/','',$hp); if(str_starts_with(trim($_POST['hp']),'+') && !str_starts_with($hpClean,'+')) $hpClean='+'.$hpClean; if(str_starts_with($hpClean,'62')) $hpClean='+62'.substr($hpClean,2); if(!preg_match('/^(\+62|08)[0-9]{8,13}$/',str_replace(' ','',$hpClean))) $err='Format HP harus 08… atau +62…'; }
      if(!$err){
        $kid=$kelasId!==''?(int)$kelasId:null;
        $fotoSet=''; $fotoVal=null;
        if(isset($_FILES['foto']) && $_FILES['foto']['error']!==4){
          if($_FILES['foto']['size']>500*1024) $err='Foto maks 500KB';
          else{
            $tmp=$_FILES['foto']['tmp_name']; $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$tmp); finfo_close($finfo);
            if(!in_array($mime,['image/jpeg','image/png'])) $err='Foto hanya JPG/PNG';
            else{
              $ext=$mime==='image/png'?'.png':'.jpg'; $fotoVal=bin2hex(random_bytes(8)).$ext;
              $dir=__DIR__.'/../assets/uploads/foto_siswa'; @mkdir($dir,0755,true); $dest="$dir/$fotoVal";
              try{ $src=$mime==='image/png'?imagecreatefrompng($tmp):imagecreatefromjpeg($tmp); if($src){ $dst=imagecreatetruecolor(150,150); if($mime==='image/png'){ imagealphablending($dst,false); imagesavealpha($dst,true); } $w=imagesx($src);$h=imagesy($src); imagecopyresampled($dst,$src,0,0,0,0,150,150,$w,$h); if($mime==='image/png') imagepng($dst,$dest); else imagejpeg($dst,$dest,85); imagedestroy($src);imagedestroy($dst);} else move_uploaded_file($tmp,$dest);}catch(Throwable $e){ move_uploaded_file($tmp,$dest); }
              $fotoSet=',foto=?';
            }
          }
        }
        if(!$err){
          try{
            if($fotoVal) $pdo->prepare("UPDATE siswa SET nipd=?,nama=?,kelas_id=?,hp_ortu=?,alamat=?,status=?,foto=? WHERE id=?")->execute([$nipd,$nama,$kid,$hpClean,$alamat?:null,$status,$fotoVal,$id]);
            else $pdo->prepare("UPDATE siswa SET nipd=?,nama=?,kelas_id=?,hp_ortu=?,alamat=?,status=? WHERE id=?")->execute([$nipd,$nama,$kid,$hpClean,$alamat?:null,$status,$id]);
            header('Location: index.php?msg='.urlencode('Perubahan disimpan')); exit;
          }catch(Throwable $e){ $err=$e->getMessage(); if(str_contains($err,'Duplicate')) $err='NIPD sudah dipakai'; }
        }
      }
    }
    } // end Admin branch
  }
  // refresh row for display after error
  try{ $st=$pdo->prepare("SELECT * FROM siswa WHERE id=? LIMIT 1"); $st->execute([$id]); $row=$st->fetch()?:$row; }catch(Throwable $e){}
}
?>
<?php require __DIR__.'/../includes/header.php'; ?>
<?php if($err): ?><div class="mb-4 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if($isWaliEdit): ?><div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 px-4 py-2.5 text-xs text-amber-800 dark:text-amber-200">Mode Wali Kelas — edit terbatas: <b>hanya ganti foto</b> untuk kelas ampu. Data lain read-only.</div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="max-w-3xl bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-card dark:shadow-none p-5 sm:p-6 space-y-5">
<?=csrf_field()?>
<div class="flex items-center gap-3">
  <?php $foto=$row['foto']? '/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($row['foto']): 'https://i.pravatar.cc/100?u='.urlencode($row['nipd']); ?>
  <img src="<?=$foto?>" class="w-14 h-14 rounded-full object-cover border">
  <div><div class="font-semibold">Edit #<?=$id?> — <?=htmlspecialchars($row['nama'])?></div><div class="text-xs text-[#475569] dark:text-[#94A3B8]">NIPD <?=htmlspecialchars($row['nipd'])?> · <?= $isWaliEdit ? 'Ganti foto saja · <a href="/kesiswaanv2/siswa/foto.php?id='.$id.'" class="text-[#2563EB] underline">mode cepat foto.php</a>' : 'Perpindahan kelas manual di sini (§4.5)' ?></div></div>
</div>
<div class="grid sm:grid-cols-2 gap-4">
  <label class="block"><span class="text-[13px] font-medium">NIPD <span class="text-red-500">*</span></span><input name="nipd" value="<?=htmlspecialchars($row['nipd'])?>" required <?= $isWaliEdit?'readonly tabindex="-1" class="mt-1 w-full h-10 px-3 rounded-input border bg-[#F8FAFC] dark:bg-[#0F172A]/50 text-sm opacity-70 cursor-not-allowed"' : 'class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"' ?>></label>
  <label class="block"><span class="text-[13px] font-medium">Nama Lengkap <span class="text-red-500">*</span></span><input name="nama" value="<?=htmlspecialchars($row['nama'])?>" required <?= $isWaliEdit?'readonly tabindex="-1" class="mt-1 w-full h-10 px-3 rounded-input border bg-[#F8FAFC] dark:bg-[#0F172A]/50 text-sm opacity-70 cursor-not-allowed"' : 'class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"' ?>></label>
  <label class="block"><span class="text-[13px] font-medium">Kelas</span><select name="kelas_id" <?= $isWaliEdit?'disabled tabindex="-1"':' ' ?> class="mt-1 w-full h-10 px-3 rounded-input border <?= $isWaliEdit?'bg-[#F8FAFC] dark:bg-[#0F172A]/50 opacity-70 cursor-not-allowed':'border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A]' ?> text-sm"><option value="">— Tanpa kelas —</option><?php foreach($kelasOpts as $k): ?><option value="<?=$k['id']?>" <?= (string)$row['kelas_id']===(string)$k['id']?'selected':'' ?>><?=htmlspecialchars($k['nama_kelas'])?></option><?php endforeach; ?></select><?php if($isWaliEdit): ?><input type="hidden" name="kelas_id" value="<?=htmlspecialchars($row['kelas_id']??'')?>"><?php endif; ?></label>
  <label class="block"><span class="text-[13px] font-medium">Status</span><select name="status" <?= $isWaliEdit?'disabled tabindex="-1"':' ' ?> class="mt-1 w-full h-10 px-3 rounded-input border <?= $isWaliEdit?'bg-[#F8FAFC] dark:bg-[#0F172A]/50 opacity-70 cursor-not-allowed':'border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A]' ?> text-sm"><option <?= $row['status']==='Aktif'?'selected':'' ?>>Aktif</option><option <?= $row['status']==='Pindah'?'selected':'' ?>>Pindah</option><option <?= $row['status']==='Lulus'?'selected':'' ?>>Lulus</option><option <?= $row['status']==='Tidak Aktif'?'selected':'' ?>>Tidak Aktif</option></select><?php if($isWaliEdit): ?><input type="hidden" name="status" value="<?=htmlspecialchars($row['status'])?>"><?php endif; ?></label>
  <label class="block"><span class="text-[13px] font-medium">No. HP Orang Tua</span><input name="hp" value="<?=htmlspecialchars($row['hp_ortu']??'')?>" <?= $isWaliEdit?'readonly tabindex="-1" class="mt-1 w-full h-10 px-3 rounded-input border bg-[#F8FAFC] dark:bg-[#0F172A]/50 text-sm opacity-70 cursor-not-allowed"' : 'class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"' ?>></label>
  <label class="block"><span class="text-[13px] font-medium">Alamat</span><input name="alamat" value="<?=htmlspecialchars($row['alamat']??'')?>" <?= $isWaliEdit?'readonly tabindex="-1" class="mt-1 w-full h-10 px-3 rounded-input border bg-[#F8FAFC] dark:bg-[#0F172A]/50 text-sm opacity-70 cursor-not-allowed"' : 'class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"' ?>></label>
  <label class="block sm:col-span-2"><span class="text-[13px] font-medium">Ganti Foto (JPG/PNG ≤500KB)</span><input type="file" name="foto" accept=".jpg,.jpeg,.png" <?= $isWaliEdit?'required':'' ?> class="mt-1 w-full text-sm"></label>
</div>
<div class="flex gap-2"><button class="h-10 px-5 rounded-input bg-[#2563EB] text-white text-sm font-semibold"><?= $isWaliEdit?'Simpan Foto':'Simpan Perubahan' ?></button><a href="/kesiswaanv2/siswa/index.php" class="h-10 px-5 rounded-input border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Batal</a></div>
</form>
<?php require __DIR__.'/../includes/footer.php'; ?>
