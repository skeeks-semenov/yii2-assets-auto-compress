<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\yii2\assetsAuto\formatters\html;

use skeeks\yii2\assetsAuto\IFormatter;
use skeeks\yii2\assetsAuto\vendor\HtmlCompressor;
use yii\base\Component;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class TylerHtmlCompressor extends Component implements IFormatter
{

    /**
     * Perform extra (possibly unsafe) compression operations
     * @var bool
     */
    public $extra = false;

    /**
     * Removes HTML comments
     * @var bool
     */
    public $noComments = true;

    /**
     * The maximum number of rows that the formatter runs on
     * @var int
     */
    public $maxNumberRows = 50000;

    /**
     * @param string $html
     * @return string
     */
    public function format($html)
    {
        $options = [
            'no-comments' => $this->noComments,
            'extra' => $this->extra,
        ];

        \Yii::beginProfile('countHtmlRows');
            $count = substr_count($html, "\n") + 1;
            \Yii::info('Number of HTML rows: ' . $count, self::class);
            if ($count > $this->maxNumberRows) {
                \Yii::info("Not run: " . self::class . ". Too many lines: {$count}. Can be no more than: {$this->maxNumberRows}", self::class);
                return $html;
            }

        \Yii::endProfile('countHtmlRows');

        $result = HtmlCompressor::compress((string) $html, $options);

        return $result;
    }

}