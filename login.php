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
    $ok = false; $user = null;
    $isDemo = ($username==='admin' && $password==='admin123') || (strtolower($username)==='guru@belajar.id' && $password==='guru12345');
    if ($isDemo && isset($pdo) && $pdo) {
      try{
        $chk=$pdo->prepare("SELECT status FROM users WHERE username=? AND deleted_at IS NULL LIMIT 1");
        $chk->execute([strtolower($username)==='guru@belajar.id'?'guru@belajar.id':$username]);
        $st=$chk->fetchColumn();
        if($st==='Nonaktif'){ $error='Akun nonaktif.'; $ok=false; }
        else { $ok=true; }
      }catch(Throwable $e){ $ok=true; }
    } elseif ($isDemo) { $ok=true; }
    if($ok && $isDemo){
      $demo = ($username==='admin') ? ['id'=>1,'nama'=>'Admin TU','username'=>'admin','role'=>'Admin'] : ['id'=>2,'nama'=>'Guru BK','username'=>'guru@belajar.id','role'=>'Guru BK'];
      $user=$demo;
      if (isset($pdo) && $pdo) {
        try {
          $row = $pdo->prepare("SELECT id,password_hash FROM users WHERE username=? LIMIT 1");
          $row->execute([$demo['username']]); $r=$row->fetch();
          $hash = password_hash($password, PASSWORD_BCRYPT);
          if (!$r) {
            $pdo->prepare("INSERT INTO users(nama,username,password_hash,role,status) VALUES(?,?,?,?,?)")
                ->execute([$demo['nama'],$demo['username'],$hash,$demo['role'],'Aktif']);
            if ($demo['username']==='guru@belajar.id') {
              $pdo->prepare("UPDATE users SET nip='1987654321' WHERE username=?")->execute([$demo['username']]);
            }
          } elseif (!password_verify($password, $r['password_hash'])) {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,$r['id']]);
          } else { $user['id']=$r['id']; }
        } catch(Throwable $e) {}
      }
    } elseif(!$isDemo) {
      if (isset($pdo) && $pdo) {
        try {
          $stmt = $pdo->prepare("SELECT id,nama,username,role,password_hash,status FROM users WHERE username=? AND deleted_at IS NULL LIMIT 1");
          $stmt->execute([$username]); $row=$stmt->fetch();
          if (!$row) $error='Username atau password salah.';
          elseif ($row['status']==='Nonaktif') $error='Akun nonaktif.';
          elseif (password_verify($password, $row['password_hash'])) { $ok=true; $user=$row; }
          else $error='Username atau password salah.';
        } catch(Throwable $e){ $error='DB error: '.$e->getMessage(); }
      } else {
        $error='Username atau password salah. (DB belum siap — hanya admin/guru demo yang bisa login)';
      }
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
$dbMsg=''; if(!$dbOk){ try{ new PDO("mysql:host=127.0.0.1;charset=utf8mb4",'root',''); }catch(Throwable $e){ $dbMsg=$e->getMessage(); } }
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
<style>*{font-family:Inter,system-ui,sans-serif}:focus-visible{outline:2px solid #2563EB;outline-offset:2px}</style>
</head>
<body class="h-full bg-[#F8FAFC] dark:bg-[#0F172A] text-[#0F172A] dark:text-[#F1F5F9] antialiased overflow-x-hidden">
<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
  <div class="absolute -top-32 -right-32 w-[520px] h-[520px] bg-[#2563EB]/[0.07] rounded-full blur-[80px]"></div>
  <div class="absolute -bottom-32 -left-32 w-[640px] h-[640px] bg-[#10B981]/[0.06] rounded-full blur-[80px]"></div>
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
          <?php if(!$dbOk): ?><div class="mt-3 rounded-input border-l-4 border-amber-500 bg-[#FFFBEB] px-3 py-2.5 text-xs">DB belum terhubung (<?=htmlspecialchars($dbMsg?:'cek XAMPP MySQL Started?')?>) — tapi login <b>admin/admin123</b> & <b>guru@belajar.id/guru12345</b> tetap bisa. Start MySQL lalu refresh untuk mode penuh. <a href="cek_db.php" class="underline font-medium">Cek DB →</a></div><?php endif; ?>
          <form method="POST" action="login.php" class="space-y-4 mt-4">
            <div><label class="block text-[13px] font-medium mb-1.5">Username <span class="text-[#EF4444]">*</span></label><input name="username" id="username" required placeholder="admin atau guru@belajar.id" class="w-full h-10 px-3 rounded-input border border-[#CBD5E1] bg-white dark:bg-[#0F172A] text-sm"></div>
            <div><label class="block text-[13px] font-medium mb-1.5">Password <span class="text-[#EF4444]">*</span></label>
              <div class="relative"><input name="password" id="password" type="password" required minlength="8" placeholder="Minimal 8 karakter" class="w-full h-10 px-3 pr-10 rounded-input border border-[#CBD5E1] bg-white dark:bg-[#0F172A] text-sm"><button type="button" id="togglePw" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-[#64748B]"><i data-lucide="eye" class="w-4 h-4"></i></button></div>
            </div>
            <button type="submit" class="w-full h-10 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold">Masuk</button>
          </form>
        </div>
        <div class="grid grid-cols-2 gap-3 mt-4">
          <div class="rounded-card border bg-white dark:bg-[#1E293B] p-3"><div class="text-[11px] font-semibold uppercase text-[#475569]">Admin</div><div class="text-sm font-medium">admin / admin123</div><button type="button" data-fill="admin" class="mt-2 text-xs font-medium text-[#2563EB] hover:underline">Isi otomatis →</button></div>
          <div class="rounded-card border bg-white dark:bg-[#1E293B] p-3"><div class="text-[11px] font-semibold uppercase text-[#475569]">Guru BK</div><div class="text-sm font-medium truncate">guru@belajar.id / guru12345</div><button type="button" data-fill="guru" class="mt-2 text-xs font-medium text-[#2563EB] hover:underline">Isi otomatis →</button></div>
        </div>
        <p class="text-center text-[11px] text-[#94A3B8] mt-3">Buka via <b>http://localhost/kesiswaanv2/login.php</b> (bukan file://).</p>
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
document.querySelectorAll('[data-fill]').forEach(b=>b.onclick=()=>{ if(b.dataset.fill==='admin'){ username.value='admin'; password.value='admin123'; } else{ username.value='guru@belajar.id'; password.value='guru12345'; } });
</script>
</body>
</html>
