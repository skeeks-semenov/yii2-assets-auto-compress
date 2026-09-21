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

    /**
     * @param string $html
     * @return string
     */
    public function format($html)
    {
        if (!class_exists('\Minify_HTML')) {
            throw new \yii\base\InvalidConfigException(
                'MrclayHtmlCompressor requires the optional mrclay/minify package. '
                . 'Install it explicitly or use TylerHtmlCompressor.'
            );
        }

        return \Minify_HTML::minify((string) $html, []);
    }

}