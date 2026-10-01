<div id="import" class="bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card p-5">
  <h3 class="font-semibold">Import dari Excel Dapodik — Master 60 Kolom</h3>
  <p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1">Upload tarikan Dapodik <code class="px-1 py-0.5 rounded bg-[#F1F5F9] dark:bg-white/10">.xls BIFF</code> / <code class="px-1 py-0.5 rounded bg-[#F1F5F9] dark:bg-white/10">.xlsx</code> / <code class="px-1 py-0.5 rounded bg-[#F1F5F9] dark:bg-white/10">.csv</code> — baca langsung via PhpSpreadsheet. Header 2 baris + merged cell didukung. Data → <code>peserta_didik</code> (60 kolom) + sinkron <code>siswa</code> &amp; auto-buat <code>kelas</code> dari Rombel.</p>
  <div class="flex flex-wrap gap-2 mt-2 text-xs text-[#475569] dark:text-[#94A3B8]">
    <span>Max 10MB</span><span>·</span><span>Header terdeteksi otomatis</span><span>·</span><span>60 kolom Dapodik → peserta_didik</span>
    <span id="apiBadge" class="ml-auto px-2 py-0.5 rounded-full bg-slate-100 dark:bg-white/10">API: mengecek…</span>
  </div>
  <?php $taDefault=''; try{ $taDefault=$pdo->query("SELECT value FROM settings WHERE key_name='tahun_ajaran_aktif' LIMIT 1")->fetchColumn()?:''; }catch(Throwable $e){} if(!$taDefault) $taDefault=date('Y').'/'.(date('Y')+1); $taDefault=preg_replace('/\s+Semester.*$/i','',$taDefault); ?>
  <form id="fImport" class="mt-4 space-y-3" onsubmit="return doPreview(event)">
    <?=csrf_field()?>
    <label class="block text-xs font-medium">Tahun Ajaran Import <span class="text-red-500">*</span> <span class="font-normal text-[#94A3B8]">auto dari file Dapodik, bisa override</span><input id="tahunAjaran" name="tahun_ajaran" value="<?=htmlspecialchars($taDefault)?>" placeholder="2025/2026" class="mt-1 w-full h-9 px-3 rounded-input border bg-white dark:bg-[#0F172A] text-sm"></label>
    <label class="flex flex-col items-center justify-center gap-2 h-28 rounded-input border-2 border-dashed border-[#CBD5E1] dark:border-[#334155] bg-[#F8FAFC] dark:bg-[#0F172A] cursor-pointer hover:bg-[#F1F5F9] dark:hover:bg-white/5">
      <i data-lucide="file-up" class="w-7 h-7 text-[#94A3B8]"></i>
      <span class="text-sm font-medium">Pilih file Excel / CSV (tarikan Dapodik)</span>
      <span id="fileName" class="text-xs text-[#475569] dark:text-[#94A3B8]">Belum ada file · .xls .xlsx .csv — BIFF didukung</span>
      <input id="fileInput" type="file" accept=".csv,.xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="hidden">
    </label>
    <div class="flex flex-wrap gap-4 text-sm">
      <label class="flex items-center gap-2"><input id="autoKelas" type="checkbox" class="rounded" checked> Auto-buat kelas dari Rombel</label>
      <label class="flex items-center gap-2"><input id="upsert" type="checkbox" class="rounded"> UPSERT (update bila NISN/NIPD sudah ada)</label>
    </div>
    <button id="btnPreview" class="h-10 px-5 rounded-input bg-[#2563EB] text-white text-sm font-semibold">Preview & Validasi (60 kolom)</button>
  </form>
  <div id="progressWrap" class="hidden mt-4">
    <div class="flex justify-between text-xs mb-1"><span id="progressLabel" class="text-[#475569]">Mengupload…</span><span id="progressPct" class="font-semibold">0%</span></div>
    <div class="h-2 rounded-full bg-[#E2E8F0] dark:bg-white/10 overflow-hidden"><div id="progressBar" class="h-full bg-[#2563EB] transition-all duration-200" style="width:0%"></div></div>
  </div>

  <div id="errBox" class="hidden rounded-lg border-l-4 border-red-500 bg-[#FFF1F2] dark:bg-red-500/10 px-4 py-3 text-sm whitespace-pre-wrap mt-4"></div>
  <div id="preview" class="hidden bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-card overflow-hidden mt-4">
    <div class="px-4 py-3 border-b border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between">
      <h3 class="font-semibold text-sm">Preview — periksa sebelum simpan</h3>
      <span id="badge" class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700">—</span>
    </div>
    <div class="overflow-x-auto max-h-[420px]">
      <table class="w-full text-sm">
        <thead class="bg-[#F1F5F9] dark:bg-[#0F172A] text-xs uppercase text-[#475569] sticky top-0"><tr><th class="px-3 py-2 text-left">#</th><th class="px-3 py-2 text-left">NIPD</th><th class="px-3 py-2 text-left">Nama</th><th class="px-3 py-2 text-left">Rombel</th><th class="px-3 py-2 text-left">JK</th><th class="px-3 py-2 text-left">Status</th></tr></thead>
        <tbody id="tbody" class="divide-y divide-[#E2E8F0]"></tbody>
      </table>
    </div>
    <div class="px-4 py-3 flex flex-wrap gap-2 items-center justify-between border-t">
      <span id="summary" class="text-xs text-[#475569]"></span>
      <div class="flex gap-2">
        <button id="btnSave" onclick="doSave()" class="h-8 px-4 rounded-input bg-[#2563EB] text-white text-xs font-semibold">Simpan ke peserta_didik + siswa</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal hasil import — tampil setelah Simpan, OK baru ke Master Siswa -->
<div id="importResultModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeImportResultModal(false)"></div>
  <div class="relative bg-white dark:bg-[#1E293B] rounded-[12px] shadow-xl max-w-lg w-full max-h-[80vh] flex flex-col overflow-hidden border border-[#E2E8F0] dark:border-[#334155]">
    <div class="px-5 py-4 border-b border-[#E2E8F0] dark:border-[#334155] flex items-start justify-between gap-3">
      <div>
        <h3 id="importModalTitle" class="font-semibold text-sm">Hasil Import</h3>
        <p id="importModalSub" class="text-xs text-[#475569] dark:text-[#94A3B8] mt-0.5"></p>
      </div>
      <button onclick="closeImportResultModal(false)" class="w-8 h-8 rounded-full hover:bg-[#F1F5F9] dark:hover:bg-white/10 flex items-center justify-center shrink-0 text-[#475569]">✕</button>
    </div>
    <div id="importModalBody" class="overflow-y-auto p-5 text-sm flex-1"></div>
    <div class="px-5 py-3 border-t border-[#E2E8F0] dark:border-[#334155] flex justify-end gap-2 bg-[#F8FAFC] dark:bg-[#0F172A]/50">
      <button onclick="closeImportResultModal(false)" class="h-9 px-4 rounded-input border border-[#CBD5E1] bg-white dark:bg-[#1E293B] text-sm font-medium">Tutup</button>
      <button onclick="closeImportResultModal(true)" class="h-9 px-5 rounded-input bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold">OK</button>
    </div>
  </div>
</div>
<script src="/kesiswaanv2/assets/js/api.js"></script>
<script>
const fileInput=document.getElementById('fileInput'), fileName=document.getElementById('fileName'), apiBadge=document.getElementById('apiBadge');
fileInput?.addEventListener('change',e=>{
  const f=e.target.files[0]; fileName.textContent=f?f.name+' · '+Math.round(f.size/1024)+'KB':'Belum ada file · .xls .xlsx .csv';
});
fetch((localStorage.getItem('kesiswaan_api_base')||'http://localhost:8080')+'/health').then(r=>r.json()).then(j=>{
  if(!apiBadge) return;
  apiBadge.textContent = j.success ? 'API Go: OK' : 'API Go: down → pakai PHP';
  apiBadge.className = j.success ? 'ml-auto px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs' : 'ml-auto px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs';
}).catch(()=>{
  if(!apiBadge) return;
  apiBadge.textContent = 'API Go: down → pakai PHP fallback';
  apiBadge.className = 'ml-auto px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs';
});
const CSRF_TOKEN = <?=json_encode($_SESSION['csrf_token']??'')?>;
function setProgress(p, label){
  const w=document.getElementById('progressWrap'), bar=document.getElementById('progressBar'), pct=document.getElementById('progressPct'), lab=document.getElementById('progressLabel');
  if(!w) return;
  w.classList.remove('hidden'); bar.style.width=p+'%'; pct.textContent=p+'%'; if(label) lab.textContent=label;
  if(p>=100) setTimeout(()=>w.classList.add('hidden'), 800);
}
function xhrPost(url, fd, onProgress){
  return new Promise((resolve, reject)=>{
    const x=new XMLHttpRequest();
    x.open('POST', url, true);
    x.upload.onprogress = e=>{ if(e.lengthComputable && onProgress) onProgress(Math.round(e.loaded/e.total*100)); };
    x.onload = ()=>{ try{ const j=JSON.parse(x.responseText); if(x.status>=200&&x.status<300 && j.success) resolve(j.data||j); else reject(new Error(j.error||j.message||x.statusText)); }catch(e){ reject(new Error(x.responseText||x.statusText)); } };
    x.onerror = ()=>reject(new Error('Network error'));
    x.send(fd);
  });
}
async function postImport(fd, auto, upsert, dry, onProgress){
  fd.set('csrf_token', CSRF_TOKEN);
  const taVal=document.getElementById('tahunAjaran')?.value?.trim()||'';
  fd.set('tahun_ajaran', taVal);
  const qs = '?dry_run='+(dry?'1':'0')+'&auto_kelas='+auto+'&upsert='+upsert+'&tahun_ajaran='+encodeURIComponent(taVal);
  try{
    const res = await apiFetch('/api/siswa/import-excel'+qs, {method:'POST', body:fd});
    if(onProgress) onProgress(100);
    return res.data||res;
  }catch(e){
    const url='/kesiswaanv2/siswa/do_import_excel.php'+qs;
    const data = await xhrPost(url, fd, onProgress);
    return data;
  }
}
let lastFile=null;
async function doPreview(e){
  e.preventDefault();
  const f=fileInput.files[0];
  if(!f) return toast('Pilih file dulu','error');
  if(f.size>10*1024*1024) return toast('Max 10MB','error');
  lastFile=f;
  const pv=document.getElementById('preview'), err=document.getElementById('errBox');
  pv.classList.add('hidden'); err.classList.add('hidden');
  setProgress(0,'Mengupload… 0%');
  let parsingDone=false;
  const parsingPromise = (async()=>{ await new Promise(r=>setTimeout(r,150)); if(!parsingDone) await animateParsing(70, 92, 'Mem-parsing 60 kolom…', 1200); })();
  try{
    const fd=new FormData(); fd.append('file', f);
    const auto=document.getElementById('autoKelas').checked ? '1':'0';
    const up=document.getElementById('upsert').checked ? '1':'0';
    const d = await postImport(fd, auto, up, true, p=>{ const v=Math.round(p*0.7); setProgress(v,'Mengupload… '+v+'%'); if(p>=100) parsingDone=true; });
    parsingDone=true;
    setProgress(96,'Mem-parsing 60 kolom… 96%');
    await new Promise(r=>setTimeout(r,120));
    setProgress(100,'Preview siap 100%');
    renderPreview(d);
  }catch(ex){
    parsingDone=true;
    setProgress(0,'Gagal 0%');
    const msg = ex.message||String(ex);
    err.textContent = msg; err.classList.remove('hidden'); toast(msg,'error');
  }
  return false;
}
function renderPreview(d){
  const tb=document.getElementById('tbody'), badge=document.getElementById('badge'), sum=document.getElementById('summary');
  const gagal=d.gagal||0, ok=d.berhasil||0, upd=d.diperbarui||0;
  const isPreview=!!d.dry_run;
  badge.textContent = isPreview ? (ok+' baru'+(upd? ' · '+upd+' akan update':'')+' · '+gagal+' bermasalah') : (ok+' tersimpan'+(upd? ' ('+upd+' update)':'')+' · '+gagal+' gagal');
  badge.className = gagal>0 ? 'text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700' : 'text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700';
  sum.textContent = isPreview ? (ok+' akan disimpan'+(upd?' ('+upd+' akan update)':'')+', '+gagal+' ditolak. (preview)') : (ok+' tersimpan'+(upd?' ('+upd+' update)':'')+', '+gagal+' gagal.');
  document.getElementById('btnSave').style.display = (isPreview && ok>0) ? '' : 'none';
  tb.innerHTML='';
  const frag=document.createDocumentFragment();
  (d.rincian||[]).forEach((r,i)=>{
    const s=r.status;
    const isGagal=s==='gagal';
    const isAkanUpdate=s==='update' && isPreview;
    const isOk=!isGagal;
    let label, cls;
    if(isAkanUpdate){ label='Akan update'; cls='bg-amber-50 text-amber-700 border border-amber-200'; }
    else if(s==='updated' || (s==='ok' && !isPreview)){ label='Tersimpan'; cls='bg-emerald-50 text-emerald-700 border border-emerald-200'; }
    else if(s==='ok'){ label='Baru'; cls='bg-[#EFF6FF] text-[#2563EB] border border-[#BFDBFE]'; }
    else { label=esc(r.error||'Gagal'); cls='bg-[#FEE2E2] text-[#DC2626]'; }
    const tr=document.createElement('tr');
    tr.className=isGagal?'bg-red-50/60':'hover:bg-[#F8FAFC]';
    tr.dataset.status=s;
    if(isGagal) tr.dataset.error=r.error||'';
    tr.innerHTML='<td class="px-3 py-2" title="Baris Excel '+r.line+'">'+(i+1)+'</td><td class="px-3 py-2 '+(isOk?'':'text-red-600')+'">'+esc(r.nipd)+'</td><td class="px-3 py-2">'+esc(r.nama)+'</td><td class="px-3 py-2 '+(r.error&&r.error.includes('kelas')?'text-red-600':'')+'">'+esc(r.kelas)+'</td><td class="px-3 py-2">'+esc(r.jk)+'</td><td class="px-3 py-2"><span class="px-2 py-1 rounded-badge text-xs font-semibold '+cls+'">'+label+'</span></td>';
    frag.appendChild(tr);
  });
  tb.appendChild(frag);
  document.getElementById('preview').classList.remove('hidden'); lucide.createIcons();
}
function showImportResultModal(d){
  const gagal=d.gagal||0, ok=d.berhasil||0, upd=d.diperbarui||0;
  const fails=(d.rincian||[]).filter(r=>r.status==='gagal');
  document.getElementById('importModalTitle').textContent = gagal>0 ? 'Import selesai — ada yang gagal' : 'Import berhasil';
  document.getElementById('importModalSub').textContent = ok+' tersimpan'+(upd?' ('+upd+' update)':'')+' · '+gagal+' gagal';
  const body=document.getElementById('importModalBody');
  if(gagal===0){
    body.innerHTML='<div class="flex flex-col items-center text-center py-4"><div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 text-xl">✓</div><p class="mt-3 font-medium">Semua data berhasil disimpan</p><p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1">Tekan OK untuk ke Master Siswa.</p></div>';
  } else {
    let h='<div class="rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-800 dark:text-amber-300 flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center shrink-0">!</span> '+gagal+' siswa gagal diimport — periksa rincian di bawah.</div>';
    h+='<div class="mt-3 flex items-center justify-between text-xs"><span class="font-semibold">Rincian gagal ('+fails.length+')</span><span class="text-[#475569]">'+ok+' ok'+(upd?' · '+upd+' update':'')+'</span></div>';
    h+='<div class="mt-2 border rounded-lg overflow-hidden divide-y max-h-[32vh] overflow-y-auto">';
    fails.forEach(r=>{
      h+='<div class="px-3 py-2 flex items-start justify-between gap-3 text-xs"><div class="min-w-0"><div class="font-medium truncate">'+esc(r.nama||'(tanpa nama)')+' · '+esc(r.nipd||'-')+'</div><div class="text-[#475569] dark:text-[#94A3B8]">Baris '+r.line+' · Rombel: '+esc(r.kelas||'-')+' · JK: '+esc(r.jk||'-')+'</div></div><span class="shrink-0 px-2 py-1 rounded-full bg-[#FEE2E2] text-[#DC2626] font-semibold text-[11px]">'+esc(r.error||'Gagal')+'</span></div>';
    });
    h+='</div><p class="mt-3 text-xs text-[#475569] dark:text-[#94A3B8]">Data yang berhasil tetap tersimpan. Tekan <b>OK</b> untuk melihat Master Siswa, atau <b>Tutup</b> untuk tetap di sini.</p>';
    body.innerHTML=h;
  }
  document.getElementById('importResultModal').classList.remove('hidden');
  document.body.style.overflow='hidden';
}
function closeImportResultModal(goMaster){
  document.getElementById('importResultModal').classList.add('hidden');
  document.body.style.overflow='';
  if(goMaster) location.href='/kesiswaanv2/siswa/index.php';
}
async function doSave(){
  if(!lastFile) return;
  const btn=document.getElementById('btnSave'); btn.disabled=true; btn.textContent='Menyimpan…';
  setProgress(0,'Menyimpan… 0%');
  try{
    const fd=new FormData(); fd.append('file', lastFile);
    const auto=document.getElementById('autoKelas').checked ? '1':'0';
    const up=document.getElementById('upsert').checked ? '1':'0';
    const d = await postImport(fd, auto, up, false, p=>{ const v=Math.round(p*0.7); setProgress(v,'Menyimpan… '+v+'%'); });
    setProgress(92,'Menyimpan ke DB… 92%');
    await new Promise(r=>setTimeout(r,200));
    setProgress(100,'Selesai 100%');
    renderPreview(d);
    showImportResultModal(d);
  }catch(ex){ setProgress(0,'Gagal 0%'); toast(ex.message,'error'); const eb=document.getElementById('errBox'); eb.textContent=ex.message; eb.classList.remove('hidden'); }
  btn.disabled=false; btn.textContent='Simpan ke peserta_didik + siswa';
}
function esc(s){ return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
</script>
