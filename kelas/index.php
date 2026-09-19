<?php
$active='kelas'; $title='Master Kelas';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF tidak valid'); }
  $aksi=$_POST['aksi']??'';
  if($aksi==='tambah'){
    $nama=trim($_POST['nama_kelas']??''); $tingkat=trim($_POST['tingkat']??'X'); $ta=trim($_POST['tahun_ajaran']??'2024/2025');
    if($nama!==''){ try{ $pdo->prepare("INSERT INTO kelas(nama_kelas,tingkat,tahun_ajaran) VALUES(?,?,?)")->execute([$nama,$tingkat,$ta]); header('Location: index.php?msg='.urlencode("Kelas $nama ditambahkan")); exit; }catch(Throwable $e){ $err=$e->getMessage(); } }
  } elseif($aksi==='hapus'){
    $id=(int)($_POST['id']??0); try{ $pdo->prepare("UPDATE kelas SET deleted_at=NOW() WHERE id=?")->execute([$id]); header('Location: index.php?msg='.urlencode('Dihapus (soft-delete)')); exit; }catch(Throwable $e){ $err=$e->getMessage(); }
  }
}
require __DIR__.'/../includes/header.php';
$rows=[]; $msg=$_GET['msg']??''; $err=$err??($_GET['err']??'');
try{ $st=$pdo->query("SELECT k.id,k.nama_kelas,k.tingkat,COALESCE(u.nama,'—') as wali,k.tahun_ajaran,(SELECT COUNT(*) FROM siswa s WHERE s.kelas_id=k.id AND s.deleted_at IS NULL) as jml FROM kelas k LEFT JOIN users u ON u.id=k.wali_kelas_id WHERE k.deleted_at IS NULL ORDER BY k.tingkat,k.nama_kelas"); $rows=$st->fetchAll(); }catch(Throwable $e){ $rows=[]; }
$isDemo=false;
?>
<?php if($msg): ?><div class="mb-3 rounded-lg border-l-4 border-emerald-500 bg-[#F0FDF4] px-4 py-2.5 text-sm"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="mb-3 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if($isDemo): ?><div class="mb-3 rounded-lg border-l-4 border-amber-500 bg-[#FFFBEB] px-4 py-2.5 text-xs">Data demo — tambah kelas dahulu.</div><?php endif; ?>
<div class="flex justify-between items-center">
  <p class="text-xs text-[#475569] dark:text-[#94A3B8]">Wali Kelas diisi dari Master User — read-only di sini (§5.1).</p>
  <button onclick="document.getElementById('modalKelas').classList.remove('hidden')" class="h-9 px-4 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold inline-flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Tambah Kelas</button>
</div>
<div class="hidden md:block bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-4">
<table class="w-full text-sm">
<thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="px-4 py-3 text-left">Nama Kelas</th><th class="px-4 py-3 text-left">Tingkat</th><th class="px-4 py-3 text-left">Wali Kelas</th><th class="px-4 py-3 text-left">Jml Siswa</th><th class="px-4 py-3 text-left">TA</th><th class="px-4 py-3 text-left">Aksi</th></tr></thead>
<tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
<?php if(empty($rows)): ?><tr><td colspan="6" class="px-4 py-10 text-center text-sm text-[#94A3B8]">Belum ada data — <button onclick="document.getElementById('modalKelas').classList.remove('hidden')" class="underline text-[#2563EB]">Tambah Kelas</button> atau <a href="/kesiswaanv2/pengaturan/import.php" class="underline text-[#2563EB]">Import Dapodik</a></td></tr><?php endif; ?>
<?php foreach($rows as $r): ?>
<tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
  <td class="px-4 py-3 font-medium"><a href="/kesiswaanv2/siswa/index.php?kelas=<?=urlencode($r['nama_kelas'])?>" class="text-[#2563EB] dark:text-[#93C5FD] hover:underline"><?=htmlspecialchars($r['nama_kelas'])?></a></td>
  <td class="px-4 py-3"><span class="px-2 py-1 rounded-badge bg-[#F1F5F9] dark:bg-white/10 text-xs font-semibold"><?=htmlspecialchars($r['tingkat'])?></span></td>
  <td class="px-4 py-3 text-xs <?= $r['wali']==='—'?'text-[#94A3B8] italic':'' ?>"><?=htmlspecialchars($r['wali'])?></td>
  <td class="px-4 py-3"><?=htmlspecialchars($r['jml'])?></td>
  <td class="px-4 py-3 text-xs"><?=htmlspecialchars($r['tahun_ajaran'])?></td>
  <td class="px-4 py-3"><div class="flex gap-1.5">
    <a href="/kesiswaanv2/kelas/edit.php?id=<?=$r['id']?>" class="px-2.5 py-1.5 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium hover:bg-[#EFF6FF] inline-flex items-center gap-1"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</a>
    <form method="POST" onsubmit="return confirm('Hapus <?=htmlspecialchars($r['nama_kelas'],ENT_QUOTES)?>? (soft-delete)')" class="inline"><?=csrf_field()?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="px-2.5 py-1.5 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium hover:bg-[#FFF1F2] inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus</button></form>
  </div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="md:hidden space-y-3 mt-4">
<?php if(empty($rows)): ?><div class="bg-white dark:bg-[#1E293B] border border-dashed rounded-card p-6 text-center text-sm text-[#94A3B8]">Belum ada kelas — tambah dahulu</div><?php endif; ?>
<?php foreach($rows as $r): ?>
<div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
  <div class="font-semibold text-sm"><?=htmlspecialchars($r['nama_kelas'])?> <span class="ml-2 px-2 py-0.5 rounded-full bg-[#F1F5F9] dark:bg-white/10 text-xs"><?=htmlspecialchars($r['tingkat'])?> · <?=htmlspecialchars($r['tahun_ajaran'])?></span></div>
  <div class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1">Wali: <?=htmlspecialchars($r['wali'])?> · <?=htmlspecialchars($r['jml'])?> siswa</div>
  <div class="flex gap-2 mt-3"><a href="/kesiswaanv2/kelas/edit.php?id=<?=$r['id']?>" class="flex-1 h-8 inline-flex justify-center items-center gap-1 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</a>
  <form method="POST" onsubmit="return confirm('Hapus?')" class="flex-1 inline"><?=csrf_field()?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="w-full h-8 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium">Hapus</button></form></div>
</div>
<?php endforeach; ?>
</div>
<div id="modalKelas" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('modalKelas').classList.add('hidden')"></div>
  <form method="POST" class="relative bg-white dark:bg-[#1E293B] rounded-card border border-[#E2E8F0] dark:border-[#334155] p-5 w-full max-w-md space-y-4">
    <?=csrf_field()?><input type="hidden" name="aksi" value="tambah">
    <h3 class="font-semibold">Tambah Kelas</h3>
    <label class="block"><span class="text-[13px] font-medium">Nama Kelas <span class="text-red-500">*</span></span><input name="nama_kelas" required placeholder="XI IPA 1" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="block"><span class="text-[13px] font-medium">Tingkat</span><select name="tingkat" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option>X</option><option>XI</option><option>XII</option><option>VII</option><option>VIII</option><option>IX</option></select></label>
    <label class="block"><span class="text-[13px] font-medium">Tahun Ajaran</span><input name="tahun_ajaran" value="2024/2025" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
    <div class="rounded-lg bg-[#F8FAFC] dark:bg-white/5 border border-[#E2E8F0] dark:border-[#334155] px-3 py-2 text-xs text-[#475569] dark:text-[#94A3B8]">Wali Kelas otomatis dari Master User → pilih Kelas Diampu di sana.</div>
    <div class="flex gap-2"><button class="flex-1 h-9 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan</button><button type="button" onclick="document.getElementById('modalKelas').classList.add('hidden')" class="flex-1 h-9 rounded-input border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm">Batal</button></div>
  </form>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
