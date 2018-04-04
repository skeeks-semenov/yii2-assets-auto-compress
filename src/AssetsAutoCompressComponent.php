<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\yii2\assetsAuto;

use skeeks\yii2\assetsAuto\vendor\HtmlCompressor;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\httpclient\Client;
use yii\web\JsExpression;
use yii\web\Response;
use yii\web\View;

/**
 * Automatically compile and merge files js + css + html in yii2 project
 *
 * @property string     $webroot;
 * @property IFormatter $htmlFormatter;
 *
 * @author Semenov Alexander <semenov@skeeks.com>
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
    public $readFileTimeout = 1;


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


    public $cssOptions = [];


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
     * @var array
     */
    public $jsOptions = [];

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
     * Do not connect the js files when all pjax requests.
     * @var bool
     */
    public $noIncludeJsFilesOnPjax = true;
    /**
     * @var bool|array|string|IFormatter
     */
    protected $_htmlFormatter = false;
    /**
     * @var string
     */
    protected $_webroot = '@webroot';
    /**
     * @return IFormatter|bool
     */
    public function getHtmlFormatter()
    {
        return $this->_htmlFormatter;
    }
    /**
     * @param bool|array|string|IFormatter $htmlFormatter
     * @return $this
     * @throws InvalidConfigException
     */
    public function setHtmlFormatter($htmlFormatter = false)
    {
        if (is_array($htmlFormatter) || $htmlFormatter === false) {
            $this->_htmlFormatter = $htmlFormatter;
        } elseif (is_string($htmlFormatter)) {
            $this->_htmlFormatter = [
                'class' => $htmlFormatter,
            ];
        } elseif (is_object($htmlFormatter) && $htmlFormatter instanceof IFormatter) {
            $this->_htmlFormatter = $htmlFormatter;
        } else {
            throw new InvalidConfigException("Bad html formatter!");
        }

        if (is_array($this->_htmlFormatter)) {
            $this->_htmlFormatter = \Yii::createObject($this->_htmlFormatter);
        }

        return $this;
    }
    /**
     * @return bool|string
     */
    public function getWebroot()
    {
        return \Yii::getAlias($this->_webroot);
    }

    /**
     * @param $path
     * @return $this
     */
    public function setWebroot($path)
    {
        $this->_webroot = $path;
        return $this;
    }

    /**
     * @param \yii\base\Application $app
     */
    public function bootstrap($app)
    {
        if ($app instanceof \yii\web\Application) {
            $app->view->on(View::EVENT_END_PAGE, function (Event $e) use ($app) {
                /**
                 * @var $view View
                 */
                $view = $e->sender;

                if ($this->enabled && $view instanceof View && $app->response->format == Response::FORMAT_HTML && !$app->request->isAjax && !$app->request->isPjax) {
                    \Yii::beginProfile('Compress assets');
                    $this->_processing($view);
                    \Yii::endProfile('Compress assets');
                }

                //TODO:: Think about it
                if ($this->enabled && $app->request->isPjax && $this->noIncludeJsFilesOnPjax) {
                    \Yii::$app->view->jsFiles = null;
                }
            });

            //Html compressing
            $app->response->on(\yii\web\Response::EVENT_BEFORE_SEND, function (\yii\base\Event $event) use ($app) {
                $response = $event->sender;

                if ($this->enabled && ($this->htmlFormatter instanceof IFormatter)  && $response->format == \yii\web\Response::FORMAT_HTML && !$app->request->isAjax && !$app->request->isPjax) {
                    if (!empty($response->data)) {
                        $response->data = $this->_processingHtml($response->data);
                    }

                    /*if (!empty($response->content)) {
                        $response->content = $this->_processingHtml($response->content);
                    }*/
                }
            });
        }
    }
    /**
     * @param View $view
     */
    protected function _processing(View $view)
    {
        //Компиляция файлов js в один.
        if ($view->jsFiles && $this->jsFileCompile) {
            \Yii::beginProfile('Compress js files');
            foreach ($view->jsFiles as $pos => $files) {
                if ($files) {
                    $view->jsFiles[$pos] = $this->_processingJsFiles($files);
                }
            }
            \Yii::endProfile('Compress js files');
        }

        //Компиляция js кода который встречается на странице
        if ($view->js && $this->jsCompress) {
            \Yii::beginProfile('Compress js code');
            foreach ($view->js as $pos => $parts) {
                if ($parts) {
                    $view->js[$pos] = $this->_processingJs($parts);
                }
            }
            \Yii::endProfile('Compress js code');
        }


        //Компиляция css файлов который встречается на странице
        if ($view->cssFiles && $this->cssFileCompile) {
            \Yii::beginProfile('Compress css files');

            $view->cssFiles = $this->_processingCssFiles($view->cssFiles);
            \Yii::endProfile('Compress css files');
        }

        //Компиляция css файлов который встречается на странице
        if ($view->css && $this->cssCompress) {
            \Yii::beginProfile('Compress css code');

            $view->css = $this->_processingCss($view->css);

            \Yii::endProfile('Compress css code');
        }
        //Компиляция css файлов который встречается на странице
        if ($view->css && $this->cssCompress) {
            \Yii::beginProfile('Compress css code');

            $view->css = $this->_processingCss($view->css);

            \Yii::endProfile('Compress css code');
        }


        //Перенос файлов css вниз страницы, где файлы js View::POS_END
        if ($view->cssFiles && $this->cssFileBottom) {
            \Yii::beginProfile('Moving css files bottom');

            if ($this->cssFileBottomLoadOnJs) {
                \Yii::beginProfile('load css on js');

                $cssFilesString = implode("", $view->cssFiles);
                $view->cssFiles = [];

                $script = Html::script(new JsExpression(<<<JS
        document.write('{$cssFilesString}');
JS
                ));

                if (ArrayHelper::getValue($view->jsFiles, View::POS_END)) {
                    $view->jsFiles[View::POS_END] = ArrayHelper::merge($view->jsFiles[View::POS_END], [$script]);

                } else {
                    $view->jsFiles[View::POS_END][] = $script;
                }


                \Yii::endProfile('load css on js');
            } else {
                if (ArrayHelper::getValue($view->jsFiles, View::POS_END)) {
                    $view->jsFiles[View::POS_END] = ArrayHelper::merge($view->cssFiles, $view->jsFiles[View::POS_END]);

                } else {
                    $view->jsFiles[View::POS_END] = $view->cssFiles;
                }

                $view->cssFiles = [];
            }

            \Yii::endProfile('Moving css files bottom');
        }
    }
    /**
     * @param array $files
     * @return array
     */
    protected function _processingJsFiles($files = [])
    {
        $fileName = md5(implode(array_keys($files)).$this->getSettingsHash()).'.js';
        $publicUrl = \Yii::$app->assetManager->baseUrl.'/js-compress/'.$fileName;
        //$publicUrl  = \Yii::getAlias('@web/assets/js-compress/' . $fileName);

        $rootDir = \Yii::$app->assetManager->basePath.'/js-compress';
        //$rootDir    = \Yii::getAlias('@webroot/assets/js-compress');
        $rootUrl = $rootDir.'/'.$fileName;

        if (file_exists($rootUrl)) {
            $resultFiles = [];

            if (!$this->jsFileRemouteCompile) {
                foreach ($files as $fileCode => $fileTag) {
                    if (!Url::isRelative($fileCode)) {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }


            $publicUrl = $publicUrl."?v=".filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::jsFile($publicUrl, $this->jsOptions);
            return $resultFiles;
        }

        //Reading the contents of the files
        try {
            $resultContent = [];
            $resultFiles = [];
            foreach ($files as $fileCode => $fileTag) {
                if (Url::isRelative($fileCode)) {
                    if ($pos = strpos($fileCode, "?")) {
                        $fileCode = substr($fileCode, 0, $pos);
                    }

                    $fileCode = $this->webroot.$fileCode;
                    $contentFile = $this->readLocalFile($fileCode);

                    /**\Yii::info("file: " . \Yii::getAlias(\Yii::$app->assetManager->basePath . $fileCode), self::class);*/
                    //$contentFile = $this->fileGetContents( Url::to(\Yii::getAlias($tmpFileCode), true) );
                    //$contentFile = $this->fileGetContents( \Yii::$app->assetManager->basePath . $fileCode );
                    $resultContent[] = trim($contentFile)."\n;";;
                } else {
                    if ($this->jsFileRemouteCompile) {
                        //Try to download the deleted file
                        $contentFile = $this->fileGetContents($fileCode);
                        $resultContent[] = trim($contentFile);
                    } else {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }
        } catch (\Exception $e) {
            \Yii::error(__METHOD__.": ".$e->getMessage(), static::class);
            return $files;
        }

        if ($resultContent) {
            $content = implode($resultContent, ";\n");
            if (!is_dir($rootDir)) {
                if (!FileHelper::createDirectory($rootDir, 0777)) {
                    return $files;
                }
            }

            if ($this->jsFileCompress) {
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


        if (file_exists($rootUrl)) {
            $publicUrl = $publicUrl."?v=".filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::jsFile($publicUrl, $this->jsOptions);
            return $resultFiles;
        } else {
            return $files;
        }
    }
    /**
     * @return string
     */
    public function getSettingsHash()
    {
        return serialize((array)$this);
    }
    /**
     * @param $filePath
     * @return string
     * @throws \Exception
     */
    public function readLocalFile($filePath)
    {
        if (YII_ENV == 'dev') {
            \Yii::info("Read local files '{$filePath}'");
        }

        if (!file_exists($filePath)) {
            throw new \Exception("Read file error '{$filePath}'");
        }

        $file = fopen($filePath, "r");
        if (!$file) {
            throw new \Exception("Unable to open file: '{$filePath}'");
        }
        return fread($file, filesize($filePath));
        fclose($file);
    }
    /**
     * Read file contents
     *
     * @param $file
     * @return string
     */
    public function fileGetContents($file)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('get')
            ->setUrl($file)
            ->addHeaders(['user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36'])
            ->setOptions([
                'timeout' => $this->readFileTimeout, // set timeout to 1 seconds for the case server is not responding
            ])
            ->send();

        if ($response->isOk) {
            return $response->content;
        }

        throw new \Exception("File get contents '{$file}' error: ".$response->content);
    }
    /**
     * @param $parts
     * @return array
     * @throws \Exception
     */
    protected function _processingJs($parts)
    {
        $result = [];

        if ($parts) {
            foreach ($parts as $key => $value) {
                $result[$key] = \JShrink\Minifier::minify($value, ['flaggedComments' => $this->jsCompressFlaggedComments]);
            }
        }

        return $result;
    }
    /**
     * @param array $files
     * @return array
     */
    protected function _processingCssFiles($files = [])
    {
        $fileName = md5(implode(array_keys($files)).$this->getSettingsHash()).'.css';
        $publicUrl = \Yii::$app->assetManager->baseUrl.'/css-compress/'.$fileName;
        //$publicUrl  = \Yii::getAlias('@web/assets/css-compress/' . $fileName);

        $rootDir = \Yii::$app->assetManager->basePath.'/css-compress';
        //$rootDir    = \Yii::getAlias('@webroot/assets/css-compress');
        $rootUrl = $rootDir.'/'.$fileName;

        if (file_exists($rootUrl)) {
            $resultFiles = [];

            if (!$this->cssFileRemouteCompile) {
                foreach ($files as $fileCode => $fileTag) {
                    if (!Url::isRelative($fileCode)) {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }

            $publicUrl = $publicUrl."?v=".filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::cssFile($publicUrl, $this->cssOptions);
            return $resultFiles;
        }

        //Reading the contents of the files
        try {
            $resultContent = [];
            $resultFiles = [];
            foreach ($files as $fileCode => $fileTag) {
                if (Url::isRelative($fileCode)) {
                    $fileCodeLocal = $fileCode;
                    if ($pos = strpos($fileCode, "?")) {
                        $fileCodeLocal = substr($fileCodeLocal, 0, $pos);
                    }

                    $fileCodeLocal = $this->webroot.$fileCodeLocal;
                    $contentTmp = trim($this->readLocalFile($fileCodeLocal));

                    //$contentTmp         = trim($this->fileGetContents( Url::to(\Yii::getAlias($fileCode), true) ));

                    $fileCodeTmp = explode("/", $fileCode);
                    unset($fileCodeTmp[count($fileCodeTmp) - 1]);
                    $prependRelativePath = implode("/", $fileCodeTmp)."/";

                    $contentTmp = \Minify_CSS::minify($contentTmp, [
                        "prependRelativePath" => $prependRelativePath,

                        'compress'         => true,
                        'removeCharsets'   => true,
                        'preserveComments' => true,
                    ]);

                    //$contentTmp = \CssMin::minify($contentTmp);

                    $resultContent[] = $contentTmp;
                } else {
                    if ($this->cssFileRemouteCompile) {
                        //Try to download the deleted file
                        $resultContent[] = trim($this->fileGetContents($fileCode));
                    } else {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }
        } catch (\Exception $e) {
            \Yii::error(__METHOD__.": ".$e->getMessage(), static::class);
            return $files;
        }

        if ($resultContent) {
            $content = implode($resultContent, "\n");
            if (!is_dir($rootDir)) {
                if (!FileHelper::createDirectory($rootDir, 0777)) {
                    return $files;
                }
            }

            if ($this->cssFileCompress) {
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


        if (file_exists($rootUrl)) {
            $publicUrl = $publicUrl."?v=".filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::cssFile($publicUrl, $this->cssOptions);
            return $resultFiles;
        } else {
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

        foreach ($css as $code => $value) {
            $newCss[] = preg_replace_callback('/<style\b[^>]*>(.*)<\/style>/is', function ($match) {
                return $match[1];
            }, $value);
        }

        $css = implode($newCss, "\n");
        $css = \CssMin::minify($css);
        return [md5($css) => "<style>".$css."</style>"];
    }

    /**
     * @param $html
     * @return string
     */
    protected function _processingHtml($html)
    {
        if ($this->htmlFormatter instanceof IFormatter) {
            $r = new \ReflectionClass($this->htmlFormatter);
            \Yii::beginProfile('Format html: ' . $r->getName());
                $result = $this->htmlFormatter->format($html);
            \Yii::endProfile('Format html: ' . $r->getName());
            return $result;
        }

        \Yii::warning("Html formatter error");

        return $html;
    }


    /**
     * @deprecated >= 1.4
     * @param $value
     * @return $this
     */
    public function setHtmlCompress($value)
    {
        return $this;
    }

    /**
     * @deprecated >= 1.4
     * @param $value
     * @return $this
     */
    public function getHtmlCompress()
    {
        return $this;
    }
    /**
     * @deprecated >= 1.4
     * @param $value array options for compressing output result
     *   * extra - use more compact algorithm
     *   * no-comments - cut all the html comments
     * @return $this
     */
    public function setHtmlCompressOptions($value)
    {
        return $this;
    }

    /**
     * @deprecated >= 1.4
     * @param $value
     * @return $this
     */
    public function getHtmlCompressOptions()
    {
        return $this;
    }
}
