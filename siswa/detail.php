<?php $active='siswa'; $title='Detail Siswa'; require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
// Siswa: read-only — cek own nanti saat load row; view boleh untuk semua role login
if(!role_can('view_siswa') && !role_can('view_siswa_readonly')){ http_response_code(403); exit('Forbidden'); }
// POST guards per aksi
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='edit_poin'){ require_can('edit_poin'); }
if($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['aksi']??'', ['simpan_kesehatan','tambah_sakit','hapus_sakit'], true)){ require_can('edit_kesehatan'); }
// POST: edit poin
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
  }catch(Throwable $e){ error_log('detail edit_poin: '.$e->getMessage()); header('Location: detail.php?id='.(int)($_GET['id']??0).'&err='.urlencode('Gagal memproses: '.$e->getMessage())); exit; }
}
// POST: simpan kesehatan
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='simpan_kesehatan'){
  if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF'); }
  $sid=(int)($_GET['id']??0);
  $tinggi=trim($_POST['tinggi_badan']??''); $berat=trim($_POST['berat_badan']??'');
  $goldar=trim($_POST['golongan_darah']??''); if(!in_array($goldar,['A','B','AB','O',''],true)) $goldar='';
  $cacat=in_array($_POST['cacat_tubuh']??'Tidak',['Ya','Tidak'],true)?$_POST['cacat_tubuh']:'Tidak';
  $cacatKet=trim($_POST['cacat_keterangan']??'');
  $kacamata=in_array($_POST['pakai_kacamata']??'Tidak',['Ya','Tidak'],true)?$_POST['pakai_kacamata']:'Tidak';
  $minus=trim($_POST['kacamata_minus']??''); $silinder=trim($_POST['kacamata_silinder']??'');
  try{
    $pdo->prepare("INSERT INTO siswa_kesehatan(siswa_id,tinggi_badan,berat_badan,golongan_darah,cacat_tubuh,cacat_keterangan,pakai_kacamata,kacamata_minus,kacamata_silinder) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tinggi_badan=VALUES(tinggi_badan),berat_badan=VALUES(berat_badan),golongan_darah=VALUES(golongan_darah),cacat_tubuh=VALUES(cacat_tubuh),cacat_keterangan=VALUES(cacat_keterangan),pakai_kacamata=VALUES(pakai_kacamata),kacamata_minus=VALUES(kacamata_minus),kacamata_silinder=VALUES(kacamata_silinder)")
      ->execute([$sid,$tinggi?:null,$berat?:null,$goldar?:null,$cacat,$cacatKet?:null,$kacamata,$minus?:null,$silinder?:null]);
    header('Location: detail.php?id='.$sid.'&msg='.urlencode('Data kesehatan disimpan')); exit;
  }catch(Throwable $e){ error_log('detail kesehatan: '.$e->getMessage()); header('Location: detail.php?id='.$sid.'&err='.urlencode('Gagal menyimpan')); exit; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='tambah_sakit'){
  if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF'); }
  $sid=(int)($_GET['id']??0);
  $jp=trim($_POST['jenis_penyakit']??''); $usia=trim($_POST['usia_saat_sakit']??''); $op=in_array($_POST['opname']??'Tidak',['Ya','Tidak'],true)?$_POST['opname']:'Tidak'; $rs=trim($_POST['rumah_sakit']??'');
  if($jp===''){ header('Location: detail.php?id='.$sid.'&err='.urlencode('Jenis penyakit wajib')); exit; }
  try{ $pdo->prepare("INSERT INTO siswa_sakit(siswa_id,jenis_penyakit,usia_saat_sakit,opname,rumah_sakit) VALUES(?,?,?,?,?)")->execute([$sid,$jp,$usia?:null,$op,$rs?:null]); header('Location: detail.php?id='.$sid.'&msg='.urlencode('Riwayat sakit ditambahkan')); exit; }catch(Throwable $e){ error_log('detail tambah_sakit: '.$e->getMessage()); header('Location: detail.php?id='.$sid.'&err='.urlencode('Gagal menambah')); exit; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='hapus_sakit'){
  if(!csrf_verify($_POST['csrf_token']??'')){ http_response_code(403); exit('CSRF'); }
  $sid=(int)($_GET['id']??0); $rid=(int)($_POST['sakit_id']??0);
  try{ $pdo->prepare("DELETE FROM siswa_sakit WHERE id=? AND siswa_id=?")->execute([$rid,$sid]); header('Location: detail.php?id='.$sid.'&msg='.urlencode('Riwayat dihapus')); exit; }catch(Throwable $e){ error_log('detail hapus_sakit: '.$e->getMessage()); header('Location: detail.php?id='.$sid.'&err='.urlencode('Gagal hapus')); exit; }
}

$id=(int)($_GET['id']??0); if(!$id){ header('Location: index.php'); exit; }
// Wali Kelas: cek kepemilikan kelas nanti setelah load row
$isWaliDetail = (current_user()['role'] ?? '') === 'Wali Kelas';
$waliIdsDetail = $isWaliDetail ? wali_ampu_ids($pdo, (int)($_SESSION['user']['id'] ?? 0)) : [];
require __DIR__.'/../includes/header.php';
$row=null; $threshold=76; $ta='2024/2025';
try{ $v=$pdo->query("SELECT value FROM settings WHERE key_name='threshold_poin_kritis' LIMIT 1")->fetchColumn(); if($v!==false&&$v!=='') $threshold=(int)$v; }catch(Throwable $e){}
try{ $ta=$pdo->query("SELECT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran DESC LIMIT 1")->fetchColumn()?:$ta; }catch(Throwable $e){}
try{ $st=$pdo->prepare("SELECT s.*, s.kelas_id as kelas_id_raw, COALESCE(k.nama_kelas,'—') as kelas_nama, COALESCE((SELECT SUM(poin_final) FROM pelanggaran_siswa WHERE siswa_id=s.id AND tahun_ajaran=?),0) as poin FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.id=? AND s.deleted_at IS NULL LIMIT 1"); $st->execute([$ta,$id]); $row=$st->fetch(); }catch(Throwable $e){}
if(!$row){ echo '<div class="rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-3 text-sm">Siswa tidak ditemukan</div>'; require __DIR__.'/../includes/footer.php'; exit; }
if($isWaliDetail && !in_array((int)($row['kelas_id_raw'] ?? 0), $waliIdsDetail, true)){
  http_response_code(403);
  $ampuNames = wali_ampu_names($pdo, (int)($_SESSION['user']['id'] ?? 0));
  $ampuTxt = $ampuNames ? implode(', ',$ampuNames) : '— belum di-set';
  echo '<div class="rounded-lg border-l-4 border-amber-500 bg-[#FFFBEB] px-4 py-3 text-sm">Anda hanya bisa melihat siswa kelas ampu Anda: <b>'.htmlspecialchars($ampuTxt).'</b> — siswa ini di kelas '.htmlspecialchars($row['kelas_nama']).'</div>';
  require __DIR__.'/../includes/footer.php'; exit;
}
$pd=null; $pdAddr=null; try{ $ps=$pdo->prepare("SELECT * FROM peserta_didik WHERE nipd=? OR nisn=? LIMIT 1"); $ps->execute([$row['nipd'],$row['nipd']]); $pd=$ps->fetch(); $pdAddr=$pd; }catch(Throwable $e){}
$alamatTampil=$row['alamat']??$pdAddr['alamat']??'-';
if($pdAddr && ($pdAddr['rt']||$pdAddr['rw']||$pdAddr['kelurahan'])){
  $parts=array_filter([$alamatTampil, $pdAddr['rt']?'RT '.$pdAddr['rt']:'', $pdAddr['rw']?'RW '.$pdAddr['rw']:'', $pdAddr['dusun']??'', $pdAddr['kelurahan']??'', $pdAddr['kecamatan']??'', $pdAddr['kode_pos']??'']);
  if(count($parts)>1) $alamatTampil=implode(', ',array_filter($parts));
}
function f($v){ return $v!==null && $v!=='' ? $v : '—'; }
$poin=(int)($row['poin']??0); $badge=$poin>$threshold?'Kritis':($poin>50?'Tinggi':($poin>25?'Sedang':'Rendah'));
$bc=['Rendah'=>'bg-[#DCFCE7] text-[#16A34A]','Sedang'=>'bg-[#FEF9C3] text-[#CA8A04]','Tinggi'=>'bg-[#FED7AA] text-[#EA580C]','Kritis'=>'bg-[#FEE2E2] text-[#DC2626]'][$badge];
$riwayat=[]; try{ $rs=$pdo->prepare("SELECT ps.id, ps.tanggal, COALESCE(jp.nama,'-') as pelanggaran, ps.poin_final as poin, COALESCE(u.nama,'-') as pelapor, ps.keterangan, ps.tahun_ajaran as ta FROM pelanggaran_siswa ps LEFT JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id LEFT JOIN users u ON u.id=ps.pelapor_id WHERE ps.siswa_id=? ORDER BY ps.tanggal DESC"); $rs->execute([$id]); $riwayat=$rs->fetchAll(); }catch(Throwable $e){}
$kes=null; try{ $st=$pdo->prepare("SELECT * FROM siswa_kesehatan WHERE siswa_id=? LIMIT 1"); $st->execute([$id]); $kes=$st->fetch(); }catch(Throwable $e){}
$sakit=[]; try{ $st=$pdo->prepare("SELECT * FROM siswa_sakit WHERE siswa_id=? ORDER BY id ASC"); $st->execute([$id]); $sakit=$st->fetchAll(); }catch(Throwable $e){}
$bk=[]; try{ $st=$pdo->prepare("SELECT bk.*, COALESCE(u.nama,'—') konselor_nama FROM bimbingan_konseling bk LEFT JOIN users u ON u.id=bk.konselor_id WHERE bk.siswa_id=? ORDER BY bk.tanggal DESC, bk.id DESC"); $st->execute([$id]); $bk=$st->fetchAll(); }catch(Throwable $e){}
$tinggiEff = $kes['tinggi_badan']??$pd['tinggi_badan']??'';
$beratEff = $kes['berat_badan']??$pd['berat_badan']??'';
?>
<?php if(isset($_GET['msg'])): ?><div class="mb-3 rounded-lg border-l-4 border-emerald-500 bg-[#F0FDF4] px-4 py-2.5 text-sm"><?=htmlspecialchars($_GET['msg'])?></div><?php endif; ?>
<?php if(isset($_GET['err'])): ?><div class="mb-3 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-sm"><?=htmlspecialchars($_GET['err'])?></div><?php endif; ?>
<div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5">
  <div class="flex flex-col sm:flex-row gap-4 sm:items-center">
    <?php $canMutateSiswaDetail = role_can('mutate_siswa'); $canEditPoinDetail = role_can('edit_poin'); $canEditKesDetail = role_can('edit_kesehatan'); $isSiswa = (current_user()['role']??'')==='Siswa'; ?>
    <?php $foto=$row['foto']? '/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($row['foto']): 'https://i.pravatar.cc/200?u='.urlencode($row['nipd']); ?>
    <img src="<?=$foto?>" class="w-20 h-20 rounded-xl object-cover border shrink-0">
    <div class="min-w-0 flex-1">
      <div class="font-bold text-[15px]"><?=htmlspecialchars($row['nama'])?></div>
      <div class="text-xs text-[#475569] dark:text-[#94A3B8]">NIPD <?=htmlspecialchars($row['nipd'])?> · <?=htmlspecialchars($row['kelas_nama'])?> · <?=htmlspecialchars($row['jenis_kelamin'])?> · <span class="inline-flex px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold"><?=htmlspecialchars($row['status'])?></span><?php if($isSiswa): ?> <span class="inline-flex px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[11px]">Read-only</span><?php endif; ?></div>
      <span class="inline-flex mt-2 px-2 py-1 rounded-badge text-xs font-semibold <?=$bc?>"><?=$badge?> · <?=$poin?> poin (TA berjalan)</span>
      <span class="text-[11px] text-[#94A3B8] ml-2">TA <?=htmlspecialchars($ta)?></span>
    </div>
    <?php if($canMutateSiswaDetail): ?><a href="/kesiswaanv2/siswa/edit.php?id=<?=$id?>" class="h-9 inline-flex justify-center items-center gap-1.5 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-sm font-medium px-4 shrink-0"><i data-lucide="pencil" class="w-4 h-4"></i> Edit Siswa</a><?php endif; ?>
  </div>
  <div class="mt-5 border-t border-[#E2E8F0] dark:border-[#334155] pt-4">
    <div class="flex flex-wrap gap-1.5 text-xs">
      <button type="button" onclick="switchBio('siswa')" id="tab-siswa" class="px-3.5 py-1.5 rounded-full bg-[#0F172A] text-white font-semibold">Biodata Siswa</button>
      <button type="button" onclick="switchBio('kes')" id="tab-kes" class="px-3.5 py-1.5 rounded-full bg-[#F1F5F9] dark:bg-white/10 text-[#475569] dark:text-[#94A3B8] font-medium">Keadaan Jasmani dan Kesehatan</button>
      <button type="button" onclick="switchBio('ayah')" id="tab-ayah" class="px-3.5 py-1.5 rounded-full bg-[#F1F5F9] dark:bg-white/10 text-[#475569] dark:text-[#94A3B8] font-medium">Ayah</button>
      <button type="button" onclick="switchBio('ibu')" id="tab-ibu" class="px-3.5 py-1.5 rounded-full bg-[#F1F5F9] dark:bg-white/10 text-[#475569] dark:text-[#94A3B8] font-medium">Ibu</button>
      <button type="button" onclick="switchBio('wali')" id="tab-wali" class="px-3.5 py-1.5 rounded-full bg-[#F1F5F9] dark:bg-white/10 text-[#475569] dark:text-[#94A3B8] font-medium">Wali</button>
    </div>
    <div id="bio-siswa" class="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-0">
      <?php $fields=[['NIPD',$row['nipd']],['NISN',f($pd['nisn']??null)],['NIK',f($pd['nik']??null)],['No KK',f($pd['no_kk']??null)],['Jenis Kelamin',$row['jenis_kelamin']],['Tempat, Tgl Lahir',($row['tempat_lahir']??'—').', '.f($row['tanggal_lahir']??($pd['tanggal_lahir']??null))],['Agama',f($pd['agama']??null)],['Status',f($row['status'])],['Jenis Tinggal',f($pd['jenis_tinggal']??null)],['Transportasi',f($pd['alat_transportasi']??null)],['Telepon',f($pd['telepon']??null)],['HP',f($pd['hp']??$row['hp_ortu'])],['Email',f($pd['email']??null)],['Jarak ke Sekolah',f($pd['jarak_ke_sekolah_km']??null)],['Anak ke',f($pd['anak_ke']??null)],['Jml Saudara',f($pd['jml_saudara_kandung']??null)],['Sekolah Asal',f($pd['sekolah_asal']??null)],['Kebutuhan Khusus',f($pd['kebutuhan_khusus']??null)],['Alamat Lengkap',$alamatTampil],['RT/RW',f(($pd['rt']??null)?'RT '.f($pd['rt']).' / RW '.f($pd['rw']):null)],['Dusun/Desa',f($pd['dusun']??null)],['Kelurahan/Desa',f($pd['kelurahan']??null)],['Kecamatan',f($pd['kecamatan']??null)],['Kode Pos',f($pd['kode_pos']??null)]];
      foreach($fields as [$k,$v]): ?><div class="flex justify-between gap-3 py-2 border-b border-dashed border-[#E2E8F0] dark:border-[#334155]/60 last:border-0"><dt class="text-[#475569] dark:text-[#94A3B8] text-xs shrink-0"><?=htmlspecialchars($k)?></dt><dd class="font-medium text-xs text-right break-words max-w-[65%]" title="<?=htmlspecialchars($v)?>"><?=nl2br(htmlspecialchars($v))?></dd></div><?php endforeach; ?>
    </div>
    <div id="bio-kes" class="hidden mt-4 space-y-4">
      <div class="grid sm:grid-cols-2 gap-4 bg-[#F8FAFC] dark:bg-white/[0.03] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
        <div class="space-y-1 text-xs"><div class="text-[#475569] dark:text-[#94A3B8]">1. Tinggi Badan</div><div class="font-semibold text-sm"><?= $tinggiEff!==''? htmlspecialchars($tinggiEff).' Cm' : '—' ?> <?php if($tinggiEff===''&&!empty($pd['tinggi_badan'])): ?><span class="font-normal text-[#94A3B8]">(Dapodik)</span><?php endif; ?></div></div>
        <div class="space-y-1 text-xs"><div class="text-[#475569] dark:text-[#94A3B8]">2. Berat Badan</div><div class="font-semibold text-sm"><?= $beratEff!==''? htmlspecialchars($beratEff).' Kg' : '—' ?></div></div>
        <div class="space-y-1 text-xs"><div class="text-[#475569] dark:text-[#94A3B8]">3. Golongan Darah</div><div class="font-semibold text-sm"><?=htmlspecialchars($kes['golongan_darah']??'—')?></div></div>
        <div class="space-y-1 text-xs"><div class="text-[#475569] dark:text-[#94A3B8]">4. Memiliki Cacat Tubuh</div><div class="font-semibold text-sm"><?=htmlspecialchars($kes['cacat_tubuh']??'Tidak')?><?php if(!empty($kes['cacat_keterangan'])): ?> — <?=htmlspecialchars($kes['cacat_keterangan'])?><?php endif; ?></div></div>
        <div class="space-y-1 text-xs sm:col-span-2"><div class="text-[#475569] dark:text-[#94A3B8]">5. Memakai Kacamata</div><div class="font-semibold text-sm"><?=htmlspecialchars($kes['pakai_kacamata']??'Tidak')?><?php if(($kes['pakai_kacamata']??'Tidak')==='Ya'): ?> <span class="font-normal">— a. Minus <?=htmlspecialchars($kes['kacamata_minus']??'—')?> · b. Silinder <?=htmlspecialchars($kes['kacamata_silinder']??'—')?></span><?php endif; ?></div></div>
      </div>
      <?php if($canEditKesDetail): ?><form method="POST" class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4 space-y-3">
        <?=csrf_field()?><input type="hidden" name="aksi" value="simpan_kesehatan">
        <h4 class="font-semibold text-xs">Edit Keadaan Jasmani dan Kesehatan</h4>
        <div class="grid sm:grid-cols-3 gap-3">
          <label class="block text-xs font-medium">Tinggi Badan (Cm)<input name="tinggi_badan" value="<?=htmlspecialchars($kes['tinggi_badan']??$pd['tinggi_badan']??'')?>" placeholder="165" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
          <label class="block text-xs font-medium">Berat Badan (Kg)<input name="berat_badan" value="<?=htmlspecialchars($kes['berat_badan']??$pd['berat_badan']??'')?>" placeholder="55" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
          <label class="block text-xs font-medium">Golongan Darah<select name="golongan_darah" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"><option value="">—</option><?php foreach(['A','B','AB','O'] as $g): ?><option <?=($kes['golongan_darah']??'')===$g?'selected':''?>><?=$g?></option><?php endforeach; ?></select></label>
          <label class="block text-xs font-medium">Cacat Tubuh<select name="cacat_tubuh" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"><option <?=($kes['cacat_tubuh']??'Tidak')==='Tidak'?'selected':''?>>Tidak</option><option <?=($kes['cacat_tubuh']??'')==='Ya'?'selected':''?>>Ya</option></select></label>
          <label class="block text-xs font-medium sm:col-span-2">Sebutkan (jika Ya)<input name="cacat_keterangan" value="<?=htmlspecialchars($kes['cacat_keterangan']??'')?>" placeholder="Keterangan cacat" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
          <label class="block text-xs font-medium">Pakai Kacamata<select name="pakai_kacamata" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"><option <?=($kes['pakai_kacamata']??'Tidak')==='Tidak'?'selected':''?>>Tidak</option><option <?=($kes['pakai_kacamata']??'')==='Ya'?'selected':''?>>Ya</option></select></label>
          <label class="block text-xs font-medium">a. Minus<input name="kacamata_minus" value="<?=htmlspecialchars($kes['kacamata_minus']??'')?>" placeholder="-2.00" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
          <label class="block text-xs font-medium">b. Silinder<input name="kacamata_silinder" value="<?=htmlspecialchars($kes['kacamata_silinder']??'')?>" placeholder="-0.50" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
        </div>
        <button class="h-9 px-4 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan Kesehatan</button>
      </form>
      <?php else: ?><div class="text-xs text-[#94A3B8] border border-dashed rounded-card p-3 text-center">Edit kesehatan terbatas untuk Admin / Guru BK / Wali Kelas</div><?php endif; ?>
      <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden">
        <div class="px-4 py-3 border-b border-[#E2E8F0] dark:border-[#334155]"><h4 class="font-semibold text-xs">6. Pernah Mengalami Sakit Keras</h4><p class="text-[11px] text-[#94A3B8]">Tabel: No · Jenis Penyakit · Pada Usia · Opname/Tidak · Rumah Sakit</p></div>
        <?php if(!$sakit): ?><div class="px-4 py-6 text-center text-xs text-[#94A3B8]">Belum ada riwayat — tambah di bawah</div><?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-[#475569] dark:text-[#94A3B8] uppercase"><tr><th class="text-left px-3 py-2">No</th><th class="text-left px-3 py-2">Jenis Penyakit</th><th class="text-left px-3 py-2">Pada Usia</th><th class="text-left px-3 py-2">Opname</th><th class="text-left px-3 py-2">Rumah Sakit</th><th class="text-right px-3 py-2">Aksi</th></tr></thead>
            <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
              <?php foreach($sakit as $i=>$s): ?><tr><td class="px-3 py-2"><?=$i+1?></td><td class="px-3 py-2 font-medium"><?=htmlspecialchars($s['jenis_penyakit'])?></td><td class="px-3 py-2"><?=htmlspecialchars($s['usia_saat_sakit']??'—')?></td><td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $s['opname']==='Ya'?'bg-[#FEE2E2] text-[#DC2626]':'bg-slate-100 text-slate-600' ?>"><?=htmlspecialchars($s['opname'])?></span></td><td class="px-3 py-2"><?=htmlspecialchars($s['rumah_sakit']??'—')?></td><td class="px-3 py-2 text-right"><?php if($canEditKesDetail): ?><form method="POST" onsubmit="return confirm('Hapus riwayat ini?')" class="inline"><?=csrf_field()?><input type="hidden" name="aksi" value="hapus_sakit"><input type="hidden" name="sakit_id" value="<?=$s['id']?>"><button class="px-2 py-1 rounded-input border border-red-200 text-[#DC2626] text-[11px]">Hapus</button></form><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
        <?php if($canEditKesDetail): ?><form method="POST" class="px-4 py-3 border-t border-[#E2E8F0] dark:border-[#334155] grid sm:grid-cols-5 gap-2 items-end">
          <?=csrf_field()?><input type="hidden" name="aksi" value="tambah_sakit">
          <label class="block text-xs font-medium">Jenis Penyakit <span class="text-red-500">*</span><input name="jenis_penyakit" required placeholder="DBD, Tifus..." class="mt-1 w-full h-8 px-2 rounded-input border bg-white dark:bg-[#0F172A] text-xs"></label>
          <label class="block text-xs font-medium">Pada Usia<input name="usia_saat_sakit" placeholder="12 tahun" class="mt-1 w-full h-8 px-2 rounded-input border bg-white dark:bg-[#0F172A] text-xs"></label>
          <label class="block text-xs font-medium">Opname<select name="opname" class="mt-1 w-full h-8 px-2 rounded-input border bg-white dark:bg-[#0F172A] text-xs"><option>Tidak</option><option>Ya</option></select></label>
          <label class="block text-xs font-medium">Rumah Sakit<input name="rumah_sakit" placeholder="RSUD ..." class="mt-1 w-full h-8 px-2 rounded-input border bg-white dark:bg-[#0F172A] text-xs"></label>
          <button class="h-8 px-3 rounded-input bg-[#2563EB] text-white text-xs font-semibold">Tambah</button>
        </form><?php endif; ?>
      </div>
    </div>
    <div id="bio-ayah" class="hidden mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-0">
      <?php $ayah=[['Nama',f($pd['ayah_nama']??$row['nama_ortu']??null)],['NIK',f($pd['ayah_nik']??null)],['Tahun Lahir',f($pd['ayah_tahun_lahir']??null)],['Pendidikan',f($pd['ayah_pendidikan']??null)],['Pekerjaan',f($pd['ayah_pekerjaan']??null)],['Penghasilan',f($pd['ayah_penghasilan']??null)]];
      foreach($ayah as [$k,$v]): ?><div class="flex justify-between gap-3 py-2 border-b border-dashed border-[#E2E8F0] dark:border-[#334155]/60 last:border-0"><dt class="text-[#475569] dark:text-[#94A3B8] text-xs"><?=htmlspecialchars($k)?></dt><dd class="font-medium text-xs text-right break-words max-w-[65%]"><?=htmlspecialchars($v)?></dd></div><?php endforeach; ?>
    </div>
    <div id="bio-ibu" class="hidden mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-0">
      <?php $ibu=[['Nama',f($pd['ibu_nama']??null)],['NIK',f($pd['ibu_nik']??null)],['Tahun Lahir',f($pd['ibu_tahun_lahir']??null)],['Pendidikan',f($pd['ibu_pendidikan']??null)],['Pekerjaan',f($pd['ibu_pekerjaan']??null)],['Penghasilan',f($pd['ibu_penghasilan']??null)]];
      foreach($ibu as [$k,$v]): ?><div class="flex justify-between gap-3 py-2 border-b border-dashed border-[#E2E8F0] dark:border-[#334155]/60 last:border-0"><dt class="text-[#475569] dark:text-[#94A3B8] text-xs"><?=htmlspecialchars($k)?></dt><dd class="font-medium text-xs text-right break-words max-w-[65%]"><?=htmlspecialchars($v)?></dd></div><?php endforeach; ?>
    </div>
    <div id="bio-wali" class="hidden mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-0">
      <?php $wali=[['Nama',f($pd['wali_nama']??null)],['NIK',f($pd['wali_nik']??null)],['Tahun Lahir',f($pd['wali_tahun_lahir']??null)],['Pendidikan',f($pd['wali_pendidikan']??null)],['Pekerjaan',f($pd['wali_pekerjaan']??null)],['Penghasilan',f($pd['wali_penghasilan']??null)]];
      foreach($wali as [$k,$v]): ?><div class="flex justify-between gap-3 py-2 border-b border-dashed border-[#E2E8F0] dark:border-[#334155]/60 last:border-0"><dt class="text-[#475569] dark:text-[#94A3B8] text-xs"><?=htmlspecialchars($k)?></dt><dd class="font-medium text-xs text-right break-words max-w-[65%]"><?=htmlspecialchars($v)?></dd></div><?php endforeach; ?>
    </div>
    <script>function switchBio(k){ ['siswa','kes','ayah','ibu','wali'].forEach(x=>{ const el=document.getElementById('bio-'+x); if(!el) return; const is= x===k; el.classList.toggle('hidden', !is); if(x==='siswa'||x==='ayah'||x==='ibu'||x==='wali') el.classList.toggle('grid', is); const t=document.getElementById('tab-'+x); if(t){ t.className = is ? 'px-3.5 py-1.5 rounded-full bg-[#0F172A] text-white font-semibold' : 'px-3.5 py-1.5 rounded-full bg-[#F1F5F9] dark:bg-white/10 text-[#475569] dark:text-[#94A3B8] font-medium'; } }); }</script>
  </div>
</div>
<div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-4">
  <div class="px-4 py-3 border-b border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between">
    <h3 class="font-semibold text-sm">Riwayat Pelanggaran</h3>
    <span class="text-xs text-[#475569] dark:text-[#94A3B8]">Total arsip: <?=count($riwayat)?></span>
  </div>
  <?php if(!$riwayat): ?><div class="px-4 py-10 text-center text-sm text-[#94A3B8]">Belum ada pelanggaran</div><?php else: ?>
  <div class="hidden md:block overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="text-left px-4 py-2">Tanggal</th><th class="text-left px-4 py-2">Pelanggaran</th><th class="text-left px-4 py-2">Poin</th><th class="text-left px-4 py-2">Pelapor</th><th class="text-left px-4 py-2">TA</th><?php if($canEditPoinDetail): ?><th class="text-left px-4 py-2">Aksi</th><?php endif; ?></tr></thead>
      <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
        <?php foreach($riwayat as $r): ?><tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]"><td class="px-4 py-2.5 text-xs"><?=htmlspecialchars($r['tanggal'])?></td><td class="px-4 py-2.5"><?=htmlspecialchars($r['pelanggaran'])?><div class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['keterangan']??'-')?></div></td><td class="px-4 py-2.5 font-bold"><?=htmlspecialchars($r['poin'])?></td><td class="px-4 py-2.5 text-xs"><?=htmlspecialchars($r['pelapor'])?></td><td class="px-4 py-2.5 text-xs"><?=htmlspecialchars($r['ta'])?></td><?php if($canEditPoinDetail): ?><td class="px-4 py-2.5"><button type="button" onclick='openEditPoin(<?=json_encode(["id"=>$r["id"],"poin"=>$r["poin"],"ket"=>$r["keterangan"]], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)?>)' class="px-2 py-1 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium inline-flex items-center gap-1"><i data-lucide="pencil" class="w-3 h-3"></i> Edit</button></td><?php endif; ?></tr><?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="md:hidden divide-y divide-[#E2E8F0] dark:divide-[#334155]">
    <?php foreach($riwayat as $r): ?><div class="p-4"><div class="flex justify-between"><span class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['tanggal'])?> · <?=htmlspecialchars($r['ta'])?></span><span class="font-bold text-sm">+<?=htmlspecialchars($r['poin'])?></span></div><div class="font-medium text-sm mt-1"><?=htmlspecialchars($r['pelanggaran'])?></div><div class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['keterangan']??'-')?> · <?=htmlspecialchars($r['pelapor'])?></div><?php if($canEditPoinDetail): ?><button type="button" onclick='openEditPoin(<?=json_encode(["id"=>$r["id"],"poin"=>$r["poin"],"ket"=>$r["keterangan"]], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)?>)' class="mt-2 w-full h-8 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium">Edit Poin</button><?php endif; ?></div><?php endforeach; ?>
  </div>
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
<?php if(role_can('view_bk')): ?><div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-4">
  <div class="px-4 py-3 border-b border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between">
    <h3 class="font-semibold text-sm flex items-center gap-2"><i data-lucide="heart-handshake" class="w-4 h-4 text-[#2563EB]"></i> Riwayat Bimbingan Konseling</h3>
    <div class="flex items-center gap-2">
      <span class="text-xs text-[#475569] dark:text-[#94A3B8]">Total: <?=count($bk)?></span>
      <a href="/kesiswaanv2/bk/index.php?siswa_id=<?=$id?>" class="h-7 px-3 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-xs font-semibold inline-flex items-center gap-1"><i data-lucide="plus" class="w-3.5 h-3.5"></i> Tambah via Data BK</a>
    </div>
  </div>
  <?php if(!$bk): ?><div class="px-4 py-8 text-center text-sm text-[#94A3B8]">Belum ada sesi BK untuk siswa ini — tambah via <a href="/kesiswaanv2/bk/index.php?siswa_id=<?=$id?>" class="text-[#2563EB] underline">Data Bimbingan Konseling</a></div><?php else: ?>
  <div class="hidden md:block overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="px-3 py-2 text-left">No</th><th class="px-3 py-2 text-left">Tanggal</th><th class="px-3 py-2 text-left">Permasalahan</th><th class="px-3 py-2 text-left">Tindakan / Followup</th><th class="px-3 py-2 text-left">Konselor</th></tr></thead>
      <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
        <?php foreach($bk as $i=>$b): ?>
        <tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
          <td class="px-3 py-2.5 text-xs font-semibold"><?=$i+1?></td>
          <td class="px-3 py-2.5 text-xs"><?=htmlspecialchars($b['tanggal'])?></td>
          <td class="px-3 py-2.5 max-w-[320px]"><?=nl2br(htmlspecialchars($b['permasalahan']))?></td>
          <td class="px-3 py-2.5 max-w-[280px] text-[#475569] dark:text-[#94A3B8]"><?=nl2br(htmlspecialchars($b['tindakan']??'—'))?></td>
          <td class="px-3 py-2.5 text-xs"><?=htmlspecialchars($b['konselor_nama'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="md:hidden divide-y divide-[#E2E8F0] dark:divide-[#334155]">
    <?php foreach($bk as $i=>$b): ?><div class="p-4"><div class="flex justify-between text-xs text-[#94A3B8]"><span>#<?=$i+1?> · <?=htmlspecialchars($b['tanggal'])?></span><span><?=htmlspecialchars($b['konselor_nama'])?></span></div><div class="text-sm font-medium mt-1"><?=nl2br(htmlspecialchars($b['permasalahan']))?></div><div class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1">Tindakan: <?=nl2br(htmlspecialchars($b['tindakan']??'—'))?></div></div><?php endforeach; ?>
  </div>
  <?php endif; ?>
</div><?php endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
