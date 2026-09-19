// api.js — helper fetch ke Go API (http://localhost:8080)
const API_BASE = localStorage.getItem('kesiswaan_api_base') || 'http://localhost:8080';

function getToken(){ return localStorage.getItem('kesiswaan_token') || ''; }
function setToken(t){ if(t) localStorage.setItem('kesiswaan_token', t); }
function clearToken(){ localStorage.removeItem('kesiswaan_token'); }

async function apiFetch(path, opts={}){
  const headers = Object.assign({}, opts.headers||{});
  const tok = getToken();
  if(tok) headers['Authorization'] = 'Bearer '+tok;
  if(opts.body && !(opts.body instanceof FormData) && !headers['Content-Type']) headers['Content-Type']='application/json';
  const res = await fetch(API_BASE+path, Object.assign({}, opts, {headers}));
  const data = await res.json().catch(()=>({success:false,error:res.statusText}));
  if(!res.ok) throw new Error(data.error || data.message || res.statusText);
  return data;
}
// login helper dipakai login.php
async function apiLogin(username,password){
  const r = await apiFetch('/api/auth/login',{method:'POST', body: JSON.stringify({username,password})});
  if(r.data && r.data.token) setToken(r.data.token);
  return r;
}
