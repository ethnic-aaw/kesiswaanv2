<?php
if(session_status()===PHP_SESSION_NONE) session_start();
if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
function csrf_field(){ return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['csrf_token']).'">'; }
function csrf_verify($t){ return hash_equals($_SESSION['csrf_token']??'', $t??''); }
