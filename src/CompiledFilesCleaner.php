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
 * - `.index/<key>` — one small file per set of sources, holding the compiled file
 *   name; its mtime is the usage mark refreshed by page hits;
 * - `.index/.since` — when the content naming started in this directory;
 * - `.tmp-*` — files being written.
 *
 * A compiled file is kept while a live index entry points to it or while it is
 * younger than the retention. Files of the previous scheme (named by sources)
 * have no index entry; they are removed only after the retention has also passed
 * since `.since`, so HTML rendered before the upgrade keeps its files.
 *
 * Only directory listings and stat() are used; compiled content is never read.
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CompiledFilesCleaner extends BaseObject
{
    const INDEX_DIR = '.index';
    const SINCE_FILE = '.since';
    const TMP_PREFIX = '.tmp-';

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
        foreach ($this->_entries($indexDir) as $name) {
            $path = $indexDir.'/'.$name;
            if ($name === self::SINCE_FILE) {
                $since = @filemtime($path) ?: null;
                continue;
            }
            if ($this->_removeStaleTmp($path, $name, $now, $stats)) {
                continue;
            }
            $mtime = @filemtime($path);
            if ($mtime === false) {
                continue;
            }
            if ($mtime < $indexCutoff) {
                if ($this->_unlink($path, $indexCutoff, $stats)) {
                    $stats['indexes']++;
                }
                continue;
            }
            $target = @file_get_contents($path);
            if ($target) {
                $referenced[$target] = true;
            }
        }

        // 2. Compiled files.
        $previousSchemeExpired = $since !== null && $since < $fileCutoff;
        foreach ($this->_entries($dir) as $name) {
            $path = $dir.'/'.$name;
            if ($this->_removeStaleTmp($path, $name, $now, $stats)) {
                continue;
            }
            if (!preg_match('/^[0-9a-f]{32}\.(css|js)$/', $name) || !is_file($path)) {
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
            if ($this->_unlink($path, $fileCutoff, $stats)) {
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
     * Streams directory entries, calling the checkpoint periodically.
     * @param string $dir
     * @return \Generator
     */
    protected function _entries($dir)
    {
        if (!is_dir($dir) || !($handle = @opendir($dir))) {
            return;
        }
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
    protected function _removeStaleTmp($path, $name, $now, array &$stats)
    {
        if (strpos($name, self::TMP_PREFIX) !== 0) {
            return false;
        }
        if (is_file($path) && $this->_unlink($path, $now - (int)$this->tmpTtl, $stats)) {
            $stats['tmp']++;
        }

        return true;
    }

    /**
     * Unlinks a file unless it was used (touched) after the cutoff in the meantime.
     * @return bool
     */
    protected function _unlink($path, $cutoff, array &$stats)
    {
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
    }
}
