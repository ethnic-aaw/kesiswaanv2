<?php $active='siswa'; $title='Tambah Siswa';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='GET') require_can('view_siswa');
if($_SERVER['REQUEST_METHOD']==='POST') require_can('mutate_siswa');
$err=''; $msg='';
$kelasOpts=[]; try{ foreach($pdo->query("SELECT id,nama_kelas FROM kelas WHERE deleted_at IS NULL ORDER BY nama_kelas") as $r) $kelasOpts[]=$r; }catch(Throwable $e){}
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_verify($_POST['csrf_token']??'')){ $err='CSRF token tidak valid'; }
  else{
    $nipd=trim($_POST['nipd']??''); $nama=trim($_POST['nama']??''); $jk=$_POST['jk']??'L'; $kelasId=$_POST['kelas_id']??''; $tempat=trim($_POST['tempat']??''); $tgl=$_POST['tgl']??''; $ortu=trim($_POST['ortu']??''); $hp=trim($_POST['hp']??''); $alamat=trim($_POST['alamat']??''); $status=$_POST['status']??'Aktif';
    if($nipd===''||$nama==='') $err='NIPD dan Nama wajib';
    elseif(strlen($nipd)>20) $err='NIPD max 20';
    else{
      $hpClean=''; if($hp!==''){ $hpClean=preg_replace('/[^0-9+]/','',$hp); if(str_starts_with(trim($hp),'+') && !str_starts_with($hpClean,'+')) $hpClean='+'.$hpClean; if(str_starts_with($hpClean,'62')) $hpClean='+62'.substr($hpClean,2); if($hpClean!=='' && !preg_match('/^(\+62|08)[0-9]{8,13}$/',str_replace(' ','',$hpClean))) $err='Format HP harus 08… atau +62… (8-13 digit)'; }
      if(!$err){
        $fotoName=null;
        if(isset($_FILES['foto']) && $_FILES['foto']['error']!==4){
          if($_FILES['foto']['size']>500*1024) $err='Foto maks 500KB';
          else{
            $tmp=$_FILES['foto']['tmp_name'];
            $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$tmp); finfo_close($finfo);
            if(!in_array($mime,['image/jpeg','image/png'])) $err='Foto hanya JPG/PNG';
            else{
              $ext=$mime==='image/png'?'.png':'.jpg';
              $fotoName=bin2hex(random_bytes(8)).$ext;
              $dir=__DIR__.'/../assets/uploads/foto_siswa'; @mkdir($dir,0755,true);
              $dest="$dir/$fotoName";
              // resize 150x150
              try{
                $src = $mime==='image/png'? imagecreatefrompng($tmp): imagecreatefromjpeg($tmp);
                if($src){ $dst=imagecreatetruecolor(150,150); if($mime==='image/png'){ imagealphablending($dst,false); imagesavealpha($dst,true); } $w=imagesx($src); $h=imagesy($src); imagecopyresampled($dst,$src,0,0,0,0,150,150,$w,$h); if($mime==='image/png') imagepng($dst,$dest); else imagejpeg($dst,$dest,85); imagedestroy($src); imagedestroy($dst); }
                else move_uploaded_file($tmp,$dest);
              }catch(Throwable $e){ move_uploaded_file($tmp,$dest); }
            }
          }
        }
        if(!$err){
          try{
            $kid=$kelasId!==''?(int)$kelasId:null;
            $pdo->prepare("INSERT INTO siswa(nipd,nama,jenis_kelamin,kelas_id,tempat_lahir,tanggal_lahir,nama_ortu,hp_ortu,foto,alamat,status) VALUES(?,?,?,?,?,?,?,?,?,?)")->execute([$nipd,$nama,$jk,$kid,$tempat?:null,$tgl?:null,$ortu?:null,$hpClean?:null,$fotoName,$alamat?:null,$status]);
            header('Location: index.php?msg='.urlencode('Siswa ditambahkan')); exit;
          }catch(Throwable $e){ $err=$e->getMessage(); if(str_contains($err,'Duplicate')) $err='NIPD sudah dipakai'; }
        }
      }
    }
  }
}
?>
<?php require __DIR__.'/../includes/header.php'; ?>
<?php if($err): ?><div class="mb-4 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($err)?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="max-w-3xl bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-card dark:shadow-none p-5 sm:p-6 space-y-5">
<?=csrf_field()?>
<div class="grid sm:grid-cols-2 gap-4">
  <label class="block"><span class="text-[13px] font-medium">NIPD / NIS <span class="text-red-500">*</span></span><input name="nipd" required maxlength="20" value="<?=htmlspecialchars($_POST['nipd']??'')?>" placeholder="2024xxx" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Nama Lengkap <span class="text-red-500">*</span></span><input name="nama" required maxlength="100" value="<?=htmlspecialchars($_POST['nama']??'')?>" placeholder="Nama siswa" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Jenis Kelamin</span><select name="jk" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option value="L" <?=($_POST['jk']??'L')==='L'?'selected':''?>>Laki-laki</option><option value="P" <?=($_POST['jk']??'')==='P'?'selected':''?>>Perempuan</option></select></label>
  <label class="block"><span class="text-[13px] font-medium">Kelas</span><select name="kelas_id" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option value="">— Tanpa kelas —</option><?php foreach($kelasOpts as $k): ?><option value="<?=$k['id']?>" <?= (($_POST['kelas_id']??'')==(string)$k['id']?'selected':'') ?>><?=htmlspecialchars($k['nama_kelas'])?></option><?php endforeach; ?></select></label>
  <label class="block"><span class="text-[13px] font-medium">Tempat Lahir</span><input name="tempat" value="<?=htmlspecialchars($_POST['tempat']??'')?>" placeholder="Kota" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Tanggal Lahir</span><input type="date" name="tgl" value="<?=htmlspecialchars($_POST['tgl']??'')?>" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Nama Orang Tua</span><input name="ortu" value="<?=htmlspecialchars($_POST['ortu']??'')?>" placeholder="Nama ayah/ibu" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">No. HP Orang Tua</span><input name="hp" value="<?=htmlspecialchars($_POST['hp']??'')?>" placeholder="08xxxxxxxxxx atau +62xxxxxxxxxx" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><span class="text-[11px] text-[#475569] dark:text-[#94A3B8]">Disimpan normalisasi untuk WA gateway</span></label>
  <label class="block sm:col-span-2"><span class="text-[13px] font-medium">Alamat</span><textarea name="alamat" rows="2" placeholder="Alamat lengkap" class="mt-1 w-full px-3 py-2 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><?=htmlspecialchars($_POST['alamat']??'')?></textarea></label>
  <label class="block"><span class="text-[13px] font-medium">Status</span><select name="status" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option <?=($_POST['status']??'Aktif')==='Aktif'?'selected':''?>>Aktif</option><option <?=($_POST['status']??'')==='Tidak Aktif'?'selected':''?>>Tidak Aktif</option><option <?=($_POST['status']??'')==='Pindah'?'selected':''?>>Pindah</option><option <?=($_POST['status']??'')==='Lulus'?'selected':''?>>Lulus</option></select></label>
  <div class="block">
    <span class="text-[13px] font-medium">Foto (JPG/PNG ≤500KB)</span>
    <label class="mt-1 flex flex-col items-center justify-center gap-2 h-[84px] rounded-input border-2 border-dashed border-[#CBD5E1] dark:border-[#334155] bg-[#F8FAFC] dark:bg-[#0F172A] cursor-pointer hover:bg-[#F1F5F9] dark:hover:bg-white/5">
      <i data-lucide="image" class="w-6 h-6 text-[#94A3B8]"></i><span class="text-xs text-[#475569] dark:text-[#94A3B8]">Klik / drag & drop</span>
      <input type="file" name="foto" accept=".jpg,.jpeg,.png" class="hidden" onchange="previewFoto(this)">
    </label>
    <img id="prev" class="hidden mt-2 w-14 h-14 rounded-lg object-cover border">
  </div>
</div>
<div class="flex gap-2 pt-2">
  <button type="submit" class="h-10 px-5 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold">Simpan</button>
  <a href="/kesiswaanv2/siswa/index.php" class="h-10 px-5 rounded-input bg-white dark:bg-[#0F172A] border border-[#E2E8F0] dark:border-[#334155] text-sm font-medium inline-flex items-center">Batal</a>
</div>
</form>
<script>
function previewFoto(i){
  const f=i.files[0]; if(!f) return;
  if(!['image/jpeg','image/png'].includes(f.type)) return toast('Hanya JPG/PNG','error');
  if(f.size>500*1024) return toast('Maks 500KB','error');
  const p=document.getElementById('prev'); p.src=URL.createObjectURL(f); p.classList.remove('hidden');
}
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
