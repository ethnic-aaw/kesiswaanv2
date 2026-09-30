<?php $active='pengaturan_import'; $title='Import Dapodik';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_pengaturan');
require __DIR__.'/../includes/header.php';
?>
<div class="max-w-5xl space-y-4">
  <div class="flex items-center gap-2 text-xs text-[#475569] dark:text-[#94A3B8]"><a href="/kesiswaanv2/pengaturan.php" class="hover:underline">Pengaturan</a> <span>›</span> <span class="font-medium text-[#0F172A] dark:text-white">Import Dapodik</span></div>
  <?php include __DIR__.'/../siswa/_import_form.php'; ?>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
