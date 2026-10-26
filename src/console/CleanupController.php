<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\yii2\assetsAuto\console;

use skeeks\yii2\assetsAuto\CompiledFilesCleaner;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Removes compiled css/js files that pages no longer use.
 *
 * ```
 * php yii assets-auto-compress/cleanup --dry-run
 * php yii assets-auto-compress/cleanup --days=7
 * ```
 *
 * Register in the console application:
 * `'controllerMap' => ['assets-auto-compress' => ['class' => CleanupController::class, 'assetsDirs' => ['@frontend/web/assets']]]`
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CleanupController extends Controller
{
    public $defaultAction = 'cleanup';

    /**
     * @var string[] asset base paths or aliases (…/web/assets)
     */
    public $assetsDirs = ['@webroot/assets'];

    /**
     * @var int retention in days since the last page that used a file
     */
    public $days = 7;

    /**
     * @var bool count only, delete nothing
     */
    public $dryRun = false;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['days', 'dryRun']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), ['n' => 'dryRun']);
    }

    /**
     * Removes compiled files unused for --days days (--dry-run only counts them).
     * @return int
     */
    public function actionCleanup()
    {
        if ((int)$this->days < 1) {
            $this->stderr("--days must be at least 1\n");
            return ExitCode::USAGE;
        }

        $dirs = CompiledFilesCleaner::compiledDirs(array_map(function ($dir) {
            return \Yii::getAlias($dir);
        }, (array)$this->assetsDirs));

        $cleaner = new CompiledFilesCleaner([
            'dirs'   => $dirs,
            'days'   => (int)$this->days,
            'dryRun' => (bool)$this->dryRun,
        ]);
        $stats = $cleaner->run();

        $this->stdout(($this->dryRun ? "Dry run, nothing deleted.\n" : "").implode("\n", $dirs).($dirs ? "\n" : "No compiled directories.\n"));
        $this->stdout(sprintf(
            "%s files: %d (%.1f MiB), unused index entries: %d, temporary files: %d, kept: %d, errors: %d\n",
            $this->dryRun ? 'Would delete' : 'Deleted',
            $stats['files'], $stats['bytes'] / 1048576, $stats['indexes'], $stats['tmp'], $stats['kept'], $stats['errors']
        ));

        return $stats['errors'] ? ExitCode::IOERR : ExitCode::OK;
    }
}
