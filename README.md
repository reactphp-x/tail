# reactphhp-framework-tail

## install

```
composer require reactphhp-framework -vvv
```

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Reactphp\Framework\Tail\Tail;

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

# License
MIT


