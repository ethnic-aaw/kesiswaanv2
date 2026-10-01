<?php
require __DIR__.'/config/db.php';
// import excel also logs to dapodik_sync_log via do_import_excel.php — but preview rincian not stored
// check most recent failed row: we can re-parse? check soft-deleted siswa who failed duplicate?
echo "siswa deleted:\n";
foreach($pdo->query("SELECT id,nipd,nama,deleted_at FROM siswa WHERE deleted_at IS NOT NULL") as $r) print_r($r);
echo "\n peserta_didik count 1569 = file Excel? Check Excel original baris = header + 1570?\n";
echo "If peserta_didik 1569, file likely 1570 minus 1 header duplicate / empty.\n";
