<?php
$active='user'; $title='Master User';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_user');

$tab=$_GET['role']??'semua';
$users=[];
try{
  $users=$pdo->query("SELECT u.id,u.nama,u.username,u.role,u.status,u.last_seen,
    (SELECT GROUP_CONCAT(k.nama_kelas SEPARATOR ', ') FROM kelas k WHERE k.wali_kelas_id=u.id AND k.deleted_at IS NULL) AS kelas
    FROM users u WHERE u.deleted_at IS NULL ORDER BY FIELD(u.role,'Admin','Guru BK','Wali Kelas','Siswa'),u.nama")->fetchAll();
}catch(Throwable $e){ $users=[]; }
$h=fn($v)=>htmlspecialchars((string)$v);
$isOnline=fn($u)=>!empty($u['last_seen']) && (time()-(int)strtotime($u['last_seen']))<300;
function onlineBadge(bool $on){ return $on
  ? '<span class="inline-flex items-center gap-1.5 text-xs"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span><span class="font-semibold text-emerald-600">Online</span></span>'
  : '<span class="inline-flex items-center gap-1.5 text-xs"><span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600"></span><span class="text-[#94A3B8]">Offline</span></span>'; }
function roleBadge($r){ return match($r){'Admin'=>'bg-[#EFF6FF] text-[#2563EB]','Guru BK'=>'bg-emerald-50 text-emerald-700','Wali Kelas'=>'bg-amber-50 text-amber-700','Siswa'=>'bg-slate-100 text-slate-700', default=>'bg-slate-100 text-slate-600'}; }
require __DIR__.'/../includes/header.php';
?>
<?php if(isset($_GET['msg'])): ?><div class="mb-3 rounded-lg border-l-4 border-emerald-500 bg-[#F0FDF4] px-4 py-2.5 text-sm"><?=$h($_GET['msg'])?></div><?php endif; ?>
<?php if(isset($_GET['err'])): ?><div class="mb-3 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=$h($_GET['err'])?></div><?php endif; ?>
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
    <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="px-4 py-3 text-left">Nama</th><th class="px-4 py-3 text-left">Username</th><th class="px-4 py-3 text-left">Role</th><th class="px-4 py-3 text-left">Kelas Diampu</th><th class="px-4 py-3 text-left">Aktivitas</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Aksi</th></tr></thead>
    <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
      <?php $shown=0; foreach($users as $u): if($tab!=='semua'&&$u['role']!==$tab) continue; $shown++; ?>
      <tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
        <td class="px-4 py-3 font-medium"><?=$h($u['nama'])?></td>
        <td class="px-4 py-3 text-xs font-mono"><?=$h($u['username'])?></td>
        <td class="px-4 py-3"><span class="px-2 py-1 rounded-badge text-xs font-semibold <?=roleBadge($u['role'])?>"><?=$h($u['role'])?></span></td>
        <td class="px-4 py-3 text-xs"><?= $u['kelas']!==null&&$u['kelas']!=='' ? $h($u['kelas']) : '—' ?></td>
        <td class="px-4 py-3"><?=onlineBadge($isOnline($u))?></td>
        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold <?= $u['status']==='Aktif'?'bg-emerald-50 text-emerald-700':'bg-[#FEE2E2] text-[#DC2626]' ?>"><?=$h($u['status'])?></span></td>
        <td class="px-4 py-3">
          <div class="flex gap-1.5">
            <a href="/kesiswaanv2/user/edit.php?id=<?=$u['id']?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium hover:bg-[#EFF6FF]"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</a>
            <button onclick="resetPw(<?=$u['id']?>,'<?=$h($u['username'])?>')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-input border border-amber-200 text-amber-700 text-xs font-medium hover:bg-amber-50"><i data-lucide="key-round" class="w-3.5 h-3.5"></i> Reset</button>
            <button onclick="delUser(<?=$u['id']?>,'<?=$h($u['username'])?>')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium hover:bg-[#FFF1F2]"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus</button>
          </div>
        </td>
      </tr>
      <?php endforeach; if(!$shown): ?><tr><td colspan="7" class="px-4 py-6 text-center text-xs text-[#94A3B8]">Belum ada user.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="md:hidden space-y-3 mt-4">
  <?php foreach($users as $u): if($tab!=='semua'&&$u['role']!==$tab) continue; ?>
  <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
    <div class="flex justify-between items-start">
      <div><div class="font-semibold text-sm"><?=$h($u['nama'])?></div><div class="text-xs font-mono text-[#475569] dark:text-[#94A3B8]"><?=$h($u['username'])?></div></div>
      <span class="px-2 py-1 rounded-badge text-xs font-semibold <?=roleBadge($u['role'])?>"><?=$h($u['role'])?></span>
    </div>
    <div class="text-xs text-[#475569] dark:text-[#94A3B8] mt-2 flex items-center justify-between"><span>Kelas: <?= $u['kelas']!==null&&$u['kelas']!=='' ? $h($u['kelas']) : '—' ?> · <span class="font-semibold"><?=$h($u['status'])?></span></span><?=onlineBadge($isOnline($u))?></div>
    <div class="flex gap-2 mt-3">
      <a href="/kesiswaanv2/user/edit.php?id=<?=$u['id']?>" class="flex-1 h-8 inline-flex justify-center items-center gap-1 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</a>
      <button onclick="resetPw(<?=$u['id']?>,'<?=$h($u['username'])?>')" class="h-8 px-3 rounded-input border border-amber-200 text-amber-700 text-xs"><i data-lucide="key-round" class="w-3.5 h-3.5"></i></button>
      <button onclick="delUser(<?=$u['id']?>,'<?=$h($u['username'])?>')" class="h-8 px-3 rounded-input border border-red-200 text-[#DC2626] text-xs"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<script>
const CSRF=<?=json_encode($_SESSION['csrf_token']??'')?>;
async function postUser(url,data){
  const fd=new FormData(); fd.append('csrf_token',CSRF); for(const k in data) fd.append(k,data[k]);
  const r=await fetch(url,{method:'POST',body:fd,credentials:'same-origin',headers:{'Accept':'application/json'}});
  let j={}; try{ j=await r.json(); }catch(e){}
  if(!r.ok||!j.success) throw new Error(j.error||r.statusText);
  return j;
}
async function resetPw(id,username){
  const pw=prompt('Password baru untuk "'+username+'" (min 8 karakter):');
  if(pw===null) return;
  try{ await postUser('/kesiswaanv2/user/reset.php',{id,pw}); toast('Password direset',''); }
  catch(e){ toast(e.message,'error'); }
}
function delUser(id,username){
  if(!confirm('Hapus user "'+username+'"? (soft-delete, riwayat tetap)')) return;
  postUser('/kesiswaanv2/user/hapus.php',{id})
    .then(()=>{ toast('User dihapus',''); setTimeout(()=>location.reload(),700); })
    .catch(e=>toast(e.message,'error'));
}
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
