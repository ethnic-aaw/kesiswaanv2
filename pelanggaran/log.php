<?php
$active='pelanggaran_log'; $title='Log Catatan Pelanggaran';
require __DIR__.'/../config/db.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_log_pelanggaran');
require __DIR__.'/../includes/header.php';
$q=trim($_GET['q']??''); $kelas=trim($_GET['kelas']??''); $from=trim($_GET['from']??''); $to=trim($_GET['to']??''); $page=max(1,(int)($_GET['page']??1)); $size=(int)($_GET['size']??25); if(!in_array($size,[10,25,50,100],true)) $size=25;
$kelasOpts=[]; try{ foreach($pdo->query("SELECT nama_kelas FROM kelas WHERE deleted_at IS NULL ORDER BY nama_kelas") as $r) $kelasOpts[]=$r['nama_kelas']; }catch(Throwable $e){}
$isWaliLog=(current_user()['role']??'')==='Wali Kelas'; $waliIdsLog=$isWaliLog? wali_ampu_ids($pdo,(int)($_SESSION['user']['id']??0)) : [];
if($isWaliLog) $kelasOpts = wali_ampu_names($pdo,(int)($_SESSION['user']['id']??0));
$where="WHERE 1=1"; $args=[];
if($isWaliLog){
  if(empty($waliIdsLog)) $where.=" AND 1=0";
  else $where.=" AND s.kelas_id IN (".implode(',',array_map('intval',$waliIdsLog)).")";
}
if($q!==''){ $where.=" AND (s.nama LIKE ? OR s.nipd LIKE ? OR jp.nama LIKE ? OR jp.kode LIKE ?)"; $a="%$q%"; $args[]=$a;$args[]=$a;$args[]=$a;$args[]=$a; }
if($kelas!==''){
  if($isWaliLog && !in_array($kelas,$kelasOpts,true)) $where.=" AND 1=0";
  else { $where.=" AND k.nama_kelas=?"; $args[]=$kelas; }
}
if($from!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){ $where.=" AND ps.tanggal>=?"; $args[]=$from; }
if($to!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){ $where.=" AND ps.tanggal<=?"; $args[]=$to; }
$total=0; try{ $st=$pdo->prepare("SELECT COUNT(*) FROM pelanggaran_siswa ps LEFT JOIN siswa s ON s.id=ps.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id $where"); $st->execute($args); $total=(int)$st->fetchColumn(); }catch(Throwable $e){}
$totalPages=max(1,(int)ceil($total/$size)); if($page>$totalPages) $page=$totalPages; $off=($page-1)*$size;
$rows=[]; try{ $st=$pdo->prepare("SELECT ps.id,ps.siswa_id,ps.tanggal,ps.poin_final,ps.tahun_ajaran,ps.lokasi,ps.keterangan,ps.tindakan,COALESCE(jp.kode,'-') kode,COALESCE(jp.nama,'-') jp_nama,s.nama siswa_nama,s.nipd,COALESCE(k.nama_kelas,'—') kelas,COALESCE(u.nama,'-') pelapor FROM pelanggaran_siswa ps LEFT JOIN siswa s ON s.id=ps.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id LEFT JOIN users u ON u.id=ps.pelapor_id $where ORDER BY ps.tanggal DESC, ps.id DESC LIMIT ? OFFSET ?"); $st->execute(array_merge($args,[$size,$off])); $rows=$st->fetchAll(); }catch(Throwable $e){}
$qsBase=array_filter(['q'=>$q,'kelas'=>$kelas,'from'=>$from,'to'=>$to,'size'=>$size]);
?>
<form method="GET" class="flex flex-wrap gap-2 items-end bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-3">
  <label class="flex-1 min-w-[180px]"><span class="text-[11px] font-semibold text-[#475569] dark:text-[#94A3B8]">Cari</span><input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Nama / NIPD / jenis / kode" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
  <label><span class="text-[11px] font-semibold text-[#475569]">Kelas</span><select name="kelas" class="mt-1 h-9 rounded-input border bg-white dark:bg-[#0F172A] text-sm px-2"><option value="">Semua</option><?php foreach($kelasOpts as $k): ?><option <?= $kelas===$k?'selected':'' ?>><?=htmlspecialchars($k)?></option><?php endforeach; ?></select></label>
  <label><span class="text-[11px] font-semibold">Dari</span><input type="date" name="from" value="<?=htmlspecialchars($from)?>" class="mt-1 h-9 px-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
  <label><span class="text-[11px] font-semibold">Sampai</span><input type="date" name="to" value="<?=htmlspecialchars($to)?>" class="mt-1 h-9 px-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
  <select name="size" class="h-9 rounded-input border bg-white dark:bg-[#0F172A] text-sm px-2"><option value="25" <?= $size===25?'selected':'' ?>>25</option><option value="50" <?= $size===50?'selected':'' ?>>50</option><option value="100" <?= $size===100?'selected':'' ?>>100</option></select>
  <button class="h-9 px-4 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Filter</button>
  <?php if($q||$kelas||$from||$to): ?><a href="log.php" class="h-9 px-4 rounded-input border bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Reset</a><?php endif; ?>
