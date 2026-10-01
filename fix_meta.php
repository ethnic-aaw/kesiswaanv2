<?php
require __DIR__.'/config/db.php';
$pdo->exec("DELETE FROM dapodik_meta WHERE (pengunduh='A' OR LENGTH(pengunduh)<=2)");
echo "remaining: ".$pdo->query("SELECT COUNT(*) FROM dapodik_meta")->fetchColumn()."\n";
$r=$pdo->query("SELECT * FROM dapodik_meta LIMIT 1")->fetch(PDO::FETCH_ASSOC);
var_export($r);
