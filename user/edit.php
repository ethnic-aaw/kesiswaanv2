<?php
$active='user'; $title='Edit User';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('mutate_user');

$ROLES=['Admin','Guru BK','Wali Kelas','Siswa'];
$id=(int)($_GET['id']??0);
$err='';
try{
  $st=$pdo->prepare("SELECT id,nama,username,role,nip,status FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1"); $st->execute([$id]); $u=$st->fetch();
}catch(Throwable $e){ $u=null; }
if(!$u){ header('Location: index.php?err='.urlencode('User tidak ditemukan')); exit; }
$kelasId=0; try{ $st=$pdo->prepare("SELECT id FROM kelas WHERE wali_kelas_id=? AND deleted_at IS NULL LIMIT 1"); $st->execute([$id]); $kelasId=(int)($st->fetchColumn()?:0); }catch(Throwable $e){}

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF'); }
  $nama=trim($_POST['nama']??''); $username=trim($_POST['username']??''); $role=$_POST['role']??''; $nip=trim($_POST['nip']??'');
  $status=($_POST['status']??'Aktif')==='Nonaktif'?'Nonaktif':'Aktif'; $kelasId=(int)($_POST['kelas_id']??0);
  $pw=$_POST['password']??'';
  try{
    if($nama===''||$username==='') throw new Exception('Nama & username wajib.');
    if(!in_array($role,$ROLES,true)) throw new Exception('Role tidak valid.');
    if($role==='Guru BK' && !preg_match('/^[^\s@]+@belajar\.id$/i',$username)) throw new Exception('Guru BK wajib format nama@belajar.id.');
    $st=$pdo->prepare("SELECT id FROM users WHERE username=? AND id<>? LIMIT 1"); $st->execute([$username,$id]);
    if($st->fetchColumn()) throw new Exception('Username sudah dipakai user lain.');
    if($pw!==''){ if(strlen($pw)<8) throw new Exception('Password min 8 karakter.'); $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($pw,PASSWORD_BCRYPT),$id]); }
    $pdo->prepare("UPDATE users SET nama=?,username=?,role=?,nip=?,status=? WHERE id=?")->execute([$nama,$username,$role,$nip?:null,$status,$id]);
    $pdo->prepare("UPDATE kelas SET wali_kelas_id=NULL WHERE wali_kelas_id=?")->execute([$id]);
    if($role==='Wali Kelas' && $kelasId) $pdo->prepare("UPDATE kelas SET wali_kelas_id=? WHERE id=? AND deleted_at IS NULL")->execute([$id,$kelasId]);
    header('Location: index.php?msg='.urlencode('User "'.$username.'" diperbarui')); exit;
  }catch(Throwable $e){ $err=$e->getMessage()?:'Gagal simpan'; $u=['nama'=>$nama,'username'=>$username,'role'=>$role,'nip'=>$nip,'status'=>$status,'id'=>$id]; }
}
$kelasList=[]; try{ $kelasList=$pdo->query("SELECT id,nama_kelas,tingkat FROM kelas WHERE deleted_at IS NULL ORDER BY tingkat,nama_kelas")->fetchAll(); }catch(Throwable $e){}
$h=fn($v)=>htmlspecialchars((string)$v);
require __DIR__.'/../includes/header.php';
?>
<?php if($err): ?><div class="mb-3 max-w-2xl rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=$h($err)?></div><?php endif; ?>
<form method="POST" class="max-w-2xl bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5 sm:p-6 space-y-4">
<?=csrf_field()?>
<div class="grid sm:grid-cols-2 gap-4">
  <label class="block sm:col-span-2"><span class="text-[13px] font-medium">Nama Lengkap <span class="text-red-500">*</span></span><input name="nama" required value="<?=$h($u['nama'])?>" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Username <span class="text-red-500">*</span></span><input name="username" required value="<?=$h($u['username'])?>" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm font-mono"></label>
  <label class="block"><span class="text-[13px] font-medium">Password Baru (opsional)</span>
    <div class="relative mt-1"><input name="password" id="pw" type="password" minlength="8" placeholder="Kosongkan bila tidak diubah" class="w-full h-10 px-3 pr-9 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><button type="button" onclick="const p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-[#94A3B8]"><i data-lucide="eye" class="w-4 h-4"></i></button></div>
  </label>
  <label class="block"><span class="text-[13px] font-medium">Role <span class="text-red-500">*</span></span><select name="role" id="role" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><?php foreach($ROLES as $r): ?><option <?=($u['role']??'')===$r?'selected':''?>><?=$r?></option><?php endforeach; ?></select></label>
  <label id="kelasWrap" class="hidden"><span class="text-[13px] font-medium">Kelas Diampu</span><select name="kelas_id" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option value="">— Pilih —</option><?php foreach($kelasList as $k): ?><option value="<?=$k['id']?>" <?=$kelasId===(int)$k['id']?'selected':''?>><?=$h($k['nama_kelas'])?></option><?php endforeach; ?></select></label>
  <label class="block"><span class="text-[13px] font-medium">NIP (opsional)</span><input name="nip" value="<?=$h($u['nip'])?>" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Status</span><select name="status" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option <?=$u['status']==='Aktif'?'selected':''?>>Aktif</option><option <?=$u['status']==='Nonaktif'?'selected':''?>>Nonaktif</option></select></label>
</div>
<div class="flex gap-2"><button class="h-10 px-5 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold">Simpan Perubahan</button><a href="/kesiswaanv2/user/index.php" class="h-10 px-5 rounded-input border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Batal</a></div>
</form>
<script>
const role=document.getElementById('role'), wrap=document.getElementById('kelasWrap');
function sync(){ wrap.classList.toggle('hidden', role.value!=='Wali Kelas'); }
role.addEventListener('change',sync); sync();
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
