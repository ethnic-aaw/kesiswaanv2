<?php $active='siswa'; $title='Ganti Foto Siswa';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
$id=(int)($_GET['id']??0); if(!$id){ header('Location: index.php'); exit; }
$row=null; try{ $st=$pdo->prepare("SELECT id,nipd,nama,kelas_id,COALESCE((SELECT nama_kelas FROM kelas k WHERE k.id=s.kelas_id),'—') kelas_nama,foto FROM siswa s WHERE id=? AND deleted_at IS NULL LIMIT 1"); $st->execute([$id]); $row=$st->fetch(); }catch(Throwable $e){}
if(!$row){ header('Location: index.php?err='.urlencode('Siswa tidak ditemukan')); exit; }
if(!can_change_foto($pdo,(int)$row['id'])){ http_response_code(403); exit('Forbidden: hanya Admin atau Wali Kelas ampu '.htmlspecialchars($row['kelas_nama']).' yang bisa ganti foto'); }
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_verify($_POST['csrf_token']??'')){ $err='CSRF token tidak valid'; }
  elseif(!isset($_FILES['foto']) || $_FILES['foto']['error']===4){ $err='Pilih file foto dulu'; }
  elseif($_FILES['foto']['error']!==0){ $err='Upload gagal: '.$_FILES['foto']['error']; }
  elseif($_FILES['foto']['size']>500*1024){ $err='Foto maks 500KB'; }
  else{
    $tmp=$_FILES['foto']['tmp_name'];
    $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$tmp); finfo_close($finfo);
    if(!in_array($mime,['image/jpeg','image/png'])) $err='Foto hanya JPG/PNG';
    else{
      $ext=$mime==='image/png'?'.png':'.jpg'; $fotoVal=bin2hex(random_bytes(8)).$ext;
      $dir=__DIR__.'/../assets/uploads/foto_siswa'; @mkdir($dir,0755,true); $dest="$dir/$fotoVal";
      try{
        $src=$mime==='image/png'?imagecreatefrompng($tmp):imagecreatefromjpeg($tmp);
        if($src){ $dst=imagecreatetruecolor(150,150); if($mime==='image/png'){ imagealphablending($dst,false); imagesavealpha($dst,true); } $w=imagesx($src);$h=imagesy($src); imagecopyresampled($dst,$src,0,0,0,0,150,150,$w,$h); if($mime==='image/png') imagepng($dst,$dest); else imagejpeg($dst,$dest,85); imagedestroy($src);imagedestroy($dst); }
        else move_uploaded_file($tmp,$dest);
      }catch(Throwable $e){ move_uploaded_file($tmp,$dest); }
      if(!$err){
        try{
          $old=$row['foto']??null;
          $pdo->prepare("UPDATE siswa SET foto=? WHERE id=?")->execute([$fotoVal,$id]);
          if($old && $old!==$fotoVal){ $oldPath=__DIR__.'/../assets/uploads/foto_siswa/'.$old; if(is_file($oldPath)) @unlink($oldPath); }
          header('Location: index.php?msg='.urlencode('Foto '.htmlspecialchars($row['nama']).' diperbarui')); exit;
        }catch(Throwable $e){ $err='Gagal simpan: '.$e->getMessage(); }
      }
    }
  }
  if($err){ try{ $st=$pdo->prepare("SELECT foto FROM siswa WHERE id=? LIMIT 1"); $st->execute([$id]); $row['foto']=$st->fetchColumn()?:$row['foto']; }catch(Throwable $e){} }
}
require __DIR__.'/../includes/header.php';
$foto=$row['foto']? '/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($row['foto']): 'https://i.pravatar.cc/150?u='.urlencode($row['nipd']);
?>
<?php if($err): ?><div class="mb-4 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($err)?></div><?php endif; ?>
<div class="max-w-lg bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-card dark:shadow-none p-5 sm:p-6 space-y-5">
  <div class="flex items-center gap-3">
    <img src="<?=$foto?>" class="w-14 h-14 rounded-full object-cover border">
    <div><div class="font-semibold text-sm"><?=htmlspecialchars($row['nama'])?></div><div class="text-xs text-[#475569] dark:text-[#94A3B8]">NIPD <?=htmlspecialchars($row['nipd'])?> · <?=htmlspecialchars($row['kelas_nama'])?></div><div class="text-[11px] text-[#94A3B8] mt-0.5">Hanya <?=htmlspecialchars(current_user()['role']??'')?> · <?=htmlspecialchars(current_user()['nama']??current_user()['username']??'')?> (<?= (current_user()['role']==='Wali Kelas' ? 'ampu: '.htmlspecialchars(implode(', ',wali_ampu_names($pdo,(int)($_SESSION['user']['id']??0)))?:'—') : 'semua kelas') ?>)</div></div>
  </div>
  <form method="POST" enctype="multipart/form-data" class="space-y-4">
    <?=csrf_field()?>
    <label class="block"><span class="text-[13px] font-medium">Foto baru (JPG/PNG ≤500KB) <span class="text-red-500">*</span></span>
      <label class="mt-1 flex flex-col items-center justify-center gap-2 h-[84px] rounded-input border-2 border-dashed border-[#CBD5E1] dark:border-[#334155] bg-[#F8FAFC] dark:bg-[#0F172A] cursor-pointer hover:bg-[#F1F5F9] dark:hover:bg-white/5">
        <i data-lucide="image" class="w-6 h-6 text-[#94A3B8]"></i><span class="text-xs text-[#475569] dark:text-[#94A3B8]">Klik / drag & drop</span>
        <input type="file" name="foto" accept=".jpg,.jpeg,.png" required class="hidden" onchange="previewFoto(this)">
      </label>
      <img id="prev" class="hidden mt-2 w-20 h-20 rounded-xl object-cover border">
    </label>
    <div class="flex gap-2"><button class="h-10 px-5 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold">Simpan Foto</button><a href="/kesiswaanv2/siswa/index.php" class="h-10 px-5 rounded-input border bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Batal</a><a href="/kesiswaanv2/siswa/detail.php?id=<?=$id?>" class="h-10 px-5 rounded-input border bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Lihat Detail</a></div>
  </form>
</div>
<script>function previewFoto(i){ const f=i.files[0]; if(!f) return; if(!['image/jpeg','image/png'].includes(f.type)) return; if(f.size>500*1024) return; const p=document.getElementById('prev'); p.src=URL.createObjectURL(f); p.classList.remove('hidden'); }</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
