<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/auth.php';
if (empty($_SESSION['user'])) { header('Location: /kesiswaanv2/login.php'); exit; }
require_once __DIR__.'/ta.php';
if(isset($_GET['set_ta']) && $_GET['set_ta']!==''){
  if(isset($pdo)&&$pdo) set_ta_aktif($pdo, $_GET['set_ta']);
  else $_SESSION['ta_aktif']=trim(preg_replace('/\s+Semester.*$/i','',$_GET['set_ta']));
  $qs=$_GET; unset($qs['set_ta']); $dest=$_SERVER['PHP_SELF'].($qs?'?'.http_build_query($qs):'');
  header('Location: '.$dest); exit;
}
$u = current_user();
if (!isset($u['avatar'])) $u['avatar'] = strtoupper(substr($u['nama'] ?? $u['username'] ?? 'A', 0, 1));
$active = $active ?? '';
$title = $title ?? 'Kesiswaan';
$ta_aktif=null; $ta_list=[];
if(isset($pdo)&&$pdo){ $ta_aktif=get_ta_aktif($pdo); $ta_list=list_ta_options($pdo); }
function navItem($key,$href,$icon,$label,$active){ $on=$active===$key;
  $base='flex items-center gap-3 px-3 py-2.5 rounded-[8px] text-[13px] font-medium transition-colors';
  $cls=$on?'bg-[#2563EB] text-white shadow-sm border-l-[3px] border-[#1D4ED8]':'text-slate-300 hover:bg-white/[0.06] hover:text-white';
  return '<a href="'.$href.'" class="'.$base.' '.$cls.'"><i data-lucide="'.$icon.'" class="w-5 h-5 shrink-0"></i><span class="nav-label">'.$label.'</span></a>';
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<title><?=htmlspecialchars($title)?> — Kesiswaan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']},colors:{primary:{DEFAULT:'#2563EB',dark:'#1D4ED8'},muted:'#475569'},borderRadius:{card:'8px',input:'6px'},boxShadow:{card:'0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06)'}}}}</script>
<style>
*{font-family:Inter,system-ui,sans-serif} :focus-visible{outline:2px solid #2563EB;outline-offset:2px}
.sidebar-collapsed #sidebar{width:64px} .sidebar-collapsed .nav-label,.sidebar-collapsed .sidebar-meta,.sidebar-collapsed .logo-text{ display:none }
.sidebar-collapsed #main{margin-left:64px}
@media(max-width:1023px){ #sidebar{width:64px} .nav-label,.sidebar-meta,.logo-text{display:none} #main{margin-left:64px} }
@media(max-width:767px){ #sidebar{transform:translateX(-100%)} #sidebar.open{transform:translateX(0);width:260px} #sidebar.open .nav-label,#sidebar.open .sidebar-meta,#sidebar.open .logo-text{display:block} #sidebar.open .nav-label{display:inline} #main{margin-left:0!important} }
</style>
</head>
<body class="h-full bg-[#F8FAFC] dark:bg-[#0F172A] text-[#0F172A] dark:text-[#F1F5F9] antialiased overflow-x-hidden">
<!-- mobile overlay -->
<div id="overlay" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-30 hidden lg:hidden"></div>

<!-- SIDEBAR -->
<aside id="sidebar" class="fixed left-0 top-0 h-[100dvh] w-[240px] bg-[#0F172A] border-r border-white/10 flex flex-col z-40 transition-all duration-200 overflow-hidden">
  <div class="h-14 flex items-center gap-2 px-3 border-b border-white/10 shrink-0">
    <button id="collapseBtn" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/15 flex items-center justify-center text-white shrink-0" aria-label="Toggle sidebar"><i data-lucide="panel-left" class="w-4 h-4"></i></button>
    <div class="w-8 h-8 rounded-[10px] bg-[#2563EB] flex items-center justify-center shrink-0"><i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i></div>
    <span class="logo-text font-bold text-white text-sm tracking-tight whitespace-nowrap">Kesiswaan</span>
    <span class="logo-text ml-auto text-[10px] tracking-widest font-semibold text-white/60 border border-white/15 rounded-full px-2 py-0.5">v1.4</span>
  </div>

  <nav class="flex-1 overflow-y-auto p-3 space-y-1">
    <?php if(role_can('view_dashboard')): ?><?=navItem('dashboard','/kesiswaanv2/dashboard.php','layout-dashboard','Dashboard',$active)?><?php endif; ?>
    <?php if(role_can('view_siswa')): ?><?=navItem('siswa','/kesiswaanv2/siswa/index.php','users','Master Siswa',$active)?><?php endif; ?>
    <?php if(role_can('view_ultah')): ?><?=navItem('ultah','/kesiswaanv2/siswa/ultah.php','cake','Ulang Tahun',$active)?><?php endif; ?>
    <?php if(role_can('view_user')): ?><?=navItem('user','/kesiswaanv2/user/index.php','user-cog','Master User',$active)?><?php endif; ?>
    <?php if(role_can('view_kelas')): ?><?=navItem('kelas','/kesiswaanv2/kelas/index.php','school','Master Kelas',$active)?><?php endif; ?>
    <?php if(role_can('view_pelanggaran_master')): ?><?=navItem('pelanggaran','/kesiswaanv2/pelanggaran/master.php','shield-alert','Poin Pelanggaran',$active)?><?php endif; ?>
    <?php if(role_can('view_catat_pelanggaran')): ?><?=navItem('pelanggaran_tambah','/kesiswaanv2/pelanggaran/tambah.php','plus-circle','Catat Pelanggaran',$active)?><?php endif; ?>
    <?php if(role_can('view_log_pelanggaran')): ?><?=navItem('pelanggaran_log','/kesiswaanv2/pelanggaran/log.php','scroll-text','Log Pelanggaran',$active)?><?php endif; ?>
    <?php if(role_can('view_bk')): ?><?=navItem('bk','/kesiswaanv2/bk/index.php','heart-handshake','Data Bimbingan Konseling',$active)?><?php endif; ?>
    <?php if(role_can('view_bk_log')): ?><?=navItem('bk_log','/kesiswaanv2/bk/log.php','clipboard-list','Log Bimbingan Konseling',$active)?><?php endif; ?>
    <?php if(role_can('view_pengaturan')): ?>
    <div class="pt-3 mt-3 border-t border-white/10">
      <div class="px-3 pb-1.5 text-[10px] tracking-widest font-semibold text-white/40">PENGATURAN</div>
      <div class="space-y-1">
        <?=navItem('pengaturan','/kesiswaanv2/pengaturan.php','sliders-horizontal','Threshold Poin',$active)?>
        <?=navItem('pengaturan_import','/kesiswaanv2/pengaturan/import.php','file-up','Import Dapodik 60 Kolom',$active)?>
        <?=navItem('pengaturan_pelanggaran','/kesiswaanv2/pengaturan/pelanggaran.php','shield-alert','Import Data Pelanggaran',$active)?>
        <?=navItem('pengaturan_hapus_siswa','/kesiswaanv2/pengaturan/hapus_siswa.php','user-x','Hapus Master Siswa',$active)?>
        <?=navItem('pengaturan_hapus_kelas','/kesiswaanv2/pengaturan/hapus_kelas.php','school','Hapus Master Kelas',$active)?>
      </div>
    </div>
    <?php endif; ?>
    <?php if(!role_can('view_dashboard') && !role_can('view_siswa') && !role_can('view_user')): ?>
    <div class="px-3 py-3 text-xs text-white/50">Menu terbatas untuk role <?=htmlspecialchars($u['role']??'')?> · hubungi Admin.</div>
    <?php endif; ?>
  </nav>

  <div class="p-3 border-t border-white/10">
    <div class="sidebar-meta flex items-center gap-3 px-2 py-2 rounded-lg bg-white/[0.06] border border-white/10">
      <div class="w-8 h-8 rounded-full bg-[#2563EB] flex items-center justify-center text-white text-xs font-bold"><?=$u['avatar']?></div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-white truncate"><?=htmlspecialchars($u['nama'])?></div>
        <div class="text-[11px] text-white/60 truncate"><?=htmlspecialchars($u['role'])?> · <?=htmlspecialchars($u['username'])?></div>
      </div>
    </div>
    <a href="/kesiswaanv2/logout.php" class="mt-2 flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] font-medium text-slate-300 hover:bg-white/[0.06] hover:text-white transition"><i data-lucide="log-out" class="w-4 h-4"></i><span class="nav-label">Logout</span></a>
  </div>
</aside>

<!-- MAIN -->
<div id="main" class="min-h-[100dvh] ml-[240px] flex flex-col transition-all duration-200">
  <!-- TOPBAR -->
  <header class="h-14 bg-white dark:bg-[#1E293B] border-b border-[#E2E8F0] dark:border-[#334155] flex items-center gap-3 px-4 sm:px-6 sticky top-0 z-20">
    <button id="hamburger" class="w-9 h-9 rounded-lg border border-[#E2E8F0] dark:border-[#334155] flex items-center justify-center md:hidden"><i data-lucide="menu" class="w-5 h-5"></i></button>
    <h1 class="font-semibold text-[15px] tracking-tight"><?=htmlspecialchars($title)?></h1>
    <div class="ml-auto flex items-center gap-2">
      <?php if(!empty($ta_list)): ?>
      <div class="hidden sm:flex items-center gap-1.5 pl-2 pr-2 border-l border-[#E2E8F0] dark:border-[#334155]">
        <span class="text-[11px] font-semibold text-[#475569] dark:text-[#94A3B8]">TA</span>
        <select id="taSwitcher" class="h-7 rounded-full border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-xs font-semibold px-2">
          <?php foreach($ta_list as $taOpt): ?><option value="<?=htmlspecialchars($taOpt)?>" <?= $taOpt===$ta_aktif?'selected':'' ?>><?=htmlspecialchars($taOpt)?></option><?php endforeach; ?>
        </select>
      </div>
      <script>document.getElementById('taSwitcher')?.addEventListener('change',function(){ const u=new URL(location.href); u.searchParams.set('set_ta',this.value); location.href=u.toString(); });</script>
      <?php endif; ?>
      <button id="themeToggle" class="w-9 h-9 rounded-full border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#1E293B] flex items-center justify-center hover:bg-[#F1F5F9] dark:hover:bg-white/5"><i data-lucide="moon" class="w-4 h-4 block dark:hidden"></i><i data-lucide="sun" class="w-4 h-4 hidden dark:block"></i></button>
      <div class="hidden sm:flex items-center gap-2 pl-2 border-l border-[#E2E8F0] dark:border-[#334155]">
        <img src="https://i.pravatar.cc/100?u=<?=urlencode($u['username'])?>" alt="" class="w-8 h-8 rounded-full object-cover">
        <div class="hidden lg:block text-xs leading-none"><div class="font-semibold"><?=htmlspecialchars($u['nama'])?></div><div class="text-[#475569] dark:text-[#94A3B8]"><?=htmlspecialchars($u['role'])?></div></div>
      </div>
    </div>
  </header>

  <!-- CONTENT -->
  <main class="flex-1 p-4 sm:p-6">
