<?php
namespace GG\Cache;

class FileCache
{
    private $_cache_dir;
    private $_index_file;
    private $_status_file;

    public function __construct($cache_dir)
    {
        if (!is_dir($cache_dir)) {
            $oldmask = umask(0);
            mkdir($cache_dir, 0777, TRUE);
            umask($oldmask);
        }

        $this->_cache_dir = rtrim($cache_dir, '/');
        $this->_index_file = $this->_cache_dir . '/index';
        $this->_status_file = $this->_cache_dir . '/status';
    }

    private function _setIndex($file, $expire)
    {
        if (!file_exists($this->_index_file)) {
            if (!touch($this->_index_file)) {
                log_message("[FileCache] Create index file fail", LOG_ERR);

                return FALSE;
            }
        }

        $fh = @fopen($this->_index_file, 'r+b');

        if (!$fh) {
            return FALSE;
        }

        if (flock($fh, LOCK_EX)) {
            $index = [];
            $filesize = filesize($this->_index_file);
            if ($filesize > 0) {
                $content = fread($fh, $filesize);
                $index = unserialize($content);
            }
            $index[$file] = $expire;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, serialize($index));
            flush();
            flock($fh, LOCK_UN);
        } else {
            log_message("[FileCache] Get index file[" . $this->_index_file . '] write lock fail', LOG_ERR);
            fclose($fh);

            return FALSE;
        }

        fclose($fh);

        $this->checkScan();

        return TRUE;
    }

    public function scan()
    {
        if (!file_exists($this->_index_file)) return;

        log_message('[FileCache] start scan...', LOG_INFO);

        $fh = fopen($this->_index_file, 'r+b');

        if (!$fh) {

            return FALSE;
        }

        if (flock($fh, LOCK_EX)) {
            $filesize = filesize($this->_index_file);
            if ($filesize > 0) {
                $content = fread($fh, $filesize);
                $index = unserialize($content);
                if ($index) {
                    $now = time();
                    $remove_count = 0;
                    foreach (array_keys($index) as $file) {
                        if ($index[$file] < $now) {
                            if (@unlink($this->_cache_dir . '/' . $file)) {
                                log_message("[FileCache] Delete expire file:$file", LOG_INFO);
                                unset($index[$file]);
                                $remove_count++;
                            }
                        }
                    }

                    if ($remove_count > 0) {
                        ftruncate($fh, 0);
                        rewind($fh);
                        fwrite($fh, serialize($index));
                    }
                }
            }
            flock($fh, LOCK_UN);
        }

        fclose($fh);

        log_message('[FileCache] finish scan...', LOG_INFO);

        return TRUE;
    }

    public function checkScan()
    {
        static $last_scan_time = 0;

        if ($last_scan_time == 0 && file_exists($this->_status_file)) {
            $fh = fopen($this->_status_file, 'r');
            $stat = fstat($fh);
            fclose($fh);
            $last_scan_time = $stat['mtime'];
        }
        if (time() - $last_scan_time > 300) {
            if ($this->scan()) {
                $last_scan_time = time();
                touch($this->_status_file);
            }
        }
    }

    public function set($key, $content, $expire = 300)
    {
        $key = md5($key);

        $file = $this->_cache_dir . "/$key";

        $fh = @fopen($file, 'w+b');

        if (!$fh) {
            log_message("[FileCache] Create cache file[{$file}] fail!", LOG_ERR);

            return FALSE;
        }

        $expire += time();
        fwrite($fh, $expire . "\n");
        fwrite($fh, $content);
        fclose($fh);

        $this->_setIndex($key, $expire);
    }

    public function getUrlCache()
    {
        return $this->get(self::getCacheKeyFromUrl());
    }

    public function get($key)
    {
        $this->checkScan();
        $key = md5($key);

        $file = $this->_cache_dir . "/$key";

        if (!file_exists($file)) {

            return FALSE;
        }

        $fh = @fopen($file, 'r+b');

        if (!$fh) {
            log_message("[FileCache] Open cache file[{$file}] fail!", LOG_ERR);

            return FALSE;
        }

        $line = stream_get_line($fh, 512, "\n");

        $expire_time = intval(trim($line));

        if ($expire_time < time()) {
            fclose($fh);
            @unlink($file);

            return FALSE;
        }

        $content = fread($fh, filesize($file));

        fclose($fh);

        return $content;
    }


    public static function getCacheKeyFromUrl()
    {
        $url = $_SERVER['REQUEST_URI'];
        $segments = explode('?', $url);
        if (count($segments) > 1) {
            $segments[1] = preg_replace('@\b_\w+=[^&]+(&|$)@', '', $segments[1]);
        }
        $key = implode('?', $segments);

        $key = md5($key);

        return $key;
    }
}

