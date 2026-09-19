<?php
$active='siswa'; $title='Master Siswa';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
$threshold=76; if(isset($pdo)&&$pdo){ try{ $v=$pdo->query("SELECT value FROM settings WHERE key_name='threshold_poin_kritis' LIMIT 1")->fetchColumn(); if($v!==false&&$v!=='') $threshold=(int)$v; }catch(Throwable $e){} }
try{ $ta=$pdo->query("SELECT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran DESC LIMIT 1")->fetchColumn(); if(!$ta) $ta='2024/2025'; }catch(Throwable $e){ $ta='2024/2025'; }
$sort=trim($_GET['sort']??''); if(!in_array($sort,['nama','nipd','kelas','poin','kelas_poin'],true)) $sort='nama';
$dir=strtolower(trim($_GET['dir']??''))==='desc' ? 'desc' : 'asc';
if($sort==='poin'||$sort==='kelas_poin') $dir='desc';
$q=trim($_GET['q']??''); $fKelas=$_GET['kelas']??''; $fStatus=$_GET['status']??''; $page=max(1,(int)($_GET['page']??1)); $size=(int)($_GET['size']??25); if(!in_array($size,[10,25,50,100])) $size=25;
$kelasOpts=[]; try{ foreach($pdo->query("SELECT nama_kelas FROM kelas WHERE deleted_at IS NULL ORDER BY nama_kelas") as $r) $kelasOpts[]=$r['nama_kelas']; }catch(Throwable $e){}
$where="WHERE s.deleted_at IS NULL"; $args=[];
if($q!==''){ $where.=" AND (s.nama LIKE ? OR s.nipd LIKE ?)"; $args[]="%$q%"; $args[]="%$q%"; }
if($fKelas!=='' ){ $where.=" AND k.nama_kelas=?"; $args[]=$fKelas; }
if($fStatus!==''){ $where.=" AND s.status=?"; $args[]=$fStatus; }
$total=0; try{ $st=$pdo->prepare("SELECT COUNT(*) FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id $where"); $st->execute($args); $total=(int)$st->fetchColumn(); }catch(Throwable $e){}
$totalPages=max(1, (int)ceil($total/$size)); if($page>$totalPages) $page=$totalPages; $offset=($page-1)*$size;
$rows=[]; $queryErr=''; try{
  $orderBy = match($sort){
    'nama' => "s.nama $dir, s.nipd ASC",
    'nipd' => "s.nipd $dir, s.nama ASC",
    'kelas' => "k.nama_kelas $dir, s.nama ASC",
    'poin' => "poin $dir, s.nama ASC",
    'kelas_poin' => "k.nama_kelas ASC, poin DESC, s.nama ASC",
    default => "s.nama ASC"
  };
  $sql="SELECT s.id,s.nipd,s.nama,s.jenis_kelamin,COALESCE(k.nama_kelas,'—') as kelas,s.foto,s.status,(SELECT COALESCE(SUM(poin_final),0) FROM pelanggaran_siswa ps WHERE ps.siswa_id=s.id AND ps.tahun_ajaran=?) as poin FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id $where ORDER BY $orderBy LIMIT ? OFFSET ?";
  $st=$pdo->prepare($sql); $st->execute(array_merge([$ta],$args,[$size,$offset])); $rows=$st->fetchAll();
  if(!$rows) $rows=[];
}catch(Throwable $e){ $queryErr=$e->getMessage(); $rows=[]; }
$isDemo = ($total===0);
function badgePoin($p,$thr=76){
  if($p>$thr) return ['Kritis','bg-[#FEE2E2] text-[#DC2626] dark:bg-red-500/15 dark:text-red-400'];
  if($p>50) return ['Tinggi','bg-[#FED7AA] text-[#EA580C] dark:bg-orange-500/15 dark:text-orange-300'];
  if($p>25) return ['Sedang','bg-[#FEF9C3] text-[#CA8A04] dark:bg-amber-500/15 dark:text-amber-300'];
  return ['Rendah','bg-[#DCFCE7] text-[#16A34A] dark:bg-emerald-500/15 dark:text-emerald-400'];
}
function fotoUrl($f,$nipd){ if($f && !str_starts_with($f,'http')) return '/kesiswaanv2/assets/uploads/foto_siswa/'.rawurlencode($f); if($f) return $f; return 'https://i.pravatar.cc/100?u='.urlencode($nipd); }
?>
<?php if(isset($_GET['msg'])): ?><div class="mb-3 rounded-lg border-l-4 border-emerald-500 bg-[#F0FDF4] px-4 py-2.5 text-sm"><?=htmlspecialchars($_GET['msg'])?></div><?php endif; ?>
<?php require __DIR__.'/../includes/header.php'; ?>
<?php if($queryErr): ?><div class="mb-3 rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] px-4 py-2.5 text-xs">Query error: <?=htmlspecialchars($queryErr)?></div><?php endif; ?>
<?php if($isDemo): ?><div class="mb-3 rounded-lg border-l-4 border-amber-500 bg-[#FFFBEB] px-4 py-2.5 text-xs">Data demo — DB kosong. Import via <a href="/kesiswaanv2/pengaturan.php#import" class="underline font-medium">Pengaturan → Import Dapodik</a> atau tambah manual.</div><?php endif; ?>
<form method="GET" class="flex flex-wrap gap-2 items-center">
  <div class="flex-1 min-w-[220px] relative">
    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]"></i>
    <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari nama / NIPD…" class="w-full h-10 pl-9 pr-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm placeholder:text-[#94A3B8] focus:border-[#2563EB] focus:ring-2 focus:ring-[#2563EB]/20 outline-none">
  </div>
  <select name="kelas" onchange="this.form.submit()" class="h-10 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm px-3">
    <option value="">Semua Kelas</option><?php foreach($kelasOpts as $k): ?><option <?= $fKelas===$k?'selected':'' ?>><?=htmlspecialchars($k)?></option><?php endforeach; ?>
  </select>
  <select name="status" onchange="this.form.submit()" class="h-10 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm px-3">
    <option value="">Semua Status</option><?php foreach(['Aktif','Pindah','Lulus','Tidak Aktif'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?=$s?></option><?php endforeach; ?>
  </select>
  <select name="size" onchange="this.form.submit()" class="h-10 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm px-3">
    <?php foreach([10,25,50,100] as $n): ?><option <?= $size==$n?'selected':'' ?>><?=$n?></option><?php endforeach; ?>
  </select>
  <button class="h-10 px-4 rounded-input border bg-white dark:bg-[#0F172A] text-sm">Cari</button>
  <?php if($q||$fKelas||$fStatus): ?><a href="index.php" class="h-10 px-4 rounded-input border bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Reset</a><?php endif; ?>
</form>
<div class="flex flex-wrap gap-2 mt-3">
  <a href="/kesiswaanv2/siswa/tambah.php" class="inline-flex items-center gap-2 h-9 px-4 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold"><i data-lucide="plus" class="w-4 h-4"></i> Tambah Siswa</a>
</div>
<?php
  $baseQs = array_filter(['q'=>$q,'kelas'=>$fKelas,'status'=>$fStatus,'size'=>$size]);
  function sort_link($baseQs,$col,$label,$curSort,$curDir){
    $nextDir = ($curSort===$col && $curDir==='asc') ? 'desc' : 'asc';
    $qs = array_merge($baseQs, ['sort'=>$col,'dir'=>$nextDir]);
    $href = 'index.php?'.http_build_query($qs);
    $icon = $curSort===$col ? ($curDir==='asc'?'▲':'▼') : '↕';
    $cls = $curSort===$col ? 'text-[#2563EB] dark:text-[#93C5FD] font-bold' : '';
    return '<a href="'.htmlspecialchars($href).'" class="inline-flex items-center gap-1 hover:text-[#2563EB] '.$cls.'">'.htmlspecialchars($label).' <span class="text-[11px]">'.$icon.'</span></a>';
  }
?>
<div class="hidden md:block bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card shadow-card dark:shadow-none overflow-hidden mt-4">
  <table class="w-full text-sm">
    <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase tracking-wide text-[#475569] dark:text-[#94A3B8]">
      <tr>
        <th class="text-left px-4 py-3 font-semibold">Foto</th>
        <th class="text-left px-4 py-3 font-semibold"><?=sort_link($baseQs,'nipd','NIPD',$sort,$dir)?></th>
        <th class="text-left px-4 py-3 font-semibold"><?=sort_link($baseQs,'nama','Nama Lengkap',$sort,$dir)?></th>
        <th class="text-left px-4 py-3 font-semibold"><?=sort_link($baseQs,'kelas','Kelas',$sort,$dir)?></th>
        <th class="text-left px-4 py-3 font-semibold">JK</th>
        <th class="text-left px-4 py-3 font-semibold"><?=sort_link($baseQs,'poin','Poin',$sort,$dir)?></th>
        <th class="text-left px-4 py-3 font-semibold">Aksi</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
      <?php foreach($rows as $r): [$lbl,$cls]=badgePoin((int)($r['poin']??0),$threshold); ?>
      <tr class="hover:bg-[#F8FAFC] dark:hover:bg-white/[0.03] even:bg-[#F8FAFC]/50 dark:even:bg-white/[0.02]">
        <td class="px-4 py-2.5"><img src="<?=fotoUrl($r['foto']??null,$r['nipd'])?>" class="w-9 h-9 rounded-full object-cover"></td>
        <td class="px-4 py-2.5 font-medium"><?=htmlspecialchars($r['nipd'])?></td>
        <td class="px-4 py-2.5"><a href="/kesiswaanv2/siswa/detail.php?id=<?=$r['id']?>" class="font-medium hover:underline text-[#2563EB] dark:text-[#93C5FD]"><?=htmlspecialchars($r['nama'])?></a></td>
        <td class="px-4 py-2.5"><?=htmlspecialchars($r['kelas'])?></td>
        <td class="px-4 py-2.5"><?=htmlspecialchars($r['jenis_kelamin'])?></td>
        <td class="px-4 py-2.5"><span class="inline-flex px-2 py-1 rounded-badge text-xs font-semibold <?=$cls?>"><?=$lbl?> · <?=$r['poin']?></span></td>
        <td class="px-4 py-2.5">
          <div class="flex gap-1.5">
            <a href="/kesiswaanv2/siswa/edit.php?id=<?=$r['id']?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-input border border-[#2563EB]/20 text-[#2563EB] dark:text-[#93C5FD] text-xs font-medium hover:bg-[#EFF6FF] dark:hover:bg-white/5"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</a>
            <form method="POST" action="hapus.php" onsubmit="return confirm('Hapus <?=htmlspecialchars($r['nama'],ENT_QUOTES)?>? (soft-delete)')" class="inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=$r['id']?>"><button class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium hover:bg-[#FFF1F2] dark:hover:bg-red-500/10"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus</button></form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td colspan="7" class="px-4 py-10 text-center text-sm text-[#94A3B8]">Belum ada data — <a href="tambah.php" class="underline text-[#2563EB]">Tambah Siswa</a> atau <a href="import.php" class="underline text-[#2563EB]">Import Excel</a></td></tr><?php endif; ?>
    </tbody>
  </table>
  <div class="px-4 py-3 flex items-center justify-between text-xs text-[#475569] dark:text-[#94A3B8] border-t border-[#E2E8F0] dark:border-[#334155]">
    <span>Menampilkan <?= $total? ($offset+1).'–'.min($offset+$size,$total)." dari $total data" : '0 data' ?></span>
    <div class="flex gap-1">
      <?php $qs=http_build_query(array_filter(['q'=>$q,'kelas'=>$fKelas,'status'=>$fStatus,'size'=>$size])); $base='index.php'.($qs?'?'.$qs.'&':'?'); ?>
      <a href="<?=$base?>page=<?=max(1,$page-1)?>" class="px-3 py-1.5 rounded-input border bg-white dark:bg-[#0F172A] border-[#E2E8F0] dark:border-[#334155] <?= $page<=1?'opacity-50 pointer-events-none':'' ?>">Prev</a>
      <span class="px-3 py-1.5">Hal <?=$page?> / <?=$totalPages?></span>
      <a href="<?=$base?>page=<?=min($totalPages,$page+1)?>" class="px-3 py-1.5 rounded-input border bg-white dark:bg-[#0F172A] border-[#E2E8F0] dark:border-[#334155] <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>">Next</a>
    </div>
  </div>
</div>
<div class="md:hidden space-y-3 mt-4">
  <?php foreach($rows as $r): [$lbl,$cls]=badgePoin((int)($r['poin']??0),$threshold); ?>
  <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
    <div class="flex gap-3">
      <img src="<?=fotoUrl($r['foto']??null,$r['nipd'])?>" class="w-12 h-12 rounded-full object-cover">
      <div class="flex-1 min-w-0">
        <a href="/kesiswaanv2/siswa/detail.php?id=<?=$r['id']?>" class="font-semibold text-sm hover:underline"><?=htmlspecialchars($r['nama'])?></a>
        <div class="text-xs text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($r['nipd'])?> · <?=htmlspecialchars($r['kelas'])?> · <?=htmlspecialchars($r['jenis_kelamin'])?></div>
        <span class="inline-flex mt-1.5 px-2 py-1 rounded-badge text-xs font-semibold <?=$cls?>"><?=$lbl?> · <?=$r['poin']?></span>
      </div>
    </div>
    <div class="flex gap-2 mt-3">
      <a href="/kesiswaanv2/siswa/edit.php?id=<?=$r['id']?>" class="flex-1 inline-flex justify-center items-center gap-1 h-8 rounded-input border border-[#2563EB]/20 text-[#2563EB] text-xs font-medium"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</a>
      <form method="POST" action="hapus.php" onsubmit="return confirm('Hapus?')" class="flex-1"><?=csrf_field()?><input type="hidden" name="id" value="<?=$r['id']?>"><button class="w-full inline-flex justify-center items-center gap-1 h-8 rounded-input border border-red-200 text-[#DC2626] text-xs font-medium"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus</button></form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
