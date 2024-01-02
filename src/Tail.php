<?php

namespace Reactphp\Framework\Tail;

use Evenement\EventEmitter;

use React\EventLoop\Loop;
use Symfony\Component\Finder\Finder;

class Tail extends EventEmitter
{
    protected $files = [];
    protected $deleteFiles = [];

    protected $paths = [];

    protected $fileToFd = [];

    protected $tick = 1;

    protected $tickTimer;

    protected $status;

    protected $lastLine = 5;


    public function addFile($file)
    {
        if (!isset($this->files[$file])) {
            $this->files[$file]['size'] = filesize($file);
        }
        unset($this->deleteFiles[$file]);
        Loop::futureTick(function () use ($file) {
            $this->tailFile($file);
        });
        return $this;
    }

    public function addPath($path, $names = [], $tick = false)
    {

        $this->paths[] = [
            'path' => $path,
            'names' => $names,
        ];
        $this->scanPath($path, $names);
        return $this;
    }

    protected function scanPath($path, $names)
    {
        $finder = new Finder();

        $finder->files()->in((array)$path);

        foreach ($names as $name) {
            $finder->name($name);
        }

        foreach ($finder as $file) {
            if (in_array($file->getRealPath(), $this->deleteFiles)) {
                continue;
            }
            $this->addFile($file->getRealPath());
        }
    }

    public function removeFile($file)
    {
        $this->removeTailFd($file);
        unset($this->files[$file]);
        $this->deleteFiles[$file] = $file;
        return $this;
    }

    public function setLastLine($lastLine)
    {
        $this->lastLine = $lastLine;
        return $this;
    }

    public function setTick($tick)
    {
        $this->tick = $tick;
    }

    protected function tick()
    {
        foreach ($this->paths as $path) {
            $this->scanPath($path['path'], $path['names']);
        }
        foreach ($this->files as $path => $value) {
            $this->tailFile($path, $value['size']);
        }
    }

    public function start()
    {
        if ($this->status) {
            return;
        }

        $this->status = 1;

        $this->tickTimer = Loop::addPeriodicTimer($this->tick, function () {
            $this->tick();
        });
    }

    public function stop()
    {
        if (!$this->status) {
            return;
        }

        foreach ($this->fileToFd as $file => $fd) {
            $this->removeFile($file);
        }

        if ($this->tickTimer) {
            Loop::cancelTimer($this->tickTimer);
            $this->tickTimer = null;
        }
        $this->status = 0;
    }

    public function restart()
    {
        $this->stop();
        $this->start();
    }

    protected function removeTailFd($file)
    {
        if ($this->existFileFd($file)) {
            $fd = $this->getFileFd($file);
            $watch_descriptor = $this->getFileFdWatchDescriptor($file);
            unset($this->fileToFd[$file]);
            Loop::removeReadStream($fd);
            inotify_rm_watch($fd, $watch_descriptor);
            @fclose($fd);
        }
    }

    protected function addFileFd($file, $fd, $watch_descriptor)
    {
        $this->fileToFd[$file] = [
            'fd' => $fd,
            'watch_descriptor' => $watch_descriptor,
        ];
    }


    protected function getFileFd($file)
    {
        return $this->fileToFd[$file]['fd'];
    }
    protected function getFileFdWatchDescriptor($file)
    {
        return $this->fileToFd[$file]['watch_descriptor'];
    }

    protected function existFileFd($file)
    {
        return isset($this->fileToFd[$file]);
    }

    protected function tailFile($file, $lastpos = 0)
    {

        if ($this->existFileFd($file)) {
            return;
        }

        $isRead = false;

        if ($this->lastLine) {
            $this->readLastLine($file, $this->lastLine);
        }

        list($fd, $watch_descriptor) = $this->watchFile($file);
        $this->addFileFd($file, $fd, $watch_descriptor);
        Loop::addReadStream($fd, function ($fd) use ($file, &$lastpos, &$isRead) {
            $buffer = $this->handleWatchFile($fd, $file, $lastpos, $isRead);
            if ($buffer === false) {
                // todo file is delete or move
            } elseif ($buffer === null) {
                // todo file is reading
            } elseif ($buffer === true) {
                // todo file read success
            } else {
                // todo file mask is:$buffer
            }
        });
    }

