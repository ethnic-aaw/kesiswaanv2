<?php $active='user'; $title='Tambah User'; require __DIR__.'/../includes/header.php'; require __DIR__.'/../includes/csrf.php'; ?>
<form class="max-w-2xl bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5 sm:p-6 space-y-4" onsubmit="return submitUser(event)">
<?=csrf_field()?>
<div class="grid sm:grid-cols-2 gap-4">
  <label class="block sm:col-span-2"><span class="text-[13px] font-medium">Nama Lengkap <span class="text-red-500">*</span></span><input id="nama" required placeholder="Nama" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Username <span class="text-red-500">*</span></span><input id="username" required placeholder="admin01 atau nama@belajar.id" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><span class="text-[11px] text-[#475569] dark:text-[#94A3B8]">Guru BK wajib @belajar.id</span></label>
  <label class="block"><span class="text-[13px] font-medium">Password <span class="text-red-500">*</span></span>
    <div class="relative mt-1"><input id="pw" type="password" required minlength="8" placeholder="Min 8 karakter" class="w-full h-10 px-3 pr-9 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><button type="button" onclick="this.previousElementSibling.type=this.previousElementSibling.type==='password'?'text':'password'" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-[#94A3B8]"><i data-lucide="eye" class="w-4 h-4"></i></button></div>
  </label>
  <label class="block"><span class="text-[13px] font-medium">Role <span class="text-red-500">*</span></span><select id="role" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option>Admin</option><option>Guru BK</option><option>Wali Kelas</option><option>Siswa</option></select></label>
  <label id="kelasWrap" class="hidden"><span class="text-[13px] font-medium">Kelas Diampu</span><select class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option value="">— Pilih —</option><option>XI IPA 1</option><option>X IPS 2</option><option>XII IPA 3</option></select><span class="text-[11px] text-[#475569] dark:text-[#94A3B8]">Set kelas.wali_kelas_id otomatis</span></label>
  <label class="block"><span class="text-[13px] font-medium">NIP (opsional)</span><input placeholder="NIP guru" class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"></label>
  <label class="block"><span class="text-[13px] font-medium">Status</span><select class="mt-1 w-full h-10 px-3 rounded-input border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm"><option>Aktif</option><option>Nonaktif</option></select></label>
</div>
<div class="flex gap-2"><button class="h-10 px-5 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Simpan</button><a href="/kesiswaanv2/user/index.php" class="h-10 px-5 rounded-input border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm inline-flex items-center">Batal</a></div>
</form>
<script>
const role=document.getElementById('role'), wrap=document.getElementById('kelasWrap');
function sync(){ wrap.classList.toggle('hidden', role.value!=='Wali Kelas'); }
role.addEventListener('change',sync); sync();
function submitUser(e){
  e.preventDefault();
  const u=document.getElementById('username').value.trim(), r=role.value;
  if(r==='Guru BK' && !/^[^\s@]+@belajar\.id$/i.test(u)) return toast('Guru BK wajib format nama@belajar.id','error');
  toast('User dibuat — POST /api/users (bcrypt hash)'); setTimeout(()=>location.href='/kesiswaanv2/user/index.php',600); return false;
}
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
