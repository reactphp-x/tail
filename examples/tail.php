<?php

require __DIR__ . '/../vendor/autoload.php';

use ReactphpX\Tail\Tail;

$tail = new Tail;

$tail->addPath('/var/log/k8s', ['*.log']);
// $tail->addPath('/var/log/k8s/fields-config-h5.xiaofuwu.wpjs.cc', ['*.log']);
// $tail->addFile('/var/log/k8s/fields-config-h5.xiaofuwu.wpjs.cc/access.log');

$tail->start();
$filePath = '';
$tail->on('start', function ($file) use (&$filePath) {
    if ($filePath != $file) {
        $filePath = $file;
        echo PHP_EOL . "==> " . $filePath . " <==" . PHP_EOL;
    }
});

$tail->on('data', function ($data) {
    echo $data;
});

$tail->on('end', function ($file) {
});