</form>
<div class="mt-3 text-xs text-[#475569] dark:text-[#94A3B8]">Total <?=number_format($total)?> catatan · Hal <?=$page?>/<?=$totalPages?></div>
<div class="hidden md:block bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-2">
<table class="w-full text-sm">
<thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="px-3 py-2.5 text-left">Tanggal</th><th class="px-3 py-2.5 text-left">Siswa</th><th class="px-3 py-2.5 text-left">Kelas</th><th class="px-3 py-2.5 text-left">Pelanggaran</th><th class="px-3 py-2.5 text-left">Poin</th><th class="px-3 py-2.5 text-left">Pelapor</th><th class="px-3 py-2.5 text-left">Lokasi</th></tr></thead>
<tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
<?php if(!$rows): ?><tr><td colspan="7" class="px-4 py-10 text-center text-sm text-[#94A3B8]">Belum ada catatan</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?>
<tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
  <td class="px-3 py-2.5 text-xs"><?=htmlspecialchars($r['tanggal'])?><div class="text-[11px] text-[#94A3B8]"><?=htmlspecialchars($r['tahun_ajaran'])?></div></td>
  <td class="px-3 py-2.5"><a href="/kesiswaanv2/siswa/detail.php?id=<?= (int)($r['siswa_id']??0) ?>" class="hover:underline font-medium"><?=htmlspecialchars($r['siswa_nama']??'-')?></a><div class="text-xs text-[#94A3B8]"><?=htmlspecialchars($r['nipd']??'')?></div></td>
  <td class="px-3 py-2.5"><?=htmlspecialchars($r['kelas'])?></td>
  <td class="px-3 py-2.5"><span class="font-mono text-xs"><?=htmlspecialchars($r['kode'])?></span> <?=htmlspecialchars($r['jp_nama'])?><div class="text-xs text-[#94A3B8] truncate max-w-[220px]"><?=htmlspecialchars($r['keterangan']??'')?></div></td>
  <td class="px-3 py-2.5"><span class="min-w-[28px] inline-flex justify-center px-2 py-1 rounded-full bg-[#0F172A] text-white text-xs font-bold"><?=$r['poin_final']?></span></td>
  <td class="px-3 py-2.5 text-xs"><?=htmlspecialchars($r['pelapor'])?></td>
  <td class="px-3 py-2.5 text-xs"><?=htmlspecialchars($r['lokasi']??'-')?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div class="px-3 py-3 flex items-center justify-between text-xs border-t">
  <span class="text-[#475569]">Menampilkan <?= $total? ($off+1).'–'.min($off+$size,$total)." dari $total" : '0' ?></span>
  <div class="flex gap-1">
    <?php $base='log.php?'.http_build_query($qsBase).($qsBase?'&':''); ?>
    <a href="<?=$base?>page=<?=max(1,$page-1)?>" class="px-3 py-1.5 rounded-input border bg-white dark:bg-[#0F172A] <?= $page<=1?'opacity-50 pointer-events-none':'' ?>">Prev</a>
    <a href="<?=$base?>page=<?=min($totalPages,$page+1)?>" class="px-3 py-1.5 rounded-input border bg-white dark:bg-[#0F172A] <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>">Next</a>
  </div>
</div>
</div>
<div class="md:hidden space-y-2 mt-2">
<?php foreach($rows as $r): ?>
<div class="bg-white dark:bg-[#1E293B] border rounded-card p-3">
  <div class="flex justify-between text-xs text-[#94A3B8]"><span><?=htmlspecialchars($r['tanggal'])?></span><span class="font-bold text-[#0F172A] dark:text-white"><?=$r['poin_final']?> poin</span></div>
  <div class="font-semibold text-sm mt-1"><?=htmlspecialchars($r['siswa_nama']??'-')?> <span class="font-normal text-xs text-[#94A3B8]"><?=htmlspecialchars($r['nipd']??'')?> · <?=htmlspecialchars($r['kelas'])?></span></div>
  <div class="text-sm"><?=htmlspecialchars($r['jp_nama'])?> <span class="text-xs font-mono text-[#94A3B8]"><?=htmlspecialchars($r['kode'])?></span></div>
  <div class="text-xs text-[#94A3B8] mt-1"><?=htmlspecialchars($r['keterangan']??'')?> <?php if($r['lokasi']): ?>· <?=htmlspecialchars($r['lokasi'])?><?php endif; ?> · Pelapor: <?=htmlspecialchars($r['pelapor'])?></div>
</div>
<?php endforeach; ?>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
