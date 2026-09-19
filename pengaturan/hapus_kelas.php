<?php $active='pengaturan_hapus_kelas'; $title='Hapus Master Kelas';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_role(['Admin']);
try{ $cKelas=(int)$pdo->query("SELECT COUNT(*) FROM kelas WHERE deleted_at IS NULL")->fetchColumn(); }catch(Throwable $e){ $cKelas=0; }
require __DIR__.'/../includes/header.php';
?>
<div class="max-w-xl space-y-4">
  <div class="flex items-center gap-2 text-xs text-[#475569] dark:text-[#94A3B8]"><a href="/kesiswaanv2/pengaturan.php" class="hover:underline">Pengaturan</a> <span>›</span> <span class="font-medium text-[#0F172A] dark:text-white">Hapus Master Kelas</span></div>
  <div class="bg-white dark:bg-[#1E293B] border border-red-200 dark:border-red-900 rounded-card p-5 sm:p-6 space-y-4">
    <div class="flex items-center gap-2"><i data-lucide="alert-triangle" class="w-5 h-5 text-[#EF4444]"></i><h3 class="font-semibold text-[#DC2626]">Zona Berbahaya — Hapus Master Kelas</h3></div>
    <p class="text-xs text-[#475569] dark:text-[#94A3B8]">Soft-delete <code>kelas</code> (<b><?=$cKelas?> aktif</b>). Hanya kelas <b>kosong (jml siswa = 0)</b> yang terhapus — kelas berisi ditolak (<code>skipped</code>). Tidak menghapus siswa/user/pelanggaran. Ketik <code>HAPUS</code> + konfirmasi.</p>
    <div class="rounded-lg bg-[#FFF1F2] dark:bg-red-950/20 border border-[#FECACA] dark:border-red-900 p-3 text-xs"><b>Dampak:</b> Kelas hilang dari Master & dropdown siswa. Siswa tidak terhapus — hanya <code>kelas_id</code> jadi null-safe.</div>
    <div class="flex flex-wrap gap-2">
      <input id="confirmKelas" placeholder="Ketik HAPUS" class="h-9 px-3 rounded-input border border-[#CBD5E1] bg-white dark:bg-[#0F172A] text-sm w-32">
      <button id="btnClearKelas" onclick="doClearKelas()" class="h-9 px-4 rounded-input bg-[#EF4444] hover:bg-[#DC2626] text-white text-sm font-semibold inline-flex items-center gap-2"><i data-lucide="trash-2" class="w-4 h-4"></i> Hapus Master Kelas</button>
      <span id="okKelas" class="hidden text-xs text-emerald-600 self-center"></span>
    </div>
    <div id="errKelas" class="hidden rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-3 py-2 text-xs"></div>
  </div>
</div>
<script>
const CSRF_HAPUS_KELAS = <?=json_encode($_SESSION['csrf_token']??'')?>;
async function doClearKelas(){
  const inp=document.getElementById('confirmKelas'), err=document.getElementById('errKelas'), ok=document.getElementById('okKelas'), btn=document.getElementById('btnClearKelas');
  err.classList.add('hidden'); ok.classList.add('hidden');
  if(!inp||inp.value.trim()!=='HAPUS'){ err.textContent='Ketik HAPUS'; err.classList.remove('hidden'); return; }
  if(!confirm('Hapus kelas kosong? ('+<?=$cKelas?>+' kelas)')) return;
  btn.disabled=true; btn.textContent='Menghapus…';
  try{
    const fd=new FormData(); fd.append('csrf_token',CSRF_HAPUS_KELAS); fd.append('confirm','HAPUS');
    const r=await fetch('/kesiswaanv2/pengaturan/clear_kelas.php',{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json(); if(!r.ok||!j.success) throw new Error(j.error||r.statusText);
    ok.textContent='Terhapus: '+j.data.kelas_cleared+' kelas, ditolak '+j.data.kelas_skipped+' (berisi)'; ok.classList.remove('hidden'); toast('Kelas dihapus',''); setTimeout(()=>location.href='/kesiswaanv2/kelas/index.php',900);
  }catch(e){ err.textContent=e.message; err.classList.remove('hidden'); toast(e.message,'error'); }
  btn.disabled=false; btn.innerHTML='<i data-lucide="trash-2" class="w-4 h-4"></i> Hapus Kelas Kosong'; lucide.createIcons();
}
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
