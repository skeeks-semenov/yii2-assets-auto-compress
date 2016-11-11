<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 05.08.2015
 */
namespace skeeks\yii2\assetsAuto;

use skeeks\yii2\assetsAuto\components\HtmlCompressor;
use yii\helpers\FileHelper;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Application;
use yii\web\JsExpression;
use yii\web\Response;
use yii\web\View;

/**
 * Class AssetsAutoCompressComponent
 * @package skeeks\yii2\assetsAuto
 */
class AssetsAutoCompressComponent extends Component implements BootstrapInterface
{
    /**
     * Enable or disable the component
     * @var bool
     */
    public $enabled = true;

    /**
     * Time in seconds for reading each asset file
     * @var int
     */
    public $readFileTimeout = 3;



    /**
     * Enable minification js in html code
     * @var bool
     */
    public $jsCompress = true;
    /**
     * Cut comments during processing js
     * @var bool
     */
    public $jsCompressFlaggedComments = true;




    /**
     * Enable minification css in html code
     * @var bool
     */
    public $cssCompress = true;






    /**
     * Turning association css files
     * @var bool
     */
    public $cssFileCompile = true;

    /**
     * Trying to get css files to which the specified path as the remote file, skchat him to her.
     * @var bool
     */
    public $cssFileRemouteCompile = false;

    /**
     * Enable compression and processing before being stored in the css file
     * @var bool
     */
    public $cssFileCompress = true;

    /**
     * Moving down the page css files
     * @var bool
     */
    public $cssFileBottom = false;

    /**
     * Transfer css file down the page and uploading them using js
     * @var bool
     */
    public $cssFileBottomLoadOnJs = false;



    /**
     * Turning association js files
     * @var bool
     */
    public $jsFileCompile = true;

    /**
     * Trying to get a js files to which the specified path as the remote file, skchat him to her.
     * @var bool
     */
    public $jsFileRemouteCompile = false;

    /**
     * Enable compression and processing js before saving a file
     * @var bool
     */
    public $jsFileCompress = true;

    /**
     * Cut comments during processing js
     * @var bool
     */
    public $jsFileCompressFlaggedComments = true;


    /**
     * Enable compression html
     * @var bool
     */
    public $htmlCompress = true;
    /**
     * @var array options for compressing output result
     *   * extra - use more compact algorithm
     *   * no-comments - cut all the html comments
     */
    public $htmlCompressOptions = [
        'extra'         => false,
        'no-comments'   => true
    ];


    /**
     * Do not connect the js files when all pjax requests.
     * @var bool
     */
    public $noIncludeJsFilesOnPjax = true;


    /**
     * @param \yii\base\Application $app
     */
    public function bootstrap($app)
    {
        if ($app instanceof \yii\web\Application)
        {
            $app->view->on(View::EVENT_END_PAGE, function(Event $e) use ($app)
            {
                /**
                 * @var $view View
                 */
                $view = $e->sender;

                if ($this->enabled && $view instanceof View && $app->response->format == Response::FORMAT_HTML && !$app->request->isAjax && !$app->request->isPjax)
                {
                    \Yii::beginProfile('Compress assets');
                    $this->_processing($view);
                    \Yii::endProfile('Compress assets');
                }

                //TODO:: Think about it
                if ($this->enabled && $app->request->isPjax && $this->noIncludeJsFilesOnPjax)
                {
                    \Yii::$app->view->jsFiles = null;
                }
            });

            //Html compressing
            $app->response->on(\yii\web\Response::EVENT_BEFORE_SEND, function (\yii\base\Event $event) use ($app)
            {
                $response = $event->sender;

                if ($this->enabled && $this->htmlCompress && $response->format == \yii\web\Response::FORMAT_HTML && !$app->request->isAjax && !$app->request->isPjax)
                {
                    if (!empty($response->data))
                    {
                        $response->data = $this->_processingHtml($response->data);
                    }

                    if (!empty($response->content))
                    {
                        $response->content = $this->_processingHtml($response->content);
                    }
                }
            });
        }
    }


    /**
     * @return string
     */
    public function getSettingsHash()
    {
        return serialize((array) $this);
    }

