<?php $active='siswa'; $title='Detail Siswa'; require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='edit_poin'){
  if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF'); }
  $psId=(int)($_POST['pelanggaran_siswa_id']??0); $newPoin=(int)($_POST['poin_final']??0);
  if($newPoin<1||$newPoin>100){ header('Location: detail.php?id='.(int)($_GET['id']??0).'&err='.urlencode('Poin 1-100')); exit; }
  try{
    $cur=$pdo->prepare("SELECT poin_final, keterangan FROM pelanggaran_siswa WHERE id=?"); $cur->execute([$psId]); $rowCur=$cur->fetch();
    if(!$rowCur) throw new Exception('Data tidak ditemukan');
    if($newPoin!==(int)$rowCur['poin_final'] && trim((string)($_POST['alasan']??''))==='') throw new Exception('Override poin wajib isi alasan');
    $alasan=trim($_POST['alasan']??$rowCur['keterangan']??'');
    $pdo->prepare("UPDATE pelanggaran_siswa SET poin_final=?, keterangan=? WHERE id=?")->execute([$newPoin,$alasan?:$rowCur['keterangan'],$psId]);
    if($newPoin!==(int)$rowCur['poin_final']){
      $pdo->prepare("INSERT INTO audit_poin(pelanggaran_siswa_id,poin_default,poin_final,alasan,changed_by) VALUES(?,?,?,?,?)")->execute([$psId,(int)$rowCur['poin_final'],$newPoin,$alasan,(int)($_SESSION['user']['id']??0)]);
    }
    header('Location: detail.php?id='.(int)($_GET['id']??0).'&msg='.urlencode('Poin diperbarui')); exit;
  }catch(Throwable $e){ header('Location: detail.php?id='.(int)($_GET['id']??0).'&err='.urlencode($e->getMessage())); exit; }
}
require __DIR__.'/../includes/header.php';
$id=(int)($_GET['id']??0); if(!$id){ header('Location: index.php'); exit; }
$row=null; $threshold=76; $ta='2024/2025';
try{ $v=$pdo->query("SELECT value FROM settings WHERE key_name='threshold_poin_kritis' LIMIT 1")->fetchColumn(); if($v!==false&&$v!=='') $threshold=(int)$v; }catch(Throwable $e){}
try{ $ta=$pdo->query("SELECT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran DESC LIMIT 1")->fetchColumn()?:$ta; }catch(Throwable $e){}
try{ $st=$pdo->prepare("SELECT s.*, COALESCE(k.nama_kelas,'—') as kelas_nama, COALESCE((SELECT SUM(poin_final) FROM pelanggaran_siswa WHERE siswa_id=s.id AND tahun_ajaran=?),0) as poin FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.id=? AND s.deleted_at IS NULL LIMIT 1"); $st->execute([$ta,$id]); $row=$st->fetch(); }catch(Throwable $e){}
if(!$row){ echo '<div class="rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-3 text-sm">Siswa tidak ditemukan</div>'; require __DIR__.'/../includes/footer.php'; exit; }
$pdAddr=null; try{ $ps=$pdo->prepare("SELECT alamat,rt,rw,dusun,kelurahan,kecamatan,kode_pos FROM peserta_didik WHERE nipd=? OR nisn=? LIMIT 1"); $ps->execute([$row['nipd'],$row['nipd']]); $pdAddr=$ps->fetch(); }catch(Throwable $e){}
$alamatTampil=$row['alamat']??$pdAddr['alamat']??'-';
if($pdAddr && ($pdAddr['rt']||$pdAddr['rw']||$pdAddr['kelurahan'])){
  $parts=array_filter([$alamatTampil, $pdAddr['rt']?'RT '.$pdAddr['rt']:'', $pdAddr['rw']?'RW '.$pdAddr['rw']:'', $pdAddr['dusun']??'', $pdAddr['kelurahan']??'', $pdAddr['kecamatan']??'', $pdAddr['kode_pos']??'']);
  if(count($parts)>1) $alamatTampil=implode(', ',array_filter($parts));
}
$poin=(int)($row['poin']??0); $badge=$poin>$threshold?'Kritis':($poin>50?'Tinggi':($poin>25?'Sedang':'Rendah'));
$bc=['Rendah'=>'bg-[#DCFCE7] text-[#16A34A]','Sedang'=>'bg-[#FEF9C3] text-[#CA8A04]','Tinggi'=>'bg-[#FED7AA] text-[#EA580C]','Kritis'=>'bg-[#FEE2E2] text-[#DC2626]'][$badge];
$riwayat=[]; try{ $rs=$pdo->prepare("SELECT ps.id, ps.tanggal, COALESCE(jp.nama,'-') as pelanggaran, ps.poin_final as poin, COALESCE(u.nama,'-') as pelapor, ps.keterangan, ps.tahun_ajaran as ta FROM pelanggaran_siswa ps LEFT JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id LEFT JOIN users u ON u.id=ps.pelapor_id WHERE ps.siswa_id=? ORDER BY ps.tanggal DESC"); $rs->execute([$id]); $riwayat=$rs->fetchAll(); }catch(Throwable $e){}
?>
<div class="grid lg:grid-cols-3 gap-4">
  <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5">
    <div class="flex gap-4">
      <?php $foto=$row['foto']? '/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($row['foto']): 'https://i.pravatar.cc/200?u='.urlencode($row['nipd']); ?>
      <img src="<?=$foto?>" class="w-20 h-20 rounded-xl object-cover border">
      <div>
        <div class="font-bold"><?=htmlspecialchars($row['nama'])?></div>
        <div class="text-xs text-[#475569] dark:text-[#94A3B8]">NIPD <?=htmlspecialchars($row['nipd'])?> · <?=htmlspecialchars($row['kelas_nama'])?> · <?=htmlspecialchars($row['jenis_kelamin'])?></div>
        <span class="inline-flex mt-2 px-2 py-1 rounded-badge text-xs font-semibold <?=$bc?>"><?=$badge?> · <?=$poin?> poin (TA berjalan)</span>
      </div>
    </div>
    <dl class="mt-4 space-y-2 text-sm">
      <div class="flex justify-between"><dt class="text-[#475569] dark:text-[#94A3B8]">Tempat, Tgl Lahir</dt><dd class="font-medium"><?=htmlspecialchars(($row['tempat_lahir']??'-').', '.($row['tanggal_lahir']??'-'))?></dd></div>
      <div class="flex justify-between"><dt class="text-[#475569] dark:text-[#94A3B8]">Orang Tua</dt><dd class="font-medium"><?=htmlspecialchars($row['nama_ortu']??'-')?></dd></div>
      <div class="flex justify-between"><dt class="text-[#475569] dark:text-[#94A3B8]">HP Ortu</dt><dd class="font-medium"><?=htmlspecialchars($row['hp_ortu']??'-')?></dd></div>
      <div class="flex justify-between"><dt class="text-[#475569] dark:text-[#94A3B8]">Status</dt><dd><span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold"><?=htmlspecialchars($row['status'])?></span></dd></div>
      <div class="pt-2 border-t border-[#E2E8F0] dark:border-[#334155]"><dt class="text-[#475569] dark:text-[#94A3B8] text-xs">Alamat</dt><dd class="font-medium mt-1 text-xs leading-5"><?=nl2br(htmlspecialchars($alamatTampil))?></dd></div>
    </dl>
    <div class="flex gap-2 mt-4">
      <a href="/kesiswaanv2/siswa/edit.php?id=<?=$id?>" class="flex-1 h-9 inline-flex justify-center items-center gap-1 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-sm font-medium"><i data-lucide="pencil" class="w-4 h-4"></i> Edit Siswa</a>
    </div>
    <p class="text-[11px] text-[#94A3B8] mt-3">Poin dihitung per tahun ajaran (<?=htmlspecialchars($ta)?>). Arsip TA lalu tetap tampil di riwayat.</p>
  </div>
  <div class="lg:col-span-2 space-y-4">
    <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden">
      <div class="px-4 py-3 border-b border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between">
        <h3 class="font-semibold text-sm">Riwayat Pelanggaran</h3>
        <span class="text-xs text-[#475569] dark:text-[#94A3B8]">Total arsip: <?=count($riwayat)?></span>
      </div>
      <?php if(!$riwayat): ?><div class="px-4 py-10 text-center text-sm text-[#94A3B8]">Belum ada pelanggaran</div><?php else: ?>
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="text-left px-4 py-2">Tanggal</th><th class="text-left px-4 py-2">Pelanggaran</th><th class="text-left px-4 py-2">Poin</th><th class="text-left px-4 py-2">Pelapor</th><th class="text-left px-4 py-2">TA</th><th class="text-left px-4 py-2">Aksi</th></tr></thead>
          <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
            <?php foreach($riwayat as $r): ?><tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]"><td class="px-4 py-2.5 text-xs"><?=htmlspecialchars($r['tanggal'])?></td><td class="px-4 py-2.5"><?=htmlspecialchars($r['pelanggaran'])?><div class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['keterangan']??'-')?></div></td><td class="px-4 py-2.5 font-bold"><?=htmlspecialchars($r['poin'])?></td><td class="px-4 py-2.5 text-xs"><?=htmlspecialchars($r['pelapor'])?></td><td class="px-4 py-2.5 text-xs"><?=htmlspecialchars($r['ta'])?></td><td class="px-4 py-2.5"><button type="button" onclick='openEditPoin(<?=json_encode(["id"=>$r["id"],"poin"=>$r["poin"],"ket"=>$r["keterangan"]], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)?>)' class="px-2 py-1 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium inline-flex items-center gap-1"><i data-lucide="pencil" class="w-3 h-3"></i> Edit</button></td></tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="md:hidden divide-y divide-[#E2E8F0] dark:divide-[#334155]">
        <?php foreach($riwayat as $r): ?><div class="p-4"><div class="flex justify-between"><span class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['tanggal'])?> · <?=htmlspecialchars($r['ta'])?></span><span class="font-bold text-sm">+<?=htmlspecialchars($r['poin'])?></span></div><div class="font-medium text-sm mt-1"><?=htmlspecialchars($r['pelanggaran'])?></div><div class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['keterangan']??'-')?> · <?=htmlspecialchars($r['pelapor'])?></div><button type="button" onclick='openEditPoin(<?=json_encode(["id"=>$r["id"],"poin"=>$r["poin"],"ket"=>$r["keterangan"]], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)?>)' class="mt-2 w-full h-8 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium">Edit Poin</button></div><?php endforeach; ?>
      </div>
      <?php if(isset($_GET['msg'])): ?><div class="mt-3 rounded-lg border-l-4 border-emerald-500 bg-[#F0FDF4] px-4 py-2 text-sm"><?=htmlspecialchars($_GET['msg'])?></div><?php endif; ?>
      <?php if(isset($_GET['err'])): ?><div class="mt-3 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2 text-sm"><?=htmlspecialchars($_GET['err'])?></div><?php endif; ?>
      <div id="mEditPoin" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40" onclick="document.getElementById('mEditPoin').classList.add('hidden')"></div>
        <form method="POST" class="relative bg-white dark:bg-[#1E293B] rounded-card border p-5 w-full max-w-sm space-y-3">
          <?=csrf_field()?><input type="hidden" name="aksi" value="edit_poin"><input type="hidden" name="pelanggaran_siswa_id" id="ep_id">
          <h3 class="font-semibold text-sm">Edit Poin Pelanggaran</h3>
          <label class="block"><span class="text-xs font-medium">Poin (1–100)</span><input type="number" name="poin_final" id="ep_poin" min="1" max="100" required class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
          <label class="block"><span class="text-xs font-medium">Alasan override <span class="text-red-500">*</span> jika ubah poin</span><textarea name="alasan" id="ep_alasan" rows="2" placeholder="Wajib isi bila poin berbeda dari sebelumnya" class="mt-1 w-full px-3 py-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></textarea></label>
          <div class="flex gap-2"><button class="flex-1 h-9 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan</button><button type="button" onclick="document.getElementById('mEditPoin').classList.add('hidden')" class="flex-1 h-9 rounded-input border bg-white dark:bg-[#0F172A] text-sm">Batal</button></div>
        </form>
      </div>
      <script>function openEditPoin(d){ document.getElementById('ep_id').value=d.id; document.getElementById('ep_poin').value=d.poin; document.getElementById('ep_alasan').value=d.ket||''; document.getElementById('mEditPoin').classList.remove('hidden'); }</script>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
