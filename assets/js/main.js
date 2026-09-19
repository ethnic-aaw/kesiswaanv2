// ponytail: ganti dengan fetch ke Go API saat backend siap
function toast(msg,type='success'){
  const c=document.getElementById('toast-container')||(()=>{
    const d=document.createElement('div');d.id='toast-container';d.className='fixed top-4 right-4 z-50 space-y-2';document.body.appendChild(d);return d;
  })();
  const bg=type==='success'?'border-emerald-500 bg-[#F0FDF4] text-emerald-800':'border-red-500 bg-[#FFF1F2] text-red-800';
  const el=document.createElement('div');el.className=`rounded-lg border-l-4 ${bg} px-4 py-3 text-sm shadow-lg min-w-[280px] flex items-center justify-between gap-3`;
  el.innerHTML=`<span>${msg}</span><button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100">×</button>`;
  c.appendChild(el); setTimeout(()=>el.remove(),3000);
}
function confirmModal(msg,cb){
  if(confirm(msg)) cb();
}
// table sort helper
document.querySelectorAll('[data-sort]').forEach(th=>{
  th.style.cursor='pointer';
  th.addEventListener('click',()=>toast('Sort: '+th.dataset.sort+' (wire ke API Go: ?sort='+th.dataset.sort+')','success'));
});
// mobile: tabel -> kartu sudah di-handle via CSS hidden/md:table
