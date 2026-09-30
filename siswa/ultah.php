<?php
$active='ultah'; $title='Ulang Tahun Siswa';
require __DIR__.'/../config/db.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_ultah');
require __DIR__.'/../includes/header.php';
$filter=$_GET['filter']??'today'; if(!in_array($filter,['yesterday','today','tomorrow'],true)) $filter='today';
$bulanMap=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$todayLabel=date('j').' '.$bulanMap[(int)date('n')].' '.date('Y');
$dtY=new DateTime('-1 day'); $dtT=new DateTime('+1 day');
$yM=(int)$dtY->format('n'); $yD=(int)$dtY->format('j'); $tM=(int)$dtT->format('n'); $tD=(int)$dtT->format('j');
$yLabel=$dtY->format('j').' '.$bulanMap[$yM]; $tLabel=$dtT->format('j').' '.$bulanMap[$tM];
$cntY=0;$cntT=0;$cntB=0;
try{ $st=$pdo->prepare("SELECT COUNT(*) FROM siswa WHERE deleted_at IS NULL AND status='Aktif' AND tanggal_lahir IS NOT NULL AND MONTH(tanggal_lahir)=? AND DAY(tanggal_lahir)=?"); $st->execute([$yM,$yD]); $cntY=(int)$st->fetchColumn(); }catch(Throwable $e){}
try{ $st=$pdo->prepare("SELECT COUNT(*) FROM siswa WHERE deleted_at IS NULL AND status='Aktif' AND tanggal_lahir IS NOT NULL AND MONTH(tanggal_lahir)=? AND DAY(tanggal_lahir)=?"); $st->execute([(int)date('n'),(int)date('j')]); $cntT=(int)$st->fetchColumn(); }catch(Throwable $e){}
try{ $st=$pdo->prepare("SELECT COUNT(*) FROM siswa WHERE deleted_at IS NULL AND status='Aktif' AND tanggal_lahir IS NOT NULL AND MONTH(tanggal_lahir)=? AND DAY(tanggal_lahir)=?"); $st->execute([$tM,$tD]); $cntB=(int)$st->fetchColumn(); }catch(Throwable $e){}
$rows=[]; $titleFilter='Hari Ini';
try{
  if($filter==='yesterday'){
    $titleFilter='Kemarin — '.$yLabel;
    $st=$pdo->prepare("SELECT s.id,s.nama,s.nipd,s.tanggal_lahir,s.foto,COALESCE(k.nama_kelas,'—') kelas,TIMESTAMPDIFF(YEAR,s.tanggal_lahir,CURDATE()) umur FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.deleted_at IS NULL AND s.status='Aktif' AND s.tanggal_lahir IS NOT NULL AND MONTH(s.tanggal_lahir)=? AND DAY(s.tanggal_lahir)=? ORDER BY s.nama ASC");
    $st->execute([$yM,$yD]); $rows=$st->fetchAll();
  } elseif($filter==='tomorrow'){
    $titleFilter='Besok — '.$tLabel;
    $st=$pdo->prepare("SELECT s.id,s.nama,s.nipd,s.tanggal_lahir,s.foto,COALESCE(k.nama_kelas,'—') kelas,TIMESTAMPDIFF(YEAR,s.tanggal_lahir,CURDATE()) umur FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.deleted_at IS NULL AND s.status='Aktif' AND s.tanggal_lahir IS NOT NULL AND MONTH(s.tanggal_lahir)=? AND DAY(s.tanggal_lahir)=? ORDER BY s.nama ASC");
    $st->execute([$tM,$tD]); $rows=$st->fetchAll();
  } else {
    $filter='today'; $titleFilter='Hari Ini — '.date('j').' '.$bulanMap[(int)date('n')];
    $st=$pdo->prepare("SELECT s.id,s.nama,s.nipd,s.tanggal_lahir,s.foto,COALESCE(k.nama_kelas,'—') kelas,TIMESTAMPDIFF(YEAR,s.tanggal_lahir,CURDATE()) umur FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.deleted_at IS NULL AND s.status='Aktif' AND s.tanggal_lahir IS NOT NULL AND MONTH(s.tanggal_lahir)=? AND DAY(s.tanggal_lahir)=? ORDER BY s.nama ASC");
    $st->execute([(int)date('n'),(int)date('j')]); $rows=$st->fetchAll();
  }
}catch(Throwable $e){}
?>
<div class="flex flex-wrap items-center gap-3 mb-4">
  <div class="inline-flex bg-[#F1F5F9] dark:bg-white/[0.06] rounded-full p-1 gap-1 border border-[#E2E8F0] dark:border-white/10">
    <a href="?filter=yesterday" class="inline-flex items-center gap-1.5 h-7 px-3.5 rounded-full text-xs font-semibold transition <?= $filter==='yesterday'?'bg-white dark:bg-[#1E293B] text-[#0F172A] dark:text-white shadow-sm border border-[#E2E8F0] dark:border-white/10':'text-[#475569] dark:text-[#94A3B8] hover:text-[#0F172A] dark:hover:text-white' ?>">Kemarin <span class="text-[10px] opacity-60"><?=htmlspecialchars($yLabel)?></span> <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full text-[11px] font-bold <?= $filter==='yesterday'?'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300':'bg-white dark:bg-white/10 text-[#475569] dark:text-[#94A3B8] border border-[#E2E8F0] dark:border-white/10' ?>"><?=$cntY?></span></a>
    <a href="?filter=today" class="inline-flex items-center gap-1.5 h-7 px-3.5 rounded-full text-xs font-semibold transition <?= $filter==='today'?'bg-white dark:bg-[#1E293B] text-[#0F172A] dark:text-white shadow-sm border border-[#E2E8F0] dark:border-white/10':'text-[#475569] dark:text-[#94A3B8] hover:text-[#0F172A] dark:hover:text-white' ?>">Hari Ini <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full text-[11px] font-bold <?= $filter==='today'?'bg-pink-100 text-pink-600 dark:bg-pink-500/20 dark:text-pink-400':'bg-white dark:bg-white/10 text-[#475569] dark:text-[#94A3B8] border border-[#E2E8F0] dark:border-white/10' ?>"><?=$cntT?></span></a>
    <a href="?filter=tomorrow" class="inline-flex items-center gap-1.5 h-7 px-3.5 rounded-full text-xs font-semibold transition <?= $filter==='tomorrow'?'bg-white dark:bg-[#1E293B] text-[#0F172A] dark:text-white shadow-sm border border-[#E2E8F0] dark:border-white/10':'text-[#475569] dark:text-[#94A3B8] hover:text-[#0F172A] dark:hover:text-white' ?>">Besok <span class="text-[10px] opacity-60"><?=htmlspecialchars($tLabel)?></span> <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full text-[11px] font-bold <?= $filter==='tomorrow'?'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300':'bg-white dark:bg-white/10 text-[#475569] dark:text-[#94A3B8] border border-[#E2E8F0] dark:border-white/10' ?>"><?=$cntB?></span></a>
  </div>
  <span class="ml-auto text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($todayLabel)?> · <?=$titleFilter?> · <?=count($rows)?> siswa</span>
