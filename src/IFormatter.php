<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\yii2\assetsAuto;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
interface IFormatter
{
    /**
     * @param string $content
     * @return string
     */
    public function format($content);
}