    /**
     * @param View $view
     */
    protected function _processing(View $view)
    {
        //Компиляция файлов js в один.
        if ($view->jsFiles && $this->jsFileCompile)
        {
            \Yii::beginProfile('Compress js files');
            foreach ($view->jsFiles as $pos => $files)
            {
                if ($files)
                {
                    $view->jsFiles[$pos] = $this->_processingJsFiles($files);
                }
            }
            \Yii::endProfile('Compress js files');
        }

        //Компиляция js кода который встречается на странице
        if ($view->js && $this->jsCompress)
        {
            \Yii::beginProfile('Compress js code');
            foreach ($view->js as $pos => $parts)
            {
                if ($parts)
                {
                    $view->js[$pos] = $this->_processingJs($parts);
                }
            }
            \Yii::endProfile('Compress js code');
        }


        //Компиляция css файлов который встречается на странице
        if ($view->cssFiles && $this->cssFileCompile)
        {
            \Yii::beginProfile('Compress css files');

            $view->cssFiles = $this->_processingCssFiles($view->cssFiles);
            \Yii::endProfile('Compress css files');
        }

        //Компиляция css файлов который встречается на странице
        if ($view->css && $this->cssCompress)
        {
            \Yii::beginProfile('Compress css code');

            $view->css = $this->_processingCss($view->css);

            \Yii::endProfile('Compress css code');
        }
        //Компиляция css файлов который встречается на странице
        if ($view->css && $this->cssCompress)
        {
            \Yii::beginProfile('Compress css code');

            $view->css = $this->_processingCss($view->css);

            \Yii::endProfile('Compress css code');
        }


        //Перенос файлов css вниз страницы, где файлы js View::POS_END
        if ($view->cssFiles && $this->cssFileBottom)
        {
            \Yii::beginProfile('Moving css files bottom');

            if ($this->cssFileBottomLoadOnJs)
            {
                \Yii::beginProfile('load css on js');

                    $cssFilesString = implode("", $view->cssFiles);
                    $view->cssFiles = [];

                    $script = Html::script(new JsExpression(<<<JS
        document.write('{$cssFilesString}');
JS
        ));

                    if (ArrayHelper::getValue($view->jsFiles, View::POS_END))
                    {
                        $view->jsFiles[View::POS_END] = ArrayHelper::merge($view->jsFiles[View::POS_END], [$script]);

                    } else
                    {
                        $view->jsFiles[View::POS_END][] = $script;
                    }


                \Yii::endProfile('load css on js');
            } else
            {
                if (ArrayHelper::getValue($view->jsFiles, View::POS_END))
                {
                    $view->jsFiles[View::POS_END] = ArrayHelper::merge($view->cssFiles, $view->jsFiles[View::POS_END]);

                } else
                {
                    $view->jsFiles[View::POS_END] = $view->cssFiles;
                }

                $view->cssFiles = [];
            }

            \Yii::endProfile('Moving css files bottom');
        }
    }

    /**
     * @param $html
     * @return string
     */
    protected function _processingHtml($html)
    {
        //$options = ['no-comments' => true];
        $options = $this->htmlCompressOptions;
        return HtmlCompressor::compress($html, $options);
    }

    /**
     * @param $parts
     * @return array
     * @throws \Exception
     */
    protected function _processingJs($parts)
    {
        $result = [];

        if ($parts)
        {
            foreach ($parts as $key => $value)
            {
                $result[$key] = \JShrink\Minifier::minify($value, ['flaggedComments' => $this->jsCompressFlaggedComments]);
            }
        }

        return $result;
    }

