<?php $active='pengaturan_pelanggaran'; $title='Import Data Pelanggaran';
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/csrf.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/../includes/auth.php';
if(empty($_SESSION['user'])){ header('Location: /kesiswaanv2/login.php'); exit; }
require_can('view_pengaturan');
require __DIR__.'/../includes/header.php';
?>
<div class="max-w-5xl space-y-4">
  <div class="flex items-center gap-2 text-xs text-[#475569] dark:text-[#94A3B8]"><a href="/kesiswaanv2/pengaturan.php" class="hover:underline">Pengaturan</a> <span>›</span> <span class="font-medium text-[#0F172A] dark:text-white">Import Data Pelanggaran</span></div>
  <div id="importPel" class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5">
    <h3 class="font-semibold">Import dari Excel — Data Pelanggaran (Master Jenis)</h3>
    <p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1">Download <a href="/kesiswaanv2/pelanggaran/export.php?fmt=xlsx" class="text-[#2563EB] underline">Template XLS (isi DB saat ini)</a> → edit kolom <code>kode,nama,kategori,bobot_poin,deskripsi,konsekuensi</code> → upload. Kolom → <code>jenis_pelanggaran</code> (upsert by <code>kode</code>). Data sinkron otomatis. Max 10MB.</p>
    <div class="flex flex-wrap gap-2 mt-2 text-xs text-[#475569] dark:text-[#94A3B8]">
      <span>Max 10MB</span><span>·</span><span>Kolom: kode,nama,kategori,bobot_poin,deskripsi,konsekuensi</span><span>·</span><span>Kategori: Kedisiplinan/Tata Krama/Kekerasan/Narkoba/Lainnya</span>
      <a href="/kesiswaanv2/pelanggaran/master.php" class="ml-auto text-[#2563EB] underline">Lihat Master Pelanggaran →</a>
    </div>
    <form id="fPelImport" class="mt-4 space-y-3" onsubmit="return doPelPreview(event)">
      <?=csrf_field()?>
      <label class="flex flex-col items-center justify-center gap-2 h-28 rounded-input border-2 border-dashed border-[#CBD5E1] dark:border-[#334155] bg-[#F8FAFC] dark:bg-[#0F172A] cursor-pointer hover:bg-[#F1F5F9] dark:hover:bg-white/5">
        <i data-lucide="file-up" class="w-7 h-7 text-[#94A3B8]"></i>
        <span class="text-sm font-medium">Pilih file Excel Pelanggaran</span>
        <span id="pelFileName" class="text-xs text-[#475569] dark:text-[#94A3B8]">Belum ada file · .xls .xlsx — template DB</span>
        <input id="pelFileInput" type="file" accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="hidden">
      </label>
      <label class="flex items-center gap-2 text-sm"><input id="pelSyncFull" type="checkbox" class="rounded"> Sinkron penuh — hapus kode yang tidak ada di file (dipakai pelanggaran di-skip)</label>
      <button class="h-10 px-5 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Preview & Validasi (6 kolom)</button>
    </form>
    <div id="pelProgressWrap" class="hidden mt-4">
      <div class="flex justify-between text-xs mb-1"><span id="pelProgressLabel" class="text-[#475569]">Mengupload…</span><span id="pelProgressPct" class="font-semibold">0%</span></div>
      <div class="h-2 rounded-full bg-[#E2E8F0] dark:bg-white/10 overflow-hidden"><div id="pelProgressBar" class="h-full bg-[#2563EB] transition-all duration-200" style="width:0%"></div></div>
    </div>
    <div id="pelErrBox" class="hidden rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] dark:bg-red-500/10 px-4 py-3 text-sm whitespace-pre-wrap mt-4"></div>
    <div id="pelPreview" class="hidden bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-4">
      <div class="px-4 py-3 border-b border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between">
        <h3 class="font-semibold text-sm">Preview — periksa sebelum simpan</h3>
        <span id="pelBadge" class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700">—</span>
      </div>
      <div class="overflow-x-auto max-h-[420px]">
        <table class="w-full text-sm">
          <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] sticky top-0"><tr><th class="px-3 py-2 text-left">#</th><th class="px-3 py-2 text-left">Kode</th><th class="px-3 py-2 text-left">Nama</th><th class="px-3 py-2 text-left">Kategori</th><th class="px-3 py-2 text-left">Bobot</th><th class="px-3 py-2 text-left">Status</th></tr></thead>
          <tbody id="pelTbody" class="divide-y divide-[#E2E8F0]"></tbody>
        </table>
      </div>
      <div class="px-4 py-3 flex flex-wrap gap-2 items-center justify-between border-t">
        <span id="pelSummary" class="text-xs text-[#475569]"></span>
        <div class="flex gap-2"><button id="pelBtnSave" onclick="doPelSave()" class="h-8 px-4 rounded-input bg-[#2563EB] text-white text-xs font-semibold">Simpan ke jenis_pelanggaran</button></div>
      </div>
    </div>
  </div>
