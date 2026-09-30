<?php
session_start();
if (!empty($_SESSION['user'])) { header('Location: dashboard.php'); exit; }
require_once __DIR__.'/config/db.php';
$error = '';
function _rate_key($u){ return sys_get_temp_dir().'/kesiswaan_rate_'.md5(strtolower($u)).'.json'; }
function _rate_ok($u){
  $f=_rate_key($u); if(!file_exists($f)) return true;
  $d=json_decode(@file_get_contents($f),true); if(!$d) return true;
  if(time() > ($d['reset']??0)){ @unlink($f); return true; }
  return ($d['count']??0) < 5;
}
function _rate_fail($u){
  $f=_rate_key($u); $d=json_decode(@file_get_contents($f),true)?:['count'=>0,'reset'=>time()+15*60];
  if(time() > ($d['reset']??0)) $d=['count'=>1,'reset'=>time()+15*60]; else $d['count']++;
  @file_put_contents($f, json_encode($d), LOCK_EX);
}
function _rate_reset($u){ @unlink(_rate_key($u)); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  if ($username === '' || $password === '') {
    $error = 'Username dan password wajib.';
  } elseif (!_rate_ok($username)) {
    $error = 'Terlalu banyak percobaan, coba lagi 15 menit.';
  } else {
    $ok=false; $user=null;
    if(isset($pdo) && $pdo){
      try{
        $stmt=$pdo->prepare("SELECT id,nama,username,role,password_hash,status FROM users WHERE username=? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$username]); $row=$stmt->fetch();
        if(!$row) $error='Username atau password salah.';
        elseif($row['status']==='Nonaktif') $error='Akun nonaktif.';
        elseif(password_verify($password,$row['password_hash'])){ $ok=true; $user=$row; }
        else $error='Username atau password salah.';
      }catch(Throwable $e){ error_log('login DB: '.$e->getMessage()); $error='Terjadi kesalahan sistem, coba lagi.'; }
    } else {
      error_log('login: DB not ready');
      $error='Sistem belum siap, coba lagi nanti.';
    }
    if ($ok && $user) {
      _rate_reset($username);
      session_regenerate_id(true);
      $_SESSION['user']=['id'=>$user['id'],'nama'=>$user['nama'],'username'=>$user['username'],'role'=>$user['role'],'avatar'=>strtoupper(substr($user['nama'],0,1))];
      header('Location: dashboard.php'); exit;
    }
    if($ok && !$user) $error='Username atau password salah.';
    if(!$ok && !$error) $error='Username atau password salah.';
    if($error==='Username atau password salah.' || str_contains($error,'DB error')) _rate_fail($username);
    if($error==='Akun nonaktif.') _rate_fail($username);
  }
}
$errQ=$_GET['error']??''; if($errQ&&!$error) $error=$errQ;
$dbOk = isset($pdo) && $pdo ? true : false;
$dbMsg=''; if(!$dbOk){ try{ new PDO("mysql:host=127.0.0.1;charset=utf8mb4",'root',''); }catch(Throwable $e){ error_log('login db probe: '.$e->getMessage()); $dbMsg='koneksi DB gagal'; } }
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<title>Masuk — Kesiswaan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']},colors:{primary:{DEFAULT:'#2563EB',dark:'#1D4ED8'}},borderRadius:{card:'8px',input:'6px'},boxShadow:{card:'0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06)'}}}}</script>
<style>
*{font-family:Inter,system-ui,sans-serif}
:focus-visible{outline:2px solid #2563EB;outline-offset:2px}
/* Mega Mendung Cirebonan — biru indigo deep, sesuai foto batik asli */
.mm-wrap{position:fixed;inset:0;z-index:-1;overflow:hidden;pointer-events:none}
.mm-bg-light{position:absolute;inset:0;background:#eef2f8;transition:opacity .35s}
.mm-bg-dark{position:absolute;inset:0;background:#060d22;transition:opacity .35s;opacity:0}
.dark .mm-bg-light{opacity:0}
.dark .mm-bg-dark{opacity:1}
.mm-clouds{position:absolute;inset:0;width:100%;height:100%}
.mm-light{opacity:.22}
.dark .mm-light{opacity:0}
.mm-dark{opacity:0}
.dark .mm-dark{opacity:1}
</style>
</head>
<body class="h-full bg-[#F8FAFC] dark:bg-[#0F172A] text-[#0F172A] dark:text-[#F1F5F9] antialiased overflow-x-hidden">
<div class="mm-wrap" aria-hidden="true">
  <div class="mm-bg-light"></div>
  <div class="mm-bg-dark"></div>
  <!-- LIGHT mega mendung pudar -->
  <svg class="mm-clouds mm-light" viewBox="0 0 820 600" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <defs><pattern id="mmL" width="410" height="295" patternUnits="userSpaceOnUse">
      <g fill="none" stroke="#7d8fb3" stroke-linecap="round" stroke-linejoin="round">
        <g opacity=".9">
          <path d="M28 100 C28 58 70 32 112 44 C132 22 172 18 190 44 C212 70 182 102 138 96 C150 80 134 64 114 70 C98 74 88 86 97 100 C82 94 44 90 28 100 Z" fill="#cbd9ef" stroke-width="1.5"/>
          <path d="M42 98 C42 66 72 48 106 56 C122 40 160 36 174 56 C186 74 166 90 134 86" fill="#d6e2f5" stroke-width="1.15"/>
          <path d="M56 96 C56 72 80 60 106 66 C120 52 148 48 160 62 C168 74 154 84 132 82" fill="#e0eaf8" stroke-width=".95"/>
          <path d="M142 68 C156 50 172 50 178 62 C184 74 174 86 160 82 C154 80 150 74 154 68" fill="none" stroke-width="1.05" stroke="#8fa0c0"/>
        </g>
        <g opacity=".85" transform="translate(218,6) scale(.90)">
          <path d="M28 100 C28 58 70 32 112 44 C132 22 172 18 190 44 C212 70 182 102 138 96 C150 80 134 64 114 70 C98 74 88 86 97 100 C82 94 44 90 28 100 Z" fill="#cbd9ef" stroke-width="1.5"/>
          <path d="M42 98 C42 66 72 48 106 56 C122 40 160 36 174 56" fill="#d6e2f5" stroke-width="1.15"/>
          <path d="M142 68 C156 50 172 50 178 62" fill="none" stroke-width="1.05" stroke="#8fa0c0"/>
        </g>
        <g opacity=".88" transform="translate(62,172) scale(.98)">
          <path d="M28 100 C28 58 70 32 112 44 C132 22 172 18 190 44 C212 70 182 102 138 96" fill="#cbd9ef" stroke-width="1.5"/>
          <path d="M42 98 C42 66 72 48 106 56" fill="#d6e2f5" stroke-width="1.15"/>
          <path d="M142 68 C156 50 172 50 178 62" fill="none" stroke-width="1.05" stroke="#8fa0c0"/>
        </g>
      </g>
    </pattern></defs>
    <rect width="100%" height="100%" fill="url(#mmL)"/>
  </svg>
  <!-- DARK mega mendung biru indigo deep navy — seperti foto -->
  <svg class="mm-clouds mm-dark" viewBox="0 0 820 600" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <defs><pattern id="mmD" width="420" height="310" patternUnits="userSpaceOnUse">
      <g fill="none" stroke-linecap="round" stroke-linejoin="round">
        <!-- awan utama -->
        <g>
          <path d="M24 112 C24 62 70 32 118 46 C140 22 180 16 200 46 C224 80 192 118 144 112 C158 94 140 72 118 80 C100 84 88 98 100 114 C82 108 40 102 24 112 Z" fill="#0f2a5a" stroke="#8fa6cc" stroke-width="1.7"/>
          <path d="M40 110 C40 70 72 50 108 60 C126 40 166 34 182 60 C198 82 174 104 140 100" fill="#14346e" stroke="#8fa6cc" stroke-width="1.35"/>
          <path d="M54 108 C54 74 80 60 108 70 C124 54 154 48 166 66 C176 82 160 96 138 92" fill="#1b3f85" stroke="#8fa6cc" stroke-width="1.1"/>
          <path d="M68 102 C68 82 84 70 106 76 C120 64 140 60 150 72 C158 86 144 96 126 92" fill="#234f9a" stroke="#9ab0d4" stroke-width=".95" opacity=".95"/>
          <!-- spiral biru muda outline -->
          <path d="M144 76 C162 54 182 56 184 74 C186 92 168 102 150 94 C140 88 136 76 146 68 C152 64 158 68 158 74 C158 80 152 84 146 82" fill="none" stroke="#b8c7e3" stroke-width="1.2"/>
          <path d="M182 60 C192 44 206 42 212 54 C218 66 208 80 194 76 C190 74 188 70 190 66" fill="none" stroke="#b8c7e3" stroke-width="1.05"/>
          <path d="M108 60 C118 46 134 44 142 56 C148 68 138 80 124 76" fill="none" stroke="#b8c7e3" stroke-width=".95"/>
          <!-- isian gringsing kecil -->
          <path d="M168 48 C176 38 188 36 194 46 C200 56 190 66 178 62" fill="none" stroke="#b8c7e3" stroke-width=".85"/>
        </g>
        <!-- awan 2 -->
        <g transform="translate(222,4) scale(.90)">
          <path d="M24 112 C24 62 70 32 118 46 C140 22 180 16 200 46 C224 80 192 118 144 112 C158 94 140 72 118 80 C100 84 88 98 100 114 C82 108 40 102 24 112 Z" fill="#0f2a5a" stroke="#8fa6cc" stroke-width="1.7"/>
          <path d="M40 110 C40 70 72 50 108 60 C126 40 166 34 182 60" fill="#14346e" stroke="#8fa6cc" stroke-width="1.35"/>
          <path d="M144 76 C162 54 182 56 184 74 C186 92 168 102 150 94" fill="none" stroke="#b8c7e3" stroke-width="1.2"/>
        </g>
        <!-- awan 3 bawah -->
        <g transform="translate(58,176) scale(.97)">
          <path d="M24 112 C24 62 70 32 118 46 C140 22 180 16 200 46 C224 80 192 118 144 112 C158 94 140 72 118 80" fill="#0f2a5a" stroke="#8fa6cc" stroke-width="1.7"/>
          <path d="M40 110 C40 70 72 50 108 60 C126 40 166 34 182 60" fill="#14346e" stroke="#8fa6cc" stroke-width="1.35"/>
          <path d="M144 76 C162 54 182 56 184 74" fill="none" stroke="#b8c7e3" stroke-width="1.2"/>
        </g>
        <!-- awan kecil isian -->
        <g opacity=".9" transform="translate(312,132) scale(.70)">
          <path d="M24 112 C24 62 70 32 118 46 C140 22 180 16 200 46 C224 80 192 118 144 112" fill="#0f2a5a" stroke="#8fa6cc" stroke-width="1.5"/>
          <path d="M144 76 C162 54 182 56 184 74" fill="none" stroke="#b8c7e3" stroke-width="1.1"/>
        </g>
        <g fill="none" stroke="#8fa6cc" opacity=".65">
          <path d="M350 40 C358 30 370 28 376 40 C382 52 372 62 360 58" stroke-width=".9"/>
          <path d="M372 172 C380 162 392 160 398 172 C404 184 394 194 382 190" stroke-width=".85"/>
        </g>
      </g>
    </pattern></defs>
    <rect width="100%" height="100%" fill="url(#mmD)"/>
  </svg>
</div>
<div class="min-h-full flex flex-col lg:flex-row">
  <div class="hidden lg:flex lg:w-[52%] xl:w-[56%] bg-[#0F172A] relative overflow-hidden flex-col justify-between p-8 xl:p-10">
    <div class="absolute inset-0 opacity-[0.04]" style="background-image:radial-gradient(circle at 1px 1px,white 1px,transparent 0);background-size:24px 24px"></div>
    <div class="absolute -bottom-20 -right-20 w-96 h-96 bg-[#2563EB]/20 rounded-full blur-[60px]"></div>
    <div class="relative flex items-center gap-3">
      <div class="w-10 h-10 rounded-[10px] bg-[#2563EB] flex items-center justify-center shadow-lg"><i data-lucide="graduation-cap" class="w-6 h-6 text-white"></i></div>
      <span class="text-white font-bold text-[18px] tracking-tight">Kesiswaan</span><span class="ml-2 text-[10px] tracking-widest font-semibold text-white/60 border border-white/15 rounded-full px-2 py-0.5">v1.4</span>
    </div>
    <div class="relative space-y-7 max-w-[520px]">
      <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur border border-white/10 rounded-full px-3 py-1 text-xs text-white/80"><span class="w-2 h-2 rounded-full bg-[#34D399] animate-pulse"></span> Sistem Bimbingan Konseling Terpusat</div>
      <h1 class="text-[32px] xl:text-[36px] font-bold leading-[1.15] text-white">Pantau, catat,<br><span class="text-[#93C5FD]">dan bina siswa</span><br>dalam satu platform.</h1>
      <p class="text-[14px] leading-6 text-slate-300">Jembatan Guru BK, Wali Kelas, dan Siswa — menggantikan pencatatan manual dengan data NIPD/NIS yang live dan aman.</p>
    </div>
    <div class="relative flex items-center justify-between text-xs text-slate-400 border-t border-white/10 pt-4"><span>© 2025 Kesiswaan</span><span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Backup harian</span></div>
  </div>
  <div class="flex-1 flex flex-col min-h-screen lg:min-h-0">
    <div class="flex items-center justify-between px-5 sm:px-8 py-4 lg:px-10 lg:py-6">
      <div class="flex items-center gap-2.5 lg:hidden"><div class="w-8 h-8 rounded-lg bg-[#2563EB] flex items-center justify-center"><i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i></div><span class="font-bold text-sm">Kesiswaan</span><span class="text-[10px] font-semibold tracking-widest text-[#475569] border border-[#E2E8F0] rounded-full px-2 py-0.5">v1.4</span></div>
      <button id="themeToggle" type="button" class="ml-auto w-9 h-9 rounded-full border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#1E293B] flex items-center justify-center"><i data-lucide="moon" class="w-4 h-4 block dark:hidden"></i><i data-lucide="sun" class="w-4 h-4 hidden dark:block"></i></button>
    </div>
    <div class="flex-1 flex items-center justify-center px-5 sm:px-8 py-6">
      <div class="w-full max-w-[420px]">
        <div class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-[12px] shadow-card p-6 sm:p-7">
          <h1 class="text-[22px] font-bold tracking-tight">Masuk ke akun</h1>
          <p class="text-[13px] text-[#475569] dark:text-[#94A3B8] mt-1">Guru pakai <b>nama@belajar.id</b>.</p>
          <?php if($error): ?><div class="mt-4 rounded-input border-l-4 border-[#EF4444] bg-[#FFF1F2] px-3 py-2.5 text-[13px]"><?=htmlspecialchars($error)?></div><?php endif; ?>
          <?php if(!$dbOk): ?><div class="mt-3 rounded-input border-l-4 border-amber-500 bg-[#FFFBEB] px-3 py-2.5 text-xs">DB belum terhubung (<?=htmlspecialchars($dbMsg?:'cek Laragon MySQL Started?')?>) — start MySQL lalu refresh. <a href="cek_db.php" class="underline font-medium">Cek DB →</a></div><?php endif; ?>
          <form method="POST" action="login.php" class="space-y-4 mt-4">
            <div><label class="block text-[13px] font-medium mb-1.5">Username <span class="text-[#EF4444]">*</span></label><input name="username" id="username" required placeholder="admin atau nama@belajar.id" class="w-full h-10 px-3 rounded-input border border-[#CBD5E1] bg-white dark:bg-[#0F172A] text-sm" autocomplete="username"></div>
            <div><label class="block text-[13px] font-medium mb-1.5">Password <span class="text-[#EF4444]">*</span></label>
              <div class="relative"><input name="password" id="password" type="password" required minlength="8" placeholder="Minimal 8 karakter" class="w-full h-10 px-3 pr-10 rounded-input border border-[#CBD5E1] bg-white dark:bg-[#0F172A] text-sm" autocomplete="current-password"><button type="button" id="togglePw" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-[#64748B]"><i data-lucide="eye" class="w-4 h-4"></i></button></div>
            </div>
            <button type="submit" class="w-full h-10 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold">Masuk</button>
          </form>
        </div>
        <div class="mt-4 bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-4">
          <h3 class="text-xs font-semibold flex items-center gap-1.5"><i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i> Cara masuk</h3>
          <ul class="mt-2 space-y-1.5 text-xs text-[#475569] dark:text-[#94A3B8] leading-5 list-disc list-inside">
            <li><b>Admin:</b> minta akun ke Tata Usaha.</li>
            <li><b>Guru:</b> pakai <b>nama@belajar.id</b> sesuai Master User.</li>
            <li>Lupa password? Hubungi Admin untuk reset.</li>
          </ul>
          <div class="mt-3 flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-[#EFF6FF] text-[#2563EB] text-[11px] font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-[#2563EB]"></span> DB siap</span>
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Soft-delete aktif</span>
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-50 text-amber-700 text-[11px] font-semibold">CSRF on</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
lucide.createIcons();
const html=document.documentElement; const s=localStorage.getItem('kesiswaan_theme');
if(s==='dark'||(!s&&matchMedia('(prefers-color-scheme:dark)').matches)) html.classList.add('dark');
document.getElementById('themeToggle').onclick=()=>{ html.classList.toggle('dark'); localStorage.setItem('kesiswaan_theme',html.classList.contains('dark')?'dark':'light'); lucide.createIcons(); };
document.getElementById('togglePw').onclick=()=>{ const p=document.getElementById('password'); p.type=p.type==='password'?'text':'password'; };

</script>
</body>
</html>