    /**
     * @param array $files
     * @return array
     */
    protected function _processingJsFiles($files = [])
    {
        $fileName   =  md5( implode(array_keys($files)) . $this->getSettingsHash()) . '.js';
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
                } else
                {
                    if ($this->jsFileRemouteCompile)
                    {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }

            $publicUrl                  = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl]    = Html::jsFile($publicUrl);
            return $resultFiles;
        }

        //Reading the contents of the files
        try
        {
            $resultContent  = [];
            $resultFiles    = [];
            foreach ($files as $fileCode => $fileTag)
            {
                if (Url::isRelative($fileCode))
                {
                    $contentFile = $this->fileGetContents( Url::to(\Yii::getAlias($fileCode), true) );
                    $resultContent[] = trim($contentFile) . "\n;";;
                } else
                {
                    if ($this->jsFileRemouteCompile)
                    {
                        //Пытаемся скачать удаленный файл
                        $contentFile = $this->fileGetContents( $fileCode );
                        $resultContent[] = trim($contentFile);
                    } else
                    {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }
        } catch (\Exception $e)
        {
            \Yii::error($e->getMessage(), static::className());
            return $files;
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

            if ($this->jsFileCompress)
            {
                $content = \JShrink\Minifier::minify($content, ['flaggedComments' => $this->jsFileCompressFlaggedComments]);
            }

            $page = \Yii::$app->request->absoluteUrl;
            $useFunction = function_exists('curl_init') ? 'curl extension' : 'php file_get_contents';
            $filesString = implode(', ', array_keys($files));

            \Yii::info("Create js file: {$publicUrl} from files: {$filesString} to use {$useFunction} on page '{$page}'", static::className());

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
     * @param array $files
     * @return array
     */
    protected function _processingCssFiles($files = [])
    {
        $fileName   =  md5( implode(array_keys($files)) . $this->getSettingsHash() ) . '.css';
        $publicUrl  = \Yii::getAlias('@web/assets/css-compress/' . $fileName);

        $rootDir    = \Yii::getAlias('@webroot/assets/css-compress');
        $rootUrl    = $rootDir . '/' . $fileName;

        if (file_exists($rootUrl))
        {
            $resultFiles        = [];

            foreach ($files as $fileCode => $fileTag)
            {
                if (Url::isRelative($fileCode))
                {

                } else
                {
                    if (!$this->cssFileRemouteCompile)
                    {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }

            }

            $publicUrl                  = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl]    = Html::cssFile($publicUrl);
            return $resultFiles;
        }

        //Reading the contents of the files
        try
        {
            $resultContent  = [];
            $resultFiles    = [];
            foreach ($files as $fileCode => $fileTag)
            {
                if (Url::isRelative($fileCode))
                {
                    $contentTmp         = trim($this->fileGetContents( Url::to(\Yii::getAlias($fileCode), true) ));

                    $fileCodeTmp = explode("/", $fileCode);
                    unset($fileCodeTmp[count($fileCodeTmp) - 1]);
                    $prependRelativePath = implode("/", $fileCodeTmp) . "/";

                    $contentTmp    = \Minify_CSS::minify($contentTmp, [
                        "prependRelativePath" => $prependRelativePath,

                        'compress'          => true,
                        'removeCharsets'    => true,
                        'preserveComments'  => true,
                    ]);

                    //$contentTmp = \CssMin::minify($contentTmp);

                    $resultContent[] = $contentTmp;
                } else
                {
                    if ($this->cssFileRemouteCompile)
                    {
                        //Пытаемся скачать удаленный файл
                        $resultContent[] = trim($this->fileGetContents( $fileCode ));
                    } else
                    {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }
        } catch (\Exception $e)
        {
            \Yii::error($e->getMessage(), static::className());
            return $files;
        }

        if ($resultContent)
        {
            $content = implode($resultContent, "\n");
            if (!is_dir($rootDir))
            {
                if (!FileHelper::createDirectory($rootDir, 0777))
                {
                    return $files;
                }
            }

            if ($this->cssFileCompress)
            {
                $content = \CssMin::minify($content);
            }

            $page = \Yii::$app->request->absoluteUrl;
            $useFunction = function_exists('curl_init') ? 'curl extension' : 'php file_get_contents';
            $filesString = implode(', ', array_keys($files));

            \Yii::info("Create css file: {$publicUrl} from files: {$filesString} to use {$useFunction} on page '{$page}'", static::className());


            $file = fopen($rootUrl, "w");
            fwrite($file, $content);
            fclose($file);
        }


        if (file_exists($rootUrl))
        {
            $publicUrl                  = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl]    = Html::cssFile($publicUrl);
            return $resultFiles;
        } else
        {
            return $files;
        }
    }


    /**
     * @param array $css
     * @return array
     */
    protected function _processingCss($css = [])
    {
        $newCss = [];

        foreach ($css as $code => $value)
        {
            $newCss[] = preg_replace_callback('/<style\b[^>]*>(.*)<\/style>/is', function($match)
            {
                return $match[1];
            }, $value);
        }

        $css = implode($newCss, "\n");
        $css = \CssMin::minify($css);
        return [md5($css) => "<style>" . $css . "</style>"];
    }


    /**
     * Read file contents
     *
     * @param $file
     * @return string
     */
    public function fileGetContents($file)
    {
        if (function_exists('curl_init'))
        {
            $url     =   $file;
            $ch      =   curl_init();
            $timeout =   (int) $this->readFileTimeout;

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

            $result = curl_exec($ch);
            if ($result === false)
            {
                $errorMessage = curl_error($ch);
                curl_close($ch);

                throw new \Exception($errorMessage);
            }

            $info = curl_getinfo($ch);
            if (ArrayHelper::getValue($info, 'http_code') == 404)
            {
                curl_close($ch);
                throw new \Exception("File not found: {$file}");
            }

            curl_close($ch);

            return $result;
        } else
        {
            $ctx = stream_context_create(array('http'=>
                array(
                    'timeout' => (int) $this->readFileTimeout,  //3 Seconds
                )
            ));

            return file_get_contents($file, false, $ctx);
        }
    }
}