</div>
<script>
const pelFileInput=document.getElementById('pelFileInput'), pelFileName=document.getElementById('pelFileName');
pelFileInput?.addEventListener('change',e=>{ const f=e.target.files[0]; pelFileName.textContent=f?f.name+' · '+Math.round(f.size/1024)+'KB':'Belum ada file · .xls .xlsx'; });
const PEL_CSRF = <?=json_encode($_SESSION['csrf_token']??'')?>;
function pelSetProgress(p,label){ const w=document.getElementById('pelProgressWrap'), bar=document.getElementById('pelProgressBar'), pct=document.getElementById('pelProgressPct'), lab=document.getElementById('pelProgressLabel'); if(!w) return; w.classList.remove('hidden'); bar.style.width=p+'%'; pct.textContent=p+'%'; if(label) lab.textContent=label; if(p>=100) setTimeout(()=>w.classList.add('hidden'),800); }
function pelXhrPost(url,fd,onProgress){ return new Promise((resolve,reject)=>{ const x=new XMLHttpRequest(); x.open('POST',url,true); x.upload.onprogress=e=>{ if(e.lengthComputable&&onProgress) onProgress(Math.round(e.loaded/e.total*100)); }; x.onload=()=>{ try{ const j=JSON.parse(x.responseText); if(x.status>=200&&x.status<300) resolve(j); else reject(new Error(j.error||j.err||x.statusText)); }catch(e){ reject(new Error(x.responseText||x.statusText)); }}; x.onerror=()=>reject(new Error('Network error')); x.send(fd); });}
let pelLastFile=null;
async function doPelPreview(e){
  e.preventDefault(); const f=pelFileInput.files[0]; if(!f) return toast('Pilih file dulu','error'); if(f.size>10*1024*1024) return toast('Max 10MB','error'); pelLastFile=f;
  const pv=document.getElementById('pelPreview'), err=document.getElementById('pelErrBox'); pv.classList.add('hidden'); err.classList.add('hidden'); pelSetProgress(0,'Mengupload… 0%');
  try{
    const fd=new FormData(); fd.append('csrf_token',PEL_CSRF); fd.append('csv_file',f); if(document.getElementById('pelSyncFull').checked) fd.append('sync_full','1');
    const j=await pelXhrPost('/kesiswaanv2/pengaturan/do_import_pelanggaran.php?dry=1',fd,p=>pelSetProgress(Math.round(p*0.7),'Mengupload… '+p+'%'));
    pelSetProgress(100,'Preview siap 100%'); pelRenderPreview(j.data||j);
  }catch(ex){ pelSetProgress(0,'Gagal 0%'); err.textContent=ex.message||String(ex); err.classList.remove('hidden'); toast(ex.message,'error'); }
  return false;
}
function pelRenderPreview(d){
  const tb=document.getElementById('pelTbody'), badge=document.getElementById('pelBadge'), sum=document.getElementById('pelSummary');
  const gagal=d.gagal||0, ok=d.berhasil||0, del=d.dihapus||0; const isPreview=!!d.dry_run;
  badge.textContent=isPreview?(ok+' akan sinkron · '+gagal+' gagal'):(ok+' tersimpan'+(del?' · '+del+' dihapus':'')+' · '+gagal+' gagal');
  badge.className=gagal>0?'text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700':'text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700';
  sum.textContent=isPreview?(ok+' akan sinkron, '+gagal+' ditolak. (preview)'):(ok+' tersimpan'+(del?' ('+del+' dihapus)':'')+', '+gagal+' gagal.');
  document.getElementById('pelBtnSave').style.display=(isPreview&&ok>0)?'':'none';
  tb.innerHTML=''; const frag=document.createDocumentFragment();
  (d.rincian||[]).forEach(r=>{
    const isGagal=r.status==='gagal'; let label,cls; if(isGagal){ label=r.error||'Gagal'; cls='bg-[#FEE2E2] text-[#DC2626]'; } else if(r.mode==='update'||r.status==='updated'){ label='Akan update'; cls='bg-amber-50 text-amber-700 border border-amber-200'; } else { label='Baru'; cls='bg-[#EFF6FF] text-[#2563EB] border border-[#BFDBFE]'; }
    const tr=document.createElement('tr'); tr.className=isGagal?'bg-red-50/60':'hover:bg-[#F8FAFC]';
    tr.innerHTML='<td class="px-3 py-2">'+r.line+'</td><td class="px-3 py-2 '+(isGagal?'text-red-600':'')+'">'+esc(r.kode)+'</td><td class="px-3 py-2">'+esc(r.nama)+'</td><td class="px-3 py-2">'+esc(r.kategori)+'</td><td class="px-3 py-2">'+esc(String(r.bobot||''))+'</td><td class="px-3 py-2"><span class="px-2 py-1 rounded-badge text-xs font-semibold '+cls+'">'+esc(label)+'</span></td>';
    frag.appendChild(tr);
  }); tb.appendChild(frag); document.getElementById('pelPreview').classList.remove('hidden'); lucide.createIcons();
}
async function doPelSave(){
  if(!pelLastFile) return; const btn=document.getElementById('pelBtnSave'); btn.disabled=true; btn.textContent='Menyimpan…'; pelSetProgress(0,'Menyimpan… 0%');
  try{
    const fd=new FormData(); fd.append('csrf_token',PEL_CSRF); fd.append('csv_file',pelLastFile); if(document.getElementById('pelSyncFull').checked) fd.append('sync_full','1');
    const j=await pelXhrPost('/kesiswaanv2/pengaturan/do_import_pelanggaran.php',fd,p=>pelSetProgress(Math.round(p*0.7),'Menyimpan… '+p+'%'));
    pelSetProgress(100,'Selesai 100%'); const d=j.data||j; pelRenderPreview({...d,dry_run:false}); toast((d.berhasil||0)+' tersimpan'+((d.dihapus||0)?' · '+d.dihapus+' dihapus':'')+' — '+(d.gagal||0)+' gagal'); if((d.berhasil||0)>0) setTimeout(()=>location.href='/kesiswaanv2/pelanggaran/master.php',1000);
  }catch(ex){ pelSetProgress(0,'Gagal 0%'); toast(ex.message,'error'); const eb=document.getElementById('pelErrBox'); eb.textContent=ex.message; eb.classList.remove('hidden'); }
  btn.disabled=false; btn.textContent='Simpan ke jenis_pelanggaran';
}
function esc(s){ return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
