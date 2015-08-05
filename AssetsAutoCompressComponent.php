<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 05.08.2015
 */
namespace skeeks\yii2\assetsAuto;

use skeeks\cms\helpers\FileHelper;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Application;
use yii\web\Response;
use yii\web\View;

/**
 * Class AssetsAutoCompressComponent
 * @package skeeks\yii2\assetsAuto
 */
class AssetsAutoCompressComponent extends Component implements BootstrapInterface
{
    /**
     * @var bool Включение выключение механизма компиляции
     */
    public $enabled = true;

    /**
     * @var bool Включение объединения js файлов
     */
    public $enabledJsFileCompile = true;


    /**
     * @param \yii\web\Application $app
     */
    public function bootstrap($app)
    {
        if ($app instanceof Application)
        {
            //Response::EVENT_AFTER_SEND,
            //$content = ob_get_clean();
            $app->view->on(View::EVENT_END_PAGE, function(Event $e)
            {
                /**
                 * @var $view View
                 */
                $view = $e->sender;

                if ($this->enabled && $view instanceof View && \Yii::$app->response->format == Response::FORMAT_HTML && !\Yii::$app->request->isAjax && !\Yii::$app->request->isPjax)
                {
                    $this->_processing($view);
                }
            });

            /*$app->response->on(Response::EVENT_AFTER_PREPARE, function(Event $e)
            {
                /**
                 * @var $response Response
                $response = $e->sender;
                if ($response->format == Response::FORMAT_HTML)
                {
                    //Подмена контента
                    //$response->content = '111';
                    //print_r($response);die;
                }
            });*/
        }
    }

    /**
     * @param View $view
     */
    protected function _processing(View $view)
    {
        if ($view->jsFiles && $this->enabledJsFileCompile)
        {
            foreach ($view->jsFiles as $pos => $files)
            {
                if ($files)
                {
                    $view->jsFiles[$pos] = $this->_processingJsFiles($files);
                }
            }
        }
    }

    /**
     * @param array $files
     * @return array
     */
    protected function _processingJsFiles($files = [])
    {
        $fileName   =  md5(implode(array_keys($files))) . '.js';
        $publicUrl  = \Yii::getAlias('@web/assets/js-compress/' . $fileName);

        $rootDir    = \Yii::getAlias('@webroot/assets/js-compress');
        $rootUrl    = $rootDir . '/' . $fileName;

        if (file_exists($rootUrl))
        {
            $resultFiles        = [];

            foreach ($files as $fileCode => $fileTag)
            {
                if (!Url::isRelative($fileCode))
                {
                    $resultFiles[$fileCode] = $fileTag;
                }
            }

            $publicUrl                  = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl]    = Html::jsFile($publicUrl);
            return $resultFiles;
        }

        $resultContent  = [];
        $resultFiles    = [];
        foreach ($files as $fileCode => $fileTag)
        {
            if (Url::isRelative($fileCode))
            {
                $resultContent[] = trim(file_get_contents( Url::to(\Yii::getAlias('@web' . $fileCode), true) ));
            } else
            {
                //$resultContent = trim(file_get_contents( $fileCode ));
                $resultFiles[$fileCode] = $fileTag;
            }

        }

        if ($resultContent)
        {
            $content = implode($resultContent, ";\n");
            if (!is_dir($rootDir))
            {
                if (!FileHelper::createDirectory($rootDir, 0777))
                {
                    return $files;
                }
            }

            $file = fopen($rootUrl, "w");
            fwrite($file, $content);
            fclose($file);
        }


        if (file_exists($rootUrl))
        {
            $publicUrl                  = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl]    = Html::jsFile($publicUrl);
            return $resultFiles;
        } else
        {
            return $files;
        }
    }

    /**
     * @param $filePath
     * @return string
     */
    protected function _readJsCssFile($fileCode)
    {
        if (Url::isRelative($fileCode))
        {
            $resultContent = trim(file_get_contents( Url::to(\Yii::getAlias('@web' . $fileCode), true) ));
        } else
        {
            $resultContent = trim(file_get_contents( $fileCode ));
        }

        return $resultContent;
    }
}