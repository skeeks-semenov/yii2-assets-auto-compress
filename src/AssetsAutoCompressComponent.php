<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\yii2\assetsAuto;

use MatthiasMullie\Minify;
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
     * Enables the compilation of files in groups rather than in a single file. Works only when the $cssFileCompile option is enabled
     * @var bool
     */
    public $cssFileCompileByGroups = false;

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
     * Enables the compilation of files in groups rather than in a single file. Works only when the $jsFileCompile option is enabled
     * @var bool
     */
    public $jsFileCompileByGroups = false;

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
     * Do not connect the js files when all pjax requests when enabled jsFileCompile
     * @var bool
     */
    public $noIncludeJsFilesOnPjax = true;

    /**
     * Do not connect the css files when all pjax requests when enabled cssFileCompile
     * @var bool
     */
    public $noIncludeCssFilesOnPjax = true;
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
                if ($this->enabled && $app->request->isPjax) {

                    if ($this->noIncludeJsFilesOnPjax && $this->jsFileCompile) {
                        \Yii::$app->view->jsFiles = null;
                    }

                    if ($this->noIncludeCssFilesOnPjax && $this->cssFileCompile) {
                        \Yii::$app->view->cssFiles = null;
                    }
                }
            });

            //Html compressing
            $app->response->on(\yii\web\Response::EVENT_BEFORE_SEND, function (\yii\base\Event $event) use ($app) {
                $response = $event->sender;

                if ($this->enabled && ($this->htmlFormatter instanceof IFormatter) && $response->format == \yii\web\Response::FORMAT_HTML && !$app->request->isAjax && !$app->request->isPjax) {
                    if (!empty($response->data)) {
                        $response->data = $this->_processingHtml($response->data);
                    }
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
        //echo "<pre><code>" . print_r($view->jsFiles, true);die;
        if ($view->jsFiles && $this->jsFileCompile) {
            \Yii::beginProfile('Compress js files');
            foreach ($view->jsFiles as $pos => $files) {
                if ($files) {
                    if ($this->jsFileCompileByGroups) {
                        $view->jsFiles[$pos] = $this->_processAndGroupJsFiles($files);
                    } else {
                        $view->jsFiles[$pos] = $this->_processingJsFiles($files);
                    }
                }
            }
            \Yii::endProfile('Compress js files');
        }
        //echo "<pre><code>" . print_r($view->jsFiles, true);die;

        //Compiling js code that is found in the html code of the page.
        if ($view->js && $this->jsCompress) {
            \Yii::beginProfile('Compress js code');
            foreach ($view->js as $pos => $parts) {
                if ($parts) {
                    $view->js[$pos] = $this->_processingJs($parts);
                }
            }
            \Yii::endProfile('Compress js code');
        }


        //Compiling css files
        if ($view->cssFiles && $this->cssFileCompile) {
            \Yii::beginProfile('Compress css files');
            if ($this->cssFileCompileByGroups) {
                $view->cssFiles = $this->_processAndGroupCssFiles($view->cssFiles);
            } else {
                $view->cssFiles = $this->_processingCssFiles($view->cssFiles);
            }
            \Yii::endProfile('Compress css files');
        }

        //Compiling css code that is found in the html code of the page.
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
     */
    protected function _processAndGroupJsFiles($files = [])
    {
        if (!$files) {
            return [];
        }

        $result = [];
        $groupedFiles = $this->_getGroupedFiles($files);
        foreach ($groupedFiles as $files) {
            $resultGroup = $this->_processingJsFiles($files);
            $result = ArrayHelper::merge($result, $resultGroup);
        }

        return $result;
        echo "<pre><code>".print_r($result, true);
        die;

    }

    public function _getGroupedFiles($files)
    {
        $result = [];

        $lastKey = null;
        $tmpData = [];
        $counter = 0;
        foreach ($files as $fileCode => $fileTag) {
            list($one, $two, $key) = explode("/", $fileCode);

            $counter++;

            if ($key != $lastKey && $counter > 1) {
                $result[] = $tmpData;
                $tmpData = [];
                $tmpData[$fileCode] = $fileTag;
            } else {
                $tmpData[$fileCode] = $fileTag;
            }

            $lastKey = $key;
        }

        return $result;
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
                        $fileCode = $this->getFileCode($fileCode);
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
                        $fileCode = $this->getFileCode($fileCode);
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
            $content = implode(";\n", $resultContent);
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

            \Yii::info("Create js file: {$publicUrl} from files: {$filesString} to use {$useFunction} on page '{$page}'", static::class);

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
        $filesSize = filesize($filePath);
        if ($filesSize) {
            return fread($file, $filesSize);
        }
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
     */
    protected function _processAndGroupCssFiles($files = [])
    {
        if (!$files) {
            return [];
        }

        $result = [];
        $groupedFiles = $this->_getGroupedFiles($files);
        foreach ($groupedFiles as $files) {
            $resultGroup = $this->_processingCssFiles($files);
            $result = ArrayHelper::merge($result, $resultGroup);
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
                        $fileCode = $this->getFileCode($fileCode);
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
                    $fileCode = $this->getFileCode($fileCode);
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

                    $contentTmp = $this->fixRelativeInCSS($contentTmp, $this->webroot . $prependRelativePath, $rootDir);

                    $contentTmp = \Minify_CSS::minify($contentTmp, [
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
            $content = implode("\n", $resultContent);
            if (!is_dir($rootDir)) {
                if (!FileHelper::createDirectory($rootDir, 0777)) {
                    return $files;
                }
            }

            if ($this->cssFileCompress) {
                $content = (new Minify\CSS($content))->minify();
                //$content = \CssMin::minify($content);
            }

            $page = \Yii::$app->request->absoluteUrl;
            $useFunction = function_exists('curl_init') ? 'curl extension' : 'php file_get_contents';
            $filesString = implode(', ', array_keys($files));

            \Yii::info("Create css file: {$publicUrl} from files: {$filesString} to use {$useFunction} on page '{$page}'", static::class);


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

        $css = implode("\n", $newCss);
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
            \Yii::beginProfile('Format html: '.$r->getName());
            $result = $this->htmlFormatter->format($html);
            \Yii::endProfile('Format html: '.$r->getName());
            return $result;
        }

        \Yii::warning("Html formatter error");

        return $html;
    }

    /**
     * Fix for Yii version 2.0.39 and higher if the project has a non-empty baseUrl
     *
     * @param  string $fileCode Path to file
     *
     * @return string
     * @link   https://github.com/yiisoft/yii2/issues/18414
     */
    protected function getFileCode($fileCode)
    {
        return !empty(\Yii::$app->request->getBaseUrl()) ? str_replace(\Yii::$app->request->getBaseUrl(), '', $fileCode) : $fileCode;
    }

    /**
     * CSS fix where relative path starts with dots
     *
     * @param  string $code     CSS code
     * @param  string $old_path Full path to the source file
     * @param  string $new_path Full path to the final file
     *
     * @return string CSS with corrected paths
     */
    protected function fixRelativeInCSS($code, $old_path, $new_path)
    {
        $list = [];

        // Find patterns
        $pattern = '/@import\\s+([^)]*)/Us';
        preg_match_all($pattern, $code, $list1);
        if (isset($list1[1]) && !empty($list1[1])) foreach ($list1[1] as $item) { $list[] = trim($item, '\'" '); }

        $pattern = '/url\\(([^)]*)\\)/Us';
        preg_match_all($pattern, $code, $list2);
        if (isset($list2[1]) && !empty($list2[1])) foreach ($list2[1] as $item) { $list[] = trim($item, '\'" '); }

        // We leave only paths with points
        foreach ($list as $key => $value) { if (!preg_match('/^[\.]*\//', $value)) unset($list[$key]); }

        // Determining where paths start to differ
        $old_path = explode('/', rtrim(str_replace('\\', '/', $old_path), '/'));
        $new_path = explode('/', rtrim(str_replace('\\', '/', $new_path), '/'));

        $f = count($old_path) > count($new_path) ? $old_path : $new_path;
        $eq = 0;

        /**
         * Example:
         * $new_path = /var/www/site/web/assets/css-compress
         * $old_path = /var/www/site/web/css
         *
         * $eq = 4
         */
        foreach ($f as $key => $value) { if (isset($new_path[$key]) && isset($old_path[$key]) && $new_path[$key] === $old_path[$key]) $eq = $key; }

        // Correcting relative paths for the new location
        foreach ($list as $item)
        {
            $e = explode('/', $item);
            $pos = count($old_path) - 1;

            /**
             * Example 1:
             * $item = ../images/image1.png
             *
             * 1 iteration: $pos = 5 or 'css'
             * 2 iteration: $pos = 4 or 'web'
             *
             * Example 2:
             * $item = ../../images/image1.png
             *
             * 1 iteration: $pos = 5 or 'css'
             * 2 iteration: $pos = 4 or 'web'
             * 3 iteration: $pos = 3 or 'site'
             *
             * Example 3:
             * $item = images/image1.png
             *
             * $pos = 5 or 'css'
             */
            foreach ($e as $key => $i)
            {
                if ($i === '..')
                {
                    $pos--;
                    unset($e[$key]);
                }
            }

            $n = count($new_path) - 1;

            if ($pos <= $eq)
            {
                /**
                 * Example 1:
                 * for /var/www/site/web/css url = ../images/image1.png
                 *
                 * $n = 7 - 1 - 4 = 2
                 *
                 * then for /var/www/site/web/assets/css-compress $new_item = ../../images/image1.png
                 *
                 * Example 2:
                 * for /var/www/site/web/css url = ../../images/image1.png
                 *
                 * $n = 7 - 1 - 3 = 3
                 *
                 * then for /var/www/site/web/assets/css-compress $new_item = ../../../images/image1.png
                 */
                $n -= $pos;
                $prefix = str_repeat('../', $n);
            }
            else
            {
                /**
                 * Example:
                 * for /var/www/site/web/css url = images/image1.png
                 *
                 * $n = 7 - 1 - 4 = 2
                 *
                 * then for /var/www/site/web/assets/css-compress $new_item = ../../css/images/image1.png
                 */
                $n -= $eq;
                $prefix = str_repeat('../', $n) . end($old_path);
            }

            $new_item = $prefix . implode('/', $e);

            $code = str_replace($item, $new_item, $code);
        }

        return $code;
    }

    /**
     * @param $value
     * @return $this
     * @deprecated >= 1.4
     */
    public function setHtmlCompress($value)
    {
        return $this;
    }

    /**
     * @param $value
     * @return $this
     * @deprecated >= 1.4
     */
    public function getHtmlCompress()
    {
        return $this;
    }
    /**
     * @param $value array options for compressing output result
     *   * extra - use more compact algorithm
     *   * no-comments - cut all the html comments
     * @return $this
     * @deprecated >= 1.4
     */
    public function setHtmlCompressOptions($value)
    {
        return $this;
    }

    /**
     * @param $value
     * @return $this
     * @deprecated >= 1.4
     */
    public function getHtmlCompressOptions()
    {
        return $this;
    }
}
