<?php
$active='pelanggaran_tambah'; $title='Catat Pelanggaran';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_catat_pelanggaran');
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_can('mutate_catat_pelanggaran');
  if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF'); }
  try{
    $sid=(int)($_POST['siswa_id']??0); $jid=(int)($_POST['jenis_pelanggaran_id']??0);
    $poin=(int)($_POST['poin_final']??0); $tgl=trim($_POST['tanggal']??date('Y-m-d'));
    $lok=trim($_POST['lokasi']??''); $ket=trim($_POST['keterangan']??''); $tind=trim($_POST['tindakan']??'');
    if(!$sid||!$jid||$poin<1||$poin>100) throw new Exception('Siswa / jenis / poin 1-100 wajib');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tgl)) $tgl=date('Y-m-d');
    $st=$pdo->prepare("SELECT s.kelas_id,k.tahun_ajaran FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.id=? AND s.deleted_at IS NULL LIMIT 1"); $st->execute([$sid]); $sx=$st->fetch();
    if(!$sx) throw new Exception('Siswa tidak ditemukan');
    if((current_user()['role']??'')==='Wali Kelas'){
      $ids=wali_ampu_ids($pdo,(int)($_SESSION['user']['id']??0));
      if(empty($ids) || !in_array((int)$sx['kelas_id'],$ids,true)) throw new Exception('Hanya bisa catat untuk kelas ampu Anda');
    }
    $taIns=$sx['tahun_ajaran']??'2024/2025';
    $st=$pdo->prepare("SELECT bobot_poin FROM jenis_pelanggaran WHERE id=? AND deleted_at IS NULL LIMIT 1"); $st->execute([$jid]); $jp=$st->fetch();
    if(!$jp) throw new Exception('Jenis pelanggaran tidak ditemukan');
    $bobot=(int)$jp['bobot_poin'];
    $pelapor=(int)($_SESSION['user']['id']??0);
    $pdo->prepare("INSERT INTO pelanggaran_siswa(siswa_id,jenis_pelanggaran_id,poin_final,tahun_ajaran,tanggal,lokasi,keterangan,tindakan,pelapor_id) VALUES(?,?,?,?,?,?,?,?,?)")->execute([$sid,$jid,$poin,$taIns,$tgl,$lok?:null,$ket?:null,$tind?:null,$pelapor]);
    $nid=(int)$pdo->lastInsertId();
    if($poin!==$bobot) $pdo->prepare("INSERT INTO audit_poin(pelanggaran_siswa_id,poin_default,poin_final,alasan,changed_by) VALUES(?,?,?,?,?)")->execute([$nid,$bobot,$poin,$ket?:'override dari catat pelanggaran',$pelapor]);
    header('Location: tambah.php?msg='.urlencode('Pelanggaran tercatat')); exit;
  }catch(Throwable $e){ error_log('catat pelanggaran: '.$e->getMessage()); header('Location: tambah.php?err='.urlencode('Gagal simpan')); exit; }
}
require __DIR__.'/../includes/header.php';
$jenisList=[];
try{ $jenisList=$pdo->query("SELECT id,kode,nama,bobot_poin FROM jenis_pelanggaran WHERE deleted_at IS NULL ORDER BY kode")->fetchAll(); }catch(Throwable $e){}
?>
<?php if(isset($_GET['msg'])): ?><div class="mb-3 rounded-lg border-l-4 border-emerald-500 bg-[#F0FDF4] px-4 py-2.5 text-sm"><?=htmlspecialchars($_GET['msg'])?></div><?php endif; ?>
<?php if(isset($_GET['err'])): ?><div class="mb-3 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($_GET['err'])?></div><?php endif; ?>
<div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4 sm:p-5 overflow-hidden max-w-full">
  <h3 class="font-semibold text-sm mb-3 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-emerald-600"></i> Catat Pelanggaran — Input Cepat</h3>
  <form method="POST" class="space-y-3 min-w-0">
    <?=csrf_field()?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 min-w-0">
      <div class="min-w-0">
        <label class="block text-xs font-medium mb-1">Siswa <span class="text-red-500">*</span></label>
        <div class="flex gap-2 min-w-0">
          <select name="siswa_id" id="siswa_id" required class="flex-1 min-w-0 w-full max-w-full h-9 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] px-3 text-sm truncate">
            <option value="">Pilih siswa...</option>
            <?php try{ $waliFilter=''; if((current_user()['role']??'')==='Wali Kelas'){ $ids=wali_ampu_ids($pdo,(int)($_SESSION['user']['id']??0)); $waliFilter = empty($ids)? " AND 1=0" : " AND s.kelas_id IN (".implode(',',array_map('intval',$ids)).")"; } $sl=$pdo->query("SELECT s.id,s.nama,s.nipd,COALESCE(k.nama_kelas,'—') kelas FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.deleted_at IS NULL AND s.status='Aktif' $waliFilter ORDER BY s.nama LIMIT 200")->fetchAll(); foreach($sl as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['nama'])?> — <?=$s['nipd']?> — <?=htmlspecialchars($s['kelas'])?></option><?php endforeach; }catch(Throwable $e){} ?>
          </select>
          <button type="button" onclick="document.getElementById('mCari').classList.remove('hidden');document.getElementById('qSiswa').focus()" class="h-9 px-3 rounded-input border text-xs font-medium shrink-0">Cari</button>
        </div>
        <div id="siswaPicked" class="text-xs font-medium text-emerald-600 mt-1 hidden break-words"></div>
      </div>
      <div class="min-w-0">
        <label class="block text-xs font-medium mb-1">Jenis Pelanggaran <span class="text-red-500">*</span></label>
        <select name="jenis_pelanggaran_id" id="jp_id" required class="w-full min-w-0 max-w-full h-9 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] px-3 text-sm truncate">
          <option value="">Pilih jenis...</option>
          <?php foreach($jenisList as $j): ?><option value="<?=$j['id']?>" data-bobot="<?=$j['bobot_poin']?>"><?=htmlspecialchars($j['kode'])?> — <?=htmlspecialchars($j['nama'])?> (<?=$j['bobot_poin']?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="min-w-0">
        <label class="block text-xs font-medium mb-1">Poin <span class="text-red-500">*</span> <span id="bobotInfo" class="font-normal text-[#94A3B8]"></span></label>
        <input type="number" name="poin_final" id="poin_final" min="1" max="100" required placeholder="auto dari bobot" class="w-full min-w-0 max-w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm">
      </div>
      <div class="min-w-0">
        <label class="block text-xs font-medium mb-1">Tanggal</label>
        <input type="date" name="tanggal" value="<?=date('Y-m-d')?>" class="w-full min-w-0 max-w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm">
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 min-w-0">
      <label class="block text-xs font-medium min-w-0">Lokasi<input type="text" name="lokasi" placeholder="Ruang kelas" class="mt-1 w-full min-w-0 max-w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
      <label class="block text-xs font-medium min-w-0">Tindakan<input type="text" name="tindakan" placeholder="Teguran, panggilan ortu" class="mt-1 w-full min-w-0 max-w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    </div>
    <label class="block text-xs font-medium min-w-0">Keterangan<textarea name="keterangan" rows="2" placeholder="Detail kejadian (wajib jika override poin)" class="mt-1 w-full min-w-0 max-w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
    <div class="flex flex-wrap gap-2">
      <button class="h-10 px-5 rounded-input bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold inline-flex items-center gap-1.5"><i data-lucide="save" class="w-4 h-4"></i> Simpan Pelanggaran</button>
      <a href="/kesiswaanv2/dashboard.php" class="h-10 px-4 inline-flex items-center rounded-input border text-sm">Kembali ke Dashboard</a>
    </div>
  </form>
  <div id="mCari" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40" onclick="document.getElementById('mCari').classList.add('hidden')"></div><div class="relative bg-white dark:bg-[#1E293B] rounded-card border p-5 w-full max-w-md"><h3 class="font-semibold text-sm mb-3">Cari Siswa</h3><input id="qSiswa" type="text" placeholder="Ketik nama/NIPD min 2 huruf..." class="w-full h-10 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm mb-3"><div id="listSiswa" class="max-h-60 overflow-y-auto divide-y"></div><button onclick="document.getElementById('mCari').classList.add('hidden')" class="mt-3 w-full h-9 rounded-input border text-sm">Tutup</button></div></div>
</div>
<script>
const jpSel=document.getElementById('jp_id'), poinIn=document.getElementById('poin_final'), info=document.getElementById('bobotInfo');
if(jpSel) jpSel.addEventListener('change', ()=>{ const o=jpSel.options[jpSel.selectedIndex]; const b=o?.dataset?.bobot; if(b){ poinIn.value=b; info.textContent='(bobot '+b+', boleh override)'; } });
const qEl=document.getElementById('qSiswa'), listEl=document.getElementById('listSiswa'), sisSel=document.getElementById('siswa_id');
let tmr=null;
if(qEl) qEl.addEventListener('input', ()=>{ clearTimeout(tmr); const q=qEl.value.trim(); if(q.length<2){ listEl.innerHTML='<div class="p-3 text-xs text-muted">Ketik min 2 huruf</div>'; return; } tmr=setTimeout(async()=>{ try{ const r=await fetch('/kesiswaanv2/siswa/search.php?q='+encodeURIComponent(q)); const j=await r.json(); listEl.innerHTML=(j.rows||[]).map(x=>`<button type="button" data-id="${x.id}" data-nama="${x.nama}" data-nipd="${x.nipd}" data-kelas="${x.kelas}" class="w-full text-left px-3 py-2 hover:bg-slate-50 dark:hover:bg-white/5 text-sm flex justify-between"><span>${x.nama}</span><span class="text-xs text-muted">${x.nipd} · ${x.kelas}</span></button>`).join('')||'<div class="p-3 text-xs">Tidak ada</div>'; listEl.querySelectorAll('button[data-id]').forEach(b=> b.addEventListener('click', ()=>{ const id=b.dataset.id, nm=b.dataset.nama, nip=b.dataset.nipd, kls=b.dataset.kelas||'—'; if(![...sisSel.options].some(o=>o.value===id)){ const o=document.createElement('option'); o.value=id; o.textContent=nm+' — '+nip+' — '+kls+' (dari pencarian)'; o.selected=true; sisSel.appendChild(o); } else sisSel.value=id; const picked=document.getElementById('siswaPicked'); if(picked){ picked.textContent='✓ '+nm+' — '+nip+' · '+kls; picked.classList.remove('hidden'); } sisSel.dispatchEvent(new Event('change')); document.getElementById('mCari').classList.add('hidden'); })); }catch(e){ listEl.innerHTML='<div class="p-3 text-xs text-red-500">Gagal cari</div>'; } },300); });
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
