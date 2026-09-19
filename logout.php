<?php
session_start();
$_SESSION=[]; session_destroy();
// ponytail: hapus remember_tokens + cookie di Go: DELETE /api/auth/logout
setcookie(session_name(),'',time()-3600,'/');
header('Location: login.php'); exit;
