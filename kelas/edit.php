<?php $active='kelas'; $title='Edit Kelas';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
$id=(int)($_GET['id']??0); if(!$id){ header('Location: index.php'); exit; }
$row=null; try{ $st=$pdo->prepare("SELECT k.*, COALESCE(u.nama,'—') as wali FROM kelas k LEFT JOIN users u ON u.id=k.wali_kelas_id WHERE k.id=? AND k.deleted_at IS NULL LIMIT 1"); $st->execute([$id]); $row=$st->fetch(); }catch(Throwable $e){}
if(!$row){ header('Location: index.php?err='.urlencode('Kelas tidak ditemukan')); exit; }
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_verify($_POST['csrf_token']??'')){ $err='CSRF tidak valid'; }
  else{
    $nama=trim($_POST['nama_kelas']??''); $tingkat=trim($_POST['tingkat']??''); $ta=trim($_POST['tahun_ajaran']??'');
    if($nama==='') $err='Nama kelas wajib';
    else{ try{ $pdo->prepare("UPDATE kelas SET nama_kelas=?,tingkat=?,tahun_ajaran=? WHERE id=?")->execute([$nama,$tingkat,$ta,$id]); header('Location: index.php?msg='.urlencode('Kelas diperbarui')); exit; }catch(Throwable $e){ $err=$e->getMessage(); } }
  }
}
?>
<?php require __DIR__.'/../includes/header.php'; ?>
<?php if($err): ?><div class="mb-4 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($err)?></div><?php endif; ?>
<form method="POST" class="max-w-md bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5 space-y-4">
  <?=csrf_field()?>
  <h3 class="font-semibold">Edit Kelas #<?=$id?></h3>
  <label class="block"><span class="text-[13px] font-medium">Nama Kelas</span><input name="nama_kelas" value="<?=htmlspecialchars($row['nama_kelas'])?>" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Tingkat</span><select name="tingkat" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option <?= $row['tingkat']==='X'?'selected':'' ?>>X</option><option <?= $row['tingkat']==='XI'?'selected':'' ?>>XI</option><option <?= $row['tingkat']==='XII'?'selected':'' ?>>XII</option><option <?= $row['tingkat']==='VII'?'selected':'' ?>>VII</option><option <?= $row['tingkat']==='VIII'?'selected':'' ?>>VIII</option><option <?= $row['tingkat']==='IX'?'selected':'' ?>>IX</option></select></label>
  <label class="block"><span class="text-[13px] font-medium">Wali Kelas (read-only)</span><input value="<?=htmlspecialchars($row['wali'])?>" disabled class="mt-1 w-full h-10 px-3 rounded-input border border-[#E2E8F0] dark:border-[#334155] bg-[#F8FAFC] dark:bg-[#0F172A] text-sm text-[#475569] dark:text-[#94A3B8]"><span class="text-[11px] text-[#94A3B8]">Ubah di Master User → Kelas Diampu</span></label>
  <label class="block"><span class="text-[13px] font-medium">Tahun Ajaran</span><input name="tahun_ajaran" value="<?=htmlspecialchars($row['tahun_ajaran'])?>" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <div class="flex gap-2"><button class="h-10 px-5 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan</button><a href="/kesiswaanv2/kelas/index.php" class="h-10 px-5 rounded-input border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Batal</a></div>
</form>
<?php require __DIR__.'/../includes/footer.php'; ?>
