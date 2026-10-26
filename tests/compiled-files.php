<?php
define('YII_ENABLE_ERROR_HANDLER', false);
// Usage: php tests/compiled-files.php /path/to/vendor/autoload.php
// Content-addressed compiled files, usage marks and cleanup on a temporary directory.
$loader = require $argv[1];
$loader->addPsr4('skeeks\\yii2\\assetsAuto\\', __DIR__ . '/../src', true);
require dirname($argv[1]) . '/yiisoft/yii2/Yii.php';

use skeeks\yii2\assetsAuto\AssetsAutoCompressComponent;
use skeeks\yii2\assetsAuto\CompiledFilesCleaner;

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function listDir($dir)
{
    return array_values(array_diff(scandir($dir), ['.', '..']));
}

function indexEntries($dir)
{
    return array_values(preg_grep(CompiledFilesCleaner::INDEX_PATTERN, listDir("$dir/.index")));
}

// A page whose usage mark refresh runs a callback just before taking the lock.
class RacingComponent extends AssetsAutoCompressComponent
{
    public $beforeMark;
    public $beforeUsageMark;
    public $failLock = false;
    public $failTouch = false;

    protected function _usageMark($path)
    {
        if ($this->beforeUsageMark) {
            call_user_func($this->beforeUsageMark, $path);
        }
        return parent::_usageMark($path);
    }

    protected function _lockShared($rootDir)
    {
        return $this->failLock ? null : parent::_lockShared($rootDir);
    }

    protected function _touch($path)
    {
        $fail = is_callable($this->failTouch) ? call_user_func($this->failTouch, $path) : $this->failTouch;
        return $fail ? false : parent::_touch($path);
    }

    protected function _markUsed($rootDir, array $paths)
    {
        if ($this->beforeMark) {
            call_user_func($this->beforeMark, $paths);
        }
        return parent::_markUsed($rootDir, $paths);
    }
}

// A cleanup that runs a callback after deciding to unlink, just before the lock.
class RacingCleaner extends CompiledFilesCleaner
{
    public $beforeLock;

    protected function _lock($dir)
    {
        if ($this->beforeLock) {
            call_user_func($this->beforeLock);
        }
        return parent::_lock($dir);
    }
}

$_SERVER['DOCUMENT_ROOT'] = '/var/www';
$temp = sys_get_temp_dir() . '/skeeks-compiled-' . bin2hex(random_bytes(8));
mkdir($temp . '/assets', 0700, true);
mkdir($temp . '/css');
mkdir($temp . '/js');
file_put_contents($temp . '/css/a.css', 'a { color: #ffffff; }');
file_put_contents($temp . '/js/a.js', 'var a = 1;');
new yii\web\Application([
    'id' => 'compiled-test', 'basePath' => $temp, 'vendorPath' => dirname($argv[1]),
    'components' => [
        'request' => ['cookieValidationKey' => 'test', 'scriptFile' => $temp . '/index.php', 'scriptUrl' => '/index.php', 'hostInfo' => 'https://example.test', 'url' => '/test'],
        'assetManager' => ['basePath' => $temp . '/assets', 'baseUrl' => '/assets'],
    ],
]);
$component = new RacingComponent(['webroot' => $temp]);
$css = new ReflectionMethod($component, '_processingCssFiles');
$css->setAccessible(true);
$js = new ReflectionMethod($component, '_processingJsFiles');
$js->setAccessible(true);
$cssSet = function ($v) { return ["/css/a.css?v=$v" => "<link href=\"/css/a.css?v=$v\">"]; };
$jsSet = function ($v) { return ["/js/a.js?v=$v" => "<script src=\"/js/a.js?v=$v\"></script>"]; };
$cssDir = $temp . '/assets/css-compress';
$jsDir = $temp . '/assets/js-compress';
$compiled = function ($dir) { return array_values(preg_grep('/^[0-9a-f]{32}\.(css|js)$/', listDir($dir))); };

