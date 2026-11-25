<?php

if (!is_dir('/tmp/views')) {
    mkdir('/tmp/views', 0755, true);
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

require __DIR__ . '/../public/index.php';