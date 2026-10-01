<?php
$active='bk'; $title='Data Bimbingan Konseling';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_bk');
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_can('mutate_bk');
  if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF'); }
  $aksi=$_POST['aksi']??'';
  try{
    if($aksi==='tambah'){
      $sid=(int)($_POST['siswa_id']??0); $tgl=trim($_POST['tanggal']??date('Y-m-d')); $prob=trim($_POST['permasalahan']??''); $tind=trim($_POST['tindakan']??'');
      if(!$sid||$prob==='') throw new Exception('Siswa & permasalahan wajib');
      if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tgl)) $tgl=date('Y-m-d');
      $kons=(int)($_SESSION['user']['id']??0);
      $pdo->prepare("INSERT INTO bimbingan_konseling(siswa_id,tanggal,permasalahan,tindakan,konselor_id) VALUES(?,?,?,?,?)")->execute([$sid,$tgl,$prob,$tind?:null,$kons?:null]);
      header('Location: index.php?msg='.urlencode('Data BK ditambahkan')); exit;
    } elseif($aksi==='edit'){
      $id=(int)($_POST['id']??0); $tgl=trim($_POST['tanggal']??''); $prob=trim($_POST['permasalahan']??''); $tind=trim($_POST['tindakan']??'');
      if(!$id||$prob==='') throw new Exception('Data tidak valid');
      if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tgl)) $tgl=date('Y-m-d');
      $pdo->prepare("UPDATE bimbingan_konseling SET tanggal=?,permasalahan=?,tindakan=? WHERE id=?")->execute([$tgl,$prob,$tind?:null,$id]);
      header('Location: index.php?msg='.urlencode('Data BK diperbarui')); exit;
    } elseif($aksi==='hapus'){
      $id=(int)($_POST['id']??0); $pdo->prepare("DELETE FROM bimbingan_konseling WHERE id=?")->execute([$id]);
      header('Location: index.php?msg='.urlencode('Data BK dihapus')); exit;
    }
  }catch(Throwable $e){ error_log('bk: '.$e->getMessage()); header('Location: index.php?err='.urlencode('Gagal proses BK')); exit; }
}
require __DIR__.'/../includes/header.php';
$filterSiswa=(int)($_GET['siswa_id']??0); $filterNama=''; $filterNipd=''; if($filterSiswa>0){ try{ $st=$pdo->prepare("SELECT nama,nipd FROM siswa WHERE id=? LIMIT 1"); $st->execute([$filterSiswa]); if($r=$st->fetch()){ $filterNama=(string)$r['nama']; $filterNipd=(string)$r['nipd']; } }catch(Throwable $e){} }
$q=trim($_GET['q']??''); $kelas=trim($_GET['kelas']??''); $page=max(1,(int)($_GET['page']??1)); $size=(int)($_GET['size']??25); if(!in_array($size,[10,25,50,100],true)) $size=25;
$kelasOpts=[]; try{ foreach($pdo->query("SELECT nama_kelas FROM kelas WHERE deleted_at IS NULL ORDER BY nama_kelas") as $r) $kelasOpts[]=$r['nama_kelas']; }catch(Throwable $e){}
$where="WHERE 1=1"; $args=[];
if($filterSiswa>0){ $where.=" AND bk.siswa_id=?"; $args[]=$filterSiswa; }
if($q!==''){ $where.=" AND (s.nama LIKE ? OR s.nipd LIKE ? OR bk.permasalahan LIKE ?)"; $a="%$q%"; $args[]=$a;$args[]=$a;$args[]=$a; }
if($kelas!==''){ $where.=" AND k.nama_kelas=?"; $args[]=$kelas; }
$total=0; try{ $st=$pdo->prepare("SELECT COUNT(*) FROM bimbingan_konseling bk JOIN siswa s ON s.id=bk.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id $where"); $st->execute($args); $total=(int)$st->fetchColumn(); }catch(Throwable $e){}
$totalPages=max(1,(int)ceil($total/$size)); if($page>$totalPages) $page=$totalPages; $off=($page-1)*$size;
$rows=[]; try{
  $st=$pdo->prepare("SELECT bk.id,bk.siswa_id,bk.tanggal,bk.permasalahan,bk.tindakan,COALESCE(u.nama,'—') konselor, s.nama siswa_nama,s.nipd,COALESCE(k.nama_kelas,'—') kelas FROM bimbingan_konseling bk JOIN siswa s ON s.id=bk.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN users u ON u.id=bk.konselor_id $where ORDER BY bk.tanggal DESC,bk.id DESC LIMIT ? OFFSET ?");
  $st->execute(array_merge($args,[$size,$off])); $rows=$st->fetchAll();
}catch(Throwable $e){}
$qsBase=array_filter(['q'=>$q,'kelas'=>$kelas,'size'=>$size,'siswa_id'=>$filterSiswa?:null]);
?>
<?php if(isset($_GET['msg'])): ?><div class="mb-3 rounded-lg border-l-4 border-emerald-500 bg-[#F0FDF4] px-4 py-2.5 text-sm"><?=htmlspecialchars($_GET['msg'])?></div><?php endif; ?>
<?php if(isset($_GET['err'])): ?><div class="mb-3 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($_GET['err'])?></div><?php endif; ?>
<div class="flex flex-wrap gap-2 items-center justify-between">
  <form method="GET" class="flex flex-wrap gap-2 items-center">
    <div class="relative"><i data-lucide="search" class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-[#94A3B8]"></i><input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari siswa / permasalahan…" class="h-9 pl-8 pr-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm w-[220px]"></div>
    <select name="kelas" onchange="this.form.submit()" class="h-9 rounded-input border bg-white dark:bg-[#0F172A] text-sm px-2"><option value="">Semua Kelas</option><?php foreach($kelasOpts as $k): ?><option <?= $kelas===$k?'selected':'' ?>><?=htmlspecialchars($k)?></option><?php endforeach; ?></select>
    <select name="size" onchange="this.form.submit()" class="h-9 rounded-input border bg-white dark:bg-[#0F172A] text-sm px-2"><option value="25" <?= $size===25?'selected':'' ?>>25</option><option value="50" <?= $size===50?'selected':'' ?>>50</option><option value="100" <?= $size===100?'selected':'' ?>>100</option></select>
    <button class="h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm">Filter</button>
  </form>
  <button onclick="document.getElementById('mBk').classList.remove('hidden')" class="h-9 px-4 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold inline-flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Tambah BK</button>
</div>
<?php if($filterSiswa>0 && $filterNama!==''): ?><div class="mt-2 flex flex-wrap items-center gap-2 text-xs"><span class="px-2.5 py-1 rounded-full bg-[#EFF6FF] dark:bg-[#1E3A8A]/30 border border-[#BFDBFE] dark:border-[#1E40AF] text-[#1D4ED8] dark:text-[#93C5FD] font-medium">Filter: <?=htmlspecialchars($filterNama)?> — <?=htmlspecialchars($filterNipd)?></span><a href="index.php" class="h-7 px-3 rounded-input border bg-white dark:bg-[#0F172A] inline-flex items-center">Lihat semua</a><a href="/kesiswaanv2/siswa/detail.php?id=<?=$filterSiswa?>" class="h-7 px-3 rounded-input border bg-white dark:bg-[#0F172A] inline-flex items-center gap-1"><i data-lucide="external-link" class="w-3.5 h-3.5"></i> Detail siswa</a></div><?php endif; ?>
<div class="mt-2 text-xs text-[#475569] dark:text-[#94A3B8]">Total <?=number_format($total)?> data · Hal <?=$page?>/<?=$totalPages?><?php if($filterSiswa>0): ?> · terfilter<?php endif; ?></div>
<div class="hidden md:block bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-2">
<table class="w-full text-sm">
<thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="px-3 py-2.5 text-left">No</th><th class="px-3 py-2.5 text-left">Tanggal</th><th class="px-3 py-2.5 text-left">Siswa</th><th class="px-3 py-2.5 text-left">Kelas</th><th class="px-3 py-2.5 text-left">Permasalahan</th><th class="px-3 py-2.5 text-left">Tindakan / Followup</th><th class="px-3 py-2.5 text-left">Konselor</th><th class="px-3 py-2.5 text-left">Aksi</th></tr></thead>
<tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
<?php if(!$rows): ?><tr><td colspan="8" class="px-4 py-10 text-center text-sm text-[#94A3B8]">Belum ada data BK — klik Tambah BK</td></tr><?php endif; ?>
<?php foreach($rows as $i=>$r): $no=$off+$i+1; ?>
<tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
  <td class="px-3 py-2.5 text-xs font-semibold"><?=$no?></td>
  <td class="px-3 py-2.5 text-xs"><?=htmlspecialchars($r['tanggal'])?></td>
  <td class="px-3 py-2.5"><a href="/kesiswaanv2/siswa/detail.php?id=<?=$r['siswa_id']?>" class="font-medium hover:underline text-[#2563EB] dark:text-[#93C5FD]"><?=htmlspecialchars($r['siswa_nama'])?></a><div class="text-xs text-[#94A3B8]"><?=htmlspecialchars($r['nipd'])?></div></td>
  <td class="px-3 py-2.5"><?=htmlspecialchars($r['kelas'])?></td>
  <td class="px-3 py-2.5 max-w-[260px] truncate" title="<?=htmlspecialchars($r['permasalahan'])?>"><?=htmlspecialchars($r['permasalahan'])?></td>
  <td class="px-3 py-2.5 max-w-[220px] truncate" title="<?=htmlspecialchars($r['tindakan']??'')?>"><?=htmlspecialchars($r['tindakan']??'—')?></td>
  <td class="px-3 py-2.5 text-xs"><?=htmlspecialchars($r['konselor'])?></td>
  <td class="px-3 py-2.5"><div class="flex gap-1">
    <button type="button" onclick='openEditBk(<?=json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)?>)' class="px-2.5 py-1.5 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium inline-flex items-center gap-1"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</button>
    <form method="POST" onsubmit="return confirm('Hapus data BK ini?')" class="inline"><?=csrf_field()?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="px-2.5 py-1.5 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium">Hapus</button></form>
  </div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div class="px-3 py-3 flex items-center justify-between text-xs border-t">
  <span class="text-[#475569]">Menampilkan <?= $total? ($off+1).'–'.min($off+$size,$total)." dari $total" : '0' ?></span>
  <div class="flex gap-1">
    <?php $base='index.php?'.http_build_query($qsBase).($qsBase?'&':''); ?>
    <a href="<?=$base?>page=<?=max(1,$page-1)?>" class="px-3 py-1.5 rounded-input border bg-white dark:bg-[#0F172A] <?= $page<=1?'opacity-50 pointer-events-none':'' ?>">Prev</a>
    <a href="<?=$base?>page=<?=min($totalPages,$page+1)?>" class="px-3 py-1.5 rounded-input border bg-white dark:bg-[#0F172A] <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>">Next</a>
  </div>
</div>
</div>
<div class="md:hidden space-y-2 mt-2">
<?php foreach($rows as $i=>$r): $no=$off+$i+1; ?>
<div class="bg-white dark:bg-[#1E293B] border rounded-card p-3">
  <div class="flex justify-between text-xs"><span class="font-bold">#<?=$no?> · <?=htmlspecialchars($r['tanggal'])?></span><span class="px-2 py-0.5 rounded-full bg-[#EFF6FF] text-[#2563EB] text-[11px]"><?=htmlspecialchars($r['kelas'])?></span></div>
  <div class="font-semibold text-sm mt-1"><?=htmlspecialchars($r['siswa_nama'])?> <span class="font-normal text-xs text-[#94A3B8]"><?=htmlspecialchars($r['nipd'])?></span></div>
  <div class="text-xs mt-2"><span class="font-semibold">Permasalahan:</span> <?=htmlspecialchars($r['permasalahan'])?></div>
  <div class="text-xs mt-1"><span class="font-semibold">Tindakan:</span> <?=htmlspecialchars($r['tindakan']??'—')?></div>
  <div class="text-[11px] text-[#94A3B8] mt-1">Konselor: <?=htmlspecialchars($r['konselor'])?></div>
  <div class="flex gap-2 mt-2"><button type="button" onclick='openEditBk(<?=json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)?>)' class="flex-1 h-8 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium">Edit</button>
  <form method="POST" onsubmit="return confirm('Hapus?')" class="flex-1"><?=csrf_field()?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="w-full h-8 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium">Hapus</button></form></div>
</div>
<?php endforeach; ?>
</div>

<div id="mBk" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('mBk').classList.add('hidden')"></div>
  <form method="POST" class="relative bg-white dark:bg-[#1E293B] rounded-card border p-5 w-full max-w-lg space-y-3">
    <?=csrf_field()?><input type="hidden" name="aksi" value="tambah">
    <h3 class="font-semibold">Tambah Bimbingan Konseling</h3>
    <label class="block"><span class="text-[13px] font-medium">Siswa <span class="text-red-500">*</span></span>
      <div class="relative">
        <input type="hidden" name="siswa_id" id="bk_siswa_id" required value="<?=$filterSiswa?>">
        <div class="relative mt-1">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8] pointer-events-none"></i>
          <input id="bk_siswa_q" type="text" placeholder="Ketik nama / NIPD (min 2 huruf)..." autocomplete="off" value="<?= $filterSiswa ? htmlspecialchars($filterNama.' — '.$filterNipd) : '' ?>" class="w-full h-9 pl-9 pr-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm placeholder:text-[#94A3B8]">
        </div>
        <div id="bk_siswa_list" class="hidden absolute z-10 mt-1 w-full bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-lg max-h-60 overflow-y-auto divide-y"></div>
        <div id="bk_siswa_picked" class="<?= $filterSiswa ? '' : 'hidden' ?> text-xs mt-1.5 font-medium text-emerald-600 flex items-center gap-1"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i><span id="bk_siswa_picked_text"><?= $filterNama ? htmlspecialchars($filterNama).' — '.htmlspecialchars($filterNipd) : '' ?></span><button type="button" id="bk_siswa_clear" class="ml-2 text-[11px] text-[#94A3B8] hover:text-[#DC2626] underline">hapus</button></div>
        <p class="text-[11px] text-[#94A3B8] mt-1">Hanya menampilkan hasil pencarian. Format: <b>Nama — NIPD · Kelas</b></p>
      </div>
    </label>
    <label class="block"><span class="text-[13px] font-medium">Tanggal <span class="text-red-500">*</span></span><input type="date" name="tanggal" required value="<?=date('Y-m-d')?>" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="block"><span class="text-[13px] font-medium">Permasalahan <span class="text-red-500">*</span></span><textarea name="permasalahan" required rows="3" placeholder="Deskripsi permasalahan siswa" class="mt-1 w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
    <label class="block"><span class="text-[13px] font-medium">Tindakan / Followup</span><textarea name="tindakan" rows="3" placeholder="Tindakan, saran, followup" class="mt-1 w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
    <div class="flex gap-2"><button class="flex-1 h-10 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan</button><button type="button" onclick="document.getElementById('mBk').classList.add('hidden')" class="flex-1 h-10 rounded-input border bg-white dark:bg-[#0F172A] text-sm">Batal</button></div>
  </form>
</div>
<div id="mCariBk" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40" onclick="document.getElementById('mCariBk').classList.add('hidden')"></div><div class="relative bg-white dark:bg-[#1E293B] rounded-card border p-5 w-full max-w-md"><h3 class="font-semibold text-sm mb-3">Cari Siswa</h3><input id="qSiswaBk" type="text" placeholder="Ketik nama/NIPD min 2 huruf…" class="w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm mb-3"><div id="listSiswaBk" class="max-h-60 overflow-y-auto divide-y"></div><button onclick="document.getElementById('mCariBk').classList.add('hidden')" class="mt-3 w-full h-9 rounded-input border text-sm">Tutup</button></div></div>
<div id="mEditBk" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-black/40" onclick="document.getElementById('mEditBk').classList.add('hidden')"></div>
  <form method="POST" class="relative bg-white dark:bg-[#1E293B] rounded-card border p-5 w-full max-w-lg space-y-3">
    <?=csrf_field()?><input type="hidden" name="aksi" value="edit"><input type="hidden" name="id" id="bk_e_id">
    <h3 class="font-semibold">Edit Bimbingan Konseling</h3>
    <label class="block"><span class="text-[13px] font-medium">Tanggal</span><input type="date" name="tanggal" id="bk_e_tgl" required class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="block"><span class="text-[13px] font-medium">Permasalahan</span><textarea name="permasalahan" id="bk_e_prob" required rows="3" class="mt-1 w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
    <label class="block"><span class="text-[13px] font-medium">Tindakan / Followup</span><textarea name="tindakan" id="bk_e_tind" rows="3" class="mt-1 w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
    <div class="flex gap-2"><button class="flex-1 h-10 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan Perubahan</button><button type="button" onclick="document.getElementById('mEditBk').classList.add('hidden')" class="flex-1 h-10 rounded-input border bg-white dark:bg-[#0F172A] text-sm">Batal</button></div>
  </form>
</div>
<?php if($filterSiswa>0): ?><script>document.addEventListener('DOMContentLoaded',()=>{ const m=document.getElementById('mBk'); if(m) m.classList.remove('hidden'); });</script><?php endif; ?>
<script>
function openEditBk(r){ document.getElementById('bk_e_id').value=r.id; document.getElementById('bk_e_tgl').value=r.tanggal; document.getElementById('bk_e_prob').value=r.permasalahan; document.getElementById('bk_e_tind').value=r.tindakan||''; document.getElementById('mEditBk').classList.remove('hidden'); }
// BK: ketik-cari siswa inline (hanya hasil pencarian + kelas) — ponytail: upgrade ke combobox jika perlu multi-select
(function(){
  const q=document.getElementById('bk_siswa_q'), list=document.getElementById('bk_siswa_list'), hid=document.getElementById('bk_siswa_id'), picked=document.getElementById('bk_siswa_picked'), pickedText=document.getElementById('bk_siswa_picked_text'), clearBtn=document.getElementById('bk_siswa_clear');
  if(!q||!list||!hid) return;
  let tmr=null, lastQ='';
  function showList(html){ list.innerHTML=html; list.classList.remove('hidden'); }
  function hideList(){ list.classList.add('hidden'); }
  function setPicked(id,nama,nipd,kelas){
    hid.value=id;
    q.value=nama + ' — ' + nipd + ' · ' + kelas;
    if(picked&&pickedText){ pickedText.textContent=nama+' — '+nipd+' · '+kelas; picked.classList.remove('hidden'); }
    hideList();
    try{ lucide.createIcons(); }catch(e){}
  }
  q.addEventListener('input', ()=>{
    const v=q.value.trim();
    // jika user edit setelah pick, reset hidden sampai pilih lagi
    if(hid.value && v !== (pickedText?.textContent||'')) { hid.value=''; if(picked) picked.classList.add('hidden'); }
    clearTimeout(tmr);
    if(v.length<2){ hideList(); return; }
    if(v===lastQ) return;
    lastQ=v;
    tmr=setTimeout(async()=>{
      showList('<div class="p-3 text-xs text-[#94A3B8]">Mencari...</div>');
      try{
        const r=await fetch('/kesiswaanv2/siswa/search.php?q='+encodeURIComponent(v));
        const j=await r.json();
        const rows=j.rows||[];
        if(!rows.length){ showList('<div class="p-3 text-xs text-[#94A3B8]">Tidak ada — coba kata kunci lain</div>'); return; }
        showList(rows.map(x=>`<button type="button" data-id="${x.id}" data-nama="${String(x.nama).replace(/"/g,'&quot;')}" data-nipd="${x.nipd}" data-kelas="${String(x.kelas).replace(/"/g,'&quot;')}" class="w-full text-left px-3 py-2.5 hover:bg-[#F1F5F9] dark:hover:bg-white/5 flex justify-between gap-3"><span class="font-medium text-sm truncate">${x.nama}</span><span class="text-xs text-[#475569] dark:text-[#94A3B8] shrink-0">${x.nipd} · ${x.kelas}</span></button>`).join(''));
        list.querySelectorAll('button[data-id]').forEach(b=> b.addEventListener('click', ()=> setPicked(b.dataset.id,b.dataset.nama,b.dataset.nipd,b.dataset.kelas)));
      }catch(e){ showList('<div class="p-3 text-xs text-red-500">Gagal cari</div>'); }
    },280);
  });
  q.addEventListener('focus', ()=>{ if(q.value.trim().length>=2 && list.innerHTML) list.classList.remove('hidden'); });
  document.addEventListener('click', (e)=>{ if(!list.contains(e.target) && e.target!==q) hideList(); });
  q.addEventListener('keydown', (e)=>{ if(e.key==='Escape') hideList(); });
  clearBtn?.addEventListener('click', ()=>{ hid.value=''; q.value=''; if(picked) picked.classList.add('hidden'); hideList(); q.focus(); });
  // validasi submit: wajib pilih dari pencarian
  const form=q.closest('form');
  form?.addEventListener('submit', (e)=>{
    if(!hid.value){
      e.preventDefault();
      q.focus();
      showList('<div class="p-3 text-xs text-[#DC2626]">Pilih siswa dari hasil pencarian dulu</div>');
    }
  });
})();
// fallback modal Cari Siswa lama (tetap hidup jika dibuka)
const qBk=document.getElementById('qSiswaBk'), listBk=document.getElementById('listSiswaBk');
let tmrBk=null;
if(qBk) qBk.addEventListener('input', ()=>{ clearTimeout(tmrBk); const q=qBk.value.trim(); if(q.length<2){ listBk.innerHTML='<div class="p-3 text-xs text-[#94A3B8]">Ketik min 2 huruf</div>'; return; } tmrBk=setTimeout(async()=>{ try{ const r=await fetch('/kesiswaanv2/siswa/search.php?q='+encodeURIComponent(q)); const j=await r.json(); listBk.innerHTML=(j.rows||[]).map(x=>`<button type="button" data-id="${x.id}" data-nama="${x.nama}" data-nipd="${x.nipd}" data-kelas="${x.kelas}" class="w-full text-left px-3 py-2 hover:bg-slate-50 dark:hover:bg-white/5 text-sm flex justify-between"><span>${x.nama}</span><span class="text-xs text-[#94A3B8]">${x.nipd} · ${x.kelas}</span></button>`).join('')||'<div class="p-3 text-xs">Tidak ada</div>'; listBk.querySelectorAll('button[data-id]').forEach(b=> b.addEventListener('click', ()=>{ const hid=document.getElementById('bk_siswa_id'), qq=document.getElementById('bk_siswa_q'), picked=document.getElementById('bk_siswa_picked'), pickedText=document.getElementById('bk_siswa_picked_text'); if(hid&&qq){ hid.value=b.dataset.id; qq.value=b.dataset.nama+' — '+b.dataset.nipd+' · '+b.dataset.kelas; if(picked&&pickedText){ pickedText.textContent=b.dataset.nama+' — '+b.dataset.nipd+' · '+b.dataset.kelas; picked.classList.remove('hidden'); } } document.getElementById('mCariBk').classList.add('hidden'); })); }catch(e){ listBk.innerHTML='<div class="p-3 text-xs text-red-500">Gagal cari</div>'; } },300); });
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
