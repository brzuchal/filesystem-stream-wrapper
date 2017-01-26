<?php declare(strict_types=1);

/**
 * Class FilesystemStreamWrapper
 *
 * This is one-file local filesystem streamWrapper class gives possibility
 * to use normal file io built in functions.
 * Docblock comments are missing deliberately due to pragmatic approach.
 *
 * @use FilesystemStreamWrapper::register('app', __DIR__ . '/myapp_direcotry')
 * @use FilesystemStreamWrapper::unregister('app')
 * @see http://php.net/manual/en/class.streamwrapper.php
 * @author Michał Brzuchalski <michal.brzuchalski@gmail.com>
 */
class FilesystemStreamWrapper
{
    private static $rootPaths = [];
    public $context;
    private $stream;
    private $dir;

    public static function register(string $protocol, string $rootPath)
    {
        self::$rootPaths[$protocol] = realpath($rootPath);
        stream_wrapper_register($protocol, self::class);
    }

    public static function unregister(string $protocol)
    {
        stream_wrapper_unregister($protocol);
    }

    private static function resolve(string $path)
    {
        foreach (self::$rootPaths as $protocol => $rootPath) {
            if (0 === strpos($path, "{$protocol}://")) {
                break;
            }
        }
        if (!empty($protocol) && array_key_exists($protocol, self::$rootPaths)) {
            return self::$rootPaths[$protocol] . substr($path, strlen($protocol) + 2);
        }
        return $path;
    }

    public function dir_closedir() : bool
    {
        try {
            if (is_resource($this->dir)) {
                closedir($this->dir);
            }
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
        return true;
    }

    public function dir_opendir(string $path, int $options) : bool
    {
        try {
            if ($this->context) {
                $this->dir = opendir(self::resolve($path), $this->context);
            } else {
                $this->dir = opendir(self::resolve($path));
            }
            return is_resource($this->dir);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function dir_readdir() : string
    {
        try {
            if (is_resource($this->dir)) {
                return (string)readdir($this->dir);
            }
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return '';
    }

    public function dir_rewinddir() : bool
    {
        try {
            if (is_resource($this->dir)) {
                rewinddir($this->dir);
            }
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
        return true;
    }

    public function mkdir(string $path, int $mode, int $options) : bool
    {
        try {
            $resolved = self::resolve($path);
            if (!@mkdir($resolved, $mode, ($options & STREAM_MKDIR_RECURSIVE) > 0, $this->context) && !is_dir($resolved)) {
                throw new RuntimeException("Unable to create directory: {$path}");
            }
            return true;
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function rename(string $oldname, string $newname) : bool
    {
        try {
            $resolved = self::resolve($oldname);
            return file_exists($resolved) && rename($resolved, self::resolve($newname), $this->context);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function rmdir(string $path, int $options) : bool
    {
        try {
            $resolved = self::resolve($path);
            if ($options & STREAM_MKDIR_RECURSIVE) {
                while (file_exists($resolved)) {
                    if (!rmdir($resolved, $this->context)) {
                        return false;
                    }
                    $resolved = dirname($resolved);
                }
                return true;
            }
            return file_exists($resolved) && rmdir($resolved, $this->context);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * @return resource
     */
    public function stream_cast(int $cast_as)
    {
        return $this->stream;
    }

    /**
     * @return void
     */
    public function stream_close()
    {
        try {
            fclose($this->stream);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
    }

    public function stream_eof() : bool
    {
        try {
            return is_resource($this->stream) && feof($this->stream);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function stream_flush() : bool
    {
        try {
            return is_resource($this->stream) && fflush($this->stream);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function stream_lock(int $operation) : bool
    {
        try {
            return is_resource($this->stream) && flock($this->stream, $operation);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }
    
    /**
     * @param   string   $path      The file path or URL to set metadata. Note that in the case of a URL,
     *                              it must be a :// delimited URL. Other URL forms are not supported.
     * @param   integer  $option    One of:
     *                                  PHP_STREAM_META_TOUCH (The method was called in response to touch())
     *                                  PHP_STREAM_META_OWNER_NAME (The method was called in response to chown() with string parameter)
     *                                  PHP_STREAM_META_OWNER (The method was called in response to chown())
     *                                  PHP_STREAM_META_GROUP_NAME (The method was called in response to chgrp())
     *                                  PHP_STREAM_META_GROUP (The method was called in response to chgrp())
     *                                  PHP_STREAM_META_ACCESS (The method was called in response to chmod())
     * @param   integer  $value     If option is
     *                                  PHP_STREAM_META_TOUCH: Array consisting of two arguments of the touch() function.
     *                                  PHP_STREAM_META_OWNER_NAME or PHP_STREAM_META_GROUP_NAME: The name of the owner
     *                                      user/group as string.
     *                                  PHP_STREAM_META_OWNER or PHP_STREAM_META_GROUP: The value owner user/group argument as integer.
     *                                  PHP_STREAM_META_ACCESS: The argument of the chmod() as integer.
     * @return  boolean             Returns TRUE on success or FALSE on failure. If option is not implemented, FALSE should be returned.
     */
    public function stream_metadata(string $path ,int $option, $value) : bool
    {
        try {
            $resolved = self::resolve($path);
            switch ($option) {
                case STREAM_META_TOUCH:
                    $currentTime = time();
                    return touch(
                        $resolved,
                        is_array($value) && array_key_exists(0, $value) ? $value[0] : $currentTime,
                        is_array($value) && array_key_exists(1, $value) ? $value[1] : $currentTime
                    );
                case STREAM_META_OWNER_NAME:
                    return chown($resolved, (string)$value);
                case STREAM_META_OWNER:
                    return chown($resolved, (int)$value);
                case STREAM_META_GROUP_NAME:
                    return chgrp($resolved, (string)$value);
                case STREAM_META_GROUP:
                    return chgrp($resolved, (int)$value);
                case STREAM_META_ACCESS:
                    return chmod($resolved, $value);
                default:
                    return false;
            }
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function stream_open(string $path, string $mode, int $options, string &$opened_path = null) : bool
    {
        try {
            return !empty($this->stream = fopen(self::resolve($path), $mode));
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function stream_read(int $count) : string
    {
        try {
            if (is_resource($this->stream)) {
                return fread($this->stream, $count);
            }
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return '';
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET) : bool
    {
        try {
            return is_resource($this->stream) && fseek($this->stream, $offset, $whence);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * AFAIK There is no need for this on local filesystem streams
     */
    public function stream_set_option(int $option, int $arg1, int $arg2) : bool
    {
        return false;
    }

    public function stream_stat() : array
    {
        try {
            if (is_resource($this->stream)) {
                return fstat($this->stream);
            }
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return [];
    }

    public function stream_tell() : int
    {
        try {
            if (is_resource($this->stream)) {
                return ftell($this->stream);
            }
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return 0;
    }

    public function stream_truncate(int $new_size) : bool
    {
        try {
            return is_resource($this->stream) && ftruncate($this->stream, $new_size);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function stream_write(string $data) : int
    {
        try {
            if (is_resource($this->stream)) {
                return fwrite($this->stream, $data);
            }
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return 0;
    }

    public function unlink(string $path) : bool
    {
        try {
            return unlink(self::resolve($path));
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * @return array|bool fstat array, but if file doesn't exists it must return false
     */
    public function url_stat(string $path, int $flags)
    {
        try {
            $resolved = self::resolve($path);
            if (!file_exists($resolved)) {
                return false;
            }
            if (($flags & STREAM_URL_STAT_LINK) && is_link($resolved)) {
                return stat(readlink($resolved));
            }
            return stat($resolved);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }
}
