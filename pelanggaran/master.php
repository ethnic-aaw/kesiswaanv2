<?php
session_start();
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
require __DIR__.'/../includes/auth.php';
$active='pelanggaran'; $title='Jenis Pelanggaran';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_pelanggaran_master');
function _verify_csrf_or_die(){ $tok=$_POST['csrf_token']??$_SERVER['HTTP_X_CSRF_TOKEN']??''; if(!csrf_verify($tok)){ http_response_code(403); exit('CSRF token tidak valid'); } }
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['aksi'])) {
  require_can('mutate_pelanggaran_master');
  _verify_csrf_or_die();
  $aksi=$_POST['aksi'];
  try{
    if($aksi==='tambah'){
      $kode=trim($_POST['kode']??''); $nama=trim($_POST['nama']??''); $kat2=trim($_POST['kategori']??'Lainnya');
      $bobot=(int)($_POST['bobot_poin']??0); $desk=trim($_POST['deskripsi']??''); $kons=trim($_POST['konsekuensi']??'');
      if($kode===''||$nama===''||$bobot<1||$bobot>100) throw new Exception('Kode/nama/bobot 1-100 wajib');
      $pdo->prepare("INSERT INTO jenis_pelanggaran(kode,nama,kategori,bobot_poin,deskripsi,konsekuensi) VALUES(?,?,?,?,?,?)")
          ->execute([$kode,$nama,$kat2,$bobot,$desk?:null,$kons?:null]);
      header("Location: master.php?msg=".urlencode("Ditambahkan $kode")); exit;
    } elseif($aksi==='edit'){
      $id=(int)($_POST['id']??0); $kode=trim($_POST['kode']??''); $nama=trim($_POST['nama']??'');
      $kat2=trim($_POST['kategori']??''); $bobot=(int)($_POST['bobot_poin']??0); $desk=trim($_POST['deskripsi']??''); $kons=trim($_POST['konsekuensi']??'');
      $pdo->prepare("UPDATE jenis_pelanggaran SET kode=?,nama=?,kategori=?,bobot_poin=?,deskripsi=?,konsekuensi=? WHERE id=?")
          ->execute([$kode,$nama,$kat2,$bobot,$desk?:null,$kons?:null,$id]);
      header("Location: master.php?msg=".urlencode("Diperbarui $kode")); exit;
    } elseif($aksi==='hapus'){
      $id=(int)($_POST['id']??0);
      $pdo->prepare("UPDATE jenis_pelanggaran SET deleted_at=NOW() WHERE id=?")->execute([$id]);
      header("Location: master.php?msg=".urlencode("Dihapus (soft-delete)")); exit;
    } elseif($aksi==='seed'){
      $f=__DIR__.'/../sql/seed_pelanggaran_leuwimunding.sql';
      if(!file_exists($f)) throw new Exception('File seed tidak ditemukan: '.$f);
      $ok=0; $fail=0; $errs=[];
      foreach(file($f) as $line){
        $line=trim($line); if($line===''||str_starts_with($line,'--')) continue;
        try{ $pdo->exec($line); $ok++; }catch(Throwable $e){ $fail++; $errs[]=$e->getMessage(); }
      }
      $cnt=(int)$pdo->query("SELECT COUNT(*) FROM jenis_pelanggaran WHERE deleted_at IS NULL")->fetchColumn();
      $msg="Seed: $ok ok, $fail gagal — total DB sekarang $cnt baris (target 56)";
      if($errs) $msg.=" · ".implode(' | ', array_slice(array_unique($errs),0,2));
      header("Location: master.php?msg=".urlencode($msg).($fail?"&err=".urlencode(implode(' | ',$errs)):"")); exit;
    }
  }catch(Throwable $e){ error_log('master pelanggaran: '.$e->getMessage()); header("Location: master.php?err=".urlencode('Gagal proses')); exit; }
}

$kat = $_GET['kategori'] ?? '';
$q = trim($_GET['q'] ?? '');
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

require __DIR__.'/../includes/header.php';

$where="WHERE deleted_at IS NULL"; $args=[];
if($kat!==''){ $where.=" AND kategori=?"; $args[]=$kat; }
if($q!==''){ $where.=" AND (kode LIKE ? OR nama LIKE ? OR deskripsi LIKE ?)"; $args[]="%$q%"; $args[]="%$q%"; $args[]="%$q%"; }
$stmt=$pdo->prepare("SELECT id,kode,nama,kategori,bobot_poin,deskripsi,konsekuensi FROM jenis_pelanggaran $where ORDER BY kode");
$stmt->execute($args); $rows=$stmt->fetchAll();
$total=count($rows);
$cntAll=(int)$pdo->query("SELECT COUNT(*) FROM jenis_pelanggaran WHERE deleted_at IS NULL")->fetchColumn();
function catColor($c){ return match($c){'Kedisiplinan'=>'bg-[#EFF6FF] text-[#2563EB]','Tata Krama'=>'bg-violet-50 text-violet-700','Kekerasan'=>'bg-[#FEE2E2] text-[#DC2626]','Narkoba'=>'bg-red-100 text-red-800','Lainnya'=>'bg-slate-100 text-slate-700', default=>'bg-slate-100 text-slate-600'}; }
?>
<?php if($msg): ?><div class="mb-3 rounded-lg border-l-4 border-emerald-500 bg-[#F0FDF4] px-4 py-2.5 text-sm"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="mb-3 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($err)?></div><?php endif; ?>

