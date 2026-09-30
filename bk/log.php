<?php
$active='bk_log'; $title='Log Bimbingan Konseling';
require __DIR__.'/../config/db.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_bk_log');
require __DIR__.'/../includes/header.php';
$q=trim($_GET['q']??''); $kelas=trim($_GET['kelas']??''); $from=trim($_GET['from']??''); $to=trim($_GET['to']??''); $page=max(1,(int)($_GET['page']??1)); $size=(int)($_GET['size']??25); if(!in_array($size,[10,25,50,100],true)) $size=25;
$kelasOpts=[]; try{ foreach($pdo->query("SELECT nama_kelas FROM kelas WHERE deleted_at IS NULL ORDER BY nama_kelas") as $r) $kelasOpts[]=$r['nama_kelas']; }catch(Throwable $e){}
$where="WHERE 1=1"; $args=[];
if($q!==''){ $where.=" AND (s.nama LIKE ? OR s.nipd LIKE ? OR bk.permasalahan LIKE ? OR bk.tindakan LIKE ?)"; $a="%$q%"; $args[]=$a;$args[]=$a;$args[]=$a;$args[]=$a; }
if($kelas!==''){ $where.=" AND k.nama_kelas=?"; $args[]=$kelas; }
if($from!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){ $where.=" AND bk.tanggal>=?"; $args[]=$from; }
if($to!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){ $where.=" AND bk.tanggal<=?"; $args[]=$to; }
$total=0; try{ $st=$pdo->prepare("SELECT COUNT(*) FROM bimbingan_konseling bk JOIN siswa s ON s.id=bk.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id $where"); $st->execute($args); $total=(int)$st->fetchColumn(); }catch(Throwable $e){}
$totalPages=max(1,(int)ceil($total/$size)); if($page>$totalPages) $page=$totalPages; $off=($page-1)*$size;
$rows=[]; try{ $st=$pdo->prepare("SELECT bk.id,bk.siswa_id,bk.tanggal,bk.permasalahan,bk.tindakan,COALESCE(u.nama,'—') konselor, s.nama siswa_nama,s.nipd,COALESCE(k.nama_kelas,'—') kelas FROM bimbingan_konseling bk JOIN siswa s ON s.id=bk.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN users u ON u.id=bk.konselor_id $where ORDER BY bk.tanggal DESC,bk.id DESC LIMIT ? OFFSET ?"); $st->execute(array_merge($args,[$size,$off])); $rows=$st->fetchAll(); }catch(Throwable $e){}
$qsBase=array_filter(['q'=>$q,'kelas'=>$kelas,'from'=>$from,'to'=>$to,'size'=>$size]);
?>
<form method="GET" class="flex flex-wrap gap-2 items-end bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-3">
  <label class="flex-1 min-w-[180px]"><span class="text-[11px] font-semibold text-[#475569] dark:text-[#94A3B8]">Cari</span><input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Nama / NIPD / permasalahan / tindakan" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
  <label><span class="text-[11px] font-semibold text-[#475569]">Kelas</span><select name="kelas" class="mt-1 h-9 rounded-input border bg-white dark:bg-[#0F172A] text-sm px-2"><option value="">Semua</option><?php foreach($kelasOpts as $k): ?><option <?= $kelas===$k?'selected':'' ?>><?=htmlspecialchars($k)?></option><?php endforeach; ?></select></label>
  <label><span class="text-[11px] font-semibold">Dari</span><input type="date" name="from" value="<?=htmlspecialchars($from)?>" class="mt-1 h-9 px-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
  <label><span class="text-[11px] font-semibold">Sampai</span><input type="date" name="to" value="<?=htmlspecialchars($to)?>" class="mt-1 h-9 px-2 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
  <select name="size" class="h-9 rounded-input border bg-white dark:bg-[#0F172A] text-sm px-2"><option value="25" <?= $size===25?'selected':'' ?>>25</option><option value="50" <?= $size===50?'selected':'' ?>>50</option><option value="100" <?= $size===100?'selected':'' ?>>100</option></select>
  <button class="h-9 px-4 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Filter</button>
  <?php if($q||$kelas||$from||$to): ?><a href="log.php" class="h-9 px-4 rounded-input border bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Reset</a><?php endif; ?>
  <a href="index.php" class="h-9 px-4 rounded-input border bg-white dark:bg-[#0F172A] text-sm inline-flex items-center gap-1"><i data-lucide="heart-handshake" class="w-4 h-4"></i> Kelola Data BK</a>
