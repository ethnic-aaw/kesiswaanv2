<?php $active='pengaturan'; $title='Pengaturan — Threshold';
require __DIR__.'/config/db.php';
require __DIR__.'/includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_pengaturan');
$threshold=76; $msg=''; $err='';
if(isset($pdo) && $pdo){
  try{ $v=$pdo->query("SELECT value FROM settings WHERE key_name='threshold_poin_kritis' LIMIT 1")->fetchColumn(); if($v!==false && $v!=='') $threshold=(int)$v; }catch(Throwable $e){}
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['threshold'])){
  require_can('mutate_pengaturan');
  if(!csrf_verify($_POST['csrf_token']??'')){ $err='CSRF token tidak valid'; }
  else{
    $val=(int)($_POST['threshold']??0);
    if($val<1||$val>100) $err='Threshold 1–100';
    elseif(!isset($pdo)||!$pdo) $err='DB tidak konek';
    else{ try{ $pdo->prepare("INSERT INTO settings(key_name,value) VALUES('threshold_poin_kritis',?) ON DUPLICATE KEY UPDATE value=VALUES(value)")->execute([(string)$val]); $threshold=$val; $msg="Threshold disimpan: $val"; }catch(Throwable $e){ $err=$e->getMessage(); } }
  }
}
require __DIR__.'/includes/header.php';
?>
<?php if($msg): ?><div class="mb-4 rounded-lg border-l-4 border-emerald-500 bg-[#F0FDF4] px-4 py-2.5 text-sm"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="mb-4 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($err)?></div><?php endif; ?>
<div class="max-w-xl bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5 sm:p-6 space-y-5">
  <div>
    <h3 class="font-semibold">Threshold Poin Kritis</h3>
    <p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1">Siswa dengan akumulasi poin &gt; threshold masuk kartu <b>Siswa Bermasalah</b> & badge <b>Kritis</b>. Disimpan di <code class="px-1 py-0.5 rounded bg-[#F1F5F9] dark:bg-white/10">settings.threshold_poin_kritis</code>.</p>
  </div>
  <form method="POST" class="space-y-4">
    <?=csrf_field()?>
    <label class="block">
      <span class="text-[13px] font-medium">Nilai Threshold (1–100) <span class="text-red-500">*</span></span>
      <input name="threshold" id="threshold" type="number" min="1" max="100" required value="<?=$threshold?>" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm">
      <span class="text-[11px] text-[#475569] dark:text-[#94A3B8]">Default 76 selaras badge Kritis (&gt;75).</span>
    </label>
    <div class="rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-[#F8FAFC] dark:bg-white/5 p-3">
      <div class="text-xs font-semibold">Preview Badge</div>
      <div class="flex flex-wrap gap-2 mt-2 text-xs">
        <span class="px-2 py-1 rounded-badge bg-[#DCFCE7] text-[#16A34A]">Rendah 0–25</span>
        <span class="px-2 py-1 rounded-badge bg-[#FEF9C3] text-[#CA8A04]">Sedang 26–50</span>
        <span class="px-2 py-1 rounded-badge bg-[#FED7AA] text-[#EA580C]">Tinggi 51–75</span>
        <span class="px-2 py-1 rounded-badge bg-[#FEE2E2] text-[#DC2626]">Kritis &gt;<span id="prevVal"><?=$threshold?></span></span>
      </div>
    </div>
    <div class="flex gap-2">
      <button class="h-10 px-5 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan</button>
      <button type="button" onclick="document.getElementById('threshold').value=76;document.getElementById('prevVal').textContent=76" class="h-10 px-5 rounded-input border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm">Reset ke 76</button>
    </div>
  </form>
</div>
<script>document.getElementById('threshold')?.addEventListener('input',e=>document.getElementById('prevVal').textContent=e.target.value||'—');</script>
<?php require __DIR__.'/includes/footer.php'; ?>
