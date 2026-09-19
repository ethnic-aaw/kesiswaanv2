  </main>
  <footer class="px-4 sm:px-6 py-4 text-xs text-[#94A3B8] border-t border-[#E2E8F0] dark:border-[#334155] flex flex-wrap gap-2 justify-between">
    <span>© 2025 Kesiswaan v1.4 · Backup harian · Soft-delete aktif</span>
    <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> DB siap · PDO prepared · CSRF on</span>
  </footer>
</div>
<script>
lucide.createIcons();
// theme
const html=document.documentElement;
const saved=localStorage.getItem('kesiswaan_theme');
if(saved==='dark'||(!saved&&matchMedia('(prefers-color-scheme:dark)').matches)) html.classList.add('dark');
document.getElementById('themeToggle')?.addEventListener('click',()=>{
  html.classList.toggle('dark');
  localStorage.setItem('kesiswaan_theme',html.classList.contains('dark')?'dark':'light');
  lucide.createIcons();
});
// sidebar collapse (desktop)
const collapseBtn=document.getElementById('collapseBtn');
collapseBtn?.addEventListener('click',()=>{
  document.body.classList.toggle('sidebar-collapsed');
  localStorage.setItem('kesiswaan_collapsed',document.body.classList.contains('sidebar-collapsed')?'1':'0');
});
if(localStorage.getItem('kesiswaan_collapsed')==='1') document.body.classList.add('sidebar-collapsed');
// mobile drawer
const sidebar=document.getElementById('sidebar'), overlay=document.getElementById('overlay'), hamburger=document.getElementById('hamburger');
function openDrawer(){ sidebar.classList.add('open'); overlay.classList.remove('hidden'); }
function closeDrawer(){ sidebar.classList.remove('open'); overlay.classList.add('hidden'); }
hamburger?.addEventListener('click', openDrawer);
overlay?.addEventListener('click', closeDrawer);
// auto-dismiss alerts
setTimeout(()=>document.querySelectorAll('[data-auto-dismiss]').forEach(el=>el.remove()),3000);
</script>
<script src="/kesiswaanv2/assets/js/main.js"></script>
</body>
</html>
