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
