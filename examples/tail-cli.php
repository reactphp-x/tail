<?php

/**
 * 命令行用法类似 GNU tail，便于本地调试：
 *
 *   php tail-cli.php -n 20 /path/to/app.log
 *   php tail-cli.php --name "*.log" /var/log
 *   php tail-cli.php -n 0 -s 2 file1.log dir/
 *
 * -n 0 表示不先输出末尾若干行，只跟随后续追加内容。
 */

require __DIR__ . '/../vendor/autoload.php';

use ReactphpX\Tail\Tail;

$lines = 5;
$tick = 5.0;
$names = [];
$paths = [];

$argv = $GLOBALS['argv'];
array_shift($argv);

for ($i = 0, $c = count($argv); $i < $c; $i++) {
    $a = $argv[$i];
    if ($a === '-h' || $a === '--help') {
        print_usage();
        exit(0);
    }
    if ($a === '-n' || $a === '--lines') {
        $lines = (int) ($argv[++$i] ?? 5);
        continue;
    }
    if (strncmp($a, '-n', 2) === 0 && strlen($a) > 2) {
        $lines = (int) substr($a, 2);
        continue;
    }
    if (strncmp($a, '--lines=', 8) === 0) {
        $lines = (int) substr($a, 8);
        continue;
    }
    if ($a === '-s' || $a === '--sleep' || $a === '--sleep-interval') {
        $tick = (float) ($argv[++$i] ?? 1.0);
        continue;
    }
    if (strncmp($a, '-s', 2) === 0 && strlen($a) > 2 && is_numeric(substr($a, 2))) {
        $tick = (float) substr($a, 2);
        continue;
    }
    if (strncmp($a, '--sleep=', 8) === 0) {
        $tick = (float) substr($a, 8);
        continue;
    }
    if ($a === '--name') {
        $n = $argv[++$i] ?? '';
        if ($n !== '') {
            $names[] = $n;
        }
        continue;
    }
    if (strncmp($a, '--name=', 7) === 0) {
        $n = substr($a, 7);
        if ($n !== '') {
            $names[] = $n;
        }
        continue;
    }
    if ($a[0] === '-') {
        fwrite(STDERR, "tail-cli: unknown option: $a\n");
        print_usage(STDERR);
        exit(1);
    }
    $paths[] = $a;
}

if ($paths === []) {
    print_usage(STDERR);
    exit(1);
}

$tail = new Tail();
$tail->setLastLine($lines);
$tail->setTick($tick);

foreach ($paths as $p) {
    if (is_dir($p)) {
        $tail->addPath($p, $names);
    } elseif (is_file($p)) {
        $tail->addFile($p);
    } else {
        fwrite(STDERR, "tail-cli: cannot open '{$p}' for reading: No such file or directory\n");
        exit(1);
    }
}

$tail->start();

$filePath = '';
$tail->on('start', function ($file) use (&$filePath) {
    if ($filePath !== $file) {
        $filePath = $file;
        echo PHP_EOL . '==> ' . $filePath . ' <==' . PHP_EOL;
    }
});

$tail->on('data', function ($data) {
    echo $data;
});

$tail->on('end', function ($file) {
});

function print_usage($out = null): void
{
    if ($out === null) {
        $out = STDOUT;
    }
    $a0 = $GLOBALS['argv'][0] ?? '';
    if ($a0 === '' || ($a0[0] ?? '') === '-') {
        $name = 'tail-cli.php';
    } else {
        $name = basename($a0);
    }
    $invoke = 'php ' . $name;
    fwrite($out, <<<TXT
Usage: {$invoke} [OPTION]... [FILE]...

Follow one or more files or directories (similar to "tail -f").

  -n, --lines N       Print the last N lines before following (default: 5).
                      Use 0 to skip initial tail output.
  -s, --sleep SEC     Rescan period for directory watches in seconds (default: 1).
      --name GLOB     For directories, only include files matching GLOB (repeatable).
  -h, --help          Show this help.

Examples:
  {$invoke} -n 20 /var/log/app.log
  {$invoke} --name "*.log" /var/log
  {$invoke} -n 0 a.log b.log

TXT);
}