</div>
<div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-card dark:shadow-none overflow-hidden">
  <div class="px-4 py-3 border-b border-[#E2E8F0] dark:border-[#334155] flex items-center gap-2">
    <i data-lucide="cake" class="w-4 h-4 text-pink-500"></i><h3 class="font-semibold text-sm">Ulang Tahun — <?=htmlspecialchars($titleFilter)?></h3><span class="ml-auto inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full bg-pink-100 dark:bg-pink-500/20 text-pink-600 dark:text-pink-400 text-xs font-bold"><?=count($rows)?></span>
  </div>
  <?php if(empty($rows)): ?>
    <div class="px-4 py-10 text-center text-sm text-[#94A3B8] flex flex-col items-center gap-2"><i data-lucide="calendar-x" class="w-8 h-8 opacity-50"></i> Tidak ada siswa yang berulang tahun pada periode ini</div>
  <?php else: ?>
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase tracking-wide text-[#475569] dark:text-[#94A3B8]"><tr><th class="text-left px-4 py-2.5 font-semibold">Foto</th><th class="text-left px-4 py-2.5 font-semibold">Nama</th><th class="text-left px-4 py-2.5 font-semibold">NIPD</th><th class="text-left px-4 py-2.5 font-semibold">Kelas</th><th class="text-left px-4 py-2.5 font-semibold">Tanggal Lahir</th><th class="text-left px-4 py-2.5 font-semibold">Usia</th></tr></thead>
        <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
          <?php foreach($rows as $r): $f=$r['foto']?(str_starts_with($r['foto'],'http')?$r['foto']:'/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($r['foto'])):'https://i.pravatar.cc/100?u='.urlencode($r['nipd']); $isToday=(int)date('md',strtotime($r['tanggal_lahir']))===(int)date('md'); $tglLahir=date('d M Y',strtotime($r['tanggal_lahir'])); ?>
          <tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03] <?= $isToday?'bg-pink-50/60 dark:bg-pink-500/5':'' ?>">
            <td class="px-4 py-2.5"><img src="<?=$f?>" class="w-8 h-8 rounded-full object-cover"></td>
            <td class="px-4 py-2.5"><a href="/kesiswaanv2/siswa/detail.php?id=<?=$r['id']?>" class="font-medium hover:underline flex items-center gap-1.5"><?=htmlspecialchars($r['nama'])?> <?php if($isToday): ?><span class="inline-flex px-1.5 py-0.5 rounded-full bg-pink-100 dark:bg-pink-500/20 text-pink-600 dark:text-pink-400 text-[11px] font-semibold">🎂 Hari ini</span><?php endif; ?></a></td>
            <td class="px-4 py-2.5 text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['nipd'])?></td>
            <td class="px-4 py-2.5"><?=htmlspecialchars($r['kelas'])?></td>
            <td class="px-4 py-2.5 text-xs"><?=htmlspecialchars($tglLahir)?></td>
            <td class="px-4 py-2.5 font-bold text-pink-600 dark:text-pink-400"><?=$r['umur']?> th</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="md:hidden divide-y divide-[#E2E8F0] dark:divide-[#334155]">
      <?php foreach($rows as $r): $f=$r['foto']?(str_starts_with($r['foto'],'http')?$r['foto']:'/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($r['foto'])):'https://i.pravatar.cc/100?u='.urlencode($r['nipd']); $isToday=(int)date('md',strtotime($r['tanggal_lahir']))===(int)date('md'); ?>
      <a href="/kesiswaanv2/siswa/detail.php?id=<?=$r['id']?>" class="flex items-center gap-3 p-4 <?= $isToday?'bg-pink-50/50 dark:bg-pink-500/5':'' ?>">
        <img src="<?=$f?>" class="w-10 h-10 rounded-full object-cover">
        <div class="min-w-0 flex-1"><div class="text-sm font-semibold truncate flex items-center gap-1.5"><?=htmlspecialchars($r['nama'])?> <?php if($isToday): ?><span class="text-[11px]">🎂</span><?php endif; ?></div><div class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['nipd'])?> · <?=htmlspecialchars($r['kelas'])?> · <?=htmlspecialchars(date('d M Y',strtotime($r['tanggal_lahir'])))?></div></div>
        <div class="text-sm font-bold text-pink-600 shrink-0"><?=$r['umur']?> th</div>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
