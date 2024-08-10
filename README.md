# reactphhp-framework-tail

## install

```
composer require reactphp-x/tail -vvv
```

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use ReactphpX\Tail\Tail;

$tail = new Tail;

$tail->addPath('/var/log', ['*.log']);
$tail->addFile('/var/log/access.log');

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
```
## dependencies

extention: [inotify](https://www.php.net/manual/zh/function.inotify-init.php)

## inotify listen file limit

see
```
cat /proc/sys/fs/inotify/max_user_instances
```

edit 
```
vi /etc/sysctl.conf
fs.inotify.max_user_instances = 65532
```
take effect

```
sysctl -p
```


# License
MIT


