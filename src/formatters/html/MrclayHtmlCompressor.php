<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\yii2\assetsAuto\formatters\html;

use skeeks\yii2\assetsAuto\IFormatter;
use yii\base\Component;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class MrclayHtmlCompressor extends Component implements IFormatter
{

    public function format($html)
    {
        return \Minify_HTML::minify($html, []);
    }

}