<div class="flex flex-wrap gap-2 items-center justify-between">
  <div class="flex flex-wrap gap-2 items-center">
    <form method="GET" class="flex gap-2 items-center">
      <div class="relative">
        <i data-lucide="search" class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-[#94A3B8]"></i>
        <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari kode / nama…" class="h-9 pl-8 pr-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm w-[200px]">
      </div>
      <select name="kategori" onchange="this.form.submit()" class="h-9 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm px-3">
        <option value="">Semua Kategori</option>
        <?php foreach(['Kedisiplinan','Tata Krama','Kekerasan','Narkoba','Lainnya'] as $c): ?>
        <option value="<?=$c?>" <?= $kat===$c?'selected':'' ?>><?=$c?></option>
        <?php endforeach; ?>
      </select>
      <button class="h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm">Filter</button>
    </form>
    <span class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=$total?> data · total DB: <?=$cntAll?> <?php if($cntAll<56): ?><span class="text-amber-600">(belum lengkap 56)</span><?php endif; ?></span>
  </div>
  <div class="flex flex-wrap gap-2">
    <?php if($cntAll<56): ?>
    <form method="POST" class="inline"><input type="hidden" name="aksi" value="seed"><button class="h-9 px-4 rounded-input bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold inline-flex items-center gap-1.5"><i data-lucide="database" class="w-4 h-4"></i> Load Seed Leuwimunding (56)</button></form>
    <?php endif; ?>
    <button onclick="document.getElementById('mPelanggaran').classList.remove('hidden')" class="h-9 px-4 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold inline-flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Tambah Pelanggaran</button>
  </div>
</div>
<div class="mt-4 text-xs text-[#94A3B8]">Import massal pindah ke <a href="/kesiswaanv2/pengaturan/pelanggaran.php" class="text-[#2563EB] underline">Pengaturan → Import Data Pelanggaran</a> (template XLS + preview, seperti Dapodik).</div>

<div class="hidden md:block bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-4">
<table class="w-full text-sm">
<thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="px-3 py-3 text-left">Kode</th><th class="px-3 py-3 text-left">Nama</th><th class="px-3 py-3 text-left">Kategori</th><th class="px-3 py-3 text-left">Bobot</th><th class="px-3 py-3 text-left">Konsekuensi</th><th class="px-3 py-3 text-left">Aksi</th></tr></thead>
<tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
<?php foreach($rows as $r): ?>
<tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
  <td class="px-3 py-2.5 font-mono text-xs"><?=htmlspecialchars($r['kode'])?></td>
  <td class="px-3 py-2.5 font-medium"><?=htmlspecialchars($r['nama'])?><div class="text-xs text-[#94A3B8] truncate max-w-[260px]"><?=htmlspecialchars($r['deskripsi']??'')?></div></td>
  <td class="px-3 py-2.5"><span class="px-2 py-1 rounded-badge text-xs font-semibold <?=catColor($r['kategori'])?>"><?=htmlspecialchars($r['kategori'])?></span></td>
  <td class="px-3 py-2.5"><span class="w-7 h-7 rounded-full bg-[#0F172A] text-white inline-flex items-center justify-center text-xs font-bold"><?=$r['bobot_poin']?></span></td>
  <td class="px-3 py-2.5 text-xs text-[#475569] dark:text-[#94A3B8] max-w-[180px] truncate"><?=htmlspecialchars($r['konsekuensi']??'-')?></td>
  <td class="px-3 py-2.5"><div class="flex gap-1">
    <button type="button" onclick='openEdit(<?=json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)?>)' class="px-2.5 py-1.5 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium inline-flex items-center gap-1"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</button>
    <form method="POST" onsubmit="return confirm('Hapus <?=htmlspecialchars($r['nama'],ENT_QUOTES)?>? (soft-delete)')" class="inline"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="px-2.5 py-1.5 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus</button></form>
  </div></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" class="px-4 py-10 text-center text-sm text-[#94A3B8]">Belum ada data — klik <b>Load Seed Leuwimunding</b> atau <b>Import CSV</b>.</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<div class="md:hidden space-y-3 mt-4">
<?php foreach($rows as $r): ?>
<div class="bg-white dark:bg-[#1E293B] border rounded-card p-4">
  <div class="flex justify-between"><span class="font-mono text-xs"><?=htmlspecialchars($r['kode'])?></span><span class="px-2 py-1 rounded-badge text-xs font-semibold <?=catColor($r['kategori'])?>"><?=htmlspecialchars($r['kategori'])?></span></div>
  <div class="font-semibold text-sm mt-1"><?=htmlspecialchars($r['nama'])?></div>
  <div class="flex items-center gap-2 mt-2"><span class="w-7 h-7 rounded-full bg-[#0F172A] text-white flex items-center justify-center text-xs font-bold"><?=$r['bobot_poin']?></span><span class="text-xs text-[#475569]"><?=htmlspecialchars($r['konsekuensi']??'-')?></span></div>
  <div class="flex gap-2 mt-3"><button type="button" onclick='openEdit(<?=json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)?>)' class="flex-1 h-8 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium">Edit</button>
  <form method="POST" onsubmit="return confirm('Hapus?')" class="flex-1"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="w-full h-8 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium">Hapus</button></form></div>
</div>
<?php endforeach; ?>
</div>

<div id="mPelanggaran" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('mPelanggaran').classList.add('hidden')"></div>
  <form method="POST" class="relative bg-white dark:bg-[#1E293B] rounded-card border p-5 w-full max-w-md space-y-3">
    <?=csrf_field()?><input type="hidden" name="aksi" value="tambah">
    <h3 class="font-semibold">Tambah Jenis Pelanggaran</h3>
    <label class="block"><span class="text-[13px] font-medium">Kode <span class="text-red-500">*</span></span><input name="kode" required placeholder="PLG-057" class="mt-1 w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="block"><span class="text-[13px] font-medium">Nama <span class="text-red-500">*</span></span><input name="nama" required maxlength="150" placeholder="Nama pelanggaran" class="mt-1 w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="block"><span class="text-[13px] font-medium">Kategori</span><select name="kategori" class="mt-1 w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"><option>Kedisiplinan</option><option>Tata Krama</option><option>Kekerasan</option><option>Narkoba</option><option>Lainnya</option></select></label>
    <label class="block"><span class="text-[13px] font-medium">Bobot Poin (1–100) <span class="text-red-500">*</span></span><input name="bobot_poin" type="number" min="1" max="100" required placeholder="10" class="mt-1 w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="block"><span class="text-[13px] font-medium">Deskripsi</span><textarea name="deskripsi" rows="2" class="mt-1 w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
    <label class="block"><span class="text-[13px] font-medium">Konsekuensi / Sanksi</span><textarea name="konsekuensi" rows="2" class="mt-1 w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
    <div class="flex gap-2"><button class="flex-1 h-10 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan</button><button type="button" onclick="document.getElementById('mPelanggaran').classList.add('hidden')" class="flex-1 h-10 rounded-input border bg-white dark:bg-[#0F172A] text-sm">Batal</button></div>
  </form>
</div>

<div id="mEdit" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('mEdit').classList.add('hidden')"></div>
  <form method="POST" id="fEdit" class="relative bg-white dark:bg-[#1E293B] rounded-card border p-5 w-full max-w-md space-y-3">
    <?=csrf_field()?><input type="hidden" name="aksi" value="edit"><input type="hidden" name="id" id="e_id">
    <h3 class="font-semibold">Edit Pelanggaran</h3>
    <label class="block"><span class="text-[13px] font-medium">Kode</span><input name="kode" id="e_kode" required class="mt-1 w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="block"><span class="text-[13px] font-medium">Nama</span><input name="nama" id="e_nama" required class="mt-1 w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="block"><span class="text-[13px] font-medium">Kategori</span><select name="kategori" id="e_kategori" class="mt-1 w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"><option>Kedisiplinan</option><option>Tata Krama</option><option>Kekerasan</option><option>Narkoba</option><option>Lainnya</option></select></label>
    <label class="block"><span class="text-[13px] font-medium">Bobot</span><input name="bobot_poin" id="e_bobot" type="number" min="1" max="100" required class="mt-1 w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="block"><span class="text-[13px] font-medium">Deskripsi</span><textarea name="deskripsi" id="e_deskripsi" rows="2" class="mt-1 w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
    <label class="block"><span class="text-[13px] font-medium">Konsekuensi</span><textarea name="konsekuensi" id="e_konsekuensi" rows="2" class="mt-1 w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
    <div class="flex gap-2"><button class="flex-1 h-10 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan Perubahan</button><button type="button" onclick="document.getElementById('mEdit').classList.add('hidden')" class="flex-1 h-10 rounded-input border bg-white dark:bg-[#0F172A] text-sm">Batal</button></div>
  </form>
</div>



<script>
function openEdit(r){
  document.getElementById('e_id').value=r.id;
  document.getElementById('e_kode').value=r.kode;
  document.getElementById('e_nama').value=r.nama;
  document.getElementById('e_kategori').value=r.kategori;
  document.getElementById('e_bobot').value=r.bobot_poin;
  document.getElementById('e_deskripsi').value=r.deskripsi||'';
  document.getElementById('e_konsekuensi').value=r.konsekuensi||'';
  document.getElementById('mEdit').classList.remove('hidden');
}
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