try {
    // Names follow the content; a repeated or republished set reuses the file.
    $first = $css->invoke($component, $cssSet(1));
    $url = array_key_first($first);
    $name = basename($url);
    check(preg_match('/^\/assets\/css-compress\/[0-9a-f]{32}\.css$/', $url), 'Unexpected compiled URL ' . $url);
    check($name === md5(file_get_contents("$cssDir/$name")) . '.css', 'Name is not the content hash.');
    check($first === $css->invoke($component, $cssSet(1)), 'Repeated request changed the result.');
    check(array_key_first($css->invoke($component, $cssSet(2))) === $url, 'Same content under a new mtime got a new file.');
    check(count($compiled($cssDir)) === 1, 'Duplicate compiled file written.');
    check(count(indexEntries($cssDir)) === 2 && is_file("$cssDir/.index/.since"), 'Expected two index entries and .since.');
    check(!preg_grep('/^\.tmp-/', listDir($cssDir)), 'Temporary file left behind.');

    file_put_contents($temp . '/css/a.css', 'a { color: #000000; }');
    $changed = array_key_first($css->invoke($component, $cssSet(3)));
    check($changed !== $url && count($compiled($cssDir)) === 2, 'Changed content did not get a new file.');

    $jsUrl = array_key_first($js->invoke($component, $jsSet(1)));
    check(array_key_first($js->invoke($component, $jsSet(2))) === $jsUrl && count($compiled($jsDir)) === 1, 'JS duplicate written.');

    // A hit refreshes a stale usage mark at most once per interval.
    $indexes = indexEntries($cssDir);
    $stale = time() - 7200;
    foreach ($indexes as $index) {
        touch("$cssDir/.index/$index", $stale);
    }
    $css->invoke($component, $cssSet(1));
    clearstatcache();
    $refreshed = array_filter($indexes, function ($index) use ($cssDir, $stale) { return filemtime("$cssDir/.index/$index") > $stale; });
    check(count($refreshed) === 1, 'Hit did not refresh exactly its own usage mark.');

    // Cleanup scenario, 7 days retention, seen from "now".
    $now = time();
    $old = $now - 10 * 86400;
    $oldName = basename($url);        // content of sets v=1, v=2
    $newName = basename($changed);    // content of set v=3
    foreach ($indexes as $index) {
        $target = file_get_contents("$cssDir/.index/$index");
        touch("$cssDir/.index/$index", $target === $oldName ? $old : $now);
        if ($target === $oldName) {
            touch("$cssDir/$oldName", $old);
        }
    }
    $tmp = CompiledFilesCleaner::TMP_PREFIX . bin2hex(random_bytes(8));
    $foreign = ['README.txt', '.tmp-notours', 'Q' . str_repeat('0', 31)];
    foreach ($foreign as $f) {
        file_put_contents("$cssDir/.index/$f", 'not an index');
        touch("$cssDir/.index/$f", $old);
        file_put_contents("$cssDir/$f", 'not compiled');
        touch("$cssDir/$f", $old);
    }
    file_put_contents("$cssDir/$tmp", 'partial');
    touch("$cssDir/$tmp", $now - 7200);
    touch("$cssDir/.index/.since", $now - 20 * 86400);

    $dry = (new CompiledFilesCleaner(['dirs' => [$cssDir], 'days' => 7, 'dryRun' => true, 'now' => $now]))->run();
    check($dry['files'] === 1 && $dry['indexes'] === 2 && $dry['tmp'] === 1, 'Dry run counted wrong: ' . json_encode($dry));
    check(is_file("$cssDir/$oldName") && is_file("$cssDir/$tmp") && count(indexEntries($cssDir)) === 3, 'Dry run deleted something.');

    $real = (new CompiledFilesCleaner(['dirs' => [$cssDir], 'days' => 7, 'now' => $now]))->run();
    check($real['files'] === 1 && $real['bytes'] > 0 && $real['indexes'] === 2 && $real['tmp'] === 1 && $real['errors'] === 0, 'Cleanup counted wrong: ' . json_encode($real));
    check(!is_file("$cssDir/$oldName") && !is_file("$cssDir/$tmp"), 'Unused file or temporary file kept.');
    check(is_file("$cssDir/$newName"), 'Used file removed.');
    foreach ($foreign as $f) {
        check(is_file("$cssDir/.index/$f") && is_file("$cssDir/$f"), "Foreign entry $f removed.");
    }

    // Previous-scheme files (no index entry, older than the switch) go only once
    // the retention has also passed since the switch: HTML rendered before the
    // upgrade still points to them.
    $legacy = md5('legacy') . '.css';
    file_put_contents("$cssDir/$legacy", 'old scheme');
    touch("$cssDir/$legacy", $now - 30 * 86400);
    touch("$cssDir/.index/.since", $now - 86400);
    $young = (new CompiledFilesCleaner(['dirs' => [$cssDir], 'days' => 7, 'now' => $now]))->run();
    check($young['files'] === 0 && is_file("$cssDir/$legacy"), 'Legacy file removed too early: ' . json_encode($young));
    touch("$cssDir/.index/.since", $now - 8 * 86400);
    $legacyRun = (new CompiledFilesCleaner(['dirs' => [$cssDir], 'days' => 7, 'now' => $now]))->run();
    check($legacyRun['files'] === 1 && !is_file("$cssDir/$legacy") && is_file("$cssDir/$newName"), 'Legacy cleanup wrong: ' . json_encode($legacyRun));

    // Without .since (compression never ran with this version) nothing is removed.
    $bare = $temp . '/assets-bare/css-compress';
    mkdir($bare, 0700, true);
    file_put_contents("$bare/$legacy", 'old scheme');
    touch("$bare/$legacy", $now - 30 * 86400);
    $none = (new CompiledFilesCleaner(['dirs' => [$bare], 'days' => 7, 'now' => $now]))->run();
    check($none['files'] === 0 && is_file("$bare/$legacy"), 'Removed files of a directory without the new scheme.');

    // Race: cleanup decided to unlink, a page refreshes the mark before the
    // exclusive lock is taken. The re-check under the lock keeps the file.
    $raceSet = function ($v) { return ["/css/a.css?r=$v" => "<link href=\"/css/a.css?r=$v\">"]; };
    $raceName = basename(array_key_first($css->invoke($component, $raceSet(1))));
    $raceIndex = array_values(array_filter(indexEntries($cssDir), function ($i) use ($cssDir, $raceName) {
        return file_get_contents("$cssDir/.index/$i") === $raceName;
    }));
    $ageRace = function () use ($cssDir, $raceIndex, $raceName, $now) {
        foreach ($raceIndex as $i) {
            touch("$cssDir/.index/$i", $now - 10 * 86400);
        }
        touch("$cssDir/$raceName", $now - 10 * 86400);
        clearstatcache();
    };
    $ageRace();
    $racing = new RacingCleaner(['dirs' => [$cssDir], 'days' => 7, 'now' => $now, 'beforeLock' => function () use ($css, $component, $raceSet) {
        $css->invoke($component, $raceSet(1));
    }]);
    $raced = $racing->run();
    clearstatcache();
    check(is_file("$cssDir/$raceName") && filesize("$cssDir/$raceName") > 0, 'Cleanup removed a file a page had just marked: ' . json_encode($raced));
    check(count(array_filter(indexEntries($cssDir), function ($i) use ($cssDir, $raceName) {
        return file_get_contents("$cssDir/.index/$i") === $raceName;
    })) >= 1, 'Cleanup removed an index entry a page had just marked.');

    // Race: a page read a stale index entry, cleanup removed the compiled file
    // before the page took the lock. The page compiles again; no empty file.
    $ageRace();
    $component->beforeMark = function ($paths) {
        unlink($paths[1]);
    };
    $recompiled = basename(array_key_first($css->invoke($component, $raceSet(1))));
    $component->beforeMark = null;
    clearstatcache();
    check($recompiled === $raceName && filesize("$cssDir/$raceName") > 0
        && md5_file("$cssDir/$raceName") . '.css' === $raceName, 'Page recreated a removed compiled file empty or wrong.');

    // Race: cleanup removed the stale index entry after the page read it and
    // before its mark was read. A vanished mark is a miss, never a served URL.
    $ageRace();
    $component->beforeUsageMark = function ($indexFile) use ($cssDir, $raceName) {
        unlink($indexFile);
        unlink("$cssDir/$raceName");
    };
    $afterVanish = basename(array_key_first($css->invoke($component, $raceSet(1))));
    $component->beforeUsageMark = null;
    clearstatcache();
    check($afterVanish === $raceName && is_file("$cssDir/$raceName") && filesize("$cssDir/$raceName") > 0, 'Served a compiled file whose index vanished.');

    // No lock: a stale mark cannot be refreshed safely and nothing can be stored,
    // so the page keeps its source files.
    $ageRace();
    $component->failLock = true;
    $noLock = $css->invoke($component, $raceSet(1));
    $component->failLock = false;
    check(array_key_first($noLock) === '/css/a.css?r=1', 'Served an unprotected compiled file without the lock.');

    // touch() refused (a file of another owner): the store rewrites both files,
    // so the served file has a fresh mark.
    $ageRace();
    $component->failTouch = true;
    $noTouch = basename(array_key_first($css->invoke($component, $raceSet(1))));
    $component->failTouch = false;
    clearstatcache();
    check($noTouch === $raceName && filemtime("$cssDir/$raceName") >= $now - 60, 'Served a compiled file with a stale mark after touch() failed.');

    // touch() refused for the second path only: the compiled file is marked
    // first, so a failed index touch never leaves a fresh index (served without
    // the lock) pointing to a stale compiled file.
    $ageRace();
    $markUsed = new ReflectionMethod($component, '_markUsed');
    $markUsed->setAccessible(true);
    $component->failTouch = function ($path) use ($cssDir) {
        return strpos($path, '/.index/') !== false;
    };
    $partial = $markUsed->invoke($component, $cssDir, ["$cssDir/$raceName", "$cssDir/.index/{$raceIndex[0]}"]);
    $component->failTouch = function ($path) use ($cssDir) {
        return strpos($path, '/.index/') === false;
    };
    $ageRace();
    $partialFirst = $markUsed->invoke($component, $cssDir, ["$cssDir/$raceName", "$cssDir/.index/{$raceIndex[0]}"]);
    $component->failTouch = false;
    clearstatcache();
    check(!$partial && !$partialFirst && filemtime("$cssDir/.index/{$raceIndex[0]}") < $now - 86400,
        'A failed touch left a fresh index entry.');

    // An expired index entry a page marked meanwhile still protects its file.
    $ageRace();
    $marking = new RacingCleaner(['dirs' => [$cssDir], 'days' => 7, 'now' => $now, 'beforeLock' => function () use ($cssDir, $raceIndex) {
        touch("$cssDir/.index/{$raceIndex[0]}");
    }]);
    $marking->run();
    clearstatcache();
    check(is_file("$cssDir/$raceName"), 'A kept index entry did not protect its compiled file.');

    // The exclusive lock of cleanup and the shared lock of pages are one lock file.
    $held = CompiledFilesCleaner::lock($cssDir, LOCK_EX);
    $other = fopen("$cssDir/.index/" . CompiledFilesCleaner::LOCK_FILE, 'r');
    check($held && !flock($other, LOCK_SH | LOCK_NB), 'Pages and cleanup do not share the lock.');
    CompiledFilesCleaner::unlock($held);
    check(flock($other, LOCK_SH | LOCK_NB), 'Lock not released.');
    fclose($other);

    // Unreadable directories and entries are errors and block deletion.
    $broken = $temp . '/assets-broken/css-compress';
    mkdir($broken, 0700, true);
    file_put_contents("$broken/.index", 'a file, not a directory');
    file_put_contents("$broken/$legacy", 'old scheme');
    touch("$broken/$legacy", $now - 30 * 86400);
    $brokenRun = (new CompiledFilesCleaner(['dirs' => [$broken, $temp . '/css/a.css'], 'days' => 7, 'now' => $now]))->run();
    check($brokenRun['errors'] === 2 && $brokenRun['files'] === 0 && is_file("$broken/$legacy"), 'Unreadable directories ignored: ' . json_encode($brokenRun));
    unlink("$broken/.index");
    mkdir("$broken/.index");
    touch("$broken/.index/.since", $now - 20 * 86400);
    touch("$broken/.index/" . md5("unreadable"));
    $entryRun = (new CompiledFilesCleaner(['dirs' => [$broken], 'days' => 7, 'now' => $now]))->run();
    check($entryRun['errors'] === 1 && $entryRun['files'] === 0 && is_file("$broken/$legacy"), 'Unreadable index entry ignored: ' . json_encode($entryRun));

    // A removed index makes the next page compile again, reusing the content name.
    $again = array_key_first($css->invoke($component, $cssSet(1)));
    check(basename($again) === $newName || is_file($cssDir . '/' . basename($again)), 'Page did not recover after cleanup.');

    // The settings hash no longer depends on unrelated component state.
    $before = $component->getSettingsHash();
    $component->usageTouchInterval = 60;
    $component->cssOptions = ['media' => 'print'];
    check($before === $component->getSettingsHash(), 'Settings hash depends on non-content options.');
    $otherRoot = new AssetsAutoCompressComponent(['webroot' => $temp . '/other']);
    check($otherRoot->getSettingsHash() !== (new AssetsAutoCompressComponent(['webroot' => $temp]))->getSettingsHash(), 'Settings hash ignores webroot.');

    echo "PASS: content names, no duplicates, atomic writes, usage marks, dry run, cleanup, legacy grace, locked races, vanished index, lock and touch failures (partial too), kept entries protect, foreign entries, read errors, webroot.\n";
} finally {
    yii\helpers\FileHelper::removeDirectory($temp);
}