    protected function watchFile($file)
    {
        $fd = inotify_init();
        $watch_descriptor = inotify_add_watch($fd, $file, IN_ALL_EVENTS);
        stream_set_blocking($fd, 0);
        return [$fd, $watch_descriptor];
    }

    protected function handleWatchFile($fd, $file, &$pos, &$isRead)
    {

        $events = inotify_read($fd);

        // exit();
        foreach ($events as $event => $evdetails) {
            // React on the event type
            switch (true) {
                    // File was modified
                case ($evdetails['mask'] & IN_MODIFY):
                    // Stop watching $file for changes
                    // inotify_rm_watch($fd, $watch_descriptor);
                    // Close the inotify instance
                    // fclose($fd);
                    // Loop::removeWriteStream($fd);
                    if ($isRead) {
                        return;
                    }

                    if (!$pos) {
                        $pos = filesize($file);
                        // $pos = max(0, $pos + $this->defaultPosition);
                    };

                    $isRead = true;

                    $pos = $this->readFile($file, $pos);

                    $isRead = false;

                    // return the new data and leave the function
                    return true;
                    // be a nice guy and program good code ;-)
                    break;

                    // File was moved or deleted
                case ($evdetails['mask'] & IN_MOVE):
                case ($evdetails['mask'] & IN_MOVE_SELF):
                case ($evdetails['mask'] & IN_DELETE):
                case ($evdetails['mask'] & IN_DELETE_SELF):
                    $this->removeFile($file);
                    // Return a failure
                    return false;
                    break;
                default:
                    return $evdetails['mask'];
                    break;
            }
        }
    }

    protected function readFile($file, $pos)
    {
        // open the file
        $fp = fopen($file, 'r');
        if (!$fp) {
            $this->removeFile($file);
            fclose($fp);
            return false;
        };

        // seek to the last EOF position
        fseek($fp, $pos);
        $this->emit('start', [$file]);

        // read until EOF
        while (!feof($fp)) {
            // $callback = $this->callback;
            // $callback(fread($fp, 8192));
            $this->emit('data', [fread($fp, 8192)]);
        }
        // save the new EOF to $pos
        $pos = ftell($fp); // (remember: $pos is called by reference)
        // close the file pointer
        fclose($fp);
        $this->emit('end', [$file]);
        return $pos;
    }

    // ref https://www.iyuu.cn/archives/441/
    protected function readLastLine($file, $line = 1)
    {
        // 文件存在并打开文件
        if (!is_file($file) || !$fp = fopen($file, 'r')) {
            return false;
        }
        $pos = -2;
        $eof = '';
        $lines = array();
        $this->emit('start', [$file]);
        while ($line > 0) {
            $str = '';
            while ($eof != "\n") {
                //在打开的文件中定位
                if (!fseek($fp, $pos, SEEK_END)) {
                    //从文件指针中读取一个字符
                    $eof = fgetc($fp);
                    $pos--;
                    $str = $eof . $str;
                } else {
                    break;
                }
            }
            // 插入到数组的开头
            array_unshift($lines, $str);
            $eof = '';
            $line--;
        }
        fclose($fp);
        $this->emit('data', [ltrim(implode('', $lines), "\n") . "\n"]);

        $this->emit('end', [$file]);
    }

    public function __destruct()
    {
        foreach ($this->fileToFd as $file => $fd) {
            $this->removeFile($file);
        }

        if ($this->tickTimer) {
            Loop::cancelTimer($this->tickTimer);
            $this->tickTimer = null;
        }
    }
}
