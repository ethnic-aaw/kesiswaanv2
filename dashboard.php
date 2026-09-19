<?php
$active='dashboard'; $title='Dashboard';
require __DIR__.'/includes/header.php';
require __DIR__.'/config/db.php';
$npsn='69944965'; $ta='2024/2025 Semester Gasal';
$metaSekolah='SMKN 1 LEUWIMUNDING'; $tglUnduh=null; $pengunduh=null; $emailPengunduh=null;
try{ $dm=$pdo->query("SELECT sekolah,npsn,tahun_ajaran,tanggal_unduh,pengunduh,email_pengunduh FROM dapodik_meta WHERE id=1 LIMIT 1")->fetch(); if($dm){ if(!empty($dm['sekolah'])) $metaSekolah=$dm['sekolah']; if(!empty($dm['npsn'])) $npsn=$dm['npsn']; if(!empty($dm['tahun_ajaran'])) $ta=$dm['tahun_ajaran']; $tglUnduh=$dm['tanggal_unduh']??null; $pengunduh=$dm['pengunduh']??null; $emailPengunduh=$dm['email_pengunduh']??null; } }catch(Throwable $e){}
$u=$_SESSION['user']??['nama'=>'Admin'];
$hariMap=['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$bulanMap=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$hari=$hariMap[date('l')]??date('l'); $tglHari=$hari.', '.date('j').' '.$bulanMap[(int)date('n')].' '.date('Y');
$threshold=76; $totalSiswa=0; $totalKelas=0; $totalPelBulan=0; $bermasalah=0;
$top=[]; $chartLabels=[]; $chartData=[];
if(isset($pdo) && $pdo){
  try{ $v=$pdo->query("SELECT value FROM settings WHERE key_name='threshold_poin_kritis' LIMIT 1")->fetchColumn(); if($v!==false&&$v!=='') $threshold=(int)$v; }catch(Throwable $e){}
  try{ $r=$pdo->query("SELECT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran DESC LIMIT 1")->fetchColumn(); if($r) $ta=$r; }catch(Throwable $e){}
  try{ $totalSiswa=(int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE deleted_at IS NULL AND status='Aktif'")->fetchColumn(); }catch(Throwable $e){}
  try{ $totalKelas=(int)$pdo->query("SELECT COUNT(*) FROM kelas WHERE deleted_at IS NULL")->fetchColumn(); }catch(Throwable $e){}
  try{ $totalPelBulan=(int)$pdo->query("SELECT COUNT(*) FROM pelanggaran_siswa WHERE DATE_FORMAT(tanggal,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn(); }catch(Throwable $e){}
  try{
    $st=$pdo->prepare("SELECT s.id,s.nama,s.nipd,COALESCE(k.nama_kelas,'—') as kelas, COALESCE(SUM(ps.poin_final),0) as poin, MAX(ps.tanggal) as tgl FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN pelanggaran_siswa ps ON ps.siswa_id=s.id AND ps.tahun_ajaran=? WHERE s.deleted_at IS NULL GROUP BY s.id HAVING poin > ? ORDER BY poin DESC LIMIT 10");
    $st->execute([$ta,$threshold]);
    $bermasalah=$st->rowCount();
  }catch(Throwable $e){}
  // top 10: always from actual poin (if bermasalah empty, still show ranking)
  try{
    $st=$pdo->prepare("SELECT s.id,s.nama,s.nipd,COALESCE(k.nama_kelas,'—') as kelas, COALESCE(SUM(ps.poin_final),0) as poin, MAX(ps.tanggal) as tgl, s.foto FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN pelanggaran_siswa ps ON ps.siswa_id=s.id AND ps.tahun_ajaran=? WHERE s.deleted_at IS NULL GROUP BY s.id ORDER BY poin DESC, s.nama ASC LIMIT 10");
    $st->execute([$ta]);
    $rank=1; while($r=$st->fetch()){ $r['rank']=$rank++; $r['poin']=(int)$r['poin']; if($r['poin']>$threshold) $r['badge']='Kritis'; elseif($r['poin']>50) $r['badge']='Tinggi'; elseif($r['poin']>25) $r['badge']='Sedang'; else $r['badge']='Rendah'; $top[]=$r; }
    if(empty($top)) $bermasalah=0;
    else { $cnt=0; foreach($top as $t) if($t['poin']>$threshold) $cnt++; if($bermasalah==0) $bermasalah=$cnt; }
  }catch(Throwable $e){}
  $chartLabels=[]; $chartData=[];
  $sumHarian=[]; $sumMingguan=[]; $sumBulanan=[]; $sumTahunan=[];
  try{
    $rs=$pdo->query("SELECT tanggal as d, COUNT(*) as c FROM pelanggaran_siswa WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d ORDER BY d");
    $m=[]; while($r=$rs->fetch()) $m[$r['d']]=(int)$r['c'];
    for($i=6;$i>=0;$i--){ $d=new DateTime("-$i days"); $k=$d->format('Y-m-d'); $sumHarian[]=['label'=>$d->format('d M'),'key'=>$k,'count'=>$m[$k]??0]; }
  }catch(Throwable $e){ $sumHarian=[]; }
  try{
    $sumMingguan=[];
    $rs2=$pdo->query("SELECT YEARWEEK(tanggal,1) as yw, COUNT(*) as c FROM pelanggaran_siswa WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 3 WEEK) GROUP BY yw ORDER BY yw");
    $m2=[]; while($r=$rs2->fetch()) $m2[(string)$r['yw']]=(int)$r['c'];
    for($i=3;$i>=0;$i--){
      $d=new DateTime("-$i weeks"); $mon=clone $d; $mon->modify('monday this week'); $sun=clone $mon; $sun->modify('+6 days');
      $yw=$mon->format('oW');
      $sumMingguan[]=['label'=>$mon->format('d M').' – '.$sun->format('d M'),'key'=>$yw,'count'=>$m2[$yw]??0];
    }
  }catch(Throwable $e){ $sumMingguan=[]; }
  try{
    $rs=$pdo->query("SELECT DATE_FORMAT(tanggal,'%Y-%m') as bln, COUNT(*) as c FROM pelanggaran_siswa WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY bln ORDER BY bln");
    $map=[]; while($r=$rs->fetch()) $map[$r['bln']]=(int)$r['c'];
    for($i=11;$i>=0;$i--){ $d=new DateTime("-$i months"); $k=$d->format('Y-m'); $sumBulanan[]=['label'=>$d->format('M Y'),'key'=>$k,'count'=>$map[$k]??0]; }
    // chart uses bulanan
    foreach($sumBulanan as $b){ $chartLabels[]=explode(' ',$b['label'])[0]; $chartData[]=$b['count']; }
    // keep only last 6 for chart
    $chartLabels=array_slice($chartLabels,-6); $chartData=array_slice($chartData,-6);
  }catch(Throwable $e){ $sumBulanan=[]; }
  try{
    $rs=$pdo->query("SELECT YEAR(tanggal) as th, COUNT(*) as c FROM pelanggaran_siswa GROUP BY th ORDER BY th");
    $m=[]; while($r=$rs->fetch()) $m[(string)$r['th']]=(int)$r['c'];
    $curY=(int)date('Y'); for($y=$curY-4;$y<=$curY;$y++) $sumTahunan[]=['label'=>(string)$y,'key'=>(string)$y,'count'=>$m[(string)$y]??0];
  }catch(Throwable $e){ $sumTahunan=[]; }
}
if(empty($chartLabels)){ $chartLabels=['Apr','Mei','Jun','Jul','Agu','Sep']; $chartData=[0,0,0,0,0,0]; }
if(empty($sumHarian)) for($i=6;$i>=0;$i--){ $d=new DateTime("-$i days"); $sumHarian[]=['label'=>$d->format('d M'),'key'=>$d->format('Y-m-d'),'count'=>0]; }
if(empty($sumMingguan)) for($i=3;$i>=0;$i--){ $d=new DateTime("-$i weeks"); $mon=clone $d; $mon->modify('monday this week'); $sumMingguan[]=['label'=>$mon->format('d M'),'key'=>$mon->format('oW'),'count'=>0]; }
if(empty($sumBulanan)) for($i=11;$i>=0;$i--){ $d=new DateTime("-$i months"); $sumBulanan[]=['label'=>$d->format('M Y'),'key'=>$d->format('Y-m'),'count'=>0]; }
if(empty($sumTahunan)){ $curY=(int)date('Y'); for($y=$curY-4;$y<=$curY;$y++) $sumTahunan[]=['label'=>(string)$y,'key'=>(string)$y,'count'=>0]; }
$chartHarianLabels=array_column($sumHarian,'label'); $chartHarianData=array_column($sumHarian,'count');
$chartMingguanLabels=array_column($sumMingguan,'label'); $chartMingguanData=array_column($sumMingguan,'count');
$chartTahunanLabels=array_column($sumTahunan,'label'); $chartTahunanData=array_column($sumTahunan,'count');
$chartBulanan6Labels=$chartLabels; $chartBulanan6Data=$chartData;
$sumBulanan6=array_slice($sumBulanan,-6);
$ultah=[]; try{ $rs=$pdo->query("SELECT s.id,s.nama,s.nipd,s.tanggal_lahir,s.foto,COALESCE(k.nama_kelas,'—') kelas,TIMESTAMPDIFF(YEAR,s.tanggal_lahir,CURDATE()) umur FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.deleted_at IS NULL AND s.status='Aktif' AND s.tanggal_lahir IS NOT NULL AND MONTH(s.tanggal_lahir)=MONTH(CURDATE()) AND DAY(s.tanggal_lahir)=DAY(CURDATE()) ORDER BY s.nama ASC"); $ultah=$rs->fetchAll(); }catch(Throwable $e){}
$isDemoTop = empty($top);
$stats=[
  ['label'=>'Total Siswa','value'=>number_format($totalSiswa,'0',',','.'),'trend'=>'+4.2%','up'=>true,'icon'=>'users','sub'=>'Aktif TA '.$ta],
  ['label'=>'Total Kelas','value'=> (string)$totalKelas,'trend'=>'+2','up'=>true,'icon'=>'school','sub'=>'Rombel aktif'],
  ['label'=>'Total Pelanggaran','value'=> (string)$totalPelBulan,'trend'=>'+12%','up'=>false,'icon'=>'shield-alert','sub'=>'Bulan ini — tren naik buruk'],
  ['label'=>'Siswa Bermasalah','value'=> (string)$bermasalah,'trend'=>'-3','up'=>true,'icon'=>'alert-triangle','sub'=>'Poin > '.$threshold.' (threshold)'],
];
function badgeClass($b){
  return match($b){'Rendah'=>'bg-[#DCFCE7] text-[#16A34A]','Sedang'=>'bg-[#FEF9C3] text-[#CA8A04]','Tinggi'=>'bg-[#FED7AA] text-[#EA580C]','Kritis'=>'bg-[#FEE2E2] text-[#DC2626]', default=>'bg-slate-100 text-slate-600'};
}
?>
<div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-card dark:shadow-none p-4 mb-4">
  <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-3">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1.5 bg-[#EFF6FF] dark:bg-white/10 text-[#2563EB] dark:text-[#93C5FD] border border-[#DBEAFE] dark:border-white/10 rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-wide"><i data-lucide="school" class="w-3.5 h-3.5"></i> <?=htmlspecialchars($metaSekolah)?></span>
        <span class="inline-flex bg-[#F1F5F9] dark:bg-white/5 text-[#475569] dark:text-[#CBD5E1] border border-[#E2E8F0] dark:border-white/10 rounded-full px-2.5 py-1 text-[11px] font-medium">NPSN: <?=htmlspecialchars($npsn)?></span>
        <span class="inline-flex bg-[#F1F5F9] dark:bg-white/5 text-[#475569] dark:text-[#CBD5E1] border border-[#E2E8F0] dark:border-white/10 rounded-full px-2.5 py-1 text-[11px] font-medium"><?=htmlspecialchars($ta)?></span>
        <?php if($tglUnduh): ?><span class="inline-flex bg-[#F1F5F9] dark:bg-white/5 text-[#475569] dark:text-[#CBD5E1] border border-[#E2E8F0] dark:border-white/10 rounded-full px-2.5 py-1 text-[11px] font-medium">Tanggal Unduh: <?=htmlspecialchars($tglUnduh)?></span><?php endif; ?>
        <?php if($pengunduh): ?><span class="inline-flex bg-[#EFF6FF] dark:bg-white/10 text-[#2563EB] dark:text-[#93C5FD] border border-[#DBEAFE] dark:border-white/10 rounded-full px-2.5 py-1 text-[11px] font-medium">Pengunduh: <?=htmlspecialchars($pengunduh)?><?= $emailPengunduh ? ' ('.htmlspecialchars($emailPengunduh).')' : '' ?></span><?php endif; ?>
      </div>
      <div class="mt-2.5 text-[13px] leading-5 text-[#475569] dark:text-[#94A3B8]">Selamat datang kembali, <b class="font-semibold text-[#0F172A] dark:text-white"><?=htmlspecialchars($u['nama'])?></b> · <?=$tglHari?></div>
    </div>
  </div>
</div>
<div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-card dark:shadow-none overflow-hidden">
  <div class="px-4 py-3 border-b border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between">
    <h3 class="font-semibold text-sm flex items-center gap-2"><i data-lucide="cake" class="w-4 h-4 text-pink-500"></i> Ulang Tahun Hari Ini <span class="ml-1 inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full bg-pink-100 dark:bg-pink-500/20 text-pink-600 dark:text-pink-400 text-xs font-bold"><?=count($ultah)?></span></h3>
    <span class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($tglHari)?></span>
  </div>
  <?php if(empty($ultah)): ?>
    <div class="px-4 py-8 text-center text-sm text-[#94A3B8] flex flex-col items-center gap-2"><i data-lucide="calendar-x" class="w-8 h-8 opacity-50"></i> Tidak ada siswa yang berulang tahun hari ini</div>
  <?php else: ?>
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase tracking-wide text-[#475569] dark:text-[#94A3B8]"><tr><th class="text-left px-4 py-2.5 font-semibold">Foto</th><th class="text-left px-4 py-2.5 font-semibold">Nama</th><th class="text-left px-4 py-2.5 font-semibold">NIPD</th><th class="text-left px-4 py-2.5 font-semibold">Kelas</th><th class="text-left px-4 py-2.5 font-semibold">Tanggal Lahir</th><th class="text-left px-4 py-2.5 font-semibold">Usia</th></tr></thead>
        <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
          <?php foreach($ultah as $u2): $f=$u2['foto']?(str_starts_with($u2['foto'],'http')?$u2['foto']:'/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($u2['foto'])):'https://i.pravatar.cc/100?u='.urlencode($u2['nipd']); $tglLahir=date('d M Y',strtotime($u2['tanggal_lahir'])); ?>
          <tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
            <td class="px-4 py-2.5"><img src="<?=$f?>" class="w-8 h-8 rounded-full object-cover"></td>
            <td class="px-4 py-2.5"><a href="/kesiswaanv2/siswa/detail.php?id=<?=$u2['id']?>" class="font-medium hover:underline flex items-center gap-1.5"><?=htmlspecialchars($u2['nama'])?> <span class="inline-flex px-1.5 py-0.5 rounded-full bg-pink-100 dark:bg-pink-500/20 text-pink-600 dark:text-pink-400 text-[11px] font-semibold">🎂 Hari ini</span></a></td>
            <td class="px-4 py-2.5 text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($u2['nipd'])?></td>
            <td class="px-4 py-2.5"><?=htmlspecialchars($u2['kelas'])?></td>
            <td class="px-4 py-2.5 text-xs"><?=htmlspecialchars($tglLahir)?></td>
            <td class="px-4 py-2.5 font-bold text-pink-600 dark:text-pink-400"><?=$u2['umur']?> th</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="md:hidden divide-y divide-[#E2E8F0] dark:divide-[#334155]">
      <?php foreach($ultah as $u2): $f=$u2['foto']?(str_starts_with($u2['foto'],'http')?$u2['foto']:'/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($u2['foto'])):'https://i.pravatar.cc/100?u='.urlencode($u2['nipd']); ?>
      <a href="/kesiswaanv2/siswa/detail.php?id=<?=$u2['id']?>" class="flex items-center gap-3 p-4">
        <img src="<?=$f?>" class="w-10 h-10 rounded-full object-cover">
        <div class="min-w-0 flex-1"><div class="text-sm font-semibold truncate flex items-center gap-1.5"><?=htmlspecialchars($u2['nama'])?> <span class="text-[11px]">🎂</span></div><div class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($u2['nipd'])?> · <?=htmlspecialchars($u2['kelas'])?> · <?=htmlspecialchars(date('d M Y',strtotime($u2['tanggal_lahir'])))?></div></div>
        <div class="text-sm font-bold text-pink-600 shrink-0"><?=$u2['umur']?> th</div>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
<?php foreach($stats as $s): ?>
  <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-card dark:shadow-none p-4">
    <div class="flex items-start justify-between">
      <div class="w-9 h-9 rounded-lg bg-[#EFF6FF] dark:bg-white/10 flex items-center justify-center text-[#2563EB] dark:text-[#93C5FD]"><i data-lucide="<?=$s['icon']?>" class="w-5 h-5"></i></div>
      <?php
        $isBad = in_array($s['label'],['Total Pelanggaran','Siswa Bermasalah']);
        $trendUp = $s['up'];
        $good = $isBad ? !$trendUp : $trendUp;
        if(str_starts_with($s['trend'],'-')){ $good = $isBad ? true : false; }
      ?>
      <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full <?= $good?'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400':'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400' ?>">
        <i data-lucide="<?= $good?'trending-up':'trending-down' ?>" class="w-3 h-3"></i> <?=$s['trend']?>
      </span>
    </div>
    <div class="mt-3 text-[11px] tracking-wide font-semibold uppercase text-[#475569] dark:text-[#94A3B8]"><?=$s['label']?></div>
    <div class="text-[28px] font-bold leading-none mt-1"><?=$s['value']?></div>
    <div class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1"><?=$s['sub']?></div>
  </div>
<?php endforeach; ?>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
  <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
    <div class="flex items-center gap-2 mb-3"><i data-lucide="calendar-days" class="w-4 h-4 text-[#2563EB]"></i><h3 class="font-semibold text-sm">Harian — 7 hari</h3><span class="ml-auto text-xs text-[#475569]">Total <?=array_sum($chartHarianData)?></span></div>
    <div class="h-[220px]"><canvas id="chartHarian"></canvas></div>
  </div>
  <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
    <div class="flex items-center gap-2 mb-3"><i data-lucide="calendar-range" class="w-4 h-4 text-[#2563EB]"></i><h3 class="font-semibold text-sm">Mingguan — 4 minggu</h3><span class="ml-auto text-xs text-[#475569]">Total <?=array_sum($chartMingguanData)?></span></div>
    <div class="h-[220px]"><canvas id="chartMingguan"></canvas></div>
  </div>
  <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
    <div class="flex items-center gap-2 mb-3"><i data-lucide="calendar" class="w-4 h-4 text-[#2563EB]"></i><h3 class="font-semibold text-sm">Bulanan — 6 bulan terakhir</h3><span class="ml-auto text-xs text-[#94A3B8]">Semester</span><span class="text-xs text-[#475569]">Total <?=array_sum($chartBulanan6Data)?></span></div>
    <div class="h-[220px]"><canvas id="chartBulanan6"></canvas></div>
  </div>
  <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
    <div class="flex items-center gap-2 mb-3"><i data-lucide="trending-up" class="w-4 h-4 text-[#2563EB]"></i><h3 class="font-semibold text-sm">Tahunan — 5 tahun</h3><span class="ml-auto text-xs text-[#475569]">Total <?=array_sum($chartTahunanData)?></span></div>
    <div class="h-[220px]"><canvas id="chartTahunan"></canvas></div>
  </div>
</div>
<div class="mt-2 rounded-lg bg-[#FFFBEB] dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-3 py-2 text-xs flex gap-2"><i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0"></i><span>Threshold <b><?=$threshold?></b> — <a href="/kesiswaanv2/pengaturan.php" class="underline font-medium">Pengaturan</a><?php if($isDemoTop): ?> <span class="text-[#94A3B8]">(belum ada siswa)</span><?php endif; ?></span></div>

<div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-card dark:shadow-none mt-4 overflow-hidden">
  <div class="px-4 py-3 border-b border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between">
    <h3 class="font-semibold text-sm">Top 10 — Poin Tertinggi (tahun ajaran berjalan)</h3>
    <a href="/kesiswaanv2/siswa/index.php" class="text-xs font-medium text-[#2563EB] dark:text-[#93C5FD] hover:underline">Lihat semua →</a>
  </div>
  <div class="hidden md:block overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase tracking-wide text-[#475569] dark:text-[#94A3B8]">
        <tr><th class="text-left px-4 py-2.5 font-semibold">#</th><th class="text-left px-4 py-2.5 font-semibold">Siswa</th><th class="text-left px-4 py-2.5 font-semibold">NIPD</th><th class="text-left px-4 py-2.5 font-semibold">Kelas</th><th class="text-left px-4 py-2.5 font-semibold">Poin</th><th class="text-left px-4 py-2.5 font-semibold">Status</th><th class="text-left px-4 py-2.5 font-semibold">Tgl Terakhir</th></tr>
      </thead>
      <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
        <?php if(empty($top)): ?><tr><td colspan="7" class="px-4 py-10 text-center text-sm text-[#94A3B8]">Belum ada data siswa — <a href="/kesiswaanv2/pengaturan/import.php" class="underline text-[#2563EB]">Import Dapodik</a></td></tr><?php endif; ?>
        <?php foreach($top as $r): ?>
        <tr class="<?= $r['rank']<=3?'bg-amber-50/60 dark:bg-amber-500/5':'' ?> hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03]">
          <td class="px-4 py-2.5 font-semibold"><?=$r['rank']?></td>
          <td class="px-4 py-2.5"><a href="/kesiswaanv2/siswa/detail.php?id=<?=$r['id']??$r['rank']?>" class="flex items-center gap-2 hover:underline"><?php $foto=$r['foto']? (str_starts_with($r['foto'],'http')?$r['foto']:'/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($r['foto'])) : 'https://i.pravatar.cc/100?u='.urlencode($r['nipd']); ?><img src="<?=$foto?>" class="w-8 h-8 rounded-full object-cover"><span class="font-medium"><?=htmlspecialchars($r['nama'])?></span></a></td>
          <td class="px-4 py-2.5 text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['nipd'])?></td>
          <td class="px-4 py-2.5"><?=htmlspecialchars($r['kelas'])?></td>
          <td class="px-4 py-2.5 font-bold"><?=$r['poin']?></td>
          <td class="px-4 py-2.5"><span class="inline-flex px-2 py-1 rounded-badge text-xs font-semibold <?=badgeClass($r['badge'])?>"><?=htmlspecialchars($r['badge'])?></span></td>
          <td class="px-4 py-2.5 text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['tgl']??'-')?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="md:hidden divide-y divide-[#E2E8F0] dark:divide-[#334155]">
    <?php foreach($top as $r): $foto=$r['foto']? (str_starts_with($r['foto'],'http')?$r['foto']:'/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($r['foto'])) : 'https://i.pravatar.cc/100?u='.urlencode($r['nipd']); ?>
    <a href="/kesiswaanv2/siswa/detail.php?id=<?=$r['id']??$r['rank']?>" class="flex items-center gap-3 p-4">
      <span class="w-7 h-7 rounded-full bg-[#0F172A] text-white flex items-center justify-center text-xs font-bold shrink-0"><?=$r['rank']?></span>
      <img src="<?=$foto?>" class="w-10 h-10 rounded-full object-cover">
      <div class="min-w-0 flex-1">
        <div class="text-sm font-semibold truncate"><?=htmlspecialchars($r['nama'])?></div>
        <div class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['nipd'])?> · <?=htmlspecialchars($r['kelas'])?></div>
      </div>
      <div class="text-right shrink-0">
        <div class="text-sm font-bold"><?=$r['poin']?></div>
        <span class="inline-flex px-2 py-0.5 rounded-badge text-[11px] font-semibold <?=badgeClass($r['badge'])?>"><?=htmlspecialchars($r['badge'])?></span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<script>
const CH={ h:{labels:<?=json_encode($chartHarianLabels)?>, data:<?=json_encode($chartHarianData)?>}, m:{labels:<?=json_encode($chartMingguanLabels)?>, data:<?=json_encode($chartMingguanData)?>}, t:{labels:<?=json_encode($chartTahunanLabels)?>, data:<?=json_encode($chartTahunanData)?>}, b:{labels:<?=json_encode($chartBulanan6Labels)?>, data:<?=json_encode($chartBulanan6Data)?>} };
function mkBar(id, labels, data, color){
  const c=document.getElementById(id); if(!c) return;
  new Chart(c,{type:'bar',data:{labels,datasets:[{label:'Kejadian',data,borderWidth:1,borderRadius:6,backgroundColor:color}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}});
}
mkBar('chartHarian', CH.h.labels, CH.h.data, '#2563EB');
mkBar('chartMingguan', CH.m.labels, CH.m.data, '#7C3AED');
mkBar('chartBulanan6', CH.b.labels, CH.b.data, '#2563EB');
mkBar('chartTahunan', CH.t.labels, CH.t.data, '#059669');
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
