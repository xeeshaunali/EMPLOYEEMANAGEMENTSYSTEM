<?php
define('UPLOAD_DIR', __DIR__ . '/../../uploads');
define('BASE_URL', '/full_app'); // change if you host in a subfolder

if(!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
?>
