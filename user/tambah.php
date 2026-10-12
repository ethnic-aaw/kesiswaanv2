<?php
$active='user'; $title='Tambah User';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('mutate_user');

$ROLES=['Admin','Guru BK','Wali Kelas','Siswa'];
$err=''; $in=['nama'=>'','username'=>'','role'=>'Admin','nip'=>'','status'=>'Aktif','kelas_id'=>0];
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF'); }
  $in=['nama'=>trim($_POST['nama']??''),'username'=>trim($_POST['username']??''),'role'=>$_POST['role']??'','nip'=>trim($_POST['nip']??''),'status'=>($_POST['status']??'Aktif'),'kelas_id'=>(int)($_POST['kelas_id']??0)];
  $pw=$_POST['password']??'';
  try{
    if($in['nama']===''||$in['username']===''||$pw==='') throw new Exception('Nama, username, password wajib.');
    if(!in_array($in['role'],$ROLES,true)) throw new Exception('Role tidak valid.');
    if(strlen($pw)<8) throw new Exception('Password min 8 karakter.');
    if($in['role']==='Guru BK' && !preg_match('/^[^\s@]+@belajar\.id$/i',$in['username'])) throw new Exception('Guru BK wajib format nama@belajar.id.');
    if($in['status']!=='Nonaktif') $in['status']='Aktif';
    $hash=password_hash($pw, PASSWORD_BCRYPT);
    // upsert: username sudah ada (termasuk yg pernah di-soft-delete) → aktifkan ulang
    $st=$pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1"); $st->execute([$in['username']]); $ex=$st->fetchColumn();
    if($ex){
      $pdo->prepare("UPDATE users SET nama=?,password_hash=?,role=?,nip=?,status=?,deleted_at=NULL WHERE id=?")->execute([$in['nama'],$hash,$in['role'],$in['nip']?:null,$in['status'],$ex]);
      $uid=(int)$ex;
    } else {
      $pdo->prepare("INSERT INTO users(nama,username,password_hash,role,nip,status) VALUES(?,?,?,?,?,?)")->execute([$in['nama'],$in['username'],$hash,$in['role'],$in['nip']?:null,$in['status']]);
      $uid=(int)$pdo->lastInsertId();
    }
    if($in['role']==='Wali Kelas'){
      $pdo->prepare("UPDATE kelas SET wali_kelas_id=NULL WHERE wali_kelas_id=?")->execute([$uid]);
      if($in['kelas_id']) $pdo->prepare("UPDATE kelas SET wali_kelas_id=? WHERE id=? AND deleted_at IS NULL")->execute([$uid,$in['kelas_id']]);
    }
    header('Location: index.php?msg='.urlencode('User "'.$in['username'].'" disimpan')); exit;
  }catch(Throwable $e){ $err=$e->getMessage()?:'Gagal simpan'; }
}
$kelasList=[]; try{ $kelasList=$pdo->query("SELECT id,nama_kelas,tingkat FROM kelas WHERE deleted_at IS NULL ORDER BY tingkat,nama_kelas")->fetchAll(); }catch(Throwable $e){}
$h=fn($v)=>htmlspecialchars((string)$v);
require __DIR__.'/../includes/header.php';
?>
<?php if($err): ?><div class="mb-3 max-w-2xl rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=$h($err)?></div><?php endif; ?>
<form method="POST" class="max-w-2xl bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5 sm:p-6 space-y-4">
<?=csrf_field()?>
<div class="grid sm:grid-cols-2 gap-4">
  <label class="block sm:col-span-2"><span class="text-[13px] font-medium">Nama Lengkap <span class="text-red-500">*</span></span><input name="nama" required value="<?=$h($in['nama'])?>" placeholder="Nama" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Username <span class="text-red-500">*</span></span><input name="username" required value="<?=$h($in['username'])?>" placeholder="admin01 atau nama@belajar.id" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm font-mono"><span class="text-[11px] text-[#475569] dark:text-[#94A3B8]">Guru BK wajib @belajar.id</span></label>
  <label class="block"><span class="text-[13px] font-medium">Password <span class="text-red-500">*</span></span>
    <div class="relative mt-1"><input name="password" id="pw" type="password" required minlength="8" placeholder="Min 8 karakter" class="w-full h-10 px-3 pr-9 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><button type="button" onclick="const p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-[#94A3B8]"><i data-lucide="eye" class="w-4 h-4"></i></button></div>
  </label>
  <label class="block"><span class="text-[13px] font-medium">Role <span class="text-red-500">*</span></span><select name="role" id="role" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><?php foreach($ROLES as $r): ?><option <?=$in['role']===$r?'selected':''?>><?=$r?></option><?php endforeach; ?></select></label>
  <label id="kelasWrap" class="hidden"><span class="text-[13px] font-medium">Kelas Diampu</span><select name="kelas_id" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option value="">— Pilih —</option><?php foreach($kelasList as $k): ?><option value="<?=$k['id']?>" <?=(int)$in['kelas_id']===(int)$k['id']?'selected':''?>><?=$h($k['nama_kelas'])?></option><?php endforeach; ?></select><span class="text-[11px] text-[#475569] dark:text-[#94A3B8]">Set kelas.wali_kelas_id otomatis</span></label>
  <label class="block"><span class="text-[13px] font-medium">NIP (opsional)</span><input name="nip" value="<?=$h($in['nip'])?>" placeholder="NIP guru" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Status</span><select name="status" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option <?=$in['status']==='Aktif'?'selected':''?>>Aktif</option><option <?=$in['status']==='Nonaktif'?'selected':''?>>Nonaktif</option></select></label>
</div>
<div class="flex gap-2"><button class="h-10 px-5 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold">Simpan</button><a href="/kesiswaanv2/user/index.php" class="h-10 px-5 rounded-input border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Batal</a></div>
</form>
<script>
const role=document.getElementById('role'), wrap=document.getElementById('kelasWrap');
function sync(){ wrap.classList.toggle('hidden', role.value!=='Wali Kelas'); }
role.addEventListener('change',sync); sync();
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