</form>
<div class="mt-3 text-xs text-[#475569] dark:text-[#94A3B8]">Total <?=number_format($total)?> sesi BK · Hal <?=$page?>/<?=$totalPages?> — read-only log</div>
<div class="hidden md:block bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-2">
<table class="w-full text-sm">
<thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] dark:text-[#94A3B8]"><tr><th class="px-3 py-2.5 text-left">No</th><th class="px-3 py-2.5 text-left">Tanggal</th><th class="px-3 py-2.5 text-left">Siswa</th><th class="px-3 py-2.5 text-left">Kelas</th><th class="px-3 py-2.5 text-left">Permasalahan</th><th class="px-3 py-2.5 text-left">Tindakan / Followup</th><th class="px-3 py-2.5 text-left">Konselor</th></tr></thead>
<tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
<?php if(!$rows): ?><tr><td colspan="7" class="px-4 py-10 text-center text-sm text-[#94A3B8]">Belum ada sesi BK</td></tr><?php endif; ?>
<?php foreach($rows as $i=>$r): $no=$off+$i+1; ?>
<tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
  <td class="px-3 py-2.5 text-xs font-semibold"><?=$no?></td>
  <td class="px-3 py-2.5 text-xs"><?=htmlspecialchars($r['tanggal'])?></td>
  <td class="px-3 py-2.5"><a href="/kesiswaanv2/siswa/detail.php?id=<?= (int)$r['siswa_id'] ?>" class="hover:underline font-medium"><?=htmlspecialchars($r['siswa_nama'])?></a><div class="text-xs text-[#94A3B8]"><?=htmlspecialchars($r['nipd'])?></div></td>
  <td class="px-3 py-2.5"><?=htmlspecialchars($r['kelas'])?></td>
  <td class="px-3 py-2.5 max-w-[280px]"><div class="line-clamp-2" title="<?=htmlspecialchars($r['permasalahan'])?>"><?=nl2br(htmlspecialchars($r['permasalahan']))?></div></td>
  <td class="px-3 py-2.5 max-w-[240px]"><div class="line-clamp-2 text-[#475569] dark:text-[#94A3B8]" title="<?=htmlspecialchars($r['tindakan']??'')?>"><?=nl2br(htmlspecialchars($r['tindakan']??'—'))?></div></td>
  <td class="px-3 py-2.5 text-xs"><?=htmlspecialchars($r['konselor'])?></td>
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
<?php foreach($rows as $i=>$r): $no=$off+$i+1; ?>
<div class="bg-white dark:bg-[#1E293B] border rounded-card p-3">
  <div class="flex justify-between text-xs text-[#94A3B8]"><span>#<?=$no?> · <?=htmlspecialchars($r['tanggal'])?></span><span><?=htmlspecialchars($r['kelas'])?></span></div>
  <div class="font-semibold text-sm mt-1"><?=htmlspecialchars($r['siswa_nama'])?> <span class="font-normal text-xs text-[#94A3B8]"><?=htmlspecialchars($r['nipd'])?></span></div>
  <div class="text-xs mt-2"><span class="font-semibold">Masalah:</span> <?=htmlspecialchars($r['permasalahan'])?></div>
  <div class="text-xs mt-1"><span class="font-semibold">Tindakan:</span> <?=htmlspecialchars($r['tindakan']??'—')?></div>
  <div class="text-[11px] text-[#94A3B8] mt-1">Konselor: <?=htmlspecialchars($r['konselor'])?></div>
</div>
<?php endforeach; ?>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
