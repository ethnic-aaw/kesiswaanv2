<?php
if(session_status()===PHP_SESSION_NONE) session_start();

function current_user(){
  return $_SESSION['user'] ?? null;
}
function require_login(){
  if(empty($_SESSION['user'])){
    header('Location: /kesiswaanv2/login.php');
    exit;
  }
}
function require_role(array $roles){
  $u = current_user();
  if(!$u || !in_array($u['role'], $roles, true)){
    http_response_code(403);
    exit('Forbidden');
  }
}
function require_roles_mutate(array $allow=['Admin','Guru BK','Wali Kelas']){
  $u=current_user();
  if(!$u || !in_array($u['role'],$allow,true)){ http_response_code(403); exit('Forbidden: role tidak diizinkan'); }
}
// ponytail: matriks hak per fitur — ubah di role_can() bila spec berubah
function role_can(string $feature): bool {
  $role = current_user()['role'] ?? '';
  return match($feature){
    'view_dashboard' => in_array($role, ['Admin','Guru BK','Wali Kelas','Siswa'], true),
    'view_siswa' => in_array($role, ['Admin','Guru BK','Wali Kelas','Siswa'], true),
    'mutate_siswa' => in_array($role, ['Admin','Guru BK','Wali Kelas'], true),
    'view_ultah' => in_array($role, ['Admin','Guru BK','Wali Kelas'], true),
    'view_user' => $role==='Admin',
    'mutate_user' => $role==='Admin',
    'view_kelas' => $role==='Admin',
    'mutate_kelas' => $role==='Admin',
    'view_pelanggaran_master' => in_array($role, ['Admin','Guru BK'], true),
    'mutate_pelanggaran_master' => in_array($role, ['Admin','Guru BK'], true),
    'view_catat_pelanggaran' => in_array($role, ['Admin','Guru BK','Wali Kelas'], true),
    'mutate_catat_pelanggaran' => in_array($role, ['Admin','Guru BK','Wali Kelas'], true),
    'view_log_pelanggaran' => in_array($role, ['Admin','Guru BK','Wali Kelas'], true),
    'view_bk' => in_array($role, ['Admin','Guru BK'], true),
    'mutate_bk' => in_array($role, ['Admin','Guru BK'], true),
    'view_bk_log' => in_array($role, ['Admin','Guru BK'], true),
    'view_pengaturan' => $role==='Admin',
    'mutate_pengaturan' => $role==='Admin',
    'edit_kesehatan' => in_array($role, ['Admin','Guru BK','Wali Kelas'], true),
    'edit_poin' => in_array($role, ['Admin','Guru BK'], true),
    // Siswa read-only: hanya biodata + poin + BK own (di detail.php dicek own)
    'view_siswa_readonly' => $role==='Siswa',
    default => false,
  };
}
function require_can(string $feature): void {
  if(!role_can($feature)){ http_response_code(403); exit('Forbidden: '.$feature); }
}
function can_change_foto(?PDO $pdo, ?int $siswaId): bool {
  $role=current_user()['role']??'';
  if($role==='Admin') return true;
  if($role==='Wali Kelas' && $pdo && $siswaId){
    $ids=wali_ampu_ids($pdo,(int)($_SESSION['user']['id']??0));
    try{
      $st=$pdo->prepare("SELECT kelas_id FROM siswa WHERE id=? AND deleted_at IS NULL LIMIT 1");
      $st->execute([$siswaId]); $cid=(int)($st->fetchColumn()??0);
      return in_array($cid,$ids,true);
    }catch(Throwable $e){ return false; }
  }
  return false;
}
// ponytail: helper scope Wali Kelas — id kelas yang diampu user login
function wali_ampu_ids(?PDO $pdo, ?int $userId): array {
  if(!$pdo || !$userId) return [];
  try{
    $st=$pdo->prepare("SELECT id FROM kelas WHERE wali_kelas_id=? AND deleted_at IS NULL");
    $st->execute([$userId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
  }catch(Throwable $e){ return []; }
}
function wali_ampu_names(?PDO $pdo, ?int $userId): array {
  if(!$pdo || !$userId) return [];
  try{
    $st=$pdo->prepare("SELECT nama_kelas FROM kelas WHERE wali_kelas_id=? AND deleted_at IS NULL ORDER BY nama_kelas");
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
  }catch(Throwable $e){ return []; }
}
