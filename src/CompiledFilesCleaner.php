<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\yii2\assetsAuto;

use yii\base\BaseObject;

/**
 * Removes compiled css/js files that pages no longer use.
 *
 * Layout of a compiled directory (css-compress, js-compress):
 * - `<md5 of content>.css|js` — compiled files, named by their content;
 * - `.index/<32 hex key>` — one small file per set of sources, holding the
 *   compiled file name; its mtime is the usage mark refreshed by page hits;
 * - `.index/.since` — when the content naming started in this directory;
 * - `.index/.lock` — the lock shared by pages and cleanup (see lock());
 * - `.tmp-<16 hex>` — files being written.
 *
 * A compiled file is kept while a live index entry points to it or while it is
 * younger than the retention. Files of the previous scheme (named by sources)
 * have no index entry; they are removed only after the retention has also passed
 * since `.since`, so HTML rendered before the upgrade keeps its files. Without
 * `.since` no compiled file is removed (expired index entries and abandoned
 * temporary files still are). Entries with other names are never touched.
 *
 * Pages refresh usage marks and store files under a shared lock; cleanup
 * re-checks the mark and unlinks under an exclusive one, so a page that marked a
 * file keeps it and a page never marks (recreates) a file already removed.
 *
 * Only directory listings, stat() and the small index entries are read;
 * compiled content is never read. If a directory or a live index entry cannot be
 * read, no compiled file is removed from that directory and an error is counted.
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CompiledFilesCleaner extends BaseObject
{
    const INDEX_DIR = '.index';
    const SINCE_FILE = '.since';
    const LOCK_FILE = '.lock';
    const TMP_PREFIX = '.tmp-';
    const INDEX_PATTERN = '/^[0-9a-f]{32}$/';
    const COMPILED_PATTERN = '/^[0-9a-f]{32}\.(css|js)$/';
    const TMP_PATTERN = '/^\.tmp-[0-9a-f]{16}$/';

    /**
     * Directories that contain compiled files (…/assets/css-compress, …/assets/js-compress).
     * @var string[]
     */
    public $dirs = [];

    /**
     * Retention in days since the last page that used a file.
     * @var int
     */
    public $days = 7;

    /**
     * Margin for the usage mark refreshed by page hits at most this often (seconds).
     * @var int
     */
    public $usageTouchInterval = 3600;

    /**
     * Unfinished temporary files older than this are removed (seconds).
     * @var int
     */
    public $tmpTtl = 3600;

    /**
     * Count only, delete nothing.
     * @var bool
     */
    public $dryRun = false;

    /**
     * Called every few hundred entries; throw from it to stop (cancellation).
     * @var callable|null
     */
    public $checkpoint;

    /**
     * @var int|null current time, for tests
     */
    public $now;

    /**
     * @param string[] $assetsDirs asset base paths (…/web/assets)
     * @return string[] existing compiled directories in them
     */
    public static function compiledDirs(array $assetsDirs)
    {
        $result = [];
        foreach ($assetsDirs as $assetsDir) {
            foreach (['css-compress', 'js-compress'] as $name) {
                $dir = rtrim($assetsDir, '/\\').'/'.$name;
                if (is_dir($dir)) {
                    $result[] = $dir;
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Takes the lock of a compiled directory.
     *
     * Pages take it shared (LOCK_SH) only to refresh a usage mark or to store a
     * file; cleanup takes it exclusive (LOCK_EX) for each unlink.
     *
     * @param string $dir compiled directory
     * @param int $operation LOCK_SH or LOCK_EX
     * @return resource|null null when the lock file cannot be opened
     */
    public static function lock($dir, $operation)
    {
        $path = $dir.'/'.self::INDEX_DIR.'/'.self::LOCK_FILE;
        // flock() needs no write access: a lock file of another user opens read-only.
        $handle = @fopen($path, 'c') ?: @fopen($path, 'r');
        if (!$handle) {
            return null;
        }
        if (!flock($handle, $operation)) {
            fclose($handle);
            return null;
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    public static function unlock($handle)
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * @return array totals: files, bytes, index entries removed, files kept, temporary files removed, errors
     */
    public function run()
    {
        $total = $this->_emptyStats();
        foreach ($this->dirs as $dir) {
            $stats = $this->cleanDir($dir);
            foreach ($stats as $name => $value) {
                $total[$name] += $value;
            }
        }

        return $total;
    }

    /**
     * @param string $dir
     * @return array
     */
    public function cleanDir($dir)
    {
        $stats = $this->_emptyStats();
        $now = $this->now ?: time();
        $fileCutoff = $now - (int)$this->days * 86400;
        $indexCutoff = $fileCutoff - (int)$this->usageTouchInterval;
        $indexDir = $dir.'/'.self::INDEX_DIR;

        // 1. Index entries: expired ones go, live ones protect their files.
        $referenced = [];
        $since = null;
        $complete = true;
        if (file_exists($indexDir)) {
            $entries = $this->_entries($indexDir, $stats);
            if ($entries === null) {
                return $stats;
            }
            foreach ($entries as $name) {
                $path = $indexDir.'/'.$name;
                if ($name === self::SINCE_FILE) {
                    $since = @filemtime($path) ?: null;
                    continue;
                }
                if ($this->_removeStaleTmp($dir, $path, $name, $now, $stats) || !preg_match(self::INDEX_PATTERN, $name)) {
                    continue;
                }
                $mtime = @filemtime($path);
                if ($mtime === false || !is_file($path)) {
                    continue;
                }
                if ($mtime < $indexCutoff) {
                    if ($this->_unlink($dir, $path, $indexCutoff, $stats)) {
                        $stats['indexes']++;
                    }
                    continue;
                }
                $target = @file_get_contents($path);
                if ($target === false || $target === '') {
                    // An unreadable live entry may protect any compiled file;
                    // entries are written atomically, so an empty one is broken too.
                    $stats['errors']++;
                    $complete = false;
                } else {
                    $referenced[$target] = true;
                }
            }
        }

        // 2. Compiled files.
        $previousSchemeExpired = $complete && $since !== null && $since < $fileCutoff;
        $entries = $this->_entries($dir, $stats);
        if ($entries === null) {
            return $stats;
        }
        foreach ($entries as $name) {
            $path = $dir.'/'.$name;
            if ($this->_removeStaleTmp($dir, $path, $name, $now, $stats)) {
                continue;
            }
            if (!preg_match(self::COMPILED_PATTERN, $name) || !is_file($path)) {
                continue;
            }
            $mtime = @filemtime($path);
            if ($mtime === false) {
                continue;
            }
            if (isset($referenced[$name]) || $mtime >= $fileCutoff || !$previousSchemeExpired) {
                $stats['kept']++;
                continue;
            }
            $size = (int)@filesize($path);
            if ($this->_unlink($dir, $path, $fileCutoff, $stats)) {
                $stats['files']++;
                $stats['bytes'] += $size;
            }
        }

        return $stats;
    }

    /**
     * @return array
     */
    protected function _emptyStats()
    {
        return ['files' => 0, 'bytes' => 0, 'indexes' => 0, 'kept' => 0, 'tmp' => 0, 'errors' => 0];
    }

    /**
     * Opens a directory for streaming its entries.
     * @param string $dir
     * @param array $stats
     * @return \Generator|null null, with an error counted, when it cannot be read
     */
    protected function _entries($dir, array &$stats)
    {
        $handle = @opendir($dir);
        if (!$handle) {
            $stats['errors']++;
            \Yii::warning("Unable to read '{$dir}'", static::class);
            return null;
        }

        return $this->_readEntries($handle);
    }

    /**
     * Streams directory entries, calling the checkpoint periodically.
     * @param resource $handle
     * @return \Generator
     */
    protected function _readEntries($handle)
    {
        try {
            $i = 0;
            while (($name = readdir($handle)) !== false) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                if ($this->checkpoint && ++$i % 500 === 0) {
                    call_user_func($this->checkpoint);
                }
                yield $name;
            }
        } finally {
            closedir($handle);
        }
    }

    /**
     * @return bool whether the entry was a temporary file (removed or not)
     */
    protected function _removeStaleTmp($dir, $path, $name, $now, array &$stats)
    {
        if (!preg_match(self::TMP_PATTERN, $name)) {
            return false;
        }
        // Pages never mark temporary files, so no lock is needed.
        if (is_file($path) && $this->_unlink($dir, $path, $now - (int)$this->tmpTtl, $stats, false)) {
            $stats['tmp']++;
        }

        return true;
    }

    /**
     * Unlinks a file unless it was used (marked) after the cutoff in the meantime.
     *
     * The mark is re-checked under the exclusive lock. A page refreshing it holds
     * the shared lock, so either the page finds the file gone (and compiles
     * again) or the cleanup finds the fresh mark (and keeps the file).
     *
     * @return bool
     */
    protected function _unlink($dir, $path, $cutoff, array &$stats, $lock = true)
    {
        $handle = null;
        if ($lock && !$this->dryRun) {
            $handle = $this->_lock($dir);
            if (!$handle) {
                $stats['errors']++;
                return false;
            }
        }
        try {
            clearstatcache(true, $path);
            $mtime = @filemtime($path);
            if ($mtime === false || $mtime >= $cutoff) {
                return false;
            }
            if ($this->dryRun) {
                return true;
            }
            if (!@unlink($path)) {
                $stats['errors']++;
                return false;
            }

            return true;
        } finally {
            if ($handle) {
                static::unlock($handle);
            }
        }
    }

    /**
     * @param string $dir
     * @return resource|null
     */
    protected function _lock($dir)
    {
        return static::lock($dir, LOCK_EX);
    }
}
