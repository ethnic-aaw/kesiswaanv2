<?php $active='user'; $title='Edit User'; require __DIR__.'/../includes/header.php'; require __DIR__.'/../includes/csrf.php'; $id=$_GET['id']??1; ?>
<form class="max-w-2xl bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5 sm:p-6 space-y-4" onsubmit="event.preventDefault();const u=this.username.value.trim();if(this.role.value==='Guru BK'&&!/^[^\s@]+@belajar\.id$/i.test(u))return toast('Guru BK wajib @belajar.id','error');toast('Perubahan disimpan — PUT /api/users/<?=$id?>');setTimeout(()=>location.href='/kesiswaanv2/user/index.php',600)">
<?=csrf_field()?>
<div class="grid sm:grid-cols-2 gap-4">
  <label class="block sm:col-span-2"><span class="text-[13px] font-medium">Nama Lengkap</span><input name="nama" value="Pak Budi" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Username</span><input name="username" value="budi@belajar.id" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm font-mono"></label>
  <label class="block"><span class="text-[13px] font-medium">Role</span><select name="role" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option>Admin</option><option>Guru BK</option><option selected>Wali Kelas</option><option>Siswa</option></select></label>
  <label class="block"><span class="text-[13px] font-medium">Kelas Diampu</span><select class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option>XI IPA 1</option><option>X IPS 2</option></select></label>
  <label class="block"><span class="text-[13px] font-medium">Status</span><select class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option selected>Aktif</option><option>Nonaktif</option></select></label>
</div>
<div class="flex gap-2"><button class="h-10 px-5 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan Perubahan</button><a href="/kesiswaanv2/user/index.php" class="h-10 px-5 rounded-input border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Batal</a></div>
<p class="text-xs text-[#475569] dark:text-[#94A3B8]">Reset password via tombol di daftar — hash ulang bcrypt.</p>
</form>
<?php require __DIR__.'/../includes/footer.php'; ?>
