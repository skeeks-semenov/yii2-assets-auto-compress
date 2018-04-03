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

    public function format($html)
    {
        return HtmlCompressor::compress($html, []);
    }

}