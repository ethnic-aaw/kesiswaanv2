<?php $active='user'; $title='Master User';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_user');
require __DIR__.'/../includes/header.php';
$tab=$_GET['role']??'semua';
$users=[
  ['id'=>1,'nama'=>'Admin TU','username'=>'admin','role'=>'Admin','kelas'=>'—','status'=>'Aktif'],
  ['id'=>2,'nama'=>'Ibu Sari','username'=>'sari@belajar.id','role'=>'Guru BK','kelas'=>'—','status'=>'Aktif'],
  ['id'=>3,'nama'=>'Pak Budi','username'=>'budi@belajar.id','role'=>'Wali Kelas','kelas'=>'XI IPA 1','status'=>'Aktif'],
  ['id'=>4,'nama'=>'Ahmad Fauzi','username'=>'ahmad.siswa','role'=>'Siswa','kelas'=>'XI IPA 1','status'=>'Aktif'],
];
function roleBadge($r){ return match($r){'Admin'=>'bg-[#EFF6FF] text-[#2563EB]','Guru BK'=>'bg-emerald-50 text-emerald-700','Wali Kelas'=>'bg-amber-50 text-amber-700','Siswa'=>'bg-slate-100 text-slate-700', default=>'bg-slate-100 text-slate-600'}; }
?>
<div class="flex flex-wrap gap-2 items-center justify-between">
  <div class="flex gap-1.5 p-1 rounded-full bg-[#F1F5F9] dark:bg-[#0F172A] border border-[#E2E8F0] dark:border-[#334155]">
    <?php foreach(['semua'=>'Semua','Admin'=>'Admin','Guru BK'=>'Guru BK','Wali Kelas'=>'Wali Kelas','Siswa'=>'Siswa'] as $k=>$l): $on=$tab===$k||($tab==='semua'&&$k==='semua'); ?>
      <a href="?role=<?=urlencode($k)?>" class="px-3 py-1.5 rounded-full text-xs font-semibold <?= $on?'bg-[#0F172A] text-white dark:bg-white dark:text-[#0F172A]':'text-[#475569] dark:text-[#94A3B8] hover:text-[#0F172A]' ?>"><?=$l?></a>
    <?php endforeach; ?>
  </div>
  <a href="/kesiswaanv2/user/tambah.php" class="inline-flex items-center gap-2 h-9 px-4 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold"><i data-lucide="plus" class="w-4 h-4"></i> Tambah User</a>
</div>

<div class="hidden md:block bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-4">
  <table class="w-full text-sm">
    <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="px-4 py-3 text-left">Nama</th><th class="px-4 py-3 text-left">Username</th><th class="px-4 py-3 text-left">Role</th><th class="px-4 py-3 text-left">Kelas Diampu</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Aksi</th></tr></thead>
    <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
      <?php foreach($users as $u): if($tab!=='semua'&&$u['role']!==$tab) continue; ?>
      <tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
        <td class="px-4 py-3 font-medium"><?=$u['nama']?></td>
        <td class="px-4 py-3 text-xs font-mono"><?=$u['username']?></td>
        <td class="px-4 py-3"><span class="px-2 py-1 rounded-badge text-xs font-semibold <?=roleBadge($u['role'])?>"><?=$u['role']?></span></td>
        <td class="px-4 py-3 text-xs"><?=$u['kelas']?></td>
        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold <?= $u['status']==='Aktif'?'bg-emerald-50 text-emerald-700':'bg-[#FEE2E2] text-[#DC2626]' ?>"><?=$u['status']?></span></td>
        <td class="px-4 py-3">
          <div class="flex gap-1.5">
            <a href="/kesiswaanv2/user/edit.php?id=<?=$u['id']?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium hover:bg-[#EFF6FF]"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</a>
            <button onclick="toast('Reset password — POST /api/users/<?=$u['id']?>/reset-password')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-input border border-amber-200 text-amber-700 text-xs font-medium hover:bg-amber-50"><i data-lucide="key-round" class="w-3.5 h-3.5"></i> Reset</button>
            <button onclick="confirmModal('Hapus <?=$u['nama']?>? (soft-delete)',()=>toast('Dihapus — DELETE /api/users/<?=$u['id']?>'))" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium hover:bg-[#FFF1F2]"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="md:hidden space-y-3 mt-4">
  <?php foreach($users as $u): if($tab!=='semua'&&$u['role']!==$tab) continue; ?>
  <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
    <div class="flex justify-between items-start">
      <div><div class="font-semibold text-sm"><?=$u['nama']?></div><div class="text-xs font-mono text-[#475569] dark:text-[#94A3B8]"><?=$u['username']?></div></div>
      <span class="px-2 py-1 rounded-badge text-xs font-semibold <?=roleBadge($u['role'])?>"><?=$u['role']?></span>
    </div>
    <div class="text-xs text-[#475569] dark:text-[#94A3B8] mt-2">Kelas: <?=$u['kelas']?> · <span class="font-semibold"><?=$u['status']?></span></div>
    <div class="flex gap-2 mt-3">
      <a href="/kesiswaanv2/user/edit.php?id=<?=$u['id']?>" class="flex-1 h-8 inline-flex justify-center items-center gap-1 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</a>
      <button onclick="toast('Reset password')" class="h-8 px-3 rounded-input border border-amber-200 text-amber-700 text-xs"><i data-lucide="key-round" class="w-3.5 h-3.5"></i></button>
      <button onclick="confirmModal('Hapus?',()=>toast('Dihapus'))" class="h-8 px-3 rounded-input border border-red-200 text-[#DC2626] text-xs"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
