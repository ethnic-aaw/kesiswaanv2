<?php
// Sync via DB Dapodik diganti Import Excel dari tarikan Dapodik.
// Keep URL lama tetap hidup → redirect ke import.php
header('Location: /kesiswaanv2/siswa/import.php', true, 302);
exit;
